<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\famtastic_pipeline\Service\CustomerPortalService;
use Drupal\famtastic_pipeline\Service\PaymentHandoffService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/** Public handoff and authenticated organization-owner configuration endpoints. */
final class PaymentHandoffController extends ControllerBase {

  public function __construct(
    private readonly PaymentHandoffService $handoffs,
    private readonly CustomerPortalService $portal,
    private readonly AccountProxyInterface $account,
    private readonly FloodInterface $flood,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('famtastic_pipeline.payment_handoffs'),
      $container->get('famtastic_pipeline.customer_portal'),
      $container->get('current_user'),
      $container->get('flood'),
    );
  }

  /** Serves an enabled public handoff; disabled configurations are absent. */
  public function publicSnapshot(string $organization, string $site_key): JsonResponse {
    try {
      return $this->response(['ok' => TRUE] + $this->handoffs->publicSnapshot($organization, $site_key), 200, FALSE);
    }
    catch (\Throwable) {
      return $this->error('payment_handoff_unavailable', 404, FALSE);
    }
  }

  /** Records an explicit view or outbound-open action, not a purchase. */
  public function event(Request $request, string $organization, string $site_key): JsonResponse {
    if (strlen($request->getContent()) > 2048) {
      return $this->error('request_too_large', 413, FALSE);
    }
    $input = $this->json($request);
    $event = (string) ($input['event'] ?? '');
    $surface = (string) ($input['surface'] ?? '');
    // Flood storage receives only a one-way identifier; event rows themselves
    // contain no visitor, browser, address, destination, or payment detail.
    $key = 'payment-handoff:' . $organization . ':' . $site_key . ':ip:' . hash('sha256', $request->getClientIp() ?: 'unknown');
    if (!$this->flood->isAllowed('famtastic_payment_handoff_event', 30, 3600, $key)) {
      return $this->error('rate_limited', 429, FALSE);
    }
    try {
      $this->handoffs->recordEvent($organization, $site_key, $event, $surface);
      $this->flood->register('famtastic_payment_handoff_event', 3600, $key);
      return $this->response([
        'ok' => TRUE,
        'event' => $event,
        'meaning' => 'payment_handoff_' . $event . '_not_purchase',
      ], 201, FALSE);
    }
    catch (\InvalidArgumentException $error) {
      return $this->error($error->getMessage(), 422, FALSE);
    }
    catch (\Throwable) {
      return $this->error('payment_handoff_unavailable', 404, FALSE);
    }
  }

  /** GET and PUT one private configuration for the signed-in organization owner. */
  public function owner(Request $request): JsonResponse {
    $customer = $this->currentCustomer();
    if (!$customer) {
      return $this->error('authentication_required', 401);
    }
    if (empty($customer['verified_at'])) {
      return $this->error('verification_required', 403);
    }
    $organization = (string) ($request->query->get('organization') ?? '');
    if ($request->isMethod('PUT')) {
      $organization = (string) ($this->json($request)['organization'] ?? $organization);
    }
    try {
      $result = $request->isMethod('PUT')
        ? $this->handoffs->save((int) $customer['id'], $organization, $this->json($request))
        : $this->handoffs->ownerSnapshot((int) $customer['id'], $organization);
      return $this->response(['ok' => TRUE] + $result);
    }
    catch (\InvalidArgumentException $error) {
      return $this->error($error->getMessage(), 422);
    }
    catch (\Throwable) {
      // Do not expose whether another organization owns a configuration.
      return $this->error('payment_handoff_not_found', 404);
    }
  }

  private function currentCustomer(): ?array {
    return $this->account->isAuthenticated()
      ? $this->portal->customerForUid((int) $this->account->id())
      : NULL;
  }

  private function json(Request $request): array {
    $decoded = json_decode($request->getContent(), TRUE);
    return is_array($decoded) ? $decoded : [];
  }

  private function error(string $error, int $status, bool $private = TRUE): JsonResponse {
    return $this->response(['ok' => FALSE, 'error' => $error], $status, $private);
  }

  private function response(array $payload, int $status = 200, bool $private = TRUE): JsonResponse {
    $response = new JsonResponse($payload, $status);
    $response->headers->set('Cache-Control', $private ? 'private, no-store' : 'no-store');
    $response->headers->set('X-Content-Type-Options', 'nosniff');
    return $response;
  }

}
