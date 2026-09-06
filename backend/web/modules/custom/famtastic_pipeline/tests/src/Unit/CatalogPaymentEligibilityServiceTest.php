<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\famtastic_pipeline\Service\CatalogPaymentEligibilityService;

/** @group famtastic_pipeline */
final class CatalogPaymentEligibilityServiceTest extends UnitTestCase {

  private CatalogPaymentEligibilityService $service;

  protected function setUp(): void {
    parent::setUp();
    $this->service = new CatalogPaymentEligibilityService();
  }

  public function testPublishedCatalogHasACompletePaymentContractForEverySku(): void {
    $catalog = json_decode((string) file_get_contents(dirname(__DIR__, 7) . '/config/famtastic-products.json'), TRUE, 512, JSON_THROW_ON_ERROR);
    $products = $catalog['products'] ?? [];
    $this->assertCount(16, $products);
    foreach ($products as $product) {
      $contract = $this->service->contract($product);
      $this->assertContains($contract['mode'], CatalogPaymentEligibilityService::MODES, (string) $product['sku']);
      $this->assertNotEmpty($contract['customer_message'], (string) $product['sku']);
      $this->assertNotEmpty($contract['requires'], (string) $product['sku']);
    }
  }

  public function testPaymentModesFailClosedAtTheCartBoundary(): void {
    $definitions = [
      'FAM-FOOT-199' => $this->product('FAM-FOOT-199', 'proof_selected_website_request'),
      'FAM-PAGE-EXTRA' => $this->product('FAM-PAGE-EXTRA', 'bundle_or_active_website'),
      'FAM-REVISION-75' => $this->product('FAM-REVISION-75', 'active_website_project'),
      'FAM-BRAND' => $this->product('FAM-BRAND', 'direct_account'),
      'FAM-HOST-999' => $this->product('FAM-HOST-999', 'renewal_authorization_only'),
    ];

    $this->assertSame('website_proof_selection_required', $this->service->evaluateCart($definitions, ['FAM-FOOT-199'], FALSE, FALSE, FALSE)['code']);
    $this->assertSame('active_website_required', $this->service->evaluateCart($definitions, ['FAM-PAGE-EXTRA'], FALSE, FALSE, FALSE)['code']);
    $this->assertSame('active_website_project_required', $this->service->evaluateCart($definitions, ['FAM-REVISION-75'], TRUE, FALSE, FALSE)['code']);
    $this->assertTrue($this->service->evaluateCart($definitions, ['FAM-BRAND'], FALSE, FALSE, FALSE)['allowed']);
    $this->assertTrue($this->service->evaluateCart($definitions, ['FAM-PAGE-EXTRA'], FALSE, TRUE, FALSE)['allowed']);
    $this->assertTrue($this->service->evaluateCart($definitions, ['FAM-REVISION-75'], FALSE, TRUE, TRUE)['allowed']);
    $this->assertSame('recurring_checkout_unavailable', $this->service->evaluateCart($definitions, ['FAM-HOST-999'], FALSE, TRUE, TRUE)['code']);
  }

  private function product(string $sku, string $mode): array {
    return [
      'sku' => $sku,
      'published' => TRUE,
      'payment' => [
        'mode' => $mode,
        'customer_message' => 'Customer-safe payment message.',
        'requires' => ['verified_account'],
      ],
    ];
  }

}
