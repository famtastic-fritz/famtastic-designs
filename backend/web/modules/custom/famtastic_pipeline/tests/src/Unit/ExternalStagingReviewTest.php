<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\famtastic_pipeline\Service\ExternalStagingReviewService;
use Drupal\famtastic_pipeline\Service\FullSiteReviewPackage;
use Drupal\famtastic_pipeline\Service\OperationalLedger;
use Drupal\famtastic_pipeline\Service\StagingReceiptService;
use Drupal\sqlite\Driver\Database\sqlite\Connection;
use Drupal\Tests\UnitTestCase;

require_once dirname(__DIR__, 3) . '/famtastic_pipeline.install';
spl_autoload_register(static function (string $class): void {
  $prefix = 'Drupal\\famtastic_pipeline\\';
  if (!str_starts_with($class, $prefix)) {
    return;
  }
  $file = dirname(__DIR__, 3) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
  if (is_file($file)) {
    require_once $file;
  }
}, TRUE, TRUE);

/**
 * Real SQLite coverage for immutable external-release binding and readiness.
 */
final class ExternalStagingReviewTest extends UnitTestCase {

  /** Test database. */
  private Connection $db;

  /** External release adapter under test. */
  private ExternalStagingReviewService $external;

  /** Current external import packet. */
  private array $packet;

  /** Current full-site review manifest digest. */
  private string $manifestSha;
  private const REQUEST_ID = 93;
  private const CUSTOMER_ID = 91;
  private const ORGANIZATION_ID = 92;
  private const PUBLIC_ID = '11111111-2222-4333-8444-555555555555';

  /** {@inheritdoc} */
  protected function setUp(): void {
    parent::setUp();
    $options = ['database' => ':memory:', 'prefix' => '', 'driver' => 'sqlite', 'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite'];
    $this->db = new Connection(Connection::open($options), $options);
    $schemas = _famtastic_pipeline_customer_portal_schema() + _famtastic_pipeline_automation_schema() + _famtastic_pipeline_lifecycle_schema();
    foreach (['famtastic_customer', 'famtastic_organization', 'famtastic_membership', 'famtastic_project_request', 'famtastic_build_run', 'famtastic_event', 'famtastic_portal_activity', 'famtastic_notification_outbox', 'famtastic_job'] as $table) {
      $this->db->schema()->createTable($table, $schemas[$table]);
    }
    $this->db->insert('famtastic_customer')->fields(['id' => self::CUSTOMER_ID, 'uid' => 191, 'public_id' => 'fixture-customer', 'email' => 'fixture@example.invalid', 'display_name' => 'Fixture', 'created' => 1, 'changed' => 1])->execute();
    $this->db->insert('famtastic_organization')->fields(['id' => self::ORGANIZATION_ID, 'public_id' => 'fixture-organization', 'name' => 'Fixture organization', 'status' => 'active', 'created' => 1, 'changed' => 1])->execute();
    $this->db->insert('famtastic_membership')->fields(['organization_id' => self::ORGANIZATION_ID, 'customer_id' => self::CUSTOMER_ID, 'role' => 'owner', 'status' => 'active', 'created' => 1])->execute();
    $manifest = [
      'schema' => FullSiteReviewPackage::SCHEMA,
      'review_id' => 'fixture-version-one',
      'title' => 'Fixture full website',
      'entry_path' => 'index.html',
      'source_commit' => str_repeat('a', 40),
      'build_id' => 'fixture-full-site',
      'pages' => [['label' => 'Home', 'path' => 'index.html']],
      'documents' => [],
      'files' => [['path' => 'index.html', 'role' => 'page', 'media_type' => 'text/html', 'sha256' => str_repeat('b', 64), 'bytes' => 128]],
      'research' => ['overview' => '', 'researched_at' => '', 'sources' => []],
    ];
    $manifest = FullSiteReviewPackage::normalize($manifest);
    $this->manifestSha = FullSiteReviewPackage::digest($manifest);
    $record = ['schema' => FullSiteReviewPackage::SCHEMA, 'request_public_id' => self::PUBLIC_ID, 'customer_id' => self::CUSTOMER_ID, 'organization_id' => self::ORGANIZATION_ID, 'manifest' => $manifest, 'manifest_sha256' => $this->manifestSha, 'created_at' => '2026-09-21T00:00:00+00:00'];
    $intake = ['notes' => 'Customer words remain untouched.', 'staff_assisted_brief' => ['schema' => 'famtastic.staff-assisted-brief.v1', 'actor' => 'codex:fixture', 'authority' => 'Synthetic test authorization', 'customer_supplied' => FALSE, 'full_site_review' => $record]];
    $this->db->insert('famtastic_project_request')->fields(['id' => self::REQUEST_ID, 'public_id' => self::PUBLIC_ID, 'customer_id' => self::CUSTOMER_ID, 'organization_id' => self::ORGANIZATION_ID, 'project_name' => 'Fixture', 'status' => 'submitted', 'proof_campaign_id' => 54, 'proof_review_status' => 'not_started', 'intake_data' => json_encode($intake, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), 'created' => 1, 'changed' => 1])->execute();
    $this->db->insert('famtastic_build_run')->fields(['build_key' => 'build-dna:fixture-full-site', 'status' => 'completed', 'source_sha' => str_repeat('a', 40), 'artifact_checksum' => str_repeat('d', 64), 'created' => 1, 'changed' => 1])->execute();
    $clock = $this->createMock(TimeInterface::class);
    $clock->method('getRequestTime')->willReturn(1790000000);
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('hasPermission')->with('administer famtastic pipeline')->willReturn(TRUE);
    $account->method('id')->willReturn(1);
    $this->external = new ExternalStagingReviewService($this->db, $clock, $account, new OperationalLedger($this->db, $clock));
    $this->packet = [
      'schema' => ExternalStagingReviewService::IMPORT_SCHEMA,
      'staging_url' => 'https://stage.example.invalid/?release=fixture-1',
      'release_id' => 'fixture-1',
      'source_commit' => str_repeat('a', 40),
      'artifact_sha256' => str_repeat('e', 64),
      'review_manifest_sha256' => $this->manifestSha,
      'qa' => [['name' => 'Desktop browser journey', 'status' => 'passed'], ['name' => 'Mobile browser journey', 'status' => 'passed']],
    ];
  }

  /** Proves the adapter is quiet, immutable, and exactly idempotent. */
  public function testExactExternalReleaseIsQuietImmutableAndIdempotent(): void {
    $first = $this->attach();
    $second = $this->attach();
    self::assertTrue($first['newly_attached']);
    self::assertFalse($second['newly_attached']);
    self::assertSame($first['receipt_hash'], $second['receipt_hash']);
    $row = $this->request();
    self::assertSame('selected', $row['proof_review_status']);
    self::assertSame('external', $row['selected_proof_direction']);
    self::assertNull($row['proof_campaign_id']);
    self::assertSame('deployed', $row['staging_status']);
    self::assertSame('pending', $row['staging_review_status']);
    self::assertNull($row['proof_approved_by_uid']);
    self::assertNull($row['proof_approved_at']);
    self::assertNull($row['commerce_order_id']);
    self::assertNull($row['project_id']);
    self::assertSame($first['receipt'], $this->external->validateStoredReceipt(self::REQUEST_ID, $first['receipt_hash']));
    self::assertSame(1, (int) $this->db->select('famtastic_event', 'e')->countQuery()->execute()->fetchField());
    self::assertSame(1, (int) $this->db->select('famtastic_portal_activity', 'a')->countQuery()->execute()->fetchField());
    foreach (['famtastic_notification_outbox', 'famtastic_job'] as $table) {
      self::assertSame(0, (int) $this->db->select($table, 't')->countQuery()->execute()->fetchField());
    }
    $event = json_decode((string) $this->db->select('famtastic_event', 'e')->fields('e', ['payload'])->execute()->fetchField(), TRUE, 512, JSON_THROW_ON_ERROR);
    self::assertFalse($event['customer_acceptance']);
    self::assertFalse($event['payment_changed']);
    self::assertFalse($event['deploy_performed']);
    self::assertFalse($event['dns_changed']);
    self::assertSame(54, $event['prior_proof_campaign_id']);
    self::assertTrue($event['prior_proof_history_preserved']);
    $this->db->update('famtastic_project_request')->fields(['commerce_order_id' => 777])->condition('id', self::REQUEST_ID)->execute();
    self::assertFalse($this->attach()['newly_attached'], 'An exact retry remains idempotent after later lifecycle state exists.');
  }

  /** Proves acceptance opens readiness without changing other boundaries. */
  public function testCustomerAcceptedExternalReceiptOpensOnlyTheReadinessGate(): void {
    $attached = $this->attach();
    $this->db->update('famtastic_project_request')->fields(['staging_review_status' => 'accepted', 'staging_reviewed_at' => 1790000001])->condition('id', self::REQUEST_ID)->execute();
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $config = $this->createMock(ConfigFactoryInterface::class);
    $clock = $this->createMock(TimeInterface::class);
    $clock->method('getRequestTime')->willReturn(1790000001);
    $receipts = new StagingReceiptService($this->db, $entities, new OperationalLedger($this->db, $clock), $clock, $config, $this->external);
    self::assertTrue($receipts->isReady(self::REQUEST_ID));
    self::assertTrue(StagingReceiptService::checkoutGateSatisfied($this->request()));
    self::assertSame($attached['receipt_hash'], $this->request()['staging_receipt_hash']);
  }

  /** Proves a second release cannot overwrite the first immutable receipt. */
  public function testDifferentReleaseCannotReplaceLockedReceipt(): void {
    $this->attach();
    $this->packet['release_id'] = 'fixture-2';
    $this->packet['staging_url'] = 'https://stage.example.invalid/?release=fixture-2';
    $this->expectExceptionMessage('different immutable staging receipt');
    $this->attach();
  }

  /**
   * Proves malformed or mismatched release evidence is rejected.
   *
   * @dataProvider invalidPacketProvider
   */
  public function testInvalidOrMismatchedEvidenceIsRejected(string $field, mixed $value, string $message): void {
    $this->packet[$field] = $value;
    $this->expectExceptionMessage($message);
    $this->attach();
  }

  /** Supplies malformed and mismatched import packets. */
  public static function invalidPacketProvider(): array {
    return [
      'non-https' => ['staging_url', 'http://stage.example.invalid/', 'HTTPS URL'],
      'release query mismatch' => ['staging_url', 'https://stage.example.invalid/?release=other-release', 'do not match'],
      'wrong source' => ['source_commit', str_repeat('f', 40), 'source_commit'],
      'wrong review' => ['review_manifest_sha256', str_repeat('f', 64), 'current full-site review manifest'],
      'failed qa' => ['qa', [['name' => 'Mobile browser journey', 'status' => 'failed']], 'named and passed'],
    ];
  }

  /** Proves exact active tenant membership is required. */
  public function testCrossTenantBindingAndInactiveMembershipAreRejected(): void {
    try {
      $this->external->attach(self::PUBLIC_ID, 999, self::ORGANIZATION_ID, $this->packet, 'codex:fixture', 'Synthetic test authorization');
      self::fail('Cross-account external release was attached.');
    }
    catch (\RuntimeException $error) {
      self::assertStringContainsString('Exact active customer', $error->getMessage());
    }
    $this->db->update('famtastic_membership')->fields(['status' => 'inactive'])->condition('customer_id', self::CUSTOMER_ID)->execute();
    $this->expectExceptionMessage('Exact active customer');
    $this->attach();
  }

  /**
   * Attaches the current fixture packet.
   *
   * @return array<string, mixed>
   *   Adapter result.
   */
  private function attach(): array {
    return $this->external->attach(self::PUBLIC_ID, self::CUSTOMER_ID, self::ORGANIZATION_ID, $this->packet, 'codex:fixture', 'Synthetic test authorization');
  }

  /**
   * Reads the current request row.
   *
   * @return array<string, mixed>
   *   Stored request fields.
   */
  private function request(): array {
    return $this->db->select('famtastic_project_request', 'r')->fields('r')->condition('id', self::REQUEST_ID)->execute()->fetchAssoc();
  }

}
