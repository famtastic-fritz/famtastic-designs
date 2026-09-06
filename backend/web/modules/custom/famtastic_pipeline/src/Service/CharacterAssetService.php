<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;

/**
 * Owns the request-scoped likeness asset and character-generation ledger.
 *
 * This service deliberately contains no provider client. A provider worker
 * may create a run, append immutable receipts, and advance the run only after
 * the stored consent and asset checks pass.
 */
final class CharacterAssetService {

  public const ROLE_LIKENESS_FRONT = 'likeness_front';
  public const ROLE_LIKENESS_THREE_QUARTER = 'likeness_three_quarter';
  public const ROLE_LIKENESS_FULL_OR_WORK = 'likeness_full_or_work';
  public const ROLE_WORK_SAMPLE = 'work_sample';
  public const ROLE_STYLE_REFERENCE = 'style_reference';
  public const ROLE_LOGO = 'logo';
  public const ROLE_OTHER = 'other';

  public const STATUS_WAITING_FOR_ASSETS = 'waiting_for_assets';
  public const STATUS_READY = 'ready';
  public const STATUS_GENERATING = 'generating';
  public const STATUS_OWNER_REVIEW = 'owner_review';
  public const STATUS_ACCEPTED = 'accepted';
  public const STATUS_CLARIFICATION_REQUIRED = 'clarification_required';
  public const STATUS_REJECTED = 'rejected';
  public const STATUS_INTEGRATED = 'integrated';

  private const ROLES = [
    self::ROLE_LIKENESS_FRONT,
    self::ROLE_LIKENESS_THREE_QUARTER,
    self::ROLE_LIKENESS_FULL_OR_WORK,
    self::ROLE_WORK_SAMPLE,
    self::ROLE_STYLE_REFERENCE,
    self::ROLE_LOGO,
    self::ROLE_OTHER,
  ];

  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
    private readonly UuidInterface $uuid,
  ) {}

  /** Returns the only roles accepted by the request upload contract. */
  public static function allowedRoles(): array {
    return self::ROLES;
  }

  /** Returns the two minimum inputs needed for an identity-sensitive run. */
  public static function requiredLikenessRoles(): array {
    return [self::ROLE_LIKENESS_FRONT, self::ROLE_LIKENESS_THREE_QUARTER];
  }

  /** Normalizes a role while retaining the old generic reference behavior. */
  public static function normalizeRole(string $role): string {
    $role = strtolower(trim($role));
    return in_array($role, self::ROLES, TRUE) ? $role : 'reference';
  }

  /**
   * Validates the private asset set before a likeness job can be generated.
   *
   * @param array<int, array<string, mixed>> $assets
   *   Request-owned rows from famtastic_request_asset.
   *
   * @return array{ok:bool,error?:string,missing?:array<int,string>}
   */
  public static function validateLikenessAssets(array $assets): array {
    $roles = [];
    foreach ($assets as $asset) {
      if ((string) ($asset['status'] ?? 'active') !== 'active') {
        continue;
      }
      $role = self::normalizeRole((string) ($asset['role'] ?? $asset['kind'] ?? 'reference'));
      $roles[$role] = TRUE;
      if (in_array($role, self::requiredLikenessRoles(), TRUE)
        && (!(bool) ($asset['ownership_confirmed'] ?? FALSE)
          || !(bool) ($asset['subject_permission_confirmed'] ?? FALSE)
          || !(bool) ($asset['ai_transformation_consent'] ?? $asset['ai_use_consent'] ?? FALSE))) {
        return ['ok' => FALSE, 'error' => 'likeness_consent_required'];
      }
    }
    $missing = array_values(array_diff(self::requiredLikenessRoles(), array_keys($roles)));
    return $missing ? ['ok' => FALSE, 'error' => 'likeness_assets_required', 'missing' => $missing] : ['ok' => TRUE];
  }

  /** Creates a durable run after re-checking request ownership and consent. */
  public function createRun(int $websiteRequestId, int $customerId, array $assetIds, array $attributes = [], int $createdByUid = 0): int {
    if (!$assetIds) {
      throw new \InvalidArgumentException('A character run requires private source assets.');
    }
    $query = $this->database->select('famtastic_request_asset', 'a')->fields('a')
      ->condition('website_request_id', $websiteRequestId)->condition('customer_id', $customerId)
      ->condition('id', array_map('intval', $assetIds), 'IN')->condition('status', 'active');
    $assets = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $validation = self::validateLikenessAssets($assets);
    if (!$validation['ok']) {
      throw new \RuntimeException((string) $validation['error']);
    }
    $now = $this->time->getRequestTime();
    $sourceIds = array_map(static fn(array $asset): int => (int) $asset['id'], $assets);
    $id = (int) $this->database->insert('famtastic_character_run')->fields([
      'public_id' => $this->uuid->generate(),
      'website_request_id' => $websiteRequestId,
      'customer_id' => $customerId,
      'status' => self::STATUS_READY,
      'style_key' => substr(preg_replace('/[^a-z0-9_.-]/i', '', (string) ($attributes['style_key'] ?? 'illustrated_realism')) ?: 'illustrated_realism', 0, 64),
      'recipe_version' => substr((string) ($attributes['recipe_version'] ?? 'shay-likeness-v1'), 0, 64),
      'source_asset_ids' => json_encode($sourceIds, JSON_THROW_ON_ERROR),
      'prompt_hash' => preg_match('/^[a-f0-9]{64}$/i', (string) ($attributes['prompt_hash'] ?? '')) ? strtolower((string) $attributes['prompt_hash']) : '',
      'created_by_uid' => $createdByUid,
      'created' => $now,
      'changed' => $now,
    ])->execute();
    $this->syncRequestState($websiteRequestId, $id, self::STATUS_READY, '', $now);
    $this->appendReceipt($id, [
      'event_type' => 'run_created',
      'status' => self::STATUS_READY,
      'input_hashes' => array_values(array_filter(array_map(static fn(array $asset): string => (string) $asset['sha256'], $assets))),
      'settings' => ['style_key' => (string) ($attributes['style_key'] ?? 'illustrated_realism')],
    ], $createdByUid);
    return $id;
  }

  /** Appends one immutable provider/QA receipt to a character run. */
  public function appendReceipt(int $runId, array $receipt, int $reviewerUid = 0): int {
    $run = $this->database->select('famtastic_character_run', 'r')->fields('r')->condition('id', $runId)->range(0, 1)->execute()->fetchAssoc();
    if (!$run) {
      throw new \RuntimeException('Character run not found.');
    }
    $status = (string) ($receipt['status'] ?? '');
    if ($status !== '' && !in_array($status, [self::STATUS_READY, self::STATUS_GENERATING, self::STATUS_OWNER_REVIEW, self::STATUS_ACCEPTED, self::STATUS_CLARIFICATION_REQUIRED, self::STATUS_REJECTED, self::STATUS_INTEGRATED], TRUE)) {
      throw new \InvalidArgumentException('Unknown character run status.');
    }
    $now = $this->time->getRequestTime();
    $id = (int) $this->database->insert('famtastic_character_receipt')->fields([
      'public_id' => $this->uuid->generate(),
      'character_run_id' => $runId,
      'event_type' => substr((string) ($receipt['event_type'] ?? 'provider_receipt'), 0, 64),
      'status' => $status ?: (string) $run['status'],
      'provider' => substr((string) ($receipt['provider'] ?? ''), 0, 64),
      'model' => substr((string) ($receipt['model'] ?? ''), 0, 128),
      'prompt_hash' => preg_match('/^[a-f0-9]{64}$/i', (string) ($receipt['prompt_hash'] ?? '')) ? strtolower((string) $receipt['prompt_hash']) : '',
      'input_hashes' => json_encode(array_values((array) ($receipt['input_hashes'] ?? [])), JSON_THROW_ON_ERROR),
      'output_hashes' => json_encode(array_values((array) ($receipt['output_hashes'] ?? [])), JSON_THROW_ON_ERROR),
      'settings_json' => json_encode((array) ($receipt['settings'] ?? []), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
      'qa_json' => json_encode((array) ($receipt['qa'] ?? []), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
      'cost_minor' => max(0, (int) ($receipt['cost_minor'] ?? 0)),
      'duration_ms' => max(0, (int) ($receipt['duration_ms'] ?? 0)),
      'decision' => substr((string) ($receipt['decision'] ?? ''), 0, 32),
      'reviewer_uid' => $reviewerUid ?: NULL,
      'created' => $now,
    ])->execute();
    if ($status !== '') {
      $fields = ['status' => $status, 'changed' => $now];
      if (!empty($receipt['accepted_asset_id'])) {
        $fields['accepted_asset_id'] = (int) $receipt['accepted_asset_id'];
      }
      $this->database->update('famtastic_character_run')->fields($fields)->condition('id', $runId)->execute();
      $this->syncRequestState((int) $run['website_request_id'], $runId, $status, '', $now);
    }
    return $id;
  }

  /** Moves a run into clarification without generating or deleting assets. */
  public function requestClarification(int $runId, string $reason, int $uid = 0): void {
    $reason = mb_substr(trim(strip_tags($reason)), 0, 2000);
    if ($reason === '') {
      throw new \InvalidArgumentException('A clarification reason is required.');
    }
    $now = $this->time->getRequestTime();
    $this->database->update('famtastic_character_run')->fields([
      'status' => self::STATUS_CLARIFICATION_REQUIRED,
      'clarification_reason' => $reason,
      'changed' => $now,
    ])->condition('id', $runId)->execute();
    $run = $this->database->select('famtastic_character_run', 'r')->fields('r', ['website_request_id'])->condition('id', $runId)->range(0, 1)->execute()->fetchAssoc();
    if (!$run) {
      throw new \RuntimeException('Character run not found.');
    }
    $this->syncRequestState((int) $run['website_request_id'], $runId, self::STATUS_CLARIFICATION_REQUIRED, $reason, $now);
    $this->appendReceipt($runId, [
      'event_type' => 'clarification_requested',
      'status' => self::STATUS_CLARIFICATION_REQUIRED,
      'decision' => 'clarification_required',
      'settings' => ['reason' => $reason],
    ], $uid);
  }

  /** Mirrors the character lane state onto its request for portal consumers. */
  private function syncRequestState(int $websiteRequestId, int $runId, string $status, string $clarification, int $now): void {
    $this->database->update('famtastic_project_request')->fields([
      'character_run_id' => $runId,
      'character_status' => $status,
      'character_clarification' => $clarification !== '' ? $clarification : NULL,
      'character_changed' => $now,
      'changed' => $now,
    ])->condition('id', $websiteRequestId)->execute();
  }

}
