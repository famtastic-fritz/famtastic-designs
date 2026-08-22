<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;

/**
 * Owns immutable, selected-direction proof revision lineage.
 *
 * A revision is not a mutation of a proof_variant. It snapshots the currently
 * visible selected direction, queues a one-direction runner job, then stores a
 * separate candidate until an authorized owner explicitly makes it visible.
 */
final class ProofRevisionService {

  public const JOB_TYPE = 'proof.revision.generate';
  public const ROUTINE = 'website_proof.generate.v1';
  public const PROOF_PHASE = 'revision';
  public const PROFILE_ID = 'portal_selected_direction_revision.v1';

  private const ALLOWED_DIRECTIONS = ['a', 'b', 'c', 'd', 'e', 'f'];
  private const OPEN_STATUSES = ['requested', 'queued', 'waiting_callback', 'owner_review'];

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entities,
    private readonly FileSystemInterface $fileSystem,
    private readonly TimeInterface $time,
    private readonly UuidInterface $uuid,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly OperationalLedger $ledger,
    private readonly BuildTelemetryService $telemetry,
  ) {}

  /**
   * Creates an account-owned, source-bound revision request and queues it.
   *
   * The caller must already have enforced account ownership. This service still
   * reloads the request and validates its refined-six lineage before it writes.
   */
  public function request(array $request, string $notes): array {
    $requestId = (int) ($request['id'] ?? 0);
    $current = $this->requestRow($requestId);
    if (!$current || (int) ($current['customer_id'] ?? 0) !== (int) ($request['customer_id'] ?? 0)) {
      throw new \RuntimeException('Website request is not available for a proof revision.');
    }
    $notes = $this->normalizeNotes($notes);
    if ($notes === '') {
      throw new \InvalidArgumentException('Tell us what you want adjusted.');
    }
    $direction = strtolower(trim((string) $current['selected_proof_direction']));
    if (!in_array($direction, self::ALLOWED_DIRECTIONS, TRUE)) {
      throw new \RuntimeException('Choose one refined proof direction before requesting changes.');
    }
    $notesHash = hash('sha256', $notes);
    $attemptedKey = '';

    $open = $this->openRevision($requestId, $direction);
    if ($open) {
      if (hash_equals((string) $open['notes_sha256'], $notesHash)) {
        return $open;
      }
      throw new \RuntimeException('A requested proof change is already awaiting review. Wait for that version before sending another change request.');
    }

    // Resolve an already-open revision before inspecting the lifecycle state:
    // the first accepted request deliberately changes it to
    // revision_requested, and a browser retry must remain idempotent.
    $this->assertEligibleRequest($current);

    $transaction = $this->database->startTransaction();
    try {
      // Re-check after beginning the transaction. The unique revision key is
      // the final concurrent-writer guard on database engines without a
      // portable SELECT ... FOR UPDATE abstraction here.
      $open = $this->openRevision($requestId, $direction);
      if ($open) {
        if (hash_equals((string) $open['notes_sha256'], $notesHash)) {
          return $open;
        }
        throw new \RuntimeException('A requested proof change is already awaiting review.');
      }
      $number = $this->nextRevisionNumber($requestId, $direction);
      $baseline = $this->baselineFor($current, $direction);
      $now = $this->time->getRequestTime();
      $publicId = $this->uuid->generate();
      $revisionKey = $this->revisionKey($requestId, $direction, $number);
      $attemptedKey = $revisionKey;

      $revisionId = (int) $this->database->insert('famtastic_proof_revision')->fields([
        'public_id' => $publicId,
        'revision_key' => $revisionKey,
        'website_request_id' => $requestId,
        'organization_id' => (int) $current['organization_id'],
        'customer_id' => (int) $current['customer_id'],
        'prospect_id' => (int) $current['prospect_id'] ?: NULL,
        'proof_campaign_id' => (int) $current['proof_campaign_id'],
        'direction_id' => $direction,
        'revision_number' => $number,
        'parent_revision_id' => $baseline['parent_revision_id'],
        'status' => 'requested',
        'notes' => $notes,
        'notes_sha256' => $notesHash,
        'baseline_variant_id' => $baseline['variant_id'],
        'baseline_artifact_sha256' => $baseline['artifact_sha256'],
        'baseline_design_dna_sha256' => $baseline['design_dna_sha256'],
        'baseline_build_dna_id' => $baseline['build_dna_id'],
        'baseline_build_dna_hash' => $baseline['build_dna_hash'],
        'requested_at' => $now,
        'created' => $now,
        'changed' => $now,
      ])->execute();

      $baselineArtifactId = $this->recordArtifact($revisionId, 'baseline', $baseline, $publicId, $number, 'historical');
      $this->database->update('famtastic_proof_revision')->fields([
        'baseline_artifact_id' => $baselineArtifactId,
        'changed' => $now,
      ])->condition('id', $revisionId)->execute();

      $jobKey = 'proof-revision:' . $publicId;
      $payload = $this->jobPayload($current, $revisionId, $publicId, $number, $direction, $notes, $notesHash, $baselineArtifactId, $baseline);
      $jobId = $this->ledger->enqueue(
        $jobKey,
        self::JOB_TYPE,
        $payload,
        (int) $current['prospect_id'] ?: NULL,
      );
      $this->database->update('famtastic_proof_revision')->fields([
        'status' => 'queued',
        'runner_job_id' => $jobId,
        'runner_job_key' => $jobKey,
        'changed' => $now,
      ])->condition('id', $revisionId)->execute();
      $requestUpdated = $this->database->update('famtastic_project_request')->fields([
        'proof_review_status' => 'revision_requested',
        'changed' => $now,
      ])->condition('id', $requestId)->condition('proof_review_status', 'selected')->execute();
      if ($requestUpdated !== 1) {
        throw new \RuntimeException('The selected proof changed before the revision could be queued.');
      }

      $this->queueRequestedNotifications($current, $publicId, $number, $direction, $notes);
      $this->activity((int) $current['organization_id'], 'website_request.proof_revision_requested', 'Your selected proof direction was saved as revision ' . $number . ' and is being prepared for owner review.');
      $this->ledger->recordEvent(
        'proof.revision.requested:' . $publicId,
        'proof.revision.requested',
        [
          'revision_public_id' => $publicId,
          'website_request_id' => $requestId,
          'proof_campaign_id' => (int) $current['proof_campaign_id'],
          'direction_id' => $direction,
          'revision_number' => $number,
          'notes_sha256' => $notesHash,
          'baseline_artifact_sha256' => $baseline['artifact_sha256'],
          'baseline_build_dna_id' => $baseline['build_dna_id'],
        ],
        (int) $current['prospect_id'] ?: NULL,
        (int) $current['proof_campaign_id'],
      );
      return $this->revisionRow($revisionId) ?? throw new \RuntimeException('Proof revision was not saved.');
    }
    catch (\Throwable $error) {
      unset($transaction);
      if ($this->isDuplicateKey($error)) {
        $existing = $attemptedKey === '' ? NULL : $this->revisionByKey($attemptedKey);
        if ($existing && hash_equals((string) $existing['notes_sha256'], $notesHash)) {
          return $existing;
        }
      }
      throw $error;
    }
  }

  /**
   * Binds an already-queued revision to an actual provider dispatch.
   *
   * This is deliberately separate from request() so a queued local work item
   * cannot be mistaken for an external provider call.
   */
  public function markRunnerDispatched(string $revisionPublicId, string $providerJobId, string $buildId, string $contractSha256): array {
    $revision = $this->revisionByPublicId($revisionPublicId);
    if (!$revision) {
      throw new \InvalidArgumentException('Proof revision is unknown.');
    }
    $providerJobId = trim($providerJobId);
    $buildId = trim($buildId);
    $contractSha256 = strtolower(trim($contractSha256));
    if ($providerJobId === '' || $buildId === '' || !preg_match('/^[a-f0-9]{64}$/', $contractSha256)) {
      throw new \InvalidArgumentException('A revision dispatch needs a provider job id, Build DNA id, and contract checksum.');
    }
    if ($revision['status'] === 'waiting_callback') {
      if (hash_equals((string) $revision['provider_job_id'], $providerJobId)
        && hash_equals((string) $revision['runner_build_id'], $buildId)
        && hash_equals((string) $revision['runner_contract_sha256'], $contractSha256)) {
        return $revision;
      }
      throw new \RuntimeException('Revision is already bound to another provider dispatch.');
    }
    if (!in_array((string) $revision['status'], ['requested', 'queued'], TRUE)) {
      throw new \RuntimeException('Only a queued revision can be dispatched.');
    }
    $now = $this->time->getRequestTime();
    $this->database->update('famtastic_proof_revision')->fields([
      'status' => 'waiting_callback',
      'provider_job_id' => $providerJobId,
      'runner_build_id' => $buildId,
      'runner_contract_sha256' => $contractSha256,
      'dispatched_at' => $now,
      'changed' => $now,
    ])->condition('id', (int) $revision['id'])->condition('status', ['requested', 'queued'], 'IN')->execute();
    $this->ledger->recordEvent(
      'proof.revision.dispatched:' . (string) $revision['public_id'],
      'proof.revision.dispatched',
      [
        'revision_public_id' => (string) $revision['public_id'],
        'provider_job_id' => $providerJobId,
        'build_id' => $buildId,
        'contract_sha256' => $contractSha256,
      ],
      (int) $revision['prospect_id'] ?: NULL,
      (int) $revision['proof_campaign_id'],
    );
    return $this->revisionByPublicId($revisionPublicId) ?? throw new \RuntimeException('Revision dispatch state was not saved.');
  }

  /**
   * Stores one signed-callback candidate after normal runner verification.
   *
   * The normal callback verifier owns provider/Build-DNA validation. This
   * method repeats only the correlation checks required to keep a verified
   * completion from replacing another request, direction, or revision.
   */
  public function acceptVerifiedCandidate(
    string $revisionPublicId,
    string $eventId,
    string $providerJobId,
    array $variant,
    array $runnerVerification,
  ): array {
    $revision = $this->revisionByPublicId($revisionPublicId);
    if (!$revision) {
      throw new \InvalidArgumentException('Proof revision is unknown.');
    }
    $eventId = trim($eventId);
    if ($eventId === '' || strlen($eventId) > 255) {
      throw new \InvalidArgumentException('Revision callback event_id is required.');
    }
    if (in_array((string) $revision['status'], ['owner_review', 'customer_ready'], TRUE)) {
      if (hash_equals((string) $revision['callback_event_id'], $eventId) && !empty($revision['candidate_artifact_id'])) {
        return $revision;
      }
      throw new \RuntimeException('Proof revision already has a different completion callback.');
    }
    if ((string) $revision['status'] !== 'waiting_callback') {
      throw new \RuntimeException('Proof revision is not waiting for a provider callback.');
    }
    if (!hash_equals((string) $revision['provider_job_id'], trim($providerJobId))) {
      throw new \InvalidArgumentException('Revision callback provider job does not match the dispatched revision.');
    }
    $this->assertVerifiedCorrelation($revision, $runnerVerification);
    $candidate = $this->normalizeCandidate($variant, (string) $revision['direction_id']);
    $now = $this->time->getRequestTime();
    $transaction = $this->database->startTransaction();
    try {
      $artifact = $this->writeCandidateArtifact($revision, $candidate);
      $artifact['build_dna_hash'] = (string) $runnerVerification['build_dna_hash'];
      $artifactId = $this->recordArtifact((int) $revision['id'], 'candidate', $artifact, (string) $revision['public_id'], (int) $revision['revision_number'], 'owner_review', (int) $revision['baseline_artifact_id']);
      $this->database->update('famtastic_proof_revision')->fields([
        'status' => 'owner_review',
        'callback_event_id' => $eventId,
        'candidate_artifact_id' => $artifactId,
        'candidate_build_dna_id' => (string) $runnerVerification['build_id'],
        'candidate_build_dna_hash' => (string) $runnerVerification['build_dna_hash'],
        'completed_at' => $now,
        'changed' => $now,
      ])->condition('id', (int) $revision['id'])->condition('status', 'waiting_callback')->execute();
      $saved = $this->revisionRow((int) $revision['id']);
      if (!$saved || (string) $saved['status'] !== 'owner_review') {
        throw new \RuntimeException('Revision callback lost its owner-review gate.');
      }
      $request = $this->requestRow((int) $revision['website_request_id']);
      if (!$request) {
        throw new \RuntimeException('Revision website request no longer exists.');
      }
      $this->queueCandidateNotifications($request, $saved);
      $this->activity((int) $revision['organization_id'], 'website_request.proof_revision_owner_review', 'A revised proof direction is ready for FAMtastic owner review.');
      $this->ledger->recordEvent(
        'proof.revision.callback_accepted:' . (string) $revision['public_id'] . ':' . $eventId,
        'proof.revision.callback_accepted',
        [
          'revision_public_id' => (string) $revision['public_id'],
          'event_id' => $eventId,
          'direction_id' => (string) $revision['direction_id'],
          'revision_number' => (int) $revision['revision_number'],
          'artifact_sha256' => $artifact['artifact_sha256'],
          'build_id' => (string) $runnerVerification['build_id'],
          'build_dna_hash' => (string) $runnerVerification['build_dna_hash'],
        ],
        (int) $revision['prospect_id'] ?: NULL,
        (int) $revision['proof_campaign_id'],
      );
      return $saved;
    }
    catch (\Throwable $error) {
      unset($transaction);
      throw $error;
    }
  }

  /** Approves one reviewed candidate and makes it eligible for customer view. */
  public function approveRevision(int $revisionId, int $uid): array {
    if ($uid < 1) {
      throw new \InvalidArgumentException('An authenticated proof reviewer is required.');
    }
    $revision = $this->revisionRow($revisionId);
    if (!$revision) {
      throw new \InvalidArgumentException('Proof revision is unknown.');
    }
    if ((string) $revision['status'] === 'customer_ready') {
      return $revision;
    }
    if ((string) $revision['status'] !== 'owner_review' || empty($revision['candidate_artifact_id'])) {
      throw new \RuntimeException('Proof revision is not awaiting owner approval.');
    }
    $request = $this->requestRow((int) $revision['website_request_id']);
    if (!$request || (string) $request['selected_proof_direction'] !== (string) $revision['direction_id']) {
      throw new \RuntimeException('The selected proof direction changed before this revision could be approved.');
    }
    $candidate = $this->artifactRow((int) $revision['candidate_artifact_id']);
    if (!$candidate || (string) $candidate['artifact_role'] !== 'candidate') {
      throw new \RuntimeException('Revision candidate artifact is unavailable.');
    }
    $this->assertStoredArtifact($candidate);
    $now = $this->time->getRequestTime();
    $transaction = $this->database->startTransaction();
    try {
      $updated = $this->database->update('famtastic_proof_revision')->fields([
        'status' => 'customer_ready',
        'owner_approved_by_uid' => $uid,
        'owner_approved_at' => $now,
        'customer_visible_at' => $now,
        'changed' => $now,
      ])->condition('id', $revisionId)->condition('status', 'owner_review')->execute();
      if ($updated !== 1) {
        throw new \RuntimeException('Revision approval state changed before it could be saved.');
      }
      $artifactUpdated = $this->database->update('famtastic_proof_revision_artifact')->fields([
        'visibility' => 'customer_visible',
      ])->condition('id', (int) $candidate['id'])->condition('visibility', 'owner_review')->execute();
      if ($artifactUpdated !== 1) {
        throw new \RuntimeException('Revision candidate visibility changed before approval could be saved.');
      }
      // Keep the already selected direction selected. This never initiates a
      // checkout; it merely lets the customer inspect the approved replacement
      // and choose their next commercial action separately.
      $requestUpdated = $this->database->update('famtastic_project_request')->fields([
        'proof_review_status' => 'selected',
        'changed' => $now,
      ])->condition('id', (int) $request['id'])->condition('proof_review_status', 'revision_requested')->execute();
      if ($requestUpdated !== 1) {
        throw new \RuntimeException('Website request state changed before the revision approval could be saved.');
      }
      $saved = $this->revisionRow($revisionId);
      if (!$saved || (string) $saved['status'] !== 'customer_ready') {
        throw new \RuntimeException('Revision approval did not preserve the customer gate.');
      }
      $this->queueApprovedNotification($request, $saved);
      $this->activity((int) $revision['organization_id'], 'website_request.proof_revision_approved', 'A revised proof direction passed FAMtastic review and is ready in your workspace.');
      $this->ledger->recordEvent(
        'proof.revision.owner_approved:' . (string) $revision['public_id'],
        'proof.revision.owner_approved',
        [
          'revision_public_id' => (string) $revision['public_id'],
          'direction_id' => (string) $revision['direction_id'],
          'revision_number' => (int) $revision['revision_number'],
          'reviewer_uid' => $uid,
          'candidate_artifact_sha256' => (string) $candidate['artifact_sha256'],
        ],
        (int) $revision['prospect_id'] ?: NULL,
        (int) $revision['proof_campaign_id'],
      );
      return $saved;
    }
    catch (\Throwable $error) {
      unset($transaction);
      throw $error;
    }
  }

  /** Returns the latest customer-safe revision state for an owned request. */
  public function customerSummary(int $requestId): ?array {
    $row = $this->database->select('famtastic_proof_revision', 'r')->fields('r', [
      'public_id', 'direction_id', 'revision_number', 'status', 'notes',
      'notes_sha256', 'requested_at', 'completed_at', 'customer_visible_at',
    ])->condition('website_request_id', $requestId)->orderBy('id', 'DESC')->range(0, 1)->execute()->fetchAssoc();
    if (!$row) {
      return NULL;
    }
    return [
      'public_id' => (string) $row['public_id'],
      'direction_id' => (string) $row['direction_id'],
      'version' => (int) $row['revision_number'],
      'status' => (string) $row['status'],
      'notes' => (string) $row['notes'],
      'notes_sha256' => (string) $row['notes_sha256'],
      'requested_at' => (int) $row['requested_at'],
      'completed_at' => $row['completed_at'] === NULL ? NULL : (int) $row['completed_at'],
      'customer_visible_at' => $row['customer_visible_at'] === NULL ? NULL : (int) $row['customer_visible_at'],
    ];
  }

  /** Returns the latest owner-review revision for the staff proof screen. */
  public function ownerPendingForRequest(int $requestId): ?array {
    $row = $this->database->select('famtastic_proof_revision', 'r')->fields('r')
      ->condition('website_request_id', $requestId)->condition('status', 'owner_review')
      ->orderBy('revision_number', 'DESC')->range(0, 1)->execute()->fetchAssoc();
    return $row ?: NULL;
  }

  /**
   * Resolves the active revision artifact for one proof route.
   *
   * Owner previews may see the candidate during owner_review. Authenticated and
   * unlisted customer routes can only see a candidate after owner approval.
   */
  public function activeArtifactForRequest(int $requestId, string $direction, bool $ownerPreview = FALSE): ?array {
    $direction = strtolower(trim($direction));
    if (!in_array($direction, self::ALLOWED_DIRECTIONS, TRUE)) {
      return NULL;
    }
    if ($ownerPreview) {
      $pending = $this->database->select('famtastic_proof_revision', 'r')->fields('r', ['candidate_artifact_id'])
        ->condition('website_request_id', $requestId)->condition('direction_id', $direction)->condition('status', 'owner_review')
        ->orderBy('revision_number', 'DESC')->range(0, 1)->execute()->fetchAssoc();
      if ($pending && !empty($pending['candidate_artifact_id'])) {
        $artifact = $this->artifactRow((int) $pending['candidate_artifact_id']);
        if ($artifact && (string) $artifact['visibility'] === 'owner_review') {
          return $artifact;
        }
      }
    }
    $visible = $this->database->select('famtastic_proof_revision', 'r')->fields('r', ['candidate_artifact_id'])
      ->condition('website_request_id', $requestId)->condition('direction_id', $direction)->condition('status', 'customer_ready')
      ->orderBy('revision_number', 'DESC')->range(0, 1)->execute()->fetchAssoc();
    if (!$visible || empty($visible['candidate_artifact_id'])) {
      return NULL;
    }
    $artifact = $this->artifactRow((int) $visible['candidate_artifact_id']);
    return $artifact && (string) $artifact['visibility'] === 'customer_visible' ? $artifact : NULL;
  }

  /** Validates and normalizes customer-provided change notes. */
  private function normalizeNotes(string $notes): string {
    return mb_substr(trim(strip_tags($notes)), 0, 5000);
  }

  /** Ensures the request is an account-owned, completed refined-six set. */
  private function assertEligibleRequest(array $request): void {
    if ((string) ($request['proof_review_status'] ?? '') !== 'selected') {
      throw new \RuntimeException('Select one approved refined proof before requesting changes.');
    }
    if ((string) ($request['proof_phase'] ?? '') !== 'refined_six'
      || (string) ($request['proof_profile_id'] ?? '') !== 'portal_refined_six.v1'
      || (int) ($request['source_preview_delivery_id'] ?? 0) < 1) {
      throw new \RuntimeException('Only a completed account-owned six-direction refinement can be revised here.');
    }
    $direction = strtolower((string) ($request['selected_proof_direction'] ?? ''));
    if (!in_array($direction, self::ALLOWED_DIRECTIONS, TRUE) || empty($request['proof_campaign_id'])) {
      throw new \RuntimeException('Choose one refined proof direction before requesting changes.');
    }
    $campaign = $this->entities->getStorage('proof_campaign')->load((int) $request['proof_campaign_id']);
    if (!$campaign || (string) $campaign->get('generation_status')->value !== 'ready') {
      throw new \RuntimeException('The selected proof campaign is not complete.');
    }
    $ids = $this->entities->getStorage('proof_variant')->getQuery()->accessCheck(FALSE)
      ->condition('campaign_id', (int) $request['proof_campaign_id'])->sort('direction_id')->execute();
    $directions = array_map(
      static fn(object $variant): string => (string) $variant->get('direction_id')->value,
      array_values($this->entities->getStorage('proof_variant')->loadMultiple($ids)),
    );
    if ($directions !== ['a', 'b', 'c', 'd', 'e', 'f']) {
      throw new \RuntimeException('A complete six-direction refined proof set is required before revision.');
    }
  }

  /** Finds the baseline artifact, preferring the latest approved revision. */
  private function baselineFor(array $request, string $direction): array {
    $latest = $this->database->select('famtastic_proof_revision', 'r')->fields('r')
      ->condition('website_request_id', (int) $request['id'])->condition('direction_id', $direction)
      ->condition('status', 'customer_ready')->orderBy('revision_number', 'DESC')->range(0, 1)->execute()->fetchAssoc();
    if ($latest && !empty($latest['candidate_artifact_id'])) {
      $artifact = $this->artifactRow((int) $latest['candidate_artifact_id']);
      if (!$artifact || (string) $artifact['visibility'] !== 'customer_visible') {
        throw new \RuntimeException('The previous approved revision artifact is unavailable for lineage.');
      }
      $this->assertStoredArtifact($artifact);
      return [
        'source_type' => 'proof_revision_artifact',
        'source_record_id' => (int) $artifact['id'],
        'parent_artifact_id' => (int) $artifact['id'],
        'parent_revision_id' => (int) $latest['id'],
        'variant_id' => NULL,
        'direction_id' => $direction,
        'artifact_path' => (string) $artifact['artifact_path'],
        'preview_url' => (string) $artifact['preview_url'],
        'thumbnail_path' => (string) $artifact['thumbnail_path'],
        'artifact_sha256' => (string) $artifact['artifact_sha256'],
        'thumbnail_sha256' => (string) $artifact['thumbnail_sha256'],
        'design_dna' => (string) ($artifact['design_dna'] ?? ''),
        'design_dna_sha256' => (string) $artifact['design_dna_sha256'],
        'build_dna_id' => (string) $artifact['build_dna_id'],
        'build_dna_hash' => (string) $artifact['build_dna_hash'],
      ];
    }

    $ids = $this->entities->getStorage('proof_variant')->getQuery()->accessCheck(FALSE)
      ->condition('campaign_id', (int) $request['proof_campaign_id'])->condition('direction_id', $direction)->range(0, 1)->execute();
    $variant = $ids ? $this->entities->getStorage('proof_variant')->load(reset($ids)) : NULL;
    if (!$variant) {
      throw new \RuntimeException('Selected proof artifact is unavailable.');
    }
    $path = (string) $variant->get('artifact_path')->value;
    $artifactSha = $this->hashStoredArtifact($path);
    $design = (string) $variant->get('design_dna')->value;
    $build = $this->telemetry->loadBuildDnaForCampaign((int) $request['proof_campaign_id']);
    if (!$build || (string) ($build['record']['status'] ?? '') !== 'completed') {
      throw new \RuntimeException('The selected proof lacks a completed Build DNA record.');
    }
    $manifest = (array) $build['manifest'];
    $run = (array) ($manifest['run'] ?? []);
    $source = (array) ($run['source_correlation'] ?? []);
    if ((string) ($manifest['schema'] ?? '') !== 'famtastic.build-dna.v1'
      || (string) ($manifest['classification'] ?? '') !== 'production_proof_completion'
      || (string) ($manifest['recipe']['routine'] ?? '') !== self::ROUTINE
      || (string) ($manifest['recipe']['profile_id'] ?? '') !== 'portal_refined_six.v1'
      || !in_array(strtolower((string) ($run['status'] ?? '')), ['passed', 'complete', 'completed'], TRUE)
      || (string) ($run['completion_state'] ?? '') !== 'provider_completed'
      || (string) ($source['website_request_id'] ?? '') !== (string) $request['id']
      || (string) ($source['website_request_public_id'] ?? '') !== (string) $request['public_id']
      || (string) ($source['proof_phase'] ?? '') !== 'refined_six') {
      throw new \RuntimeException('The selected proof Build DNA does not match this completed refined request.');
    }
    $buildId = trim((string) ($manifest['build_id'] ?? ''));
    if ($buildId === '') {
      throw new \RuntimeException('The selected proof Build DNA record has no build id.');
    }
    return [
      'source_type' => 'proof_variant',
      'source_record_id' => (int) $variant->id(),
      'parent_artifact_id' => NULL,
      'parent_revision_id' => NULL,
      'variant_id' => (int) $variant->id(),
      'direction_id' => $direction,
      'artifact_path' => $path,
      'preview_url' => (string) $variant->get('preview_url')->value,
      'thumbnail_path' => (string) $variant->get('thumbnail_path')->value,
      'artifact_sha256' => $artifactSha,
      'thumbnail_sha256' => '',
      'design_dna' => $design,
      'design_dna_sha256' => $design === '' ? '' : hash('sha256', $design),
      'build_dna_id' => $buildId,
      'build_dna_hash' => $this->buildDnaHash($manifest),
    ];
  }

  /** Builds the intentionally one-direction runner envelope. */
  private function jobPayload(array $request, int $revisionId, string $revisionPublicId, int $number, string $direction, string $notes, string $notesHash, int $baselineArtifactId, array $baseline): array {
    return [
      'routine' => self::ROUTINE,
      'delivery_class' => 'authenticated_revision',
      'proof_phase' => self::PROOF_PHASE,
      'requested_profile_id' => self::PROFILE_ID,
      'website_request_id' => (int) $request['id'],
      'website_request_public_id' => (string) $request['public_id'],
      'proof_campaign_id' => (int) $request['proof_campaign_id'],
      'revision_id' => $revisionId,
      'revision_public_id' => $revisionPublicId,
      'revision_number' => $number,
      'selected_direction' => $direction,
      'direction_id' => $direction,
      'proof_count' => 1,
      'direction_contract' => [
        $direction => [
          'name' => 'Selected direction revision',
          'intent' => 'Refine only the selected direction using the stored customer notes. Do not alter, regenerate, or replace any other direction.',
        ],
      ],
      'revision_notes' => $notes,
      'revision_notes_sha256' => $notesHash,
      'baseline' => [
        'artifact_id' => $baselineArtifactId,
        'artifact_path' => $baseline['artifact_path'],
        'artifact_sha256' => $baseline['artifact_sha256'],
        'design_dna_sha256' => $baseline['design_dna_sha256'],
        'build_dna_id' => $baseline['build_dna_id'],
        'build_dna_hash' => $baseline['build_dna_hash'],
      ],
      'source_correlation' => [
        'website_request_id' => (int) $request['id'],
        'website_request_public_id' => (string) $request['public_id'],
        'proof_campaign_id' => (int) $request['proof_campaign_id'],
        'revision_public_id' => $revisionPublicId,
        'revision_number' => $number,
        'selected_direction' => $direction,
        'direction_id' => $direction,
      ],
      'customer_visibility' => 'owner_review_only',
      'owner_review_required_before_customer_visibility' => TRUE,
      'commercial_mutations_allowed' => FALSE,
    ];
  }

  /** Verifies that the normal callback verifier bound this completion to us. */
  private function assertVerifiedCorrelation(array $revision, array $verification): void {
    if ((string) ($verification['status'] ?? '') !== 'verified') {
      throw new \InvalidArgumentException('Revision completion requires a verified proof-runner callback.');
    }
    if (!hash_equals((string) $revision['runner_build_id'], (string) ($verification['build_id'] ?? ''))
      || !preg_match('/^[a-f0-9]{64}$/', (string) ($verification['build_dna_hash'] ?? ''))) {
      throw new \InvalidArgumentException('Revision callback Build DNA does not match the dispatched revision.');
    }
    if ((string) ($verification['profile_id'] ?? '') !== self::PROFILE_ID
      || (string) ($verification['proof_phase'] ?? '') !== self::PROOF_PHASE) {
      throw new \InvalidArgumentException('Revision callback profile or proof phase is not the canonical selected-direction revision profile.');
    }
    $source = (array) ($verification['source_correlation'] ?? []);
    $expected = [
      'website_request_id' => (int) $revision['website_request_id'],
      'proof_campaign_id' => (int) $revision['proof_campaign_id'],
      'revision_public_id' => (string) $revision['public_id'],
      'revision_number' => (int) $revision['revision_number'],
      'selected_direction' => (string) $revision['direction_id'],
      'direction_id' => (string) $revision['direction_id'],
    ];
    $request = $this->requestRow((int) $revision['website_request_id']);
    if (!$request) {
      throw new \RuntimeException('Revision website request no longer exists.');
    }
    $expected['website_request_public_id'] = (string) $request['public_id'];
    foreach ($expected as $key => $value) {
      if ((string) ($source[$key] ?? '') !== (string) $value) {
        throw new \InvalidArgumentException('Revision callback source correlation differs at ' . $key . '.');
      }
    }
    $registered = $this->telemetry->loadBuildDna((string) $verification['build_id']);
    if (!$registered || (string) ($registered['record']['status'] ?? '') !== 'completed') {
      throw new \InvalidArgumentException('Revision callback Build DNA has not been registered as a completed run.');
    }
    $manifest = (array) $registered['manifest'];
    $run = (array) ($manifest['run'] ?? []);
    if ((string) ($manifest['recipe']['routine'] ?? '') !== self::ROUTINE
      || (string) ($manifest['recipe']['profile_id'] ?? '') !== self::PROFILE_ID
      || (string) ($manifest['schema'] ?? '') !== 'famtastic.build-dna.v1'
      || (string) ($manifest['classification'] ?? '') !== 'production_proof_completion'
      || !in_array(strtolower((string) ($run['status'] ?? '')), ['passed', 'complete', 'completed'], TRUE)
      || (string) ($run['completion_state'] ?? '') !== 'provider_completed'
      || !hash_equals((string) $verification['build_dna_hash'], $this->buildDnaHash($manifest))) {
      throw new \InvalidArgumentException('Revision callback Build DNA receipt does not match the registered completion.');
    }
  }

  /** Normalizes one and only one selected-direction callback artifact. */
  private function normalizeCandidate(array $variant, string $expectedDirection): array {
    $direction = strtolower(trim((string) ($variant['direction_id'] ?? '')));
    $html = (string) ($variant['html'] ?? '');
    $declaredHash = strtolower(trim((string) ($variant['artifact_sha256'] ?? '')));
    if ($direction !== $expectedDirection || $html === '' || strlen($html) > 2 * 1024 * 1024
      || !preg_match('/^[a-f0-9]{64}$/', $declaredHash) || !hash_equals($declaredHash, hash('sha256', $html))) {
      throw new \InvalidArgumentException('Revision callback must contain exactly the selected direction with matching HTML bytes.');
    }
    $design = $variant['design_dna'] ?? [];
    if (!is_array($design)) {
      throw new \InvalidArgumentException('Revision callback design metadata must be structured.');
    }
    $designJson = json_encode($design, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $thumbnail = $this->normalizeThumbnail($variant);
    return [
      'source_type' => 'proof_runner_callback',
      'source_record_id' => NULL,
      'parent_artifact_id' => NULL,
      'parent_revision_id' => NULL,
      'variant_id' => NULL,
      'direction_id' => $direction,
      'html' => $html,
      'artifact_sha256' => $declaredHash,
      'design_dna' => $designJson,
      'design_dna_sha256' => hash('sha256', $designJson),
      'thumbnail_binary' => $thumbnail['binary'],
      'thumbnail_extension' => $thumbnail['extension'],
      'thumbnail_sha256' => $thumbnail['sha256'],
    ];
  }

  /** Writes a candidate into an isolated revision/version directory. */
  private function writeCandidateArtifact(array $revision, array $candidate): array {
    $campaign = $this->entities->getStorage('proof_campaign')->load((int) $revision['proof_campaign_id']);
    if (!$campaign) {
      throw new \RuntimeException('Proof campaign is unavailable for revision storage.');
    }
    $campaignId = preg_replace('/[^a-zA-Z0-9_-]/', '-', (string) $campaign->get('campaign_id')->value) ?: 'campaign';
    $revisionId = preg_replace('/[^a-f0-9-]/', '', (string) $revision['public_id']);
    $direction = (string) $revision['direction_id'];
    $relativeDirectory = 'web/proofs/' . $campaignId . '/revisions/' . $revisionId . '/v' . (int) $revision['revision_number'] . '/' . $direction;
    $directory = dirname(\Drupal::root()) . '/' . $relativeDirectory;
    if (!$this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
      throw new \RuntimeException('Unable to create revision artifact storage.');
    }
    $path = $directory . '/index.html';
    if (is_file($path)) {
      if (!hash_equals((string) $candidate['artifact_sha256'], hash_file('sha256', $path))) {
        throw new \RuntimeException('Revision artifact path already contains different bytes.');
      }
    }
    elseif ($this->fileSystem->saveData((string) $candidate['html'], $path, FileSystemInterface::EXISTS_ERROR) === FALSE) {
      throw new \RuntimeException('Unable to persist revision artifact.');
    }
    $thumbnailPath = '';
    if ($candidate['thumbnail_binary'] !== NULL) {
      $thumbnailFile = 'thumbnail.' . $candidate['thumbnail_extension'];
      $thumbnailAbsolute = $directory . '/' . $thumbnailFile;
      if (is_file($thumbnailAbsolute)) {
        if (!hash_equals((string) $candidate['thumbnail_sha256'], hash_file('sha256', $thumbnailAbsolute))) {
          throw new \RuntimeException('Revision thumbnail path already contains different bytes.');
        }
      }
      elseif ($this->fileSystem->saveData((string) $candidate['thumbnail_binary'], $thumbnailAbsolute, FileSystemInterface::EXISTS_ERROR) === FALSE) {
        throw new \RuntimeException('Unable to persist revision thumbnail.');
      }
      $thumbnailPath = '/' . preg_replace('/^web\//', '', $relativeDirectory) . '/' . $thumbnailFile;
    }
    return [
      'source_type' => (string) $candidate['source_type'],
      'source_record_id' => NULL,
      'parent_artifact_id' => NULL,
      'direction_id' => $direction,
      'artifact_path' => $relativeDirectory . '/index.html',
      'preview_url' => '',
      'thumbnail_path' => $thumbnailPath,
      'artifact_sha256' => (string) $candidate['artifact_sha256'],
      'thumbnail_sha256' => (string) $candidate['thumbnail_sha256'],
      'design_dna' => (string) $candidate['design_dna'],
      'design_dna_sha256' => (string) $candidate['design_dna_sha256'],
      'build_dna_id' => (string) $revision['runner_build_id'],
      'build_dna_hash' => '',
    ];
  }

  /** Parses a constrained optional callback thumbnail. */
  private function normalizeThumbnail(array $variant): array {
    $encoded = (string) ($variant['thumbnail_base64'] ?? '');
    if ($encoded === '') {
      return ['binary' => NULL, 'extension' => '', 'sha256' => ''];
    }
    $mediaType = strtolower(trim((string) ($variant['thumbnail_media_type'] ?? '')));
    $extensions = ['image/png' => 'png', 'image/jpeg' => 'jpg'];
    if (!isset($extensions[$mediaType])) {
      throw new \InvalidArgumentException('Revision thumbnail must be PNG or JPEG.');
    }
    $binary = base64_decode($encoded, TRUE);
    if ($binary === FALSE || $binary === '' || strlen($binary) > 2 * 1024 * 1024) {
      throw new \InvalidArgumentException('Revision thumbnail bytes are invalid.');
    }
    return ['binary' => $binary, 'extension' => $extensions[$mediaType], 'sha256' => hash('sha256', $binary)];
  }

  /** Creates a separate, immutable artifact row. */
  private function recordArtifact(int $revisionId, string $role, array $artifact, string $revisionPublicId, int $number, string $visibility, ?int $parentArtifactId = NULL): int {
    $artifactKey = $revisionPublicId . ':' . $role;
    $existing = $this->database->select('famtastic_proof_revision_artifact', 'a')->fields('a', ['id'])
      ->condition('artifact_key', $artifactKey)->range(0, 1)->execute()->fetchField();
    if ($existing) {
      return (int) $existing;
    }
    return (int) $this->database->insert('famtastic_proof_revision_artifact')->fields([
      'artifact_key' => $artifactKey,
      'revision_id' => $revisionId,
      'artifact_role' => $role,
      'source_type' => (string) ($artifact['source_type'] ?? ''),
      'source_record_id' => $artifact['source_record_id'] ?? NULL,
      'parent_artifact_id' => $parentArtifactId ?? ($artifact['parent_artifact_id'] ?? NULL),
      'direction_id' => (string) $artifact['direction_id'],
      'version_number' => $number,
      'artifact_path' => (string) $artifact['artifact_path'],
      'preview_url' => (string) ($artifact['preview_url'] ?? ''),
      'thumbnail_path' => (string) ($artifact['thumbnail_path'] ?? ''),
      'artifact_sha256' => (string) $artifact['artifact_sha256'],
      'thumbnail_sha256' => (string) ($artifact['thumbnail_sha256'] ?? ''),
      'design_dna' => (string) ($artifact['design_dna'] ?? ''),
      'design_dna_sha256' => (string) ($artifact['design_dna_sha256'] ?? ''),
      'build_dna_id' => (string) ($artifact['build_dna_id'] ?? ''),
      'build_dna_hash' => (string) ($artifact['build_dna_hash'] ?? ''),
      'visibility' => $visibility,
      'created' => $this->time->getRequestTime(),
    ])->execute();
  }

  /** Queues request receipts without sending mail or exposing candidate links. */
  private function queueRequestedNotifications(array $request, string $revisionPublicId, int $number, string $direction, string $notes): void {
    $owner = $this->ownerEmail();
    $reviewUrl = 'https://famtasticdesigns.com/web/admin/famtastic/website-request/' . (int) $request['id'] . '/proof-review';
    $this->queueNotification(
      'proof-revision:' . $revisionPublicId . ':owner-requested',
      'operational',
      $owner,
      'Website proof revision requested — ' . (string) $request['project_name'],
      "A client requested revision {$number} for selected direction {$direction}.\n\nClient notes:\n{$notes}\n\nReview: {$reviewUrl}",
    );
    if ($email = $this->customerEmail((int) $request['customer_id'])) {
      $this->queueNotification(
        'proof-revision:' . $revisionPublicId . ':customer-received',
        'transactional',
        $email,
        'We received your website proof changes',
        'Your requested changes were saved. FAMtastic will prepare a replacement for your selected direction and review it before it becomes visible in your workspace.',
      );
    }
  }

  /** Queues review-state notifications after the verified candidate is stored. */
  private function queueCandidateNotifications(array $request, array $revision): void {
    $reviewUrl = 'https://famtasticdesigns.com/web/admin/famtastic/website-request/' . (int) $request['id'] . '/proof-review';
    $publicId = (string) $revision['public_id'];
    $this->queueNotification(
      'proof-revision:' . $publicId . ':owner-candidate-review',
      'operational',
      $this->ownerEmail(),
      'Revised website proof needs owner approval — ' . (string) $request['project_name'],
      'A new version of selected direction ' . (string) $revision['direction_id'] . ' is ready for owner review. Nothing has been shown to the customer. Review: ' . $reviewUrl,
    );
    if ($email = $this->customerEmail((int) $request['customer_id'])) {
      $this->queueNotification(
        'proof-revision:' . $publicId . ':customer-quality-review',
        'transactional',
        $email,
        'Your requested website update is in review',
        'FAMtastic completed the requested update and is reviewing the new version before it appears in your workspace.',
      );
    }
  }

  /** Queues the only customer-facing candidate-ready message after approval. */
  private function queueApprovedNotification(array $request, array $revision): void {
    $email = $this->customerEmail((int) $request['customer_id']);
    if (!$email) {
      return;
    }
    $base = rtrim((string) $this->configFactory->get('famtastic_pipeline.settings')->get('frontend_base_url'), '/');
    $url = $base . '/portal/?section=projects&request=' . rawurlencode((string) $request['public_id']);
    $this->queueNotification(
      'proof-revision:' . (string) $revision['public_id'] . ':customer-approved',
      'transactional',
      $email,
      'Your revised website proof is ready',
      'FAMtastic reviewed your updated selected direction. Sign in to compare the approved revision and choose your next step: ' . $url,
    );
  }

  /** Adds a durable outbox record only; a separate worker owns actual sends. */
  private function queueNotification(string $key, string $category, string $recipient, string $subject, string $body): void {
    $now = $this->time->getRequestTime();
    $this->database->merge('famtastic_notification_outbox')->key('notification_key', $key)->insertFields([
      'notification_key' => $key,
      'category' => $category,
      'recipient' => mb_strtolower(trim($recipient)),
      'subject' => $subject,
      'body' => $body,
      'status' => 'queued',
      'attempts' => 0,
      'max_attempts' => 5,
      'available_at' => $now,
      'created' => $now,
      'changed' => $now,
    ])->execute();
  }

  private function ownerEmail(): string {
    return (string) ($this->configFactory->get('famtastic_pipeline.settings')->get('notification_to_email') ?: 'fitzgerald.medine@gmail.com');
  }

  private function customerEmail(int $customerId): ?string {
    $email = $this->database->select('famtastic_customer', 'c')->fields('c', ['email'])
      ->condition('id', $customerId)->range(0, 1)->execute()->fetchField();
    $email = mb_strtolower(trim((string) $email));
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : NULL;
  }

  private function activity(int $organizationId, string $type, string $summary): void {
    $this->database->insert('famtastic_portal_activity')->fields([
      'organization_id' => $organizationId,
      'event_type' => $type,
      'summary' => $summary,
      'created' => $this->time->getRequestTime(),
    ])->execute();
  }

  private function requestRow(int $requestId): ?array {
    if ($requestId < 1) {
      return NULL;
    }
    $row = $this->database->select('famtastic_project_request', 'r')->fields('r')
      ->condition('id', $requestId)->range(0, 1)->execute()->fetchAssoc();
    return $row ?: NULL;
  }

  private function revisionRow(int $revisionId): ?array {
    $row = $this->database->select('famtastic_proof_revision', 'r')->fields('r')
      ->condition('id', $revisionId)->range(0, 1)->execute()->fetchAssoc();
    return $row ?: NULL;
  }

  private function revisionByPublicId(string $publicId): ?array {
    $row = $this->database->select('famtastic_proof_revision', 'r')->fields('r')
      ->condition('public_id', $publicId)->range(0, 1)->execute()->fetchAssoc();
    return $row ?: NULL;
  }

  private function revisionByKey(string $key): ?array {
    $row = $this->database->select('famtastic_proof_revision', 'r')->fields('r')
      ->condition('revision_key', $key)->range(0, 1)->execute()->fetchAssoc();
    return $row ?: NULL;
  }

  private function artifactRow(int $artifactId): ?array {
    $row = $this->database->select('famtastic_proof_revision_artifact', 'a')->fields('a')
      ->condition('id', $artifactId)->range(0, 1)->execute()->fetchAssoc();
    return $row ?: NULL;
  }

  private function openRevision(int $requestId, string $direction): ?array {
    $row = $this->database->select('famtastic_proof_revision', 'r')->fields('r')
      ->condition('website_request_id', $requestId)->condition('direction_id', $direction)
      ->condition('status', self::OPEN_STATUSES, 'IN')->orderBy('revision_number', 'DESC')->range(0, 1)->execute()->fetchAssoc();
    return $row ?: NULL;
  }

  private function nextRevisionNumber(int $requestId, string $direction): int {
    $query = $this->database->select('famtastic_proof_revision', 'r');
    $query->addExpression('MAX(revision_number)', 'latest_number');
    $query->condition('website_request_id', $requestId)->condition('direction_id', $direction);
    return max(1, (int) $query->execute()->fetchField() + 1);
  }

  private function revisionKey(int $requestId, string $direction, int $number): string {
    return 'website-request:' . $requestId . ':direction:' . $direction . ':revision:' . $number;
  }

  private function hashStoredArtifact(string $stored): string {
    $path = $this->absoluteArtifactPath($stored);
    if (!is_file($path)) {
      throw new \RuntimeException('Selected proof artifact is unavailable for immutable revision lineage.');
    }
    return hash_file('sha256', $path) ?: throw new \RuntimeException('Selected proof artifact could not be hashed.');
  }

  private function assertStoredArtifact(array $artifact): void {
    $actual = $this->hashStoredArtifact((string) $artifact['artifact_path']);
    if (!hash_equals((string) $artifact['artifact_sha256'], $actual)) {
      throw new \RuntimeException('Stored revision artifact integrity check failed.');
    }
  }

  private function absoluteArtifactPath(string $stored): string {
    $path = str_starts_with($stored, '/') ? $stored : dirname(\Drupal::root()) . '/' . ltrim($stored, '/');
    $real = realpath($path);
    $root = realpath(\Drupal::root() . '/proofs');
    if (!$real || !$root || !str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
      throw new \RuntimeException('Proof artifact path is outside approved storage.');
    }
    return $real;
  }

  private function buildDnaHash(array $manifest): string {
    return hash('sha256', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
  }

  private function isDuplicateKey(\Throwable $error): bool {
    return str_contains(strtolower($error->getMessage()), 'duplicate')
      || str_contains(strtolower($error->getMessage()), 'unique constraint');
  }

}
