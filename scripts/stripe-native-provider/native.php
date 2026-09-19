<?php
declare(strict_types=1);

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_order\Entity\OrderItemType;
use Drupal\commerce_payment\Entity\PaymentGateway;
use Drupal\commerce_store\Entity\Store;
use Drupal\commerce_store\Entity\StoreType;
use Symfony\Component\HttpFoundation\Request;

$root = realpath(getenv('NATIVE_PROBE_ROOT') ?: '') ?: '';
if (!preg_match('#/famtastic-stripe-native\.[A-Za-z0-9]{6}$#', $root) || realpath(__DIR__) !== $root . '/probe') exit(2);
$phase = getenv('NATIVE_PROBE_PHASE') ?: (in_array('configure', $_SERVER['argv'], TRUE) ? 'configure' : 'seed');
$bindingPath = $root . '/binding.json';
$binding = json_decode(file_get_contents($bindingPath), TRUE, 512, JSON_THROW_ON_ERROR);
$save = static function() use (&$binding, $bindingPath): void { NativeProbeGuard::durable($bindingPath, json_encode($binding)); };

if ($phase === 'configure') {
  $settings = $root . '/backend/web/sites/default/settings.php';
  if (file_exists($settings)) exit(2);
  copy(dirname($settings) . '/default.settings.php', $settings);
  file_put_contents($settings, "\n\$settings['hash_salt'] = " . var_export(bin2hex(random_bytes(24)), TRUE) . ";\n"
    . "\$settings['file_private_path'] = " . var_export($root . '/backend/private', TRUE) . ";\n"
    . "\$settings['trusted_host_patterns'] = ['^native-probe\\.example\\.test$'];\n"
    . "\$config['system.mail']['interface']['default'] = 'test_mail_collector';\n"
    . "\$config['automated_cron.settings']['interval'] = 0;\n"
    . "\$settings['container_yamls'][] = __DIR__ . '/probe.services.yml';\n", FILE_APPEND);
  file_put_contents($settings, <<<'PHP'

// Per-process only. Native nested gateway loads must receive the same ephemeral
// key through Drupal's config overrides; never save it to config storage.
if (in_array(getenv('NATIVE_PROBE_PHASE'), ['create', 'confirm', 'callback', 'replay', 'refund', 'inspect', 'observe', 'cancel', 'reconcile', 'recover_callback', 'recover_replay'], TRUE)) {
  if (!isset($GLOBALS['nativeProbeSecrets'])) {
    $GLOBALS['nativeProbeSecrets'] = json_decode(file_get_contents('php://stdin'), TRUE, 512, JSON_THROW_ON_ERROR);
  }
  $probeSecrets = $GLOBALS['nativeProbeSecrets'];
  if (!preg_match('/^(sk|rk)_test_[A-Za-z0-9_]+$/', $probeSecrets['key'] ?? '') || !preg_match('/^whsec_[A-Za-z0-9]+$/', $probeSecrets['webhook'] ?? '')) {
    throw new RuntimeException('test_secrets_refused');
  }
  $config['commerce_payment.commerce_payment_gateway.native_probe']['configuration']['secret_key'] = $probeSecrets['key'];
  $config['commerce_payment.commerce_payment_gateway.native_probe']['configuration']['webhook_signing_secret'] = $probeSecrets['webhook'];
}
PHP, FILE_APPEND);
  file_put_contents(dirname($settings) . '/probe.services.yml', "services:\n  native_probe_http_blocker:\n    class: GuzzleHttp\\Handler\\MockHandler\n  http_handler_stack:\n    class: GuzzleHttp\\HandlerStack\n    factory: GuzzleHttp\\HandlerStack::create\n    arguments: ['@native_probe_http_blocker']\n");
  chmod($settings, 0600);
  exit(0);
}

try {
  require __DIR__ . '/Guard.php';
  $db = \Drupal::database()->getConnectionOptions();
  if (\Drupal::root() !== $root . '/backend/web' || $db['driver'] !== 'sqlite'
    || realpath($db['database']) !== $root . '/backend/web/sites/default/files/.ht.sqlite'
    || \Drupal::moduleHandler()->moduleExists('famtastic_pipeline')
    || \Drupal::config('system.mail')->get('interface.default') !== 'test_mail_collector'
    || ini_get('allow_url_fopen') !== '0' || function_exists('mail') || function_exists('stream_socket_client')) throw new RuntimeException('runtime_boundary_refused');
  if ($phase === 'seed') {
    if (\Drupal::entityTypeManager()->getStorage('commerce_order')->getQuery()->accessCheck(FALSE)->count()->execute()) throw new RuntimeException('nonempty_runtime');
    if (!StoreType::load('online')) StoreType::create(['id' => 'online', 'label' => 'Online'])->save();
    if (!OrderItemType::load('native_probe')) OrderItemType::create(['id' => 'native_probe', 'label' => 'Synthetic test item', 'orderType' => 'default'])->save();
    \Drupal::service('commerce_price.currency_importer')->import('USD');
    $store = Store::create(['type' => 'online', 'name' => 'Synthetic test store', 'mail' => 'store@example.test', 'default_currency' => 'USD',
      'address' => ['country_code' => 'US', 'administrative_area' => 'FL', 'locality' => 'Test City', 'postal_code' => '34986', 'address_line1' => '1 Test Street']]);
    $store->save();
    $item = OrderItem::create(['type' => 'native_probe', 'title' => 'Synthetic native integration test', 'quantity' => '1', 'unit_price' => ['number' => '199.00', 'currency_code' => 'USD']]);
    $item->save();
    $gateway = PaymentGateway::create(['id' => 'native_probe', 'label' => 'Disposable test provider', 'plugin' => 'stripe_payment_element', 'status' => TRUE]);
    $gateway->setPluginConfiguration(['mode' => 'test', 'api_version' => '2024-06-20', 'authentication_method' => 'api_key',
      'payment_method_usage' => 'single_use', 'capture_method' => 'automatic', 'collect_billing_information' => FALSE]);
    $gateway->save();
    $order = Order::create(['type' => 'default', 'store_id' => $store->id(), 'uid' => 0, 'mail' => 'buyer@example.test',
      'state' => 'draft', 'order_items' => [$item], 'payment_gateway' => 'native_probe']);
    $order->save();
    $binding['order_id'] = (string) $order->id(); $binding['store_id'] = (string) $store->id(); $save();
    // Diagnose plugin reload behavior without a real key or any network.
    $fakeKey = implode('_', ['rk', 'test', 'synthetic_not_real']);
    $temporary = $gateway->getPluginConfiguration(); $temporary['secret_key'] = $fakeKey;
    $gateway->setPluginConfiguration($temporary)->getPlugin();
    $before = \Stripe\Stripe::getApiKey() === $fakeKey;
    $method = \Drupal::entityTypeManager()->getStorage('commerce_payment_method')->createForCustomer('stripe_card', 'native_probe', 0, NULL);
    $method->getPaymentGateway()->getPlugin();
    $after = \Stripe\Stripe::getApiKey() === $fakeKey;
    print json_encode(['phase' => 'seed', 'order_id' => $order->id(), 'amount' => $order->getTotalPrice()->getNumber(),
      'in_memory_key_before_nested_gateway' => $before, 'in_memory_key_after_nested_gateway' => $after]) . "\n";
    return;
  }
  if ($phase === 'offline_interrupt') {
    // Exercise the SAME Guard exit through the actual locked Drush wrapper.
    // No real key resolution, SDK transport or provider objects. Keep fake
    // journals separate from the provider ledger and leave the native order alone.
    if (function_exists('curl_exec')) throw new RuntimeException('offline_transport_required');
    $journal = realpath(getenv('NATIVE_PROBE_EVIDENCE') ?: '') ?: '';
    if (!str_ends_with($journal, '/.artifacts/stripe-native-provider/' . $binding['run_id'])) throw new RuntimeException('journal_boundary_refused');
    $fixture = $journal . '/offline-interruption';
    if (!mkdir($fixture, 0700)) throw new RuntimeException('offline_fixture_exists');
    $fakeBinding = $binding;
    $fakeBinding['scenario'] = 'recovery'; $fakeBinding['phase'] = 'confirm';
    $fakeBinding['intent_id'] = 'pi_Offline';
    $fakeBinding['verified_account'] = 'acct_1TqwE9DDGtWR2WVN'; $fakeBinding['verified_test'] = TRUE;
    NativeProbeGuard::durable($fixture . '/binding.json', json_encode($fakeBinding));
    $fakeKey = implode('_', ['rk', 'test', 'synthetic_not_real']);
    $fake = new class implements \Stripe\HttpClient\ClientInterface {
      public function request($method, $absUrl, $headers, $params, $hasFile) {
        return ['NOT_JSON_NEVER_LOG_THIS_RESPONSE', 200, []];
      }
    };
    $guard = new NativeProbeGuard($fixture, $fakeKey, $fake);
    $guard->request('post', 'https://api.stripe.com/v1/payment_intents/pi_Offline/confirm',
      ['Authorization: Bearer ' . $fakeKey], ['payment_method' => 'pm_card_visa'], FALSE);
    throw new RuntimeException('offline_interruption_missing');
  }
  // Pipe only: keys/signatures/raw events never enter files, CLI arguments or DB.
  $secrets = $GLOBALS['nativeProbeSecrets'] ?? [];
  if (!preg_match('/^(sk|rk)_test_[A-Za-z0-9_]+$/', $secrets['key'] ?? '') || !preg_match('/^whsec_[A-Za-z0-9]+$/', $secrets['webhook'] ?? '')) throw new RuntimeException('test_secrets_refused');
  $curl = new \Stripe\HttpClient\CurlClient([CURLOPT_PROXY => '', CURLOPT_FOLLOWLOCATION => FALSE]);
  $curl->setTimeout(15); $curl->setConnectTimeout(10);
  $journal = realpath(getenv('NATIVE_PROBE_EVIDENCE') ?: '') ?: '';
  if (!str_ends_with($journal, '/.artifacts/stripe-native-provider/' . $binding['run_id']) || str_starts_with($journal, $root . '/')) throw new RuntimeException('journal_boundary_refused');
  \Stripe\ApiRequestor::setHttpClient(new NativeProbeGuard($root, $secrets['key'], $curl, $journal));
  \Stripe\Stripe::setMaxNetworkRetries(0); // Preserve locked native plugin's SDK; no SDK migration here.
  $client = new \Stripe\StripeClient(['api_key' => $secrets['key'], 'stripe_version' => '2024-06-20']);
  $gateway = PaymentGateway::load('native_probe');
  $config = $gateway->getPluginConfiguration();
  $config['secret_key'] = $secrets['key']; $config['webhook_signing_secret'] = $secrets['webhook'];
  $gateway->setPluginConfiguration($config); // Deliberately not saved; secrets never enter Drupal config/DB.
  $plugin = $gateway->getPlugin();
  $diagnosticMethod = \Drupal::entityTypeManager()->getStorage('commerce_payment_method')->createForCustomer('stripe_card', 'native_probe', 0, NULL);
  $diagnosticMethod->getPaymentGateway()->getPlugin();
  if (\Stripe\Stripe::getApiKey() !== $secrets['key']) throw new RuntimeException('ephemeral_gateway_reload_failed');
  $order = Order::load($binding['order_id']);
  if (!$order || $order->getEmail() !== 'buyer@example.test' || $order->getCustomerId() != 0
    || (string) $order->getStoreId() !== $binding['store_id'] || $order->get('payment_gateway')->target_id !== 'native_probe'
    || !$order->getTotalPrice()->equals(new \Drupal\commerce_price\Price('199.00', 'USD'))) throw new RuntimeException('synthetic_order_refused');
  $scenario = $binding['scenario'] ?? 'success';
  $eventType = ['success' => 'payment_intent.succeeded', 'recovery' => 'payment_intent.succeeded', 'decline' => 'payment_intent.payment_failed',
    'action-required' => 'payment_intent.requires_action', 'abandonment' => 'payment_intent.canceled'][$scenario] ?? NULL;
  if (!$eventType) throw new RuntimeException('scenario_refused');
  $verifyIntent = static function($intent) use (&$binding): void {
    if ($intent->livemode !== FALSE || $intent->id !== $binding['intent_id'] || $intent->amount !== 19900 || $intent->currency !== 'usd' || $intent->capture_method !== 'automatic'
      || $intent->customer !== NULL || $intent->receipt_email !== NULL || $intent->metadata->native_probe !== $binding['run_id']
      || (string) $intent->metadata->order_id !== $binding['order_id'] || (string) $intent->metadata->store_id !== $binding['store_id'])
      throw new RuntimeException('intent_binding_refused');
  };
  $observeIntent = static function($intent) use (&$binding, $verifyIntent): void {
    $verifyIntent($intent);
    $binding['provider_status'] = $intent->status;
    $binding['amount_received'] = $intent->amount_received;
    $binding['action_required'] = $intent->status === 'requires_action' && !empty($intent->next_action->type);
    $binding['method_id'] = $intent->payment_method ?? '';
  };
  if ($phase === 'create') {
    if ($binding['intent_id'] ?? '') throw new RuntimeException('existing_intent_refused');
    if ($client->accounts->retrieve()->id !== 'acct_1TqwE9DDGtWR2WVN' || $client->balance->retrieve()->livemode !== FALSE) throw new RuntimeException('account_mode_refused');
    $binding['verified_account'] = 'acct_1TqwE9DDGtWR2WVN'; $binding['verified_test'] = TRUE; $binding['phase'] = 'create'; $save();
    $intent = $plugin->createPaymentIntent($order, ['metadata' => ['native_probe' => $binding['run_id']], 'automatic_payment_methods' => ['allow_redirects' => 'never']]);
    $binding['intent_id'] = $intent->id; $binding['phase'] = 'created'; $save();
  }
  elseif ($phase === 'confirm') {
    $binding['phase'] = 'confirm'; $save();
    $fixture = ['success' => 'pm_card_visa', 'recovery' => 'pm_card_visa', 'decline' => 'pm_card_visa_chargeDeclined', 'action-required' => 'pm_card_threeDSecure2Required'][$scenario] ?? NULL;
    if (!$fixture) throw new RuntimeException('scenario_refused');
    try {
      $intent = $client->paymentIntents->confirm($binding['intent_id'], ['payment_method' => $fixture]);
      if ($scenario === 'decline') throw new RuntimeException('expected_decline_missing');
    }
    catch (\Stripe\Exception\CardException $decline) {
      if ($scenario !== 'decline' || $decline->getStripeCode() !== 'card_declined' || $decline->getDeclineCode() !== 'generic_decline') throw new RuntimeException('unexpected_decline');
      $intent = $client->paymentIntents->retrieve($binding['intent_id']);
      $verifyIntent($intent);
      if ($intent->status !== 'requires_payment_method' || $intent->amount_received !== 0) throw new RuntimeException('decline_state_refused');
      $binding['decline_verified'] = TRUE;
    }
    $observeIntent($intent); $binding['phase'] = 'confirmed'; $save();
  }
  elseif ($phase === 'reconcile') {
    if ($scenario !== 'recovery' || ($binding['phase'] ?? '') !== 'confirm' || is_file($journal . '/reconciliation.json')) throw new RuntimeException('recovery_boundary_refused');
    $fault = json_decode(file_get_contents($journal . '/interruption.json'), TRUE, 512, JSON_THROW_ON_ERROR);
    $attempts = array_map(static fn($s) => json_decode($s, TRUE, 512, JSON_THROW_ON_ERROR), file($journal . '/attempts.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
    $responses = array_map(static fn($s) => json_decode($s, TRUE, 512, JSON_THROW_ON_ERROR), file($journal . '/requests.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
    $missing = array_values(array_filter($attempts, static fn($a) => !in_array($a['seq'], array_column($responses, 'seq'), TRUE)));
    if (count($missing) !== 1 || $fault['seq'] !== $missing[0]['seq'] || $fault['run_id'] !== $binding['run_id']
      || $fault['intent_id'] !== $binding['intent_id'] || $fault['account_id'] !== 'acct_1TqwE9DDGtWR2WVN'
      || $fault['fault'] !== 'exit_before_response_observation' || $fault['exit_code'] !== 86
      || $fault['method'] !== 'post' || $fault['path'] !== '/v1/payment_intents/' . $binding['intent_id'] . '/confirm'
      || $fault['params_sha256'] !== hash('sha256', json_encode(['payment_method' => 'pm_card_visa']))
      || $fault['idempotency_sha256'] !== hash('sha256', $binding['run_id'] . '-confirm')
      || array_intersect_key($fault, $missing[0]) !== $missing[0]) throw new RuntimeException('recovery_journal_refused');
    if ($client->accounts->retrieve()->id !== 'acct_1TqwE9DDGtWR2WVN' || $client->balance->retrieve()->livemode !== FALSE) throw new RuntimeException('account_mode_refused');
    $intent = $client->paymentIntents->retrieve($binding['intent_id']);
    $readSeq = (int) file_get_contents($journal . '/request-count');
    $verifyIntent($intent);
    if ($intent->status !== 'succeeded' || $intent->amount_received !== 19900 || !preg_match('/^ch_[A-Za-z0-9]+$/', $intent->latest_charge ?? '')) throw new RuntimeException('recovery_state_refused');
    $charge = $intent->latest_charge;
    $binding['recovery_validated'] = TRUE; $binding['phase'] = 'confirm_replay'; $save();
    // Reconcile only the original operation, identical parameters and key.
    $replayed = $client->paymentIntents->confirm($binding['intent_id'], ['payment_method' => 'pm_card_visa']);
    $replaySeq = (int) file_get_contents($journal . '/request-count');
    $verifyIntent($replayed);
    if ($replayed->status !== 'succeeded' || $replayed->amount_received !== 19900 || $replayed->latest_charge !== $charge
      || NativeProbeGuard::header($replayed->getLastResponse()->headers, 'idempotent-replayed') !== 'true') throw new RuntimeException('idempotent_recovery_refused');
    NativeProbeGuard::durable($journal . '/reconciliation.json', json_encode([
      'schema' => 'famtastic.native-confirm-reconciliation.v1', 'run_id' => $binding['run_id'], 'intent_id' => $binding['intent_id'],
      'account_id' => 'acct_1TqwE9DDGtWR2WVN', 'livemode' => FALSE, 'provider_status' => 'succeeded', 'amount_received' => 19900,
      'currency' => 'usd', 'charge_id' => $charge, 'covered_seq' => $fault['seq'], 'read_seq' => $readSeq, 'replay_seq' => $replaySeq]));
    $observeIntent($replayed); $binding['confirmation_reconciled'] = TRUE; $binding['phase'] = 'reconciled'; $save();
  }
  elseif ($phase === 'recover_callback' || $phase === 'recover_replay') {
    if ($scenario !== 'recovery' || ($binding['confirmation_reconciled'] ?? FALSE) !== TRUE) throw new RuntimeException('recovery_boundary_refused');
    $existing = \Drupal::entityTypeManager()->getStorage('commerce_payment')->loadByProperties(['order_id' => $order->id()]);
    if ($phase === 'recover_callback' && ($existing || $order->getState()->getId() !== 'draft' || $order->getData('stripe_intent') !== $binding['intent_id'])) throw new RuntimeException('recovery_order_refused');
    if ($phase === 'recover_replay') {
      $previous = $existing ? reset($existing) : NULL;
      if (count($existing) !== 1 || $previous->getRemoteId() !== $binding['intent_id'] || $previous->getPaymentGatewayId() !== 'native_probe'
        || $previous->getState()->getId() !== 'completed' || !$order->getBalance()->isZero() || $order->getState()->getId() !== 'completed') throw new RuntimeException('recovery_order_refused');
    }
    $lost = json_decode(file_get_contents($journal . '/lost-callback.json'), TRUE, 512, JSON_THROW_ON_ERROR);
    if ($lost['run_id'] !== $binding['run_id'] || $lost['intent_id'] !== $binding['intent_id'] || $lost['signature_verified'] !== TRUE
      || $lost['body_retained'] !== FALSE || !preg_match('/^evt_[A-Za-z0-9]+$/', $lost['event_id'] ?? '')) throw new RuntimeException('lost_callback_refused');
    $binding['event_id'] = $lost['event_id']; $save();
    // Authenticated provider API recovery, NOT a forged/redelivered signed webhook.
    $event = $client->events->retrieve($binding['event_id']);
    if ($event->livemode !== FALSE || $event->type !== 'payment_intent.succeeded' || !empty($event->account)) throw new RuntimeException('event_account_refused');
    $verifyIntent($event->data->object);
    if ($event->data->object->status !== 'succeeded' || $event->data->object->amount_received !== 19900) throw new RuntimeException('recovery_state_refused');
    $binding['method_id'] = $event->data->object->payment_method; $save();
    $response = $plugin->processWebHook(NULL, $event);
    if ($response && $response->getStatusCode() >= 400) throw new RuntimeException('native_callback_failed');
    $binding['recovery_event_verified'] = TRUE; $binding['event_type'] = $event->type; $save();
  }
  elseif ($phase === 'observe' || $phase === 'cancel') {
    if ($scenario === 'success') throw new RuntimeException('scenario_refused');
    $intent = $client->paymentIntents->retrieve($binding['intent_id']);
    $verifyIntent($intent);
    if ($phase === 'cancel') {
      if ($intent->amount_received !== 0 || !in_array($intent->status, ['requires_payment_method', 'requires_action'], TRUE)) throw new RuntimeException('cancel_state_refused');
      $binding['phase'] = 'cancel'; $save();
      $intent = $client->paymentIntents->cancel($binding['intent_id'], ['cancellation_reason' => 'abandoned']);
      $verifyIntent($intent);
      if ($intent->status !== 'canceled' || $intent->amount_received !== 0) throw new RuntimeException('cancel_state_refused');
    }
    $observeIntent($intent); $save();
  }
  elseif ($phase === 'callback' || $phase === 'replay') {
    $packet = $secrets['packet'];
    $event = \Stripe\Webhook::constructEvent($packet['body'], $packet['signature'], $secrets['webhook']);
    $object = $event->data->object;
    if ($event->livemode !== FALSE || $event->type !== $eventType || $object->livemode !== FALSE
      || $object->id !== $binding['intent_id'] || (string) $object->metadata->order_id !== $binding['order_id']
      || (string) $object->metadata->store_id !== $binding['store_id'] || $object->metadata->native_probe !== $binding['run_id']
      || $object->amount !== 19900 || $object->currency !== 'usd' || !empty($event->account)) throw new RuntimeException('callback_binding_refused');
    $binding['event_id'] = $event->id; $binding['method_id'] = $object->payment_method; $save();
    $providerEvent = $client->events->retrieve($event->id);
    if ($providerEvent->livemode !== FALSE || $providerEvent->type !== $eventType || $providerEvent->data->object->id !== $binding['intent_id']) throw new RuntimeException('event_account_refused');
    $request = Request::create('http://native-probe.example.test/payment/notify/native_probe', 'POST', [], [], [], ['HTTP_STRIPE_SIGNATURE' => $packet['signature']], $packet['body']);
    $response = $plugin->onNotify($request);
    if ($response && $response->getStatusCode() >= 400) throw new RuntimeException('native_callback_failed');
    $binding['callback_verified'] = TRUE; $binding['event_type'] = $eventType; $save();
  }
  elseif ($phase === 'refund') {
    $payment = \Drupal::entityTypeManager()->getStorage('commerce_payment')->loadByRemoteId($binding['intent_id']);
    if (!$payment || $payment->getState()->getId() !== 'completed') throw new RuntimeException('refund_without_native_payment');
    $binding['phase'] = 'refund'; $save();
    $plugin->refundPayment($payment);
    $binding['phase'] = 'refunded'; $save();
  }
  elseif ($phase !== 'inspect') throw new RuntimeException('phase_refused');
  \Drupal::entityTypeManager()->getStorage('commerce_order')->resetCache();
  $order = Order::load($binding['order_id']);
  $payments = \Drupal::entityTypeManager()->getStorage('commerce_payment')->loadByProperties(['order_id' => $order->id()]);
  $payment = $payments ? reset($payments) : NULL;
  $savedGateway = \Drupal::service('config.storage')->read('commerce_payment.commerce_payment_gateway.native_probe');
  print json_encode(['phase' => $phase, 'run_id' => $binding['run_id'], 'order_id' => $order->id(), 'order_state' => $order->getState()->getId(),
    'balance' => $order->getBalance()->getNumber(), 'payment_count' => count($payments), 'payment_id' => $payment?->id(),
    'balance_zero' => $order->getBalance()->isZero(),
    'payment_state' => $payment?->getState()->getId(), 'refunded' => $payment?->getRefundedAmount()->getNumber(),
    'refunded_full' => $payment ? $payment->getRefundedAmount()->equals(new \Drupal\commerce_price\Price('199.00', 'USD')) : FALSE,
    'intent_id' => $binding['intent_id'] ?? NULL, 'event_id' => $binding['event_id'] ?? NULL,
    'callback_verified' => $binding['callback_verified'] ?? FALSE,
    'event_type' => $binding['event_type'] ?? NULL, 'scenario' => $scenario,
    'provider_status' => $binding['provider_status'] ?? NULL, 'amount_received' => $binding['amount_received'] ?? NULL,
    'decline_verified' => $binding['decline_verified'] ?? FALSE, 'action_required' => $binding['action_required'] ?? FALSE,
    'confirmation_reconciled' => $binding['confirmation_reconciled'] ?? FALSE, 'recovery_event_verified' => $binding['recovery_event_verified'] ?? FALSE,
    'captured_mail_count' => count(\Drupal::state()->get('system.test_mail_collector', [])),
    'credentials_persisted' => !empty($savedGateway['configuration']['secret_key']) || !empty($savedGateway['configuration']['webhook_signing_secret']),
    'ephemeral_gateway_reload_verified' => TRUE,
    'agency_module_installed' => FALSE, 'browser_checkout_proven' => FALSE]) . "\n";
}
catch (\Throwable $error) {
  // No raw exception trace/message from SDK/Drupal; they may contain secrets.
  $allowed = ['runtime_boundary_refused','nonempty_runtime','test_secrets_refused','synthetic_order_refused','existing_intent_refused','account_mode_refused',
    'callback_binding_refused','event_account_refused','native_callback_failed','refund_without_native_payment','phase_refused','provider_request_refused',
    'expired_or_non_test_binding','credential_binding_refused','connected_account_refused','provider_request_limit','provider_mode_refused'];
  $reason = in_array($error->getMessage(), $allowed, TRUE) || preg_match('/^provider_http_\d{3}$/', $error->getMessage()) ? $error->getMessage() : 'native_probe_failed';
  print json_encode(['phase' => $phase, 'status' => 'refused', 'reason' => $reason, 'error_class' => get_class($error),
    'error_source' => basename($error->getFile()) . ':' . $error->getLine(),
    'offline_seed_diagnostic' => $phase === 'seed' ? $error->getMessage() : NULL]) . "\n";
  exit(2);
}
