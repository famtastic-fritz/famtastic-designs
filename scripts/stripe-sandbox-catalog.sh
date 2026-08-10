#!/usr/bin/env bash
set -euo pipefail

CLI_PROJECT="${STRIPE_CLI_PROJECT:-famtastic-sandbox-auth}"
REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
EVIDENCE_DIR="$REPO_ROOT/.artifacts/stripe"
EVIDENCE_FILE="$EVIDENCE_DIR/sandbox-catalog.json"

command -v stripe >/dev/null || { echo "ERROR: Stripe CLI is required." >&2; exit 1; }
command -v jq >/dev/null || { echo "ERROR: jq is required." >&2; exit 1; }

balance="$(stripe balance retrieve --project-name "$CLI_PROJECT")"
test "$(jq -r '.livemode' <<<"$balance")" = "false" || {
  echo "ERROR: refusing to modify a live Stripe account." >&2
  exit 1
}

mkdir -p "$EVIDENCE_DIR"
items='[]'

ensure_item() {
  local sku="$1" name="$2" amount="$3" interval="$4" description="$5"
  local product price recurring_query
  product="$(stripe products search --project-name "$CLI_PROJECT" --query "metadata['famtastic_sku']:'$sku'" | jq -r '.data[0].id // empty')"
  if [[ -z "$product" ]]; then
    product="$(stripe products create --project-name "$CLI_PROJECT" --confirm \
      --name "$name" --description "$description" \
      -d "metadata[famtastic_sku]=$sku" \
      -d 'metadata[environment]=sandbox' \
      --idempotency "famtastic-product-$sku" | jq -r '.id')"
  fi

  recurring_query='.recurring == null'
  [[ "$interval" != "one_time" ]] && recurring_query=".recurring.interval == \"$interval\""
  price="$(stripe prices list --project-name "$CLI_PROJECT" --product="$product" --active=true --limit=100 | \
    jq -r --argjson amount "$amount" --arg sku "$sku" ".data[] | select(.unit_amount == \$amount and .currency == \"usd\" and ($recurring_query)) | .id" | head -1)"
  if [[ -z "$price" ]]; then
    args=(prices create --project-name "$CLI_PROJECT" --confirm --product "$product" --currency usd --unit-amount "$amount" --nickname "$sku" -d "metadata[famtastic_sku]=$sku" -d 'metadata[environment]=sandbox' --idempotency "famtastic-price-$sku-$amount-$interval")
    [[ "$interval" != "one_time" ]] && args+=(--recurring.interval "$interval")
    price="$(stripe "${args[@]}" | jq -r '.id')"
  fi

  items="$(jq -c --arg sku "$sku" --arg product "$product" --arg price "$price" --arg interval "$interval" --argjson amount "$amount" \
    '. + [{sku:$sku,product_id:$product,price_id:$price,amount_minor:$amount,currency:"usd",interval:$interval}]' <<<"$items")"
  printf 'Synchronized %s (%s) in Stripe sandbox.\n' "$name" "$sku"
}

ensure_item FAM-FOOT-199 'Web Basics Bundle — Website Launch' 19900 one_time 'One focused landing-page website with one year of managed hosting and a first-year domain choice.'
ensure_item FAM-HOST-999 'Basic Managed Hosting — Monthly Renewal' 999 month 'Managed hosting after the included first year; activated only with separate recurring authorization.'
ensure_item FAM-REVISION-75 'Additional Revision Round' 7500 one_time 'One additional revision round.'
ensure_item FAM-PAGE-EXTRA 'Additional Website Page' 14900 one_time 'Additional page design and implementation.'
ensure_item FAM-COPY 'Copywriting Assistance' 19900 one_time 'Professional website copy assistance.'
ensure_item FAM-BRAND 'Logo and Brand Starter' 24900 one_time 'Focused visual identity starter.'
ensure_item FAM-SCHEDULING 'Appointment Scheduling' 14900 one_time 'Website appointment scheduling setup.'
ensure_item FAM-LEAD-AUTOMATION 'Lead Automation' 29900 one_time 'Lead routing, acknowledgments, alerts, and follow-up setup.'
ensure_item FAM-AI-AGENT 'AI Website Agent Setup' 49900 one_time 'AI website assistant setup around approved business content.'
ensure_item FAM-ANALYTICS 'Growth Analytics — Monthly' 2999 month 'Monthly traffic, lead, and conversion reporting entitlement.'
ensure_item FAM-LOCAL-SEO 'Local SEO Setup' 29900 one_time 'Local search foundation and measurement setup.'
ensure_item FAM-MAINTENANCE 'Website Maintenance — Monthly' 4999 month 'Ongoing website care and managed updates.'
ensure_item FAM-BUSINESS-EMAIL 'Business Email Setup' 9900 one_time 'Branded business email configuration and handoff.'
ensure_item FAM-ECOMMERCE-DISCOVERY 'Ecommerce Discovery' 14900 one_time 'Scoped discovery for a larger ecommerce build.'

jq -n --arg generated_at "$(date -u +%Y-%m-%dT%H:%M:%SZ)" --arg account_id "$(jq -r '.account // empty' <<<"$balance")" --argjson items "$items" \
  '{environment:"stripe_sandbox",livemode:false,generated_at:$generated_at,account_id:$account_id,items:$items}' > "$EVIDENCE_FILE"

echo "PASS: Stripe sandbox catalog is idempotent and contains $(jq '.items | length' "$EVIDENCE_FILE") priced items."
echo "Evidence: $EVIDENCE_FILE"
