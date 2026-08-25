<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\EventSubscriber;

use Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Returns the customer to the portal after checkout completes.
 *
 * The Drupal completion page is an operational dead end: the customer's
 * home is the portal (services, receipts, messages). Redirect the moment
 * checkout completes so the purchase ends inside the brand experience.
 */
final class CheckoutCompleteRedirectSubscriber implements EventSubscriberInterface {

  public static function getSubscribedEvents(): array {
    // Before routing/access checks so the redirect always wins.
    return [KernelEvents::REQUEST => ['redirectComplete', 150]];
  }

  public function redirectComplete(RequestEvent $event): void {
    if (!$event->isMainRequest() || $event->getRequest()->getMethod() !== 'GET') {
      return;
    }
    $path = $event->getRequest()->getPathInfo();
    if (preg_match('#^/checkout/(\d+)/complete$#', $path, $m) === 1) {
      $event->setResponse(new TrustedRedirectResponse('/portal?order=' . $m[1] . '&completed=1'));
    }
  }

}
