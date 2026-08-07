#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")"

DRUSH=(vendor/bin/drush --root=web)

echo "==> Enabling the Drupal Commerce foundation"
"${DRUSH[@]}" pm:enable -y \
  commerce \
  commerce_cart \
  commerce_checkout \
  commerce_log \
  commerce_order \
  commerce_payment \
  commerce_price \
  commerce_product \
  commerce_promotion \
  commerce_store

echo "==> Creating FAMtastic product architecture and store"
"${DRUSH[@]}" php:script scripts/setup-commerce.php

echo "==> Rebuilding caches"
"${DRUSH[@]}" cache:rebuild

echo "Drupal Commerce foundation is ready."
