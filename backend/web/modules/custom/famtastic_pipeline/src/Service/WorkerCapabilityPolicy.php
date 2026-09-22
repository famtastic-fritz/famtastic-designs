<?php
declare(strict_types=1);
namespace Drupal\famtastic_pipeline\Service;

/** Source-owned claim policy, not provider authority or a worker-body override. */
final class WorkerCapabilityPolicy {
  public const PROOF = 'proof-creative-v1';
  public const PROOF_POLICY = 'bounded-proof-claims-v1';

  public static function profile(string $capability): array {
    return match ($capability) {
      WorkerCoordinator::CAPABILITY => ['policy' => WorkerCoordinator::POLICY, 'lease' => WorkerCoordinator::LEASE_SECONDS, 'heartbeat' => 30, 'execution' => WorkerCoordinator::MAX_RUN_SECONDS, 'grace' => 30, 'attempts' => 3],
      self::PROOF => ['policy' => self::PROOF_POLICY, 'lease' => 180, 'heartbeat' => 30, 'execution' => 1800, 'grace' => 30, 'attempts' => 3],
      default => throw new \InvalidArgumentException('Unsupported worker capability.'),
    };
  }

  /** Call only with installed registry capabilities or trusted service arguments. */
  public static function claimCapabilities(array $authorized): array {
    return array_values(array_intersect([WorkerCoordinator::CAPABILITY, self::PROOF], array_filter($authorized, 'is_string')));
  }

  /** Shape/binding validation only. Live request/rights admission is not installed. */
  public static function assertProof(array $job, int $reservation, array $reviewedPolicies): void {
    $p = json_decode((string) $job['payload'], TRUE, flags: JSON_THROW_ON_ERROR);
    if (!is_array($p) || ($p['schema'] ?? '') !== 'famtastic.proof-worker-input.v1'
      || $job['job_type'] !== 'proof.generate' || ($p['routine'] ?? '') !== 'website_proof.generate.v1'
      || ($p['brief_version'] ?? NULL) !== 1 || ($p['direction_ids'] ?? NULL) !== ['a', 'b', 'c']) {
      throw new \InvalidArgumentException('A frozen v1 account proof payload is required.');
    }
    $keys = ['schema', 'routine', 'website_request_id', 'website_request_public_id', 'customer_id', 'organization_id', 'prospect_id',
      'proof_campaign_id', 'campaign_id', 'studio_job_id', 'brief_version', 'brief_sha256', 'website_discovery_v3',
      'request_binding_sha256', 'asset_authority_sha256', 'direction_ids', 'recipe', 'tool_allowlist', 'cost_policy'];
    if (array_diff(array_keys($p), $keys) || array_diff($keys, array_keys($p))) throw new \InvalidArgumentException('Proof payload fields do not match v1.');
    foreach (['website_request_id', 'customer_id', 'organization_id', 'prospect_id', 'proof_campaign_id'] as $key) {
      if (!is_int($p[$key] ?? NULL) || $p[$key] < 1) throw new \InvalidArgumentException('Proof identity is incomplete.');
    }
    if ((int) ($job['prospect_id'] ?? 0) !== $p['prospect_id']
      || !is_string($p['website_request_public_id'] ?? NULL)
      || !preg_match('/^[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}$/D', $p['website_request_public_id'])) throw new \InvalidArgumentException('Proof request/prospect binding is invalid.');
    foreach (['campaign_id', 'studio_job_id'] as $key) self::identifier($p[$key] ?? NULL);
    foreach (['brief_sha256', 'request_binding_sha256', 'asset_authority_sha256'] as $key) self::digest($p[$key] ?? NULL);
    if (!is_array($p['website_discovery_v3'] ?? NULL) || !$p['website_discovery_v3'] || array_is_list($p['website_discovery_v3'])
      || !hash_equals($p['brief_sha256'], hash('sha256', json_encode($p['website_discovery_v3'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)))
      || $job['job_key'] !== 'website_proof.generate.v1:request:' . $p['website_request_id'] . ':brief:' . $p['brief_sha256']) throw new \InvalidArgumentException('Proof brief/job binding is invalid.');
    $recipe = $p['recipe'] ?? [];
    self::identifier($recipe['id'] ?? NULL);
    self::digest($recipe['sha256'] ?? NULL);
    if (!is_string($recipe['revision'] ?? NULL) || !preg_match('/^[a-f0-9]{40}$/D', $recipe['revision'])) throw new \InvalidArgumentException('Proof recipe revision is required.');
    $tools = $p['tool_allowlist'] ?? [];
    if (!is_array($tools) || !array_is_list($tools) || !$tools || count($tools) > 16) throw new \InvalidArgumentException('Proof tools must be explicitly bounded.');
    foreach ($tools as $tool) self::identifier($tool);
    if (count(array_unique($tools)) !== count($tools)) throw new \InvalidArgumentException('Duplicate proof tools.');
    $cost = $p['cost_policy'] ?? [];
    self::identifier($cost['id'] ?? NULL);
    $reviewed = $reviewedPolicies[$cost['id']] ?? NULL;
    // No production cost catalog is installed. A future adapter must supply one
    // through reviewed service wiring, never HTTP, Settings or this payload.
    if (!is_array($reviewed) || $cost !== ($reviewed['cost_policy'] ?? NULL)
      || $recipe !== ($reviewed['recipe'] ?? NULL) || $tools !== ($reviewed['tool_allowlist'] ?? NULL)
      || ($cost['currency'] ?? '') !== 'USD' || !is_string($cost['revision'] ?? NULL)
      || !preg_match('/^[a-f0-9]{40}$/D', $cost['revision'])
      || !is_int($cost['max_calls'] ?? NULL) || $cost['max_calls'] < 1 || $cost['max_calls'] > 32
      || !is_int($cost['max_cost_cents'] ?? NULL) || $cost['max_cost_cents'] < 1
      || ($cost['reservation_cents'] ?? NULL) !== $reservation || $reservation < 25 || $reservation > 250
      || $cost['max_cost_cents'] > $reservation) throw new \InvalidArgumentException('Unsupported or unbounded proof cost policy.');
  }

  private static function identifier(mixed $value): void {
    if (!is_string($value) || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,190}$/D', $value)) throw new \InvalidArgumentException('Proof identity is missing or invalid.');
  }

  private static function digest(mixed $value): void {
    if (!is_string($value) || !preg_match('/^[a-f0-9]{64}$/D', $value)) throw new \InvalidArgumentException('Proof digest is missing or invalid.');
  }
}
