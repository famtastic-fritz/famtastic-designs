<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\famtastic_pipeline\Service\PublicPreviewDeliveryService;
use Drupal\Tests\UnitTestCase;

/** @group famtastic_pipeline */
final class VerifiedColdResearchStagingGuardTest extends UnitTestCase {

  public function testVerifiedColdStagingRejectsMissingResearchBeforeOwnerReview(): void {
    $service = (new \ReflectionClass(PublicPreviewDeliveryService::class))->newInstanceWithoutConstructor();
    $method = new \ReflectionMethod(PublicPreviewDeliveryService::class, 'assertResearchForSourceLane');

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('requires a customer-safe research teaser');
    $method->invoke($service, 'verified_cold', [
      'teaser' => '',
      'sources' => '',
      'evidence_hash' => '',
      'evidence_role' => '',
    ]);
  }

  public function testOtherPublicPreviewLanesRemainBackwardCompatibleWithoutResearch(): void {
    $service = (new \ReflectionClass(PublicPreviewDeliveryService::class))->newInstanceWithoutConstructor();
    $method = new \ReflectionMethod(PublicPreviewDeliveryService::class, 'assertResearchForSourceLane');
    $method->invoke($service, 'anonymous_public', []);
    $this->addToAssertionCount(1);
  }

}
