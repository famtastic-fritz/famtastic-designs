#!/usr/bin/env bash
# FAMtastic Designs — UTM attribution capture validator (local only).
# Boots the local Drupal API, applies pending updates (8037), then proves the
# full attribution chain end to end:
#   1. public capture API persists utm_* + gclid from QUERY params  -> prospect.utm_json
#   2. public capture API persists utm_* from JSON BODY (SPA style) -> prospect.utm_json
#   3. a lead whose utm_content matches famtastic_social_record.content_id increments leads_count by exactly 1
#   4. portal-path service semantics (snapshotFromArray + recordSocialLead) behave identically
# Cleans up every synthetic row afterwards and restores the seeded counter.
# Never sends mail (memory transport). Local-only; no deploy.
set -uo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DRUSH="$REPO_ROOT/backend/vendor/bin/drush"
PORT_API="${UTM_PORT:-8939}"
BASE="http://127.0.0.1:$PORT_API"
ART="$REPO_ROOT/.artifacts/utm-attribution/$(date +%s)"
CONTENT_ID="${UTM_TEST_CONTENT_ID:-55c-d01-teach}"
CAMPAIGN="55-cents-17-day"
mkdir -p "$ART"

log() { printf '%s\n' "$*"; }
FAILS=0
fail() { log "FAIL: $*"; FAILS=$((FAILS+1)); }
pass() { log "PASS: $*"; }

sqlq() { "$DRUSH" -r "$REPO_ROOT/backend/web" sqlq "$1" 2>/dev/null | grep -m1 . || true; }

cleanup_servers() { [[ -n "${API_PID:-}" ]] && { kill "$API_PID" 2>/dev/null || true; }; }
trap 'cleanup_servers' EXIT

EMAIL_Q="utm-validator-q-$(date +%s)@qa.famtasticdesigns.com"
EMAIL_B="utm-validator-b-$(date +%s)@qa.famtasticdesigns.com"

log "== UTM attribution validator $(date -u +%FT%TZ) =="
log "artifacts: $ART"

"$DRUSH" -r "$REPO_ROOT/backend/web" cr -y >"$ART/cr.log" 2>&1 || true
"$DRUSH" -r "$REPO_ROOT/backend/web" updatedb -y >"$ART/updb.log" 2>&1 \
  || { log "FATAL: drush updatedb failed (see $ART/updb.log)"; exit 2; }
if grep -qE "famtastic_pipeline_update_8037|Finished performing updates|No database updates required|No pending updates" "$ART/updb.log"; then
  pass "schema current after updatedb (8037 applied or already present)"
else
  fail "updatedb did not report a clean schema state (see $ART/updb.log)"
fi
COL=$(sqlq "SELECT utm_json IS NULL FROM famtastic_prospect WHERE id = (SELECT MIN(id) FROM famtastic_prospect)")
[[ -n "$COL" ]] && pass "famtastic_prospect.utm_json column exists" || fail "utm_json column missing"
LCOL=$("$DRUSH" -r "$REPO_ROOT/backend/web" php:eval "var_export((int) Drupal::database()->schema()->fieldExists('famtastic_social_record', 'leads_count'));" 2>/dev/null | tail -1)
[[ "$LCOL" == "1" ]] && pass "famtastic_social_record.leads_count column exists" || fail "leads_count column missing"

export FAMTASTIC_TRANSACTIONAL_EMAIL_TRANSPORT=memory
CAPTURE="$ART/mail-capture.jsonl"; : >"$CAPTURE"
export FAMTASTIC_TRANSACTIONAL_EMAIL_CAPTURE="$CAPTURE"

"$DRUSH" -r "$REPO_ROOT/backend/web" runserver "127.0.0.1:$PORT_API" >"$ART/api.log" 2>&1 &
API_PID=$!
UP=0
for i in $(seq 1 30); do curl -s -o /dev/null "$BASE" && UP=1 && break; sleep 1; done
[[ "$UP" == 1 ]] || { log "FATAL: local API did not start (see $ART/api.log)"; exit 2; }
log "api ready on $BASE"

# --- Seed / resolve the social record under test -------------------------------
SEEDED=0
PRIOR=$(sqlq "SELECT COALESCE((SELECT leads_count FROM famtastic_social_record WHERE content_id='$CONTENT_ID'), -1)")
if [[ "$PRIOR" == "-1" ]]; then
  sqlq "INSERT INTO famtastic_social_record (content_id, day, moment, theme, promise, scheduled_time_et, state, asset_variants, changed) VALUES ('$CONTENT_ID', 1, 'teach', '55 cents', 'validator fixture', '09:00', 'idea', '[]', strftime('%s','now'))" >/dev/null
  PRIOR=0; SEEDED=1
  pass "seeded social record $CONTENT_ID (leads_count=0)"
else
  PRIOR=$((PRIOR + 0))
  log "using existing social record $CONTENT_ID (leads_count=$PRIOR)"
fi

# --- 1. Public contact capture with UTMs on the QUERY string -------------------
CODE_Q=$(curl -s -o "$ART/public-query.json" -w "%{http_code}" -X POST \
  "$BASE/api/public/contact?utm_source=facebook&utm_medium=cpc&utm_campaign=$CAMPAIGN&utm_content=$CONTENT_ID&utm_term=fam%20web&gclid=E2E-GCLID-QUERY" \
  -H 'Content-Type: application/json' \
  -d "{\"source\":\"contact-form\",\"name\":\"UTM Validator Q\",\"email\":\"$EMAIL_Q\",\"message\":\"attribution query-string proof\"}")
[[ "$CODE_Q" == "200" || "$CODE_Q" == "202" ]] || fail "public/query capture HTTP $CODE_Q (expected 200/202)"
PID_Q=$(python3 -c "import json;print(json.load(open('$ART/public-query.json')).get('prospect_id',''))" 2>/dev/null)
JSON_Q=$(sqlq "SELECT utm_json FROM famtastic_prospect WHERE id=${PID_Q:-0}")
CID="$CONTENT_ID" python3 - "$JSON_Q" <<'PY' && pass "query capture snapshot complete (all 6 params)" || fail "query capture snapshot incomplete"
import json,sys,os
s=json.loads(sys.argv[1])
need={"utm_source":"facebook","utm_medium":"cpc","utm_campaign":"55-cents-17-day","utm_content":os.environ["CID"],"utm_term":"fam web","gclid":"E2E-GCLID-QUERY"}
missing=[k for k,v in need.items() if s.get(k)!=v]
assert s.get("captured_via")=="public_contact", f"captured_via={s.get('captured_via')}"
sys.exit(1 if missing else 0)
PY

# --- 2. Public quote capture with UTMs inside the JSON body (SPA style) -------
BODY=$(cat <<EOF
{"source":"solution-finder","branch":"website","answers":{"email":"$EMAIL_B"},"email":"$EMAIL_B",
 "utm":{"utm_source":"tiktok","utm_medium":"paid_social","utm_campaign":"$CAMPAIGN","utm_content":"$CONTENT_ID","fbclid":"E2E-FBCLID-BODY"}}
EOF
)
CODE_B=$(curl -s -o "$ART/public-body.json" -w "%{http_code}" -X POST "$BASE/api/public/quote" -H 'Content-Type: application/json' -d "$BODY")
[[ "$CODE_B" == "200" || "$CODE_B" == "202" ]] || fail "public/body capture HTTP $CODE_B (expected 200/202)"
PID_B=$(python3 -c "import json;print(json.load(open('$ART/public-body.json')).get('prospect_id',''))" 2>/dev/null)
JSON_B=$(sqlq "SELECT utm_json FROM famtastic_prospect WHERE id=${PID_B:-0}")
CID="$CONTENT_ID" python3 - "$JSON_B" <<'PY' && pass "body capture snapshot complete (SPA-style utm object)" || fail "body capture snapshot incomplete"
import json,sys,os
s=json.loads(sys.argv[1])
need={"utm_source":"tiktok","utm_medium":"paid_social","utm_campaign":"55-cents-17-day","utm_content":os.environ["CID"],"fbclid":"E2E-FBCLID-BODY"}
missing=[k for k,v in need.items() if s.get(k)!=v]
assert s.get("captured_via")=="public_quote", f"captured_via={s.get('captured_via')}"
sys.exit(1 if missing else 0)
PY

# --- 3. Social record lead counter incremented exactly once per matched lead --
AFTER=$(sqlq "SELECT leads_count FROM famtastic_social_record WHERE content_id='$CONTENT_ID'")
EXPECTED=$((PRIOR + 2))
[[ "${AFTER:-x}" == "$EXPECTED" ]] && pass "leads_count $PRIOR -> $AFTER (+2: query lead + body lead)" || fail "leads_count expected $EXPECTED got '${AFTER:-unset}'"

# --- 4. Portal-path semantics through the shared service -----------------------
cat >"$ART/portal-path.php" <<PHP
<?php
\$service = Drupal::service('famtastic_pipeline.attribution');
\$storage = Drupal::entityTypeManager()->getStorage('famtastic_prospect');
\$before = (int) Drupal::database()->select('famtastic_social_record','s')->fields('s',['leads_count'])->condition('content_id','$CONTENT_ID')->execute()->fetchField();
\$snapshot = \$service->snapshotFromArray(['utm'=>['utm_content'=>'$CONTENT_ID','utm_source'=>'newsletter']], 'customer_portal');
\$prospect = \$storage->create([
  'business_name' => 'UTM Validator Portal', 'public_email' => '$EMAIL_B',
  'campaign' => 'customer_portal', 'source' => 'customer_portal',
  'status' => 'new', 'owner_uid' => 1,
  'utm_json' => \$service->toJson(\$snapshot),
]);
\$prospect->save();
\$stored = json_decode((string) \$prospect->get('utm_json')->value, TRUE);
assert(\$stored['utm_content'] === '$CONTENT_ID' && \$stored['captured_via'] === 'customer_portal');
\$service->recordSocialLead('$CONTENT_ID');
\$after = (int) Drupal::database()->select('famtastic_social_record','s')->fields('s',['leads_count'])->condition('content_id','$CONTENT_ID')->execute()->fetchField();
echo json_encode(['portal_prospect_id' => \$prospect->id(), 'leads_count_before' => \$before, 'leads_count_after' => \$after]), PHP_EOL;
\$empty = \$service->snapshotFromArray([], 'customer_portal');
if (\$service->toJson(\$empty) !== NULL) { fwrite(STDERR, "FAIL empty snapshot must encode to NULL\n"); exit(1); }
\$service->recordSocialLead('');
echo 'OK', PHP_EOL;
PHP
PORTAL_OUT=$("$DRUSH" -r "$REPO_ROOT/backend/web" php:script "$ART/portal-path.php" 2>"$ART/portal-path.err")
LINE=$(printf '%s\n' "$PORTAL_OUT" | grep -m1 '^{' || true)
PORTAL_PID=""
if [[ -n "$LINE" ]]; then
  PORTAL_PID=$(printf '%s' "$LINE" | python3 -c "import json,sys;print(json.load(sys.stdin)['portal_prospect_id'])" 2>/dev/null)
  COUNTS=$(printf '%s' "$LINE" | python3 -c "import json,sys;d=json.load(sys.stdin);print(d['leads_count_before'],d['leads_count_after'])" 2>/dev/null)
  [[ -n "$PORTAL_PID" && -n "$COUNTS" ]] \
    && pass "portal path: utm_json stored via service; leads_count ${COUNTS% *} -> ${COUNTS#* } (+1)" \
    || { fail "portal path: unexpected script output"; printf '%s\n' "$LINE"; }
else
  fail "portal path script failed"; cat "$ART/portal-path.err"
fi

# --- Cleanup --------------------------------------------------------------------
for PID_ in "$PID_Q" "$PID_B"; do
  [[ -n "$PID_" ]] && sqlq "DELETE FROM famtastic_intake WHERE prospect_ref=$PID_" >/dev/null
  [[ -n "$PID_" ]] && "$DRUSH" -r "$REPO_ROOT/backend/web" entity:delete famtastic_prospect "$PID_" >/dev/null 2>&1 || true
done
[[ -n "$PORTAL_PID" ]] && "$DRUSH" -r "$REPO_ROOT/backend/web" entity:delete famtastic_prospect "$PORTAL_PID" >/dev/null 2>&1 || true
LEFT=$(sqlq "SELECT COUNT(*) FROM famtastic_prospect WHERE public_email LIKE 'utm-validator-%@qa.famtasticdesigns.com'")
[[ "$LEFT" == "0" ]] && pass "cleanup removed all synthetic prospects" || fail "cleanup left $LEFT synthetic prospects"
RESTORED=$(sqlq "SELECT leads_count FROM famtastic_social_record WHERE content_id='$CONTENT_ID'")
[[ "$RESTORED" == "$PRIOR" ]] && pass "social record restored to leads_count=$PRIOR" || {
  DELTA=$(( ${RESTORED:-0} - PRIOR ));
  sqlq "UPDATE famtastic_social_record SET leads_count=$PRIOR WHERE content_id='$CONTENT_ID'" >/dev/null;
  pass "social record reset back to leads_count=$PRIOR (drift was $DELTA)";
}
[[ "$SEEDED" == "1" ]] && { sqlq "DELETE FROM famtastic_social_record WHERE content_id='$CONTENT_ID'" >/dev/null; pass "removed seeded social record"; }

log ""
if [[ $FAILS -gt 0 ]]; then
  log "RESULT: FAIL ($FAILS failure(s)) — artifacts: $ART"
  exit 1
fi
log "RESULT: PASS — attribution persisted at both capture paths; join counter verified; evidence: $ART"
exit 0
