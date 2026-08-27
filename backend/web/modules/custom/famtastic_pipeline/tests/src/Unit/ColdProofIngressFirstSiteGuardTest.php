<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\famtastic_pipeline\Service\ColdProofIngressService;
use Drupal\Tests\UnitTestCase;

/** @group famtastic_pipeline */
final class ColdProofIngressFirstSiteGuardTest extends UnitTestCase {

  private function assertIngressEligibility(array $lead): void {
    $service = (new \ReflectionClass(ColdProofIngressService::class))->newInstanceWithoutConstructor();
    $method = new \ReflectionMethod($service, 'assertFirstSiteEligibility');
    $method->invoke($service, $lead);
  }

  public function testIngressAcceptsOnlyAConfirmedAbsentLeadWithNoWebsiteUrl(): void {
    $this->assertIngressEligibility([
      'website_observation' => ['status' => 'confirmed_absent'],
      'website_url' => '',
    ]);
    $this->addToAssertionCount(1);
  }

  public function testIngressRejectsAnIndependentWebsiteBeforeItCanWrite(): void {
    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('requires confirmed_absent and a blank website_url');
    $this->assertIngressEligibility([
      'website_observation' => ['status' => 'confirmed_absent'],
      'website_url' => 'https://existing.example.test',
    ]);
  }

  public function testIngressRejectsVerifiedPresentBeforeItCanWrite(): void {
    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('requires confirmed_absent and a blank website_url');
    $this->assertIngressEligibility([
      'website_observation' => ['status' => 'verified_present'],
      'website_url' => '',
    ]);
  }

}
