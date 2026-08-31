<?php

declare(strict_types=1);

/**
 * Local-only state exercise for the Thirst Trap production microsite.
 */

$statePath = (string) getenv('FAMTASTIC_E2E_STATE');
if ($statePath === '') {
  throw new \RuntimeException('FAMTASTIC_E2E_STATE is required.');
}

$service = \Drupal::service('famtastic_pipeline.microsites');
$siteKey = 'thirst-trap-772';
$run = preg_replace('/[^a-z0-9-]+/i', '-', (string) getenv('FAMTASTIC_E2E_RUN')) ?: 'fixture';
$email = "owner-{$run}@example.test";

$initial = $service->publicSnapshot($siteKey);
if (($initial['site']['brand']['name'] ?? '') !== 'Thirst Trap 772') {
  throw new \RuntimeException('Seeded storefront content is unavailable.');
}

$disabledPreorderDenied = FALSE;
try {
  $service->preorder($siteKey, [
    'name' => 'Disabled Fixture',
    'email' => $email,
    'items' => [['product_id' => 'fruit-punch', 'quantity' => 1]],
  ], 'local-e2e');
}
catch (\RuntimeException $error) {
  $disabledPreorderDenied = $error->getMessage() === 'preorders_unavailable';
}
if (!$disabledPreorderDenied) {
  throw new \RuntimeException('Preorders must fail closed until the owner enables them.');
}

$service->assignOwner($siteKey, 1);
$updated = $service->updateContent($siteKey, 1, FALSE, [
  'brand' => [
    'name' => 'Thirst Trap 772',
    'tagline' => 'Crave. Drink. Repeat.',
    'service_area' => 'Vero Beach and the Treasure Coast',
    'intro' => 'Fixture intro for the production storefront contract.',
  ],
  'products' => [
    [
      'id' => 'fixture-pouch',
      'name' => 'Fixture Pouch',
      'kicker' => 'Cold + bright',
      'description' => 'Synthetic fixture product.',
      'price_label' => '$5 fixture',
      'price_cents' => 500,
      'status' => 'active',
      'visual' => 'lime',
    ],
    [
      'id' => 'hidden-fixture',
      'name' => 'Hidden Fixture',
      'status' => 'hidden',
      'visual' => 'pink',
    ],
  ],
  'events' => [
    [
      'id' => 'fixture-pop-up',
      'title' => 'Fixture Pop-Up',
      'date_label' => 'Fixture date',
      'location' => 'Fixture location',
      'details' => 'Synthetic fixture event.',
      'status' => 'scheduled',
    ],
  ],
  'payments' => [
    'preorders_enabled' => TRUE,
    'cash_app_url' => 'https://cash.app/$FixtureOnly',
    'cash_app_label' => '$FixtureOnly',
    'payment_note' => 'Include the fixture order reference.',
    'pickup_note' => 'Fixture pickup is confirmed manually.',
  ],
  'socials' => [
    'instagram' => 'https://www.instagram.com/thirst_trap772/',
    'facebook' => 'https://www.facebook.com/ThirstTrap772/',
  ],
]);

$contact = $service->capture($siteKey, 'contact', [
  'name' => 'Microsite Fixture',
  'email' => $email,
  'phone' => '555-0100',
  'subject' => 'Event booking',
  'message' => 'Synthetic fixture contact only.',
], 'local-e2e');
$subscriber = $service->capture($siteKey, 'subscriber', [
  'email' => $email,
  'consent' => TRUE,
], 'local-e2e');
$duplicate = $service->capture($siteKey, 'subscriber', [
  'email' => $email,
  'consent' => TRUE,
], 'local-e2e');

$preorder = $service->preorder($siteKey, [
  'name' => 'Preorder Fixture',
  'email' => $email,
  'phone' => '555-0100',
  'pickup_event_id' => 'fixture-pop-up',
  'notes' => 'Synthetic preorder only.',
  'items' => [
    ['product_id' => 'fixture-pouch', 'quantity' => 2],
  ],
], 'local-e2e');

$owner = $service->ownerSnapshot($siteKey, 1, FALSE);
$order = $owner['orders'][0] ?? NULL;
if (!$order) {
  throw new \RuntimeException('Captured preorder was not returned to the owner.');
}
$service->updateOrderStatus($siteKey, 1, FALSE, (int) $order['id'], 'confirmed', 'confirmed');
$ownerAfterOrder = $service->ownerSnapshot($siteKey, 1, FALSE);
$contactMessage = array_values(array_filter(
  $owner['messages'],
  static fn(array $message): bool => ($message['kind'] ?? '') === 'contact',
))[0] ?? NULL;
if (!$contactMessage) {
  throw new \RuntimeException('Captured contact was not returned to the owner.');
}
$service->updateMessageStatus($siteKey, 1, FALSE, (int) $contactMessage['id'], 'resolved');
$service->updateMessageStatus($siteKey, 1, FALSE, (int) $contactMessage['id'], 'resolved');

$denied = FALSE;
try {
  $service->ownerSnapshot($siteKey, 2, FALSE);
}
catch (\RuntimeException $error) {
  $denied = $error->getMessage() === 'microsite_access_denied';
}

$public = $service->publicSnapshot($siteKey);
file_put_contents($statePath, json_encode([
  'site_key' => $siteKey,
  'owner_uid' => $owner['owner_uid'],
  'public_product_count' => count($public['site']['products'] ?? []),
  'public_event_count' => count($public['site']['events'] ?? []),
  'product_name' => $public['site']['products'][0]['name'] ?? '',
  'updated_product_name' => $updated['site']['products'][0]['name'] ?? '',
  'contact_status' => $contact['status'] ?? '',
  'subscriber_status' => $subscriber['status'] ?? '',
  'duplicate_subscriber' => $duplicate['duplicate'] ?? FALSE,
  'message_count' => count($owner['messages']),
  'order_count' => count($owner['orders']),
  'order_reference_prefix' => substr((string) ($preorder['order']['reference'] ?? ''), 0, 6),
  'order_total_cents' => $preorder['order']['total_cents'] ?? NULL,
  'cash_app_available' => $preorder['payment']['available'] ?? FALSE,
  'cash_app_url' => $preorder['payment']['url'] ?? '',
  'order_status' => $ownerAfterOrder['orders'][0]['order_status'] ?? '',
  'payment_status' => $ownerAfterOrder['orders'][0]['payment_status'] ?? '',
  'cross_owner_denied' => $denied,
  'disabled_preorder_denied' => $disabledPreorderDenied,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
