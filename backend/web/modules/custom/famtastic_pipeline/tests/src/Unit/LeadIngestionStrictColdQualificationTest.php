<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\famtastic_pipeline\Service\LeadIngestionService;
use Drupal\Tests\UnitTestCase;

/** @group famtastic_pipeline */
final class LeadIngestionStrictColdQualificationTest extends UnitTestCase {

  private function assess(array $lead): array {
    $service = (new \ReflectionClass(LeadIngestionService::class))->newInstanceWithoutConstructor();
    $method = new \ReflectionMethod($service, 'assess');
    return $method->invoke($service, $lead, TRUE);
  }

  public function testStrictColdModeDoesNotInferMissingWebsiteFromBlankInput(): void {
    $result = $this->assess([
      'business_name' => 'Example', 'email' => 'owner@example.test', 'website_url' => '',
      'verified_website_observation' => '', 'upgrade_signal' => FALSE,
    ]);
    $this->assertSame('unqualified', $result['status']);
    $this->assertStringContainsString('explicit verified website observation', $result['reasons'][0]);
  }

  public function testStrictColdModeUsesConfirmedAbsentObservationRatherThanInference(): void {
    $result = $this->assess([
      'business_name' => 'Example', 'email' => 'owner@example.test', 'website_url' => '',
      'verified_website_observation' => 'confirmed_absent', 'upgrade_signal' => FALSE,
    ]);
    $this->assertSame('qualified', $result['status']);
    $this->assertSame('Verified source records a confirmed absence of a public website.', $result['reasons'][0]);
  }

  public function testStrictColdModeSupportsVerifiedPresentAsAnExploratoryConceptReview(): void {
    $result = $this->assess([
      'business_name' => 'Example', 'email' => 'owner@example.test', 'website_url' => 'https://example.test',
      'verified_website_observation' => 'verified_present', 'upgrade_signal' => FALSE,
    ]);

    $this->assertSame('qualified', $result['status']);
    $this->assertSame('', $result['target_offer']);
    $this->assertStringContainsString('no website weakness or absence is inferred', $result['reasons'][0]);
  }

  public function testStrictColdModeSupportsAnExploratoryReviewWithoutInferringWebsiteAbsence(): void {
    $result = $this->assess([
      'business_name' => 'Example', 'email' => 'owner@example.test', 'website_url' => '',
      'verified_website_observation' => 'exploratory', 'upgrade_signal' => FALSE,
    ]);

    $this->assertSame('qualified', $result['status']);
    $this->assertSame('', $result['target_offer']);
    $this->assertStringContainsString('no website weakness or absence is inferred', $result['reasons'][0]);
  }

}
