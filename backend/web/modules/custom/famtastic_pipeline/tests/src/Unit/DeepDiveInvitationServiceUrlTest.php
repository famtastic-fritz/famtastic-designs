<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\famtastic_pipeline\Service\DeepDiveInvitationService;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\famtastic_pipeline\Service\DeepDiveInvitationService
 * @group famtastic_pipeline
 */
final class DeepDiveInvitationServiceUrlTest extends UnitTestCase {

  /** @covers ::validateAnswer */
  public function testPublicBookingUrlGetsHttpsWhenPastedWithoutAScheme(): void {
    $this->assertSame(
      'https://booksy.com/en-us/12345/tighten-up-your-locs',
      $this->validateUrl('booksy.com/en-us/12345/tighten-up-your-locs'),
    );
  }

  /** @covers ::validateAnswer */
  public function testPublicBookingUrlPreservesExplicitHttps(): void {
    $this->assertSame(
      'https://booksy.com/en-us/12345/tighten-up-your-locs',
      $this->validateUrl('https://booksy.com/en-us/12345/tighten-up-your-locs'),
    );
  }

  /** @covers ::validateAnswer */
  public function testNonWebAndCredentialBearingUrlsAreRejected(): void {
    foreach (['javascript:alert(1)', 'ftp://booksy.com/private', 'https://user:password@booksy.com/private'] as $value) {
      try {
        $this->validateUrl($value);
        $this->fail(sprintf('Expected %s to be rejected.', $value));
      }
      catch (\InvalidArgumentException $error) {
        $this->assertSame('Paste a public website address, such as booksy.com/your-business.', $error->getMessage());
      }
    }
  }

  private function validateUrl(string $value): string {
    $service = (new \ReflectionClass(DeepDiveInvitationService::class))->newInstanceWithoutConstructor();
    $method = new \ReflectionMethod($service, 'validateAnswer');
    return $method->invoke($service, ['key' => 'booksy_url', 'type' => 'url', 'required' => TRUE], $value);
  }

}
