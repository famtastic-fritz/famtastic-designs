<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Tests\UnitTestCase;

/** @group famtastic_pipeline */
final class PublicPreviewDeliveryCommercialGateOrderTest extends UnitTestCase {

  /**
   * The DB-heavy service is covered by the local acceptance fixture in a full
   * Drupal checkout. Keep this small regression guard here as well: a draft
   * commercial campaign must fail at the cold-message hold inside the same
   * transaction as the held outbox and delivery approval writes.
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
    $transaction = strpos($method, '$this->database->startTransaction()');
    $rollback = strpos($method, '$transaction->rollBack()');
    $conditionalState = strpos($method, "->condition('state', 'email_staged')");

    $this->assertIsInt($hold);
    $this->assertIsInt($outbox);
    $this->assertIsInt($approved);
    $this->assertIsInt($transaction);
    $this->assertIsInt($rollback);
    $this->assertIsInt($conditionalState);
    $this->assertLessThan($hold, $transaction);
    $this->assertLessThan($outbox, $hold);
    $this->assertLessThan($approved, $hold);
    $this->assertGreaterThan($approved, $conditionalState);
    $this->assertLessThan($rollback, $approved);
  }

}
