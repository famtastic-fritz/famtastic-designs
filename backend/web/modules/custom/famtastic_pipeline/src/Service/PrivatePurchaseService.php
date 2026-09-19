<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_price\Price;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Site\Settings;

/** Account-owned private scopes, not an alternate payment processor/catalog. */
final class PrivatePurchaseService {
  public const KEY = 'famtastic_private_scope_checkout';
  public const REUNION = '8000fc68-aae3-4f40-a3de-2251bd09076a';
  public const STOCK = '4940a4fd-91af-40c4-b8a5-2b4dad1a3b95';
  public const REUNION_HASH = '478a57b7673dc087f657bd273cc48b428ab5497e1a454f8161685ddf472db408';

  public static function url(string $request): string {
    return '/web/customer/private-purchase/' . rawurlencode($request);
  }

  /** Read-only; never issue a code, reserve an order or initialize Stripe on GET. */
  public function context(AccountInterface $account, string $publicId, bool $lock = FALSE): array {
    if (!$account->isAuthenticated() || !in_array($publicId, [self::REUNION, self::STOCK], TRUE)) throw new \RuntimeException('private_purchase_not_found');
    $db = \Drupal::database();
    $query = $db->select('famtastic_project_request', 'r')->fields('r')->condition('public_id', $publicId);
    if ($lock) $query->forUpdate();
    $request = $query->execute()->fetchAssoc();
    $customer = $db->select('famtastic_customer', 'c')->fields('c')->condition('uid', (int) $account->id())->execute()->fetchAssoc();
    $expectedCustomer = $publicId === self::STOCK ? 15 : 14;
    $email = $publicId === self::STOCK ? 'sprospere@yahoo.com' : 'mbshclassof2000@gmail.com';
    OfflinePrepaymentService::assertIdentity($request ?: [], $customer ?: [], $expectedCustomer, $email);
    if ((int) $request['organization_id'] !== $expectedCustomer
      || !hash_equals(strtolower($email), strtolower($account->getEmail()))
      || !$db->select('famtastic_membership', 'm')->condition('customer_id', $expectedCustomer)->condition('organization_id', $expectedCustomer)
        ->condition('status', 'active')->condition('role', 'owner')->countQuery()->execute()->fetchField()) throw new \RuntimeException('private_purchase_not_found');
    $offerId = OfflinePrepaymentService::offerId(($publicId === self::REUNION ? 'reunion-private-scope:' : '') . $publicId);
    $offer = $db->select('famtastic_private_offer', 'o')->fields('o')->condition('public_id', $offerId)->execute()->fetchAssoc();
    if (!$offer || (int) $offer['website_request_id'] !== (int) $request['id'] || (int) $offer['customer_id'] !== $expectedCustomer
      || (int) $offer['organization_id'] !== $expectedCustomer) throw new \RuntimeException('private_purchase_not_found');
    // Native load() can refresh/save a draft order. loadUnchanged() explicitly
    // skips Commerce refresh, keeping this GET/read path free of those effects.
    $order = empty($offer['commerce_order_id']) ? NULL : \Drupal::entityTypeManager()->getStorage('commerce_order')->loadUnchanged((int) $offer['commerce_order_id']);
    if (!empty($offer['commerce_order_id']) && (!$order || (int) $order->getCustomerId() !== (int) $account->id())) throw new \RuntimeException('private_purchase_order_mismatch');
    if ($publicId === self::STOCK) {
      if (!$order || $offer['status'] !== 'prepaid_held') throw new \RuntimeException('private_purchase_not_found');
      $data = (array) $order->getData(OfflinePrepaymentService::KEY);
      if (($data['request_public_id'] ?? '') !== $publicId || (int) ($data['customer_id'] ?? 0) !== 15) throw new \RuntimeException('private_purchase_order_mismatch');
      $receipt = (new OfflinePrepaymentService())->receipt($order);
      return compact('request', 'customer', 'offer', 'order', 'data', 'receipt') + ['kind' => 'prepaid', 'scope' => $data['scope'], 'scope_hash' => $data['scope_hash']];
    }
    $event = $db->select('famtastic_event', 'e')->fields('e', ['payload'])->condition('event_key', 'private-scope:request:16:class-of-2000-v1')->execute()->fetchField();
    $evidence = json_decode((string) $event, TRUE, 512, JSON_THROW_ON_ERROR);
    self::assertReunionScope($request, $offer, $evidence ?: []);
    $scope = $evidence['scope'];
    return compact('request', 'customer', 'offer', 'order', 'scope') + ['kind' => 'reunion', 'scope_hash' => self::REUNION_HASH];
  }

  public static function assertReunionScope(array $request, array $offer, array $event): void {
    if ((int) ($request['id'] ?? 0) !== 16 || ($request['public_id'] ?? '') !== self::REUNION
      || (int) ($request['customer_id'] ?? 0) !== 14 || (int) ($request['organization_id'] ?? 0) !== 14
      || (int) ($offer['website_request_id'] ?? 0) !== 16 || (int) ($offer['customer_id'] ?? 0) !== 14 || (int) ($offer['organization_id'] ?? 0) !== 14
      || ($offer['sku'] ?? '') !== 'PRIVATE-REUNION16-199' || (int) ($offer['offered_amount_minor'] ?? 0) !== 19900 || ($offer['currency'] ?? '') !== 'usd'
      || !in_array($offer['status'] ?? '', ['active', 'checkout_started'], TRUE)
      || (!empty($offer['expires_at']) && (int) $offer['expires_at'] <= time())
      || ($event['authority'] ?? '') !== 'Fritz Medine explicit approval' || ($event['scope_hash'] ?? '') !== self::REUNION_HASH
      || (int) ($event['request_id'] ?? 0) !== 16 || (int) ($event['customer_id'] ?? 0) !== 14 || (int) ($event['organization_id'] ?? 0) !== 14
      || ($event['offer_public_id'] ?? '') !== ($offer['public_id'] ?? '')
      || !hash_equals(self::REUNION_HASH, hash('sha256', json_encode($event['scope'] ?? [], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)))) {
      throw new \RuntimeException('private_scope_changed');
    }
  }

  public static function selection(array $request): array {
    if (($request['status'] ?? '') !== 'submitted' || ($request['proof_review_status'] ?? '') !== 'selected'
      || !in_array($request['selected_proof_direction'] ?? '', ['a', 'b', 'c'], TRUE) || empty($request['proof_campaign_id'])) {
      throw new \RuntimeException('private_scope_selection_required');
    }
    return ['campaign_id' => (int) $request['proof_campaign_id'], 'direction' => $request['selected_proof_direction']];
  }

  public static function assertDetails(array $input, string $version, string $hash): void {
    if (($input['accept_terms'] ?? FALSE) !== TRUE || ($input['terms_version'] ?? '') !== $version || ($input['scope_hash'] ?? '') !== $hash
      || !empty($input['recurring_authorized'])) throw new \RuntimeException('private_scope_acknowledgment_required');
    if (!in_array($input['domain_choice'] ?? '', ['new_domain', 'existing_domain', 'undecided'], TRUE)) throw new \RuntimeException('private_scope_domain_invalid');
    $domain = trim((string) ($input['domain'] ?? ''));
    if ($domain !== '' && (strlen($domain) > 253 || !str_contains($domain, '.') || !filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME))) throw new \RuntimeException('private_scope_domain_invalid');
  }

  public static function checkoutEnabled(): bool {
    // Release gate, not a per-customer Fritz approval. Enable only after native
    // checkout/webhook tests, including failed/refunded states, are verified.
    return Settings::get('famtastic_payment_mode') !== 'disabled' && Settings::get('famtastic_private_reunion_checkout_enabled', FALSE) === TRUE;
  }

  /** POST only by the verified customer. Creates no payment and calls no gateway. */
  public function startReunion(AccountInterface $account, string $publicId, array $input): OrderInterface {
    if ($publicId !== self::REUNION || !self::checkoutEnabled()) throw new \RuntimeException('private_checkout_unavailable');
    $db = \Drupal::database();
    $transaction = $db->startTransaction();
    try {
      $context = $this->context($account, $publicId, TRUE);
      self::assertDetails($input, $context['scope']['version'], $context['scope_hash']);
      $selection = self::selection($context['request']);
      if ($context['order']) {
        $this->assertReunionOrder($context['order'], $context);
        return $context['order'];
      }
      if (!empty($context['request']['commerce_order_id'])) throw new \RuntimeException('private_existing_purchase_reconcile');
      $entities = \Drupal::entityTypeManager();
      $orderStorage = $entities->getStorage('commerce_order');
      $candidateIds = $orderStorage->getQuery()->accessCheck(FALSE)->condition('uid', (int) $account->id())->execute();
      foreach ($candidateIds as $candidateId) {
        $candidate = $orderStorage->loadUnchanged($candidateId);
        if (($candidate->getData(self::KEY)['request_public_id'] ?? '') === $publicId
          || ($candidate->getData('famtastic_checkout')['website_request_public_id'] ?? '') === $publicId) throw new \RuntimeException('private_existing_purchase_reconcile');
      }
      $gateways = array_filter($entities->getStorage('commerce_payment_gateway')->loadByProperties(['status' => TRUE]), static fn($g): bool => in_array($g->getPluginId(), ['stripe', 'stripe_payment_element'], TRUE));
      if (!$gateways) throw new \RuntimeException('private_checkout_unavailable');
      $store = $entities->getStorage('commerce_store')->load(1);
      if (!$store || !$store->isPublished() || $store->getDefaultCurrencyCode() !== 'USD') throw new \RuntimeException('private_checkout_unavailable');
      $item = $entities->getStorage('commerce_order_item')->create(['type' => 'default', 'title' => $context['scope']['title'], 'quantity' => '1']);
      $item->setUnitPrice(new Price('199.00', 'USD'), TRUE);
      $item->save();
      $order = $entities->getStorage('commerce_order')->create(['type' => 'default', 'store_id' => 1, 'uid' => (int) $account->id(),
        'mail' => $context['customer']['email'], 'order_items' => [$item], 'state' => 'draft', 'cart' => FALSE]);
      $order->setData(self::KEY, [
        'version' => 1, 'request_id' => 16, 'request_public_id' => $publicId, 'customer_id' => 14, 'organization_id' => 14,
        'offer_public_id' => $context['offer']['public_id'], 'scope' => $context['scope'], 'scope_hash' => $context['scope_hash'],
        'selection' => $selection, 'accepted_by_uid' => (int) $account->id(), 'accepted_at' => time(),
        'domain_choice' => $input['domain_choice'], 'domain' => trim((string) ($input['domain'] ?? '')),
        'recurring_authorized' => FALSE, 'client_acceptance' => NULL, 'launch_authorized' => FALSE,
        'hold' => 'selected_staging_continues_independently;final_acceptance_and_launch_readiness_required',
      ]);
      $order->setRefreshState(OrderInterface::REFRESH_SKIP);
      $order->save();
      $db->update('famtastic_private_offer')->fields(['status' => 'checkout_started', 'commerce_order_id' => (int) $order->id(), 'accepted_at' => time(), 'changed' => time()])
        ->condition('id', (int) $context['offer']['id'])->execute();
      \Drupal::service('famtastic_pipeline.customer_portal')->claimResource(14, 'commerce_order', (int) $order->id());
      \Drupal::service('famtastic_pipeline.operational_ledger')->recordEvent('private-scope:request:16:checkout', 'commerce.private_scope_checkout_started', [
        'request_id' => 16, 'customer_id' => 14, 'order_id' => (int) $order->id(), 'scope_hash' => self::REUNION_HASH,
        'actor_uid' => (int) $account->id(), 'payment_created' => FALSE, 'client_acceptance' => FALSE, 'launch_authorized' => FALSE,
      ], 298, NULL, (int) $order->id());
      return $order;
    }
    catch (\Throwable $error) { $transaction->rollBack(); throw $error; }
  }

  /** Reconcile exact private order before resuming checkout or placing it. */
  public function assertReunionOrder(OrderInterface $order, ?array $context = NULL): void {
    $data = (array) $order->getData(self::KEY);
    $context ??= $this->context($order->getCustomer(), (string) ($data['request_public_id'] ?? ''));
    $selection = self::selection($context['request']);
    if ($context['kind'] !== 'reunion' || (int) $context['offer']['commerce_order_id'] !== (int) $order->id()
      || ($data['scope_hash'] ?? '') !== self::REUNION_HASH || ($data['selection'] ?? []) !== $selection
      || hash('sha256', json_encode($data['scope'] ?? [], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)) !== self::REUNION_HASH
      || ($data['recurring_authorized'] ?? TRUE) !== FALSE || (int) ($data['accepted_by_uid'] ?? 0) !== (int) $order->getCustomerId()
      || (int) ($data['customer_id'] ?? 0) !== 14 || (int) ($data['organization_id'] ?? 0) !== 14
      || $order->getData(OfflinePrepaymentService::KEY) || count($order->getItems()) !== 1
      || !$order->getTotalPrice()?->equals(new Price('199.00', 'USD')) || $order->getAdjustments()
      || ($order->getState()->value === 'completed' && !$order->isPaid())
      || !in_array($order->getState()->value, ['draft', 'completed'], TRUE)) throw new \RuntimeException('private_order_reconciliation_required');
    $item = $order->getItems()[0];
    if (!$item->getUnitPrice()?->equals(new Price('199.00', 'USD')) || (float) $item->getQuantity() !== 1.0
      || $item->getPurchasedEntity() || $item->getAdjustments()) throw new \RuntimeException('private_order_reconciliation_required');
  }
}
