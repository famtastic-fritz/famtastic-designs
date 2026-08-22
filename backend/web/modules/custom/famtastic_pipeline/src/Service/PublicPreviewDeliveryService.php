<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Site\Settings;
use Drupal\famtastic_pipeline\Entity\Prospect;

/**
 * Owns public-lead concept-room delivery without becoming a second CRM.
 *
 * A delivery is deliberately inert until an owner stages a complete, audited
 * Safe/Wild/OMG campaign and explicitly approves its one transactional email.
 */
final class PublicPreviewDeliveryService {

  private const STATES_VISIBLE_TO_HOLDER = [
    'share_enabled', 'email_queued', 'email_accepted', 'concept_room_viewed',
    'signup_started', 'account_verified_and_claimed', 'request_submitted',
  ];

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entities,
    private readonly TimeInterface $time,
    private readonly UuidInterface $uuid,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly OperationalLedger $ledger,
  ) {}

  /** Creates (or returns) the durable, non-sendable delivery for a public lead. */
  public function createForPublicLead(int $prospectId, int $intakeId = 0): array {
    $prospect = $this->prospect($prospectId);
    $email = $this->email($prospect);
    $key = 'public-preview:' . $prospectId . ':' . $intakeId;
    if ($existing = $this->loadBy('delivery_key', $key)) {
      return $existing;
    }
    $now = $this->time->getRequestTime();
    $id = (int) $this->database->insert('famtastic_preview_delivery')->fields([
      'public_id' => $this->uuid->generate(),
      'delivery_key' => $key,
      'prospect_id' => $prospectId,
      'intake_id' => $intakeId ?: NULL,
      'state' => 'lead_captured',
      'recipient_hash' => $this->ledger->contactHash($email),
      // This snapshot exists solely for the owner-approved transactional send.
      'recipient_address_snapshot' => $email,
      'subject_snapshot' => '',
      'text_snapshot' => '',
      'requested_at' => $now,
      'last_event_at' => $now,
      'created' => $now,
      'changed' => $now,
    ])->execute();
    $row = $this->load($id);
    $this->event($row, 'preview.lead_captured');
    return $row;
  }

  /**
   * Freezes the invitation content only after an actual Build DNA-backed core
   * campaign is ready. Staging never enables the public room or queues mail.
   */
  public function stage(int $deliveryId, int $proofCampaignId, string $buildDnaId, string $buildDnaHash): array {
    $row = $this->require($deliveryId);
    if (!in_array($row['state'], ['lead_captured', 'preview_requested', 'research_ready', 'proof_ready_owner_review', 'email_staged'], TRUE)) {
      throw new \RuntimeException('Preview delivery can no longer be staged.');
    }
    $this->assertCompleteCoreCampaign($proofCampaignId, (int) $row['prospect_id']);
    $buildDnaId = trim($buildDnaId);
    $buildDnaHash = strtolower(trim($buildDnaHash));
    if ($buildDnaId === '' || !preg_match('/^[a-f0-9]{64}$/', $buildDnaHash)) {
      throw new \InvalidArgumentException('A Build DNA identifier and SHA-256 hash are required before staging.');
    }
    $this->assertRegisteredBuildDna($buildDnaId, $buildDnaHash);
    $now = $this->time->getRequestTime();
    $expires = $now + (14 * 86400);
    $version = max(1, (int) $row['share_version']);
    $signature = $this->shareSignature((string) $row['public_id'], $version);
    $shareUrl = $this->shareUrl((string) $row['public_id'], $signature);
    $business = $this->prospect((int) $row['prospect_id'])->label();
    $subject = 'Three website directions for ' . $business;
    $text = $this->invitationBody($business, $shareUrl, $this->registrationUrl((string) $row['public_id']));
    $this->database->update('famtastic_preview_delivery')->fields([
      'proof_campaign_id' => $proofCampaignId,
      'build_dna_id' => $buildDnaId,
      'build_dna_hash' => $buildDnaHash,
      'state' => 'email_staged',
      // The room signature is deterministic from the server secret + version;
      // only its one-way hash is retained as an audit value.
      'share_token_hash' => hash('sha256', $signature),
      'share_expires_at' => $expires,
      'expires_at' => $expires,
      'subject_snapshot' => $subject,
      'text_snapshot' => $text,
      'proof_ready_at' => $now,
      'last_event_at' => $now,
      'changed' => $now,
    ])->condition('id', $deliveryId)->execute();
    $row = $this->require($deliveryId);
    $this->event($row, 'preview.staged', ['proof_campaign_id' => $proofCampaignId, 'build_dna_id' => $buildDnaId]);
    return $row;
  }

  /** Holds a complete public-lead proof set for Build DNA registration and owner review. */
  public function markCampaignReady(int $prospectId, int $proofCampaignId): bool {
    $row = $this->database->select('famtastic_preview_delivery', 'p')->fields('p')
      ->condition('prospect_id', $prospectId)
      ->condition('state', ['lead_captured', 'preview_requested', 'research_ready'], 'IN')
      ->orderBy('created', 'DESC')->range(0, 1)->execute()->fetchAssoc();
    if (!$row) return FALSE;
    $this->assertCompleteCoreCampaign($proofCampaignId, $prospectId);
    $now = $this->time->getRequestTime();
    $this->database->update('famtastic_preview_delivery')->fields([
      'proof_campaign_id' => $proofCampaignId,
      'state' => 'proof_ready_owner_review',
      'proof_ready_at' => $now,
      'last_event_at' => $now,
      'changed' => $now,
    ])->condition('id', $row['id'])->execute();
    $this->event($this->require((int) $row['id']), 'preview.proof_ready_owner_review', ['proof_campaign_id' => $proofCampaignId]);
    return TRUE;
  }

  /** Explicit owner action: enables the room and queues exactly one email. */
  public function approveAndQueue(int $deliveryId, int $uid): array {
    $row = $this->require($deliveryId);
    if ($row['state'] !== 'email_staged') {
      throw new \RuntimeException('Preview email is not staged for owner approval.');
    }
    $now = $this->time->getRequestTime();
    $key = 'preview-delivery:' . $deliveryId . ':share:' . (int) $row['share_version'];
    $this->database->merge('famtastic_notification_outbox')->key('notification_key', $key)->insertFields([
      'notification_key' => $key,
      'category' => 'transactional',
      'recipient' => (string) $row['recipient_address_snapshot'],
      'subject' => (string) $row['subject_snapshot'],
      'body' => (string) $row['text_snapshot'],
      'status' => 'queued',
      'attempts' => 0,
      'max_attempts' => 5,
      'available_at' => $now,
      'created' => $now,
      'changed' => $now,
    ])->execute();
    $outbox = $this->database->select('famtastic_notification_outbox', 'n')->fields('n')
      ->condition('notification_key', $key)->execute()->fetchAssoc();
    $this->database->update('famtastic_preview_delivery')->fields([
      'state' => 'email_queued',
      'owner_approved_at' => $now,
      'owner_approved_by_uid' => $uid,
      'email_outbox_id' => (int) $outbox['id'],
      'queued_at' => $now,
      'last_event_at' => $now,
      'changed' => $now,
    ])->condition('id', $deliveryId)->execute();
    $row = $this->require($deliveryId);
    $this->event($row, 'preview.approved', ['owner_uid' => $uid]);
    $this->event($row, 'preview.email_queued', ['outbox_id' => (int) $outbox['id']]);
    return $row;
  }

  /** Immediately invalidates the existing room and requires a new stage. */
  public function revoke(int $deliveryId, int $uid): array {
    $row = $this->require($deliveryId);
    $now = $this->time->getRequestTime();
    $this->database->update('famtastic_preview_delivery')->fields([
      'state' => 'share_revoked',
      'share_version' => (int) $row['share_version'] + 1,
      'share_revoked_at' => $now,
      'share_revoked_by_uid' => $uid,
      'last_event_at' => $now,
      'changed' => $now,
    ])->condition('id', $deliveryId)->execute();
    $row = $this->require($deliveryId);
    $this->event($row, 'preview.share_revoked', ['owner_uid' => $uid]);
    return $row;
  }

  /** Marks exact SMTP acceptance without treating it as inbox delivery. */
  public function markAcceptedByOutbox(int $outboxId, string $providerMessageId): void {
    $row = $this->database->select('famtastic_preview_delivery', 'p')->fields('p')
      ->condition('email_outbox_id', $outboxId)->execute()->fetchAssoc();
    if (!$row) {
      return;
    }
    $now = $this->time->getRequestTime();
    $this->database->update('famtastic_preview_delivery')->fields([
      'state' => 'email_accepted',
      'provider_message_id' => $providerMessageId,
      'accepted_at' => $now,
      'last_event_at' => $now,
      'changed' => $now,
    ])->condition('id', $row['id'])->condition('state', 'email_queued')->execute();
    $updated = $this->load((int) $row['id']);
    if ($updated && $updated['state'] === 'email_accepted') {
      $this->event($updated, 'preview.email_accepted', ['outbox_id' => $outboxId]);
    }
  }

  /** Resolves the deliberately minimal public concept-room payload. */
  public function publicShare(string $publicId, string $signature): ?array {
    $row = $this->loadBy('public_id', strtolower($publicId));
    if (!$row || !$this->isCurrentShare($row, $signature)) {
      return NULL;
    }
    $variants = $this->variants((int) $row['proof_campaign_id']);
    if (count($variants) !== 3) {
      return NULL;
    }
    $now = $this->time->getRequestTime();
    if ($row['state'] === 'email_accepted') {
      $this->database->update('famtastic_preview_delivery')->fields([
        'state' => 'concept_room_viewed', 'last_event_at' => $now, 'changed' => $now,
      ])->condition('id', $row['id'])->execute();
      $row = $this->require((int) $row['id']);
      $this->event($row, 'preview.room_viewed');
    }
    $prospect = $this->prospect((int) $row['prospect_id']);
    return [
      'public_id' => (string) $row['public_id'],
      'business_name' => $prospect->label(),
      'proof_count' => 3,
      'private_label' => 'Private review concept · Not yet published.',
      'registration_url' => $this->registrationUrl((string) $row['public_id']),
      'variants' => array_map(fn ($variant): array => [
        'direction_id' => (string) $variant->get('direction_id')->value,
        'direction_name' => (string) $variant->get('direction_name')->value,
        'preview_url' => $this->publicPreviewUrl((string) $row['public_id'], $signature, (string) $variant->get('direction_id')->value),
      ], $variants),
    ];
  }

  /** Returns the approved campaign/direction only for a valid current share. */
  public function publicVariant(string $publicId, string $signature, string $direction): ?object {
    $row = $this->loadBy('public_id', strtolower($publicId));
    if (!$row || !$this->isCurrentShare($row, $signature)) {
      return NULL;
    }
    $direction = strtolower($direction);
    if (!in_array($direction, ['a', 'b', 'c'], TRUE)) {
      return NULL;
    }
    $ids = $this->entities->getStorage('proof_variant')->getQuery()->accessCheck(FALSE)
      ->condition('campaign_id', (int) $row['proof_campaign_id'])->condition('direction_id', $direction)->range(0, 1)->execute();
    return $ids ? $this->entities->getStorage('proof_variant')->load(reset($ids)) : NULL;
  }

  /** Records a non-sensitive signup start from the signed continuation URL. */
  public function markSignupStarted(string $continuation, string $email): void {
    [$publicId, $signature] = array_pad(explode('.', trim($continuation), 2), 2, '');
    $row = $this->loadBy('public_id', strtolower($publicId));
    if (!$row || !hash_equals($this->continuationSignature((string) $row['public_id']), $signature)) {
      return;
    }
    if (!hash_equals((string) $row['recipient_hash'], $this->ledger->contactHash($email))) {
      return;
    }
    if (in_array($row['state'], ['share_revoked', 'expired'], TRUE)) {
      return;
    }
    $now = $this->time->getRequestTime();
    $this->database->update('famtastic_preview_delivery')->fields([
      'state' => 'signup_started', 'signup_started_at' => $now, 'last_event_at' => $now, 'changed' => $now,
    ])->condition('id', $row['id'])->execute();
    $this->event($this->require((int) $row['id']), 'preview.signup_started');
  }

  /** Claims eligible previews only after the account email has been verified. */
  public function claimVerifiedCustomer(int $customerId, string $email): void {
    $hash = $this->ledger->contactHash($email);
    $rows = $this->database->select('famtastic_preview_delivery', 'p')->fields('p')
      ->condition('recipient_hash', $hash)
      ->condition('customer_id', NULL, 'IS NULL')
      ->condition('state', ['lead_captured', 'email_staged', 'email_queued', 'email_accepted', 'concept_room_viewed', 'signup_started'], 'IN')
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $now = $this->time->getRequestTime();
    foreach ($rows as $row) {
      $this->database->update('famtastic_preview_delivery')->fields([
        'customer_id' => $customerId,
        'claimed_at' => $now,
        'state' => 'account_verified_and_claimed',
        'last_event_at' => $now,
        'changed' => $now,
      ])->condition('id', $row['id'])->execute();
      $this->event($this->require((int) $row['id']), 'preview.claimed', ['customer_id' => $customerId]);
    }
  }

  /** Supplies the one unclaimed public prospect that should back the next request. */
  public function claimedProspectId(int $customerId): ?int {
    $value = $this->database->select('famtastic_preview_delivery', 'p')->fields('p', ['prospect_id'])
      ->condition('customer_id', $customerId)
      ->isNull('website_request_id')
      ->condition('state', 'account_verified_and_claimed')
      ->orderBy('claimed_at', 'DESC')->range(0, 1)->execute()->fetchField();
    return $value ? (int) $value : NULL;
  }

  /** Binds a customer's new canonical request to their claimed preview. */
  public function attachClaimedRequest(int $customerId, int $requestId, string $state): void {
    $row = $this->database->select('famtastic_preview_delivery', 'p')->fields('p')
      ->condition('customer_id', $customerId)
      ->isNull('website_request_id')
      ->condition('state', 'account_verified_and_claimed')
      ->orderBy('claimed_at', 'DESC')->range(0, 1)->execute()->fetchAssoc();
    if (!$row) {
      return;
    }
    $now = $this->time->getRequestTime();
    $next = $state === 'submitted' ? 'request_submitted' : 'account_verified_and_claimed';
    $this->database->update('famtastic_preview_delivery')->fields([
      'website_request_id' => $requestId,
      'state' => $next,
      'last_event_at' => $now,
      'changed' => $now,
    ])->condition('id', $row['id'])->execute();
    $this->event($this->require((int) $row['id']), $state === 'submitted' ? 'preview.request_submitted' : 'preview.request_attached', ['website_request_id' => $requestId]);
  }

  private function assertCompleteCoreCampaign(int $campaignId, int $prospectId): void {
    $campaign = $this->entities->getStorage('proof_campaign')->load($campaignId);
    if (!$campaign || (int) $campaign->get('prospect_id')->target_id !== $prospectId || $campaign->get('generation_status')->value !== 'ready') {
      throw new \RuntimeException('A ready proof campaign for this public lead is required.');
    }
    $directions = array_map(static fn ($variant): string => (string) $variant->get('direction_id')->value, $this->variants($campaignId));
    sort($directions);
    if ($directions !== ['a', 'b', 'c']) {
      throw new \RuntimeException('Public concept rooms require exactly the Safe, Wild, and OMG proof set.');
    }
  }

  /** Requires the immutable Drupal projection before a public handoff. */
  private function assertRegisteredBuildDna(string $buildDnaId, string $buildDnaHash): void {
    $row = $this->database->select('famtastic_build_run', 'b')->fields('b', ['artifact_checksum'])
      ->condition('build_key', 'build-dna:' . $buildDnaId)->range(0, 1)->execute()->fetchAssoc();
    if (!$row || !hash_equals((string) $row['artifact_checksum'], $buildDnaHash)) {
      throw new \RuntimeException('The matching immutable Build DNA projection must be registered before public preview staging.');
    }
  }

  private function variants(int $campaignId): array {
    if (!$campaignId) return [];
    $ids = $this->entities->getStorage('proof_variant')->getQuery()->accessCheck(FALSE)
      ->condition('campaign_id', $campaignId)->sort('direction_id')->execute();
    return array_values($this->entities->getStorage('proof_variant')->loadMultiple($ids));
  }

  private function isCurrentShare(array $row, string $signature): bool {
    if (!in_array($row['state'], self::STATES_VISIBLE_TO_HOLDER, TRUE)
      || !empty($row['share_revoked_at'])
      || (!empty($row['share_expires_at']) && (int) $row['share_expires_at'] < $this->time->getRequestTime())) {
      return FALSE;
    }
    $expected = $this->shareSignature((string) $row['public_id'], (int) $row['share_version']);
    return preg_match('/^[a-f0-9]{64}$/', $signature) === 1 && hash_equals($expected, $signature);
  }

  private function shareSignature(string $publicId, int $version): string {
    return hash_hmac('sha256', 'public-preview-share-v1|' . $publicId . '|' . $version, Settings::getHashSalt());
  }

  private function continuationSignature(string $publicId): string {
    return hash_hmac('sha256', 'public-preview-continuation-v1|' . $publicId, Settings::getHashSalt());
  }

  private function shareUrl(string $publicId, string $signature): string {
    return $this->frontendBase() . '/proofs/preview/' . rawurlencode($publicId) . '/' . rawurlencode($signature);
  }

  private function publicPreviewUrl(string $publicId, string $signature, string $direction): string {
    return '/web/api/public-preview/' . rawurlencode($publicId) . '/' . rawurlencode($signature) . '/proofs/' . rawurlencode($direction);
  }

  private function registrationUrl(string $publicId): string {
    return $this->frontendBase() . '/login?mode=register&continuation=' . rawurlencode($publicId . '.' . $this->continuationSignature($publicId)) . '&redirect=%2Fportal%3Fstart%3Dwebsite';
  }

  private function frontendBase(): string {
    return rtrim((string) (getenv('FRONTEND_BASE_URL') ?: $this->configFactory->get('famtastic_pipeline.settings')->get('frontend_base_url') ?: 'https://famtasticdesigns.com'), '/');
  }

  private function invitationBody(string $business, string $shareUrl, string $registrationUrl): string {
    return "Hi,\n\nWe prepared three exploratory directions for {$business}: Safe, Wild, and OMG. They are a starting point from the general details shared so far—not a final site scope or a statement of current programs, events, partners, or results.\n\nView your private concept room:\n{$shareUrl}\n\nTo make the strongest direction unmistakably yours, create your free FAMtastic Designs workspace with this same email and complete the website request. You can add the actual pages, audiences, current details, assets, integrations, references, and style preferences that guide the next proof.\n\nCreate your free workspace:\n{$registrationUrl}\n\nThe concept room is private and review-only. Nothing is published, selected, priced, or purchased from it.\n\nShay Shay\nFAMtastic Concierge\nConnected digital systems for growing businesses\nhttps://famtasticdesigns.com";
  }

  private function prospect(int $id): Prospect {
    $prospect = $this->entities->getStorage('famtastic_prospect')->load($id);
    if (!$prospect instanceof Prospect) {
      throw new \RuntimeException('Public prospect no longer exists.');
    }
    return $prospect;
  }

  private function email(Prospect $prospect): string {
    $email = mb_strtolower(trim((string) $prospect->get('public_email')->value));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      throw new \RuntimeException('Public prospect has no valid email.');
    }
    return $email;
  }

  private function require(int $id): array {
    $row = $this->load($id);
    if (!$row) throw new \RuntimeException('Preview delivery not found.');
    return $row;
  }

  private function load(int $id): ?array {
    return $this->loadBy('id', $id);
  }

  private function loadBy(string $field, string|int $value): ?array {
    if (!in_array($field, ['id', 'public_id', 'delivery_key'], TRUE)) {
      throw new \InvalidArgumentException('Invalid preview delivery lookup.');
    }
    $row = $this->database->select('famtastic_preview_delivery', 'p')->fields('p')
      ->condition($field, $value)->range(0, 1)->execute()->fetchAssoc();
    return $row ?: NULL;
  }

  private function event(array $row, string $type, array $meta = []): void {
    $this->ledger->recordEvent('preview:' . $type . ':' . $row['id'] . ':' . max(1, (int) $row['last_event_at']), $type, $meta, (int) $row['prospect_id']);
  }

}
