<?php
declare(strict_types=1);
namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/** Unregistered, default-unconfigured import. No route, QA queue or notification. */
final class ManagedProofImporter {
  public function __construct(private readonly Connection $database, private readonly EntityTypeManagerInterface $entities,
    private readonly TimeInterface $time, private readonly FreshProofAdmission $admission, private readonly WorkerCoordinator $coordinator,
    private readonly ProofOperationJournal $journal, private readonly BuildTelemetryService $telemetry,
    private readonly ?ManagedProofArtifactStore $prepared = NULL, private readonly ?ManagedProofArtifactPackage $packages = NULL,
    private readonly ?\Closure $resolveProvenance = NULL, private readonly array $completionPolicies = [],
    private readonly ?\Closure $authorizeAcknowledgment = NULL) {}

  /** Worker identity/grants are server-authenticated arguments, not body claims. */
  public function importPrepared(int $requestId, int $jobId, string $worker, string $token, int $attempt, array $serverGrants,
    string $packageId, string $packageHash, string $bundleId, string $preparedHash, string $provenanceId, string $provenanceHash): array {
    if ($this->database->inTransaction()) throw new \LogicException('Managed import requires its own root transaction.');
    if (!$this->prepared || !$this->packages || !$this->resolveProvenance || !$this->completionPolicies) throw new \RuntimeException('Managed importer is unconfigured.');
    ProofOperationContract::integer($requestId, 1, PHP_INT_MAX); ProofOperationContract::integer($jobId, 1, PHP_INT_MAX);
    ProofOperationContract::id($provenanceId); ProofOperationContract::digest($provenanceHash);
    $actor = ManagedProofImportContract::workerIdentity($worker);
    // All file processing and trusted local provenance verification precede TX.
    $package = $this->packages->verifyPreparedPackage($packageId, $packageHash, $bundleId, $preparedHash);
    $source = $this->prepared->verifyPrepared($bundleId, $preparedHash);
    $callback = json_decode($source['raw_callback'], TRUE, 16, JSON_THROW_ON_ERROR);
    $provenance = ManagedProofImportContract::provenance(($this->resolveProvenance)($provenanceId, $provenanceHash), $provenanceId, $provenanceHash);
    $expected = ['request_id' => $requestId, 'job_id' => $jobId, 'package_id' => $packageId, 'package_manifest_sha256' => $packageHash,
      'prepared_bundle_id' => $bundleId, 'prepared_manifest_sha256' => $preparedHash, 'callback_sha256' => hash('sha256', $source['raw_callback'])];
    foreach ($expected as $key => $value) if ($provenance[$key] !== $value) throw new \RuntimeException('Producer provenance is bound to different input.');
    $dna = $provenance['build_dna']; $run = $dna['run'] ?? [];
    ProofOperationContract::id($dna['build_id'] ?? NULL);
    foreach (['started_at', 'completed_at'] as $key) {
      $value = $run[$key] ?? NULL;
      if (!is_string($value) || !preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z\z/', $value)
        || ($timestamp = strtotime($value)) === FALSE || $timestamp < 1 || gmdate('Y-m-d\TH:i:s\Z', $timestamp) !== $value) throw new \RuntimeException('Managed Build DNA requires explicit UTC timestamps.');
    }
    if ($run['started_at'] > $run['completed_at'] || ($run['source_lane'] ?? '') !== 'managed_proof'
      || ($run['job_id'] ?? NULL) !== $jobId || ($run['website_request_id'] ?? NULL) !== $requestId
      || ($run['campaign_id'] ?? NULL) !== $callback['campaign_id'] || ($run['callback_event_id'] ?? NULL) !== $callback['event_id']) throw new \RuntimeException('Managed Build DNA run correlation differs.');
    $projection = $this->telemetry->prepareBuildDnaProjection($dna);
    if (strlen($projection['output_manifest']) > 262144) throw new \RuntimeException('Managed Build DNA exceeds its bound.');
    $variantValues = []; $variantFacts = [];
    foreach (['a', 'b', 'c'] as $d) {
      $input = $source['normalized'][$d]; $v = $package['manifest']['variants'][$d];
      $name = $input['design_dna']['direction_name'] ?? ['a' => 'Safe', 'b' => 'Wild', 'c' => 'OMG'][$d];
      if (!is_string($name) || $name === '' || strlen($name) > 255 || strip_tags($name) !== $name || preg_match('/[\x00-\x1f\x7f]/', $name)) throw new \RuntimeException('Managed direction name is invalid.');
      $description = ManagedProofImportContract::wire(['schema' => 'famtastic.managed-proof-variant.v1', 'worker_description' => $input['design_dna'],
        'package_id' => $packageId, 'package_manifest_sha256' => $packageHash, 'direction' => $d, 'projection' => $v['projection'], 'producer_ids' => $provenance['producer_ids']]);
      $ref = ManagedProofImportContract::reference($packageId, $d, 'html');
      $thumbnail = $v['thumbnail'] === NULL ? '' : ManagedProofImportContract::reference($packageId, $d, 'thumbnail');
      $variantValues[$d] = ['direction_id' => $d, 'direction_name' => $name, 'artifact_path' => $ref, 'thumbnail_path' => $thumbnail, 'preview_url' => '',
        'design_dna' => ['value' => $description, 'format' => NULL]];
      $variantFacts[$d] = ['name' => $name, 'artifact_ref' => $ref, 'thumbnail_ref' => $thumbnail, 'dna_sha256' => hash('sha256', $description),
        'html_sha256' => $v['projection']['derived_home']['sha256'], 'original_sha256' => hash('sha256', $input['html']), 'projection_sha256' => ManagedProofImportContract::hash($v['projection'])];
    }
    $callback = array_intersect_key($callback, array_flip(['campaign_id', 'job_id', 'event_id']));
    unset($source, $package, $dna, $input, $v, $description); // Drop media before the root mutex.
    $transaction = $this->database->startTransaction(); $campaignId = NULL;
    try {
      $binding = $this->admission->lockCurrentBinding($requestId, $this->database);
      if ((int) $binding['job']['id'] !== $jobId) throw new \RuntimeException('Import job differs from current admission.');
      $claim = $this->coordinator->lockOwnedProofClaim($jobId, $worker, $token, $serverGrants, $attempt, $this->database);
      $p = $binding['payload']; $campaignId = $p['proof_campaign_id'];
      if ($provenance['payload_sha256'] !== $claim['payload_sha256'] || $provenance['admission_sha256'] !== ManagedProofImportContract::hash($binding['binding'])
        || $callback['campaign_id'] !== $p['campaign_id'] || $callback['job_id'] !== $p['studio_job_id']
        || ($run['proof_campaign_id'] ?? NULL) !== $campaignId || ($run['prospect_id'] ?? NULL) !== $p['prospect_id']
        || ($run['customer_id'] ?? NULL) !== $p['customer_id'] || ($run['organization_id'] ?? NULL) !== $p['organization_id']
        || ($provenance['build_dna']['recipe']['routine'] ?? NULL) !== $p['routine']
        || ($provenance['build_dna']['recipe']['proof_recipe'] ?? NULL) !== $p['recipe']) throw new \RuntimeException('Import immutable authority differs.');
      ProofOperationContract::rights($provenance['input'], $binding['binding']);
      $policy = $this->completionPolicies[$p['cost_policy']['id']] ?? [];
      $operations = $this->journal->lockImportEvidence($binding, $policy, $provenance['operations'], $this->database);
      $request = $this->locked('famtastic_project_request', $requestId); $campaign = $this->locked('proof_campaign', $campaignId);
      $now = $this->time->getCurrentTime();
      if ($request['status'] !== 'submitted' || $request['proof_review_status'] !== 'not_started' || $request['selected_proof_direction'] !== ''
        || !empty($request['customer_archived_at']) || !empty($request['proof_approved_at']) || !empty($request['proof_notified_at'])
        || $campaign['status'] !== 'active' || $campaign['generation_status'] !== 'queued' || (int) $campaign['expires_at'] <= $now
        || !empty($campaign['ready_at']) || !empty($campaign['selected_variant'])) throw new \RuntimeException('Managed import is not an initial pending proof.');
      if ($this->database->select('proof_variant', 'v')->fields('v', ['id'])->condition('campaign_id', $campaignId)->range(0, 1)->forUpdate()->execute()->fetchField()
        || $this->database->select('famtastic_event', 'e')->fields('e', ['id'])->condition('event_key', ManagedProofImportContract::key($jobId))->forUpdate()->execute()->fetchField()) throw new \RuntimeException('Managed import history already exists; use exact authorized acknowledgment.');
      $buildId = $this->telemetry->insertPreparedBuildDnaLocked($projection, $this->database);
      $build = $this->locked('famtastic_build_run', $buildId);
      foreach ($variantValues as $d => $values) {
        $entity = $this->entities->getStorage('proof_variant')->create($values + ['campaign_id' => $campaignId, 'created' => $now]);
        $entity->save(); $variantFacts[$d] = ['id' => (int) $entity->id()] + $variantFacts[$d];
      }
      // Recheck already-owned authority after writers and before changing review state.
      $this->admission->lockCurrentBinding($requestId, $this->database);
      $this->journal->lockImportEvidence($binding, $policy, $operations, $this->database);
      $this->updateRow('proof_campaign', $campaign, ['generation_status' => 'ready', 'ready_at' => $now, 'changed' => $now]);
      $this->updateRow('famtastic_project_request', $request, ['proof_review_status' => 'owner_review', 'changed' => $now]);
      $receipt = ManagedProofImportContract::receipt(['schema' => ManagedProofImportContract::SCHEMA, 'job_id' => $jobId, 'request_id' => $requestId,
        'customer_id' => $p['customer_id'], 'organization_id' => $p['organization_id'], 'prospect_id' => $p['prospect_id'], 'campaign_entity_id' => $campaignId,
        'campaign_id' => $p['campaign_id'], 'studio_job_id' => $p['studio_job_id'], 'job_key' => $binding['job']['job_key'],
        'payload_sha256' => $claim['payload_sha256'], 'admission_sha256' => $provenance['admission_sha256'], 'worker_id' => $actor, 'attempt' => $attempt,
        'claim_sha256' => ManagedProofImportContract::claimHash($claim), 'prepared_bundle_id' => $bundleId, 'prepared_manifest_sha256' => $preparedHash,
        'package_id' => $packageId, 'package_manifest_sha256' => $packageHash, 'callback_sha256' => $provenance['callback_sha256'], 'callback_event_id' => $callback['event_id'],
        'provenance_id' => $provenanceId, 'provenance_sha256' => $provenanceHash, 'producer_ids' => $provenance['producer_ids'],
        'completion_policy_sha256' => ManagedProofImportContract::hash($policy),
        'build' => ['row_id' => $buildId, 'build_id' => $provenance['build_dna']['build_id'], 'sha256' => $projection['artifact_checksum'], 'projection_sha256' => ManagedProofImportReceipt::buildHash($build)],
        'variants' => $variantFacts, 'operations' => $operations, 'imported_at' => $now]);
      // Direct unique insert: no OperationalLedger duplicate swallowing/adoption.
      $this->database->insert('famtastic_event')->fields(['event_key' => ManagedProofImportContract::key($jobId), 'event_type' => ManagedProofImportContract::EVENT,
        'prospect_id' => $p['prospect_id'], 'campaign_id' => $campaignId, 'payload' => ManagedProofImportContract::wire($receipt), 'occurred_at' => $now, 'recorded_at' => $now])->execute();
      $hash = ManagedProofImportContract::hash($receipt);
      $this->coordinator->completeProofImportLocked($jobId, $worker, $token, $serverGrants, $attempt, $hash, $this->database);
      $transaction->commitOrRelease(); unset($transaction);
      if ($this->database->inTransaction()) throw new \RuntimeException('Managed import root commit is unconfirmed.');
      $this->entities->getStorage('proof_campaign')->resetCache([$campaignId]);
      return ManagedProofImportReceipt::committed($this->database, $jobId, $hash);
    }
    catch (\Throwable $error) {
      if (isset($transaction) && $this->database->inTransaction()) { try { $transaction->rollBack(); } catch (\Throwable) {} }
      try { unset($transaction); } catch (\Throwable) {}
      // No filesystem rollback/cleanup/adoption. Commit uncertainty uses ACK.
      throw $error;
    }
  }

  /** Historical evidence only; explicit separate authorization, never read grants. */
  public function acknowledgeCommittedImport(int $jobId, string $receiptHash, string $authenticatedIdentity): array {
    if ($this->authorizeAcknowledgment === NULL) throw new \RuntimeException('Managed import acknowledgment authorization is unconfigured.');
    $facts = ManagedProofImportReceipt::committed($this->database, $jobId, $receiptHash);
    if (($this->authorizeAcknowledgment)($authenticatedIdentity, $facts['receipt']) !== TRUE) throw new \RuntimeException('Managed import acknowledgment is unauthorized.');
    return $facts;
  }
  private function locked(string $table, int $id): array {
    return $this->database->select($table, 'r')->fields('r')->condition('id', $id)->forUpdate()->execute()->fetchAssoc() ?: throw new \RuntimeException('Managed import row is missing.');
  }
  private function updateRow(string $table, array $before, array $fields): void {
    $q = $this->database->update($table)->fields($fields);
    foreach ($before as $key => $value) $value === NULL ? $q->isNull($key) : $q->condition($key, $value);
    if ($q->execute() !== 1) throw new \RuntimeException('Managed import writer CAS failed; transaction rolled back.');
  }
}
