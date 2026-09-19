<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\EventSubscriber;

use Drupal\commerce_payment\Event\FilterPaymentGatewaysEvent;
use Drupal\commerce_order\Entity\OrderInterface;
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
    if (!$order || !$this->applies($order)) {
      // Do not suppress the normal catalog checkout's later native refresh by
      // leaving our non-refreshing inspection in entity static cache.
      $storage->resetCache([(int) $matches[1]]);
      return;
    }
    try {
      $service = new PrivatePurchaseService();
      $context = $service->context(\Drupal::currentUser(), (string) ($order->getData(PrivatePurchaseService::KEY)['request_public_id'] ?? ''));
      $service->assertReunionOrder($order, $context);
      $this->assertSavedGateway($order);
      if (!PrivatePurchaseService::checkoutEnabled()) throw new \RuntimeException('disabled');
    }
    catch (\Throwable) { throw new AccessDeniedHttpException('This private checkout is unavailable. Return to your project to review its current scope and status.'); }
  }

  public function gateways(FilterPaymentGatewaysEvent $event): void {
    if (!$this->applies($event->getOrder())) return;
    try {
      if (!PrivatePurchaseService::checkoutEnabled()) throw new \RuntimeException('disabled');
      $service = new PrivatePurchaseService();
      $context = $service->context(\Drupal::currentUser(), PrivatePurchaseService::REUNION);
      $service->assertReunionOrder($event->getOrder(), $context);
      $this->assertSavedGateway($event->getOrder());
      $event->setPaymentGateways(array_filter($event->getPaymentGateways(), static fn($gateway): bool => $gateway->status() && in_array($gateway->getPluginId(), ['stripe', 'stripe_payment_element'], TRUE)));
    }
    catch (\Throwable) { $event->setPaymentGateways([]); }
  }

  public function place(WorkflowTransitionEvent $event): void {
    $order = $event->getEntity();
    if (!$this->applies($order)) return;
    try {
      if (!PrivatePurchaseService::checkoutEnabled()) throw new \RuntimeException('disabled');
      (new PrivatePurchaseService())->assertReunionOrder($order);
      $this->assertSavedGateway($order);
    }
    catch (\Throwable) { throw new AccessDeniedHttpException('The private scope must be reconciled before completing this order.'); }
  }

  /** A resumed payment must not use a stale or disabled saved gateway. */
  private function assertSavedGateway(OrderInterface $order): void {
    if (!$order->hasField('payment_gateway')) return;
    $id = (string) ($order->get('payment_gateway')->target_id ?? '');
    if ($id === '') return; // Initial checkout has not selected a gateway yet.
    $gateway = \Drupal::entityTypeManager()->getStorage('commerce_payment_gateway')->loadUnchanged($id);
    if (!$gateway || !$gateway->status() || !in_array($gateway->getPluginId(), ['stripe', 'stripe_payment_element'], TRUE)) {
      throw new \RuntimeException('private_saved_gateway_unavailable');
    }
  }

  /** Removing order metadata must not bypass the durable private-offer guard. */
  private function applies(OrderInterface $order): bool {
    return $order->getData(PrivatePurchaseService::KEY) !== NULL || ($order->id()
      && (bool) \Drupal::database()->select('famtastic_private_offer', 'o')
        ->condition('website_request_id', 16)->condition('commerce_order_id', (int) $order->id())
        ->countQuery()->execute()->fetchField());
  }
}
