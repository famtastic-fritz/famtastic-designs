#!/usr/bin/env bash
set -euo pipefail

repo_root="$(cd "$(dirname "$0")/.." && pwd)"
node "$repo_root/scripts/validate-stripe-provider-e2e-matrix.mjs"

if [[ "${FAMTASTIC_STRIPE_PROVIDER_E2E:-0}" != "1" ]]; then
  echo 'SCAFFOLD ONLY: provider execution is intentionally disabled. Set FAMTASTIC_STRIPE_PROVIDER_E2E=1 only in a fresh disposable runtime with explicit operator approval.'
  exit 0
fi

echo 'BLOCKED: the provider runner has not been implemented. The matrix prevents a false Stripe-proof claim until Checkout, 3DS, decline, abandon, signed-webhook replay, Commerce, fulfillment, receipt, and portal evidence are captured together.' >&2
exit 2
