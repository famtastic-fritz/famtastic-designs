<?php
declare(strict_types=1);

/** Test-only SDK transport. Not a gateway, payment engine, or production module. */
final class NativeProbeGuard implements \Stripe\HttpClient\ClientInterface {
  private string $journalDirectory;
  public function __construct(private string $directory, private string $key, private \Stripe\HttpClient\ClientInterface $transport, ?string $journalDirectory = NULL) {
    $this->journalDirectory = $journalDirectory ?? $directory;
  }

  public static function durable(string $path, string $data, bool $append = FALSE): void {
    $file = @fopen($path, $append ? 'ab' : 'cb');
    if (!$file) throw new RuntimeException('journal_open_failed');
    try {
      if (!flock($file, LOCK_EX)) throw new RuntimeException('journal_lock_failed');
      if (!$append && (!ftruncate($file, 0) || !rewind($file))) throw new RuntimeException('journal_truncate_failed');
      $offset = 0;
      while ($offset < strlen($data)) {
        $written = @fwrite($file, substr($data, $offset));
        if (!$written) throw new RuntimeException('journal_write_failed');
        $offset += $written;
      }
      if (!fflush($file) || !fsync($file)) throw new RuntimeException('journal_sync_failed');
    }
    finally { fclose($file); }
  }

  public static function allow(string $method, string $url, array $params, array $binding, bool $file = FALSE): string {
    $fail = static fn() => throw new RuntimeException('provider_request_refused');
    $u = parse_url($url);
    if ($file || !$u || ($u['scheme'] ?? '') !== 'https' || ($u['host'] ?? '') !== 'api.stripe.com'
      || isset($u['user']) || isset($u['pass']) || isset($u['port']) || isset($u['query']) || isset($u['fragment'])) $fail();
    $path = $u['path'] ?? '';
    if ($method === 'get' && !$params && in_array($path, ['/v1/account', '/v1/balance'], TRUE)) return $path;
    if (($binding['verified_account'] ?? '') !== 'acct_1TqwE9DDGtWR2WVN' || ($binding['verified_test'] ?? NULL) !== TRUE) $fail();
    $metadata = ['order_id' => (string) $binding['order_id'], 'store_id' => (string) $binding['store_id'], 'native_probe' => $binding['run_id']];
    $same = static function(array $a, array $b): bool { ksort($a); ksort($b); return $a == $b; };
    $intent = $binding['intent_id'] ?? '';
    $methodId = $binding['method_id'] ?? '';
    $event = $binding['event_id'] ?? '';
    if ($method === 'get' && !$params) {
      foreach (['/v1/payment_intents/' => $intent, '/v1/payment_methods/' => $methodId, '/v1/events/' => $event] as $prefix => $id) {
        if ($id !== '' && $path === $prefix . $id) return $path;
      }
    }
    if ($method === 'post' && $path === '/v1/payment_intents' && !$intent && ($binding['phase'] ?? '') === 'create') {
      $expected = ['amount' => 19900, 'currency' => 'usd', 'capture_method' => 'automatic', 'metadata' => $metadata,
        'automatic_payment_methods' => ['enabled' => TRUE, 'allow_redirects' => 'never'],
        // Locked native plugin adds this option while enumerating method types;
        // it is not a new bank-payment feature or a manual method restriction.
        'payment_method_options' => ['us_bank_account' => ['verification_method' => 'instant']]];
      if ($same($params, $expected)) return $path;
    }
    if ($method === 'post' && $intent && $path === '/v1/payment_intents/' . $intent . '/confirm'
      && ($binding['phase'] ?? '') === 'confirm' && $params === ['payment_method' => 'pm_card_visa']) return $path;
    if ($method === 'post' && $intent && $path === '/v1/payment_intents/' . $intent
      && $same($params, ['metadata' => $metadata])) return $path;
    if ($method === 'post' && $path === '/v1/refunds' && $intent && ($binding['phase'] ?? '') === 'refund'
      && $same($params, ['amount' => 19900, 'payment_intent' => $intent,
        'metadata' => ['refund_source' => 'Drupal', 'refund_uid' => '0']])) return $path;
    $fail();
  }

  public function request($method, $absUrl, $headers, $params, $hasFile) {
    $binding = json_decode(file_get_contents($this->directory . '/binding.json'), TRUE, 512, JSON_THROW_ON_ERROR);
    if (time() > $binding['expires_at'] || !preg_match('/^(sk|rk)_test_[A-Za-z0-9_]+$/', $this->key)) throw new RuntimeException('expired_or_non_test_binding');
    $path = self::allow($method, $absUrl, $params, $binding, $hasFile);
    $authorization = array_values(array_filter($headers, static fn($h) => stripos($h, 'Authorization:') === 0));
    if ($authorization !== ['Authorization: Bearer ' . $this->key]) throw new RuntimeException('credential_binding_refused');
    foreach ($headers as $index => $header) {
      if (preg_match('/^Stripe-(Account|Context):(.*)$/i', $header, $match)) {
        if (trim($match[2]) !== '') throw new RuntimeException('connected_account_refused');
        // SDK15 emits an empty header for its default null stripe_account.
        // Remove only that empty header; never accept any account/context value.
        unset($headers[$index]);
      }
    }
    $headers = array_values($headers);
    $countPath = $this->journalDirectory . '/request-count';
    $count = is_file($countPath) ? (int) file_get_contents($countPath) : 0;
    if ($count >= 35) throw new RuntimeException('provider_request_limit');
    self::durable($countPath, (string) ($count + 1));
    // Stable per-run intent/refund idempotency, including an uncertain response.
    if ($method === 'post' && in_array($path, ['/v1/payment_intents', '/v1/refunds'], TRUE)) {
      $headers = array_values(array_filter($headers, static fn($h) => stripos($h, 'Idempotency-Key:') !== 0));
      $headers[] = 'Idempotency-Key: ' . $binding['run_id'] . ($path === '/v1/refunds' ? '-refund' : '-intent');
    }
    self::durable($this->journalDirectory . '/attempts.jsonl', json_encode(['method' => $method, 'path' => $path,
      'run_id' => $binding['run_id'], 'account_id' => 'acct_1TqwE9DDGtWR2WVN', 'intent_id' => $binding['intent_id'] ?? NULL]) . "\n", TRUE);
    $result = $this->transport->request($method, $absUrl, $headers, $params, $hasFile);
    $object = json_decode($result[0], TRUE, 512, JSON_THROW_ON_ERROR);
    if ($result[1] >= 400) throw new RuntimeException('provider_http_' . (int) $result[1]);
    if (($object['object'] ?? '') === 'refund' && $path === '/v1/refunds') {
      // Refund objects do not carry livemode. Bind to the already test-proven PI,
      // exact amount/currency and successful status; never treat missing as false.
      if (($object['payment_intent'] ?? '') !== $binding['intent_id'] || ($object['amount'] ?? 0) !== 19900
        || ($object['currency'] ?? '') !== 'usd' || ($object['status'] ?? '') !== 'succeeded') throw new RuntimeException('refund_response_refused');
    }
    elseif ($path !== '/v1/account' && ($object['livemode'] ?? NULL) !== FALSE) throw new RuntimeException('provider_mode_refused');
    $safe = ['method' => $method, 'path' => $path, 'status' => $result[1], 'request_id' => $result[2]['Request-Id'] ?? NULL,
      'provider_object_id' => isset($object['id']) && preg_match('/^(pi|pm|evt|re)_[A-Za-z0-9]+$/', $object['id']) ? $object['id'] : NULL];
    self::durable($this->journalDirectory . '/requests.jsonl', json_encode($safe) . "\n", TRUE);
    return $result;
  }
}
