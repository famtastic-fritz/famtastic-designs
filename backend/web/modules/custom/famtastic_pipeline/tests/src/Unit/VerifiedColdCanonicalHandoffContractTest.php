<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Tests\UnitTestCase;

/** @group famtastic_pipeline */
final class VerifiedColdCanonicalHandoffContractTest extends UnitTestCase {

  /**
   * The runner must receive durable Drupal identities, never synthetic names
   * which a callback cannot safely bind. Keep the ingress/job/export contract
   * aligned even when its database-heavy acceptance fixture is unavailable.
   */
  public function testDedicatedJobAndHandoffCarryTheCanonicalRunTuple(): void {
    $module = dirname(__DIR__, 3);
    $ingress = file_get_contents($module . '/src/Service/ColdProofIngressService.php');
    $previews = file_get_contents($module . '/src/Service/PublicPreviewDeliveryService.php');
    $handoff = file_get_contents($module . '/src/Service/ColdProofBuildHandoffService.php');
    $worker = file_get_contents($module . '/src/Service/AutomationWorker.php');

    foreach ([$ingress, $previews, $handoff, $worker] as $source) {
      $this->assertIsString($source);
      $this->assertStringContainsString('verified_cold', $source);
    }
    $this->assertStringContainsString('createQueuedPublicPreviewCampaign', $ingress);
    $this->assertStringContainsString("'public_preview.generate'", $previews);
    $this->assertStringContainsString("'proof_campaign_id' => \$proofCampaignId", $previews);
    $this->assertStringContainsString("'build_dna_run'", $previews);
    $this->assertStringContainsString("'callback_event_id' => \$callbackEventId", $previews);
    $this->assertStringContainsString("'run_started_at' => \$runStartedAt", $previews);
    $this->assertStringContainsString("'famtastic.verified-cold-proof-handoff.v1'", $handoff);
    $this->assertStringContainsString("'job_id' => \$jobId", $handoff);
    $this->assertStringContainsString("'callback_event_id' => \$callbackEventId", $handoff);
    $this->assertStringContainsString("'run_started_at' => \$runStartedAt", $handoff);
    $this->assertStringContainsString('generatePublicPreviewProofs', $worker);
    $this->assertStringContainsString('dedicated public_preview.generate worker lane', $worker);
  }

  /** The cold callback must use its own signed import boundary, never GoDaddy. */
  public function testVerifiedColdImportRemainsExactDeliveryAndSignedAssetBound(): void {
    $module = dirname(__DIR__, 3);
    $commands = file_get_contents($module . '/src/Drush/Commands/PipelineCommands.php');
    $assembler = file_get_contents(dirname($module, 5) . '/website-delivery-swarm/cohorts/beauty-hair-braiding/assemble-verified-cold-callback.mjs');

    $this->assertIsString($commands);
    $this->assertIsString($assembler);
    $this->assertStringContainsString("'famtastic:verified-cold-proof-import'", $commands);
    $this->assertStringContainsString('privateRegularFile', $commands);
    $this->assertStringContainsString('verifyCallbackSignature', $commands);
    $this->assertStringContainsString('verifiedColdCallbackContractForCampaign', $commands);
    $this->assertStringContainsString('recordBuildDna($dna)', $commands);
    $this->assertStringContainsString('acceptCallback(', $commands);
    $this->assertStringNotContainsString('promote-local-proof-godaddy', $commands);
    $this->assertStringContainsString("'famtastic.verified-cold-proof-callback.v1'", $assembler);
    $this->assertStringContainsString('signed_asset_manifest_sha256', $assembler);
    $this->assertStringContainsString('no email send', $assembler);
  }

}
