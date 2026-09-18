<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Theme;

use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/** Presentation only: never changes routing, access, or HTTP error status. */
final class AdminErrorContext {

  public static function applies(RouteMatchInterface $routeMatch, RequestStack $requests): bool {
    if (!in_array($routeMatch->getRouteName(), ['system.403', 'system.404'], TRUE)) {
      return FALSE;
    }
    $path = $requests->getMainRequest()?->getPathInfo() ?? '';
    return $path === '/admin' || str_starts_with($path, '/admin/');
  }

}
