<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Flood\FloodInterface;
use Drupal\famtastic_pipeline\Service\BookingAppointmentService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Token-scoped customer response to one owner-proposed appointment time.
 */
final class BookingAppointmentController extends ControllerBase {

  public function __construct(
    private readonly BookingAppointmentService $appointments,
    private readonly FloodInterface $flood,
  ) {}

  /**
   * Creates the controller from Drupal services.
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('famtastic_pipeline.booking_appointments'), $container->get('flood'));
  }

  /**
   * Saves one token-authorized proposal response.
   */
  public function respond(Request $request, string $appointment): JsonResponse {
    $key = 'appointment-proposal:' . hash('sha256', $appointment . ':' . ($request->getClientIp() ?: 'unknown'));
    if (!$this->flood->isAllowed('famtastic_booking_proposal', 10, 3600, $key)) {
      return $this->response(['ok' => FALSE, 'error' => 'rate_limited'], 429);
    }
    $input = json_decode($request->getContent(), TRUE);
    $input = is_array($input) ? $input : [];
    try {
      $result = $this->appointments->respondToProposal(
        $appointment,
        (string) ($input['token'] ?? ''),
        (string) ($input['decision'] ?? ''),
      );
      $this->flood->register('famtastic_booking_proposal', 3600, $key);
      return $this->response(['ok' => TRUE, 'appointment' => $result]);
    }
    catch (\InvalidArgumentException $error) {
      return $this->response(['ok' => FALSE, 'error' => $error->getMessage()], 422);
    }
    catch (\RuntimeException $error) {
      $status = $error->getMessage() === 'appointment_slot_conflict' ? 409 : 404;
      return $this->response(['ok' => FALSE, 'error' => $error->getMessage()], $status);
    }
  }

  /**
   * Returns minimum proposal details after token verification.
   */
  public function proposal(Request $request, string $appointment): JsonResponse {
    try {
      return $this->response([
        'ok' => TRUE,
        'appointment' => $this->appointments->proposalSnapshot($appointment, (string) $request->query->get('token', '')),
      ]);
    }
    catch (\InvalidArgumentException $error) {
      return $this->response(['ok' => FALSE, 'error' => $error->getMessage()], 422);
    }
    catch (\RuntimeException $error) {
      return $this->response(['ok' => FALSE, 'error' => $error->getMessage()], 404);
    }
  }

  /**
   * Builds a non-cacheable JSON response.
   */
  private function response(array $payload, int $status = 200): JsonResponse {
    $response = new JsonResponse($payload, $status);
    $response->headers->set('Cache-Control', 'private, no-store');
    $response->headers->set('X-Content-Type-Options', 'nosniff');
    return $response;
  }

}
