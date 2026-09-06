<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Selects the admin theme for the authenticated canonical user profile.
 */
final class UserProfileAdminRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    // Core already marks user edit/cancel forms as administrative. The
    // canonical /user/{user} route is the missing piece that caused public
    // Olivero navigation to render beneath the Drupal toolbar on mobile.
    if ($route = $collection->get('entity.user.canonical')) {
      $route->setOption('_admin_route', TRUE);
    }
  }

}
