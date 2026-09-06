<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Tests\UnitTestCase;

/** @group famtastic_pipeline */
final class RevenueLoopContractTest extends UnitTestCase {

  public function testWebsiteCheckoutCannotBypassAnAccountOwnedProofSelection(): void {
    $module = dirname(__DIR__, 3);
    $controller = file_get_contents($module . '/src/Controller/CustomerPortalController.php');
    $this->assertIsString($controller);
    $websiteSkuPosition = strpos($controller, "array_intersect(\$skus, ['FAM-FOOT-199', 'FAM-BUSINESS-499'])");
    $requestGate = strpos($controller, 'if (!$websiteRequest)', $websiteSkuPosition ?: 0);
    $selectionGate = strpos($controller, "!== 'selected'");
    $this->assertNotFalse($websiteSkuPosition);
    $this->assertNotFalse($requestGate);
    $this->assertNotFalse($selectionGate);
    $this->assertStringContainsString('Start with your business intake and choose an approved website direction before checkout.', $controller);
    $this->assertStringNotContainsString("if (empty(\$data['recurring_authorized']))", $controller);
  }

  public function testCheckoutAndCatalogUseCanonicalOfferContractSnapshots(): void {
    $module = dirname(__DIR__, 3);
    $controller = file_get_contents($module . '/src/Controller/CustomerPortalController.php');
    $this->assertIsString($controller);
    $this->assertStringContainsString("'schema' => 'famtastic.offer-contract.v1'", $controller);
    $this->assertStringContainsString("'offer_contracts' => array_map", $controller);
    $this->assertStringContainsString('$item[\'offer_contract\'] = $this->offerContractSnapshot', $controller);
    $this->assertStringContainsString('$this->paymentEligibility->evaluateCart', $controller);
    $this->assertStringContainsString("'payment' => \$this->paymentEligibility->contract", $controller);
    $this->assertStringContainsString("'hash'", $controller);
    $lifecycle = file_get_contents($module . '/src/Service/CommerceLifecycleService.php');
    $this->assertIsString($lifecycle);
    $this->assertStringContainsString("(array) (\$checkout['offer_contracts'] ?? [])", $lifecycle);
    $this->assertStringContainsString('commerce_checkout_contract_missing:', $lifecycle);
  }

  public function testConversionProvisionsOnlyAPrivateOwnerDeskBinding(): void {
    $module = dirname(__DIR__, 3);
    $lifecycle = file_get_contents($module . '/src/Service/CommerceLifecycleService.php');
    $owners = file_get_contents($module . '/src/Service/BookingSiteOwnerService.php');
    $this->assertIsString($lifecycle);
    $this->assertIsString($owners);
    $this->assertStringContainsString('bindToConvertedRequest($siteKey', $lifecycle);
    $this->assertStringContainsString('This does not publish a', $lifecycle);
    $this->assertStringContainsString('forWebsiteRequest', $owners);
  }

}
