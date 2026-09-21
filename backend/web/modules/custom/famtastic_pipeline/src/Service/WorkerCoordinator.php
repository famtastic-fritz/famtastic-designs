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

  public function __construct(private readonly Connection $database, private readonly TimeInterface $time, private readonly LockBackendInterface $lock) {}

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
    if ($reservationCents < 25 || $reservationCents > 250 || !preg_match('/^[a-f0-9]{64}$/', $payloadHash)) {
      throw new \InvalidArgumentException('Each attempt needs a conservative 25–250 cent reservation and immutable payload hash.');
    }
    return $this->atomic(function () use ($jobId, $jobKey, $payloadHash, $reservationCents): array {
      $job = $this->job($jobId);
      if (!$job || $job['job_key'] !== $jobKey || $job['job_type'] !== 'site_studio_staging_prepare'
        || !hash_equals(hash('sha256', (string) $job['payload']), $payloadHash)) {
        throw new \InvalidArgumentException('Admission requires the exact selected-staging job and frozen payload.');
      }
      $existing = $this->claimRow($jobId);
      if ($existing) {
        if ($existing['payload_sha256'] !== $payloadHash || (int) $existing['reservation_cents'] !== $reservationCents) throw new \RuntimeException('Enrollment is immutable.');
        return ['status' => 'already_enrolled', 'job_id' => $jobId];
      }
      if ($job['status'] !== 'queued' || (int) $job['attempts'] !== 0) throw new \RuntimeException('Only fresh unattempted jobs may enroll; historical work requires separate reconciliation.');
      if (!self::supportsPayload($job['job_type'], json_decode((string) $job['payload'], TRUE, flags: JSON_THROW_ON_ERROR))) {
        throw new \RuntimeException('Planning/ecommerce work requires an implementation capability, not static packaging.');
      }
      $this->database->insert('famtastic_worker_claim')->fields([
        'job_id' => $jobId, 'policy_version' => self::POLICY, 'payload_sha256' => $payloadHash,
        'capability' => self::CAPABILITY, 'reservation_cents' => $reservationCents, 'state' => 'pending', 'changed' => $this->now(),
      ])->execute();
      if ($this->database->update('famtastic_job')->fields(['status' => 'worker_queued', 'changed' => $this->now()])
        ->condition('id', $jobId)->condition('status', 'queued')->execute() !== 1) throw new \RuntimeException('A legacy worker already owns the job.');
      return ['status' => 'enrolled', 'job_id' => $jobId];
    });
  }

  /** No enrollment means no work. The legacy worker cannot claim worker_queued. */
  public function claim(string $worker): ?array {
    $this->assertWorker($worker);
    return $this->atomic(function () use ($worker): ?array {
      $now = $this->now();
      $this->recoverExpired($now);
      // A lost heartbeat is not proof a process stopped. Fence its full runtime.
      $active = $this->database->select('famtastic_worker_claim', 'c')->condition('attempt_deadline', $now, '>')->countQuery()->execute()->fetchField();
      if ($active) return NULL;
      $query = $this->database->select('famtastic_worker_claim', 'c');
      $query->join('famtastic_job', 'j', 'j.id = c.job_id');
      $row = $query->fields('c')->condition('c.state', 'pending')->condition('j.status', 'worker_queued')
        ->condition('j.available_at', $now, '<=')->orderBy('c.job_id')->range(0, 1)->execute()->fetchAssoc();
      if (!$row) return NULL;
      $job = $this->job((int) $row['job_id']);
      if (!hash_equals($row['payload_sha256'], hash('sha256', (string) $job['payload']))) throw new \RuntimeException('Enrolled payload changed; exception review required.');
      $attempt = (int) $row['attempt'] + 1;
      if ($attempt > min(3, (int) $job['max_attempts'])) throw new \RuntimeException('Retry bound exceeded.');
      $month = gmdate('Y-m', $now);
      $used = $this->reserved($month);
      // Unknown previous-month usage remains held in that month; no refund or reset of a job.
      if ($used + (int) $row['reservation_cents'] > self::STOP_CENTS) throw new \RuntimeException('worker_budget_exhausted');
      $token = bin2hex(random_bytes(32));
      $this->database->insert('famtastic_worker_budget')->fields([
        'reservation_key' => $job['id'] . ':' . $attempt, 'month' => $month, 'job_id' => $job['id'], 'attempt' => $attempt,
        'reserved_cents' => (int) $row['reservation_cents'], 'created' => $now,
      ])->execute();
      $this->database->update('famtastic_worker_claim')->fields([
        'state' => 'leased', 'worker_id' => $worker, 'token_hash' => hash('sha256', $token),
        'lease_until' => $now + self::LEASE_SECONDS, 'attempt_deadline' => $now + self::MAX_RUN_SECONDS + 30,
        'attempt' => $attempt, 'changed' => $now,
      ])->condition('job_id', $job['id'])->execute();
      if ($this->database->update('famtastic_job')->fields(['status' => 'worker_running', 'locked_at' => $now, 'changed' => $now])
        ->condition('id', $job['id'])->condition('status', 'worker_queued')->execute() !== 1) throw new \RuntimeException('Queue state changed.');
      return [
        'job_id' => (int) $job['id'], 'job_key' => $job['job_key'], 'attempt' => $attempt, 'lease_token' => $token,
        'lease_until' => $now + self::LEASE_SECONDS, 'execution_deadline' => $now + self::MAX_RUN_SECONDS,
        'payload_sha256' => $row['payload_sha256'], 'payload_wire' => $job['payload'], 'capability' => self::CAPABILITY,
        'reservation_cents' => (int) $row['reservation_cents'], 'policy_version' => self::POLICY,
      ];
    });
  }

  public function renew(int $jobId, string $worker, string $token): array {
    return $this->atomic(function () use ($jobId, $worker, $token): array {
      $row = $this->owned($jobId, $worker, $token);
      $until = min($this->now() + self::LEASE_SECONDS, (int) $row['attempt_deadline'] - 30);
      if ($until <= $this->now()) throw new \RuntimeException('Execution deadline reached.');
      $this->database->update('famtastic_worker_claim')->fields(['lease_until' => $until, 'changed' => $this->now()])->condition('job_id', $jobId)->execute();
      return ['lease_until' => $until];
    });
  }

  /** Completion proves dispatch only; signed staging callbacks remain separate. */
  public function finish(int $jobId, string $worker, string $token, array $result): array {
    return $this->atomic(function () use ($jobId, $worker, $token, $result): array {
      $wire = json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
      $hash = hash('sha256', $wire);
      $row = $this->claimRow($jobId);
      if ($row && $row['state'] === 'handoff_completed' && $row['worker_id'] === $worker && hash_equals($row['token_hash'], hash('sha256', $token)) && hash_equals($row['result_sha256'], $hash)) {
        return ['status' => 'handoff_completed', 'duplicate' => TRUE];
      }
      $this->owned($jobId, $worker, $token);
      $job = $this->job($jobId);
      $packet = json_decode($job['payload'], TRUE, flags: JSON_THROW_ON_ERROR)['packet'];
      if (($result['status'] ?? '') !== 'accepted_waiting_callback' || empty($result['receipt_id'])
        || ($result['packet_id'] ?? '') !== ($packet['packet_id'] ?? NULL)
        || ($result['idempotency_key'] ?? '') !== ($packet['idempotency_key'] ?? NULL)) throw new \InvalidArgumentException('Dispatch receipt does not prove this exact packet handoff.');
      $this->database->update('famtastic_worker_claim')->fields(['state' => 'handoff_completed', 'lease_until' => 0, 'attempt_deadline' => 0, 'result_sha256' => $hash, 'changed' => $this->now()])->condition('job_id', $jobId)->execute();
      $this->database->update('famtastic_job')->fields(['status' => 'completed', 'result' => $wire, 'completed_at' => $this->now(), 'locked_at' => NULL, 'changed' => $this->now()])->condition('id', $jobId)->condition('status', 'worker_running')->execute();
      return ['status' => 'handoff_completed', 'duplicate' => FALSE];
    });
  }

  public function fail(int $jobId, string $worker, string $token): array {
    return $this->atomic(function () use ($jobId, $worker, $token): array {
      $row = $this->owned($jobId, $worker, $token);
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
    // Unique insert is the replay gate; duplicate requests fail closed.
    $this->database->insert('famtastic_worker_nonce')->fields(['nonce_key' => $worker . ':' . $nonce, 'expires' => $this->now() + 180])->execute();
    $this->database->delete('famtastic_worker_nonce')->condition('expires', $this->now(), '<')->execute();
  }

  private function recoverExpired(int $now): void {
    $rows = $this->database->select('famtastic_worker_claim', 'c')->fields('c')->condition('state', 'leased')->condition('lease_until', $now, '<=')->execute()->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($rows as $row) $this->retry($row, $now);
  }

  private function retry(array $row, int $now): array {
    $job = $this->job((int) $row['job_id']);
    $exhausted = (int) $row['attempt'] >= min(3, (int) $job['max_attempts']);
    $this->database->update('famtastic_worker_claim')->fields(['state' => $exhausted ? 'exception' : 'pending', 'token_hash' => '', 'lease_until' => 0, 'changed' => $now])->condition('job_id', $row['job_id'])->execute();
    $this->database->update('famtastic_job')->fields(['status' => $exhausted ? 'failed' : 'worker_queued', 'attempts' => $row['attempt'], 'locked_at' => NULL,
      'available_at' => max((int) $row['attempt_deadline'], $now + 30 * (2 ** ((int) $row['attempt'] - 1))),
      'last_error' => 'Worker interrupted or dispatch unconfirmed; retained cost reservation and immutable packet for idempotent retry.', 'changed' => $now])->condition('id', $row['job_id'])->execute();
    return ['status' => $exhausted ? 'exception' : 'retry', 'attempt' => (int) $row['attempt']];
  }

  private function owned(int $id, string $worker, string $token): array {
    $this->assertWorker($worker);
    $row = $this->claimRow($id);
    $job = $this->job($id);
    if (!$row || $row['state'] !== 'leased' || $row['worker_id'] !== $worker || (int) $row['lease_until'] <= $this->now()
      || !preg_match('/^[a-f0-9]{64}$/', $token) || !hash_equals($row['token_hash'], hash('sha256', $token))
      || !$job || $job['status'] !== 'worker_running' || !hash_equals($row['payload_sha256'], hash('sha256', $job['payload']))) {
      throw new \RuntimeException('Lease lost, foreign worker, expired token or changed payload.');
    }
    return $row;
  }

  private function reserved(string $month): int {
    $query = $this->database->select('famtastic_worker_budget', 'b')->condition('month', $month);
    $query->addExpression('COALESCE(SUM(reserved_cents), 0)', 'reserved');
    return (int) $query->execute()->fetchField();
  }

  private function claimRow(int $id): array|false { return $this->database->select('famtastic_worker_claim', 'c')->fields('c')->condition('job_id', $id)->execute()->fetchAssoc(); }
  private function job(int $id): array|false { return $this->database->select('famtastic_job', 'j')->fields('j')->condition('id', $id)->execute()->fetchAssoc(); }
  private function now(): int { return $this->time->getCurrentTime(); }
  private function assertWorker(string $worker): void { if (!preg_match('/^[a-z][a-z0-9._-]{2,63}$/', $worker)) throw new \InvalidArgumentException('Invalid worker identity.'); }

  private function atomic(callable $operation): mixed {
    // Drupal's shared database lock serializes enrollment, budget reservations and leases.
    $name = 'famtastic:bounded-worker-coordinator:v1';
    if (!$this->lock->acquire($name, 30)) throw new \RuntimeException('Worker coordinator busy; retry with jitter.');
    $transaction = $this->database->startTransaction();
    try { $result = $operation(); unset($transaction); return $result; }
    catch (\Throwable $e) { $transaction->rollBack(); throw $e; }
    finally { $this->lock->release($name); }
  }
}
