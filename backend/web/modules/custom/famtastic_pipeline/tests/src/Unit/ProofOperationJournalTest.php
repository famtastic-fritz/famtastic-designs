<?php
declare(strict_types=1);
namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Site\Settings;
use Drupal\famtastic_pipeline\Entity\ProofCampaign;
use Drupal\famtastic_pipeline\Service\{FreshProofAdmission, ProofOperationContract, ProofOperationJournal, WorkerCapabilityPolicy, WorkerCoordinator, WorkerCoordinatorSchema};
use Drupal\Tests\famtastic_pipeline\Unit\Fixtures\ProofOperationConnection;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

require_once dirname(__DIR__, 3) . '/famtastic_pipeline.install';
require_once __DIR__ . '/Fixtures/ProofOperationConnection.php';

/** Actual admission/coordinator/SQLite, synthetic entities and local verifiers. */
final class ProofOperationJournalTest extends UnitTestCase {
  private ProofOperationConnection $db;
  private TimeInterface $clock;
  private FreshProofAdmission $admission;
  private WorkerCoordinator $coordinator;
  private ProofOperationJournal $journal;
  private array $catalog;
  private array $claim;
  private array $input;
  private int $now = 1790010000;
  private string $worker = 'synthetic-mac';
  private int $inputChecks = 0;
  private int $receiptChecks = 0;
  private bool $inputVerified = TRUE;
  private bool $receiptVerified = TRUE;
  private bool $receiptThrows = FALSE;

  protected function setUp(): void {
    parent::setUp();
    new Settings(['famtastic_fresh_proof_admission_enabled' => TRUE]);
    $options = ['database' => ':memory:', 'prefix' => '', 'driver' => 'sqlite', 'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite'];
    $this->db = new ProofOperationConnection(ProofOperationConnection::open($options), $options);
    $schemas = _famtastic_pipeline_customer_portal_schema() + _famtastic_pipeline_automation_schema() + WorkerCoordinatorSchema::tables();
    foreach (['famtastic_project_request', 'famtastic_customer', 'famtastic_organization', 'famtastic_membership', 'famtastic_customer_resource', 'famtastic_request_asset', 'famtastic_job', 'famtastic_event', 'famtastic_worker_mutex', 'famtastic_worker_claim', 'famtastic_worker_budget', 'famtastic_worker_nonce', 'famtastic_proof_operation'] as $table) $this->db->schema()->createTable($table, $schemas[$table]);
    $this->db->query('CREATE TABLE famtastic_prospect (id INTEGER PRIMARY KEY)');
    $this->db->query('CREATE TABLE proof_campaign (id INTEGER PRIMARY KEY AUTOINCREMENT, campaign_id TEXT, prospect_id INTEGER, business_name TEXT, status TEXT, generation_status TEXT, studio_job_id TEXT, expires_at INTEGER)');
    $this->db->insert('famtastic_customer')->fields(['id' => 1, 'public_id' => $this->uuid(1), 'uid' => 1, 'display_name' => 'Synthetic', 'email' => 'synthetic@example.test', 'verified_at' => $this->now, 'created' => $this->now])->execute();
    $this->db->insert('famtastic_organization')->fields(['id' => 2, 'public_id' => $this->uuid(2), 'name' => 'Synthetic', 'status' => 'active', 'created' => $this->now])->execute();
    $this->db->insert('famtastic_membership')->fields(['customer_id' => 1, 'organization_id' => 2, 'status' => 'active', 'created' => $this->now])->execute();
    $this->db->insert('famtastic_prospect')->fields(['id' => 3])->execute();
    $this->db->insert('famtastic_customer_resource')->fields(['organization_id' => 2, 'resource_type' => 'prospect', 'resource_id' => 3, 'created' => $this->now])->execute();
    $this->db->insert('famtastic_project_request')->fields(['id' => 1, 'public_id' => $this->uuid(4), 'customer_id' => 1, 'organization_id' => 2, 'prospect_id' => 3,
      'project_name' => 'Synthetic', 'business_name' => 'Synthetic', 'project_type' => 'website', 'status' => 'submitted', 'submitted_at' => $this->now,
      'intake_data' => '{"primary_goal":"Synthetic only"}', 'created' => $this->now, 'changed' => $this->now])->execute();
    $this->db->insert('famtastic_request_asset')->fields(['id' => 1, 'public_id' => $this->uuid(5), 'website_request_id' => 1, 'customer_id' => 1, 'file_id' => 7,
      'original_name' => 'synthetic.txt', 'mime_type' => 'text/plain', 'size_bytes' => 9, 'sha256' => hash('sha256', 'synthetic'), 'ownership_confirmed' => 1,
      'ai_use_consent' => 1, 'subject_permission_confirmed' => 1, 'ai_transformation_consent' => 1, 'likeness_consent_version' => 'synthetic-v1', 'likeness_consent_at' => $this->now,
      'created' => $this->now, 'changed' => $this->now])->execute();
    $this->clock = $this->createMock(TimeInterface::class);
    $this->clock->method('getCurrentTime')->willReturnCallback(fn() => $this->now);
    $this->clock->method('getRequestTime')->willReturnCallback(fn() => $this->now);
    $lock = $this->createMock(LockBackendInterface::class); $lock->method('acquire')->willReturn(TRUE);
    $policy = ['recipe' => ['id' => 'synthetic-only', 'revision' => str_repeat('a', 40), 'sha256' => str_repeat('b', 64)], 'tool_allowlist' => ['synthetic-tool'],
      'cost_policy' => ['id' => 'synthetic-cost', 'revision' => str_repeat('c', 40), 'currency' => 'USD', 'max_calls' => 2, 'max_cost_cents' => 100, 'reservation_cents' => 100]];
    $this->coordinator = new WorkerCoordinator($this->db, $this->clock, $lock, ['synthetic-cost' => $policy]);
    $entities = $this->createMock(EntityTypeManagerInterface::class); $storage = $this->createMock(EntityStorageInterface::class);
    $entities->method('getStorage')->with('proof_campaign')->willReturn($storage);
    $storage->method('create')->willReturnCallback(function (array $values) {
      $entity = $this->createMock(ProofCampaign::class); $id = NULL;
      $entity->method('save')->willReturnCallback(function () use ($values, &$id) { $id = (int) $this->db->insert('proof_campaign')->fields($values)->execute(); return 1; });
      $entity->method('id')->willReturnCallback(function () use (&$id) { return $id; });
      return $entity;
    });
    $this->admission = new FreshProofAdmission($this->db, $entities, $this->clock, $this->coordinator, $policy);
    $tx = $this->db->startTransaction(); $this->admission->admit(1, 1, 'portal.create', NULL); $tx->commitOrRelease(); unset($tx);
    $this->claim = $this->coordinator->claim($this->worker, [WorkerCapabilityPolicy::PROOF]);
    $slot = ['tool' => 'synthetic-tool', 'adapter' => 'synthetic-adapter', 'max_cost_cents' => 40, 'timeout_seconds' => 60, 'headroom_seconds' => 5];
    $this->catalog = ['synthetic-cost' => $policy + ['slots' => ['research' => $slot, 'direction-a' => $slot, 'repair-a' => $slot, 'expensive' => array_replace($slot, ['max_cost_cents' => 70])]]];
    $this->input = ['input_sha256' => hash('sha256', 'synthetic input'), 'prompt_sha256' => hash('sha256', 'synthetic prompt'), 'asset_ids' => [1]];
    $this->journal = $this->makeJournal(); $this->db->observed = [];
  }

  protected function tearDown(): void { new Settings([]); parent::tearDown(); }
  private function uuid(int $n): string { return '00000000-0000-0000-0000-' . str_pad((string) $n, 12, '0', STR_PAD_LEFT); }
  private function makeJournal(): ProofOperationJournal {
    return new ProofOperationJournal($this->db, $this->clock, $this->admission, $this->coordinator, $this->catalog,
      function (int $request, array $input): bool {
        self::assertFalse($this->db->inTransaction()); self::assertSame(1, $request); $this->inputChecks++;
        return $this->inputVerified; // Synthetic only: deliberately NOT real file-byte proof.
      }, function (array $identity, string $recorder, array $receipt): bool {
        self::assertFalse($this->db->inTransaction()); $this->receiptChecks++;
        if ($this->receiptThrows) throw new \RuntimeException('Synthetic verifier failure.');
        return $this->receiptVerified && in_array($recorder, ['synthetic-mac', 'synthetic-cloud'], TRUE);
      });
  }
  private function authorize(string $slot = 'research', ?array $input = NULL): array {
    return $this->journal->authorizeSubmission(1, 1, $this->worker, $this->claim['lease_token'], $this->claim['attempt'], [WorkerCapabilityPolicy::PROOF], $slot, $input ?? $this->input);
  }
  private function resume(): array {
    return $this->journal->resumeCheckpoint(1, 1, $this->worker, $this->claim['lease_token'], $this->claim['attempt'], [WorkerCapabilityPolicy::PROOF], 'research', $this->input);
  }
  private function receipt(array $permit): array {
    return ['operation_id' => $permit['operation_id'], 'input_sha256' => $this->input['input_sha256'], 'adapter' => 'synthetic-adapter', 'provider_request_id' => 'synthetic-receipt',
      'outcome' => 'succeeded', 'cost_status' => 'unknown', 'actual_cost_cents' => NULL, 'checkpoint' => [['name' => 'research-facts', 'sha256' => hash('sha256', 'synthetic result'), 'bytes' => 16]]];
  }
  private function rows(string $table): array { return $this->db->select($table, 't')->fields('t')->execute()->fetchAll(\PDO::FETCH_ASSOC); }
  private function unchangedRecords(): array {
    $state = [];
    foreach (['famtastic_project_request', 'proof_campaign', 'famtastic_job', 'famtastic_worker_claim', 'famtastic_worker_budget', 'famtastic_event', 'famtastic_request_asset'] as $t) $state[$t] = $this->rows($t);
    return $state;
  }
  private function reject(callable $call, string $message): void {
    $error = NULL;
    try { $call(); } catch (\Throwable $e) { $error = $e; }
    self::assertNotNull($error, 'Expected rejection: ' . $message);
    self::assertStringContainsString($message, $error->getMessage());
  }
  private function replaceClaim(): void {
    $this->now += 1831; $this->worker = 'synthetic-cloud';
    $claim = $this->coordinator->claim($this->worker, [WorkerCapabilityPolicy::PROOF]);
    if ($claim === NULL) { $this->now += 31; $claim = $this->coordinator->claim($this->worker, [WorkerCapabilityPolicy::PROOF]); }
    self::assertNotNull($claim); $this->claim = $claim;
  }

  public function testOnePermitPersistsUnknownBeforeReturningWithoutOtherMutation(): void {
    $before = $this->unchangedRecords(); $permit = $this->authorize();
    self::assertFalse($this->db->inTransaction()); self::assertSame('submit_once', $permit['status']);
    self::assertSame('submission_unknown', $this->rows('famtastic_proof_operation')[0]['state']);
    self::assertSame('reconciliation_required', $this->authorize()['status']);
    self::assertCount(1, $this->rows('famtastic_proof_operation')); self::assertSame($before, $this->unchangedRecords());
    self::assertStringNotContainsString($this->claim['lease_token'], json_encode($this->rows('famtastic_proof_operation')));
    $this->reject(fn() => $this->authorize('direction-a'), 'Unresolved paid operation');
  }
  public function testLostResponseCannotMintAnotherPermitAcrossClaimGeneration(): void {
    $first = $this->authorize(); $this->replaceClaim();
    self::assertSame(2, $this->claim['attempt']); self::assertSame('reconciliation_required', $this->authorize()['status']);
    self::assertSame($first['operation_id'], $this->authorize()['operation_id']);
    self::assertSame('synthetic-mac', json_decode($this->rows('famtastic_proof_operation')[0]['identity_wire'], TRUE)['producer_id']);
    self::assertCount(2, $this->rows('famtastic_worker_budget'));
  }
  public function testLateReceiptThenPositiveCurrentGenerationResumeRetainsProducerAndHolds(): void {
    $first = $this->authorize(); $this->replaceClaim(); $before = $this->unchangedRecords(); $receipt = $this->receipt($first);
    self::assertSame(['status' => 'receipt_recorded', 'duplicate' => FALSE], $this->journal->recordReceipt($first['operation_id'], $this->worker, $receipt));
    self::assertTrue($this->journal->recordReceipt($first['operation_id'], 'synthetic-mac', $receipt)['duplicate']);
    $resume = $this->resume(); self::assertSame('checkpoint', $resume['status']); self::assertSame($receipt, $resume['receipt']);
    self::assertSame('synthetic-mac', $resume['producer_id']); self::assertSame('synthetic-cloud', $resume['recorder_id']);
    self::assertNull($resume['receipt']['actual_cost_cents']); self::assertSame($before, $this->unchangedRecords());
    self::assertSame('checkpoint', $this->authorize()['status']); self::assertCount(1, $this->rows('famtastic_proof_operation'));
    self::assertSame('submit_once', $this->authorize('direction-a')['status']);
  }
  public function testCallLimitAcrossGenerationsAndKnownCostsNeverRefund(): void {
    foreach (['research', 'direction-a'] as $slot) {
      $p = $this->authorize($slot); $r = $this->receipt($p); $r['cost_status'] = 'verified'; $r['actual_cost_cents'] = 1;
      $this->journal->recordReceipt($p['operation_id'], $this->worker, $r);
    }
    $this->replaceClaim(); $this->reject(fn() => $this->authorize('repair-a'), 'call or cost bound');
    self::assertSame(200, array_sum(array_column($this->rows('famtastic_worker_budget'), 'reserved_cents')));
  }
  public function testCostCeilingsAcrossGenerationsDoNotUseLowerActualCostAsRefund(): void {
    $p = $this->authorize(); $this->journal->recordReceipt($p['operation_id'], $this->worker, $this->receipt($p)); $this->replaceClaim();
    $this->reject(fn() => $this->authorize('expensive'), 'call or cost bound'); self::assertCount(1, $this->rows('famtastic_proof_operation'));
  }
  #[DataProvider('authorityChanges')]
  public function testLiveAuthorityChangeDeniesSubmissionAndCheckpointUse(string $table, array $fields, string $message): void {
    $p = $this->authorize(); $this->journal->recordReceipt($p['operation_id'], $this->worker, $this->receipt($p));
    $this->db->update($table)->fields($fields)->execute(); $before = $this->unchangedRecords();
    $this->reject(fn() => $this->authorize('direction-a'), $message); $this->reject(fn() => $this->resume(), $message);
    self::assertSame($before, $this->unchangedRecords()); self::assertCount(1, $this->rows('famtastic_proof_operation'));
  }
  public static function authorityChanges(): iterable {
    yield 'brief' => ['famtastic_project_request', ['intake_data' => '{"primary_goal":"Changed"}'], 'differs from current input'];
    yield 'membership' => ['famtastic_membership', ['status' => 'inactive'], 'Verified account'];
    yield 'verified' => ['famtastic_customer', ['verified_at' => NULL], 'Verified account'];
    yield 'rights' => ['famtastic_request_asset', ['ai_transformation_consent' => 0], 'differs from current input'];
    yield 'withdrawal' => ['famtastic_request_asset', ['status' => 'withdrawn'], 'differs from current input'];
    yield 'asset-bytes-metadata' => ['famtastic_request_asset', ['sha256' => str_repeat('d', 64)], 'differs from current input'];
    yield 'campaign' => ['proof_campaign', ['studio_job_id' => 'other-job'], 'campaign differs'];
  }
  public function testLateVerifiedReceiptRecordsEvidenceDespiteRevokedRightsButCannotResume(): void {
    $p = $this->authorize(); $this->db->update('famtastic_request_asset')->fields(['status' => 'withdrawn'])->execute();
    $this->now += 181; $before = $this->unchangedRecords();
    $this->journal->recordReceipt($p['operation_id'], 'synthetic-cloud', $this->receipt($p));
    self::assertSame($before, $this->unchangedRecords()); $this->reject(fn() => $this->resume(), 'current input');
  }
  public function testChangedInputSameSlotCannotCreateAnotherOperation(): void {
    $this->authorize(); $changed = array_replace($this->input, ['prompt_sha256' => str_repeat('d', 64)]);
    $this->reject(fn() => $this->authorize('research', $changed), 'identity is immutable'); self::assertCount(1, $this->rows('famtastic_proof_operation'));
  }
  public function testUnknownSlotAndChangedSourceCatalogFailClosed(): void {
    $this->reject(fn() => $this->authorize('body-created-slot'), 'not reviewed');
    $this->authorize(); $this->catalog['synthetic-cost']['slots']['research']['adapter'] = 'another-adapter'; $this->journal = $this->makeJournal();
    $this->reject(fn() => $this->authorize(), 'Stored operation policy');
  }
  public function testUnconfiguredVerifiersAndUnverifiedBytesCannotAuthorize(): void {
    $this->journal = new ProofOperationJournal($this->db, $this->clock, $this->admission, $this->coordinator);
    $this->reject(fn() => $this->authorize(), 'unconfigured');
    $this->journal = $this->makeJournal(); $this->inputVerified = FALSE;
    $this->reject(fn() => $this->authorize(), 'input is unverified'); self::assertSame([], $this->rows('famtastic_proof_operation'));
  }
  public function testReceiptConflictUnverifiedAndThrowingVerifierCannotAlterStoredEvidence(): void {
    $p = $this->authorize(); $r = $this->receipt($p); $this->receiptVerified = FALSE;
    $this->reject(fn() => $this->journal->recordReceipt($p['operation_id'], $this->worker, $r), 'receipt is unverified');
    $this->receiptVerified = TRUE; $this->receiptThrows = TRUE;
    $this->reject(fn() => $this->journal->recordReceipt($p['operation_id'], $this->worker, $r), 'verifier failure');
    self::assertSame('submission_unknown', $this->rows('famtastic_proof_operation')[0]['state']);
    $this->receiptThrows = FALSE; $this->journal->recordReceipt($p['operation_id'], $this->worker, $r); $before = $this->rows('famtastic_proof_operation');
    $r['provider_request_id'] = 'different-receipt'; $this->reject(fn() => $this->journal->recordReceipt($p['operation_id'], $this->worker, $r), 'receipt conflict');
    self::assertSame($before, $this->rows('famtastic_proof_operation'));
  }
  public function testLeaseAfterLockWaitAndPostCommitDelayDenyPermit(): void {
    $this->db->afterMutex = function () { $this->now += 116; };
    $this->reject(fn() => $this->authorize(), 'lease window'); self::assertSame([], $this->rows('famtastic_proof_operation'));
    $this->coordinator->renew(1, $this->worker, $this->claim['lease_token'], [WorkerCapabilityPolicy::PROOF], 1);
    $this->db->afterMutex = function () { $this->db->transactionManager()->addPostTransactionCallback(function () { $this->now += 116; }); };
    $this->reject(fn() => $this->authorize(), 'lease window');
    self::assertSame('submission_unknown', $this->rows('famtastic_proof_operation')[0]['state']);
  }
  public function testOriginalMonthHoldCannotAuthorizeAfterRollover(): void {
    $this->db->update('famtastic_worker_budget')->fields(['month' => '2026-08'])->execute(); $before = $this->unchangedRecords();
    $this->reject(fn() => $this->authorize(), 'current-month budget hold'); self::assertSame($before, $this->unchangedRecords());
  }
  public function testOuterTransactionRejectedBeforeVerifierAndLockedSeamsRequireTransaction(): void {
    $tx = $this->db->startTransaction(); $this->reject(fn() => $this->authorize(), 'own root transaction'); $tx->rollBack(); unset($tx);
    self::assertSame(0, $this->inputChecks);
    $this->reject(fn() => $this->admission->lockCurrentBinding(1, $this->db), 'outer request transaction');
    $this->reject(fn() => $this->coordinator->lockOwnedProofClaim(1, $this->worker, $this->claim['lease_token'], [WorkerCapabilityPolicy::PROOF], 1, $this->db), 'active transaction');
  }
  #[DataProvider('commitFaults')]
  public function testCommitFailureNeverReturnsPermission(string $fault, bool $persists): void {
    $this->db->commitFault = $fault; $before = $this->unchangedRecords();
    $this->reject(fn() => $this->authorize(), 'Synthetic'); self::assertFalse($this->db->inTransaction());
    self::assertCount($persists ? 1 : 0, $this->rows('famtastic_proof_operation')); self::assertSame($before, $this->unchangedRecords());
    self::assertSame($persists ? 'reconciliation_required' : 'submit_once', $this->authorize()['status']);
  }
  public static function commitFaults(): iterable { yield 'before actual commit' => ['before_commit', FALSE]; yield 'after actual commit' => ['after_commit', TRUE]; }
  public function testPostCommitCallbackFailureLeavesUnknownAndNeverReturnsPermission(): void {
    $this->db->afterMutex = function () {
      $this->db->transactionManager()->addPostTransactionCallback(static function (bool $success) { self::assertTrue($success); throw new \RuntimeException('Synthetic post-commit callback failure.'); });
    };
    $this->reject(fn() => $this->authorize(), 'post-commit callback failure');
    self::assertFalse($this->db->inTransaction()); self::assertCount(1, $this->rows('famtastic_proof_operation'));
    self::assertSame('reconciliation_required', $this->authorize()['status']);
  }
  public function testIgnoredInsertAndReceiptCasFailureFailClosed(): void {
    $this->db->query('CREATE TRIGGER ignore_operation BEFORE INSERT ON {famtastic_proof_operation} BEGIN SELECT RAISE(IGNORE); END', [], ['allow_delimiter_in_query' => TRUE]);
    $this->reject(fn() => $this->authorize(), 'insert was not confirmed'); self::assertSame([], $this->rows('famtastic_proof_operation'));
    $this->db->query('DROP TRIGGER ignore_operation'); $p = $this->authorize();
    $this->db->query('CREATE TRIGGER ignore_receipt BEFORE UPDATE ON {famtastic_proof_operation} BEGIN SELECT RAISE(IGNORE); END', [], ['allow_delimiter_in_query' => TRUE]);
    $this->reject(fn() => $this->journal->recordReceipt($p['operation_id'], $this->worker, $this->receipt($p)), 'transaction rolled back');
    self::assertSame('submission_unknown', $this->rows('famtastic_proof_operation')[0]['state']);
  }
  public function testRightsChangedAfterInsertRollBackJournalAndInjectedMutation(): void {
    $this->db->query("CREATE TRIGGER withdraw_after_operation AFTER INSERT ON {famtastic_proof_operation} BEGIN UPDATE famtastic_request_asset SET status = 'withdrawn'; END", [], ['allow_delimiter_in_query' => TRUE]);
    $before = $this->unchangedRecords(); $this->reject(fn() => $this->authorize(), 'current input');
    self::assertSame([], $this->rows('famtastic_proof_operation')); self::assertSame($before, $this->unchangedRecords());
  }
  public function testRequestedLockOrderIsAuthorityThenMutexThenJournalAndBudget(): void {
    $this->authorize(); $events = $this->db->observed;
    $mutex = array_search(TRUE, array_map(fn($r) => str_starts_with($r['sql'], 'INSERT INTO {famtastic_worker_mutex}'), $events), TRUE);
    self::assertNotFalse($mutex);
    foreach (['famtastic_project_request', 'famtastic_membership', 'famtastic_request_asset'] as $table) {
      $reads = array_keys(array_filter($events, fn($r) => str_contains($r['sql'], $table) && $r['locking']));
      self::assertNotEmpty($reads); self::assertLessThan($mutex, min($reads));
    }
    foreach (['famtastic_job', 'famtastic_worker_claim', 'famtastic_proof_operation', 'famtastic_worker_budget'] as $table) {
      $reads = array_keys(array_filter($events, fn($r) => str_starts_with($r['sql'], 'SELECT') && str_contains($r['sql'], $table)));
      self::assertNotEmpty($reads); self::assertGreaterThan($mutex, min($reads));
      foreach ($reads as $i) self::assertTrue($events[$i]['locking']);
    }
  }

  #[DataProvider('invalidReceipts')]
  public function testReceiptGuardsExecuteAtJournalBoundary(string $case, string $message): void {
    $p = $this->authorize(); $r = $this->receipt($p); $before = $this->rows('famtastic_proof_operation');
    switch ($case) {
      case 'credential': $r['lease_token'] = 'synthetic-secret'; break;
      case 'url': $r['provider_request_id'] = 'https://example.test/receipt'; break;
      case 'path': $r['checkpoint'][0]['name'] = '../private'; break;
      case 'media': $r['checkpoint'][0]['base64'] = 'AAAA'; break;
      case 'size': $r['provider_request_id'] = str_repeat('x', 65536); break;
      case 'hash': $r['checkpoint'][0]['sha256'] = 'wrong'; break;
      case 'duplicate': $r['checkpoint'][] = $r['checkpoint'][0]; break;
      case 'unknown-cost': $r['actual_cost_cents'] = 0; break;
      case 'over-cost': $r['cost_status'] = 'verified'; $r['actual_cost_cents'] = 41; break;
      case 'adapter': $r['adapter'] = 'other-adapter'; break;
      case 'operation': $r['operation_id'] = str_repeat('d', 64); break;
    }
    $this->reject(fn() => $this->journal->recordReceipt($p['operation_id'], $this->worker, $r), $message);
    self::assertSame(0, $this->receiptChecks); self::assertSame($before, $this->rows('famtastic_proof_operation'));
  }
  public static function invalidReceipts(): iterable {
    foreach (['credential' => 'closed schema', 'url' => 'identifier', 'path' => 'identifier', 'media' => 'closed schema', 'size' => '64 KiB',
      'hash' => 'digest', 'duplicate' => 'Duplicate checkpoint', 'unknown-cost' => 'remain null', 'over-cost' => 'frozen operation',
      'adapter' => 'frozen operation', 'operation' => 'frozen operation'] as $case => $message) yield $case => [$case, $message];
  }
  public function testWrongGenerationTokenAndCapabilityNeverCreateOperation(): void {
    foreach ([['synthetic-mac', $this->claim['lease_token'], 2, [WorkerCapabilityPolicy::PROOF], 'generation mismatch'],
      ['synthetic-cloud', $this->claim['lease_token'], 1, [WorkerCapabilityPolicy::PROOF], 'Lease lost'],
      ['synthetic-mac', str_repeat('f', 64), 1, [WorkerCapabilityPolicy::PROOF], 'Lease lost'],
      ['synthetic-mac', $this->claim['lease_token'], 1, [WorkerCoordinator::CAPABILITY], 'capability rejected']] as [$worker, $token, $attempt, $grants, $message]) {
      $this->reject(fn() => $this->journal->authorizeSubmission(1, 1, $worker, $token, $attempt, $grants, 'research', $this->input), $message);
    }
    self::assertSame([], $this->rows('famtastic_proof_operation'));
  }
  public function testReceiptCannotCompleteProofOrReleaseBudgetAndKnownFailureCannotResubmit(): void {
    $p = $this->authorize(); $r = $this->receipt($p); $r['outcome'] = 'failed'; $r['checkpoint'] = [];
    $before = $this->unchangedRecords(); $this->journal->recordReceipt($p['operation_id'], $this->worker, $r);
    self::assertSame('known_failure', $this->authorize()['status']); self::assertSame($before, $this->unchangedRecords());
    $this->reject(fn() => $this->coordinator->finish(1, $this->worker, $this->claim['lease_token'], [], [WorkerCapabilityPolicy::PROOF], 1), 'future authoritative importer');
  }
  public function testUnclaimedAssetCannotHideBehindUnchangedAdmissionSnapshot(): void {
    // Input verifier is deliberately permissive; only the actual journal's
    // asset-use check can reject this absent ID. No snapshot-mismatch shortcut.
    $this->reject(fn() => $this->authorize('research', array_replace($this->input, ['asset_ids' => [2]])), 'explicit current rights');
    self::assertSame([], $this->rows('famtastic_proof_operation'));
  }
  public function testSeamsRejectASecondConnectionEvenWithAnActiveRoot(): void {
    $options = ['database' => ':memory:', 'prefix' => '', 'driver' => 'sqlite', 'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite'];
    $other = new ProofOperationConnection(ProofOperationConnection::open($options), $options);
    $tx = $this->db->startTransaction();
    $this->reject(fn() => $this->admission->lockCurrentBinding(1, $other), 'same database connection');
    $this->reject(fn() => $this->coordinator->lockOwnedProofClaim(1, $this->worker, $this->claim['lease_token'], [WorkerCapabilityPolicy::PROOF], 1, $other), 'same database connection');
    $tx->rollBack(); unset($tx);
  }
  public function testResumeMissingAndCorruptCheckpointsNeverCreatesPermission(): void {
    $this->reject(fn() => $this->resume(), 'checkpoint is missing'); self::assertSame([], $this->rows('famtastic_proof_operation'));
    $p = $this->authorize(); $this->journal->recordReceipt($p['operation_id'], $this->worker, $this->receipt($p));
    $this->db->update('famtastic_proof_operation')->fields(['receipt_sha256' => str_repeat('e', 64)])->execute();
    $this->reject(fn() => $this->resume(), 'receipt is corrupt');
    $this->reject(fn() => $this->authorize('direction-a'), 'receipt is corrupt'); self::assertCount(1, $this->rows('famtastic_proof_operation'));
  }
}
