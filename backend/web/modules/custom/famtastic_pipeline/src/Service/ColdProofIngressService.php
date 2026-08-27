<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\famtastic_pipeline\Entity\Intake;

/**
 * Persists a verified-source cold cohort into the owner-gated proof lane.
 *
 * This is intentionally not a campaign-email importer. It can create only
 * source/audit records, an anonymous intake, a public-preview delivery, and
 * the delivery-scoped initial proof job. Owner staging and exact-ID delivery
 * remain separate operations.
 */
final class ColdProofIngressService {

  private const MAX_SEED_BYTES = 10 * 1024 * 1024;

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entities,
    private readonly TimeInterface $time,
    private readonly LeadIngestionService $leads,
    private readonly PublicPreviewDeliveryService $previews,
    private readonly ProofCampaignService $proofCampaigns,
    private readonly ProofCohortProfileResolverInterface $profiles,
    private readonly ColdProofCampaignSeedValidator $validator,
    private readonly OperationalLedger $ledger,
  ) {}

  /**
   * Validates or persists a source-backed seed.
   *
   * @return array{schema_version:string,dry_run:bool,cohort:array,total:int,counts:array,leads:list<array>}
   */
  public function importSeed(string $path, bool $dryRun = FALSE): array {
    $seed = $this->readSeed($path);
    $profile = $this->profiles->resolveAnonymous((string) ($seed['cohort']['package_profile'] ?? ''));
    // Validator always includes package_profile (possibly empty); use a
    // replacement merge so the resolved default is what gets frozen.
    $cohort = array_merge($seed['cohort'], [
      'package_profile' => $profile['id'],
      'direction_count' => $profile['direction_count'],
      'direction_contract' => $profile['directions'],
      'source_lane' => 'verified_cold',
    ]);
    $results = [];
    if ($dryRun) {
      foreach ($seed['leads'] as $lead) {
        $result = $this->leads->importRowWithoutGenericProof(
          $this->leadImportRow($lead),
          $cohort['source_name'],
          $cohort['campaign_key'],
          TRUE,
          'public_preview',
          TRUE,
        );
        $results[] = $this->result($lead, $result, NULL, NULL, NULL, NULL, 'would_not_write', $profile['id']);
      }
      return $this->report($cohort, TRUE, $results);
    }

    $transaction = $this->database->startTransaction();
    try {
      $campaignId = $this->leads->ensureCampaignForChannel($cohort['campaign_key'], $cohort['source_name'], 'public_preview');
      $cohortRow = $this->ensureCohort($cohort, $campaignId);
      // Drupal database drivers return scalar query results as strings. Cast
      // once at the persistence boundary so the idempotency key is identical
      // on every driver and strict downstream signatures cannot receive a
      // database string.
      $cohortId = (int) ($cohortRow['id'] ?? 0);
      if ($cohortId < 1) {
        throw new \RuntimeException('Cold proof cohort persistence did not return a valid ID.');
      }
      foreach ($seed['leads'] as $lead) {
        $existing = $this->existingIngress($cohortId, $lead['source_record_id'], $lead['evidence_hash']);
        if ($existing) {
          $results[] = $this->result($lead, [
            'status' => 'duplicate', 'score' => 0, 'target_offer' => '', 'prospect_id' => $existing['prospect_id'] ?: NULL,
            'dedupe_key' => '', 'reasons' => ['This exact verified-source lead was already ingressed.'],
          ], $existing['intake_id'] ?: NULL, $existing['preview_delivery_id'] ?: NULL, $existing['proof_campaign_id'] ?: NULL, $existing['proof_job_id'] ?: NULL, 'already_ingressed', $profile['id']);
          continue;
        }
        $import = $this->leads->importRowWithoutGenericProof(
          $this->leadImportRow($lead),
          $cohort['source_name'],
          $cohort['campaign_key'],
          FALSE,
          'public_preview',
          TRUE,
        );
        $intakeId = NULL;
        $deliveryId = NULL;
        $proofCampaignId = NULL;
        $publicCampaignId = NULL;
        $jobId = NULL;
        $runtimeRun = [];
        $status = (string) $import['status'];
        if ($status === 'qualified' && !empty($import['prospect_id'])) {
          $intakeId = $this->createAnonymousIntake((int) $import['prospect_id'], $lead);
          $delivery = $this->previews->createForPublicLead(
            (int) $import['prospect_id'],
            $intakeId,
            $profile['id'],
            $lead['scheduled_release_at'],
            'verified_cold',
          );
          $deliveryId = (int) $delivery['id'];
          $prospect = $this->entities->getStorage('famtastic_prospect')->load((int) $import['prospect_id']);
          if (!$prospect) {
            throw new \RuntimeException('Cold proof prospect could not be reloaded before campaign binding.');
          }
          // Freeze canonical campaign identities before the job exists. The
          // worker and external runner receive these exact IDs rather than
          // inventing values that no callback or Build DNA projection can bind.
          $proofCampaign = $this->proofCampaigns->createQueuedPublicPreviewCampaign(
            $prospect,
            $this->previews->publicIntakeProofContext($deliveryId),
          );
          $proofCampaignId = (int) $proofCampaign->id();
          $publicCampaignId = (string) $proofCampaign->get('campaign_id')->value;
          $jobId = $this->previews->queueInitialProof($deliveryId);
          // Reload the persisted payload after enqueue. If another importer
          // won the idempotency race, this prevents our audit event from
          // describing a losing process's generated callback identity.
          $runtimeRun = (array) ($this->previews->publicIntakeProofContext($deliveryId)['build_dna_run'] ?? []);
          $status = 'preview_requested';
        }
        $this->insertIngress($cohortId, $lead, $import, $intakeId, $deliveryId, $proofCampaignId, $jobId, $status);
        if ($deliveryId && $jobId) {
          $this->ledger->recordEvent(
            'cold-proof.ingressed:' . $cohortRow['cohort_key'] . ':' . $lead['source_record_id'] . ':' . $lead['evidence_hash'],
            'cold_proof.ingressed',
            [
              'cohort_key' => $cohortRow['cohort_key'],
              'package_profile' => $profile['id'],
              'direction_count' => $profile['direction_count'],
              'preview_delivery_id' => $deliveryId,
              'proof_campaign_id' => $proofCampaignId,
              'campaign_id' => $publicCampaignId,
              'proof_job_id' => $jobId,
              'job_id' => (string) ($runtimeRun['job_id'] ?? ''),
              'callback_event_id' => (string) ($runtimeRun['callback_event_id'] ?? ''),
              'run_started_at' => (string) ($runtimeRun['run_started_at'] ?? ''),
              'scheduled_release_at' => $lead['scheduled_release_at'],
              'evidence_hash' => $lead['evidence_hash'],
              'source_lane' => 'verified_cold',
            ],
            (int) $import['prospect_id'],
            $campaignId,
          );
        }
        $reportedImport = $import;
        $reportedImport['status'] = $status;
        $results[] = $this->result($lead, $reportedImport, $intakeId, $deliveryId, $proofCampaignId, $jobId, $deliveryId ? 'preview_job_queued' : 'not_eligible_for_preview', $profile['id'], $publicCampaignId);
      }
    }
    catch (\Throwable $error) {
      $transaction->rollBack();
      throw $error;
    }
    return $this->report($cohort, FALSE, $results);
  }

  /** Returns a bounded, structured dry-run report without reading secrets. */
  private function report(array $cohort, bool $dryRun, array $results): array {
    $counts = [];
    foreach ($results as $result) {
      $status = (string) $result['status'];
      $counts[$status] = ($counts[$status] ?? 0) + 1;
    }
    ksort($counts);
    return [
      'schema_version' => ColdProofCampaignSeedValidator::SCHEMA_VERSION,
      'dry_run' => $dryRun,
      'cohort' => [
        'cohort_key' => $cohort['cohort_key'],
        'campaign_key' => $cohort['campaign_key'],
        'source_name' => $cohort['source_name'],
        'package_profile' => $cohort['package_profile'],
        'direction_count' => $cohort['direction_count'],
        'source_lane' => $cohort['source_lane'],
        'scheduled_release_at' => $cohort['scheduled_release_at'],
      ],
      'total' => count($results),
      'counts' => $counts,
      'leads' => $results,
    ];
  }

  private function readSeed(string $path): array {
    if (!is_file($path) || !is_readable($path)) {
      throw new \InvalidArgumentException('Cold proof seed is not readable.');
    }
    if (filesize($path) > self::MAX_SEED_BYTES) {
      throw new \InvalidArgumentException('Cold proof seed exceeds the 10 MB limit.');
    }
    try {
      $decoded = json_decode((string) file_get_contents($path), TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\Throwable) {
      throw new \InvalidArgumentException('Cold proof seed must be valid JSON.');
    }
    if (!is_array($decoded)) {
      throw new \InvalidArgumentException('Cold proof seed must be a JSON object.');
    }
    return $this->validator->validate($decoded);
  }

  private function leadImportRow(array $lead): array {
    return [
      'source_record_id' => $lead['source_record_id'],
      'source_lane' => 'verified_cold',
      'business_name' => $lead['business_name'],
      'business_category' => $lead['business_category'],
      'business_description' => $lead['business_description'],
      'address' => $lead['address'],
      'service_area' => $lead['service_area'],
      'email' => $lead['email'],
      'phone' => $lead['phone'],
      'website_url' => $lead['website_url'],
      'verified_website_observation' => $lead['website_observation']['status'],
      'website_quality' => $lead['website_quality'],
      'upgrade_signal' => $lead['upgrade_signal'],
    ];
  }

  /** Persists a redacted initial intake owned by the selected prospect. */
  private function createAnonymousIntake(int $prospectId, array $lead): int {
    /** @var \Drupal\famtastic_pipeline\Entity\Intake $intake */
    $intake = $this->entities->getStorage('famtastic_intake')->create([
      'prospect_ref' => $prospectId,
      'primary_goal' => 'Explore a public website direction for ' . $lead['business_name'],
      'primary_cta' => 'Create a free project space',
      'services' => $lead['corroborated_fact'],
      'about' => $lead['proof_teaser'],
      'differentiators' => $lead['corroborated_fact'],
      'reference_sites' => $lead['public_source']['url'],
      'existing_domain' => (string) (parse_url($lead['website_url'], PHP_URL_HOST) ?: ''),
      'existing_website' => $lead['website_url'],
      'info_to_avoid' => 'Use only the corroborated fact and verified public source supplied in this cold cohort. Do not infer services, outcomes, staff, pricing, availability, testimonials, or operating claims.',
      'submitted_at' => $this->time->getRequestTime(),
    ]);
    $intake->save();
    return (int) $intake->id();
  }

  /** Creates one immutable cohort configuration or proves an existing match. */
  private function ensureCohort(array $cohort, int $campaignId): array {
    $snapshot = json_encode([
      'package_profile' => $cohort['package_profile'],
      'direction_count' => $cohort['direction_count'],
      'direction_contract' => $cohort['direction_contract'],
      'source_lane' => $cohort['source_lane'],
    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $hash = hash('sha256', $snapshot);
    $existing = $this->database->select('famtastic_cold_proof_cohort', 'c')->fields('c')
      ->condition('cohort_key', $cohort['cohort_key'])->range(0, 1)->execute()->fetchAssoc();
    if ($existing) {
      $matches = (int) $existing['campaign_id'] === $campaignId
        && hash_equals((string) $existing['profile_snapshot_hash'], $hash)
        && (int) ($existing['scheduled_release_at'] ?? 0) === (int) ($cohort['scheduled_release_at'] ?? 0);
      if (!$matches) {
        throw new \RuntimeException('A cold proof cohort key is immutable; use a new key for a changed campaign, profile, or release schedule.');
      }
      return $existing;
    }
    $now = $this->time->getRequestTime();
    $id = (int) $this->database->insert('famtastic_cold_proof_cohort')->fields([
      'cohort_key' => $cohort['cohort_key'],
      'campaign_id' => $campaignId,
      'campaign_key' => $cohort['campaign_key'],
      'source_name' => $cohort['source_name'],
      'package_profile' => $cohort['package_profile'],
      'direction_count' => $cohort['direction_count'],
      'direction_contract' => $snapshot,
      'profile_snapshot_hash' => $hash,
      'source_lane' => $cohort['source_lane'],
      'scheduled_release_at' => $cohort['scheduled_release_at'],
      'status' => 'seeded',
      'created' => $now,
      'changed' => $now,
    ])->execute();
    return $this->database->select('famtastic_cold_proof_cohort', 'c')->fields('c')->condition('id', $id)->execute()->fetchAssoc() ?: [];
  }

  private function existingIngress(int $cohortId, string $sourceRecordId, string $evidenceHash): ?array {
    $key = hash('sha256', implode('|', [$cohortId, $sourceRecordId, $evidenceHash]));
    $row = $this->database->select('famtastic_cold_proof_ingress', 'i')->fields('i')
      ->condition('ingress_key', $key)->range(0, 1)->execute()->fetchAssoc();
    return $row ?: NULL;
  }

  private function insertIngress(int $cohortId, array $lead, array $import, ?int $intakeId, ?int $deliveryId, ?int $proofCampaignId, ?int $jobId, string $status): void {
    $now = $this->time->getRequestTime();
    $key = hash('sha256', implode('|', [$cohortId, $lead['source_record_id'], $lead['evidence_hash']]));
    $this->database->insert('famtastic_cold_proof_ingress')->fields([
      'ingress_key' => $key,
      'cohort_id' => $cohortId,
      'prospect_id' => $import['prospect_id'] ?: NULL,
      'intake_id' => $intakeId,
      'preview_delivery_id' => $deliveryId,
      'proof_campaign_id' => $proofCampaignId,
      'proof_job_id' => $jobId,
      'source_record_id' => $lead['source_record_id'],
      'source_lane' => 'verified_cold',
      'source_url' => $lead['public_source']['url'],
      'source_provenance' => $lead['public_source']['provenance'],
      'source_timeframe' => $lead['public_source']['timeframe'],
      'website_observation_status' => $lead['website_observation']['status'],
      'website_observation_fact' => $lead['website_observation']['fact'],
      'corroborated_fact' => $lead['corroborated_fact'],
      'proof_teaser' => $lead['proof_teaser'],
      'evidence_hash' => $lead['evidence_hash'],
      'scheduled_release_at' => $lead['scheduled_release_at'],
      'status' => $status,
      'created' => $now,
      'changed' => $now,
    ])->execute();
  }

  private function result(array $lead, array $import, ?int $intakeId, ?int $deliveryId, ?int $proofCampaignId, ?int $jobId, string $action, string $packageProfile, ?string $campaignId = NULL): array {
    return [
      'source_record_id' => $lead['source_record_id'],
      'status' => (string) $import['status'],
      'score' => (int) ($import['score'] ?? 0),
      'prospect_id' => $import['prospect_id'] ?: NULL,
      'intake_id' => $intakeId,
      'preview_delivery_id' => $deliveryId,
      'proof_campaign_id' => $proofCampaignId,
      'campaign_id' => $campaignId,
      'proof_job_id' => $jobId,
      'package_profile' => $packageProfile,
      'source_lane' => 'verified_cold',
      'scheduled_release_at' => $lead['scheduled_release_at'],
      'evidence_hash' => $lead['evidence_hash'],
      'reasons' => array_values((array) ($import['reasons'] ?? [])),
      'action' => $action,
    ];
  }

}
