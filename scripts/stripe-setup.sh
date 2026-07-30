#!/usr/bin/env bash
#
# FAMtastic Designs — repeatable Stripe TEST-MODE product + $199 price setup.
#
# Creates (once, idempotently) the product:
#     FAMtastic $199 Launch Site
# and a $199 USD one-time price, tagged with metadata:
#     famtastic_product=launch_site  famtastic_package=basic_199  environment=test
# then prints the resulting price id (a price_… id is NOT a secret).
#
# Two credential sources, in priority order — TEST MODE ONLY in both:
#   1. STRIPE_SECRET_KEY=sk_test_…  → uses the Stripe REST API over HTTPS.
#   2. otherwise, the authenticated Stripe CLI (`stripe login`) if installed.
#
# NEVER touches live mode: it refuses sk_live_ keys and never passes --live to
# the CLI. If neither source is available it prints guidance and exits 0.

set -uo pipefail

KEY="${STRIPE_SECRET_KEY:-}"
API="https://api.stripe.com/v1"
LOOKUP_KEY="famtastic_basic_199"
PRODUCT_NAME="FAMtastic \$199 Launch Site"
AMOUNT=19900
CURRENCY=usd

# Some environments prepend a non-JSON hint line to CLI output; strip it.
jqid() { python3 -c 'import sys,json
raw="".join(l for l in sys.stdin if "claude-code-hint" not in l)
try: d=json.loads(raw)
except Exception: print(""); sys.exit()
print(d["data"][0]["id"] if isinstance(d.get("data"),list) and d["data"] else (d.get("id") or ""))'; }

price_match() { python3 -c 'import sys,json
raw="".join(l for l in sys.stdin if "claude-code-hint" not in l)
try: d=json.loads(raw)
except Exception: sys.exit()
for p in d.get("data",[]):
    if p.get("active") and p.get("unit_amount")=='"$AMOUNT"' and p.get("currency")=="'"$CURRENCY"'" and not p.get("recurring"):
        print(p["id"]); break'; }

# ---------------------------------------------------------------------------
# Mode 2: authenticated Stripe CLI (no key in env).
# ---------------------------------------------------------------------------
if [ -z "$KEY" ] && command -v stripe >/dev/null 2>&1; then
  echo "Using the authenticated Stripe CLI (TEST mode)."

  # Guard: never operate against live mode via this script.
  #   (No command below passes --live; the CLI defaults to test.)

  echo "Searching for an existing product by metadata…"
  PROD="$(stripe products search --query "metadata['famtastic_product']:'launch_site'" 2>/dev/null | jqid)"
  if [ -n "$PROD" ]; then
    echo "  reusing product: ${PROD}"
  else
    echo "  creating product: ${PRODUCT_NAME}"
    PROD="$(stripe products create \
      --name "${PRODUCT_NAME}" \
      -d "metadata[famtastic_product]=launch_site" \
      -d "metadata[famtastic_package]=basic_199" \
      -d "metadata[environment]=test" 2>/dev/null | jqid)"
  fi
  [ -z "$PROD" ] && { echo "Failed to find or create product."; exit 1; }

  # Reuse an existing active $199 one-time price on this product if present.
  PRICE="$(stripe prices list --product "$PROD" --limit 100 2>/dev/null | price_match)"
  if [ -n "$PRICE" ]; then
    echo "  reusing price: ${PRICE}"
  else
    echo "  creating \$199 one-time price…"
    PRICE="$(stripe prices create \
      --product "$PROD" \
      --unit-amount "$AMOUNT" \
      --currency "$CURRENCY" \
      -d "metadata[famtastic_product]=launch_site" \
      -d "metadata[famtastic_package]=basic_199" \
      -d "metadata[environment]=test" 2>/dev/null | jqid)"
  fi
  [ -z "$PRICE" ] && { echo "Failed to find or create price."; exit 1; }

  echo ""
  echo "PRODUCT_ID=${PROD}"
  echo "STRIPE_PRICE_ID=${PRICE}"
  exit 0
fi

# ---------------------------------------------------------------------------
# No key and no CLI: guidance only (never fails).
# ---------------------------------------------------------------------------
if [ -z "$KEY" ]; then
  cat <<'EOF'
No STRIPE_SECRET_KEY set and no authenticated Stripe CLI found — nothing created.

Options:
  * Install + `stripe login`, then re-run this script (test mode), OR
  * STRIPE_SECRET_KEY=sk_test_xxx ./scripts/stripe-setup.sh

The app also works with NO Stripe at all (stub gateway) for local proofs.
EOF
  exit 0
fi

# ---------------------------------------------------------------------------
# Mode 1: explicit TEST secret key (REST API).
# ---------------------------------------------------------------------------
case "$KEY" in
  sk_live_*) echo "ERROR: refusing a LIVE key. Use a sk_test_ key only."; exit 1 ;;
  sk_test_*) ;;
  rk_live_*) echo "ERROR: refusing a LIVE restricted key."; exit 1 ;;
  *) echo "WARNING: key does not look like sk_test_ — continuing anyway." ;;
esac

sapi() { local method="$1" path="$2"; shift 2; curl -s -u "${KEY}:" -X "$method" "${API}${path}" "$@"; }

echo "Searching for an existing product by metadata…"
PROD="$(sapi GET "/products/search?query=metadata%5B%27famtastic_product%27%5D%3A%27launch_site%27" | jqid)"
if [ -n "$PROD" ]; then
  echo "  reusing product: ${PROD}"
else
  echo "  creating product: ${PRODUCT_NAME}"
  PROD="$(sapi POST "/products" \
    -d "name=${PRODUCT_NAME}" \
    -d "metadata[famtastic_product]=launch_site" \
    -d "metadata[famtastic_package]=basic_199" \
    -d "metadata[environment]=test" | jqid)"
fi
[ -z "$PROD" ] && { echo "Failed to find or create product."; exit 1; }

PRICE="$(sapi GET "/prices?product=${PROD}&active=true&limit=100" | price_match)"
if [ -n "$PRICE" ]; then
  echo "  reusing price: ${PRICE}"
else
  echo "  creating \$199 one-time price…"
  PRICE="$(sapi POST "/prices" \
    -d "product=${PROD}" -d "unit_amount=${AMOUNT}" -d "currency=${CURRENCY}" \
    -d "metadata[famtastic_product]=launch_site" \
    -d "metadata[famtastic_package]=basic_199" \
    -d "metadata[environment]=test" | jqid)"
fi
[ -z "$PRICE" ] && { echo "Failed to find or create price."; exit 1; }

echo ""
echo "PRODUCT_ID=${PROD}"
echo "STRIPE_PRICE_ID=${PRICE}"
