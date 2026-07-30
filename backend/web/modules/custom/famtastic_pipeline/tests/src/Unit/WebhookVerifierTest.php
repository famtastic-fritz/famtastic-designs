<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\famtastic_pipeline\Service\WebhookVerifier;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\famtastic_pipeline\Service\WebhookVerifier
 * @group famtastic_pipeline
 */
class WebhookVerifierTest extends UnitTestCase {

  protected string $secret = 'whsec_unit_test';

  /**
   * @covers ::sign
   * @covers ::verify
   */
  public function testValidSignatureVerifies(): void {
    $v = new WebhookVerifier();
    $payload = '{"id":"evt_1","type":"checkout.session.completed"}';
    $header = $v->sign($payload, (string) time(), $this->secret);
    $this->assertTrue($v->verify($payload, $header, $this->secret));
  }

  /** @covers ::verify */
  public function testTamperedPayloadFails(): void {
    $v = new WebhookVerifier();
    $header = $v->sign('{"amount":199}', (string) time(), $this->secret);
    $this->assertFalse($v->verify('{"amount":1}', $header, $this->secret));
  }

  /** @covers ::verify */
  public function testWrongSecretFails(): void {
    $v = new WebhookVerifier();
    $payload = '{"id":"evt_2"}';
    $header = $v->sign($payload, (string) time(), $this->secret);
    $this->assertFalse($v->verify($payload, $header, 'whsec_wrong'));
  }

  /** @covers ::verify */
  public function testMalformedHeaderFails(): void {
    $v = new WebhookVerifier();
    $payload = '{"id":"evt_3"}';
    $this->assertFalse($v->verify($payload, '', $this->secret));
    $this->assertFalse($v->verify($payload, 'garbage', $this->secret));
    $this->assertFalse($v->verify($payload, 't=123', $this->secret));
  }

  /** @covers ::verify */
  public function testExpiredTimestampFailsWithinTolerance(): void {
    $v = new WebhookVerifier();
    $payload = '{"id":"evt_4"}';
    $oldTs = (string) (time() - 10_000);
    $header = $v->sign($payload, $oldTs, $this->secret);
    // Signature is valid but the timestamp is outside the 300s tolerance.
    $this->assertFalse($v->verify($payload, $header, $this->secret, 300));
    // With tolerance disabled it verifies (signature itself is correct).
    $this->assertTrue($v->verify($payload, $header, $this->secret, 0));
  }

}
