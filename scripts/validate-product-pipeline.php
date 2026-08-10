<?php

declare(strict_types=1);

$path = $argv[1] ?? dirname(__DIR__) . '/backend/config/famtastic-products.json';
$catalog = json_decode((string) file_get_contents($path), TRUE, 512, JSON_THROW_ON_ERROR);
if (($catalog['schema'] ?? '') !== 'famtastic.product-pipeline.v1') {
  throw new RuntimeException('Unsupported product pipeline schema.');
}
$required = ['sku', 'type', 'title', 'price', 'published', 'summary', 'billing', 'eligibility', 'entitlements', 'intake_schema', 'fulfillment', 'communications', 'portal', 'upsells', 'reporting', 'acceptance'];
$skus = [];
foreach ($catalog['products'] ?? [] as $index => $product) {
  foreach ($required as $key) {
    if (!array_key_exists($key, $product) || $product[$key] === '' || $product[$key] === []) {
      if (!in_array($key, ['upsells'], TRUE)) {
        throw new RuntimeException("Product {$index} is missing {$key}.");
      }
    }
  }
  if (!preg_match('/^FAM-[A-Z0-9-]+$/', (string) $product['sku'])) throw new RuntimeException('Invalid SKU.');
  if (isset($skus[$product['sku']])) throw new RuntimeException('Duplicate SKU: ' . $product['sku']);
  if (!in_array($product['type'], ['service', 'add_on'], TRUE)) throw new RuntimeException('Invalid type: ' . $product['sku']);
  if (!preg_match('/^\d+\.\d{2}$/', (string) $product['price'])) throw new RuntimeException('Invalid price: ' . $product['sku']);
  foreach (['project_template', 'milestones'] as $key) if (!array_key_exists($key, $product['fulfillment'])) throw new RuntimeException("Missing fulfillment.{$key}: {$product['sku']}");
  $skus[$product['sku']] = TRUE;
}
foreach ($catalog['products'] as $product) {
  foreach ($product['upsells'] as $sku) if (!isset($skus[$sku])) throw new RuntimeException("Unknown upsell {$sku} from {$product['sku']}");
  $renewal = $product['billing']['renewal_sku'] ?? NULL;
  if ($renewal && !isset($skus[$renewal])) throw new RuntimeException("Unknown renewal SKU {$renewal}");
}
echo 'PASS: product onboarding contract validates ' . count($skus) . " products across all 12 required definition areas.\n";
