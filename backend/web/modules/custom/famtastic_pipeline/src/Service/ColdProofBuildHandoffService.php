<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\famtastic_pipeline\Entity\ProofCampaign;

/**
 * Exports exact, source-safe verified-cold proof work bindings for a runner.
 *
 * This is a read-only boundary. It neither claims a job, calls a provider,
 * stages a room, nor sends an email. A runner must return the supplied
 * campaign/job/event tuple on callback and copy build_dna_run unchanged into
 * its final immutable Build DNA `run` object.
 */
final class ColdProofBuildHandoffService {

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entities,
    private readonly TimeInterface $time,
    private readonly PublicPreviewDeliveryService $previews,
  ) {}

  /**
   * @return array{schema:string,generated_at:string,source_lane:string,deliveries:list<array>,sha256:string}
   */
  public function export(array $deliveryIds): array {
    $ids = array_values(array_unique(array_filter(array_map('intval', $deliveryIds), static fn (int $id): bool => $id > 0)));
    sort($ids, SORT_NUMERIC);
    if ($ids === [] || count($ids) > 10) {
      throw new \InvalidArgumentException('Verified-cold handoff export requires between one and ten exact delivery IDs.');
    }
    $deliveries = [];
    foreach ($ids as $deliveryId) {
      $delivery = $this->database->select('famtastic_preview_delivery', 'p')->fields('p')
        ->condition('id', $deliveryId)->range(0, 1)->execute()->fetchAssoc();
      if (!$delivery || (string) ($delivery['source_lane'] ?? '') !== 'verified_cold') {
        throw new \RuntimeException('Handoff export accepts only exact verified-cold public preview deliveries.');
      }
      if (!in_array((string) $delivery['state'], ['preview_requested', 'research_ready', 'proof_ready_owner_review'], TRUE)) {
        throw new \RuntimeException('Verified-cold handoff export requires a pre-delivery proof state.');
      }
      $proofCampaignId = (int) ($delivery['proof_campaign_id'] ?? 0);
      /** @var \Drupal\famtastic_pipeline\Entity\ProofCampaign|null $campaign */
      $campaign = $proofCampaignId ? $this->entities->getStorage('proof_campaign')->load($proofCampaignId) : NULL;
      if (!$campaign instanceof ProofCampaign || (int) $campaign->get('prospect_id')->target_id !== (int) $delivery['prospect_id']) {
        throw new \RuntimeException('Verified-cold handoff export requires an exact delivery-bound proof campaign.');
      }
      $ingress = $this->database->select('famtastic_cold_proof_ingress', 'i')->fields('i')
        ->condition('preview_delivery_id', $deliveryId)
        ->condition('source_lane', 'verified_cold')
        ->range(0, 1)->execute()->fetchAssoc();
      $jobDatabaseId = (int) ($ingress['proof_job_id'] ?? 0);
      $job = $jobDatabaseId ? $this->database->select('famtastic_job', 'j')->fields('j')
        ->condition('id', $jobDatabaseId)->range(0, 1)->execute()->fetchAssoc() : FALSE;
      if (!$job || (string) $job['job_type'] !== 'public_preview.generate') {
        throw new \RuntimeException('Verified-cold handoff export requires its dedicated public-preview job.');
      }
      try {
        $jobPayload = json_decode((string) $job['payload'], TRUE, 32, JSON_THROW_ON_ERROR);
      }
      catch (\Throwable) {
        throw new \RuntimeException('Verified-cold handoff export found an unreadable job payload.');
      }
      $context = $this->previews->publicIntakeProofContext($deliveryId);
      $run = (array) ($jobPayload['build_dna_run'] ?? $context['build_dna_run'] ?? []);
      $campaignId = (string) $campaign->get('campaign_id')->value;
      $jobId = trim((string) ($jobPayload['job_id'] ?? ''));
      $callbackEventId = trim((string) ($jobPayload['callback_event_id'] ?? ''));
      $runStartedAt = trim((string) ($jobPayload['run_started_at'] ?? ''));
      if (
        (int) ($jobPayload['prospect_id'] ?? 0) !== (int) $delivery['prospect_id']
        || (int) ($jobPayload['public_preview_delivery_id'] ?? 0) !== $deliveryId
        || (int) ($jobPayload['proof_campaign_id'] ?? 0) !== $proofCampaignId
        || !hash_equals($campaignId, (string) ($jobPayload['campaign_id'] ?? ''))
        || !hash_equals('verified_cold', (string) ($jobPayload['source_lane'] ?? ''))
        || !hash_equals((string) $campaign->get('studio_job_id')->value, $jobId)
        || !$this->safeReference($jobId)
        || !$this->safeReference($callbackEventId)
        || !$this->validIsoTime($runStartedAt)
        || (int) ($run['prospect_id'] ?? 0) !== (int) $delivery['prospect_id']
        || (int) ($run['public_preview_delivery_id'] ?? 0) !== $deliveryId
        || (int) ($run['proof_campaign_id'] ?? 0) !== $proofCampaignId
        || !hash_equals($campaignId, (string) ($run['campaign_id'] ?? ''))
        || !hash_equals('verified_cold', (string) ($run['source_lane'] ?? ''))
        || !hash_equals($jobId, (string) ($run['job_id'] ?? ''))
        || !hash_equals($callbackEventId, (string) ($run['callback_event_id'] ?? ''))
        || !hash_equals($runStartedAt, (string) ($run['run_started_at'] ?? ''))
      ) {
        throw new \RuntimeException('Verified-cold handoff identities no longer match their durable delivery/job contract.');
      }
      $profile = (array) ($context['public_preview_proof_profile'] ?? []);
      $directions = (array) ($profile['directions'] ?? []);
      if ((int) ($profile['direction_count'] ?? 0) < 1 || count($directions) !== (int) $profile['direction_count']) {
        throw new \RuntimeException('Verified-cold handoff has an invalid frozen proof profile.');
      }
      $deliveries[] = [
        'prospect_id' => (int) $delivery['prospect_id'],
        'public_preview_delivery_id' => $deliveryId,
        'proof_campaign_id' => $proofCampaignId,
        'campaign_id' => $campaignId,
        // Exact canonical field names match the runtime binder. `job.id` is
        // retained below only as an internal database audit reference.
        'job_id' => $jobId,
        'callback_event_id' => $callbackEventId,
        'run_started_at' => $runStartedAt,
        'source_lane' => 'verified_cold',
        'proof_profile' => [
          'id' => (string) ($profile['id'] ?? ''),
          'direction_count' => (int) $profile['direction_count'],
          'directions' => $directions,
        ],
        'build_dna_run' => $run,
        'callback_contract' => [
          'campaign_id' => $campaignId,
          'job_id' => $jobId,
          'event_id' => $callbackEventId,
          'asset_contract' => 'variants[].assets[] requires asset_id, relative_path, media_type, base64, sha256; at least one signed asset per direction',
        ],
        // The builder receives only the public allowlist, never recipient
        // contact data, campaign approval, outbox, or share credentials.
        'public_brief' => (array) ($context['website_discovery_v3'] ?? []),
        'source_evidence' => [
          'source_record_id' => (string) ($ingress['source_record_id'] ?? ''),
          'evidence_hash' => (string) ($ingress['evidence_hash'] ?? ''),
          'source_url' => (string) ($ingress['source_url'] ?? ''),
          'source_timeframe' => (string) ($ingress['source_timeframe'] ?? ''),
        ],
        'job' => [
          'id' => $jobDatabaseId,
          'job_key' => (string) $job['job_key'],
          'job_type' => (string) $job['job_type'],
          'status' => (string) $job['status'],
        ],
      ];
    }
    $bundle = [
      'schema' => 'famtastic.verified-cold-proof-handoff.v1',
      'generated_at' => gmdate(DATE_ATOM, $this->time->getRequestTime()),
      'source_lane' => 'verified_cold',
      'deliveries' => $deliveries,
    ];
    $bundle['sha256'] = hash('sha256', json_encode($bundle, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    return $bundle;
  }

  /** Runtime binder syntax: an opaque, non-local callback/job identifier. */
  private function safeReference(string $value): bool {
    return preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{1,254}$/', $value) === 1
      && preg_match('/^(?:local-|beauty-proof:)/i', $value) !== 1;
  }

  /** This value was stored on job creation, so export never creates a clock value. */
  private function validIsoTime(string $value): bool {
    return $value !== '' && strlen($value) <= 80 && strtotime($value) !== FALSE;
  }

}
