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
    return [KernelEvents::REQUEST => [
      // Native Commerce can deny or convert an order while routing. Refuse its
      // public checkout path one priority earlier so no access checker, form,
      // or controller can become the first staging-side payment boundary.
      ['blockNativeCheckoutPath', 33],
      // Routing has run by priority 32. Stop named provider routes before
      // controller or form execution.
      ['blockPaymentRoutes', 31],
    ]];
  }

  public function blockNativeCheckoutPath(RequestEvent $event): void {
    if (!$event->isMainRequest() || Settings::get('famtastic_payment_mode') !== 'disabled') {
      return;
    }
    if (preg_match('#^/checkout(?:/|$)#', $event->getRequest()->getPathInfo()) !== 1) {
      return;
    }
    $event->setResponse($this->paymentDisabledResponse());
  }

  public function blockPaymentRoutes(RequestEvent $event): void {
    if (!$event->isMainRequest() || Settings::get('famtastic_payment_mode') !== 'disabled') {
      return;
    }
    $route = (string) $event->getRequest()->attributes->get('_route', '');
    if (!in_array($route, self::BLOCKED_ROUTES, TRUE)) {
      return;
    }
    $event->setResponse($this->paymentDisabledResponse());
  }

  private function paymentDisabledResponse(): JsonResponse {
    return new JsonResponse([
      'error' => 'payment_disabled',
      'message' => 'Payment is disabled in this protected review environment.',
    ], 503);
  }

}
