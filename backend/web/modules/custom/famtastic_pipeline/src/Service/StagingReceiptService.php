<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;

/**
 * Persists the signed, account-bound Site Studio staging handoff.
 *
 * A staging receipt is deliberately separate from a paid fulfillment receipt:
 * it proves that the selected artifact exists on the shared staging target,
 * but it never authorizes production, DNS, or payment by itself.
 */
final class StagingReceiptService {

  private const SCHEMA = 'famtastic.site-studio.staging-receipt.v1';

  public function __construct(
    private readonly Connection $database,
    private readonly OperationalLedger $ledger,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Accepts a signed callback after the route has verified its HMAC.
   *
   * @return array{newly_processed: bool, request_id: int, staging_url: string}
   */
  public function accept(array $receipt): array {
    $this->validate($receipt);
    $requestId = (int) $receipt['website_request_id'];
    $row = $this->database->select('famtastic_project_request', 'r')
      ->fields('r', ['id', 'project_id', 'proof_review_status', 'commerce_order_id', 'staging_status', 'staging_receipt_hash', 'staging_receipt_json'])
      ->condition('id', $requestId)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!$row) {
      throw new \InvalidArgumentException('Staging receipt references an unknown website request.');
    }
    if ((string) $row['proof_review_status'] !== 'selected') {
      throw new \InvalidArgumentException('Staging receipt requires a selected website proof.');
    }
    if ((int) ($receipt['project_id'] ?? 0) < 1 || (!empty($row['project_id']) && (int) $row['project_id'] !== (int) $receipt['project_id'])) {
      throw new \InvalidArgumentException('Staging receipt project does not match the website request.');
    }
    $receiptHash = hash('sha256', json_encode($receipt, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    $existingHash = trim((string) ($row['staging_receipt_hash'] ?? ''));
    if ($existingHash !== '') {
      if (!hash_equals($existingHash, $receiptHash)) {
        throw new \InvalidArgumentException('Website request already has a different staging receipt.');
      }
      return [
        'newly_processed' => FALSE,
        'request_id' => $requestId,
        'staging_url' => (string) ($receipt['staging_url'] ?? ''),
      ];
    }
    if (!empty($row['commerce_order_id'])) {
      throw new \InvalidArgumentException('A staging receipt cannot replace a paid checkout handoff.');
    }

    $now = $this->time->getRequestTime();
    $transaction = $this->database->startTransaction();
    try {
      $isNew = $this->ledger->recordEvent(
        'site-studio.staging:' . $receipt['event_id'],
        'site_studio.staging_deployed',
        [
          'event_id' => $receipt['event_id'],
          'packet_id' => $receipt['packet_id'],
          'website_request_id' => $requestId,
          'staging_url' => $receipt['staging_url'],
          'artifact_sha256' => $receipt['artifact_sha256'],
          'qa' => $receipt['qa'],
        ],
        projectId: !empty($receipt['project_id']) ? (int) $receipt['project_id'] : NULL,
        provider: 'site_studio',
        providerEventId: (string) $receipt['event_id'],
      );
      if (!$isNew) {
        unset($transaction);
        return [
          'newly_processed' => FALSE,
          'request_id' => $requestId,
          'staging_url' => (string) $receipt['staging_url'],
        ];
      }
      $updated = $this->database->update('famtastic_project_request')
        ->fields([
          'staging_status' => 'deployed',
          // The staging lock-in is the point at which a pre-payment request
          // receives its standalone project binding.
          'project_id' => (int) $receipt['project_id'],
          'staging_receipt_json' => json_encode($receipt, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
          'staging_receipt_hash' => $receiptHash,
          'staging_locked_at' => $now,
          'staging_deployed_at' => $now,
          'changed' => $now,
        ])
        ->condition('id', $requestId)
        ->condition('proof_review_status', 'selected')
        ->isNull('commerce_order_id')
        ->execute();
      if ((int) $updated !== 1) {
        throw new \RuntimeException('Website request changed before the staging receipt could be locked.');
      }
      unset($transaction);
      return [
        'newly_processed' => TRUE,
        'request_id' => $requestId,
        'staging_url' => (string) $receipt['staging_url'],
      ];
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }
  }

  /** Returns whether a request has a complete pre-payment staging handoff. */
  public function isReady(int $requestId): bool {
    if ($requestId < 1) {
      return FALSE;
    }
    $row = $this->database->select('famtastic_project_request', 'r')
      ->fields('r', ['staging_status', 'staging_receipt_hash', 'staging_receipt_json'])
      ->condition('id', $requestId)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!$row || (string) ($row['staging_status'] ?? '') !== 'deployed' || trim((string) ($row['staging_receipt_hash'] ?? '')) === '') {
      return FALSE;
    }
    $receipt = json_decode((string) ($row['staging_receipt_json'] ?? ''), TRUE);
    return is_array($receipt) && ($receipt['schema'] ?? '') === self::SCHEMA;
  }

  private function validate(array $receipt): void {
    if (($receipt['schema'] ?? '') !== self::SCHEMA || ($receipt['status'] ?? '') !== 'deployed') {
      throw new \InvalidArgumentException('A deployed Site Studio staging receipt is required.');
    }
    foreach (['event_id', 'packet_id', 'idempotency_key', 'staging_url', 'artifact_sha256', 'completed_at', 'target_path', 'remote_subdirectory'] as $field) {
      if (trim((string) ($receipt[$field] ?? '')) === '') {
        throw new \InvalidArgumentException(sprintf('Staging receipt %s is required.', $field));
      }
    }
    if ((int) ($receipt['website_request_id'] ?? 0) < 1) {
      throw new \InvalidArgumentException('Staging receipt website_request_id is required.');
    }
    if ((int) ($receipt['project_id'] ?? 0) < 1 || !is_array($receipt['repository'] ?? NULL)) {
      throw new \InvalidArgumentException('Staging receipt project_id and repository evidence are required.');
    }
    if (trim((string) ($receipt['repository']['branch'] ?? '')) === '' || trim((string) ($receipt['repository']['mode'] ?? '')) === '') {
      throw new \InvalidArgumentException('Staging receipt repository mode and branch are required.');
    }
    if (str_contains((string) $receipt['remote_subdirectory'], '..') || str_starts_with((string) $receipt['remote_subdirectory'], '/')) {
      throw new \InvalidArgumentException('Staging receipt remote_subdirectory must be a safe relative path.');
    }
    if (!filter_var((string) $receipt['staging_url'], FILTER_VALIDATE_URL) || !str_starts_with((string) $receipt['staging_url'], 'https://')) {
      throw new \InvalidArgumentException('Staging receipt must contain an HTTPS staging URL.');
    }
    if (!preg_match('/^[a-f0-9]{64}$/', (string) $receipt['artifact_sha256'])) {
      throw new \InvalidArgumentException('Staging receipt artifact_sha256 must be a SHA-256 digest.');
    }
    if (!is_array($receipt['qa'] ?? NULL) || !$receipt['qa']) {
      throw new \InvalidArgumentException('Staging receipt QA evidence is required.');
    }
    foreach ($receipt['qa'] as $check) {
      if (!is_array($check) || ($check['status'] ?? '') !== 'passed' || trim((string) ($check['name'] ?? '')) === '') {
        throw new \InvalidArgumentException('Every staging QA check must be named and passed.');
      }
    }
  }

}
