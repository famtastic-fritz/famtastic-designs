<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\EventSubscriber;

use Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Redirects the Drupal front page to the SPA homepage.
 *
 * The React SPA owns the public site root. The Drupal front page (/web/ for
 * visitors) is bare backend chrome and must never be a landing surface —
 * the .htaccess rule is best-effort; this subscriber is the guarantee.
 * Admin, API, checkout, and user routes are unaffected (they are not the
 * front path).
 */
final class FrontPageRedirectSubscriber implements EventSubscriberInterface {

  private const SPA_HOME = 'https://famtasticdesigns.com/';

  public static function getSubscribedEvents(): array {
    // Run before routing so we never render the bare front page.
    return [KernelEvents::REQUEST => ['redirectFrontPage', 100]];
  }

  public function redirectFrontPage(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }
    $request = $event->getRequest();
    if ($request->getPathInfo() !== '/' || $request->getMethod() !== 'GET') {
      return;
    }
    // Never intercept Drush/CLI or install contexts.
    if (PHP_SAPI === 'cli') {
      return;
    }
    $event->setResponse(new TrustedRedirectResponse(self::SPA_HOME));
  }

}
