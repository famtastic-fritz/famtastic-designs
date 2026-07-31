<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\famtastic_pipeline\Service\FulfillmentService;
use Drupal\famtastic_pipeline\Service\WebhookVerifier;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Receives Stripe webhooks. Signature-verified; fulfillment is idempotent.
 */
class StripeWebhookController extends ControllerBase {

  public function __construct(
    protected WebhookVerifier $verifier,
    protected FulfillmentService $fulfillment,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('famtastic_pipeline.webhook_verifier'),
      $container->get('famtastic_pipeline.fulfillment'),
    );
  }

  /**
   * POST /api/pipeline/stripe/webhook.
   */
  public function handle(Request $request): JsonResponse {
    $payload = $request->getContent();
    if (strlen($payload) > 1024 * 1024) {
      return new JsonResponse(['error' => 'request_too_large'], 413);
    }
    $sig = $request->headers->get('Stripe-Signature', '');

    if (!$this->verifier->verify($payload, $sig)) {
      $this->getLogger('famtastic_pipeline')->warning('Rejected webhook: bad signature.');
      return new JsonResponse(['error' => 'invalid_signature'], 400);
    }

    $event = json_decode($payload, TRUE);
    if (!is_array($event) || empty($event['id']) || empty($event['type'])) {
      return new JsonResponse(['error' => 'invalid_payload'], 400);
    }

    if ($event['type'] === 'checkout.session.completed') {
      $sessionObj = $event['data']['object'] ?? [];
      $sessionId = $sessionObj['id'] ?? '';
      $paymentIntent = $sessionObj['payment_intent'] ?? NULL;
      if ($sessionId === '') {
        return new JsonResponse(['error' => 'missing_session'], 400);
      }
      if (($sessionObj['payment_status'] ?? '') !== 'paid') {
        return new JsonResponse(['received' => TRUE, 'paid' => FALSE, 'reason' => 'payment_not_paid']);
      }
      $result = $this->fulfillment->markPaidBySession(
        $sessionId,
        $paymentIntent,
        $event['id'],
        isset($sessionObj['amount_total']) ? (int) $sessionObj['amount_total'] : NULL,
        isset($sessionObj['currency']) ? (string) $sessionObj['currency'] : NULL,
      );
      if (!empty($result['error'])) {
        return new JsonResponse(['received' => TRUE, 'paid' => FALSE, 'error' => $result['error']], 409);
      }
      // When the checkout session was created with a proof campaign
      // selection, mark that campaign converted (intake flow untouched).
      $campaignId = (string) ($sessionObj['metadata']['campaign_id'] ?? '');
      if ($result['paid'] && $campaignId !== '') {
        $converted = $this->fulfillment->markProofCampaignConverted($campaignId, $sessionId);
        return new JsonResponse([
          'received' => TRUE,
          'found' => $result['found'],
          'newly_processed' => $result['newly_processed'],
          'paid' => $result['paid'],
          'campaign_converted' => $converted,
        ]);
      }
      return new JsonResponse([
        'received' => TRUE,
        'found' => $result['found'],
        'newly_processed' => $result['newly_processed'],
        'paid' => $result['paid'],
      ]);
    }

    // Other event types are acknowledged and ignored.
    return new JsonResponse(['received' => TRUE, 'ignored' => $event['type']]);
  }

}
