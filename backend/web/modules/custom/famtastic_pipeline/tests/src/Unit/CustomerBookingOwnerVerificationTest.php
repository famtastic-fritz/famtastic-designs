<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Database\StatementInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\famtastic_pipeline\Controller\CustomerBookingOwnerController;
use Drupal\famtastic_pipeline\Service\BookingSiteOwnerService;
use Drupal\famtastic_pipeline\Service\CustomerPortalService;
use Drupal\Tests\UnitTestCase;

// The shared vendor/bootstrap may belong to the canonical checkout. Exercise
// this worktree's controller explicitly, never an older autoloaded copy.
require_once dirname(__DIR__, 3) . '/src/Controller/CustomerBookingOwnerController.php';

/** Exercises real final services without mocking away the authorization gate. */
#[\PHPUnit\Framework\Attributes\Group('famtastic_pipeline')]
final class CustomerBookingOwnerVerificationTest extends UnitTestCase {

  public function testUnverifiedCustomerNeverReadsOwnerBinding(): void {
    foreach ([NULL, 0, '', FALSE] as $verification) {
      $controller = $this->controller(['id' => 11, 'verified_at' => $verification], FALSE);
      $response = $controller->requests('site-fixture');
      $this->assertSame(404, $response->getStatusCode());
      $this->assertSame(['ok' => FALSE, 'error' => 'booking_owner_access_denied'], json_decode((string) $response->getContent(), TRUE));
      $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }
  }

  public function testMissingCustomerNeverReadsOwnerBinding(): void {
    $response = $this->controller(FALSE, FALSE)->requests('site-fixture');
    $this->assertSame(404, $response->getStatusCode());
  }

  public function testVerifiedCustomerStillRequiresExactActiveBinding(): void {
    $controller = $this->controller(['id' => 11, 'verified_at' => 1700000000], TRUE, FALSE);
    $this->assertSame(404, $controller->requests('site-fixture')->getStatusCode());
  }

  public function testVerifiedCustomerWithExactBindingAndMembershipPassesGate(): void {
    $controller = $this->controller(['id' => 11, 'verified_at' => 1700000000], TRUE, TRUE);
    // Inspect the shared gate so every read and mutation keeps using the same
    // verification + ownership check without invoking unrelated capture logic.
    $method = new \ReflectionMethod($controller, 'authorize');
    $this->assertNull($method->invoke($controller, 'site-fixture'));
  }

  private function controller(array|false $customer, bool $expectBinding, bool $bound = FALSE): CustomerBookingOwnerController {
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('isAuthenticated')->willReturn(TRUE);
    $account->method('id')->willReturn(42);
    $customerDb = $this->createMock(Connection::class);
    $customerQuery = $this->query($customer);
    $customerDb->expects($this->once())->method('select')->with('famtastic_customer', 'c')->willReturn($customerQuery);
    $customerQuery->expects($this->once())->method('condition')->with('uid', 42)->willReturnSelf();
    $portal = (new \ReflectionClass(CustomerPortalService::class))->newInstanceWithoutConstructor();
    (new \ReflectionProperty($portal, 'database'))->setValue($portal, $customerDb);

    $ownerDb = $this->createMock(Connection::class);
    if (!$expectBinding) {
      $ownerDb->expects($this->never())->method('select');
    }
    else {
      $binding = $this->query($bound ? ['customer_id' => 11, 'organization_id' => 7, 'site_key' => 'site-fixture'] : FALSE);
      $conditions = [];
      $binding->method('condition')->willReturnCallback(function ($field, $value) use (&$conditions, $binding) {
        $conditions[$field] = $value;
        return $binding;
      });
      $member = $this->query(FALSE, 11);
      $member->method('condition')->willReturnSelf();
      $ownerDb->expects($this->exactly($bound ? 2 : 1))->method('select')->willReturnCallback(function ($table) use ($binding, $member, &$conditions) {
        if ($table === 'famtastic_booking_site_owner') return $binding;
        $this->assertSame(['site_key' => 'site-fixture', 'customer_id' => 11, 'status' => 'active'], $conditions);
        $this->assertSame('famtastic_membership', $table);
        return $member;
      });
    }
    $owners = (new \ReflectionClass(BookingSiteOwnerService::class))->newInstanceWithoutConstructor();
    (new \ReflectionProperty($owners, 'database'))->setValue($owners, $ownerDb);
    $controller = (new \ReflectionClass(CustomerBookingOwnerController::class))->newInstanceWithoutConstructor();
    foreach (['account' => $account, 'portal' => $portal, 'owners' => $owners] as $property => $value) {
      (new \ReflectionProperty($controller, $property))->setValue($controller, $value);
    }
    return $controller;
  }

  private function query(array|false $row, mixed $field = FALSE): SelectInterface {
    $statement = $this->createMock(StatementInterface::class);
    $statement->method('fetchAssoc')->willReturn($row);
    $statement->method('fetchField')->willReturn($field);
    $query = $this->createMock(SelectInterface::class);
    $query->method('fields')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn($statement);
    return $query;
  }

}
