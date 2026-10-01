<?php

declare(strict_types=1);
namespace Drupal\Tests\famtastic_pipeline\Unit;
use Drupal\Tests\UnitTestCase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Database\Connection;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\famtastic_pipeline\Service\StaffAiTaskService;
use Drupal\famtastic_pipeline\Service\CommunicationDraftService;

/** @group famtastic_pipeline */
final class StaffAiTaskServiceTest extends UnitTestCase {
  private function service(bool $enabled, ?object $manager): StaffAiTaskService {
    $settings = $this->createMock(ImmutableConfig::class);
    $settings->method('get')->willReturnCallback(static fn(string $key): mixed => str_starts_with($key, 'enabled.') ? $enabled : 5);
    $config = $this->createMock(ConfigFactoryInterface::class);
    $config->method('get')->willReturn($settings);
    $database = $this->createMock(Connection::class);
    $database->expects($this->never())->method('insert');
    return new StaffAiTaskService($config, $database, $this->createMock(FloodInterface::class), $this->createMock(LockBackendInterface::class), $manager);
  }
  public function testNoSilentProviderFallback(): void {
    $manager = new class {
      public function getDefaultProviderForOperationType(string $operation): array { return []; }
      public function createInstance(): void { throw new \LogicException('Must not call provider'); }
    };
    $this->assertFalse($this->service(TRUE, $manager)->readiness('reply')['ready']);
    $this->assertFalse($this->service(FALSE, $manager)->readiness('reply')['ready']);
    $this->assertFalse($this->service(TRUE, NULL)->readiness('reply')['ready']);
  }
  public function testConfiguredIsNotConnectionProof(): void {
    $manager = new class { public function getDefaultProviderForOperationType(string $operation): array { return ['provider_id' => 'openai', 'model_id' => 'configured-model']; } };
    $ready = $this->service(TRUE, $manager)->readiness('reply');
    $this->assertTrue($ready['ready']);
    $this->assertFalse($ready['connection_proven']);
    $this->assertSame('configured-model', $ready['model']);
  }
  public function testUnauthorizedGenerateCannotReachProviderOrReceipt(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->willReturn(FALSE);
    $this->expectException(\RuntimeException::class);
    $this->service(TRUE, NULL)->generate($account, 'reply', []);
  }
  public function testRecipeCannotChooseLifecycleTemplate(): void {
    $this->expectException(\InvalidArgumentException::class);
    CommunicationDraftService::recipe('customer_proof_ready', 'Customer', 'Publish my proof');
  }
  public function testRecipeRequiresDetails(): void {
    $this->expectException(\InvalidArgumentException::class);
    CommunicationDraftService::recipe('clarify', 'Customer', '');
  }
}
