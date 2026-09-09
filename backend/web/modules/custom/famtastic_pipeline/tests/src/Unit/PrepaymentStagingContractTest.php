<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Tests\UnitTestCase;

/** @group famtastic_pipeline */
final class PrepaymentStagingContractTest extends UnitTestCase {

  public function testProofSelectionCreatesAnAccountBoundStagingHandoff(): void {
    $module = dirname(__DIR__, 3);
    $portal = file_get_contents($module . '/src/Service/CustomerPortalService.php');
    $commerce = file_get_contents($module . '/src/Service/CommerceLifecycleService.php');
    $receipt = file_get_contents($module . '/src/Service/StagingReceiptService.php');
    $services = file_get_contents($module . '/famtastic_pipeline.services.yml');
    $this->assertIsString($portal);
    $this->assertIsString($commerce);
    $this->assertIsString($receipt);
    $this->assertIsString($services);

    $this->assertStringContainsString('prepareSelectedProofStaging', $portal);
    $this->assertStringContainsString("'prepayment_selected_direction_staging'", $portal);
    $this->assertStringContainsString("'site_studio_staging_prepare'", $portal);
    $this->assertStringContainsString("'selected_direction_ids' => ['direction-' . $direction]", $portal);
    $this->assertStringContainsString("'staging_status' => 'queued'", $portal);
    $this->assertStringContainsString('$project = $projectStorage->load((int) $request[\'project_id\']);', $commerce);
    $this->assertStringContainsString("'proof_review_status' => 'selected'", $receipt);
    $this->assertStringContainsString("'site_studio.staging_deployed'", $receipt);
    $this->assertStringContainsString('@famtastic_pipeline.site_studio_build_packets', $services);
  }

  public function testCheckoutSerializationRequiresDeployedStaging(): void {
    $module = dirname(__DIR__, 3);
    $portal = file_get_contents($module . '/src/Service/CustomerPortalService.php');
    $this->assertIsString($portal);
    $this->assertStringContainsString("(\$row['staging_status'] ?? '') === 'deployed'", $portal);
    $this->assertStringContainsString('staging preview required before checkout opens', $portal);
  }

}
