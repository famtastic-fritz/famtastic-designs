<?php

declare(strict_types=1);

$database = \Drupal::database();
$schema = $database->schema();
if (!$schema->tableExists('famtastic_revenue_freshness')) {
  throw new RuntimeException('Revenue freshness schema is not installed. Run drush updatedb before this local-only acceptance script.');
}

$now = \Drupal::time()->getRequestTime();
$runId = (string) (getenv('FAMTASTIC_SYNTHETIC_RUN_ID') ?: time());
$uuid = \Drupal::service('uuid');
$projectStorage = \Drupal::entityTypeManager()->getStorage('famtastic_project');
$requestIds = [];
$projectIds = [];
$taskKeys = [];

$outboxCount = static fn(): int => (int) $database->select('famtastic_notification_outbox', 'o')->countQuery()->execute()->fetchField();
$cleanup = static function () use (&$taskKeys, &$requestIds, &$projectIds, $database, $projectStorage): void {
  if ($taskKeys !== []) {
    $database->delete('famtastic_revenue_freshness')->condition('task_key', $taskKeys, 'IN')->execute();
  }
  if ($requestIds !== []) {
    $database->delete('famtastic_project_request')->condition('id', $requestIds, 'IN')->execute();
  }
  if ($projectIds !== []) {
    foreach ($projectStorage->loadMultiple($projectIds) as $project) {
      $project->delete();
    }
  }
};

try {
  $requestBase = [
    'organization_id' => 1,
    'customer_id' => 1,
    'prospect_id' => NULL,
    'commerce_order_id' => NULL,
    'intake_id' => NULL,
    'project_id' => NULL,
    'proof_campaign_id' => NULL,
    'proof_approved_by_uid' => NULL,
    'proof_approved_at' => NULL,
    'proof_notified_at' => NULL,
    'proof_share_enabled' => 0,
    'proof_share_version' => 1,
    'proof_share_changed_at' => NULL,
    'proof_share_changed_by_uid' => NULL,
    'project_type' => 'new_website',
    'domain_choice' => 'undecided',
    'existing_domain' => '',
    'recommendation_requested' => 1,
    'intake_data' => '{}',
  ];
  $notStartedId = (int) $database->insert('famtastic_project_request')->fields($requestBase + [
    'public_id' => $uuid->generate(),
    'status' => 'submitted',
    'project_name' => 'Revenue health proof state ' . $runId,
    'business_name' => 'Revenue health proof state',
    'proof_review_status' => 'not_started',
    'selected_proof_direction' => '',
    'selected_proof_at' => NULL,
    'submitted_at' => $now - 90000,
    'created' => $now - 90000,
    'changed' => $now - 90000,
  ])->execute();
  $selectedId = (int) $database->insert('famtastic_project_request')->fields($requestBase + [
    'public_id' => $uuid->generate(),
    'status' => 'submitted',
    'project_name' => 'Revenue health selection ' . $runId,
    'business_name' => 'Revenue health selection',
    'proof_review_status' => 'selected',
    'selected_proof_direction' => 'a',
    'selected_proof_at' => $now - 260000,
    'submitted_at' => $now - 260000,
    'created' => $now - 260000,
    'changed' => $now - 260000,
  ])->execute();
  $requestIds = [$notStartedId, $selectedId];

  $stale = $projectStorage->create(['delivery_status' => 'submitted', 'approval_status' => 'pending']);
  $stale->save();
  $receipt = $projectStorage->create(['delivery_status' => 'approved', 'approval_status' => 'approved', 'approved_at' => $now - 90000]);
  $receipt->save();
  $staleId = (int) $stale->id();
  $receiptId = (int) $receipt->id();
  $projectIds = [$staleId, $receiptId];
  $database->update('famtastic_project')->fields(['changed' => $now - 605000])->condition('id', $staleId)->execute();

  $taskKeys = [
    'website_request:' . $notStartedId . ':submitted_request',
    'website_request:' . $notStartedId . ':proof_state',
    'website_request:' . $selectedId . ':selected_not_paid',
    'project:' . $staleId . ':stale',
    'project:' . $receiptId . ':release_receipt',
  ];
  $beforeOutbox = $outboxCount();
  $operations = \Drupal::service('famtastic_pipeline.lifecycle_operations');
  $first = $operations->reconcileRevenueFreshness($now);
  $open = (int) $database->select('famtastic_revenue_freshness', 'f')->condition('task_key', $taskKeys, 'IN')
    ->condition('status', 'open')->countQuery()->execute()->fetchField();
  $health = $operations->revenueHealth(FALSE);
  $afterOutbox = $outboxCount();

  $database->update('famtastic_project_request')->fields(['status' => 'converted', 'changed' => $now])->condition('id', $notStartedId)->execute();
  $database->update('famtastic_project_request')->fields(['commerce_order_id' => 999999, 'changed' => $now])->condition('id', $selectedId)->execute();
  $database->update('famtastic_project')->fields(['changed' => $now])->condition('id', $staleId)->execute();
  $database->update('famtastic_project')->fields(['release_sha' => str_repeat('a', 40), 'artifact_checksum' => str_repeat('b', 64)])->condition('id', $receiptId)->execute();
  $second = $operations->reconcileRevenueFreshness($now + 1);
  $recoveredRows = $database->select('famtastic_revenue_freshness', 'f')->fields('f', ['recovery_evidence_json'])
    ->condition('task_key', $taskKeys, 'IN')->condition('status', 'recovered')->execute()->fetchAll(\PDO::FETCH_ASSOC);
  $recoveryEvidenceValid = count($recoveredRows) === 5;
  foreach ($recoveredRows as $row) {
    $evidence = json_decode((string) $row['recovery_evidence_json'], TRUE);
    $recoveryEvidenceValid = $recoveryEvidenceValid && ($evidence['reason'] ?? '') === 'source_condition_not_observed';
  }
  $checks = [
    'all_five_owner_task_types_open' => $open === 5,
    'report_schema_is_dashboard_friendly' => ($health['schema'] ?? '') === 'famtastic.revenue-health.v1' && ($health['owner_task_only'] ?? FALSE) === TRUE,
    'freshness_never_queues_outbox_mail' => $beforeOutbox === $afterOutbox,
    'first_reconciliation_created_expected_tasks' => (int) ($first['created'] ?? 0) === 5,
    'source_recovery_is_durable' => (int) ($second['recovered'] ?? 0) === 5 && $recoveryEvidenceValid,
  ];
  if (in_array(FALSE, $checks, TRUE)) {
    throw new RuntimeException('Revenue freshness acceptance failed: ' . json_encode($checks, JSON_THROW_ON_ERROR));
  }
  echo json_encode([
    'schema' => 'famtastic.revenue-health-e2e.v1',
    'status' => 'passed',
    'run_id' => $runId,
    'checks' => $checks,
    'external_effects' => [],
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
}
finally {
  $cleanup();
}
