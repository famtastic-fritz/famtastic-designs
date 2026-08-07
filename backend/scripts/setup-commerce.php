<?php

declare(strict_types=1);

/**
 * @file
 * Creates the idempotent FAMtastic Drupal Commerce foundation.
 *
 * Required only when the store does not exist:
 *   FAMTASTIC_COMMERCE_STORE_ADDRESS_JSON='{"country_code":"US",...}'
 *
 * Optional:
 *   FAMTASTIC_COMMERCE_STORE_EMAIL='hello@example.com'
 */

use Drupal\commerce_product\Entity\ProductType;
use Drupal\commerce_product\Entity\ProductVariationType;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\commerce_price\Price;
use Drupal\commerce_store\Entity\Store;

$required_modules = [
  'commerce_cart',
  'commerce_checkout',
  'commerce_order',
  'commerce_payment',
  'commerce_product',
  'commerce_promotion',
  'commerce_store',
];

foreach ($required_modules as $module) {
  if (!\Drupal::moduleHandler()->moduleExists($module)) {
    throw new RuntimeException("Required module {$module} is not enabled.");
  }
}

$ensure_product_type = static function (string $id, string $label, string $description): void {
  if (!ProductVariationType::load($id)) {
    ProductVariationType::create([
      'id' => $id,
      'label' => $label,
      'orderItemType' => 'default',
      'generateTitle' => TRUE,
    ])->save();
    echo "Created {$label} variation type.\n";
  }

  if (!ProductType::load($id)) {
    ProductType::create([
      'id' => $id,
      'label' => $label,
      'description' => $description,
      'variationTypes' => [$id],
      'multipleVariations' => FALSE,
      'injectVariationFields' => TRUE,
    ])->save();
    echo "Created {$label} product type.\n";
  }
};

$ensure_product_type(
  'service',
  'Service',
  'Primary FAMtastic website, application, consulting, and implementation offers.',
);
$ensure_product_type(
  'add_on',
  'Add-on',
  'Optional upgrades and post-purchase upsells attached to a primary service.',
);

$store_storage = \Drupal::entityTypeManager()->getStorage('commerce_store');
if (!$store_storage->getQuery()->accessCheck(FALSE)->count()->execute()) {
  $address_json = trim((string) getenv('FAMTASTIC_COMMERCE_STORE_ADDRESS_JSON'));
  if ($address_json === '') {
    throw new RuntimeException('FAMTASTIC_COMMERCE_STORE_ADDRESS_JSON is required to create the store.');
  }

  $address = json_decode($address_json, TRUE, 512, JSON_THROW_ON_ERROR);
  foreach (['country_code', 'address_line1', 'locality', 'administrative_area', 'postal_code'] as $key) {
    if (trim((string) ($address[$key] ?? '')) === '') {
      throw new RuntimeException("The store address is missing {$key}.");
    }
  }

  $store_email = trim((string) getenv('FAMTASTIC_COMMERCE_STORE_EMAIL'));
  $store_email = $store_email ?: (string) \Drupal::config('system.site')->get('mail');
  if (!filter_var($store_email, FILTER_VALIDATE_EMAIL)) {
    throw new RuntimeException('A valid FAMTASTIC_COMMERCE_STORE_EMAIL or Drupal site email is required.');
  }

  $store = Store::create([
    'type' => 'online',
    'uid' => 1,
    'name' => 'FAMtastic Designs',
    'mail' => $store_email,
    'address' => $address,
    'default_currency' => 'USD',
    'timezone' => 'America/New_York',
    'status' => TRUE,
  ]);
  $store->save();
  echo "Created the FAMtastic Designs online store.\n";
}
else {
  echo "Commerce store already exists; left unchanged.\n";
}

$store = $store ?? $store_storage->load(reset($store_storage->getQuery()->accessCheck(FALSE)->range(0, 1)->execute()));
$ensure_product = static function (string $sku, string $type, string $title, string $price, array $metadata) use ($store): void {
  $variation_storage = \Drupal::entityTypeManager()->getStorage('commerce_product_variation');
  $existing = $variation_storage->loadByProperties(['sku' => $sku]);
  if ($existing) {
    echo "Commerce product {$sku} already exists; left unchanged.\n";
    return;
  }
  $variation = ProductVariation::create([
    'type' => $type, 'sku' => $sku, 'title' => $title,
    'price' => new Price($price, 'USD'), 'status' => $sku !== 'FAM-ANALYTICS',
  ]);
  $variation->save();
  $product = Product::create([
    'type' => $type, 'title' => $title, 'stores' => [$store],
    'variations' => [$variation], 'status' => $sku !== 'FAM-ANALYTICS',
  ]);
  $product->save();
  echo "Created {$title} ({$sku}).\n";
};

$ensure_product('FAM-FOOT-199', 'service', 'Foot in the Door — Single-Page Website', '199.00', [
  'description' => 'One focused landing-page website with one year of FAMtastic-managed hosting. Includes first-year new-domain registration when needed or connection of an existing customer-owned domain.',
]);
$ensure_product('FAM-HOST-999', 'add_on', 'Basic Managed Hosting — Monthly Renewal', '9.99', [
  'description' => 'Monthly managed-hosting renewal after the included first year. Recurring billing requires separately recorded customer authorization.',
]);
$ensure_product('FAM-ANALYTICS', 'add_on', 'Growth Analytics', '0.00', [
  'description' => 'Configurable analytics entitlement. Keep unpublished from direct sale until final packaging and pricing are approved.',
]);

echo "Commerce setup complete: Service and Add-on catalogs are available.\n";
