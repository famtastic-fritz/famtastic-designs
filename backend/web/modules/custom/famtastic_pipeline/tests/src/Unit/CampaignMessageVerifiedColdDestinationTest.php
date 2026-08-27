<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\famtastic_pipeline\Service\CampaignMessageService;
use Drupal\Tests\UnitTestCase;

/** @group famtastic_pipeline */
final class CampaignMessageVerifiedColdDestinationTest extends UnitTestCase {

  private function valid(string $url): bool {
    $service = (new \ReflectionClass(CampaignMessageService::class))->newInstanceWithoutConstructor();
    $method = new \ReflectionMethod($service, 'isVerifiedColdProofUrl');
    return $method->invoke($service, $url, 'https://famtasticdesigns.com');
  }

  public function testOnlySameOriginSignedProofRoomsCanBeRedirectDestinations(): void {
    $room = 'https://famtasticdesigns.com/proofs/preview/123e4567-e89b-12d3-a456-426614174000/' . str_repeat('b', 64);
    $this->assertTrue($this->valid($room));
    $this->assertFalse($this->valid('https://attacker.example/proofs/preview/123e4567-e89b-12d3-a456-426614174000/' . str_repeat('b', 64)));
    $this->assertFalse($this->valid('https://famtasticdesigns.com/p/' . str_repeat('a', 64)));
    $this->assertFalse($this->valid($room . '?destination=https://attacker.example'));
  }

}
