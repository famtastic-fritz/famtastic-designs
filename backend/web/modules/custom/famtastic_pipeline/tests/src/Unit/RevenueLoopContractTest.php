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
    $this->assertStringContainsString("'offer_contracts' => array_combine", $controller);
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

  public function testPaymentIsTheCustomerGateAndStartsDurableFulfillmentEvidence(): void {
    $module = dirname(__DIR__, 3);
    $controller = file_get_contents($module . '/src/Controller/CustomerPortalController.php');
    $lifecycle = file_get_contents($module . '/src/Service/CommerceLifecycleService.php');
    $configRoot = dirname($module, 4) . '/config';
    $catalog = json_decode((string) file_get_contents($configRoot . '/famtastic-products.json'), TRUE, 512, JSON_THROW_ON_ERROR);
    $terms = json_decode((string) file_get_contents($configRoot . '/famtastic-deal-terms.json'), TRUE, 512, JSON_THROW_ON_ERROR);
    $this->assertStringContainsString("['new_domain', 'existing_domain', 'undecided']", $controller);
    $this->assertStringContainsString("'domain_choice' => (string) (\$data['domain_choice'] ?? (\$websiteRequest ? 'undecided' : 'not_applicable'))", $controller);
    $this->assertStringContainsString("'payment.fulfillment_started'", $lifecycle);
    $this->assertStringContainsString("'customer_gate' => 'payment_succeeded'", $lifecycle);
    $this->assertFalse(in_array('domain_choice', $catalog['products'][0]['payment']['requires'], TRUE));
    $this->assertFalse(in_array('domain_choice', $terms['deals']['FAM-FOOT-199']['required_consents'], TRUE));
    $this->assertFalse($terms['deals']['FAM-FOOT-199']['domain_choice_required']);
  }

  public function testWebsiteCheckoutRequiresAnAccountBoundStagingReceipt(): void {
    $module = dirname(__DIR__, 3);
    $controller = file_get_contents($module . '/src/Controller/CustomerPortalController.php');
    $callback = file_get_contents($module . '/src/Controller/SiteStudioCallbackController.php');
    $service = file_get_contents($module . '/src/Service/StagingReceiptService.php');
    $install = file_get_contents($module . '/famtastic_pipeline.install');
    $this->assertIsString($controller);
    $this->assertIsString($callback);
    $this->assertIsString($service);
    $this->assertIsString($install);
    $this->assertStringContainsString("'website_staging_receipt_required'", $controller);
    $this->assertStringContainsString('$this->stagingReceipts->isReady((int) $websiteRequest[\'id\'])', $controller);
    $this->assertStringContainsString("famtastic.site-studio.staging-receipt.v1", $callback);
    $this->assertStringContainsString("staging_deployed", $service);
    $this->assertStringContainsString("'target_path'", $service);
    $this->assertStringContainsString("'repository'", $service);
    $this->assertStringContainsString('function famtastic_pipeline_update_8058', $install);
    $this->assertStringContainsString("'staging_receipt_json'", $install);
  }

}
