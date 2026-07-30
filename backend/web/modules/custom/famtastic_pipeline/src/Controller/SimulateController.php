<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\famtastic_pipeline\Service\FulfillmentService;
use Drupal\famtastic_pipeline\Service\PipelineRepository;
use Drupal\famtastic_pipeline\Service\StripeGateway;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Stub-mode ONLY: simulates a successful test payment.
 *
 * When no Stripe key is configured, the UI has no real Stripe to redirect to,
 * so this clearly-labeled test control drives the exact same fulfillment code
 * path the webhook uses. It refuses to run when a real Stripe key is present.
 */
class SimulateController extends ControllerBase {

  public function __construct(
    protected PipelineRepository $repository,
    protected FulfillmentService $fulfillment,
    protected TimeInterface $time,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('famtastic_pipeline.repository'),
      $container->get('famtastic_pipeline.fulfillment'),
      $container->get('datetime.time'),
    );
  }

  /**
   * POST /api/pipeline/stripe/simulate.
   */
  public function handle(Request $request): JsonResponse {
    if (StripeGateway::isConfigured()) {
      return new JsonResponse(['ok' => FALSE, 'error' => 'stub_only', 'message' => 'Simulation is disabled when a real Stripe key is configured.'], 403);
    }
    $token = $request->headers->get('X-Prospect-Token', '') ?: (string) $request->query->get('token', '');
    $prospect = $this->repository->loadProspectByToken(trim($token));
    if (!$prospect) {
      return new JsonResponse(['ok' => FALSE, 'error' => 'invalid_or_expired_token'], 404);
    }
    $order = $this->repository->getOrder($prospect);
    if (!$order || !$order->get('stripe_checkout_session_id')->value) {
      return new JsonResponse(['ok' => FALSE, 'error' => 'no_checkout'], 409);
    }
    $eventId = 'evt_stub_' . $order->id() . '_' . $this->time->getRequestTime();
    $result = $this->fulfillment->markPaidBySession(
      $order->get('stripe_checkout_session_id')->value,
      'pi_test_stub_' . $order->id(),
      $eventId,
    );
    return new JsonResponse(['ok' => TRUE, 'paid' => $result['paid']]);
  }

}
