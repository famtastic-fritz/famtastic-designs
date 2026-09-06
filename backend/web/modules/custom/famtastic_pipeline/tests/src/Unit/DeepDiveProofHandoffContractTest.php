<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\famtastic_pipeline\Service\CustomerPortalService;
use Drupal\Tests\UnitTestCase;

/** @group famtastic_pipeline */
final class DeepDiveProofHandoffContractTest extends UnitTestCase {

  public function testCompletedDeepDiveAdvancesItsLinkedRequestAndQueuesThreeProofs(): void {
    $module = dirname(__DIR__, 3);
    $portal = file_get_contents($module . '/src/Service/CustomerPortalService.php');
    $controller = file_get_contents($module . '/src/Controller/CustomerPortalController.php');
    $deepDive = file_get_contents($module . '/src/Service/DeepDiveInvitationService.php');
    $this->assertIsString($portal);
    $this->assertIsString($controller);
    $this->assertIsString($deepDive);

    $this->assertStringContainsString("'status' => 'submitted'", $portal);
    $this->assertStringContainsString("'requested_count' => 3", $portal);
    $this->assertStringContainsString('submitClaimedDeepDiveRequest', $portal);
    $this->assertStringContainsString('queueWebsiteRequestProofJob($requestId', $portal);
    $this->assertStringNotContainsString("if (!empty(\$deepDive['website_request_id'])) {\n        continue;", $controller);
    $this->assertStringContainsString('three website directions will enter FAMtastic review', $deepDive);
  }

  public function testProofJobsAreVersionedByTheNormalizedBrief(): void {
    $module = dirname(__DIR__, 3);
    $portal = file_get_contents($module . '/src/Service/CustomerPortalService.php');
    $this->assertIsString($portal);
    $this->assertStringContainsString('websiteRequestProofJobKey($requestId, $briefHash)', $portal);
    $this->assertStringContainsString("'brief_sha256' => \$briefHash", $portal);
    $this->assertStringContainsString("->condition('job_key', 'website_proof.generate.v1:request:' . \$requestId . '%', 'LIKE')", $portal);

    $service = (new \ReflectionClass(CustomerPortalService::class))->newInstanceWithoutConstructor();
    $method = new \ReflectionMethod($service, 'websiteRequestProofJobKey');
    $hash = hash('sha256', '{"schema_version":"website_discovery_v3"}');
    $this->assertSame(
      'website_proof.generate.v1:request:12:brief:' . $hash,
      $method->invoke($service, 12, $hash),
    );
  }

  public function testExplicitRepairCanRearmOnlyTheExactFailedProofJob(): void {
    $module = dirname(__DIR__, 3);
    $commands = file_get_contents($module . '/src/Drush/Commands/PipelineCommands.php');
    $ledger = file_get_contents($module . '/src/Service/OperationalLedger.php');
    $this->assertIsString($commands);
    $this->assertIsString($ledger);
    $this->assertStringContainsString("name: 'famtastic:deep-dive-proof-resume'", $commands);
    $this->assertStringContainsString('requeueFailedJob((int) $job[\'id\'], (string) $job[\'job_key\'])', $commands);
    $this->assertStringContainsString('public function requeueFailedJob(int $jobId, string $expectedJobKey): bool', $ledger);
    $this->assertStringContainsString("->condition('id', \$jobId)->condition('status', 'failed')", $ledger);
    $this->assertStringContainsString("'status' => 'resolved'", $ledger);
  }

}
