<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Controller;

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
      $this->notifyCustomerMessage($customer, $thread, $data, TRUE);
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
      $threadRecord = $this->portal->thread((int) $customer['id'], $thread)['thread'];
      $this->notifyCustomerMessage($customer, $threadRecord, $data, FALSE);
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

  /**
   * Alerts staff about portal messages and confirms newly opened requests.
   */
  private function notifyCustomerMessage(array $customer, array $thread, array $data, bool $opened): void {
    $subject = trim(strip_tags((string) ($thread['subject'] ?? $data['subject'] ?? 'Customer support request')));
    $body = trim(strip_tags((string) ($data['body'] ?? '')));
    $recipient = (string) ($this->portalConfigFactory->get('famtastic_pipeline.settings')->get('notification_to_email') ?: 'hello@famtasticdesigns.com');
    try {
      $this->mailer->send(
        $recipient,
        sprintf('Portal %s: %s', $opened ? 'request opened' : 'customer reply', $subject),
        "Customer: " . ($customer['display_name'] ?: $customer['email']) . "\nEmail: " . $customer['email'] . "\n\n" . $body . "\n\nOpen support: https://famtasticdesigns.com/web/admin/famtastic/metric/support",
      );
      if ($opened) {
        $this->mailer->send(
          (string) $customer['email'],
          'We received your support request — FAMtastic Designs',
          "Your request \"{$subject}\" is recorded in your customer portal. We will reply there and notify you by email.\n\nFAMtastic Designs",
        );
      }
    }
    catch (\Throwable $error) {
      $this->logger->error('Portal message notification failed for customer @customer: @error', [
        '@customer' => $customer['public_id'] ?? 'unknown',
        '@error' => $error->getMessage(),
      ]);
    }
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
}
