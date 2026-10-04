<?php
/** Controlled production fixture. No SMTP and no customer-visible account. */
declare(strict_types=1);
use Drupal\famtastic_pipeline\Service\InboundEnvelope;

$phase = (string) getenv('FAMTASTIC_INBOUND_PROOF_PHASE');
if (!in_array($phase, ['setup', 'verify', 'cleanup'], TRUE)) throw new RuntimeException('proof_phase_required');
$home = (string) getenv('HOME');
$folder = $home . '/deploy/famtastic-designs/inbound-proof';
$receiptFile = $folder . '/fixture.json';
$db = \Drupal::database();
$mailRoot = $home . '/mail/famtasticdesigns.com';
$snapshot = static function () use ($mailRoot): array {
  $files = [];
  foreach (['hello', 'support'] as $box) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($mailRoot . '/' . $box, FilesystemIterator::SKIP_DOTS)) as $file) {
      if ($file->isFile() && !$file->isLink() && in_array(basename(dirname($file->getPathname())), ['new', 'cur'], TRUE)) {
        $files[$file->getPathname()] = hash_file('sha256', $file->getPathname());
      }
    }
  }
  return $files;
};
if ($phase === 'setup') {
  if (is_file($receiptFile)) throw new RuntimeException('existing_fixture_requires_reconciliation');
  if (!\Drupal::state()->get('famtastic.inbound_mail.activation')) throw new RuntimeException('activate_before_fixture');
  if (!is_dir($folder)) mkdir($folder, 0700, TRUE);
  $tag = 'synthetic-inbound-' . bin2hex(random_bytes(8));
  $uuid = \Drupal::service('uuid')->generate();
  $email = $tag . '@example.invalid';
  $key = $tag . ':outbound';
  $outbound = '<' . $tag . '-outbound@example.invalid>';
  $messageId = '<' . $tag . '-reply@example.invalid>';
  $path = $mailRoot . '/hello/new/' . time() . '.' . $tag;
  $fixture = ['tag' => $tag, 'thread_public_id' => $uuid, 'notification_key' => $key, 'message_id' => $messageId,
    'outbound_fixture' => 'simulated_sent_receipt_no_transport', 'path' => $path, 'baseline' => $snapshot(),
    'outbox_sent_before' => (int) $db->select('famtastic_notification_outbox', 'n')->condition('status', 'sent')->countQuery()->execute()->fetchField(),
    'inbound_before' => (int) $db->select('famtastic_inbound_message', 'i')->countQuery()->execute()->fetchField()];
  $tx = $db->startTransaction();
  try {
    $thread = (int) $db->insert('famtastic_portal_thread')->fields(['public_id' => $uuid, 'organization_id' => 0, 'contact_email' => $email,
      'kind' => 'contact', 'subject' => '[SYNTHETIC] inbound ingestion verification', 'created' => time(), 'changed' => time()])->execute();
    $db->insert('famtastic_portal_message')->fields(['thread_id' => $thread, 'author_type' => 'staff', 'body' => '[SYNTHETIC] No outbound mail sent.', 'notification_key' => $key, 'created' => time()])->execute();
    $db->insert('famtastic_notification_outbox')->fields(['notification_key' => $key, 'category' => 'transactional', 'recipient' => $email,
      'subject' => '[SYNTHETIC] receipt fixture', 'body' => '[SYNTHETIC] Simulated receipt only, no SMTP.', 'status' => 'sent',
      'provider_message_id' => $outbound, 'sent_at' => time(), 'created' => time(), 'changed' => time()])->execute();
    $fixture['thread_id'] = $thread;
    file_put_contents($receiptFile, json_encode($fixture, JSON_THROW_ON_ERROR)); chmod($receiptFile, 0600);
    $raw = "Message-ID: $messageId\nFrom: Synthetic fixture <$email>\nTo: hello@famtasticdesigns.com\nIn-Reply-To: $outbound\nSubject: [SYNTHETIC] ingestion test\nContent-Type: text/plain; charset=UTF-8\n\nPlease revise the synthetic fixture. No real customer content.\n";
    $handle = fopen($path, 'x');
    if ($handle === FALSE || fwrite($handle, $raw) !== strlen($raw)) throw new RuntimeException('fixture_mail_write_failed');
    fclose($handle); chmod($path, 0600);
    unset($tx);
  } catch (Throwable $error) { $tx->rollBack(); throw $error; }
  print json_encode(['phase' => 'setup', 'fixture' => 'private_unclaimed_synthetic', 'mailbox' => 'hello', 'historical_files' => count($fixture['baseline']), 'smtp_calls' => 0], JSON_THROW_ON_ERROR) . "\n";
  return;
}
$fixture = json_decode((string) file_get_contents($receiptFile), TRUE, flags: JSON_THROW_ON_ERROR);
$hash = hash('sha256', $fixture['message_id']);
if ($phase === 'verify') {
  $checks = [];
  $inbound = $db->select('famtastic_inbound_message', 'i')->fields('i')->condition('message_id_hash', $hash)->execute()->fetchAll(PDO::FETCH_ASSOC);
  $checks['scheduler_imported_once'] = count($inbound) === 1 && $inbound[0]['status'] === 'matched' && $inbound[0]['thread_public_id'] === $fixture['thread_public_id'];
  $checks['one_portal_reply'] = (int) $db->select('famtastic_portal_message', 'm')->condition('thread_id', $fixture['thread_id'])->condition('author_type', 'customer')->countQuery()->execute()->fetchField() === 1;
  $checks['one_pending_unapproved_draft'] = count($inbound) === 1 && (int) $db->select('famtastic_support_draft', 'd')->condition('message_id', $inbound[0]['id'])->condition('status', 'pending')->countQuery()->execute()->fetchField() === 1;
  $current = $snapshot(); $preserved = 0;
  foreach ($fixture['baseline'] as $path => $expected) if (($current[$path] ?? '') === $expected) $preserved++;
  $checks['all_historical_files_preserved'] = $preserved === count($fixture['baseline']);
  $checks['only_synthetic_message_ingested'] = (int) $db->select('famtastic_inbound_message', 'i')->countQuery()->execute()->fetchField() === $fixture['inbound_before'] + 1;
  $checks['no_additional_outbound_receipts'] = (int) $db->select('famtastic_notification_outbox', 'n')->condition('status', 'sent')->countQuery()->execute()->fetchField() === $fixture['outbox_sent_before'] + 1;
  // Replay through the deployed signed HTTP receiver, not only a service call.
  $payload = json_encode(InboundEnvelope::parse((string) file_get_contents($fixture['path']), time()), JSON_THROW_ON_ERROR);
  $secret = (string) \Drupal\Core\Site\Settings::get('famtastic_inbound_mail_secret');
  $response = \Drupal::httpClient()->post('https://famtasticdesigns.com/web/api/pipeline/mail/inbound', [
    'body' => $payload, 'headers' => ['Content-Type' => 'application/json', 'X-FAMtastic-Mail-Signature' => hash_hmac('sha256', $payload, $secret)], 'timeout' => 30]);
  $replay = json_decode((string) $response->getBody(), TRUE, flags: JSON_THROW_ON_ERROR);
  $checks['signed_http_replay_duplicate'] = $response->getStatusCode() === 202 && $replay['duplicate'] === TRUE && $replay['status'] === 'matched';
  $checks['replay_did_not_duplicate_portal_reply'] = (int) $db->select('famtastic_portal_message', 'm')->condition('thread_id', $fixture['thread_id'])->condition('author_type', 'customer')->countQuery()->execute()->fetchField() === 1;
  $report = ['phase' => 'verify', 'passed' => !in_array(FALSE, $checks, TRUE), 'checks' => $checks, 'historical_files_preserved' => $preserved,
    'proof_boundary' => 'controlled Maildir fixture to Drupal, plus signed HTTP replay; external SMTP reply not exercised', 'smtp_calls' => 0, 'approval' => 'pending'];
  file_put_contents($folder . '/verification.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
  print json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
  if (!$report['passed']) throw new RuntimeException('production_inbound_proof_failed');
  return;
}
// Remove only this exact synthetic fixture, leaving private proof receipts.
$tx = $db->startTransaction();
try {
  $id = $db->select('famtastic_inbound_message', 'i')->fields('i', ['id'])->condition('message_id_hash', $hash)->execute()->fetchField();
  if ($id) { $db->delete('famtastic_support_draft')->condition('message_id', $id)->execute(); $db->delete('famtastic_inbound_message')->condition('id', $id)->execute(); }
  $db->delete('famtastic_portal_message')->condition('thread_id', $fixture['thread_id'])->execute();
  $db->delete('famtastic_portal_thread')->condition('id', $fixture['thread_id'])->condition('public_id', $fixture['thread_public_id'])->execute();
  $db->delete('famtastic_notification_outbox')->condition('notification_key', $fixture['notification_key'])->execute();
  if (is_file($fixture['path'])) unlink($fixture['path']);
  unset($tx);
} catch (Throwable $error) { $tx->rollBack(); throw $error; }
rename($receiptFile, $folder . '/fixture-cleaned.json');
print "{\"phase\":\"cleanup\",\"synthetic_records_removed\":true,\"customer_records_touched\":0}\n";
