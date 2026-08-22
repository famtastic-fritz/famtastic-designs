<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\famtastic_pipeline\Service\InkboxWebhookVerifier;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\famtastic_pipeline\Service\InkboxWebhookVerifier
 * @group famtastic_pipeline
 */
class InkboxWebhookVerifierTest extends UnitTestCase {

  /** @covers ::verify */
  public function testValidInkboxSignatureVerifies(): void {
    $verifier = new InkboxWebhookVerifier();
    $body = '{"id":"evt_123","event_type":"message.received"}';
    $signature = $verifier->sign($body, 'req_123', '1000', 'unit-secret');
    $this->assertTrue($verifier->verify($body, 'req_123', '1000', $signature, 'unit-secret', 1000));
  }

  /** @covers ::verify */
  public function testRejectsModifiedPayloadAndStaleDelivery(): void {
    $verifier = new InkboxWebhookVerifier();
    $body = '{"id":"evt_123"}';
    $signature = $verifier->sign($body, 'req_123', '1000', 'unit-secret');
    $this->assertFalse($verifier->verify('{"id":"evt_changed"}', 'req_123', '1000', $signature, 'unit-secret', 1000));
    $this->assertFalse($verifier->verify($body, 'req_123', '1000', $signature, 'unit-secret', 1301));
  }

}
