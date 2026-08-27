<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Tests\UnitTestCase;

/** @group famtastic_pipeline */
final class PublicPreviewDeliveryCommercialGateOrderTest extends UnitTestCase {

  /**
   * The DB-heavy service is covered by browser/kernel suites in a full Drupal
   * checkout. Keep this small regression guard here as well: a draft
   * commercial campaign must fail at the cold-message hold before this method
   * can create a held notification outbox row or mark a delivery approved.
   */
  public function testCommercialHoldPrecedesOutboxAndDeliveryApprovalWrites(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Service/PublicPreviewDeliveryService.php');
    $this->assertIsString($source);
    $start = strpos($source, 'public function approveAndHold');
    $end = strpos($source, 'public function dispatchApproved', $start ?: 0);
    $method = substr($source, (int) $start, (int) $end - (int) $start);
    $hold = strpos($method, '$this->coldMessages->hold($deliveryId)');
    $outbox = strpos($method, "merge('famtastic_notification_outbox')");
    $approved = strpos($method, "'state' => 'email_approved'");

    $this->assertIsInt($hold);
    $this->assertIsInt($outbox);
    $this->assertIsInt($approved);
    $this->assertLessThan($outbox, $hold);
    $this->assertLessThan($approved, $hold);
  }

}
