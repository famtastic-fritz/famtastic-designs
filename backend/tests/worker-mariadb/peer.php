<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

use Drupal\Core\Database\Transaction;
use Drupal\Core\Lock\DatabaseLockBackend;
use Drupal\Core\Site\Settings;
use Drupal\famtastic_pipeline\Service\{OperationalLedger, WorkerCapabilityPolicy, WorkerCoordinator, WorkerCoordinatorMutex, WorkerCoordinatorSchema};

function emitProof(array $data): void { echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n"; flush(); }
function safeProofError(Throwable $e): array {
  // No driver DSNs, SQL, credentials, lease tokens or arbitrary stack dumps.
  $database = $e instanceof PDOException || str_contains($e::class, 'Database');
  return ['class' => $e::class, 'message' => $database ? 'database_operation_failed' : substr($e->getMessage(), 0, 300)];
}
final class ProofPeer {
  private ?Transaction $root = NULL;
  private ProofClock $clock;
  private WorkerCoordinator $coordinator;
  private OperationalLedger $ledger;
  private array $input;
  private array $claims = [];
  private array $captured = [];
  public function __construct(private Drupal\mysql\Driver\Database\mysql\Connection $db, string $hint, private string $role) {
    $this->clock = new ProofClock(); $this->input = proofInputs();
    proofNeed(in_array($hint, ['ineffective', 'database'], TRUE) && in_array($role, ['a', 'b'], TRUE), 'invalid_peer_options');
    $lock = $hint === 'database' ? new DatabaseLockBackend($db) : new IneffectiveBusyHint();
    $this->coordinator = new WorkerCoordinator($db, $this->clock, $lock, $this->input['catalog']);
    $this->ledger = new OperationalLedger($db, $this->clock, $this->coordinator);
    new Settings([]);
  }
  private function tables(): array {
    return WorkerCoordinatorSchema::tables() + array_intersect_key(_famtastic_pipeline_automation_schema(), array_flip(['famtastic_job', 'famtastic_event', 'famtastic_exception']))
      + ['semaphore' => (new DatabaseLockBackend($this->db))->schemaDefinition()];
  }
  public function rollback(): void {
    if ($this->root !== NULL) { $this->root->rollBack(); $this->root = NULL; }
    proofNeed(!$this->db->inTransaction(), 'unexpected_remaining_transaction');
  }
  private function row(string $table, int $id): array {
    return $this->db->select($table, 't')->fields('t')->condition($table === 'famtastic_job' ? 'id' : 'job_id', $id)->execute()->fetchAssoc();
  }
  private function seed(): void {
    foreach (['static' => 1, 'proof' => 2] as $kind => $id) {
      $payload = $this->input[$kind]; $wire = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
      $key = $kind === 'static' ? 'fixture:1' : 'website_proof.generate.v1:request:17:brief:' . $payload['brief_sha256'];
      $this->db->insert('famtastic_job')->fields(['id' => $id, 'job_key' => $key, 'job_type' => $kind === 'static' ? 'site_studio_staging_prepare' : 'proof.generate',
        'prospect_id' => $kind === 'proof' ? 23 : NULL, 'status' => 'queued', 'attempts' => 0, 'max_attempts' => 3, 'available_at' => $this->clock->now,
        'payload' => $wire, 'created' => $this->clock->now, 'changed' => $this->clock->now])->execute();
      $method = $kind === 'static' ? 'enroll' : 'enrollProof';
      $this->coordinator->$method($id, $key, hash('sha256', $wire), 100);
    }
    // Owned fixture only, before either root transaction: exercise lazy insertion.
    $this->db->delete('famtastic_worker_mutex')->execute();
  }
  private function stats(): array {
    $out = [];
    foreach (['famtastic_worker_claim', 'famtastic_worker_budget'] as $table) {
      $out[$table] = $this->db->select($table, 't')->fields('t')->orderBy($table === 'famtastic_worker_claim' ? 'job_id' : 'reservation_key')->execute()->fetchAll(PDO::FETCH_ASSOC);
    }
    $out['jobs'] = $this->db->select('famtastic_job', 'j')->fields('j', ['id', 'status', 'attempts', 'available_at', 'locked_at'])->orderBy('id')->execute()->fetchAll(PDO::FETCH_ASSOC);
    $out['mutex_rows'] = (int) $this->db->select('famtastic_worker_mutex', 'm')->countQuery()->execute()->fetchField();
    $out['total_holds'] = array_sum(array_column($out['famtastic_worker_budget'], 'reserved_cents'));
    $out['health'] = $this->coordinator->health();
    return $out;
  }
  public function command(array $r): mixed {
    switch ($r['op']) {
      case 'schema':
        proofNeed($this->role === 'a' && !$this->db->inTransaction(), 'schema_outside_fixture_setup');
        $tables = $this->tables();
        $existing = $this->db->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()')->fetchCol();
        proofNeed(!array_diff($existing, [...array_keys($tables), 'proof_harness_owner']), 'unexpected_tables_do_not_adopt');
        foreach ($tables as $name => $schema) if (!$this->db->schema()->tableExists($name)) $this->db->schema()->createTable($name, $schema);
        $engines = $this->db->query('SELECT DISTINCT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()')->fetchCol();
        proofNeed($engines === ['InnoDB'], 'nontransactional_fixture_table');
        return ['tables' => count($tables)];
      case 'reset':
        proofNeed($this->role === 'a' && !$this->db->inTransaction(), 'reset_outside_fixture_setup');
        foreach (array_keys($this->tables()) as $table) $this->db->delete($table)->execute();
        $this->claims = []; $this->captured = [];
        if ($r['seed'] ?? TRUE) $this->seed();
        return $this->stats();
      case 'clock':
        proofNeed(is_int($r['now']) && $r['now'] > 0, 'invalid_clock');
        $this->clock->now = $r['now']; $this->clock->live = $r['live'] ?? FALSE; $this->clock->anchor = microtime(TRUE);
        return TRUE;
      case 'begin':
        proofNeed($this->root === NULL && !$this->db->inTransaction(), 'root_already_open');
        $this->root = $this->db->startTransaction(); return TRUE;
      case 'commit':
        proofNeed($this->root !== NULL, 'root_missing');
        $this->root->commitOrRelease(); $this->root = NULL;
        proofNeed(!$this->db->inTransaction(), 'nested_operation_committed_wrong_scope'); return TRUE;
      case 'rollback': $this->rollback(); return TRUE;
      case 'snapshot': case 'stats': return $this->stats();
      case 'claim':
        $caps = [($r['cap'] ?? 'static') === 'proof' ? WorkerCapabilityPolicy::PROOF : WorkerCoordinator::CAPABILITY];
        $claim = $this->coordinator->claim('synthetic-worker-' . $this->role, $caps);
        if ($claim === NULL) return NULL;
        $this->claims[$claim['job_id']][] = $claim;
        unset($claim['lease_token'], $claim['payload_wire']); // Raw token remains process-private.
        $claim['root_still_open'] = $this->db->inTransaction(); return $claim;
      case 'renew': case 'fail': case 'finish':
        $id = $r['job']; $history = $this->claims[$id] ?? [];
        $claim = $history[$r['index'] ?? (count($history) - 1)] ?? NULL;
        proofNeed(is_array($claim), 'local_claim_missing');
        $args = [$id, 'synthetic-worker-' . $this->role, $claim['lease_token']];
        if ($r['op'] === 'finish') $args[] = ['status' => 'accepted_waiting_callback', 'receipt_id' => 'synthetic-receipt', 'packet_id' => 'fixture-packet', 'idempotency_key' => 'fixture-key'];
        $args[] = [$claim['capability']]; $args[] = $claim['attempt'];
        return $this->coordinator->{$r['op']}(...$args);
      case 'budget':
        $this->db->insert('famtastic_worker_budget')->fields(['reservation_key' => $r['key'], 'month' => $r['month'] ?? gmdate('Y-m', $this->clock->now),
          'job_id' => 800, 'attempt' => 1, 'reserved_cents' => $r['cents'], 'created' => $this->clock->now])->execute(); return TRUE;
      case 'waits':
        return (bool) $this->db->query('SELECT COUNT(*) FROM information_schema.INNODB_LOCK_WAITS w
          JOIN information_schema.INNODB_TRX b ON b.trx_id = w.blocking_trx_id
          JOIN information_schema.INNODB_TRX r ON r.trx_id = w.requesting_trx_id
          JOIN information_schema.INNODB_LOCKS l ON l.lock_id = w.requested_lock_id
          WHERE b.trx_mysql_thread_id = CONNECTION_ID() AND r.trx_mysql_thread_id = :peer AND l.lock_table LIKE :table',
          [':peer' => $r['peer'], ':table' => '%famtastic_worker_mutex%'])->fetchField();
      case 'enqueue':
        new Settings(['famtastic_fresh_selected_admission_enabled' => TRUE]);
        try { return $this->ledger->enqueue('fixture:duplicate', 'site_studio_staging_prepare', $this->input['static'], NULL, 3); }
        finally { new Settings([]); }
      case 'capture':
        proofNeed(in_array($r['table'], ['famtastic_job', 'famtastic_worker_claim'], TRUE), 'invalid_capture');
        $this->captured[$r['table']] = $this->row($r['table'], 1); return TRUE;
      case 'tamper':
        $table = $r['table']; proofNeed(in_array($table, ['famtastic_job', 'famtastic_worker_claim'], TRUE), 'invalid_tamper');
        $this->db->update($table)->fields([$table === 'famtastic_job' ? 'attempts' : 'attempt' => 2])
          ->condition($table === 'famtastic_job' ? 'id' : 'job_id', 1)->execute(); return TRUE;
      case 'cas':
        $table = $r['table']; proofNeed(isset($this->captured[$table]), 'capture_missing');
        return WorkerCoordinatorMutex::run($this->db, function () use ($table) {
          $this->command(['op' => 'budget', 'key' => 'cas-sentinel', 'cents' => 1]);
          $method = new ReflectionMethod(WorkerCoordinator::class, $table === 'famtastic_job' ? 'updateJob' : 'updateClaim');
          $method->invoke($this->coordinator, $this->captured[$table], ['changed' => $this->clock->now + 1]); return TRUE;
        });
      case 'legacy':
        return match ($r['action']) {
          'claim' => $this->ledger->claimNext(),
          'complete' => $this->ledger->completeJob(1, ['synthetic' => TRUE]),
          'fail' => $this->ledger->failJob(1, 'synthetic-only'),
          'requeue' => $this->ledger->requeueFailedJob(1, 'fixture:1'),
          default => throw new RuntimeException('invalid_legacy_action'),
        };
      case 'exit': $this->rollback(); return TRUE;
      default: throw new RuntimeException('unknown_peer_command');
    }
  }
}
$peer = NULL; $exit = 0;
try {
  proofNeed($argc === 5, 'peer_arguments_required');
  $config = proofConfig($argv[1]); proofLoad($argv[2]);
  $db = proofConnection($config); $peer = new ProofPeer($db, $argv[3], $argv[4]);
  emitProof(['phase' => 'hello', 'connection_id' => (int) $db->query('SELECT CONNECTION_ID()')->fetchField(), 'php' => PHP_VERSION,
    'server_version' => $db->query('SELECT VERSION()')->fetchField(), 'source_mode' => $argv[2], 'hint' => $argv[3]]);
  while (($line = fgets(STDIN, 16385)) !== FALSE) {
    proofNeed(str_ends_with($line, "\n") && strlen($line) < 16384, 'oversized_command');
    $request = json_decode($line, TRUE, flags: JSON_THROW_ON_ERROR);
    proofNeed(is_int($request['id']) && is_string($request['op']), 'invalid_command');
    emitProof(['phase' => 'started', 'id' => $request['id']]);
    try { emitProof(['phase' => 'done', 'id' => $request['id'], 'ok' => TRUE, 'value' => $peer->command($request)]); }
    catch (Throwable $e) { emitProof(['phase' => 'done', 'id' => $request['id'], 'ok' => FALSE, 'error' => safeProofError($e)]); }
    if ($request['op'] === 'exit') break;
  }
} catch (Throwable $e) {
  emitProof(['phase' => 'bootstrap_or_protocol_failure', 'error' => safeProofError($e)]); $exit = 2;
} finally {
  // Normal EOF/error must not let Drupal's transaction destructor commit fixtures.
  if ($peer !== NULL) $peer->rollback();
}
exit($exit);
