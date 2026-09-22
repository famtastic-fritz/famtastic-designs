<?php
declare(strict_types=1);
namespace Drupal\famtastic_pipeline\Service;

use Drupal\Core\Database\Connection;

/** Immutable admission evidence also closes every unfenced legacy importer. */
final class FreshProofBinding {
  public const EVENT = 'proof.fresh_admitted.v1';
  public static function key(int $requestId): string { return 'proof-admission:request:' . $requestId; }

  public static function event(Connection $db, int $requestId, bool $lock = FALSE): array|false {
    $query = $db->select('famtastic_event', 'e')->fields('e')->condition('event_key', self::key($requestId));
    if ($lock) $query->forUpdate();
    return $query->execute()->fetchAssoc();
  }

  /** Stored identity survives flag changes, input edits and malformed evidence. */
  public static function isManaged(Connection $db, array $request): bool {
    $query = $db->select('famtastic_event', 'e');
    $scope = $query->orConditionGroup()->condition('event_key', self::key((int) $request['id']));
    if (!empty($request['proof_campaign_id'])) {
      $marker = $query->orConditionGroup()->condition('event_type', self::EVENT)
        ->condition('event_key', $db->escapeLike('proof-admission:request:') . '%', 'LIKE');
      $scope->condition($query->andConditionGroup()->condition('campaign_id', (int) $request['proof_campaign_id'])->condition($marker));
    }
    return (bool) $query->fields('e', ['id'])->condition($scope)->range(0, 1)->forUpdate()->execute()->fetchField();
  }

  public static function assertGenericImportAllowed(Connection $db, int $campaignId): void {
    // An invalid marker must also deny: no parsing failure can reopen a bypass.
    $requests = $db->select('famtastic_project_request', 'r')->fields('r', ['id'])->condition('proof_campaign_id', $campaignId)->execute()->fetchCol();
    $query = $db->select('famtastic_event', 'e');
    $marker = $query->orConditionGroup()->condition('event_type', self::EVENT)
      ->condition('event_key', $db->escapeLike('proof-admission:request:') . '%', 'LIKE');
    $scope = $query->orConditionGroup()->condition($query->andConditionGroup()->condition('campaign_id', $campaignId)->condition($marker));
    if ($requests) $scope->condition('event_key', array_map(static fn($id) => self::key((int) $id), $requests), 'IN');
    if ($query->fields('e', ['id'])->condition($scope)->range(0, 1)->forUpdate()->execute()->fetchField()) {
      throw new \InvalidArgumentException('Managed proof campaigns require the authoritative fenced importer; completion is closed.');
    }
  }

  /** Return only a byte-identical binding with its exact job and claim. */
  public static function read(Connection $db, array $event, array $request, bool $lock = FALSE): array {
    $b = json_decode((string) $event['payload'], TRUE, flags: JSON_THROW_ON_ERROR);
    if (!is_array($b) || array_keys($b) !== ['schema', 'freshness', 'request_snapshot', 'asset_snapshot', 'job_id', 'job_key', 'payload_wire', 'payload_sha256']
      || FreshProofInput::wire($b) !== $event['payload'] || ($b['request_snapshot']['freshness'] ?? NULL) !== $b['freshness']
      || ($b['schema'] ?? '') !== 'famtastic.fresh-proof-admission.v1'
      || $event['event_key'] !== self::key((int) $request['id']) || $event['event_type'] !== self::EVENT
      || (int) $event['campaign_id'] !== (int) $request['proof_campaign_id']
      || (int) $event['prospect_id'] !== (int) $request['prospect_id']) throw new \RuntimeException('Proof admission evidence changed.');
    $jobs = $db->select('famtastic_job', 'j')->fields('j')->condition('id', $b['job_id'] ?? 0);
    $claims = $db->select('famtastic_worker_claim', 'c')->fields('c')->condition('job_id', $b['job_id'] ?? 0);
    if ($lock) { $jobs->forUpdate(); $claims->forUpdate(); }
    $job = $jobs->execute()->fetchAssoc();
    $claim = $claims->execute()->fetchAssoc();
    $p = json_decode((string) ($job['payload'] ?? ''), TRUE, flags: JSON_THROW_ON_ERROR);
    if (!$job || !$claim || $job['job_type'] !== 'proof.generate' || $job['job_key'] !== ($b['job_key'] ?? NULL)
      || (int) $job['max_attempts'] !== 3 || (int) $job['prospect_id'] !== (int) $request['prospect_id']
      || $job['payload'] !== ($b['payload_wire'] ?? NULL) || hash('sha256', $job['payload']) !== ($b['payload_sha256'] ?? NULL)
      || $claim['payload_sha256'] !== $b['payload_sha256'] || $claim['capability'] !== WorkerCapabilityPolicy::PROOF
      || $claim['policy_version'] !== WorkerCapabilityPolicy::PROOF_POLICY
      || (int) $claim['reservation_cents'] !== ($p['cost_policy']['reservation_cents'] ?? NULL)
      || ($p['website_request_id'] ?? NULL) !== (int) $request['id'] || ($p['website_request_public_id'] ?? NULL) !== $request['public_id']
      || ($p['customer_id'] ?? NULL) !== (int) $request['customer_id'] || ($p['organization_id'] ?? NULL) !== (int) $request['organization_id']
      || ($p['prospect_id'] ?? NULL) !== (int) $request['prospect_id'] || ($p['proof_campaign_id'] ?? NULL) !== (int) $request['proof_campaign_id']
      || hash('sha256', FreshProofInput::wire($b['request_snapshot'] ?? [])) !== ($p['request_binding_sha256'] ?? NULL)
      || hash('sha256', FreshProofInput::wire($b['asset_snapshot'] ?? [])) !== ($p['asset_authority_sha256'] ?? NULL)) throw new \RuntimeException('Proof admission job binding changed.');
    if ($b['request_snapshot'] !== FreshProofInput::request($request, $b['freshness'])) throw new \RuntimeException('Proof admission differs from current input.');
    return ['binding' => $b, 'job' => $job, 'claim' => $claim, 'payload' => $p];
  }

  public static function handoff(Connection $db, array $request, int $now): ?array {
    $event = self::event($db, (int) $request['id']);
    if (!$event) return NULL;
    $base = ['state' => 'needs_attention', 'label' => 'Proof preparation needs attention',
      'detail' => 'Your request is saved. No completed proof import is recorded.'];
    try {
      // A committed import changes this ONE lifecycle field. Validate its actual
      // receipt first; a ready campaign or manually set review state is not proof.
      // This is a status projection, not provider, artifact-read or QA authority.
      if (($request['proof_review_status'] ?? '') === 'owner_review') {
        $admission = json_decode((string) $event['payload'], TRUE, 32, JSON_THROW_ON_ERROR);
        if (!is_int($admission['job_id'] ?? NULL)
          || ($admission['request_snapshot']['proof_review_status'] ?? '') !== 'not_started') return $base;
        $facts = ManagedProofImportReceipt::committed($db, $admission['job_id']);
        if ($facts['receipt']['request_id'] !== (int) $request['id']) return $base;
        $beforeReview = $request;
        $beforeReview['proof_review_status'] = 'not_started';
        // FreshProofInput itself remains exact and unchanged. All authored,
        // tenant, selection, project, commercial and asset fields must still match.
        $record = self::read($db, $event, $beforeReview);
        if ($record['binding']['asset_snapshot'] !== FreshProofInput::assets($db, $request, FALSE)) return $base;
        return [
          'state' => 'owner_review', 'label' => 'Proof files saved; independent review pending',
          'detail' => 'Your three proof files have been saved. Independent quality review is pending; they have not been released to your account.',
          'job_id' => (int) $record['job']['id'], 'job_status' => $record['job']['status'],
          'attempts' => (int) $record['claim']['attempt'], 'max_attempts' => 3,
        ];
      }
      $record = self::read($db, $event, $request);
      if ($record['binding']['asset_snapshot'] !== FreshProofInput::assets($db, $request, FALSE)) return $base;
    }
    catch (\Throwable) { return $base; }
    $job = $record['job']; $claim = $record['claim'];
    $base += ['job_id' => (int) $job['id'], 'job_status' => $job['status'], 'attempts' => (int) $claim['attempt'], 'max_attempts' => 3];
    if ($job['status'] === 'worker_queued' && $claim['state'] === 'pending') return array_replace($base, [
      'state' => 'queued', 'label' => 'Proof request queued', 'detail' => 'Your brief is reserved in the shared proof queue. Creative execution and import are not yet confirmed.']);
    if ($job['status'] === 'worker_running' && $claim['state'] === 'leased' && (int) $claim['lease_until'] > $now
      && (int) $claim['attempt_deadline'] - 30 > $now) return array_replace($base, [
        'state' => 'preparing', 'label' => 'Proof worker assigned', 'detail' => 'A worker holds this proof request. Generated artifacts and their authoritative import are not yet confirmed.']);
    return $base;
  }
}
