#!/usr/bin/env bash
# FAMtastic Designs — Campaign Operations channel-health card acceptance.
# Renders /admin/famtastic/campaigns locally and asserts the Channel health
# section reflects the live Postiz integrations API. Local-only; nothing
# publishes and no credentials are read from the repository.
set -uo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DRUSH="$REPO_ROOT/backend/vendor/bin/drush"
ART="$REPO_ROOT/.artifacts/channel-status/$(date +%s)"
mkdir -p "$ART"
FAILURES=0
fail() { printf 'FAIL: %s\n' "$1"; FAILURES=$((FAILURES+1)); }
pass() { printf 'PASS: %s\n' "$1"; }

export FAMTASTIC_POSTIZ_API_KEY="${FAMTASTIC_POSTIZ_API_KEY:-}"
if [[ -z "$FAMTASTIC_POSTIZ_API_KEY" ]]; then
  PG_CONTAINER="${POSTIZ_PG_CONTAINER:-postiz-postgres}"
  export FAMTASTIC_POSTIZ_API_KEY="$(docker exec "$PG_CONTAINER" sh -c 'psql -U "$POSTGRES_USER" -d "${POSTGRES_DB:-postiz-db-local}" -t -A -c "SELECT \"apiKey\" FROM \"Organization\" WHERE \"apiKey\" IS NOT NULL LIMIT 1"' 2>/dev/null | head -1)"
fi

HTML="$("$DRUSH" -r "$REPO_ROOT/backend/web" php:script "$REPO_ROOT/backend/scripts/e2e-render-dashboard.php" 2>"$ART/render.err")"
printf '%s\n' "$HTML" > "$ART/dashboard.html"

if [[ -z "$HTML" ]]; then
  fail "dashboard render produced no output (see $ART/render.err)"
  printf '{"schema":"famtastic.channel-status.v1","status":false,"failures":%s,"generated_at":"%s"}\n' \
    "$FAILURES" "$(date -u +%Y-%m-%dT%H:%M:%SZ)" > "$ART/evidence.json"
  exit 1
fi

grep -q "Channel health" <<<"$HTML" \
  && pass "Channel health section present on Campaign Operations" \
  || fail "Channel health section missing"

if [[ -n "$FAMTASTIC_POSTIZ_API_KEY" ]]; then
  grep -qi "Facebook · FAMTastic Designs" <<<"$HTML" \
    && pass "facebook platform card rendered from live Postiz data" \
    || fail "facebook card missing though Postiz key was provided"
  grep -q ">Connected<" <<<"$HTML" \
    && pass "connected state badge rendered" \
    || fail "no Connected state found in rendered cards"
else
  pass "(no key available) asserting unconfigured hint path only"
  grep -q "Not configured" <<<"$HTML" || fail "unconfigured hint missing"
fi

STATUS=true
[[ $FAILURES -eq 0 ]] || STATUS=false
jq -n --argjson status "$STATUS" --argjson failures "$FAILURES" --arg at "$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
  '{schema:"famtastic.channel-status.v1", status:$status, failures:$failures, generated_at:$at}' > "$ART/evidence.json"

if [[ $FAILURES -eq 0 ]]; then
  printf 'Evidence: %s/evidence.json\n' "$ART"
  exit 0
fi
printf 'Evidence: %s/evidence.json (%d failure(s))\n' "$ART" "$FAILURES"
exit 1
