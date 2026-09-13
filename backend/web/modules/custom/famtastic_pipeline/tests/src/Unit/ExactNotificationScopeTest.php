<?php
declare(strict_types=1);
namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\famtastic_pipeline\Service\LifecycleOperationsService;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/src/Service/LifecycleOperationsService.php';

#[\PHPUnit\Framework\Attributes\Group('famtastic_pipeline')]
final class ExactNotificationScopeTest extends TestCase {
  public function testEmptyScopeNeverTouchesDependencies(): void {
    $service = (new \ReflectionClass(LifecycleOperationsService::class))->newInstanceWithoutConstructor();
    $this->assertSame('empty_exact_scope', $service->dispatchNotifications(25, [])['skipped']);
  }

  public function testScopeIsExactDeduplicatedAndDefaultCompatible(): void {
    $service = (new \ReflectionClass(LifecycleOperationsService::class))->newInstanceWithoutConstructor();
    $method = new \ReflectionMethod($service, 'validateNotificationKeys');
    $this->assertNull($method->invoke($service, NULL));
    $key = 'booking-request:f82a13ba-293d-40f8-baee-5cff1c9f85f5:owner';
    $this->assertSame([$key], $method->invoke($service, [$key, $key]));
    foreach ([[''], ['%'], ['booking-request:*'], [NULL], array_fill(0, 101, 'key')] as $bad) {
      try { $method->invoke($service, $bad); $this->fail('Invalid scope accepted'); }
      catch (\InvalidArgumentException) { $this->addToAssertionCount(1); }
    }
  }

  public function testSelectionAndClaimRecoveryBothUseExactInPredicate(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Service/LifecycleOperationsService.php');
    $this->assertSame(2, substr_count($source, "condition('notification_key', \$notificationKeys, 'IN')"));
    $this->assertStringContainsString('releaseExpiredNotificationClaims($now, $notificationKeys)', $source);
    $this->assertStringContainsString("'notification_dispatch_exact'", $source);
    $this->assertStringContainsString("condition('claim_token', \$claim)", $source);
  }
}
