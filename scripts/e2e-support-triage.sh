#!/usr/bin/env bash
# FAMtastic Designs — AUTONOMOUS_CUSTOMER_SERVICE B2 (L0 draft queue) and
# B4 (SLA clocks + breach alerts) acceptance. Local sqlite + memory transport;
# synthetic messages are cleaned up by the script itself. Nothing transmits.
set -uo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DRUSH="$REPO_ROOT/backend/vendor/bin/drush"
ART="$REPO_ROOT/.artifacts/support-triage/$(date +%s)-b2b4"
mkdir -p "$ART"

OUT="$(EVIDENCE_DIR="$ART" "$DRUSH" -r "$REPO_ROOT/backend/web" php:script "$REPO_ROOT/backend/scripts/e2e-support-triage.php" 2>&1)"
printf '%s\n' "$OUT" | tee "$ART/run.log"

if [[ ! -s "$ART/evidence.json" ]]; then
  printf '{"schema":"famtastic.support-triage-b2-b4.v1","status":false,"note":"script produced no evidence"}\n' > "$ART/evidence.json"
fi

STATUS="$(jq -r '.status' "$ART/evidence.json" 2>/dev/null || echo false)"

if [[ "$STATUS" == "true" ]]; then
  printf 'PASS: B2+B4 accepted — evidence: %s/evidence.json\n' "$ART"
  exit 0
fi
printf 'FAIL: B2/B4 rejected — inspect %s\n' "$ART"
exit 1
