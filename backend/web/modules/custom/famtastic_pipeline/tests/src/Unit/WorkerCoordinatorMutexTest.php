<?php
declare(strict_types=1);
namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\StatementInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Site\Settings;
use Drupal\famtastic_pipeline\Service\{OperationalLedger, WorkerCoordinator, WorkerCoordinatorMutex, WorkerCoordinatorSchema};
use Drupal\Tests\famtastic_pipeline\Unit\Fixtures\WorkerMutexConnection;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

require_once dirname(__DIR__, 3) . '/famtastic_pipeline.install';
require_once __DIR__ . '/Fixtures/WorkerMutexConnection.php';

/** Sequential SQLite + exact SQL contracts. Real MariaDB contention is a separate gate. */
final class WorkerCoordinatorMutexTest extends UnitTestCase {
  private WorkerMutexConnection $db;
  private WorkerCoordinator $coordinator;
  private OperationalLedger $ledger;
  private int $now = 1790010000;
  private string $wire;

  protected function setUp(): void {
    parent::setUp();
    new Settings([]);
    $options = ['database' => ':memory:', 'prefix' => '', 'driver' => 'sqlite', 'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite'];
    $this->db = new WorkerMutexConnection(WorkerMutexConnection::open($options), $options);
    $legacy = _famtastic_pipeline_automation_schema();
    foreach (WorkerCoordinatorSchema::tables() + array_intersect_key($legacy, array_flip(['famtastic_job', 'famtastic_event', 'famtastic_exception'])) as $name => $schema) $this->db->schema()->createTable($name, $schema);
    $clock = $this->createMock(TimeInterface::class);
    $clock->method('getCurrentTime')->willReturnCallback(fn() => $this->now);
    $clock->method('getRequestTime')->willReturnCallback(fn() => $this->now);
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturn(TRUE); // Advisory lock proves nothing about exclusion.
    $this->coordinator = new WorkerCoordinator($this->db, $clock, $lock);
    $this->ledger = new OperationalLedger($this->db, $clock, $this->coordinator);
    $this->wire = json_encode(['packet' => ['build_class' => 'prepayment_selected_direction_staging', 'packet_id' => 'fixture-packet', 'idempotency_key' => 'fixture-key',
      'continuation' => ['spec' => ['capability_class' => 'static'], 'operation' => 'package_existing', 'requested_next_action' => 'protected_review']]], JSON_THROW_ON_ERROR);
    $this->db->insert('famtastic_job')->fields(['id' => 1, 'job_key' => 'fixture:1', 'job_type' => 'site_studio_staging_prepare', 'status' => 'queued', 'attempts' => 0,
      'max_attempts' => 3, 'available_at' => $this->now, 'payload' => $this->wire, 'created' => $this->now, 'changed' => $this->now])->execute();
    $this->db->observed = [];
  }

  protected function tearDown(): void { new Settings([]); parent::tearDown(); }
  private function enroll(): void { $this->coordinator->enroll(1, 'fixture:1', hash('sha256', $this->wire), 25); }
  private function claim(): array { return $this->coordinator->claim('synthetic-worker'); }
  private function receipt(): array { return ['status' => 'accepted_waiting_callback', 'receipt_id' => 'synthetic-receipt', 'packet_id' => 'fixture-packet', 'idempotency_key' => 'fixture-key']; }
  private function row(string $table): array|false { return $this->db->select($table, 't')->fields('t')->execute()->fetchAssoc(); }
  private function countRows(string $table): int { return (int) $this->db->select($table, 't')->countQuery()->execute()->fetchField(); }
  private function state(): array {
    $state = [];
    foreach (['famtastic_job', 'famtastic_worker_claim', 'famtastic_worker_budget', 'famtastic_event', 'famtastic_exception'] as $table) $state[$table] = $this->db->select($table, 't')->fields('t')->execute()->fetchAll(\PDO::FETCH_ASSOC);
    return $state;
  }
  private function rejected(callable $operation, string $message): void {
    $error = NULL;
    try { $operation(); } catch (\Throwable $e) { $error = $e; }
    self::assertInstanceOf(\RuntimeException::class, $error);
    self::assertStringContainsString($message, $error->getMessage());
  }

  #[DataProvider('mutexSql')]
  public function testExplicitSingleStatementDialect(string $driver, string $suffix): void {
    $db = $this->createMock(Connection::class);
    $db->method('inTransaction')->willReturn(TRUE);
    $db->method('driver')->willReturn($driver);
    $db->expects(self::once())->method('query')->with('INSERT INTO {famtastic_worker_mutex} (id) VALUES (1) ' . $suffix)
      ->willReturn($this->createMock(StatementInterface::class));
    WorkerCoordinatorMutex::acquire($db);
  }
  public static function mutexSql(): iterable {
    yield 'MySQL and MariaDB through Drupal mysql driver' => ['mysql', 'ON DUPLICATE KEY UPDATE id = 1'];
    yield 'SQLite' => ['sqlite', 'ON CONFLICT (id) DO UPDATE SET id = excluded.id'];
  }
  public function testUnsupportedDriverFailsBeforeAnyQuery(): void {
    $db = $this->createMock(Connection::class);
    $db->method('inTransaction')->willReturn(TRUE); $db->method('driver')->willReturn('unsupported');
    $db->expects(self::never())->method('query');
    $this->rejected(fn() => WorkerCoordinatorMutex::acquire($db), 'Unsupported');
  }
  public function testAcquisitionWithoutTransactionFailsBeforeWriting(): void {
    try { WorkerCoordinatorMutex::acquire($this->db); self::fail('Missing transaction accepted.'); }
    catch (\LogicException $e) { self::assertStringContainsString('active transaction', $e->getMessage()); }
    self::assertSame(0, $this->countRows('famtastic_worker_mutex'));
  }
  public function testMissingTableDoesNotCreateRuntimeSchemaOrChangeJob(): void {
    $this->db->schema()->dropTable('famtastic_worker_mutex');
    $before = $this->state();
    try { $this->enroll(); self::fail('Missing migration accepted.'); }
    catch (\Drupal\Core\Database\DatabaseExceptionWrapper $e) { self::assertStringContainsString('famtastic_worker_mutex', $e->getMessage()); }
    self::assertFalse($this->db->schema()->tableExists('famtastic_worker_mutex'));
    self::assertSame($before, $this->state());
  }
  public function testNestedSuccessDoesNotCommitOuterTransactionOrPersistItsLazyRow(): void {
    $before = $this->state();
    $outer = $this->db->startTransaction();
    $this->enroll(); $claim = $this->claim();
    $this->coordinator->finish(1, 'synthetic-worker', $claim['lease_token'], $this->receipt());
    self::assertTrue($this->db->inTransaction());
    self::assertSame(['id' => '1'], array_map('strval', $this->row('famtastic_worker_mutex')));
    $outer->rollBack();
    self::assertSame($before, $this->state());
    self::assertSame(0, $this->countRows('famtastic_worker_mutex'));
  }
  public function testLazyRowIsFixedAndReacquiredAcrossTransactions(): void {
    self::assertSame(['id'], array_keys(WorkerCoordinatorSchema::tables()['famtastic_worker_mutex']['fields']));
    for ($i = 0; $i < 2; $i++) WorkerCoordinatorMutex::run($this->db, fn() => WorkerCoordinatorMutex::acquire($this->db));
    self::assertFalse($this->db->inTransaction());
    self::assertSame(1, $this->countRows('famtastic_worker_mutex'));
    self::assertSame(4, count(array_filter($this->db->observed, fn($r) => str_starts_with($r['sql'], 'INSERT INTO {famtastic_worker_mutex}'))));
  }
  public function testAllCoordinatorDecisionReadsRequestLocksWithoutSnapshotAggregates(): void {
    $this->enroll(); $claim = $this->claim();
    $this->coordinator->renew(1, 'synthetic-worker', $claim['lease_token']);
    $this->coordinator->fail(1, 'synthetic-worker', $claim['lease_token']);
    $reads = array_filter($this->db->observed, fn($r) => str_starts_with($r['sql'], 'SELECT'));
    self::assertNotEmpty($reads);
    foreach ($reads as $read) {
      self::assertTrue($read['locking'], $read['sql']);
      self::assertDoesNotMatchRegularExpression('/\b(?:SUM|COUNT)\s*\(/i', $read['sql']);
    }
    // SQLite ignores FOR UPDATE: this observes the requested API, not its MySQL effect.
  }
  public function testClockIsSampledAfterMutexAndBudgetReadWaits(): void {
    $this->enroll(); $start = $this->now;
    $this->db->afterMutex = function () { $this->now += 40; };
    $this->db->afterBudgetRead = function () { $this->now += 20; };
    $claim = $this->claim();
    self::assertSame($start + 60 + 90, $claim['lease_until']);
    self::assertSame($start + 60 + 300, $claim['execution_deadline']);
  }
  public function testLeaseThatExpiresWaitingForMutexCannotRenew(): void {
    $this->enroll(); $claim = $this->claim(); $before = $this->state();
    $this->db->afterMutex = function () { $this->now += 91; };
    $this->rejected(fn() => $this->coordinator->renew(1, 'synthetic-worker', $claim['lease_token']), 'Lease lost');
    self::assertSame($before, $this->state());
  }
  public function testMonthChangeDuringBudgetReadRejectsWithoutReservation(): void {
    $this->now = strtotime('2026-09-30 23:59:59 UTC'); $this->enroll(); $before = $this->state();
    $this->db->afterBudgetRead = function () { $this->now += 2; };
    $this->rejected(fn() => $this->claim(), 'Budget month changed');
    self::assertSame($before, $this->state());
  }

  #[DataProvider('casFailures')]
  public function testZeroRowCriticalWriteRollsBackEntireOperation(string $operation, string $table): void {
    $claim = NULL;
    if ($operation !== 'enroll') $this->enroll();
    if (!in_array($operation, ['enroll', 'claim'], TRUE)) $claim = $this->claim();
    if ($operation === 'recover') $this->now += 91;
    $before = $this->state();
    // Actual SQLite affected-row failure after earlier writes in the operation.
    $this->db->query('CREATE TRIGGER reject_write BEFORE UPDATE ON {' . $table . '} BEGIN SELECT RAISE(IGNORE); END', [], ['allow_delimiter_in_query' => TRUE]);
    $this->rejected(fn() => match ($operation) {
      'enroll' => $this->enroll(), 'claim', 'recover' => $this->claim(),
      'renew' => $this->coordinator->renew(1, 'synthetic-worker', $claim['lease_token']),
      'finish' => $this->coordinator->finish(1, 'synthetic-worker', $claim['lease_token'], $this->receipt()),
      'fail' => $this->coordinator->fail(1, 'synthetic-worker', $claim['lease_token']),
    }, 'changed; transaction rolled back');
    self::assertSame($before, $this->state());
  }
  public static function casFailures(): iterable {
    foreach (['enroll', 'claim', 'renew', 'finish', 'fail', 'recover'] as $operation) {
      foreach (['famtastic_worker_claim', 'famtastic_job'] as $table) {
        if (($operation === 'enroll' && $table !== 'famtastic_job') || ($operation === 'renew' && $table !== 'famtastic_worker_claim')) continue;
        yield $operation . '-' . $table => [$operation, $table];
      }
    }
  }

  #[DataProvider('staleSnapshots')]
  public function testCasPredicatesRejectActualChangedRows(string $table, string $field, mixed $value): void {
    $this->enroll(); $this->claim();
    $before = $this->row($table);
    $this->db->update($table)->fields([$field => $value])->execute();
    $changed = $this->state();
    $method = new \ReflectionMethod(WorkerCoordinator::class, $table === 'famtastic_job' ? 'updateJob' : 'updateClaim');
    $this->rejected(fn() => WorkerCoordinatorMutex::run($this->db,
      fn() => $method->invoke($this->coordinator, $before, ['changed' => $this->now + 1])), 'changed; transaction rolled back');
    self::assertSame($changed, $this->state());
  }
  public static function staleSnapshots(): iterable {
    foreach (['state' => 'pending', 'attempt' => 2, 'worker_id' => 'other-worker', 'token_hash' => str_repeat('f', 64),
      'lease_until' => 1, 'attempt_deadline' => 2, 'payload_sha256' => str_repeat('e', 64), 'reservation_cents' => 250] as $field => $value) {
      yield 'claim-' . $field => ['famtastic_worker_claim', $field, $value];
    }
    foreach (['status' => 'failed', 'attempts' => 2, 'payload' => '{}', 'locked_at' => 1, 'max_attempts' => 1] as $field => $value) {
      yield 'job-' . $field => ['famtastic_job', $field, $value];
    }
  }

  #[DataProvider('legacyAttempts')]
  public function testLegacyWritersCannotChangeManagedIdentityOrHolds(string $state, string $operation): void {
    $this->enroll();
    if ($state !== 'pending') {
      for ($i = 0; $i < ($state === 'exception' ? 3 : 1); $i++) {
        $claim = $this->claim();
        if ($state === 'exception') { $this->coordinator->fail(1, 'synthetic-worker', $claim['lease_token']); $this->now += 331; }
      }
      if ($state === 'completed') $this->coordinator->finish(1, 'synthetic-worker', $claim['lease_token'], $this->receipt());
    }
    $before = $this->state();
    $this->rejected(fn() => match ($operation) {
      'complete' => $this->ledger->completeJob(1, ['fake' => TRUE]),
      'fail' => $this->ledger->failJob(1, 'synthetic failure'),
      'requeue' => $this->ledger->requeueFailedJob(1, 'fixture:1'),
    }, 'Coordinator-owned');
    self::assertSame($before, $this->state());
  }
  public static function legacyAttempts(): iterable {
    foreach (['pending', 'leased', 'exception', 'completed'] as $state) foreach (['complete', 'fail', 'requeue'] as $operation) yield $state . '-' . $operation => [$state, $operation];
  }
  public function testLegacyClaimCannotAdoptManagedRowEvenWithQueuedStatus(): void {
    $this->enroll();
    $this->db->update('famtastic_job')->fields(['status' => 'queued'])->condition('id', 1)->execute();
    $before = $this->state();
    self::assertNull($this->ledger->claimNext());
    self::assertSame($before, $this->state());
  }
  public function testLegacyUnownedCompletionRetryAndRequeueRemainAvailable(): void {
    self::assertSame(1, $this->ledger->claimNext()['id']);
    $first = $this->ledger->failJob(1, 'synthetic failure');
    self::assertSame(['exhausted' => FALSE, 'attempts' => 1, 'retry_at' => $this->now + 30], $first);
    $this->now += 30; self::assertSame(1, $this->ledger->claimNext()['id']);
    $this->ledger->completeJob(1, ['ok' => TRUE]); $before = $this->row('famtastic_job');
    $this->ledger->completeJob(1, ['different' => TRUE]); self::assertSame($before, $this->row('famtastic_job'));
    $this->db->update('famtastic_job')->fields(['status' => 'failed', 'attempts' => 3])->condition('id', 1)->execute();
    self::assertTrue($this->ledger->requeueFailedJob(1, 'fixture:1'));
    self::assertSame('queued', $this->row('famtastic_job')['status']);
    self::assertSame(0, $this->countRows('famtastic_worker_claim'));
    self::assertSame(0, $this->countRows('famtastic_worker_budget'));
  }
  public function testFreshSelectedAdmissionLocksBeforeJobReadAndOuterRollbackUndoesAll(): void {
    new Settings(['famtastic_fresh_selected_admission_enabled' => TRUE]);
    $outer = $this->db->startTransaction();
    $id = $this->ledger->enqueue('fixture:new', 'site_studio_staging_prepare', json_decode($this->wire, TRUE));
    $events = $this->db->observed;
    $mutex = array_search(TRUE, array_map(fn($r) => str_starts_with($r['sql'], 'INSERT INTO {famtastic_worker_mutex}'), $events), TRUE);
    self::assertNotFalse($mutex);
    $lockedJobs = array_keys(array_filter($events, fn($r) => str_contains($r['sql'], 'famtastic_job') && $r['locking']));
    self::assertNotEmpty($lockedJobs); self::assertGreaterThan($mutex, min($lockedJobs));
    self::assertTrue($this->db->inTransaction()); self::assertGreaterThan(1, $id);
    $outer->rollBack();
    self::assertSame(1, $this->countRows('famtastic_job'));
    self::assertSame(0, $this->countRows('famtastic_worker_claim'));
    self::assertSame(0, $this->countRows('famtastic_worker_mutex'));
  }
}
