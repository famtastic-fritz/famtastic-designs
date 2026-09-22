<?php
declare(strict_types=1);
namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Site\Settings;
use Drupal\famtastic_pipeline\Service\OperationalLedger;
use Drupal\famtastic_pipeline\Service\WorkerCoordinator;
use Drupal\famtastic_pipeline\Service\WorkerCoordinatorSchema;
use Drupal\sqlite\Driver\Database\sqlite\Connection;
use Drupal\Tests\UnitTestCase;

require_once dirname(__DIR__, 3) . '/famtastic_pipeline.install';

final class FreshSelectedJobAdmissionTest extends UnitTestCase {
  private Connection $db;
  private TimeInterface $clock;
  private WorkerCoordinator $coordinator;
  private OperationalLedger $ledger;
  private bool $lockAvailable = TRUE;

  protected function setUp(): void {
    parent::setUp();
    new Settings([]);
    $options = ['database' => ':memory:', 'prefix' => '', 'driver' => 'sqlite', 'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite'];
    $this->db = new Connection(Connection::open($options), $options);
    foreach (WorkerCoordinatorSchema::tables() + ['famtastic_job' => _famtastic_pipeline_automation_schema()['famtastic_job']] as $name => $schema) $this->db->schema()->createTable($name, $schema);
    $this->clock = $this->createMock(TimeInterface::class);
    $this->clock->method('getRequestTime')->willReturn(1790010000);
    $this->clock->method('getCurrentTime')->willReturn(1790010000);
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturnCallback(fn() => $this->lockAvailable);
    $this->coordinator = new WorkerCoordinator($this->db, $this->clock, $lock);
    $this->ledger = new OperationalLedger($this->db, $this->clock, $this->coordinator);
  }
  protected function tearDown(): void { new Settings([]); parent::tearDown(); }
  private function enable(): void { new Settings(['famtastic_fresh_selected_admission_enabled' => TRUE]); }
  private function payload(): array {
    return ['packet' => ['build_class' => 'prepayment_selected_direction_staging', 'packet_id' => 'disposable-selected', 'idempotency_key' => 'disposable-selected',
      'continuation' => ['spec' => ['capability_class' => 'static'], 'operation' => 'package_existing', 'requested_next_action' => 'protected_review']]];
  }
  private function enqueue(string $key = 'fixture:new', ?array $payload = NULL, string $type = 'site_studio_staging_prepare'): int {
    return $this->ledger->enqueue($key, $type, $payload ?? $this->payload());
  }
  private function jobStatus(int $id): string { return $this->db->select('famtastic_job', 'j')->fields('j', ['status'])->condition('id', $id)->execute()->fetchField(); }

  public function testDefaultDoesNotEnrollOrActivateAnything(): void {
    self::assertSame('queued', $this->jobStatus($this->enqueue()));
    self::assertSame(0, $this->coordinator->health()['enrolled_count']);
    self::assertNull($this->coordinator->claim('mac-worker'));
  }
  public function testFreshAdmissionIsAutomaticAndSharedAcrossHosts(): void {
    $this->enable(); $id = $this->enqueue();
    self::assertSame('worker_queued', $this->jobStatus($id));
    self::assertSame(0, $this->coordinator->health()['reserved_cents']);
    self::assertSame($id, $this->coordinator->claim('mac-worker')['job_id']);
    self::assertNull($this->coordinator->claim('cloud-worker'));
    self::assertSame(25, $this->coordinator->health()['reserved_cents']);
  }
  public function testDuplicateDoesNotCreateAnotherJobOrEnrollment(): void {
    $this->enable(); $id = $this->enqueue();
    self::assertSame($id, $this->enqueue());
    self::assertSame(1, $this->coordinator->health()['enrolled_count']);
  }
  public function testEnablingDoesNotReviveOldQueuedWork(): void {
    $old = $this->enqueue('fixture:old'); $this->enable();
    self::assertSame($old, $this->enqueue('fixture:old'));
    self::assertSame('queued', $this->jobStatus($old));
    $new = $this->enqueue();
    self::assertSame($new, $this->coordinator->claim('mac-worker')['job_id']);
  }
  public function testCommerceAndFunctionalBackendsStayOutsideStaticCapability(): void {
    $this->enable();
    foreach (['backend', 'functional_contract'] as $field) {
      $payload = $this->payload(); $payload['packet']['continuation']['spec'][$field] = ['woocommerce'];
      self::assertSame('queued', $this->jobStatus($this->enqueue('fixture:' . $field, $payload)));
    }
    self::assertSame(0, $this->coordinator->health()['enrolled_count']);
  }
  public function testProofAndOutreachJobsAreNotStaticBuilds(): void {
    $this->enable();
    foreach (['proof.generate', 'outreach.send'] as $type) self::assertSame('queued', $this->jobStatus($this->enqueue('fixture:' . $type, NULL, $type)));
    self::assertSame(0, $this->coordinator->health()['enrolled_count']);
  }
  public function testAdmissionFailureRollsBackFreshJobAndCanRetry(): void {
    $this->enable(); $this->lockAvailable = FALSE;
    try { $this->enqueue(); self::fail('Expected coordinator lock rejection.'); }
    catch (\RuntimeException $error) { self::assertStringContainsString('busy', $error->getMessage()); }
    self::assertSame(0, (int) $this->db->select('famtastic_job', 'j')->countQuery()->execute()->fetchField());
    $this->lockAvailable = TRUE;
    self::assertSame('worker_queued', $this->jobStatus($this->enqueue()));
  }
  public function testMissingCoordinatorFailsClosedBeforeInsert(): void {
    $this->enable(); $this->ledger = new OperationalLedger($this->db, $this->clock);
    try { $this->enqueue(); self::fail('Expected missing coordinator rejection.'); }
    catch (\RuntimeException $error) { self::assertStringContainsString('requires', $error->getMessage()); }
    self::assertSame(0, (int) $this->db->select('famtastic_job', 'j')->countQuery()->execute()->fetchField());
  }
}
