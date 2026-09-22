<?php
declare(strict_types=1);
namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;

/**
 * Unregistered source groundwork, not a provider or an exactly-once HTTP protocol.
 * Verifiers are reviewed local-only dependencies, never supplied by worker data.
 * Input verifier proves complete prepared bytes/asset use; receipt verifier proves
 * authenticated actor, provider identity and terminal evidence. Both run OUTSIDE
 * transactions. Missing verifiers/catalog close every public operation.
 */
final class ProofOperationJournal {
  public function __construct(private readonly Connection $database, private readonly TimeInterface $time,
    private readonly FreshProofAdmission $admission, private readonly WorkerCoordinator $coordinator,
    private readonly array $reviewedOperations = [], private readonly ?\Closure $verifyInput = NULL,
    private readonly ?\Closure $verifyReceipt = NULL) {}

  /** Server-authenticated identity/grants only. A replay never receives a permit. */
  public function authorizeSubmission(int $requestId, int $jobId, string $worker, string $token, int $attempt,
    array $authorizedCapabilities, string $slot, array $input): array {
    return $this->operation($requestId, $jobId, $worker, $token, $attempt, $authorizedCapabilities, $slot, $input, TRUE);
  }

  /** Current authority is required even for an old successful checkpoint. */
  public function resumeCheckpoint(int $requestId, int $jobId, string $worker, string $token, int $attempt,
    array $authorizedCapabilities, string $slot, array $input): array {
    return $this->operation($requestId, $jobId, $worker, $token, $attempt, $authorizedCapabilities, $slot, $input, FALSE);
  }

  private function operation(int $requestId, int $jobId, string $worker, string $token, int $attempt,
    array $grants, string $slot, array $input, bool $allowNew): array {
    $this->configured();
    ProofOperationContract::id($slot);
    $input = ProofOperationContract::input($input);
    if (($this->verifyInput)($requestId, $input) !== TRUE) throw new \RuntimeException('Prepared operation input is unverified.');
    $result = $this->root(function () use ($requestId, $jobId, $worker, $token, $attempt, $grants, $slot, $input, $allowNew): array {
      $binding = $this->admission->lockCurrentBinding($requestId, $this->database);
      if ((int) $binding['job']['id'] !== $jobId) throw new \RuntimeException('Operation job differs from current admission.');
      $claim = $this->coordinator->lockOwnedProofClaim($jobId, $worker, $token, $grants, $attempt, $this->database);
      $payload = $binding['payload'];
      $spec = ProofOperationContract::slot($payload, $this->reviewedOperations, $slot);
      ProofOperationContract::rights($input, $binding['binding']);
      $policyHash = hash('sha256', ProofOperationContract::wire($this->reviewedOperations[$payload['cost_policy']['id']]));
      $identity = ['schema' => 'famtastic.proof-operation.v1', 'job_id' => $jobId, 'request_id' => $requestId, 'slot' => $slot,
        'payload_sha256' => $claim['payload_sha256'], 'binding_sha256' => hash('sha256', FreshProofInput::wire($binding['binding'])),
        'policy_sha256' => $policyHash, 'spec' => $spec, 'input' => $input];
      $operationId = hash('sha256', 'proof-operation-v1:' . $jobId . ':' . $slot);
      $rows = $this->database->select('famtastic_proof_operation', 'o')->fields('o')->condition('job_id', $jobId)->orderBy('operation_id')->forUpdate()->execute()->fetchAll(\PDO::FETCH_ASSOC);
      $reserved = 0;
      foreach ($rows as $row) {
        $stored = $this->identity($row);
        $checkpoint = $this->checkpoint($row);
        if ($stored['policy_sha256'] !== $policyHash || $stored['payload_sha256'] !== $claim['payload_sha256']) throw new \RuntimeException('Stored operation policy or payload changed.');
        $reserved += (int) $row['reserved_cents'];
        if ($row['operation_id'] === $operationId) {
          foreach ($identity as $key => $value) if (($stored[$key] ?? NULL) !== $value) throw new \RuntimeException('Operation identity is immutable.');
          return $checkpoint;
        }
      }
      if (!$allowNew) throw new \RuntimeException('Operation checkpoint is missing.');
      // Unknown remote activity outlives a claim deadline. No next paid call,
      // including another job, while any authorized operation is unresolved.
      if ($this->database->select('famtastic_proof_operation', 'o')->fields('o', ['operation_id'])->condition('state', 'submission_unknown')->range(0, 1)->forUpdate()->execute()->fetchField()) throw new \RuntimeException('Unresolved paid operation requires reconciliation.');
      $cost = $payload['cost_policy'];
      if (count($rows) >= $cost['max_calls'] || $reserved + $spec['max_cost_cents'] > $cost['max_cost_cents']) throw new \RuntimeException('Job operation call or cost bound exhausted.');
      $reservationKey = $jobId . ':' . $attempt;
      $hold = $this->database->select('famtastic_worker_budget', 'b')->fields('b')->condition('reservation_key', $reservationKey)->forUpdate()->execute()->fetchAssoc();
      $now = $this->time->getCurrentTime();
      if (!$hold || (int) $hold['job_id'] !== $jobId || (int) $hold['attempt'] !== $attempt
        || (int) $hold['reserved_cents'] !== (int) $claim['reservation_cents']
        || $hold['month'] !== gmdate('Y-m', $now)) throw new \RuntimeException('Operation requires its original current-month budget hold.');
      $until = min((int) $claim['lease_until'], (int) $claim['attempt_deadline'] - 30);
      $this->window($now, $until, $spec);
      $identity += ['producer_id' => $worker, 'authorizing_attempt' => $attempt, 'reservation_key' => $reservationKey, 'month' => $hold['month']];
      $wire = ProofOperationContract::wire($identity);
      $this->database->insert('famtastic_proof_operation')->fields([
        'operation_id' => $operationId, 'job_id' => $jobId, 'slot' => $slot, 'identity_wire' => $wire, 'identity_sha256' => hash('sha256', $wire),
        'reserved_cents' => $spec['max_cost_cents'], 'state' => 'submission_unknown', 'receipt_wire' => '', 'created' => $now, 'changed' => $now,
      ])->execute();
      $inserted = $this->database->select('famtastic_proof_operation', 'o')->fields('o')->condition('operation_id', $operationId)->forUpdate()->execute()->fetchAssoc();
      if (!$inserted || $inserted['identity_wire'] !== $wire || $inserted['state'] !== 'submission_unknown') throw new \RuntimeException('Operation insert was not confirmed.');
      $this->identity($inserted);
      // Recheck only already-owned authority after insertion, including fault
      // interleavings. No untrusted callbacks or provider work in this scope.
      $this->admission->lockCurrentBinding($requestId, $this->database);
      $this->coordinator->lockOwnedProofClaim($jobId, $worker, $token, $grants, $attempt, $this->database);
      return ['status' => 'submit_once', 'operation_id' => $operationId, 'identity' => $identity,
        'authorized_at' => $now, 'lease_until' => $until, 'attempt' => $attempt];
    });
    if ($result['status'] === 'submit_once') {
      $now = $this->time->getCurrentTime();
      if ($now < $result['authorized_at'] || gmdate('Y-m', $now) !== $result['identity']['month']) throw new \RuntimeException('Operation clock changed after commit; reconciliation required.');
      $this->window($now, $result['lease_until'], $result['identity']['spec']);
    }
    return $result;
  }

  /**
   * Evidence-only write. A trusted verifier authenticates recorder independently
   * of expired claim tokens. Never changes a claim, job, hold, rights or QA state.
   */
  public function recordReceipt(string $operationId, string $recorder, array $receipt): array {
    $this->configured(); ProofOperationContract::digest($operationId);
    if (!preg_match('/\A[a-z][a-z0-9._-]{2,63}\z/', $recorder)) throw new \InvalidArgumentException('Invalid receipt recorder identity.');
    $receipt = ProofOperationContract::receipt($receipt);
    $before = $this->database->select('famtastic_proof_operation', 'o')->fields('o')->condition('operation_id', $operationId)->execute()->fetchAssoc();
    if (!$before) throw new \RuntimeException('Operation evidence is missing.');
    $identity = $this->identity($before);
    $this->receiptIdentity($before, $identity, $receipt);
    if (($this->verifyReceipt)($identity, $recorder, $receipt) !== TRUE) throw new \RuntimeException('Operation receipt is unverified.');
    return $this->root(function () use ($operationId, $before, $recorder, $receipt): array {
      WorkerCoordinatorMutex::acquire($this->database);
      $row = $this->database->select('famtastic_proof_operation', 'o')->fields('o')->condition('operation_id', $operationId)->forUpdate()->execute()->fetchAssoc();
      if (!$row || $row['identity_wire'] !== $before['identity_wire'] || $row['identity_sha256'] !== $before['identity_sha256']) throw new \RuntimeException('Operation identity changed during receipt verification.');
      $this->receiptIdentity($row, $this->identity($row), $receipt);
      $wire = ProofOperationContract::wire($receipt);
      if ($row['state'] === 'receipt_recorded') {
        if ($row['receipt_wire'] !== $wire || $row['receipt_sha256'] !== hash('sha256', $wire)) throw new \RuntimeException('Operation receipt conflict.');
        return ['status' => 'receipt_recorded', 'duplicate' => TRUE];
      }
      if ($row['state'] !== 'submission_unknown' || $row['receipt_wire'] !== '' || $row['receipt_sha256'] !== '') throw new \RuntimeException('Operation receipt state is inconsistent.');
      $query = $this->database->update('famtastic_proof_operation')->fields(['state' => 'receipt_recorded', 'receipt_wire' => $wire,
        'receipt_sha256' => hash('sha256', $wire), 'recorder_id' => $recorder, 'changed' => $this->time->getCurrentTime()]);
      foreach ($row as $key => $value) $query->condition($key, $value);
      if ($query->execute() !== 1) throw new \RuntimeException('Operation receipt changed; transaction rolled back.');
      return ['status' => 'receipt_recorded', 'duplicate' => FALSE];
    });
  }

  private function identity(array $row): array {
    $identity = json_decode($row['identity_wire'], TRUE, 16, JSON_THROW_ON_ERROR);
    if (is_array($identity)) ProofOperationContract::keys($identity, ['schema', 'job_id', 'request_id', 'slot', 'payload_sha256', 'binding_sha256',
      'policy_sha256', 'spec', 'input', 'producer_id', 'authorizing_attempt', 'reservation_key', 'month']);
    if (!is_array($identity) || ProofOperationContract::wire($identity) !== $row['identity_wire']
      || ($identity['schema'] ?? '') !== 'famtastic.proof-operation.v1' || !in_array($row['state'], ['submission_unknown', 'receipt_recorded'], TRUE)
      || ($row['state'] === 'submission_unknown' && ($row['receipt_wire'] !== '' || $row['receipt_sha256'] !== '' || $row['recorder_id'] !== ''))
      || hash('sha256', $row['identity_wire']) !== $row['identity_sha256']
      || ($identity['job_id'] ?? NULL) !== (int) $row['job_id'] || ($identity['slot'] ?? NULL) !== $row['slot']
      || ($identity['spec']['max_cost_cents'] ?? NULL) !== (int) $row['reserved_cents']
      || hash('sha256', 'proof-operation-v1:' . $row['job_id'] . ':' . $row['slot']) !== $row['operation_id']) throw new \RuntimeException('Stored operation identity is corrupt.');
    return $identity;
  }

  /**
   * Internal import read: authority and mutex already owned on this connection.
   * Required successful slots come from reviewed SOURCE policy, never a worker.
   * Every recorded slot needs a trusted terminal receipt, not known billing.
   * No verifier callback, provider operation, hold reduction or receipt repair.
   */
  public function lockImportEvidence(array $binding, array $completionPolicy, array $expected, Connection $connection): array {
    if ($connection !== $this->database || !$connection->inTransaction()) throw new \LogicException('Import journal read requires the same active connection.');
    ProofOperationContract::keys($completionPolicy, ['operation_policy_sha256', 'required_success_slots']);
    $requiredSlots = $completionPolicy['required_success_slots'];
    if (!$this->reviewedOperations || !array_is_list($requiredSlots) || !$requiredSlots || count($requiredSlots) > 32
      || count(array_unique($requiredSlots)) !== count($requiredSlots)) throw new \RuntimeException('Import completion slots are unconfigured.');
    $payload = $binding['payload']; $jobId = (int) $binding['job']['id'];
    foreach ($requiredSlots as $slot) { ProofOperationContract::id($slot); ProofOperationContract::slot($payload, $this->reviewedOperations, $slot); }
    $policyHash = hash('sha256', ProofOperationContract::wire($this->reviewedOperations[$payload['cost_policy']['id']]));
    if ($completionPolicy['operation_policy_sha256'] !== $policyHash) throw new \RuntimeException('Import completion catalog differs from the journal policy.');
    $rows = $connection->select('famtastic_proof_operation', 'o')->fields('o')->condition('job_id', $jobId)->orderBy('operation_id')->range(0, 33)->forUpdate()->execute()->fetchAll(\PDO::FETCH_ASSOC);
    if (count($rows) > min(32, $payload['cost_policy']['max_calls'])) throw new \RuntimeException('Import operation count exceeds the frozen bound.');
    $facts = []; $succeeded = []; $reserved = 0;
    foreach ($rows as $row) {
      $i = $this->identity($row); $checkpoint = $this->checkpoint($row);
      if ($checkpoint['status'] === 'reconciliation_required') throw new \RuntimeException('Import requires terminal operation evidence.');
      $spec = ProofOperationContract::slot($payload, $this->reviewedOperations, $row['slot']);
      if ($i['request_id'] !== $payload['website_request_id'] || $i['payload_sha256'] !== $binding['claim']['payload_sha256']
        || $i['binding_sha256'] !== hash('sha256', FreshProofInput::wire($binding['binding'])) || $i['policy_sha256'] !== $policyHash
        || $i['spec'] !== $spec || !is_int($i['authorizing_attempt']) || $i['authorizing_attempt'] < 1
        || $i['authorizing_attempt'] > (int) $binding['claim']['attempt'] || $i['reservation_key'] !== $jobId . ':' . $i['authorizing_attempt']) throw new \RuntimeException('Import operation lineage differs.');
      ProofOperationContract::input($i['input']); ProofOperationContract::rights($i['input'], $binding['binding']);
      $hold = $connection->select('famtastic_worker_budget', 'b')->fields('b')->condition('reservation_key', $i['reservation_key'])->forUpdate()->execute()->fetchAssoc();
      if (!$hold || (int) $hold['job_id'] !== $jobId || (int) $hold['attempt'] !== $i['authorizing_attempt']
        || $hold['month'] !== $i['month'] || (int) $hold['reserved_cents'] !== (int) $binding['claim']['reservation_cents']) throw new \RuntimeException('Import operation original hold differs.');
      $reserved += (int) $row['reserved_cents'];
      if ($checkpoint['status'] === 'checkpoint') $succeeded[] = $row['slot'];
      $facts[$row['operation_id']] = ['identity_sha256' => $row['identity_sha256'], 'receipt_sha256' => $row['receipt_sha256'],
        'producer_id' => ManagedProofImportContract::workerIdentity($i['producer_id']), 'recorder_id' => ManagedProofImportContract::workerIdentity($row['recorder_id'])];
    }
    if ($reserved > $payload['cost_policy']['max_cost_cents'] || array_diff($requiredSlots, $succeeded)) throw new \RuntimeException('Import completion requirements are not satisfied.');
    if ($facts !== $expected) throw new \RuntimeException('Import provenance differs from locked journal evidence.');
    return $facts;
  }

  private function receiptIdentity(array $row, array $identity, array $receipt): void {
    if ($receipt['operation_id'] !== $row['operation_id'] || $receipt['input_sha256'] !== $identity['input']['input_sha256']
      || $receipt['adapter'] !== $identity['spec']['adapter']
      || ($receipt['cost_status'] === 'verified' && $receipt['actual_cost_cents'] > (int) $row['reserved_cents'])) throw new \RuntimeException('Receipt identity or cost exceeds the frozen operation.');
  }

  private function checkpoint(array $row): array {
    if ($row['state'] === 'submission_unknown') return ['status' => 'reconciliation_required', 'operation_id' => $row['operation_id']];
    if ($row['state'] !== 'receipt_recorded' || hash('sha256', $row['receipt_wire']) !== $row['receipt_sha256']) throw new \RuntimeException('Stored operation receipt is corrupt.');
    $receipt = ProofOperationContract::receipt(json_decode($row['receipt_wire'], TRUE, 16, JSON_THROW_ON_ERROR));
    if (ProofOperationContract::wire($receipt) !== $row['receipt_wire']) throw new \RuntimeException('Stored operation receipt bytes changed.');
    $identity = $this->identity($row); $this->receiptIdentity($row, $identity, $receipt);
    return ['status' => $receipt['outcome'] === 'succeeded' ? 'checkpoint' : 'known_failure', 'operation_id' => $row['operation_id'],
      'producer_id' => $identity['producer_id'], 'recorder_id' => $row['recorder_id'], 'receipt' => $receipt];
  }

  private function configured(): void {
    if ($this->database->inTransaction()) throw new \LogicException('Operation journal requires its own root transaction.');
    if (!$this->reviewedOperations || !$this->verifyInput || !$this->verifyReceipt) throw new \RuntimeException('Operation journal is unconfigured.');
  }
  private function window(int $now, int $until, array $spec): void {
    if ($until - $now < $spec['timeout_seconds'] + $spec['headroom_seconds']) throw new \RuntimeException('Insufficient operation lease window; no submission permitted.');
  }

  private function root(callable $operation): array {
    if ($this->database->inTransaction()) throw new \LogicException('Operation journal requires its own root transaction.');
    $transaction = $this->database->startTransaction();
    try {
      $result = $operation();
      $transaction->commitOrRelease();
      // Drupal runs post-transaction callbacks on destruction. A failure there
      // must withhold the permit too, even though the unknown row may persist.
      unset($transaction);
      if ($this->database->inTransaction()) throw new \RuntimeException('Operation root commit is unconfirmed.');
      return $result;
    }
    catch (\Throwable $error) {
      if (isset($transaction) && $this->database->inTransaction()) {
        try { $transaction->rollBack(); } catch (\Throwable) { /* No permit; outcome remains unknown. */ }
      }
      try { unset($transaction); } catch (\Throwable) { /* Preserve the original failure. */ }
      throw $error;
    }
  }
}
