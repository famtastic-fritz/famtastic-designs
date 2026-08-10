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
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

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

$ensure_field = static function (
  string $entityType,
  string $bundle,
  string $name,
  string $label,
  string $type,
  int $cardinality = 1,
): void {
  if (!FieldStorageConfig::loadByName($entityType, $name)) {
    FieldStorageConfig::create([
      'field_name' => $name,
      'entity_type' => $entityType,
      'type' => $type,
      'cardinality' => $cardinality,
    ])->save();
  }
  if (!FieldConfig::loadByName($entityType, $bundle, $name)) {
    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => $entityType,
      'bundle' => $bundle,
      'label' => $label,
    ])->save();
  }
};

foreach (['service', 'add_on'] as $catalog_type) {
  $ensure_field('commerce_product', $catalog_type, 'field_famtastic_summary', 'Customer-facing summary', 'string_long');
  $ensure_field('commerce_product_variation', $catalog_type, 'field_entitlement_keys', 'Entitlement grants', 'string', -1);
  $ensure_field('commerce_product_variation', $catalog_type, 'field_intake_schema', 'Intake schema key', 'string');
}

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
$ensure_product = static function (
  string $sku,
  string $type,
  string $title,
  string $price,
  bool $published,
  array $metadata,
) use ($store): void {
  $variation_storage = \Drupal::entityTypeManager()->getStorage('commerce_product_variation');
  $existing = $variation_storage->loadByProperties(['sku' => $sku]);
  if ($existing) {
    $variation = reset($existing);
    $product = $variation->getProduct();
    $variation->setTitle($title)->setPrice(new Price($price, 'USD'))->set('status', $published ? 1 : 0);
    $product?->setTitle($title)->set('status', $published ? 1 : 0);
  }
  else {
    $variation = ProductVariation::create([
      'type' => $type, 'sku' => $sku, 'title' => $title,
      'price' => new Price($price, 'USD'), 'status' => $published,
    ]);
    $variation->save();
    $product = Product::create([
      'type' => $type, 'title' => $title, 'stores' => [$store],
      'variations' => [$variation], 'status' => $published,
    ]);
  }
  $variation->set('field_entitlement_keys', array_map(static fn(string $key): array => ['value' => $key], $metadata['entitlements'] ?? []));
  $variation->set('field_intake_schema', (string) ($metadata['intake_schema'] ?? ''));
  $variation->save();
  $product->set('field_famtastic_summary', (string) ($metadata['description'] ?? ''));
  $product->save();
  $variation->set('status', $published ? 1 : 0)->save();
  $product->set('status', $published ? 1 : 0)->save();
  echo "Synchronized {$title} ({$sku}).\n";
};

$ensure_product('FAM-FOOT-199', 'service', 'Web Basics Bundle — Website Launch', '199.00', TRUE, [
  'description' => 'One focused landing-page website with one year of FAMtastic-managed hosting. Includes first-year new-domain registration when needed or connection of an existing customer-owned domain.',
  'entitlements' => ['website_service', 'hosting_included_year', 'domain_choice'],
  'intake_schema' => 'web_basics_v1',
]);
$ensure_product('FAM-HOST-999', 'add_on', 'Basic Managed Hosting — Monthly Renewal', '9.99', TRUE, [
  'description' => 'Monthly managed-hosting renewal after the included first year. Recurring billing requires separately recorded customer authorization.',
  'entitlements' => ['hosting_recurring'],
  'intake_schema' => 'hosting_renewal_v1',
]);
$ensure_product('FAM-REVISION-75', 'add_on', 'Additional Revision Round', '75.00', TRUE, [
  'description' => 'One additional revision round after the revisions included with the selected website service.',
  'entitlements' => ['revision_round'],
  'intake_schema' => 'revision_request_v1',
]);

foreach ([
  ['FAM-PAGE-EXTRA', 'Additional Website Page', '149.00', 'Additional page design and implementation.', 'additional_page'],
  ['FAM-COPY', 'Copywriting Assistance', '199.00', 'Professional help shaping clear website copy.', 'copywriting'],
  ['FAM-BRAND', 'Logo and Brand Starter', '249.00', 'A focused visual identity starter for the website launch.', 'brand_starter'],
  ['FAM-SCHEDULING', 'Appointment Scheduling', '149.00', 'Customer-facing appointment scheduling connected to the website.', 'appointment_scheduling'],
  ['FAM-LEAD-AUTOMATION', 'Lead Automation', '299.00', 'Lead routing, acknowledgments, notifications, and follow-up automation.', 'lead_automation'],
  ['FAM-AI-AGENT', 'AI Website Agent Setup', '499.00', 'An AI website assistant configured around approved business content.', 'ai_site_agent'],
  ['FAM-ANALYTICS', 'Growth Analytics — Monthly', '29.99', 'Customer analytics entitlement with traffic, lead, and conversion reporting.', 'customer_analytics'],
  ['FAM-LOCAL-SEO', 'Local SEO Setup', '299.00', 'Local search foundation, business signals, and measurement setup.', 'local_seo'],
  ['FAM-MAINTENANCE', 'Website Maintenance — Monthly', '49.99', 'Ongoing website care and managed updates.', 'maintenance'],
  ['FAM-BUSINESS-EMAIL', 'Business Email Setup', '99.00', 'Branded business email configuration and handoff.', 'business_email'],
  ['FAM-ECOMMERCE-DISCOVERY', 'Ecommerce Discovery', '149.00', 'A scoped discovery engagement for a larger ecommerce build.', 'ecommerce_discovery'],
] as [$sku, $title, $price, $description, $catalog_key]) {
  $ensure_product($sku, 'add_on', $title, $price, TRUE, [
    'description' => $description,
    'entitlements' => [$catalog_key],
    'intake_schema' => $catalog_key . '_v1',
  ]);
}

echo "Commerce setup complete: Service and Add-on catalogs are available.\n";
