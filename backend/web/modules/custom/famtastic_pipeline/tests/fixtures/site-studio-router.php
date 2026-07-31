<?php

declare(strict_types=1);

$body = file_get_contents('php://input') ?: '';
$secret = getenv('SITE_STUDIO_MOCK_SECRET') ?: '';
$provided = $_SERVER['HTTP_X_FAMTASTIC_SIGNATURE'] ?? '';
$expected = 'sha256=' . hash_hmac('sha256', $body, $secret);
$data = json_decode($body, TRUE);

header('Content-Type: application/json');
if ($secret === '' || !hash_equals($expected, $provided)) {
  http_response_code(401);
  echo json_encode(['error' => 'invalid_signature']);
  return;
}
if (
  !is_array($data)
  || ($data['required_variant_count'] ?? NULL) !== 3
  || array_keys($data['directions'] ?? []) !== ['a', 'b', 'c']
) {
  http_response_code(422);
  echo json_encode(['error' => 'invalid_contract']);
  return;
}
echo json_encode(['job_id' => 'studio-' . $data['campaign_id']]);
