<?php

declare(strict_types=1);

$path = $argv[1] ?? dirname(__DIR__) . '/backend/config/famtastic-products.json';
$dealPath = $argv[2] ?? dirname(__DIR__) . '/backend/config/famtastic-deal-terms.json';
$catalog = json_decode((string) file_get_contents($path), TRUE, 512, JSON_THROW_ON_ERROR);
$dealRegistry = json_decode((string) file_get_contents($dealPath), TRUE, 512, JSON_THROW_ON_ERROR);
if (($catalog['schema'] ?? '') !== 'famtastic.product-pipeline.v1') {
  throw new RuntimeException('Unsupported product pipeline schema.');
}
if (($dealRegistry['schema'] ?? '') !== 'famtastic.deal-terms.v1') throw new RuntimeException('Unsupported deal-terms schema.');
$policy = $dealRegistry['policy'] ?? [];
foreach (['version', 'status', 'legal_review_required', 'business_approved', 'support_response', 'marketing_default', 'payment_security', 'change_control'] as $key) {
  if (!array_key_exists($key, $policy) || $policy[$key] === '') throw new RuntimeException("Deal policy is missing {$key}.");
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
$deals = $dealRegistry['deals'] ?? [];
if (array_diff_key($skus, $deals) || array_diff_key($deals, $skus)) throw new RuntimeException('Every catalog SKU must have exactly one deal definition.');
foreach ($deals as $sku => $deal) {
  foreach (['scope_version', 'promise', 'deliverables', 'not_included', 'cancellation', 'refund', 'required_consents'] as $key) {
    if (!array_key_exists($key, $deal) || $deal[$key] === '' || $deal[$key] === []) throw new RuntimeException("Deal {$sku} is missing {$key}.");
  }
  if (($catalog['products'][array_search($sku, array_column($catalog['products'], 'sku'), TRUE)]['billing']['kind'] ?? '') === 'recurring') {
    foreach (['amount', 'interval', 'start', 'authorization'] as $key) if (empty($deal['renewal'][$key])) throw new RuntimeException("Recurring deal {$sku} is missing renewal.{$key}.");
  }
}
echo 'PASS: product onboarding contract validates ' . count($skus) . " products, per-SKU deal terms, and all 12 required definition areas.\n";
