<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

/**
 * Selects the active payment gateway.
 *
 * StripeGateway when a Stripe secret key is configured, StubGateway otherwise.
 */
class PaymentGatewayManager {

  public function __construct(
    protected StripeGateway $stripeGateway,
    protected StubGateway $stubGateway,
  ) {}

  /**
   * Returns the active gateway implementation.
   */
  public function active(): PaymentGatewayInterface {
    return StripeGateway::isConfigured() ? $this->stripeGateway : $this->stubGateway;
  }

}
