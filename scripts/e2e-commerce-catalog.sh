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

"$DRUSH" --root="$REPO_ROOT/backend/web" php:eval '
  $storage = \Drupal::entityTypeManager()->getStorage("commerce_product_variation");
  $assert = static function (string $sku, string $title, string $amount, bool $published, array $entitlements) use ($storage): void {
    $matches = $storage->loadByProperties(["sku" => $sku]);
    assert(count($matches) === 1, "$sku must exist exactly once");
    $variation = reset($matches);
    assert($variation->label() === $title, "$sku title");
    assert((float) $variation->getPrice()->getNumber() === (float) $amount, "$sku price");
    assert($variation->isPublished() === $published, "$sku publication status");
    assert(array_column($variation->get("field_entitlement_keys")->getValue(), "value") === $entitlements, "$sku entitlements");
  };
  $assert("FAM-FOOT-199", "Web Basics Bundle — Website Launch", "199.00", TRUE, ["website_service", "hosting_included_year", "domain_choice"]);
  $assert("FAM-HOST-999", "Basic Managed Hosting — Monthly Renewal", "9.99", TRUE, ["hosting_recurring"]);
  $assert("FAM-REVISION-75", "Additional Revision Round", "75.00", TRUE, ["revision_round"]);
  foreach (["FAM-PAGE-EXTRA", "FAM-COPY", "FAM-BRAND", "FAM-SCHEDULING", "FAM-LEAD-AUTOMATION", "FAM-AI-AGENT", "FAM-ANALYTICS", "FAM-LOCAL-SEO", "FAM-MAINTENANCE", "FAM-BUSINESS-EMAIL", "FAM-ECOMMERCE-DISCOVERY"] as $sku) {
    $matches = $storage->loadByProperties(["sku" => $sku]);
    assert(count($matches) === 1, "$sku must exist exactly once");
    assert(reset($matches)->isPublished() === FALSE, "$sku must remain draft until pricing is approved");
  }
'

echo "PASS: Commerce catalog is idempotent, the Web Basics Bundle is canonical, approved prices are exact, and unpriced add-ons remain drafts."
