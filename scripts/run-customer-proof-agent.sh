#!/usr/bin/env bash
set -euo pipefail

repo_root="$(cd "$(dirname "$0")/.." && pwd)"
cd "$repo_root"

test -x backend/vendor/bin/drush || { echo "ERROR: run composer install in backend first." >&2; exit 1; }
command -v jq >/dev/null || { echo "ERROR: jq is required." >&2; exit 1; }

echo "FAMtastic customer proof agent"
echo "Safety: local DB, memory email, stub payment, fixture DNS, isolated deploy."
scripts/e2e-commerce-catalog.sh
scripts/e2e-autonomous-journey.sh
php scripts/validate-product-pipeline.php
scripts/e2e-lifecycle-operations.sh
scripts/e2e-hosting-lifecycle.sh
echo "PASS: catalog and complete synthetic customer lifecycle are internally consistent."
