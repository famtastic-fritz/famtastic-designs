<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Tests\UnitTestCase;

/** @group famtastic_pipeline */
final class PublicPreviewDeliveryRevokeRaceGuardTest extends UnitTestCase {

  /**
   * Preserve the compare-and-swap contract between exact-ID dispatch and
   * revoke. A stale revoke must never rewrite a room after dispatch owns its
   * outbox, and a held verified-cold commercial snapshot must be cancelled
   * with the same transaction as the delivery state change.
   */
  public function testRevokeClaimsOnlyItsExactHeldOutboxBeforeRoomMutation(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Service/PublicPreviewDeliveryService.php');
    $this->assertIsString($source);
    $start = strpos($source, 'public function revoke');
    $end = strpos($source, 'private function recordDispatchAccepted', $start ?: 0);
    $method = substr($source, (int) $start, (int) $end - (int) $start);

    $heldClaim = strpos($method, "->condition('status', 'held')->execute()");
    $commercialRevoke = strpos($method, '$this->coldMessages->revoke($deliveryId');
    $deliveryCas = strpos($method, ")->condition('id', \$deliveryId)->condition('state', \$state)->execute()");
    $dispatchRace = strrpos($method, "=== 'email_dispatching'");
    $rollback = strpos($method, '$transaction->rollBack()');

    $this->assertIsInt($heldClaim);
    $this->assertIsInt($commercialRevoke);
    $this->assertIsInt($deliveryCas);
    $this->assertIsInt($dispatchRace);
    $this->assertIsInt($rollback);
    $this->assertLessThan($commercialRevoke, $heldClaim);
    $this->assertLessThan($deliveryCas, $commercialRevoke);
    $this->assertLessThan($dispatchRace, $rollback);
  }

}
