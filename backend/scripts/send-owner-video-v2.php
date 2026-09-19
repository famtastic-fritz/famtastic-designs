<?php

declare(strict_types=1);

/**
 * Sends the one owner-authorized version-two film notice through the exact-key
 * outbox. Default mode is read-only; pass only --send after the live page and
 * film have been independently verified. Never run a broad notification worker.
 *
 * Drush: drush php:script /private/path/send-owner-video-v2.php [-- --send]
 */

use Drupal\famtastic_pipeline\Service\OutreachMailer;

const VIDEO_V2_NOTICE_KEY = 'video-v2-20260919-motion-complete';
const VIDEO_V2_RECIPIENT = 'fritz.medine@gmail.com';
const VIDEO_V2_SUBJECT = 'Version two: faster narration and corrected FAMtastic pronunciation';
const VIDEO_V2_BODY = "Hi Fritz,\n\nVersion two of the What’s the Catch film is ready for you to watch. It is 72.8 seconds instead of 103.9 seconds, retains your complete script, and has newly generated narration with fam-TAS-tik supplied directly to the speech engine. All captions were retimed.\n\nWatch version two:\nhttps://famtasticdesigns.com/media/films/whats-the-catch-v2-20260919.mp4\n\nThis is the faster synthetic narrator, not a clone of your voice. I’m continuing with the presenter-led walking edit and local voice-cloning proof, and will email their results separately.\n\nAlways FAMtastic,\nShay";
const VIDEO_V2_TEMPLATE = 'standard';
const VIDEO_V2_TEMPLATE_VERSION = 2;
const VIDEO_V2_CATEGORY = 'operational';
const VIDEO_V2_MAX_ATTEMPTS = 1;

$scriptArgs = array_values(array_filter(
  array_map('strval', $extra ?? []),
  static fn (string $argument): bool => $argument !== '--',
));
if ($scriptArgs !== [] && $scriptArgs !== ['--send']) {
  throw new InvalidArgumentException('Only the optional --send flag is accepted.');
}
$send = $scriptArgs === ['--send'];

$recipient = VIDEO_V2_RECIPIENT;
$subject = VIDEO_V2_SUBJECT;
$body = VIDEO_V2_BODY;
$template = VIDEO_V2_TEMPLATE;
$templateVersion = VIDEO_V2_TEMPLATE_VERSION;
$notificationKey = VIDEO_V2_NOTICE_KEY;

$bindingHash = static function (
  string $key,
  string $category,
  string $recipient,
  string $subject,
  string $body,
  string $template,
  int $templateVersion,
  int $maxAttempts,
): string {
  return hash('sha256', json_encode([
    'notification_key' => $key,
    'category' => $category,
    'recipient' => mb_strtolower(trim($recipient)),
    'subject' => $subject,
    'body' => $body,
    'template_id' => $template,
    'template_version' => $templateVersion,
    'max_attempts' => $maxAttempts,
  ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
};

$expectedHash = $bindingHash(
  $notificationKey,
  VIDEO_V2_CATEGORY,
  $recipient,
  $subject,
  $body,
  $template,
  $templateVersion,
  VIDEO_V2_MAX_ATTEMPTS,
);

$emit = static function (array $result): void {
  print json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
};

if (!OutreachMailer::supportsTemplate($template, $templateVersion)) {
  throw new RuntimeException('The approved standard/v2 email template is unavailable.');
}

$db = \Drupal::database();
$loadNotice = static fn (): array|false => $db->select('famtastic_notification_outbox', 'n')
  ->fields('n')
  ->condition('notification_key', $notificationKey)
  ->range(0, 1)
  ->execute()
  ->fetchAssoc();

$assertBound = static function (array $row) use ($bindingHash, $expectedHash, $notificationKey): void {
  $actualHash = $bindingHash(
    (string) ($row['notification_key'] ?? ''),
    (string) ($row['category'] ?? ''),
    (string) ($row['recipient'] ?? ''),
    (string) ($row['subject'] ?? ''),
    (string) ($row['body'] ?? ''),
    (string) ($row['template_id'] ?? ''),
    (int) ($row['template_version'] ?? 0),
    (int) ($row['max_attempts'] ?? 0),
  );
  if ((string) ($row['notification_key'] ?? '') !== $notificationKey || !hash_equals($expectedHash, $actualHash)) {
    throw new RuntimeException('The fixed notification key exists with different recipient or content; stop for manual review.');
  }
};

$existing = $loadNotice();
if ($existing) {
  $assertBound($existing);
  $status = (string) ($existing['status'] ?? '');
  if ($status === 'sent') {
    $messageId = trim((string) ($existing['provider_message_id'] ?? ''));
    if ((int) ($existing['attempts'] ?? 0) !== 1 || $messageId === '' || empty($existing['sent_at'])) {
      throw new RuntimeException('The row says sent but has no complete provider receipt; stop for manual review.');
    }
    $emit([
      'status' => 'already_sent',
      'notification_key' => $notificationKey,
      'recipient' => $recipient,
      'template' => $template . '/v' . $templateVersion,
      'binding_sha256' => $expectedHash,
      'outbox_id' => (int) $existing['id'],
      'provider_message_id' => $messageId,
      'sent_at' => (int) $existing['sent_at'],
      'resent' => false,
    ]);
    return;
  }
  if ($status !== 'queued'
    || (int) ($existing['attempts'] ?? -1) !== 0
    || (int) ($existing['max_attempts'] ?? 0) !== VIDEO_V2_MAX_ATTEMPTS
    || trim((string) ($existing['provider_message_id'] ?? '')) !== ''
    || trim((string) ($existing['claim_token'] ?? '')) !== ''
    || !empty($existing['claimed_at'])) {
    throw new RuntimeException('The fixed notification key has been attempted or is not pristine queued work; no retry is allowed.');
  }
}

if (!$send) {
  $emit([
    'status' => $existing ? 'dry_run_existing_queued' : 'dry_run_ready',
    'notification_key' => $notificationKey,
    'recipient' => $recipient,
    'subject' => $subject,
    'body' => $body,
    'template' => $template . '/v' . $templateVersion,
    'binding_sha256' => $expectedHash,
    'attempted' => false,
    'queued' => false,
    'sent' => false,
  ]);
  return;
}

if (\Drupal\Core\Site\Settings::get('famtastic_protected_staging', FALSE)) {
  throw new RuntimeException('Refusing production email from a protected staging runtime.');
}
$transport = (string) (getenv('FAMTASTIC_TRANSACTIONAL_EMAIL_TRANSPORT')
  ?: \Drupal\Core\Site\Settings::get('famtastic_transactional_email_transport', 'smtp'));
if ($transport !== 'smtp' || !\Drupal::config('smtp.settings')->get('smtp_on')) {
  throw new RuntimeException('Refusing to send unless the configured transport is enabled production SMTP.');
}

if (!$existing) {
  $now = \Drupal::time()->getRequestTime();
  try {
    // Use a unique insert (not the queue helper's merge/upsert) so a race can
    // never reset an already attempted row back to queued.
    $db->insert('famtastic_notification_outbox')->fields([
      'notification_key' => $notificationKey,
      'category' => VIDEO_V2_CATEGORY,
      'recipient' => $recipient,
      'subject' => $subject,
      'body' => $body,
      'template_id' => $template,
      'template_version' => $templateVersion,
      'status' => 'queued',
      'attempts' => 0,
      'max_attempts' => VIDEO_V2_MAX_ATTEMPTS,
      'available_at' => $now,
      'created' => $now,
      'changed' => $now,
    ])->execute();
  }
  catch (Throwable $error) {
    // A concurrent invocation may have inserted this unique key. Re-read and
    // accept only the exact pristine row; otherwise preserve the failure.
    $existing = $loadNotice();
    if (!$existing) {
      throw new RuntimeException('Could not create the unique outbox notice; no email was sent.', 0, $error);
    }
    $assertBound($existing);
    if ((string) ($existing['status'] ?? '') !== 'queued'
      || (int) ($existing['attempts'] ?? -1) !== 0
      || (int) ($existing['max_attempts'] ?? 0) !== VIDEO_V2_MAX_ATTEMPTS
      || trim((string) ($existing['provider_message_id'] ?? '')) !== ''
      || trim((string) ($existing['claim_token'] ?? '')) !== ''
      || !empty($existing['claimed_at'])) {
      throw new RuntimeException('Concurrent outbox activity attempted this notice; no retry is allowed.', 0, $error);
    }
  }
}

$dispatch = \Drupal::service('famtastic_pipeline.lifecycle_operations')
  ->dispatchNotifications(1, [$notificationKey]);
$receipt = $loadNotice();
if (!$receipt) {
  throw new RuntimeException('The exact outbox row disappeared during dispatch; inspect before any retry.');
}
$assertBound($receipt);
if ((string) ($receipt['status'] ?? '') !== 'sent' || trim((string) ($receipt['provider_message_id'] ?? '')) === '' || empty($receipt['sent_at'])) {
  $emit([
    'status' => 'not_confirmed',
    'notification_key' => $notificationKey,
    'recipient' => $recipient,
    'binding_sha256' => $expectedHash,
    'dispatch' => $dispatch,
    'outbox_id' => (int) $receipt['id'],
    'outbox_status' => (string) ($receipt['status'] ?? ''),
    'attempts' => (int) ($receipt['attempts'] ?? 0),
    'retry_allowed' => false,
  ]);
  throw new RuntimeException('SMTP acceptance is not recorded; inspect the exact outbox receipt and do not retry automatically.');
}

$emit([
  'status' => 'sent',
  'notification_key' => $notificationKey,
  'recipient' => $recipient,
  'template' => $template . '/v' . $templateVersion,
  'binding_sha256' => $expectedHash,
  'dispatch' => $dispatch,
  'outbox_id' => (int) $receipt['id'],
  'provider_message_id' => trim((string) $receipt['provider_message_id']),
  'sent_at' => (int) $receipt['sent_at'],
  'resent' => false,
]);
