<?php

/**
 * Creates the owner's real-money checkout test coupon.
 *
 * Purpose (2026-08-24, Fritz): execute genuine end-to-end purchases of the
 * sites the FAMtastic ecosystem needs through the LIVE gateway at ~$1, with
 * full order/entitlement/receipt side effects. This is the money-step proof
 * the brutal review demanded - executed by a human, not simulated by code.
 *
 * Idempotent: creates or updates the FOUNDER-DOLLAR promotion + coupon.
 * Usage-limited so it cannot leak as a public discount.
 *
 * Run on prod: drush -r <root> php:script backend/scripts/setup-founder-promotion.php
 */

$storage = \Drupal::entityTypeManager()->getStorage('commerce_promotion');
$existing = $storage->loadByProperties(['name' => 'FOUNDER-DOLLAR 2026Q3']);
/** @var \Drupal\commerce_promotion\Entity\PromotionInterface|null $promotion */
$promotion = $existing ? reset($existing) : NULL;

$values = [
  'name' => 'FOUNDER-DOLLAR 2026Q3',
  'display_name' => 'Founder purchase test',
  'description' => 'Owner-executed end-to-end checkout validation. Reduces qualifying Web Basics/Business orders to $1.',
  'status' => TRUE,
  'usage_limit' => 5,
  'usage_limit_customer' => 2,
  'start_date' => '2026-08-24',
  'end_date' => '2026-10-31',
];

if (!$promotion) {
  // commerce_promotion stores offers/conditions as plugin collections; build
  // through the entity + save, then attach plugins via data structures the
  // version in use expects. Order-item-level fixed discount of $198 makes a
  // $199 order total exactly $1 (and is capped per-order by Commerce).
  $promotion = $storage->create($values + [
    'order_types' => [['target_id' => 'default']],
    'stores' => [['target_id' => 1]],
    'offer' => [
      'plugin' => 'order_fixed_amount_off',
      'configuration' => ['amount' => ['number' => '198.00', 'currency_code' => 'USD']],
    ],
  ]);
  $promotion->save();
  print "created promotion FOUNDER-DOLLAR 2026Q3\n";
}
else {
  foreach ($values as $key => $value) {
    if ($key !== 'name') {
      $promotion->set($key, $value);
    }
  }
  $promotion->save();
  print "updated promotion FOUNDER-DOLLAR 2026Q3\n";
}

// Coupon attached to the promotion.
$coupon_storage = \Drupal::entityTypeManager()->getStorage('commerce_promotion_coupon');
$coupons = $coupon_storage->loadByProperties(['code' => 'FAMFOUNDER']);
$couponIds = $promotion->getCouponIds();
$coupon = $coupons ? reset($coupons) : NULL;
if (!$coupon) {
  $coupon = $coupon_storage->create([
    'code' => 'FAMFOUNDER',
    'status' => TRUE,
    'usage_limit' => 5,
    'usage_limit_customer' => 2,
  ]);
  $coupon->save();
  $promotion->set('coupons', [$coupon->id()]);
  $promotion->save();
  print "created coupon FAMFOUNDER and attached\n";
}
else {
  print "coupon FAMFOUNDER already exists (id " . $coupon->id() . ")\n";
}

print "READY - owner applies code FAMFOUNDER at checkout.\n";
