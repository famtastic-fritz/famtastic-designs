<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Controller;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\famtastic_pipeline\Service\CustomerPortalService;
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
    $storage = $this->entityTypeManager()->getStorage('user');
    if ($existingUsers = $storage->loadByProperties(['mail' => $email])) {
      /** @var \Drupal\user\UserInterface $existingUser */
      $existingUser = reset($existingUsers);
      $existingCustomer = $this->portal->customerForUid((int) $existingUser->id());
      if ($existingCustomer && empty($existingCustomer['verified_at'])) {
        $this->sendVerification($request, $existingCustomer);
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
    $this->sendVerification($request, $customer);
    return new JsonResponse(['ok' => TRUE, 'verification_required' => TRUE], 201);
  }

  public function verify(Request $request): JsonResponse {
    $data = $this->body($request);
    $record = $this->portal->consumeToken((string) ($data['token'] ?? ''), 'verify');
    if (!$record) return $this->error('invalid_or_expired_token', 422, 'This verification link is invalid or expired.');
    $this->portal->markVerified((int) $record['customer_id']);
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
    user_login_finalize($user);
    $this->flood->clear('famtastic_portal_login', $identifier);
    return $this->sessionPayload($customer);
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

  /** Returns the administrator-controlled public Commerce catalog. */
  public function catalog(): JsonResponse {
    $definitions = $this->productDefinitions();
    $policy = $this->dealRegistry()['policy'];
    $items = [];
    foreach ($definitions as $definition) {
      if (empty($definition['published'])) continue;
      $items[] = array_intersect_key($definition, array_flip([
        'sku', 'type', 'title', 'summary', 'price', 'currency', 'billing',
        'included', 'exclusions', 'intake_schema',
      ]));
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

    $skus = array_values(array_unique(array_filter(array_map('strval', (array) ($data['skus'] ?? [])))));
    if (!$skus || count($skus) > 12) return $this->error('invalid_cart', 422, 'Choose at least one available service.');
    $definitions = $this->productDefinitions();
    foreach ($skus as $sku) {
      if (empty($definitions[$sku]['published'])) return $this->error('product_unavailable', 422, 'One selected service is unavailable.');
    }
    if (in_array('FAM-FOOT-199', $skus, TRUE)) {
      if (!in_array((string) ($data['domain_choice'] ?? ''), ['new_domain', 'existing_domain'], TRUE)) {
        return $this->error('domain_choice_required', 422, 'Choose a new domain or connect an existing domain.');
      }
      if (empty($data['recurring_authorized'])) {
        return $this->error('renewal_authorization_required', 422, 'Authorize the disclosed month-13 hosting renewal or contact us for a non-renewing arrangement.');
      }
    }
    $terms = $this->dealRegistry();
    if (empty($data['accept_terms']) || (string) ($data['terms_version'] ?? '') !== (string) $terms['policy']['version']) {
      return $this->error('terms_required', 422, 'Accept the current purchase and renewal terms.');
    }

    $storeIds = $this->entityTypeManager()->getStorage('commerce_store')->getQuery()->accessCheck(FALSE)->condition('status', 1)->range(0, 1)->execute();
    if (!$storeIds) return $this->error('store_unavailable', 503, 'Checkout is temporarily unavailable.');
    $items = [];
    foreach ($skus as $sku) {
      $variationIds = $this->entityTypeManager()->getStorage('commerce_product_variation')->getQuery()->accessCheck(FALSE)
        ->condition('sku', $sku)->condition('status', 1)->range(0, 1)->execute();
      /** @var \Drupal\commerce_product\Entity\ProductVariationInterface|null $variation */
      $variation = $variationIds ? ProductVariation::load(reset($variationIds)) : NULL;
      if (!$variation) return $this->error('product_unavailable', 422, 'One selected service is unavailable.');
      $item = OrderItem::create([
        'type' => $variation->getOrderItemTypeId(), 'purchased_entity' => $variation, 'quantity' => 1,
        'unit_price' => $variation->getPrice(), 'title' => $variation->getTitle(),
      ]);
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
      'captured_at' => gmdate(DATE_ATOM),
    ]);
    $order->save();
    return $this->noStore(new JsonResponse([
      'ok' => TRUE, 'order_id' => (int) $order->id(),
      'checkout_url' => $request->getSchemeAndHttpHost() . '/web/checkout/' . $order->id(),
    ], 201));
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

  private function sendVerification(Request $request, array $customer): void {
    $token = $this->portal->issueToken((int) $customer['id'], (string) $customer['email'], 'verify');
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
}
