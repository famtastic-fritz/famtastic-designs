<?php
/** Isolated local database + Maildirs. No SMTP, client records or production paths. */
declare(strict_types=1);
use Drupal\famtastic_pipeline\Service\InboundMailboxService;
use Drupal\famtastic_pipeline\Service\InboundEnvelope;
use Drupal\famtastic_pipeline\Service\InboundReplyCorrelation;
use Drupal\Core\State\State;
use Drupal\Core\KeyValueStore\MemoryStorage;

$db = \Drupal::database();
$transaction = $db->startTransaction();
$root = sys_get_temp_dir() . '/inbound-test-' . bin2hex(random_bytes(5));
foreach (['hello', 'support'] as $box) mkdir($root . '/' . $box . '/new', 0700, TRUE);
// State is also isolated; fixture baseline never overwrites runtime activation.
$state = new class implements \Drupal\Core\State\StateInterface {
  private array $values = [];
  public function get($key, $default = NULL) { return $this->values[$key] ?? $default; }
  public function getMultiple(array $keys) { return array_intersect_key($this->values, array_flip($keys)); }
  public function set($key, $value) { $this->values[$key] = $value; }
  public function setMultiple(array $data) { $this->values = $data + $this->values; }
  public function delete($key) { unset($this->values[$key]); }
  public function deleteMultiple(array $keys) { foreach ($keys as $key) $this->delete($key); }
  public function resetCache() {}
  public function getValuesSetDuringRequest(string $key): ?array { return isset($this->values[$key]) ? [$this->values[$key]] : NULL; }
};
$checks = [];
$uuid = \Drupal::service('uuid')->generate();
$email = 'inbound-fixture@example.invalid';
$key = 'inbound-fixture:' . bin2hex(random_bytes(6));
$id = '<' . $key . '@example.invalid>';
try {
  $thread = (int) $db->insert('famtastic_portal_thread')->fields(['public_id' => $uuid, 'organization_id' => 0, 'contact_email' => $email, 'kind' => 'contact', 'subject' => 'Synthetic ingestion test', 'created' => time(), 'changed' => time()])->execute();
  $db->insert('famtastic_portal_message')->fields(['thread_id' => $thread, 'author_type' => 'staff', 'body' => 'Fixture outbound', 'notification_key' => $key, 'created' => time()])->execute();
  $db->insert('famtastic_notification_outbox')->fields(['notification_key' => $key, 'category' => 'transactional', 'recipient' => $email, 'subject' => 'Fixture', 'body' => 'Fixture', 'status' => 'sent', 'provider_message_id' => $id, 'sent_at' => time(), 'created' => time(), 'changed' => time()])->execute();
  $wire = static fn(string $msg, string $from, string $to, string $refs): string => "Message-ID: <$msg@example.invalid>\nFrom: $from\nTo: $to\nReferences: $refs\nSubject: Synthetic test\n\nPlease revise this fixture.";
  $old = $wire('historical-' . $key, $email, 'hello@famtasticdesigns.com', $id);
  file_put_contents($root . '/hello/new/historical', $old);
  $operations = \Drupal::service('famtastic_pipeline.lifecycle_operations');
  $service = new InboundMailboxService($state, \Drupal::lock(), $db, $operations, $root);
  $service->activate();
  // Activation is idempotent; a later call cannot exclude new mail silently.
  $new = $wire('reply-' . $key, $email, 'hello@famtasticdesigns.com', $id);
  file_put_contents($root . '/hello/new/new-reply', $new);
  $service->activate();
  $outboxBefore = (int) $db->select('famtastic_notification_outbox', 'n')->countQuery()->execute()->fetchField();
  $report = $service->tick();
  $checks['hello_exact_sent_receipt_matches'] = $report['matched'] === 1 && $report['processed'] === 1;
  $checks['historical_baseline_preserved'] = file_get_contents($root . '/hello/new/historical') === $old && $report['excluded'] === 1;
  $checks['original_reply_preserved'] = file_get_contents($root . '/hello/new/new-reply') === $new;
  $checks['second_scan_no_reimport'] = $service->tick()['processed'] === 0;
  $message = InboundEnvelope::parse($new, time());
  $checks['endpoint_replay_idempotent'] = $operations->ingestInbound($message)['duplicate'] === TRUE;
  $count = (int) $db->select('famtastic_portal_message', 'm')->condition('thread_id', $thread)->condition('author_type', 'customer')->countQuery()->execute()->fetchField();
  $checks['one_customer_message'] = $count === 1;
  $inboundId = $db->select('famtastic_inbound_message', 'i')->fields('i', ['id'])->condition('message_id_hash', hash('sha256', $message['message_id']))->execute()->fetchField();
  $checks['one_unapproved_draft'] = (int) $db->select('famtastic_support_draft', 'd')->condition('message_id', $inboundId)->condition('status', 'pending')->countQuery()->execute()->fetchField() === 1;
  // Wrong sender cannot use the receipt, and a from-only hello reply never guesses.
  $forged = InboundEnvelope::parse($wire('forged-' . $key, 'wrong@example.invalid', 'hello@famtasticdesigns.com', $id), time());
  $checks['wrong_sender_unmatched'] = $operations->ingestInbound($forged)['status'] === 'unmatched';
  $unknown = InboundEnvelope::parse($wire('unknown-' . $key, $email, 'hello@famtasticdesigns.com', ''), time());
  $checks['sender_only_unmatched'] = $operations->ingestInbound($unknown)['status'] === 'unmatched';
  $support = InboundEnvelope::parse($wire('support-' . $key, $email, 'support+' . $uuid . '@famtasticdesigns.com', ''), time());
  $checks['existing_support_plus_route'] = $operations->ingestInbound($support)['status'] === 'matched';
  $secondUuid = \Drupal::service('uuid')->generate();
  $secondThread = $db->insert('famtastic_portal_thread')->fields(['public_id' => $secondUuid, 'organization_id' => 0, 'contact_email' => $email, 'subject' => 'Other fixture', 'created' => time(), 'changed' => time()])->execute();
  $otherKey = $key . '-other'; $otherId = '<other-' . $key . '@example.invalid>';
  $db->insert('famtastic_portal_message')->fields(['thread_id' => $secondThread, 'author_type' => 'staff', 'body' => 'Other outbound', 'notification_key' => $otherKey, 'created' => time()])->execute();
  $db->insert('famtastic_notification_outbox')->fields(['notification_key' => $otherKey, 'category' => 'transactional', 'recipient' => $email, 'subject' => 'Fixture', 'body' => 'Fixture', 'status' => 'sent', 'provider_message_id' => $otherId, 'created' => time(), 'changed' => time()])->execute();
  $message['references'][] = $otherId;
  $checks['ambiguous_references_do_not_guess'] = (new InboundReplyCorrelation($db))->resolve($message) === '';
  $db->delete('famtastic_notification_outbox')->condition('notification_key', $otherKey)->execute();
  $checks['ingestion_queues_no_outbound'] = (int) $db->select('famtastic_notification_outbox', 'n')->countQuery()->execute()->fetchField() === $outboxBefore;
  file_put_contents($root . '/hello/new/invalid', 'not a valid message');
  $bad = $service->tick();
  $checks['invalid_preserved_and_retry_visible'] = $bad['failed'] === 1 && is_file($root . '/hello/new/invalid') && $service->health()['retry_files'] === 1;
  $checks['retry_backoff'] = $service->tick()['processed'] === 0;
  $checks['clock_heartbeat_recent'] = $service->health()['clock_status'] === 'recent';
  $org = $db->insert('famtastic_organization')->fields(['public_id' => \Drupal::service('uuid')->generate(), 'name' => 'Isolated synthetic organization', 'created' => time(), 'changed' => time()])->execute();
  $customer = $db->insert('famtastic_customer')->fields(['public_id' => \Drupal::service('uuid')->generate(), 'uid' => 2147483646, 'display_name' => 'Synthetic member', 'email' => 'member-' . $email, 'created' => time(), 'changed' => time()])->execute();
  $membership = $db->insert('famtastic_membership')->fields(['customer_id' => $customer, 'organization_id' => $org, 'status' => 'active', 'created' => time(), 'changed' => time()])->execute();
  $projectUuid = \Drupal::service('uuid')->generate();
  $db->insert('famtastic_portal_thread')->fields(['public_id' => $projectUuid, 'organization_id' => $org, 'project_id' => 2147483646, 'kind' => 'project', 'subject' => 'Isolated project fixture', 'created' => time(), 'changed' => time()])->execute();
  $projectMessage = InboundEnvelope::parse($wire('member-' . $key, 'member-' . $email, 'support+' . $projectUuid . '@famtasticdesigns.com', ''), time());
  $checks['active_member_matches_exact_project'] = $operations->ingestInbound($projectMessage)['status'] === 'matched';
  $projectMessage['message_id'] = '<outsider-' . $key . '@example.invalid>';
  $projectMessage['from'] = $email;
  $checks['cross_tenant_sender_denied'] = $operations->ingestInbound($projectMessage)['status'] === 'unmatched';
  $db->update('famtastic_membership')->fields(['status' => 'inactive'])->condition('id', $membership)->execute();
  $projectMessage['message_id'] = '<inactive-' . $key . '@example.invalid>';
  $projectMessage['from'] = 'member-' . $email;
  $checks['inactive_membership_denied'] = $operations->ingestInbound($projectMessage)['status'] === 'unmatched';
  print json_encode(['passed' => !in_array(FALSE, $checks, TRUE), 'checks' => $checks], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
} finally {
  $transaction->rollBack();
  foreach (glob($root . '/*/new/*') as $file) unlink($file);
  foreach (['hello', 'support'] as $box) { rmdir($root . '/' . $box . '/new'); rmdir($root . '/' . $box); }
  rmdir($root);
}
if (in_array(FALSE, $checks, TRUE)) throw new \RuntimeException('inbound_acceptance_failed');
