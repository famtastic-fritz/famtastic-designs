<?php

declare(strict_types=1);

// Exact private CLI operation for invoice TR-KAO-001. Run through the
// deployed Drupal/Commerce runtime. It records no bank transaction identifier,
// sends no email, starts no fulfillment, changes no DNS, and launches nothing.
if (PHP_SAPI !== 'cli') {
  throw new RuntimeException('CLI only');
}

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_price\Price;
use Drupal\famtastic_pipeline\Service\CustomerInvoiceService;

$mode = getenv('FAMTASTIC_KOFI_ZELLE_MODE') ?: 'dry-run';
$confirm = getenv('FAMTASTIC_KOFI_ZELLE_CONFIRM') ?: '';
$expectedConfirm = 'TR-KAO-001:customer13:Zelle:USD100:owner-confirmed:2026-10-03';
if (!in_array($mode, ['dry-run', 'apply', 'receipt'], TRUE)) {
  throw new RuntimeException('Unknown mode');
}
if ($mode === 'apply' && !hash_equals($expectedConfirm, $confirm)) {
  throw new RuntimeException('Exact confirmation required');
}

$requestPublicId = 'fb3c723f-072b-4458-a91f-e3dea54f840c';
$customerId = 13;
$organizationId = 13;
$requestId = 15;
$invoiceNumber = 'TR-KAO-001';
$evidenceId = hash('sha256', $expectedConfirm);
$authority = 'Fritz Medine confirmed receipt through Zelle on October 3, 2026 and instructed FAMtastic to update the invoice record.';
$provider = CustomerInvoiceService::OFFLINE_PAYMENT_PROVIDER;
$dataKey = 'famtastic_customer_invoice_offline_payment';
$gatewayId = 'famtastic_manual_invoice_receipt';
$db = Drupal::database();
$entities = Drupal::entityTypeManager();
$invoices = Drupal::service('famtastic_pipeline.customer_invoices');

$receipt = static function (array $invoice) use ($entities, $dataKey, $gatewayId, $evidenceId): array {
  $orderId = (int) ($invoice['commerce_order_id'] ?? 0);
  if ((string) ($invoice['status'] ?? '') !== 'paid' || $orderId <= 0) {
    throw new RuntimeException('Offline invoice receipt is unavailable');
  }
  $order = $entities->getStorage('commerce_order')->load($orderId);
  if (!$order || $order->getState()->value !== 'draft' || $order->getPlacedTime()) {
    throw new RuntimeException('Offline invoice order state mismatch');
  }
  $data = (array) $order->getData($dataKey);
  if (($data['invoice_public_id_sha256'] ?? '') !== hash('sha256', (string) $invoice['public_id'])
    || ($data['snapshot_sha256'] ?? '') !== (string) $invoice['snapshot_sha256']
    || ($data['evidence_id'] ?? '') !== $evidenceId
    || ($data['evidence_state'] ?? '') !== 'owner_attested_received'
    || ($data['terms_accepted'] ?? NULL) !== FALSE
    || ($data['staging_acceptance_inferred'] ?? NULL) !== FALSE) {
    throw new RuntimeException('Offline invoice evidence mismatch');
  }
  $payments = $entities->getStorage('commerce_payment')->loadMultipleByOrder($order);
  if (count($payments) !== 1) {
    throw new RuntimeException('Offline invoice payment count mismatch');
  }
  $payment = reset($payments);
  if (!$payment->isCompleted()
    || $payment->getPaymentGatewayId() !== $gatewayId
    || !$payment->getAmount()->equals(new Price('100.00', 'USD'))
    || !$payment->getRefundedAmount()->isZero()
    || !$order->getTotalPrice()->equals(new Price('100.00', 'USD'))
    || !$order->getBalance()->isZero()) {
    throw new RuntimeException('Offline invoice financial reconciliation required');
  }
  return [
    'invoice_number' => (string) $invoice['invoice_number'],
    'invoice_status' => (string) $invoice['status'],
    'invoice_snapshot_sha256' => (string) $invoice['snapshot_sha256'],
    'commerce_order_id' => $orderId,
    'payment_id' => (int) $payment->id(),
    'payment_state' => (string) $payment->getState()->value,
    'received' => '100.00',
    'outstanding' => '0.00',
    'currency' => 'USD',
    'payment_method' => 'Zelle',
    'evidence_state' => 'owner_attested_received',
    'provider_verified' => FALSE,
    'bank_transaction_reference_recorded' => FALSE,
    'bank_received_at_recorded' => FALSE,
    'recorded_at' => (int) ($data['recorded_at'] ?? 0),
    'terms_accepted' => FALSE,
    'staging_acceptance_inferred' => FALSE,
    'email_queued' => FALSE,
    'fulfillment_started' => FALSE,
    'dns_changed' => FALSE,
    'launch_authorized' => FALSE,
  ];
};

$invoice = $invoices->invoiceForRequest($customerId, $organizationId, $requestPublicId);
if (!$invoice || (string) $invoice['invoice_number'] !== $invoiceNumber
  || (int) $invoice['website_request_id'] !== $requestId
  || (int) $invoice['total_amount_minor'] !== 10000
  || strtolower((string) $invoice['currency']) !== 'usd') {
  throw new RuntimeException('Exact invoice scope mismatch');
}
if ($mode === 'receipt') {
  echo json_encode($receipt($invoice), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
  return;
}

$outer = $db->startTransaction();
try {
  if ((string) $invoice['status'] === 'paid') {
    $result = ['invoice' => $invoice, 'transitioned' => FALSE];
  }
  else {
    $customer = $db->select('famtastic_customer', 'c')->fields('c')
      ->condition('id', $customerId)->execute()->fetchAssoc();
    $staff = $entities->getStorage('user')->load(1);
    $customerUser = $customer ? $entities->getStorage('user')->load((int) $customer['uid']) : NULL;
    if (!$customer || empty($customer['verified_at']) || !$customerUser || !$customerUser->isActive()
      || !$staff || !$staff->isActive() || !$staff->hasPermission('administer famtastic pipeline')) {
      throw new RuntimeException('Verified customer and staff accounts required');
    }
    $gateway = $entities->getStorage('commerce_payment_gateway')->load($gatewayId);
    if (!$gateway) {
      $gateway = $entities->getStorage('commerce_payment_gateway')->create([
        'id' => $gatewayId,
        'label' => 'Owner-confirmed invoice receipts (staff only)',
        'plugin' => 'manual',
        'status' => FALSE,
        'configuration' => [
          'display_label' => 'Offline payment already received',
          'mode' => 'n/a',
          'instructions' => ['value' => '', 'format' => 'plain_text'],
        ],
      ]);
      $gateway->save();
    }
    if ($gateway->getPluginId() !== 'manual' || $gateway->status()) {
      throw new RuntimeException('Offline invoice gateway must remain disabled and manual');
    }
    $store = $entities->getStorage('commerce_store')->load(1);
    if (!$store || $store->getDefaultCurrencyCode() !== 'USD') {
      throw new RuntimeException('Commerce store currency mismatch');
    }
    $item = $entities->getStorage('commerce_order_item')->create([
      'type' => 'default',
      'title' => 'The Reckoning — owner-hosted launch contribution',
      'quantity' => '1',
      'unit_price' => new Price('100.00', 'USD'),
    ]);
    $item->setUnitPrice(new Price('100.00', 'USD'), TRUE);
    $item->save();
    $now = Drupal::time()->getCurrentTime();
    $order = $entities->getStorage('commerce_order')->create([
      'type' => 'default',
      'store_id' => $store->id(),
      'uid' => (int) $customer['uid'],
      'mail' => (string) $customer['email'],
      'order_items' => [$item],
      'state' => 'draft',
      'cart' => FALSE,
      'payment_gateway' => $gatewayId,
    ]);
    $order->setData($dataKey, [
      'version' => 1,
      'invoice_public_id_sha256' => hash('sha256', (string) $invoice['public_id']),
      'invoice_number' => $invoiceNumber,
      'snapshot_sha256' => (string) $invoice['snapshot_sha256'],
      'request_id' => $requestId,
      'customer_id' => $customerId,
      'organization_id' => $organizationId,
      'method' => 'Zelle',
      'evidence_id' => $evidenceId,
      'evidence_state' => 'owner_attested_received',
      'evidence_source' => 'Fritz Medine explicit confirmation',
      'bank_transaction_reference' => NULL,
      'bank_received_at' => NULL,
      'recorded_at' => $now,
      'recorded_by_uid' => 1,
      'payment_completed_time_means' => 'recorded_at_not_bank_received_at',
      'terms_accepted' => FALSE,
      'staging_acceptance_inferred' => FALSE,
    ]);
    $order->lock();
    $order->setRefreshState(OrderInterface::REFRESH_SKIP);
    $order->save();
    $payment = $entities->getStorage('commerce_payment')->create([
      'type' => 'payment_manual',
      'order_id' => $order->id(),
      'payment_gateway' => $gatewayId,
      'amount' => new Price('100.00', 'USD'),
      'state' => 'new',
      'remote_id' => NULL,
    ]);
    $gateway->getPlugin()->createPayment($payment, TRUE);
    Drupal::service('commerce_payment.order_updater')->updateOrder($order, TRUE);
    $result = $invoices->recordOwnerConfirmedOfflinePayment(
      (string) $invoice['public_id'],
      $customerId,
      $organizationId,
      $requestId,
      (int) $order->id(),
      10000,
      'usd',
      $provider,
      $evidenceId,
      1,
      'Fritz Medine',
      $authority,
    );
    Drupal::service('famtastic_pipeline.customer_portal')->claimResource($organizationId, 'commerce_order', (int) $order->id());
    Drupal::service('famtastic_pipeline.operational_ledger')->recordEvent(
      'invoice-offline-payment:' . $invoiceNumber,
      'commerce.invoice_offline_payment_recorded',
      [
        'invoice_number' => $invoiceNumber,
        'invoice_snapshot_sha256' => (string) $invoice['snapshot_sha256'],
        'order_id' => (int) $order->id(),
        'payment_id' => (int) $payment->id(),
        'amount_minor' => 10000,
        'currency' => 'usd',
        'provider' => $provider,
        'evidence_id' => $evidenceId,
        'evidence_state' => 'owner_attested_received',
        'terms_accepted' => FALSE,
        'staging_acceptance_inferred' => FALSE,
        'email_queued' => FALSE,
        'fulfillment_started' => FALSE,
        'dns_changed' => FALSE,
        'launch_authorized' => FALSE,
      ],
      NULL,
      NULL,
      (int) $order->id(),
    );
  }
  $current = $invoices->invoiceForRequest($customerId, $organizationId, $requestPublicId);
  $verifiedReceipt = $receipt($current);
  $again = $invoices->recordOwnerConfirmedOfflinePayment(
    (string) $current['public_id'],
    $customerId,
    $organizationId,
    $requestId,
    (int) $current['commerce_order_id'],
    10000,
    'usd',
    $provider,
    $evidenceId,
    1,
    'Fritz Medine',
    $authority,
  );
  if ($again['transitioned']) {
    throw new RuntimeException('Offline invoice replay created a second transition');
  }
  if ($mode === 'dry-run') {
    $outer->rollBack();
    $verifiedReceipt['mode'] = 'dry-run';
    $verifiedReceipt['all_mutations_rolled_back'] = TRUE;
  }
  else {
    unset($outer);
    $verifiedReceipt['mode'] = 'apply';
    $verifiedReceipt['all_mutations_rolled_back'] = FALSE;
  }
  echo json_encode($verifiedReceipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
}
catch (Throwable $error) {
  if (isset($outer)) {
    $outer->rollBack();
  }
  throw $error;
}
