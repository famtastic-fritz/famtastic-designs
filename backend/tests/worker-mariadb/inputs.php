<?php
declare(strict_types=1);

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Lock\LockBackendInterface;

final class ProofClock implements TimeInterface {
  public int $now = 1790010000;
  public bool $live = FALSE;
  public float $anchor;
  public function __construct() { $this->anchor = microtime(TRUE); }
  public function getCurrentTime() { return $this->now + ($this->live ? (int) (microtime(TRUE) - $this->anchor) : 0); }
  public function getRequestTime() { return $this->getCurrentTime(); }
  public function getCurrentMicroTime() { return (float) $this->getCurrentTime(); }
  public function getRequestMicroTime() { return $this->getCurrentMicroTime(); }
}
/** Intentionally ineffective hint. The database mutex must supply correctness. */
final class IneffectiveBusyHint implements LockBackendInterface {
  public function acquire($name, $timeout = 30.0) { return TRUE; }
  public function lockMayBeAvailable($name) { return TRUE; }
  public function wait($name, $delay = 30) { return FALSE; }
  public function release($name) {}
  public function releaseAll($lockId = NULL) {}
  public function getLockId() { return 'synthetic-ineffective-hint'; }
}
function proofInputs(): array {
  $brief = ['business_name' => 'Synthetic MariaDB contention only'];
  $proof = ['schema' => 'famtastic.proof-worker-input.v1', 'routine' => 'website_proof.generate.v1', 'brief_version' => 1,
    'website_request_id' => 17, 'website_request_public_id' => '00000000-0000-0000-0000-000000000017',
    'customer_id' => 21, 'organization_id' => 22, 'prospect_id' => 23, 'proof_campaign_id' => 24,
    'campaign_id' => 'pc-synthetic-mariadb', 'studio_job_id' => 'synthetic-run',
    'brief_sha256' => hash('sha256', json_encode($brief, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)), 'website_discovery_v3' => $brief,
    'request_binding_sha256' => str_repeat('a', 64), 'asset_authority_sha256' => str_repeat('b', 64), 'direction_ids' => ['a', 'b', 'c'],
    'recipe' => ['id' => 'synthetic-no-provider', 'revision' => str_repeat('c', 40), 'sha256' => str_repeat('d', 64)],
    'tool_allowlist' => ['synthetic-record-only'],
    'cost_policy' => ['id' => 'synthetic-hold-only', 'revision' => str_repeat('e', 40), 'currency' => 'USD', 'max_calls' => 1, 'max_cost_cents' => 100, 'reservation_cents' => 100]];
  $static = ['packet' => ['build_class' => 'prepayment_selected_direction_staging', 'packet_id' => 'fixture-packet', 'idempotency_key' => 'fixture-key',
    'continuation' => ['spec' => ['capability_class' => 'static'], 'operation' => 'package_existing', 'requested_next_action' => 'protected_review']]];
  return ['static' => $static, 'proof' => $proof, 'catalog' => ['synthetic-hold-only' => array_intersect_key($proof, array_flip(['recipe', 'tool_allowlist', 'cost_policy']))]];
}
