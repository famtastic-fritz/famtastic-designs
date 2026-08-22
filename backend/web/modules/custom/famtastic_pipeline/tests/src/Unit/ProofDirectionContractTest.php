<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\famtastic_pipeline\Service\ProofCampaignService;
use Drupal\Tests\UnitTestCase;

/**
 * Locks the stable, generic proof-direction integration contract.
 *
 * @group famtastic_pipeline
 */
final class ProofDirectionContractTest extends UnitTestCase {

  public function testCoreDirectionContractMatchesThePublicInitialProofPromise(): void {
    $this->assertSame(['a', 'b', 'c'], array_keys(ProofCampaignService::CORE_DIRECTIONS));
    $this->assertSame([
      'a' => 'Safe',
      'b' => 'Medium FAMtastic',
      'c' => 'Ultra FAMtastic',
    ], ProofCampaignService::CORE_DIRECTIONS);
    $this->assertSame(
      ProofCampaignService::CORE_DIRECTIONS,
      $this->directionNames(ProofCampaignService::CORE_DIRECTION_CONTRACT),
    );
  }

  public function testShowcaseDefaultsStayGenericAndKeepStableIds(): void {
    $this->assertSame(['d', 'e', 'f'], array_keys(ProofCampaignService::SHOWCASE_DIRECTIONS));
    $this->assertSame(
      ProofCampaignService::SHOWCASE_DIRECTIONS,
      $this->directionNames(ProofCampaignService::SHOWCASE_DIRECTION_CONTRACT),
    );
    $labels = implode(' ', ProofCampaignService::DIRECTIONS);
    $this->assertStringNotContainsString('Royal Current', $labels);
    $this->assertStringNotContainsString('Crownverse', $labels);
    $this->assertStringNotContainsString('Shay Live', $labels);
  }

  /**
   * Reduces a per-run contract to its direction labels without losing IDs.
   */
  private function directionNames(array $contract): array {
    $names = [];
    foreach ($contract as $directionId => $direction) {
      $names[$directionId] = $direction['name'] ?? NULL;
    }
    return $names;
  }

}
