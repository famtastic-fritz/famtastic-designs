<?php

/**
 * Catalog parity guard: Commerce variations must match the advertised catalog.
 *
 * Fails the backend deployment when the SKU set in famtastic-products.json
 * differs from what is actually sellable in Commerce (BRUTAL-REVIEW
 * 2026-08-24 critical #1: the $499 tier was unsellable for days because the
 * two sources drifted silently).
 *
 * Run: drush -r <root> php:script backend/scripts/assert-catalog-parity.php <path-to-famtastic-products.json>
 */

$path = $extra[0] ?? (dirname(\Drupal::root(), 2) . '/config/famtastic-products.json');
if (!is_file($path)) {
  throw new RuntimeException("catalog file missing: $path");
}
$catalog = json_decode((string) file_get_contents($path), TRUE, 512, JSON_THROW_ON_ERROR);
$advertised = array_keys($catalog['products'] ?? []);
sort($advertised);

$storage = \Drupal::entityTypeManager()->getStorage('commerce_product_variation');
$sellable = [];
foreach ($storage->loadMultiple() as $variation) {
  $sellable[] = $variation->get('sku')->value;
}
sort($sellable);

if ($advertised !== $sellable) {
  throw new RuntimeException(
    'CATALOG DRIFT: advertised=' . implode(',', $advertised) . ' sellable=' . implode(',', $sellable)
    . ' — run backend/setup-commerce.sh on this host to synchronize.'
  );
}
print 'Catalog drift guard verified: ' . count($sellable) . " SKUs advertised == sellable.\n";
