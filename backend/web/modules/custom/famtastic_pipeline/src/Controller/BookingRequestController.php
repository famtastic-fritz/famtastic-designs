<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Flood\FloodInterface;
use Drupal\famtastic_pipeline\Service\BookingRequestService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/** Public capture and operator workflow for owner-managed booking requests. */
final class BookingRequestController extends ControllerBase {

  public function __construct(
    private readonly BookingRequestService $requests,
    private readonly FloodInterface $flood,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('famtastic_pipeline.booking_requests'),
      $container->get('flood'),
      $container->get('config.factory'),
    );
  }

  /** POST /api/booking-request/{site_key}. */
  public function capture(Request $request, string $site_key): JsonResponse {
    if (!$this->enabled($site_key)) {
      return $this->response(['ok' => FALSE, 'error' => 'booking_requests_not_enabled'], 409);
    }
    if (strlen($request->getContent()) > 16384) {
      return $this->response(['ok' => FALSE, 'error' => 'request_too_large'], 413);
    }
    $input = json_decode($request->getContent(), TRUE);
    $input = is_array($input) ? $input : [];
    // Quiet success gives spambots no field-validation oracle.
    if (trim((string) ($input['website'] ?? '')) !== '') {
      return $this->response(['ok' => TRUE, 'status' => 'received']);
    }
    $email = mb_strtolower(trim((string) ($input['email'] ?? '')));
    $ipKey = 'booking-request:' . $site_key . ':ip:' . ($request->getClientIp() ?: 'unknown');
    $emailKey = 'booking-request:' . $site_key . ':email:' . hash('sha256', $email);
    if (!$this->flood->isAllowed('famtastic_booking_request', 10, 3600, $ipKey) || !$this->flood->isAllowed('famtastic_booking_request', 4, 3600, $emailKey)) {
      return $this->response(['ok' => FALSE, 'error' => 'rate_limited'], 429);
    }
    try {
      $result = $this->requests->create($site_key, $input, (string) ($input['source'] ?? 'site'));
      $this->flood->register('famtastic_booking_request', 3600, $ipKey);
      $this->flood->register('famtastic_booking_request', 3600, $emailKey);
      return $this->response(['ok' => TRUE] + $result);
    }
    catch (\InvalidArgumentException $error) {
      return $this->response(['ok' => FALSE, 'error' => $error->getMessage()], 422);
    }
    catch (\Throwable) {
      return $this->response(['ok' => FALSE, 'error' => 'booking_request_unavailable'], 503);
    }
  }

  /** GET operator inbox. */
  public function owner(string $site_key): JsonResponse {
    try {
      return $this->response(['ok' => TRUE] + $this->requests->ownerSnapshot($site_key));
    }
    catch (\InvalidArgumentException $error) {
      return $this->response(['ok' => FALSE, 'error' => $error->getMessage()], 422);
    }
  }

  /** PATCH owner workflow state. */
  public function updateStatus(Request $request, string $site_key, int $request_id): JsonResponse {
    $input = json_decode($request->getContent(), TRUE);
    $input = is_array($input) ? $input : [];
    try {
      $this->requests->updateStatus($site_key, $request_id, (string) ($input['status'] ?? ''));
      return $this->response(['ok' => TRUE, 'status' => (string) $input['status']]);
    }
    catch (\InvalidArgumentException $error) {
      return $this->response(['ok' => FALSE, 'error' => $error->getMessage()], 422);
    }
    catch (\RuntimeException $error) {
      return $this->response(['ok' => FALSE, 'error' => $error->getMessage()], 404);
    }
  }

  private function enabled(string $siteKey): bool {
    $enabled = $this->configFactory->get('famtastic_pipeline.settings')->get('booking_request_enabled_sites') ?: [];
    return in_array($siteKey, is_array($enabled) ? $enabled : [], TRUE);
  }

  private function response(array $payload, int $status = 200): JsonResponse {
    $response = new JsonResponse($payload, $status);
    $response->headers->set('Cache-Control', 'private, no-store');
    $response->headers->set('X-Content-Type-Options', 'nosniff');
    return $response;
  }

}
