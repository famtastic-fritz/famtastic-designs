<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_price\Price;
use Drupal\Core\Session\AccountInterface;

/**
 * Records an owner-confirmed offline prepayment without placing an order.
 *
 * The private offer is the request/order binding until final checkout. Keeping
 * the request's normal commerce_order_id empty preserves pre-purchase revisions.
 * No catalog product, coupon, grant, notification or fulfillment is created.
 */
final class OfflinePrepaymentService {

  public const KEY = 'famtastic_offline_prepayment';
  public const GATEWAY = 'famtastic_manual_record';

  public static function offerId(string $request): string {
    $hash = hash('sha256', 'famtastic.offline-prepayment.v1:' . $request);
    return substr($hash, 0, 8) . '-' . substr($hash, 8, 4) . '-5' . substr($hash, 13, 3) . '-a' . substr($hash, 17, 3) . '-' . substr($hash, 20, 12);
  }

  public static function assertIdentity(array $request, array $customer, int $customerId, string $email): void {
    if ((int) ($request['customer_id'] ?? 0) !== $customerId || (int) ($customer['id'] ?? 0) !== $customerId
      || empty($customer['verified_at']) || !hash_equals(strtolower($email), strtolower((string) ($customer['email'] ?? '')))) {
      throw new \RuntimeException('prepayment_account_mismatch');
    }
  }

  /** Immutable, exact owner-authorized special scope; never a public SKU. */
  public static function stockandshipScope(): array {
    return [
      'version' => 'stockandship98-private-scope-v1',
      'sku' => 'PRIVATE-STOCKANDSHIP98-200',
      'amount' => '200.00', 'currency' => 'USD',
      'title' => 'StockandShip98 — private ecommerce website scope',
      'included' => [
        'Three mobile-ready design directions; selected design preserved into staging',
        'Business-owned WordPress/WooCommerce catalog, product detail, cart and tested checkout',
        'Phone-admin guidance with site-specific screenshots',
        'Contact forms, analytics foundation and supported business-email setup',
        'Research-led gradual transition from seller-owned eBay inventory',
      ],
      'boundaries' => [
        'Merchant authority, inventory, shipping, tax, returns and checkout verification before real payments',
        'Paid subscriptions, unusual third-party costs and expanded scope require separate approval',
        'No recurring billing authorization is inferred',
        'Payment is not design acceptance, site readiness or final-launch approval',
      ],
    ];
  }

  /** Executes under a durable request row lock; repeating returns the same sale. */
  public function record(string $publicId, int $customerId, string $email, array $scope, string $confirmation): array {
    $db = \Drupal::database();
    $transaction = $db->startTransaction();
    try {
      $request = $db->select('famtastic_project_request', 'r')->fields('r')->condition('public_id', $publicId)->forUpdate()->execute()->fetchAssoc();
      $customer = $db->select('famtastic_customer', 'c')->fields('c')->condition('id', $customerId)->execute()->fetchAssoc();
      self::assertIdentity($request ?: [], $customer ?: [], $customerId, $email);
      $membership = $db->select('famtastic_membership', 'm')->condition('customer_id', $customerId)
        ->condition('organization_id', (int) $request['organization_id'])->condition('role', 'owner')->condition('status', 'active')->countQuery()->execute()->fetchField();
      if (!$membership || $confirmation === '' || ($scope['amount'] ?? '') !== '200.00' || ($scope['currency'] ?? '') !== 'USD') {
        throw new \RuntimeException('prepayment_authority_or_amount_invalid');
      }
      $entities = \Drupal::entityTypeManager();
      $user = $entities->getStorage('user')->load((int) $customer['uid']);
      if (!$user || !$user->isActive() || !hash_equals(strtolower($email), strtolower($user->getEmail()))) throw new \RuntimeException('prepayment_user_mismatch');
      $offerId = self::offerId($publicId);
      $existing = $db->select('famtastic_private_offer', 'o')->fields('o')->condition('public_id', $offerId)->execute()->fetchAssoc();
      $scopeHash = hash('sha256', json_encode($scope, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
      if ($existing) {
        $order = $entities->getStorage('commerce_order')->load((int) $existing['commerce_order_id']);
        if (!$order || (int) $existing['customer_id'] !== $customerId || (int) $existing['website_request_id'] !== (int) $request['id']
          || !hash_equals($scopeHash, (string) ($order->getData(self::KEY)['scope_hash'] ?? ''))) throw new \RuntimeException('prepayment_replay_conflict');
        return $this->receipt($order, TRUE);
      }
      if (!empty($request['commerce_order_id']) || $db->select('famtastic_private_offer', 'o')->condition('website_request_id', (int) $request['id'])->countQuery()->execute()->fetchField()) {
        throw new \RuntimeException('prepayment_existing_purchase_requires_reconciliation');
      }
      // Do not create a second order if an incomplete earlier operation left one.
      foreach ($entities->getStorage('commerce_order')->loadByProperties(['uid' => (int) $customer['uid']]) as $candidate) {
        $data = (array) $candidate->getData(self::KEY);
        $checkout = (array) $candidate->getData('famtastic_checkout');
        if (($data['request_public_id'] ?? '') === $publicId || ($checkout['website_request_public_id'] ?? '') === $publicId) throw new \RuntimeException('prepayment_orphan_requires_reconciliation');
      }
      $gateway = $entities->getStorage('commerce_payment_gateway')->load(self::GATEWAY);
      if (!$gateway) {
        $gateway = $entities->getStorage('commerce_payment_gateway')->create([
          'id' => self::GATEWAY, 'label' => 'Owner-confirmed offline receipts (staff only)',
          'plugin' => 'manual', 'status' => FALSE,
          'configuration' => ['display_label' => 'Offline payment already received', 'mode' => 'n/a', 'instructions' => ['value' => '', 'format' => 'plain_text']],
        ]);
        $gateway->save();
      }
      if ($gateway->getPluginId() !== 'manual' || $gateway->status()) throw new \RuntimeException('prepayment_gateway_must_be_disabled_manual');
      $store = $entities->getStorage('commerce_store')->load(1);
      if (!$store || $store->getDefaultCurrencyCode() !== 'USD') throw new \RuntimeException('prepayment_store_mismatch');
      $item = $entities->getStorage('commerce_order_item')->create([
        'type' => 'default', 'title' => $scope['title'], 'quantity' => '1', 'unit_price' => new Price('200.00', 'USD'),
      ]);
      $item->setUnitPrice(new Price('200.00', 'USD'), TRUE);
      $item->save();
      $now = \Drupal::time()->getCurrentTime();
      $order = $entities->getStorage('commerce_order')->create([
        'type' => 'default', 'store_id' => $store->id(), 'uid' => (int) $customer['uid'],
        'mail' => $email, 'order_items' => [$item], 'state' => 'draft', 'cart' => FALSE,
        'payment_gateway' => self::GATEWAY,
      ]);
      $order->setData(self::KEY, [
        'version' => 1, 'request_id' => (int) $request['id'], 'request_public_id' => $publicId,
        'customer_id' => $customerId, 'organization_id' => (int) $request['organization_id'],
        'offer_public_id' => $offerId, 'scope' => $scope, 'scope_hash' => $scopeHash,
        'method' => 'Zelle', 'evidence_source' => 'Fritz Medine explicit confirmation', 'confirmation' => $confirmation,
        'bank_transaction_reference' => NULL, 'received_at' => NULL, 'recorded_at' => $now,
        'recorded_by' => 'codex:request17-commerce', 'payment_completed_time_means' => 'recorded_at_not_bank_received_at',
        'hold' => 'awaiting_customer_terms_domain_and_final_acceptance', 'client_acceptance' => NULL,
        'completion' => ['state' => 'not_issued'],
      ]);
      $order->lock();
      $order->setRefreshState(OrderInterface::REFRESH_SKIP);
      $order->save();
      $payment = $entities->getStorage('commerce_payment')->create([
        'type' => 'payment_manual', 'order_id' => $order->id(), 'payment_gateway' => self::GATEWAY,
        'amount' => new Price('200.00', 'USD'), 'state' => 'new', 'remote_id' => NULL,
      ]);
      // Native manual gateway receipt; no external charge or invented reference.
      $gateway->getPlugin()->createPayment($payment, TRUE);
      \Drupal::service('commerce_payment.order_updater')->updateOrder($order, TRUE);
      $db->insert('famtastic_private_offer')->fields([
        'public_id' => $offerId, 'website_request_id' => (int) $request['id'],
        'organization_id' => (int) $request['organization_id'], 'customer_id' => $customerId,
        'sku' => $scope['sku'], 'list_amount_minor' => 20000, 'offered_amount_minor' => 20000, 'currency' => 'usd',
        'reason' => 'Owner-authorized flat-rate ecommerce scope. $200 Zelle received per Fritz confirmation; terms/domain and client acceptance pending.',
        'status' => 'prepaid_held', 'expires_at' => NULL, 'created_by_uid' => 0,
        'commerce_order_id' => (int) $order->id(), 'accepted_at' => NULL, 'created' => $now, 'changed' => $now,
      ])->execute();
      \Drupal::service('famtastic_pipeline.customer_portal')->claimResource((int) $request['organization_id'], 'commerce_order', (int) $order->id());
      $receipt = $this->receipt($order, FALSE);
      \Drupal::service('famtastic_pipeline.operational_ledger')->recordEvent('offline-prepayment:' . $publicId, 'commerce.offline_prepayment_recorded', $receipt + [
        'evidence_source' => 'Fritz Medine explicit confirmation', 'automation_identity' => 'codex:request17-commerce',
        'received_at' => NULL, 'bank_transaction_reference' => NULL, 'scope_hash' => $scopeHash,
      ], (int) $request['prospect_id'] ?: NULL, NULL, (int) $order->id());
      return $receipt;
    }
    catch (\Throwable $error) {
      $transaction->rollBack();
      throw $error;
    }
  }

  public function receipt(OrderInterface $order, bool $existing = TRUE): array {
    $data = (array) $order->getData(self::KEY);
    $payments = \Drupal::entityTypeManager()->getStorage('commerce_payment')->loadMultipleByOrder($order);
    if (count($payments) !== 1) throw new \RuntimeException('prepayment_payment_count_mismatch');
    $payment = reset($payments);
    if (!$payment->isCompleted() || $payment->getPaymentGatewayId() !== self::GATEWAY || !$payment->getAmount()->equals(new Price('200.00', 'USD'))
      || !$payment->getRefundedAmount()->isZero() || !$order->getTotalPrice()->equals(new Price('200.00', 'USD'))
      || !$order->getBalance()->isZero() || (int) $order->getCustomerId() !== (int) (\Drupal::database()->select('famtastic_customer', 'c')->fields('c', ['uid'])->condition('id', (int) $data['customer_id'])->execute()->fetchField())) {
      throw new \RuntimeException('prepayment_financial_reconciliation_required');
    }
    if ($order->getState()->value !== 'draft' || $order->getPlacedTime()) throw new \RuntimeException('prepayment_order_unexpectedly_placed');
    $db = \Drupal::database();
    if ($db->select('famtastic_commerce_fulfillment', 'f')->condition('commerce_order_id', (int) $order->id())->countQuery()->execute()->fetchField()
      || $db->select('famtastic_notification_outbox', 'n')->condition('notification_key', 'commerce:' . $order->id() . ':%', 'LIKE')->countQuery()->execute()->fetchField()) {
      throw new \RuntimeException('prepayment_unexpected_side_effect');
    }
    return [
      'existing' => $existing, 'request_id' => (int) $data['request_id'], 'customer_id' => (int) $data['customer_id'],
      'order_id' => (int) $order->id(), 'order_number' => $order->getOrderNumber(), 'order_state' => 'draft',
      'offer_public_id' => $data['offer_public_id'], 'payment_id' => (int) $payment->id(),
      'payment_state' => $payment->getState()->value, 'received' => '200.00', 'outstanding' => '0.00', 'currency' => 'USD',
      'recorded_at' => $data['recorded_at'], 'received_at' => NULL, 'bank_transaction_reference' => NULL,
      'evidence_source' => $data['evidence_source'], 'hold' => $data['hold'],
      'email_queued' => FALSE, 'fulfillment_started' => FALSE, 'launch_authorized' => FALSE,
    ];
  }

  /**
   * Narrow optional bridge for an older caller that already bound order_id.
   *
   * The recorder deliberately leaves that field empty. If a caller later binds
   * it, this permits only the exact owner-approved StockandShip prepayment, not
   * arbitrary paid orders. Selection/QA/artifact/acceptance checks still apply.
   */
  public function permitsSelectedStaging(array $request): bool {
    try {
      $offer = \Drupal::database()->select('famtastic_private_offer', 'o')->fields('o')
        ->condition('public_id', self::offerId((string) ($request['public_id'] ?? '')))->execute()->fetchAssoc();
      if (!$offer) return FALSE;
      $order = \Drupal::entityTypeManager()->getStorage('commerce_order')->load((int) $offer['commerce_order_id']);
      if (!$order) return FALSE;
      return self::matchesSelectedStagingEvidence($request, $offer, (array) $order->getData(self::KEY), $this->receipt($order));
    }
    catch (\Throwable) { return FALSE; }
  }

  public static function matchesSelectedStagingEvidence(array $request, array $offer, array $data, array $receipt): bool {
    $scopeHash = hash('sha256', json_encode(self::stockandshipScope(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    return (int) ($request['id'] ?? 0) === 17
      && ($request['public_id'] ?? '') === '4940a4fd-91af-40c4-b8a5-2b4dad1a3b95'
      && (int) ($request['customer_id'] ?? 0) === 15 && (int) ($request['organization_id'] ?? 0) === 15
      && ($request['status'] ?? '') === 'submitted'
      && (int) ($offer['website_request_id'] ?? 0) === 17 && (int) ($offer['customer_id'] ?? 0) === 15
      && (int) ($offer['organization_id'] ?? 0) === 15 && ($offer['status'] ?? '') === 'prepaid_held'
      && ($offer['sku'] ?? '') === 'PRIVATE-STOCKANDSHIP98-200' && (int) ($offer['offered_amount_minor'] ?? 0) === 20000
      && ($offer['currency'] ?? '') === 'usd'
      && (int) ($receipt['order_id'] ?? 0) > 0 && (int) ($offer['commerce_order_id'] ?? 0) === (int) ($receipt['order_id'] ?? 0)
      && (empty($request['commerce_order_id']) || (int) $request['commerce_order_id'] === (int) $receipt['order_id'])
      && (int) ($data['request_id'] ?? 0) === 17 && ($data['request_public_id'] ?? '') === $request['public_id']
      && (int) ($data['customer_id'] ?? 0) === 15 && (int) ($data['organization_id'] ?? 0) === 15
      && ($data['scope']['version'] ?? '') === 'stockandship98-private-scope-v1'
      && hash_equals($scopeHash, (string) ($data['scope_hash'] ?? ''))
      && hash_equals($scopeHash, hash('sha256', json_encode($data['scope'] ?? [], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)))
      && ($data['hold'] ?? '') === 'awaiting_customer_terms_domain_and_final_acceptance'
      && array_key_exists('client_acceptance', $data) && $data['client_acceptance'] === NULL
      && ($data['evidence_source'] ?? '') === 'Fritz Medine explicit confirmation'
      && ($receipt['payment_state'] ?? '') === 'completed' && ($receipt['received'] ?? '') === '200.00'
      && ($receipt['outstanding'] ?? '') === '0.00' && ($receipt['currency'] ?? '') === 'USD'
      && ($receipt['order_state'] ?? '') === 'draft' && ($receipt['launch_authorized'] ?? TRUE) === FALSE;
  }

  /** A private, expiring capability. Return once; never log or email here. */
  public function issueCompletionCode(int $orderId, AccountInterface $staff): string {
    if (!$staff->isAuthenticated() || !$staff->hasPermission('administer famtastic pipeline')) throw new \RuntimeException('staff_required');
    $db = \Drupal::database();
    $transaction = $db->startTransaction();
    try {
      $order = $this->lockedOrder($orderId);
      $this->receipt($order);
      $data = (array) $order->getData(self::KEY);
      if (($data['completion']['state'] ?? '') !== 'not_issued') throw new \RuntimeException('completion_code_already_issued');
      $code = bin2hex(random_bytes(24));
      $data['completion'] = ['state' => 'issued', 'hash' => hash('sha256', $code), 'expires_at' => time() + 7 * 86400, 'issued_by_uid' => (int) $staff->id()];
      $order->setData(self::KEY, $data)->save();
      return $code;
    }
    catch (\Throwable $error) { $transaction->rollBack(); throw $error; }
  }

  /** Authenticated code use captures terms/domain, never places another sale. */
  public function complete(int $orderId, AccountInterface $account, string $code, array $input): array {
    $db = \Drupal::database();
    $transaction = $db->startTransaction();
    try {
      $order = $this->lockedOrder($orderId);
      $data = (array) $order->getData(self::KEY);
      $customer = $db->select('famtastic_customer', 'c')->fields('c')->condition('uid', (int) $account->id())->execute()->fetchAssoc();
      if (!$account->isAuthenticated() || !$customer || empty($customer['verified_at']) || (int) $customer['id'] !== (int) ($data['customer_id'] ?? 0)
        || (int) $order->getCustomerId() !== (int) $account->id()) throw new \RuntimeException('completion_account_mismatch');
      $request = \Drupal::service('famtastic_pipeline.customer_portal')->ownedWebsiteRequest((int) $customer['id'], (string) $data['request_public_id']);
      if (!$request || (int) $request['organization_id'] !== (int) $data['organization_id']) throw new \RuntimeException('completion_account_mismatch');
      self::assertCompletion($data, $code, $input, time());
      $this->receipt($order);
      $data['completion'] = [
        'state' => 'consumed', 'consumed_at' => time(), 'consumed_by_uid' => (int) $account->id(),
        'terms_version' => $data['scope']['version'], 'scope_hash' => $data['scope_hash'],
        'domain_choice' => $input['domain_choice'], 'domain' => trim((string) ($input['domain'] ?? '')),
        'recurring_authorized' => FALSE,
      ];
      $data['hold'] = 'awaiting_exact_staging_acceptance_and_launch_readiness';
      $order->setData(self::KEY, $data)->save();
      \Drupal::service('famtastic_pipeline.operational_ledger')->recordEvent('offline-prepayment:' . $orderId . ':terms-domain', 'commerce.prepaid_details_confirmed', [
        'order_id' => $orderId, 'request_id' => $data['request_id'], 'actor_uid' => (int) $account->id(), 'scope_hash' => $data['scope_hash'],
        'payment_created' => FALSE, 'launch_authorized' => FALSE,
      ], NULL, NULL, $orderId);
      return $this->receipt($order) + ['details_confirmed' => TRUE];
    }
    catch (\Throwable $error) { $transaction->rollBack(); throw $error; }
  }

  public static function assertCompletion(array $data, string $code, array $input, int $now): void {
    $completion = $data['completion'] ?? [];
    if (($completion['state'] ?? '') !== 'issued' || ($completion['expires_at'] ?? 0) <= $now
      || !preg_match('/^[a-f0-9]{48}$/D', $code) || !hash_equals((string) ($completion['hash'] ?? ''), hash('sha256', $code))) throw new \RuntimeException('completion_code_invalid_or_used');
    if (($input['accept_terms'] ?? FALSE) !== TRUE || ($input['terms_version'] ?? '') !== ($data['scope']['version'] ?? '')
      || ($input['scope_hash'] ?? '') !== ($data['scope_hash'] ?? '')) throw new \RuntimeException('completion_current_terms_required');
    if (!in_array($input['domain_choice'] ?? '', ['new_domain', 'existing_domain', 'undecided'], TRUE)) throw new \RuntimeException('completion_domain_invalid');
    $domain = trim((string) ($input['domain'] ?? ''));
    if ($domain !== '' && (!filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) || !str_contains($domain, '.') || strlen($domain) > 253)) throw new \RuntimeException('completion_domain_invalid');
  }

  private function lockedOrder(int $id): OrderInterface {
    $db = \Drupal::database();
    if (!$db->select('commerce_order', 'o')->fields('o', ['order_id'])->condition('order_id', $id)->forUpdate()->execute()->fetchField()) throw new \RuntimeException('completion_not_found');
    $storage = \Drupal::entityTypeManager()->getStorage('commerce_order');
    $storage->resetCache([$id]);
    $order = $storage->load($id);
    if (!$order || !$order->getData(self::KEY)) throw new \RuntimeException('completion_not_found');
    return $order;
  }

  /** Creates only the account-bound empty conversation, not a pretend message. */
  public function ensureProjectConversation(string $requestPublicId, int $customerId, string $email): array {
    $db = \Drupal::database();
    $transaction = $db->startTransaction();
    try {
      $request = $db->select('famtastic_project_request', 'r')->fields('r')->condition('public_id', $requestPublicId)->forUpdate()->execute()->fetchAssoc();
      $customer = $db->select('famtastic_customer', 'c')->fields('c')->condition('id', $customerId)->execute()->fetchAssoc();
      self::assertIdentity($request ?: [], $customer ?: [], $customerId, $email);
      $owner = \Drupal::entityTypeManager()->getStorage('user')->load(1);
      if (!$owner || !$owner->isActive() || !$owner->hasPermission('administer famtastic pipeline')) throw new \RuntimeException('project_owner_not_staff');
      $key = 'website-request:' . $request['id'] . ':direct-project';
      $existing = $db->select('famtastic_portal_thread', 't')->fields('t')->condition('source_key', $key)->execute()->fetchAssoc();
      if (!$existing) {
        // Refuse to split an existing exact project/prospect conversation.
        if (!empty($request['prospect_id'])) {
          $existing = $db->select('famtastic_portal_thread', 't')->fields('t')->condition('organization_id', (int) $request['organization_id'])
            ->condition('prospect_id', (int) $request['prospect_id'])->orderBy('id')->range(0, 1)->execute()->fetchAssoc();
        }
      }
      if ($existing && (int) $existing['organization_id'] !== (int) $request['organization_id']) throw new \RuntimeException('project_conversation_binding_conflict');
      $now = time();
      if (!$existing) {
        $publicId = \Drupal::service('uuid')->generate();
        $threadId = (int) $db->insert('famtastic_portal_thread')->fields([
          'public_id' => $publicId, 'organization_id' => (int) $request['organization_id'],
          'project_id' => $request['project_id'] ?: NULL, 'prospect_id' => $request['prospect_id'] ?: NULL,
          'source_key' => $key, 'kind' => 'project', 'subject' => $request['project_name'] . ' — direct project conversation',
          'status' => 'open', 'created_by' => 0, 'contact_email' => $email, 'created' => $now, 'changed' => $now,
        ])->execute();
      }
      else { $threadId = (int) $existing['id']; $publicId = $existing['public_id']; }
      $case = $db->select('famtastic_support_case', 's')->fields('s')->condition('thread_id', $threadId)->execute()->fetchAssoc();
      if ($case && (int) $case['owner_uid'] !== 1) throw new \RuntimeException('project_conversation_owner_conflict');
      if (!$case) $db->insert('famtastic_support_case')->fields([
        'case_number' => 'FAM-' . gmdate('ymd') . '-' . str_pad((string) $threadId, 5, '0', STR_PAD_LEFT),
        'thread_id' => $threadId, 'category' => 'website_project', 'priority' => 'normal', 'status' => 'waiting_on_customer',
        'owner_uid' => 1, 'service_key' => 'website-request-' . $request['id'], 'response_due' => 0, 'created' => $now, 'changed' => $now,
      ])->execute();
      \Drupal::service('famtastic_pipeline.operational_ledger')->recordEvent($key, 'project.direct_conversation_ready', [
        'request_id' => (int) $request['id'], 'thread_id' => $threadId, 'owner_uid' => 1,
        'automation_identity' => 'codex:request17-commerce', 'messages_created' => 0, 'notifications_created' => 0,
      ], (int) $request['prospect_id'] ?: NULL);
      return ['public_id' => $publicId, 'thread_id' => $threadId, 'owner_uid' => 1,
        'url' => 'https://famtasticdesigns.com/portal?section=messages&thread=' . $publicId,
        'admin_url' => 'https://famtasticdesigns.com/web/admin/famtastic/messages/' . $publicId];
    }
    catch (\Throwable $error) { $transaction->rollBack(); throw $error; }
  }
}
