<?php

declare(strict_types=1);

// Never commits a customer selection. Run only after the complete approved proof
// set exists. Existing selection/status is not manufactured for this test.
if (PHP_SAPI !== 'cli') throw new RuntimeException('CLI only');
require_once __DIR__ . '/../web/modules/custom/famtastic_pipeline/src/Service/OfflinePrepaymentService.php';
use Drupal\famtastic_pipeline\Service\OfflinePrepaymentService;
$db = Drupal::database();
$transaction = $db->startTransaction();
try {
  $row = $db->select('famtastic_project_request', 'r')->fields('r')->condition('id', 17)->forUpdate()->execute()->fetchAssoc();
  if (!$row || !in_array($row['proof_review_status'], ['customer_ready', 'notified'], TRUE) || $row['selected_proof_direction'] !== '') throw new RuntimeException('Real client-ready unselected proofs required; no selection rehearsal performed');
  if (!empty($row['commerce_order_id']) || !(new OfflinePrepaymentService())->permitsSelectedStaging($row)) throw new RuntimeException('Prepayment staging binding failed');
  $portal = Drupal::service('famtastic_pipeline.customer_portal');
  try { $portal->decideWebsiteRequestProof(14, $row['public_id'], ['action' => 'select', 'direction' => 'a']); throw new LogicException('Cross-account selection allowed'); }
  catch (RuntimeException $error) { if ($error->getMessage() !== 'Website proofs are not available.') throw $error; }
  $result = $portal->decideWebsiteRequestProof(15, $row['public_id'], ['action' => 'select', 'direction' => 'a']);
  $after = $db->select('famtastic_project_request', 'r')->fields('r')->condition('id', 17)->execute()->fetchAssoc();
  if ($after['proof_review_status'] !== 'selected' || $after['selected_proof_direction'] !== 'a'
    || $after['staging_status'] !== 'queued' || $after['staging_review_status'] === 'accepted' || !empty($after['staging_reviewed_at'])) throw new RuntimeException('Selection did not queue staging separately from acceptance');
  $jobs = $db->select('famtastic_job', 'j')->fields('j', ['id', 'job_key', 'status'])
    ->condition('job_key', 'site-studio.staging:request:17:%', 'LIKE')->execute()->fetchAll(PDO::FETCH_ASSOC);
  if (count($jobs) !== 1 || $jobs[0]['status'] !== 'queued') throw new RuntimeException('Expected one selected-staging job');
  $receipt = (new OfflinePrepaymentService())->receipt(Drupal::entityTypeManager()->getStorage('commerce_order')->load(21));
  if ($receipt['payment_id'] !== 5) throw new RuntimeException('Payment changed');
  $transaction->rollBack();
  echo json_encode(['request_id' => 17, 'account_bound_selection_passed' => TRUE, 'one_staging_job' => TRUE,
    'customer_acceptance_not_inferred' => TRUE, 'payment_unchanged' => TRUE, 'all_selection_and_notification_writes_rolled_back' => TRUE], JSON_PRETTY_PRINT) . PHP_EOL;
}
catch (Throwable $error) { $transaction->rollBack(); throw $error; }
