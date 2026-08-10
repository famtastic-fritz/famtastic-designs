<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Converts a paid Commerce order into one durable customer lifecycle.
 */
final class CommerceLifecycleService {

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entities,
    private readonly TimeInterface $time,
    private readonly UuidInterface $uuid,
    private readonly CustomerPortalService $portal,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Idempotently fulfills one completed Commerce order.
   */
  public function fulfill(OrderInterface $order): array {
    if ($order->getState()->value !== 'completed') {
      return ['fulfilled' => FALSE, 'reason' => 'order_not_completed'];
    }
    $existing = $this->database->select('famtastic_commerce_fulfillment', 'f')->fields('f')
      ->condition('commerce_order_id', (int) $order->id())->execute()->fetchAssoc();
    if ($existing && $existing['status'] === 'fulfilled') {
      return ['fulfilled' => TRUE, 'existing' => TRUE, 'record' => $existing];
    }
    $user = $order->getCustomer();
    if (!$user || $user->isAnonymous()) {
      throw new \RuntimeException('commerce_customer_account_required');
    }
    $customer = $this->portal->customerForUid((int) $user->id()) ?: $this->portal->createCustomer($user, [
      'name' => $order->getEmail() ?: $user->getDisplayName(),
      'source' => 'commerce',
      'marketing_opt_out' => TRUE,
    ]);
    $organizations = $this->portal->organizations((int) $customer['id']);
    if (!$organizations) throw new \RuntimeException('commerce_customer_organization_missing');
    $organizationId = (int) $organizations[0]['id'];
    $profile = $order->getBillingProfile();
    if ($profile) {
      $this->database->update('famtastic_customer')->fields(['commerce_profile_id' => (int) $profile->id(), 'changed' => $this->time->getRequestTime()])
        ->condition('id', (int) $customer['id'])->execute();
    }

    $definitions = $this->definitions();
    $skus = [];
    $grants = [];
    $intakeSchemas = [];
    foreach ($order->getItems() as $item) {
      $variation = $item->getPurchasedEntity();
      if (!$variation || !method_exists($variation, 'getSku')) continue;
      $sku = (string) $variation->getSku();
      if (!isset($definitions[$sku])) throw new \RuntimeException('commerce_product_definition_missing:' . $sku);
      $skus[] = $sku;
      $definition = $definitions[$sku];
      $intakeSchemas[] = $definition['intake_schema'];
      foreach ($definition['entitlements'] as $type) {
        $grants[$type] = $definition;
      }
    }
    if (!$skus) throw new \RuntimeException('commerce_order_has_no_supported_skus');

    $now = $this->time->getRequestTime();
    $total = $order->getTotalPrice();
    $fields = [
      'commerce_order_id' => (int) $order->id(), 'organization_id' => $organizationId,
      'customer_id' => (int) $customer['id'], 'status' => 'fulfilling',
      'sku_snapshot' => json_encode($this->dealSnapshot($skus, $definitions), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
      'amount_minor' => (int) round((float) $total->getNumber() * 100),
      'currency' => strtolower($total->getCurrencyCode()), 'fulfilled_at' => 0,
      'created' => $existing ? (int) $existing['created'] : $now, 'changed' => $now,
    ];
    $this->database->merge('famtastic_commerce_fulfillment')->key('commerce_order_id', (int) $order->id())->fields($fields)->execute();
    $this->portal->claimResource($organizationId, 'commerce_order', (int) $order->id());

    foreach ($grants as $type => $definition) {
      $exists = $this->database->select('famtastic_entitlement', 'e')->condition('organization_id', $organizationId)
        ->condition('order_id', (int) $order->id())->condition('entitlement_type', $type)->countQuery()->execute()->fetchField();
      if ($exists) continue;
      $billing = $definition['billing'];
      $includedUntil = !empty($billing['included_period_days']) ? $now + ((int) $billing['included_period_days'] * 86400) : NULL;
      $this->database->insert('famtastic_entitlement')->fields([
        'public_id' => $this->uuid->generate(), 'organization_id' => $organizationId,
        'order_id' => (int) $order->id(), 'entitlement_type' => $type, 'status' => 'active', 'starts_at' => $now,
        'included_until' => $includedUntil, 'renews_at' => $includedUntil,
        'amount_minor' => $billing['kind'] === 'recurring' ? (int) round(((float) $definition['price']) * 100) : 0,
        'billing_interval' => $billing['interval'] ?? 'none', 'created' => $now, 'changed' => $now,
      ])->execute();
    }

    $this->portal->activity($organizationId, 'commerce.fulfilled', 'Your purchase is confirmed and your services are ready for intake.');
    $this->queueNotifications($order, $customer, $skus, array_values(array_unique($intakeSchemas)));
    $this->database->update('famtastic_commerce_fulfillment')->fields(['status' => 'fulfilled', 'fulfilled_at' => $now, 'changed' => $now])
      ->condition('commerce_order_id', (int) $order->id())->execute();
    return ['fulfilled' => TRUE, 'existing' => FALSE, 'skus' => $skus, 'entitlements' => array_keys($grants)];
  }

  /** Reconciles refund, void, and failed-payment states into service access. */
  public function reconcilePayment(object $payment): void {
    if (!method_exists($payment, 'getOrder') || !$payment->getOrder()) return;
    $order = $payment->getOrder();
    $state = (string) $payment->getState()->value;
    if ($state === 'completed') {
      $this->fulfill($order);
      return;
    }
    $mapped = in_array($state, ['refunded', 'voided'], TRUE) ? 'cancelled' : (in_array($state, ['failed', 'expired'], TRUE) ? 'payment_attention' : NULL);
    if (!$mapped) return;
    $row = $this->database->select('famtastic_commerce_fulfillment', 'f')->fields('f')->condition('commerce_order_id', (int) $order->id())->execute()->fetchAssoc();
    if (!$row) return;
    $now = $this->time->getRequestTime();
    $this->database->update('famtastic_commerce_fulfillment')->fields(['status' => $mapped, 'changed' => $now])->condition('id', $row['id'])->execute();
    if ($mapped === 'cancelled') {
      $this->database->update('famtastic_entitlement')->fields(['status' => 'suspended', 'changed' => $now])
        ->condition('organization_id', $row['organization_id'])->condition('order_id', (int) $order->id())->execute();
    }
    $customer = $this->database->select('famtastic_customer', 'c')->fields('c')->condition('id', $row['customer_id'])->execute()->fetchAssoc();
    $admin = (string) ($this->configFactory->get('famtastic_pipeline.settings')->get('notification_to_email') ?: 'fritz.medine@gmail.com');
    $this->queue("commerce:{$order->id()}:payment:{$state}", 'transactional', $customer['email'], 'Payment update for your FAMtastic order', "Order {$order->getOrderNumber()} payment status: {$state}. Sign in to review next steps.");
    $this->queue("commerce:{$order->id()}:staff-payment:{$state}", 'operational', $admin, "Commerce payment requires review — {$state}", "Order: {$order->getOrderNumber()}\nCustomer: {$customer['email']}\nState: {$state}");
  }

  private function queueNotifications(OrderInterface $order, array $customer, array $skus, array $intakeSchemas): void {
    $admin = (string) ($this->configFactory->get('famtastic_pipeline.settings')->get('notification_to_email') ?: 'fritz.medine@gmail.com');
    $number = $order->getOrderNumber() ?: (string) $order->id();
    $summary = implode(', ', $skus);
    $this->queue('commerce:' . $order->id() . ':customer-receipt', 'transactional', (string) $customer['email'],
      'Your FAMtastic Designs order is confirmed', "Order {$number} is confirmed.\nProducts: {$summary}\nNext step: sign in to your portal and complete the requested intake.\nIntake: " . implode(', ', $intakeSchemas));
    $this->queue('commerce:' . $order->id() . ':staff-sale', 'operational', $admin,
      'New FAMtastic Commerce sale — order ' . $number, "Customer: {$customer['display_name']}\nEmail: {$customer['email']}\nProducts: {$summary}\nOrder: {$number}");
  }

  public function queue(string $key, string $category, string $recipient, string $subject, string $body): void {
    $now = $this->time->getRequestTime();
    $this->database->merge('famtastic_notification_outbox')->key('notification_key', $key)->insertFields([
      'notification_key' => $key, 'category' => $category, 'recipient' => mb_strtolower($recipient), 'subject' => $subject, 'body' => $body,
      'status' => 'queued', 'attempts' => 0, 'max_attempts' => 5, 'available_at' => $now, 'created' => $now, 'changed' => $now,
    ])->execute();
  }

  private function definitions(): array {
    $path = dirname(\Drupal::root()) . '/config/famtastic-products.json';
    $catalog = json_decode((string) file_get_contents($path), TRUE, 512, JSON_THROW_ON_ERROR);
    $definitions = [];
    foreach ($catalog['products'] ?? [] as $product) $definitions[$product['sku']] = $product;
    return $definitions;
  }

  /** Captures the exact catalog and promise presented for immutable fulfillment evidence. */
  private function dealSnapshot(array $skus, array $definitions): array {
    $path = dirname(\Drupal::root()) . '/config/famtastic-deal-terms.json';
    $registry = json_decode((string) file_get_contents($path), TRUE, 512, JSON_THROW_ON_ERROR);
    $items = [];
    foreach ($skus as $sku) {
      if (empty($registry['deals'][$sku])) throw new \RuntimeException('commerce_deal_definition_missing:' . $sku);
      $items[] = ['sku' => $sku, 'product' => $definitions[$sku], 'deal' => $registry['deals'][$sku]];
    }
    $snapshot = ['policy' => $registry['policy'], 'items' => $items];
    $snapshot['checksum'] = hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    return $snapshot;
  }
}
