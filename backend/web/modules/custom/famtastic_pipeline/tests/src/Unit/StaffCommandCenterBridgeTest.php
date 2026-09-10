<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Core\Session\AccountInterface;
use Drupal\famtastic_pipeline\Portal\StaffCommandCenterBridge;
use Drupal\Tests\UnitTestCase;

/**
 * Verifies staff command-center disclosure at the portal session boundary.
 *
 * @group famtastic_pipeline
 */
final class StaffCommandCenterBridgeTest extends UnitTestCase {

  /**
   * A Drupal account granted the exact pipeline permission gets the bridge.
   */
  public function testPermittedAccountReceivesMinimalStaffCapability(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->expects($this->once())
      ->method('hasPermission')
      ->with('administer famtastic pipeline')
      ->willReturn(TRUE);

    $payload = StaffCommandCenterBridge::enrichSession($account, ['ok' => TRUE]);

    $this->assertSame([
      'can_access_command_center' => TRUE,
      'command_center_url' => '/web/admin/famtastic',
    ], $payload['staff']);
  }

  /**
   * An ordinary customer payload has no staff capability or destination.
   */
  public function testOrdinaryCustomerReceivesNoStaffField(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->expects($this->once())
      ->method('hasPermission')
      ->with('administer famtastic pipeline')
      ->willReturn(FALSE);

    $payload = StaffCommandCenterBridge::enrichSession($account, ['ok' => TRUE]);

    $this->assertArrayNotHasKey('staff', $payload);
  }

}
