<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\EventSubscriber;

use Drupal\commerce_payment\Event\FilterPaymentGatewaysEvent;
use Drupal\famtastic_pipeline\Service\PrivatePurchaseService;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/** Rechecks scope/ownership before native checkout can initialize a provider. */
final class PrivateScopeCheckoutGuard implements EventSubscriberInterface {
  public static function getSubscribedEvents(): array {
    return [KernelEvents::REQUEST => ['request', 31],
      'commerce_payment.filter_payment_gateways' => ['gateways', -1000],
      'commerce_order.place.pre_transition' => ['place', 1000]];
  }

  public function request(RequestEvent $event): void {
    if (!$event->isMainRequest() || !preg_match('#^/checkout/(\d+)(?:/|$)#D', $event->getRequest()->getPathInfo(), $matches)) return;
    $storage = \Drupal::entityTypeManager()->getStorage('commerce_order');
    $order = $storage->loadUnchanged((int) $matches[1]);
    if (!$order || !$order->getData(PrivatePurchaseService::KEY)) {
      // Do not suppress the normal catalog checkout's later native refresh by
      // leaving our non-refreshing inspection in entity static cache.
      $storage->resetCache([(int) $matches[1]]);
      return;
    }
    try {
      $service = new PrivatePurchaseService();
      $context = $service->context(\Drupal::currentUser(), (string) $order->getData(PrivatePurchaseService::KEY)['request_public_id']);
      $service->assertReunionOrder($order, $context);
      if (!PrivatePurchaseService::checkoutEnabled()) throw new \RuntimeException('disabled');
    }
    catch (\Throwable) { throw new AccessDeniedHttpException('This private checkout is unavailable. Return to your project to review its current scope and status.'); }
  }

  public function gateways(FilterPaymentGatewaysEvent $event): void {
    if (!$event->getOrder()->getData(PrivatePurchaseService::KEY)) return;
    try {
      (new PrivatePurchaseService())->assertReunionOrder($event->getOrder());
      if (!PrivatePurchaseService::checkoutEnabled()) throw new \RuntimeException('disabled');
      $event->setPaymentGateways(array_filter($event->getPaymentGateways(), static fn($gateway): bool => $gateway->status() && in_array($gateway->getPluginId(), ['stripe', 'stripe_payment_element'], TRUE)));
    }
    catch (\Throwable) { $event->setPaymentGateways([]); }
  }

  public function place(WorkflowTransitionEvent $event): void {
    $order = $event->getEntity();
    if (!$order->getData(PrivatePurchaseService::KEY)) return;
    try {
      if (!PrivatePurchaseService::checkoutEnabled()) throw new \RuntimeException('disabled');
      (new PrivatePurchaseService())->assertReunionOrder($order);
    }
    catch (\Throwable) { throw new AccessDeniedHttpException('The private scope must be reconciled before completing this order.'); }
  }
}
