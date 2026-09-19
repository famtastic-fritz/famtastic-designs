<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Tests\UnitTestCase;

/** @group famtastic_pipeline */
final class PrepaymentStagingContractTest extends UnitTestCase {

  public function testProofSelectionCreatesAnAccountBoundStagingHandoff(): void {
    $module = dirname(__DIR__, 3);
    $portal = file_get_contents($module . '/src/Service/CustomerPortalService.php');
    $continuation = file_get_contents($module . '/src/Service/SelectedStagingContinuation.php');
    $packets = file_get_contents($module . '/src/Service/SiteStudioBuildPacketService.php');
    $commerce = file_get_contents($module . '/src/Service/CommerceLifecycleService.php');
    $receipt = file_get_contents($module . '/src/Service/StagingReceiptService.php');
    $worker = file_get_contents($module . '/src/Service/AutomationWorker.php');
    $services = file_get_contents($module . '/famtastic_pipeline.services.yml');
    $this->assertIsString($portal);
    $this->assertIsString($continuation);
    $this->assertIsString($packets);
    $this->assertIsString($commerce);
    $this->assertIsString($receipt);
    $this->assertIsString($worker);
    $this->assertIsString($services);

    $this->assertStringContainsString('prepareSelectedProofStaging', $portal);
    // The portal now delegates serialization and durable registration. Keep
    // asserting the same contract at its real producer, plus the wiring.
    $this->assertStringContainsString('SelectedStagingContinuation::createPacket($row,', $portal);
    $this->assertStringContainsString('$this->siteStudioPackets->registerPacket($packet)', $portal);
    $this->assertStringContainsString("'prepayment_selected_direction_staging'", $continuation);
    $this->assertStringContainsString("'site_studio_staging_prepare'", $portal);
    $this->assertStringContainsString("'selected_direction_ids' => ['direction-' . \$direction]", $continuation);
    $this->assertStringContainsString("'staging_status' => 'queued'", $packets);
    $this->assertStringContainsString('$project = $projectStorage->load((int) $request[\'project_id\']);', $commerce);
    $this->assertStringContainsString("\$row['proof_review_status'] !== 'selected'", $receipt);
    $this->assertStringContainsString("'site_studio.staging_deployed'", $receipt);
    $this->assertStringContainsString('@famtastic_pipeline.site_studio_build_packets', $services);
    $this->assertStringContainsString('@famtastic_pipeline.site_studio_staging_client', $services);
    $this->assertStringContainsString("'site_studio_staging_prepare'", $worker);
    $this->assertStringContainsString('$this->stagingClient->dispatch($packet)', $worker);
  }

  public function testCheckoutSerializationRequiresDeployedStaging(): void {
    $module = dirname(__DIR__, 3);
    $portal = file_get_contents($module . '/src/Service/CustomerPortalService.php');
    $this->assertIsString($portal);
    $this->assertStringContainsString("(\$row['staging_status'] ?? '') === 'deployed'", $portal);
    $this->assertStringContainsString("(\$row['staging_review_status'] ?? '') === 'accepted'", $portal);
    $this->assertStringContainsString('StagingReceiptService::assertCurrentReviewReceipt($row, $expectedReceiptHash)', $portal);
    $this->assertStringContainsString("->condition('staging_receipt_hash', \$expectedReceiptHash)", $portal);
    $this->assertStringContainsString('staging preview required before checkout opens', $portal);
  }

  public function testIntegratedSelectionAndRevisionRetainTheExactPrepaidPolicy(): void {
    $portal = file_get_contents(dirname(__DIR__, 3) . '/src/Service/CustomerPortalService.php');
    $selection = substr($portal, strpos($portal, 'public function decideWebsiteRequestProof('), strpos($portal, 'public function assertCurrentSelectedStagingPacket(') - strpos($portal, 'public function decideWebsiteRequestProof('));
    $revision = substr($portal, strpos($portal, 'private function queueSelectedSiteRevision('), strpos($portal, 'private function prepareSelectedProofStaging(') - strpos($portal, 'private function queueSelectedSiteRevision('));
    // Initial guard and locked re-read both retain Rawls' financial reconciliation.
    self::assertSame(2, substr_count($selection, '(new OfflinePrepaymentService())->permitsSelectedStaging($row)'));
    self::assertStringContainsString('(new OfflinePrepaymentService())->permitsSelectedStaging($row)', $revision);
    self::assertStringContainsString('->forUpdate()', $selection);
    self::assertStringContainsString('->forUpdate()', $revision);
  }

}
