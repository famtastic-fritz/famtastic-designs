<?php
declare(strict_types=1);

// Run through Drush against the deployed release. Read-only unless explicitly
// confirmed; uses only the existing queue and exact-key dispatcher.
$db = \Drupal::database();
$request = $db->select('famtastic_project_request', 'r')->fields('r', ['id', 'customer_id', 'business_name', 'selected_proof_direction'])->condition('id', 9)->execute()->fetchAssoc();
$customer = $db->select('famtastic_customer', 'c')->fields('c', ['id', 'email', 'verified_at'])->condition('id', 9)->execute()->fetchAssoc();
if (!$request || !$customer || (int) $request['customer_id'] !== 9 || strtolower($request['selected_proof_direction']) !== 'c' || $request['business_name'] !== 'Pros In Training aka The P.I.T' || !$customer['verified_at'] || strtolower($customer['email']) !== 'valerie@prosintraining.org') {
  throw new RuntimeException('Approved request/recipient binding changed; stop.');
}
$fixture = require dirname(__DIR__, 2) . '/scripts/email-preview/valerie.php';
$key = 'website-request:9:staging-review:1a0cec52ac1091cd8b48cdb92a30311c5b3c058a5d873504eb8543c87c536bd6:v1';
$query = fn() => $db->select('famtastic_notification_outbox', 'o')->fields('o')->condition('notification_key', $key)->execute()->fetchAssoc();
$existing = $query();
if ($existing && ($existing['subject'] !== $fixture['subject'] || $existing['body'] !== $fixture['body'] || $existing['recipient'] !== strtolower($customer['email']) || $existing['template_id'] !== 'customer_staging_review_ready' || (int) $existing['template_version'] !== 1)) {
  throw new RuntimeException('Existing immutable notification differs; stop.');
}
if ($existing && $existing['status'] === 'sent') {
  echo json_encode(['status' => 'already_sent', 'id' => $existing['id'], 'provider_message_id' => $existing['provider_message_id'], 'sent_at' => $existing['sent_at']]) . "\n";
  return;
}
echo json_encode(['recipient' => $customer['email'], 'request_id' => 9, 'subject' => $fixture['subject'], 'template' => 'customer_staging_review_ready/v1', 'existing_status' => $existing['status'] ?? 'absent']) . "\n";
if (getenv('FAMTASTIC_APPROVED_STAGING_SEND') !== 'request-9-valerie-2026-09-17') { echo "Read-only preflight; no queue or send.\n"; return; }
if ($existing && $existing['status'] !== 'queued') { throw new RuntimeException('Existing attempted notification requires manual reconciliation; no retry.'); }
if (\Drupal\Core\Site\Settings::get('famtastic_protected_staging', FALSE) || (getenv('FAMTASTIC_TRANSACTIONAL_EMAIL_TRANSPORT') ?: \Drupal\Core\Site\Settings::get('famtastic_transactional_email_transport', 'smtp')) !== 'smtp') {
  throw new RuntimeException('Not the production SMTP environment.');
}
$client = \Drupal::httpClient();
$site = $client->get('https://prosintraining.famtasticinc.com/', ['timeout' => 20, 'allow_redirects' => FALSE]);
if ($site->getStatusCode() !== 200 || hash('sha256', (string) $site->getBody()) !== '1a0cec52ac1091cd8b48cdb92a30311c5b3c058a5d873504eb8543c87c536bd6' || !str_contains($site->getHeaderLine('X-Robots-Tag'), 'noindex')) { throw new RuntimeException('Approved site artifact not live.'); }
$logo = $client->get('https://famtasticdesigns.com/brand/famtastic-designs-logo-v1.png', ['timeout' => 20, 'allow_redirects' => FALSE]);
if ($logo->getStatusCode() !== 200 || hash('sha256', (string) $logo->getBody()) !== 'ebb0477344132d32e449ba19e2b622921585aa71af0decdbcf8abfbe033fa950') { throw new RuntimeException('Approved logo not live.'); }
\Drupal::service('famtastic_pipeline.customer_portal')->queueNotification($key, 'project_staging_review_ready', $customer['email'], $fixture['subject'], $fixture['body'], 'customer_staging_review_ready', 1);
$result = \Drupal::service('famtastic_pipeline.lifecycle_operations')->dispatchNotifications(1, [$key]);
$receipt = $query();
echo json_encode(['dispatch' => $result, 'id' => $receipt['id'], 'status' => $receipt['status'], 'provider_message_id' => $receipt['provider_message_id'], 'sent_at' => $receipt['sent_at']]) . "\n";
if ($receipt['status'] !== 'sent' || empty($receipt['provider_message_id'])) { throw new RuntimeException('SMTP acceptance not established; inspect receipt before any retry.'); }
