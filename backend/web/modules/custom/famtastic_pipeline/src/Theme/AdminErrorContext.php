<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Theme;

use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/** Presentation only: never changes routing, access, or HTTP error status. */
final class AdminErrorContext {

  public static function applies(RouteMatchInterface $routeMatch, RequestStack $requests): bool {
    // ThemeManager passes the master route match to negotiators. For a 404
    // that match has no name; the current subrequest carries the error route.
    $routeName = $requests->getCurrentRequest()?->attributes->get('_route') ?? $routeMatch->getRouteName();
    if (!in_array($routeName, ['system.403', 'system.404'], TRUE)) {
      return FALSE;
    }
    $path = $requests->getMainRequest()?->getPathInfo() ?? '';
    return $path === '/admin' || str_starts_with($path, '/admin/');
  }

}
