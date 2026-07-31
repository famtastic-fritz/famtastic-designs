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
  ) {}

  /**
   * Runs up to the requested number of available jobs.
   */
  public function run(int $limit = 25, ?string $jobType = NULL): array {
    $limit = max(1, min(100, $limit));
    $results = [];
    for ($i = 0; $i < $limit; $i++) {
      $job = $this->ledger->claimNext($jobType);
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
      default => throw new \RuntimeException('Unsupported job type: ' . $job['job_type']),
    };
  }

  /**
   * Produces exactly three isolated proof variants or throws for retry.
   */
  private function generateProofs(array $job): array {
    $prospectId = (int) ($job['payload']['prospect_id'] ?? $job['prospect_id'] ?? 0);
    $prospect = $this->entityTypeManager->getStorage('famtastic_prospect')->load($prospectId);
    if (!$prospect) {
      throw new \RuntimeException('Prospect no longer exists.');
    }
    $existing = $this->proofCampaigns->getForProspect($prospect);
    $created = $existing ?: $this->proofCampaigns->createForProspect($prospect);
    $variants = $created['variants'];
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
    $this->ledger->enqueue(
      'outreach.prepare:prospect:' . $prospectId . ':campaign:' . $campaign->id(),
      'outreach.prepare',
      ['prospect_id' => $prospectId, 'proof_campaign_id' => (int) $campaign->id()],
      $prospectId,
    );
    return [
      'campaign_id' => $campaign->get('campaign_id')->value,
      'variant_count' => 3,
      'directions' => $directions,
    ];
  }

}
