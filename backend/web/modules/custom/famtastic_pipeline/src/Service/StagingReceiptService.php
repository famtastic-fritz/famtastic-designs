<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\famtastic_pipeline\Entity\Project;

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
    private readonly EntityTypeManagerInterface $entities,
    private readonly OperationalLedger $ledger,
    private readonly TimeInterface $time,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Accepts a signed callback after the route has verified its HMAC.
   *
   * @return array{newly_processed: bool, request_id: int, staging_url: string}
   */
  public function accept(array $receipt): array {
    foreach (['customer_accepted', 'checkout_eligible', 'final_launch'] as $boundary) {
      if (($receipt[$boundary] ?? FALSE) !== FALSE) throw new \InvalidArgumentException('Studio callbacks cannot authorize acceptance, checkout or final launch.');
    }
    $failure = ($receipt['schema'] ?? '') === 'famtastic.site-studio.staging-failure.v1';
    if (!$failure) $this->validate($receipt);
    else self::validateFailure($receipt);
    $transaction = $this->database->startTransaction();
    $requestId = (int) $receipt['website_request_id'];
    $row = $this->database->select('famtastic_project_request', 'r')
      ->fields('r', ['id', 'project_id', 'customer_id', 'public_id', 'proof_review_status', 'commerce_order_id', 'staging_status', 'staging_review_status', 'staging_receipt_hash', 'staging_receipt_json'])
      ->condition('id', $requestId)
      ->range(0, 1)
      ->forUpdate()
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
    $packet = $this->registeredPacket((int) $receipt['project_id']);
    if (!$failure) SelectedAssetRights::assertPacket($this->database, $packet);
    self::assertReceiptMatchesRegisteredPacket($receipt, $packet);
    self::assertRequestBinding($receipt, $packet, $row);
    $receiptHash = hash('sha256', json_encode($receipt, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    $existingHash = trim((string) ($row['staging_receipt_hash'] ?? ''));
    if ($existingHash !== '') {
      if (!hash_equals($existingHash, $receiptHash)) {
        throw new \InvalidArgumentException('Website request already has a different staging receipt.');
      }
      if (!$failure && isset($receipt['source_completion'])) SelectedFinalizedSource::recordCompletion($this->entities->getStorage('famtastic_project')->load((int) $receipt['project_id']), $packet, $receipt);
      if (!$failure) $this->queueStagingReviewNotification($row, $receiptHash);
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
    try {
      $isNew = $this->ledger->recordEvent(
        'site-studio.staging:' . $receipt['event_id'],
        $failure ? 'site_studio.staging_failed' : 'site_studio.staging_deployed',
        [
          'event_id' => $receipt['event_id'],
          'packet_id' => $receipt['packet_id'],
          'idempotency_key' => $receipt['idempotency_key'],
          'request_id' => $receipt['request_id'],
          'website_request_id' => $requestId,
          'selected_direction_id' => $receipt['selected_direction_id'],
          'selected_artifact_sha256' => $receipt['selected_artifact_sha256'],
          'artifact_manifest_sha256' => $receipt['artifact_manifest_sha256'],
          'staging_url' => $receipt['staging_url'] ?? '',
          'error' => $receipt['error'] ?? NULL,
          // This is the deployed output checksum. It is intentionally not
          // compared with selected_artifact_sha256, which identifies the
          // immutable source file selected from the registered packet.
          'output_artifact_sha256' => $receipt['artifact_sha256'] ?? NULL,
          'qa' => $receipt['qa'] ?? [],
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
          'staging_url' => (string) ($receipt['staging_url'] ?? ''),
        ];
      }
      $updated = $this->database->update('famtastic_project_request')
        ->fields([
          'staging_status' => $failure ? 'failed' : 'deployed',
          'staging_review_status' => $failure ? 'not_started' : 'pending',
          'staging_reviewed_at' => NULL,
          // The staging lock-in is the point at which a pre-payment request
          // receives its standalone project binding.
          'project_id' => (int) $receipt['project_id'],
          'staging_receipt_json' => json_encode($receipt, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
          'staging_receipt_hash' => $receiptHash,
          'staging_locked_at' => $now,
          'staging_deployed_at' => $failure ? NULL : $now,
          'changed' => $now,
        ])
        ->condition('id', $requestId)
        ->condition('proof_review_status', 'selected')
        ->isNull('commerce_order_id')
        ->execute();
      if ((int) $updated !== 1) {
        throw new \RuntimeException('Website request changed before the staging receipt could be locked.');
      }
      if (!$failure && (isset($receipt['source_completion']) || ($packet['continuation']['brand']['design_contract']['kind'] ?? '') === 'selected-source-preservation-v1')) SelectedFinalizedSource::recordCompletion($this->entities->getStorage('famtastic_project')->load((int) $receipt['project_id']), $packet, $receipt);
      if (!$failure) $this->queueStagingReviewNotification($row, $receiptHash);
      unset($transaction);
      return [
        'newly_processed' => TRUE,
        'request_id' => $requestId,
        'staging_url' => (string) ($receipt['staging_url'] ?? ''),
      ];
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }
  }

  /**
   * Returns whether a request has a complete, customer-accepted pre-payment
   * staging handoff. A deployed callback alone never opens checkout.
   */
  public function isReady(int $requestId): bool {
    if ($requestId < 1) {
      return FALSE;
    }
    $row = $this->database->select('famtastic_project_request', 'r')
      ->fields('r', ['project_id', 'staging_status', 'staging_review_status', 'staging_receipt_hash', 'staging_receipt_json'])
      ->condition('id', $requestId)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!$row || !self::checkoutGateSatisfied($row)) return FALSE;
    try { SelectedAssetRights::assertPacket($this->database, $this->registeredPacket((int) $row['project_id'])); }
    catch (\InvalidArgumentException) { return FALSE; }
    return TRUE;
  }

  /**
   * Pure gate used by the checkout boundary and exercised without a database.
   */
  public static function checkoutGateSatisfied(array $row): bool {
    if ((string) ($row['staging_status'] ?? '') !== 'deployed'
      || (string) ($row['staging_review_status'] ?? '') !== 'accepted'
      || trim((string) ($row['staging_receipt_hash'] ?? '')) === '') {
      return FALSE;
    }
    $receipt = json_decode((string) ($row['staging_receipt_json'] ?? ''), TRUE);
    return is_array($receipt)
      && ($receipt['schema'] ?? '') === self::SCHEMA
      && ($receipt['status'] ?? '') === 'deployed';
  }

  private function validate(array $receipt): void {
    if (($receipt['schema'] ?? '') !== self::SCHEMA || ($receipt['status'] ?? '') !== 'deployed') {
      throw new \InvalidArgumentException('A deployed Site Studio staging receipt is required.');
    }
    foreach (['event_id', 'packet_id', 'idempotency_key', 'request_id', 'selected_direction_id', 'selected_artifact_sha256', 'artifact_manifest_sha256', 'staging_url', 'artifact_sha256', 'completed_at', 'target_path', 'remote_subdirectory'] as $field) {
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
    if (!filter_var((string) ($receipt['staging_url'] ?? ''), FILTER_VALIDATE_URL) || !str_starts_with((string) ($receipt['staging_url'] ?? ''), 'https://')) {
      throw new \InvalidArgumentException('Staging receipt must contain an HTTPS staging URL.');
    }
    foreach (['artifact_sha256', 'selected_artifact_sha256', 'artifact_manifest_sha256'] as $field) {
      if (!preg_match('/^[a-f0-9]{64}$/', (string) $receipt[$field])) {
        throw new \InvalidArgumentException(sprintf('Staging receipt %s must be a SHA-256 digest.', $field));
      }
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

  /**
   * Rejects a callback unless it names the exact immutable packet identity.
   *
   * artifact_sha256 is deliberately excluded: it identifies generated staging
   * output, while selected_artifact_sha256 and artifact_manifest_sha256 bind
   * the callback to selected source bytes and the packet's full file manifest.
   */
  public static function assertReceiptMatchesRegisteredPacket(array $receipt, array $packet): void {
    if (isset($packet['continuation'])) {
      $c = $packet['continuation'];
      if (($receipt['status'] ?? '') === 'deployed') {
        foreach (['staging_url', 'target_path', 'remote_subdirectory'] as $field) {
          if (empty($c['hosting_target'][$field]) || !hash_equals((string) $c['hosting_target'][$field], (string) ($receipt[$field] ?? ''))) throw new \InvalidArgumentException('Staging receipt review target mismatch: ' . $field);
        }
      }

      if ((string) ($receipt['customer_id'] ?? '') !== (string) ($c['customer']['id'] ?? '') || ($receipt['website_request_id'] ?? NULL) !== ($c['website_request_id'] ?? NULL) || ($receipt['selection_revision'] ?? NULL) !== ($c['selection_revision'] ?? NULL)) {
        throw new \InvalidArgumentException('Staging callback does not match the current account/revision.');
      }
    }

    foreach (['packet_id', 'idempotency_key', 'request_id', 'project_id', 'artifact_manifest_sha256'] as $field) {
      if (!hash_equals((string) ($packet[$field] ?? ''), (string) ($receipt[$field] ?? ''))) {
        throw new \InvalidArgumentException(sprintf('Staging receipt %s does not match the registered build packet.', $field));
      }
    }
    $direction = (string) ($receipt['selected_direction_id'] ?? '');
    if (!in_array($direction, (array) ($packet['selected_direction_ids'] ?? []), TRUE)) {
      throw new \InvalidArgumentException('Staging receipt selected direction is not selected in the registered build packet.');
    }
    $selected = array_values(array_filter(
      (array) ($packet['selected_artifacts'] ?? []),
      static fn ($artifact): bool => is_array($artifact) && (string) ($artifact['direction_id'] ?? '') === $direction,
    ));
    if (count($selected) !== 1 || !hash_equals((string) ($selected[0]['source_artifact_sha256'] ?? ''), (string) ($receipt['selected_artifact_sha256'] ?? ''))) {
      throw new \InvalidArgumentException('Staging receipt selected artifact does not match the registered build packet.');
    }
  }

  /** Browser acceptance is bound to the exact displayed receipt. */
  public static function assertCurrentReviewReceipt(array $row, string $expectedReceiptHash): void {
    if ($expectedReceiptHash === '' || !hash_equals((string) ($row['staging_receipt_hash'] ?? ''), $expectedReceiptHash)) {
      throw new \InvalidArgumentException('Accept the exact staging revision currently displayed. Refresh and review the current artifact.');
    }
  }

  /** Failure callback is terminal evidence, never readiness or customer mail. */
  public static function validateFailure(array $receipt): void {
    if (($receipt['schema'] ?? '') !== 'famtastic.site-studio.staging-failure.v1' || ($receipt['status'] ?? '') !== 'failed' || empty($receipt['event_id']) || empty($receipt['error']['code']) || empty($receipt['error']['stage'])) {
      throw new \InvalidArgumentException('Named selected-staging failure evidence is required.');
    }
    if (isset($receipt['staging_url']) || isset($receipt['artifact_sha256'])) throw new \InvalidArgumentException('Failure cannot claim a ready artifact.');
  }

  public static function assertRequestBinding(array $receipt, array $packet, array $row): void {
    if (isset($packet['continuation'])) {
      if ((string) ($receipt['customer_id'] ?? '') !== (string) $row['customer_id'] || (int) ($receipt['website_request_id'] ?? 0) !== (int) $row['id'] || (int) ($receipt['project_id'] ?? 0) !== (int) $row['project_id']) {
        throw new \InvalidArgumentException('Staging callback account/request/project binding mismatch.');
      }
      // Current packet, not prior receipt, is the revision authority.
      if (($receipt['selection_revision'] ?? NULL) !== ($packet['continuation']['selection_revision'] ?? NULL)) throw new \InvalidArgumentException('Stale selected staging callback.');
    }
  }

  /** Loads the immutable packet saved on the exact project entity. */
  private function registeredPacket(int $projectId): array {
    $project = $this->entities->getStorage('famtastic_project')->load($projectId);
    if (!$project instanceof Project) {
      throw new \InvalidArgumentException('Staging receipt references an unknown Site Studio project.');
    }
    $studio = json_decode((string) $project->get('studio_json')->value ?: '{}', TRUE);
    if (!empty($studio['selected_staging_exception'])) throw new \InvalidArgumentException('Selected staging evidence is unresolved; callback cannot enable review.');
    $packet = is_array($studio) ? ($studio['site_studio_build_packet'] ?? NULL) : NULL;
    if (!is_array($packet)) {
      throw new \InvalidArgumentException('Staging receipt project has no registered Site Studio build packet.');
    }
    return $packet;
  }

  /** Queues one durable, idempotent review notification; delivery is separate. */
  private function queueStagingReviewNotification(array $request, string $receiptHash): void {
    $email = $this->database->select('famtastic_customer', 'c')
      ->fields('c', ['email'])
      ->condition('id', (int) ($request['customer_id'] ?? 0))
      ->range(0, 1)
      ->execute()
      ->fetchField();
    if (!$email) {
      return;
    }
    $base = rtrim((string) $this->configFactory->get('famtastic_pipeline.settings')->get('frontend_base_url'), '/');
    $portal = $base . '/portal/?section=projects&project=' . rawurlencode((string) ($request['public_id'] ?? ''));
    $now = $this->time->getRequestTime();
    $key = 'website-request:' . (int) $request['id'] . ':staging-review-ready:' . $receiptHash;
    $this->database->merge('famtastic_notification_outbox')->key('notification_key', $key)->insertFields([
      'notification_key' => $key,
      'category' => 'project_staging_review_ready',
      'recipient' => mb_strtolower((string) $email),
      'subject' => 'Your website staging preview is ready for review',
      'body' => "Your selected website staging preview is ready in your project workspace. Checkout remains closed until you review and accept the staging preview.\n" . $portal,
      'status' => 'queued',
      'attempts' => 0,
      'max_attempts' => 5,
      'available_at' => $now,
      'created' => $now,
      'changed' => $now,
    ])->execute();
  }

}
