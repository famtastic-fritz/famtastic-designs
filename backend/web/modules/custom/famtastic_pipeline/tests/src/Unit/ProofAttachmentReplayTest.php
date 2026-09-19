<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\famtastic_pipeline\Entity\ProofCampaign;
use Drupal\famtastic_pipeline\Entity\ProofVariant;
use Drupal\famtastic_pipeline\Service\CustomerPortalService;
use Drupal\sqlite\Driver\Database\sqlite\Connection;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

require_once dirname(__DIR__, 3) . '/famtastic_pipeline.install';

/** Exercises the actual attachment method against isolated, in-memory records. */
final class ProofAttachmentReplayTest extends UnitTestCase {

  private const REQUEST = 991;
  private const CAMPAIGN = 995;
  private const BEFORE = 1789700000;
  private const NOW = 1789703600;

  private Connection $db;
  private CustomerPortalService $portal;
  private ?\Closure $beforeAttachmentUpdate = NULL;

  protected function setUp(): void {
    parent::setUp();
    $options = ['database' => ':memory:', 'prefix' => '', 'driver' => 'sqlite', 'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite'];
    $this->db = new Connection(Connection::open($options), $options);
    $schemas = _famtastic_pipeline_customer_portal_schema() + _famtastic_pipeline_lifecycle_schema();
    foreach (['famtastic_project_request', 'famtastic_notification_outbox', 'famtastic_portal_activity'] as $table) {
      $this->db->schema()->createTable($table, $schemas[$table]);
    }
    $clock = $this->createMock(TimeInterface::class);
    $clock->method('getCurrentTime')->willReturn(self::NOW);
    $clock->method('getRequestTime')->willReturnCallback(function (): int {
      // Deterministically interleave another writer after the method's read,
      // before its conditional UPDATE. This does not claim MySQL concurrency.
      if ($this->beforeAttachmentUpdate !== NULL) {
        $callback = $this->beforeAttachmentUpdate;
        $this->beforeAttachmentUpdate = NULL;
        $callback();
      }
      return self::NOW;
    });
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->expects(self::never())->method('getStorage');
    $this->portal = (new \ReflectionClass(CustomerPortalService::class))->newInstanceWithoutConstructor();
    foreach ([
      'database' => $this->db,
      'time' => $clock,
      'entities' => $entities,
      'configFactory' => $this->getConfigFactoryStub([
        'famtastic_pipeline.settings' => ['notification_to_email' => 'operator@example.test'],
      ]),
    ] as $property => $value) {
      (new \ReflectionProperty($this->portal, $property))->setValue($this->portal, $value);
    }
  }

  private function seedRequest(string $state, ?int $campaign = self::CAMPAIGN, bool $advanced = TRUE): void {
    $this->db->insert('famtastic_project_request')->fields([
      'id' => self::REQUEST, 'public_id' => 'offline-replay-fixture',
      'customer_id' => 901, 'organization_id' => 902, 'prospect_id' => 903,
      'project_name' => 'Offline replay fixture', 'business_name' => 'Fixture only',
      'project_type' => 'new_website', 'status' => 'submitted',
      'proof_campaign_id' => $campaign, 'proof_review_status' => $state,
      'proof_approved_by_uid' => NULL,
      'proof_approved_at' => $advanced ? self::BEFORE + 10 : NULL,
      'proof_notified_at' => $advanced ? self::BEFORE + 20 : NULL,
      'selected_proof_direction' => $advanced ? 'c' : '',
      'selected_proof_at' => $advanced ? self::BEFORE + 30 : NULL,
      'proof_share_enabled' => $advanced ? 1 : 0,
      'proof_share_version' => 7,
      'staging_status' => $advanced ? 'queued' : 'not_started',
      'staging_receipt_json' => '{"fixture":"preserve exactly"}',
      'intake_data' => '{"proof_revision_request":{"notes":"Retain customer feedback"},"fixture":true}',
      'created' => self::BEFORE - 100, 'changed' => self::BEFORE,
    ])->execute();
  }

  private function seedExistingHistory(): void {
    foreach (['owner-proof-review:995:3' => 'operational', 'proofs:995:qa-v1' => 'transactional'] as $suffix => $category) {
      $this->db->insert('famtastic_notification_outbox')->fields([
        'notification_key' => 'website-request:991:' . $suffix,
        'category' => $category, 'recipient' => 'fixture@example.test',
        'subject' => 'Preserve subject', 'body' => 'Preserve personal copy',
        'template_id' => 'standard', 'template_version' => 2,
        'status' => 'queued', 'attempts' => 0, 'max_attempts' => 1,
        'available_at' => self::BEFORE, 'created' => self::BEFORE, 'changed' => self::BEFORE,
      ])->execute();
    }
    $this->db->insert('famtastic_portal_activity')->fields([
      'organization_id' => 902, 'event_type' => 'fixture.original',
      'summary' => 'Preserve existing activity', 'metadata' => '{"fixture":true}',
      'created' => self::BEFORE,
    ])->execute();
  }

  private function campaign(int $id = self::CAMPAIGN, int $prospect = 903): ProofCampaign {
    $campaign = $this->createMock(ProofCampaign::class);
    $campaign->method('id')->willReturn($id);
    $campaign->method('get')->willReturnCallback(static fn(string $field): object => (object) [
      'value' => $field === 'generation_status' ? 'ready' : NULL,
      'target_id' => $field === 'prospect_id' ? $prospect : NULL,
    ]);
    return $campaign;
  }

  private function variants(bool $showcase = FALSE): array {
    $variants = [];
    foreach ($showcase ? ['f', 'b', 'd', 'a', 'e', 'c'] : ['c', 'a', 'b'] as $direction) {
      $variant = $this->createMock(ProofVariant::class);
      $variant->method('get')->willReturnCallback(static fn(string $field): object => (object) [
        'value' => $field === 'direction_id' ? $direction : NULL,
      ]);
      $variants[] = $variant;
    }
    return $variants;
  }

  /** Capture every column and every row, not just status or side-effect counts. */
  private function snapshot(): array {
    $snapshot = [];
    foreach (['famtastic_project_request', 'famtastic_notification_outbox', 'famtastic_portal_activity'] as $table) {
      $snapshot[$table] = [];
      $result = $this->db->select($table, 't')->fields('t')->orderBy('id')->execute();
      while ($row = $result->fetchAssoc()) $snapshot[$table][] = $row;
    }
    return $snapshot;
  }

  public static function advancedStates(): iterable {
    foreach (['customer_ready', 'notified', 'selected', 'revision_requested'] as $state) yield $state => [$state];
  }

  #[DataProvider('advancedStates')]
  public function testSameCampaignReplayPreservesEveryFieldAndHasNoSideEffects(string $state): void {
    $this->seedRequest($state);
    // A linked project must not be reset or even loaded on an advanced replay.
    $this->db->update('famtastic_project_request')->fields(['project_id' => 909, 'commerce_order_id' => 908])->condition('id', self::REQUEST)->execute();
    $this->seedExistingHistory();
    $before = $this->snapshot();
    foreach ([FALSE, TRUE, FALSE] as $showcase) {
      $this->portal->attachWebsiteRequestProof(self::REQUEST, $this->campaign(), $this->variants($showcase));
      self::assertSame($before, $this->snapshot(), 'Retry must not change request fields, timestamps, mail, activity, or project state.');
    }
  }

  public static function freshBindings(): iterable {
    yield 'unbound null' => [NULL];
    yield 'unbound zero' => [0];
    yield 'queued same campaign' => [self::CAMPAIGN];
  }

  #[DataProvider('freshBindings')]
  public function testFreshAttachmentStillEntersOwnerReview(?int $boundCampaign): void {
    $this->seedRequest('queued', $boundCampaign, FALSE);
    $before = $this->snapshot()['famtastic_project_request'][0];
    $this->portal->attachWebsiteRequestProof(self::REQUEST, $this->campaign(), $this->variants());
    $after = $this->snapshot();
    $expected = $before;
    $expected['proof_campaign_id'] = self::CAMPAIGN;
    $expected['proof_review_status'] = 'owner_review';
    $expected['changed'] = self::NOW;
    self::assertEquals($expected, $after['famtastic_project_request'][0]);
    self::assertCount(1, $after['famtastic_notification_outbox']);
    self::assertSame('operational', $after['famtastic_notification_outbox'][0]['category']);
    self::assertSame('operator@example.test', $after['famtastic_notification_outbox'][0]['recipient']);
    self::assertSame('website-request:991:owner-proof-review:995:3', $after['famtastic_notification_outbox'][0]['notification_key']);
    self::assertCount(1, $after['famtastic_portal_activity']);
    self::assertSame('website_request.proofs_owner_review', $after['famtastic_portal_activity'][0]['event_type']);
  }

  public function testDifferentBoundCampaignIsRejectedWithoutMutation(): void {
    $this->seedRequest('customer_ready');
    $this->seedExistingHistory();
    $this->assertRejectedWithoutMutation($this->campaign(996));
  }

  public function testWrongProspectIsRejectedEvenOnAnOtherwiseNoOpReplay(): void {
    $this->seedRequest('selected');
    $this->seedExistingHistory();
    $this->assertRejectedWithoutMutation($this->campaign(self::CAMPAIGN, 999));
  }

  private function assertRejectedWithoutMutation(ProofCampaign $campaign): void {
    $before = $this->snapshot();
    try {
      $this->portal->attachWebsiteRequestProof(self::REQUEST, $campaign, $this->variants());
      self::fail('A foreign campaign must be rejected.');
    }
    catch (\RuntimeException $exception) {
      self::assertStringContainsString('campaign', $exception->getMessage());
    }
    self::assertSame($before, $this->snapshot());
  }

  #[DataProvider('advancedStates')]
  public function testConcurrentAdvancementWinsTheAttachmentCompareAndSet(string $state): void {
    $this->seedRequest('owner_review');
    $this->seedExistingHistory();
    $advanced = NULL;
    $this->beforeAttachmentUpdate = function () use ($state, &$advanced): void {
      $this->db->update('famtastic_project_request')->fields([
        'proof_review_status' => $state, 'changed' => self::NOW + 1,
        'proof_approved_at' => self::NOW + 1, 'proof_approved_by_uid' => NULL,
        'intake_data' => '{"concurrent_writer":"preserve"}',
      ])->condition('id', self::REQUEST)->execute();
      $advanced = $this->snapshot();
    };
    $this->portal->attachWebsiteRequestProof(self::REQUEST, $this->campaign(), $this->variants(TRUE));
    self::assertNotNull($advanced, 'The race must actually interleave after the initial read.');
    self::assertSame($advanced, $this->snapshot(), 'CAS loss must not undo release/selection or queue mail/activity.');
  }

  public function testConcurrentCampaignReplacementIsNotSilentlyOverwritten(): void {
    $this->seedRequest('owner_review');
    $this->seedExistingHistory();
    $replaced = NULL;
    $this->beforeAttachmentUpdate = function () use (&$replaced): void {
      $this->db->update('famtastic_project_request')->fields([
        'proof_campaign_id' => 996, 'changed' => self::NOW + 1,
      ])->condition('id', self::REQUEST)->execute();
      $replaced = $this->snapshot();
    };
    try {
      $this->portal->attachWebsiteRequestProof(self::REQUEST, $this->campaign(), $this->variants());
      self::fail('Losing the campaign CAS must not overwrite the new binding.');
    }
    catch (\RuntimeException $exception) {
      self::assertStringContainsString('concurrently', $exception->getMessage());
    }
    self::assertNotNull($replaced);
    self::assertSame($replaced, $this->snapshot());
  }

}
