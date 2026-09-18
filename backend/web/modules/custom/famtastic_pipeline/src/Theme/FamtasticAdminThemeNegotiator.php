<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Theme;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Theme\ThemeNegotiatorInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Uses the branded administration system on account recovery entry points.
 */
final class FamtasticAdminThemeNegotiator implements ThemeNegotiatorInterface {

  public function __construct(
    private readonly ThemeHandlerInterface $themeHandler,
    private readonly RequestStack $requests,
  ) {}

  private const ROUTES = [
    'user.login',
    'user.pass',
    'user.reset',
    'user.reset.login',
  ];

  public function applies(RouteMatchInterface $route_match): bool {
    return $this->themeHandler->themeExists('famtastic_admin')
      && (in_array($route_match->getRouteName(), self::ROUTES, TRUE)
        || AdminErrorContext::applies($route_match, $this->requests));
  }

  public function determineActiveTheme(RouteMatchInterface $route_match): ?string {
    return $this->themeHandler->themeExists('famtastic_admin')
      ? 'famtastic_admin'
      : NULL;
  }

}
