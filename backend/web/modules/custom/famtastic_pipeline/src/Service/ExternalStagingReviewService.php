<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Binds one already-built release to its exact account-owned review request.
 *
 * This adapter records review evidence only. It cannot accept for a customer,
 * open checkout, send mail, deploy files, change DNS, or create a project.
 */
final class ExternalStagingReviewService {

  public const SCHEMA = 'famtastic.external-staging-review.v1';
  public const IMPORT_SCHEMA = 'famtastic.external-staging-import.v1';

  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
    private readonly AccountProxyInterface $account,
    private readonly OperationalLedger $ledger,
  ) {}

  /**
   * Attaches an exact external staging release without inventing proof history.
   *
   * @return array{newly_attached: bool, request_id: int, receipt_hash: string, receipt: array<string, mixed>}
   *   The immutable receipt and whether this invocation first attached it.
   */
  public function attach(string $requestPublicId, int $customerId, int $organizationId, array $input, string $actor, string $authority): array {
    $this->assertStaffAuthority($actor, $authority);
    if (!preg_match('/^[0-9a-f-]{36}$/', $requestPublicId) || $customerId < 1 || $organizationId < 1) {
      throw new \InvalidArgumentException('Exact request, customer and organization identifiers are required.');
    }
    if (($input['schema'] ?? '') !== self::IMPORT_SCHEMA) {
      throw new \InvalidArgumentException('A versioned external staging import packet is required.');
    }

    $transaction = $this->database->startTransaction();
    try {
      $row = $this->database->select('famtastic_project_request', 'r')
        ->fields('r')
        ->condition('public_id', $requestPublicId)
        ->condition('customer_id', $customerId)
        ->condition('organization_id', $organizationId)
        ->range(0, 1)
        ->forUpdate()
        ->execute()
        ->fetchAssoc();
      if (!$row || !$this->activeMembership($customerId, $organizationId)) {
        throw new \RuntimeException('Exact active customer, organization and request binding is required.');
      }
      $review = $this->currentReview($row);
      $manifest = $review['manifest'];
      $receipt = self::normalizeReceipt([
        'schema' => self::SCHEMA,
        'status' => 'deployed',
        'website_request_id' => (int) $row['id'],
        'request_public_id' => (string) $row['public_id'],
        'customer_id' => (int) $row['customer_id'],
        'organization_id' => (int) $row['organization_id'],
        'review_id' => (string) $manifest['review_id'],
        'review_manifest_sha256' => (string) $review['manifest_sha256'],
        'build_id' => (string) $manifest['build_id'],
        'source_commit' => (string) ($input['source_commit'] ?? ''),
        'staging_url' => (string) ($input['staging_url'] ?? ''),
        'release_id' => (string) ($input['release_id'] ?? ''),
        'artifact_sha256' => (string) ($input['artifact_sha256'] ?? ''),
        'qa' => $input['qa'] ?? NULL,
        'actor' => $actor,
        'authority_sha256' => hash('sha256', trim($authority)),
      ]);
      if (!hash_equals((string) ($input['review_manifest_sha256'] ?? ''), $receipt['review_manifest_sha256'])) {
        throw new \InvalidArgumentException('Import packet does not name the current full-site review manifest.');
      }
      $this->assertCurrentReceipt($receipt, $row);
      $digest = self::digest($receipt);
      $existingHash = trim((string) ($row['staging_receipt_hash'] ?? ''));
      if ($existingHash !== '') {
        if (!hash_equals($existingHash, $digest)
          || !hash_equals((string) ($row['staging_receipt_json'] ?? ''), json_encode($receipt, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR))) {
          throw new \RuntimeException('Website request already has a different immutable staging receipt.');
        }
        unset($transaction);
        return [
          'newly_attached' => FALSE,
          'request_id' => (int) $row['id'],
          'receipt_hash' => $digest,
          'receipt' => $receipt,
        ];
      }
      if (!empty($row['commerce_order_id']) || !empty($row['project_id'])) {
        throw new \RuntimeException('External staging review cannot replace an active project or purchase.');
      }
      if (!in_array((string) $row['proof_review_status'], ['', 'not_started', 'selected'], TRUE)
        || ((string) $row['proof_review_status'] === 'selected' && (string) $row['selected_proof_direction'] !== 'external')) {
        throw new \RuntimeException('External staging review cannot replace an existing proof selection.');
      }

      $eventKey = 'external-staging-review:' . (int) $row['id'] . ':' . $receipt['release_id'];
      $payload = [
        'website_request_id' => (int) $row['id'],
        'request_public_id' => (string) $row['public_id'],
        'customer_id' => (int) $row['customer_id'],
        'organization_id' => (int) $row['organization_id'],
        'receipt_sha256' => $digest,
        'receipt' => $receipt,
        'actor' => $actor,
        'authority_ref' => $authority,
        'execution_uid' => (int) $this->account->id(),
        'prior_proof_campaign_id' => !empty($row['proof_campaign_id']) ? (int) $row['proof_campaign_id'] : NULL,
        'prior_proof_history_preserved' => TRUE,
        'customer_acceptance' => FALSE,
        'notification_queued' => FALSE,
        'payment_changed' => FALSE,
        'deploy_performed' => FALSE,
        'dns_changed' => FALSE,
      ];
      if (!$this->ledger->recordEvent($eventKey, 'website_request.external_staging_review_attached', $payload, prospectId: !empty($row['prospect_id']) ? (int) $row['prospect_id'] : NULL, provider: 'staff_external_staging')) {
        throw new \RuntimeException('External staging release changed concurrently; retry the exact import packet.');
      }

      $now = $this->time->getRequestTime();
      $updated = $this->database->update('famtastic_project_request')
        ->fields([
          'proof_review_status' => 'selected',
          // The historical campaign and job records remain in their own
          // ledgers. Detach only the request pointer so the externally built
          // release cannot be presented as one of the old concept variants.
          'proof_campaign_id' => NULL,
          'selected_proof_direction' => 'external',
          'selected_proof_at' => $now,
          'staging_status' => 'deployed',
          'staging_review_status' => 'pending',
          'staging_reviewed_at' => NULL,
          'staging_receipt_json' => json_encode($receipt, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
          'staging_receipt_hash' => $digest,
          'staging_locked_at' => $now,
          'staging_deployed_at' => $now,
          'changed' => $now,
        ])
        ->condition('id', (int) $row['id'])
        ->condition('staging_receipt_hash', '')
        ->isNull('commerce_order_id')
        ->execute();
      if ((int) $updated !== 1) {
        throw new \RuntimeException('Website request changed before the external release could be locked.');
      }
      $this->database->insert('famtastic_portal_activity')->fields([
        'organization_id' => $organizationId,
        'event_type' => 'website_request.external_staging_review_attached',
        'summary' => 'Your exact staging release is ready for review.',
        'created' => $now,
      ])->execute();
      unset($transaction);
      return [
        'newly_attached' => TRUE,
        'request_id' => (int) $row['id'],
        'receipt_hash' => $digest,
        'receipt' => $receipt,
      ];
    }
    catch (\Throwable $error) {
      $transaction->rollBack();
      throw $error;
    }
  }

  /**
   * Normalizes the bounded, immutable receipt used at review and checkout.
   */
  public static function normalizeReceipt(array $receipt): array {
    if (($receipt['schema'] ?? '') !== self::SCHEMA || ($receipt['status'] ?? '') !== 'deployed') {
      throw new \InvalidArgumentException('A deployed external staging review receipt is required.');
    }
    foreach (['website_request_id', 'customer_id', 'organization_id'] as $field) {
      if (!is_int($receipt[$field] ?? NULL) || $receipt[$field] < 1) {
        throw new \InvalidArgumentException('External staging receipt ' . $field . ' is required.');
      }
    }
    $bounded = static function (mixed $value, string $field, int $max, string $pattern): string {
      $value = trim((string) $value);
      if ($value === '' || strlen($value) > $max || !preg_match($pattern, $value)) {
        throw new \InvalidArgumentException('External staging receipt ' . $field . ' is invalid.');
      }
      return $value;
    };
    $requestPublicId = $bounded($receipt['request_public_id'] ?? '', 'request_public_id', 36, '/^[0-9a-f-]{36}$/');
    $reviewId = $bounded($receipt['review_id'] ?? '', 'review_id', 100, '/^[a-z0-9][a-z0-9-]{2,99}$/');
    $buildId = $bounded($receipt['build_id'] ?? '', 'build_id', 170, '/^[a-zA-Z0-9][a-zA-Z0-9:._\/-]{0,169}$/');
    $releaseId = $bounded($receipt['release_id'] ?? '', 'release_id', 120, '/^[a-zA-Z0-9][a-zA-Z0-9._-]{0,119}$/');
    $actor = $bounded($receipt['actor'] ?? '', 'actor', 160, '/^[a-zA-Z0-9][a-zA-Z0-9:._-]{2,159}$/');
    $url = trim((string) ($receipt['staging_url'] ?? ''));
    if (!FullSiteReviewPackage::httpsUrl($url) || !empty(parse_url($url, PHP_URL_FRAGMENT))) {
      throw new \InvalidArgumentException('External staging receipt requires an HTTPS URL without credentials or a fragment.');
    }
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    if (isset($query['release']) && (!is_string($query['release']) || !hash_equals($releaseId, $query['release']))) {
      throw new \InvalidArgumentException('External staging URL and release_id do not match.');
    }
    foreach (['review_manifest_sha256', 'artifact_sha256', 'authority_sha256'] as $field) {
      if (!preg_match('/^[a-f0-9]{64}$/', (string) ($receipt[$field] ?? ''))) {
        throw new \InvalidArgumentException('External staging receipt ' . $field . ' must be a SHA-256 digest.');
      }
    }
    $sourceCommit = (string) ($receipt['source_commit'] ?? '');
    if (!preg_match('/^[a-f0-9]{40}$/', $sourceCommit)) {
      throw new \InvalidArgumentException('External staging receipt source_commit must be a full Git commit.');
    }
    $qa = $receipt['qa'] ?? NULL;
    if (!is_array($qa) || !array_is_list($qa) || !$qa || count($qa) > 30) {
      throw new \InvalidArgumentException('External staging receipt requires bounded QA evidence.');
    }
    $normalizedQa = [];
    foreach ($qa as $check) {
      if (!is_array($check) || ($check['status'] ?? '') !== 'passed') {
        throw new \InvalidArgumentException('Every external staging QA check must be named and passed.');
      }
      $name = trim(strip_tags((string) ($check['name'] ?? '')));
      if ($name === '' || mb_strlen($name) > 160 || isset($normalizedQa[mb_strtolower($name)])) {
        throw new \InvalidArgumentException('Every external staging QA check must have a unique bounded name.');
      }
      $normalizedQa[mb_strtolower($name)] = ['name' => $name, 'status' => 'passed'];
    }
    ksort($normalizedQa);
    return [
      'schema' => self::SCHEMA,
      'status' => 'deployed',
      'website_request_id' => $receipt['website_request_id'],
      'request_public_id' => $requestPublicId,
      'customer_id' => $receipt['customer_id'],
      'organization_id' => $receipt['organization_id'],
      'review_id' => $reviewId,
      'review_manifest_sha256' => $receipt['review_manifest_sha256'],
      'build_id' => $buildId,
      'source_commit' => $sourceCommit,
      'staging_url' => $url,
      'release_id' => $releaseId,
      'artifact_sha256' => $receipt['artifact_sha256'],
      'qa' => array_values($normalizedQa),
      'actor' => $actor,
      'authority_sha256' => $receipt['authority_sha256'],
    ];
  }

  /**
   * Computes the canonical receipt digest.
   */
  public static function digest(array $receipt): string {
    return hash('sha256', json_encode(self::normalizeReceipt($receipt), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
  }

  /**
   * Verifies a receipt against the current request, full-site review, and DNA.
   *
   * @return array<string, mixed>
   *   Canonical receipt.
   */
  public function assertCurrentReceipt(array $receipt, array $requestRow): array {
    $receipt = self::normalizeReceipt($receipt);
    $binding = [
      'id' => 'website_request_id',
      'public_id' => 'request_public_id',
      'customer_id' => 'customer_id',
      'organization_id' => 'organization_id',
    ];
    foreach ($binding as $rowField => $receiptField) {
      if ((string) ($requestRow[$rowField] ?? '') !== (string) $receipt[$receiptField]) {
        throw new \InvalidArgumentException('External staging receipt request ownership binding mismatch.');
      }
    }
    if (!$this->activeMembership((int) $receipt['customer_id'], (int) $receipt['organization_id'])) {
      throw new \InvalidArgumentException('External staging receipt requires an active customer organization membership.');
    }
    $review = $this->currentReview($requestRow);
    $manifest = $review['manifest'];
    foreach (['review_id' => 'review_id', 'build_id' => 'build_id', 'source_commit' => 'source_commit'] as $receiptField => $manifestField) {
      if (!hash_equals((string) $manifest[$manifestField], (string) $receipt[$receiptField])) {
        throw new \InvalidArgumentException('External staging receipt does not match the current full-site review ' . $receiptField . '.');
      }
    }
    if (!hash_equals((string) $review['manifest_sha256'], (string) $receipt['review_manifest_sha256'])) {
      throw new \InvalidArgumentException('External staging receipt does not match the current full-site review manifest.');
    }
    $build = $this->database->select('famtastic_build_run', 'b')->fields('b', ['source_sha', 'artifact_checksum'])->condition('build_key', 'build-dna:' . $manifest['build_id'])->execute()->fetchAssoc();
    if (!$build || !hash_equals((string) $build['source_sha'], (string) $receipt['source_commit']) || !preg_match('/^[a-f0-9]{64}$/', (string) $build['artifact_checksum'])) {
      throw new \InvalidArgumentException('External staging receipt requires the current registered Build DNA.');
    }
    return $receipt;
  }

  /**
   * Loads and validates the immutable receipt currently locked to a request.
   */
  public function validateStoredReceipt(int $requestId, ?string $expectedHash = NULL): array {
    if ($requestId < 1) {
      throw new \InvalidArgumentException('A website request is required.');
    }
    $row = $this->database->select('famtastic_project_request', 'r')->fields('r')->condition('id', $requestId)->range(0, 1)->execute()->fetchAssoc();
    if (!$row) {
      throw new \InvalidArgumentException('External staging receipt request was not found.');
    }
    $receipt = json_decode((string) ($row['staging_receipt_json'] ?? ''), TRUE);
    if (!is_array($receipt)) {
      throw new \InvalidArgumentException('External staging receipt is unavailable.');
    }
    $receipt = $this->assertCurrentReceipt($receipt, $row);
    $digest = self::digest($receipt);
    if (!hash_equals((string) ($row['staging_receipt_hash'] ?? ''), $digest)
      || ($expectedHash !== NULL && !hash_equals($expectedHash, $digest))) {
      throw new \InvalidArgumentException('External staging receipt integrity check failed.');
    }
    return $receipt;
  }

  /**
   * Reads the exact full-site review embedded in the request.
   *
   * @return array{manifest: array<string, mixed>, manifest_sha256: string}
   *   The canonical review manifest and its digest.
   */
  private function currentReview(array $row): array {
    $intake = json_decode((string) ($row['intake_data'] ?? ''), TRUE, 512, JSON_THROW_ON_ERROR);
    $record = $intake['staff_assisted_brief']['full_site_review'] ?? NULL;
    if (!is_array($record)
      || (string) ($record['request_public_id'] ?? '') !== (string) ($row['public_id'] ?? '')
      || (int) ($record['customer_id'] ?? 0) !== (int) ($row['customer_id'] ?? 0)
      || (int) ($record['organization_id'] ?? 0) !== (int) ($row['organization_id'] ?? 0)) {
      throw new \InvalidArgumentException('The exact account-owned full-site review is required.');
    }
    $manifest = FullSiteReviewPackage::normalize($record['manifest'] ?? []);
    $digest = FullSiteReviewPackage::digest($manifest);
    if (!hash_equals($digest, (string) ($record['manifest_sha256'] ?? ''))) {
      throw new \InvalidArgumentException('Full-site review integrity check failed.');
    }
    return ['manifest' => $manifest, 'manifest_sha256' => $digest];
  }

  /**
   * Checks the exact active customer-to-organization membership.
   */
  private function activeMembership(int $customerId, int $organizationId): bool {
    return (bool) $this->database->select('famtastic_membership', 'm')
      ->condition('customer_id', $customerId)
      ->condition('organization_id', $organizationId)
      ->condition('status', 'active')
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  /**
   * Requires a real staff execution identity and an owner authority reference.
   */
  private function assertStaffAuthority(string $actor, string $authority): void {
    if (!$this->account->hasPermission('administer famtastic pipeline')) {
      throw new \RuntimeException('Staff authorization is required.');
    }
    if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9:._-]{2,159}$/', $actor) || trim($authority) === '' || strlen($authority) > 2000) {
      throw new \InvalidArgumentException('The actual actor and owner authority reference are required.');
    }
  }

}
