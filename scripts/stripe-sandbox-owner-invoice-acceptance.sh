#!/usr/bin/env bash
set -euo pipefail

repo_root="$(cd "$(dirname "$0")/.." && pwd)"
project="${STRIPE_CLI_PROJECT:-famtastic-sandbox-auth}"
run_id="$(date +%s)-$$"
evidence_dir="${STRIPE_EVIDENCE_DIR:-$repo_root/.artifacts/stripe/owner-invoice-$run_id}"
mkdir -p "$evidence_dir"

command -v stripe >/dev/null || { echo 'ERROR: Stripe CLI is required.' >&2; exit 1; }
command -v jq >/dev/null || { echo 'ERROR: jq is required.' >&2; exit 1; }
balance="$(stripe balance retrieve --project-name="$project" --color=off)"
test "$(jq -r '.livemode' <<<"$balance")" = false || { echo 'ERROR: refusing live Stripe mode.' >&2; exit 1; }

payment_method() {
  stripe payment_methods create --project-name="$project" --type=card -d "card[token]=$1" --confirm --color=off | jq -r .id
}

success_method="$(payment_method tok_visa)"
success_raw="$(stripe payment_intents create --project-name="$project" --amount=10000 --currency=usd \
  --payment-method="$success_method" --confirm=true --automatic-payment-methods.enabled=true \
  -d 'automatic_payment_methods[allow_redirects]=never' \
  -d "metadata[famtastic_test]=owner-invoice-$run_id-success" --confirm --color=off)"
success_id="$(jq -r .id <<<"$success_raw")"
success_charge="$(jq -r .latest_charge <<<"$success_raw")"

refund_raw="$(stripe refunds create --project-name="$project" --charge="$success_charge" \
  -d "metadata[famtastic_test]=owner-invoice-$run_id-refund" --confirm --color=off)"

decline_method="$(payment_method tok_chargeDeclined)"
set +e
decline_raw="$(stripe payment_intents create --project-name="$project" --amount=10000 --currency=usd \
  --payment-method="$decline_method" --confirm=true --automatic-payment-methods.enabled=true \
  -d 'automatic_payment_methods[allow_redirects]=never' \
  -d "metadata[famtastic_test]=owner-invoice-$run_id-decline" --confirm --color=off 2>&1)"
decline_status=$?
set -e
decline_json="${decline_raw#*\{}"
decline_json="{${decline_json}"
test "$(jq -r '.error.code // empty' <<<"$decline_json")" = 'card_declined' || {
  echo "ERROR: decline fixture did not return card_declined (CLI status $decline_status)." >&2
  exit 1
}

abandon_raw="$(stripe payment_intents create --project-name="$project" --amount=10000 --currency=usd \
  --automatic-payment-methods.enabled=true -d 'automatic_payment_methods[allow_redirects]=never' \
  -d "metadata[famtastic_test]=owner-invoice-$run_id-abandon" --color=off)"
abandon_id="$(jq -r .id <<<"$abandon_raw")"
cancel_raw="$(stripe payment_intents cancel "$abandon_id" --project-name="$project" --confirm --color=off)"

jq -n \
  --arg run_id "$run_id" \
  --arg generated_at "$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
  --argjson success "$(jq '{id,status,livemode,amount,currency,latest_charge}' <<<"$success_raw")" \
  --argjson refund "$(jq '{id,status,amount,currency,charge}' <<<"$refund_raw")" \
  --argjson decline "$(jq '{type:.error.type,code:.error.code,decline_code:.error.decline_code,id:.error.payment_intent.id,status:.error.payment_intent.status}' <<<"$decline_json")" \
  --argjson abandon "$(jq '{id,status,livemode,amount,currency}' <<<"$abandon_raw")" \
  --argjson canceled "$(jq '{id,status,livemode,amount,currency}' <<<"$cancel_raw")" \
  '{schema:"famtastic.owner-invoice-stripe-sandbox-proof.v1",environment:"stripe_test",run_id:$run_id,generated_at:$generated_at,
    boundary:"Provider objects only. Drupal Commerce linkage, signed webhook replay, invoice activation, receipt, and portal state require separate application evidence.",
    checks:{success:(($success.status=="succeeded") and ($success.livemode==false) and ($success.amount==10000) and ($success.currency=="usd")),
      decline:(($decline.code=="card_declined") and ($decline.status=="requires_payment_method")),
      abandonment:(($abandon.status=="requires_payment_method") and ($abandon.livemode==false) and ($abandon.amount==10000)),
      cancellation:(($canceled.status=="canceled") and ($canceled.livemode==false) and ($canceled.amount==10000)),
      refund:(($refund.status=="succeeded") and ($refund.amount==10000) and ($refund.currency=="usd") and ($refund.charge==$success.latest_charge))},
    provider_objects:{success_payment_intent:$success.id,declined_payment_intent:$decline.id,abandoned_payment_intent:$abandon.id,canceled_payment_intent:$canceled.id,refund:$refund.id}}' \
  > "$evidence_dir/evidence.json"

jq -e '.checks | to_entries | all(.value == true)' "$evidence_dir/evidence.json" >/dev/null
echo 'PASS: Stripe TEST $100 success, decline, abandonment, cancellation, and full refund verified.'
echo "Evidence: $evidence_dir/evidence.json"
