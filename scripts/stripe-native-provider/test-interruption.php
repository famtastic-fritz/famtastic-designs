<?php
// Fake transport only. Called by test-guard.php in its exact private temp folder.
require rtrim(getenv('FAMTASTIC_BACKEND_VENDOR') ?: '', '/') . '/autoload.php';
require __DIR__ . '/Guard.php';
$directory = realpath($argv[1] ?? '') ?: '';
if (!preg_match('#/native-guard-test-[a-f0-9]{16}/interrupt$#', $directory)) exit(2);
$key = implode('_', ['rk', 'test', 'synthetic_not_real']);
$transport = new class implements \Stripe\HttpClient\ClientInterface {
  public function request($method, $absUrl, $headers, $params, $hasFile) {
    // The injected exit must happen before this can be decoded or logged.
    return ['NOT_JSON_NEVER_LOG_THIS_RESPONSE', 200, []];
  }
};
$guard = new NativeProbeGuard($directory, $key, $transport);
$guard->request('post', 'https://api.stripe.com/v1/payment_intents/pi_Test/confirm',
  ['Authorization: Bearer ' . $key], ['payment_method' => 'pm_card_visa'], FALSE);
exit(3);
