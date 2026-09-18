<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\famtastic_pipeline\Theme\FamtasticAdminThemeNegotiator;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

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

    $negotiator = new FamtasticAdminThemeNegotiator($theme_handler, new RequestStack());
    $this->assertTrue($negotiator->applies($route_match));
    $this->assertSame('famtastic_admin', $negotiator->determineActiveTheme($route_match));
  }

  public function testUnrelatedRoutesDoNotUseTheTheme(): void {
    $theme_handler = $this->createMock(ThemeHandlerInterface::class);
    $theme_handler->method('themeExists')->willReturn(TRUE);
    $route_match = $this->createMock(RouteMatchInterface::class);
    $route_match->method('getRouteName')->willReturn('system.admin_content');

    $negotiator = new FamtasticAdminThemeNegotiator($theme_handler, new RequestStack());
    $this->assertFalse($negotiator->applies($route_match));
  }

  public function testMissingThemeFailsGracefully(): void {
    $theme_handler = $this->createMock(ThemeHandlerInterface::class);
    $theme_handler->method('themeExists')->with('famtastic_admin')->willReturn(FALSE);
    $route_match = $this->createMock(RouteMatchInterface::class);
    $route_match->method('getRouteName')->willReturn('user.login');

    $negotiator = new FamtasticAdminThemeNegotiator($theme_handler, new RequestStack());
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

  /** @dataProvider errorRoutes */
  public function testErrorScope(string $route, string $path, bool $expected): void {
    $themes = $this->createMock(ThemeHandlerInterface::class);
    $themes->method('themeExists')->willReturn(TRUE);
    $match = $this->createMock(RouteMatchInterface::class);
    $match->method('getRouteName')->willReturn($route);
    $requests = new RequestStack();
    $requests->push(Request::create($path));
    // Drupal's error renderer is a subrequest; classify the original URL.
    $requests->push(Request::create('/system/404'));
    $this->assertSame($expected, (new FamtasticAdminThemeNegotiator($themes, $requests))->applies($match));
  }

  public static function errorRoutes(): array {
    return [
      ['system.404', '/admin/missing', TRUE],
      ['system.403', '/admin/people', TRUE],
      ['system.404', '/admin', TRUE],
      ['system.404', '/administrator', FALSE],
      ['system.404', '/portal/missing', FALSE],
      ['system.403', '/customer/private', FALSE],
      ['system.404', '/missing?destination=/admin/user', FALSE],
      ['system.admin_content', '/admin/content', FALSE],
    ];
  }

}
