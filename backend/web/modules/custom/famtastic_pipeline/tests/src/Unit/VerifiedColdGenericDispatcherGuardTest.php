<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Tests\UnitTestCase;

/** @group famtastic_pipeline */
final class VerifiedColdGenericDispatcherGuardTest extends UnitTestCase {

  public function testCommercialPreviewTemplateIsExcludedFromGenericCampaignDispatch(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Service/CampaignMessageService.php');
    $this->assertIsString($source);
    $this->assertStringContainsString("->condition('template_key', 'verified_cold_preview', '<>')", $source);
    $this->assertStringContainsString('Verified-cold commercial previews must use the exact-ID public preview dispatcher.', $source);
  }

}
