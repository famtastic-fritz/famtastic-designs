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
    private readonly OperationalLedger $ledger,
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
    $checkout = (array) ($order->getData('famtastic_checkout') ?? []);
    $organization = $organizations[0];
    if (!empty($checkout['organization_public_id'])) {
      foreach ($organizations as $candidate) {
        if (hash_equals((string) $candidate['public_id'], (string) $checkout['organization_public_id'])) $organization = $candidate;
      }
    }
    $organizationId = (int) $organization['id'];
    if ($existing && $existing['status'] === 'fulfilled') {
      $operations = $this->ensureOperationalRecords($order, $customer, $organizationId, $existing);
      $this->enqueueProjectProofs($order, $operations, (array) ($order->getData('famtastic_checkout') ?? []));
      return ['fulfilled' => TRUE, 'existing' => TRUE, 'record' => $existing, 'operations' => $operations];
    }
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
        if ($type === 'domain_choice') {
          $type = ($checkout['domain_choice'] ?? '') === 'new_domain' ? 'domain_registration' : 'domain_connection';
        }
        $grants[$type] = $definition;
      }
    }
    if (!$skus) throw new \RuntimeException('commerce_order_has_no_supported_skus');

    $now = $this->time->getRequestTime();
    $total = $order->getTotalPrice();
    $fields = [
      'commerce_order_id' => (int) $order->id(), 'organization_id' => $organizationId,
      'customer_id' => (int) $customer['id'], 'status' => 'fulfilling',
      'sku_snapshot' => json_encode($this->dealSnapshot($skus, $definitions, $checkout), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
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
      $renewal = !empty($definition['renewal_sku']) ? ($definitions[$definition['renewal_sku']] ?? NULL) : NULL;
      $includedUntil = !empty($billing['included_period_days']) ? $now + ((int) $billing['included_period_days'] * 86400) : NULL;
      $this->database->insert('famtastic_entitlement')->fields([
        'public_id' => $this->uuid->generate(), 'organization_id' => $organizationId,
        'order_id' => (int) $order->id(), 'entitlement_type' => $type, 'status' => 'active', 'starts_at' => $now,
        'included_until' => $includedUntil, 'renews_at' => $includedUntil,
        'amount_minor' => $renewal ? (int) round(((float) $renewal['price']) * 100) : ($billing['kind'] === 'recurring' ? (int) round(((float) $definition['price']) * 100) : 0),
        'billing_interval' => $renewal['billing']['interval'] ?? $billing['interval'] ?? 'none', 'created' => $now, 'changed' => $now,
      ])->execute();
    }

    $operations = $this->ensureOperationalRecords($order, $customer, $organizationId, $existing ?: []);
    $this->enqueueProjectProofs($order, $operations, $checkout);

    $this->portal->activity($organizationId, 'commerce.fulfilled', 'Your purchase is confirmed and your services are ready for intake.');
    $this->queueNotifications($order, $customer, $skus, array_values(array_unique($intakeSchemas)));
    $this->database->update('famtastic_commerce_fulfillment')->fields(['status' => 'fulfilled', 'fulfilled_at' => $now, 'changed' => $now])
      ->condition('commerce_order_id', (int) $order->id())->execute();
    return ['fulfilled' => TRUE, 'existing' => FALSE, 'skus' => $skus, 'entitlements' => array_keys($grants), 'operations' => $operations];
  }

  /** Creates the staff-GUI intake and project records once for each order. */
  private function ensureOperationalRecords(OrderInterface $order, array $customer, int $organizationId, array $fulfillment): array {
    $prospectStorage = $this->entities->getStorage('famtastic_prospect');
    $prospect = !empty($fulfillment['prospect_id']) ? $prospectStorage->load((int) $fulfillment['prospect_id']) : NULL;
    $checkout = (array) ($order->getData('famtastic_checkout') ?? []);
    $request = NULL;
    if (!empty($checkout['website_request_public_id'])) {
      $request = $this->database->select('famtastic_project_request', 'r')->fields('r')
        ->condition('public_id', (string) $checkout['website_request_public_id'])
        ->condition('organization_id', $organizationId)->condition('commerce_order_id', (int) $order->id())->execute()->fetchAssoc();
      if (!$request) throw new \RuntimeException('commerce_website_request_missing_or_unowned');
      if (!empty($request['prospect_id'])) $prospect = $prospectStorage->load((int) $request['prospect_id']);
    }
    if (!$prospect && !$request) {
      $ids = $prospectStorage->getQuery()->accessCheck(FALSE)
        ->condition('public_email', mb_strtolower((string) $customer['email']))->sort('id', 'DESC')->range(0, 1)->execute();
      $prospect = $ids ? $prospectStorage->load(reset($ids)) : NULL;
    }
    if (!$prospect) {
      $prospect = $prospectStorage->create([
        'business_name' => (string) ($customer['display_name'] ?: $customer['email']),
        'public_email' => mb_strtolower((string) $customer['email']),
        'contact_name' => (string) $customer['display_name'],
        'contact_method' => 'email', 'contact_value' => mb_strtolower((string) $customer['email']),
        'campaign' => 'direct_commerce', 'source' => 'commerce', 'authorized' => TRUE,
        'confirmed_at' => $this->time->getRequestTime(), 'status' => 'paid', 'owner_uid' => 1,
      ]);
      $prospect->save();
    }

    $intakeStorage = $this->entities->getStorage('famtastic_intake');
    $intake = !empty($fulfillment['intake_id']) ? $intakeStorage->load((int) $fulfillment['intake_id']) : NULL;
    if (!$intake) {
      $requestIntake = $request ? (json_decode((string) $request['intake_data'], TRUE) ?: []) : [];
      $intake = $intakeStorage->create([
        'prospect_ref' => $prospect->id(),
        'primary_goal' => $requestIntake['primary_goal'] ?? 'Complete purchased-service onboarding',
        'ideal_customer' => $requestIntake['ideal_customer'] ?? '',
        'services' => ($requestIntake['products_services'] ?? '') . "\n\nPurchased configuration: " . json_encode(['skus' => array_values((array) ($checkout['selected_skus'] ?? [])), 'domain_choice' => $checkout['domain_choice'] ?? ''], JSON_THROW_ON_ERROR),
        'required_sections' => $requestIntake['required_features'] ?? '',
        'style_preferences' => $requestIntake['style_preferences'] ?? '',
        'reference_sites' => $requestIntake['reference_sites'] ?? '',
        'existing_domain' => $request['existing_domain'] ?? '',
      ]);
      $intake->save();
    }

    $projectStorage = $this->entities->getStorage('famtastic_project');
    $project = !empty($fulfillment['project_id']) ? $projectStorage->load((int) $fulfillment['project_id']) : NULL;
    if (!$project) {
      $revisionLimit = (in_array('FAM-BUSINESS-499', (array) ($checkout['selected_skus'] ?? []), TRUE) ? 2 : 1)
        + (in_array('FAM-REVISION-75', (array) ($checkout['selected_skus'] ?? []), TRUE) ? 1 : 0);
      $project = $projectStorage->create([
        'prospect_ref' => $prospect->id(), 'intake_ref' => $intake->id(),
        'delivery_status' => 'intake_pending', 'approval_status' => 'pending', 'revision_limit' => $revisionLimit,
      ]);
      $project->save();
    }
    $this->portal->claimResource($organizationId, 'prospect', (int) $prospect->id());
    $this->portal->claimResource($organizationId, 'project', (int) $project->id());
    $this->database->update('famtastic_commerce_fulfillment')->fields([
      'prospect_id' => (int) $prospect->id(), 'intake_id' => (int) $intake->id(), 'project_id' => (int) $project->id(),
      'changed' => $this->time->getRequestTime(),
    ])->condition('commerce_order_id', (int) $order->id())->execute();
    if ($request) {
      $this->database->update('famtastic_project_request')->fields([
        'status' => 'converted', 'intake_id' => (int) $intake->id(), 'project_id' => (int) $project->id(), 'changed' => $this->time->getRequestTime(),
      ])->condition('id', $request['id'])->execute();
    }
    return ['prospect_id' => (int) $prospect->id(), 'intake_id' => (int) $intake->id(), 'project_id' => (int) $project->id()];
  }

  /** Queues the three-proof studio job once, only after payment and conversion. */
  private function enqueueProjectProofs(OrderInterface $order, array $operations, array $checkout): void {
    $request = [];
    if (!empty($checkout['website_request_public_id'])) {
      $row = $this->database->select('famtastic_project_request', 'r')->fields('r')
        ->condition('public_id', (string) $checkout['website_request_public_id'])->execute()->fetchAssoc();
      if ($row) $request = json_decode((string) $row['intake_data'], TRUE) ?: [];
    }
    $projectId = (int) $operations['project_id'];
    $prospectId = (int) $operations['prospect_id'];
    $this->ledger->enqueue(
      'proof.generate:paid-project:' . $projectId,
      'proof.generate',
      [
        'prospect_id' => $prospectId,
        'project_id' => $projectId,
        'commerce_order_id' => (int) $order->id(),
        'website_request_public_id' => (string) ($checkout['website_request_public_id'] ?? ''),
        'website_discovery_v2' => $request,
      ],
      $prospectId,
    );
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
  private function dealSnapshot(array $skus, array $definitions, array $checkout = []): array {
    $path = dirname(\Drupal::root()) . '/config/famtastic-deal-terms.json';
    $registry = json_decode((string) file_get_contents($path), TRUE, 512, JSON_THROW_ON_ERROR);
    $items = [];
    foreach ($skus as $sku) {
      if (empty($registry['deals'][$sku])) throw new \RuntimeException('commerce_deal_definition_missing:' . $sku);
      $items[] = ['sku' => $sku, 'product' => $definitions[$sku], 'deal' => $registry['deals'][$sku]];
    }
    $snapshot = ['policy' => $registry['policy'], 'items' => $items, 'customer_selection' => array_intersect_key($checkout, array_flip([
      'organization_public_id', 'domain_choice', 'terms_version', 'recurring_authorized', 'marketing_opt_in', 'selected_skus', 'captured_at',
      'website_request_public_id',
    ]))];
    $snapshot['checksum'] = hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    return $snapshot;
  }
}
