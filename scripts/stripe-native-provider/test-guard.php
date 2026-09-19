<?php
declare(strict_types=1);
require rtrim(getenv('FAMTASTIC_BACKEND_VENDOR') ?: '', '/') . '/autoload.php';
require __DIR__ . '/Guard.php';
$binding = ['run_id' => 'native-probe-synthetic', 'order_id' => '1', 'store_id' => '1', 'phase' => 'create',
  'verified_account' => 'acct_1TqwE9DDGtWR2WVN', 'verified_test' => TRUE];
$metadata = ['order_id' => '1', 'store_id' => '1', 'native_probe' => $binding['run_id']];
$params = ['amount' => 19900, 'currency' => 'usd', 'capture_method' => 'automatic', 'metadata' => $metadata,
  'automatic_payment_methods' => ['enabled' => TRUE, 'allow_redirects' => 'never'],
  'payment_method_options' => ['us_bank_account' => ['verification_method' => 'instant']]];
$passed = 0;
$check = static function(string $name, string $method, string $url, array $params, array $binding, bool $expected) use (&$passed): void {
  try { NativeProbeGuard::allow($method, $url, $params, $binding); $allowed = TRUE; }
  catch (RuntimeException) { $allowed = FALSE; }
  if ($allowed !== $expected) throw new RuntimeException('Failed: ' . $name);
  ++$passed;
};
$base = 'https://api.stripe.com';
$check('create own', 'post', "$base/v1/payment_intents", $params, $binding, TRUE);
foreach (['https://evil.example/v1/payment_intents', 'http://api.stripe.com/v1/payment_intents', 'https://api.stripe.com:443/v1/payment_intents', 'https://user@api.stripe.com/v1/payment_intents', "$base/v1/payment_intents?test=1", "$base/v1/payment_intents#fragment", "$base/v1/customers"] as $url)
  $check('url refusal', 'post', $url, $params, $binding, FALSE);
foreach (['receipt_email' => 'client@example.com', 'customer' => 'cus_Foreign', 'amount' => 20000, 'currency' => 'eur', 'confirm' => TRUE, 'payment_method_types' => ['card'], 'shipping' => ['name' => 'Client']] as $field => $value)
  $check('field refusal ' . $field, 'post', "$base/v1/payment_intents", array_replace($params, [$field => $value]), $binding, FALSE);
foreach (['verified_account' => 'acct_Foreign', 'verified_test' => FALSE, 'phase' => 'confirmed', 'intent_id' => 'pi_Existing'] as $field => $value)
  $check('binding refusal ' . $field, 'post', "$base/v1/payment_intents", $params, array_replace($binding, [$field => $value]), FALSE);
$check('balance only read', 'get', "$base/v1/balance", [], [], TRUE);
$check('balance rejects params', 'get', "$base/v1/balance", ['account' => 'other'], [], FALSE);
$binding['intent_id'] = 'pi_Test'; $binding['phase'] = 'confirm';
$check('own confirm', 'post', "$base/v1/payment_intents/pi_Test/confirm", ['payment_method' => 'pm_card_visa'], $binding, TRUE);
$check('foreign confirm', 'post', "$base/v1/payment_intents/pi_Foreign/confirm", ['payment_method' => 'pm_card_visa'], $binding, FALSE);
$check('nonfixture method', 'post', "$base/v1/payment_intents/pi_Test/confirm", ['payment_method' => 'pm_Real'], $binding, FALSE);
$check('own metadata', 'post', "$base/v1/payment_intents/pi_Test", ['metadata' => $metadata], $binding, TRUE);
$check('foreign metadata', 'post', "$base/v1/payment_intents/pi_Test", ['metadata' => $metadata + ['client' => '17']], $binding, FALSE);
$binding['phase'] = 'refund';
$refund = ['payment_intent' => 'pi_Test', 'amount' => 19900, 'metadata' => ['refund_source' => 'Drupal', 'refund_uid' => '0']];
$check('own refund', 'post', "$base/v1/refunds", $refund, $binding, TRUE);
$check('foreign refund', 'post', "$base/v1/refunds", array_replace($refund, ['payment_intent' => 'pi_Foreign']), $binding, FALSE);
$directory = sys_get_temp_dir() . '/native-guard-test-' . bin2hex(random_bytes(8));
mkdir($directory, 0700);
$key = implode('_', ['rk', 'test', 'synthetic_not_real']);
$transport = new class implements \Stripe\HttpClient\ClientInterface {
  public int $calls = 0;
  public bool $uncertain = FALSE;
  public function request($method, $absUrl, $headers, $params, $hasFile) {
    ++$this->calls;
    if ($this->uncertain) throw new RuntimeException('simulated_uncertain_response');
    return ['{"object":"balance","livemode":false}', 200, []];
  }
};
try {
  $binding['expires_at'] = time() + 60;
  NativeProbeGuard::durable($directory . '/binding.json', json_encode($binding));
  $guard = new NativeProbeGuard($directory, $key, $transport);
  foreach (['request-count', 'attempts.jsonl'] as $blocked) {
    mkdir($directory . '/' . $blocked);
    try { $guard->request('get', "$base/v1/balance", ['Authorization: Bearer ' . $key], [], FALSE); throw new LogicException('journal failed open'); }
    catch (RuntimeException $e) { if (!str_starts_with($e->getMessage(), 'journal_') || $transport->calls !== 0) throw $e; ++$passed; }
    finally { rmdir($directory . '/' . $blocked); }
  }
  foreach ([['Authorization: Bearer rk_live_invalid'], ['Authorization: Bearer ' . $key, 'Stripe-Account: acct_Foreign']] as $headers) {
    try { $guard->request('get', "$base/v1/balance", $headers, [], FALSE); throw new LogicException('headers failed open'); }
    catch (RuntimeException $e) { if ($transport->calls !== 0) throw $e; ++$passed; }
  }
  $transport->uncertain = TRUE;
  try { $guard->request('get', "$base/v1/balance", ['Authorization: Bearer ' . $key], [], FALSE); throw new LogicException('uncertainty missing'); }
  catch (RuntimeException $e) {
    if ($transport->calls !== 1 || !is_file($directory . '/attempts.jsonl') || is_file($directory . '/requests.jsonl')) throw $e;
    ++$passed;
  }
  $transport->uncertain = FALSE;
  $guard->request('get', "$base/v1/balance", ['Authorization: Bearer ' . $key, 'Stripe-Account: ', 'Stripe-Context:'], [], FALSE);
  if ($transport->calls !== 2) throw new LogicException('Empty SDK account header not handled');
  ++$passed;
}
finally {
  foreach (['binding.json', 'request-count', 'attempts.jsonl', 'requests.jsonl'] as $file) if (is_file($directory . '/' . $file)) unlink($directory . '/' . $file);
  rmdir($directory);
}
print json_encode(['status' => 'passed', 'checks' => $passed, 'provider_calls' => 0]) . "\n";
