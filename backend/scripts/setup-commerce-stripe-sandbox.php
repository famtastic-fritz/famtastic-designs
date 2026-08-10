<?php

declare(strict_types=1);

use Drupal\commerce_payment\Entity\PaymentGateway;

$publishableKey = trim((string) getenv('FAMTASTIC_STRIPE_TEST_PUBLISHABLE_KEY'));
$secretKey = trim((string) getenv('FAMTASTIC_STRIPE_TEST_SECRET_KEY'));
$webhookSecret = trim((string) getenv('FAMTASTIC_STRIPE_TEST_WEBHOOK_SECRET'));
$enabled = filter_var(getenv('FAMTASTIC_STRIPE_GATEWAY_ENABLED') ?: '0', FILTER_VALIDATE_BOOL);

if (!str_starts_with($publishableKey, 'pk_test_')) {
  throw new RuntimeException('A Stripe test publishable key is required.');
}
if (!str_starts_with($secretKey, 'sk_test_') && !str_starts_with($secretKey, 'rk_test_')) {
  throw new RuntimeException('A Stripe test secret or restricted key is required.');
}
if ($webhookSecret !== '' && !str_starts_with($webhookSecret, 'whsec_')) {
  throw new RuntimeException('The webhook secret format is invalid.');
}

$storage = \Drupal::entityTypeManager()->getStorage('commerce_payment_gateway');
$gateway = $storage->load('famtastic_stripe_sandbox');
if (!$gateway) {
  $gateway = PaymentGateway::create([
    'id' => 'famtastic_stripe_sandbox',
    'label' => 'FAMtastic Stripe Sandbox',
    'plugin' => 'stripe_payment_element',
    'weight' => -10,
    'status' => $enabled,
  ]);
}

$plugin = $gateway->getPlugin();
$configuration = $plugin->getConfiguration();
$configuration = array_replace_recursive($configuration, [
  'display_label' => 'Credit or debit card',
  'mode' => 'test',
  'payment_method_types' => ['credit_card'],
  'collect_billing_information' => TRUE,
  'api_version' => '2024-06-20',
  'authentication_method' => 'api_key',
  'access_token' => '',
  'stripe_user_id' => '',
  'publishable_key' => $publishableKey,
  'secret_key' => $secretKey,
  'webhook_signing_secret' => $webhookSecret,
  'payment_method_usage' => 'on_session',
  'capture_method' => 'automatic',
  'express_checkout' => [
    'enable_on_cart' => FALSE,
    'allowed_payment_method_types' => [],
  ],
  'style' => ['theme' => 'night', 'layout' => 'tabs'],
]);
$gateway->setPluginConfiguration($configuration);
$gateway->setStatus($enabled);
$gateway->save();

// Prove the configured credentials resolve only to a Stripe test environment.
$storage->resetCache([$gateway->id()]);
$storage->load($gateway->id())->getPlugin();
$balance = \Stripe\Balance::retrieve();
if ($balance->livemode !== FALSE) {
  $gateway->setStatus(FALSE)->save();
  throw new RuntimeException('Stripe returned live mode; the gateway was disabled.');
}

echo sprintf(
  "Commerce Stripe sandbox gateway synchronized (%s).\n",
  $enabled ? 'enabled for test checkout' : 'disabled pending test checkout',
);
