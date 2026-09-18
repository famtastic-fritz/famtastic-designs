<?php

declare(strict_types=1);

// Private CLI operation only. Run through the existing Drupal/Commerce runtime.
// No email send, general queue, order placement, real charge or launch occurs.
if (PHP_SAPI !== 'cli') throw new RuntimeException('CLI only');
require_once __DIR__ . '/../web/modules/custom/famtastic_pipeline/src/Service/OfflinePrepaymentService.php';

use Drupal\famtastic_pipeline\Service\OfflinePrepaymentService;

$mode = getenv('FAMTASTIC_PREPAYMENT_MODE') ?: 'dry-run';
$request = '4940a4fd-91af-40c4-b8a5-2b4dad1a3b95';
if (!in_array($mode, ['dry-run', 'apply', 'receipt'], TRUE)) throw new RuntimeException('Unknown mode');
if ($mode === 'apply' && getenv('FAMTASTIC_PREPAYMENT_CONFIRM') !== 'request17:customer15:Zelle:USD200:owner-confirmed') throw new RuntimeException('Exact confirmation required');
$service = new OfflinePrepaymentService();
$db = Drupal::database();
$scope = OfflinePrepaymentService::stockandshipScope();
$confirmation = 'Fritz explicitly confirmed that his childhood friend, request 17, paid $200 through Zelle and instructed FAMtastic to record it as received. Transaction reference and bank receipt date were not supplied.';
if ($mode === 'receipt') {
  $id = $db->select('famtastic_private_offer', 'o')->fields('o', ['commerce_order_id'])->condition('public_id', OfflinePrepaymentService::offerId($request))->execute()->fetchField();
  if (!$id) throw new RuntimeException('No receipt exists');
  echo json_encode($service->receipt(Drupal::entityTypeManager()->getStorage('commerce_order')->load((int) $id)), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
  return;
}
$outer = $db->startTransaction();
try {
  $receipt = $service->record($request, 15, 'sprospere@yahoo.com', $scope, $confirmation);
  $again = $service->record($request, 15, 'sprospere@yahoo.com', $scope, $confirmation);
  if ($receipt['order_id'] !== $again['order_id'] || $receipt['payment_id'] !== $again['payment_id'] || !$again['existing']) throw new RuntimeException('Replay failed');
  $thread = $service->ensureProjectConversation($request, 15, 'sprospere@yahoo.com');
  $threadAgain = $service->ensureProjectConversation($request, 15, 'sprospere@yahoo.com');
  if ($thread !== $threadAgain) throw new RuntimeException('Conversation replay failed');
  $checks = ['record_replay_same_order_payment' => TRUE, 'conversation_replay_same_thread' => TRUE];
  if ($mode === 'dry-run') {
    try { $service->record($request, 14, 'mbshclassof2000@gmail.com', $scope, $confirmation); throw new LogicException('Cross-account receipt allowed'); }
    catch (RuntimeException $error) { if ($error->getMessage() !== 'prepayment_account_mismatch') throw $error; $checks['cross_account_record_denied'] = TRUE; }
    // Rehearse code issuance/consumption only before the genuine code is issued.
    $staff = Drupal::entityTypeManager()->getStorage('user')->load(1);
    $customer = Drupal::entityTypeManager()->getStorage('user')->load(15);
    $other = Drupal::entityTypeManager()->getStorage('user')->load(14);
    $code = $service->issueCompletionCode($receipt['order_id'], $staff);
    $input = ['accept_terms' => TRUE, 'terms_version' => $scope['version'], 'scope_hash' => hash('sha256', json_encode($scope, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)), 'domain_choice' => 'undecided'];
    try { $service->complete($receipt['order_id'], $other, $code, $input); throw new LogicException('Cross-account code allowed'); }
    catch (RuntimeException $error) { if ($error->getMessage() !== 'completion_account_mismatch') throw $error; $checks['cross_account_code_denied'] = TRUE; }
    $completed = $service->complete($receipt['order_id'], $customer, $code, $input);
    if ($completed['order_id'] !== $receipt['order_id'] || $completed['payment_id'] !== $receipt['payment_id']) throw new RuntimeException('Completion created another purchase');
    try { $service->complete($receipt['order_id'], $customer, $code, $input); throw new LogicException('Reused code allowed'); }
    catch (RuntimeException $error) { if ($error->getMessage() !== 'completion_code_invalid_or_used') throw $error; $checks['used_code_denied'] = TRUE; }
    $messaging = Drupal::service('famtastic_pipeline.client_messages');
    $detail = $messaging->detail($customer, $thread['public_id']);
    if (($detail['thread']['context']['request_id'] ?? 0) !== 17) throw new RuntimeException('Conversation request context mismatch');
    try { $messaging->detail($other, $thread['public_id']); throw new LogicException('Cross-account conversation allowed'); }
    catch (RuntimeException $error) { if ($error->getMessage() !== 'Conversation not found.') throw $error; $checks['cross_account_conversation_denied'] = TRUE; }
    // No synthetic messages are written, even in this rollback rehearsal.
    $outer->rollBack();
    $checks['all_mutations_rolled_back'] = TRUE;
  }
  else { unset($outer); }
  echo json_encode(['mode' => $mode, 'receipt' => $receipt, 'conversation' => $thread, 'checks' => $checks, 'completion_code_issued' => FALSE], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}
catch (Throwable $error) { if (isset($outer)) $outer->rollBack(); throw $error; }
