<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Tests\UnitTestCase;

/** @group famtastic_pipeline */
final class PublicPreviewDeliveryVerifiedColdDispatchGateTest extends UnitTestCase {

  public function testVerifiedColdTransportGateRunsBeforeAnyHeldOutboxClaim(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Service/PublicPreviewDeliveryService.php');
    $this->assertIsString($source);
    $start = strpos($source, 'public function dispatchApproved');
    $end = strpos($source, 'public function revoke', $start ?: 0);
    $method = substr($source, (int) $start, (int) $end - (int) $start);
    $gate = strpos($method, '$this->coldOutreachGate->assertDispatchAllowed()');
    $transaction = strpos($method, '$this->database->startTransaction()');
    $mailer = strpos($method, '$this->mailer->send(');

    $this->assertIsInt($gate);
    $this->assertIsInt($transaction);
    $this->assertIsInt($mailer);
    $this->assertLessThan($transaction, $gate);
    $this->assertLessThan($mailer, $gate);
  }

}
