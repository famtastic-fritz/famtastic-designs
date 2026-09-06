#!/usr/bin/env bash
set -euo pipefail

# End-to-end regression for the public-preview account-claim boundary. It is
# deliberately local-only: it uses a SQLite Drupal database and the memory
# transactional-mail transport, creates only synthetic records, and never
# dispatches an outbox message or executes a proof job.

repo_root="$(cd "$(dirname "$0")/.." && pwd)"
drush="$repo_root/backend/vendor/bin/drush"
port="${PORT:-8956}"
base="http://127.0.0.1:$port"
run="$(date +%s)-$$"
sandbox="$(mktemp -d "${TMPDIR:-/tmp}/famtastic-preview-registration.XXXXXX")"
state="$sandbox/state.json"
mail_capture="$sandbox/mail.jsonl"
server_log="$sandbox/server.log"
server_pid=""

cleanup() {
  if [[ -n "$server_pid" ]]; then
    kill "$server_pid" 2>/dev/null || true
    wait "$server_pid" 2>/dev/null || true
  fi
  case "$sandbox" in
    "${TMPDIR:-/tmp}"/famtastic-preview-registration.*) rm -rf "$sandbox" ;;
    *) echo "Refusing to remove unexpected sandbox: $sandbox" >&2 ;;
  esac
}
trap cleanup EXIT

assert_json() {
  local json="$1"
  shift
  jq -e "$@" <<<"$json" >/dev/null
}

assert_equals() {
  local actual="$1"
  local expected="$2"
  local label="$3"
  if [[ "$actual" != "$expected" ]]; then
    echo "FAIL: $label (expected $expected, got $actual)" >&2
    exit 1
  fi
}

sql_scalar() {
  "$drush" sqlq "$1" | tr -d '[:space:]'
}

test "${FAMTASTIC_PREVIEW_SIGNUP_ISOLATION_CONFIRM:-}" = "LOCAL_ONLY" || {
  echo "Refusing to run without FAMTASTIC_PREVIEW_SIGNUP_ISOLATION_CONFIRM=LOCAL_ONLY." >&2
  exit 2
}
test -x "$drush" || { echo "ERROR: backend Composer dependencies are required." >&2; exit 2; }
command -v curl >/dev/null || { echo "ERROR: curl is required." >&2; exit 2; }
command -v jq >/dev/null || { echo "ERROR: jq is required." >&2; exit 2; }
assert_equals "$("$drush" status --field=db-driver)" "sqlite" "local SQLite database requirement"

"$drush" en -y famtastic_pipeline >/dev/null
"$drush" updb -y >/dev/null
"$drush" cr >/dev/null

FAMTASTIC_PREVIEW_SIGNUP_ISOLATION_STATE="$state" \
FAMTASTIC_PREVIEW_SIGNUP_ISOLATION_RUN="$run" \
  "$drush" php:script "$repo_root/backend/web/modules/custom/famtastic_pipeline/tests/fixtures/e2e-preview-registration-isolation.php"

preview_email="$(jq -r '.preview.email' "$state")"
preview_prospect_id="$(jq -r '.preview.prospect_id' "$state")"
preview_delivery_id="$(jq -r '.preview.delivery_id' "$state")"
preview_continuation="$(jq -r '.preview.continuation' "$state")"
ordinary_email="$(jq -r '.ordinary.email' "$state")"
ordinary_prospect_id="$(jq -r '.ordinary.prospect_id' "$state")"
started_at="$(jq -r '.started_at' "$state")"

caller_dir="$PWD"
cd "$repo_root/backend"
FAMTASTIC_TRANSACTIONAL_EMAIL_TRANSPORT=memory \
FAMTASTIC_TRANSACTIONAL_EMAIL_CAPTURE="$mail_capture" \
  "$drush" runserver "127.0.0.1:$port" >"$server_log" 2>&1 &
server_pid=$!
cd "$caller_dir"
for _ in $(seq 1 40); do
  curl -s -o /dev/null -w '%{http_code}' "$base/robots.txt" | grep -qv '^000$' && break
  sleep 0.25
done
test "$(curl -s -o /dev/null -w '%{http_code}' "$base/robots.txt")" != "000"

preview_password="Preview-${run}-Pass!"
preview_registration="$(jq -nc --arg email "$preview_email" --arg password "$preview_password" --arg continuation "$preview_continuation" '{email:$email,password:$password,name:"Preview Fixture",preview_continuation:$continuation}')"
preview_response="$(curl -sS -X POST -H 'Content-Type: application/json' -d "$preview_registration" "$base/api/customer/register")"
assert_json "$preview_response" '.ok == true and .verification_required == true'

preview_customer_id="$(sql_scalar "SELECT id FROM famtastic_customer WHERE email = '$preview_email' ORDER BY id DESC LIMIT 1;")"
test -n "$preview_customer_id"
assert_equals "$(sql_scalar "SELECT COUNT(*) FROM famtastic_project_request WHERE prospect_id = $preview_prospect_id;")" "0" "preview signup request count before verification"
assert_equals "$(sql_scalar "SELECT COUNT(*) FROM famtastic_job WHERE prospect_id = $preview_prospect_id AND job_key LIKE 'website_proof.generate.v1:request:%';")" "0" "preview signup generic proof jobs before verification"
assert_equals "$(sql_scalar "SELECT COUNT(*) FROM famtastic_notification_outbox WHERE created >= $started_at AND notification_key LIKE 'website-request:%';")" "0" "preview signup customer/staff request notifications before verification"
assert_equals "$(sql_scalar "SELECT COUNT(*) FROM famtastic_notification_outbox WHERE notification_key = 'customer:$preview_customer_id:staff-registration';")" "0" "preview signup registration alert before verification"
assert_equals "$(sql_scalar "SELECT COUNT(*) FROM famtastic_preview_delivery WHERE id = $preview_delivery_id AND customer_id IS NULL AND signup_started_at > 0;")" "1" "preview remains unclaimed before verification"

verification_url="$(jq -rsr --arg email "$preview_email" '[.[] | select(.to == $email and (.subject | test("Verify")))] | last.body' "$mail_capture" | sed -nE 's#.*(https?://[^ ]+/verify-email\?token=[^ ]+).*#\1#p')"
verification_token="${verification_url##*token=}"
test -n "$verification_token"
verified="$(curl -sS -X POST -H 'Content-Type: application/json' -d "{\"token\":\"$verification_token\"}" "$base/api/customer/verify")"
assert_json "$verified" '.ok == true'

assert_equals "$(sql_scalar "SELECT customer_id FROM famtastic_preview_delivery WHERE id = $preview_delivery_id;")" "$preview_customer_id" "same-email preview claim after verification"
assert_equals "$(sql_scalar "SELECT COUNT(*) FROM famtastic_preview_delivery WHERE id = $preview_delivery_id AND claimed_at > 0;")" "1" "claimed preview timestamp"
assert_equals "$(sql_scalar "SELECT COUNT(*) FROM famtastic_project_request WHERE prospect_id = $preview_prospect_id;")" "0" "preview signup request count after verification"
assert_equals "$(sql_scalar "SELECT COUNT(*) FROM famtastic_job WHERE prospect_id = $preview_prospect_id AND job_key LIKE 'website_proof.generate.v1:request:%';")" "0" "preview signup generic proof jobs after verification"
assert_equals "$(sql_scalar "SELECT COUNT(*) FROM famtastic_notification_outbox WHERE notification_key = 'customer:$preview_customer_id:staff-registration';")" "1" "deferred preview registration alert after verification"
verified_alert_body="$("$drush" sqlq "SELECT body FROM famtastic_notification_outbox WHERE notification_key = 'customer:$preview_customer_id:staff-registration';")"
[[ "$verified_alert_body" == *"Email verification completed."* ]] || {
  echo "FAIL: preview owner alert must describe completed verification." >&2
  exit 1
}

ordinary_password="Ordinary-${run}-Pass!"
ordinary_registration="$(jq -nc --arg email "$ordinary_email" --arg password "$ordinary_password" '{email:$email,password:$password,name:"Ordinary Fixture"}')"
ordinary_response="$(curl -sS -X POST -H 'Content-Type: application/json' -d "$ordinary_registration" "$base/api/customer/register")"
assert_json "$ordinary_response" '.ok == true and .verification_required == true'
ordinary_request_id="$(sql_scalar "SELECT id FROM famtastic_project_request WHERE prospect_id = $ordinary_prospect_id ORDER BY id DESC LIMIT 1;")"
test -n "$ordinary_request_id"
assert_equals "$(sql_scalar "SELECT COUNT(*) FROM famtastic_job WHERE prospect_id = $ordinary_prospect_id AND job_key LIKE 'website_proof.generate.v1:request:$ordinary_request_id:brief:%';")" "1" "ordinary discovery registration proof job"
assert_equals "$(sql_scalar "SELECT COUNT(*) FROM famtastic_notification_outbox WHERE notification_key = 'website-request:$ordinary_request_id:customer';")" "1" "ordinary discovery customer notification"
assert_equals "$(sql_scalar "SELECT COUNT(*) FROM famtastic_notification_outbox WHERE notification_key = 'website-request:$ordinary_request_id:staff';")" "1" "ordinary discovery staff notification"

echo "PASS: valid public-preview continuation creates no request, request notification, or generic proof job before verification."
echo "PASS: same-email preview is claimed only after verification, with the owner registration alert deferred."
echo "PASS: ordinary discovery-based registration still creates its request, notifications, and generic proof job."
