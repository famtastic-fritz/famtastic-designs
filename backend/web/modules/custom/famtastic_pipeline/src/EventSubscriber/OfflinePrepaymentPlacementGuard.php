<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\EventSubscriber;

use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** Prevents native checkout from treating receipt of money as site acceptance. */
final class OfflinePrepaymentPlacementGuard implements EventSubscriberInterface {
  public static function getSubscribedEvents(): array {
    return ['commerce_order.place.pre_transition' => ['guard', 1000]];
  }

  public function guard(WorkflowTransitionEvent $event): void {
    if ($event->getEntity()->getData('famtastic_offline_prepayment')) {
      throw new AccessDeniedHttpException('This payment is already recorded. Use the private completion process; final acceptance and launch readiness remain separate.');
    }
  }
}
