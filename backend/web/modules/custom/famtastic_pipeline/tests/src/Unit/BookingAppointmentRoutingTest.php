<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Yaml\Yaml;

/**
 * Proves the checked-in appointment routes and mutation boundary. */
final class BookingAppointmentRoutingTest extends TestCase {

  /**
   * Matches one path against one checked-in route definition. */
  private function match(string $name, array $definition, string $method, string $path): array {
    $collection = new RouteCollection();
    $collection->add($name, new Route(
      $definition['path'],
      $definition['defaults'],
      $definition['requirements'],
      $definition['options'],
      '',
      [],
      $definition['methods'],
    ));
    $context = new RequestContext();
    $context->setMethod($method);
    return (new UrlMatcher($collection, $context))->match($path);
  }

  /**
   * Proves owner reads and writes use separate method and CSRF contracts. */
  public function testOwnerAppointmentReadAndCommandRoutes(): void {
    $yaml = Yaml::parseFile(dirname(__DIR__, 3) . '/famtastic_pipeline.routing.yml');
    $path = '/api/customer/owner-sites/tighten-up-your-locs/appointments';
    $read = $yaml['famtastic_pipeline.customer_booking_owner_appointments'];
    $write = $yaml['famtastic_pipeline.customer_booking_owner_appointment_command'];
    $this->assertSame(['GET'], $read['methods']);
    $this->assertArrayNotHasKey('_csrf_request_header_token', $read['requirements']);
    $this->assertSame(['POST'], $write['methods']);
    $this->assertSame('TRUE', $write['requirements']['_csrf_request_header_token']);
    $this->assertSame('tighten-up-your-locs', $this->match('read', $read, 'GET', $path)['site_key']);
    $this->assertSame('tighten-up-your-locs', $this->match('write', $write, 'POST', $path)['site_key']);
  }

  /**
   * Proves only UUID-shaped proposal identifiers reach public controllers. */
  public function testTokenScopedProposalRoutes(): void {
    $yaml = Yaml::parseFile(dirname(__DIR__, 3) . '/famtastic_pipeline.routing.yml');
    $id = '00000000-0000-4000-8000-000000000001';
    $read = $yaml['famtastic_pipeline.booking_appointment_proposal'];
    $write = $yaml['famtastic_pipeline.booking_appointment_proposal_response'];
    $this->assertSame($id, $this->match('read', $read, 'GET', '/api/booking-appointment/' . $id)['appointment']);
    $this->assertSame($id, $this->match('write', $write, 'POST', '/api/booking-appointment/' . $id . '/respond')['appointment']);
  }

}
