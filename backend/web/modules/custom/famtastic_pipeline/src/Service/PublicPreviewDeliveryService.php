<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Site\Settings;
use Drupal\famtastic_pipeline\Entity\ProofCampaign;
use Drupal\famtastic_pipeline\Entity\Intake;
use Drupal\famtastic_pipeline\Entity\Prospect;

/**
 * Owns public-lead concept-room delivery without becoming a second CRM.
 *
 * A delivery is deliberately inert until an owner stages a complete, audited
 * configured proof cohort and explicitly approves its one invitation.
 */
final class PublicPreviewDeliveryService {

  private const STATES_VISIBLE_TO_HOLDER = [
    'email_approved', 'email_dispatching', 'email_accepted', 'concept_room_viewed',
    // A provider accepted the invitation, but its durable receipt needs
    // reconciliation. Do not resend merely because the receipt write failed.
    'email_receipt_unknown',
    // Kept for source-branch compatibility only. New claims are recorded in
    // customer_id/claimed_at and preserve the delivery's actual state.
    'signup_started', 'account_verified_and_claimed', 'request_submitted',
  ];

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entities,
    private readonly TimeInterface $time,
    private readonly UuidInterface $uuid,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly OperationalLedger $ledger,
    private readonly OutreachMailer $mailer,
    private readonly ProofCohortProfileResolverInterface $profiles,
    private readonly ColdProofCommercialMessageService $coldMessages,
  ) {}

  /** Creates (or returns) the durable, non-sendable delivery for a public lead. */
  public function createForPublicLead(
    int $prospectId,
    int $intakeId = 0,
    ?string $packageProfile = NULL,
    ?int $scheduledReleaseAt = NULL,
    string $sourceLane = 'anonymous_public',
  ): array {
    $prospect = $this->prospect($prospectId);
    $email = $this->email($prospect);
    $profile = $this->profiles->resolveAnonymous($packageProfile);
    $profileSnapshot = $this->profileSnapshot($profile);
    $sourceLane = trim($sourceLane);
    if (!in_array($sourceLane, ['anonymous_public', 'verified_cold'], TRUE)) {
      throw new \InvalidArgumentException('Public preview source lane is invalid.');
    }
    $scheduledReleaseAt = $this->normalizeScheduledReleaseAt($scheduledReleaseAt);
    $key = 'public-preview:prospect:' . $prospectId;
    if ($existing = $this->loadBy('delivery_key', $key)) {
      $mutable = in_array($existing['state'], ['lead_captured', 'preview_requested', 'research_ready'], TRUE);
      if ((string) ($existing['package_profile'] ?? '') !== '' && (string) $existing['package_profile'] !== $profile['id']) {
        if (!$mutable) {
          throw new \RuntimeException('A public preview delivery profile cannot change after proof review begins.');
        }
      }
      if ($mutable && (
        ($intakeId > 0 && (int) ($existing['intake_id'] ?? 0) !== $intakeId)
        || (string) ($existing['package_profile'] ?? '') !== $profile['id']
        || (int) ($existing['scheduled_release_at'] ?? 0) !== (int) ($scheduledReleaseAt ?? 0)
        || (string) ($existing['source_lane'] ?? 'anonymous_public') !== $sourceLane
      )) {
        $this->database->update('famtastic_preview_delivery')->fields([
          'intake_id' => $intakeId ?: ($existing['intake_id'] ?? NULL),
          'package_profile' => $profile['id'],
          'package_variant_count' => $profile['direction_count'],
          'proof_profile_snapshot' => $profileSnapshot,
          'proof_profile_hash' => hash('sha256', $profileSnapshot),
          'source_lane' => $sourceLane,
          'scheduled_release_at' => $scheduledReleaseAt,
          'scheduled_release_set_at' => $scheduledReleaseAt ? $this->time->getRequestTime() : NULL,
          'changed' => $this->time->getRequestTime(),
        ])->condition('id', $existing['id'])->execute();
        return $this->require((int) $existing['id']);
      }
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
      'package_profile' => $profile['id'],
      'package_variant_count' => $profile['direction_count'],
      'proof_profile_snapshot' => $profileSnapshot,
      'proof_profile_hash' => hash('sha256', $profileSnapshot),
      'source_lane' => $sourceLane,
      'scheduled_release_at' => $scheduledReleaseAt,
      'scheduled_release_set_at' => $scheduledReleaseAt ? $now : NULL,
      'subject_snapshot' => '',
      'text_snapshot' => '',
      'proof_variant_snapshot' => '',
      'requested_at' => $now,
      'last_event_at' => $now,
      'created' => $now,
      'changed' => $now,
    ])->execute();
    $row = $this->load($id);
    $this->event($row, 'preview.lead_captured', [
      'package_profile' => $profile['id'],
      'direction_count' => $profile['direction_count'],
      'source_lane' => $sourceLane,
      'scheduled_release_at' => $scheduledReleaseAt,
    ]);
    return $row;
  }

  /**
   * Queues the frozen initial proof cohort job exactly once for this
   * delivery. It creates no public link and sends no email.
   */
  public function queueInitialProof(int $deliveryId): int {
    $row = $this->require($deliveryId);
    $profile = $this->profileForDelivery($row);
    $jobKey = 'public-preview:proof.generate:delivery:' . $deliveryId;
    $existing = $this->database->select('famtastic_job', 'j')->fields('j', ['id'])
      ->condition('job_key', $jobKey)->range(0, 1)->execute()->fetchField();
    if ($existing) {
      return (int) $existing;
    }
    if (!in_array($row['state'], ['lead_captured', 'preview_requested', 'research_ready'], TRUE)) {
      throw new \RuntimeException('This public preview is no longer eligible for an initial proof job.');
    }
    $jobId = $this->ledger->enqueue(
      $jobKey,
      'proof.generate',
      [
        'routine' => 'website_proof.generate.v1',
        'prospect_id' => (int) $row['prospect_id'],
        'public_preview_delivery_id' => $deliveryId,
        'public_preview_package_profile' => $profile['id'],
        'source_lane' => (string) ($row['source_lane'] ?? 'anonymous_public'),
        // The full contract is repeated in the durable job payload. A worker
        // must not silently resolve a later config revision for this lead.
        'public_preview_proof_profile' => $profile,
        'required_variants' => $profile['direction_count'],
      ],
      (int) $row['prospect_id'],
    );
    $now = $this->time->getRequestTime();
    $this->database->update('famtastic_preview_delivery')->fields([
      'state' => 'preview_requested',
      'last_event_at' => $now,
      'changed' => $now,
    ])->condition('id', $deliveryId)->condition('state', ['lead_captured', 'preview_requested', 'research_ready'], 'IN')->execute();
    $this->event($this->require($deliveryId), 'preview.proof_job_queued', [
      'job_id' => $jobId,
      'package_profile' => $profile['id'],
      'direction_count' => $profile['direction_count'],
    ]);
    return $jobId;
  }

  /**
   * Returns a deliberately allowlisted, contact-free brief for an anonymous
   * public proof. Raw public request JSON is never forwarded to Site Studio.
   */
  public function publicIntakeProofContext(int $deliveryId): array {
    $delivery = $this->require($deliveryId);
    $profile = $this->profileForDelivery($delivery);
    $intakeId = (int) ($delivery['intake_id'] ?? 0);
    /** @var \Drupal\famtastic_pipeline\Entity\Intake|null $intake */
    $intake = $intakeId ? $this->entities->getStorage('famtastic_intake')->load($intakeId) : NULL;
    if (!$intake instanceof Intake || (int) $intake->get('prospect_ref')->target_id !== (int) $delivery['prospect_id']) {
      throw new \RuntimeException('The public preview intake is unavailable or does not belong to this prospect.');
    }

    $safe = static function (string $value, int $maximum = 1200): string {
      $value = preg_replace('/\s+/u', ' ', trim(strip_tags($value))) ?? '';
      $value = preg_replace('/\b[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}\b/i', '[redacted email]', $value) ?? '';
      $value = preg_replace('/(?<!\w)(?:\+?1[ .-]?)?(?:\(?\d{3}\)?[ .-]?)\d{3}[ .-]?\d{4}(?!\w)/', '[redacted phone]', $value) ?? '';
      return mb_substr($value, 0, $maximum);
    };
    $field = static fn (Intake $entity, string $name, int $maximum = 1200): string => $safe((string) $entity->get($name)->value, $maximum);
    $brief = [
      'schema_version' => 'public_preview_intake_v1',
      'brief_origin' => 'anonymous_public_intake_allowlist',
      'primary_goal' => $field($intake, 'primary_goal', 255),
      'primary_cta' => $field($intake, 'primary_cta', 255),
      'services' => $field($intake, 'services'),
      'ideal_customer' => $field($intake, 'ideal_customer'),
      'customer_problem' => $field($intake, 'customer_problem'),
      'brand_colors' => $field($intake, 'brand_colors', 255),
      'style_preferences' => $field($intake, 'style_preferences'),
      'reference_sites' => $field($intake, 'reference_sites'),
      'existing_domain' => $field($intake, 'existing_domain', 255),
      'fact_boundary' => 'Use only this preliminary public brief and supplied prospect facts. Do not infer or invent services, outcomes, prices, staff, availability, partners, or customer claims.',
    ];
    if ($evidence = $this->coldProofIngressEvidence($deliveryId)) {
      $brief['verified_source'] = $evidence;
      $brief['fact_boundary'] = 'Use only the supplied preliminary public brief and the explicitly corroborated fact in verified_source. The proof teaser is a non-factual invitation cue, not a statement of current operations. Do not infer or invent services, outcomes, prices, staff, availability, partners, or customer claims.';
    }
    return [
      'public_preview_delivery_id' => $deliveryId,
      'public_preview_package_profile' => $profile['id'],
      'public_preview_proof_profile' => $profile,
      'source_lane' => (string) ($delivery['source_lane'] ?? 'anonymous_public'),
      'website_discovery_v2' => $brief,
      'website_discovery_v3' => $brief,
      // The cohort snapshot, rather than an importer literal, owns these
      // labels and intents. The public room renders the frozen artifacts.
      'public_preview_direction_contract' => $profile['directions'],
    ];
  }

  /** Returns the one campaign explicitly bound to this public delivery. */
  public function initialProofCampaignId(int $deliveryId): ?int {
    $campaignId = (int) ($this->require($deliveryId)['proof_campaign_id'] ?? 0);
    return $campaignId ?: NULL;
  }

  /**
   * Binds the newly created campaign before it can dispatch a remote callback.
   * This is the ownership seam that prevents public jobs from borrowing a
   * request, cold, or older public campaign for the same prospect.
   */
  public function bindInitialProofCampaign(int $deliveryId, int $proofCampaignId): void {
    $row = $this->require($deliveryId);
    $campaign = $this->entities->getStorage('proof_campaign')->load($proofCampaignId);
    if (!$campaign instanceof ProofCampaign || (int) $campaign->get('prospect_id')->target_id !== (int) $row['prospect_id']) {
      throw new \RuntimeException('The public delivery can only bind a proof campaign owned by its prospect.');
    }
    $bound = (int) ($row['proof_campaign_id'] ?? 0);
    if ($bound && $bound !== $proofCampaignId) {
      throw new \RuntimeException('The public delivery is already bound to a different proof campaign.');
    }
    if ($bound === $proofCampaignId) {
      return;
    }
    if (!in_array($row['state'], ['lead_captured', 'preview_requested', 'research_ready', 'signup_started', 'account_verified_and_claimed'], TRUE)) {
      throw new \RuntimeException('The public delivery can no longer receive an initial proof campaign.');
    }
    $now = $this->time->getRequestTime();
    $this->database->update('famtastic_preview_delivery')->fields([
      'proof_campaign_id' => $proofCampaignId,
      'last_event_at' => $now,
      'changed' => $now,
    ])->condition('id', $deliveryId)->condition('proof_campaign_id', NULL, 'IS NULL')->execute();
    $updated = $this->require($deliveryId);
    if ((int) ($updated['proof_campaign_id'] ?? 0) !== $proofCampaignId) {
      throw new \RuntimeException('The public delivery changed before its initial campaign could be bound.');
    }
    $this->event($updated, 'preview.proof_campaign_bound', ['proof_campaign_id' => $proofCampaignId]);
  }

  /**
   * Freezes the invitation content only after an actual Build DNA-backed core
   * campaign is ready. Staging never enables the public room or queues mail.
   */
  public function stage(
    int $deliveryId,
    int $proofCampaignId,
    string $buildDnaId,
    string $buildDnaHash,
    array $research = [],
  ): array {
    $row = $this->require($deliveryId);
    if (!in_array($row['state'], ['lead_captured', 'preview_requested', 'research_ready', 'proof_ready_owner_review', 'email_staged', 'share_revoked', 'signup_started', 'account_verified_and_claimed'], TRUE)) {
      throw new \RuntimeException('Preview delivery can no longer be staged.');
    }
    if ((int) ($row['proof_campaign_id'] ?? 0) !== $proofCampaignId) {
      throw new \RuntimeException('Stage only the exact proof campaign bound to this public delivery.');
    }
    $profile = $this->profileForDelivery($row);
    $campaignEvidence = $this->assertCompleteProofCampaign($proofCampaignId, (int) $row['prospect_id'], $profile);
    $buildDnaId = trim($buildDnaId);
    $buildDnaHash = strtolower(trim($buildDnaHash));
    if ($buildDnaId === '' || !preg_match('/^[a-f0-9]{64}$/', $buildDnaHash)) {
      throw new \InvalidArgumentException('A Build DNA identifier and SHA-256 hash are required before staging.');
    }
    $research = $this->normalizeResearch($research);
    $this->assertRegisteredBuildDna($buildDnaId, $buildDnaHash, $campaignEvidence, $research['evidence_hash'], $research['evidence_role'], (string) ($row['source_lane'] ?? 'anonymous_public'));
    $now = $this->time->getRequestTime();
    $expires = $now + (14 * 86400);
    $version = max(1, (int) $row['share_version']);
    $signature = $this->shareSignature((string) $row['public_id'], $version);
    $shareUrl = $this->shareUrl((string) $row['public_id'], $signature);
    $prospect = $this->prospect((int) $row['prospect_id']);
    $business = $prospect->label();
    $proofCount = (int) $profile['direction_count'];
    $subject = $this->proofCountLabel($proofCount) . ' website directions for ' . $business;
    $text = $this->invitationBody($business, $shareUrl, $this->registrationUrl((string) $row['public_id']), $research, $proofCount);
    $commercialMessageId = NULL;
    if ((string) ($row['source_lane'] ?? 'anonymous_public') === 'verified_cold') {
      $commercial = $this->coldMessages->stage($row, $prospect, $subject, $text, $shareUrl);
      $commercialMessageId = (int) $commercial['id'];
      $subject = (string) $commercial['subject'];
      $text = (string) $commercial['body_snapshot'];
    }
    $this->database->update('famtastic_preview_delivery')->fields([
      'proof_campaign_id' => $proofCampaignId,
      'proof_variant_snapshot' => json_encode($campaignEvidence['variants'], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
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
      'commercial_message_id' => $commercialMessageId,
      'public_context_snapshot' => $research['public_context'],
      'research_teaser_snapshot' => $research['teaser'],
      'research_sources_snapshot' => $research['sources'],
      'research_report_snapshot' => $research['report'],
      'research_snapshot_hash' => $research['snapshot_hash'],
      'research_evidence_hash' => $research['evidence_hash'],
      'research_evidence_role' => $research['evidence_role'],
      // A replacement stage intentionally produces a new signed room after a
      // revocation.  The previous version remains invalid because its version
      // no longer matches; clearing these markers enables the new version.
      'share_revoked_at' => NULL,
      'share_revoked_by_uid' => NULL,
      'proof_ready_at' => $now,
      'last_event_at' => $now,
      'changed' => $now,
    ])->condition('id', $deliveryId)->execute();
    $row = $this->require($deliveryId);
    $this->event($row, 'preview.staged', [
      'proof_campaign_id' => $proofCampaignId,
      'build_dna_id' => $buildDnaId,
      'research_snapshot_hash' => $research['snapshot_hash'],
      'research_evidence_hash' => $research['evidence_hash'],
      'commercial_message_id' => $commercialMessageId,
    ]);
    return $row;
  }

  /** Holds a complete public-lead proof set for Build DNA registration and owner review. */
  public function markCampaignReady(int $prospectId, int $proofCampaignId): bool {
    $query = $this->database->select('famtastic_preview_delivery', 'p')->fields('p')
      ->condition('prospect_id', $prospectId)
      ->condition('state', [
        'lead_captured', 'preview_requested', 'research_ready', 'proof_ready_owner_review',
        'email_staged', 'email_approved', 'email_dispatching', 'email_dispatch_failed',
        'email_accepted', 'email_receipt_unknown', 'concept_room_viewed',
        'signup_started', 'account_verified_and_claimed', 'share_revoked', 'expired', 'request_submitted',
      ], 'IN');
    $row = $query->condition('proof_campaign_id', $proofCampaignId)->orderBy('created', 'DESC')->range(0, 1)->execute()->fetchAssoc();
    if (!$row) return FALSE;
    return $this->markCampaignReadyForDelivery((int) $row['id'], $proofCampaignId);
  }

  /** True only when this exact campaign can belong to a public delivery. */
  public function isPublicDeliveryForCampaign(int $prospectId, int $proofCampaignId): bool {
    $query = $this->database->select('famtastic_preview_delivery', 'p')->fields('p', ['id'])
      ->condition('prospect_id', $prospectId)
      ->condition('state', [
        'lead_captured', 'preview_requested', 'research_ready', 'proof_ready_owner_review',
        'email_staged', 'email_approved', 'email_dispatching', 'email_dispatch_failed',
        'email_accepted', 'email_receipt_unknown', 'concept_room_viewed',
        'signup_started', 'account_verified_and_claimed', 'share_revoked', 'expired', 'request_submitted',
      ], 'IN');
    return (bool) $query->condition('proof_campaign_id', $proofCampaignId)->range(0, 1)->execute()->fetchField();
  }

  /**
   * Returns the frozen profile for one exact public campaign, never a global
   * default. Non-public campaigns intentionally return NULL.
   */
  public function proofProfileForCampaign(int $prospectId, int $proofCampaignId): ?array {
    $row = $this->database->select('famtastic_preview_delivery', 'p')->fields('p')
      ->condition('prospect_id', $prospectId)
      ->condition('proof_campaign_id', $proofCampaignId)
      ->range(0, 1)->execute()->fetchAssoc();
    return $row ? $this->profileForDelivery($row) : NULL;
  }

  /** Returns the immutable source lane for the exact campaign, if public. */
  public function sourceLaneForCampaign(int $prospectId, int $proofCampaignId): ?string {
    $lane = $this->database->select('famtastic_preview_delivery', 'p')->fields('p', ['source_lane'])
      ->condition('prospect_id', $prospectId)
      ->condition('proof_campaign_id', $proofCampaignId)
      ->range(0, 1)->execute()->fetchField();
    return $lane === FALSE ? NULL : (string) $lane;
  }

  /**
   * Marks one known public delivery ready. Unlike the legacy prospect lookup,
   * this exact-ID path can never fall back to generic outreach on a retry.
   */
  public function markCampaignReadyForDelivery(int $deliveryId, int $proofCampaignId): bool {
    $row = $this->require($deliveryId);
    $prospectId = (int) $row['prospect_id'];
    $this->assertCompleteProofCampaign($proofCampaignId, $prospectId, $this->profileForDelivery($row));
    $boundCampaignId = (int) ($row['proof_campaign_id'] ?? 0);
    if ($boundCampaignId && $boundCampaignId !== $proofCampaignId) {
      throw new \RuntimeException('This public delivery already belongs to a different proof campaign.');
    }
    if (in_array($row['state'], [
      'proof_ready_owner_review', 'email_staged', 'email_approved', 'email_dispatching',
      'email_dispatch_failed', 'email_accepted', 'email_receipt_unknown', 'concept_room_viewed',
      'share_revoked', 'expired', 'request_submitted',
    ], TRUE)) {
      return TRUE;
    }
    if (!in_array($row['state'], ['lead_captured', 'preview_requested', 'research_ready', 'signup_started', 'account_verified_and_claimed'], TRUE)) {
      return FALSE;
    }
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

  /**
   * Explicit owner action: freezes one approved invitation in a held outbox.
   *
   * Held preview messages are intentionally invisible to the global lifecycle
   * dispatcher. They may be sent only by dispatchApproved() with the exact
   * owner-reviewed delivery IDs.
   */
  public function approveAndHold(int $deliveryId, int $uid): array {
    $row = $this->require($deliveryId);
    if ($row['state'] !== 'email_staged') {
      throw new \RuntimeException('Preview email is not staged for owner approval.');
    }
    if ($this->ledger->isSuppressed((string) $row['recipient_address_snapshot'])) {
      throw new \RuntimeException('A suppressed contact cannot receive a public preview invitation.');
    }
    // For commercial verified-cold delivery, establish the campaign-approved
    // hold before exposing any outbox row or marking the delivery approved.
    // A draft/revoked campaign therefore fails closed without a dispatchable
    // preview record. The commercial hold is idempotent for safe recovery if
    // a later local write is interrupted.
    if ((string) ($row['source_lane'] ?? 'anonymous_public') === 'verified_cold') {
      $this->coldMessages->hold($deliveryId);
    }
    $now = $this->time->getRequestTime();
    $key = 'preview-delivery:' . $deliveryId . ':share:' . (int) $row['share_version'];
    $this->database->merge('famtastic_notification_outbox')->key('notification_key', $key)->insertFields([
      'notification_key' => $key,
      'category' => 'transactional',
      'recipient' => (string) $row['recipient_address_snapshot'],
      'subject' => (string) $row['subject_snapshot'],
      'body' => (string) $row['text_snapshot'],
      'status' => 'held',
      'attempts' => 0,
      'max_attempts' => 5,
      'available_at' => $now,
      'created' => $now,
      'changed' => $now,
    ])->execute();
    $outbox = $this->database->select('famtastic_notification_outbox', 'n')->fields('n')
      ->condition('notification_key', $key)->execute()->fetchAssoc();
    $this->database->update('famtastic_preview_delivery')->fields([
      'state' => 'email_approved',
      'owner_approved_at' => $now,
      'owner_approved_by_uid' => $uid,
      'email_outbox_id' => (int) $outbox['id'],
      'queued_at' => $now,
      'last_event_at' => $now,
      'changed' => $now,
    ])->condition('id', $deliveryId)->execute();
    $row = $this->require($deliveryId);
    $this->event($row, 'preview.approved', ['owner_uid' => $uid]);
    $this->event($row, 'preview.email_held', ['outbox_id' => (int) $outbox['id']]);
    return $row;
  }

  /**
   * Delivers only the exact owner-approved public preview IDs supplied.
   *
   * This deliberately does not call LifecycleOperationsService and cannot
   * discover unrelated queued mail. All IDs are atomically claimed first;
   * each provider send then receives its own durable acceptance or failure
   * receipt. A process interruption leaves `email_dispatching` for operator
   * reconciliation instead of risking an automatic duplicate send.
   *
   * @return array{dispatch_key:string,claimed:int,sent:int,failed:int,receipt_unknown:int,results:list<array{id:int,status:string}>}
   */
  public function dispatchApproved(array $deliveryIds): array {
    $ids = array_values(array_unique(array_filter(array_map('intval', $deliveryIds), static fn (int $id): bool => $id > 0)));
    sort($ids, SORT_NUMERIC);
    if ($ids === [] || count($ids) > 10) {
      throw new \InvalidArgumentException('Public preview dispatch requires between one and ten exact delivery IDs.');
    }

    $now = $this->time->getRequestTime();
    $dispatchKey = 'preview-dispatch:' . $this->uuid->generate();
    $claimed = [];
    $transaction = $this->database->startTransaction();
    try {
      foreach ($ids as $id) {
        $delivery = $this->require($id);
        $outboxId = (int) ($delivery['email_outbox_id'] ?? 0);
        $expectedKey = 'preview-delivery:' . $id . ':share:' . (int) $delivery['share_version'];
        $outbox = $outboxId ? $this->database->select('famtastic_notification_outbox', 'n')->fields('n')
          ->condition('id', $outboxId)->range(0, 1)->execute()->fetchAssoc() : FALSE;
        if (
          $delivery['state'] !== 'email_approved'
          || !$outbox
          || $outbox['status'] !== 'held'
          || !hash_equals($expectedKey, (string) $outbox['notification_key'])
        ) {
          throw new \RuntimeException('Preview delivery ' . $id . ' is not an exact owner-approved held invitation.');
        }
        if ($this->ledger->isSuppressed((string) $outbox['recipient'])) {
          throw new \RuntimeException('Preview delivery ' . $id . ' is suppressed and cannot be dispatched.');
        }
        if ($this->database->update('famtastic_notification_outbox')->fields([
          'status' => 'dispatching', 'changed' => $now,
        ])->condition('id', $outboxId)->condition('status', 'held')->execute() !== 1) {
          throw new \RuntimeException('Preview delivery ' . $id . ' could not be claimed for targeted dispatch.');
        }
        if ($this->database->update('famtastic_preview_delivery')->fields([
          'state' => 'email_dispatching', 'dispatch_key' => $dispatchKey,
          'dispatch_claimed_at' => $now, 'last_event_at' => $now, 'changed' => $now,
        ])->condition('id', $id)->condition('state', 'email_approved')->execute() !== 1) {
          throw new \RuntimeException('Preview delivery ' . $id . ' changed before targeted dispatch could begin.');
        }
        if ((string) ($delivery['source_lane'] ?? 'anonymous_public') === 'verified_cold') {
          $this->coldMessages->claim($id);
        }
        $claimed[] = [
          'id' => $id,
          'outbox_id' => $outboxId,
          'recipient' => (string) $outbox['recipient'],
          'subject' => (string) $outbox['subject'],
          'body' => (string) $outbox['body'],
        ];
      }
      foreach ($claimed as $item) {
        $this->event($this->require($item['id']), 'preview.dispatch_claimed', ['dispatch_key' => $dispatchKey, 'outbox_id' => $item['outbox_id']]);
      }
    }
    catch (\Throwable $error) {
      $transaction->rollBack();
      throw $error;
    }
    // Commit the exact-ID claim before any network I/O. SMTP and receipt writes
    // must never share a transaction with held-message claiming.
    unset($transaction);

    $result = [
      'dispatch_key' => $dispatchKey,
      'claimed' => count($claimed),
      'sent' => 0,
      'failed' => 0,
      'receipt_unknown' => 0,
      'results' => [],
    ];
    foreach ($claimed as $item) {
      $providerAccepted = FALSE;
      $providerMessageId = '';
      try {
        $providerMessageId = $this->mailer->send($item['recipient'], $item['subject'], $item['body']);
        $providerAccepted = TRUE;
        $this->recordDispatchAccepted($item['id'], $item['outbox_id'], $dispatchKey, $providerMessageId);
        $result['sent']++;
        $result['results'][] = ['id' => $item['id'], 'status' => 'accepted'];
      }
      catch (\Throwable $error) {
        if ($providerAccepted) {
          // The provider accepted the email. A later database/event failure is
          // a reconciliation problem, never evidence that it is safe to retry.
          $this->recordDispatchReceiptUnknown($item['id'], $item['outbox_id'], $dispatchKey, $providerMessageId, $error->getMessage());
          $result['receipt_unknown']++;
          $result['results'][] = ['id' => $item['id'], 'status' => 'receipt_unknown'];
        }
        else {
          $this->recordDispatchFailure($item['id'], $item['outbox_id'], $dispatchKey, $error->getMessage());
          $result['failed']++;
          $result['results'][] = ['id' => $item['id'], 'status' => 'failed'];
        }
      }
    }
    return $result;
  }

  /** Immediately invalidates the existing room and requires a new stage. */
  public function revoke(int $deliveryId, int $uid): array {
    $row = $this->require($deliveryId);
    if ($row['state'] === 'email_dispatching') {
      throw new \RuntimeException('This invitation is being dispatched. Wait for its recorded SMTP result before revoking the room.');
    }
    $now = $this->time->getRequestTime();
    // Cancel the exact, not-yet-sent commercial snapshot before changing the
    // room version. This prevents an orphan held cold message from surviving
    // a revoked room and being mistaken for a later eligible send.
    if (
      (string) ($row['source_lane'] ?? 'anonymous_public') === 'verified_cold'
      && in_array((string) $row['state'], ['email_staged', 'email_approved'], TRUE)
    ) {
      $this->coldMessages->revoke($deliveryId, 'public_preview_share_revoked_before_dispatch');
    }
    // A held invitation is not visible to the general lifecycle dispatcher,
    // but revoke it explicitly as well so it cannot be mistaken for an
    // eligible exact-ID dispatch after this delivery is restaged.
    $outboxId = (int) ($row['email_outbox_id'] ?? 0);
    if ($row['state'] === 'email_approved' && $outboxId > 0) {
      $this->database->update('famtastic_notification_outbox')->fields([
        'status' => 'cancelled',
        'last_error' => 'cancelled: public preview link revoked before targeted dispatch',
        'changed' => $now,
      ])->condition('id', $outboxId)->condition('status', 'held')->execute();
    }
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

  /** Records exact SMTP acceptance without treating it as inbox delivery. */
  private function recordDispatchAccepted(int $deliveryId, int $outboxId, string $dispatchKey, string $providerMessageId): void {
    $now = $this->time->getRequestTime();
    $transaction = $this->database->startTransaction();
    try {
      $outboxUpdated = $this->database->update('famtastic_notification_outbox')->fields([
        'status' => 'sent', 'attempts' => $this->database->query('SELECT attempts FROM {famtastic_notification_outbox} WHERE id = :id', [':id' => $outboxId])->fetchField() + 1,
        'sent_at' => $now, 'provider_message_id' => $providerMessageId, 'last_error' => NULL, 'changed' => $now,
      ])->condition('id', $outboxId)->condition('status', 'dispatching')->execute();
      $deliveryUpdated = $this->database->update('famtastic_preview_delivery')->fields([
      'state' => 'email_accepted', 'provider_message_id' => $providerMessageId,
        'accepted_at' => $now, 'dispatch_completed_at' => $now, 'last_event_at' => $now, 'changed' => $now,
      ])->condition('id', $deliveryId)->condition('state', 'email_dispatching')->condition('dispatch_key', $dispatchKey)->execute();
      if ($outboxUpdated !== 1 || $deliveryUpdated !== 1) {
        throw new \RuntimeException('Targeted preview dispatch acceptance could not be recorded safely.');
      }
      if ($this->isVerifiedColdDelivery($deliveryId)) {
        $this->coldMessages->accepted($deliveryId, $providerMessageId);
      }
    }
    catch (\Throwable $error) {
      $transaction->rollBack();
      throw $error;
    }
    unset($transaction);
    // The provider receipt is already durable. Event-recording trouble must
    // never turn an accepted send into a retryable failure.
    try {
      $this->event($this->require($deliveryId), 'preview.email_accepted', [
        'outbox_id' => $outboxId,
        'dispatch_key' => $dispatchKey,
        'provider_message_id' => $providerMessageId,
      ]);
    }
    catch (\Throwable) {
      // Reconciliation can recover the provider id from the durable rows.
    }
  }

  /** Records an accepted provider send whose final local receipt needs review. */
  private function recordDispatchReceiptUnknown(int $deliveryId, int $outboxId, string $dispatchKey, string $providerMessageId, string $message): void {
    $now = $this->time->getRequestTime();
    $transaction = $this->database->startTransaction();
    try {
      $attempts = (int) $this->database->query('SELECT attempts FROM {famtastic_notification_outbox} WHERE id = :id', [':id' => $outboxId])->fetchField();
      $outboxUpdated = $this->database->update('famtastic_notification_outbox')->fields([
        'status' => 'receipt_unknown', 'attempts' => $attempts + 1,
        'provider_message_id' => $providerMessageId,
        'last_error' => mb_substr('provider accepted; local receipt reconciliation required: ' . $message, 0, 2000),
        'changed' => $now,
      ])->condition('id', $outboxId)->condition('status', 'dispatching')->execute();
      $deliveryUpdated = $this->database->update('famtastic_preview_delivery')->fields([
        'state' => 'email_receipt_unknown', 'provider_message_id' => $providerMessageId,
        'dispatch_completed_at' => $now, 'last_event_at' => $now, 'changed' => $now,
      ])->condition('id', $deliveryId)->condition('state', 'email_dispatching')->condition('dispatch_key', $dispatchKey)->execute();
      if ($outboxUpdated !== 1 || $deliveryUpdated !== 1) {
        throw new \RuntimeException('Provider acceptance could not be marked for receipt reconciliation.');
      }
      if ($this->isVerifiedColdDelivery($deliveryId)) {
        $this->coldMessages->receiptUnknown($deliveryId, $providerMessageId);
      }
    }
    catch (\Throwable $receiptError) {
      $transaction->rollBack();
      // This path must never trigger a resend. Preserve the original error in
      // the command result and leave the dispatching rows for manual recovery.
      return;
    }
    unset($transaction);
    try {
      $this->event($this->require($deliveryId), 'preview.email_receipt_unknown', [
        'outbox_id' => $outboxId,
        'dispatch_key' => $dispatchKey,
        'provider_message_id' => $providerMessageId,
        'error_hash' => hash('sha256', $message),
      ]);
    }
    catch (\Throwable) {
      // The durable delivery/outbox rows remain the reconciliation source.
    }
  }

  /** Leaves a bounded targeted send as an explicit operator exception. */
  private function recordDispatchFailure(int $deliveryId, int $outboxId, string $dispatchKey, string $message): void {
    $now = $this->time->getRequestTime();
    $transaction = $this->database->startTransaction();
    try {
      $attempts = (int) $this->database->query('SELECT attempts FROM {famtastic_notification_outbox} WHERE id = :id', [':id' => $outboxId])->fetchField();
      $outboxUpdated = $this->database->update('famtastic_notification_outbox')->fields([
        'status' => 'dispatch_failed', 'attempts' => $attempts + 1,
        'last_error' => mb_substr($message, 0, 2000), 'changed' => $now,
      ])->condition('id', $outboxId)->condition('status', 'dispatching')->execute();
      $deliveryUpdated = $this->database->update('famtastic_preview_delivery')->fields([
        'state' => 'email_dispatch_failed', 'dispatch_failed_at' => $now,
        'last_event_at' => $now, 'changed' => $now,
      ])->condition('id', $deliveryId)->condition('state', 'email_dispatching')->condition('dispatch_key', $dispatchKey)->execute();
      if ($outboxUpdated !== 1 || $deliveryUpdated !== 1) {
        throw new \RuntimeException('Targeted preview dispatch failure could not be recorded safely.');
      }
      if ($this->isVerifiedColdDelivery($deliveryId)) {
        $this->coldMessages->failed($deliveryId, $message);
      }
      $this->event($this->require($deliveryId), 'preview.email_dispatch_failed', [
        'outbox_id' => $outboxId,
        'dispatch_key' => $dispatchKey,
        'error_hash' => hash('sha256', $message),
      ]);
    }
    catch (\Throwable $error) {
      $transaction->rollBack();
      throw $error;
    }
  }

  /** Resolves the deliberately minimal public concept-room payload. */
  public function publicShare(string $publicId, string $signature): ?array {
    $row = $this->loadBy('public_id', strtolower($publicId));
    if (!$row || !$this->isCurrentShare($row, $signature)) {
      return NULL;
    }
    $profile = $this->profileForDelivery($row);
    $variants = $this->snapshotVariants($row, $profile);
    if (count($variants) !== $profile['direction_count']) {
      return NULL;
    }
    $now = $this->time->getRequestTime();
    if (in_array($row['state'], ['email_accepted', 'email_receipt_unknown'], TRUE)) {
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
      'proof_count' => $profile['direction_count'],
      'proof_profile' => ['id' => $profile['id'], 'directions' => $profile['directions']],
      'private_label' => 'Private review concept · Not yet published.',
      'public_context' => (string) ($row['public_context_snapshot'] ?? ''),
      'research_teaser' => (string) ($row['research_teaser_snapshot'] ?? ''),
      'registration_url' => $this->registrationUrl((string) $row['public_id']),
      'variants' => array_map(fn (array $variant): array => [
        'direction_id' => $variant['direction_id'],
        'direction_name' => $variant['direction_name'],
        'preview_url' => $this->publicPreviewUrl((string) $row['public_id'], $signature, $variant['direction_id']),
      ], $variants),
    ];
  }

  /** Returns a research snapshot only to the verified customer that claimed it. */
  public function researchForVerifiedCustomer(int $customerId, string $publicId): ?array {
    $row = $this->loadBy('public_id', strtolower($publicId));
    if (!$row || (int) ($row['customer_id'] ?? 0) !== $customerId) {
      return NULL;
    }
    $report = trim((string) ($row['research_report_snapshot'] ?? ''));
    if ($report === '') {
      return NULL;
    }
    return [
      'preview_delivery' => (string) $row['public_id'],
      'research_teaser' => (string) ($row['research_teaser_snapshot'] ?? ''),
      'sources_summary' => (string) ($row['research_sources_snapshot'] ?? ''),
      'report' => $report,
      'build_dna_id' => (string) ($row['build_dna_id'] ?? ''),
      'research_evidence_hash' => (string) ($row['research_evidence_hash'] ?? ''),
      'research_evidence_role' => (string) ($row['research_evidence_role'] ?? ''),
    ];
  }

  /** Returns the approved campaign/direction only for a valid current share. */
  public function publicVariant(string $publicId, string $signature, string $direction): ?array {
    $row = $this->loadBy('public_id', strtolower($publicId));
    if (!$row || !$this->isCurrentShare($row, $signature)) {
      return NULL;
    }
    $profile = $this->profileForDelivery($row);
    $direction = strtolower($direction);
    if (!array_key_exists($direction, $profile['directions'])) {
      return NULL;
    }
    foreach ($this->snapshotVariants($row, $profile) as $variant) {
      if ($variant['direction_id'] === $direction) {
        return $variant;
      }
    }
    return NULL;
  }

  /**
   * Resolves one frozen image only while the signed room remains valid.
   *
   * This intentionally rehashes the file at read time. A later write, path
   * swap, revocation, or expiration therefore fails closed instead of leaking
   * a mutable filesystem asset through an otherwise valid concept-room link.
   */
  public function publicAsset(string $publicId, string $signature, string $direction, string $relativePath): ?array {
    try {
      $relativePath = ProofAssetContract::normalizeRelativePath($relativePath);
    }
    catch (\InvalidArgumentException) {
      return NULL;
    }
    $row = $this->loadBy('public_id', strtolower($publicId));
    if (!$row || !$this->isCurrentShare($row, $signature)) {
      return NULL;
    }
    $direction = strtolower($direction);
    $profile = $this->profileForDelivery($row);
    if (!array_key_exists($direction, $profile['directions'])) {
      return NULL;
    }
    $campaign = $this->entities->getStorage('proof_campaign')->load((int) ($row['proof_campaign_id'] ?? 0));
    if (!$campaign instanceof ProofCampaign) {
      return NULL;
    }
    foreach ($this->snapshotVariants($row, $profile) as $variant) {
      if ($variant['direction_id'] !== $direction) {
        continue;
      }
      foreach ($variant['assets'] as $asset) {
        if (!hash_equals($asset['relative_path'], $relativePath)) {
          continue;
        }
        try {
          $expected = ProofAssetContract::artifactPath((string) $campaign->get('campaign_id')->value, $direction, $asset['relative_path']);
        }
        catch (\InvalidArgumentException) {
          return NULL;
        }
        if (!hash_equals($expected, $asset['artifact_path'])) {
          return NULL;
        }
        $path = dirname(\Drupal::root()) . '/' . $asset['artifact_path'];
        $real = realpath($path);
        $root = realpath(\Drupal::root() . '/proofs');
        $expectedRoot = $root ? $root . DIRECTORY_SEPARATOR . (string) $campaign->get('campaign_id')->value . DIRECTORY_SEPARATOR . $direction . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR : '';
        if (!$real || $expectedRoot === '' || !str_starts_with($real, $expectedRoot) || !is_file($real)) {
          return NULL;
        }
        // Verify the exact bytes returned to the controller rather than a
        // prior filesystem snapshot, closing a check/read race on mutable
        // storage.
        $bytes = file_get_contents($real);
        if ($bytes === FALSE || !hash_equals($asset['sha256'], hash('sha256', $bytes)) || strlen($bytes) !== (int) $asset['size_bytes']) {
          return NULL;
        }
        return $asset + ['bytes' => $bytes];
      }
    }
    return NULL;
  }

  /** The injected document base that routes every relative media URL by signature. */
  public function publicAssetBaseUrl(string $publicId, string $signature, string $direction): string {
    // Stored proof HTML uses relative asset URLs such as "assets/hero.webp".
    // The signed reader is rooted at the proof URL, not its assets directory,
    // so the browser resolves that relative path exactly once.
    return '/web/api/public-preview/' . rawurlencode($publicId) . '/' . rawurlencode($signature) . '/proofs/' . rawurlencode(strtolower($direction)) . '/';
  }

  /** Records a non-sensitive signup start without advancing delivery state. */
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
      'signup_started_at' => $now, 'last_event_at' => $now, 'changed' => $now,
    ])->condition('id', $row['id'])->execute();
    $this->event($this->require((int) $row['id']), 'preview.signup_started');
  }

  /** Claims eligible previews only after the account email has been verified. */
  public function claimVerifiedCustomer(int $customerId, string $email): void {
    $hash = $this->ledger->contactHash($email);
    $rows = $this->database->select('famtastic_preview_delivery', 'p')->fields('p')
      ->condition('recipient_hash', $hash)
      ->condition('customer_id', NULL, 'IS NULL')
      ->condition('state', [
        'lead_captured', 'preview_requested', 'research_ready', 'proof_ready_owner_review',
        'email_staged', 'email_approved', 'email_dispatching', 'email_dispatch_failed',
        'email_accepted', 'email_receipt_unknown', 'concept_room_viewed', 'signup_started', 'account_verified_and_claimed',
        // Claiming preserves a revoked/expired room as unavailable while
        // retaining the same prospect and proof history in the workspace.
        'share_revoked', 'expired', 'request_submitted',
      ], 'IN')
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $now = $this->time->getRequestTime();
    foreach ($rows as $row) {
      $this->database->update('famtastic_preview_delivery')->fields([
        'customer_id' => $customerId,
        'claimed_at' => $now,
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
      ->orderBy('claimed_at', 'DESC')->range(0, 1)->execute()->fetchField();
    return $value ? (int) $value : NULL;
  }

  /** Binds a customer's new canonical request to their claimed preview. */
  public function attachClaimedRequest(int $customerId, int $requestId, string $state): void {
    $row = $this->database->select('famtastic_preview_delivery', 'p')->fields('p')
      ->condition('customer_id', $customerId)
      ->isNull('website_request_id')
      ->orderBy('claimed_at', 'DESC')->range(0, 1)->execute()->fetchAssoc();
    if (!$row) {
      return;
    }
    $now = $this->time->getRequestTime();
    $this->database->update('famtastic_preview_delivery')->fields([
      'website_request_id' => $requestId,
      'last_event_at' => $now,
      'changed' => $now,
    ])->condition('id', $row['id'])->execute();
    $this->event($this->require((int) $row['id']), $state === 'submitted' ? 'preview.request_submitted' : 'preview.request_attached', ['website_request_id' => $requestId]);
  }

  /** Resolves the immutable cohort profile selected for this public delivery. */
  private function profileForDelivery(array $delivery): array {
    $snapshot = trim((string) ($delivery['proof_profile_snapshot'] ?? ''));
    if ($snapshot !== '') {
      try {
        $profile = json_decode($snapshot, TRUE, 16, JSON_THROW_ON_ERROR);
      }
      catch (\Throwable) {
        throw new \RuntimeException('The stored public proof cohort snapshot is unreadable.');
      }
      if (!is_array($profile) || (string) ($profile['audience'] ?? '') !== 'anonymous_public') {
        throw new \RuntimeException('The stored public proof cohort snapshot is invalid.');
      }
      $profile['id'] = trim((string) ($profile['id'] ?? ''));
      $profile['direction_count'] = (int) ($profile['direction_count'] ?? 0);
      $profile['directions'] = (array) ($profile['directions'] ?? []);
      $expected = array_slice(['a', 'b', 'c', 'd', 'e', 'f'], 0, $profile['direction_count']);
      if ($profile['id'] === '' || $profile['direction_count'] < 1 || $profile['direction_count'] > 6 || array_keys($profile['directions']) !== $expected) {
        throw new \RuntimeException('The stored public proof cohort does not define a bounded direction contract.');
      }
      foreach ($profile['directions'] as $definition) {
        if (!is_array($definition) || trim((string) ($definition['name'] ?? '')) === '' || trim((string) ($definition['intent'] ?? '')) === '') {
          throw new \RuntimeException('The stored public proof cohort has an incomplete direction definition.');
        }
      }
      if ((int) ($delivery['package_variant_count'] ?? 0) !== $profile['direction_count']) {
        throw new \RuntimeException('The stored public preview direction count does not match its frozen cohort profile.');
      }
      $storedHash = strtolower(trim((string) ($delivery['proof_profile_hash'] ?? '')));
      if ($storedHash !== '' && !hash_equals($storedHash, hash('sha256', $snapshot))) {
        throw new \RuntimeException('The stored public proof cohort snapshot failed integrity verification.');
      }
      return $profile;
    }
    $profileId = trim((string) ($delivery['package_profile'] ?? ''));
    $profile = $this->profiles->resolveAnonymous($profileId ?: NULL);
    if ((int) ($delivery['package_variant_count'] ?? 0) > 0 && (int) $delivery['package_variant_count'] !== $profile['direction_count']) {
      throw new \RuntimeException('The stored public preview direction count no longer matches its named cohort profile.');
    }
    return $profile;
  }

  private function isVerifiedColdDelivery(int $deliveryId): bool {
    return (bool) $this->database->select('famtastic_preview_delivery', 'p')->fields('p', ['id'])
      ->condition('id', $deliveryId)
      ->condition('source_lane', 'verified_cold')
      ->range(0, 1)->execute()->fetchField();
  }

  /** Stable JSON for the delivery/job/Build DNA proof-profile contract. */
  private function profileSnapshot(array $profile): string {
    return json_encode([
      'id' => (string) $profile['id'],
      'audience' => 'anonymous_public',
      'direction_count' => (int) $profile['direction_count'],
      'directions' => $profile['directions'],
    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
  }

  /** Accepts an optional, auditable Unix release time; never releases a room. */
  private function normalizeScheduledReleaseAt(?int $scheduledReleaseAt): ?int {
    if ($scheduledReleaseAt === NULL || $scheduledReleaseAt === 0) {
      return NULL;
    }
    if ($scheduledReleaseAt < 1) {
      throw new \InvalidArgumentException('Scheduled public-preview release time must be a positive Unix timestamp.');
    }
    return $scheduledReleaseAt;
  }

  /**
   * Gets the evidence allowlist for a cold cohort without exposing a lead's
   * contact data to a public proof worker or room.
   */
  private function coldProofIngressEvidence(int $deliveryId): ?array {
    if (!$this->database->schema()->tableExists('famtastic_cold_proof_ingress')) {
      return NULL;
    }
    $row = $this->database->select('famtastic_cold_proof_ingress', 'i')->fields('i', [
      'source_url', 'source_provenance', 'source_timeframe', 'website_observation_status', 'website_observation_fact', 'corroborated_fact', 'proof_teaser', 'evidence_hash',
    ])->condition('preview_delivery_id', $deliveryId)->range(0, 1)->execute()->fetchAssoc();
    if (!$row) {
      return NULL;
    }
    $safe = static function (mixed $value, int $maximum): string {
      $value = preg_replace('/\s+/u', ' ', trim(strip_tags((string) $value))) ?? '';
      return mb_substr($value, 0, $maximum);
    };
    $sourceUrl = trim((string) $row['source_url']);
    $parts = parse_url($sourceUrl);
    if (is_array($parts) && isset($parts['scheme'], $parts['host'])) {
      $sourceUrl = $parts['scheme'] . '://' . $parts['host'] . ($parts['path'] ?? '');
    }
    return [
      'source_url' => $safe($sourceUrl, 2048),
      'source_provenance' => $safe($row['source_provenance'], 255),
      'source_timeframe' => $safe($row['source_timeframe'], 128),
      'website_observation' => [
        'status' => $safe($row['website_observation_status'], 32),
        'fact' => $safe($row['website_observation_fact'], 600),
      ],
      'corroborated_fact' => $safe($row['corroborated_fact'], 1200),
      'proof_teaser' => $safe($row['proof_teaser'], 600),
      'evidence_hash' => strtolower(trim((string) $row['evidence_hash'])),
    ];
  }

  /**
   * @return array{campaign_id:int,campaign_public_id:string,prospect_id:int,artifact_hashes:list<string>,asset_hashes:list<string>,variants:list<array{direction_id:string,direction_name:string,artifact_path:string,artifact_hash:string,assets:list<array{asset_id:string,relative_path:string,media_type:string,sha256:string,size_bytes:int,artifact_path:string}>}>}
   */
  private function assertCompleteProofCampaign(int $campaignId, int $prospectId, array $profile): array {
    $campaign = $this->entities->getStorage('proof_campaign')->load($campaignId);
    if (!$campaign instanceof ProofCampaign || (int) $campaign->get('prospect_id')->target_id !== $prospectId || $campaign->get('generation_status')->value !== 'ready') {
      throw new \RuntimeException('A ready proof campaign for this public lead is required.');
    }
    $variants = $this->variants($campaignId);
    $directions = array_map(static fn ($variant): string => (string) $variant->get('direction_id')->value, $variants);
    sort($directions);
    if ($directions !== array_keys($profile['directions'])) {
      throw new \RuntimeException('Public concept rooms require exactly the directions frozen in their cohort profile.');
    }
    $hashes = [];
    $assetHashes = [];
    $snapshot = [];
    $requiresSignedAssets = $this->sourceLaneForCampaign($prospectId, $campaignId) === 'verified_cold';
    foreach ($variants as $variant) {
      $direction = (string) $variant->get('direction_id')->value;
      $dna = json_decode((string) $variant->get('design_dna')->value, TRUE);
      if (is_array($dna) && in_array((string) ($dna['source'] ?? ''), ['no_image_pilot_v1', 'fixture', 'mock'], TRUE)) {
        throw new \RuntimeException('Public concept rooms cannot stage pilot, fixture, or mock proof variants.');
      }
      $evidence = $this->proofArtifactEvidence($variant);
      $assets = $this->proofAssetEvidence($variant, (string) $campaign->get('campaign_id')->value, $direction);
      if ($requiresSignedAssets && $assets === []) {
        throw new \RuntimeException('Verified-cold public proof directions require at least one signed visual asset.');
      }
      $hashes[] = $evidence['artifact_hash'];
      foreach ($assets as $asset) {
        $hashes[] = $asset['sha256'];
        $assetHashes[] = $asset['sha256'];
      }
      $snapshot[] = [
        'direction_id' => $direction,
        'direction_name' => mb_substr(trim(strip_tags((string) $variant->get('direction_name')->value)), 0, 255) ?: $direction,
        'artifact_path' => $evidence['artifact_path'],
        'artifact_hash' => $evidence['artifact_hash'],
        'assets' => $assets,
      ];
    }
    return [
      'campaign_id' => (int) $campaign->id(),
      'campaign_public_id' => (string) $campaign->get('campaign_id')->value,
      'prospect_id' => $prospectId,
      'artifact_hashes' => $hashes,
      'asset_hashes' => $assetHashes,
      'variants' => $snapshot,
    ];
  }

  /** Requires the immutable Drupal projection before a public handoff. */
  private function assertRegisteredBuildDna(string $buildDnaId, string $buildDnaHash, array $campaignEvidence, string $researchEvidenceHash = '', string $researchEvidenceRole = '', string $sourceLane = 'anonymous_public'): void {
    $row = $this->database->select('famtastic_build_run', 'b')->fields('b', ['artifact_checksum', 'output_manifest', 'prospect_id', 'proof_campaign_id'])
      ->condition('build_key', 'build-dna:' . $buildDnaId)->range(0, 1)->execute()->fetchAssoc();
    if (!$row || !hash_equals((string) $row['artifact_checksum'], $buildDnaHash)) {
      throw new \RuntimeException('The matching immutable Build DNA projection must be registered before public preview staging.');
    }
    if ((int) ($row['prospect_id'] ?? 0) !== $campaignEvidence['prospect_id'] || (int) ($row['proof_campaign_id'] ?? 0) !== $campaignEvidence['campaign_id']) {
      throw new \RuntimeException('The Build DNA projection must be registered for this exact public prospect and proof campaign.');
    }
    $manifest = $this->decodeBuildDnaManifest((string) $row['output_manifest']);
    $run = is_array($manifest['run'] ?? NULL) ? $manifest['run'] : [];
    if (!hash_equals($campaignEvidence['campaign_public_id'], trim((string) ($run['campaign_id'] ?? '')))) {
      throw new \RuntimeException('The Build DNA run must name this exact public proof campaign.');
    }
    if ($sourceLane === 'verified_cold' && !hash_equals('verified_cold', (string) ($run['source_lane'] ?? ''))) {
      throw new \RuntimeException('Verified-cold public proof staging requires Build DNA run.source_lane=verified_cold.');
    }
    foreach ($campaignEvidence['artifact_hashes'] as $artifactHash) {
      if (!$this->manifestContainsArtifact($manifest, $artifactHash)) {
        throw new \RuntimeException('Every served public proof artifact must be present in the registered Build DNA manifest.');
      }
    }
    // The verified cold lane is deliberately quality-bearing: every direction
    // must carry frozen media, and every served media hash must be present in
    // the immutable Build DNA evidence. Existing assetless public rooms remain
    // readable until they are deliberately replaced on the new lane.
    if (strtolower(trim((string) ($run['source_lane'] ?? ''))) === 'verified_cold') {
      foreach ($campaignEvidence['variants'] as $variant) {
        if (empty($variant['assets'])) {
          throw new \RuntimeException('Verified cold public proofs require at least one signed asset in every direction.');
        }
      }
      foreach ($campaignEvidence['asset_hashes'] as $assetHash) {
        if (!$this->manifestContainsArtifact($manifest, $assetHash)) {
          throw new \RuntimeException('Every signed cold-proof asset must be present in the registered Build DNA manifest.');
        }
      }
    }
    if ($researchEvidenceHash !== '' && !$this->manifestContainsArtifact($manifest, $researchEvidenceHash, $researchEvidenceRole)) {
      throw new \RuntimeException('The research teaser must reference an exact research-role artifact hash recorded in the registered Build DNA.');
    }
  }

  /**
   * Produces an immutable, customer-safe research snapshot for one invitation.
   *
   * The optional evidence hash is intentionally separate from the text hash:
   * the former must point at a source artifact inside Build DNA, while the
   * latter detects later changes to the frozen email/room snapshot.
   */
  private function normalizeResearch(array $research): array {
    $clean = static function (mixed $value, int $maximum): string {
      $value = preg_replace('/\s+/u', ' ', trim(strip_tags((string) $value))) ?? '';
      return mb_substr($value, 0, $maximum);
    };
    $result = [
      'public_context' => $clean($research['public_context'] ?? '', 1200),
      'teaser' => $clean($research['teaser'] ?? '', 1600),
      'sources' => $clean($research['sources'] ?? '', 6000),
      'report' => $clean($research['report'] ?? '', 16000),
      'evidence_hash' => strtolower(trim((string) ($research['evidence_hash'] ?? ''))),
      'evidence_role' => strtolower(trim((string) ($research['evidence_role'] ?? ''))),
    ];
    $hasResearch = $result['teaser'] !== '' || $result['sources'] !== '' || $result['report'] !== '';
    if ($hasResearch && ($result['teaser'] === '' || $result['sources'] === '')) {
      throw new \InvalidArgumentException('A research-backed public preview requires both a bounded teaser and a source summary.');
    }
    if ($hasResearch && (preg_match('/^[a-f0-9]{64}$/', $result['evidence_hash']) !== 1 || preg_match('/^[a-z0-9_.-]{3,128}$/', $result['evidence_role']) !== 1 || !str_contains($result['evidence_role'], 'research'))) {
      throw new \InvalidArgumentException('A research-backed public preview requires the exact SHA-256 and a research-role identifier from its Build DNA artifact.');
    }
    if (!$hasResearch && ($result['evidence_hash'] !== '' || $result['evidence_role'] !== '')) {
      throw new \InvalidArgumentException('Research evidence may be supplied only with a research teaser or report.');
    }
    $result['snapshot_hash'] = hash('sha256', json_encode([
      'public_context' => $result['public_context'],
      'teaser' => $result['teaser'],
      'sources' => $result['sources'],
      'report' => $result['report'],
    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    return $result;
  }

  private function decodeBuildDnaManifest(string $manifest): array {
    try {
      $decoded = json_decode($manifest, TRUE, 512, JSON_THROW_ON_ERROR);
      if (!is_array($decoded)) {
        throw new \RuntimeException('The registered Build DNA manifest is not an object.');
      }
      return $decoded;
    }
    catch (\Throwable) {
      throw new \RuntimeException('The registered Build DNA manifest is unreadable.');
    }
  }

  private function manifestContainsArtifact(array $manifest, string $hash, string $role = ''): bool {
    foreach ((array) ($manifest['artifacts'] ?? []) as $artifact) {
      if (is_array($artifact) && isset($artifact['sha256']) && hash_equals($hash, (string) $artifact['sha256']) && ($role === '' || (isset($artifact['role']) && hash_equals($role, strtolower((string) $artifact['role']))))) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /** Calculates an immutable served-artifact evidence record inside web/proofs. */
  private function proofArtifactEvidence(object $variant): array {
    $stored = (string) $variant->get('artifact_path')->value;
    $path = str_starts_with($stored, '/') ? $stored : dirname(\Drupal::root()) . '/' . ltrim($stored, '/');
    $real = realpath($path);
    $root = realpath(\Drupal::root() . '/proofs');
    if (!$real || !$root || !str_starts_with($real, $root . DIRECTORY_SEPARATOR) || !is_file($real)) {
      throw new \RuntimeException('A public proof artifact is unavailable for Build DNA verification.');
    }
    $hash = hash_file('sha256', $real);
    if ($hash === FALSE) {
      throw new \RuntimeException('A public proof artifact could not be hashed for Build DNA verification.');
    }
    return ['artifact_path' => $stored, 'artifact_hash' => $hash];
  }

  /**
   * Rehashes the precise protected image files named by direction DNA.
   *
   * @return list<array{asset_id:string,relative_path:string,media_type:string,sha256:string,size_bytes:int,artifact_path:string}>
   */
  private function proofAssetEvidence(object $variant, string $campaignId, string $direction): array {
    $dna = json_decode((string) $variant->get('design_dna')->value, TRUE);
    if (!is_array($dna)) {
      throw new \RuntimeException('A public proof asset manifest is unreadable.');
    }
    try {
      $assets = ProofAssetContract::normalizeStoredManifest($dna['asset_manifest'] ?? []);
    }
    catch (\InvalidArgumentException) {
      throw new \RuntimeException('A public proof asset manifest is unsafe.');
    }
    $root = realpath(\Drupal::root() . '/proofs');
    if (!$root) {
      throw new \RuntimeException('Protected proof asset storage is unavailable.');
    }
    foreach ($assets as &$asset) {
      $expected = ProofAssetContract::artifactPath($campaignId, $direction, $asset['relative_path']);
      if (!hash_equals($expected, $asset['artifact_path'])) {
        throw new \RuntimeException('A public proof asset is not stored at its declared protected path.');
      }
      $path = dirname(\Drupal::root()) . '/' . $asset['artifact_path'];
      $real = realpath($path);
      $expectedRoot = $root . DIRECTORY_SEPARATOR . $campaignId . DIRECTORY_SEPARATOR . $direction . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR;
      if (!$real || !str_starts_with($real, $expectedRoot) || !is_file($real)) {
        throw new \RuntimeException('A public proof asset is unavailable for Build DNA verification.');
      }
      $hash = hash_file('sha256', $real);
      $size = filesize($real);
      if ($hash === FALSE || $size === FALSE || !hash_equals($asset['sha256'], $hash) || (int) $size !== (int) $asset['size_bytes']) {
        throw new \RuntimeException('A public proof asset no longer matches its frozen manifest.');
      }
    }
    unset($asset);
    return $assets;
  }

  /** Decodes and validates the exact staged concept-room artifact set. */
  private function snapshotVariants(array $row, array $profile): array {
    try {
      $decoded = json_decode((string) ($row['proof_variant_snapshot'] ?? ''), TRUE, 16, JSON_THROW_ON_ERROR);
    }
    catch (\Throwable) {
      return [];
    }
    if (!is_array($decoded) || count($decoded) !== $profile['direction_count']) {
      return [];
    }
    $valid = [];
    foreach ($decoded as $variant) {
      if (!is_array($variant)) return [];
      $direction = strtolower((string) ($variant['direction_id'] ?? ''));
      $name = mb_substr(trim(strip_tags((string) ($variant['direction_name'] ?? ''))), 0, 255);
      $path = (string) ($variant['artifact_path'] ?? '');
      $hash = strtolower((string) ($variant['artifact_hash'] ?? ''));
      if (!array_key_exists($direction, $profile['directions']) || isset($valid[$direction]) || $name === '' || $path === '' || preg_match('/^[a-f0-9]{64}$/', $hash) !== 1) {
        return [];
      }
      try {
        $assets = ProofAssetContract::normalizeStoredManifest($variant['assets'] ?? []);
      }
      catch (\InvalidArgumentException) {
        return [];
      }
      $valid[$direction] = [
        'direction_id' => $direction,
        'direction_name' => $name,
        'artifact_path' => $path,
        'artifact_hash' => $hash,
        'assets' => $assets,
      ];
    }
    ksort($valid);
    return array_keys($valid) === array_keys($profile['directions']) ? array_values($valid) : [];
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

  private function invitationBody(string $business, string $shareUrl, string $registrationUrl, array $research, int $proofCount): string {
    $researchNote = '';
    if ($research['teaser'] !== '') {
      $researchNote = "\n\nA short research note from this early review (not a statement of your current operations):\n{$research['teaser']}\n\nSources reviewed:\n{$research['sources']}";
    }
    $countLabel = strtolower($this->proofCountLabel($proofCount));
    return "Hi,\n\nWe prepared {$countLabel} exploratory website directions for {$business}. They are a starting point from safe public context and the general details available so far—not a final site scope or a statement of current services, facts, partners, outcomes, or availability.{$researchNote}\n\nView your private concept room:\n{$shareUrl}\n\nCreate your free FAMtastic Designs workspace with this same email to save this work, add the current pages, audiences, assets, integrations, references, and style preferences that should guide the next refinement.\n\nCreate your free workspace:\n{$registrationUrl}\n\nThe concept room is private and review-only. Nothing is published, selected, priced, or purchased from it.\n\nFAMtastic Concierge\nFAMtastic Designs\nhttps://famtasticdesigns.com";
  }

  private function proofCountLabel(int $count): string {
    return match ($count) {
      1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five', 6 => 'Six',
      default => (string) $count,
    };
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
