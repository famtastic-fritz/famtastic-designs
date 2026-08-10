<?php

declare(strict_types=1);

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\user\Entity\User;

$runId = (string) (getenv('FAMTASTIC_SYNTHETIC_RUN_ID') ?: time());
$email = "lifecycle-{$runId}@example.test";
$now = \Drupal::time()->getRequestTime();
$user = User::create(['name' => $email, 'mail' => $email, 'status' => 1]);
$user->setPassword('Synthetic-Lifecycle-Pass!');
$user->save();
$portal = \Drupal::service('famtastic_pipeline.customer_portal');
$customer = $portal->createCustomer($user, ['name' => 'Lifecycle Customer', 'business_name' => 'Lifecycle Test ' . $runId, 'source' => 'synthetic']);
$portal->markVerified((int) $customer['id']);
$organization = $portal->organizations((int) $customer['id'])[0];

$variationIds = \Drupal::entityQuery('commerce_product_variation')->accessCheck(FALSE)->condition('sku', 'FAM-FOOT-199')->execute();
$variation = ProductVariation::load(reset($variationIds));
if (!$variation) throw new RuntimeException('FAM-FOOT-199 missing');
$storeIds = \Drupal::entityQuery('commerce_store')->accessCheck(FALSE)->range(0, 1)->execute();
$item = OrderItem::create(['type' => 'default', 'purchased_entity' => $variation, 'quantity' => 1, 'unit_price' => $variation->getPrice(), 'title' => $variation->getTitle()]);
$item->save();
$order = Order::create(['type' => 'default', 'store_id' => reset($storeIds), 'uid' => $user->id(), 'mail' => $email, 'order_items' => [$item], 'state' => 'draft']);
$order->save();
$order->set('state', 'completed');
$order->setPlacedTime($now);
$order->save();
$commerce = \Drupal::service('famtastic_pipeline.commerce_lifecycle')->fulfill($order);
$paymentState = static fn(string $state) => new class($order, $state) {
  public function __construct(private object $order, private string $state) {}
  public function getOrder(): object { return $this->order; }
  public function getState(): object { return (object) ['value' => $this->state]; }
};
\Drupal::service('famtastic_pipeline.commerce_lifecycle')->reconcilePayment($paymentState('failed'));
$paymentAttention = \Drupal::database()->select('famtastic_commerce_fulfillment', 'f')->fields('f', ['status'])->condition('commerce_order_id', $order->id())->execute()->fetchField();
\Drupal::service('famtastic_pipeline.commerce_lifecycle')->reconcilePayment($paymentState('refunded'));

$thread = $portal->createThread((int) $customer['id'], $organization['public_id'], [
  'subject' => 'Synthetic mobile support case', 'body' => 'Please test the complete support lifecycle.',
  'category' => 'website', 'priority' => 'high', 'service_key' => 'website_service',
], (int) $user->id());
$operations = \Drupal::service('famtastic_pipeline.lifecycle_operations');
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', TRUE);
$inbound = $operations->ingestInbound([
  'message_id' => '<lifecycle-' . $runId . '@example.test>', 'from' => $email,
  'to' => 'support+' . $thread['public_id'] . '@famtasticdesigns.com', 'subject' => 'Re: ' . $thread['case_number'],
  'body' => 'Customer reply captured from the controlled mailbox fixture.',
  'attachments' => [['name' => 'screenshot.png', 'mime' => 'image/png', 'size' => strlen($png), 'sha256' => hash('sha256', $png), 'content_base64' => base64_encode($png)]],
]);
$duplicate = $operations->ingestInbound([
  'message_id' => '<lifecycle-' . $runId . '@example.test>', 'from' => $email,
  'to' => 'support+' . $thread['public_id'] . '@famtasticdesigns.com', 'subject' => 'duplicate', 'body' => 'duplicate',
]);
$rejected = FALSE;
try {
  $operations->ingestInbound([
    'message_id' => '<unsafe-' . $runId . '@example.test>', 'from' => $email,
    'to' => 'support+' . $thread['public_id'] . '@famtasticdesigns.com', 'subject' => 'unsafe', 'body' => 'unsafe',
    'attachments' => [['name' => 'payload.exe', 'mime' => 'application/x-msdownload', 'size' => 10]],
  ]);
}
catch (InvalidArgumentException) { $rejected = TRUE; }
$staffReply = $operations->staffReply($thread['case_number'], 'We received your update and are working on it.');
$delivery = $operations->dispatchNotifications(25);
$protection = $operations->runProtection();

$db = \Drupal::database();
$fulfillment = $db->select('famtastic_commerce_fulfillment', 'f')->fields('f')->condition('commerce_order_id', $order->id())->execute()->fetchAssoc();
$entitlements = (int) $db->select('famtastic_entitlement', 'e')->condition('organization_id', $organization['id'])->condition('order_id', $order->id())->countQuery()->execute()->fetchField();
$suspended = (int) $db->select('famtastic_entitlement', 'e')->condition('organization_id', $organization['id'])->condition('order_id', $order->id())->condition('status', 'suspended')->countQuery()->execute()->fetchField();
$messages = (int) $db->select('famtastic_portal_message', 'm')->condition('thread_id', $db->select('famtastic_portal_thread', 't')->fields('t', ['id'])->condition('public_id', $thread['public_id']))->countQuery()->execute()->fetchField();
$checks = [
  'commerce_completed' => $order->getState()->value === 'completed',
  'commerce_fulfilled_once' => $commerce['fulfilled'] && (int) ($fulfillment['fulfilled_at'] ?? 0) > 0,
  'sku_entitlements' => $entitlements === 3,
  'failed_payment_attention' => $paymentAttention === 'payment_attention',
  'refund_suspends_entitlements' => $suspended === 3,
  'customer_and_staff_notifications' => $delivery['sent'] >= 4,
  'support_case_number' => str_starts_with($thread['case_number'], 'FAM-'),
  'inbound_reply_matched' => $inbound['accepted'] === TRUE,
  'inbound_deduplicated' => $duplicate['duplicate'] === TRUE,
  'unsafe_attachment_rejected' => $rejected,
  'staff_reply_recorded' => $staffReply['status'] === 'waiting_on_customer',
  'single_timeline' => $messages === 3,
  'worker_heartbeat' => $protection['failed'] === 0,
];
if (in_array(FALSE, $checks, TRUE)) throw new RuntimeException('Lifecycle acceptance failed: ' . json_encode($checks));
$evidenceDir = (string) getenv('FAMTASTIC_LIFECYCLE_EVIDENCE_DIR');
if ($evidenceDir === '' || (!is_dir($evidenceDir) && !mkdir($evidenceDir, 0770, TRUE))) throw new RuntimeException('Evidence directory unavailable');
$evidence = ['schema' => 'famtastic.lifecycle-proof.v1', 'status' => 'passed', 'run_id' => $runId, 'checks' => $checks,
  'records' => ['commerce_order_id' => (int) $order->id(), 'organization_public_id' => $organization['public_id'], 'case_number' => $thread['case_number']],
  'notifications' => $delivery, 'generated_at' => gmdate(DATE_ATOM)];
file_put_contents($evidenceDir . '/evidence.json', json_encode($evidence, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo "PASS: Commerce fulfillment, notification outbox, support case, mailbox reply, attachment rejection, and worker heartbeat verified.\n";
echo 'Evidence: ' . $evidenceDir . "/evidence.json\n";
