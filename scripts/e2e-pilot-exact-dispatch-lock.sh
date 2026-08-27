#!/usr/bin/env bash
set -euo pipefail

# Disposable Drupal acceptance for the exact-ID public-preview safety lock.
# It proves both independent scheduler routes (`drush cron` / hook_cron and
# `famtastic:lifecycle-run`) leave a queued general job and general outbox row
# untouched when the durable Drupal config lock is on. It also proves a fresh
# process-only env lock works, normal operation remains available when both
# are off, the generic callback rejects declared verified-cold work, and the
# dynamic scheduled-release command cannot execute a due-record selection.
# No SMTP, provider, customer, proof, deployment, or production state is used.

repo_root="$(cd "$(dirname "$0")/.." && pwd)"
sandbox="$(mktemp -d "${TMPDIR:-/tmp}/famtastic-pilot-exact-lock.XXXXXX")"
run_id="$(date +%s)-$$"
mail_capture="$sandbox/mail.jsonl"
verified_cold_state="$sandbox/verified-cold-state.json"
server_log="$sandbox/server.log"
server_pid=""
port="${PORT:-$((9000 + ($$ % 500)))}"

cleanup() {
  local original_exit=$?
  trap - EXIT
  if [[ -n "$server_pid" ]]; then
    kill "$server_pid" 2>/dev/null || true
    wait "$server_pid" 2>/dev/null || true
  fi
  case "$sandbox" in
    "${TMPDIR:-/tmp}"/famtastic-pilot-exact-lock.*)
      chmod -R u+rwX "$sandbox" 2>/dev/null || true
      rm -rf "$sandbox"
      ;;
    *)
      echo "Refusing to remove unexpected sandbox: $sandbox" >&2
      ;;
  esac
  exit "$original_exit"
}
trap cleanup EXIT

assert_equals() {
  local actual="$1" expected="$2" label="$3"
  if [[ "$actual" != "$expected" ]]; then
    echo "FAIL: $label (expected $expected, got $actual)" >&2
    exit 1
  fi
}

test "${FAMTASTIC_PILOT_EXACT_DISPATCH_LOCK_CONFIRM:-}" = "LOCAL_ONLY" || {
  echo "Refusing to run without FAMTASTIC_PILOT_EXACT_DISPATCH_LOCK_CONFIRM=LOCAL_ONLY." >&2
  exit 2
}
for command_name in curl jq rsync openssl; do
  command -v "$command_name" >/dev/null || { echo "Missing required command: $command_name" >&2; exit 2; }
done
test -x "$repo_root/backend/vendor/bin/drush" || { echo "Run composer install in backend before this local acceptance test." >&2; exit 2; }

runtime_vendor="$(cd -P "$repo_root/backend/vendor" && pwd)"
runtime_backend="$(cd "$runtime_vendor/.." && pwd)"
test -d "$runtime_backend/web/core" || { echo "The installed Drupal runtime is missing web/core." >&2; exit 2; }

mkdir -p "$sandbox/backend"
rsync -a --exclude vendor --exclude private --exclude 'web/sites/default/files' "$repo_root/backend/" "$sandbox/backend/"
rsync -aL "$repo_root/backend/vendor/" "$sandbox/backend/vendor/"
rsync -a "$runtime_backend/web/core/" "$sandbox/backend/web/core/"
rsync -a --ignore-existing "$runtime_backend/web/modules/" "$sandbox/backend/web/modules/"
rsync -a --ignore-existing "$runtime_backend/web/profiles/" "$sandbox/backend/web/profiles/"
rsync -a --ignore-existing "$runtime_backend/web/themes/" "$sandbox/backend/web/themes/"
for runtime_file in .ht.router.php .htaccess autoload.php autoload_runtime.php index.php robots.txt update.php; do
  cp "$runtime_backend/web/$runtime_file" "$sandbox/backend/web/$runtime_file"
done
cp "$runtime_backend/web/sites/default/default.settings.php" "$sandbox/backend/web/sites/default/default.settings.php"
chmod -R u+rwX "$sandbox/backend/web/sites/default"
mkdir -p "$sandbox/backend/web/sites/default/files" "$sandbox/backend/private"
perl -0pi -e 's/\n\$databases\['\''default'\''\]\['\''default'\''\] = array \(\n.*?\n\);\n/\n/s' "$sandbox/backend/web/sites/default/settings.php"

drush=("$sandbox/backend/vendor/bin/drush" "--root=$sandbox/backend/web")
"${drush[@]}" site:install standard --db-url="sqlite://sites/default/files/.ht.sqlite" --account-name=admin --account-pass=admin --account-mail=admin@famtastic.local --site-name="FAMtastic pilot lock fixture" --site-mail=no-reply@famtastic.local -y >/dev/null
"${drush[@]}" en -y famtastic_pipeline >/dev/null
"${drush[@]}" updb -y >/dev/null
"${drush[@]}" cr >/dev/null

"${drush[@]}" php:eval '
  $now = \Drupal::time()->getRequestTime();
  $database = \Drupal::database();
  $database->insert("famtastic_job")->fields([
    "job_key" => "e2e-pilot-lock-general-job",
    "job_type" => "unsupported_e2e",
    "prospect_id" => NULL,
    "status" => "queued",
    "attempts" => 0,
    "max_attempts" => 5,
    "available_at" => $now,
    "payload" => "{}",
    "created" => $now,
    "changed" => $now,
  ])->execute();
  $database->insert("famtastic_notification_outbox")->fields([
    "notification_key" => "e2e-pilot-lock-general-outbox",
    "category" => "operational",
    "recipient" => "pilot-lock@example.test",
    "subject" => "Pilot lock fixture",
    "body" => "This local fixture must never leave memory transport while locked.",
    "status" => "queued",
    "attempts" => 0,
    "max_attempts" => 5,
    "available_at" => $now,
    "created" => $now,
    "changed" => $now,
  ])->execute();
  \Drupal::configFactory()->getEditable("famtastic_pipeline.settings")->set("pilot_exact_dispatch_only", TRUE)->save(TRUE);
'

# Durable config is intentionally tested from a new Drush process with no
# pilot environment variable. This mirrors cPanel's independent cron process.
FAMTASTIC_TRANSACTIONAL_EMAIL_TRANSPORT=memory \
FAMTASTIC_TRANSACTIONAL_EMAIL_CAPTURE="$mail_capture" \
  "${drush[@]}" cron >/dev/null
assert_equals "$("${drush[@]}" sql:query "SELECT status FROM famtastic_job WHERE job_key = 'e2e-pilot-lock-general-job'" | tr -d '[:space:]')" "queued" "hook_cron general job state while durable lock is active"
assert_equals "$("${drush[@]}" sql:query "SELECT status FROM famtastic_notification_outbox WHERE notification_key = 'e2e-pilot-lock-general-outbox'" | tr -d '[:space:]')" "queued" "hook_cron general outbox state while durable lock is active"
test ! -s "$mail_capture" || { echo "FAIL: hook_cron emitted mail while durable pilot lock was active." >&2; exit 1; }

set +e
lifecycle_output="$(FAMTASTIC_TRANSACTIONAL_EMAIL_TRANSPORT=memory FAMTASTIC_TRANSACTIONAL_EMAIL_CAPTURE="$mail_capture" "${drush[@]}" famtastic:lifecycle-run --limit=1 2>&1)"
lifecycle_exit=$?
set -e
assert_equals "$lifecycle_exit" "1" "lifecycle-run exit while durable lock is active"
[[ "$lifecycle_output" == *"Pilot exact-dispatch lock is active"* ]] || { echo "FAIL: lifecycle-run did not report the exact pilot lock." >&2; exit 1; }
assert_equals "$("${drush[@]}" sql:query "SELECT status FROM famtastic_job WHERE job_key = 'e2e-pilot-lock-general-job'" | tr -d '[:space:]')" "queued" "lifecycle general job state while durable lock is active"
assert_equals "$("${drush[@]}" sql:query "SELECT status FROM famtastic_notification_outbox WHERE notification_key = 'e2e-pilot-lock-general-outbox'" | tr -d '[:space:]')" "queued" "lifecycle general outbox state while durable lock is active"

# The environment variable remains an additive emergency lock. Clear durable
# config, start yet another fresh process, and prove the env alone blocks it.
"${drush[@]}" config:set famtastic_pipeline.settings pilot_exact_dispatch_only 0 -y >/dev/null
"${drush[@]}" cr >/dev/null
FAMTASTIC_PILOT_EXACT_DISPATCH_ONLY=1 \
FAMTASTIC_TRANSACTIONAL_EMAIL_TRANSPORT=memory \
FAMTASTIC_TRANSACTIONAL_EMAIL_CAPTURE="$mail_capture" \
  "${drush[@]}" cron >/dev/null
assert_equals "$("${drush[@]}" sql:query "SELECT status FROM famtastic_job WHERE job_key = 'e2e-pilot-lock-general-job'" | tr -d '[:space:]')" "queued" "hook_cron general job state while env lock is active"
assert_equals "$("${drush[@]}" sql:query "SELECT status FROM famtastic_notification_outbox WHERE notification_key = 'e2e-pilot-lock-general-outbox'" | tr -d '[:space:]')" "queued" "hook_cron general outbox state while env lock is active"

set +e
env_lifecycle_output="$(FAMTASTIC_PILOT_EXACT_DISPATCH_ONLY=1 FAMTASTIC_TRANSACTIONAL_EMAIL_TRANSPORT=memory FAMTASTIC_TRANSACTIONAL_EMAIL_CAPTURE="$mail_capture" "${drush[@]}" famtastic:lifecycle-run --limit=1 2>&1)"
env_lifecycle_exit=$?
set -e
assert_equals "$env_lifecycle_exit" "1" "lifecycle-run exit while env lock is active"
[[ "$env_lifecycle_output" == *"Pilot exact-dispatch lock is active"* ]] || { echo "FAIL: env-locked lifecycle-run did not report the exact pilot lock." >&2; exit 1; }
assert_equals "$("${drush[@]}" sql:query "SELECT status FROM famtastic_job WHERE job_key = 'e2e-pilot-lock-general-job'" | tr -d '[:space:]')" "queued" "lifecycle general job state while env lock is active"
assert_equals "$("${drush[@]}" sql:query "SELECT status FROM famtastic_notification_outbox WHERE notification_key = 'e2e-pilot-lock-general-outbox'" | tr -d '[:space:]')" "queued" "lifecycle general outbox state while env lock is active"

# With both switches off, ordinary lifecycle behavior is unchanged: the
# synthetic unsupported job is claimed/retried and the memory-only outbox row
# is sent. This asserts the lock did not become a permanent default.
FAMTASTIC_TRANSACTIONAL_EMAIL_TRANSPORT=memory \
FAMTASTIC_TRANSACTIONAL_EMAIL_CAPTURE="$mail_capture" \
  "${drush[@]}" famtastic:lifecycle-run --limit=1 >/dev/null
assert_equals "$("${drush[@]}" sql:query "SELECT attempts FROM famtastic_job WHERE job_key = 'e2e-pilot-lock-general-job'" | tr -d '[:space:]')" "1" "ordinary lifecycle claimed the general job after lock is off"
assert_equals "$("${drush[@]}" sql:query "SELECT status FROM famtastic_job WHERE job_key = 'e2e-pilot-lock-general-job'" | tr -d '[:space:]')" "queued" "ordinary lifecycle retry state after lock is off"
assert_equals "$("${drush[@]}" sql:query "SELECT status FROM famtastic_notification_outbox WHERE notification_key = 'e2e-pilot-lock-general-outbox'" | tr -d '[:space:]')" "sent" "ordinary lifecycle general outbox state after lock is off"
test -s "$mail_capture" || { echo "FAIL: ordinary lifecycle did not use the disposable memory transport." >&2; exit 1; }

# The generic public callback rejects a validly signed declared verified-cold
# envelope before it can touch a campaign. The real lane is private-file only.
caller_dir="$PWD"
cd "$sandbox/backend"
SITE_STUDIO_CALLBACK_SECRET="pilot-lock-callback-secret" \
  "${drush[@]}" runserver "127.0.0.1:$port" >"$server_log" 2>&1 &
server_pid=$!
cd "$caller_dir"
for _ in $(seq 1 40); do
  curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:$port/robots.txt" | grep -qv '^000$' && break
  sleep 0.25
done
test "$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:$port/robots.txt")" != "000"
callback_payload='{"source_lane":"verified_cold","event_id":"forbidden-pilot-callback","campaign_id":"unbound","job_id":"unbound","variants":[]}'
callback_signature="sha256=$(printf '%s' "$callback_payload" | openssl dgst -sha256 -hmac pilot-lock-callback-secret | sed 's/^.*= //')"
callback_response="$(curl -sS -w $'\n%{http_code}' -X POST -H 'Content-Type: application/json' -H "X-FAMtastic-Signature: $callback_signature" -d "$callback_payload" "http://127.0.0.1:$port/api/pipeline/site-studio/callback")"
assert_equals "${callback_response##*$'\n'}" "403" "generic verified-cold callback status"
assert_equals "$(printf '%s' "${callback_response%$'\n'*}" | jq -r '.error')" "verified_cold_private_import_required" "generic verified-cold callback error"

# A malicious/buggy remote builder could omit source_lane. Create one real
# local verified-cold ingress record, then prove the controller derives its
# lane from the bound campaign and still rejects the generic HTTP callback.
FAMTASTIC_E2E_STATE="$verified_cold_state" \
FAMTASTIC_E2E_RUN="pilot-lock-$run_id" \
  "${drush[@]}" php:script "$sandbox/backend/web/modules/custom/famtastic_pipeline/tests/fixtures/e2e-verified-cold-handoff.php"
verified_cold_campaign="$(jq -r '.lead.campaign_id' "$verified_cold_state")"
test -n "$verified_cold_campaign" && test "$verified_cold_campaign" != "null"
inferred_callback_payload="$(jq -nc --arg campaign "$verified_cold_campaign" '{event_id:"forbidden-inferred-pilot-callback",campaign_id:$campaign,job_id:"unbound",variants:[]}')"
inferred_callback_signature="sha256=$(printf '%s' "$inferred_callback_payload" | openssl dgst -sha256 -hmac pilot-lock-callback-secret | sed 's/^.*= //')"
inferred_callback_response="$(curl -sS -w $'\n%{http_code}' -X POST -H 'Content-Type: application/json' -H "X-FAMtastic-Signature: $inferred_callback_signature" -d "$inferred_callback_payload" "http://127.0.0.1:$port/api/pipeline/site-studio/callback")"
assert_equals "${inferred_callback_response##*$'\n'}" "403" "generic callback inferred verified-cold status"
assert_equals "$(printf '%s' "${inferred_callback_response%$'\n'*}" | jq -r '.error')" "verified_cold_private_import_required" "generic callback inferred verified-cold error"

# The old scheduled selector remains useful as a dry-run list only. Any
# executable token must fail before it reaches the service or outbox.
set +e
scheduled_output="$("${drush[@]}" famtastic:cold-proof-scheduled-release --limit=1 --execute=scheduled-owner-approved-cold-preview 2>&1)"
scheduled_exit=$?
set -e
assert_equals "$scheduled_exit" "1" "scheduled cold release execute exit"
[[ "$scheduled_output" == *"Scheduled verified-cold release is disabled"* ]] || { echo "FAIL: scheduled cold release did not fail closed." >&2; exit 1; }
"${drush[@]}" php:eval '
  try {
    \Drupal::service("famtastic_pipeline.cold_proof_scheduled_release")->releaseDue(1, FALSE);
    throw new RuntimeException("Dynamic scheduled release was not blocked.");
  }
  catch (LogicException) {
    print "scheduled_release_fail_closed\n";
  }
' | grep -qx 'scheduled_release_fail_closed'

echo "PASS: durable Drupal config blocks fresh hook_cron and lifecycle-run processes from general automation/outbox work; env=1 is additive and normal lifecycle resumes only after both locks are off."
echo "PASS: generic Site Studio callback rejects verified_cold and dynamic scheduled cold release is fail-closed; no external mail, provider, customer, deployment, or production state was used."
