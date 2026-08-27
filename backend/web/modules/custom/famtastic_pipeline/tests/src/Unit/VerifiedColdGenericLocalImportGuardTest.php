<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Tests\UnitTestCase;

/** @group famtastic_pipeline */
final class VerifiedColdGenericLocalImportGuardTest extends UnitTestCase {

  /**
   * A syntactically valid local callback must not bypass the cold importer
   * merely because it came from a private file instead of the HTTP endpoint.
   */
  public function testGenericLocalImportRejectsVerifiedColdBeforeCallback(): void {
    $module = dirname(__DIR__, 3);
    $commands = file_get_contents($module . '/src/Drush/Commands/PipelineCommands.php');
    $this->assertIsString($commands);

    $genericStart = strpos($commands, 'public function proofLocalImport(');
    $privateStart = strpos($commands, 'public function verifiedColdProofImport(');
    $this->assertNotFalse($genericStart);
    $this->assertNotFalse($privateStart);
    $generic = substr($commands, $genericStart, $privateStart - $genericStart);

    $guard = strpos($generic, 'isVerifiedColdCampaignId($campaignId)');
    $callback = strpos($generic, '$proofs->acceptCallback(');
    $this->assertNotFalse($guard);
    $this->assertNotFalse($callback);
    $this->assertLessThan($callback, $guard);
    $this->assertStringContainsString('Verified-cold proof imports require famtastic:verified-cold-proof-import', $generic);
  }

  /**
   * Generic service callers must fail closed too; the private importer is the
   * only operation permitted to persist Build DNA with cold proof artifacts.
   */
  public function testServiceRequiresTheDedicatedAtomicVerifiedColdOperation(): void {
    $module = dirname(__DIR__, 3);
    $service = file_get_contents($module . '/src/Service/ProofCampaignService.php');
    $commands = file_get_contents($module . '/src/Drush/Commands/PipelineCommands.php');
    $this->assertIsString($service);
    $this->assertIsString($commands);

    $this->assertStringContainsString('acceptCallbackInternal($eventId, $campaignId, $studioJobId, $variants, FALSE)', $service);
    $this->assertStringContainsString('public function acceptVerifiedColdCallback(', $service);
    $this->assertStringContainsString('assertVerifiedColdPrivateImportProvenance', $service);
    $this->assertStringContainsString('recordBuildDna($buildDna)', $service);
    $this->assertStringContainsString('Verified-cold callbacks require the private Build DNA importer', $service);

    $privateStart = strpos($commands, 'public function verifiedColdProofImport(');
    $nextMethod = strpos($commands, 'public function lifecycleRun(', $privateStart);
    $this->assertNotFalse($privateStart);
    $this->assertNotFalse($nextMethod);
    $privateImporter = substr($commands, $privateStart, $nextMethod - $privateStart);
    $this->assertStringContainsString('$proofs->acceptVerifiedColdCallback(', $privateImporter);
    $this->assertStringNotContainsString('$proofs->acceptCallback(', $privateImporter);
  }

}
