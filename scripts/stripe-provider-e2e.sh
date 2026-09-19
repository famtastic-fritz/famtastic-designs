#!/usr/bin/env bash
set -euo pipefail

repo_root="$(cd "$(dirname "$0")/.." && pwd)"
# Separate read-only capability path; success is NOT an E2E payment receipt.
if [[ "${1:-}" == --preflight && "$#" == 1 ]]; then
  node "$repo_root/scripts/validate-stripe-provider-e2e-matrix.mjs" >&2
  exec node "$repo_root/scripts/stripe-provider-preflight.mjs"
fi
if [[ "$#" != 0 ]]; then
  echo 'Usage: stripe-provider-e2e.sh [--preflight]' >&2
  exit 2
fi
node "$repo_root/scripts/validate-stripe-provider-e2e-matrix.mjs"

if [[ "${FAMTASTIC_STRIPE_PROVIDER_E2E:-0}" != "1" ]]; then
  echo 'SCAFFOLD ONLY: provider execution is intentionally disabled. Set FAMTASTIC_STRIPE_PROVIDER_E2E=1 only in a fresh disposable runtime with explicit operator approval.'
  exit 0
fi

echo 'BLOCKED: the provider runner has not been implemented. The matrix prevents a false Stripe-proof claim until Checkout, 3DS, decline, abandon, signed-webhook replay, Commerce, fulfillment, receipt, and portal evidence are captured together.' >&2
exit 2
