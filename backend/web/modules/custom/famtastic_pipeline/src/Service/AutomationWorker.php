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
   * Produces exactly three isolated proof variants or throws for retry.
   */
  private function generateProofs(array $job): array {
    $context = (array) ($job['payload'] ?? []);
    $requestId = (int) ($context['website_request_id'] ?? 0);
    if ($requestId) {
      $context = array_replace($context, $this->portal->websiteRequestProofContext($requestId));
    }
    $prospectId = (int) ($job['payload']['prospect_id'] ?? $job['prospect_id'] ?? 0);
    $prospect = $this->entityTypeManager->getStorage('famtastic_prospect')->load($prospectId);
    if (!$prospect) {
      throw new \RuntimeException('Prospect no longer exists.');
    }
    $existing = $this->proofCampaigns->getForProspect($prospect);
    $created = $existing ?: $this->proofCampaigns->createForProspect($prospect, $context);
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
    if (count($variants) !== 3) {
      throw new \RuntimeException(sprintf('Site Studio returned %d proof variants; exactly 3 are required.', count($variants)));
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
      if (!in_array($direction, ['a', 'b', 'c'], TRUE) || $path === '' || !is_file($absolutePath)) {
        throw new \RuntimeException('A proof variant is invalid or its isolated artifact is missing.');
      }
      $directions[] = $direction;
      $paths[] = realpath($absolutePath) ?: $absolutePath;
    }
    if (count(array_unique($directions)) !== 3 || count(array_unique($paths)) !== 3) {
      throw new \RuntimeException('Proof variants are not distinct and isolated.');
    }
    $pilotSources = array_filter($variants, static function ($variant): bool {
      $dna = json_decode((string) $variant->get('design_dna')->value, TRUE);
      return is_array($dna) && ($dna['source'] ?? NULL) === 'no_image_pilot_v1';
    });
    $pilotAllowed = getenv('FAMTASTIC_ALLOW_NO_IMAGE_PILOT_PROOFS') === '1'
      || getenv('FAMTASTIC_ALLOW_STUB_OUTREACH') === '1';
    if ($pilotSources && !$pilotAllowed) {
      throw new \RuntimeException('Image-free pilot proofs require explicit environment approval before outreach can be queued.');
    }
    $campaign = $created['campaign'];
    $this->ledger->recordEvent(
      'proof.ready:' . $campaign->get('campaign_id')->value,
      'proof.ready',
      [
        'campaign_id' => $campaign->get('campaign_id')->value,
        'variant_count' => 3,
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
    elseif (!$this->previews->markCampaignReady($prospectId, (int) $campaign->id())) {
      $this->ledger->enqueue(
        'outreach.prepare:prospect:' . $prospectId . ':campaign:' . $campaign->id(),
        'outreach.prepare',
        ['prospect_id' => $prospectId, 'proof_campaign_id' => (int) $campaign->id()],
        $prospectId,
      );
    }
    return [
      'campaign_id' => $campaign->get('campaign_id')->value,
      'variant_count' => 3,
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

}
