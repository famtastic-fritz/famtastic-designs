#!/usr/bin/env bash
#
# FAMtastic Designs — repeatable Stripe TEST-MODE product + $199 price setup.
#
# Creates (once) a "FAMtastic Basic Website" product and a $199 one-time price
# in Stripe TEST mode, then prints the price id to put in STRIPE_PRICE_ID.
# Safe to re-run: it looks the product up by metadata before creating.
#
# Requires a TEST secret key (starts with sk_test_). If none is supplied it
# prints exactly what to do and exits 0 — so it never fails a dry run.
#
#   STRIPE_SECRET_KEY=sk_test_xxx ./scripts/stripe-setup.sh
#
# NEVER use a live key here. This script refuses keys that start with sk_live_.

set -uo pipefail

KEY="${STRIPE_SECRET_KEY:-}"
API="https://api.stripe.com/v1"
LOOKUP_KEY="famtastic_basic_199"
AMOUNT=19900
CURRENCY=usd

if [ -z "$KEY" ]; then
  cat <<'EOF'
No STRIPE_SECRET_KEY set — nothing was created (this is fine).

To create the $199 test product + price:
  1. Get your TEST secret key from https://dashboard.stripe.com/test/apikeys
     (it starts with sk_test_).
  2. Run:  STRIPE_SECRET_KEY=sk_test_xxx ./scripts/stripe-setup.sh
  3. Put the printed price id into the backend environment as STRIPE_PRICE_ID
     (and STRIPE_SECRET_KEY / STRIPE_WEBHOOK_SECRET). See docs.

The app also works with NO Stripe key at all (stub gateway) for local proofs.
EOF
  exit 0
fi

case "$KEY" in
  sk_live_*) echo "ERROR: refusing a LIVE key. Use a sk_test_ key only."; exit 1 ;;
  sk_test_*) ;;
  *) echo "WARNING: key does not look like sk_test_ — continuing anyway." ;;
esac

sapi() { # method path [form-args...]
  local method="$1" path="$2"; shift 2
  curl -s -u "${KEY}:" -X "$method" "${API}${path}" "$@"
}

echo "Looking up existing price by lookup_key=${LOOKUP_KEY}…"
EXISTING="$(sapi GET "/prices?lookup_keys[]=${LOOKUP_KEY}&limit=1" | python3 -c 'import sys,json;d=json.load(sys.stdin);print(d["data"][0]["id"] if d.get("data") else "")' 2>/dev/null)"
if [ -n "$EXISTING" ]; then
  echo "Price already exists: ${EXISTING}"
  echo "STRIPE_PRICE_ID=${EXISTING}"
  exit 0
fi

echo "Creating product…"
PRODUCT_ID="$(sapi POST "/products" \
  -d "name=FAMtastic Basic Website" \
  -d "description=A professional, mobile-ready website built for your business." \
  -d "metadata[famtastic_package]=basic_199" \
  | python3 -c 'import sys,json;print(json.load(sys.stdin).get("id",""))')"
[ -z "$PRODUCT_ID" ] && { echo "Failed to create product."; exit 1; }
echo "  product: ${PRODUCT_ID}"

echo "Creating \$199 one-time price…"
PRICE_ID="$(sapi POST "/prices" \
  -d "product=${PRODUCT_ID}" \
  -d "unit_amount=${AMOUNT}" \
  -d "currency=${CURRENCY}" \
  -d "lookup_key=${LOOKUP_KEY}" \
  | python3 -c 'import sys,json;print(json.load(sys.stdin).get("id",""))')"
[ -z "$PRICE_ID" ] && { echo "Failed to create price."; exit 1; }

echo ""
echo "Done. Set this in the backend environment:"
echo "  STRIPE_PRICE_ID=${PRICE_ID}"
