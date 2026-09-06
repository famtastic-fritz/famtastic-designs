#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DRUSH="$REPO_ROOT/backend/vendor/bin/drush"

export FAMTASTIC_COMMERCE_STORE_EMAIL="${FAMTASTIC_COMMERCE_STORE_EMAIL:-hello@famtasticdesigns.com}"
export FAMTASTIC_COMMERCE_STORE_ADDRESS_JSON="${FAMTASTIC_COMMERCE_STORE_ADDRESS_JSON:-{\"country_code\":\"US\",\"address_line1\":\"1729 NW St. Lucie West Blvd #1181\",\"locality\":\"Port Saint Lucie\",\"administrative_area\":\"FL\",\"postal_code\":\"34986\"}}"

"$REPO_ROOT/backend/setup-commerce.sh" >/dev/null
count_before="$($DRUSH --root="$REPO_ROOT/backend/web" sqlq "SELECT COUNT(*) FROM commerce_product_variation_field_data;")"
"$REPO_ROOT/backend/setup-commerce.sh" >/dev/null
count_after="$($DRUSH --root="$REPO_ROOT/backend/web" sqlq "SELECT COUNT(*) FROM commerce_product_variation_field_data;")"
test "$count_before" = "$count_after"

FAMTASTIC_PRODUCT_EXPECTATIONS="$(jq -c '.products' "$REPO_ROOT/backend/config/famtastic-products.json")" \
"$DRUSH" --root="$REPO_ROOT/backend/web" php:eval '
  $storage = \Drupal::entityTypeManager()->getStorage("commerce_product_variation");
  $catalog = json_decode((string) getenv("FAMTASTIC_PRODUCT_EXPECTATIONS"), TRUE, 512, JSON_THROW_ON_ERROR);
  assert(is_array($catalog) && count($catalog) === 16, "catalog contract");
  $assert = static function (array $definition) use ($storage): void {
    $sku = (string) $definition["sku"];
    $title = (string) $definition["title"];
    $amount = (string) $definition["price"];
    $published = (bool) $definition["published"];
    $entitlements = (array) ($definition["entitlements"] ?? []);
    $matches = $storage->loadByProperties(["sku" => $sku]);
    assert(count($matches) === 1, "$sku must exist exactly once");
    $variation = reset($matches);
    assert($variation->label() === $title, "$sku title");
    assert((float) $variation->getPrice()->getNumber() === (float) $amount, "$sku price");
    assert($variation->isPublished() === $published, "$sku publication status");
    assert(array_column($variation->get("field_entitlement_keys")->getValue(), "value") === $entitlements, "$sku entitlements");
  };
  foreach ($catalog as $definition) {
    $assert($definition);
  }
'

echo "PASS: Commerce catalog is idempotent and every configured product has the expected title, price, publication state, and entitlement."
