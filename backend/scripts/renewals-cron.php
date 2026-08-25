<?php

declare(strict_types=1);

/**
 * RENEWALS CRON — SCAFFOLD. NOT ENABLED. NEVER CHARGES.
 *
 * Per docs/audits/R1-RENEWAL-CHARGING-RESEARCH.md (R1, 2026-08-25):
 * finds active hosting entitlements whose renewal date falls within the next
 * 7 days and creates DRAFT Commerce renewal orders flagged approval_required.
 *
 * Hard guarantees:
 * - No Stripe call exists in this file. No payment is created, captured, or
 *   confirmed. Live charging remains a Fritz gate (provider flip + crontab).
 * - Dry-run by default: prints what it WOULD create and exits 0.
 * - Creating drafts requires --create-drafts AND the explicit environment
 *   acknowledgment FAMTASTIC_RENEWALS_CRON_ACK=local_scaffold.
 * - Idempotent: one open draft per entitlement per renewal cycle; a draft that
 *   already exists for the cycle is never duplicated.
 *
 * Run locally:
 *   backend/vendor/bin/drush --root=backend/web php:script backend/scripts/renewals-cron.php
 *   backend/vendor/bin/drush --root=backend/web php:script backend/scripts/renewals-cron.php -- --create-drafts
 */

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_product\Entity\ProductVariation;

$database = \Drupal::database();
$time = \Drupal::time()->getRequestTime();
$createDrafts = in_array('--create-drafts', $_SERVER['argv'] ?? [], TRUE)
  && getenv('FAMTASTIC_RENEWALS_CRON_ACK') === 'local_scaffold';

if (!$createDrafts) {
  echo "DRY RUN — no orders will be created. Pass --create-drafts with FAMTASTIC_RENEWALS_CRON_ACK=local_scaffold to materialize drafts.\n";
}

/** Renewal SKU per hosting tier, resolved from the immutable catalog — never hardcoded prices. */
$catalog = json_decode((string) file_get_contents(dirname(\Drupal::root()) . '/config/famtastic-products.json'), TRUE, 512, JSON_THROW_ON_ERROR);
$catalogBySku = [];
foreach ($catalog['products'] ?? [] as $product) {
  $catalogBySku[$product['sku']] = $product;
}
$renewalSkus = ['FAM-HOST-999', 'FAM-HOST-BUSINESS-1999'];
$variations = [];
foreach ($renewalSkus as $sku) {
  if (empty($catalogBySku[$sku])) {
    throw new RuntimeException('Renewal SKU missing from catalog: ' . $sku);
  }
  $ids = \Drupal::entityQuery('commerce_product_variation')->accessCheck(FALSE)->condition('sku', $sku)->execute();
  $variation = $ids ? ProductVariation::load(reset($ids)) : NULL;
  if (!$variation) {
    throw new RuntimeException('Commerce variation missing for ' . $sku . ' — run setup-commerce.php first.');
  }
  $variations[$sku] = $variation;
}

// Active hosting entitlements entering their paid renewal window within 7 days.
$cutoff = $time + 7 * 86400;
$due = $database->select('famtastic_entitlement', 'e')
  ->fields('e', ['id', 'organization_id', 'order_id', 'amount_minor', 'billing_interval', 'renews_at'])
  ->condition('e.entitlement_type', 'hosting')
  ->condition('e.status', 'active')
  ->condition('e.renews_at', 0, '>')
  ->condition('e.renews_at', $cutoff, '<=')
  ->execute()
  ->fetchAll(\PDO::FETCH_ASSOC);

echo sprintf("Found %d hosting entitlement(s) due for renewal by %s.\n", count($due), gmdate(DATE_ATOM, $cutoff));

$created = 0;
foreach ($due as $entitlement) {
  $entitlementId = (int) $entitlement['id'];
  $cycleAt = (int) $entitlement['renews_at'];

  // Tier resolution: the fulfillment snapshot decides basic vs business.
  $snapshot = (string) ($database->select('famtastic_commerce_fulfillment', 'f')
    ->fields('f', ['sku_snapshot'])
    ->condition('order_id', (int) $entitlement['order_id'])
    ->execute()->fetchField() ?: '');
  $businessTier = str_contains($snapshot, 'FAM-HOST-BUSINESS-1999') || str_contains($snapshot, 'FAM-BUSINESS-499');
  $sku = $businessTier ? 'FAM-HOST-BUSINESS-1999' : 'FAM-HOST-999';
  $variation = $variations[$sku];
  $amountMinor = (int) round((float) $variation->getPrice()->getNumber() * 100);

  // Idempotency: the ledger event key is unique per entitlement per cycle.
  $existing = (int) $database->select('famtastic_event', 'ev')
    ->condition('ev.event_key', 'renewal.draft:' . $entitlementId . ':' . $cycleAt)
    ->countQuery()->execute()->fetchField();
  if ($existing > 0) {
    echo sprintf("  skip entitlement #%d — a draft renewal order already exists for cycle %s.\n", $entitlementId, gmdate(DATE_ATOM, $cycleAt));
    continue;
  }

  if (!$createDrafts) {
    echo sprintf("  would create DRAFT order: entitlement #%d org #%d cycle %s SKU %s amount %d %s [approval_required]\n",
      $entitlementId, (int) $entitlement['organization_id'], gmdate(DATE_ATOM, $cycleAt), $sku, $amountMinor, $variation->getPrice()->getCurrencyCode());
    continue;
  }

  $storeIds = \Drupal::entityQuery('commerce_store')->accessCheck(FALSE)->range(0, 1)->execute();
  if (!$storeIds) {
    throw new RuntimeException('No commerce store configured.');
  }
  $item = OrderItem::create([
    'type' => 'default',
    'purchased_entity' => $variation,
    'quantity' => 1,
    'unit_price' => $variation->getPrice(),
    'title' => $variation->getTitle(),
  ]);
  $item->save();
  $order = Order::create([
    'type' => 'default',
    'store_id' => reset($storeIds),
    'uid' => 1,
    'mail' => 'renewals@famtasticdesigns.com',
    'order_items' => [$item],
    'state' => 'draft',
  ]);
  // approval_required blocks every automated transition; only a Fritz-gated
  // approved run may move this order past draft. Nothing is ever charged here.
  $order->setData('famtastic_renewal', [
    'approval_required' => TRUE,
    'entitlement_id' => $entitlementId,
    'organization_id' => (int) $entitlement['organization_id'],
    'source_order_id' => (int) $entitlement['order_id'],
    'cycle_at' => $cycleAt,
    'billing_interval' => (string) $entitlement['billing_interval'],
    'created_by' => 'renewals-cron scaffold',
  ]);
  $order->save();

  \Drupal::service('famtastic_pipeline.operational_ledger')->recordEvent(
    'renewal.draft:' . $entitlementId . ':' . $cycleAt,
    'renewal.draft_created',
    [
      'commerce_order_id' => (int) $order->id(),
      'entitlement_id' => $entitlementId,
      'sku' => $sku,
      'amount_minor' => $amountMinor,
      'approval_required' => TRUE,
    ],
  );
  $created++;
  echo sprintf("  created DRAFT order #%d for entitlement #%d (%s, %d minor) [approval_required]\n",
    (int) $order->id(), $entitlementId, $sku, $amountMinor);
}

echo $createDrafts
  ? sprintf("DONE: %d draft renewal order(s) created. Nothing was charged.\n", $created)
  : "DONE: dry run complete. Nothing was created or charged.\n";
