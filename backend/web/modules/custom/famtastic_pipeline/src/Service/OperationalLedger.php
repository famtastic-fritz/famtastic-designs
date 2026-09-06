<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;

/**
 * Durable, idempotent operational records for autonomous pipeline work.
 */
final class OperationalLedger {

  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Returns the active immutable offer version.
   */
  public function activeOffer(string $offerKey): ?array {
    $record = $this->database->select('famtastic_offer_version', 'o')
      ->fields('o')
      ->condition('offer_key', $offerKey)
      ->condition('active', 1)
      ->orderBy('version', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!$record) {
      return NULL;
    }
    foreach (['id', 'version', 'amount_minor', 'included_revisions', 'hosting_months', 'active', 'created'] as $field) {
      $record[$field] = (int) $record[$field];
    }
    $record['definition'] = json_decode((string) $record['definition'], TRUE, flags: JSON_THROW_ON_ERROR);
    return $record;
  }

  /**
   * Returns the active website-service terms version.
   */
  public function activeTerms(): ?array {
    $record = $this->database->select('famtastic_terms_version', 't')
      ->fields('t')
      ->condition('terms_key', 'website_service')
      ->condition('active', 1)
      ->orderBy('version', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!$record) {
      return NULL;
    }
    foreach (['id', 'version', 'effective_at', 'active', 'created'] as $field) {
      $record[$field] = (int) $record[$field];
    }
    return $record;
  }

  /** Returns an immutable checksum-ready snapshot of the exact SKU promises. */
  public function dealSnapshotForSkus(array $skus): array {
    $catalogPath = dirname(\Drupal::root()) . '/config/famtastic-products.json';
    $dealPath = dirname(\Drupal::root()) . '/config/famtastic-deal-terms.json';
    $catalog = json_decode((string) file_get_contents($catalogPath), TRUE, 512, JSON_THROW_ON_ERROR);
    $registry = json_decode((string) file_get_contents($dealPath), TRUE, 512, JSON_THROW_ON_ERROR);
    $products = [];
    foreach ($catalog['products'] ?? [] as $product) $products[$product['sku']] = $product;
    $items = [];
    foreach (array_values(array_unique($skus)) as $sku) {
      if (empty($products[$sku]) || empty($registry['deals'][$sku])) throw new \RuntimeException('deal_definition_missing:' . $sku);
      $items[] = ['sku' => $sku, 'product' => $products[$sku], 'deal' => $registry['deals'][$sku]];
    }
    $snapshot = ['policy' => $registry['policy'], 'items' => $items];
    $snapshot['checksum'] = hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    return $snapshot;
  }

  /**
   * Hashes a normalized contact value so suppression does not require raw PII.
   */
  public function contactHash(string $contact): string {
    return hash('sha256', mb_strtolower(trim($contact)));
  }

  /**
   * Returns TRUE when outreach is forbidden for a contact.
   */
  public function isSuppressed(string $contact): bool {
    return (bool) $this->database->select('famtastic_consent', 'c')
      ->condition('contact_hash', $this->contactHash($contact))
      ->condition('consent_type', 'outreach')
      ->condition('status', ['unsubscribed', 'bounced', 'complained', 'suppressed'], 'IN')
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  /**
   * Appends a consent/suppression fact and a corresponding event.
   */
  public function recordConsent(
    string $contact,
    string $status,
    ?int $prospectId = NULL,
    string $consentType = 'outreach',
    ?int $termsVersionId = NULL,
    array $evidence = [],
  ): int {
    $allowed = ['granted', 'accepted', 'unsubscribed', 'bounced', 'complained', 'suppressed'];
    if (!in_array($status, $allowed, TRUE)) {
      throw new \InvalidArgumentException('Unsupported consent status.');
    }
    $now = $this->time->getRequestTime();
    $id = (int) $this->database->insert('famtastic_consent')
      ->fields([
        'prospect_id' => $prospectId,
        'contact_hash' => $this->contactHash($contact),
        'consent_type' => $consentType,
        'status' => $status,
        'terms_version_id' => $termsVersionId,
        'evidence' => json_encode($evidence, JSON_THROW_ON_ERROR),
        'ip_hash' => (string) ($evidence['ip_hash'] ?? ''),
        'user_agent_hash' => (string) ($evidence['user_agent_hash'] ?? ''),
        'recorded_at' => $now,
        'revoked_at' => in_array($status, ['unsubscribed', 'suppressed'], TRUE) ? $now : NULL,
      ])
      ->execute();
    $this->recordEvent(
      sprintf('consent:%d', $id),
      'consent.' . $status,
      ['consent_id' => $id, 'consent_type' => $consentType],
      $prospectId,
    );
    return $id;
  }

  /**
   * Appends an event exactly once by caller-provided idempotency key.
   */
  public function recordEvent(
    string $eventKey,
    string $eventType,
    array $payload = [],
    ?int $prospectId = NULL,
    ?int $campaignId = NULL,
    ?int $orderId = NULL,
    ?int $projectId = NULL,
    string $provider = '',
    string $providerEventId = '',
    ?int $occurredAt = NULL,
  ): bool {
    $now = $this->time->getRequestTime();
    try {
      $this->database->insert('famtastic_event')
        ->fields([
          'event_key' => $eventKey,
          'event_type' => $eventType,
          'prospect_id' => $prospectId,
          'campaign_id' => $campaignId,
          'order_id' => $orderId,
          'project_id' => $projectId,
          'provider' => $provider,
          'provider_event_id' => $providerEventId,
          'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
          'occurred_at' => $occurredAt ?? $now,
          'recorded_at' => $now,
        ])
        ->execute();
      return TRUE;
    }
    catch (\Throwable $e) {
      if ($this->isDuplicateKey($e)) {
        return FALSE;
      }
      throw $e;
    }
  }

  /**
   * Enqueues a job exactly once and returns its database id.
   */
  public function enqueue(
    string $jobKey,
    string $jobType,
    array $payload,
    ?int $prospectId = NULL,
    int $maxAttempts = 5,
    ?int $availableAt = NULL,
  ): int {
    $existing = $this->database->select('famtastic_job', 'j')
      ->fields('j', ['id'])
      ->condition('job_key', $jobKey)
      ->execute()
      ->fetchField();
    if ($existing) {
      return (int) $existing;
    }
    $now = $this->time->getRequestTime();
    try {
      return (int) $this->database->insert('famtastic_job')
        ->fields([
          'job_key' => $jobKey,
          'job_type' => $jobType,
          'prospect_id' => $prospectId,
          'status' => 'queued',
          'attempts' => 0,
          'max_attempts' => $maxAttempts,
          'available_at' => $availableAt ?? $now,
          'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
          'created' => $now,
          'changed' => $now,
        ])
        ->execute();
    }
    catch (\Throwable $e) {
      if ($this->isDuplicateKey($e)) {
        return (int) $this->database->select('famtastic_job', 'j')
          ->fields('j', ['id'])
          ->condition('job_key', $jobKey)
          ->execute()
          ->fetchField();
      }
      throw $e;
    }
  }

  /**
   * Atomically claims the next available job with optional scope constraints.
   */
  public function claimNext(?string $jobType = NULL, ?array $prospectIds = NULL): ?array {
    $now = $this->time->getRequestTime();
    $query = $this->database->select('famtastic_job', 'j')
      ->fields('j')
      ->condition('status', 'queued')
      ->condition('available_at', $now, '<=')
      ->orderBy('available_at', 'ASC')
      ->orderBy('id', 'ASC')
      ->range(0, 1);
    if ($jobType !== NULL) {
      $query->condition('job_type', $jobType);
    }
    if ($prospectIds !== NULL) {
      $prospectIds = array_values(array_unique(array_filter(array_map('intval', $prospectIds))));
      if ($prospectIds === []) {
        return NULL;
      }
      $query->condition('prospect_id', $prospectIds, 'IN');
    }
    $job = $query->execute()->fetchAssoc();
    if (!$job) {
      return NULL;
    }
    $claimed = $this->database->update('famtastic_job')
      ->fields(['status' => 'running', 'locked_at' => $now, 'changed' => $now])
      ->condition('id', $job['id'])
      ->condition('status', 'queued')
      ->execute();
    if ($claimed !== 1) {
      return NULL;
    }
    foreach (['id', 'prospect_id', 'attempts', 'max_attempts', 'available_at', 'created', 'changed'] as $field) {
      $job[$field] = $job[$field] === NULL ? NULL : (int) $job[$field];
    }
    $job['payload'] = json_decode((string) $job['payload'], TRUE, flags: JSON_THROW_ON_ERROR);
    $job['status'] = 'running';
    return $job;
  }

  /**
   * Marks a job complete. Repeated completion is harmless.
   */
  public function completeJob(int $jobId, array $result): void {
    $now = $this->time->getRequestTime();
    $this->database->update('famtastic_job')
      ->fields([
        'status' => 'completed',
        'result' => json_encode($result, JSON_THROW_ON_ERROR),
        'completed_at' => $now,
        'locked_at' => NULL,
        'last_error' => NULL,
        'changed' => $now,
      ])
      ->condition('id', $jobId)
      ->condition('status', 'completed', '<>')
      ->execute();
  }

  /**
   * Schedules retry with exponential backoff or opens an exception.
   */
  public function failJob(int $jobId, string $message): array {
    $job = $this->database->select('famtastic_job', 'j')
      ->fields('j')
      ->condition('id', $jobId)
      ->execute()
      ->fetchAssoc();
    if (!$job) {
      throw new \InvalidArgumentException('Unknown job.');
    }
    $attempts = (int) $job['attempts'] + 1;
    $now = $this->time->getRequestTime();
    $exhausted = $attempts >= (int) $job['max_attempts'];
    $retryAt = $exhausted ? NULL : $now + min(3600, 30 * (2 ** max(0, $attempts - 1)));
    $this->database->update('famtastic_job')
      ->fields([
        'status' => $exhausted ? 'failed' : 'queued',
        'attempts' => $attempts,
        'available_at' => $retryAt ?? 0,
        'locked_at' => NULL,
        'last_error' => mb_substr($message, 0, 5000),
        'changed' => $now,
      ])
      ->condition('id', $jobId)
      ->execute();
    if ($exhausted) {
      $this->openException(
        'job:' . $job['job_key'],
        (string) $job['job_type'],
        'Job exhausted its retry allowance.',
        ['job_key' => $job['job_key'], 'last_error' => $message],
        (int) ($job['prospect_id'] ?: 0) ?: NULL,
        $jobId,
        FALSE,
      );
    }
    return ['exhausted' => $exhausted, 'attempts' => $attempts, 'retry_at' => $retryAt];
  }

  /** Explicitly rearms one exact exhausted job after operator intervention. */
  public function requeueFailedJob(int $jobId, string $expectedJobKey): bool {
    $job = $this->database->select('famtastic_job', 'j')->fields('j')
      ->condition('id', $jobId)->range(0, 1)->execute()->fetchAssoc();
    if (!$job || !hash_equals((string) $job['job_key'], $expectedJobKey)) {
      throw new \InvalidArgumentException('The exact failed job was not found.');
    }
    if ((string) $job['status'] !== 'failed') {
      return FALSE;
    }
    $now = $this->time->getRequestTime();
    $updated = $this->database->update('famtastic_job')->fields([
      'status' => 'queued',
      'attempts' => 0,
      'available_at' => $now,
      'locked_at' => NULL,
      'completed_at' => NULL,
      'changed' => $now,
    ])->condition('id', $jobId)->condition('status', 'failed')->execute();
    if ($updated !== 1) {
      return FALSE;
    }
    $this->database->update('famtastic_exception')->fields([
      'status' => 'resolved',
      'resolved_at' => $now,
      'changed' => $now,
    ])->condition('exception_key', 'job:' . $expectedJobKey)->condition('status', 'open')->execute();
    $this->recordEvent(
      'job.requeued:' . $jobId . ':' . $now,
      'job.requeued',
      ['job_id' => $jobId, 'job_key' => $expectedJobKey, 'prior_attempts' => (int) $job['attempts']],
      (int) ($job['prospect_id'] ?? 0) ?: NULL,
    );
    return TRUE;
  }

  /**
   * Opens or refreshes a unique actionable exception.
   */
  public function openException(
    string $exceptionKey,
    string $category,
    string $summary,
    array $details = [],
    ?int $prospectId = NULL,
    ?int $jobId = NULL,
    bool $retryable = TRUE,
    string $severity = 'error',
  ): int {
    $now = $this->time->getRequestTime();
    $existing = $this->database->select('famtastic_exception', 'e')
      ->fields('e', ['id'])
      ->condition('exception_key', $exceptionKey)
      ->execute()
      ->fetchField();
    $fields = [
      'category' => $category,
      'severity' => $severity,
      'status' => 'open',
      'prospect_id' => $prospectId,
      'job_id' => $jobId,
      'summary' => mb_substr($summary, 0, 512),
      'details' => json_encode($details, JSON_THROW_ON_ERROR),
      'retryable' => $retryable ? 1 : 0,
      'changed' => $now,
    ];
    if ($existing) {
      $this->database->update('famtastic_exception')
        ->fields($fields)
        ->condition('id', $existing)
        ->execute();
      return (int) $existing;
    }
    return (int) $this->database->insert('famtastic_exception')
      ->fields(['exception_key' => $exceptionKey, 'created' => $now] + $fields)
      ->execute();
  }

  /**
   * Detects portable unique-key violations without hiding other failures.
   */
  private function isDuplicateKey(\Throwable $exception): bool {
    $message = $exception->getMessage();
    return str_contains($message, 'UNIQUE constraint failed')
      || str_contains($message, 'Duplicate entry')
      || str_contains($message, 'SQLSTATE[23000]');
  }

}
