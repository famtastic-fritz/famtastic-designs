<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\famtastic_pipeline\Service\ColdProofCommercialMessageService;
use Drupal\Tests\UnitTestCase;

/** @group famtastic_pipeline */
final class ColdProofCommercialMessageServiceTest extends UnitTestCase {

  protected function setUp(): void {
    parent::setUp();
    putenv('FAMTASTIC_PUBLIC_BASE_URL');
    putenv('FAMTASTIC_PUBLIC_API_BASE_URL');
  }

  protected function tearDown(): void {
    putenv('FAMTASTIC_PUBLIC_BASE_URL');
    putenv('FAMTASTIC_PUBLIC_API_BASE_URL');
    parent::tearDown();
  }

  public function testCustomerBodyUsesCanonicalDrupalApiAndContainsOnlyTrackedProofCta(): void {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnCallback(static fn (string $key): mixed => match ($key) {
      'frontend_base_url' => 'https://famtasticdesigns.com',
      'public_api_base_url' => '',
      default => NULL,
    });
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')->with('famtastic_pipeline.settings')->willReturn($config);
    $reflection = new \ReflectionClass(ColdProofCommercialMessageService::class);
    $service = $reflection->newInstanceWithoutConstructor();
    $property = $reflection->getProperty('configFactory');
    $property->setValue($service, $factory);
    $method = $reflection->getMethod('commercialBody');
    $signed = 'https://famtasticdesigns.com/proofs/preview/123e4567-e89b-12d3-a456-426614174000/' . str_repeat('b', 64);
    $body = $method->invoke($service, "View your private concept room:\n{$signed}", $signed, str_repeat('a', 48), str_repeat('c', 48), '1729 Example Boulevard');
    $this->assertStringNotContainsString($signed, $body);
    $this->assertStringContainsString('https://famtasticdesigns.com/web/api/pipeline/email/click/' . str_repeat('a', 48), $body);
    $this->assertStringContainsString('https://famtasticdesigns.com/web/api/pipeline/email/unsubscribe/' . str_repeat('c', 48), $body);
    $this->assertStringNotContainsString('https://famtasticdesigns.com/api/pipeline/email/click/', $body);
  }

  public function testExplicitPublicApiBaseMustRemainAtSameOriginDrupalDocumentRoot(): void {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnCallback(static fn (string $key): mixed => match ($key) {
      'frontend_base_url' => 'https://famtasticdesigns.com',
      'public_api_base_url' => 'https://other.example.test/web',
      default => NULL,
    });
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')->with('famtastic_pipeline.settings')->willReturn($config);
    $reflection = new \ReflectionClass(ColdProofCommercialMessageService::class);
    $service = $reflection->newInstanceWithoutConstructor();
    $property = $reflection->getProperty('configFactory');
    $property->setValue($service, $factory);
    $method = $reflection->getMethod('commercialBody');
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('must use the public site origin');
    $method->invoke($service, 'Body', 'https://famtasticdesigns.com/proofs/preview/room/token', str_repeat('a', 48), str_repeat('c', 48), '1729 Example Boulevard');
  }

}
