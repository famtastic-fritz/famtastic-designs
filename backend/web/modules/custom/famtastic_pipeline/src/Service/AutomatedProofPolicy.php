<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

/** Pure gate. Evidence is supplied by a separately authenticated reviewer. */
final class AutomatedProofPolicy {
  public const VERSION = 'customer-delivery-green-v1';
  public const CHECKS = ['desktop', 'mobile', 'accessibility', 'links', 'functional', 'rights', 'claims', 'no_live_checkout', 'distinct_directions'];

  public static function decide(array $request, array $hashes, string $researchHash, array $evidence, string $reviewer): array {
    if (($request['status'] ?? '') !== 'submitted' || empty($request['customer_id']) || empty($request['proof_campaign_id'])
      || ($request['proof_review_status'] ?? '') !== 'owner_review') {
      throw new \InvalidArgumentException('A submitted account-bound proof set awaiting QA is required.');
    }
    if ($reviewer === '' || !str_starts_with($reviewer, 'automation:')
      || ($evidence['reviewer'] ?? '') !== $reviewer || ($evidence['producer'] ?? '') === $reviewer
      || trim((string) ($evidence['producer'] ?? '')) === '' || ($evidence['policy_version'] ?? '') !== self::VERSION
      || ($evidence['request_id'] ?? NULL) !== (int) $request['id']
      || ($evidence['customer_id'] ?? NULL) !== (int) $request['customer_id']
      || ($evidence['campaign_id'] ?? NULL) !== (int) $request['proof_campaign_id']) {
      throw new \InvalidArgumentException('Independent automated QA identity or account/campaign/policy binding is invalid.');
    }
    ksort($hashes);
    $supplied = $evidence['artifact_hashes'] ?? [];
    if (!is_array($supplied)) throw new \InvalidArgumentException('Artifact hashes are required.');
    ksort($supplied);
    if (array_keys($hashes) !== ['a', 'b', 'c'] || $hashes !== $supplied || count(array_unique($hashes)) !== 3
      || !preg_match('/^[a-f0-9]{64}$/', $researchHash) || ($evidence['research_sha256'] ?? '') !== $researchHash) {
      throw new \InvalidArgumentException('QA must bind the current research and exactly three distinct artifacts.');
    }
    foreach ($hashes as $hash) if (!preg_match('/^[a-f0-9]{64}$/', $hash)) throw new \InvalidArgumentException('Invalid artifact digest.');
    if (($evidence['exceptions'] ?? NULL) !== [] || ($evidence['scope_in_bounds'] ?? NULL) !== TRUE) {
      throw new \InvalidArgumentException('Scope, spending, rights, security and repeated QA failures require exception review.');
    }
    foreach (self::CHECKS as $check) {
      $result = $evidence['checks'][$check] ?? [];
      if (($result['passed'] ?? NULL) !== TRUE || !preg_match('/^[a-f0-9]{64}$/', (string) ($result['evidence_sha256'] ?? ''))
        || !preg_match('/^(?:evidence|sha256):[A-Za-z0-9._\/-]+$/', (string) ($result['evidence_ref'] ?? ''))) {
        throw new \InvalidArgumentException('Missing independently retained QA evidence: ' . $check);
      }
    }
    return [
      'schema' => 'famtastic.automated-proof-decision.v1', 'policy_version' => self::VERSION,
      'actor' => $reviewer, 'actor_type' => 'automation', 'owner_uid' => NULL,
      'request_id' => (int) $request['id'], 'customer_id' => (int) $request['customer_id'],
      'campaign_id' => (int) $request['proof_campaign_id'], 'artifact_hashes' => $hashes,
      'research_sha256' => $researchHash, 'evidence_sha256' => hash('sha256', json_encode($evidence, JSON_THROW_ON_ERROR)),
      'evidence' => $evidence, 'decision' => 'customer_review_ready',
      'not_acceptance_not_payment_not_launch' => TRUE,
    ];
  }
}
