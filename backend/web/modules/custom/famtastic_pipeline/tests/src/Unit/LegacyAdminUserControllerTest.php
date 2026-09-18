<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\famtastic_pipeline\Controller\LegacyAdminUserController;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Yaml\Yaml;

/** @group famtastic_pipeline */
final class LegacyAdminUserControllerTest extends UnitTestCase {

  public function testFixedDestinationPreservesDrupalBasePath(): void {
    $generator = $this->createMock(UrlGeneratorInterface::class);
    $generator->expects($this->once())->method('generateFromRoute')
      ->with('entity.user.collection')->willReturn('/web/admin/people');
    $container = new ContainerBuilder();
    $container->set('url_generator', $generator);
    \Drupal::setContainer($container);
    $request = Request::create('/web/admin/user?destination=https://example.org');
    $response = (new LegacyAdminUserController())->redirectToPeople($request);
    $this->assertSame('/web/admin/people', $response->getTargetUrl());
    $this->assertSame(302, $response->getStatusCode());
    $this->assertFalse($request->query->has('destination'));
    $this->assertSame(0, $response->getCacheableMetadata()->getCacheMaxAge());
  }

  public function testRouteRetainsPeoplePermissionAndOnlyAcceptsGet(): void {
    $routes = Yaml::parseFile(dirname(__DIR__, 3) . '/famtastic_pipeline.routing.yml');
    $route = $routes['famtastic_pipeline.legacy_admin_user'];
    $this->assertSame('/admin/user', $route['path']);
    $this->assertSame(['_permission' => 'administer users'], $route['requirements']);
    $this->assertSame(['GET'], $route['methods']);
    $this->assertTrue($route['options']['_admin_route']);
  }

}
