#!/usr/bin/env bash
set -euo pipefail

repo_root="$(cd "$(dirname "$0")/.." && pwd)"
project="${STRIPE_CLI_PROJECT:-famtastic-sandbox-auth}"
run_id="$(date +%s)-$$"
evidence_dir="${STRIPE_EVIDENCE_DIR:-$repo_root/.artifacts/stripe/billing-$run_id}"
catalog="$repo_root/.artifacts/stripe/sandbox-catalog.json"
mkdir -p "$evidence_dir"

command -v stripe >/dev/null || { echo 'ERROR: Stripe CLI is required.' >&2; exit 1; }
command -v jq >/dev/null || { echo 'ERROR: jq is required.' >&2; exit 1; }
balance="$(stripe balance retrieve --project-name="$project" --color=off)"
test "$(jq -r '.livemode' <<<"$balance")" = false || { echo 'ERROR: refusing live Stripe mode.' >&2; exit 1; }
test -f "$catalog" || "$repo_root/scripts/stripe-sandbox-catalog.sh"
hosting_price="$(jq -r '.items[] | select(.sku=="FAM-HOST-999" and .interval=="month" and .amount_minor==999) | .price_id' "$catalog")"
test -n "$hosting_price"

payment_method() {
  stripe payment_methods create --project-name="$project" --type=card -d "card[token]=$1" --confirm --color=off | jq -r .id
}
intent() {
  local method="$1" label="$2"
  stripe payment_intents create --project-name="$project" --amount=19900 --currency=usd --payment-method="$method" --confirm=true \
    --automatic-payment-methods.enabled=true -d 'automatic_payment_methods[allow_redirects]=never' \
    -d "metadata[famtastic_test]=$label" --color=off 2>&1
}

success_pm="$(payment_method tok_visa)"
decline_pm="$(payment_method tok_chargeDeclined)"
three_ds_pm="$(payment_method tok_threeDSecure2Required)"
success_raw="$(intent "$success_pm" "billing-$run_id-success")"
decline_raw="$(intent "$decline_pm" "billing-$run_id-decline")"
three_ds_raw="$(intent "$three_ds_pm" "billing-$run_id-3ds")"

success="$(jq '{id,status,livemode,amount,currency}' <<<"$success_raw")"
decline="$(jq '{type:.error.type,code:.error.code,decline_code:.error.decline_code,id:.error.payment_intent.id,status:.error.payment_intent.status}' <<<"$decline_raw")"
three_ds="$(jq '{id,status,livemode,next_action_type:.next_action.type}' <<<"$three_ds_raw")"

customer="$(stripe customers create --project-name="$project" --email="billing-$run_id@example.test" -d "metadata[famtastic_test]=billing-$run_id-subscription" --color=off | jq -r .id)"
subscription_pm="$(payment_method tok_visa)"
stripe payment_methods attach "$subscription_pm" --project-name="$project" --customer="$customer" --confirm --color=off >/dev/null
subscription_raw="$(stripe subscriptions create --project-name="$project" --customer="$customer" --default-payment-method="$subscription_pm" \
  --payment-behavior=error_if_incomplete -d "items[0][price]=$hosting_price" -d "metadata[famtastic_test]=billing-$run_id-subscription" --confirm --color=off)"
subscription_id="$(jq -r .id <<<"$subscription_raw")"
canceled_raw="$(stripe subscriptions cancel "$subscription_id" --project-name="$project" --confirm --color=off 2>&1)"
canceled_json="${canceled_raw#*\{}"; canceled_json="{${canceled_json}"
stripe customers delete "$customer" --project-name="$project" --confirm --color=off >/dev/null 2>&1 || true

jq -n \
  --arg run_id "$run_id" --arg generated_at "$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
  --argjson success "$success" --argjson decline "$decline" --argjson three_ds "$three_ds" \
  --argjson subscription "$(jq '{id,status,livemode,latest_invoice}' <<<"$subscription_raw")" \
  --argjson canceled "$(jq '{id,status,livemode,canceled_at}' <<<"$canceled_json")" \
  '{schema:"famtastic.stripe-billing-proof.v1",environment:"stripe_sandbox",run_id:$run_id,generated_at:$generated_at,
    checks:{success:(($success.status=="succeeded") and ($success.livemode==false) and ($success.amount==19900)),
      decline:(($decline.code=="card_declined") and ($decline.status=="requires_payment_method")),
      three_ds:(($three_ds.status=="requires_action") and ($three_ds.next_action_type=="use_stripe_sdk") and ($three_ds.livemode==false)),
      subscription_active:(($subscription.status=="active") and ($subscription.livemode==false)),
      subscription_canceled:(($canceled.status=="canceled") and ($canceled.livemode==false))},
    provider_objects:{success_payment_intent:$success.id,declined_payment_intent:$decline.id,three_ds_payment_intent:$three_ds.id,
      subscription:$subscription.id,invoice:$subscription.latest_invoice}}' > "$evidence_dir/evidence.json"

jq -e '.checks | to_entries | all(.value == true)' "$evidence_dir/evidence.json" >/dev/null
echo 'PASS: Stripe TEST success, decline, 3DS challenge, USD 9.99 subscription, and customer cancellation verified.'
echo "Evidence: $evidence_dir/evidence.json"
