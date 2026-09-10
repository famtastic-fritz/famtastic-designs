<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\famtastic_pipeline\Entity\Order;

/**
 * Fail-closed payment boundary for protected staging and review runtimes.
 */
final class DisabledPaymentGateway implements PaymentGatewayInterface {

  public function createCheckoutSession(Order $order, array $context): array {
    throw new \RuntimeException('Payment is disabled in this environment.');
  }

  public function retrieveSession(string $sessionId): array {
    throw new \RuntimeException('Payment is disabled in this environment.');
  }

  public function getMode(): string {
    return 'disabled';
  }

}
