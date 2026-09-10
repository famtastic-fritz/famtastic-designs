<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Core\Site\Settings;

/**
 * Selects the active payment gateway.
 *
 * StripeGateway when a Stripe secret key is configured, StubGateway otherwise.
 */
class PaymentGatewayManager {

  public function __construct(
    protected StripeGateway $stripeGateway,
    protected StubGateway $stubGateway,
    protected DisabledPaymentGateway $disabledGateway,
  ) {}

  /**
   * Returns the active gateway implementation.
   */
  public function active(): PaymentGatewayInterface {
    if (Settings::get('famtastic_payment_mode') === 'disabled') {
      return $this->disabledGateway;
    }
    return StripeGateway::isConfigured() ? $this->stripeGateway : $this->stubGateway;
  }

}
