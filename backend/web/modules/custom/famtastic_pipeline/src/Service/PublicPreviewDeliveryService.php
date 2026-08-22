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
 * Safe/Medium FAMtastic/Ultra FAMtastic campaign and explicitly approves its
 * one transactional email.
 */
final class PublicPreviewDeliveryService {

  private const STATES_VISIBLE_TO_HOLDER = [
    'share_enabled', 'email_queued', 'email_accepted', 'concept_room_viewed',
    'signup_started', 'account_verified_and_claimed', 'request_attached', 'request_submitted',
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
   * Queues one concise operator alert for a captured public lead.
   *
   * Public capture must never call SMTP directly. The durable outbox is the
   * only delivery boundary, which leaves an operator-visible record even if
   * the mail provider later rejects or delays the message.
   */
  public function queueLeadCapturedAlert(int $deliveryId, string $subject, string $body): array {
    $row = $this->require($deliveryId);
    $admin = trim((string) ($this->configFactory->get('famtastic_pipeline.settings')->get('notification_to_email') ?: 'fitzgerald.medine@gmail.com'));
    if (!filter_var($admin, FILTER_VALIDATE_EMAIL)) {
      throw new \RuntimeException('Operational owner email is not configured.');
    }
    $reviewUrl = $this->frontendBase() . '/web/admin/famtastic/preview-delivery/' . (int) $row['id'] . '/review';
    $message = trim($body) . "\n\nPreview delivery: {$reviewUrl}\nNothing has been sent to the prospect. Stage a Build DNA-backed three-concept package before approving delivery.";
    $now = $this->time->getRequestTime();
    $key = 'preview-delivery:' . (int) $row['id'] . ':owner-lead-captured';
    $this->database->merge('famtastic_notification_outbox')->key('notification_key', $key)->insertFields([
      'notification_key' => $key,
      'category' => 'operational',
      'recipient' => mb_strtolower($admin),
      'subject' => mb_substr(trim($subject), 0, 512),
      'body' => $message,
      'status' => 'queued',
      'attempts' => 0,
      'max_attempts' => 5,
      'available_at' => $now,
      'created' => $now,
      'changed' => $now,
    ])->execute();
    $this->database->update('famtastic_preview_delivery')->fields([
      'state' => 'preview_requested',
      'last_event_at' => $now,
      'changed' => $now,
    ])->condition('id', $deliveryId)->condition('state', 'lead_captured')->execute();
    $updated = $this->require($deliveryId);
    $this->event($updated, 'preview.owner_alert_queued');
    return $updated;
  }

  /** Queues the one canonical Safe/Medium/Ultra public proof run. */
  public function queueInitialProofJob(int $deliveryId): void {
    $row = $this->require($deliveryId);
    if (!in_array($row['state'], ['lead_captured', 'preview_requested', 'research_ready'], TRUE)) {
      return;
    }
    $this->ledger->enqueue(
      'website_proof.generate.v1:public-preview:' . $deliveryId,
      'proof.generate',
      [
        'routine' => 'website_proof.generate.v1',
        'delivery_class' => 'public_initial',
        'proof_count' => 3,
        'proof_mix' => ['safe', 'medium_famtastic', 'ultra_famtastic'],
        'directions' => ProofCampaignService::CORE_DIRECTIONS,
        'direction_contract' => ProofCampaignService::CORE_DIRECTION_CONTRACT,
        'public_preview_delivery_id' => $deliveryId,
        'prospect_id' => (int) $row['prospect_id'],
        'intake_id' => (int) ($row['intake_id'] ?? 0),
      ],
      (int) $row['prospect_id'],
    );
    $this->event($row, 'preview.proof_generation_queued', [
      'proof_count' => 3,
      'direction_ids' => array_keys(ProofCampaignService::CORE_DIRECTION_CONTRACT),
    ]);
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
    // A public delivery is the immutable historical record for its original
    // three-direction campaign. Refinement belongs to a later, account-owned
    // request and must never replace this campaign or its invitation evidence.
    if (!empty($row['proof_campaign_id']) && (int) $row['proof_campaign_id'] !== $proofCampaignId) {
      throw new \RuntimeException('This public preview delivery is already bound to a different immutable proof campaign.');
    }
    if ((string) ($row['build_dna_id'] ?? '') !== '' && !hash_equals((string) $row['build_dna_id'], $buildDnaId)) {
      throw new \RuntimeException('This public preview delivery is already bound to a different immutable Build DNA record.');
    }
    $this->assertRegisteredBuildDna($buildDnaId, $buildDnaHash, $proofCampaignId, (int) $row['prospect_id'], $deliveryId);
    $now = $this->time->getRequestTime();
    $expires = $now + (14 * 86400);
    $version = max(1, (int) $row['share_version']);
    $signature = $this->shareSignature((string) $row['public_id'], $version);
    $shareUrl = $this->shareUrl((string) $row['public_id'], $signature);
    $business = $this->prospect((int) $row['prospect_id'])->label();
    $subject = 'Your 3 website directions for ' . $business;
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

  /**
   * Holds one exact public-lead campaign for Build DNA registration/review.
   *
   * A prospect can submit more than one lead or project. Selecting the latest
   * delivery by prospect would allow one proof job to mutate another lead's
   * concept room, so callers must pass the immutable delivery correlation
   * returned by the runner contract.
   */
  public function markCampaignReady(int $deliveryId, int $prospectId, int $proofCampaignId): bool {
    $row = $this->load($deliveryId);
    if (!$row || (int) $row['prospect_id'] !== $prospectId) return FALSE;
    if (!in_array((string) $row['state'], ['lead_captured', 'preview_requested', 'research_ready', 'proof_ready_owner_review'], TRUE)) {
      return FALSE;
    }
    if (!empty($row['proof_campaign_id']) && (int) $row['proof_campaign_id'] !== $proofCampaignId) {
      throw new \RuntimeException('A different proof campaign is already immutable on this public preview delivery.');
    }
    $this->assertCompleteCoreCampaign($proofCampaignId, $prospectId);
    $now = $this->time->getRequestTime();
    $this->database->update('famtastic_preview_delivery')->fields([
      'proof_campaign_id' => $proofCampaignId,
      'state' => 'proof_ready_owner_review',
      'proof_ready_at' => $now,
      'last_event_at' => $now,
      'changed' => $now,
    ])->condition('id', $deliveryId)->execute();
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
        'direction_name' => ProofCampaignService::CORE_DIRECTIONS[(string) $variant->get('direction_id')->value] ?? (string) $variant->get('direction_name')->value,
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

  /**
   * Resolves one signed continuation to one unbound preview delivery.
   *
   * This intentionally does not claim by email. The returned record must be
   * bound to the newly created customer before verification can claim it.
   */
  public function markSignupStarted(string $continuation, string $email): ?array {
    [$publicId, $signature] = array_pad(explode('.', trim($continuation), 2), 2, '');
    $row = $this->loadBy('public_id', strtolower($publicId));
    if (!$row || !hash_equals($this->continuationSignature((string) $row['public_id']), $signature)) {
      return NULL;
    }
    if (!hash_equals((string) $row['recipient_hash'], $this->ledger->contactHash($email))) {
      return NULL;
    }
    if (!empty($row['customer_id'])
      || !in_array((string) $row['state'], ['email_staged', 'email_queued', 'email_accepted', 'concept_room_viewed', 'signup_started'], TRUE)) {
      return NULL;
    }
    $now = $this->time->getRequestTime();
    $updated = $this->database->update('famtastic_preview_delivery')->fields([
      'state' => 'signup_started', 'signup_started_at' => $now, 'last_event_at' => $now, 'changed' => $now,
    ])->condition('id', $row['id'])->isNull('customer_id')->execute();
    if ($updated !== 1) {
      return NULL;
    }
    $started = $this->require((int) $row['id']);
    $this->event($started, 'preview.signup_started');
    return $started;
  }

  /**
   * Binds one validated signup continuation to one newly created customer.
   *
   * Customer verification is deliberately a second step. Binding before it
   * prevents a same-email signup from claiming unrelated deliveries.
   */
  public function bindSignupCustomer(int $deliveryId, int $customerId, string $email): array {
    $row = $this->require($deliveryId);
    if (!hash_equals((string) $row['recipient_hash'], $this->ledger->contactHash($email))
      || (string) $row['state'] !== 'signup_started'
      || !empty($row['website_request_id'])) {
      throw new \RuntimeException('The signed public preview continuation is no longer eligible for this account.');
    }
    if (!empty($row['customer_id']) && (int) $row['customer_id'] !== $customerId) {
      throw new \RuntimeException('The signed public preview continuation is already bound to another account.');
    }
    if (empty($row['customer_id'])) {
      $updated = $this->database->update('famtastic_preview_delivery')->fields([
        'customer_id' => $customerId,
        'changed' => $this->time->getRequestTime(),
      ])->condition('id', $deliveryId)->isNull('customer_id')->execute();
      if ($updated !== 1) {
        throw new \RuntimeException('The signed public preview continuation could not be bound safely.');
      }
      $row = $this->require($deliveryId);
      $this->event($row, 'preview.signup_bound', ['customer_id' => $customerId]);
    }
    return $row;
  }

  /** Claims only the continuation delivery already bound to a verified account. */
  public function claimVerifiedCustomer(int $customerId, string $email): void {
    $hash = $this->ledger->contactHash($email);
    $rows = $this->database->select('famtastic_preview_delivery', 'p')->fields('p')
      ->condition('recipient_hash', $hash)
      ->condition('customer_id', $customerId)
      ->isNull('website_request_id')
      ->condition('state', 'signup_started')
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
    $preview = $this->claimedPreviewForCustomer($customerId);
    return $preview ? (int) $preview['prospect_id'] : NULL;
  }

  /**
   * Returns one exact verified public preview eligible for detailed refinement.
   *
   * A missing target is allowed only when exactly one claimed-but-unattached
   * delivery exists for the account. Multiple active deliveries require the
   * caller to name the opaque public delivery ID; they are never resolved by
   * "latest by email" or "latest by prospect".
   */
  public function claimedPreviewForCustomer(int $customerId, ?string $deliveryPublicId = NULL): ?array {
    $query = $this->database->select('famtastic_preview_delivery', 'p')->fields('p')
      ->condition('customer_id', $customerId)
      ->isNull('website_request_id')
      ->condition('state', 'account_verified_and_claimed')
      ->isNotNull('proof_campaign_id');
    $deliveryPublicId = strtolower(trim((string) $deliveryPublicId));
    if ($deliveryPublicId !== '') {
      if (!preg_match('/^[0-9a-f-]{36}$/', $deliveryPublicId)) {
        throw new \InvalidArgumentException('The public preview source is invalid.');
      }
      $row = $query->condition('public_id', $deliveryPublicId)->range(0, 1)->execute()->fetchAssoc();
      return $row ?: NULL;
    }
    $rows = $query->orderBy('claimed_at', 'DESC')->range(0, 2)->execute()->fetchAll(\PDO::FETCH_ASSOC);
    if (count($rows) > 1) {
      throw new \InvalidArgumentException('Choose which public preview should become this detailed website request.');
    }
    return $rows[0] ?? NULL;
  }

  /** Binds one customer-owned detailed request to its exact claimed preview. */
  public function attachClaimedRequest(int $customerId, int $deliveryId, int $requestId, string $state): void {
    $row = $this->require($deliveryId);
    if ((int) $row['customer_id'] !== $customerId
      || !in_array((string) $row['state'], ['account_verified_and_claimed', 'request_attached', 'request_submitted'], TRUE)
      || empty($row['proof_campaign_id'])) {
      throw new \RuntimeException('The selected public preview is not eligible for this detailed website request.');
    }
    if (!empty($row['website_request_id']) && (int) $row['website_request_id'] !== $requestId) {
      throw new \RuntimeException('The selected public preview is already bound to another website request.');
    }
    $now = $this->time->getRequestTime();
    $next = $state === 'submitted' ? 'request_submitted' : 'request_attached';
    $update = $this->database->update('famtastic_preview_delivery')->fields([
      'website_request_id' => $requestId,
      'state' => $next,
      'last_event_at' => $now,
      'changed' => $now,
    ])->condition('id', $deliveryId);
    // Do not let two browser submissions race into the same public delivery.
    // An existing attachment is only idempotent for the same request ID.
    if (empty($row['website_request_id'])) {
      $update->isNull('website_request_id');
    }
    else {
      $update->condition('website_request_id', $requestId);
    }
    if ($update->execute() !== 1) {
      $latest = $this->require($deliveryId);
      if ((int) ($latest['website_request_id'] ?? 0) !== $requestId) {
        throw new \RuntimeException('The selected public preview was claimed by another website request.');
      }
    }
    $updated = $this->require($deliveryId);
    $this->event($updated, $state === 'submitted' ? 'preview.request_submitted' : 'preview.request_attached', ['website_request_id' => $requestId]);
  }

  private function assertCompleteCoreCampaign(int $campaignId, int $prospectId): void {
    $campaign = $this->entities->getStorage('proof_campaign')->load($campaignId);
    if (!$campaign || (int) $campaign->get('prospect_id')->target_id !== $prospectId || $campaign->get('generation_status')->value !== 'ready') {
      throw new \RuntimeException('A ready proof campaign for this public lead is required.');
    }
    $directions = array_map(static fn ($variant): string => (string) $variant->get('direction_id')->value, $this->variants($campaignId));
    sort($directions);
    if ($directions !== ['a', 'b', 'c']) {
      throw new \RuntimeException('Public concept rooms require exactly the Safe, Medium FAMtastic, and Ultra FAMtastic proof set.');
    }
  }

  /** Requires the immutable Drupal projection before a public handoff. */
  private function assertRegisteredBuildDna(string $buildDnaId, string $buildDnaHash, int $proofCampaignId, int $prospectId, int $deliveryId): void {
    $row = $this->database->select('famtastic_build_run', 'b')->fields('b', ['artifact_checksum', 'status', 'output_manifest'])
      ->condition('build_key', 'build-dna:' . $buildDnaId)->range(0, 1)->execute()->fetchAssoc();
    if (!$row || !hash_equals((string) $row['artifact_checksum'], $buildDnaHash) || (string) $row['status'] !== 'completed') {
      throw new \RuntimeException('The matching immutable Build DNA projection must be registered before public preview staging.');
    }
    try {
      $dna = json_decode((string) $row['output_manifest'], TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\Throwable) {
      throw new \RuntimeException('The registered Build DNA projection is unreadable.');
    }
    if (($dna['schema'] ?? '') !== 'famtastic.build-dna.v1'
      || ($dna['classification'] ?? '') !== 'production_proof_completion'
      || ($dna['run']['completion_state'] ?? '') !== 'provider_completed'
      || !in_array(mb_strtolower((string) ($dna['run']['status'] ?? '')), ['passed', 'complete', 'completed'], TRUE)) {
      throw new \RuntimeException('A preflight, fixture, or incomplete Build DNA record cannot stage a public preview.');
    }
    $run = (array) ($dna['run'] ?? []);
    $source = (array) ($run['source_correlation'] ?? []);
    if (($dna['recipe']['routine'] ?? '') !== ProofRunnerContractService::ROUTINE
      || ($dna['recipe']['profile_id'] ?? '') !== 'public_initial.v1'
      || (int) ($run['prospect_id'] ?? 0) !== $prospectId
      || (int) ($run['proof_campaign_id'] ?? 0) !== $proofCampaignId
      || ($source['type'] ?? '') !== 'public_solution_finder_intake'
      || ($source['proof_phase'] ?? '') !== 'initial'
      || (int) ($source['public_preview_delivery_id'] ?? 0) !== $deliveryId) {
      throw new \RuntimeException('Build DNA does not belong to this exact public preview delivery and proof campaign.');
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
    return "Hi,\n\nWe prepared three exploratory directions for {$business}: Safe, Medium FAMtastic, and Ultra FAMtastic. They are a starting point from the general details shared so far—not a final site scope or a statement of current programs, events, partners, or results.\n\nView your private concept room:\n{$shareUrl}\n\nTo make the strongest direction unmistakably yours, create your free FAMtastic Designs workspace with this same email and complete the website request. You can save this work, add your real pages, assets, references, brand direction, and integrations, then receive up to six refined directions before selecting anything.\n\nCreate your free workspace:\n{$registrationUrl}\n\nThe concept room is private and review-only. Nothing is published, selected, priced, or purchased from it.\n\nShay Shay\nFAMtastic Concierge\nConnected digital systems for growing businesses\nhttps://famtasticdesigns.com";
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
