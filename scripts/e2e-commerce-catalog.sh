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
  $assert("FAM-BUSINESS-499", "Business Website Bundle — Growth Launch", "499.00", TRUE, ["business_website_service", "hosting_business_included_year", "domain_choice", "lead_capture", "foundational_seo", "analytics_connection"]);
  $assert("FAM-HOST-999", "Basic Managed Hosting — Monthly Renewal", "9.99", TRUE, ["hosting_recurring"]);
  $assert("FAM-HOST-BUSINESS-1999", "Business Managed Hosting — Monthly Renewal", "19.99", TRUE, ["hosting_business_recurring"]);
  $assert("FAM-REVISION-75", "Additional Revision Round", "75.00", TRUE, ["revision_round"]);
  $assert("FAM-PAGE-EXTRA", "Additional Website Page", "149.00", TRUE, ["additional_page"]);
  $assert("FAM-COPY", "Copywriting Assistance", "199.00", TRUE, ["copywriting"]);
  $assert("FAM-BRAND", "Logo and Brand Starter", "249.00", TRUE, ["brand_starter"]);
  $assert("FAM-SCHEDULING", "Appointment Scheduling", "149.00", TRUE, ["appointment_scheduling"]);
  $assert("FAM-LEAD-AUTOMATION", "Lead Automation", "299.00", TRUE, ["lead_automation"]);
  $assert("FAM-AI-AGENT", "AI Website Agent Setup", "499.00", TRUE, ["ai_site_agent"]);
  $assert("FAM-ANALYTICS", "Growth Analytics — Monthly", "29.99", TRUE, ["customer_analytics"]);
  $assert("FAM-LOCAL-SEO", "Local SEO Setup", "299.00", TRUE, ["local_seo"]);
  $assert("FAM-MAINTENANCE", "Website Maintenance — Monthly", "49.99", TRUE, ["maintenance"]);
  $assert("FAM-BUSINESS-EMAIL", "Business Email Setup", "99.00", TRUE, ["business_email"]);
  $assert("FAM-ECOMMERCE-DISCOVERY", "Ecommerce Discovery", "149.00", TRUE, ["ecommerce_discovery"]);
'

echo "PASS: Commerce catalog is idempotent and every launch product has the expected price, publication state, and entitlement."
