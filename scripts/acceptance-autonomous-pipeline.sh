#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$REPO_ROOT"

if git grep -nE '(sk_live_[A-Za-z0-9]{16,}|whsec_[A-Za-z0-9]{16,}|AKIA[0-9A-Z]{16}|-----BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY-----)' -- ':!docs/**' ':!*.lock'; then
  echo "FAIL: probable production secret found in tracked source." >&2
  exit 1
fi

find backend/web/modules/custom/famtastic_pipeline -name '*.php' -print0 |
  xargs -0 -n1 php -l >/dev/null
backend/vendor/bin/phpunit \
  -c backend/web/core/phpunit.xml.dist \
  backend/web/modules/custom/famtastic_pipeline/tests/src/Unit
scripts/e2e-fresh-backend-install.sh

scripts/e2e-lead-import.sh
scripts/e2e-site-studio-callback.sh
scripts/e2e-email-campaign.sh
PORT=8899 scripts/e2e-proof.sh
PORT=8900 PACKAGE=business_499 EXPECTED_AMOUNT=49900 EXPECTED_REVISIONS=2 scripts/e2e-proof.sh
scripts/e2e-customer-deployment.sh
scripts/e2e-domain-lifecycle.sh
scripts/e2e-hosting-lifecycle.sh
scripts/e2e-analytics.sh

npm --prefix frontend audit --audit-level=high
npm --prefix frontend run build
composer --working-dir=backend audit

echo "PASS: autonomous lead-to-launch acceptance suite completed."
