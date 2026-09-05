<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\famtastic_pipeline\Service\BookingAvailabilityService;
use Drupal\famtastic_pipeline\Service\BookingRequestService;
use Drupal\famtastic_pipeline\Service\BookingSiteOwnerService;
use Drupal\famtastic_pipeline\Service\CustomerPortalService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/** Authenticated, exact-owner APIs for a client's mobile booking command center. */
final class CustomerBookingOwnerController extends ControllerBase {

  public function __construct(
    private readonly AccountProxyInterface $account,
    private readonly CustomerPortalService $portal,
    private readonly BookingSiteOwnerService $owners,
    private readonly BookingRequestService $requests,
    private readonly BookingAvailabilityService $availability,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('current_user'),
      $container->get('famtastic_pipeline.customer_portal'),
      $container->get('famtastic_pipeline.booking_site_owners'),
      $container->get('famtastic_pipeline.booking_requests'),
      $container->get('famtastic_pipeline.booking_availability'),
    );
  }

  /** GET the current customer's own request inbox for one bound site. */
  public function requests(string $site_key): JsonResponse {
    try {
      $this->authorize($site_key);
      return $this->response(['ok' => TRUE] + $this->requests->ownerSnapshot($site_key));
    }
    catch (\InvalidArgumentException $error) {
      return $this->response(['ok' => FALSE, 'error' => $error->getMessage()], 422);
    }
    catch (\RuntimeException) {
      return $this->response(['ok' => FALSE, 'error' => 'booking_owner_access_denied'], 404);
    }
  }

  /** PATCH workflow state for a request that belongs to the current customer site. */
  public function updateRequest(Request $request, string $site_key, int $request_id): JsonResponse {
    try {
      $this->authorize($site_key);
      $input = $this->json($request);
      $this->requests->updateStatus($site_key, $request_id, (string) ($input['status'] ?? ''));
      return $this->response(['ok' => TRUE, 'status' => (string) ($input['status'] ?? '')]);
    }
    catch (\InvalidArgumentException $error) {
      return $this->response(['ok' => FALSE, 'error' => $error->getMessage()], 422);
    }
    catch (\RuntimeException $error) {
      return $this->response(['ok' => FALSE, 'error' => $error->getMessage() === 'booking_request_not_found' ? 'booking_request_not_found' : 'booking_owner_access_denied'], 404);
    }
  }

  /** GET and POST availability windows for the exact signed-in site owner. */
  public function availability(Request $request, string $site_key): JsonResponse {
    try {
      $this->authorize($site_key);
      if ($request->isMethod('POST')) {
        return $this->response(['ok' => TRUE, 'window' => $this->availability->create($site_key, $this->json($request))], 201);
      }
      return $this->response(['ok' => TRUE] + $this->availability->ownerWindows($site_key));
    }
    catch (\InvalidArgumentException $error) {
      return $this->response(['ok' => FALSE, 'error' => $error->getMessage()], 422);
    }
    catch (\RuntimeException) {
      return $this->response(['ok' => FALSE, 'error' => 'booking_owner_access_denied'], 404);
    }
  }

  /** PATCH one availability window scoped to the exact signed-in site owner. */
  public function updateAvailability(Request $request, string $site_key, int $window_id): JsonResponse {
    try {
      $this->authorize($site_key);
      return $this->response(['ok' => TRUE, 'window' => $this->availability->update($site_key, $window_id, $this->json($request))]);
    }
    catch (\InvalidArgumentException $error) {
      return $this->response(['ok' => FALSE, 'error' => $error->getMessage()], 422);
    }
    catch (\RuntimeException $error) {
      return $this->response(['ok' => FALSE, 'error' => $error->getMessage() === 'availability_window_not_found' ? 'availability_window_not_found' : 'booking_owner_access_denied'], 404);
    }
  }

  private function authorize(string $siteKey): void {
    $customer = $this->account->isAuthenticated() ? $this->portal->customerForUid((int) $this->account->id()) : NULL;
    if (!$customer) {
      throw new \RuntimeException('booking_owner_access_denied');
    }
    $this->owners->requireCustomerOwner((int) $customer['id'], $siteKey);
  }

  private function json(Request $request): array {
    $decoded = json_decode($request->getContent(), TRUE);
    return is_array($decoded) ? $decoded : [];
  }

  private function response(array $payload, int $status = 200): JsonResponse {
    $response = new JsonResponse($payload, $status);
    $response->headers->set('Cache-Control', 'no-store, private');
    $response->headers->set('X-Content-Type-Options', 'nosniff');
    return $response;
  }

}
