<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Core\Database\Connection;

/**
 * Shared current DB facts, NOT principal authentication or read/release authority.
 *
 * Always use the caller's primary Connection. No service lookup, filesystem,
 * provider, callback, transaction creation/commit or paid-claim mutation occurs.
 * Read facts must never be reused as a later write grant. Locked facts are valid
 * only while the caller retains its transaction and all these locks.
 */
final class ManagedProofCurrentBinding {
  /**
   * Unnormalized facts outside transactions; no lifecycle authorization here.
   *
   * Includes admission_event and asset_snapshot for Reader's strict freshness
   * comparison AFTER it authenticates a reviewer or verifies an exact release.
   * This method deliberately does not reverse customer_ready/notified (or any
   * other state). A successful read alone proves neither unchanged authored input
   * nor an authorized lifecycle transition. No paths or package bytes are read.
   */
  public static function read(Connection $db, int $requestId, int $now): array {
    if ($db->inTransaction()) throw new \LogicException('Managed current reads require a committed connection outside transactions.');
    self::primary($db);
    $request = self::request($db, $requestId, FALSE);
    $event = FreshProofBinding::event($db, $requestId);
    $jobId = self::jobId($event);
    $committed = ManagedProofImportReceipt::committed($db, $jobId);
    if ($committed['receipt']['request_id'] !== $requestId) throw new \RuntimeException('Managed receipt belongs to a different request.');
    return self::withCampaign($db, self::authority($db, $request, FALSE), $now, FALSE)
      + ['committed' => $committed, 'admission_event' => $event];
  }

  /**
   * Pending-only DB revalidation in the caller's active transaction.
   *
   * Order: request -> customer/user/org/member/resource/prospect/assets
   * -> WorkerCoordinatorMutex -> job/claim/events -> campaign/receipt graph.
   * Re-reading already-owned upstream rows is permitted. No new upstream request
   * may be acquired after the mutex. Admission's nonlocking job-ID hint is never
   * trusted as authority: the pinned committed receipt and locked event must agree.
   * No nested transaction, root ownership, release, research or outbox write.
   */
  public static function lockPending(Connection $db, int $requestId, int $now, string $expectedReceiptHash): array {
    if (!$db->inTransaction()) throw new \LogicException('Managed pending binding requires an active transaction.');
    self::primary($db);
    ProofOperationContract::digest($expectedReceiptHash);
    $request = self::request($db, $requestId, TRUE);
    if ($request['proof_review_status'] !== 'owner_review' || $request['proof_approved_by_uid'] !== NULL
      || $request['proof_approved_at'] !== NULL || $request['proof_notified_at'] !== NULL) throw new \RuntimeException('Managed proof is not pending independent review.');
    return self::lockGraph($db, $request, $now, $expectedReceiptHash);
  }

  /**
   * Final same-transaction invariant, NOT a grant to adopt a released request.
   *
   * The caller owns a pending snapshot and has verified its new immutable release
   * rows. Require that exact request plus ONLY this operation's four reveal fields;
   * locally reverse those four fields to compare every locked authority/receipt
   * fact with the original pending snapshot. lockPending remains pending-only.
   */
  public static function assertNewReleaseUnchanged(Connection $db, array $pending, int $now, int $approvedAt): void {
    if (!$db->inTransaction()) throw new \LogicException('Managed release recheck requires an active transaction.');
    self::primary($db);
    $before = $pending['request'];
    if ($before['proof_review_status'] !== 'owner_review' || $before['proof_approved_by_uid'] !== NULL
      || $before['proof_approved_at'] !== NULL || $before['proof_notified_at'] !== NULL
      || $approvedAt < 1 || $now < $approvedAt) throw new \RuntimeException('Managed release recheck requires its original pending state.');
    $current = self::request($db, (int) $before['id'], TRUE);
    $transition = ['proof_review_status' => 'customer_ready', 'proof_approved_by_uid' => NULL, 'proof_approved_at' => $approvedAt, 'changed' => $approvedAt];
    $expected = array_replace($before, $transition);
    if (count($current) !== count($expected) || array_diff_key($current, $expected)) throw new \RuntimeException('Managed release request changed.');
    foreach ($expected as $key => $value) {
      if ($value === NULL ? $current[$key] !== NULL : ($current[$key] === NULL || (string) $current[$key] !== (string) $value)) throw new \RuntimeException('Managed release request changed.');
    }
    foreach ($transition as $key => $_) $current[$key] = $before[$key];
    if (self::lockGraph($db, $current, $now, $pending['committed']['receipt_sha256']) !== $pending) throw new \RuntimeException('Managed release authority changed after final write.');
  }

  /** Lifecycle is validated by the narrow caller before this shared locked graph. */
  private static function lockGraph(Connection $db, array $request, int $now, string $expectedReceiptHash): array {
    $requestId = (int) $request['id'];
    $current = self::authority($db, $request, TRUE);
    $jobId = self::jobId(FreshProofBinding::event($db, $requestId));
    WorkerCoordinatorMutex::acquire($db);
    $committed = ManagedProofImportReceipt::committedLocked($db, $jobId, $expectedReceiptHash, $requestId);
    if ($now < $committed['receipt']['imported_at']) throw new \RuntimeException('Managed review clock precedes the committed import.');
    $current = self::withCampaign($db, $current, $now, TRUE);
    $event = FreshProofBinding::event($db, $requestId, TRUE);
    if (self::jobId($event) !== $jobId || hash('sha256', $event['payload']) !== $committed['receipt']['admission_sha256']) throw new \RuntimeException('Managed admission binding changed.');
    // Only the actual committed import proves this ONE local reversal. Never
    // erase selection/project/commerce/brief/rights or mutate the stored request.
    $beforeImport = $request; $beforeImport['proof_review_status'] = 'not_started';
    $binding = FreshProofBinding::read($db, $event, $beforeImport, TRUE);
    if ($binding['binding']['asset_snapshot'] !== $current['asset_snapshot']) throw new \RuntimeException('Managed asset authority changed.');
    return $current + ['committed' => $committed, 'admission_event' => $event];
  }

  private static function request(Connection $db, int $requestId, bool $lock): array {
    ProofOperationContract::integer($requestId, 1, PHP_INT_MAX);
    $request = self::row($db, 'famtastic_project_request', ['id' => $requestId], $lock);
    if (!$request || $request['status'] !== 'submitted' || !empty($request['customer_archived_at'])
      || $request['selected_proof_direction'] !== '' || $request['selected_proof_at'] !== NULL) throw new \RuntimeException('Managed request is not eligible for this reader.');
    return $request;
  }

  private static function jobId(array|false $event): int {
    if (!$event) throw new \RuntimeException('Managed admission is absent.');
    $admission = json_decode($event['payload'], TRUE, 32, JSON_THROW_ON_ERROR);
    if (!is_int($admission['job_id'] ?? NULL) || $admission['job_id'] < 1
      || ($admission['request_snapshot']['proof_review_status'] ?? '') !== 'not_started') throw new \RuntimeException('Managed admission binding is invalid.');
    return $admission['job_id'];
  }

  /** Shared unchanged account checks for nonlocking reads and locked writers. */
  private static function authority(Connection $db, array $request, bool $lock): array {
    $customer = self::row($db, 'famtastic_customer', ['id' => (int) $request['customer_id']], $lock);
    $user = $customer ? self::row($db, 'users_field_data', ['uid' => (int) $customer['uid'], 'default_langcode' => 1], $lock, ['uid', 'status']) : FALSE;
    $organization = self::row($db, 'famtastic_organization', ['id' => (int) $request['organization_id']], $lock);
    $member = self::row($db, 'famtastic_membership', ['customer_id' => (int) $request['customer_id'], 'organization_id' => (int) $request['organization_id']], $lock);
    $resource = self::row($db, 'famtastic_customer_resource', ['resource_type' => 'prospect', 'resource_id' => (int) $request['prospect_id']], $lock);
    $prospect = self::row($db, 'famtastic_prospect', ['id' => (int) $request['prospect_id']], $lock);
    if (!$customer || (int) $customer['uid'] < 1 || (int) $customer['verified_at'] < 1 || !$user || (int) $user['status'] !== 1
      || !$organization || $organization['status'] !== 'active' || !$member || $member['status'] !== 'active'
      || !$resource || (int) $resource['organization_id'] !== (int) $request['organization_id'] || !$prospect) throw new \RuntimeException('Managed account authority is inactive or invalid.');
    $assets = FreshProofInput::assets($db, $request, $lock);
    return ['request' => $request, 'customer' => $customer, 'asset_snapshot' => $assets,
      'authority_rows' => [$customer, $user, $organization, $member, $resource]];
  }

  /** Campaign locks belong AFTER mutex/job, matching the existing admission. */
  private static function withCampaign(Connection $db, array $current, int $now, bool $lock): array {
    $request = $current['request'];
    $campaign = self::row($db, 'proof_campaign', ['id' => (int) $request['proof_campaign_id']], $lock);
    if (!$campaign || $campaign['status'] !== 'active' || (int) $campaign['expires_at'] <= $now
      || !empty($campaign['selected_variant'])) throw new \RuntimeException('Managed campaign is unavailable.');
    $current['authority_sha256'] = ManagedProofImportContract::hash([$request, ...$current['authority_rows'], $campaign]);
    unset($current['authority_rows']);
    return $current;
  }

  private static function row(Connection $db, string $table, array $conditions, bool $lock, array $fields = []): array|false {
    $query = $db->select($table, 'r')->fields('r', $fields);
    foreach ($conditions as $field => $value) $query->condition($field, $value);
    if ($lock) $query->forUpdate();
    return $query->execute()->fetchAssoc();
  }

  private static function primary(Connection $db): void {
    // Directly constructed fixture connections have no registered target. Never
    // accept an explicitly registered replica or resolve another connection here.
    if ($db->getTarget() !== NULL && $db->getTarget() !== 'default') throw new \LogicException('Managed binding requires the primary database connection.');
  }
}
