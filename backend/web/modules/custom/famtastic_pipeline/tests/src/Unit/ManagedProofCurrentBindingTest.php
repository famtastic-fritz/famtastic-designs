<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Core\Session\AccountInterface;
use Drupal\famtastic_pipeline\Service\{FreshProofBinding, FreshProofInput, ManagedProofCurrentBinding as Binding,
  ManagedProofImportContract as Contract, ManagedProofImportReceipt as Receipt};
use Drupal\Tests\famtastic_pipeline\Unit\Fixtures\ManagedProofImportFixture;
use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/ManagedProofReaderTest.php';

/**
 * Real importer, committed receipt, SQLite and private package fixture. Producer
 * provenance, user setup and reviewer/release identity are explicitly synthetic.
 * Observed FOR UPDATE/order is NOT independent-connection MariaDB contention.
 * No QA/release writer, provider, activation, live DB or transport is exercised.
 */
final class ManagedProofCurrentBindingTest extends ManagedProofImportFixture {
  use ManagedProofReaderTestSetup;

  protected function setUp(): void {
    parent::setUp();
    $this->installSyntheticReaderUser();
  }

  public function testReadReturnsActualUnnormalizedFactsWithoutLocksOrWrites(): void {
    $receipt = $this->import(); $before = $this->state(); $this->db->observed = [];
    $current = Binding::read($this->db, 1, $this->now);
    self::assertSame($receipt, $current['committed']);
    self::assertSame('owner_review', $current['request']['proof_review_status']);
    self::assertSame('synthetic@example.test', $current['customer']['email']);
    self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $current['authority_sha256']);
    self::assertSame(['request', 'customer', 'asset_snapshot', 'authority_sha256', 'committed', 'admission_event'], array_keys($current));
    self::assertNotEmpty($this->db->observed);
    foreach ($this->db->observed as $query) self::assertFalse($query['locking'], $query['sql']);
    self::assertSame(FreshProofInput::assets($this->db, $current['request'], FALSE), $current['asset_snapshot']);
    // Raw facts are intentionally NOT a freshness/lifecycle/read grant.
    $this->denied(fn() => FreshProofBinding::read($this->db, $current['admission_event'], $current['request']), 'current input');
    self::assertSame($before, $this->state()); self::assertFalse($this->db->inTransaction());
  }

  public function testPendingLocksInSharedOrderAndLeavesRootOwnershipAndAllRowsWithCaller(): void {
    $receipt = $this->import(); $read = Binding::read($this->db, 1, $this->now); $before = $this->state();
    $this->db->observed = []; $mutexCalls = 0;
    $tx = $this->db->startTransaction();
    $this->db->afterMutex = function () use (&$mutexCalls): void {
      ++$mutexCalls;
      self::assertSame(1, $this->db->transactionManager()->stackDepth(), 'No helper-owned root or savepoint.');
    };
    try {
      $locked = Binding::lockPending($this->db, 1, $this->now, $receipt['receipt_sha256']);
      self::assertSame($read, $locked);
      self::assertSame(1, $mutexCalls); self::assertSame(1, $this->db->transactionManager()->stackDepth());
      $queries = $this->db->observed;
      $previous = -1;
      foreach (['famtastic_project_request', 'famtastic_customer', 'users_field_data', 'famtastic_organization',
        'famtastic_membership', 'famtastic_customer_resource', 'famtastic_prospect', 'famtastic_request_asset',
        'famtastic_worker_mutex', 'famtastic_job', 'famtastic_worker_claim', 'famtastic_event', 'proof_campaign',
        'proof_variant', 'famtastic_build_run', 'famtastic_proof_operation'] as $table) {
        $position = $this->firstLock($queries, $table);
        self::assertGreaterThan($previous, $position, 'First lock order: ' . $table); $previous = $position;
      }
      foreach ($queries as $query) {
        if (str_contains($query['sql'], 'famtastic_job') || str_contains($query['sql'], 'famtastic_worker_claim')) self::assertTrue($query['locking'], $query['sql']);
      }
      self::assertSame($locked, Binding::lockPending($this->db, 1, $this->now, $receipt['receipt_sha256']), 'Repeat pending validation is identical.');
      self::assertSame($before, $this->state());
    }
    finally { $tx->rollBack(); unset($tx); }
    self::assertFalse($this->db->inTransaction()); self::assertSame($before, $this->state());
  }

  public function testLazyMutexInsertionRollsBackOnlyWhenCallerRollsBack(): void {
    $receipt = $this->import();
    $this->db->delete('famtastic_worker_mutex')->condition('id', 1)->execute();
    $before = $this->state(); $tx = $this->db->startTransaction();
    try {
      Binding::lockPending($this->db, 1, $this->now, $receipt['receipt_sha256']);
      self::assertCount(1, $this->rows('famtastic_worker_mutex'));
      self::assertTrue($this->db->inTransaction());
    }
    finally { $tx->rollBack(); unset($tx); }
    self::assertSame($before, $this->state());
  }

  public function testTransactionBoundariesRejectBeforeQueries(): void {
    $receipt = $this->import(); $this->db->observed = [];
    $this->denied(fn() => Binding::lockPending($this->db, 1, $this->now, $receipt['receipt_sha256']), 'active transaction');
    $this->denied(fn() => Receipt::committedLocked($this->db, 1, $receipt['receipt_sha256'], 1), 'active transaction');
    $this->denied(fn() => Receipt::pendingLocked($this->db, 1, $receipt['receipt_sha256']), 'active transaction');
    self::assertSame([], $this->db->observed);
    $tx = $this->db->startTransaction();
    try {
      $this->denied(fn() => Binding::read($this->db, 1, $this->now), 'outside transactions');
      $this->denied(fn() => Receipt::committed($this->db, 1), 'committed connection');
      self::assertSame([], $this->db->observed); self::assertSame(1, $this->db->transactionManager()->stackDepth());
    }
    finally { $tx->rollBack(); unset($tx); }
  }

  public function testExplicitReplicaTargetCannotSupplyCurrentAuthority(): void {
    $receipt = $this->import(); $this->db->setTarget('replica'); $this->db->observed = [];
    $this->denied(fn() => Binding::read($this->db, 1, $this->now), 'primary database');
    $tx = $this->db->startTransaction();
    try {
      $this->denied(fn() => Binding::lockPending($this->db, 1, $this->now, $receipt['receipt_sha256']), 'primary database');
      self::assertSame([], $this->db->observed);
    }
    finally { $tx->rollBack(); unset($tx); }
  }

  public function testCommittedLockedIsDistinctFromPendingCompletionAndPreservesBothOldApis(): void {
    $receipt = $this->import(); $before = $this->state(); $tx = $this->db->startTransaction();
    try {
      Binding::lockPending($this->db, 1, $this->now, $receipt['receipt_sha256']);
      self::assertSame($receipt, Receipt::committedLocked($this->db, 1, $receipt['receipt_sha256'], 1));
      $this->denied(fn() => Receipt::pendingLocked($this->db, 1, $receipt['receipt_sha256']), 'pending fenced completion');
      // Reconstruct only the pre-completion state inside this disposable rollback.
      $this->db->update('famtastic_job')->fields(['status' => 'worker_running'])->condition('id', 1)->execute();
      $this->db->update('famtastic_worker_claim')->fields(['state' => 'leased'])->condition('job_id', 1)->execute();
      self::assertSame($receipt, Receipt::pendingLocked($this->db, 1, $receipt['receipt_sha256']));
      $this->denied(fn() => Receipt::committedLocked($this->db, 1, $receipt['receipt_sha256'], 1), 'not committed');
    }
    finally { $tx->rollBack(); unset($tx); }
    self::assertSame($receipt, Receipt::committed($this->db, 1, $receipt['receipt_sha256'])); self::assertSame($before, $this->state());
  }

  public function testWrongExpectedRequestStopsBeforeAnyDifferentUpstreamLock(): void {
    $receipt = $this->import(); $tx = $this->db->startTransaction();
    try {
      Binding::lockPending($this->db, 1, $this->now, $receipt['receipt_sha256']);
      $this->db->observed = [];
      $this->denied(fn() => Receipt::committedLocked($this->db, 1, $receipt['receipt_sha256'], 2), 'receipt identity differs');
      foreach ($this->db->observed as $query) {
        self::assertStringNotContainsString('famtastic_project_request', $query['sql']);
        self::assertStringNotContainsString('proof_campaign', $query['sql']);
      }
    }
    finally { $tx->rollBack(); unset($tx); }
  }

  #[DataProvider('mutations')]
  public function testLockedPendingRejectsCurrentAuthorityAndFrozenInputMutations(string $table, array $fields, string $error): void {
    $receipt = $this->import(); $this->db->update($table)->fields($fields)->execute(); $before = $this->state();
    $tx = $this->db->startTransaction();
    try {
      $this->denied(fn() => Binding::lockPending($this->db, 1, $this->now, $receipt['receipt_sha256']), $error);
      self::assertTrue($this->db->inTransaction()); self::assertSame(1, $this->db->transactionManager()->stackDepth());
      self::assertSame($before, $this->state());
    }
    finally { $tx->rollBack(); unset($tx); }
    self::assertSame($before, $this->state());
  }

  public static function mutations(): iterable {
    // Reuse the actual Reader's restriction inventory; changed metadata is not a
    // denial for a fresh read, but must change its fingerprint (tested separately).
    foreach (ManagedProofReaderTest::liveMutations() as $name => $case) {
      if ($name !== 'changed account metadata') yield $name => $case;
    }
    yield 'anonymous customer' => ['famtastic_customer', ['uid' => 0], 'account authority'];
    yield 'another Drupal user' => ['famtastic_customer', ['uid' => 999], 'account authority'];
    yield 'notified lifecycle' => ['famtastic_project_request', ['proof_review_status' => 'notified'], 'not pending'];
    yield 'revision lifecycle' => ['famtastic_project_request', ['proof_review_status' => 'revision_requested'], 'not pending'];
    yield 'proof input reset' => ['famtastic_project_request', ['proof_review_status' => 'not_started'], 'not pending'];
    yield 'request project type' => ['famtastic_project_request', ['project_type' => 'online_store'], 'current input'];
    yield 'request public ID' => ['famtastic_project_request', ['public_id' => '00000000-0000-0000-0000-000000000099'], 'campaign association'];
    yield 'submitted timestamp' => ['famtastic_project_request', ['submitted_at' => 1], 'current input'];
    yield 'asset file ID' => ['famtastic_request_asset', ['file_id' => 8], 'asset authority'];
    yield 'asset MIME' => ['famtastic_request_asset', ['mime_type' => 'image/png'], 'asset authority'];
    yield 'asset recorded size' => ['famtastic_request_asset', ['size_bytes' => 10], 'asset authority'];
    yield 'asset likeness policy' => ['famtastic_request_asset', ['likeness_consent_version' => 'changed'], 'asset authority'];
    yield 'asset likeness timestamp' => ['famtastic_request_asset', ['likeness_consent_at' => NULL], 'asset authority'];
    yield 'claim still leased' => ['famtastic_worker_claim', ['state' => 'leased'], 'not committed'];
    yield 'job still running' => ['famtastic_job', ['status' => 'worker_running'], 'not committed'];
    yield 'claim reservation' => ['famtastic_worker_claim', ['reservation_cents' => 99], 'job binding changed'];
  }

  #[DataProvider('missingAuthority')]
  public function testMissingAuthorityCannotBeReplacedByReceipt(string $table, string $error): void {
    $receipt = $this->import(); $this->db->delete($table)->execute(); $before = $this->state();
    $tx = $this->db->startTransaction();
    try { $this->denied(fn() => Binding::lockPending($this->db, 1, $this->now, $receipt['receipt_sha256']), $error); }
    finally { $tx->rollBack(); unset($tx); }
    self::assertSame($before, $this->state());
  }

  public static function missingAuthority(): iterable {
    foreach (['famtastic_customer', 'users_field_data', 'famtastic_organization', 'famtastic_membership', 'famtastic_customer_resource', 'famtastic_prospect'] as $table) yield $table => [$table, 'account authority'];
    yield 'request' => ['famtastic_project_request', 'not eligible'];
    yield 'campaign' => ['proof_campaign', 'campaign association'];
    yield 'asset inventory' => ['famtastic_request_asset', 'asset authority'];
    yield 'claim' => ['famtastic_worker_claim', 'admission is missing'];
    yield 'job' => ['famtastic_job', 'admission is missing'];
  }

  public function testAddedOrRemovedAssetsRequireExactSnapshotAndRequestFirstLock(): void {
    $receipt = $this->import(); $asset = $this->rows('famtastic_request_asset')[0];
    $asset['id'] = 2; $asset['public_id'] = '00000000-0000-0000-0000-000000000088';
    $asset['file_id'] = 8; $asset['sha256'] = hash('sha256', 'distinct synthetic reference');
    $this->db->insert('famtastic_request_asset')->fields($asset)->execute();
    $tx = $this->db->startTransaction();
    try { $this->denied(fn() => Binding::lockPending($this->db, 1, $this->now, $receipt['receipt_sha256']), 'asset authority'); }
    finally { $tx->rollBack(); unset($tx); }
    $this->db->delete('famtastic_request_asset')->execute(); $this->db->observed = [];
    $tx = $this->db->startTransaction();
    try {
      $this->denied(fn() => Binding::lockPending($this->db, 1, $this->now, $receipt['receipt_sha256']), 'asset authority');
      self::assertLessThan($this->firstLock($this->db->observed, 'famtastic_request_asset'), $this->firstLock($this->db->observed, 'famtastic_project_request'));
    }
    finally { $tx->rollBack(); unset($tx); }
  }

  public function testMissingReceiptAndWrongHashNeverAuthorizeLocalReviewReversal(): void {
    $receipt = $this->import(); $before = $this->state(); $tx = $this->db->startTransaction();
    try {
      $this->denied(fn() => Binding::lockPending($this->db, 1, $this->now, str_repeat('0', 64)), 'receipt identity differs');
      $this->db->delete('famtastic_event')->condition('event_key', Contract::key(1))->execute();
      $this->denied(fn() => Binding::lockPending($this->db, 1, $this->now, $receipt['receipt_sha256']), 'receipt is missing');
      self::assertSame('owner_review', $this->rows('famtastic_project_request')[0]['proof_review_status']);
    }
    finally { $tx->rollBack(); unset($tx); }
    self::assertSame($before, $this->state());
  }

  #[DataProvider('afterMutexMutations')]
  public function testReceiptGraphUsesCurrentLockedRowsAfterMutex(string $table, array $fields, string $error): void {
    $receipt = $this->import(); $before = $this->state(); $tx = $this->db->startTransaction();
    $this->db->afterMutex = function () use ($table, $fields): void { $this->db->update($table)->fields($fields)->execute(); };
    try { $this->denied(fn() => Binding::lockPending($this->db, 1, $this->now, $receipt['receipt_sha256']), $error); }
    finally { $tx->rollBack(); unset($tx); }
    self::assertSame($before, $this->state());
  }

  public static function afterMutexMutations(): iterable {
    yield 'job' => ['famtastic_job', ['result' => '{}'], 'not committed'];
    yield 'claim' => ['famtastic_worker_claim', ['state' => 'leased'], 'not committed'];
    yield 'campaign' => ['proof_campaign', ['status' => 'closed'], 'campaign is unavailable'];
    yield 'variant' => ['proof_variant', ['design_dna__value' => '{}'], 'variant bytes differ'];
    yield 'build' => ['famtastic_build_run', ['provider' => 'forged'], 'Build DNA differs'];
    yield 'operation' => ['famtastic_proof_operation', ['receipt_wire' => '{}'], 'journal bytes differ'];
  }

  public function testSecondPendingCheckObservesChangedAuthorityAndExpiryWithoutNewTransaction(): void {
    $receipt = $this->import(); $before = $this->state(); $tx = $this->db->startTransaction();
    try {
      $first = Binding::lockPending($this->db, 1, $this->now, $receipt['receipt_sha256']);
      $this->db->update('famtastic_customer')->fields(['email' => 'changed@example.test'])->condition('id', 1)->execute();
      $second = Binding::lockPending($this->db, 1, $this->now, $receipt['receipt_sha256']);
      self::assertNotSame($first['authority_sha256'], $second['authority_sha256']); self::assertNotSame($first, $second);
      self::assertSame('changed@example.test', $second['customer']['email']);
      self::assertSame($first['committed'], $second['committed']);
      $expires = (int) $this->rows('proof_campaign')[0]['expires_at'];
      $this->denied(fn() => Binding::lockPending($this->db, 1, $expires, $receipt['receipt_sha256']), 'campaign is unavailable');
      $this->db->update('users_field_data')->fields(['status' => 0])->condition('uid', 1)->execute();
      $this->denied(fn() => Binding::lockPending($this->db, 1, $this->now, $receipt['receipt_sha256']), 'account authority');
      self::assertSame(1, $this->db->transactionManager()->stackDepth());
    }
    finally { $tx->rollBack(); unset($tx); }
    self::assertSame($before, $this->state());
  }

  public function testClockCannotPrecedeActualImportAndExpiryIsExclusive(): void {
    $receipt = $this->import(); $expires = (int) $this->rows('proof_campaign')[0]['expires_at']; $tx = $this->db->startTransaction();
    try {
      $this->denied(fn() => Binding::lockPending($this->db, 1, $receipt['receipt']['imported_at'] - 1, $receipt['receipt_sha256']), 'clock precedes');
      self::assertSame($receipt, Binding::lockPending($this->db, 1, $receipt['receipt']['imported_at'], $receipt['receipt_sha256'])['committed']);
      self::assertSame($receipt, Binding::lockPending($this->db, 1, $expires - 1, $receipt['receipt_sha256'])['committed']);
      $this->denied(fn() => Binding::lockPending($this->db, 1, $expires, $receipt['receipt_sha256']), 'campaign is unavailable');
    }
    finally { $tx->rollBack(); unset($tx); }
  }

  public function testDbFactsDoNotTryToOpenPackageFiles(): void {
    $receipt = $this->import();
    // These exact fixture-owned directories are restored even if an assertion
    // fails. This models unavailable bytes; the helper cannot grant artifact reads.
    self::assertTrue(rename($this->temporary . '/packages', $this->temporary . '/packages-unavailable'));
    try {
      self::assertSame($receipt, Binding::read($this->db, 1, $this->now)['committed']);
      $tx = $this->db->startTransaction();
      try { self::assertSame($receipt, Binding::lockPending($this->db, 1, $this->now, $receipt['receipt_sha256'])['committed']); }
      finally { $tx->rollBack(); unset($tx); }
    }
    finally { self::assertTrue(rename($this->temporary . '/packages-unavailable', $this->temporary . '/packages')); }
  }

  public function testReleasedRawFactsNeverNormalizeBeforeReaderVerifiesRelease(): void {
    $receipt = $this->import();
    // Explicit SYNTHETIC release flags, not a stored QA/release decision.
    $this->db->update('famtastic_project_request')->fields(['proof_review_status' => 'customer_ready', 'proof_approved_at' => $this->now, 'project_id' => 4])->condition('id', 1)->execute();
    $raw = Binding::read($this->db, 1, $this->now);
    self::assertSame('customer_ready', $raw['request']['proof_review_status']); self::assertSame(4, (int) $raw['request']['project_id']);
    $principal = $this->createMock(AccountInterface::class);
    $principal->method('id')->willReturn(1); $principal->method('isAuthenticated')->willReturn(TRUE);
    $reader = $this->makeManagedReader();
    $this->denied(fn() => $reader->context(1, $principal, 'customer'), 'release verifier is unconfigured');
    $calls = 0;
    $reader = $this->makeManagedReader(NULL, function () use (&$calls): array {
      ++$calls; throw new \RuntimeException('Synthetic release authority refused before input normalization.');
    });
    $this->denied(fn() => $reader->context(1, $principal, 'customer'), 'Synthetic release authority refused');
    self::assertSame(1, $calls);
    $r = $receipt['receipt'];
    $grant = ['receipt_id' => $receipt['receipt_id'], 'receipt_sha256' => $receipt['receipt_sha256'],
      'request_id' => 1, 'customer_id' => 1, 'campaign_id' => 1, 'package_manifest_sha256' => $r['package_manifest_sha256'],
      'producer_ids' => $r['producer_ids'], 'proof_review_status' => 'customer_ready', 'proof_approved_at' => $this->now,
      'evidence_sha256' => hash('sha256', 'SYNTHETIC evidence'), 'release_sha256' => hash('sha256', 'SYNTHETIC release')];
    $reader = $this->makeManagedReader(NULL, static fn() => $grant);
    $this->denied(fn() => $reader->context(1, $principal, 'customer'), 'current input');
    $tx = $this->db->startTransaction();
    try { $this->denied(fn() => Binding::lockPending($this->db, 1, $this->now, $receipt['receipt_sha256']), 'not pending'); }
    finally { $tx->rollBack(); unset($tx); }
    self::assertSame($raw, Binding::read($this->db, 1, $this->now));
  }

  private function firstLock(array $queries, string $table): int {
    foreach ($queries as $index => $query) if ($query['locking'] && preg_match('/\b(?:FROM|INTO)\s+["{`]?' . preg_quote($table, '/') . '["}`]?(?:\s|\()/i', $query['sql'])) return $index;
    self::fail('Missing requested lock for ' . $table);
  }

  private function denied(callable $call, string $message): void {
    $error = NULL;
    try { $call(); } catch (\Throwable $caught) { $error = $caught; }
    self::assertNotNull($error, 'Expected managed binding rejection.');
    self::assertNotInstanceOf(\PHPUnit\Framework\AssertionFailedError::class, $error);
    self::assertTrue($error instanceof \RuntimeException || $error instanceof \LogicException, 'Unexpected failure type: ' . get_class($error));
    self::assertStringContainsString($message, $error->getMessage());
  }

  private function state(): array {
    $state = $this->snapshot();
    foreach (['users_field_data', 'famtastic_organization', 'famtastic_customer_resource', 'famtastic_prospect', 'famtastic_worker_mutex'] as $table) $state[$table] = $this->rows($table);
    return $state;
  }
}
