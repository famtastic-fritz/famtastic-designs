<?php
declare(strict_types=1);
namespace Drupal\famtastic_pipeline\Service;

/** Closed metadata contracts. Neither a digest nor this validator grants rights. */
final class ManagedProofImportContract {
  public const SCHEMA = 'famtastic.managed-proof-import.v1';
  public const EVENT = 'proof.managed_imported.v1';
  public const MAX_RECEIPT_BYTES = 65536;
  public static function key(int $jobId): string { return 'proof-import:job:' . $jobId; }
  public static function wire(array $value): string { return ProofOperationContract::wire($value); }
  public static function hash(array $value): string { return hash('sha256', self::wire($value)); }
  public static function workerIdentity(string $worker): string {
    if (!preg_match('/\A[a-z][a-z0-9._-]{2,63}\z/', $worker)) throw new \InvalidArgumentException('Invalid canonical producer worker.');
    return 'automation:' . $worker;
  }
  public static function producers(mixed $ids): void {
    if (!is_array($ids) || !array_is_list($ids) || !$ids || count($ids) > 32) throw new \InvalidArgumentException('Invalid canonical producer set.');
    $sorted = $ids; sort($sorted, SORT_STRING);
    if ($ids !== $sorted || count(array_unique($ids)) !== count($ids)) throw new \InvalidArgumentException('Producer identities must be sorted and unique.');
    foreach ($ids as $id) if (!is_string($id) || !preg_match('/\Aautomation:[a-z][a-z0-9._-]{2,63}\z/', $id)) throw new \InvalidArgumentException('Invalid canonical producer identity.');
  }
  public static function operations(mixed $operations): void {
    if (!is_array($operations) || !$operations || count($operations) > 32) throw new \InvalidArgumentException('Invalid import operation inventory.');
    $sorted = $operations; ksort($sorted);
    if ($sorted !== $operations) throw new \InvalidArgumentException('Import operations must be sorted.');
    foreach ($operations as $id => $op) {
      ProofOperationContract::digest($id);
      if (!is_array($op)) throw new \InvalidArgumentException('Invalid import operation facts.');
      ProofOperationContract::keys($op, ['identity_sha256', 'receipt_sha256', 'producer_id', 'recorder_id']);
      foreach (['identity_sha256', 'receipt_sha256'] as $key) ProofOperationContract::digest($op[$key]);
      self::producers([$op['producer_id']]); self::producers([$op['recorder_id']]);
    }
  }
  private static function correlation(array $value): void {
    foreach (['job_id', 'request_id'] as $key) ProofOperationContract::integer($value[$key], 1, PHP_INT_MAX);
    foreach (['payload_sha256', 'admission_sha256', 'prepared_manifest_sha256', 'package_manifest_sha256', 'callback_sha256'] as $key) ProofOperationContract::digest($value[$key]);
    if (!is_string($value['prepared_bundle_id']) || !preg_match('/\A[a-f0-9]{32}\z/', $value['prepared_bundle_id'])
      || !is_string($value['package_id']) || !preg_match('/\Amp-[a-f0-9]{32}\z/', $value['package_id'])) throw new \InvalidArgumentException('Invalid managed artifact identity.');
    self::producers($value['producer_ids']); self::operations($value['operations']);
    foreach ($value['operations'] as $op) if (!in_array($op['producer_id'], $value['producer_ids'], TRUE)) throw new \RuntimeException('Operation producer is missing from provenance.');
  }
  /** Returned ONLY by a trusted local provenance resolver, never worker DNA. */
  public static function provenance(array $value, string $id, string $hash): array {
    ProofOperationContract::id($id); ProofOperationContract::digest($hash);
    ProofOperationContract::keys($value, ['schema', 'id', 'job_id', 'request_id', 'payload_sha256', 'admission_sha256',
      'prepared_bundle_id', 'prepared_manifest_sha256', 'package_id', 'package_manifest_sha256', 'callback_sha256', 'producer_ids', 'input', 'operations', 'build_dna']);
    if ($value['schema'] !== 'famtastic.managed-proof-provenance.v1' || $value['id'] !== $id
      || strlen(self::wire($value)) > 262144 || self::hash($value) !== $hash) throw new \RuntimeException('Managed producer provenance differs.');
    self::correlation($value); ProofOperationContract::input($value['input']);
    if (!is_array($value['build_dna'])) throw new \InvalidArgumentException('Managed Build DNA is missing.');
    return $value;
  }
  public static function receipt(array $r): array {
    ProofOperationContract::keys($r, ['schema', 'job_id', 'request_id', 'customer_id', 'organization_id', 'prospect_id', 'campaign_entity_id',
      'campaign_id', 'studio_job_id', 'job_key', 'payload_sha256', 'admission_sha256', 'worker_id', 'attempt', 'claim_sha256',
      'prepared_bundle_id', 'prepared_manifest_sha256', 'package_id', 'package_manifest_sha256', 'callback_sha256', 'callback_event_id',
      'provenance_id', 'provenance_sha256', 'producer_ids', 'completion_policy_sha256', 'build', 'variants', 'operations', 'imported_at']);
    if ($r['schema'] !== self::SCHEMA || strlen(self::wire($r)) > self::MAX_RECEIPT_BYTES) throw new \InvalidArgumentException('Invalid managed import receipt schema or bound.');
    self::correlation($r);
    foreach (['customer_id', 'organization_id', 'prospect_id', 'campaign_entity_id', 'imported_at'] as $key) ProofOperationContract::integer($r[$key], 1, PHP_INT_MAX);
    ProofOperationContract::integer($r['attempt'], 1, 3); self::producers([$r['worker_id']]); ProofOperationContract::id($r['provenance_id']);
    foreach (['claim_sha256', 'provenance_sha256', 'completion_policy_sha256'] as $key) ProofOperationContract::digest($r[$key]);
    foreach (['campaign_id', 'studio_job_id', 'callback_event_id', 'job_key'] as $key) {
      if (!is_string($r[$key]) || !preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]{0,254}\z/', $r[$key])) throw new \InvalidArgumentException('Invalid import correlation key.');
    }
    if (!preg_match('/\Apc-[a-z0-9-]{1,124}\z/', $r['campaign_id']) || strlen($r['job_key']) > 191) throw new \InvalidArgumentException('Invalid import campaign or job key.');
    ProofOperationContract::keys($r['build'], ['row_id', 'build_id', 'sha256', 'projection_sha256']);
    ProofOperationContract::integer($r['build']['row_id'], 1, PHP_INT_MAX); ProofOperationContract::id($r['build']['build_id']);
    ProofOperationContract::digest($r['build']['sha256']); ProofOperationContract::digest($r['build']['projection_sha256']);
    if (array_keys($r['variants']) !== ['a', 'b', 'c']) throw new \InvalidArgumentException('Import requires exact A/B/C variants.');
    $ids = [];
    foreach ($r['variants'] as $d => $v) {
      ProofOperationContract::keys($v, ['id', 'name', 'artifact_ref', 'thumbnail_ref', 'dna_sha256', 'html_sha256', 'original_sha256', 'projection_sha256']);
      ProofOperationContract::integer($v['id'], 1, PHP_INT_MAX); $ids[] = $v['id'];
      if (!is_string($v['name']) || $v['name'] === '' || strlen($v['name']) > 255 || strip_tags($v['name']) !== $v['name']
        || preg_match('/[\x00-\x1f\x7f]/', $v['name']) || $v['artifact_ref'] !== self::reference($r['package_id'], $d, 'html')
        || !in_array($v['thumbnail_ref'], ['', self::reference($r['package_id'], $d, 'thumbnail')], TRUE)) throw new \InvalidArgumentException('Invalid managed variant reference or name.');
      foreach (['dna_sha256', 'html_sha256', 'original_sha256', 'projection_sha256'] as $key) ProofOperationContract::digest($v[$key]);
    }
    if (count(array_unique($ids)) !== 3) throw new \InvalidArgumentException('Duplicate managed variant rows.');
    return $r;
  }
  public static function reference(string $package, string $direction, string $role): string { return 'managed-proof:' . $package . ':' . $direction . ':' . $role; }
  public static function claimHash(array $claim): string {
    // One-way fence fingerprint; NEVER persist or return the raw lease token.
    return self::hash(['job_id' => (int) $claim['job_id'], 'worker_id' => $claim['worker_id'], 'token_hash' => $claim['token_hash'],
      'attempt' => (int) $claim['attempt'], 'payload_sha256' => $claim['payload_sha256']]);
  }
  public static function result(array $receipt): string {
    return self::wire(['status' => 'proof_imported', 'receipt_id' => self::key($receipt['job_id']), 'receipt_sha256' => self::hash($receipt)]);
  }
}
