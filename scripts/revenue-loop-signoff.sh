#!/usr/bin/env bash
# Read-only/isolated verification harness for the FAMtastic revenue loop.
# It never deploys, charges, sends customer mail, or contacts production.
set -euo pipefail

repo_root="${1:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
full="${FAMTASTIC_REVENUE_SIGNOFF_FULL:-0}"
backend="$repo_root/backend"
module="$backend/web/modules/custom/famtastic_pipeline"

test -f "$backend/config/famtastic-products.json"
test -f "$backend/config/famtastic-deal-terms.json"

php "$repo_root/scripts/validate-product-pipeline.php" \
  "$backend/config/famtastic-products.json" \
  "$backend/config/famtastic-deal-terms.json"

while IFS= read -r -d '' source; do
  php -l "$source" >/dev/null
done < <(find "$module/src" -type f -name '*.php' -print0)
echo "PASS: PHP syntax"

node "$repo_root/scripts/validate-client-portal-design-dna.mjs"
echo "PASS: portal design contract"

npm --prefix "$repo_root/frontend" run build
echo "PASS: frontend build"

if [[ "$full" == "1" ]]; then
  proof_runner="/Users/famtastic-fritz/.codex/skills/prove-famtastic-customer-journey/scripts/run-proof.sh"
  test -x "$proof_runner"
  "$proof_runner" "$repo_root"
  echo "PASS: canonical isolated customer journey"
else
  cat <<'EOF'
SKIP: canonical isolated customer journey (set FAMTASTIC_REVENUE_SIGNOFF_FULL=1).
This static signoff is not payment, provider, deployment, or launch proof.
EOF
fi
