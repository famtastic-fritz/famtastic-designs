<?php

declare(strict_types=1);

/** Local-only owner/public handoff fixture. It creates no provider account. */

$statePath = (string) getenv('FAMTASTIC_E2E_STATE');
if ($statePath === '') {
  throw new \RuntimeException('FAMTASTIC_E2E_STATE is required.');
}

$run = preg_replace('/[^a-z0-9]+/i', '-', (string) getenv('FAMTASTIC_E2E_RUN')) ?: 'fixture';
$database = \Drupal::database();
$time = \Drupal::time()->getRequestTime();
$uuid = \Drupal::service('uuid');
$users = \Drupal::entityTypeManager()->getStorage('user');
$handoffs = \Drupal::service('famtastic_pipeline.payment_handoffs');
$bookingSiteOwners = \Drupal::service('famtastic_pipeline.booking_site_owners');

$makeCustomer = static function (string $role) use ($users, $database, $time, $run): array {
  $email = "{$role}-payment-{$run}@example.test";
  $password = "fixture-{$role}-payment-pass-2026";
  /** @var \Drupal\user\Entity\User $user */
  $user = $users->create([
    'name' => $email,
    'mail' => $email,
    'pass' => $password,
    'status' => TRUE,
  ]);
  $user->save();
  // The module's user-insert hook creates the durable customer and its first
  // owner organization. Mark this synthetic local account verified without a
  // mail send so the session-backed owner endpoint can exercise its real gate.
  $customer = $database->select('famtastic_customer', 'c')->fields('c', ['id'])
    ->condition('uid', (int) $user->id())->range(0, 1)->execute()->fetchAssoc();
  if (!$customer) {
    throw new \RuntimeException('Fixture customer auto-create failed.');
  }
  $customerId = (int) $customer['id'];
  $database->update('famtastic_customer')->fields(['verified_at' => $time, 'changed' => $time])
    ->condition('id', $customerId)->execute();
  return ['id' => $customerId, 'email' => $email, 'password' => $password];
};

$owner = $makeCustomer('owner');
$member = $makeCustomer('member');
$organization = $database->select('famtastic_membership', 'm')
  ->fields('m', ['organization_id'])
  ->condition('customer_id', $owner['id'])->condition('role', 'owner')->condition('status', 'active')
  ->range(0, 1)->execute()->fetchAssoc();
if (!$organization) {
  throw new \RuntimeException('Fixture owner organization is unavailable.');
}
$organizationId = (int) $organization['organization_id'];
$organizationPublicId = (string) $database->select('famtastic_organization', 'o')->fields('o', ['public_id'])
  ->condition('id', $organizationId)->range(0, 1)->execute()->fetchField();
$otherOrganizationQuery = $database->select('famtastic_membership', 'm');
$otherOrganizationQuery->join('famtastic_organization', 'o', 'o.id = m.organization_id');
$otherOrganizationPublicId = (string) $otherOrganizationQuery->fields('o', ['public_id'])
  ->condition('m.customer_id', $member['id'])->condition('m.role', 'owner')->condition('m.status', 'active')
  ->range(0, 1)->execute()->fetchField();
$database->update('famtastic_organization')->fields(['name' => 'Payment Handoff Fixture', 'changed' => $time])
  ->condition('id', $organizationId)->execute();
$database->insert('famtastic_membership')->fields([
  'organization_id' => $organizationId,
  'customer_id' => $member['id'],
  'role' => 'member',
  'status' => 'active',
  'created' => $time,
  'changed' => $time,
])->execute();

// Exercise the existing converted-request → booking-site → organization
// binding, rather than inventing a handoff-specific site key or mapping.
$requestPublicId = $uuid->generate();
$websiteRequestId = (int) $database->insert('famtastic_project_request')->fields([
  'public_id' => $requestPublicId,
  'organization_id' => $organizationId,
  'customer_id' => $owner['id'],
  'project_id' => 1,
  'status' => 'converted',
  'project_name' => 'Payment handoff fixture project',
  'business_name' => 'Payment Handoff Fixture',
  'project_type' => 'new_website',
  'domain_choice' => 'existing_domain',
  'existing_domain' => 'fixture.example.test',
  'recommendation_requested' => 0,
  'intake_data' => '{}',
  'submitted_at' => $time,
  'created' => $time,
  'changed' => $time,
])->execute();
$siteKey = 'site-' . substr(str_replace('-', '', $requestPublicId), 0, 16);
$bookingSiteOwners->bindToConvertedRequest($siteKey, $websiteRequestId, 1);

$initial = $handoffs->ownerSnapshot($owner['id'], $organizationPublicId);
$memberDenied = FALSE;
try {
  $handoffs->ownerSnapshot($member['id'], $organizationPublicId);
}
catch (\RuntimeException $error) {
  $memberDenied = $error->getMessage() === 'payment_handoff_owner_access_denied';
}
$credentialUrlDenied = FALSE;
try {
  $handoffs->save($owner['id'], $organizationPublicId, [
    'mode' => 'payment_link',
    'destination_url' => 'https://owner:secret@payments.example.test/checkout',
  ]);
}
catch (\InvalidArgumentException $error) {
  $credentialUrlDenied = $error->getMessage() === 'payment_handoff_destination_invalid';
}
$disabledPublicAbsent = FALSE;
try {
  $handoffs->publicSnapshot($organizationPublicId, $siteKey);
}
catch (\RuntimeException $error) {
  $disabledPublicAbsent = $error->getMessage() === 'payment_handoff_unavailable';
}

file_put_contents($statePath, json_encode([
  'organization_public_id' => $organizationPublicId,
  'other_organization_public_id' => $otherOrganizationPublicId,
  'site_key' => $siteKey,
  'owner_email' => $owner['email'],
  'owner_password' => $owner['password'],
  'member_email' => $member['email'],
  'member_password' => $member['password'],
  'initial_mode' => $initial['payment_handoff']['mode'] ?? NULL,
  'initial_configured' => $initial['configured'] ?? NULL,
  'member_denied' => $memberDenied,
  'credential_url_denied' => $credentialUrlDenied,
  'disabled_public_absent' => $disabledPublicAbsent,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
