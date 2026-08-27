<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\famtastic_pipeline\Service\PilotExactDispatchLock;
use Drupal\Tests\UnitTestCase;

/** @group famtastic_pipeline */
final class PilotExactDispatchSafetyContractTest extends UnitTestCase {

  public function testDurableLockNormalizesFalseStringsAndFailsClosedForMalformedValues(): void {
    foreach ([
      [FALSE, FALSE],
      [TRUE, TRUE],
      [0, FALSE],
      [1, TRUE],
      ['false', FALSE],
      ['OFF', FALSE],
      ['true', TRUE],
      ['yes', TRUE],
      ['unexpected', TRUE],
      [[], TRUE],
      [NULL, FALSE],
    ] as [$value, $expected]) {
      $config = $this->createMock(ImmutableConfig::class);
      $config->method('get')->with('pilot_exact_dispatch_only')->willReturn($value);
      $factory = $this->createMock(ConfigFactoryInterface::class);
      $factory->method('get')->with('famtastic_pipeline.settings')->willReturn($config);
      $lock = new PilotExactDispatchLock($factory);
      $this->assertSame($expected, $lock->durableConfigEnabled());
    }
  }

  public function testDurablePilotLockGuardsBothBroadRuntimePaths(): void {
    $module = dirname(__DIR__, 3);
    $lock = file_get_contents($module . '/src/Service/PilotExactDispatchLock.php');
    $hook = file_get_contents($module . '/famtastic_pipeline.module');
    $commands = file_get_contents($module . '/src/Drush/Commands/PipelineCommands.php');
    $settings = file_get_contents($module . '/config/install/famtastic_pipeline.settings.yml');
    $schema = file_get_contents($module . '/config/schema/famtastic_pipeline.schema.yml');

    foreach ([$lock, $hook, $commands, $settings, $schema] as $source) {
      $this->assertIsString($source);
    }
    $this->assertStringContainsString("get('pilot_exact_dispatch_only')", $lock);
    $this->assertStringContainsString("getenv('FAMTASTIC_PILOT_EXACT_DISPATCH_ONLY') === '1'", $lock);
    $this->assertStringContainsString('pilot_exact_dispatch_only: false', $settings);
    $this->assertStringContainsString('pilot_exact_dispatch_only:', $schema);

    $hookStart = strpos($hook, 'function famtastic_pipeline_cron');
    $hookLock = strpos($hook, "famtastic_pipeline.pilot_exact_dispatch_lock", $hookStart ?: 0);
    $hookReturn = strpos($hook, 'return;', $hookLock ?: 0);
    $hookAutomation = strpos($hook, 'runAutomation(10)', $hookStart ?: 0);
    $hookOutbox = strpos($hook, 'dispatchNotifications(50)', $hookStart ?: 0);
    $this->assertIsInt($hookLock);
    $this->assertIsInt($hookReturn);
    $this->assertIsInt($hookAutomation);
    $this->assertIsInt($hookOutbox);
    $this->assertLessThan($hookReturn, $hookLock);
    $this->assertLessThan($hookAutomation, $hookReturn);
    $this->assertLessThan($hookOutbox, $hookReturn);

    $lifecycleStart = strpos($commands, 'public function lifecycleRun');
    $lifecycleLock = strpos($commands, "famtastic_pipeline.pilot_exact_dispatch_lock", $lifecycleStart ?: 0);
    $lifecycleAutomation = strpos($commands, 'runAutomation(min(50, $limit))', $lifecycleStart ?: 0);
    $lifecycleOutbox = strpos($commands, 'dispatchNotifications($limit)', $lifecycleStart ?: 0);
    $this->assertIsInt($lifecycleLock);
    $this->assertIsInt($lifecycleAutomation);
    $this->assertIsInt($lifecycleOutbox);
    $this->assertLessThan($lifecycleAutomation, $lifecycleLock);
    $this->assertLessThan($lifecycleOutbox, $lifecycleLock);
  }

  public function testVerifiedColdBypassesAreFailClosed(): void {
    $module = dirname(__DIR__, 3);
    $controller = file_get_contents($module . '/src/Controller/SiteStudioCallbackController.php');
    $campaigns = file_get_contents($module . '/src/Service/ProofCampaignService.php');
    $commands = file_get_contents($module . '/src/Drush/Commands/PipelineCommands.php');
    $scheduled = file_get_contents($module . '/src/Service/ColdProofScheduledReleaseService.php');

    foreach ([$controller, $campaigns, $commands, $scheduled] as $source) {
      $this->assertIsString($source);
    }
    $this->assertStringContainsString('declaresVerifiedColdLane($data)', $controller);
    $this->assertStringContainsString('isVerifiedColdCampaignId', $controller);
    $this->assertStringContainsString('verified_cold_private_import_required', $controller);
    $this->assertStringContainsString('atomic', $controller);
    $this->assertStringContainsString('public function isVerifiedColdCampaignId', $campaigns);
    $this->assertStringContainsString('Build DNA', $campaigns);
    $this->assertStringContainsString('Scheduled verified-cold release is disabled.', $commands);
    $this->assertStringContainsString('Dynamic verified-cold scheduled release is disabled.', $scheduled);
    $this->assertStringNotContainsString('dispatchApproved($ids)', $scheduled);
  }

  public function testGovernedDeployPersistsLockAndGatesLegacyQueueAfterPromotion(): void {
    $module = dirname(__DIR__, 3);
    $repo = dirname($module, 5);
    $deployer = file_get_contents($repo . '/scripts/deploy-backend-godaddy.sh');
    $this->assertIsString($deployer);
    $this->assertStringContainsString('config:set famtastic_pipeline.settings pilot_exact_dispatch_only', $deployer);
    $this->assertStringContainsString('assert_pilot_dispatch_lock 1', $deployer);
    $this->assertStringContainsString('active_global_drupal_cron_count', $deployer);
    $this->assertStringContainsString('FAMTASTIC_PILOT_LEGACY_QUARANTINE_CAMPAIGN', $deployer);
    $this->assertStringContainsString('FAMTASTIC_PILOT_LEGACY_QUARANTINE_CONFIRM', $deployer);
    $this->assertStringContainsString('famtastic:campaign-proof-quarantine', $deployer);
    $this->assertStringContainsString('legacy_cold_proof_quarantine_receipt', $deployer);

    $updatedb = strpos($deployer, '"$drush" updatedb -y --strict=0');
    $quarantine = strrpos($deployer, 'quarantine_legacy_cold_queue_after_promotion');
    $this->assertIsInt($updatedb);
    $this->assertIsInt($quarantine);
    $this->assertLessThan($quarantine, $updatedb);
  }

}
