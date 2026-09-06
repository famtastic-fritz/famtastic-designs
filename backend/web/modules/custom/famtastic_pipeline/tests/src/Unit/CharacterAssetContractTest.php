<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\famtastic_pipeline\Service\CharacterAssetService;
use Drupal\Tests\UnitTestCase;

/** @group famtastic_pipeline */
final class CharacterAssetContractTest extends UnitTestCase {

  public function testLikenessRolesAndConsentAreExplicit(): void {
    $this->assertSame([
      'likeness_front',
      'likeness_three_quarter',
    ], CharacterAssetService::requiredLikenessRoles());
    $this->assertSame('reference', CharacterAssetService::normalizeRole('not-a-role'));
    $this->assertSame('likeness_front', CharacterAssetService::normalizeRole('likeness_front'));
    $this->assertSame(['ok' => FALSE, 'error' => 'likeness_assets_required', 'missing' => ['likeness_front', 'likeness_three_quarter']], CharacterAssetService::validateLikenessAssets([]));
    $this->assertSame(['ok' => FALSE, 'error' => 'likeness_consent_required'], CharacterAssetService::validateLikenessAssets([
      ['role' => 'likeness_front', 'status' => 'active', 'ownership_confirmed' => 1, 'subject_permission_confirmed' => 1, 'ai_transformation_consent' => 0],
      ['role' => 'likeness_three_quarter', 'status' => 'active', 'ownership_confirmed' => 1, 'subject_permission_confirmed' => 1, 'ai_transformation_consent' => 1],
    ]));
    $this->assertSame(['ok' => TRUE], CharacterAssetService::validateLikenessAssets([
      ['role' => 'likeness_front', 'status' => 'active', 'ownership_confirmed' => 1, 'subject_permission_confirmed' => 1, 'ai_transformation_consent' => 1],
      ['role' => 'likeness_three_quarter', 'status' => 'active', 'ownership_confirmed' => 1, 'subject_permission_confirmed' => 1, 'ai_transformation_consent' => 1],
    ]));
  }

  public function testBackendStoresRunReceiptAndClarificationContracts(): void {
    $module = dirname(__DIR__, 3);
    $controller = file_get_contents($module . '/src/Controller/WebsiteRequestProofController.php');
    $service = file_get_contents($module . '/src/Service/CharacterAssetService.php');
    $install = file_get_contents($module . '/famtastic_pipeline.install');
    $services = file_get_contents($module . '/famtastic_pipeline.services.yml');
    $this->assertIsString($controller);
    $this->assertIsString($service);
    $this->assertIsString($install);
    $this->assertIsString($services);
    $this->assertStringContainsString("'subject_permission_confirmed'", $controller);
    $this->assertStringContainsString("'ai_transformation_consent'", $controller);
    $this->assertStringContainsString('famtastic_character_run', $install);
    $this->assertStringContainsString('famtastic_character_receipt', $install);
    $this->assertStringContainsString('function famtastic_pipeline_update_8057', $install);
    $this->assertStringContainsString('appendReceipt', $service);
    $this->assertStringContainsString('requestClarification', $service);
    $this->assertStringContainsString('syncRequestState', $service);
    $this->assertStringContainsString('famtastic_pipeline.character_assets', $services);
  }

}
