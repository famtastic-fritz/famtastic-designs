<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\famtastic_pipeline\Service\CustomerPortalService;
use Drupal\sqlite\Driver\Database\sqlite\Connection;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

require_once dirname(__DIR__, 3) . '/famtastic_pipeline.install';

/** Actual public update writer and database; no customer or transport access. */
final class WebsiteRequestAuditPreservationTest extends UnitTestCase {
  private Connection $db;
  private CustomerPortalService $portal;
  private const KEYS = ['proof_design_reset_requests', 'proof_edit_round_requests', 'proof_revision_request', 'staff_assisted_brief', 'selected_site_revision_requests'];

  protected function setUp(): void {
    parent::setUp();
    $options = ['database' => ':memory:', 'prefix' => '', 'driver' => 'sqlite', 'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite'];
    $this->db = new Connection(Connection::open($options), $options);
    $schemas = _famtastic_pipeline_customer_portal_schema() + _famtastic_pipeline_automation_schema() + _famtastic_pipeline_lifecycle_schema();
    foreach (['famtastic_project_request', 'famtastic_membership', 'famtastic_private_offer', 'famtastic_request_asset', 'famtastic_job', 'famtastic_event', 'famtastic_notification_outbox'] as $table) {
      $this->db->schema()->createTable($table, $schemas[$table]);
    }
    $this->db->insert('famtastic_membership')->fields(['organization_id' => 902, 'customer_id' => 901, 'role' => 'owner', 'status' => 'active', 'created' => 1789700000])->execute();
    $this->db->insert('famtastic_project_request')->fields([
      'id' => 991, 'public_id' => 'fixture-audit-request', 'customer_id' => 901, 'organization_id' => 902,
      'project_name' => 'Synthetic reunion', 'project_type' => 'new_website', 'status' => 'submitted',
      'proof_review_status' => 'not_started', 'intake_data' => '{}', 'created' => 1789700000, 'changed' => 1789700000,
    ])->execute();
    $clock = $this->createMock(TimeInterface::class);
    $clock->method('getRequestTime')->willReturn(1789700000);
    $this->portal = (new \ReflectionClass(CustomerPortalService::class))->newInstanceWithoutConstructor();
    foreach (['database' => $this->db, 'time' => $clock, 'configFactory' => $this->getConfigFactoryStub(['famtastic_pipeline.settings' => ['frontend_base_url' => 'https://example.test']])] as $property => $value) {
      (new \ReflectionProperty($this->portal, $property))->setValue($this->portal, $value);
    }
  }

  /** Keys/shape from class-2000-proof-delivery/scripts/request16-staff-proof-handoff.php. */
  private function trustedIntake(): array {
    return [
      'primary_goal' => 'high school reunion website',
      'staff_assisted_brief' => [
        'schema' => 'famtastic.staff-assisted-brief.v1', 'actor' => 'codex:class-2000-delivery',
        'authority' => 'Fritz explicitly approved Client delivery first plan on 2026-09-18',
        'customer_supplied' => FALSE, 'preserve_original_customer_intake' => TRUE,
        'purpose' => 'Create three reunion website proofs for Miami Beach Senior High Class of 2000.',
        'directions' => ['a' => 'Hi-Tide Legacy', 'b' => 'Miami After Dark', 'c' => 'Class of 2000: Then & Now'],
        'proposed_scope' => ['event information', 'RSVP', 'tickets and sponsorship', 'alumni access', 'dinner preferences', 'memories', 'memorial and time capsule', 'committee administration', 'help'],
        'reference' => 'https://mbsh96reunion.com/',
        'unknowns' => ['date', 'venue', 'ticket pricing', 'capacity', 'photos and rights', 'committee details', 'domain'],
        'private_offer' => ['amount_minor' => 19900, 'currency' => 'USD', 'status' => 'owner_authorized_not_paid', 'scope' => 'reasonable launch work toward the agreed reunion functionality; unusual costs and subscriptions require an exception', 'payment_timing' => 'offered after direction selection; not necessary for proofs or staging'],
        'restrictions' => ['no borrowed private Class of 1996 data or assets', 'no payment captured', 'no final launch', 'no email from this script'],
      ],
      'proof_design_reset_requests' => [['notes' => 'Recorded reset', 'requested_at' => '2026-09-18T20:00:00Z']],
      'proof_edit_round_requests' => [['notes' => 'Recorded edit', 'requested_at' => '2026-09-18T20:01:00Z']],
      'proof_revision_request' => ['notes' => 'Recorded revision', 'type' => 'edit_round'],
      'selected_site_revision_requests' => [
        ['notes' => 'First selected edit', 'requested_at' => '2026-09-18T20:02:00Z'],
        ['notes' => 'Second selected edit', 'requested_at' => '2026-09-18T20:03:00Z'],
      ],
    ];
  }

  private function storeIntake(array $intake): void {
    $this->db->update('famtastic_project_request')->fields(['intake_data' => json_encode($intake, JSON_THROW_ON_ERROR)])->condition('id', 991)->execute();
  }

  private function intake(): array {
    return json_decode($this->db->select('famtastic_project_request', 'r')->fields('r', ['intake_data'])->condition('id', 991)->execute()->fetchField(), TRUE, flags: JSON_THROW_ON_ERROR);
  }

  private function update(array $input = []): array {
    $input += ['project_name' => 'Synthetic reunion', 'primary_goal' => 'Customer changed the goal', 'products_services' => 'Reunion information', 'action' => 'save'];
    return $this->portal->updateWebsiteRequest(901, 'fixture-audit-request', $input);
  }

  #[DataProvider('customerAuditAttempts')]
  public function testRepeatedUpdatesPreserveServerAuditAndNeverCopyOriginalLedger(string $attempt): void {
    $trusted = $this->trustedIntake();
    $this->storeIntake($trusted);
    $originalRaw = '{ "primary_goal": "high school reunion website" }';
    $audit = json_encode(['original_status' => 'draft', 'original_intake_raw' => $originalRaw, 'original_intake_sha256' => hash('sha256', $originalRaw), 'staff_assisted_brief' => $trusted['staff_assisted_brief']], JSON_THROW_ON_ERROR);
    $this->db->insert('famtastic_event')->fields(['event_key' => 'fixture-original-intake', 'event_type' => 'website_request.staff_assisted_brief', 'payload' => $audit, 'occurred_at' => 1789700000, 'recorded_at' => 1789700000])->execute();
    $input = [];
    foreach (self::KEYS as $key) {
      if ($attempt !== 'omitted') $input[$key] = $attempt === 'null' ? NULL : ['actor' => 'customer-forged', 'customer_supplied' => TRUE];
    }
    $input['intake'] = array_fill_keys(self::KEYS, ['nested_forgery' => TRUE]);
    $input['original_intake_raw'] = 'forged';
    $input['original_intake_sha256'] = str_repeat('0', 64);
    for ($round = 1; $round <= 3; $round++) {
      $input['notes'] = 'Customer note ' . $round;
      $result = $this->update($input);
      $stored = $this->intake();
      foreach (self::KEYS as $key) self::assertSame($trusted[$key], $stored[$key]);
      self::assertSame($stored, $result['intake']);
      self::assertSame('Customer changed the goal', $stored['primary_goal']);
      self::assertSame($input['notes'], $stored['notes']);
      self::assertArrayNotHasKey('original_intake_raw', $stored);
      self::assertArrayNotHasKey('original_intake_sha256', $stored);
      self::assertSame($audit, $this->db->select('famtastic_event', 'e')->fields('e', ['payload'])->condition('event_key', 'fixture-original-intake')->execute()->fetchField());
    }
    self::assertSame(0, (int) $this->db->select('famtastic_notification_outbox', 'n')->countQuery()->execute()->fetchField());
    self::assertSame(0, (int) $this->db->select('famtastic_job', 'j')->countQuery()->execute()->fetchField());
  }

  public static function customerAuditAttempts(): iterable {
    foreach (['omitted', 'forged', 'null'] as $case) yield $case => [$case];
  }

  public function testCustomerCannotIntroduceMissingServerAudit(): void {
    $this->update($this->trustedIntake());
    foreach (self::KEYS as $key) self::assertArrayNotHasKey($key, $this->intake());
  }

  public function testEmptyAndNullTrustedValuesArePreserved(): void {
    $trusted = array_fill_keys(self::KEYS, []);
    $trusted['proof_revision_request'] = NULL;
    $this->storeIntake($trusted);
    $this->update($this->trustedIntake());
    foreach (self::KEYS as $key) self::assertSame($trusted[$key], $this->intake()[$key]);
  }

  public function testForeignCustomerCannotMutateAuditOrAnswers(): void {
    $this->storeIntake($this->trustedIntake());
    $before = $this->intake();
    try {
      $this->portal->updateWebsiteRequest(999, 'fixture-audit-request', ['project_name' => 'Forged']);
      self::fail('Foreign customer must be refused.');
    } catch (\RuntimeException $error) {
      self::assertSame('Website request not found.', $error->getMessage());
    }
    self::assertSame($before, $this->intake());
  }

}
