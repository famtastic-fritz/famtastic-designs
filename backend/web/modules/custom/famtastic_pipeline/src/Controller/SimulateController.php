<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Site\Settings;
use Drupal\famtastic_pipeline\Service\FulfillmentService;
use Drupal\famtastic_pipeline\Service\PipelineRepository;
use Drupal\famtastic_pipeline\Service\ProofCampaignService;
use Drupal\famtastic_pipeline\Service\StripeGateway;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Explicitly enabled local-test mode ONLY: simulates a successful test payment.
 *
 * The endpoint defaults to disabled and refuses to run when a real Stripe key
 * is present. Tests must opt in with FAMTASTIC_ALLOW_PAYMENT_SIMULATION.
 */
class SimulateController extends ControllerBase {

  public function __construct(
    protected PipelineRepository $repository,
    protected FulfillmentService $fulfillment,
    protected TimeInterface $time,
    protected ProofCampaignService $proofCampaigns,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('famtastic_pipeline.repository'),
      $container->get('famtastic_pipeline.fulfillment'),
      $container->get('datetime.time'),
      $container->get('famtastic_pipeline.proof_campaign_service'),
    );
  }

  /**
   * POST /api/pipeline/stripe/simulate.
   */
  public function handle(Request $request): JsonResponse {
    $simulationAllowed = filter_var(
      getenv('FAMTASTIC_ALLOW_PAYMENT_SIMULATION') ?: Settings::get('famtastic_allow_payment_simulation', FALSE),
      FILTER_VALIDATE_BOOL,
    );
    if (!$simulationAllowed) {
      return new JsonResponse(['ok' => FALSE, 'error' => 'simulation_disabled', 'message' => 'Payment simulation is disabled.'], 403);
    }
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
    // Stub-mode parity with the real webhook: convert the active selection.
    $converted = FALSE;
    if ($result['paid'] && ($selection = $this->proofCampaigns->activeSelection($prospect))) {
      $converted = $this->fulfillment->markProofCampaignConverted($selection['campaign_id'], $order->get('stripe_checkout_session_id')->value);
    }
    return new JsonResponse(['ok' => TRUE, 'paid' => $result['paid'], 'campaign_converted' => $converted]);
  }

}
