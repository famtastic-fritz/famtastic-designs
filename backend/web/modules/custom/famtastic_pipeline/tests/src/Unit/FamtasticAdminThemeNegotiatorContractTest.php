<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\famtastic_pipeline\Theme\FamtasticAdminThemeNegotiator;
use Drupal\Tests\UnitTestCase;

/** @group famtastic_pipeline */
final class FamtasticAdminThemeNegotiatorContractTest extends UnitTestCase {

  /**
   * @dataProvider accountEntryRoutes
   */
  public function testAccountEntryRoutesUseTheReusableAdminTheme(string $route_name): void {
    $theme_handler = $this->createMock(ThemeHandlerInterface::class);
    $theme_handler->method('themeExists')->with('famtastic_admin')->willReturn(TRUE);
    $route_match = $this->createMock(RouteMatchInterface::class);
    $route_match->method('getRouteName')->willReturn($route_name);

    $negotiator = new FamtasticAdminThemeNegotiator($theme_handler);
    $this->assertTrue($negotiator->applies($route_match));
    $this->assertSame('famtastic_admin', $negotiator->determineActiveTheme($route_match));
  }

  public function testUnrelatedRoutesDoNotUseTheTheme(): void {
    $theme_handler = $this->createMock(ThemeHandlerInterface::class);
    $theme_handler->method('themeExists')->willReturn(TRUE);
    $route_match = $this->createMock(RouteMatchInterface::class);
    $route_match->method('getRouteName')->willReturn('system.admin_content');

    $negotiator = new FamtasticAdminThemeNegotiator($theme_handler);
    $this->assertFalse($negotiator->applies($route_match));
  }

  public function testMissingThemeFailsGracefully(): void {
    $theme_handler = $this->createMock(ThemeHandlerInterface::class);
    $theme_handler->method('themeExists')->with('famtastic_admin')->willReturn(FALSE);
    $route_match = $this->createMock(RouteMatchInterface::class);
    $route_match->method('getRouteName')->willReturn('user.login');

    $negotiator = new FamtasticAdminThemeNegotiator($theme_handler);
    $this->assertFalse($negotiator->applies($route_match));
    $this->assertNull($negotiator->determineActiveTheme($route_match));
  }

  public static function accountEntryRoutes(): array {
    return [
      ['user.login'],
      ['user.pass'],
      ['user.reset'],
      ['user.reset.login'],
    ];
  }

}
