<?php
declare(strict_types=1);
namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Site\Settings;

/** Fresh portal admission only. No provider, importer, notification or scanner. */
final class FreshProofAdmission {
  public function __construct(private readonly Connection $database, private readonly EntityTypeManagerInterface $entities,
    private readonly TimeInterface $time, private readonly WorkerCoordinator $coordinator,
    private readonly array $reviewedPolicy = []) {}

  public static function enabled(): bool { return Settings::get('famtastic_fresh_proof_admission_enabled', FALSE) === TRUE; }

  /**
   * Trusted writer intent, never request-body data. Caller owns the outer tx.
   * NULL prior status means this writer inserted the request in that transaction.
   */
  public function admit(int $requestId, int $customerId, string $source, ?string $priorStatus): int {
    if (!self::enabled() || !$this->database->inTransaction()) throw new \RuntimeException('Fresh proof admission requires its enabled outer request transaction.');
    $freshness = ['source' => $source, 'prior_status' => $priorStatus, 'actor_customer_id' => $customerId];
    if (!(($source === 'portal.create' && $priorStatus === NULL) || ($source === 'portal.update' && $priorStatus === 'draft'))) throw new \InvalidArgumentException('Fresh portal submission intent is required.');
    $reservation = $this->reviewedPolicy['cost_policy']['reservation_cents'] ?? NULL;
    if (!is_int($reservation)) throw new \RuntimeException('No reviewed creative cost policy is installed.');
    $row = $this->locked('famtastic_project_request', 'id', $requestId);
    if (!$row || (int) $row['customer_id'] !== $customerId || $row['status'] !== 'submitted'
      || empty($row['submitted_at']) || !empty($row['commerce_order_id']) || !empty($row['project_id'])
      || $row['proof_review_status'] !== 'not_started' || $row['selected_proof_direction'] !== '') throw new \RuntimeException('Request is not eligible for initial proof admission.');
    $this->assertAccount($row);
    $assets = FreshProofInput::assets($this->database, $row);
    $prior = FreshProofBinding::event($this->database, $requestId, TRUE);
    // Request/account/assets precede the shared mutex; job history and inserts
    // must follow it. Enrollment's nested savepoint cannot release this lock.
    WorkerCoordinatorMutex::acquire($this->database);
    if ($prior) {
      $record = FreshProofBinding::read($this->database, $prior, $row, TRUE);
      $b = $record['binding'];
      if (($b['freshness'] ?? NULL) !== $freshness || ($b['request_snapshot'] ?? NULL) !== FreshProofInput::request($row, $freshness)
        || ($b['asset_snapshot'] ?? NULL) !== $assets) throw new \RuntimeException('Existing proof admission differs from current input.');
      return $this->reuse($requestId);
    }
    $this->assertNoHistory($row);
    $now = $this->time->getCurrentTime();
    $campaignId = 'pc-' . bin2hex(random_bytes(16));
    $studioJobId = 'proof-worker-' . bin2hex(random_bytes(16));
    $campaign = $this->entities->getStorage('proof_campaign')->create([
      'campaign_id' => $campaignId, 'prospect_id' => (int) $row['prospect_id'], 'business_name' => $row['business_name'],
      'status' => 'active', 'generation_status' => 'queued', 'studio_job_id' => $studioJobId, 'expires_at' => $now + 604800,
    ]);
    $campaign->save();
    $campaignEntityId = (int) $campaign->id();
    if ($this->database->update('famtastic_project_request')->fields(['proof_campaign_id' => $campaignEntityId])
      ->condition('id', $requestId)->condition('status', 'submitted')->isNull('proof_campaign_id')->execute() !== 1) throw new \RuntimeException('Request campaign binding changed.');
    $row['proof_campaign_id'] = $campaignEntityId;
    $snapshot = FreshProofInput::request($row, $freshness);
    $brief = $snapshot['intake'];
    $payload = [
      'schema' => 'famtastic.proof-worker-input.v1', 'routine' => 'website_proof.generate.v1', 'brief_version' => 1,
      'website_request_id' => $requestId, 'website_request_public_id' => $row['public_id'], 'customer_id' => $customerId,
      'organization_id' => (int) $row['organization_id'], 'prospect_id' => (int) $row['prospect_id'],
      'proof_campaign_id' => $campaignEntityId, 'campaign_id' => $campaignId, 'studio_job_id' => $studioJobId,
      'brief_sha256' => hash('sha256', FreshProofInput::wire($brief)), 'website_discovery_v3' => $brief,
      'request_binding_sha256' => hash('sha256', FreshProofInput::wire($snapshot)),
      'asset_authority_sha256' => hash('sha256', FreshProofInput::wire($assets)), 'direction_ids' => ['a', 'b', 'c'],
      'recipe' => $this->reviewedPolicy['recipe'] ?? [], 'tool_allowlist' => $this->reviewedPolicy['tool_allowlist'] ?? [],
      'cost_policy' => $this->reviewedPolicy['cost_policy'],
    ];
    $key = 'website_proof.generate.v1:request:' . $requestId . ':brief:' . $payload['brief_sha256'];
    $wire = FreshProofInput::wire($payload);
    // Deliberately no OperationalLedger::enqueue duplicate-swallowing behavior.
    $jobId = (int) $this->database->insert('famtastic_job')->fields([
      'job_key' => $key, 'job_type' => 'proof.generate', 'prospect_id' => (int) $row['prospect_id'], 'status' => 'queued',
      'attempts' => 0, 'max_attempts' => 3, 'available_at' => $now, 'payload' => $wire, 'created' => $now, 'changed' => $now,
    ])->execute();
    $this->coordinator->enrollProof($jobId, $key, hash('sha256', $wire), $reservation);
    // These locking reads also catch deterministic interleavings in focused tests.
    // Future provider/import boundaries must revalidate: admission is not a license.
    $this->assertAccount($row);
    if (FreshProofInput::request($this->locked('famtastic_project_request', 'id', $requestId), $freshness) !== $snapshot
      || FreshProofInput::assets($this->database, $row) !== $assets) throw new \RuntimeException('Proof input changed during admission.');
    $binding = ['schema' => 'famtastic.fresh-proof-admission.v1', 'freshness' => $freshness,
      'request_snapshot' => $snapshot, 'asset_snapshot' => $assets, 'job_id' => $jobId, 'job_key' => $key,
      'payload_wire' => $wire, 'payload_sha256' => hash('sha256', $wire)];
    $this->database->insert('famtastic_event')->fields([
      'event_key' => FreshProofBinding::key($requestId), 'event_type' => FreshProofBinding::EVENT,
      'prospect_id' => (int) $row['prospect_id'], 'campaign_id' => $campaignEntityId, 'payload' => FreshProofInput::wire($binding),
      'occurred_at' => $now, 'recorded_at' => $now,
    ])->execute();
    return $jobId;
  }

  /** Exact managed retry only, even when fresh admission has since been disabled. */
  public function reuse(int $requestId): int {
    return (int) $this->lockCurrentBinding($requestId, $this->database)['job']['id'];
  }

  /** Transaction-only current records, NOT permission for a later external effect. */
  public function lockCurrentBinding(int $requestId, Connection $connection): array {
    if ($connection !== $this->database) throw new \LogicException('Proof binding requires the same database connection.');
    if (!$this->database->inTransaction()) throw new \RuntimeException('Managed proof reuse requires an outer request transaction.');
    $row = $this->locked('famtastic_project_request', 'id', $requestId);
    $event = FreshProofBinding::event($this->database, $requestId, TRUE);
    if (!$row || !$event) throw new \RuntimeException('Managed proof admission evidence is missing; replacement requires reconciliation.');
    $this->assertAccount($row);
    $assets = FreshProofInput::assets($this->database, $row);
    WorkerCoordinatorMutex::acquire($this->database);
    $record = FreshProofBinding::read($this->database, $event, $row, TRUE);
    if ($record['binding']['asset_snapshot'] !== $assets) throw new \RuntimeException('Existing proof admission differs from current input; replacement policy is required.');
    $reservation = $this->reviewedPolicy['cost_policy']['reservation_cents'] ?? NULL;
    if (!is_int($reservation)) throw new \RuntimeException('No reviewed creative cost policy is installed.');
    WorkerCapabilityPolicy::assertProof($record['job'], $reservation, [$this->reviewedPolicy['cost_policy']['id'] => $this->reviewedPolicy]);
    $p = $record['payload'];
    $campaign = $this->locked('proof_campaign', 'id', (int) $row['proof_campaign_id']);
    if (!$campaign || $campaign['campaign_id'] !== $p['campaign_id'] || $campaign['studio_job_id'] !== $p['studio_job_id']
      || (int) $campaign['prospect_id'] !== (int) $row['prospect_id']) throw new \RuntimeException('Existing proof campaign differs.');
    return $record;
  }

  private function locked(string $table, string $field, int $id): array|false {
    return $this->database->select($table, 't')->fields('t')->condition($field, $id)->forUpdate()->execute()->fetchAssoc();
  }

  private function assertAccount(array $r): void {
    $customer = $this->locked('famtastic_customer', 'id', (int) $r['customer_id']);
    $organization = $this->locked('famtastic_organization', 'id', (int) $r['organization_id']);
    $member = $this->database->select('famtastic_membership', 'm')->fields('m')->condition('customer_id', (int) $r['customer_id'])
      ->condition('organization_id', (int) $r['organization_id'])->forUpdate()->execute()->fetchAssoc();
    $resource = $this->database->select('famtastic_customer_resource', 'r')->fields('r')->condition('resource_type', 'prospect')
      ->condition('resource_id', (int) $r['prospect_id'])->forUpdate()->execute()->fetchAssoc();
    $prospect = $this->locked('famtastic_prospect', 'id', (int) $r['prospect_id']);
    if (!$customer || empty($customer['verified_at']) || !$organization || $organization['status'] !== 'active'
      || !$member || $member['status'] !== 'active' || !$resource || (int) $resource['organization_id'] !== (int) $r['organization_id']
      || !$prospect) throw new \RuntimeException('Verified account, active membership and exact prospect ownership are required.');
  }

  private function assertNoHistory(array $r): void {
    if ($r['proof_campaign_id'] !== NULL) throw new \RuntimeException('Existing proof campaign requires reconciliation.');
    $prefix = 'website_proof.generate.v1:request:' . $r['id'];
    $q = $this->database->select('famtastic_job', 'j');
    $scope = $q->orConditionGroup()->condition('job_key', $prefix)->condition('job_key', $this->database->escapeLike($prefix . ':') . '%', 'LIKE')
      ->condition($q->andConditionGroup()->condition('job_type', 'proof.generate')->condition('prospect_id', (int) $r['prospect_id']));
    // Current locking reads, not an earlier repeatable-read snapshot. Account,
    // resource and prospect rows already serialize other fresh admissions.
    if ($q->fields('j', ['id'])->condition($scope)->range(0, 1)->forUpdate()->execute()->fetchField()
      || $this->database->select('proof_campaign', 'c')->fields('c', ['id'])->condition('prospect_id', (int) $r['prospect_id'])->range(0, 1)->forUpdate()->execute()->fetchField()) {
      throw new \RuntimeException('Historical proof work requires reconciliation, never fresh enrollment.');
    }
  }
}
