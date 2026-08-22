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
    private readonly ProofRunnerContractService $proofRunner,
    private readonly ProofCampaignService $proofCampaigns,
    private readonly CampaignMessageService $campaignMessages,
    private readonly CustomerDeploymentService $deployments,
    private readonly DomainLifecycleService $domains,
    private readonly HostingLifecycleService $hosting,
    private readonly CustomerPortalService $portal,
    private readonly PublicPreviewDeliveryService $previews,
    private readonly ProofRevisionService $revisions,
    private readonly SiteStudioProofClient $studioClient,
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
      'proof.showcase.generate' => $this->generateShowcaseProofs($job),
      'proof.refined.generate' => $this->generateRefinedSixJob($job),
      'proof.revision.generate' => $this->generateRevisionProof($job),
      'proof.revision.requested' => $this->recordRevisionRequest($job),
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
    $runner = NULL;
    // Canonical runner jobs are explicitly opted in and must carry one of the
    // two source correlations. Preserve older imports/synthetic proof jobs
    // until they are intentionally migrated instead of making them fail on a
    // missing public-delivery or website-request record.
    $canonicalRunnerJob = (string) ($context['routine'] ?? '') === ProofRunnerContractService::ROUTINE
      && (!empty($context['public_preview_delivery_id']) || !empty($context['website_request_id']));
    if ($canonicalRunnerJob) {
      // A prospect may own several projects. Resume only a campaign already
      // source-bound to this exact intake, never the latest campaign across
      // all of the prospect's work.
      $existing = $this->proofRunner->existingCampaignForSource($prospect, $context);
    }
    else {
      $existing = $this->proofCampaigns->getForProspect($prospect);
    }
    if (!$existing) {
      if (!$canonicalRunnerJob) {
        $created = $this->proofCampaigns->createForProspect($prospect, $context);
      }
      else {
        // No proof builder, pilot renderer, email, or customer-facing artifact
        // may start until the source-bound runner contract has passed its
        // declared preflight and registered its preflight Build DNA projection.
        $runner = $this->proofRunner->prepare($prospect, $context, $job);
        if ($this->proofRunner->isLocalContractFixture($runner)) {
          return [
            'status' => 'local_contract_fixture',
            'build_id' => $runner['build_id'],
            'contract_uri' => $runner['contract_uri'],
            'build_dna_uri' => $runner['build_dna_uri'],
            'message' => 'Fixture validated only; no proof, email, payment, or publication was created.',
          ];
        }
        // ProofCampaignService consumes the private evidence URI and binds
        // it to the just-created campaign before it dispatches any remote
        // request. It removes that URI before Site Studio receives the signed
        // envelope, so there is neither a private:// leak nor a fast-callback
        // linkage race.
        $context = $this->proofRunner->applyProfileToContext($context, $runner);
        $context['proof_runner'] = $runner['handoff'];
        $created = $this->proofCampaigns->createForProspect($prospect, $context);
      }
    }
    else {
      $created = $existing;
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
        'proof_runner_build_id' => $runner['build_id'] ?? NULL,
      ];
    }
    $expectedDirections = $canonicalRunnerJob
      ? $this->proofRunner->expectedDirectionIds($context)
      : ['a', 'b', 'c'];
    $expectedCount = count($expectedDirections);
    if (count($variants) !== $expectedCount) {
      throw new \RuntimeException(sprintf('Site Studio returned %d proof variants; exactly %d are required.', count($variants), $expectedCount));
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
    if (count(array_unique($directions)) !== $expectedCount || count(array_unique($paths)) !== $expectedCount) {
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
        'variant_count' => $expectedCount,
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
    elseif (!$canonicalRunnerJob) {
      // Runner-bound public proofs are promoted only inside the signed
      // callback after final Build DNA verification, using its exact immutable
      // public_preview_delivery_id. A worker retry must not infer a delivery
      // from the prospect or stage a lead from mutable queue context.
      $this->ledger->enqueue(
        'outreach.prepare:prospect:' . $prospectId . ':campaign:' . $campaign->id(),
        'outreach.prepare',
        ['prospect_id' => $prospectId, 'proof_campaign_id' => (int) $campaign->id()],
        $prospectId,
      );
    }
    return [
      'campaign_id' => $campaign->get('campaign_id')->value,
      'variant_count' => $expectedCount,
      'directions' => $directions,
      'proof_runner_build_id' => $runner['build_id'] ?? NULL,
    ];
  }

  /** Starts a legacy showcase append or a new detailed six-proof package. */
  private function generateShowcaseProofs(array $job): array {
    $context = (array) ($job['payload'] ?? []);
    $requestId = (int) ($context['website_request_id'] ?? 0);
    $publicId = (string) ($context['website_request_public_id'] ?? '');
    if (!$requestId || $publicId === '') {
      throw new \RuntimeException('Refined proof generation requires the exact website request.');
    }
    // Rehydrate the operational request while retaining the queue's immutable
    // detailed snapshot and asset manifest bytes.
    $context = array_replace($this->portal->websiteRequestProofContext($requestId), $context);
    $context['routine'] = ProofRunnerContractService::ROUTINE;
    $context['website_request_id'] = $requestId;
    $context['website_request_public_id'] = $publicId;
    $isRefinedSix = (string) ($context['requested_profile_id'] ?? $context['proof_profile_id'] ?? '') === 'portal_refined_six.v1'
      || (string) ($context['delivery_class'] ?? '') === 'authenticated_refined'
      || (string) ($context['proof_phase'] ?? '') === 'refined_six';
    if ($isRefinedSix) {
      return $this->generateRefinedSixProofs($job, $context);
    }
    // Retained for historical records only. New detailed intake proof sets
    // use portal_refined_six.v1 below and never append to public a/b/c.
    $context['proof_phase'] = 'showcase';
    $prospectId = (int) ($context['prospect_id'] ?? $job['prospect_id'] ?? 0);
    $prospect = $this->entityTypeManager->getStorage('famtastic_prospect')->load($prospectId);
    if (!$prospect) {
      throw new \RuntimeException('Showcase proof prospect no longer exists.');
    }
    $existing = $this->proofRunner->existingCampaignForSource($prospect, $context);
    $runner = NULL;
    if (!$existing) {
      $runner = $this->proofRunner->prepare($prospect, $context, $job);
      if ($this->proofRunner->isLocalContractFixture($runner)) {
        return [
          'status' => 'local_contract_fixture',
          'build_id' => $runner['build_id'],
          'contract_uri' => $runner['contract_uri'],
          'build_dna_uri' => $runner['build_dna_uri'],
          'message' => 'Fixture validated only; no refinement, email, payment, or publication was created.',
        ];
      }
      $context = $this->proofRunner->applyProfileToContext($context, $runner);
      $context['proof_runner'] = $runner['handoff'];
      $campaign = $this->proofCampaigns->prepareWebsiteRequestShowcase($requestId, $publicId, $context);
    }
    else {
      $campaign = $existing['campaign'];
    }
    return [
      'campaign_id' => (string) $campaign->get('campaign_id')->value,
      'status' => (string) $campaign->get('generation_status')->value,
      'studio_job_id' => (string) $campaign->get('studio_job_id')->value,
      'proof_count' => 6,
      'proof_runner_build_id' => $runner['build_id'] ?? NULL,
    ];
  }

  /** Normalizes the dedicated detailed-intake queue before proof execution. */
  private function generateRefinedSixJob(array $job): array {
    $context = (array) ($job['payload'] ?? []);
    $requestId = (int) ($context['website_request_id'] ?? 0);
    $publicId = (string) ($context['website_request_public_id'] ?? '');
    if (!$requestId || $publicId === '') {
      throw new \RuntimeException('Refined six-proof generation requires the exact website request.');
    }
    $context = array_replace($this->portal->websiteRequestProofContext($requestId), $context);
    $context['routine'] = ProofRunnerContractService::ROUTINE;
    $context['proof_phase'] = 'refined_six';
    $context['requested_profile_id'] = 'portal_refined_six.v1';
    $context['delivery_class'] = 'authenticated_refined';
    $context['website_request_id'] = $requestId;
    $context['website_request_public_id'] = $publicId;
    return $this->generateRefinedSixProofs($job, $context);
  }

  /**
   * Creates a distinct campaign with six fresh detailed-intake directions.
   *
   * This intentionally bypasses prepareWebsiteRequestShowcase(): that method
   * is an append-only legacy d/e/f path, while this profile requires one
   * newly-bound Build DNA record and a single a-f callback for the detailed
   * request source.
   */
  private function generateRefinedSixProofs(array $job, array $context): array {
    $requestId = (int) ($context['website_request_id'] ?? 0);
    $publicId = (string) ($context['website_request_public_id'] ?? '');
    $prospectId = (int) ($context['prospect_id'] ?? $job['prospect_id'] ?? 0);
    if (!$requestId || $publicId === '' || !$prospectId) {
      throw new \RuntimeException('Refined six-proof generation requires an exact request, opaque ID, and prospect.');
    }
    $prospect = $this->entityTypeManager->getStorage('famtastic_prospect')->load($prospectId);
    if (!$prospect) {
      throw new \RuntimeException('Refined proof prospect no longer exists.');
    }
    $context['proof_phase'] = 'refined_six';
    $context['requested_profile_id'] = 'portal_refined_six.v1';
    $context['delivery_class'] = 'authenticated_refined';
    $existing = $this->proofRunner->existingCampaignForSource($prospect, $context);
    $runner = NULL;
    if (!$existing) {
      $runner = $this->proofRunner->prepare($prospect, $context, $job);
      if ($this->proofRunner->isLocalContractFixture($runner)) {
        return [
          'status' => 'local_contract_fixture',
          'build_id' => $runner['build_id'],
          'contract_uri' => $runner['contract_uri'],
          'build_dna_uri' => $runner['build_dna_uri'],
          'message' => 'Fixture validated only; no refined proof, email, payment, or publication was created.',
        ];
      }
      $context = $this->proofRunner->applyProfileToContext($context, $runner);
      $context['proof_runner'] = $runner['handoff'];
      $created = $this->proofCampaigns->createForProspect($prospect, $context);
    }
    else {
      $created = $existing;
    }
    $campaign = $created['campaign'];
    $variants = $created['variants'];
    $expected = ['a', 'b', 'c', 'd', 'e', 'f'];
    if ($variants === [] && $campaign->get('generation_status')->value === 'waiting_callback') {
      return [
        'campaign_id' => (string) $campaign->get('campaign_id')->value,
        'status' => 'waiting_callback',
        'studio_job_id' => (string) $campaign->get('studio_job_id')->value,
        'proof_count' => 6,
        'proof_phase' => 'refined_six',
        'profile_id' => 'portal_refined_six.v1',
        'proof_runner_build_id' => $runner['build_id'] ?? NULL,
      ];
    }
    $directions = array_map(static fn($variant): string => (string) $variant->get('direction_id')->value, $variants);
    sort($directions);
    if ($campaign->get('generation_status')->value !== 'ready' || $directions !== $expected) {
      throw new \RuntimeException('The refined six-proof campaign is not complete and source-verified.');
    }
    return [
      'campaign_id' => (string) $campaign->get('campaign_id')->value,
      'status' => 'ready',
      'proof_count' => 6,
      'proof_phase' => 'refined_six',
      'profile_id' => 'portal_refined_six.v1',
      'proof_runner_build_id' => $runner['build_id'] ?? NULL,
    ];
  }

  /**
   * Dispatches exactly one selected-direction revision through the canonical
   * proof runner. The original campaign stays intact: its a-f artifacts are
   * the immutable baseline, while the revision service owns the isolated
   * candidate and its owner/customer notification gates.
   */
  private function generateRevisionProof(array $job): array {
    $context = (array) ($job['payload'] ?? []);
    if ((string) ($context['routine'] ?? '') !== ProofRunnerContractService::ROUTINE
      || (string) ($context['proof_phase'] ?? '') !== 'revision'
      || (string) ($context['requested_profile_id'] ?? '') !== 'portal_selected_direction_revision.v1'
      || (string) ($context['delivery_class'] ?? '') !== 'authenticated_revision') {
      throw new \RuntimeException('Revision proof jobs must use the canonical selected-direction runner contract.');
    }
    $requestId = (int) ($context['website_request_id'] ?? 0);
    $campaignId = (int) ($context['proof_campaign_id'] ?? 0);
    $revisionPublicId = trim((string) ($context['revision_public_id'] ?? ''));
    $prospectId = (int) ($job['prospect_id'] ?? $context['prospect_id'] ?? 0);
    if ($requestId < 1 || $campaignId < 1 || $revisionPublicId === '' || $prospectId < 1) {
      throw new \RuntimeException('Revision proof generation requires exact request, campaign, revision, and prospect identities.');
    }
    /** @var \Drupal\famtastic_pipeline\Entity\Prospect|null $prospect */
    $prospect = $this->entityTypeManager->getStorage('famtastic_prospect')->load($prospectId);
    /** @var \Drupal\famtastic_pipeline\Entity\ProofCampaign|null $campaign */
    $campaign = $this->entityTypeManager->getStorage('proof_campaign')->load($campaignId);
    if (!$prospect || !$campaign || (int) $campaign->get('prospect_id')->target_id !== (int) $prospect->id()) {
      throw new \RuntimeException('Revision proof source does not resolve to its exact prospect and proof campaign.');
    }

    // Normalize against the durable revision, request, baseline bytes, and
    // baseline Build DNA before any provider call. A retry may only resume a
    // preflight already linked to this exact revision—not a latest campaign.
    $existing = $this->proofRunner->existingCampaignForSource($prospect, $context);
    if ($existing) {
      if ((int) $existing['campaign']->id() !== $campaignId) {
        throw new \RuntimeException('Revision runner source is linked to a different proof campaign.');
      }
      $runner = $this->proofRunner->resumeLinkedHandoffForSource($prospect, $context, $campaign);
      if (!$runner) {
        throw new \RuntimeException('Revision runner has an existing campaign but no resumable immutable preflight handoff.');
      }
    }
    else {
      $runner = $this->proofRunner->prepare($prospect, $context, $job);
      if ($this->proofRunner->isLocalContractFixture($runner)) {
        return [
          'status' => 'local_contract_fixture',
          'build_id' => $runner['build_id'],
          'contract_uri' => $runner['contract_uri'],
          'build_dna_uri' => $runner['build_dna_uri'],
          'revision_public_id' => $revisionPublicId,
          'message' => 'Fixture validated only; no revision candidate, email, payment, or publication was created.',
        ];
      }
    }
    if (!$this->studioClient->isRemote()) {
      throw new \RuntimeException('Selected-direction revision is preflight-ready but no signed remote proof provider is configured.');
    }

    $context = $this->proofRunner->applyProfileToContext($context, $runner);
    // Link before the first HTTP request, closing the fast-callback race while
    // keeping the original campaign proof-ready for its baseline artifacts.
    // A resumed run already has this immutable linkage and reuses its original
    // provider idempotency key rather than minting a competing build.
    $context['proof_runner'] = $existing
      ? (array) $runner['handoff']
      : $this->proofRunner->linkCampaignHandoff((array) $runner['handoff'], $campaign);
    $providerJobId = $this->studioClient->dispatch($prospect, $campaign, $context);
    $this->revisions->markRunnerDispatched(
      $revisionPublicId,
      $providerJobId,
      (string) $runner['build_id'],
      (string) $context['proof_runner']['contract_sha256'],
    );
    return [
      'campaign_id' => (string) $campaign->get('campaign_id')->value,
      'status' => 'waiting_callback',
      'studio_job_id' => $providerJobId,
      'proof_phase' => 'revision',
      'profile_id' => 'portal_selected_direction_revision.v1',
      'revision_public_id' => $revisionPublicId,
      'proof_runner_build_id' => (string) $runner['build_id'],
    ];
  }

  /**
   * Closes the transactional handoff for a customer revision request.
   *
   * A revision cannot silently rewrite a selected proof: it remains a durable
   * owner-reviewed work item until a versioned Site Studio revision callback
   * is available. The job is still material operational evidence, rather than
   * a UI-only state change.
   */
  private function recordRevisionRequest(array $job): array {
    $context = (array) ($job['payload'] ?? []);
    $requestId = (int) ($context['website_request_id'] ?? 0);
    if (!$requestId) {
      throw new \RuntimeException('Revision work item requires a website request.');
    }
    return [
      'website_request_id' => $requestId,
      'selected_direction' => (string) ($context['selected_direction'] ?? ''),
      'status' => 'owner_review_required',
      'revision_version' => (int) ($context['revision_version'] ?? 1),
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
