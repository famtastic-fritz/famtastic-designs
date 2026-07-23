<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\famtastic_pipeline\Entity\Order;

/**
 * Deterministic stub gateway for the local proof (no Stripe key configured).
 *
 * Produces a fake test-mode session id and sends the buyer straight to the
 * success URL. Payment is NOT auto-confirmed here — it is confirmed exactly the
 * same way as real Stripe: through the webhook/fulfillment code path. In the UI,
 * a clearly-labeled "simulate payment" control drives that path; in the E2E
 * test, a correctly HMAC-signed webhook does.
 */
class StubGateway implements PaymentGatewayInterface {

  /**
   * {@inheritdoc}
   */
  public function getMode(): string {
    return 'stub';
  }

  /**
   * {@inheritdoc}
   */
  public function createCheckoutSession(Order $order, array $context): array {
    $sessionId = 'cs_test_stub_' . $order->id() . '_' . substr(hash('sha256', (string) $order->uuid()), 0, 16);
    return [
      'id' => $sessionId,
      'url' => $context['success_url'],
      'payment_intent' => 'pi_test_stub_' . $order->id(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function retrieveSession(string $sessionId): array {
    // The database order is the source of truth in stub mode.
    return [
      'id' => $sessionId,
      'payment_status' => 'unpaid',
      'payment_intent' => NULL,
    ];
  }

}
