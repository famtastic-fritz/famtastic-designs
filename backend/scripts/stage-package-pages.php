<?php

/**
 * Stages portfolio/package_page nodes from famtastic-products.json.
 *
 * PRODUCT_PIPELINE step 7 prep (storefront surfacing ≥5 offers). Idempotent:
 * updates by slug if present, creates otherwise. Exits cleanly with guidance
 * when the package_page content type does not exist in this installation
 * (it lives in production; export its config before running here).
 *
 * Run: drush -r <root> php:script backend/scripts/stage-package-pages.php
 */

$storage = \Drupal::entityTypeManager()->getStorage('node_type');
if (!$storage->load('package_page')) {
  print "SKIP: package_page content type not installed here.\n";
  print "Production holds it. Export first, then re-run:\n";
  print "  drush -r \$HOME/public_html config:export --partial --destination=../config-export\n";
  print "  commit node.type.package_page + core.entity_form_display.node.package_page.* (+view displays)\n";
  exit(0);
}

$catalog = json_decode((string) file_get_contents(dirname(\Drupal::root()) . '/config/famtastic-products.json'), TRUE);
$surfaced = ['FAM-FOOT-199', 'FAM-BUSINESS-499', 'FAM-BUSINESS-EMAIL', 'FAM-MAINTENANCE', 'FAM-LOCAL-SEO', 'FAM-ANALYTICS'];
$order = 10;

foreach ($catalog['products'] as $product) {
  if (!in_array($product['sku'], $surfaced, TRUE)) {
    continue;
  }
  $existing = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
    'type' => 'package_page',
    'field_sku' => $product['sku'],
  ]);
  $values = [
    'type' => 'package_page',
    'title' => $product['title'],
    'field_sku' => $product['sku'],
    'field_price_display' => '$' . $product['price'] . ($product['billing']['kind'] === 'recurring' ? '/mo' : ''),
    'field_summary' => $product['summary'],
    'field_sort_order' => $order,
    'status' => 1,
  ];
  if ($existing) {
    $node = reset($existing);
    foreach ($values as $key => $value) {
      if ($key !== 'type') {
        $node->set($key, $value);
      }
    }
    $node->save();
    print "updated: {$product['sku']} (nid {$node->id()})\n";
  }
  else {
    $node = \Drupal::entityTypeManager()->getStorage('node')->create($values + ['uid' => 1]);
    $node->save();
    print "created: {$product['sku']} (nid {$node->id()})\n";
  }
  $order += 10;
}
print "DONE — surfaced " . count($surfaced) . " offers.\n";
