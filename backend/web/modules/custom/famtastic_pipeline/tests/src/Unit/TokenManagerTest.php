<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\famtastic_pipeline\Service\TokenManager;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\famtastic_pipeline\Service\TokenManager
 * @group famtastic_pipeline
 */
class TokenManagerTest extends UnitTestCase {

  protected function makeManager(int $ttlDays = 14, int $now = 1_000_000): TokenManager {
    $immutable = $this->createMock(ImmutableConfig::class);
    $immutable->method('get')->willReturnMap([
      ['token_ttl_days', $ttlDays],
      ['frontend_base_url', 'http://localhost:5173'],
    ]);
    $config = $this->createMock(ConfigFactoryInterface::class);
    $config->method('get')->willReturn($immutable);
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn($now);
    return new TokenManager($config, $time);
  }

  /** @covers ::hash */
  public function testHashIsDeterministicAndSha256(): void {
    $tm = $this->makeManager();
    $this->assertSame($tm->hash('abc'), $tm->hash('abc'));
    $this->assertSame(hash('sha256', 'abc'), $tm->hash('abc'));
    $this->assertNotSame($tm->hash('abc'), $tm->hash('abd'));
  }

  /** @covers ::verify */
  public function testVerifyMatchesAndRejects(): void {
    $tm = $this->makeManager();
    $raw = 'a-random-token';
    $hash = $tm->hash($raw);
    $this->assertTrue($tm->verify($raw, $hash));
    $this->assertFalse($tm->verify('wrong', $hash));
    $this->assertFalse($tm->verify('', $hash));
    $this->assertFalse($tm->verify($raw, ''));
  }

  /** @covers ::generate */
  public function testGenerateProducesHashedTokenWithExpiry(): void {
    $tm = $this->makeManager(14, 1_000_000);
    $bundle = $tm->generate();
    $this->assertArrayHasKey('raw', $bundle);
    $this->assertArrayHasKey('hash', $bundle);
    $this->assertArrayHasKey('expires', $bundle);
    // Raw token is a substantial, URL-safe secret.
    $this->assertGreaterThanOrEqual(40, strlen($bundle['raw']));
    $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $bundle['raw']);
    // Stored value is the hash of the raw token, never the raw token.
    $this->assertSame($tm->hash($bundle['raw']), $bundle['hash']);
    $this->assertNotSame($bundle['raw'], $bundle['hash']);
    // Expiry is now + ttl days.
    $this->assertSame(1_000_000 + 14 * 86400, $bundle['expires']);
  }

  /** @covers ::generate */
  public function testGeneratedTokensAreUnique(): void {
    $tm = $this->makeManager();
    $this->assertNotSame($tm->generate()['raw'], $tm->generate()['raw']);
  }

}
