#!/usr/bin/env bash
#
# FAMtastic Designs — prospect pipeline V1 local end-to-end proof.
#
# Proves the full chain against a real, locally-served Drupal 11:
#   create prospect → secure link → confirm → lead → $199 offer →
#   Stripe test checkout → SIGNED webhook (+ idempotency + tamper reject) →
#   unlock intake (paid gate) → intake + asset → Site Studio brief + JSON →
#   record proof URL → request revision / approve → project delivered.
#
# Uses the stub gateway (no Stripe key needed). The webhook is verified with a
# real HMAC-SHA256 signature, exactly as Stripe signs. Exit code 0 = all pass.
#
# Usage:  ./scripts/e2e-proof.sh
# Env:    PORT (default 8899), STRIPE_WEBHOOK_SECRET (default whsec_local_dev_secret)

set -uo pipefail

BACKEND_DIR="$(cd "$(dirname "$0")/../backend" && pwd)"
PORT="${PORT:-8899}"
BASE="http://127.0.0.1:${PORT}"
SECRET="${STRIPE_WEBHOOK_SECRET:-whsec_local_dev_secret}"
DRUSH="${BACKEND_DIR}/vendor/bin/drush"
PASS=0
FAIL=0
SERVER_PID=""

cleanup() { [ -n "$SERVER_PID" ] && kill "$SERVER_PID" 2>/dev/null; }
trap cleanup EXIT

say()  { printf '\n\033[1m%s\033[0m\n' "$*"; }
ok()   { printf '  \033[32m✓ PASS\033[0m %s\n' "$*"; PASS=$((PASS+1)); }
bad()  { printf '  \033[31m✗ FAIL\033[0m %s\n' "$*"; FAIL=$((FAIL+1)); }

assert_eq()       { [ "$2" = "$3" ] && ok "$1 ($2)" || bad "$1 (expected $3, got $2)"; }
assert_contains() { case "$2" in *"$3"*) ok "$1" ;; *) bad "$1 (missing '$3' in: ${2:0:160})" ;; esac; }

http_code() { curl -s -o /dev/null -w '%{http_code}' "$@"; }

# ---------------------------------------------------------------------------
say "0. Start local Drupal server on :${PORT}"
if [ "$(http_code "${BASE}/" )" = "000" ]; then
  ( cd "$BACKEND_DIR" && "$DRUSH" runserver "127.0.0.1:${PORT}" >/tmp/famtastic-e2e-server.log 2>&1 ) &
  SERVER_PID=$!
  for _ in $(seq 1 20); do
    [ "$(http_code "${BASE}/robots.txt")" != "000" ] && break
    sleep 0.5
  done
fi
[ "$(http_code "${BASE}/robots.txt")" != "000" ] && ok "server responding" || { bad "server not reachable"; exit 1; }

# ---------------------------------------------------------------------------
say "1. Create prospect + issue secure link (drush)"
CREATE_OUT="$(cd "$BACKEND_DIR" && "$DRUSH" famtastic:prospect-create \
  --business-name="E2E Diner ${RANDOM}" --category="Restaurant" \
  --description="Neighborhood breakfast spot" --phone="602-555-0199" \
  --email="owner@e2e-diner.test" --service-area="Tempe, AZ" \
  --source=google --campaign="e2e" --notes="INTERNAL: found via google, no site" 2>&1)"
TOKEN="$(echo "$CREATE_OUT" | awk -F': ' '/Raw token/ {print $2}' | tr -d ' ')"
PID="$(echo "$CREATE_OUT" | awk -F': ' '/Prospect ID/ {print $2}' | tr -d ' ')"
[ -n "$TOKEN" ] && ok "prospect #$PID created, token issued" || { bad "no token"; echo "$CREATE_OUT"; exit 1; }

TH=(-H "X-Prospect-Token: ${TOKEN}")
JH=(-H "Content-Type: application/json")

# ---------------------------------------------------------------------------
say "2. Prospect landing session shows discovered business (deliverable 3)"
SESS="$(curl -s "${TH[@]}" "${BASE}/api/pipeline/session")"
assert_contains "session returns business name" "$SESS" "E2E Diner"
assert_contains "session does NOT leak internal notes" "$( [ "${SESS/INTERNAL/}" = "$SESS" ] && echo CLEAN || echo LEAK )" "CLEAN"

say "2b. Bad token is rejected (security)"
assert_eq "bad token → 404" "$(http_code -H 'X-Prospect-Token: nope' "${BASE}/api/pipeline/session")" "404"

say "3. Paid gate BEFORE payment blocks intake (deliverable 12)"
assert_eq "intake before pay → 402" "$(http_code -X POST "${TH[@]}" "${JH[@]}" -d '{}' "${BASE}/api/pipeline/intake")" "402"

# ---------------------------------------------------------------------------
say "4. Confirm business + contact + authorization → lead (deliverables 4,5)"
CONF="$(curl -s -X POST "${TH[@]}" "${JH[@]}" \
  -d '{"authorized":true,"contact_name":"Dana Owner","contact_method":"email","contact_value":"dana@e2e-diner.test","corrections":{"business_name":"E2E Diner & Cafe"}}' \
  "${BASE}/api/pipeline/confirm")"
assert_contains "confirm returns status lead" "$CONF" '"status":"lead"'

say "4b. Authorization is required (reject authorized=false)"
assert_eq "confirm without authorization → 422" \
  "$(http_code -X POST "${TH[@]}" "${JH[@]}" -d '{"authorized":false}' "${BASE}/api/pipeline/confirm")" "422"

# ---------------------------------------------------------------------------
say "5. Present + purchase the \$199 offer → Stripe test checkout (deliverables 6,8)"
CO="$(curl -s -X POST "${TH[@]}" "${BASE}/api/pipeline/checkout")"
assert_contains "checkout returns a session id" "$CO" '"session_id":"cs_'
SID="$(echo "$CO" | sed -n 's/.*"session_id":"\([^"]*\)".*/\1/p')"

say "5b. Browser-accessible payment simulation is disabled by default"
assert_eq "payment simulation → 403" \
  "$(http_code -X POST "${TH[@]}" "${BASE}/api/pipeline/stripe/simulate")" "403"

say "6. Signature-verified webhook fulfills the order (deliverables 10,11)"
TS="$(date +%s)"
PAYLOAD="$(printf '{"id":"evt_e2e_%s","type":"checkout.session.completed","data":{"object":{"id":"%s","payment_intent":"pi_e2e"}}}' "$TS" "$SID")"
SIG="$(printf '%s.%s' "$TS" "$PAYLOAD" | openssl dgst -sha256 -hmac "$SECRET" | sed 's/^.*= //')"
WH="$(curl -s -X POST -H "Stripe-Signature: t=${TS},v1=${SIG}" "${JH[@]}" -d "$PAYLOAD" "${BASE}/api/pipeline/stripe/webhook")"
assert_contains "webhook fulfilled (paid)" "$WH" '"paid":true'
assert_contains "webhook newly processed" "$WH" '"newly_processed":true'

say "6b. Duplicate webhook is idempotent (deliverable 11)"
WH2="$(curl -s -X POST -H "Stripe-Signature: t=${TS},v1=${SIG}" "${JH[@]}" -d "$PAYLOAD" "${BASE}/api/pipeline/stripe/webhook")"
assert_contains "duplicate not re-processed" "$WH2" '"newly_processed":false'

say "6c. Tampered signature is rejected (deliverable 10)"
assert_eq "bad signature → 400" \
  "$(http_code -X POST -H 'Stripe-Signature: t=1,v1=deadbeef' "${JH[@]}" -d "$PAYLOAD" "${BASE}/api/pipeline/stripe/webhook")" "400"

say "7. Server-verified payment status (deliverable 9)"
assert_contains "order-status paid" "$(curl -s "${TH[@]}" "${BASE}/api/pipeline/order-status")" '"payment_status":"paid"'

# ---------------------------------------------------------------------------
say "8. Intake unlocked after payment (deliverables 12,13)"
INTAKE="$(curl -s -X POST "${TH[@]}" "${JH[@]}" -d '{
  "ideal_customer":"Local families","customer_problem":"No easy way to see the menu",
  "desired_outcome":"More weekend reservations","primary_goal":"Drive reservations",
  "primary_cta":"Book a table","secondary_cta":"See the menu",
  "services":"Breakfast\nBrunch\nCatering","about":"Serving Tempe since 2010",
  "differentiators":"Local ingredients\nDog-friendly patio","required_sections":"Home\nMenu\nContact",
  "brand_colors":"Warm orange and cream","existing_domain":"e2e-diner.test","asset_ownership_confirmed":true
}' "${BASE}/api/pipeline/intake")"
assert_contains "intake accepted" "$INTAKE" '"ok":true'

say "9. Asset upload (logo) (deliverable 14)"
PNG=/tmp/famtastic-e2e-logo.png
python3 -c "import base64,sys;sys.stdout.buffer.write(base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M8AAAMBAQDJ/pLvAAAAAElFTkSuQmCC'))" > "$PNG"
ASSET="$(curl -s -X POST "${TH[@]}" -F "file=@${PNG};type=image/png;filename=logo.png" "${BASE}/api/pipeline/asset")"
assert_contains "asset stored + returns file id" "$ASSET" '"file_id":'

# ---------------------------------------------------------------------------
say "10. Generate Site Studio request: brief + JSON (deliverables 15,16)"
GEN="$(cd "$BACKEND_DIR" && "$DRUSH" famtastic:studio-generate "$PID" 2>&1)"
assert_contains "studio request generated" "$GEN" "generated"
JSON_PATH="$(echo "$GEN" | awk -F': ' '/Exported to/ {print $2}' | tr -d ' ')"
if [ -f "$JSON_PATH" ]; then
  assert_contains "JSON has schema_version" "$(cat "$JSON_PATH")" '"schema_version"'
  assert_contains "JSON has positioning" "$(cat "$JSON_PATH")" '"positioning"'
  assert_contains "JSON carries confirmed name" "$(cat "$JSON_PATH")" 'E2E Diner & Cafe'
  assert_contains "brief file exists" "$( [ -f "${JSON_PATH%.json}.md" ] && echo yes )" "yes"
else
  bad "exported JSON not found at '$JSON_PATH'"
fi

# ---------------------------------------------------------------------------
say "11. Admin records proof URL on the project (deliverable 17)"
(cd "$BACKEND_DIR" && "$DRUSH" eval '
  $pid = '"$PID"';
  $ps = \Drupal::entityTypeManager()->getStorage("famtastic_project");
  $ids = $ps->getQuery()->accessCheck(FALSE)->condition("prospect_ref",$pid)->execute();
  $p = $ps->load(reset($ids));
  $p->set("proof_url","https://proof.sitestudio.dev/e2e-diner")
    ->set("studio_job_id","job_e2e")->set("repo_url","https://github.com/famtastic/e2e-diner")
    ->set("delivery_status","proof_delivered")->save();
  $pr = \Drupal::entityTypeManager()->getStorage("famtastic_prospect")->load($pid);
  $pr->set("status","proof_ready")->save();
' >/dev/null 2>&1) && ok "proof URL recorded" || bad "could not record proof URL"

say "12. Customer proof-review page sees the proof (deliverable 18)"
# JsonResponse escapes forward slashes (\/); strip backslashes before matching.
SESS12="$(curl -s "${TH[@]}" "${BASE}/api/pipeline/session")"
assert_contains "session exposes proof_url" "${SESS12//\\/}" 'proof.sitestudio.dev/e2e-diner'

say "13. Customer requests revision, then approves (deliverable 19)"
assert_contains "request revision" \
  "$(curl -s -X POST "${TH[@]}" "${JH[@]}" -d '{"action":"request_revision","note":"Please use a bigger hero photo."}' "${BASE}/api/pipeline/approval")" \
  '"approval_status":"revision_requested"'
assert_contains "approve" \
  "$(curl -s -X POST "${TH[@]}" "${JH[@]}" -d '{"action":"approve"}' "${BASE}/api/pipeline/approval")" \
  '"approval_status":"approved"'

say "14. Mark the project delivered/launched (admin)"
(cd "$BACKEND_DIR" && "$DRUSH" eval '
  $pid = '"$PID"';
  $ps = \Drupal::entityTypeManager()->getStorage("famtastic_project");
  $ids = $ps->getQuery()->accessCheck(FALSE)->condition("prospect_ref",$pid)->execute();
  $p = $ps->load(reset($ids));
  $p->set("delivery_status","launched")->set("live_url","https://e2e-diner.com")->save();
  $pr = \Drupal::entityTypeManager()->getStorage("famtastic_prospect")->load($pid);
  $pr->set("status","launched")->save();
' >/dev/null 2>&1) && ok "project marked delivered/launched" || bad "could not mark delivered"

# ---------------------------------------------------------------------------
say "RESULT: ${PASS} passed, ${FAIL} failed"
[ "$FAIL" -eq 0 ] && { echo "ALL GREEN — full prospect→paid→intake→Site Studio→approval chain proven."; exit 0; } || exit 1
