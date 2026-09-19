<?php
declare(strict_types=1);
namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\famtastic_pipeline\Service\WorkerCoordinator;
use Drupal\famtastic_pipeline\Service\WorkerCoordinatorSchema;
use Drupal\famtastic_pipeline\Service\WorkerRequestSignature;
use Drupal\sqlite\Driver\Database\sqlite\Connection;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

require_once dirname(__DIR__, 3) . '/famtastic_pipeline.install';

final class WorkerCoordinatorTest extends UnitTestCase {
  private Connection $db;
  private WorkerCoordinator $coordinator;
  private int $now = 1789700000;
  private string $payload;
  protected function setUp(): void {
    parent::setUp();
    $o = ['database' => ':memory:', 'prefix' => '', 'driver' => 'sqlite', 'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite'];
    $this->db = new Connection(Connection::open($o), $o);
    foreach (WorkerCoordinatorSchema::tables() + ['famtastic_job' => _famtastic_pipeline_automation_schema()['famtastic_job']] as $name => $schema) $this->db->schema()->createTable($name, $schema);
    $clock = $this->createMock(TimeInterface::class);
    $clock->method('getCurrentTime')->willReturnCallback(fn() => $this->now);
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturn(TRUE);
    $this->coordinator = new WorkerCoordinator($this->db, $clock, $lock);
    $this->payload = json_encode(['packet' => ['build_class' => 'prepayment_selected_direction_staging', 'packet_id' => 'fixture-packet', 'idempotency_key' => 'fixture-immutable',
      'continuation' => ['spec' => ['capability_class' => 'static'], 'operation' => 'package_existing', 'requested_next_action' => 'protected_review']]], JSON_THROW_ON_ERROR);
    $this->job(901);
  }
  private function job(int $id, string $status = 'queued', string $type = 'site_studio_staging_prepare'): void {
    $this->db->insert('famtastic_job')->fields(['id' => $id, 'job_key' => 'fixture:' . $id, 'job_type' => $type, 'status' => $status, 'attempts' => 0, 'max_attempts' => 3, 'available_at' => $this->now, 'payload' => $this->payload, 'created' => $this->now, 'changed' => $this->now])->execute();
  }
  private function enroll(int $id = 901, int $reserve = 25): array { return $this->coordinator->enroll($id, 'fixture:' . $id, hash('sha256', $this->payload), $reserve); }
  private function dispatchReceipt(): array { return ['status' => 'accepted_waiting_callback', 'receipt_id' => 'fixture-receipt', 'packet_id' => 'fixture-packet', 'idempotency_key' => 'fixture-immutable']; }

  public function testUnenrolledBacklogAndHealthAreReadOnly(): void {
    $this->job(902, 'queued', 'outreach.send');
    self::assertNull($this->coordinator->claim('cloud-run'));
    self::assertSame(0, $this->coordinator->health()['enrolled_count']);
    self::assertSame(2, (int) $this->db->select('famtastic_job', 'j')->condition('status', 'queued')->countQuery()->execute()->fetchField());
  }
  public function testOneSharedClaimAndCostReservationAcrossHosts(): void {
    self::assertSame('enrolled', $this->enroll()['status']);
    self::assertSame('already_enrolled', $this->enroll()['status']);
    $cloud = $this->coordinator->claim('cloud-run');
    self::assertSame(901, $cloud['job_id']);
    self::assertNull($this->coordinator->claim('mac-fallback'));
    self::assertSame(25, $this->coordinator->health()['reserved_cents']);
    self::assertSame(hash('sha256', $this->payload), $cloud['payload_sha256']);
    self::assertSame('worker_running', $this->db->select('famtastic_job', 'j')->fields('j', ['status'])->execute()->fetchField());
  }
  public function testCompletionAcknowledgementRetryDoesNotDuplicateOrClaimDeployment(): void {
    $this->enroll(); $claim = $this->coordinator->claim('cloud-run');
    $a = $this->coordinator->finish(901, 'cloud-run', $claim['lease_token'], $this->dispatchReceipt());
    $b = $this->coordinator->finish(901, 'cloud-run', $claim['lease_token'], $this->dispatchReceipt());
    self::assertSame('handoff_completed', $a['status']);
    self::assertFalse($a['duplicate']); self::assertTrue($b['duplicate']);
    self::assertSame(25, $this->coordinator->health()['reserved_cents']);
    self::assertNull($this->coordinator->claim('mac-fallback'));
  }
  public function testRenewalUsesLiveClockAndEnforcesDeadline(): void {
    $this->enroll(); $claim = $this->coordinator->claim('cloud-run');
    $this->now += 60;
    self::assertSame($this->now + 90, $this->coordinator->renew(901, 'cloud-run', $claim['lease_token'])['lease_until']);
    $this->now += 30;
    self::assertSame('handoff_completed', $this->coordinator->finish(901, 'cloud-run', $claim['lease_token'], $this->dispatchReceipt())['status']);
  }
  public function testExpiredLeaseIsFencedThenReclaimedWithAnotherReservation(): void {
    $this->enroll(); $old = $this->coordinator->claim('cloud-run');
    $this->now += 91;
    self::assertNull($this->coordinator->claim('mac-fallback'));
    $this->now += 240;
    $next = $this->coordinator->claim('mac-fallback');
    self::assertSame(2, $next['attempt']); self::assertNotSame($old['lease_token'], $next['lease_token']);
    self::assertSame(50, $this->coordinator->health()['reserved_cents']);
    try { $this->coordinator->finish(901, 'cloud-run', $old['lease_token'], $this->dispatchReceipt()); self::fail('Old worker cannot finish.'); }
    catch (\RuntimeException $e) { self::assertStringContainsString('Lease lost', $e->getMessage()); }
  }
  public function testRetriesAreBoundedEvenWhenWorkersDisappear(): void {
    $this->enroll();
    for ($i = 1; $i <= 3; $i++) {
      self::assertSame($i, $this->coordinator->claim('cloud-run')['attempt']);
      $this->now += 91;
      self::assertNull($this->coordinator->claim('cloud-run'));
      $this->now += 240;
    }
    self::assertNull($this->coordinator->claim('cloud-run'));
    self::assertSame('failed', $this->db->select('famtastic_job', 'j')->fields('j', ['status'])->execute()->fetchField());
    self::assertSame(75, $this->coordinator->health()['reserved_cents']);
  }
  public function testBudgetExhaustionDoesNotClaimOrCallAProvider(): void {
    $this->enroll(901, 250);
    $this->db->insert('famtastic_worker_budget')->fields(['reservation_key' => 'prior-usage', 'month' => gmdate('Y-m', $this->now), 'job_id' => 800, 'attempt' => 1, 'reserved_cents' => 1800, 'created' => $this->now])->execute();
    try { $this->coordinator->claim('cloud-run'); self::fail('Budget must stop.'); }
    catch (\RuntimeException $e) { self::assertSame('worker_budget_exhausted', $e->getMessage()); }
    self::assertSame(1800, $this->coordinator->health()['reserved_cents']);
    self::assertSame('worker_queued', $this->db->select('famtastic_job', 'j')->fields('j', ['status'])->execute()->fetchField());
  }
  #[DataProvider('unsafeEnrollments')]
  public function testNoHistoricalOrWrongJobEnrollment(string $case): void {
    match ($case) {
      'failed' => $this->db->update('famtastic_job')->fields(['status' => 'failed'])->execute(),
      'attempted' => $this->db->update('famtastic_job')->fields(['attempts' => 1])->execute(),
      'mail' => $this->db->update('famtastic_job')->fields(['job_type' => 'outreach.send'])->execute(),
      'commerce' => $this->db->update('famtastic_job')->fields(['job_type' => 'payment.capture'])->execute(),
      'changed' => $this->db->update('famtastic_job')->fields(['payload' => '{}'])->execute(),
    };
    $this->expectException(\Exception::class); $this->enroll();
  }
  public static function unsafeEnrollments(): iterable { foreach (['failed', 'attempted', 'mail', 'commerce', 'changed'] as $c) yield $c => [$c]; }
  public function testForeignWorkerCannotRenewOrFinish(): void {
    $this->enroll(); $c = $this->coordinator->claim('cloud-run');
    $this->expectException(\RuntimeException::class); $this->coordinator->renew(901, 'mac-fallback', $c['lease_token']);
  }
  public function testNonceReplayIsRejected(): void {
    $nonce = str_repeat('a', 32); $this->coordinator->rememberNonce('cloud-run', $nonce);
    $this->expectException(\Exception::class); $this->coordinator->rememberNonce('cloud-run', $nonce);
  }
  public function testEcommerceCannotMasqueradeAsTheStaticBuildClass(): void {
    $payload = json_decode($this->payload, TRUE);
    $payload['packet']['continuation']['spec']['backend'] = ['engine' => 'woocommerce'];
    $this->payload = json_encode($payload, JSON_THROW_ON_ERROR);
    $this->db->update('famtastic_job')->fields(['payload' => $this->payload])->condition('id', 901)->execute();
    try { $this->enroll(); self::fail('Static admission must reject backend work.'); }
    catch (\RuntimeException $e) { self::assertStringContainsString('implementation capability', $e->getMessage()); }
    self::assertSame(0, $this->coordinator->health()['enrolled_count']);
    self::assertSame(0, $this->coordinator->health()['reserved_cents']);
  }
  public function testSignatureBindsIdentityTimeBodyAndEndpoint(): void {
    $worker = 'cloud-run'; $body = '{}'; $nonce = str_repeat('a', 32); $secret = str_repeat('s', 32); $timestamp = (string) $this->now; $path = '/api/pipeline/worker/claim';
    $sig = 'sha256=' . hash_hmac('sha256', implode("\n", ['POST', $path, $worker, $timestamp, $nonce, hash('sha256', $body)]), $secret);
    WorkerRequestSignature::verify('POST', $path, $body, $worker, $timestamp, $nonce, $sig, $secret, $this->now);
    self::assertTrue(TRUE);
    foreach (['wrong-worker', 'changed-body', 'wrong-path', 'expired'] as $case) {
      try {
        WorkerRequestSignature::verify('POST', $case === 'wrong-path' ? '/api/pipeline/worker/finish' : $path, $case === 'changed-body' ? '{"changed":1}' : $body,
          $case === 'wrong-worker' ? 'mac-fallback' : $worker, $timestamp, $nonce, $sig, $secret, $this->now + ($case === 'expired' ? 91 : 0));
        self::fail('Tampered or expired signature accepted.');
      } catch (\InvalidArgumentException $e) { self::assertSame('Worker authentication rejected.', $e->getMessage()); }
    }
  }
}
