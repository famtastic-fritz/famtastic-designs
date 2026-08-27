<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Site\Settings;
use Drupal\famtastic_pipeline\Entity\ProofCampaign;
use Drupal\famtastic_pipeline\Entity\ProofVariant;
use Drupal\famtastic_pipeline\Entity\Prospect;
use Psr\Log\LoggerInterface;

/**
 * Creates core three-direction proofs and optional owner-gated showcase packs.
 *
 * On first creation the service generates three proof variants. When a Site
 * Studio URL is configured the generation request is handed off through the
 * module's Site Studio adapter interface. Otherwise a deliberately opt-in,
 * image-free pilot renderer writes three distinct static proof sites under
 * backend/web/proofs/<campaign_id>/<a|b|c>/index.html.
 */
class ProofCampaignService {

  /**
   * Direction id => human-facing direction name.
   */
  public const DIRECTIONS = [
    'a' => 'Safe',
    'b' => 'Wild',
    'c' => 'OMG',
    'd' => 'Royal Current',
    'e' => 'Crownverse',
    'f' => 'Shay Live',
  ];

  public const CORE_DIRECTIONS = [
    'a' => 'Safe',
    'b' => 'Wild',
    'c' => 'OMG',
  ];

  public const SHOWCASE_DIRECTIONS = [
    'd' => 'Royal Current',
    'e' => 'Crownverse',
    'f' => 'Shay Live',
  ];

  /**
   * Packages a prospect may choose with a variant.
   */
  public const PACKAGES = ['essential_199', 'business_499'];

  /**
   * Campaign lifetime in seconds (7 days).
   */
  protected const TTL = 7 * 24 * 60 * 60;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ConfigFactoryInterface $configFactory,
    protected TimeInterface $time,
    protected FileSystemInterface $fileSystem,
    protected SiteStudioAdapterInterface $studioAdapter,
    protected PipelineRepository $repository,
    protected LoggerInterface $logger,
    protected SiteStudioProofClient $studioClient,
    protected OperationalLedger $ledger,
    protected BuildTelemetryService $buildTelemetry,
    protected Connection $database,
    protected CustomerPortalService $portal,
    protected PublicPreviewDeliveryService $previews,
  ) {}

  /**
   * Creates a proof campaign with 3 variants for a prospect.
   *
   * Idempotency is enforced by the caller (controller returns any existing
   * active campaign first), so this always creates a fresh campaign.
   *
   * @return array{campaign:\Drupal\famtastic_pipeline\Entity\ProofCampaign,variants:\Drupal\famtastic_pipeline\Entity\ProofVariant[]}
   */
  public function createForProspect(Prospect $prospect, array $context = []): array {
    $businessName = (string) ($prospect->get('business_name')->value ?: 'Your Business');
    $campaignId = $this->buildCampaignId($businessName);
    $now = $this->time->getRequestTime();

    $allowPilot = getenv('FAMTASTIC_ALLOW_NO_IMAGE_PILOT_PROOFS') === '1'
      || getenv('FAMTASTIC_ALLOW_STUB_OUTREACH') === '1'
      || Settings::get('famtastic_allow_no_image_pilot_proofs', FALSE);
    $remote = $this->studioClient->isRemote();
    $publicPreviewDeliveryId = (int) ($context['public_preview_delivery_id'] ?? 0);
    $websiteRequestId = (int) ($context['website_request_id'] ?? 0);
    if ($publicPreviewDeliveryId && $websiteRequestId) {
      throw new \RuntimeException('A proof campaign cannot be both a public delivery and an account-owned website request.');
    }
    if ($publicPreviewDeliveryId && !$remote && $allowPilot) {
      throw new \RuntimeException('Public concept rooms require a real creative provider; image-free pilot generation is not permitted.');
    }
    $localJobId = !$remote && !$allowPilot ? 'local-' . bin2hex(random_bytes(16)) : '';
    $storage = $this->entityTypeManager->getStorage('proof_campaign');
    /** @var \Drupal\famtastic_pipeline\Entity\ProofCampaign $campaign */
    $campaign = $storage->create([
      'campaign_id' => $campaignId,
      'prospect_id' => $prospect->id(),
      'business_name' => $businessName,
      'status' => 'active',
      'generation_status' => $remote ? 'dispatching' : ($allowPilot ? 'ready' : 'waiting_callback'),
      'studio_job_id' => $localJobId,
      'expires_at' => $now + self::TTL,
    ]);
    $campaign->save();
    if ($publicPreviewDeliveryId) {
      // Bind before any remote dispatch so a callback cannot attach to a
      // different request/campaign owned by this same prospect.
      $this->previews->bindInitialProofCampaign($publicPreviewDeliveryId, (int) $campaign->id());
    }
    if ($websiteRequestId) {
      $this->portal->bindWebsiteRequestProofCampaign($websiteRequestId, (int) $prospect->id(), (int) $campaign->id());
    }

    if ($remote) {
      return $this->dispatchRemoteCampaign($prospect, $campaign, $context);
    }

    if (!$allowPilot) {
      $this->ledger->recordEvent(
        'proof.local_waiting:' . $campaignId,
        'proof.waiting_for_creative_provider',
        ['campaign_id' => $campaignId, 'studio_job_id' => $localJobId, 'routine' => (string) ($context['routine'] ?? 'website_proof.generate.v1')],
        (int) $prospect->id(),
      );
      return ['campaign' => $campaign, 'variants' => []];
    }

    $source = 'no_image_pilot_v1';

    $variants = [];
    $variantStorage = $this->entityTypeManager->getStorage('proof_variant');
    foreach (self::CORE_DIRECTIONS as $direction => $directionName) {
      $artifact = $this->writeStubArtifact($campaignId, $direction, $directionName, $businessName, $prospect, $source);
      $thumbnail = $this->writePilotThumbnail($campaignId, $direction, $directionName, $businessName);
      $dna = [
        'source' => $source,
        'direction' => $direction,
        'direction_name' => $directionName,
        'business_name' => $businessName,
        'palette' => $this->palette($direction),
        'typography' => $direction === 'b' ? 'Georgia serif headlines, system body' : 'System sans headlines, generous body',
        'layout' => $direction === 'c' ? 'Single column, warm and personal' : 'Hero-first landing with section blocks',
        'generated_at' => date(DATE_ATOM, $now),
      ];
      /** @var \Drupal\famtastic_pipeline\Entity\ProofVariant $variant */
      $variant = $variantStorage->create([
        'campaign_id' => $campaign->id(),
        'direction_id' => $direction,
        'direction_name' => $directionName,
        'artifact_path' => $artifact,
        'design_dna' => json_encode($dna, JSON_UNESCAPED_SLASHES),
        'thumbnail_path' => $thumbnail,
        'preview_url' => $this->previewUrl($campaignId, $direction),
      ]);
      $variant->save();
      $variants[] = $variant;
    }

    $this->logger->info('Proof campaign @cid created for prospect @p (@src).', [
      '@cid' => $campaignId,
      '@p' => $prospect->id(),
      '@src' => $source,
    ]);
    $campaign->set('ready_at', $now)->save();
    $this->buildTelemetry->recordPilotProof($prospect, $campaign, $variants);

    return ['campaign' => $campaign, 'variants' => $variants];
  }

  /**
   * Creates, binds, and persists an inert public-preview campaign before a
   * worker is allowed to invoke a creative provider.
   *
   * Cold ingress needs the exact Drupal entity ID and public campaign ID in
   * its durable job/Build-DNA contract. Creating the campaign only after a
   * generic worker claims the job leaves a runner with synthetic identifiers
   * that cannot be safely correlated with a callback or immutable evidence.
   * This method intentionally does not dispatch Site Studio, render a pilot,
   * create variants, or send anything.
   */
  public function createQueuedPublicPreviewCampaign(Prospect $prospect, array $context): ProofCampaign {
    $deliveryId = (int) ($context['public_preview_delivery_id'] ?? 0);
    if ($deliveryId < 1 || !empty($context['website_request_id'])) {
      throw new \InvalidArgumentException('A queued public-preview campaign requires one public delivery and no website request.');
    }
    $boundCampaignId = $this->previews->initialProofCampaignId($deliveryId);
    if ($boundCampaignId) {
      $bound = $this->getForId($prospect, $boundCampaignId);
      if (!$bound) {
        throw new \RuntimeException('The public delivery references an unavailable proof campaign.');
      }
      return $bound['campaign'];
    }

    $businessName = (string) ($prospect->get('business_name')->value ?: 'Your Business');
    $campaignId = $this->buildCampaignId($businessName);
    $now = $this->time->getRequestTime();
    /** @var \Drupal\famtastic_pipeline\Entity\ProofCampaign $campaign */
    $campaign = $this->entityTypeManager->getStorage('proof_campaign')->create([
      'campaign_id' => $campaignId,
      'prospect_id' => $prospect->id(),
      'business_name' => $businessName,
      'status' => 'active',
      // The handoff bundle carries this opaque callback identity. It is
      // created before any provider work, so a local/offline runner can never
      // invent an ID that Drupal cannot bind on callback.
      'generation_status' => 'waiting_callback',
      'studio_job_id' => 'cold-preview-' . bin2hex(random_bytes(16)),
      'expires_at' => $now + self::TTL,
    ]);
    $campaign->save();
    try {
      $this->previews->bindInitialProofCampaign($deliveryId, (int) $campaign->id());
    }
    catch (\Throwable $error) {
      // Do not leave an unbound identity that a later callback could target.
      $campaign->delete();
      throw $error;
    }
    $this->ledger->recordEvent(
      'proof.public_preview_queued:' . $campaignId,
      'proof.queued',
      [
        'campaign_id' => $campaignId,
        'proof_campaign_id' => (int) $campaign->id(),
        'public_preview_delivery_id' => $deliveryId,
        'source_lane' => (string) ($context['source_lane'] ?? 'anonymous_public'),
      ],
      (int) $prospect->id(),
    );
    return $campaign;
  }

  /**
   * Creates an idempotent waiting campaign for an offline/local Site Studio.
   */
  public function createLocalHandoff(Prospect $prospect): ProofCampaign {
    $existing = $this->getForProspect($prospect);
    if ($existing && $existing['campaign']->get('status')->value === 'active') {
      $campaign = $existing['campaign'];
      $jobId = (string) $campaign->get('studio_job_id')->value;
      if ($campaign->get('generation_status')->value === 'waiting_callback' && str_starts_with($jobId, 'local-')) {
        return $campaign;
      }
      throw new \RuntimeException('Prospect already has an active proof campaign. Expire or complete it before creating a local Site Studio handoff.');
    }

    $businessName = (string) ($prospect->get('business_name')->value ?: 'Your Business');
    $campaignId = $this->buildCampaignId($businessName);
    $jobId = 'local-' . bin2hex(random_bytes(16));
    $now = $this->time->getRequestTime();
    /** @var \Drupal\famtastic_pipeline\Entity\ProofCampaign $campaign */
    $campaign = $this->entityTypeManager->getStorage('proof_campaign')->create([
      'campaign_id' => $campaignId,
      'prospect_id' => $prospect->id(),
      'business_name' => $businessName,
      'status' => 'active',
      'generation_status' => 'waiting_callback',
      'studio_job_id' => $jobId,
      'dispatched_at' => $now,
      'expires_at' => $now + self::TTL,
    ]);
    $campaign->save();
    $this->ledger->recordEvent(
      'proof.local_exported:' . $campaignId,
      'proof.dispatched',
      ['campaign_id' => $campaignId, 'studio_job_id' => $jobId, 'transport' => 'offline_ssh_bundle'],
      (int) $prospect->id(),
    );
    return $campaign;
  }

  /**
   * Prepares an in-place local refresh while the current pilot stays public.
   */
  public function prepareLocalRefresh(Prospect $prospect, string $campaignId): ProofCampaign {
    $existing = $this->getForProspect($prospect);
    if (!$existing || !hash_equals((string) $existing['campaign']->get('campaign_id')->value, $campaignId)) {
      throw new \RuntimeException('The confirmed proof campaign is not the prospect current campaign.');
    }
    $campaign = $existing['campaign'];
    if ($campaign->get('status')->value !== 'active' || $campaign->get('generation_status')->value !== 'ready') {
      throw new \RuntimeException('Only an active, ready proof campaign can be refreshed.');
    }
    if (count($existing['variants']) !== 3) {
      throw new \RuntimeException('The current campaign does not contain exactly three proof variants.');
    }
    foreach ($existing['variants'] as $variant) {
      $dna = json_decode((string) $variant->get('design_dna')->value, TRUE);
      if (!is_array($dna) || ($dna['source'] ?? '') !== 'no_image_pilot_v1') {
        throw new \RuntimeException('Only a deterministic image-free pilot can be refreshed through this command.');
      }
    }
    $jobId = (string) $campaign->get('studio_job_id')->value;
    if (str_starts_with($jobId, 'local-refresh-')) {
      return $campaign;
    }
    $jobId = 'local-refresh-' . bin2hex(random_bytes(16));
    $campaign
      ->set('studio_job_id', $jobId)
      ->set('dispatched_at', $this->time->getRequestTime())
      ->save();
    $this->ledger->recordEvent(
      'proof.local_refresh_exported:' . $campaignId,
      'proof.refresh_dispatched',
      ['campaign_id' => $campaignId, 'studio_job_id' => $jobId, 'transport' => 'offline_ssh_bundle'],
      (int) $prospect->id(),
    );
    return $campaign;
  }

  /** Prepares an owner-gated three-direction FAMtastic showcase expansion. */
  public function prepareWebsiteRequestShowcase(int $requestId, string $publicId): ProofCampaign {
    $request = $this->database->select('famtastic_project_request', 'r')->fields('r')
      ->condition('id', $requestId)->execute()->fetchAssoc();
    if (!$request || !hash_equals((string) $request['public_id'], $publicId)) {
      throw new \RuntimeException('Showcase export requires the exact website request confirmation.');
    }
    if (!in_array($request['proof_review_status'], ['owner_review', 'showcase_building'], TRUE) || empty($request['proof_campaign_id'])) {
      throw new \RuntimeException('Only an owner-review proof set can receive a FAMtastic showcase expansion.');
    }
    $campaign = $this->entityTypeManager->getStorage('proof_campaign')->load((int) $request['proof_campaign_id']);
    if (!$campaign || $campaign->get('generation_status')->value !== 'ready') {
      throw new \RuntimeException('The attached proof campaign is not ready.');
    }
    $variants = $this->loadVariants($campaign);
    $directions = array_map(static fn(ProofVariant $variant): string => (string) $variant->get('direction_id')->value, $variants);
    sort($directions);
    if ($directions !== array_keys(self::CORE_DIRECTIONS)) {
      throw new \RuntimeException('The showcase expansion requires exactly the original Safe, Wild, and OMG set.');
    }
    $jobId = (string) $campaign->get('studio_job_id')->value;
    if (!str_starts_with($jobId, 'local-showcase-')) {
      $jobId = 'local-showcase-' . bin2hex(random_bytes(16));
      $campaign->set('studio_job_id', $jobId)->set('dispatched_at', $this->time->getRequestTime())->save();
      $this->ledger->recordEvent(
        'proof.showcase_exported:' . $campaign->get('campaign_id')->value,
        'proof.showcase_dispatched',
        ['campaign_id' => $campaign->get('campaign_id')->value, 'studio_job_id' => $jobId, 'website_request_id' => $requestId],
        (int) $campaign->get('prospect_id')->target_id,
      );
    }
    $this->database->update('famtastic_project_request')->fields([
      'proof_review_status' => 'showcase_building',
      'changed' => $this->time->getRequestTime(),
    ])->condition('id', $requestId)->condition('proof_review_status', ['owner_review', 'showcase_building'], 'IN')->execute();
    return $campaign;
  }

  /**
   * Accepts one idempotent non-verified-cold callback.
   *
   * Verified-cold proof artifacts have a separate, Build-DNA-backed import
   * transaction. Keeping this public generic entry point fail-closed prevents
   * a future CLI or HTTP caller from treating a valid callback payload as a
   * substitute for that immutable provenance record.
   */
  public function acceptCallback(string $eventId, string $campaignId, string $studioJobId, array $variants): array {
    return $this->acceptCallbackInternal($eventId, $campaignId, $studioJobId, $variants, FALSE);
  }

  /**
   * Atomically records Build DNA and accepts one exact verified-cold callback.
   *
   * This is intentionally the only service entry point that can complete a
   * verified-cold campaign. The private importer has already authenticated
   * its files, checksum, and HMAC; this service repeats the immutable
   * delivery/job/event/Build-DNA provenance checks so another caller cannot
   * bypass the transaction by calling the generic callback method.
   */
  public function acceptVerifiedColdCallback(string $eventId, string $campaignId, string $studioJobId, array $variants, array $buildDna): array {
    $transaction = $this->database->startTransaction();
    try {
      $this->assertVerifiedColdPrivateImportProvenance($eventId, $campaignId, $studioJobId, $buildDna);
      $buildRunId = $this->buildTelemetry->recordBuildDna($buildDna);
      $result = $this->acceptCallbackInternal($eventId, $campaignId, $studioJobId, $variants, TRUE);
    }
    catch (\Throwable $error) {
      $transaction->rollBack();
      throw $error;
    }
    unset($transaction);
    $result['build_run_id'] = $buildRunId;
    return $result;
  }

  /**
   * Shared callback implementation after the source-lane boundary is known.
   */
  private function acceptCallbackInternal(string $eventId, string $campaignId, string $studioJobId, array $variants, bool $verifiedColdPrivateImport): array {
    if ($eventId === '' || strlen($eventId) > 255) {
      throw new \InvalidArgumentException('callback event_id is required.');
    }
    $campaign = $this->loadByCampaignId($campaignId);
    if (!$campaign || !hash_equals((string) $campaign->get('studio_job_id')->value, $studioJobId)) {
      throw new \InvalidArgumentException('Unknown campaign or Site Studio job.');
    }
    $prospectId = (int) $campaign->get('prospect_id')->target_id;
    $isShowcase = str_starts_with($studioJobId, 'local-showcase-');
    $isPublicPreviewCampaign = !$isShowcase && $this->previews->isPublicDeliveryForCampaign($prospectId, (int) $campaign->id());
    $publicProfile = $isPublicPreviewCampaign
      ? $this->previews->proofProfileForCampaign($prospectId, (int) $campaign->id())
      : NULL;
    $publicSourceLane = $isPublicPreviewCampaign
      ? $this->previews->sourceLaneForCampaign($prospectId, (int) $campaign->id())
      : NULL;
    if ($isPublicPreviewCampaign && !$publicProfile) {
      throw new \RuntimeException('The public proof callback has no frozen delivery profile.');
    }
    $requiresSignedAssets = $publicSourceLane === 'verified_cold';
    if ($requiresSignedAssets && !$verifiedColdPrivateImport) {
      throw new \InvalidArgumentException('Verified-cold callbacks require the private Build DNA importer and cannot use the generic callback path.');
    }
    if ($requiresSignedAssets) {
      // The external builder may only complete the exact ingress-created
      // runtime binding. A fresh event ID or a substituted job ID would break
      // the immutable Build DNA correlation even if its HTML/assets look valid.
      $runtime = $this->previews->verifiedColdCallbackContractForCampaign($prospectId, (int) $campaign->id());
      if (!$runtime
        || !hash_equals((string) $runtime['job_id'], $studioJobId)
        || !hash_equals((string) $runtime['callback_event_id'], $eventId)
      ) {
        throw new \InvalidArgumentException('Verified-cold callback does not match its ingress-frozen job and event identity.');
      }
    }
    $request = NULL;
    if (!$isPublicPreviewCampaign) {
      // New request jobs bind their campaign before remote dispatch. Prefer
      // that exact owner relation over the unsafe legacy "latest prospect"
      // heuristic; retain the latter only for already-created legacy jobs.
      $request = $this->database->select('famtastic_project_request', 'r')->fields('r')
        ->condition('prospect_id', $prospectId)
        ->condition('proof_campaign_id', (int) $campaign->id())
        ->orderBy('changed', 'DESC')->range(0, 1)->execute()->fetchAssoc() ?: NULL;
      if (!$request) {
        $legacyQuery = $this->database->select('famtastic_project_request', 'r')->fields('r')
          ->condition('prospect_id', $prospectId);
        if ($isShowcase) {
          $legacyQuery->condition('proof_campaign_id', (int) $campaign->id());
        }
        $request = $legacyQuery->orderBy('changed', 'DESC')->range(0, 1)->execute()->fetchAssoc() ?: NULL;
      }
    }
    $processed = json_decode((string) $campaign->get('callback_event_ids')->value ?: '[]', TRUE);
    if (in_array($eventId, (array) $processed, TRUE)) {
      $existingVariants = $this->loadVariants($campaign);
      // Callback event acceptance can precede filesystem protection. A retry
      // must finish both its telemetry projection and bounded post-processing
      // rather than becoming a no-op.
      $this->recordCallbackTelemetry($campaign, $existingVariants, $isShowcase);
      $this->finalizeCallbackDelivery($campaign, $request, $existingVariants);
      return ['newly_processed' => FALSE, 'campaign' => $campaign, 'variants' => $existingVariants];
    }
    $requiredDirections = $isShowcase
      ? self::SHOWCASE_DIRECTIONS
      : ($publicProfile ? array_map(static fn (array $definition): string => (string) $definition['name'], $publicProfile['directions']) : self::CORE_DIRECTIONS);
    if (count($variants) !== count($requiredDirections)) {
      throw new \InvalidArgumentException(sprintf('Exactly %d variants are required for this proof set.', count($requiredDirections)));
    }
    $validated = [];
    foreach ($variants as $variant) {
      if (!is_array($variant)) {
        throw new \InvalidArgumentException('Each variant must be an object.');
      }
      $direction = strtolower((string) ($variant['direction_id'] ?? ''));
      $html = (string) ($variant['html'] ?? '');
      if (!array_key_exists($direction, $requiredDirections) || isset($validated[$direction])) {
        throw new \InvalidArgumentException('Variants must contain the unique directions required by this proof set.');
      }
      if ($html === '' || strlen($html) > 500000) {
        throw new \InvalidArgumentException('Each proof HTML artifact is required and limited to 500 KB.');
      }
      if (preg_match('/<(script|iframe|object|embed|base)\b|\son[a-z]+\s*=|javascript\s*:/i', $html)) {
        throw new \InvalidArgumentException('Proof HTML contains disallowed active content.');
      }
      $assets = ProofAssetContract::normalizeCallbackAssets($variant['assets'] ?? NULL);
      $thumbnail = NULL;
      $thumbnailBase64 = (string) ($variant['thumbnail_base64'] ?? '');
      if ($thumbnailBase64 !== '') {
        $mediaType = strtolower((string) ($variant['thumbnail_media_type'] ?? ''));
        if (!in_array($mediaType, ['image/jpeg', 'image/png'], TRUE)) {
          throw new \InvalidArgumentException('Proof thumbnail must be JPEG or PNG.');
        }
        $thumbnail = base64_decode($thumbnailBase64, TRUE);
        if ($thumbnail === FALSE || strlen($thumbnail) > 1500000) {
          throw new \InvalidArgumentException('Proof thumbnail is invalid or exceeds 1.5 MB.');
        }
        $validSignature = $mediaType === 'image/png'
          ? str_starts_with($thumbnail, "\x89PNG\r\n\x1a\n")
          : str_starts_with($thumbnail, "\xff\xd8\xff");
        if (!$validSignature) {
          throw new \InvalidArgumentException('Proof thumbnail bytes do not match the declared media type.');
        }
      }
      if ($requiresSignedAssets && $assets === []) {
        throw new \InvalidArgumentException('Verified-cold proof directions require at least one signed visual asset.');
      }
      $validated[$direction] = [
        'html' => $html,
        'thumbnail' => $thumbnail,
        'thumbnail_extension' => (($variant['thumbnail_media_type'] ?? '') === 'image/png') ? 'png' : 'jpg',
        'design_dna' => is_array($variant['design_dna'] ?? NULL) ? $variant['design_dna'] : [],
        'assets' => $assets,
      ];
    }
    if (array_keys($validated) !== array_keys($requiredDirections)) {
      ksort($validated);
    }
    if (array_keys($validated) !== array_keys($requiredDirections)) {
      throw new \InvalidArgumentException('Proof directions do not match the requested proof set.');
    }
    $currentVariants = $this->loadVariants($campaign);
    $isRefresh = str_starts_with($studioJobId, 'local-refresh-');
    if ($currentVariants && !$isRefresh && !$isShowcase) {
      throw new \InvalidArgumentException('Campaign already has proof artifacts.');
    }
    if ($isShowcase) {
      $currentDirections = array_map(static fn(ProofVariant $variant): string => (string) $variant->get('direction_id')->value, $currentVariants);
      sort($currentDirections);
      if (!$request || $request['proof_review_status'] !== 'showcase_building' || (int) $request['proof_campaign_id'] !== (int) $campaign->id() || $currentDirections !== array_keys(self::CORE_DIRECTIONS)) {
        throw new \InvalidArgumentException('A showcase expansion requires one matching account-owned Safe, Wild, and OMG set.');
      }
    }
    if ($isRefresh) {
      if (count($currentVariants) !== 3) {
        throw new \InvalidArgumentException('A local refresh requires exactly three existing pilot artifacts.');
      }
      foreach ($currentVariants as $currentVariant) {
        $dna = json_decode((string) $currentVariant->get('design_dna')->value, TRUE);
        if (!is_array($dna) || ($dna['source'] ?? '') !== 'no_image_pilot_v1') {
          throw new \InvalidArgumentException('Only deterministic pilot artifacts may be refreshed in place.');
        }
      }
    }
    $storage = $this->entityTypeManager->getStorage('proof_variant');
    $currentByDirection = [];
    foreach ($currentVariants as $currentVariant) {
      $currentByDirection[(string) $currentVariant->get('direction_id')->value] = $currentVariant;
    }
    $created = [];
    foreach ($validated as $direction => $variant) {
      $path = $this->writeCallbackArtifact($campaignId, $direction, $variant['html']);
      $assetManifest = $this->writeCallbackAssets($campaignId, $direction, $variant['assets']);
      $thumbnailPath = $variant['thumbnail'] === NULL
        ? NULL
        : $this->writeCallbackThumbnail($campaignId, $direction, $variant['thumbnail'], $variant['thumbnail_extension']);
      $entity = $currentByDirection[$direction] ?? $storage->create([
        'campaign_id' => $campaign->id(),
        'direction_id' => $direction,
      ]);
      $fallbackDirectionName = (string) ($requiredDirections[$direction] ?? self::DIRECTIONS[$direction]);
      $directionName = mb_substr(trim(strip_tags((string) ($variant['design_dna']['direction_name'] ?? $fallbackDirectionName))), 0, 255);
      $designDna = $variant['design_dna'];
      // The stored manifest is generated from validated bytes and the exact
      // protected artifact locations. Never trust an upstream DNA asset list.
      $designDna['asset_manifest'] = $assetManifest;
      $designDna['source_lane'] = $publicSourceLane ?: ($designDna['source_lane'] ?? '');
      $entity
        ->set('direction_name', $directionName ?: $fallbackDirectionName)
        ->set('artifact_path', $path)
        ->set('thumbnail_path', $thumbnailPath)
        ->set('design_dna', json_encode($designDna, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))
        ->set('preview_url', $request
          ? '/web/admin/famtastic/website-request/' . (int) $request['id'] . '/proof/' . $direction
          : $this->previewUrl($campaignId, $direction));
      $entity->save();
      $created[] = $entity;
    }
    $processed[] = $eventId;
    $campaign
      ->set('callback_event_ids', json_encode(array_values($processed), JSON_THROW_ON_ERROR))
      ->set('generation_status', 'ready')
      ->set('ready_at', $this->time->getRequestTime())
      ->save();
    $completeVariants = $isShowcase ? $this->loadVariants($campaign) : $created;
    $this->ledger->recordEvent(
      'proof.callback:' . $eventId,
      'proof.ready',
      ['campaign_id' => $campaignId, 'studio_job_id' => $studioJobId, 'variant_count' => count($completeVariants), 'refresh' => $isRefresh, 'showcase' => $isShowcase, 'public_cohort' => $publicProfile ? $publicProfile['id'] : '', 'source_lane' => $publicSourceLane ?: ''],
      $prospectId,
    );
    $this->finalizeCallbackDelivery($campaign, $request, $completeVariants);
    $this->recordCallbackTelemetry($campaign, $completeVariants, $isShowcase);
    return ['newly_processed' => TRUE, 'campaign' => $campaign, 'variants' => $completeVariants];
  }

  /**
   * Returns TRUE only for a callback campaign bound to the verified-cold lane.
   *
   * The generic HTTP callback must not accept this lane, even with a valid
   * HMAC: verified-cold needs the private importer to atomically register the
   * matching Build DNA record and its immutable callback binding first.
   */
  public function isVerifiedColdCampaignId(string $campaignId): bool {
    $campaignId = trim($campaignId);
    if ($campaignId === '' || strlen($campaignId) > 255) {
      return FALSE;
    }
    $campaign = $this->loadByCampaignId($campaignId);
    if (!$campaign) {
      return FALSE;
    }
    return $this->previews->sourceLaneForCampaign(
      (int) $campaign->get('prospect_id')->target_id,
      (int) $campaign->id(),
    ) === 'verified_cold';
  }

  /**
   * Rechecks the private importer evidence before it can write Build DNA or
   * proof artifacts. The Drush command performs its own file/HMAC checks;
   * this service boundary protects future callers that might otherwise call
   * the callback service with a syntactically valid generic payload.
   */
  private function assertVerifiedColdPrivateImportProvenance(string $eventId, string $campaignId, string $studioJobId, array $buildDna): void {
    if (($buildDna['schema'] ?? '') !== 'famtastic.build-dna.v1') {
      throw new \InvalidArgumentException('Verified-cold callback requires a finalized Build DNA manifest.');
    }
    $campaign = $this->loadByCampaignId($campaignId);
    if (!$campaign || !hash_equals((string) $campaign->get('studio_job_id')->value, $studioJobId)) {
      throw new \InvalidArgumentException('Unknown verified-cold campaign or Site Studio job.');
    }
    $prospectId = (int) $campaign->get('prospect_id')->target_id;
    $proofCampaignId = (int) $campaign->id();
    if ($this->previews->sourceLaneForCampaign($prospectId, $proofCampaignId) !== 'verified_cold') {
      throw new \InvalidArgumentException('The private Build DNA importer may accept verified-cold campaigns only.');
    }
    $runtime = $this->previews->verifiedColdCallbackContractForCampaign($prospectId, $proofCampaignId);
    $run = is_array($buildDna['run'] ?? NULL) ? $buildDna['run'] : [];
    $runStartedAt = (string) ($run['started_at'] ?? $run['run_started_at'] ?? '');
    if (
      !$runtime
      || (int) ($run['prospect_id'] ?? 0) !== $prospectId
      || (int) ($run['proof_campaign_id'] ?? 0) !== $proofCampaignId
      || (int) ($run['public_preview_delivery_id'] ?? 0) !== (int) $runtime['public_preview_delivery_id']
      || !hash_equals($campaignId, (string) ($run['campaign_id'] ?? ''))
      || !hash_equals('verified_cold', (string) ($run['source_lane'] ?? ''))
      || !hash_equals($studioJobId, (string) ($runtime['job_id'] ?? ''))
      || !hash_equals($studioJobId, (string) ($run['job_id'] ?? ''))
      || !hash_equals($eventId, (string) ($runtime['callback_event_id'] ?? ''))
      || !hash_equals($eventId, (string) ($run['callback_event_id'] ?? ''))
      || !hash_equals((string) ($runtime['run_started_at'] ?? ''), $runStartedAt)
    ) {
      throw new \InvalidArgumentException('Verified-cold callback, Build DNA, and immutable delivery runtime do not share one exact binding.');
    }
  }

  /** Rebuilds the callback telemetry projection idempotently on a retry. */
  private function recordCallbackTelemetry(ProofCampaign $campaign, array $variants, bool $isShowcase): void {
    $prospect = $this->entityTypeManager->getStorage('famtastic_prospect')->load((int) $campaign->get('prospect_id')->target_id);
    if (!$prospect || $variants === []) {
      return;
    }
    $first = reset($variants);
    $dna = $first ? json_decode((string) $first->get('design_dna')->value, TRUE) : [];
    $dna = is_array($dna) ? $dna : [];
    $telemetry = is_array($dna['telemetry'] ?? NULL) ? $dna['telemetry'] : [];
    $prospectId = (int) $campaign->get('prospect_id')->target_id;
    $sourceLane = $this->previews->sourceLaneForCampaign($prospectId, (int) $campaign->id()) ?: '';
    $inputSnapshot = is_array($telemetry['input_snapshot'] ?? NULL) ? $telemetry['input_snapshot'] : [];
    if ($sourceLane !== '') {
      $inputSnapshot['source_lane'] = $sourceLane;
      $inputSnapshot['required_directions'] = array_map(static fn (ProofVariant $variant): string => (string) $variant->get('direction_id')->value, $variants);
      $inputSnapshot['asset_contract'] = $sourceLane === 'verified_cold'
        ? 'variants[].assets[] requires asset_id, relative_path, media_type, base64, sha256; at least one asset per direction'
        : 'optional';
    }
    $this->buildTelemetry->recordStudioProof($prospect, $campaign, $variants, [
      'build_key_suffix' => $isShowcase ? 'site-studio-showcase' : 'site-studio',
      'flow_key' => $telemetry['flow_key'] ?? 'site-studio-local-promotion',
      'task_key' => $telemetry['task_key'] ?? 'proof.generate',
      'provider' => $telemetry['provider'] ?? ($dna['provider'] ?? 'site_studio_local'),
      'agent_name' => $telemetry['agent_name'] ?? ($dna['agent'] ?? 'shay'),
      'prompt_snapshot' => $telemetry['prompt_snapshot'] ?? '',
      'input_snapshot' => $inputSnapshot,
      'source_sha' => $telemetry['source_sha'] ?? '',
    ]);
  }

  /** Completes the route-specific handoff after callback artifacts exist. */
  private function finalizeCallbackDelivery(ProofCampaign $campaign, ?array $request, array $variants): void {
    $campaignId = (string) $campaign->get('campaign_id')->value;
    $prospectId = (int) $campaign->get('prospect_id')->target_id;
    if ($request) {
      $this->protectProofArtifacts($campaignId);
      $this->portal->attachWebsiteRequestProof((int) $request['id'], $campaign, $variants);
      return;
    }
    if ($this->previews->isPublicDeliveryForCampaign($prospectId, (int) $campaign->id())) {
      // Secure raw artifact paths before the delivery can become review-ready.
      $this->protectPublicPreviewArtifacts($campaign);
      if (!$this->previews->markCampaignReady($prospectId, (int) $campaign->id())) {
        throw new \RuntimeException('The public preview delivery changed before it could be marked ready.');
      }
      return;
    }
    $this->ledger->enqueue(
      'outreach.prepare:prospect:' . $prospectId . ':campaign:' . $campaign->id(),
      'outreach.prepare',
      ['prospect_id' => $prospectId, 'proof_campaign_id' => (int) $campaign->id()],
      $prospectId,
    );
  }

  /**
   * Returns the latest campaign + variants for a prospect, or NULL.
   *
   * @return array{campaign:\Drupal\famtastic_pipeline\Entity\ProofCampaign,variants:\Drupal\famtastic_pipeline\Entity\ProofVariant[]}|null
   */
  public function getForProspect(Prospect $prospect): ?array {
    $storage = $this->entityTypeManager->getStorage('proof_campaign');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('prospect_id', $prospect->id())
      ->sort('id', 'DESC')
      ->range(0, 1)
      ->execute();
    if (!$ids) {
      return NULL;
    }
    /** @var \Drupal\famtastic_pipeline\Entity\ProofCampaign $campaign */
    $campaign = $storage->load(reset($ids));
    return ['campaign' => $campaign, 'variants' => $this->loadVariants($campaign)];
  }

  /** Returns only the exact campaign owned by this prospect. */
  public function getForId(Prospect $prospect, int $campaignId): ?array {
    /** @var \Drupal\famtastic_pipeline\Entity\ProofCampaign|null $campaign */
    $campaign = $this->entityTypeManager->getStorage('proof_campaign')->load($campaignId);
    if (!$campaign instanceof ProofCampaign || (int) $campaign->get('prospect_id')->target_id !== (int) $prospect->id()) {
      return NULL;
    }
    return ['campaign' => $campaign, 'variants' => $this->loadVariants($campaign)];
  }

  /** Reissues a failed-to-receipt remote dispatch with the same campaign key. */
  public function resumeRemoteDispatch(Prospect $prospect, ProofCampaign $campaign, array $context): array {
    if (!$this->studioClient->isRemote() || $campaign->get('generation_status')->value !== 'dispatching') {
      return ['campaign' => $campaign, 'variants' => $this->loadVariants($campaign)];
    }
    return $this->dispatchRemoteCampaign($prospect, $campaign, $context);
  }

  /** Sends one idempotent Site Studio request and persists its returned job id. */
  private function dispatchRemoteCampaign(Prospect $prospect, ProofCampaign $campaign, array $context): array {
    $jobId = $this->studioClient->dispatch($prospect, $campaign, $context);
    $campaign
      ->set('generation_status', 'waiting_callback')
      ->set('studio_job_id', $jobId)
      ->set('dispatched_at', $this->time->getRequestTime())
      ->save();
    $this->ledger->recordEvent(
      'proof.dispatched:' . $campaign->get('campaign_id')->value,
      'proof.dispatched',
      ['campaign_id' => $campaign->get('campaign_id')->value, 'studio_job_id' => $jobId],
      (int) $prospect->id(),
    );
    return ['campaign' => $campaign, 'variants' => []];
  }

  /**
   * Records the prospect's variant + package selection.
   *
   * @throws \InvalidArgumentException
   *   When the variant id or package is not allowed.
   */
  public function select(ProofCampaign $campaign, string $variantId, string $package): ProofCampaign {
    $variantId = strtolower(trim($variantId));
    if (!array_key_exists($variantId, self::DIRECTIONS)) {
      throw new \InvalidArgumentException('variant_id must be one of: a, b, c, d, e, f.');
    }
    $package = trim($package);
    if (!in_array($package, self::PACKAGES, TRUE)) {
      throw new \InvalidArgumentException('package must be one of: essential_199, business_499.');
    }
    $campaign->set('selected_variant', $variantId);
    $campaign->set('selected_package', $package);
    $campaign->set('selected_at', $this->time->getRequestTime());
    $campaign->save();
    $prospectId = (int) $campaign->get('prospect_id')->target_id;
    $this->ledger->recordEvent(
      'proof.selected:' . $campaign->get('campaign_id')->value,
      'proof.selected',
      [
        'campaign_id' => $campaign->get('campaign_id')->value,
        'variant_id' => $variantId,
        'package' => $package,
      ],
      $prospectId,
    );
    return $campaign;
  }

  /**
   * Expires every active campaign past its expiry; returns the count.
   */
  public function expireActive(): int {
    $storage = $this->entityTypeManager->getStorage('proof_campaign');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', 'active')
      ->condition('expires_at', $this->time->getRequestTime(), '<')
      ->execute();
    $count = 0;
    foreach ($ids as $id) {
      /** @var \Drupal\famtastic_pipeline\Entity\ProofCampaign $campaign */
      $campaign = $storage->load($id);
      if ($campaign) {
        $campaign->set('status', 'expired');
        $campaign->save();
        $count++;
      }
    }
    return $count;
  }

  /**
   * Marks a campaign converted after a successful payment.
   *
   * Called from fulfillment when the Stripe checkout session metadata carries
   * a campaign_id. Idempotent: an already-converted campaign is left alone.
   */
  public function markConverted(string $campaignId, ?string $stripeOrderId = NULL): bool {
    $campaign = $this->loadByCampaignId($campaignId);
    if (!$campaign) {
      $this->logger->warning('Proof campaign @cid not found for conversion.', ['@cid' => $campaignId]);
      return FALSE;
    }
    if ($campaign->get('status')->value === 'converted') {
      return TRUE;
    }
    $campaign->set('status', 'converted');
    if ($stripeOrderId) {
      $campaign->set('stripe_order_id', $stripeOrderId);
    }
    $campaign->save();
    $prospectId = (int) $campaign->get('prospect_id')->target_id;
    $this->ledger->recordEvent(
      'proof.converted:' . $campaignId,
      'proof.converted',
      ['campaign_id' => $campaignId, 'checkout_session_id' => $stripeOrderId],
      $prospectId,
    );
    $this->logger->info('Proof campaign @cid marked converted.', ['@cid' => $campaignId]);
    return TRUE;
  }

  /**
   * Returns the active selection for Stripe metadata, if any.
   *
   * @return array{campaign_id:string,selected_variant:string,selected_package:string}|null
   */
  public function activeSelection(Prospect $prospect): ?array {
    $found = $this->getForProspect($prospect);
    if (!$found) {
      return NULL;
    }
    $campaign = $found['campaign'];
    if ($campaign->get('status')->value !== 'active' || $campaign->isExpired()) {
      return NULL;
    }
    $variant = $campaign->get('selected_variant')->value;
    $package = $campaign->get('selected_package')->value;
    if (!$variant || !$package) {
      return NULL;
    }
    return [
      'campaign_id' => $campaign->get('campaign_id')->value,
      'selected_variant' => $variant,
      'selected_package' => $package,
    ];
  }

  /**
   * Loads a campaign by its public campaign_id.
   */
  public function loadByCampaignId(string $campaignId): ?ProofCampaign {
    $storage = $this->entityTypeManager->getStorage('proof_campaign');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('campaign_id', $campaignId)
      ->range(0, 1)
      ->execute();
    return $ids ? $storage->load(reset($ids)) : NULL;
  }

  /**
   * Loads all variants for a campaign, ordered by direction id.
   *
   * @return \Drupal\famtastic_pipeline\Entity\ProofVariant[]
   */
  protected function loadVariants(ProofCampaign $campaign): array {
    $storage = $this->entityTypeManager->getStorage('proof_variant');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('campaign_id', $campaign->id())
      ->sort('direction_id', 'ASC')
      ->execute();
    return $ids ? array_values($storage->loadMultiple($ids)) : [];
  }

  /**
   * Builds the public campaign id: pc-<slug>-<random4>.
   */
  protected function buildCampaignId(string $businessName): string {
    $slug = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $businessName));
    $slug = trim($slug, '-');
    $slug = $slug === '' ? 'business' : substr($slug, 0, 32);
    return sprintf('pc-%s-%s', $slug, bin2hex(random_bytes(8)));
  }

  /**
   * Hands the generation request to Site Studio when a studio URL is set.
   *
   * @return bool
   *   TRUE when a handoff was made through the adapter interface.
   */
  protected function dispatchToStudio(Prospect $prospect, ProofCampaign $campaign): bool {
    $studioUrl = (string) $this->configFactory->get('famtastic_pipeline.settings')->get('studio_url');
    if ($studioUrl === '') {
      return FALSE;
    }
    $project = $this->repository->getProject($prospect);
    if (!$project) {
      $this->logger->warning('studio_url is configured but prospect @p has no project; using stub proofs.', ['@p' => $prospect->id()]);
      return FALSE;
    }
    $json = [
      'type' => 'proof_campaign',
      'campaign_id' => $campaign->get('campaign_id')->value,
      'studio_url' => $studioUrl,
      'directions' => self::DIRECTIONS,
      'output_dir' => 'web/proofs/' . $campaign->get('campaign_id')->value . '/',
    ];
    $brief = sprintf(
      "Proof campaign %s for %s\n\nGenerate three design directions (a/b/c) as static HTML under web/proofs/%s/<direction>/index.html.\n",
      $campaign->get('campaign_id')->value,
      $campaign->get('business_name')->value,
      $campaign->get('campaign_id')->value,
    );
    try {
      $result = $this->studioAdapter->submit($json, $brief, $project);
      $this->logger->info('Site Studio proof handoff for @cid: @note', [
        '@cid' => $campaign->get('campaign_id')->value,
        '@note' => $result['note'] ?? 'submitted',
      ]);
      return TRUE;
    }
    catch (\Throwable $e) {
      $this->logger->error('Site Studio handoff failed for @cid: @m — falling back to stub proofs.', [
        '@cid' => $campaign->get('campaign_id')->value,
        '@m' => $e->getMessage(),
      ]);
      return FALSE;
    }
  }

  /**
   * Writes one static stub proof page; returns its backend-relative path.
   */
  protected function writeStubArtifact(string $campaignId, string $direction, string $directionName, string $businessName, Prospect $prospect, string $source): string {
    $relative = 'web/proofs/' . $campaignId . '/' . $direction . '/index.html';
    $absolute = \Drupal::root() . '/proofs/' . $campaignId . '/' . $direction;
    $this->fileSystem->prepareDirectory($absolute, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    $this->fileSystem->saveData($this->stubHtml($direction, $directionName, $businessName, $prospect, $source), $absolute . '/index.html', FileSystemInterface::EXISTS_REPLACE);
    return $relative;
  }

  /**
   * Writes a truthful layout thumbnail for an image-free pilot proof.
   */
  protected function writePilotThumbnail(string $campaignId, string $direction, string $directionName, string $businessName): string {
    $absolute = \Drupal::root() . '/proofs/' . $campaignId . '/' . $direction;
    $this->fileSystem->prepareDirectory($absolute, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    $p = $this->palette($direction);
    $e = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 400" role="img" aria-label="' . $e($directionName . ' preview for ' . $businessName) . '">
<rect width="640" height="400" fill="' . $p['bg'] . '"/>
<rect x="36" y="32" width="112" height="10" rx="5" fill="' . $p['accent'] . '"/>
<rect x="36" y="76" width="470" height="28" rx="5" fill="' . $p['ink'] . '" opacity=".95"/>
<rect x="36" y="116" width="350" height="12" rx="6" fill="' . $p['ink'] . '" opacity=".5"/>
<rect x="36" y="154" width="118" height="38" rx="' . ($direction === 'c' ? '19' : '5') . '" fill="' . $p['accent'] . '"/>
<rect x="36" y="235" width="174" height="112" rx="10" fill="#ffffff" opacity=".08" stroke="' . $p['accent'] . '"/>
<rect x="233" y="235" width="174" height="112" rx="10" fill="#ffffff" opacity=".08" stroke="' . $p['accent'] . '"/>
<rect x="430" y="235" width="174" height="112" rx="10" fill="#ffffff" opacity=".08" stroke="' . $p['accent'] . '"/>
</svg>';
    $this->fileSystem->saveData($svg, $absolute . '/thumbnail.svg', FileSystemInterface::EXISTS_REPLACE);
    return '/proofs/' . $campaignId . '/' . $direction . '/thumbnail.svg';
  }

  /**
   * Writes validated callback HTML into its isolated campaign/direction path.
   */
  protected function writeCallbackArtifact(string $campaignId, string $direction, string $html): string {
    $relative = 'web/proofs/' . $campaignId . '/' . $direction . '/index.html';
    $absolute = \Drupal::root() . '/proofs/' . $campaignId . '/' . $direction;
    $this->fileSystem->prepareDirectory($absolute, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    $this->fileSystem->saveData($html, $absolute . '/index.html', FileSystemInterface::EXISTS_REPLACE);
    return $relative;
  }

  /**
   * Stores a callback's validated media below the protected proof directory.
   *
   * The returned manifest is intentionally byte-free and becomes part of the
   * direction's immutable design DNA. Public rooms later freeze this manifest
   * and rehash the files on every signed asset request.
   *
   * @param list<array{asset_id:string,relative_path:string,media_type:string,bytes:string,sha256:string,size_bytes:int}> $assets
   *
   * @return list<array{asset_id:string,relative_path:string,media_type:string,sha256:string,size_bytes:int,artifact_path:string}>
   */
  protected function writeCallbackAssets(string $campaignId, string $direction, array $assets): array {
    if ($assets === []) {
      return [];
    }
    // The asset subtree is denied independently of the parent proof page.
    // A generic/static proof can therefore never become an accidental bypass
    // for original image bytes while a signed-room controller remains the only
    // public delivery route.
    $assetsDirectory = \Drupal::root() . '/proofs/' . $campaignId . '/' . $direction . '/assets';
    if (!$this->fileSystem->prepareDirectory($assetsDirectory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
      throw new \RuntimeException('Unable to prepare protected proof asset storage.');
    }
    $rules = "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n";
    if ($this->fileSystem->saveData($rules, $assetsDirectory . '/.htaccess', FileSystemInterface::EXISTS_REPLACE) === FALSE) {
      throw new \RuntimeException('Unable to protect proof assets from static access.');
    }
    $manifest = [];
    foreach ($assets as $asset) {
      $relative = ProofAssetContract::artifactPath($campaignId, $direction, $asset['relative_path']);
      $absolute = dirname(\Drupal::root()) . '/' . $relative;
      $directory = dirname($absolute);
      if (!$this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
        throw new \RuntimeException('Unable to prepare protected proof asset storage.');
      }
      if ($this->fileSystem->saveData($asset['bytes'], $absolute, FileSystemInterface::EXISTS_REPLACE) === FALSE) {
        throw new \RuntimeException('Unable to write protected proof asset.');
      }
      $actualHash = hash_file('sha256', $absolute);
      $actualSize = filesize($absolute);
      if ($actualHash === FALSE || $actualSize === FALSE || !hash_equals($asset['sha256'], $actualHash) || (int) $actualSize !== (int) $asset['size_bytes']) {
        throw new \RuntimeException('Protected proof asset verification failed after storage.');
      }
      $manifest[] = [
        'asset_id' => $asset['asset_id'],
        'relative_path' => $asset['relative_path'],
        'media_type' => $asset['media_type'],
        'sha256' => $asset['sha256'],
        'size_bytes' => $asset['size_bytes'],
        'artifact_path' => $relative,
      ];
    }
    return $manifest;
  }

  /**
   * Writes a generated proof screenshot and returns its public URL path.
   */
  protected function writeCallbackThumbnail(string $campaignId, string $direction, string $binary, string $extension): string {
    $filename = 'thumbnail.' . ($extension === 'png' ? 'png' : 'jpg');
    $absolute = \Drupal::root() . '/proofs/' . $campaignId . '/' . $direction;
    $this->fileSystem->prepareDirectory($absolute, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    $this->fileSystem->saveData($binary, $absolute . '/' . $filename, FileSystemInterface::EXISTS_REPLACE);
    return '/proofs/' . $campaignId . '/' . $direction . '/' . $filename;
  }

  /** Prevents direct web access to account-owned or signed-room proof artifacts. */
  protected function protectProofArtifacts(string $campaignId): void {
    $directory = \Drupal::root() . '/proofs/' . $campaignId;
    if (!$this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
      throw new \RuntimeException('Unable to secure proof artifacts.');
    }
    $rules = "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n";
    if ($this->fileSystem->saveData($rules, $directory . '/.htaccess', FileSystemInterface::EXISTS_REPLACE) === FALSE) {
      throw new \RuntimeException('Unable to secure proof artifacts.');
    }
  }

  /** Protects a public-lead proof set before the signed concept-room handoff. */
  public function protectPublicPreviewArtifacts(ProofCampaign $campaign): void {
    $this->protectProofArtifacts((string) $campaign->get('campaign_id')->value);
  }

  /**
   * Builds the public preview URL for a direction.
   *
   * Default: {scheme}{host}/proofs/<campaign_id>/<direction>/. The full
   * base (including the /proofs prefix) is overridable via the
   * famtastic_pipeline.settings.proofs_base_url config for prod.
   */
  protected function previewUrl(string $campaignId, string $direction): string {
    $base = rtrim((string) $this->configFactory->get('famtastic_pipeline.settings')->get('proofs_base_url'), '/');
    if ($base === '') {
      $base = \Drupal::request()->getSchemeAndHttpHost() . '/proofs';
    }
    return $base . '/' . $campaignId . '/' . $direction . '/';
  }

  /**
   * Accent palette per direction (dark + lime family, visually distinct).
   *
   * @return array{bg:string,accent:string,ink:string}
   */
  protected function palette(string $direction): array {
    return match ($direction) {
      'a' => ['bg' => '#0c0f0a', 'accent' => '#b8f135', 'ink' => '#f4f7ee'],
      'b' => ['bg' => '#101418', 'accent' => '#8fd14f', 'ink' => '#eef2f6'],
      default => ['bg' => '#131009', 'accent' => '#cdea44', 'ink' => '#faf6ea'],
    };
  }

  /**
   * Renders a complete, image-free pilot proof page.
   */
  protected function stubHtml(string $direction, string $directionName, string $businessName, Prospect $prospect, string $source): string {
    $p = $this->palette($direction);
    $e = static fn(?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    $category = trim((string) $prospect->get('business_category')->value) ?: 'Local business';
    $content = $this->pilotContent($category, (string) $prospect->get('business_description')->value);
    $tagline = $e($content['tagline']);
    $phone = $e($prospect->get('public_phone')->value);
    $area = $e($prospect->get('service_area')->value ?: $prospect->get('address')->value);
    $items = '';
    foreach ($content['services'] as $service) {
      $items .= '<article class="card"><span class="number">0' . (substr_count($items, 'class="card"') + 1) . '</span><h3>' . $e($service[0]) . '</h3><p>' . $e($service[1]) . '</p></article>';
    }
    $contactBits = trim($phone . ($phone && $area ? ' &middot; ' : '') . $area);
    $phoneHref = preg_replace('/[^0-9+]/', '', (string) $prospect->get('public_phone')->value);
    $ctaHref = $phoneHref ? 'tel:' . $phoneHref : '#contact';
    $ctaLabel = $phoneHref ? 'Call today' : 'Get in touch';
    $bodyClass = 'direction-' . $direction;

    return '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>' . $e($businessName) . ' — ' . $e($directionName) . '</title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  :root { --bg:' . $p['bg'] . '; --accent:' . $p['accent'] . '; --ink:' . $p['ink'] . '; }
  body { background:var(--bg); color:var(--ink); font-family:Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; line-height:1.55; min-height:100vh; }
  nav { max-width:1160px; margin:auto; padding:26px 28px; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #ffffff1c; }
  .brand { font-size:15px; font-weight:850; letter-spacing:-.02em; }
  .nav-note { font-size:12px; letter-spacing:.14em; text-transform:uppercase; opacity:.62; }
  header { min-height:540px; max-width:1160px; margin:auto; padding:84px 28px 70px; display:grid; grid-template-columns:minmax(0,1.45fr) minmax(250px,.55fr); gap:68px; align-items:end; }
  .kicker { color:var(--accent); font-size:12px; font-weight:800; letter-spacing:.2em; text-transform:uppercase; margin-bottom:22px; }
  h1 { font-size:clamp(46px,8vw,94px); line-height:.91; letter-spacing:-.065em; max-width:900px; margin-bottom:28px; }
  h1 span { color:' . $p['accent'] . '; }
  .tag { font-size:clamp(18px,2.3vw,25px); opacity:.8; max-width:690px; }
  .cta { display:inline-flex; margin-top:34px; background:var(--accent); color:var(--bg); font-weight:850; padding:16px 28px; border-radius:4px; text-decoration:none; }
  .hero-aside { border-left:1px solid #ffffff28; padding-left:26px; font-size:14px; opacity:.76; }
  .hero-aside strong { display:block; color:var(--accent); font-size:38px; line-height:1; margin-bottom:12px; }
  section { max-width:1160px; margin:0 auto; padding:0 28px 92px; display:grid; gap:18px; grid-template-columns:repeat(3,1fr); }
  .card { min-height:230px; border:1px solid #ffffff22; padding:30px; background:#ffffff08; }
  .number { color:var(--accent); font-size:12px; font-weight:800; }
  .card h3 { font-size:22px; margin:44px 0 10px; }
  .card p { font-size:15px; opacity:.7; }
  footer { border-top:1px solid #ffffff1a; padding:28px; text-align:center; font-size:13px; opacity:.68; }
  .direction-b { --bg:#f4f0e7; --ink:#18231e; --accent:#275b46; }
  .direction-b nav { border-color:#18231e22; }
  .direction-b header { grid-template-columns:1fr; text-align:center; max-width:980px; min-height:570px; align-content:center; }
  .direction-b h1 { font-family:Georgia,"Times New Roman",serif; letter-spacing:-.045em; margin-left:auto; margin-right:auto; }
  .direction-b .tag { margin:auto; }
  .direction-b .hero-aside { display:none; }
  .direction-b .card { border-color:#18231e22; background:#ffffff66; border-radius:6px; }
  .direction-b footer { border-color:#18231e22; }
  .direction-c { --bg:#fff8e9; --ink:#252014; --accent:#cf5b32; }
  .direction-c nav { border-color:#2520141f; }
  .direction-c header { min-height:500px; align-items:center; }
  .direction-c h1 { letter-spacing:-.05em; }
  .direction-c .cta { border-radius:999px; }
  .direction-c .hero-aside { border:0; border-radius:28px; padding:30px; background:#cf5b3212; }
  .direction-c .card { border-color:#2520141f; background:#ffffff85; border-radius:24px; }
  .direction-c footer { border-color:#2520141f; }
  @media (max-width:760px) { header { grid-template-columns:1fr; min-height:auto; padding-top:58px; } .hero-aside { display:none; } section { grid-template-columns:1fr; } .nav-note { display:none; } }
</style>
</head>
<body class="' . $bodyClass . '">
<nav><div class="brand">' . $e($businessName) . '</div><div class="nav-note">' . $e($directionName) . '</div></nav>
<header>
  <div><div class="kicker">' . $e($category) . ($area ? ' · ' . $area : '') . '</div>
  <h1>' . $e($businessName) . '<span>.</span></h1>
  <p class="tag">' . $tagline . '</p>
  <a class="cta" href="' . $e($ctaHref) . '">' . $e($ctaLabel) . ' &nbsp;→</a></div>
  <aside class="hero-aside"><strong>Local.</strong>A focused site concept designed to help customers quickly understand what you offer and how to reach you.</aside>
</header>
<section>' . $items . '</section>
<footer id="contact">
  ' . ($contactBits !== '' ? $contactBits . '<br>' : '') . 'Website concept prepared for ' . $e($businessName) . '.
</footer>
</body>
</html>
';
  }

  /**
   * Returns category-aware, factual copy without inventing business claims.
   *
   * @return array{tagline:string,services:array<int,array{0:string,1:string}>}
   */
  protected function pilotContent(string $category, string $description): array {
    $key = strtolower($category . ' ' . $description);
    if (preg_match('/coffee|cafe|bakery/', $key)) {
      return ['tagline' => 'A welcoming online home for local favorites, current hours, and the next visit.', 'services' => [
        ['What is fresh', 'Give customers a clear place to discover signature drinks, baked goods, and seasonal highlights.'],
        ['Plan a visit', 'Put hours, location, and contact details where guests can find them without digging.'],
        ['Stay connected', 'Create a simple destination for announcements, catering questions, and local updates.'],
      ]];
    }
    if (preg_match('/hydraulic|repair|equipment/', $key)) {
      return ['tagline' => 'A direct, capable site that makes specialized service easier to understand and request.', 'services' => [
        ['Repair expertise', 'Explain the equipment and components your team is equipped to evaluate and rebuild.'],
        ['Clear next steps', 'Help customers know what information to provide before requesting service.'],
        ['Reach the shop', 'Keep service area, phone, and location easy to find for time-sensitive work.'],
      ]];
    }
    if (preg_match('/auto|motor|vehicle|car/', $key)) {
      return ['tagline' => 'A polished destination that helps shoppers move from discovery to a real conversation.', 'services' => [
        ['Featured inventory', 'Create space to highlight available vehicles and the details buyers care about.'],
        ['A simpler inquiry', 'Give shoppers one clear route to ask a question or arrange their next step.'],
        ['Local confidence', 'Present contact details and business information in a consistent, credible format.'],
      ]];
    }
    if (preg_match('/hair|lash|skin|beauty|salon|wellness|studio|loc/', $key)) {
      return ['tagline' => 'A refined, welcoming site concept built around services, appointments, and local discovery.', 'services' => [
        ['Signature services', 'Organize the treatments and services customers want to understand before booking.'],
        ['Booking made clear', 'Create a focused path from first impression to an appointment request.'],
        ['Your local presence', 'Keep location, contact details, and important visit information in one place.'],
      ]];
    }
    return ['tagline' => 'A clear, modern home for services, contact details, and the next customer conversation.', 'services' => [
      ['What you offer', 'Explain your core services in plain language that customers can scan quickly.'],
      ['Why customers call', 'Bring the most useful decision-making details into one focused experience.'],
      ['Easy contact', 'Make location, service area, and contact information simple to find on any device.'],
    ]];
  }

}
