<?php
declare(strict_types=1);
namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Core\Site\Settings;
use Drupal\famtastic_pipeline\Service\{FreshProofBinding, ManagedProofImportContract as Contract, ManagedProofImportReceipt as Receipt, WorkerCapabilityPolicy};
use Drupal\Tests\famtastic_pipeline\Unit\Fixtures\ManagedProofImportFixture;
use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/Fixtures/ManagedProofImportFixture.php';

/** Synthetic authority/entity doubles; actual importer/coordinator/journal/SQLite. */
final class ManagedProofImporterTest extends ManagedProofImportFixture {
  public function testAtomicImportRetainsUnknownBillingAndOriginalsWithoutQaOrNotice(): void {
    $holds = $this->rows('famtastic_worker_budget'); $operations = $this->rows('famtastic_proof_operation');
    $outbox = $this->rows('famtastic_notification_outbox'); $activity = $this->rows('famtastic_portal_activity');
    $files = $this->fileSnapshot(); $result = $this->import(); $r = $result['receipt'];
    self::assertFalse($this->db->inTransaction()); self::assertSame($result, Receipt::committed($this->db, 1));
    self::assertSame('proof-import:job:1', $result['receipt_id']); self::assertSame(Contract::hash($r), $result['receipt_sha256']);
    self::assertSame('owner_review', $this->rows('famtastic_project_request')[0]['proof_review_status']);
    self::assertNull($this->rows('famtastic_project_request')[0]['proof_approved_at']);
    self::assertNull($this->rows('famtastic_project_request')[0]['proof_notified_at']);
    self::assertSame('ready', $this->rows('proof_campaign')[0]['generation_status']);
    self::assertSame('proof_imported', $this->rows('famtastic_worker_claim')[0]['state']);
    self::assertSame(0, (int) $this->rows('famtastic_worker_claim')[0]['lease_until']);
    self::assertSame('completed', $this->rows('famtastic_job')[0]['status']);
    self::assertCount(1, $this->rows('famtastic_job')); self::assertCount(2, $this->rows('famtastic_event'));
    self::assertCount(1, $outbox); self::assertCount(1, $activity);
    self::assertSame($outbox, $this->rows('famtastic_notification_outbox')); self::assertSame($activity, $this->rows('famtastic_portal_activity'));
    self::assertCount(3, $this->rows('proof_variant'));
    self::assertCount(1, $this->rows('famtastic_build_run')); self::assertSame($holds, $this->rows('famtastic_worker_budget'));
    self::assertSame($operations, $this->rows('famtastic_proof_operation')); self::assertNull(json_decode($operations[0]['receipt_wire'], TRUE)['actual_cost_cents']);
    self::assertSame($files, $this->fileSnapshot()); self::assertSame(['automation:synthetic-mac'], $r['producer_ids']);
    self::assertSame('automation:synthetic-recovery', array_values($r['operations'])[0]['recorder_id']);
    self::assertStringNotContainsString($this->claim['lease_token'], json_encode($this->snapshot()));
    foreach ($this->rows('proof_variant') as $v) {
      self::assertStringStartsWith('managed-proof:mp-', $v['artifact_path']); self::assertSame('', $v['preview_url']);
      self::assertFalse(realpath($this->temporary . '/web/' . $v['artifact_path']));
      $dna = json_decode($v['design_dna'], TRUE); self::assertArrayHasKey('worker_description', $dna);
      self::assertArrayNotHasKey('asset_manifest', $dna); self::assertArrayNotHasKey('source_capture', $dna);
    }
    $this->reject(fn() => FreshProofBinding::assertGenericImportAllowed($this->db, 1), 'authoritative fenced importer');
    $this->reject(fn() => $this->package->read($result['receipt_id'], 'a', 'html'), 'resolver is unconfigured');
    $this->reject(fn() => $this->coordinator->finish(1, $this->worker, $this->claim['lease_token'], [], [WorkerCapabilityPolicy::PROOF], 1), 'authoritative importer');
  }

  #[DataProvider('liveChanges')]
  public function testLiveAuthorityChangesRejectWithoutAnyImportWrite(string $table, array $fields, string $error): void {
    $this->db->update($table)->fields($fields)->execute(); $before = $this->snapshot();
    $this->reject(fn() => $this->import(), $error); self::assertSame($before, $this->snapshot());
  }
  public static function liveChanges(): iterable {
    yield 'brief' => ['famtastic_project_request', ['intake_data' => '{"primary_goal":"changed"}'], 'current input'];
    yield 'rights' => ['famtastic_request_asset', ['ai_transformation_consent' => 0], 'current input'];
    yield 'withdrawal' => ['famtastic_request_asset', ['status' => 'withdrawn'], 'current input'];
    yield 'account' => ['famtastic_customer', ['verified_at' => NULL], 'Verified account'];
    yield 'member' => ['famtastic_membership', ['status' => 'inactive'], 'Verified account'];
    yield 'prospect ownership' => ['famtastic_customer_resource', ['organization_id' => 9], 'Verified account'];
    yield 'campaign' => ['proof_campaign', ['studio_job_id' => 'foreign-job'], 'campaign differs'];
    yield 'expired campaign' => ['proof_campaign', ['expires_at' => 1], 'initial pending proof'];
    yield 'archived' => ['famtastic_project_request', ['customer_archived_at' => 1], 'initial pending proof'];
    yield 'job tamper' => ['famtastic_job', ['payload' => '{}'], 'job binding changed'];
  }
  public function testDefaultDependenciesAndProvenanceRemainClosed(): void {
    $this->importer = $this->makeImporter(FALSE); $before = $this->snapshot();
    $this->reject(fn() => $this->import(), 'unconfigured');
    $this->importer = $this->makeImporter(); $this->provenanceAllowed = FALSE;
    $this->reject(fn() => $this->import(), 'provenance denied'); self::assertSame($before, $this->snapshot());
  }
  public function testExplicitTimestampsAndFrozenProvenanceCorrelationRequired(): void {
    unset($this->provenance['build_dna']['run']['started_at']); $before = $this->snapshot();
    $this->reject(fn() => $this->import(), 'explicit UTC timestamps'); self::assertSame($before, $this->snapshot());
  }
  public function testForeignPackageHashAndProducerNamespaceReject(): void {
    $this->provenance['package_manifest_sha256'] = str_repeat('e', 64); $before = $this->snapshot();
    $this->reject(fn() => $this->import(), 'different input'); self::assertSame($before, $this->snapshot());
    $this->provenance['package_manifest_sha256'] = $this->preparedPackage['package_manifest_sha256'];
    $this->provenance['producer_ids'] = ['synthetic-mac'];
    $this->reject(fn() => $this->import(), 'canonical producer identity'); self::assertSame($before, $this->snapshot());
  }
  public function testExpiredLeaseAfterPreverificationRejectsAndRightsChangesAfterWritersRollBack(): void {
    $this->afterVerification = function () { $this->now += 181; }; $before = $this->snapshot();
    $this->reject(fn() => $this->import(), 'Lease lost'); self::assertSame($before, $this->snapshot());
    $this->now -= 181; $this->afterVerification = NULL;
    $this->afterVariantSaved = function () { $this->db->update('famtastic_request_asset')->fields(['status' => 'withdrawn'])->execute(); };
    $this->reject(fn() => $this->import(), 'current input'); self::assertSame($before, $this->snapshot());
  }
  public function testDeadlineAfterWritesRejectsWholeRoot(): void {
    $before = $this->snapshot(); $this->afterVariantSaved = function () { $this->now += 1800; };
    $this->reject(fn() => $this->import(), 'execution deadline'); self::assertSame($before, $this->snapshot());
  }
  public function testNewGenerationMayImportPriorTerminalEvidenceButOldAttemptCannot(): void {
    $old = $this->claim; $this->now += 1831;
    self::assertNull($this->coordinator->claim('synthetic-cloud', [WorkerCapabilityPolicy::PROOF]));
    $this->now += 31; $next = $this->coordinator->claim('synthetic-cloud', [WorkerCapabilityPolicy::PROOF]); self::assertNotNull($next);
    $before = $this->snapshot(); $this->reject(fn() => $this->import(), 'generation mismatch'); self::assertSame($before, $this->snapshot());
    $this->claim = $next; $this->worker = 'synthetic-cloud'; $r = $this->import()['receipt'];
    self::assertSame(2, $r['attempt']); self::assertSame('automation:synthetic-cloud', $r['worker_id']);
    self::assertSame(['automation:synthetic-mac'], $r['producer_ids']); self::assertCount(2, $this->rows('famtastic_worker_budget'));
    self::assertStringNotContainsString($old['lease_token'], Contract::wire($r));
  }
  public function testRootOnlyAndUnresolvedOperationsCannotComplete(): void {
    $tx = $this->db->startTransaction(); $before = $this->snapshot();
    $this->reject(fn() => $this->import(), 'own root transaction'); self::assertSame($before, $this->snapshot()); $tx->rollBack(); unset($tx);
    $this->db->update('famtastic_proof_operation')->fields(['state' => 'submission_unknown', 'receipt_wire' => '', 'receipt_sha256' => '', 'recorder_id' => ''])->execute();
    $before = $this->snapshot(); $this->reject(fn() => $this->import(), 'terminal operation evidence'); self::assertSame($before, $this->snapshot());
  }
  public function testRequiredSuccessPolicyAndExactJournalBytesCannotBeWorkerReplaced(): void {
    $this->provenance['operations'][array_key_first($this->provenance['operations'])]['receipt_sha256'] = str_repeat('e', 64);
    $before = $this->snapshot(); $this->reject(fn() => $this->import(), 'locked journal evidence'); self::assertSame($before, $this->snapshot());
    $this->provenance['operations'] = $this->operationFacts();
    $this->completion['synthetic-cost']['required_success_slots'][] = 'optional-repair'; $this->importer = $this->makeImporter();
    $this->reject(fn() => $this->import(), 'completion requirements'); self::assertSame($before, $this->snapshot());
  }
  public function testOptionalKnownFailureWithUnknownBillingDoesNotInventCostOrRefund(): void {
    $p = $this->journal->authorizeSubmission(1, 1, $this->worker, $this->claim['lease_token'], 1, [WorkerCapabilityPolicy::PROOF], 'optional-repair', $this->provenance['input']);
    $r = array_replace($this->operationReceipt, ['operation_id' => $p['operation_id'], 'provider_request_id' => 'synthetic-failed', 'outcome' => 'failed', 'checkpoint' => []]);
    $this->journal->recordReceipt($p['operation_id'], 'synthetic-recovery', $r);
    $this->provenance['operations'] = $this->operationFacts(); $before = $this->rows('famtastic_proof_operation'); $holds = $this->rows('famtastic_worker_budget');
    self::assertCount(2, $this->import()['receipt']['operations']); self::assertSame($before, $this->rows('famtastic_proof_operation'));
    self::assertSame($holds, $this->rows('famtastic_worker_budget'));
  }

  #[DataProvider('writerFailures')]
  public function testEveryDatabaseWriterAndCompletionCasRollsBack(string $table, string $operation, string $when, string $action, string $error): void {
    $this->db->query("CREATE TRIGGER import_fault $when $operation ON {$table} BEGIN $action; END", [], ['allow_delimiter_in_query' => TRUE]);
    $before = $this->snapshot(); $files = $this->fileSnapshot();
    $this->reject(fn() => $this->import(), $error); self::assertFalse($this->db->inTransaction()); self::assertSame($before, $this->snapshot()); self::assertSame($files, $this->fileSnapshot());
  }
  public static function writerFailures(): iterable {
    yield 'build writer' => ['famtastic_build_run', 'INSERT', 'BEFORE', "SELECT RAISE(ABORT, 'synthetic build write')", 'synthetic build write'];
    yield 'variant writer' => ['proof_variant', 'INSERT', 'BEFORE', "SELECT RAISE(ABORT, 'synthetic variant write')", 'synthetic variant write'];
    yield 'campaign CAS' => ['proof_campaign', 'UPDATE', 'BEFORE', 'SELECT RAISE(IGNORE)', 'writer CAS failed'];
    yield 'request CAS' => ['famtastic_project_request', 'UPDATE', 'BEFORE', 'SELECT RAISE(IGNORE)', 'writer CAS failed'];
    yield 'receipt insert' => ['famtastic_event', 'INSERT', 'BEFORE', 'SELECT RAISE(IGNORE)', 'receipt is missing'];
    yield 'claim CAS' => ['famtastic_worker_claim', 'UPDATE', 'BEFORE', 'SELECT RAISE(IGNORE)', 'Worker claim changed'];
    yield 'job CAS after claim' => ['famtastic_job', 'UPDATE', 'BEFORE', 'SELECT RAISE(IGNORE)', 'Worker job changed'];
    yield 'forged persisted variant' => ['famtastic_event', 'INSERT', 'AFTER', "UPDATE proof_variant SET artifact_path = 'web/proofs/forged.html'", 'variant bytes differ'];
    yield 'forged persisted Build DNA' => ['famtastic_event', 'INSERT', 'AFTER', "UPDATE famtastic_build_run SET output_manifest = '{}'", 'Build DNA differs'];
  }
  public function testBareOrForgedReceiptCannotCompleteTheClaim(): void {
    foreach ([FALSE, TRUE] as $forged) {
      $tx = $this->db->startTransaction(); $this->admission->lockCurrentBinding(1, $this->db); $before = $this->snapshot();
      if ($forged) $this->db->insert('famtastic_event')->fields(['event_key' => Contract::key(1), 'event_type' => Contract::EVENT, 'payload' => '{"ok":true}', 'occurred_at' => $this->now, 'recorded_at' => $this->now])->execute();
      $this->reject(fn() => $this->coordinator->completeProofImportLocked(1, $this->worker, $this->claim['lease_token'], [WorkerCapabilityPolicy::PROOF], 1, str_repeat('a', 64), $this->db), $forged ? 'closed schema' : 'receipt is missing');
      $tx->rollBack(); unset($tx); self::assertSame($before, $this->snapshot());
    }
  }
  #[DataProvider('commitFaults')]
  public function testRootCommitUncertaintyRequiresHistoricalAcknowledgment(string $fault, bool $persisted): void {
    $before = $this->snapshot(); $this->db->commitFault = $fault;
    $this->reject(fn() => $this->import(), 'Synthetic'); self::assertFalse($this->db->inTransaction());
    if (!$persisted) { self::assertSame($before, $this->snapshot()); return; }
    $facts = Receipt::committed($this->db, 1); $after = $this->snapshot();
    self::assertSame($facts, $this->importer->acknowledgeCommittedImport(1, $facts['receipt_sha256'], 'synthetic-authorized-account'));
    self::assertSame($after, $this->snapshot());
  }
  public static function commitFaults(): iterable { yield ['before_commit', FALSE]; yield ['after_commit', TRUE]; }
  public function testPostCommitCallbackFailureCannotReturnImportSuccessOrReplayWrites(): void {
    $this->db->afterMutex = function () { $this->db->transactionManager()->addPostTransactionCallback(static function (bool $success) {
      self::assertTrue($success); throw new \RuntimeException('Synthetic post-commit callback failure');
    }); };
    $this->reject(fn() => $this->import(), 'post-commit callback failure'); self::assertFalse($this->db->inTransaction());
    $facts = Receipt::committed($this->db, 1); $before = $this->snapshot();
    self::assertSame($facts, $this->importer->acknowledgeCommittedImport(1, $facts['receipt_sha256'], 'synthetic-authorized-account'));
    $this->reject(fn() => $this->import(), 'current input'); self::assertSame($before, $this->snapshot());
  }
  public function testHistoricalAcknowledgmentAfterSelectionDoesNotWeakenFreshBindingOrGrantReads(): void {
    $facts = $this->import(); new Settings([]);
    $this->db->update('famtastic_project_request')->fields(['proof_review_status' => 'selected', 'selected_proof_direction' => 'b', 'selected_proof_at' => $this->now,
      'proof_approved_at' => $this->now, 'proof_approved_by_uid' => 0, 'proof_notified_at' => $this->now, 'intake_data' => '{"later":"changed"}'])->execute();
    $this->db->update('proof_campaign')->fields(['selected_variant' => $facts['receipt']['variants']['b']['id']])->execute();
    $this->db->update('famtastic_request_asset')->fields(['status' => 'withdrawn'])->execute(); $before = $this->snapshot();
    self::assertSame($facts, $this->importer->acknowledgeCommittedImport(1, $facts['receipt_sha256'], 'synthetic-authorized-account'));
    self::assertSame($facts, Receipt::committed($this->db, 1));
    $this->reject(fn() => $this->import(), 'current input');
    $this->reject(fn() => $this->importer->acknowledgeCommittedImport(1, str_repeat('f', 64), 'synthetic-authorized-account'), 'receipt identity differs');
    $this->reject(fn() => $this->importer->acknowledgeCommittedImport(1, $facts['receipt_sha256'], 'foreign-account'), 'unauthorized');
    $this->reject(fn() => $this->makeImporter(TRUE, FALSE)->acknowledgeCommittedImport(1, $facts['receipt_sha256'], 'synthetic-authorized-account'), 'unconfigured');
    $this->reject(fn() => $this->package->read($facts['receipt_id'], 'a', 'html'), 'resolver is unconfigured'); self::assertSame($before, $this->snapshot());
  }
  #[DataProvider('historyTampering')]
  public function testHistoricalReceiptRequiresActualUnchangedRows(string $table, array $fields, string $error): void {
    $facts = $this->import(); $this->db->update($table)->fields($fields)->execute(); $before = $this->snapshot();
    $this->reject(fn() => $this->importer->acknowledgeCommittedImport(1, $facts['receipt_sha256'], 'synthetic-authorized-account'), $error);
    self::assertSame($before, $this->snapshot());
  }
  public static function historyTampering(): iterable {
    yield 'variant' => ['proof_variant', ['design_dna' => '{}'], 'variant bytes differ'];
    yield 'build' => ['famtastic_build_run', ['provider' => 'forged'], 'Build DNA differs'];
    yield 'journal' => ['famtastic_proof_operation', ['receipt_wire' => '{}'], 'journal bytes differ'];
    yield 'job completion' => ['famtastic_job', ['result' => '{}'], 'completion is not committed'];
    yield 'claim hash' => ['famtastic_worker_claim', ['result_sha256' => str_repeat('e', 64)], 'completion is not committed'];
    yield 'account association' => ['famtastic_project_request', ['customer_id' => 2], 'request association differs'];
  }
  private function fileSnapshot(): array {
    $snapshot = [];
    foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->temporary, \FilesystemIterator::SKIP_DOTS)) as $file) {
      $snapshot[substr($file->getPathname(), strlen($this->temporary))] = [hash_file('sha256', $file->getPathname()), $file->getSize(), $file->getPerms() & 0777];
    }
    ksort($snapshot); return $snapshot;
  }
}
