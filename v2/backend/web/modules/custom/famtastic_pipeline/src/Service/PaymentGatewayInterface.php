<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\famtastic_pipeline\Entity\Order;

/**
 * Boundary for creating and reading a checkout session.
 *
 * Two implementations exist: StripeGateway (real, test-mode, used when a Stripe
 * secret key is present) and StubGateway (deterministic, used for the local
 * proof when no key is configured). Webhook signature verification is identical
 * regardless of which gateway created the session.
 */
interface PaymentGatewayInterface {

  /**
   * Creates a checkout session for an order.
   *
   * @param \Drupal\famtastic_pipeline\Entity\Order $order
   *   The pending order.
   * @param array $context
   *   Keys: success_url, cancel_url, customer_email, product_name.
   *
   * @return array{id:string,url:string,payment_intent:?string}
   *   The created session.
   */
  public function createCheckoutSession(Order $order, array $context): array;

  /**
   * Retrieves a session's server-side payment status.
   *
   * @return array{id:string,payment_status:string,payment_intent:?string}
   *   payment_status is 'paid' or 'unpaid'.
   */
  public function retrieveSession(string $sessionId): array;

  /**
   * Returns 'stripe' or 'stub'.
   */
  public function getMode(): string;

}
