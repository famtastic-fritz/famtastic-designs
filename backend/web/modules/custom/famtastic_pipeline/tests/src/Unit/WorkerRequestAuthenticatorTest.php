<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Site\Settings;
use Drupal\famtastic_pipeline\Service\WorkerCapabilityPolicy;
use Drupal\famtastic_pipeline\Service\WorkerCoordinator;
use Drupal\famtastic_pipeline\Service\WorkerCoordinatorSchema;
use Drupal\famtastic_pipeline\Service\WorkerRequestAuthenticator;
use Drupal\sqlite\Driver\Database\sqlite\Connection;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;

/**
 * Actual HMAC, Settings, coordinator and disposable SQLite nonce persistence.
 * Secrets/clock/registry are synthetic. NOT installed identity or visual QA proof.
 * No controller, receipt, release, job, provider or account activation is mocked
 * into this authentication grant. The only database table is the nonce table.
 */
final class WorkerRequestAuthenticatorTest extends UnitTestCase {
  private WorkerAuthenticationConnection $db;
  private WorkerCoordinator $coordinator;
  private WorkerRequestAuthenticator $auth;
  private TimeInterface $clock;
  private int $now = 1789700000;
  private int $sequence = 0;
  private array $registry;

  protected function setUp(): void {
    parent::setUp();
    $options = ['database' => ':memory:', 'prefix' => '', 'driver' => 'sqlite', 'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite'];
    $this->db = new WorkerAuthenticationConnection(Connection::open($options), $options);
    $this->db->schema()->createTable('famtastic_worker_nonce', WorkerCoordinatorSchema::tables()['famtastic_worker_nonce']);
    $this->clock = $this->createMock(TimeInterface::class);
    $this->clock->method('getCurrentTime')->willReturnCallback(fn() => $this->now);
    $this->clock->method('getRequestTime')->willReturnCallback(fn() => $this->now);
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects(self::never())->method('acquire');
    $lock->expects(self::never())->method('release');
    $this->coordinator = new WorkerCoordinator($this->db, $this->clock, $lock);
    $this->auth = new WorkerRequestAuthenticator($this->coordinator, $this->clock);
    $this->registry = [
      'mac-builder' => ['secret' => str_repeat('b', 32), 'capabilities' => [WorkerCoordinator::CAPABILITY]],
      'qa-reviewer' => ['secret' => str_repeat('q', 32), 'capabilities' => ['proof.review']],
      'multi-worker' => ['secret' => str_repeat('m', 32), 'capabilities' => ['unknown', WorkerCapabilityPolicy::PROOF, 'proof.review', WorkerCoordinator::CAPABILITY, WorkerCapabilityPolicy::PROOF]],
    ];
    $this->settings();
  }

  protected function tearDown(): void {
    new Settings([]);
    parent::tearDown();
  }

  private function settings(bool $enabled = TRUE): void {
    new Settings(['famtastic_bounded_workers_enabled' => $enabled, 'famtastic_worker_registry' => $this->registry]);
  }

  /** Canonical existing wire, including a Drupal /web prefix on the actual URL. */
  private function request(string $operation = 'claim', string $worker = 'mac-builder', string $wire = '{}', ?string $timestamp = NULL, ?string $nonce = NULL, ?string $secret = NULL): Request {
    $timestamp ??= (string) $this->now;
    $nonce ??= str_pad(dechex(++$this->sequence), 32, '0', STR_PAD_LEFT);
    $secret ??= $this->registry[$worker]['secret'];
    $path = '/api/pipeline/worker/' . $operation;
    $request = Request::create('https://authority.example.test/web' . $path, 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], $wire);
    $signature = hash_hmac('sha256', implode("\n", ['POST', $path, $worker, $timestamp, $nonce, hash('sha256', $wire)]), $secret);
    $request->headers->add(['X-FAMtastic-Worker' => $worker, 'X-FAMtastic-Timestamp' => $timestamp, 'X-FAMtastic-Nonce' => $nonce, 'X-FAMtastic-Signature' => 'sha256=' . $signature]);
    return $request;
  }

  private function nonces(): array {
    return $this->db->select('famtastic_worker_nonce', 'n')->fields('n')->orderBy('nonce_key')->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  private function denied(callable $operation): \Throwable {
    try { $operation(); }
    catch (\Throwable $error) {
      if ($error instanceof \PHPUnit\Framework\AssertionFailedError) throw $error;
      return $error;
    }
    self::fail('The authentication boundary must reject this operation.');
  }

  #[DataProvider('operations')]
  public function testExistingOperationsMintOnlyInstalledIdentityAndConsumeOneNonce(string $operation, string $worker, array $capabilities): void {
    $wire = '{"request_id":901,"worker":"forged-worker","identity":"automation:owner","capabilities":["proof.review"],"token":"synthetic-lease","attempt":2}';
    $request = $this->request($operation, $worker, $wire);
    $principal = $this->auth->authenticate($request, $operation);
    $expected = ['worker' => $worker, 'operation' => $operation, 'capabilities' => $capabilities,
      'identity' => 'automation:' . $worker, 'body' => json_decode($wire, TRUE), 'body_sha256' => hash('sha256', $wire)];
    self::assertSame($expected, $this->auth->facts($principal));
    self::assertSame($expected, $this->auth->facts($principal));
    self::assertSame([], get_object_vars($principal));
    self::assertStringNotContainsString($this->registry[$worker]['secret'], serialize([$principal, $expected]));
    self::assertCount(1, $this->nonces());
    self::assertSame($worker . ':' . $request->headers->get('X-FAMtastic-Nonce'), $this->nonces()[0]['nonce_key']);
    self::assertSame($this->now + 180, (int) $this->nonces()[0]['expires']);
    self::assertFalse($this->db->inTransaction());
    self::assertSame($operation === 'review' ? 'automation:' . $worker : NULL, $this->auth->reviewer($principal, 901));
  }

  public static function operations(): iterable {
    foreach (['claim', 'renew', 'finish', 'fail'] as $operation) yield $operation => [$operation, 'mac-builder', [WorkerCoordinator::CAPABILITY]];
    yield 'review only' => ['review', 'qa-reviewer', []];
    yield 'policy intersection' => ['claim', 'multi-worker', [WorkerCoordinator::CAPABILITY, WorkerCapabilityPolicy::PROOF]];
    yield 'review plus claim' => ['review', 'multi-worker', [WorkerCoordinator::CAPABILITY, WorkerCapabilityPolicy::PROOF]];
  }

  public function testSignedValuesAndReturnedFactsCannotBeMutatedIntoAnotherAuthority(): void {
    $wire = "{\n  \"request_id\": 901, \"nested\": {\"values\": [\"original\"]}\n}";
    $request = $this->request('review', 'qa-reviewer', $wire);
    $principal = $this->auth->authenticate($request, 'review');
    $expected = $this->auth->facts($principal);
    $request->initialize([], [], [], [], [], ['HTTPS' => 'off', 'REQUEST_METHOD' => 'GET'], '{"request_id":902}');
    $request->headers->set('X-FAMtastic-Worker', 'mac-builder');
    $facts = $this->auth->facts($principal);
    $facts['body']['request_id'] = 902;
    $facts['body']['nested']['values'][0] = 'changed';
    $facts['capabilities'][] = WorkerCoordinator::CAPABILITY;
    $facts['identity'] = 'automation:owner';
    $principal->worker = 'owner';
    $principal->request_id = 902;
    self::assertSame($expected, $this->auth->facts($principal));
    self::assertSame(hash('sha256', $wire), $expected['body_sha256']);
    self::assertNotSame(hash('sha256', json_encode($expected['body'])), $expected['body_sha256']);
    self::assertSame('automation:qa-reviewer', $this->auth->reviewer($principal, 901));
    self::assertNull($this->auth->reviewer($principal, 902));
  }

  public function testForgedClonedSerializedAndForeignInstancePrincipalsAreRejected(): void {
    $principal = $this->auth->authenticate($this->request('review', 'qa-reviewer', '{"request_id":901}'), 'review');
    foreach ([new \stdClass(), (object) ['worker' => 'qa-reviewer', 'request_id' => 901], clone $principal, unserialize(serialize($principal))] as $forged) {
      self::assertInstanceOf(\InvalidArgumentException::class, $this->denied(fn() => $this->auth->facts($forged)));
      self::assertInstanceOf(\InvalidArgumentException::class, $this->denied(fn() => $this->auth->reviewer($forged, 901)));
    }
    $other = new WorkerRequestAuthenticator($this->coordinator, $this->clock);
    self::assertInstanceOf(\InvalidArgumentException::class, $this->denied(fn() => $other->facts($principal)));
    self::assertSame('automation:qa-reviewer', $this->auth->reviewer($principal, 901));
  }

  #[DataProvider('nonExactReviewIds')]
  public function testReviewerRequiresStrictPositiveSignedIntegerAndExactServerId(string $wire, int $exact): void {
    $principal = $this->auth->authenticate($this->request('review', 'qa-reviewer', $wire), 'review');
    self::assertNull($this->auth->reviewer($principal, $exact));
  }

  public static function nonExactReviewIds(): iterable {
    foreach (['"901"', '901.0', '9.01e2', 'true', 'null', '[]', '{}', '902', '-1', '0', '9223372036854775808'] as $value) yield $value => ['{"request_id":' . $value . '}', 901];
    yield 'missing' => ['{}', 901];
    yield 'legacy empty list' => ['[]', 901];
    yield 'server zero' => ['{"request_id":0}', 0];
    yield 'server negative' => ['{"request_id":-1}', -1];
  }

  public function testNonceReplayRemainsRejectedAcrossAuthenticatorInstances(): void {
    $request = $this->request();
    $principal = $this->auth->authenticate($request, 'claim');
    $before = $this->nonces();
    $this->denied(fn() => $this->auth->authenticate($request, 'claim'));
    $other = new WorkerRequestAuthenticator($this->coordinator, $this->clock);
    $this->denied(fn() => $other->authenticate($request, 'claim'));
    self::assertSame($before, $this->nonces());
    self::assertSame('mac-builder', $this->auth->facts($principal)['worker']);
    // The existing authority keys nonces by worker, not globally.
    $review = $this->request('review', 'qa-reviewer', '{"request_id":901}', nonce: $request->headers->get('X-FAMtastic-Nonce'));
    self::assertSame('automation:qa-reviewer', $this->auth->reviewer($this->auth->authenticate($review, 'review'), 901));
    self::assertCount(2, $this->nonces());
  }

  #[DataProvider('invalidAuthentication')]
  public function testAuthenticationFailuresNeverInsertANonce(string $case): void {
    $request = $this->request(); $operation = 'claim';
    switch ($case) {
      case 'default off': new Settings([]); break;
      case 'disabled': $this->settings(FALSE); break;
      case 'unknown worker': $request = $this->request(worker: 'unknown-worker', secret: str_repeat('z', 32)); break;
      case 'malformed registry': new Settings(['famtastic_bounded_workers_enabled' => TRUE, 'famtastic_worker_registry' => 'not an array']); break;
      case 'malformed identity': $this->registry['mac-builder'] = 'not an identity'; $this->settings(); break;
      case 'missing secret': unset($this->registry['mac-builder']['secret']); $this->settings(); break;
      case 'short secret': $this->registry['mac-builder']['secret'] = 'short'; $this->settings(); $request = $this->request(); break;
      case 'bad capabilities': $this->registry['mac-builder']['capabilities'] = 'proof.review'; $this->settings(); break;
      case 'unknown capabilities': $this->registry['mac-builder']['capabilities'] = ['unknown', 1, TRUE]; $this->settings(); break;
      case 'body cannot grant review': $operation = 'review'; $request = $this->request('review', wire: '{"capabilities":["proof.review"],"worker":"qa-reviewer"}'); break;
      case 'reviewer cannot claim': $request = $this->request(worker: 'qa-reviewer'); break;
      case 'TLS': $request->server->set('HTTPS', 'off'); break;
      case 'method': $request->setMethod('GET'); break;
      case 'signature': $request->headers->set('X-FAMtastic-Signature', 'sha256=' . str_repeat('0', 64)); break;
      case 'worker substitution': $request->headers->set('X-FAMtastic-Worker', 'multi-worker'); break;
      case 'invalid worker': $request = $this->request(worker: 'BadWorker', secret: str_repeat('b', 32)); $this->registry['BadWorker'] = $this->registry['mac-builder']; $this->settings(); break;
      case 'timestamp': $request = $this->request(timestamp: '178970000'); break;
      case 'nonce': $request = $this->request(nonce: str_repeat('A', 32)); break;
      case 'path binding': $operation = 'finish'; break;
      case 'operation alias': $operation = 'claim/'; break;
      case 'body binding': $replacement = $this->request(wire: '{"changed":true}'); $replacement->headers->replace($request->headers->all()); $request = $replacement; break;
      case 'past window': $request = $this->request(timestamp: (string) ($this->now - 91)); break;
      case 'future window': $request = $this->request(timestamp: (string) ($this->now + 91)); break;
      case 'body limit': $request = $this->request(wire: '{"x":"' . str_repeat('x', 99993) . '"}'); break;
    }
    $this->denied(fn() => $this->auth->authenticate($request, $operation));
    self::assertSame([], $this->nonces());
    self::assertFalse($this->db->inTransaction());
  }

  public static function invalidAuthentication(): iterable {
    foreach (['default off', 'disabled', 'unknown worker', 'malformed registry', 'malformed identity', 'missing secret', 'short secret', 'bad capabilities', 'unknown capabilities', 'body cannot grant review', 'reviewer cannot claim', 'TLS', 'method', 'signature', 'worker substitution', 'invalid worker', 'timestamp', 'nonce', 'path binding', 'operation alias', 'body binding', 'past window', 'future window', 'body limit'] as $case) yield $case => [$case];
  }

  public function testExistingInclusiveSkewAndExactByteLimitArePreserved(): void {
    $wire = '{"x":"' . str_repeat('x', 99992) . '"}';
    self::assertSame(100000, strlen($wire));
    foreach ([-90, 90] as $skew) {
      $principal = $this->auth->authenticate($this->request(wire: $wire, timestamp: (string) ($this->now + $skew)), 'claim');
      self::assertSame(hash('sha256', $wire), $this->auth->facts($principal)['body_sha256']);
    }
    self::assertCount(2, $this->nonces());
  }

  #[DataProvider('invalidJson')]
  public function testSignedMalformedJsonConsumesNonceAndCannotBeRetried(string $wire): void {
    $request = $this->request(wire: $wire);
    $this->denied(fn() => $this->auth->authenticate($request, 'claim'));
    self::assertCount(1, $this->nonces());
    $before = $this->nonces();
    $this->denied(fn() => $this->auth->authenticate($request, 'claim'));
    self::assertSame($before, $this->nonces());
  }

  public static function invalidJson(): iterable {
    foreach (['{', '', 'null', 'true', '901', '"text"', "{\"x\":\"\xFF\"}", str_repeat('{"x":', 33) . '0' . str_repeat('}', 33)] as $i => $wire) yield 'invalid JSON ' . $i => [$wire];
  }

  #[DataProvider('authorityChanges')]
  public function testCurrentFactsRejectChangedOrRevokedInstalledAuthorityPermanently(string $case): void {
    $principal = $this->auth->authenticate($this->request('review', 'multi-worker', '{"request_id":901}'), 'review');
    $original = $this->registry;
    switch ($case) {
      case 'disabled': $this->settings(FALSE); break;
      case 'removed worker': unset($this->registry['multi-worker']); $this->settings(); break;
      case 'rotated secret': $this->registry['multi-worker']['secret'] = str_repeat('r', 32); $this->settings(); break;
      case 'removed review': $this->registry['multi-worker']['capabilities'] = [WorkerCoordinator::CAPABILITY]; $this->settings(); break;
      case 'removed claim': $this->registry['multi-worker']['capabilities'] = ['proof.review']; $this->settings(); break;
      case 'added capability': $this->registry['multi-worker']['capabilities'][] = 'other'; $this->settings(); break;
      case 'reordered capability': $this->registry['multi-worker']['capabilities'] = array_reverse($this->registry['multi-worker']['capabilities']); $this->settings(); break;
      case 'expired': $this->now += 91; break;
      case 'clock backwards': $this->now -= 91; break;
    }
    $before = $this->nonces();
    $this->denied(fn() => $this->auth->reviewer($principal, 901));
    $this->registry = $original; $this->now = 1789700000; $this->settings();
    self::assertSame('Unknown worker principal.', $this->denied(fn() => $this->auth->facts($principal))->getMessage());
    self::assertSame($before, $this->nonces());
    $fresh = $this->auth->authenticate($this->request('review', 'multi-worker', '{"request_id":901}'), 'review');
    self::assertSame('automation:multi-worker', $this->auth->reviewer($fresh, 901));
  }

  public static function authorityChanges(): iterable {
    foreach (['disabled', 'removed worker', 'rotated secret', 'removed review', 'removed claim', 'added capability', 'reordered capability', 'expired', 'clock backwards'] as $case) yield $case => [$case];
  }

  public function testUnrelatedRegistryChangeDoesNotGrantOrRevokeThisIdentity(): void {
    $principal = $this->auth->authenticate($this->request('review', 'qa-reviewer', '{"request_id":901}'), 'review');
    $this->registry['mac-builder']['secret'] = str_repeat('r', 32);
    $this->settings();
    self::assertSame('automation:qa-reviewer', $this->auth->reviewer($principal, 901));
  }

  public function testReviewerRejectsSharedSecretBeforeAuthenticationAndOnCurrentFacts(): void {
    $principal = $this->auth->authenticate($this->request('review', 'qa-reviewer', '{"request_id":901}'), 'review');
    $this->registry['producer-alias'] = ['secret' => $this->registry['qa-reviewer']['secret'], 'capabilities' => [WorkerCapabilityPolicy::PROOF]];
    $this->settings();
    $request = $this->request('review', 'qa-reviewer', '{"request_id":901}');
    $before = $this->nonces();
    $error = $this->denied(fn() => $this->auth->authenticate($request, 'review'));
    self::assertSame('Worker reviewer credential is not independent.', $error->getMessage());
    self::assertStringNotContainsString($this->registry['qa-reviewer']['secret'], $error->getMessage());
    self::assertStringNotContainsString('producer-alias', $error->getMessage());
    self::assertSame($error->getMessage(), $this->denied(fn() => $this->auth->facts($principal))->getMessage());
    self::assertSame($before, $this->nonces(), 'Shared-secret rejection must precede nonce consumption.');

    // The narrow independence check does not change existing claim capability.
    $claim = $this->auth->authenticate($this->request(worker: 'producer-alias'), 'claim');
    self::assertSame([WorkerCapabilityPolicy::PROOF], $this->auth->facts($claim)['capabilities']);
    self::assertNull($this->auth->reviewer($claim, 901));

    unset($this->registry['producer-alias']); $this->settings();
    self::assertSame('Unknown worker principal.', $this->denied(fn() => $this->auth->reviewer($principal, 901))->getMessage());
    // Removing the alias allows fresh authentication, never revives the handle.
    $fresh = $this->auth->authenticate($request, 'review');
    self::assertSame('automation:qa-reviewer', $this->auth->reviewer($fresh, 901));
  }

  #[DataProvider('changesDuringNoncePersistence')]
  public function testAuthorityIsCheckedAgainAfterNoncePersistenceBeforeMinting(string $case): void {
    $request = $this->request();
    $this->db->afterNonce = function () use ($case): void {
      if ($case === 'expiry') $this->now += 91;
      else { $this->registry['mac-builder']['secret'] = str_repeat('r', 32); $this->settings(); }
    };
    $this->denied(fn() => $this->auth->authenticate($request, 'claim'));
    self::assertNull($this->db->afterNonce, 'The seam ran after the real SQLite insert.');
    self::assertCount(1, $this->nonces());
    $this->now = 1789700000; $this->registry['mac-builder']['secret'] = str_repeat('b', 32); $this->settings();
    $this->denied(fn() => $this->auth->authenticate($request, 'claim'));
    self::assertCount(1, $this->nonces());
  }

  public static function changesDuringNoncePersistence(): iterable {
    yield 'expiry' => ['expiry'];
    yield 'registry rotation' => ['rotation'];
  }

  public function testCallerTransactionCannotSupplyRollbackableNonceAuthority(): void {
    $request = $this->request();
    $transaction = $this->db->startTransaction();
    $this->db->insert('famtastic_worker_nonce')->fields(['nonce_key' => 'sentinel', 'expires' => $this->now + 180])->execute();
    try {
      self::assertInstanceOf(\LogicException::class, $this->denied(fn() => $this->auth->authenticate($request, 'claim')));
      self::assertTrue($this->db->inTransaction());
      self::assertSame(1, $this->db->transactionManager()->stackDepth());
      self::assertSame(['sentinel'], array_column($this->nonces(), 'nonce_key'));
    }
    finally { $transaction->rollBack(); unset($transaction); }
    self::assertFalse($this->db->inTransaction());
    self::assertSame([], $this->nonces());
    $principal = $this->auth->authenticate($request, 'claim');
    self::assertSame('mac-builder', $this->auth->facts($principal)['worker']);
    self::assertCount(1, $this->nonces());
  }

  public function testReplicaCannotMintPrincipalOrConsumeNonce(): void {
    $this->db->setTarget('replica');
    self::assertInstanceOf(\LogicException::class, $this->denied(fn() => $this->auth->authenticate($this->request(), 'claim')));
    self::assertSame([], $this->nonces());
  }

  public function testExplicitPrimaryConnectionCanAuthenticate(): void {
    $this->db->setTarget('default');
    self::assertSame('mac-builder', $this->auth->facts($this->auth->authenticate($this->request(), 'claim'))['worker']);
    self::assertCount(1, $this->nonces());
  }

  public function testFailedCleanupWithholdsPrincipalButDoesNotUndoConsumedNonce(): void {
    $this->db->insert('famtastic_worker_nonce')->fields(['nonce_key' => 'expired-fixture', 'expires' => $this->now - 1])->execute();
    $this->db->query("CREATE TRIGGER auth_cleanup_failure BEFORE DELETE ON famtastic_worker_nonce BEGIN SELECT RAISE(ABORT, 'synthetic cleanup failure'); END", [], ['allow_delimiter_in_query' => TRUE]);
    $request = $this->request();
    $this->denied(fn() => $this->auth->authenticate($request, 'claim'));
    self::assertCount(2, $this->nonces());
    self::assertFalse($this->db->inTransaction());
    $this->db->query('DROP TRIGGER auth_cleanup_failure');
    $this->denied(fn() => $this->auth->authenticate($request, 'claim'));
    self::assertCount(2, $this->nonces());
    $fresh = $this->auth->authenticate($this->request(), 'claim');
    self::assertSame('mac-builder', $this->auth->facts($fresh)['worker']);
    self::assertCount(2, $this->nonces());
    self::assertNotContains('expired-fixture', array_column($this->nonces(), 'nonce_key'));
  }

  public function testUnavailableNonceStorageHasNoMemoryOnlyFallback(): void {
    $this->db->schema()->dropTable('famtastic_worker_nonce');
    $request = $this->request();
    $this->denied(fn() => $this->auth->authenticate($request, 'claim'));
    $this->db->schema()->createTable('famtastic_worker_nonce', WorkerCoordinatorSchema::tables()['famtastic_worker_nonce']);
    self::assertSame('mac-builder', $this->auth->facts($this->auth->authenticate($request, 'claim'))['worker']);
    self::assertCount(1, $this->nonces());
  }

  public function testSilentlyIgnoredNonceNeverMintsAPrincipalOrAdoptsPriorNonce(): void {
    $request = $this->request();
    $this->db->query('CREATE TRIGGER ignored_nonce BEFORE INSERT ON famtastic_worker_nonce BEGIN SELECT RAISE(IGNORE); END', [], ['allow_delimiter_in_query' => TRUE]);
    $this->denied(fn() => $this->auth->authenticate($request, 'claim'));
    self::assertSame([], $this->nonces());
    $this->db->query('DROP TRIGGER ignored_nonce');
    $this->auth->authenticate($request, 'claim'); $before = $this->nonces();
    $this->db->query('CREATE TRIGGER ignored_nonce BEFORE INSERT ON famtastic_worker_nonce BEGIN SELECT RAISE(IGNORE); END', [], ['allow_delimiter_in_query' => TRUE]);
    $this->denied(fn() => $this->auth->authenticate($request, 'claim'));
    self::assertSame($before, $this->nonces());
  }

  public function testAlteredOrDeletedNonceCannotAuthorizeWork(): void {
    foreach (['UPDATE famtastic_worker_nonce SET expires = expires + 1', 'DELETE FROM famtastic_worker_nonce'] as $mutation) {
      $this->db->query("CREATE TRIGGER altered_nonce AFTER INSERT ON famtastic_worker_nonce BEGIN $mutation; END", [], ['allow_delimiter_in_query' => TRUE]);
      $this->denied(fn() => $this->auth->authenticate($this->request(), 'claim'));
      $this->db->query('DROP TRIGGER altered_nonce');
    }
    self::assertFalse($this->db->inTransaction());
  }

  public function testInterleavedWinnerCannotBeAdoptedWhenLosingInsertIsSilentlyIgnored(): void {
    $request = $this->request();
    $this->db->query('CREATE TRIGGER ignore_duplicate_nonce BEFORE INSERT ON famtastic_worker_nonce WHEN EXISTS (SELECT 1 FROM famtastic_worker_nonce WHERE nonce_key = NEW.nonce_key) BEGIN SELECT RAISE(IGNORE); END', [], ['allow_delimiter_in_query' => TRUE]);
    $winnerAuth = new WorkerRequestAuthenticator($this->coordinator, $this->clock); $winner = NULL;
    $this->db->beforeNonce = function() use ($request, $winnerAuth, &$winner): void {
      // Deterministic interleaving after the loser's precheck, before its INSERT.
      // The winner uses actual signature, nonce insert, cleanup and readback.
      $winner = $winnerAuth->authenticate($request, 'claim');
    };
    $error = $this->denied(fn() => $this->auth->authenticate($request, 'claim'));
    self::assertSame('Worker nonce insertion was not confirmed.', $error->getMessage());
    self::assertNull($this->db->beforeNonce); self::assertNotNull($winner);
    self::assertSame('mac-builder', $winnerAuth->facts($winner)['worker']);
    self::assertCount(1, $this->nonces()); self::assertFalse($this->db->inTransaction());
  }
}

/** Deterministic post-insert seam over real SQLite, not a coordinator mock. */
final class WorkerAuthenticationConnection extends Connection {
  public ?\Closure $beforeNonce = NULL;
  public ?\Closure $afterNonce = NULL;

  public function prepareStatement(string $query, array $options, bool $allow_row_count = FALSE): \Drupal\Core\Database\StatementInterface {
    if (str_starts_with($query, 'INSERT INTO {famtastic_worker_nonce}')) {
      return new WorkerAuthenticationStatement($this->connection, $this, $this->preprocessStatement($query, $options), $options['pdo'] ?? [], $allow_row_count);
    }
    return parent::prepareStatement($query, $options, $allow_row_count);
  }
}

/** Real prepared SQLite INSERT and affected-row count, with bounded test hooks. */
final class WorkerAuthenticationStatement extends \Drupal\sqlite\Driver\Database\sqlite\Statement {
  public function execute($args = [], $options = []) {
    if ($callback = $this->connection->beforeNonce) {
      $this->connection->beforeNonce = NULL;
      $callback();
    }
    $result = parent::execute($args, $options);
    if ($callback = $this->connection->afterNonce) {
      $this->connection->afterNonce = NULL;
      $callback();
    }
    return $result;
  }
}
