<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Controller;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\commerce_price\Price;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\famtastic_pipeline\Service\CustomerPortalService;
use Drupal\famtastic_pipeline\Service\CatalogPaymentEligibilityService;
use Drupal\famtastic_pipeline\Service\CommerceLifecycleService;
use Drupal\famtastic_pipeline\Service\DeepDiveInvitationService;
use Drupal\famtastic_pipeline\Service\GrantCodeService;
use Drupal\famtastic_pipeline\Service\OutreachMailer;
use Drupal\user\UserAuthInterface;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Same-origin, session-backed customer identity and portal API.
 */
final class CustomerPortalController extends ControllerBase {

  public function __construct(
    private readonly CustomerPortalService $portal,
    private readonly UserAuthInterface $userAuth,
    private readonly FloodInterface $flood,
    private readonly AccountProxyInterface $account,
    private readonly OutreachMailer $mailer,
    private readonly ConfigFactoryInterface $portalConfigFactory,
    private readonly LoggerInterface $logger,
    private readonly Connection $database,
    private readonly GrantCodeService $grantCodes,
    private readonly CatalogPaymentEligibilityService $paymentEligibility,
    private readonly CommerceLifecycleService $commerceLifecycle,
    private readonly DeepDiveInvitationService $deepDives,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('famtastic_pipeline.customer_portal'),
      $container->get('user.auth'),
      $container->get('flood'),
      $container->get('current_user'),
      $container->get('famtastic_pipeline.mailer'),
      $container->get('config.factory'),
      $container->get('logger.channel.famtastic_pipeline'),
      $container->get('database'),
      $container->get('famtastic_pipeline.grant_codes'),
      $container->get('famtastic_pipeline.catalog_payment_eligibility'),
      $container->get('famtastic_pipeline.commerce_lifecycle'),
      $container->get('famtastic_pipeline.deep_dive_invitations'),
    );
  }

  public function register(Request $request): JsonResponse {
    $data = $this->body($request);
    $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
    $password = (string) ($data['password'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
      return $this->error('invalid_registration', 422, 'Use a valid email and a password of at least 12 characters.');
    }
    $identifier = 'portal-register:' . hash('sha256', $email . '|' . ($request->getClientIp() ?: 'unknown'));
    if (!$this->flood->isAllowed('famtastic_portal_register', 5, 3600, $identifier)) {
      return $this->error('try_later', 429, 'Please wait before trying again.');
    }
    $this->flood->register('famtastic_portal_register', 3600, $identifier);
    // This validates the continuation before User::save() fires
    // hook_user_insert(). An invalid or unrelated value is intentionally a
    // no-op so ordinary registration retains its existing behavior.
    $previewDelivery = $this->portal->beginPublicPreviewRegistration($email, (string) ($data['preview_continuation'] ?? ''));
    try {
      $deepDive = $this->deepDives->beginRegistration($email, (string) ($data['deep_dive_continuation'] ?? ''));
    }
    catch (\InvalidArgumentException $error) {
      return $this->error('deep_dive_continuation_invalid', 422, $error->getMessage());
    }
    $storage = $this->entityTypeManager()->getStorage('user');
    try {
      if ($existingUsers = $storage->loadByProperties(['mail' => $email])) {
        /** @var \Drupal\user\UserInterface $existingUser */
        $existingUser = reset($existingUsers);
        $existingCustomer = $this->portal->customerForUid((int) $existingUser->id());
        if ($existingCustomer && empty($existingCustomer['verified_at'])) {
          if ($deepDive) $this->deepDives->attachPendingCustomer((string) $deepDive['public_id'], (int) $existingCustomer['id']);
          $this->sendVerification($request, $existingCustomer, $previewDelivery);
        }
        elseif ($existingCustomer) {
          $this->portal->claimPreviewsForVerifiedCustomer((int) $existingCustomer['id']);
          if ($deepDive) $this->claimDeepDive((int) $existingCustomer['id'], $email);
        }
        return new JsonResponse(['ok' => TRUE, 'verification_required' => TRUE], 202);
      }
      /** @var \Drupal\user\UserInterface $user */
      $user = $storage->create([
        'name' => $email, 'mail' => $email, 'pass' => $password,
        'status' => TRUE, 'roles' => ['authenticated'],
      ]);
      $user->save();
      $customer = $this->portal->createCustomer($user, $data);
      if ($deepDive) $this->deepDives->attachPendingCustomer((string) $deepDive['public_id'], (int) $customer['id']);
      // The preview path receives only its verification email at this point.
      // Its owner alert is queued after the one-time verification token is
      // consumed; it never inherits generic request notifications or jobs.
      if ($previewDelivery === NULL) {
        $this->portal->queueRegistrationNotification($customer);
      }
      $this->sendVerification($request, $customer, $previewDelivery);
      return new JsonResponse(['ok' => TRUE, 'verification_required' => TRUE], 201);
    }
    finally {
      $this->portal->endPublicPreviewRegistration($email);
    }
  }

  public function verify(Request $request): JsonResponse {
    $data = $this->body($request);
    $record = $this->portal->consumeToken((string) ($data['token'] ?? ''), 'verify');
    if (!$record) return $this->error('invalid_or_expired_token', 422, 'This verification link is invalid or expired.');
    $this->portal->markVerified((int) $record['customer_id']);
    $this->claimDeepDive((int) $record['customer_id'], (string) $record['email']);
    $payload = json_decode((string) ($record['payload'] ?? ''), TRUE);
    if (is_array($payload) && ($payload['registration_flow'] ?? '') === 'public_preview_v1') {
      $this->portal->queueVerifiedPreviewRegistrationNotification((int) $record['customer_id']);
    }
    return new JsonResponse(['ok' => TRUE]);
  }

  public function login(Request $request): JsonResponse {
    $data = $this->body($request);
    $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
    $identifier = 'portal-login:' . hash('sha256', $email . '|' . ($request->getClientIp() ?: 'unknown'));
    if (!$this->flood->isAllowed('famtastic_portal_login', 5, 900, $identifier)) {
      return $this->error('invalid_credentials', 403, 'The email or password was not accepted.');
    }
    $uid = $this->userAuth->authenticate($email, (string) ($data['password'] ?? ''));
    if (!$uid) {
      $this->flood->register('famtastic_portal_login', 900, $identifier);
      return $this->error('invalid_credentials', 403, 'The email or password was not accepted.');
    }
    /** @var \Drupal\user\UserInterface $user */
    $user = $this->entityTypeManager()->getStorage('user')->load($uid);
    $customer = $this->portal->customerForUid((int) $uid);
    if (!$user || !$customer || empty($customer['verified_at'])) {
      return $this->error('verification_required', 403, 'Verify your email before opening the portal.');
    }
    $this->portal->claimPreviewsForVerifiedCustomer((int) $customer['id']);
    $this->claimDeepDive((int) $customer['id'], (string) $customer['email']);
    user_login_finalize($user);
    $this->flood->clear('famtastic_portal_login', $identifier);
    return $this->sessionPayload($customer);
  }

  /** Creates or advances the account-owned request after exact-email verification. */
  private function claimDeepDive(int $customerId, string $email): void {
    foreach ($this->deepDives->claimForVerifiedCustomer($customerId, $email) as $deepDive) {
      $requestId = $this->portal->createWebsiteRequestFromDeepDive($customerId, $deepDive);
      if ($requestId && empty($deepDive['website_request_id'])) {
        $this->deepDives->attachWebsiteRequest((int) $deepDive['id'], $requestId);
      }
    }
  }

  public function forgotPassword(Request $request): JsonResponse {
    $email = mb_strtolower(trim((string) ($this->body($request)['email'] ?? '')));
    $identifier = 'portal-recovery:' . hash('sha256', $email . '|' . ($request->getClientIp() ?: 'unknown'));
    if ($this->flood->isAllowed('famtastic_portal_recovery', 3, 3600, $identifier)) {
      $this->flood->register('famtastic_portal_recovery', 3600, $identifier);
      if ($customer = $this->portal->customerForEmail($email)) {
        $token = $this->portal->issueToken((int) $customer['id'], $email, 'recovery');
        $url = $request->getSchemeAndHttpHost() . '/reset-password?token=' . rawurlencode($token);
        $this->mailer->send($email, 'Reset your FAMtastic Designs password', "Reset your password:\n\n{$url}\n\nThis link expires in one hour.");
      }
    }
    return new JsonResponse(['ok' => TRUE, 'message' => 'If the account exists, a recovery email is on the way.'], 202);
  }

  public function resetPassword(Request $request): JsonResponse {
    $data = $this->body($request);
    $password = (string) ($data['password'] ?? '');
    if (strlen($password) < 12) return $this->error('invalid_password', 422, 'Use a password of at least 12 characters.');
    $record = $this->portal->consumeToken((string) ($data['token'] ?? ''), 'recovery');
    if (!$record) return $this->error('invalid_or_expired_token', 422, 'This recovery link is invalid or expired.');
    $customer = $this->portal->customerForEmail((string) $record['email']);
    if (!$customer) return $this->error('invalid_or_expired_token', 422, 'This recovery link is invalid or expired.');
    /** @var \Drupal\user\UserInterface|null $user */
    $user = $this->entityTypeManager()->getStorage('user')->load((int) $customer['uid']);
    if (!$user) return $this->error('invalid_or_expired_token', 422, 'This recovery link is invalid or expired.');
    $user->setPassword($password)->save();
    return new JsonResponse(['ok' => TRUE]);
  }

  public function logout(Request $request): JsonResponse {
    if ($this->account->isAuthenticated()) user_logout();
    return new JsonResponse(['ok' => TRUE]);
  }

  public function session(Request $request): JsonResponse {
    $customer = $this->currentCustomer();
    return $customer ? $this->sessionPayload($customer) : $this->error('authentication_required', 401, 'Sign in to continue.');
  }

  public function workspace(Request $request): JsonResponse {
    $customer = $this->currentCustomer();
    if (!$customer) return $this->error('authentication_required', 401, 'Sign in to continue.');
    try {
      return $this->noStore(new JsonResponse($this->portal->workspace((int) $customer['id'], $request->query->get('organization'))));
    }
    catch (\RuntimeException) {
      return $this->error('workspace_not_found', 404, 'Customer workspace not found.');
    }
  }

  /** Returns a research snapshot only after the same-email customer claim. */
  public function previewResearch(string $preview_delivery): JsonResponse {
    $customer = $this->currentCustomer();
    if (!$customer) return $this->error('authentication_required', 401, 'Sign in to continue.');
    if (empty($customer['verified_at'])) return $this->error('verification_required', 403, 'Verify your email before opening research.');
    $research = $this->portal->previewResearchForCustomer((int) $customer['id'], $preview_delivery);
    if (!$research) return $this->error('preview_research_not_found', 404, 'This research snapshot is unavailable.');
    return $this->noStore(new JsonResponse(['ok' => TRUE, 'preview_research' => $research]));
  }

  /** Returns the administrator-controlled public Commerce catalog. */
  public function catalog(): JsonResponse {
    $definitions = $this->productDefinitions();
    $policy = $this->dealRegistry()['policy'];
    $items = [];
    foreach ($definitions as $definition) {
      if (empty($definition['published'])) continue;
      $item = array_intersect_key($definition, array_flip([
        'sku', 'type', 'title', 'summary', 'price', 'currency', 'billing',
        'payment', 'included', 'exclusions', 'entitlements', 'intake_schema', 'fulfillment',
        'portal', 'upsells',
      ]));
      $item['offer_contract'] = $this->offerContractSnapshot((string) $definition['sku']);
      $items[] = $item;
    }
    return $this->noStore(new JsonResponse(['products' => $items, 'terms' => [
      'version' => $policy['version'], 'status' => $policy['status'],
      'support_response' => $policy['support_response'], 'marketing_default' => $policy['marketing_default'],
      'payment_security' => $policy['payment_security'],
    ]]));
  }

  /** Creates an account-owned Commerce order and hands off to Commerce checkout. */
  public function commerceCheckout(Request $request): JsonResponse {
    $customer = $this->currentCustomer();
    if (!$customer) return $this->error('authentication_required', 401, 'Sign in to continue.');
    if (empty($customer['verified_at'])) return $this->error('verification_required', 403, 'Verify your email before purchasing.');
    $data = $this->body($request);
    $organizationPublicId = (string) ($data['organization'] ?? '');
    $organizations = $this->portal->organizations((int) $customer['id']);
    $organization = NULL;
    foreach ($organizations as $candidate) {
      if (hash_equals((string) $candidate['public_id'], $organizationPublicId)) $organization = $candidate;
    }
    if (!$organization) return $this->error('workspace_not_found', 404, 'Customer workspace not found.');

    $websiteRequest = NULL;
    $privateOffer = NULL;
    if (!empty($data['website_request'])) {
      $websiteRequest = $this->portal->ownedWebsiteRequest((int) $customer['id'], (string) $data['website_request']);
      if (!$websiteRequest || (int) $websiteRequest['organization_id'] !== (int) $organization['id']) {
        return $this->error('website_request_not_found', 404, 'Website request not found.');
      }
      if (!in_array($websiteRequest['status'], ['submitted', 'checkout_started'], TRUE)) {
        return $this->error('website_request_not_ready', 422, 'Submit the website request before purchasing.');
      }
      $requestIntake = json_decode((string) $websiteRequest['intake_data'], TRUE) ?: [];
      $recommendedSku = (string) ($requestIntake['recommendation']['recommended_sku'] ?? '');
      $privateOffer = $this->database->select('famtastic_private_offer', 'o')->fields('o')
        ->condition('website_request_id', (int) $websiteRequest['id'])->condition('customer_id', (int) $customer['id'])
        ->condition('status', 'active')->condition('expires_at', time(), '>')->orderBy('created', 'DESC')->range(0, 1)->execute()->fetchAssoc();
      if ($privateOffer) $recommendedSku = (string) $privateOffer['sku'];
      if (!empty($requestIntake['recommendation']['review_required']) || !in_array($recommendedSku, ['FAM-FOOT-199', 'FAM-BUSINESS-499'], TRUE)) {
        return $this->error('website_request_review_required', 422, 'This request needs a FAMtastic recommendation or private offer before checkout.');
      }
      if (!empty($websiteRequest['commerce_order_id'])) {
        return $this->error('website_request_already_ordered', 409, 'This website request already has a purchase in progress.');
      }
      if (($websiteRequest['proof_review_status'] ?? '') !== 'selected') {
        return $this->error('website_proof_selection_required', 422, 'Choose one of your approved website concepts before purchasing.');
      }
    }

    $skus = array_values(array_unique(array_filter(array_map('strval', (array) ($data['skus'] ?? [])))));
    if (!$skus || count($skus) > 12) return $this->error('invalid_cart', 422, 'Choose at least one available service.');
    $definitions = $this->productDefinitions();
    $hasActiveWebsiteEntitlement = $this->hasActiveWebsiteEntitlement((int) $organization['id']);
    $hasActiveWebsiteProject = $this->hasActiveWebsiteProject((int) $organization['id']);
    $paymentEligibility = $this->paymentEligibility->evaluateCart(
      $definitions,
      $skus,
      $websiteRequest !== NULL && ($websiteRequest['proof_review_status'] ?? '') === 'selected',
      $hasActiveWebsiteEntitlement,
      $hasActiveWebsiteProject,
    );
    if (empty($paymentEligibility['allowed'])) {
      return $this->error(
        (string) ($paymentEligibility['code'] ?? 'payment_contract_unavailable'),
        422,
        (string) ($paymentEligibility['message'] ?? 'This service is not available for checkout.'),
      );
    }
    if ($websiteRequest && !in_array($recommendedSku, $skus, TRUE)) {
      return $this->error('recommended_package_required', 422, 'The package in this checkout does not match the website recommendation.');
    }
    $websiteSkus = array_values(array_intersect($skus, ['FAM-FOOT-199', 'FAM-BUSINESS-499']));
    if (count($websiteSkus) > 1) return $this->error('invalid_cart', 422, 'Choose one website bundle per request.');
    if ($websiteSkus) {
      if (!$websiteRequest) {
        return $this->error('website_proof_selection_required', 422, 'Start with your business intake and choose an approved website direction before checkout.');
      }
      if (!in_array((string) ($data['domain_choice'] ?? ''), ['new_domain', 'existing_domain'], TRUE)) {
        return $this->error('domain_choice_required', 422, 'Choose a new domain or connect an existing domain.');
      }
    }
    $terms = $this->dealRegistry();
    if (empty($data['accept_terms']) || (string) ($data['terms_version'] ?? '') !== (string) $terms['policy']['version']) {
      return $this->error('terms_required', 422, 'Accept the current purchase and renewal terms.');
    }

    $storeIds = $this->entityTypeManager()->getStorage('commerce_store')->getQuery()->accessCheck(FALSE)->condition('status', 1)->range(0, 1)->execute();
    if (!$storeIds) return $this->error('store_unavailable', 503, 'Checkout is temporarily unavailable.');
    $variations = [];
    $skuAmounts = [];
    foreach ($skus as $sku) {
      $variationIds = $this->entityTypeManager()->getStorage('commerce_product_variation')->getQuery()->accessCheck(FALSE)
        ->condition('sku', $sku)->condition('status', 1)->range(0, 1)->execute();
      /** @var \Drupal\commerce_product\Entity\ProductVariationInterface|null $variation */
      $variation = $variationIds ? ProductVariation::load(reset($variationIds)) : NULL;
      if (!$variation) return $this->error('product_unavailable', 422, 'One selected service is unavailable.');
      $variations[$sku] = $variation;
      $amount = (int) round((float) $variation->getPrice()->getNumber() * 100);
      if ($privateOffer && $sku === $privateOffer['sku']) {
        $amount = (int) $privateOffer['offered_amount_minor'];
      }
      $skuAmounts[$sku] = $amount;
    }
    $grantQuote = NULL;
    if (trim((string) ($data['grant_code'] ?? '')) !== '') {
      try {
        $grantQuote = $this->grantCodes->quote(
          (string) $data['grant_code'],
          (int) $customer['id'],
          (int) $organization['id'],
          $websiteRequest ? (int) $websiteRequest['id'] : NULL,
          $skuAmounts,
        );
      }
      catch (\InvalidArgumentException $error) {
        return $this->error('grant_code_invalid', 422, $error->getMessage());
      }
    }

    $transaction = $this->database->startTransaction();
    $items = [];
    foreach ($variations as $sku => $variation) {
      $item = OrderItem::create([
        'type' => $variation->getOrderItemTypeId(), 'purchased_entity' => $variation, 'quantity' => 1,
        'unit_price' => $variation->getPrice(), 'title' => $variation->getTitle(),
      ]);
      if ($privateOffer && $sku === $privateOffer['sku']) {
        $item->setUnitPrice(new Price(number_format(((int) $privateOffer['offered_amount_minor']) / 100, 2, '.', ''), strtoupper((string) $privateOffer['currency'])), TRUE);
      }
      if ($grantQuote && $sku === $grantQuote['sku']) {
        $newAmount = max(0, (int) $skuAmounts[$sku] - (int) $grantQuote['discount_minor']);
        $item->setUnitPrice(new Price(number_format($newAmount / 100, 2, '.', ''), 'USD'), TRUE);
      }
      $item->save();
      $items[] = $item;
    }
    $order = Order::create([
      'type' => 'default', 'store_id' => reset($storeIds), 'uid' => (int) $this->account->id(),
      'mail' => (string) $customer['email'], 'order_items' => $items, 'state' => 'draft',
    ]);
    $order->setData('famtastic_checkout', [
      'organization_public_id' => $organizationPublicId,
      'domain_choice' => (string) ($data['domain_choice'] ?? 'not_applicable'),
      'terms_version' => (string) $terms['policy']['version'],
      'recurring_authorized' => !empty($data['recurring_authorized']),
      'marketing_opt_in' => !empty($data['marketing_opt_in']),
      'selected_skus' => $skus,
      'offer_contracts' => array_map(fn(string $sku): array => $this->offerContractSnapshot($sku), $skus),
      'website_request_public_id' => $websiteRequest['public_id'] ?? '',
      'private_offer' => $privateOffer ? ['public_id' => $privateOffer['public_id'], 'sku' => $privateOffer['sku'], 'list_amount_minor' => (int) $privateOffer['list_amount_minor'], 'offered_amount_minor' => (int) $privateOffer['offered_amount_minor'], 'reason' => $privateOffer['reason']] : NULL,
      'grant' => $grantQuote ? array_diff_key($grantQuote, ['id' => TRUE]) : NULL,
      'captured_at' => gmdate(DATE_ATOM),
    ]);
    $order->save();
    if ($websiteRequest) {
      $this->portal->bindWebsiteRequestToOrder((int) $customer['id'], (string) $websiteRequest['public_id'], (int) $order->id());
    }
    if ($privateOffer) $this->database->update('famtastic_private_offer')->fields(['status' => 'accepted', 'commerce_order_id' => (int) $order->id(), 'accepted_at' => time(), 'changed' => time()])->condition('id', $privateOffer['id'])->condition('status', 'active')->execute();
    if ($grantQuote) {
      try {
        $this->grantCodes->redeem($grantQuote, (int) $order->id(), (int) $customer['id'], (int) $organization['id'], $websiteRequest ? (int) $websiteRequest['id'] : NULL);
      }
      catch (\RuntimeException $error) {
        $transaction->rollBack();
        return $this->error('grant_code_reservation_failed', 409, $error->getMessage());
      }
    }
    $order->recalculateTotalPrice();
    if ($order->getTotalPrice() && $order->getTotalPrice()->isZero()) {
      $order->set('state', 'completed');
      $order->setPlacedTime(time());
      $order->save();
      $fulfillment = $this->commerceLifecycle->fulfill($order);
      if (empty($fulfillment['fulfilled'])) {
        throw new \RuntimeException('The sponsored order completed, but service activation requires staff review.');
      }
      return $this->noStore(new JsonResponse([
        'ok' => TRUE, 'order_id' => (int) $order->id(), 'completed' => TRUE,
        'checkout_url' => $request->getSchemeAndHttpHost() . '/portal/?order=' . $order->id() . '&grant=applied',
      ], 201));
    }
    return $this->noStore(new JsonResponse([
      'ok' => TRUE, 'order_id' => (int) $order->id(),
      'checkout_url' => $request->getSchemeAndHttpHost() . '/web/checkout/' . $order->id(),
    ], 201));
  }

  public function createWebsiteRequest(Request $request): JsonResponse {
    $customer = $this->currentCustomer();
    if (!$customer) return $this->error('authentication_required', 401, 'Sign in to continue.');
    try {
      $data = $this->body($request);
      return new JsonResponse(['ok' => TRUE, 'website_request' => $this->portal->createWebsiteRequest((int) $customer['id'], (string) ($data['organization'] ?? ''), $data)], 201);
    }
    catch (\InvalidArgumentException $e) { return $this->error('invalid_website_request', 422, $e->getMessage()); }
    catch (\RuntimeException) { return $this->error('workspace_not_found', 404, 'Customer workspace not found.'); }
  }

  public function updateWebsiteRequest(Request $request, string $website_request): JsonResponse {
    $customer = $this->currentCustomer();
    if (!$customer) return $this->error('authentication_required', 401, 'Sign in to continue.');
    try {
      return new JsonResponse(['ok' => TRUE, 'website_request' => $this->portal->updateWebsiteRequest((int) $customer['id'], $website_request, $this->body($request))]);
    }
    catch (\InvalidArgumentException $e) { return $this->error('invalid_website_request', 422, $e->getMessage()); }
    catch (\RuntimeException) { return $this->error('website_request_not_found', 404, 'Website request not found.'); }
  }

  public function websiteRequestArchive(Request $request, string $website_request): JsonResponse {
    $customer = $this->currentCustomer();
    if (!$customer) return $this->error('authentication_required', 401, 'Sign in to continue.');
    try {
      return $this->noStore(new JsonResponse([
        'ok' => TRUE,
        'website_request' => $this->portal->setWebsiteRequestArchiveState(
          (int) $customer['id'],
          $website_request,
          (string) ($this->body($request)['action'] ?? ''),
        ),
      ]));
    }
    catch (\InvalidArgumentException $error) { return $this->error('invalid_archive_action', 422, $error->getMessage()); }
    catch (\RuntimeException) { return $this->error('website_request_not_found', 404, 'Website request not found.'); }
  }

  public function websiteProofDecision(Request $request, string $website_request): JsonResponse {
    $customer = $this->currentCustomer();
    if (!$customer) return $this->error('authentication_required', 401, 'Sign in to continue.');
    try {
      return new JsonResponse(['ok' => TRUE, 'website_request' => $this->portal->decideWebsiteRequestProof((int) $customer['id'], $website_request, $this->body($request))]);
    }
    catch (\InvalidArgumentException $error) { return $this->error('invalid_proof_decision', 422, $error->getMessage()); }
    catch (\RuntimeException) { return $this->error('website_proofs_not_found', 404, 'Website proofs are not available.'); }
  }

  public function websiteProofShare(Request $request, string $website_request): JsonResponse {
    $customer = $this->currentCustomer();
    if (!$customer) return $this->error('authentication_required', 401, 'Sign in to continue.');
    try {
      $data = $this->body($request);
      return $this->noStore(new JsonResponse(['ok' => TRUE, 'website_request' => $this->portal->updateWebsiteProofShare(
        (int) $customer['id'],
        $website_request,
        (string) ($data['action'] ?? ''),
        (int) $this->account->id(),
      )]));
    }
    catch (\InvalidArgumentException $error) { return $this->error('invalid_proof_share_action', 422, $error->getMessage()); }
    catch (\RuntimeException) { return $this->error('website_proofs_not_found', 404, 'Website proofs are not available.'); }
  }

  public function sendToSiteStudio(Request $request, string $website_request): JsonResponse {
    $customer = $this->currentCustomer();
    if (!$customer) return $this->error('authentication_required', 401, 'Sign in to continue.');
    try {
      return new JsonResponse(['ok' => TRUE, 'website_request' => $this->portal->sendWebsiteRequestToSiteStudio((int) $customer['id'], $website_request)]);
    }
    catch (\InvalidArgumentException|\RuntimeException $e) { return $this->error('site_studio_error', 422, $e->getMessage()); }
  }

  public function profile(Request $request): JsonResponse {
    $customer = $this->currentCustomer();
    if (!$customer) return $this->error('authentication_required', 401, 'Sign in to continue.');
    $this->portal->updateCustomer((int) $customer['id'], $this->body($request));
    return $this->sessionPayload($this->portal->customerForUid((int) $this->account->id()) ?? $customer);
  }

  public function preferences(Request $request): JsonResponse {
    $customer = $this->currentCustomer();
    if (!$customer) return $this->error('authentication_required', 401, 'Sign in to continue.');
    return $this->noStore(new JsonResponse(['ok' => TRUE, 'preferences' => $this->portal->updatePreferences((int) $customer['id'], $this->body($request))]));
  }

  public function referral(Request $request): JsonResponse {
    $customer = $this->currentCustomer();
    if (!$customer) return $this->error('authentication_required', 401, 'Sign in to continue.');
    $data = $this->body($request);
    try {
      return new JsonResponse(['ok' => TRUE, 'referral' => $this->portal->createReferral((int) $customer['id'], (string) ($data['organization'] ?? ''), $data)], 201);
    }
    catch (\InvalidArgumentException $e) {
      return $this->error('invalid_referral', 422, $e->getMessage());
    }
    catch (\RuntimeException) {
      return $this->error('workspace_not_found', 404, 'Customer workspace not found.');
    }
  }

  public function createThread(Request $request): JsonResponse {
    $customer = $this->currentCustomer();
    if (!$customer) return $this->error('authentication_required', 401, 'Sign in to continue.');
    $data = $this->body($request);
    try {
      $thread = $this->portal->createThread((int) $customer['id'], (string) ($data['organization'] ?? ''), $data, (int) $this->account->id());
      return new JsonResponse(['ok' => TRUE, 'thread' => $thread], 201);
    }
    catch (\InvalidArgumentException $e) {
      return $this->error('invalid_message', 422, $e->getMessage());
    }
    catch (\RuntimeException) {
      return $this->error('workspace_not_found', 404, 'Customer workspace not found.');
    }
  }

  public function thread(Request $request, string $thread): JsonResponse {
    $customer = $this->currentCustomer();
    if (!$customer) return $this->error('authentication_required', 401, 'Sign in to continue.');
    try { return new JsonResponse($this->portal->thread((int) $customer['id'], $thread)); }
    catch (\RuntimeException) { return $this->error('thread_not_found', 404, 'Conversation not found.'); }
  }

  public function reply(Request $request, string $thread): JsonResponse {
    $customer = $this->currentCustomer();
    if (!$customer) return $this->error('authentication_required', 401, 'Sign in to continue.');
    try {
      $data = $this->body($request);
      $this->portal->addMessage((int) $customer['id'], $thread, (string) ($data['body'] ?? ''), (int) $this->account->id());
      return new JsonResponse(['ok' => TRUE]);
    }
    catch (\InvalidArgumentException $e) { return $this->error('invalid_message', 422, $e->getMessage()); }
    catch (\RuntimeException) { return $this->error('thread_not_found', 404, 'Conversation not found.'); }
  }

  private function currentCustomer(): ?array {
    return $this->account->isAuthenticated() ? $this->portal->customerForUid((int) $this->account->id()) : NULL;
  }

  private function sessionPayload(array $customer): JsonResponse {
    return $this->noStore(new JsonResponse(['ok' => TRUE, 'customer' => [
      'public_id' => $customer['public_id'], 'display_name' => $customer['display_name'],
      'email' => $customer['email'], 'phone' => $customer['phone'],
      'marketing_status' => $customer['marketing_status'], 'verified' => !empty($customer['verified_at']),
    ], 'organizations' => array_map(fn(array $o): array => array_diff_key($o, ['id' => TRUE]), $this->portal->organizations((int) $customer['id']))]));
  }

  private function sendVerification(Request $request, array $customer, ?string $previewDelivery = NULL): void {
    $payload = $previewDelivery === NULL ? [] : [
      // The continuation itself is not copied into the token. It was already
      // validated and recorded against the delivery before User::save().
      'registration_flow' => 'public_preview_v1',
      'preview_delivery' => $previewDelivery,
    ];
    $token = $this->portal->issueToken((int) $customer['id'], (string) $customer['email'], 'verify', $payload);
    $url = $request->getSchemeAndHttpHost() . '/verify-email?token=' . rawurlencode($token);
    $this->mailer->send((string) $customer['email'], 'Verify your FAMtastic Designs account', "Verify your customer account:\n\n{$url}\n\nThis link expires in 24 hours.");
  }

  private function body(Request $request): array {
    try { return (array) json_decode($request->getContent(), TRUE, 512, JSON_THROW_ON_ERROR); }
    catch (\JsonException) { return []; }
  }

  private function error(string $code, int $status, string $message): JsonResponse {
    return $this->noStore(new JsonResponse(['error' => $code, 'message' => $message], $status));
  }

  private function noStore(JsonResponse $response): JsonResponse {
    $response->headers->set('Cache-Control', 'no-store, private');
    return $response;
  }

  /** @return array<string, array<string, mixed>> */
  private function productDefinitions(): array {
    $path = dirname(\Drupal::root()) . '/config/famtastic-products.json';
    $catalog = json_decode((string) file_get_contents($path), TRUE, 512, JSON_THROW_ON_ERROR);
    $definitions = [];
    foreach ($catalog['products'] ?? [] as $product) $definitions[(string) $product['sku']] = $product;
    return $definitions;
  }

  private function dealRegistry(): array {
    $path = dirname(\Drupal::root()) . '/config/famtastic-deal-terms.json';
    return json_decode((string) file_get_contents($path), TRUE, 512, JSON_THROW_ON_ERROR);
  }

  /**
   * Produces the exact public/commercial terms used by a checkout attempt.
   *
   * This is intentionally derived from the existing canonical product and
   * deal-term registries. It stops routes, receipts, and fulfillment from
   * carrying independent hand-maintained feature lists.
   */
  private function offerContractSnapshot(string $sku): array {
    $definitions = $this->productDefinitions();
    $registry = $this->dealRegistry();
    $product = $definitions[$sku] ?? [];
    $deal = $registry['deals'][$sku] ?? [];
    $snapshot = [
      'schema' => 'famtastic.offer-contract.v1',
      'policy_version' => (string) ($registry['policy']['version'] ?? ''),
      'sku' => $sku,
      'price' => (string) ($product['price'] ?? ''),
      'currency' => strtoupper((string) ($product['currency'] ?? 'USD')),
      'scope_version' => (int) ($deal['scope_version'] ?? 0),
      'promise' => (string) ($deal['promise'] ?? $product['summary'] ?? ''),
      'deliverables' => array_values((array) ($deal['deliverables'] ?? [])),
      'included' => array_values((array) ($deal['included'] ?? $product['included'] ?? [])),
      'not_included' => array_values((array) ($deal['not_included'] ?? $product['exclusions'] ?? [])),
      'renewal' => $deal['renewal'] ?? NULL,
      'required_consents' => array_values((array) ($deal['required_consents'] ?? [])),
      'optional_consents' => array_values((array) ($deal['optional_consents'] ?? [])),
      'eligibility' => array_values((array) ($product['eligibility'] ?? [])),
      'fulfillment' => $product['fulfillment'] ?? [],
      'portal' => array_values((array) ($product['portal'] ?? [])),
      'payment' => $this->paymentEligibility->contract($product),
    ];
    $snapshot['hash'] = hash('sha256', json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    return $snapshot;
  }

  /** True only when the current organization owns an active website service. */
  private function hasActiveWebsiteEntitlement(int $organizationId): bool {
    return (bool) $this->database->select('famtastic_entitlement', 'e')
      ->condition('organization_id', $organizationId)
      ->condition('status', 'active')
      ->condition('entitlement_type', ['website_service', 'business_website_service'], 'IN')
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  /** True only when the current organization owns a non-cancelled project. */
  private function hasActiveWebsiteProject(int $organizationId): bool {
    $query = $this->database->select('famtastic_customer_resource', 'r');
    $query->join('famtastic_project', 'p', 'p.id = r.resource_id');
    return (bool) $query
      ->condition('r.organization_id', $organizationId)
      ->condition('r.resource_type', 'project')
      ->condition('p.delivery_status', 'cancelled', '<>')
      ->countQuery()
      ->execute()
      ->fetchField();
  }
}
