<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\EventSubscriber;

use Drupal\Core\Site\Settings;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Stops payment and provider entry points in protected review environments.
 */
final class ProtectedStagingRequestSubscriber implements EventSubscriberInterface {

  /** Routes that can create or reconcile financial state. */
  private const BLOCKED_ROUTES = [
    'commerce_checkout.checkout',
    'commerce_checkout.form',
    'famtastic_pipeline.stripe_webhook',
    'famtastic_pipeline.stripe_simulate',
  ];

  public static function getSubscribedEvents(): array {
    // Routing has run by priority 32. Stop before controller or form execution.
    return [KernelEvents::REQUEST => ['blockPaymentRoutes', 31]];
  }

  public function blockPaymentRoutes(RequestEvent $event): void {
    if (!$event->isMainRequest() || Settings::get('famtastic_payment_mode') !== 'disabled') {
      return;
    }
    $route = (string) $event->getRequest()->attributes->get('_route', '');
    if (!in_array($route, self::BLOCKED_ROUTES, TRUE)) {
      return;
    }
    $event->setResponse(new JsonResponse([
      'error' => 'payment_disabled',
      'message' => 'Payment is disabled in this protected review environment.',
    ], 503));
  }

}
