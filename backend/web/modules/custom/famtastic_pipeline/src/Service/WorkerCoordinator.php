<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Component\Datetime\TimeInterface;

/** Drupal is the single claim/budget authority for both Mac and cloud workers. */
final class WorkerCoordinator {
  public const POLICY = 'bounded-workers-v1';
  public const STOP_CENTS = 2000; // $25 authorization less $5 billing/trigger headroom.
  public const LEASE_SECONDS = 90;
  public const MAX_RUN_SECONDS = 300;
  public const CAPABILITY = 'selected-static-dispatch-v1';

  public function __construct(private readonly Connection $database, private readonly TimeInterface $time, private readonly LockBackendInterface $lock,
    private readonly array $reviewedProofCostPolicies = []) {}

  /** Shared capability predicate; enrollment never upgrades a static consumer. */
  public static function supportsPayload(string $jobType, array $payload): bool {
    $packet = $payload['packet'] ?? [];
    $continuation = $packet['continuation'] ?? [];
    return $jobType === 'site_studio_staging_prepare'
      && ($packet['build_class'] ?? '') === 'prepayment_selected_direction_staging'
      && ($continuation['spec']['capability_class'] ?? '') === 'static'
      && empty($continuation['spec']['backend']) && empty($continuation['spec']['functional_contract'])
      && in_array($continuation['operation'] ?? '', ['package_existing', 'continue_build'], TRUE)
      && ($continuation['requested_next_action'] ?? '') === 'protected_review';
  }

  /** Explicit exact-job admission. Does not run it, send mail or revive failures. */
  public function enroll(int $jobId, string $jobKey, string $payloadHash, int $reservationCents): array {
    return $this->enrollCapability($jobId, $jobKey, $payloadHash, $reservationCents, self::CAPABILITY);
  }

  /** Trusted service seam only. No HTTP/CLI/fresh-admission caller or default cost. */
  public function enrollProof(int $jobId, string $jobKey, string $payloadHash, int $reservationCents): array {
    return $this->enrollCapability($jobId, $jobKey, $payloadHash, $reservationCents, WorkerCapabilityPolicy::PROOF);
  }

  private function enrollCapability(int $jobId, string $jobKey, string $payloadHash, int $reservationCents, string $capability): array {
    if ($reservationCents < 25 || $reservationCents > 250 || !preg_match('/^[a-f0-9]{64}$/', $payloadHash)) {
      throw new \InvalidArgumentException('Each attempt needs a conservative 25–250 cent reservation and immutable payload hash.');
    }
    return $this->atomic(function () use ($jobId, $jobKey, $payloadHash, $reservationCents, $capability): array {
      $profile = WorkerCapabilityPolicy::profile($capability);
      $job = $this->job($jobId);
      $type = $capability === self::CAPABILITY ? 'site_studio_staging_prepare' : 'proof.generate';
      if (!$job || $job['job_key'] !== $jobKey || $job['job_type'] !== $type
        || !hash_equals(hash('sha256', (string) $job['payload']), $payloadHash)) {
        throw new \InvalidArgumentException('Admission requires the exact capability job and frozen payload.');
      }
      if ($capability === WorkerCapabilityPolicy::PROOF) WorkerCapabilityPolicy::assertProof($job, $reservationCents, $this->reviewedProofCostPolicies);
      $existing = $this->claimRow($jobId);
      if ($existing) {
        if ($existing['capability'] !== $capability || $existing['policy_version'] !== $profile['policy']
          || $existing['payload_sha256'] !== $payloadHash || (int) $existing['reservation_cents'] !== $reservationCents) throw new \RuntimeException('Enrollment is immutable.');
        return ['status' => 'already_enrolled', 'job_id' => $jobId];
      }
      if ($job['status'] !== 'queued' || (int) $job['attempts'] !== 0) throw new \RuntimeException('Only fresh unattempted jobs may enroll; historical work requires separate reconciliation.');
      if ($capability === self::CAPABILITY && !self::supportsPayload($job['job_type'], json_decode((string) $job['payload'], TRUE, flags: JSON_THROW_ON_ERROR))) {
        throw new \RuntimeException('Planning/ecommerce work requires an implementation capability, not static packaging.');
      }
      $this->database->insert('famtastic_worker_claim')->fields([
        'job_id' => $jobId, 'policy_version' => $profile['policy'], 'payload_sha256' => $payloadHash,
        'capability' => $capability, 'reservation_cents' => $reservationCents, 'state' => 'pending', 'changed' => $this->now(),
      ])->execute();
      $this->updateJob($job, ['status' => 'worker_queued', 'changed' => $this->now()]);
      return ['status' => 'enrolled', 'job_id' => $jobId];
    });
  }

  /** Capabilities are trusted server grants, never raw worker-body data. */
  public function claim(string $worker, array $authorizedCapabilities = [self::CAPABILITY]): ?array {
    $this->assertWorker($worker);
    $capabilities = WorkerCapabilityPolicy::claimCapabilities($authorizedCapabilities);
    if (!$capabilities) return NULL;
    return $this->atomic(function () use ($worker, $capabilities): ?array {
      $now = $this->now();
      $this->recoverExpired($now);
      // A lost heartbeat is not proof a process stopped. Fence its full runtime.
      $active = $this->database->select('famtastic_worker_claim', 'c')->fields('c', ['job_id'])
        ->condition('attempt_deadline', $now, '>')->orderBy('job_id')->range(0, 1)->forUpdate()->execute()->fetchField();
      if ($active) return NULL;
      $query = $this->database->select('famtastic_worker_claim', 'c');
      $query->join('famtastic_job', 'j', 'j.id = c.job_id');
      $row = $query->fields('c')->condition('c.state', 'pending')->condition('j.status', 'worker_queued')
        ->condition('c.capability', $capabilities, 'IN')
        ->condition('j.available_at', $now, '<=')->orderBy('c.job_id')->range(0, 1)->forUpdate()->execute()->fetchAssoc();
      if (!$row) return NULL;
      $job = $this->job((int) $row['job_id']);
      if (!hash_equals($row['payload_sha256'], hash('sha256', (string) $job['payload']))) throw new \RuntimeException('Enrolled payload changed; exception review required.');
      $profile = $this->storedProfile($row);
      if ($row['capability'] === WorkerCapabilityPolicy::PROOF) WorkerCapabilityPolicy::assertProof($job, (int) $row['reservation_cents'], $this->reviewedProofCostPolicies);
      $attempt = (int) $row['attempt'] + 1;
      if ($attempt > min($profile['attempts'], (int) $job['max_attempts'])) throw new \RuntimeException('Retry bound exceeded.');
      $month = gmdate('Y-m', $this->now());
      $used = $this->reserved($month, TRUE);
      // Row/mutex acquisition may wait. Never issue an already-aged lease or
      // charge a new-month attempt to the month sampled before a lock wait.
      $now = $this->now();
      if ($month !== gmdate('Y-m', $now)) throw new \RuntimeException('Budget month changed while locking; retry.');
      // Unknown previous-month usage remains held in that month; no refund or reset of a job.
      if ($used + (int) $row['reservation_cents'] > self::STOP_CENTS) throw new \RuntimeException('worker_budget_exhausted');
      $token = bin2hex(random_bytes(32));
      $this->database->insert('famtastic_worker_budget')->fields([
        'reservation_key' => $job['id'] . ':' . $attempt, 'month' => $month, 'job_id' => $job['id'], 'attempt' => $attempt,
        'reserved_cents' => (int) $row['reservation_cents'], 'created' => $now,
      ])->execute();
      $this->updateClaim($row, [
        'state' => 'leased', 'worker_id' => $worker, 'token_hash' => hash('sha256', $token),
        'lease_until' => $now + $profile['lease'], 'attempt_deadline' => $now + $profile['execution'] + $profile['grace'],
        'attempt' => $attempt, 'changed' => $now,
      ]);
      $this->updateJob($job, ['status' => 'worker_running', 'locked_at' => $now, 'changed' => $now]);
      return [
        'job_id' => (int) $job['id'], 'job_key' => $job['job_key'], 'attempt' => $attempt, 'lease_token' => $token,
        'lease_until' => $now + $profile['lease'], 'execution_deadline' => $now + $profile['execution'],
        'heartbeat_seconds' => $profile['heartbeat'],
        'payload_sha256' => $row['payload_sha256'], 'payload_wire' => $job['payload'], 'capability' => $row['capability'],
        'reservation_cents' => (int) $row['reservation_cents'], 'policy_version' => $profile['policy'],
      ];
    });
  }

  public function renew(int $jobId, string $worker, string $token, array $authorizedCapabilities = [self::CAPABILITY], ?int $attempt = NULL): array {
    return $this->atomic(function () use ($jobId, $worker, $token, $authorizedCapabilities, $attempt): array {
      $row = $this->owned($jobId, $worker, $token, $authorizedCapabilities, $attempt);
      $profile = $this->storedProfile($row);
      $now = $this->now();
      $until = min($now + $profile['lease'], (int) $row['attempt_deadline'] - $profile['grace']);
      if ($until <= $now || (int) $row['lease_until'] <= $now) throw new \RuntimeException('Execution deadline reached or lease expired.');
      $this->updateClaim($row, ['lease_until' => $until, 'changed' => $now]);
      return ['lease_until' => $until];
    });
  }

  /** Completion proves dispatch only; signed staging callbacks remain separate. */
  public function finish(int $jobId, string $worker, string $token, array $result, array $authorizedCapabilities = [self::CAPABILITY], ?int $attempt = NULL): array {
    return $this->atomic(function () use ($jobId, $worker, $token, $result, $authorizedCapabilities, $attempt): array {
      $wire = json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
      $hash = hash('sha256', $wire);
      $row = $this->claimRow($jobId);
      if ($row) {
        $this->assertCapability($row, $authorizedCapabilities);
        if ($row['capability'] !== self::CAPABILITY) throw new \RuntimeException('Proof completion requires the future authoritative importer.');
      }
      if ($row && $row['state'] === 'handoff_completed' && $row['worker_id'] === $worker && hash_equals($row['token_hash'], hash('sha256', $token)) && hash_equals($row['result_sha256'], $hash)) {
        return ['status' => 'handoff_completed', 'duplicate' => TRUE];
      }
      $row = $this->owned($jobId, $worker, $token, $authorizedCapabilities, $attempt);
      $job = $this->job($jobId);
      $packet = json_decode($job['payload'], TRUE, flags: JSON_THROW_ON_ERROR)['packet'];
      if (($result['status'] ?? '') !== 'accepted_waiting_callback' || empty($result['receipt_id'])
        || ($result['packet_id'] ?? '') !== ($packet['packet_id'] ?? NULL)
        || ($result['idempotency_key'] ?? '') !== ($packet['idempotency_key'] ?? NULL)) throw new \InvalidArgumentException('Dispatch receipt does not prove this exact packet handoff.');
      $now = $this->now();
      if ((int) $row['lease_until'] <= $now) throw new \RuntimeException('Lease lost before completion.');
      $this->updateClaim($row, ['state' => 'handoff_completed', 'lease_until' => 0, 'attempt_deadline' => 0, 'result_sha256' => $hash, 'changed' => $now]);
      $this->updateJob($job, ['status' => 'completed', 'result' => $wire, 'completed_at' => $now, 'locked_at' => NULL, 'changed' => $now]);
      return ['status' => 'handoff_completed', 'duplicate' => FALSE];
    });
  }

  public function fail(int $jobId, string $worker, string $token, array $authorizedCapabilities = [self::CAPABILITY], ?int $attempt = NULL): array {
    return $this->atomic(function () use ($jobId, $worker, $token, $authorizedCapabilities, $attempt): array {
      $row = $this->owned($jobId, $worker, $token, $authorizedCapabilities, $attempt);
      return $this->retry($row, $this->now());
    });
  }

  /** Counts only: no queue drain, mail dispatch or cost/provider calls. */
  public function health(): array {
    $now = $this->now();
    return ['schema' => 'famtastic.automation-health.v1', 'at' => gmdate(DATE_ATOM, $now), 'php_sapi' => PHP_SAPI,
      'policy_version' => self::POLICY, 'mode' => 'observe_only', 'queue_mutations' => 0,
      'enrolled_count' => (int) $this->database->select('famtastic_worker_claim', 'c')->countQuery()->execute()->fetchField(),
      'reserved_cents' => $this->reserved(gmdate('Y-m', $now)), 'stop_cents' => self::STOP_CENTS, 'authorized_monthly_cents' => 2500,
      'laptop_independence_proven' => FALSE];
  }

  public function rememberNonce(string $worker, string $nonce): void {
    // Authentication may not be rolled back by a later caller operation or
    // checked on a lagging replica. The unique insert commits independently.
    if ($this->database->inTransaction() || ($this->database->getTarget() !== NULL && $this->database->getTarget() !== 'default')) {
      throw new \LogicException('Worker nonce authentication requires the committed primary connection.');
    }
    $key = $worker . ':' . $nonce; $expires = $this->now() + 180;
    // Unique insert remains the concurrency gate. Reject an existing nonce first
    // so an INSERT-ignore hook cannot make a replay look like this caller's write.
    if ($this->database->select('famtastic_worker_nonce', 'n')->fields('n', ['nonce_key'])->condition('nonce_key', $key)->execute()->fetchField() !== FALSE) {
      throw new \RuntimeException('Worker nonce was already consumed.');
    }
    // Require this statement's inserted row, not merely a later matching row
    // which a concurrent authenticated request might have inserted first.
    $statement = $this->database->prepareStatement('INSERT INTO {famtastic_worker_nonce} (nonce_key, expires) VALUES (:nonce_key, :expires)', [], TRUE);
    $statement->execute([':nonce_key' => $key, ':expires' => $expires]);
    if ($statement->rowCount() !== 1) throw new \RuntimeException('Worker nonce insertion was not confirmed.');
    $this->database->delete('famtastic_worker_nonce')->condition('expires', $this->now(), '<')->execute();
    $persisted = $this->database->select('famtastic_worker_nonce', 'n')->fields('n', ['expires'])->condition('nonce_key', $key)->execute()->fetchField();
    if ($persisted === FALSE || (int) $persisted !== $expires) throw new \RuntimeException('Worker nonce persistence could not be verified.');
  }

  private function recoverExpired(int $now): void {
    $rows = $this->database->select('famtastic_worker_claim', 'c')->fields('c')->condition('state', 'leased')->condition('lease_until', $now, '<=')->orderBy('job_id')->forUpdate()->execute()->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($rows as $row) $this->retry($row, $now);
  }

  private function retry(array $row, int $now): array {
    $job = $this->job((int) $row['job_id']);
    if (!$job || $job['status'] !== 'worker_running' || !hash_equals($row['payload_sha256'], hash('sha256', (string) $job['payload']))) {
      throw new \RuntimeException('Worker retry job state or payload changed; reconciliation required.');
    }
    $now = max($now, $this->now());
    $exhausted = (int) $row['attempt'] >= min($this->storedProfile($row)['attempts'], (int) $job['max_attempts']);
    $this->updateClaim($row, ['state' => $exhausted ? 'exception' : 'pending', 'token_hash' => '', 'lease_until' => 0, 'changed' => $now]);
    $this->updateJob($job, ['status' => $exhausted ? 'failed' : 'worker_queued', 'attempts' => $row['attempt'], 'locked_at' => NULL,
      'available_at' => max((int) $row['attempt_deadline'], $now + 30 * (2 ** ((int) $row['attempt'] - 1))),
      'last_error' => 'Worker interrupted or dispatch unconfirmed; retained cost reservation and immutable packet for idempotent retry.', 'changed' => $now]);
    return ['status' => $exhausted ? 'exception' : 'retry', 'attempt' => (int) $row['attempt']];
  }

  private function owned(int $id, string $worker, string $token, array $authorizedCapabilities, ?int $attempt): array {
    $this->assertWorker($worker);
    $row = $this->claimRow($id);
    $job = $this->job($id);
    if ($row) {
      $this->assertCapability($row, $authorizedCapabilities);
      if ($row['capability'] === WorkerCapabilityPolicy::PROOF) {
        if ($attempt !== (int) $row['attempt']) throw new \RuntimeException('Proof attempt generation mismatch.');
        if ($this->now() >= (int) $row['attempt_deadline'] - $this->storedProfile($row)['grace']) throw new \RuntimeException('Proof execution deadline reached.');
        if ($job) WorkerCapabilityPolicy::assertProof($job, (int) $row['reservation_cents'], $this->reviewedProofCostPolicies);
      }
    }
    if (!$row || $row['state'] !== 'leased' || $row['worker_id'] !== $worker || (int) $row['lease_until'] <= $this->now()
      || !preg_match('/^[a-f0-9]{64}$/', $token) || !hash_equals($row['token_hash'], hash('sha256', $token))
      || !$job || $job['status'] !== 'worker_running' || !hash_equals($row['payload_sha256'], hash('sha256', $job['payload']))) {
      throw new \RuntimeException('Lease lost, foreign worker, expired token or changed payload.');
    }
    return $row;
  }

  /**
   * Internal locked read only, never a submission permit. Caller must first lock
   * current request/account/assets on this connection, then commit its own work.
   */
  public function lockOwnedProofClaim(int $id, string $worker, string $token, array $authorizedCapabilities, int $attempt, Connection $connection): array {
    if ($connection !== $this->database) throw new \LogicException('Proof claim requires the same database connection.');
    if (!$this->database->inTransaction()) throw new \LogicException('Proof ownership read requires an active transaction.');
    WorkerCoordinatorMutex::acquire($this->database);
    $row = $this->owned($id, $worker, $token, $authorizedCapabilities, $attempt);
    if ($row['capability'] !== WorkerCapabilityPolicy::PROOF) throw new \RuntimeException('Paid operations require a proof claim.');
    return $row;
  }

  private function storedProfile(array $row): array {
    $profile = WorkerCapabilityPolicy::profile($row['capability']);
    if ($row['policy_version'] !== $profile['policy']) throw new \RuntimeException('Stored worker policy mismatch.');
    return $profile;
  }

  /** Internal importer only: request/account/assets must already be locked. */
  public function completeProofImportLocked(int $id, string $worker, string $token, array $grants, int $attempt, string $receiptHash, Connection $connection): void {
    $row = $this->lockOwnedProofClaim($id, $worker, $token, $grants, $attempt, $connection);
    $facts = ManagedProofImportReceipt::pendingLocked($connection, $id, $receiptHash);
    $job = $this->job($id);
    // Re-sample AFTER all receipt checks. The final CAS still cannot promise
    // elapsed wall time stops during DB commit; this is not an HTTP transaction.
    $row = $this->owned($id, $worker, $token, $grants, $attempt);
    $now = $this->now();
    $this->updateClaim($row, ['state' => 'proof_imported', 'lease_until' => 0, 'attempt_deadline' => 0, 'result_sha256' => $receiptHash, 'changed' => $now]);
    $this->updateJob($job, ['status' => 'completed', 'result' => ManagedProofImportContract::result($facts['receipt']),
      'completed_at' => $facts['receipt']['imported_at'], 'locked_at' => NULL, 'changed' => $now]);
  }

  private function assertCapability(array $row, array $authorizedCapabilities): void {
    $this->storedProfile($row);
    if (!in_array($row['capability'], WorkerCapabilityPolicy::claimCapabilities($authorizedCapabilities), TRUE)) throw new \RuntimeException('Worker capability rejected.');
  }

  private function reserved(string $month, bool $lock = FALSE): int {
    $query = $this->database->select('famtastic_worker_budget', 'b')->condition('month', $month);
    if ($lock) {
      // Lock base rows: a SUM/COUNT can otherwise reuse an outer TX snapshot.
      $rows = $query->fields('b', ['reservation_key', 'reserved_cents'])->orderBy('reservation_key')->forUpdate()->execute()->fetchAll(\PDO::FETCH_ASSOC);
      return (int) array_sum(array_column($rows, 'reserved_cents'));
    }
    $query->addExpression('COALESCE(SUM(reserved_cents), 0)', 'reserved');
    return (int) $query->execute()->fetchField();
  }

  private function claimRow(int $id): array|false { return $this->database->select('famtastic_worker_claim', 'c')->fields('c')->condition('job_id', $id)->forUpdate()->execute()->fetchAssoc(); }
  private function job(int $id): array|false { return $this->database->select('famtastic_job', 'j')->fields('j')->condition('id', $id)->forUpdate()->execute()->fetchAssoc(); }
  private function now(): int { return $this->time->getCurrentTime(); }
  private function assertWorker(string $worker): void { if (!preg_match('/^[a-z][a-z0-9._-]{2,63}$/', $worker)) throw new \InvalidArgumentException('Invalid worker identity.'); }

  private function atomic(callable $operation): mixed {
    return WorkerCoordinatorMutex::run($this->database, function () use ($operation): mixed {
      // Optional busy hint ONLY, acquired after the correctness mutex. Its TTL
      // and early release cannot release the root transaction's database lock.
      $name = 'famtastic:bounded-worker-coordinator:v1';
      if (!$this->lock->acquire($name, 30)) throw new \RuntimeException('Worker coordinator busy; retry with jitter.');
      try { return $operation(); }
      finally { $this->lock->release($name); }
    });
  }

  private function updateClaim(array $before, array $fields): void {
    $query = $this->database->update('famtastic_worker_claim')->fields($fields);
    foreach (['job_id', 'state', 'attempt', 'worker_id', 'token_hash', 'lease_until', 'attempt_deadline',
      'policy_version', 'capability', 'payload_sha256', 'reservation_cents', 'result_sha256', 'changed'] as $key) $query->condition($key, $before[$key]);
    if ($query->execute() !== 1) throw new \RuntimeException('Worker claim changed; transaction rolled back.');
  }

  private function updateJob(array $before, array $fields): void {
    $query = $this->database->update('famtastic_job')->fields($fields);
    foreach (['id', 'status', 'attempts', 'max_attempts', 'job_key', 'job_type', 'payload', 'available_at', 'locked_at', 'changed'] as $key) {
      $before[$key] === NULL ? $query->isNull($key) : $query->condition($key, $before[$key]);
    }
    if ($query->execute() !== 1) throw new \RuntimeException('Worker job changed; transaction rolled back.');
  }
}
