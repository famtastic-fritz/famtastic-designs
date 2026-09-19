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
  public const REUNION_POLICY = 'request16-private-payment-after-direction-v1';

  private readonly array $reunion;

  public function __construct(?PrivatePurchaseAuthorityInterface $authority = NULL) {
    $definition = ($authority ?? new ApprovedPrivatePurchaseAuthority())->reunion();
    $integers = ['request_id', 'customer_id', 'organization_id', 'prospect_id'];
    $strings = ['public_id', 'email', 'sku', 'scope_hash', 'policy', 'event_key', 'authority'];
    if (count($definition) !== count($integers) + count($strings)) throw new \InvalidArgumentException('private_authority_invalid');
    foreach ($integers as $field) {
      if (!is_int($definition[$field] ?? NULL) || $definition[$field] < 1) throw new \InvalidArgumentException('private_authority_invalid');
    }
    foreach ($strings as $field) {
      if (!is_string($definition[$field] ?? NULL) || $definition[$field] === '' || strlen($definition[$field]) > 254
        || preg_match('/[\r\n\x00]/', $definition[$field])) throw new \InvalidArgumentException('private_authority_invalid');
    }
    if (!preg_match('/^[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}$/D', $definition['public_id'])
      || $definition['public_id'] === self::STOCK || $definition['request_id'] === 17
      || !filter_var($definition['email'], FILTER_VALIDATE_EMAIL)
      || !preg_match('/^[a-f0-9]{64}$/D', $definition['scope_hash'])) throw new \InvalidArgumentException('private_authority_invalid');
    // Snapshot once: an implementation cannot change authorization mid-operation.
    $this->reunion = $definition;
  }

  public function reunionRequestId(): int { return $this->reunion['request_id']; }
  public function reunionPublicId(): string { return $this->reunion['public_id']; }

  public static function url(string $request): string {
    return '/web/customer/private-purchase/' . rawurlencode($request);
  }

  /** Read-only; never issue a code, reserve an order or initialize Stripe on GET. */
  public function context(AccountInterface $account, string $publicId, bool $lock = FALSE): array {
    $a = $this->reunion;
    if (!$account->isAuthenticated() || ($account instanceof \Drupal\user\UserInterface && !$account->isActive())
      || !in_array($publicId, [$a['public_id'], self::STOCK], TRUE)) throw new \RuntimeException('private_purchase_not_found');
    $db = \Drupal::database();
    $query = $db->select('famtastic_project_request', 'r')->fields('r')->condition('public_id', $publicId);
    if ($lock) $query->forUpdate();
    $request = $query->execute()->fetchAssoc();
    $customer = $db->select('famtastic_customer', 'c')->fields('c')->condition('uid', (int) $account->id())->execute()->fetchAssoc();
    $expectedCustomer = $publicId === self::STOCK ? 15 : $a['customer_id'];
    $expectedOrganization = $publicId === self::STOCK ? 15 : $a['organization_id'];
    $email = $publicId === self::STOCK ? 'sprospere@yahoo.com' : $a['email'];
    OfflinePrepaymentService::assertIdentity($request ?: [], $customer ?: [], $expectedCustomer, $email);
    if ((int) $request['organization_id'] !== $expectedOrganization
      || !hash_equals(strtolower($email), strtolower($account->getEmail()))
      || !$db->select('famtastic_membership', 'm')->condition('customer_id', $expectedCustomer)->condition('organization_id', $expectedOrganization)
        ->condition('status', 'active')->condition('role', 'owner')->countQuery()->execute()->fetchField()) throw new \RuntimeException('private_purchase_not_found');
    $offerId = OfflinePrepaymentService::offerId(($publicId === $a['public_id'] ? 'reunion-private-scope:' : '') . $publicId);
    $offer = $db->select('famtastic_private_offer', 'o')->fields('o')->condition('public_id', $offerId)->execute()->fetchAssoc();
    if (!$offer || (int) $offer['website_request_id'] !== (int) $request['id'] || (int) $offer['customer_id'] !== $expectedCustomer
      || (int) $offer['organization_id'] !== $expectedOrganization) throw new \RuntimeException('private_purchase_not_found');
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
    $event = $db->select('famtastic_event', 'e')->fields('e', ['payload'])->condition('event_key', $a['event_key'])->execute()->fetchField();
    $evidence = json_decode((string) $event, TRUE, 512, JSON_THROW_ON_ERROR);
    $this->assertScope($request, $offer, $evidence ?: []);
    if (!empty($request['commerce_order_id']) || ($order ? $offer['status'] !== 'checkout_started' : $offer['status'] !== 'active')) {
      throw new \RuntimeException('private_existing_purchase_reconcile');
    }
    $scope = $evidence['scope'];
    return compact('request', 'customer', 'offer', 'order', 'scope') + ['kind' => 'reunion', 'scope_hash' => $a['scope_hash']];
  }

  public static function assertReunionScope(array $request, array $offer, array $event): void {
    (new self())->assertScope($request, $offer, $event);
  }

  public function assertScope(array $request, array $offer, array $event): void {
    $a = $this->reunion;
    if ((int) ($request['id'] ?? 0) !== $a['request_id'] || ($request['public_id'] ?? '') !== $a['public_id']
      || (int) ($request['customer_id'] ?? 0) !== $a['customer_id'] || (int) ($request['organization_id'] ?? 0) !== $a['organization_id']
      || (int) ($offer['website_request_id'] ?? 0) !== $a['request_id'] || (int) ($offer['customer_id'] ?? 0) !== $a['customer_id'] || (int) ($offer['organization_id'] ?? 0) !== $a['organization_id']
      || ($offer['sku'] ?? '') !== $a['sku'] || (int) ($offer['offered_amount_minor'] ?? 0) !== 19900 || ($offer['currency'] ?? '') !== 'usd'
      || !in_array($offer['status'] ?? '', ['active', 'checkout_started'], TRUE)
      || (!empty($offer['expires_at']) && (int) $offer['expires_at'] <= time())
      || ($event['authority'] ?? '') !== $a['authority'] || ($event['scope_hash'] ?? '') !== $a['scope_hash']
      || (int) ($event['request_id'] ?? 0) !== $a['request_id'] || (int) ($event['customer_id'] ?? 0) !== $a['customer_id'] || (int) ($event['organization_id'] ?? 0) !== $a['organization_id']
      || ($event['offer_public_id'] ?? '') !== ($offer['public_id'] ?? '')
      || !hash_equals($a['scope_hash'], hash('sha256', json_encode($event['scope'] ?? [], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)))) {
      throw new \RuntimeException('private_scope_changed');
    }
  }

  /**
   * Current, producer-recorded selection authority, not staging acceptance.
   *
   * Request16 explicitly permits payment after direction selection. Bind that
   * exception to its real selection revision and current inputs; never invent
   * an intent or silently rebase an existing purchase onto new work.
   */
  public static function selection(array $request): array {
    return (new self())->selectionSnapshot($request);
  }

  public function selectionSnapshot(array $request): array {
    $a = $this->reunion;
    if (($request['status'] ?? '') !== 'submitted' || ($request['proof_review_status'] ?? '') !== 'selected'
      || !in_array($request['selected_proof_direction'] ?? '', ['a', 'b', 'c'], TRUE) || empty($request['proof_campaign_id'])) {
      throw new \RuntimeException('private_scope_selection_required');
    }
    try {
      if ((int) ($request['id'] ?? 0) !== $a['request_id'] || ($request['public_id'] ?? '') !== $a['public_id']
        || (int) ($request['customer_id'] ?? 0) !== $a['customer_id'] || (int) ($request['organization_id'] ?? 0) !== $a['organization_id']
        || (int) ($request['project_id'] ?? 0) < 1 || (int) ($request['selected_proof_at'] ?? 0) < 1) {
        throw new \RuntimeException('missing_selection');
      }
      $entities = \Drupal::entityTypeManager();
      // Bypass static entity caches: a previously loaded project is not fresh
      // authority after a same-direction revision or source association.
      $project = $entities->getStorage('famtastic_project')->loadUnchanged((int) $request['project_id']);
      $studio = $project ? json_decode((string) $project->get('studio_json')->value, TRUE, 512, JSON_THROW_ON_ERROR) : [];
      $intent = $studio['selected_source_intent'] ?? [];
      $revision = $intent['selection']['revision'] ?? NULL;
      if (($intent['schema'] ?? '') !== 'famtastic.selected-source-intent.v1' || !is_int($revision) || $revision < 1
        || ($intent['intent_id'] ?? '') !== 'selected-source:request:' . $a['request_id'] . ':revision:' . $revision
        || ($intent['request_id'] ?? '') !== $a['public_id'] || (int) ($intent['website_request_id'] ?? 0) !== $a['request_id']
        || ($intent['project_id'] ?? '') !== (string) $request['project_id'] || ($intent['customer_id'] ?? '') !== (string) $a['customer_id']
        || ($intent['proof_campaign_id'] ?? '') !== (string) $request['proof_campaign_id']
        || ($intent['selection']['direction_id'] ?? '') !== $request['selected_proof_direction']
        || empty($intent['selection']['selected_at']) || (int) ($intent['selection']['variant_id'] ?? 0) < 1) {
        throw new \RuntimeException('selection_identity_changed');
      }
      $assets = self::requestAssets((int) $request['id']);
      $intake = json_decode((string) $request['intake_data'], TRUE, 512, JSON_THROW_ON_ERROR);
      $scopeJson = json_encode(SelectedSourceIntent::requestedScope($request, $intake), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
      if (SelectedSourceIntent::requestedScope($request, $intake) !== ($intent['scope']['snapshot'] ?? NULL)
        || ($intent['scope']['snapshot_json'] ?? '') !== $scopeJson
        || ($intent['scope']['snapshot_sha256'] ?? '') !== hash('sha256', $scopeJson)
        || ($intake['authored_content']['pages'] ?? NULL) !== ($intent['authored_content']['pages'] ?? NULL)
        || ($intent['asset_authority']['source'] ?? '') !== 'famtastic_request_asset'
        || $assets !== ($intent['asset_authority']['records'] ?? NULL)) {
        throw new \RuntimeException('selection_input_changed');
      }
      foreach ($assets as $asset) {
        if ($asset['customer_id'] !== (string) $a['customer_id'] || $asset['website_request_id'] !== (string) $a['request_id']) throw new \RuntimeException('selection_asset_owner_changed');
      }
      $variant = $entities->getStorage('proof_variant')->loadUnchanged((int) $intent['selection']['variant_id']);
      $campaign = $entities->getStorage('proof_campaign')->loadUnchanged((int) $request['proof_campaign_id']);
      $source = $intent['source'] ?? [];
      $artifacts = $source['artifacts'] ?? [];
      if (!$variant || !$campaign || (int) $variant->get('campaign_id')->target_id !== (int) $request['proof_campaign_id']
        || (string) $variant->get('direction_id')->value !== $request['selected_proof_direction']
        || (string) $campaign->get('selected_variant')->value !== $request['selected_proof_direction']
        || (int) $campaign->get('selected_at')->value !== (int) $request['selected_proof_at']
        || (int) $campaign->get('prospect_id')->target_id !== (int) ($request['prospect_id'] ?? 0)
        || (int) $project->get('prospect_ref')->target_id !== (int) ($request['prospect_id'] ?? 0)
        || (int) ($request['prospect_id'] ?? 0) < 1
        || ($source['kind'] ?? '') !== 'selected_proof' || !is_array($artifacts) || !$artifacts
        || ($artifacts[0]['role'] ?? '') !== 'selected_preview'
        || ($artifacts[0]['path'] ?? '') !== (string) $variant->get('artifact_path')->value
        || json_decode((string) $variant->get('design_dna')->value ?: '{}', TRUE, 512, JSON_THROW_ON_ERROR) !== ($source['design_dna'] ?? NULL)
        || hash('sha256', json_encode($source['design_dna'], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)) !== ($source['design_dna_sha256'] ?? '')) {
        throw new \RuntimeException('selection_source_changed');
      }
      $root = realpath(dirname(\Drupal::root()));
      foreach ($artifacts as $artifact) {
        $relative = (string) ($artifact['path'] ?? '');
        $path = $root && $relative !== '' && !str_starts_with($relative, '/') && !str_contains($relative, '..')
          ? realpath($root . '/' . $relative) : FALSE;
        if (!$path || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path)
          || !is_int($artifact['bytes'] ?? NULL) || filesize($path) !== $artifact['bytes']
          || !preg_match('/^[a-f0-9]{64}$/D', (string) ($artifact['sha256'] ?? ''))
          || hash_file('sha256', $path) !== $artifact['sha256']) throw new \RuntimeException('selection_source_changed');
      }
      return [
        'schema' => 'famtastic.private-purchase-selection.v1', 'policy' => $a['policy'],
        'campaign_id' => (int) $request['proof_campaign_id'], 'direction' => $request['selected_proof_direction'],
        'project_id' => (int) $request['project_id'], 'selected_proof_at' => (int) $request['selected_proof_at'],
        'intent_id' => $intent['intent_id'], 'selection_revision' => $revision, 'variant_id' => (int) $intent['selection']['variant_id'],
        'request_binding' => SelectedSourceIntent::requestBinding($request, $assets),
        'source_sha256' => hash('sha256', json_encode($source, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
        'execution_binding_sha256' => hash('sha256', json_encode($intent['execution_binding'] ?? [], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
        'requested_changes_sha256' => hash('sha256', json_encode($intent['requested_changes'] ?? [], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
      ];
    }
    catch (\Throwable $error) {
      throw new \RuntimeException('private_scope_selection_authority_changed', 0, $error);
    }
  }

  /** Same active-asset projection consumed by the canonical selection producer. */
  private static function requestAssets(int $requestId): array {
    return array_map(static fn(array $row): array => [
      'public_id' => $row['public_id'], 'kind' => $row['kind'], 'role' => $row['role'] ?? $row['kind'], 'name' => $row['original_name'],
      'mime_type' => $row['mime_type'], 'size_bytes' => (int) $row['size_bytes'],
      'sha256' => (string) ($row['sha256'] ?? ''), 'website_request_id' => (string) ($row['website_request_id'] ?? ''),
      'customer_id' => (string) ($row['customer_id'] ?? ''), 'status' => (string) ($row['status'] ?? ''),
      'ownership_confirmed' => (bool) $row['ownership_confirmed'], 'ai_use_consent' => (bool) $row['ai_use_consent'],
      'likeness_consent_version' => (string) ($row['likeness_consent_version'] ?? ''),
      'likeness_consent_at' => !empty($row['likeness_consent_at']) ? (int) $row['likeness_consent_at'] : NULL,
      'subject_permission_confirmed' => (bool) ($row['subject_permission_confirmed'] ?? FALSE),
      'ai_transformation_consent' => (bool) ($row['ai_transformation_consent'] ?? $row['ai_use_consent'] ?? FALSE),
    ], \Drupal::database()->select('famtastic_request_asset', 'a')->fields('a')
      ->condition('website_request_id', $requestId)->condition('status', 'active')->orderBy('created')->execute()->fetchAll(\PDO::FETCH_ASSOC));
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
    $a = $this->reunion;
    if ($publicId !== $a['public_id'] || !self::checkoutEnabled()) throw new \RuntimeException('private_checkout_unavailable');
    $db = \Drupal::database();
    $transaction = $db->startTransaction();
    try {
      $context = $this->context($account, $publicId, TRUE);
      self::assertDetails($input, $context['scope']['version'], $context['scope_hash']);
      $selection = $this->selectionSnapshot($context['request']);
      if (($input['selection_snapshot'] ?? NULL) !== $selection) throw new \RuntimeException('private_scope_selection_snapshot_changed');
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
        'version' => 2, 'request_id' => $a['request_id'], 'request_public_id' => $publicId, 'customer_id' => $a['customer_id'], 'organization_id' => $a['organization_id'],
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
      \Drupal::service('famtastic_pipeline.customer_portal')->claimResource($a['organization_id'], 'commerce_order', (int) $order->id());
      \Drupal::service('famtastic_pipeline.operational_ledger')->recordEvent('private-scope:request:' . $a['request_id'] . ':checkout', 'commerce.private_scope_checkout_started', [
        'request_id' => $a['request_id'], 'customer_id' => $a['customer_id'], 'order_id' => (int) $order->id(), 'scope_hash' => $a['scope_hash'],
        'actor_uid' => (int) $account->id(), 'payment_created' => FALSE, 'client_acceptance' => FALSE, 'launch_authorized' => FALSE,
      ], $a['prospect_id'], NULL, (int) $order->id());
      return $order;
    }
    catch (\Throwable $error) { $transaction->rollBack(); throw $error; }
  }

  /** Reconcile exact private order before resuming checkout or placing it. */
  public function assertReunionOrder(OrderInterface $order, ?array $context = NULL): void {
    $a = $this->reunion;
    $data = (array) $order->getData(self::KEY);
    if ($context !== NULL && (int) ($context['customer']['uid'] ?? 0) !== (int) $order->getCustomerId()) {
      throw new \RuntimeException('private_order_reconciliation_required');
    }
    // Supplied form/request context may be stale. Re-read account, membership,
    // offer and request even when resuming within an existing request process.
    $context = $this->context($order->getCustomer(), (string) ($data['request_public_id'] ?? ''));
    $selection = $this->selectionSnapshot($context['request']);
    if ($context['kind'] !== 'reunion' || (int) $context['offer']['commerce_order_id'] !== (int) $order->id()
      || ($data['version'] ?? NULL) !== 2 || ($data['request_id'] ?? NULL) !== $a['request_id'] || ($data['request_public_id'] ?? '') !== $a['public_id']
      || ($data['offer_public_id'] ?? '') !== $context['offer']['public_id']
      || ($data['scope_hash'] ?? '') !== $a['scope_hash'] || ($data['selection'] ?? []) !== $selection
      || hash('sha256', json_encode($data['scope'] ?? [], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)) !== $a['scope_hash']
      || ($data['recurring_authorized'] ?? TRUE) !== FALSE || (int) ($data['accepted_by_uid'] ?? 0) !== (int) $order->getCustomerId()
      || !array_key_exists('client_acceptance', $data) || $data['client_acceptance'] !== NULL
      || ($data['launch_authorized'] ?? TRUE) !== FALSE
      || ($data['hold'] ?? '') !== 'selected_staging_continues_independently;final_acceptance_and_launch_readiness_required'
      || (int) ($data['customer_id'] ?? 0) !== $a['customer_id'] || (int) ($data['organization_id'] ?? 0) !== $a['organization_id']
      || $order->getData(OfflinePrepaymentService::KEY) || $order->getData('famtastic_checkout') || count($order->getItems()) !== 1
      || (int) $order->getStoreId() !== 1
      || !$order->getTotalPrice()?->equals(new Price('199.00', 'USD')) || $order->getAdjustments()
      || ($order->getState()->value === 'completed' && !$order->isPaid())
      || !in_array($order->getState()->value, ['draft', 'completed'], TRUE)) throw new \RuntimeException('private_order_reconciliation_required');
    $item = $order->getItems()[0];
    if (!$item->getUnitPrice()?->equals(new Price('199.00', 'USD')) || !$item->isUnitPriceOverridden() || (float) $item->getQuantity() !== 1.0
      || $item->getPurchasedEntity() || $item->getAdjustments()) throw new \RuntimeException('private_order_reconciliation_required');
  }
}
