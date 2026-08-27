<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Executes bounded idempotent jobs and routes failures to retry/exception state.
 */
final class AutomationWorker {

  public function __construct(
    private readonly OperationalLedger $ledger,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ProofCampaignService $proofCampaigns,
    private readonly CampaignMessageService $campaignMessages,
    private readonly CustomerDeploymentService $deployments,
    private readonly DomainLifecycleService $domains,
    private readonly HostingLifecycleService $hosting,
    private readonly CustomerPortalService $portal,
    private readonly PublicPreviewDeliveryService $previews,
  ) {}

  /**
   * Runs up to the requested number of available jobs.
   */
  public function run(int $limit = 25, ?string $jobType = NULL, ?array $prospectIds = NULL): array {
    $limit = max(1, min(100, $limit));
    $results = [];
    for ($i = 0; $i < $limit; $i++) {
      $job = $this->ledger->claimNext($jobType, $prospectIds);
      if (!$job) {
        break;
      }
      try {
        $result = $this->execute($job);
        $this->ledger->completeJob($job['id'], $result);
        $results[] = ['job_id' => $job['id'], 'status' => 'completed', 'result' => $result];
      }
      catch (\Throwable $e) {
        $failure = $this->ledger->failJob($job['id'], $e->getMessage());
        $results[] = ['job_id' => $job['id'], 'status' => $failure['exhausted'] ? 'failed' : 'retry', 'error' => $e->getMessage()];
      }
    }
    return $results;
  }

  /**
   * Executes one known job type.
   */
  private function execute(array $job): array {
    return match ($job['job_type']) {
      'proof.generate' => $this->generateProofs($job),
      'public_preview.generate' => $this->generatePublicPreviewProofs($job),
      'outreach.prepare' => $this->prepareOutreach($job),
      'outreach.send' => $this->sendOutreach($job),
      'deployment.prepare' => $this->prepareDeployment($job),
      'deployment.apply' => $this->applyDeployment($job),
      'domain.verify' => $this->verifyDomain($job),
      'hosting.activate' => $this->activateHosting($job),
      default => throw new \RuntimeException('Unsupported job type: ' . $job['job_type']),
    };
  }

  /**
   * Produces the frozen proof cohort variants or throws for retry.
   */
  private function generatePublicPreviewProofs(array $job): array {
    $context = (array) ($job['payload'] ?? []);
    $deliveryId = (int) ($context['public_preview_delivery_id'] ?? 0);
    $proofCampaignId = (int) ($context['proof_campaign_id'] ?? 0);
    $campaignId = trim((string) ($context['campaign_id'] ?? ''));
    $run = (array) ($context['build_dna_run'] ?? []);
    $sourceLane = (string) ($context['source_lane'] ?? '');
    if (
      $deliveryId < 1
      || $proofCampaignId < 1
      || $campaignId === ''
      || (int) ($run['prospect_id'] ?? 0) !== (int) ($context['prospect_id'] ?? $job['prospect_id'] ?? 0)
      || (int) ($run['proof_campaign_id'] ?? 0) !== $proofCampaignId
      || !hash_equals($campaignId, (string) ($run['campaign_id'] ?? ''))
      || !in_array((string) ($run['source_lane'] ?? ''), ['anonymous_public', 'verified_cold'], TRUE)
    ) {
      throw new \RuntimeException('Public preview job is missing its exact campaign and Build DNA run identity contract.');
    }
    if ($sourceLane === 'verified_cold' && (
      !hash_equals((string) ($context['job_id'] ?? ''), (string) ($run['job_id'] ?? ''))
      || !hash_equals((string) ($context['callback_event_id'] ?? ''), (string) ($run['callback_event_id'] ?? ''))
      || !hash_equals((string) ($context['run_started_at'] ?? ''), (string) ($run['run_started_at'] ?? ''))
      || !$this->canonicalRuntimeReference((string) ($run['job_id'] ?? ''))
      || !$this->canonicalRuntimeReference((string) ($run['callback_event_id'] ?? ''))
      || !$this->canonicalRuntimeTimestamp((string) ($run['run_started_at'] ?? ''))
    )) {
      throw new \RuntimeException('Verified-cold public preview job is missing its ingress-frozen callback runtime contract.');
    }
    return $this->generateProofs($job, TRUE);
  }

  private function generateProofs(array $job, bool $dedicatedPublicPreview = FALSE): array {
    $context = (array) ($job['payload'] ?? []);
    $requestId = (int) ($context['website_request_id'] ?? 0);
    $publicPreviewDeliveryId = (int) ($context['public_preview_delivery_id'] ?? 0);
    if ($publicPreviewDeliveryId && !$dedicatedPublicPreview) {
      throw new \RuntimeException('Public previews must run through the dedicated public_preview.generate worker lane.');
    }
    if ($requestId) {
      $context = array_replace($context, $this->portal->websiteRequestProofContext($requestId));
    }
    elseif ($publicPreviewDeliveryId) {
      $context = array_replace($context, $this->previews->publicIntakeProofContext($publicPreviewDeliveryId));
    }
    $expectedDirections = ['a', 'b', 'c'];
    if ($publicPreviewDeliveryId) {
      $profile = (array) ($context['public_preview_proof_profile'] ?? []);
      $contract = (array) ($profile['directions'] ?? []);
      $expectedDirections = array_keys($contract);
      if (count($expectedDirections) < 1 || count($expectedDirections) > 6 || $expectedDirections !== array_slice(['a', 'b', 'c', 'd', 'e', 'f'], 0, count($expectedDirections))) {
        throw new \RuntimeException('Public proof job has no valid frozen direction contract.');
      }
    }
    $prospectId = (int) ($job['payload']['prospect_id'] ?? $job['prospect_id'] ?? 0);
    $prospect = $this->entityTypeManager->getStorage('famtastic_prospect')->load($prospectId);
    if (!$prospect) {
      throw new \RuntimeException('Prospect no longer exists.');
    }
    if ($requestId) {
      $boundCampaignId = $this->portal->websiteRequestProofCampaignId($requestId);
      $created = $boundCampaignId
        ? $this->proofCampaigns->getForId($prospect, $boundCampaignId)
        : $this->proofCampaigns->createForProspect($prospect, $context);
      if (!$created) {
        throw new \RuntimeException('The website request references an unavailable proof campaign.');
      }
    }
    elseif ($publicPreviewDeliveryId) {
      $boundCampaignId = $this->previews->initialProofCampaignId($publicPreviewDeliveryId);
      if ($boundCampaignId !== (int) ($context['proof_campaign_id'] ?? 0)) {
        throw new \RuntimeException('Public preview job does not match its delivery-bound proof campaign.');
      }
      $created = $boundCampaignId
        ? $this->proofCampaigns->getForId($prospect, $boundCampaignId)
        : NULL;
      if (!$created) {
        throw new \RuntimeException('The public delivery references an unavailable proof campaign.');
      }
    }
    else {
      $existing = $this->proofCampaigns->getForProspect($prospect);
      $created = $existing ?: $this->proofCampaigns->createForProspect($prospect, $context);
    }
    if (count($created['variants']) === 0 && $created['campaign']->get('generation_status')->value === 'dispatching') {
      $created = $this->proofCampaigns->resumeRemoteDispatch($prospect, $created['campaign'], $context);
    }
    $variants = $created['variants'];
    if (
      count($variants) === 0
      && $created['campaign']->get('generation_status')->value === 'waiting_callback'
    ) {
      return [
        'campaign_id' => $created['campaign']->get('campaign_id')->value,
        'status' => 'waiting_callback',
        'studio_job_id' => $created['campaign']->get('studio_job_id')->value,
      ];
    }
    if (count($variants) !== count($expectedDirections)) {
      throw new \RuntimeException(sprintf('Site Studio returned %d proof variants; exactly %d are required by this proof cohort.', count($variants), count($expectedDirections)));
    }
    $directions = [];
    $paths = [];
    foreach ($variants as $variant) {
      $direction = (string) $variant->get('direction_id')->value;
      $path = (string) $variant->get('artifact_path')->value;
      $absolutePath = $path;
      if ($path !== '' && !str_starts_with($path, '/') && !is_file($path)) {
        $absolutePath = dirname(\Drupal::root()) . '/' . ltrim($path, '/');
      }
      if (!in_array($direction, $expectedDirections, TRUE) || $path === '' || !is_file($absolutePath)) {
        throw new \RuntimeException('A proof variant is invalid or its isolated artifact is missing.');
      }
      $directions[] = $direction;
      $paths[] = realpath($absolutePath) ?: $absolutePath;
    }
    sort($directions);
    if ($directions !== $expectedDirections || count(array_unique($paths)) !== count($expectedDirections)) {
      throw new \RuntimeException('Proof variants are not distinct and isolated.');
    }
    $pilotSources = array_filter($variants, static function ($variant): bool {
      $dna = json_decode((string) $variant->get('design_dna')->value, TRUE);
      return is_array($dna) && ($dna['source'] ?? NULL) === 'no_image_pilot_v1';
    });
    $pilotAllowed = getenv('FAMTASTIC_ALLOW_NO_IMAGE_PILOT_PROOFS') === '1'
      || getenv('FAMTASTIC_ALLOW_STUB_OUTREACH') === '1';
    if ($pilotSources && $publicPreviewDeliveryId) {
      throw new \RuntimeException('Public concept rooms cannot use image-free pilot proofs, even when another lane permits them.');
    }
    if ($pilotSources && !$pilotAllowed) {
      throw new \RuntimeException('Image-free pilot proofs require explicit environment approval before outreach can be queued.');
    }
    $campaign = $created['campaign'];
    $this->ledger->recordEvent(
      'proof.ready:' . $campaign->get('campaign_id')->value,
      'proof.ready',
      [
        'campaign_id' => $campaign->get('campaign_id')->value,
        'variant_count' => count($expectedDirections),
        'directions' => $directions,
      ],
      $prospectId,
    );
    $projectId = (int) ($job['payload']['project_id'] ?? 0);
    if ($requestId) {
      $this->portal->attachWebsiteRequestProof($requestId, $campaign, $variants);
    }
    elseif ($projectId) {
      $this->portal->markProjectProofReady($projectId, $campaign, $variants);
    }
    elseif ($publicPreviewDeliveryId) {
      // An explicit public job is never eligible for generic outreach. This
      // exact delivery remains retry-safe if artifact protection fails.
      $this->proofCampaigns->protectPublicPreviewArtifacts($campaign);
      if (!$this->previews->markCampaignReadyForDelivery($publicPreviewDeliveryId, (int) $campaign->id())) {
        throw new \RuntimeException('The public preview delivery could not be marked ready after proof protection.');
      }
    }
    elseif ($this->previews->isPublicDeliveryForCampaign($prospectId, (int) $campaign->id())) {
      $this->proofCampaigns->protectPublicPreviewArtifacts($campaign);
      if (!$this->previews->markCampaignReady($prospectId, (int) $campaign->id())) {
        throw new \RuntimeException('The public preview delivery changed before it could be marked ready.');
      }
    }
    else {
      $this->ledger->enqueue(
        'outreach.prepare:prospect:' . $prospectId . ':campaign:' . $campaign->id(),
        'outreach.prepare',
        ['prospect_id' => $prospectId, 'proof_campaign_id' => (int) $campaign->id()],
        $prospectId,
      );
    }
    return [
      'campaign_id' => $campaign->get('campaign_id')->value,
      'variant_count' => count($expectedDirections),
      'directions' => $directions,
    ];
  }

  private function prepareOutreach(array $job): array {
    $prospectId = (int) ($job['payload']['prospect_id'] ?? $job['prospect_id'] ?? 0);
    $proofCampaignId = (int) ($job['payload']['proof_campaign_id'] ?? 0);
    $prospect = $this->entityTypeManager->getStorage('famtastic_prospect')->load($prospectId);
    if (!$prospect || !$proofCampaignId) {
      throw new \RuntimeException('Outreach preparation is missing its prospect or proof campaign.');
    }
    $message = $this->campaignMessages->prepare($prospect, $proofCampaignId);
    return ['message_id' => (int) $message['id'], 'status' => $message['status']];
  }

  private function sendOutreach(array $job): array {
    $messageId = (int) ($job['payload']['message_id'] ?? 0);
    if (!$messageId) {
      throw new \RuntimeException('Outreach send job is missing its message id.');
    }
    $message = $this->campaignMessages->send($messageId);
    return [
      'message_id' => (int) $message['id'],
      'status' => $message['status'],
      'provider' => $message['provider'],
      'provider_message_id' => $message['provider_message_id'],
    ];
  }

  private function prepareDeployment(array $job): array {
    $projectId = (int) ($job['payload']['project_id'] ?? 0);
    if (!$projectId) {
      throw new \RuntimeException('Deployment preparation is missing its project id.');
    }
    $deployment = $this->deployments->prepare($projectId);
    return [
      'deployment_id' => (int) $deployment['id'],
      'status' => $deployment['status'],
      'release_sha' => $deployment['release_sha'],
      'artifact_checksum' => $deployment['artifact_checksum'],
    ];
  }

  private function applyDeployment(array $job): array {
    $deploymentId = (int) ($job['payload']['deployment_id'] ?? 0);
    if (!$deploymentId) {
      throw new \RuntimeException('Deployment apply is missing its deployment id.');
    }
    $deployment = $this->deployments->apply($deploymentId);
    return [
      'deployment_id' => (int) $deployment['id'],
      'status' => $deployment['status'],
      'target_path' => $deployment['target_path'],
      'public_url' => $deployment['public_url'],
      'backup_path' => $deployment['backup_path'],
    ];
  }

  private function verifyDomain(array $job): array {
    $deploymentId = (int) ($job['payload']['deployment_id'] ?? 0);
    if (!$deploymentId) {
      throw new \RuntimeException('Domain verification is missing its deployment id.');
    }
    return $this->domains->verifyDeployment(
      $deploymentId,
      isset($job['payload']['domain_id']) ? (int) $job['payload']['domain_id'] : NULL,
    );
  }

  private function activateHosting(array $job): array {
    $projectId = (int) ($job['payload']['project_id'] ?? 0);
    if (!$projectId) {
      throw new \RuntimeException('Hosting activation is missing its project id.');
    }
    return $this->hosting->activate($projectId);
  }

  /** Reject local builder placeholders in a verified-cold worker payload. */
  private function canonicalRuntimeReference(string $value): bool {
    return preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{1,254}$/', $value) === 1
      && preg_match('/^(?:local-|beauty-proof:)/i', $value) !== 1;
  }

  /** The immutable contract stores an ISO time when its job was created. */
  private function canonicalRuntimeTimestamp(string $value): bool {
    return $value !== '' && strlen($value) <= 80 && strtotime($value) !== FALSE;
  }

}
