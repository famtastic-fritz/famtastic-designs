<?php

/**
 * B2/B4 acceptance: draft generation on ingest, L0 decision flow, SLA breach
 * alerts. Local-only, memory transport, fully idempotent via fixed synthetic
 * message ids; everything created here is removed in the finally block.
 */

use Drupal\famtastic_pipeline\Service\SupportDraftService;
use Drupal\famtastic_pipeline\Service\SupportSlaService;

$database = \Drupal::database();
$time = \Drupal::time();
$drafts = \Drupal::service('famtastic_pipeline.support_drafts');
$sla = \Drupal::service('famtastic_pipeline.support_sla');
$lifecycle = \Drupal::service('famtastic_pipeline.lifecycle_operations');

$checks = [];
$cleanupTables = static function () use ($database): void {
  $ids = $database->select('famtastic_support_draft', 'd')
    ->fields('d', ['id'])
    ->condition('thread_public_id', 'b2e2test-0000-0000-0000-000000000001')
    ->execute()->fetchCol();
  if ($ids) {
    $database->delete('famtastic_notification_outbox')
      ->condition('notification_key', array_map(static fn($i): string => 'support-draft:' . $i, $ids), 'IN')->execute();
    $database->delete('famtastic_notification_outbox')
      ->condition('notification_key', array_map(static fn($i): string => 'support-sla-breach:' . $i, $ids), 'IN')->execute();
    $database->delete('famtastic_notification_outbox')
      ->condition('notification_key', array_map(static fn($i): string => 'support-draft:' . $i . ':unresolved', $ids), 'IN')->execute();
    $database->delete('famtastic_support_draft')->condition('id', $ids, 'IN')->execute();
  }
  $database->delete('famtastic_inbound_message')
    ->condition('message_id_hash', [hash('sha256', 'b2e2-msg-1'), hash('sha256', 'b2e2-msg-old')], 'IN')->execute();
  $database->delete('famtastic_customer')
    ->condition('email', 'b2e2-customer@example.test')->execute();
};

$seedCustomer = static function () use ($database, $time): void {
  $database->insert('famtastic_customer')->fields([
    'public_id' => 'b2e2cust-0000-0000-0000-000000000001',
    'uid' => 0, 'display_name' => 'B2E2 Test Customer',
    'email' => 'b2e2-customer@example.test',
    'created' => $time->getRequestTime(), 'changed' => $time->getRequestTime(),
  ])->execute();
};

try {
  $cleanupTables();
  $seedCustomer();
  $now = $time->getRequestTime();

  // --- Ingest two synthetic messages (billing question + stale technical) --
  $lifecycle->ingestInbound([
    'message_id' => 'b2e2-msg-1',
    'from' => 'b2e2-customer@example.test',
    'to' => 'support@famtasticdesigns.com',
    'subject' => 'Charge on my card?',
    'body' => 'There is a charge from your company on my credit card that I do not recognize. Please explain or refund it.',
    'received_at' => $now,
  ]);
  $lifecycle->ingestInbound([
    'message_id' => 'b2e2-msg-old',
    'from' => 'b2e2-customer@example.test',
    'to' => 'support@famtasticdesigns.com',
    'subject' => 'Site is down',
    'body' => 'My website is not working at all. Visitors say it shows an error page.',
    'received_at' => $now - 10 * 3600,
  ]);

  $rows = $database->select('famtastic_inbound_message', 'm')
    ->fields('m', ['id'])
    ->condition('message_id_hash', [hash('sha256', 'b2e2-msg-1'), hash('sha256', 'b2e2-msg-old')], 'IN')
    ->execute()->fetchCol();

  // Drafts must have been generated automatically during ingest.
  $generated = $database->select('famtastic_support_draft', 'd')
    ->fields('d', ['id', 'intent', 'confidence', 'escalate', 'status', 'created'])
    ->condition('d.message_id', $rows ?: [0], 'IN')
    ->execute()->fetchAll(\PDO::FETCH_ASSOC);
  $checks['auto_drafts_created_on_ingest'] = count($generated) === 2;
  $byIntent = [];
  foreach ($generated as $g) { $byIntent[$g['intent']] = $g; }
  $checks['billing_intent_classified'] = isset($byIntent['billing']);
  $checks['technical_intent_classified'] = isset($byIntent['technical']);

  // Idempotency: re-running ingest must not duplicate drafts.
  $lifecycle->ingestInbound([
    'message_id' => 'b2e2-msg-1', 'from' => 'b2e2-customer@example.test', 'to' => 'support@famtasticdesigns.com',
    'subject' => 'Charge on my card?', 'body' => 'duplicate body', 'received_at' => $now,
  ]);
  $count = (int) $database->select('famtastic_support_draft', 'd')
    ->condition('d.message_id', $rows ?: [0], 'IN')->countQuery()->execute()->fetchField();
  $checks['draft_generation_idempotent'] = $count === 2;

  // --- SLA: the 10h-old technical draft (4h target) must breach ------------
  $breachedIds = array_map(static fn(array $b): int => $b['id'], $sla->breaches());
  $staleDraft = NULL;
  foreach ($generated as $g) { if ($g['intent'] === 'technical') { $staleDraft = $g; } }
  $checks['sla_breach_detected'] = $staleDraft && in_array((int) $staleDraft['id'], $breachedIds, TRUE);

  $alertsQueued = $sla->alertBreaches();
  $alertKey = 'support-sla-breach:' . ($staleDraft ? (int) $staleDraft['id'] : 0);
  $alertPresent = (int) $database->select('famtastic_notification_outbox', 'o')
    ->condition('notification_key', $alertKey)->countQuery()->execute()->fetchField();
  $checks['sla_owner_alert_queued'] = $alertPresent === 1;
  // Second scan must not duplicate alerts.
  $sla->alertBreaches();
  $alertDupes = (int) $database->select('famtastic_notification_outbox', 'o')
    ->condition('notification_key', $alertKey)->countQuery()->execute()->fetchField();
  $checks['sla_alert_idempotent'] = $alertDupes === 1;

  // --- L0 decision flow -----------------------------------------------------
  $billingDraft = null;
  foreach ($generated as $g) { if ($g['intent'] === 'billing') { $billingDraft = $g; } }
  $decided = $drafts->decide((int) $billingDraft['id'], 1, TRUE);
  $checks['approve_decision_accepted'] = $decided;
  $queuedReply = (int) $database->select('famtastic_notification_outbox', 'o')
    ->condition('notification_key', 'support-draft:' . $billingDraft['id'])
    ->condition('status', 'queued')->countQuery()->execute()->fetchField();
  $checks['approved_reply_in_reviewed_outbox'] = $queuedReply === 1;
  $checks['reject_path_works'] = $drafts->decide((int) $staleDraft['id'], 1, FALSE)
    && $database->select('famtastic_support_draft', 'd')->fields('d', ['status'])
      ->condition('id', $staleDraft['id'])->execute()->fetchField() === 'rejected';

  // Queue page renders.
  try {
    $controller = \Drupal::classResolver('\Drupal\famtastic_pipeline\Controller\OperationsController');
    $html = (string) \Drupal::service('renderer')->renderRoot($controller->metric('support-drafts'));
    $checks['queue_page_renders'] = str_contains($html, 'Draft preview') && str_contains($html, 'SLA');
  }
  catch (\Throwable $e) {
    $checks['queue_page_renders'] = FALSE;
    $evidence_render_error = substr((string) $e->getMessage(), 0, 200);
  }
  $checks['queue_page_shows_intents'] = str_contains($html, '>billing<') || str_contains($html, 'Billing');

  $failed = array_keys($checks, FALSE, TRUE);
  $evidence = [
    'schema' => 'famtastic.support-triage-b2-b4.v1',
    'status' => $failed === [],
    'checks' => $checks,
    'failures' => $failed,
    'transport' => 'local sqlite, memory mail',
    'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
  ];
}
finally {
  $cleanupTables();
}

if (isset($evidence)) {
  $dest = getenv('EVIDENCE_DIR') ?: sys_get_temp_dir();
  $evidence['render_error'] = $evidence_render_error ?? '';
  file_put_contents($dest . '/evidence.json', json_encode($evidence, JSON_PRETTY_PRINT) . "\n");
  printf("%s — checks=%d failures=%s\n", $evidence['status'] ? 'PASS' : 'FAIL', count($checks), implode(',', $evidence['failures']) ?: 'none');
  return;
}
printf("FAIL — evidence not produced\n");
