<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\famtastic_pipeline\Service\VerifiedColdOutreachGate;
use Drupal\Tests\UnitTestCase;

/** @group famtastic_pipeline */
final class VerifiedColdOutreachGateTest extends UnitTestCase {

  protected function setUp(): void {
    parent::setUp();
    foreach ([
      'FAMTASTIC_TRANSACTIONAL_EMAIL_TRANSPORT',
      'FAMTASTIC_ALLOW_REAL_OUTREACH',
      'FAMTASTIC_ALLOW_VERIFIED_COLD_REAL_OUTREACH',
      'FAMTASTIC_ALLOW_VERIFIED_COLD_MEMORY_DISPATCH',
    ] as $name) {
      putenv($name);
    }
  }

  protected function tearDown(): void {
    foreach ([
      'FAMTASTIC_TRANSACTIONAL_EMAIL_TRANSPORT',
      'FAMTASTIC_ALLOW_REAL_OUTREACH',
      'FAMTASTIC_ALLOW_VERIFIED_COLD_REAL_OUTREACH',
      'FAMTASTIC_ALLOW_VERIFIED_COLD_MEMORY_DISPATCH',
    ] as $name) {
      putenv($name);
    }
    parent::tearDown();
  }

  public function testDefaultSmtpConfigurationDeniesVerifiedColdOutreach(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('FAMTASTIC_ALLOW_REAL_OUTREACH=true');
    $this->gate()->assertDispatchAllowed();
  }

  public function testMemoryRehearsalNeedsAnExplicitTestOnlyGate(): void {
    putenv('FAMTASTIC_TRANSACTIONAL_EMAIL_TRANSPORT=memory');
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('FAMTASTIC_ALLOW_VERIFIED_COLD_MEMORY_DISPATCH=true');
    $this->gate()->assertDispatchAllowed();
  }

  public function testExplicitMemoryRehearsalIsAllowedWithoutRealOutreachGate(): void {
    putenv('FAMTASTIC_TRANSACTIONAL_EMAIL_TRANSPORT=memory');
    putenv('FAMTASTIC_ALLOW_VERIFIED_COLD_MEMORY_DISPATCH=true');
    $this->gate()->assertDispatchAllowed();
    $this->addToAssertionCount(1);
  }

  public function testRealOutreachNeedsBothExplicitGates(): void {
    putenv('FAMTASTIC_TRANSACTIONAL_EMAIL_TRANSPORT=smtp');
    putenv('FAMTASTIC_ALLOW_REAL_OUTREACH=true');
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('FAMTASTIC_ALLOW_VERIFIED_COLD_REAL_OUTREACH=true');
    $this->gate()->assertDispatchAllowed();
  }

  public function testBothRealOutreachGatesPermitOnlyTheTransportDecision(): void {
    putenv('FAMTASTIC_TRANSACTIONAL_EMAIL_TRANSPORT=smtp');
    putenv('FAMTASTIC_ALLOW_REAL_OUTREACH=true');
    putenv('FAMTASTIC_ALLOW_VERIFIED_COLD_REAL_OUTREACH=true');
    $this->gate()->assertDispatchAllowed();
    $this->addToAssertionCount(1);
  }

  private function gate(): VerifiedColdOutreachGate {
    return new VerifiedColdOutreachGate();
  }

}
