#!/usr/bin/env bash
# FAMtastic Designs — customer-portal link & experience audit (deep).
# Boots the local Drupal API and the Vite frontend, seeds a controlled test
# customer plus its own synthetic workspace data (draft request, open thread,
# token-scoped client portal), then drives a headless Chromium through every
# portal section the dashboard can render. Asserts per surface: render OK,
# no fake-affordance patterns, no synthetic strings in customer-visible
# content, notices context-scoped, no horizontal overflow past the viewport
# marker. Idempotent; removes its own synthetic data afterwards. Local-only.
set -uo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DRUSH="$REPO_ROOT/backend/vendor/bin/drush"
PORT_API="${AUDIT_PORT:-8935}"
PORT_UI="${UI_PORT:-8937}"
BASE_UI="http://127.0.0.1:$PORT_UI"
ART="$REPO_ROOT/.artifacts/portal-audit/$(date +%s)"
mkdir -p "$ART"

EMAIL="${FAMTASTIC_E2E_CUSTOMER_EMAIL:-portal-crawler@qa.famtasticdesigns.com}"
PASSWORD="${FAMTASTIC_E2E_CUSTOMER_PASSWORD:-local-crawler-pass-2026-rotate-me}"
NAME="${FAMTASTIC_E2E_CUSTOMER_NAME:-Portal Crawl Lead}"
BUSINESS="${FAMTASTIC_E2E_CUSTOMER_BUSINESS:-Portal Crawl Studio}"

log() { printf '%s\n' "$*"; }

cleanup_servers() {
  [[ -n "${UI_PID:-}" ]] && { kill "$UI_PID" 2>/dev/null || true; }
  [[ -n "${API_PID:-}" ]] && { kill "$API_PID" 2>/dev/null || true; }
}
trap 'cleanup_servers' EXIT

sqlq() { { "$DRUSH" -r "$REPO_ROOT/backend/web" sqlq "$1" 2>/dev/null | grep -m1 . || true; } | tr -d '[:space:]'; }

# --- Resolve (or create) the controlled customer's local ids -----------------
lookup_ids() {
  UID_=$(sqlq "SELECT uid FROM users_field_data WHERE mail='$EMAIL'")
  CID=$(sqlq "SELECT id FROM famtastic_customer WHERE email='$EMAIL'")
  ORG=""
  if [[ -n "${CID:-}" ]]; then
    ORG=$(sqlq "SELECT organization_id FROM famtastic_membership WHERE customer_id=$CID LIMIT 1")
  fi
}

# --- Cleanup: remove every trace of previous crawl data (idempotency) --------
cleanup_data() {
  lookup_ids
  if [[ -n "${ORG:-}" ]]; then
    sqlq "DELETE FROM famtastic_portal_message WHERE thread_id IN (SELECT id FROM famtastic_portal_thread WHERE organization_id=$ORG)" >/dev/null
    sqlq "DELETE FROM famtastic_portal_thread WHERE organization_id=$ORG" >/dev/null
    sqlq "DELETE FROM famtastic_portal_activity WHERE organization_id=$ORG" >/dev/null
    PROSPECTS=$("$DRUSH" -r "$REPO_ROOT/backend/web" sqlq "SELECT DISTINCT prospect_id FROM famtastic_project_request WHERE organization_id=$ORG AND prospect_id IS NOT NULL" 2>/dev/null | grep -E '^[0-9]+$' | tr '\n' ' ')
    sqlq "DELETE FROM famtastic_project_request WHERE organization_id=$ORG" >/dev/null
    for pid in $PROSPECTS; do
      [[ -n "$pid" ]] && "$DRUSH" -r "$REPO_ROOT/backend/web" entity:delete famtastic_prospect "$pid" >/dev/null 2>&1 || true
    done
    sqlq "DELETE FROM famtastic_membership WHERE organization_id=$ORG" >/dev/null
    sqlq "DELETE FROM famtastic_organization WHERE id=$ORG" >/dev/null
  fi
  if [[ -n "${CID:-}" ]]; then sqlq "DELETE FROM famtastic_customer WHERE id=$CID" >/dev/null; fi
  if [[ -n "${UID_:-}" ]]; then "$DRUSH" -r "$REPO_ROOT/backend/web" entity:delete user "$UID_" >/dev/null 2>&1 || true; fi
  # Sweep prospects orphaned by earlier interrupted runs of this crawler.
  ORPHANS=$("$DRUSH" -r "$REPO_ROOT/backend/web" sqlq "SELECT id FROM famtastic_prospect WHERE business_name LIKE '${BUSINESS}%'" 2>/dev/null | grep -E '^[0-9]+$' | tr '\n' ' ')
  for pid in $ORPHANS; do
    "$DRUSH" -r "$REPO_ROOT/backend/web" entity:delete famtastic_prospect "$pid" >/dev/null 2>&1 || true
  done
}

log "== portal audit $(date -u +%FT%TZ) =="
log "artifacts: $ART"

# Remove leftovers from any previous run before seeding (idempotency).
cleanup_data

"$DRUSH" -r "$REPO_ROOT/backend/web" runserver "127.0.0.1:$PORT_API" >"$ART/api.log" 2>&1 &
API_PID=$!
for i in $(seq 1 30); do curl -s -o /dev/null "http://127.0.0.1:$PORT_API" && break; sleep 1; done

# --- Seed ---------------------------------------------------------------------
export FAMTASTIC_E2E_CUSTOMER_EMAIL="$EMAIL" FAMTASTIC_E2E_CUSTOMER_PASSWORD="$PASSWORD"
export FAMTASTIC_E2E_CUSTOMER_NAME="$NAME" FAMTASTIC_E2E_CUSTOMER_BUSINESS="$BUSINESS"
"$DRUSH" -r "$REPO_ROOT/backend/web" php-script "$REPO_ROOT/backend/scripts/provision-e2e-customer.php" >"$ART/seed.log" 2>&1 \
  || { log "FATAL: seeding failed (see $ART/seed.log)"; exit 2; }
lookup_ids
[[ -n "${CID:-}" && -n "${ORG:-}" ]] || { log "FATAL: seeded ids missing"; exit 2; }
log "seed ok (customer=$CID org=$ORG)"

JAR="$ART/cookies.txt"
LOGIN_CODE=$(curl -s -o "$ART/login.json" -w "%{http_code}" -c "$JAR" -H 'Content-Type: application/json' \
  -d "{\"email\":\"$EMAIL\",\"password\":\"$PASSWORD\"}" "http://127.0.0.1:$PORT_API/api/customer/login")
[[ "$LOGIN_CODE" == "200" ]] || { log "FATAL: customer login failed ($LOGIN_CODE)"; cat "$ART/login.json"; exit 2; }

ORG_PUBLIC=$(python3 -c "import json;print(json.load(open('$ART/login.json'))['organizations'][0]['public_id'])" 2>/dev/null)
[[ -n "$ORG_PUBLIC" ]] || ORG_PUBLIC=$(curl -s -b "$JAR" "http://127.0.0.1:$PORT_API/api/customer/workspace" | python3 -c "import sys,json;print(json.load(sys.stdin)['organization']['public_id'])")

# Open thread with one customer message (exercises messages + reply affordances).
CSRF=$(curl -s -b "$JAR" "http://127.0.0.1:$PORT_API/session/token")
curl -s -o /dev/null -b "$JAR" -H 'Content-Type: application/json' -H "X-CSRF-Token: $CSRF" \
  -d "{\"kind\":\"support\",\"subject\":\"Launch timing question\",\"body\":\"Could we move the launch date up one week?\",\"organization\":\"$ORG_PUBLIC\"}" \
  "http://127.0.0.1:$PORT_API/api/customer/threads" >/dev/null
THREAD_ID=$(curl -s -b "$JAR" "http://127.0.0.1:$PORT_API/api/customer/workspace" | python3 -c "import sys,json;d=json.load(sys.stdin);print(d['threads'][0]['public_id'] if d['threads'] else '')")

# --- Frontend dev server (proxies /api to the local backend) ------------------
(cd "$REPO_ROOT/frontend" && VITE_DRUPAL_PROXY_TARGET="http://127.0.0.1:$PORT_API" npm run dev -- --host 127.0.0.1 --port "$PORT_UI" --strictPort >"$ART/ui.log" 2>&1) &
UI_PID=$!
UI_UP=0
for i in $(seq 1 45); do
  curl -s -o /dev/null "$BASE_UI" && UI_UP=1 && break
  sleep 1
done
[[ "$UI_UP" == 1 ]] || { log "FATAL: frontend dev server did not start (see $ART/ui.log)"; exit 2; }
log "frontend ready on $BASE_UI"

# --- Client-portal token (prospect command center at /portal/:token) ---------
CLIENT_TOKEN=""
CLIENT_PROSPECT_ID=""
PROSPECT_LINE=$("$DRUSH" -r "$REPO_ROOT/backend/web" fpc --business-name="$BUSINESS website" --category="QA" --source=customer_portal 2>/dev/null || true)
TOKEN_LINE=$(printf '%s\n' "$PROSPECT_LINE" | grep -m1 'Raw token' || true)
ID_LINE=$(printf '%s\n' "$PROSPECT_LINE" | grep -m1 'Prospect ID' || true)
if [[ -n "$ID_LINE" ]]; then CLIENT_PROSPECT_ID="${ID_LINE//[^0-9]/}"; fi
if [[ -n "$TOKEN_LINE" ]]; then
  CLIENT_TOKEN="${TOKEN_LINE#*Raw token   : }"
  export FAMTASTIC_CRAWL_CLIENT_TOKEN="$CLIENT_TOKEN" FAMTASTIC_CRAWL_CLIENT_BUSINESS="$BUSINESS website"
fi

# --- Static CSS guard assertions ----------------------------------------------
CSS_GUARDS=(
  ".portal-app{width:100%;max-width:100%;overflow-x:clip}"
  ".portal-grid > * { min-width: 0; }"
  ".portal-conversation { overflow: hidden; }"
  ".portal-thread-list button strong { overflow-wrap: anywhere; }"
)
GUARD_FAIL=0
for g in "${CSS_GUARDS[@]}"; do
  if ! grep -qF "$g" "$REPO_ROOT/frontend/src/portal.css"; then
    log "FAIL css-guard: missing '$g'"
    GUARD_FAIL=$((GUARD_FAIL+1))
  fi
done

# --- Crawl ----------------------------------------------------------------------
CRAWL_JSON="$ART/crawl-results.json"
set +e
(cd "$REPO_ROOT/frontend" && node e2e/portal-links.crawl.mjs "$BASE_UI" "$EMAIL" "$PASSWORD" "$CRAWL_JSON") >"$ART/crawl-stdout.log" 2>"$ART/crawl.log"
CRAWL_RC=$?
set -e

if [[ -s "$CRAWL_JSON" ]]; then
  python3 - "$CRAWL_JSON" <<'PY'
import json, sys
with open(sys.argv[1]) as f:
    data = json.load(f)
print(f"\n{'SURFACE':<34} {'VERDICT':<6} DETAIL")
for r in data["results"]:
    detail = "; ".join(r["failures"] + r["warnings"]) or "-"
    print(f"{r['name']:<34} {r['verdict']:<6} {detail[:90]}")
print(f"\nFailures: {data['failCount']}   Warnings: {data['warnCount']}")
PY
else
  log "WARN: crawl produced no result JSON — raw driver log follows:"
  sed -n '1,40p' "$ART/crawl.log"
fi

# --- Cleanup own synthetic data ---------------------------------------------------
cleanup_data
if [[ -n "${CLIENT_PROSPECT_ID:-}" ]]; then
  "$DRUSH" -r "$REPO_ROOT/backend/web" entity:delete famtastic_prospect "$CLIENT_PROSPECT_ID" >/dev/null 2>&1 || true
fi
AFTER=$(sqlq "SELECT COUNT(*) FROM famtastic_customer WHERE email='$EMAIL'")
if [[ "${AFTER:-1}" == "0" ]]; then log "cleanup ok (no crawl rows remain)"; else log "WARN cleanup left rows for $EMAIL"; fi

log ""
if [[ $CRAWL_RC -ne 0 || $GUARD_FAIL -gt 0 ]]; then
  log "RESULT: FAIL (crawl rc=$CRAWL_RC css-guards-failed=$GUARD_FAIL)"
  exit 1
fi
log "RESULT: PASS (warnings are findings, not blockers — see table above)"
exit 0
