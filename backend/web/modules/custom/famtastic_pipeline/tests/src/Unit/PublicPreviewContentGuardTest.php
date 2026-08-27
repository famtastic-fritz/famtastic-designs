<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\famtastic_pipeline\Service\PublicPreviewContentGuard;
use Drupal\Tests\UnitTestCase;

/** @group famtastic_pipeline */
final class PublicPreviewContentGuardTest extends UnitTestCase {

  public function testRedactsListingContactDataAndCredentialLikeTextBeforePublicProjection(): void {
    $text = (new PublicPreviewContentGuard())->redact('Source says contact owner@example.test or 772-555-0199. api_key=fixture-secret-value-1234567890');

    $this->assertStringContainsString('[redacted email]', $text);
    $this->assertStringContainsString('[redacted phone]', $text);
    $this->assertStringContainsString('[redacted secret]', $text);
    $this->assertStringNotContainsString('owner@example.test', $text);
    $this->assertStringNotContainsString('772-555-0199', $text);
    $this->assertStringNotContainsString('fixture-secret-value-1234567890', $text);
  }

  public function testResearchSnapshotUsesTheSameGuardBeforeCustomerEmailOrRoomStorage(): void {
    $reflection = new \ReflectionClass(\Drupal\famtastic_pipeline\Service\PublicPreviewDeliveryService::class);
    $service = $reflection->newInstanceWithoutConstructor();
    $property = $reflection->getProperty('publicContentGuard');
    $property->setValue($service, new PublicPreviewContentGuard());
    $method = $reflection->getMethod('normalizeResearch');
    $research = $method->invoke($service, [
      'teaser' => 'Researcher copied client@example.test, 772-555-0199, and password: not-for-customer.',
      'sources' => 'Public source summary with Bearer abcdefghijklmnopqrstuvwxyz123456.',
      'evidence_hash' => str_repeat('a', 64),
      'evidence_role' => 'research.source_summary',
    ]);

    $snapshot = json_encode($research, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    $this->assertStringNotContainsString('client@example.test', $snapshot);
    $this->assertStringNotContainsString('772-555-0199', $snapshot);
    $this->assertStringNotContainsString('not-for-customer', $snapshot);
    $this->assertStringNotContainsString('abcdefghijklmnopqrstuvwxyz123456', $snapshot);
    $this->assertStringContainsString('[redacted email]', $snapshot);
    $this->assertStringContainsString('[redacted phone]', $snapshot);
    $this->assertStringContainsString('[redacted secret]', $snapshot);
  }

}
