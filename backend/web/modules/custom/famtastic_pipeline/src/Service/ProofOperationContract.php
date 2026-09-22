<?php
declare(strict_types=1);
namespace Drupal\famtastic_pipeline\Service;

/** Closed metadata contract. No free text, media, URL, path or credential fields. */
final class ProofOperationContract {
  public const MAX_RECEIPT_BYTES = 65536;

  public static function keys(array $value, array $keys): void {
    if (array_diff(array_keys($value), $keys) || array_diff($keys, array_keys($value))) throw new \InvalidArgumentException('Operation fields differ from the closed schema.');
  }
  public static function id(mixed $value): void {
    if (!is_string($value) || !preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9_-]{0,127}\z/', $value)) throw new \InvalidArgumentException('Invalid operation identifier.');
  }
  public static function digest(mixed $value): void {
    if (!is_string($value) || !preg_match('/\A[a-f0-9]{64}\z/', $value)) throw new \InvalidArgumentException('Invalid operation digest.');
  }
  public static function integer(mixed $value, int $min, int $max): void {
    if (!is_int($value) || $value < $min || $value > $max) throw new \InvalidArgumentException('Operation integer is outside its bound.');
  }
  public static function wire(array $value): string { return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES); }

  /** Source-injected catalog only; never accept a worker-supplied slot catalog. */
  public static function slot(array $payload, array $catalog, string $slot): array {
    $policy = $catalog[$payload['cost_policy']['id']] ?? [];
    self::keys($policy, ['recipe', 'tool_allowlist', 'cost_policy', 'slots']);
    foreach (['recipe', 'tool_allowlist', 'cost_policy'] as $key) {
      if ($policy[$key] !== $payload[$key]) throw new \RuntimeException('Operation policy differs from the immutable proof policy.');
    }
    if (!is_array($policy['slots']) || !$policy['slots'] || count($policy['slots']) > 32) throw new \RuntimeException('Reviewed operation slots are missing or unbounded.');
    foreach ($policy['slots'] as $name => $spec) {
      self::id($name);
      if (!is_array($spec)) throw new \InvalidArgumentException('Invalid operation slot.');
      self::keys($spec, ['tool', 'adapter', 'max_cost_cents', 'timeout_seconds', 'headroom_seconds']);
      self::id($spec['tool']); self::id($spec['adapter']);
      if (!in_array($spec['tool'], $payload['tool_allowlist'], TRUE)) throw new \RuntimeException('Operation tool is outside the frozen allowlist.');
      self::integer($spec['max_cost_cents'], 1, $payload['cost_policy']['max_cost_cents']);
      self::integer($spec['timeout_seconds'], 1, 150);
      self::integer($spec['headroom_seconds'], 5, 30);
    }
    if (!isset($policy['slots'][$slot])) throw new \RuntimeException('Operation slot is not reviewed.');
    return $policy['slots'][$slot];
  }

  /** Hashes refer to privately verified input; the journal never loads paths. */
  public static function input(array $input): array {
    self::keys($input, ['input_sha256', 'prompt_sha256', 'asset_ids']);
    self::digest($input['input_sha256']); self::digest($input['prompt_sha256']);
    if (!is_array($input['asset_ids']) || !array_is_list($input['asset_ids']) || count($input['asset_ids']) > 32) throw new \InvalidArgumentException('Invalid operation asset set.');
    $previous = 0;
    foreach ($input['asset_ids'] as $id) { self::integer($id, $previous + 1, PHP_INT_MAX); $previous = $id; }
    return ['input_sha256' => $input['input_sha256'], 'prompt_sha256' => $input['prompt_sha256'], 'asset_ids' => $input['asset_ids']];
  }

  /** Conservative AI use: no active claimed asset without all recorded consents. */
  public static function rights(array $input, array $binding): void {
    $records = array_column($binding['asset_snapshot']['records'], NULL, 'id');
    foreach ($input['asset_ids'] as $id) {
      $a = $records[$id] ?? [];
      if (($a['status'] ?? '') !== 'active' || empty($a['ownership_confirmed']) || empty($a['ai_use_consent'])
        || empty($a['subject_permission_confirmed']) || empty($a['ai_transformation_consent'])
        || empty($a['likeness_consent_version']) || empty($a['likeness_consent_at'])) throw new \RuntimeException('Operation asset use lacks explicit current rights.');
    }
  }

  /** Metadata checkpoint only; hashes do not establish media availability or QA. */
  public static function receipt(array $receipt): array {
    if (strlen(self::wire($receipt)) > self::MAX_RECEIPT_BYTES) throw new \InvalidArgumentException('Operation receipt exceeds 64 KiB.');
    self::keys($receipt, ['operation_id', 'input_sha256', 'adapter', 'provider_request_id', 'outcome', 'cost_status', 'actual_cost_cents', 'checkpoint']);
    self::digest($receipt['operation_id']); self::digest($receipt['input_sha256']);
    self::id($receipt['adapter']); self::id($receipt['provider_request_id']);
    if (!in_array($receipt['outcome'], ['succeeded', 'failed'], TRUE) || !in_array($receipt['cost_status'], ['unknown', 'verified'], TRUE)) throw new \InvalidArgumentException('Invalid operation receipt outcome.');
    if ($receipt['cost_status'] === 'unknown') {
      if ($receipt['actual_cost_cents'] !== NULL) throw new \InvalidArgumentException('Unknown cost must remain null.');
    }
    else self::integer($receipt['actual_cost_cents'], 0, 250);
    $items = $receipt['checkpoint'];
    if (!is_array($items) || !array_is_list($items) || count($items) > 32 || ($receipt['outcome'] === 'succeeded' && !$items)
      || ($receipt['outcome'] === 'failed' && $items)) throw new \InvalidArgumentException('Invalid operation checkpoint inventory.');
    $names = [];
    foreach ($items as $item) {
      if (!is_array($item)) throw new \InvalidArgumentException('Invalid checkpoint item.');
      self::keys($item, ['name', 'sha256', 'bytes']); self::id($item['name']); self::digest($item['sha256']); self::integer($item['bytes'], 1, 65536);
      if (isset($names[$item['name']])) throw new \InvalidArgumentException('Duplicate checkpoint item.');
      $names[$item['name']] = TRUE;
    }
    // Fixed ordering means semantically identical receipt retries have exact bytes.
    $result = [];
    foreach (['operation_id', 'input_sha256', 'adapter', 'provider_request_id', 'outcome', 'cost_status', 'actual_cost_cents'] as $key) $result[$key] = $receipt[$key];
    $result['checkpoint'] = array_map(static fn($i) => ['name' => $i['name'], 'sha256' => $i['sha256'], 'bytes' => $i['bytes']], $items);
    return $result;
  }
}
