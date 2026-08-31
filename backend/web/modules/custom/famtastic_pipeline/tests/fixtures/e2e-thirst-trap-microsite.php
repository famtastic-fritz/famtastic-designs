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

$owner = $service->ownerSnapshot($siteKey, 1, FALSE);
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
  'cross_owner_denied' => $denied,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
