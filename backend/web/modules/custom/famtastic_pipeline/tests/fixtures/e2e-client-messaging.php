<?php

declare(strict_types=1);

/** Synthetic-only inbox state; used exclusively by the disposable harness. */
if (\Drupal::database()->driver() !== 'sqlite' || getenv('FAMTASTIC_TRANSACTIONAL_EMAIL_TRANSPORT') !== 'memory') {
  throw new \RuntimeException('This fixture requires isolated SQLite and memory mail.');
}
$db = \Drupal::database();
$now = \Drupal::time()->getRequestTime();
$users = \Drupal::entityTypeManager()->getStorage('user');
$role = \Drupal\user\Entity\Role::create(['id' => 'inbox_staff', 'label' => 'Inbox fixture staff']);
$role->grantPermission('administer famtastic pipeline')->grantPermission('access administration pages')->save();
$accounts = [];
foreach (['staff', 'customer', 'foreign', 'unverified'] as $kind) {
  $email = $kind . '-inbox@example.test';
  $user = $users->create(['name' => $kind === 'staff' ? 'inbox-owner' : $email, 'mail' => $email, 'pass' => 'Fixture-inbox-pass-2026!', 'status' => TRUE]);
  if ($kind === 'staff') $user->addRole('inbox_staff');
  $user->save();
  $customer = $db->select('famtastic_customer', 'c')->fields('c')->condition('uid', (int) $user->id())->execute()->fetchAssoc();
  if (!$customer) throw new \RuntimeException('Fixture customer auto-create failed.');
  if ($kind === 'staff') {
    $db->delete('famtastic_membership')->condition('customer_id', (int) $customer['id'])->execute();
    $db->delete('famtastic_customer')->condition('id', (int) $customer['id'])->execute();
  }
  elseif ($kind !== 'unverified') $db->update('famtastic_customer')->fields(['verified_at' => $now])->condition('id', (int) $customer['id'])->execute();
  $accounts[$kind] = ['uid' => (int) $user->id(), 'email' => $email, 'customer_id' => $kind === 'staff' ? NULL : (int) $customer['id']];
}
$prospect = \Drupal\famtastic_pipeline\Entity\Prospect::create(['business_name' => 'Inbox Proof Fixture', 'public_email' => $accounts['customer']['email'], 'contact_method' => 'email', 'contact_value' => $accounts['customer']['email'], 'status' => 'lead', 'campaign' => 'public_contact']);
$prospect->save();
$intake = \Drupal\famtastic_pipeline\Entity\Intake::create([
  'prospect_ref' => $prospect->id(), 'primary_goal' => 'Public contact request', 'submitted_at' => $now,
  'about' => "CONTACT request\n" . json_encode(['name' => 'Inbox Fixture Client', 'email' => $accounts['customer']['email'], 'message' => 'Please send the website proofs. This is the original contact form message.']),
]);
$intake->save();
$organization = (int) $db->select('famtastic_membership', 'm')->fields('m', ['organization_id'])->condition('customer_id', $accounts['customer']['customer_id'])->execute()->fetchField();
$requestId = (int) $db->insert('famtastic_project_request')->fields([
  'public_id' => \Drupal::service('uuid')->generate(), 'organization_id' => $organization, 'customer_id' => $accounts['customer']['customer_id'], 'prospect_id' => (int) $prospect->id(),
  'intake_id' => (int) $intake->id(), 'project_name' => 'Inbox Fixture Website', 'business_name' => 'Inbox Proof Fixture', 'status' => 'submitted', 'intake_data' => '{}', 'created' => $now, 'changed' => $now,
])->execute();
$archivedRequestId = (int) $db->insert('famtastic_project_request')->fields([
  'public_id' => \Drupal::service('uuid')->generate(), 'organization_id' => $organization, 'customer_id' => $accounts['customer']['customer_id'], 'prospect_id' => (int) $prospect->id(),
  'project_name' => 'Customer Archived Duplicate Fixture', 'business_name' => 'Inbox Proof Fixture', 'status' => 'submitted',
  'customer_archived_at' => $now, 'intake_data' => '{}', 'created' => $now, 'changed' => $now + 1,
])->execute();
\Drupal::configFactory()->getEditable('system.mail')->set('interface.default', 'test_mail_collector')->save();
\Drupal::configFactory()->getEditable('famtastic_pipeline.settings')->set('frontend_base_url', 'https://famtasticdesigns.com')->set('notification_to_email', 'staff-inbox@example.test')->save();
$state = ['accounts' => $accounts, 'intake_id' => (int) $intake->id(), 'request_id' => $requestId, 'archived_request_id' => $archivedRequestId, 'initial_outbox_count' => (int) $db->select('famtastic_notification_outbox', 'n')->countQuery()->execute()->fetchField()];
file_put_contents((string) getenv('FAMTASTIC_INBOX_STATE'), json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
