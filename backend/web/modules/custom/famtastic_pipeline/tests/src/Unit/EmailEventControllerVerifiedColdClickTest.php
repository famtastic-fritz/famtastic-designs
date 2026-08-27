<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\famtastic_pipeline\Controller\EmailEventController;
use Drupal\famtastic_pipeline\Entity\Prospect;
use Drupal\famtastic_pipeline\Service\CampaignMessageService;
use Drupal\famtastic_pipeline\Service\TokenManager;
use Drupal\Tests\UnitTestCase;

/** @group famtastic_pipeline */
final class EmailEventControllerVerifiedColdClickTest extends UnitTestCase {

  public function testVerifiedColdClickTracksThenRedirectsToStoredSignedRoom(): void {
    $tracking = str_repeat('a', 48);
    $signedRoom = 'https://famtasticdesigns.com/proofs/preview/123e4567-e89b-12d3-a456-426614174000/' . str_repeat('b', 64);
    $messages = $this->createMock(CampaignMessageService::class);
    $messages->expects($this->once())->method('track')->with($tracking, 'clicked')->willReturn($this->createMock(Prospect::class));
    $messages->expects($this->once())->method('verifiedColdClickDestination')->with($tracking)->willReturn($signedRoom);
    $tokens = $this->createMock(TokenManager::class);
    $response = (new EmailEventController($messages, $tokens))->click($tracking);
    $this->assertSame(302, $response->getStatusCode());
    $this->assertSame($signedRoom, $response->headers->get('Location'));
  }

}
