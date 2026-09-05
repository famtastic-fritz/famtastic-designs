<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Tests\UnitTestCase;

/** @group famtastic_pipeline */
final class RevenueFreshnessSafetyContractTest extends UnitTestCase {

  public function testFreshnessLedgerAndAtomicClaimSchemaAreInstalledTogether(): void {
    $module = dirname(__DIR__, 3);
    $install = file_get_contents($module . '/famtastic_pipeline.install');
    $this->assertIsString($install);
    $this->assertStringContainsString('function famtastic_pipeline_update_8054', $install);
    $this->assertStringContainsString('famtastic_revenue_freshness', $install);
    $this->assertStringContainsString("['claimed_at', 'claim_token']", $install);
    $this->assertStringContainsString("'recovery_evidence_json'", $install);
    $this->assertStringContainsString("'open_deadline'", $install);
  }

  public function testFreshnessReconciliationRemainsOwnerTaskOnly(): void {
    $module = dirname(__DIR__, 3);
    $service = file_get_contents($module . '/src/Service/LifecycleOperationsService.php');
    $commands = file_get_contents($module . '/src/Drush/Commands/PipelineCommands.php');
    $this->assertIsString($service);
    $this->assertIsString($commands);

    $start = strpos($service, 'public function reconcileRevenueFreshness');
    $end = strpos($service, 'public function revenueHealth', $start ?: 0);
    $this->assertIsInt($start);
    $this->assertIsInt($end);
    $reconciliation = substr($service, $start, $end - $start);
    $this->assertStringContainsString("'submitted_request'", $reconciliation);
    $this->assertStringContainsString("'proof_state'", $reconciliation);
    $this->assertStringContainsString("'selected_not_paid'", $reconciliation);
    $this->assertStringContainsString("'stale_project'", $reconciliation);
    $this->assertStringContainsString("'release_receipt'", $reconciliation);
    $this->assertStringNotContainsString('->send(', $reconciliation);
    $this->assertStringNotContainsString('queue(', $reconciliation);
    $this->assertStringContainsString("name: 'famtastic:revenue-health'", $commands);
    $this->assertStringContainsString("'owner_task_only' => TRUE", $service);
  }

  public function testGenericOutboxUsesConditionalClaimAndReceiptWrites(): void {
    $service = file_get_contents(dirname(__DIR__, 3) . '/src/Service/LifecycleOperationsService.php');
    $this->assertIsString($service);
    $this->assertStringContainsString('releaseExpiredNotificationClaims', $service);
    $this->assertStringContainsString('claimNotification', $service);
    $this->assertStringContainsString("->condition('status', 'dispatching')->condition('claim_token', \$claim)", $service);
    $this->assertStringContainsString("'notification_dispatch_claim_expired'", $service);
  }

}
