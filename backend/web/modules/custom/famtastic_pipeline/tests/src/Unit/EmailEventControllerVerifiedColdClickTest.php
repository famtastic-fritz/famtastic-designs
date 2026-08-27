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
    $messages->expects($this->once())->method('resolveVerifiedColdClick')->with($tracking)->willReturn([
      'is_verified_cold' => TRUE,
      'destination' => $signedRoom,
    ]);
    $tokens = $this->createMock(TokenManager::class);
    $response = (new EmailEventController($messages, $tokens))->click($tracking);
    $this->assertSame(302, $response->getStatusCode());
    $this->assertSame($signedRoom, $response->headers->get('Location'));
  }

  public function testMalformedVerifiedColdDestinationFailsClosedWithoutLegacyToken(): void {
    $tracking = str_repeat('a', 48);
    $messages = $this->createMock(CampaignMessageService::class);
    $messages->expects($this->once())->method('track')->with($tracking, 'clicked')->willReturn($this->createMock(Prospect::class));
    $messages->expects($this->once())->method('resolveVerifiedColdClick')->with($tracking)->willReturn([
      'is_verified_cold' => TRUE,
      'destination' => NULL,
    ]);
    $tokens = $this->createMock(TokenManager::class);
    $tokens->expects($this->never())->method('generate');

    $response = (new EmailEventController($messages, $tokens))->click($tracking);

    $this->assertSame(404, $response->getStatusCode());
    $this->assertStringContainsString('invalid_cold_preview_destination', (string) $response->getContent());
  }

  public function testLegacyClickStillUsesLegacyProspectTokenFlow(): void {
    $tracking = str_repeat('a', 48);
    $prospect = $this->createMock(Prospect::class);
    $set = [];
    $prospect->expects($this->exactly(3))->method('set')->willReturnCallback(function (string $field, mixed $value) use (&$set, $prospect): Prospect {
      $set[$field] = $value;
      return $prospect;
    });
    $prospect->expects($this->once())->method('save');
    $messages = $this->createMock(CampaignMessageService::class);
    $messages->expects($this->once())->method('track')->with($tracking, 'clicked')->willReturn($prospect);
    $messages->expects($this->once())->method('resolveVerifiedColdClick')->with($tracking)->willReturn([
      'is_verified_cold' => FALSE,
      'destination' => NULL,
    ]);
    $tokens = $this->createMock(TokenManager::class);
    $tokens->expects($this->once())->method('generate')->willReturn(['hash' => 'hash', 'expires' => 123, 'raw' => 'raw-token']);
    $tokens->expects($this->once())->method('link')->with('raw-token')->willReturn('https://famtasticdesigns.com/legacy/raw-token');

    $response = (new EmailEventController($messages, $tokens))->click($tracking);

    $this->assertSame(302, $response->getStatusCode());
    $this->assertSame('https://famtasticdesigns.com/legacy/raw-token', $response->headers->get('Location'));
    $this->assertSame(['token_hash' => 'hash', 'token_expires' => 123, 'token_revoked' => FALSE], $set);
  }

}
