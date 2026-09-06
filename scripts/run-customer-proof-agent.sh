#!/usr/bin/env bash
set -euo pipefail

repo_root="$(cd "$(dirname "$0")/.." && pwd)"
cd "$repo_root"

if [[ "${FAMTASTIC_PROOF_RUNTIME_READY:-0}" != "1" ]]; then
  exec "$repo_root/scripts/e2e-customer-proof-fresh.sh" "$@"
fi

test -x backend/vendor/bin/drush || { echo "ERROR: run composer install in backend first." >&2; exit 1; }
command -v jq >/dev/null || { echo "ERROR: jq is required." >&2; exit 1; }

echo "FAMtastic customer proof agent"
echo "Safety: fresh SQLite runtime, memory email, stub payment, fixture DNS, isolated deploy."
scripts/e2e-commerce-catalog.sh
backend/vendor/bin/drush en -y famtastic_pipeline >/dev/null
backend/vendor/bin/drush updb -y >/dev/null
scripts/e2e-seo-discovery.sh
scripts/e2e-autonomous-journey.sh
php scripts/validate-product-pipeline.php
scripts/e2e-lifecycle-operations.sh
scripts/e2e-hosting-lifecycle.sh
echo "PASS: catalog and complete synthetic customer lifecycle are internally consistent."
