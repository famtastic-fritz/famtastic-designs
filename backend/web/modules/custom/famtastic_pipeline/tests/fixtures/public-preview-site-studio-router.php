<?php

declare(strict_types=1);

/**
 * Local-only Site Studio dispatch double for public-preview lifecycle tests.
 *
 * It validates the wire contract but never creates proofs, contacts a provider,
 * or returns customer-facing content. The acceptance script supplies its own
 * explicitly-labelled callback fixture after Drupal has recorded this dispatch.
 */

$body = file_get_contents('php://input') ?: '';
$secret = (string) getenv('SITE_STUDIO_MOCK_SECRET');
$provided = (string) ($_SERVER['HTTP_X_FAMTASTIC_SIGNATURE'] ?? '');
$expected = 'sha256=' . hash_hmac('sha256', $body, $secret);
$data = json_decode($body, TRUE);

header('Content-Type: application/json');
if ($secret === '' || !hash_equals($expected, $provided)) {
  http_response_code(401);
  echo json_encode(['error' => 'invalid_signature']);
  return;
}

$routine = is_array($data) ? (string) ($data['routine'] ?? '') : '';
$directions = is_array($data) ? array_keys((array) ($data['directions'] ?? [])) : [];
$contractDirections = is_array($data) ? array_keys((array) ($data['direction_contract'] ?? [])) : [];
$proofPhase = is_array($data) ? (string) ($data['proof_runner']['contract']['source']['proof_phase'] ?? 'initial') : '';
$expectedDirections = $routine !== 'website_proof.generate.v1'
  ? []
  : match ($proofPhase) {
    'showcase' => ['d', 'e', 'f'],
    'refined_six' => ['a', 'b', 'c', 'd', 'e', 'f'],
    // A revision is not a global “direction g”: the runner dispatches exactly
    // the persisted selected a-f direction and the fixture only echoes that
    // signed one-direction contract. It never generates any proof bytes.
    'revision' => $contractDirections,
    default => ['a', 'b', 'c'],
  };
if (
  !is_array($data)
  || ($data['schema_version'] ?? NULL) !== 2
  || ($data['required_variant_count'] ?? NULL) !== count($expectedDirections)
  || $expectedDirections === []
  || ($proofPhase === 'revision' && count($expectedDirections) !== 1)
  || $directions !== $expectedDirections
  || $contractDirections !== $expectedDirections
  || trim((string) ($data['campaign_id'] ?? '')) === ''
  || trim((string) ($data['idempotency_key'] ?? '')) === ''
) {
  http_response_code(422);
  echo json_encode(['error' => 'invalid_public_preview_contract']);
  return;
}

$capture = trim((string) getenv('SITE_STUDIO_MOCK_CAPTURE'));
if ($capture !== '') {
  $record = [
    'classification' => 'fixture_local_only',
    'routine' => $routine,
    'campaign_id' => (string) $data['campaign_id'],
    'idempotency_key' => (string) $data['idempotency_key'],
    'proof_phase' => $proofPhase,
    'directions' => $directions,
    'direction_contract' => (array) $data['direction_contract'],
    'callback_url' => (string) ($data['callback_url'] ?? ''),
    // Capture only opaque Build-DNA correlation. Never persist the sanitized
    // source contract itself: this loopback double proves the dispatch shape,
    // not a provider's ability to generate a customer proof.
    'proof_runner' => [
      'build_id' => (string) ($data['proof_runner']['build_id'] ?? ''),
      'contract_sha256' => (string) ($data['proof_runner']['contract_sha256'] ?? ''),
      'profile_id' => (string) ($data['proof_runner']['profile_id'] ?? ''),
      'campaign_id' => (string) ($data['proof_runner']['campaign_id'] ?? ''),
      'proof_campaign_id' => (int) ($data['proof_runner']['proof_campaign_id'] ?? 0),
    ],
  ];
  file_put_contents($capture, json_encode($record, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
}

echo json_encode([
  'job_id' => 'fixture-' . substr(hash('sha256', $routine . '|' . (string) $data['campaign_id'] . '|' . (string) $data['idempotency_key']), 0, 40),
]);
