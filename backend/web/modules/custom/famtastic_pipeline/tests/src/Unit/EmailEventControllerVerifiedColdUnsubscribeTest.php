<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\famtastic_pipeline\Controller\EmailEventController;
use Drupal\famtastic_pipeline\Service\CampaignMessageService;
use Drupal\famtastic_pipeline\Service\TokenManager;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;

/** @group famtastic_pipeline */
final class EmailEventControllerVerifiedColdUnsubscribeTest extends UnitTestCase {

  public function testGetRendersConfirmationWithoutMutatingSuppressionState(): void {
    $key = str_repeat('a', 48);
    $messages = $this->createMock(CampaignMessageService::class);
    $messages->expects($this->never())->method('unsubscribeVerifiedCold');
    $tokens = $this->createMock(TokenManager::class);

    $response = (new EmailEventController($messages, $tokens))->verifiedColdUnsubscribe(
      Request::create('/web/api/pipeline/email/unsubscribe/confirm/' . $key, 'GET'),
      $key,
    );

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('text/html; charset=UTF-8', $response->headers->get('Content-Type'));
    $this->assertStringContainsString('Confirm unsubscribe', (string) $response->getContent());
    $this->assertStringContainsString('action="/web/api/pipeline/email/unsubscribe/confirm/' . $key . '"', (string) $response->getContent());
    $this->assertStringContainsString('name="List-Unsubscribe" value="One-Click"', (string) $response->getContent());
  }

  public function testOneClickPostSuppressesOnlyThroughTheColdLaneMethod(): void {
    $key = str_repeat('b', 48);
    $messages = $this->createMock(CampaignMessageService::class);
    $messages->expects($this->once())->method('unsubscribeVerifiedCold')->with($key)->willReturn(TRUE);
    $tokens = $this->createMock(TokenManager::class);

    $response = (new EmailEventController($messages, $tokens))->verifiedColdUnsubscribe(
      Request::create('/web/api/pipeline/email/unsubscribe/confirm/' . $key, 'POST', ['List-Unsubscribe' => 'One-Click']),
      $key,
    );

    $this->assertSame(200, $response->getStatusCode());
    $this->assertStringContainsString('You have been unsubscribed.', (string) $response->getContent());
  }

  public function testPostWithoutOneClickValueCannotMutateSuppressionState(): void {
    $key = str_repeat('c', 48);
    $messages = $this->createMock(CampaignMessageService::class);
    $messages->expects($this->never())->method('unsubscribeVerifiedCold');
    $tokens = $this->createMock(TokenManager::class);

    $response = (new EmailEventController($messages, $tokens))->verifiedColdUnsubscribe(
      Request::create('/web/api/pipeline/email/unsubscribe/confirm/' . $key, 'POST'),
      $key,
    );

    $this->assertSame(400, $response->getStatusCode());
    $this->assertStringContainsString('unsubscribe_confirmation_required', (string) $response->getContent());
  }

  public function testInvalidColdOneClickKeyDoesNotReportSuccess(): void {
    $key = str_repeat('d', 48);
    $messages = $this->createMock(CampaignMessageService::class);
    $messages->expects($this->once())->method('unsubscribeVerifiedCold')->with($key)->willReturn(FALSE);
    $tokens = $this->createMock(TokenManager::class);

    $response = (new EmailEventController($messages, $tokens))->verifiedColdUnsubscribe(
      Request::create('/web/api/pipeline/email/unsubscribe/confirm/' . $key, 'POST', ['List-Unsubscribe' => 'One-Click']),
      $key,
    );

    $this->assertSame(404, $response->getStatusCode());
    $this->assertStringContainsString('invalid_unsubscribe_link', (string) $response->getContent());
  }

}
