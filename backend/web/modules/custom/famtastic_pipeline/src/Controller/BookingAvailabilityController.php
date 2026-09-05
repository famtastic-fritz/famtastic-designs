<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\famtastic_pipeline\Service\BookingAvailabilityService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/** Availability invitations for one-owner appointment businesses. */
final class BookingAvailabilityController extends ControllerBase {

  public function __construct(
    private readonly BookingAvailabilityService $availability,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('famtastic_pipeline.booking_availability'),
      $container->get('config.factory'),
    );
  }

  public function publicWindows(string $site_key): JsonResponse {
    if (!$this->enabled($site_key)) {
      return $this->response(['ok' => FALSE, 'error' => 'availability_not_enabled'], 404);
    }
    try {
      return $this->response(['ok' => TRUE] + $this->availability->publicWindows($site_key));
    }
    catch (\InvalidArgumentException $error) {
      return $this->response(['ok' => FALSE, 'error' => $error->getMessage()], 422);
    }
  }

  public function ownerWindows(Request $request, string $site_key): JsonResponse {
    try {
      if ($request->isMethod('POST')) {
        return $this->response(['ok' => TRUE, 'window' => $this->availability->create($site_key, $this->json($request))], 201);
      }
      return $this->response(['ok' => TRUE] + $this->availability->ownerWindows($site_key));
    }
    catch (\InvalidArgumentException $error) {
      return $this->response(['ok' => FALSE, 'error' => $error->getMessage()], 422);
    }
  }

  public function updateWindow(Request $request, string $site_key, int $window_id): JsonResponse {
    try {
      return $this->response(['ok' => TRUE, 'window' => $this->availability->update($site_key, $window_id, $this->json($request))]);
    }
    catch (\InvalidArgumentException $error) {
      return $this->response(['ok' => FALSE, 'error' => $error->getMessage()], 422);
    }
    catch (\RuntimeException $error) {
      return $this->response(['ok' => FALSE, 'error' => $error->getMessage()], 404);
    }
  }

  private function enabled(string $siteKey): bool {
    $enabled = $this->configFactory->get('famtastic_pipeline.settings')->get('booking_availability_enabled_sites') ?: [];
    return in_array($siteKey, is_array($enabled) ? $enabled : [], TRUE);
  }

  private function json(Request $request): array {
    $decoded = json_decode($request->getContent(), TRUE);
    return is_array($decoded) ? $decoded : [];
  }

  private function response(array $payload, int $status = 200): JsonResponse {
    $response = new JsonResponse($payload, $status);
    $response->headers->set('Cache-Control', 'private, no-store');
    $response->headers->set('X-Content-Type-Options', 'nosniff');
    return $response;
  }

}
