#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
RUN_ID="launch-gate-$(date -u +%Y%m%dT%H%M%SZ)"
EVIDENCE_DIR="$REPO_ROOT/.artifacts/launch-gates/$RUN_ID"
mkdir -p "$EVIDENCE_DIR"

status=pass
run_check() {
  local name="$1"; shift
  if "$@" >"$EVIDENCE_DIR/$name.log" 2>&1; then
    jq -cn --arg name "$name" '{name:$name,passed:true}' >>"$EVIDENCE_DIR/assertions.ndjson"
  else
    jq -cn --arg name "$name" '{name:$name,passed:false}' >>"$EVIDENCE_DIR/assertions.ndjson"
    status=fail
  fi
}

run_check synthetic_customer_lifecycle "$SCRIPT_DIR/run-customer-proof-agent.sh"
run_check stripe_provider_lifecycle "$SCRIPT_DIR/stripe-sandbox-billing-acceptance.sh"

if [[ "${FAMTASTIC_PRODUCTION_SMOKE:-0}" == "1" ]]; then
  run_check production_catalog curl --fail --silent --show-error https://famtasticdesigns.com/web/api/customer/catalog
  run_check production_sitemap curl --fail --silent --show-error https://famtasticdesigns.com/sitemap.xml
  run_check production_robots curl --fail --silent --show-error https://famtasticdesigns.com/robots.txt
  if [[ -n "${FAMTASTIC_E2E_CUSTOMER_EMAIL:-}" && -n "${FAMTASTIC_E2E_CUSTOMER_PASSWORD:-}" ]]; then
    run_check production_browser bash -lc "cd '$REPO_ROOT/frontend' && npx playwright test --retries=0 -g 'mobile-safe'"
  fi
fi

jq -s --arg run_id "$RUN_ID" --arg status "$status" --arg generated_at "$(date -u +%FT%TZ)" \
  '{run_id:$run_id,status:$status,generated_at:$generated_at,assertions:.}' \
  "$EVIDENCE_DIR/assertions.ndjson" >"$EVIDENCE_DIR/evidence.json"

{
  echo "# FAMtastic launch gate — $RUN_ID"
  echo
  echo "Status: **$status**"
  echo
  jq -r '.assertions[] | "- " + (if .passed then "PASS" else "FAIL" end) + ": `" + .name + "`"' "$EVIDENCE_DIR/evidence.json"
  echo
  echo "Scenario registry: \`backend/config/famtastic-scenarios.json\`"
} >"$EVIDENCE_DIR/report.md"

echo "Launch gate: $status"
echo "Evidence: $EVIDENCE_DIR/evidence.json"
echo "Report:   $EVIDENCE_DIR/report.md"
[[ "$status" == "pass" ]]
