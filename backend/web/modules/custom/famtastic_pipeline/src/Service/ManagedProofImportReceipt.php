<?php
declare(strict_types=1);
namespace Drupal\famtastic_pipeline\Service;

use Drupal\Core\Database\Connection;

/** Persisted content/lineage facts ONLY. Not current eligibility or read grants. */
final class ManagedProofImportReceipt {
  /** May be called only after commit. Does not mutate or acquire worker locks. */
  public static function committed(Connection $db, int $jobId, ?string $expectedReceiptHash = NULL): array {
    if ($db->inTransaction()) throw new \LogicException('Committed import facts require a committed connection.');
    return self::verify($db, $jobId, $expectedReceiptHash, FALSE);
  }

  /** Internal completion check; caller owns request, authority and mutex first. */
  public static function pendingLocked(Connection $db, int $jobId, string $expectedReceiptHash): array {
    if (!$db->inTransaction()) throw new \LogicException('Pending import facts require an active transaction.');
    return self::verify($db, $jobId, $expectedReceiptHash, TRUE);
  }

  private static function verify(Connection $db, int $jobId, ?string $expectedHash, bool $pending): array {
    ProofOperationContract::integer($jobId, 1, PHP_INT_MAX);
    if ($expectedHash !== NULL) ProofOperationContract::digest($expectedHash);
    $event = self::row($db, 'famtastic_event', 'event_key', ManagedProofImportContract::key($jobId), $pending);
    if (!$event || strlen((string) $event['payload']) > ManagedProofImportContract::MAX_RECEIPT_BYTES) throw new \RuntimeException('Managed import receipt is missing or oversized.');
    $r = ManagedProofImportContract::receipt(json_decode($event['payload'], TRUE, 32, JSON_THROW_ON_ERROR));
    $hash = ManagedProofImportContract::hash($r);
    if (ManagedProofImportContract::wire($r) !== $event['payload'] || ($expectedHash !== NULL && !hash_equals($expectedHash, $hash))
      || $r['job_id'] !== $jobId || $event['event_type'] !== ManagedProofImportContract::EVENT
      || (int) $event['prospect_id'] !== $r['prospect_id'] || (int) $event['campaign_id'] !== $r['campaign_entity_id']
      || (int) $event['occurred_at'] !== $r['imported_at'] || (int) $event['recorded_at'] !== $r['imported_at']) throw new \RuntimeException('Managed import receipt identity differs.');
    $admission = self::row($db, 'famtastic_event', 'event_key', FreshProofBinding::key($r['request_id']), $pending);
    $job = self::row($db, 'famtastic_job', 'id', $jobId, $pending);
    $claim = self::row($db, 'famtastic_worker_claim', 'job_id', $jobId, $pending);
    if (!$admission || !$job || !$claim || hash('sha256', $admission['payload']) !== $r['admission_sha256']
      || $admission['event_type'] !== FreshProofBinding::EVENT || (int) $admission['campaign_id'] !== $r['campaign_entity_id']
      || (int) $admission['prospect_id'] !== $r['prospect_id']) throw new \RuntimeException('Managed import admission is missing or changed.');
    $b = json_decode($admission['payload'], TRUE, 32, JSON_THROW_ON_ERROR);
    ProofOperationContract::keys($b, ['schema', 'freshness', 'request_snapshot', 'asset_snapshot', 'job_id', 'job_key', 'payload_wire', 'payload_sha256']);
    $p = json_decode($job['payload'], TRUE, 32, JSON_THROW_ON_ERROR);
    if ($b['schema'] !== 'famtastic.fresh-proof-admission.v1' || FreshProofInput::wire($b) !== $admission['payload']
      || $b['job_id'] !== $jobId || $b['job_key'] !== $r['job_key'] || $b['payload_wire'] !== $job['payload']
      || $b['payload_sha256'] !== $r['payload_sha256'] || hash('sha256', $job['payload']) !== $r['payload_sha256']
      || $job['job_key'] !== $r['job_key'] || $job['job_type'] !== 'proof.generate' || (int) $job['max_attempts'] !== 3
      || $claim['capability'] !== WorkerCapabilityPolicy::PROOF || $claim['policy_version'] !== WorkerCapabilityPolicy::PROOF_POLICY
      || $claim['payload_sha256'] !== $r['payload_sha256'] || (int) $claim['attempt'] !== $r['attempt']
      || (int) $job['attempts'] !== $r['attempt'] - 1 || ManagedProofImportContract::workerIdentity($claim['worker_id']) !== $r['worker_id']
      || ManagedProofImportContract::claimHash($claim) !== $r['claim_sha256']
      || hash('sha256', FreshProofInput::wire($b['request_snapshot'])) !== $p['request_binding_sha256']
      || hash('sha256', FreshProofInput::wire($b['asset_snapshot'])) !== $p['asset_authority_sha256']) throw new \RuntimeException('Managed import job lineage differs.');
    foreach (['request_id' => 'website_request_id', 'customer_id' => 'customer_id', 'organization_id' => 'organization_id', 'prospect_id' => 'prospect_id',
      'campaign_entity_id' => 'proof_campaign_id', 'campaign_id' => 'campaign_id', 'studio_job_id' => 'studio_job_id'] as $key => $field) {
      if (($p[$field] ?? NULL) !== $r[$key]) throw new \RuntimeException('Managed import tenant correlation differs.');
    }
    $request = self::row($db, 'famtastic_project_request', 'id', $r['request_id'], $pending);
    $campaign = self::row($db, 'proof_campaign', 'id', $r['campaign_entity_id'], $pending);
    foreach (['customer_id', 'organization_id', 'prospect_id'] as $field) if (!$request || (int) $request[$field] !== $r[$field]) throw new \RuntimeException('Managed import request association differs.');
    if ((int) $request['proof_campaign_id'] !== $r['campaign_entity_id'] || $request['public_id'] !== $p['website_request_public_id']
      || !$campaign || $campaign['campaign_id'] !== $r['campaign_id'] || $campaign['studio_job_id'] !== $r['studio_job_id']
      || (int) $campaign['prospect_id'] !== $r['prospect_id'] || $campaign['generation_status'] !== 'ready'
      || (int) $campaign['ready_at'] !== $r['imported_at']) throw new \RuntimeException('Managed import campaign association differs.');
    // Deliberately no comparison of current review/selection/brief to fresh input.
    // A historical receipt is not permission to use a currently revoked asset.
    $q = $db->select('proof_variant', 'v')->fields('v')->condition('campaign_id', $r['campaign_entity_id'])->orderBy('direction_id')->range(0, 4);
    if ($pending) $q->forUpdate();
    $variants = $q->execute()->fetchAll(\PDO::FETCH_ASSOC);
    if (count($variants) !== 3) throw new \RuntimeException('Managed import variant inventory differs.');
    foreach ($variants as $v) {
      $expected = $r['variants'][$v['direction_id']] ?? NULL;
      if (!$expected || (int) $v['id'] !== $expected['id'] || $v['direction_name'] !== $expected['name'] || $v['artifact_path'] !== $expected['artifact_ref']
        || ($v['thumbnail_path'] ?? '') !== $expected['thumbnail_ref'] || ($v['preview_url'] ?? '') !== ''
        // ProofVariant.design_dna is text_long: Drupal stores both properties.
        // Never read a fictional flat column or filtered/rendered JSON.
        || !is_string($v['design_dna__value'] ?? NULL) || !array_key_exists('design_dna__format', $v) || $v['design_dna__format'] !== NULL
        || hash('sha256', $v['design_dna__value']) !== $expected['dna_sha256']) throw new \RuntimeException('Managed import variant bytes differ.');
      $dna = json_decode($v['design_dna__value'], TRUE, 32, JSON_THROW_ON_ERROR);
      ProofOperationContract::keys($dna, ['schema', 'worker_description', 'package_id', 'package_manifest_sha256', 'direction', 'projection', 'producer_ids']);
      if ($dna['schema'] !== 'famtastic.managed-proof-variant.v1' || $dna['package_id'] !== $r['package_id'] || $dna['package_manifest_sha256'] !== $r['package_manifest_sha256']
        || $dna['direction'] !== $v['direction_id'] || $dna['producer_ids'] !== $r['producer_ids']
        || ManagedProofImportContract::hash($dna['projection']) !== $expected['projection_sha256']
        || $dna['projection']['original_home']['sha256'] !== $expected['original_sha256']
        || $dna['projection']['derived_home']['sha256'] !== $expected['html_sha256']) throw new \RuntimeException('Managed import variant content facts differ.');
    }
    $build = self::row($db, 'famtastic_build_run', 'id', $r['build']['row_id'], $pending);
    if (!$build || strlen($build['output_manifest']) > 262144 || $build['build_key'] !== 'build-dna:' . $r['build']['build_id'] || $build['artifact_checksum'] !== $r['build']['sha256']
      || hash('sha256', $build['output_manifest']) !== $r['build']['sha256'] || self::buildHash($build) !== $r['build']['projection_sha256']
      || (int) $build['proof_campaign_id'] !== $r['campaign_entity_id'] || (int) $build['prospect_id'] !== $r['prospect_id']
      || $build['campaign_key'] !== $r['campaign_id'] || $build['status'] !== 'completed') throw new \RuntimeException('Managed import Build DNA differs.');
    $q = $db->select('famtastic_proof_operation', 'o')->fields('o')->condition('job_id', $jobId)->orderBy('operation_id')->range(0, 33);
    if ($pending) $q->forUpdate();
    $ops = $q->execute()->fetchAll(\PDO::FETCH_ASSOC);
    if (count($ops) !== count($r['operations'])) throw new \RuntimeException('Managed import journal inventory differs.');
    foreach ($ops as $op) {
      $expected = $r['operations'][$op['operation_id']] ?? NULL;
      if (!$expected || $op['state'] !== 'receipt_recorded' || $op['identity_sha256'] !== $expected['identity_sha256'] || $op['receipt_sha256'] !== $expected['receipt_sha256']
        || hash('sha256', $op['identity_wire']) !== $op['identity_sha256'] || hash('sha256', $op['receipt_wire']) !== $op['receipt_sha256']) throw new \RuntimeException('Managed import journal bytes differ.');
      $identity = json_decode($op['identity_wire'], TRUE, 16, JSON_THROW_ON_ERROR);
      $receipt = ProofOperationContract::receipt(json_decode($op['receipt_wire'], TRUE, 16, JSON_THROW_ON_ERROR));
      if ($identity['job_id'] !== $jobId || $identity['request_id'] !== $r['request_id'] || $identity['binding_sha256'] !== $r['admission_sha256']
        || $identity['payload_sha256'] !== $r['payload_sha256'] || ManagedProofImportContract::workerIdentity($identity['producer_id']) !== $expected['producer_id']
        || ManagedProofImportContract::workerIdentity($op['recorder_id']) !== $expected['recorder_id'] || $receipt['operation_id'] !== $op['operation_id']
        || ProofOperationContract::wire($receipt) !== $op['receipt_wire']) throw new \RuntimeException('Managed import journal lineage differs.');
    }
    if ($pending) {
      if ($request['proof_review_status'] !== 'owner_review' || $job['status'] !== 'worker_running' || $claim['state'] !== 'leased') throw new \RuntimeException('Managed import is not pending fenced completion.');
    }
    elseif ($job['status'] !== 'completed' || $job['result'] !== ManagedProofImportContract::result($r) || (int) $job['completed_at'] !== $r['imported_at']
      || $job['locked_at'] !== NULL || $claim['state'] !== 'proof_imported' || $claim['result_sha256'] !== $hash
      || (int) $claim['lease_until'] !== 0 || (int) $claim['attempt_deadline'] !== 0) throw new \RuntimeException('Managed import completion is not committed.');
    return ['receipt_id' => ManagedProofImportContract::key($jobId), 'receipt_sha256' => $hash, 'receipt' => $r];
  }

  public static function buildHash(array $row): string {
    $facts = [];
    foreach (['build_key', 'campaign_key', 'prospect_id', 'proof_campaign_id', 'project_id', 'flow_key', 'task_key', 'provider', 'agent_name',
      'status', 'prompt_snapshot', 'input_snapshot', 'output_manifest', 'source_sha', 'artifact_checksum', 'error', 'started_at', 'completed_at'] as $key) {
      $value = $row[$key];
      $facts[$key] = $value !== NULL && in_array($key, ['prospect_id', 'proof_campaign_id', 'project_id', 'started_at', 'completed_at'], TRUE) ? (int) $value : $value;
    }
    return ManagedProofImportContract::hash($facts);
  }
  private static function row(Connection $db, string $table, string $field, int|string $value, bool $lock): array|false {
    $q = $db->select($table, 'r')->fields('r')->condition($field, $value);
    if ($lock) $q->forUpdate();
    return $q->execute()->fetchAssoc();
  }
}
