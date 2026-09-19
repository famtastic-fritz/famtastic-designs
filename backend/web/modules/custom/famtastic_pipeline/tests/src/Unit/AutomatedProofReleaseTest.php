<?php
declare(strict_types=1);
namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\famtastic_pipeline\Entity\ProofCampaign;
use Drupal\famtastic_pipeline\Entity\ProofVariant;
use Drupal\famtastic_pipeline\Service\AutomatedProofPolicy;
use Drupal\famtastic_pipeline\Service\AutomatedProofRelease;
use Drupal\famtastic_pipeline\Service\CustomerPortalService;
use Drupal\famtastic_pipeline\Service\OperationalLedger;
use Drupal\sqlite\Driver\Database\sqlite\Connection;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

require_once dirname(__DIR__, 3) . '/famtastic_pipeline.install';

final class AutomatedProofReleaseTest extends UnitTestCase {
  private Connection $db;
  private CustomerPortalService $portal;
  private AutomatedProofRelease $release;
  private string $fixtureRoot;
  private array $research;
  private array $evidence;
  private array $notice;

  protected function setUp(): void {
    parent::setUp();
    $o = ['database' => ':memory:', 'prefix' => '', 'driver' => 'sqlite', 'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite'];
    $this->db = new Connection(Connection::open($o), $o);
    $schemas = _famtastic_pipeline_customer_portal_schema() + _famtastic_pipeline_lifecycle_schema() + _famtastic_pipeline_automation_schema();
    foreach (['famtastic_project_request', 'famtastic_customer', 'famtastic_notification_outbox', 'famtastic_event'] as $table) $this->db->schema()->createTable($table, $schemas[$table]);
    $this->db->schema()->createTable('famtastic_website_proof_research_snapshot', _famtastic_pipeline_website_proof_research_snapshot_schema());
    $this->db->insert('famtastic_customer')->fields(['id' => 901, 'uid' => 901, 'public_id' => 'fixture-customer', 'email' => 'fixture@example.test', 'display_name' => 'Fixture', 'verified_at' => 1789700000, 'created' => 1789700000, 'changed' => 1789700000])->execute();
    $this->db->insert('famtastic_project_request')->fields(['id' => 991, 'public_id' => 'fixture-request', 'customer_id' => 901, 'organization_id' => 902, 'prospect_id' => 903, 'project_name' => 'Fixture', 'project_type' => 'new_website', 'status' => 'submitted', 'proof_review_status' => 'owner_review', 'proof_campaign_id' => 995, 'intake_data' => '{}', 'created' => 1789700000, 'changed' => 1789700000])->execute();
    $clock = $this->createMock(TimeInterface::class);
    $clock->method('getCurrentTime')->willReturn(1789700000);
    $clock->method('getRequestTime')->willReturn(1789700000);
    $this->fixtureRoot = sys_get_temp_dir() . '/famtastic-qa-fixture-' . bin2hex(random_bytes(6));
    $variants = [];
    foreach (['a', 'b', 'c'] as $direction) {
      $path = $this->fixtureRoot . '/web/proofs/fixture-campaign/' . $direction . '/index.html';
      mkdir(dirname($path), 0700, TRUE);
      file_put_contents($path, '<!doctype html><title>Fixture ' . $direction . '</title>');
      $variant = $this->createMock(ProofVariant::class);
      $variant->method('get')->willReturnCallback(fn($field) => (object) ['value' => ['direction_id' => $direction, 'artifact_path' => 'web/proofs/fixture-campaign/' . $direction . '/index.html', 'design_dna' => '{}'][$field] ?? NULL]);
      $variants[] = $variant;
    }
    $campaign = $this->createMock(ProofCampaign::class);
    $campaign->method('get')->willReturnCallback(fn($field) => (object) ['value' => ['generation_status' => 'ready', 'campaign_id' => 'fixture-campaign'][$field] ?? NULL, 'target_id' => $field === 'prospect_id' ? 903 : NULL]);
    $campaignStorage = $this->createMock(EntityStorageInterface::class);
    $campaignStorage->method('load')->willReturn($campaign);
    $query = $this->createMock(QueryInterface::class);
    foreach (['accessCheck', 'condition', 'sort'] as $method) $query->method($method)->willReturnSelf();
    $query->method('execute')->willReturn([1, 2, 3]);
    $variantStorage = $this->createMock(EntityStorageInterface::class);
    $variantStorage->method('getQuery')->willReturn($query);
    $variantStorage->method('loadMultiple')->willReturn($variants);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->willReturnCallback(fn($type) => $type === 'proof_campaign' ? $campaignStorage : $variantStorage);
    $this->portal = (new \ReflectionClass(CustomerPortalService::class))->newInstanceWithoutConstructor();
    foreach (['database' => $this->db, 'time' => $clock] as $property => $value) (new \ReflectionProperty($this->portal, $property))->setValue($this->portal, $value);
    $this->release = new AutomatedProofRelease($this->db, $entities, $clock, new OperationalLedger($this->db, $clock), $this->portal);
    $container = new ContainerBuilder();
    $container->setParameter('app.root', $this->fixtureRoot . '/web');
    $container->set('famtastic_pipeline.automated_proof_release', $this->release);
    \Drupal::setContainer($container);
    $this->research = ['overview' => 'Fixture research', 'direction_rationale' => ['a' => 'First', 'b' => 'Second', 'c' => 'Third'], 'sources' => ['https://example.test/source'], 'researched_at' => '2026-09-18'];
    $context = $this->portal->websiteRequestAutomatedProofQaContext(991, $this->research);
    $this->evidence = $context + ['producer' => 'automation:builder', 'reviewer' => 'automation:independent-reviewer', 'exceptions' => [], 'scope_in_bounds' => TRUE];
    foreach (AutomatedProofPolicy::CHECKS as $check) $this->evidence['checks'][$check] = ['passed' => TRUE, 'evidence_ref' => 'evidence:fixture/' . $check, 'evidence_sha256' => hash('sha256', $check)];
    $this->notice = ['notification_key' => 'website-request:991:proofs:995:qa-v1', 'recipient' => 'fixture@example.test', 'subject' => 'Personal fixture', 'body' => "A personal message. Always FAMtastic, Shay\n\nOpen your project:\nhttps://famtasticdesigns.com/portal/?section=projects&request=fixture-request"];
  }

  protected function tearDown(): void {
    foreach (['a', 'b', 'c'] as $d) { unlink($this->fixtureRoot . '/web/proofs/fixture-campaign/' . $d . '/index.html'); rmdir($this->fixtureRoot . '/web/proofs/fixture-campaign/' . $d); }
    foreach (['/web/proofs/fixture-campaign', '/web/proofs', '/web', ''] as $d) rmdir($this->fixtureRoot . $d);
    parent::tearDown();
  }

  private function release(): array { return $this->portal->releaseWebsiteRequestProofAfterQa(991, $this->research, $this->evidence, 'automation:independent-reviewer', $this->notice); }
  private function rowCount(string $table): int { return (int) $this->db->select($table, 't')->countQuery()->execute()->fetchField(); }

  public function testReleaseIsAtomicPersonalizedAndIdempotentWithoutHumanImpersonation(): void {
    $first = $this->release();
    self::assertFalse($first['email_sent_by_this_operation']);
    self::assertSame('customer_ready', $first['proof_review_status']);
    self::assertTrue($this->release()['duplicate']);
    self::assertSame(1, $this->rowCount('famtastic_notification_outbox'));
    self::assertSame(1, $this->rowCount('famtastic_event'));
    $r = $this->db->select('famtastic_project_request', 'r')->fields('r')->execute()->fetchAssoc();
    self::assertNull($r['proof_approved_by_uid']);
    $m = $this->db->select('famtastic_notification_outbox', 'n')->fields('n')->execute()->fetchAssoc();
    self::assertSame($this->notice['body'], $m['body']);
    self::assertSame('customer_proof_ready', $m['template_id']);
    self::assertSame(4, (int) $m['template_version']);
    self::assertSame(1, (int) $m['max_attempts']);
    self::assertSame(0, (int) $m['attempts']);
  }

  #[DataProvider('negativeCases')]
  public function testRefusesUnsafeReview(string $case): void {
    match ($case) {
      'self_review' => $this->evidence['producer'] = $this->evidence['reviewer'],
      'foreign_customer' => $this->evidence['customer_id'] = 999,
      'foreign_campaign' => $this->evidence['campaign_id'] = 999,
      'foreign_request' => $this->evidence['request_id'] = 999,
      'stale_policy' => $this->evidence['policy_version'] = 'old',
      'missing_evidence' => $this->evidence['checks']['mobile'] = [],
      'failed_qa' => $this->evidence['checks']['links']['passed'] = FALSE,
      'scope_exception' => $this->evidence['exceptions'] = ['merchant_permission'],
      'wrong_recipient' => $this->notice['recipient'] = 'other@example.test',
      'wrong_key' => $this->notice['notification_key'] = 'shared-key',
      'no_portal_link' => $this->notice['body'] = 'No destination',
      'admin_link' => $this->notice['body'] = 'https://famtasticdesigns.com/web/admin/famtastic/website-request/991/proof/a',
      'api_link' => $this->notice['body'] = 'https://famtasticdesigns.com/web/api/customer/website-requests/fixture-request/proofs/a',
      'foreign_portal_link' => $this->notice['body'] = 'https://famtasticdesigns.com/portal/?section=projects&request=foreign-request',
      'extra_link' => $this->notice['body'] .= "\nhttps://example.test/unrelated",
      'changed_artifact' => file_put_contents($this->fixtureRoot . '/web/proofs/fixture-campaign/a/index.html', 'changed'),
      'changed_research' => $this->research['overview'] = 'changed',
    };
    try { $this->release(); self::fail('Unsafe QA must not release.'); }
    catch (\InvalidArgumentException|\RuntimeException $e) { self::assertNotSame('', $e->getMessage()); }
    self::assertSame(0, $this->rowCount('famtastic_event'));
    self::assertSame(0, $this->rowCount('famtastic_notification_outbox'));
    self::assertSame('owner_review', $this->db->select('famtastic_project_request', 'r')->fields('r', ['proof_review_status'])->execute()->fetchField());
  }
  public static function negativeCases(): iterable { foreach (['self_review','foreign_customer','foreign_campaign','foreign_request','stale_policy','missing_evidence','failed_qa','scope_exception','wrong_recipient','wrong_key','no_portal_link','admin_link','api_link','foreign_portal_link','extra_link','changed_artifact','changed_research'] as $c) yield $c => [$c]; }

  public function testHistoricalExactRetryPreservesOriginalTemplateAndReceipt(): void {
    $this->release();
    // Fixture representing a pre-fix release. No production history is edited.
    $this->notice['body'] = "Legacy personal body\nhttps://famtasticdesigns.com/web/api/customer/website-requests/fixture-request/proofs/a";
    $payload = json_decode($this->db->select('famtastic_event', 'e')->fields('e', ['payload'])->execute()->fetchField(), TRUE);
    $payload['notification_sha256'] = hash('sha256', json_encode($this->notice, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    $this->db->update('famtastic_event')->fields(['payload' => json_encode($payload)])->execute();
    $this->db->update('famtastic_notification_outbox')->fields(['template_id' => 'standard', 'template_version' => 2, 'body' => $this->notice['body'], 'status' => 'sent', 'provider_message_id' => '<original@fixture.invalid>'])->execute();
    $before = $this->db->select('famtastic_notification_outbox', 'n')->fields('n')->execute()->fetchAssoc();
    self::assertTrue($this->release()['duplicate']);
    self::assertSame($before, $this->db->select('famtastic_notification_outbox', 'n')->fields('n')->execute()->fetchAssoc());
  }

  public function testLateOutboxFailureRollsBackRevealResearchAndDecision(): void {
    $this->db->query("CREATE TRIGGER fail_notice BEFORE INSERT ON famtastic_notification_outbox BEGIN SELECT RAISE(ABORT, 'fixture late failure'); END", [], ['allow_delimiter_in_query' => TRUE]);
    try { $this->release(); self::fail('Injected failure expected.'); } catch (\Exception $e) { self::assertStringContainsString('fixture late failure', $e->getMessage()); }
    self::assertSame('owner_review', $this->db->select('famtastic_project_request', 'r')->fields('r', ['proof_review_status'])->execute()->fetchField());
    self::assertSame(0, $this->rowCount('famtastic_website_proof_research_snapshot'));
    self::assertSame(0, $this->rowCount('famtastic_event'));
  }

  public function testChangedRetryNeverOverwritesPersonalMail(): void {
    $this->release();
    $this->notice['body'] = 'Changed after release';
    $this->expectException(\RuntimeException::class);
    $this->release();
  }
}
