<?php
declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Yaml\Yaml;

/** Actual checked-in route YAML, not a hand-written approximation. */
final class BookingOwnerRoutingTest extends TestCase {
  private function routes(): array {
    $yaml = Yaml::parseFile(dirname(__DIR__, 3) . '/famtastic_pipeline.routing.yml');
    return [
      ['famtastic_pipeline.customer_booking_owner_request', 'request_id', '/api/customer/owner-sites/site-dffd4cb9c3aa47fd/booking-requests/', $yaml['famtastic_pipeline.customer_booking_owner_request']],
      ['famtastic_pipeline.customer_booking_owner_availability_window', 'window_id', '/api/customer/owner-sites/site-dffd4cb9c3aa47fd/availability/', $yaml['famtastic_pipeline.customer_booking_owner_availability_window']],
    ];
  }

  private function matcher(string $name, array $definition, string $method): UrlMatcher {
    $collection = new RouteCollection();
    $collection->add($name, new Route($definition['path'], $definition['defaults'], $definition['requirements'], $definition['options'], '', [], $definition['methods']));
    $context = new RequestContext();
    $context->setMethod($method);
    return new UrlMatcher($collection, $context);
  }

  public function testNumericPatchMatchesAndRetainsCsrfRequirement(): void {
    foreach ($this->routes() as [$name, $parameter, $prefix, $definition]) {
      self::assertSame('TRUE', $definition['requirements']['_csrf_request_header_token']);
      foreach (['1', '25', '123456'] as $id) {
        $match = $this->matcher($name, $definition, 'PATCH')->match($prefix . $id);
        self::assertSame($name, $match['_route']);
        self::assertSame($id, $match[$parameter]);
        self::assertSame('site-dffd4cb9c3aa47fd', $match['site_key']);
      }
    }
  }

  public function testMalformedIdentifiersDoNotMatch(): void {
    foreach ($this->routes() as [$name, $parameter, $prefix, $definition]) {
      foreach (['abc', '-1', '1.2', '1x', '1/2', '\\ddd', ''] as $id) {
        try {
          $this->matcher($name, $definition, 'PATCH')->match($prefix . $id);
          self::fail('Malformed identifier matched: ' . $name . ':' . $id);
        } catch (ResourceNotFoundException) {
          self::assertTrue(TRUE);
        }
      }
    }
  }

  public function testNonPatchMethodsAreRejected(): void {
    foreach ($this->routes() as [$name, $parameter, $prefix, $definition]) {
      foreach (['GET', 'POST', 'PUT', 'DELETE'] as $method) {
        try {
          $this->matcher($name, $definition, $method)->match($prefix . '25');
          self::fail('Unexpected method matched: ' . $method);
        } catch (MethodNotAllowedException $error) {
          self::assertSame(['PATCH'], $error->getAllowedMethods());
        }
      }
    }
  }
}
