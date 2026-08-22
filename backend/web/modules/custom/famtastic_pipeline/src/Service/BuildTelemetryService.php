<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\famtastic_pipeline\Entity\Project;
use Drupal\famtastic_pipeline\Entity\ProofCampaign;
use Drupal\famtastic_pipeline\Entity\Prospect;

/**
 * Persists operator-visible build, prompt, agent, task, and artifact telemetry.
 */
final class BuildTelemetryService {

  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Records a completed deterministic proof build.
   */
  public function recordPilotProof(Prospect $prospect, ProofCampaign $campaign, array $variants): int {
    $manifest = array_map(static fn ($variant): array => [
      'direction' => (string) $variant->get('direction_id')->value,
      'artifact_path' => (string) $variant->get('artifact_path')->value,
      'thumbnail_path' => (string) $variant->get('thumbnail_path')->value,
      'preview_url' => (string) $variant->get('preview_url')->value,
      'design_dna' => json_decode((string) $variant->get('design_dna')->value, TRUE),
    ], $variants);
    $input = [
      'business_name' => $prospect->label(),
      'category' => (string) $prospect->get('business_category')->value,
      'description' => (string) $prospect->get('business_description')->value,
      'service_area' => (string) $prospect->get('service_area')->value,
      'public_phone' => (string) $prospect->get('public_phone')->value,
      'required_directions' => ['a', 'b', 'c'],
      'media_policy' => 'No images and no image placeholders.',
    ];
    return $this->record([
      'build_key' => 'proof:' . $campaign->get('campaign_id')->value . ':no-image-pilot-v1',
      'campaign_key' => (string) $prospect->get('campaign')->value,
      'prospect_id' => (int) $prospect->id(),
      'proof_campaign_id' => (int) $campaign->id(),
      'flow_key' => 'lead-to-proof',
      'task_key' => 'proof.generate',
      'provider' => 'drupal_deterministic_renderer',
      'agent_name' => 'none',
      'status' => 'completed',
      'prompt_snapshot' => 'Render three category-aware, script-free landing-page directions. Use only supplied public business facts. Do not invent reviews, prices, inventory, performance claims, images, or image placeholders.',
      'input_snapshot' => $this->json($input),
      'output_manifest' => $this->json($manifest),
      'artifact_checksum' => hash('sha256', $this->json($manifest)),
      'completed_at' => $this->time->getRequestTime(),
    ]);
  }

  /**
   * Records a Site Studio request export before local generation begins.
   */
  public function recordStudioRequest(Prospect $prospect, Project $project, array $json, string $brief, array $handoff): int {
    return $this->record([
      'build_key' => 'project:' . $project->id() . ':site-studio-request',
      'campaign_key' => (string) $prospect->get('campaign')->value,
      'prospect_id' => (int) $prospect->id(),
      'project_id' => (int) $project->id(),
      'flow_key' => 'site-studio-local-handoff',
      'task_key' => 'request.export',
      'provider' => 'local_file_export',
      'agent_name' => 'unassigned',
      'status' => (string) ($handoff['status'] ?? 'exported'),
      'prompt_snapshot' => $brief,
      'input_snapshot' => $this->json($json),
      'output_manifest' => $this->json($handoff),
      'completed_at' => $this->time->getRequestTime(),
    ]);
  }

  /**
   * Records one completed local Site Studio callback/import.
   */
  public function recordStudioProof(Prospect $prospect, ProofCampaign $campaign, array $variants, array $source = []): int {
    $manifest = array_map(static fn ($variant): array => [
      'direction' => (string) $variant->get('direction_id')->value,
      'artifact_path' => (string) $variant->get('artifact_path')->value,
      'thumbnail_path' => (string) $variant->get('thumbnail_path')->value,
      'preview_url' => (string) $variant->get('preview_url')->value,
      'design_dna' => json_decode((string) $variant->get('design_dna')->value, TRUE),
    ], $variants);
    $suffix = preg_replace('/[^a-z0-9_-]+/', '-', strtolower((string) ($source['build_key_suffix'] ?? 'site-studio')));
    $suffix = trim((string) $suffix, '-') ?: 'site-studio';
    return $this->record([
      'build_key' => 'proof:' . $campaign->get('campaign_id')->value . ':' . $suffix,
      'campaign_key' => (string) $prospect->get('campaign')->value,
      'prospect_id' => (int) $prospect->id(),
      'proof_campaign_id' => (int) $campaign->id(),
      'flow_key' => (string) ($source['flow_key'] ?? 'site-studio-local-promotion'),
      'task_key' => (string) ($source['task_key'] ?? 'proof.generate'),
      'provider' => (string) ($source['provider'] ?? 'site_studio_local'),
      'agent_name' => (string) ($source['agent_name'] ?? 'shay'),
      'status' => 'completed',
      'prompt_snapshot' => (string) ($source['prompt_snapshot'] ?? ''),
      'input_snapshot' => $this->json($source['input_snapshot'] ?? []),
      'output_manifest' => $this->json($manifest),
      'source_sha' => (string) ($source['source_sha'] ?? ''),
      'artifact_checksum' => hash('sha256', $this->json($manifest)),
      'completed_at' => $this->time->getRequestTime(),
    ]);
  }

  /**
   * Projects one immutable Build DNA manifest into the searchable run ledger.
   *
   * The complete manifest stays in output_manifest. This table is deliberately
   * a retrieval projection, not a competing customer, project, or artifact
   * source of truth.
   */
  public function recordBuildDna(array $dna): int {
    if (($dna['schema'] ?? '') !== 'famtastic.build-dna.v1') {
      throw new \InvalidArgumentException('Unsupported Build DNA schema.');
    }
    $buildId = trim((string) ($dna['build_id'] ?? ''));
    if ($buildId === '' || strlen($buildId) > 170) {
      throw new \InvalidArgumentException('Build DNA build_id is required and must be at most 170 characters.');
    }
    $run = is_array($dna['run'] ?? NULL) ? $dna['run'] : [];
    $repository = is_array($dna['repository'] ?? NULL) ? $dna['repository'] : [];
    $stages = is_array($dna['stages'] ?? NULL) ? $dna['stages'] : [];
    if (!$stages) {
      throw new \InvalidArgumentException('Build DNA requires at least one stage.');
    }

    $firstExecution = [];
    foreach ($stages as $stage) {
      if (is_array($stage['execution'] ?? NULL)) {
        $firstExecution = $stage['execution'];
        break;
      }
    }
    $provider = is_array($firstExecution['provider'] ?? NULL) ? $firstExecution['provider'] : [];
    $model = is_array($firstExecution['model'] ?? NULL) ? $firstExecution['model'] : [];
    $started = $this->timestamp($run['started_at'] ?? $dna['created_at'] ?? NULL) ?? $this->time->getRequestTime();
    $completed = $this->timestamp($run['completed_at'] ?? NULL);
    $projectId = $this->numericId($run['project_id'] ?? NULL);
    $prospectId = $this->numericId($run['prospect_id'] ?? NULL);
    $proofCampaignId = $this->numericId($run['proof_campaign_id'] ?? NULL);
    $promptArtifacts = [];
    foreach ($stages as $stage) {
      $prompt = is_array($stage['execution']['prompt'] ?? NULL) ? $stage['execution']['prompt'] : [];
      if ($prompt) {
        $promptArtifacts[] = [
          'stage_id' => (string) ($stage['stage_id'] ?? ''),
          'artifact' => (string) ($prompt['artifact'] ?? ''),
          'sha256' => (string) ($prompt['sha256'] ?? ''),
          'field' => (string) ($prompt['field'] ?? ''),
        ];
      }
    }

    return $this->record([
      'build_key' => 'build-dna:' . $buildId,
      'campaign_key' => substr((string) ($run['campaign_id'] ?? ''), 0, 128),
      'prospect_id' => $prospectId,
      'proof_campaign_id' => $proofCampaignId,
      'project_id' => $projectId,
      'flow_key' => 'build-dna',
      'task_key' => substr((string) ($dna['recipe']['routine'] ?? 'build.record'), 0, 128),
      'provider' => substr((string) ($provider['id'] ?? 'unresolved'), 0, 128),
      'agent_name' => substr((string) ($model['id'] ?? $model['status'] ?? 'unresolved'), 0, 128),
      // A source-bound preflight is intentionally not a completed creative
      // build. The ledger must preserve that distinction so an owner cannot
      // mistake a runner-contract receipt for final proof evidence.
      'status' => $this->buildDnaStatus($run),
      'prompt_snapshot' => $this->json(['prompt_artifacts' => $promptArtifacts]),
      'input_snapshot' => $this->json([
        'run' => $run,
        'recipe' => $dna['recipe'] ?? [],
        'lineage' => $dna['lineage'] ?? [],
      ]),
      'output_manifest' => $this->json($dna),
      'source_sha' => substr((string) ($repository['revision'] ?? ''), 0, 64),
      'artifact_checksum' => hash('sha256', $this->json($dna)),
      'started_at' => $started,
      'completed_at' => $completed,
    ]);
  }

  /** Returns the registered projection used to correlate a provider callback. */
  public function loadBuildDna(string $buildId): ?array {
    $buildId = trim($buildId);
    if ($buildId === '') {
      return NULL;
    }
    $row = $this->database->select('famtastic_build_run', 'b')
      ->fields('b', ['id', 'build_key', 'status', 'artifact_checksum', 'output_manifest', 'prospect_id', 'proof_campaign_id', 'project_id'])
      ->condition('build_key', 'build-dna:' . $buildId)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!$row) {
      return NULL;
    }
    try {
      $manifest = json_decode((string) $row['output_manifest'], TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\Throwable) {
      return NULL;
    }
    return [
      'record' => [
        'id' => (int) $row['id'],
        'build_key' => (string) $row['build_key'],
        'status' => (string) $row['status'],
        'artifact_checksum' => (string) $row['artifact_checksum'],
        'prospect_id' => $row['prospect_id'] === NULL ? NULL : (int) $row['prospect_id'],
        'proof_campaign_id' => $row['proof_campaign_id'] === NULL ? NULL : (int) $row['proof_campaign_id'],
        'project_id' => $row['project_id'] === NULL ? NULL : (int) $row['project_id'],
      ],
      'manifest' => $manifest,
    ];
  }

  /** Returns the current runner-bound Build DNA record for one proof campaign. */
  public function loadBuildDnaForCampaign(int $proofCampaignId): ?array {
    if ($proofCampaignId < 1) {
      return NULL;
    }
    $row = $this->database->select('famtastic_build_run', 'b')
      ->fields('b', ['id', 'build_key', 'status', 'artifact_checksum', 'output_manifest', 'prospect_id', 'proof_campaign_id', 'project_id'])
      ->condition('proof_campaign_id', $proofCampaignId)
      ->condition('build_key', 'build-dna:%', 'LIKE')
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!$row) {
      return NULL;
    }
    $buildId = preg_replace('/^build-dna:/', '', (string) $row['build_key']);
    return $this->loadBuildDna((string) $buildId);
  }

  /**
   * Finds the current Build DNA record for an immutable intake correlation.
   *
   * The source fields live in the canonical manifest rather than in a second
   * database column so changing a schema is not required to add a new
   * correlation key. This is intentionally used only to resume a canonical
   * proof job; it never performs a broad "latest prospect campaign" lookup.
   */
  public function loadBuildDnaForSource(array $source, string $routine = 'website_proof.generate.v1'): ?array {
    $prospectId = $this->numericId($source['prospect_id'] ?? NULL);
    $sourceType = trim((string) ($source['type'] ?? ''));
    if (!$prospectId || $sourceType === '') {
      return NULL;
    }
    $rows = $this->database->select('famtastic_build_run', 'b')
      ->fields('b', ['build_key', 'output_manifest'])
      ->condition('prospect_id', $prospectId)
      ->condition('build_key', 'build-dna:%', 'LIKE')
      ->orderBy('id', 'DESC')
      ->execute()
      ->fetchAllAssoc('build_key');
    $keys = [
      'prospect_id',
      'type',
      'proof_phase',
      'public_preview_delivery_id',
      'intake_id',
      'website_request_id',
      'website_request_public_id',
    ];
    foreach ($rows as $row) {
      try {
        $manifest = json_decode((string) $row->output_manifest, TRUE, 512, JSON_THROW_ON_ERROR);
      }
      catch (\Throwable) {
        continue;
      }
      $candidate = (array) ($manifest['run']['source_correlation'] ?? []);
      if (($manifest['recipe']['routine'] ?? '') !== $routine) {
        continue;
      }
      $matches = TRUE;
      foreach ($keys as $key) {
        if (array_key_exists($key, $source) && (string) ($candidate[$key] ?? '') !== (string) $source[$key]) {
          $matches = FALSE;
          break;
        }
      }
      if (!$matches) {
        continue;
      }
      $buildId = preg_replace('/^build-dna:/', '', (string) $row->build_key);
      return $this->loadBuildDna((string) $buildId);
    }
    return NULL;
  }

  /** TRUE only when the campaign has a final, not-preflight Build DNA record. */
  public function hasCompletedBuildDnaForCampaign(int $proofCampaignId, array $requiredSource = [], ?string $routine = NULL): bool {
    $record = $this->loadBuildDnaForCampaign($proofCampaignId);
    if (!$record || ($record['record']['status'] ?? '') !== 'completed') {
      return FALSE;
    }
    $manifest = (array) $record['manifest'];
    if (($manifest['schema'] ?? '') !== 'famtastic.build-dna.v1'
      || in_array((string) ($manifest['classification'] ?? ''), ['local_contract_fixture', 'proof_runner_preflight'], TRUE)
      || !in_array(mb_strtolower((string) ($manifest['run']['status'] ?? '')), ['passed', 'complete', 'completed'], TRUE)) {
      return FALSE;
    }
    if ($routine !== NULL && ($manifest['recipe']['routine'] ?? '') !== $routine) {
      return FALSE;
    }
    $actualSource = (array) ($manifest['run']['source_correlation'] ?? []);
    foreach ($requiredSource as $key => $expected) {
      if ((string) ($actualSource[$key] ?? '') !== (string) $expected) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Idempotently stores a build-run snapshot.
   */
  public function record(array $values): int {
    $now = $this->time->getRequestTime();
    $key = trim((string) ($values['build_key'] ?? ''));
    if ($key === '') {
      throw new \InvalidArgumentException('Build telemetry requires a build key.');
    }
    $fields = [
      'campaign_key' => '',
      'prospect_id' => NULL,
      'proof_campaign_id' => NULL,
      'project_id' => NULL,
      'flow_key' => '',
      'task_key' => '',
      'provider' => '',
      'agent_name' => '',
      'status' => 'unknown',
      'prompt_snapshot' => NULL,
      'input_snapshot' => NULL,
      'output_manifest' => NULL,
      'source_sha' => $this->releaseSha(),
      'artifact_checksum' => '',
      'error' => NULL,
      'started_at' => $now,
      'completed_at' => NULL,
      'changed' => $now,
    ];
    foreach ($fields as $field => $default) {
      if (array_key_exists($field, $values)) {
        $fields[$field] = $values[$field];
      }
    }
    $existing = $this->database->select('famtastic_build_run', 'b')
      ->fields('b', ['id'])
      ->condition('build_key', $key)
      ->execute()
      ->fetchField();
    if ($existing) {
      $this->database->update('famtastic_build_run')
        ->fields($fields)
        ->condition('id', (int) $existing)
        ->execute();
      return (int) $existing;
    }
    $fields['build_key'] = $key;
    $fields['created'] = $now;
    return (int) $this->database->insert('famtastic_build_run')->fields($fields)->execute();
  }

  private function json(mixed $value): string {
    return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
  }

  private function timestamp(mixed $value): ?int {
    if (!is_string($value) || trim($value) === '') {
      return NULL;
    }
    $timestamp = strtotime($value);
    return $timestamp === FALSE ? NULL : $timestamp;
  }

  private function numericId(mixed $value): ?int {
    if (is_int($value) || (is_string($value) && ctype_digit($value))) {
      return (int) $value;
    }
    return NULL;
  }

  /** Maps honest Build DNA lifecycle facts to the searchable projection. */
  private function buildDnaStatus(array $run): string {
    $status = mb_strtolower(trim((string) ($run['status'] ?? 'completed')));
    return match ($status) {
      'passed', 'complete', 'completed' => 'completed',
      'preflight_ready', 'dispatched_waiting_callback', 'waiting_for_runner', 'queued' => 'preflight',
      'gated' => 'gated',
      'failed' => 'failed',
      'partial' => 'partial',
      'local_contract_fixture_validated' => 'fixture',
      default => 'unknown',
    };
  }

  private function releaseSha(): string {
    $marker = dirname(\Drupal::root()) . '/.backend-release';
    if (is_readable($marker)) {
      $contents = (string) file_get_contents($marker);
      if (preg_match('/^commit=([a-f0-9]{40})$/m', $contents, $matches)) {
        return $matches[1];
      }
    }
    return '';
  }

}
