#!/usr/bin/env bash
set -euo pipefail

# Fresh local-only acceptance for the organization-owned payment handoff. It
# uses synthetic example.test URLs, makes no provider call, does not create a
# merchant account, and never attempts or verifies a payment.

repo_root="$(cd "$(dirname "$0")/.." && pwd)"
test_name="famtastic-payment-handoff"
sandbox="$(mktemp -d "${TMPDIR:-/tmp}/${test_name}.XXXXXX")"
run_id="$(date +%s)-$$"
port=$((26000 + ($$ % 500)))
server_pid=""
vendor_source="${FAMTASTIC_BACKEND_VENDOR:-$repo_root/backend/vendor}"

cleanup() {
  local original_exit=$?
  trap - EXIT
  if [[ -n "$server_pid" ]]; then
    kill "$server_pid" 2>/dev/null || true
    wait "$server_pid" 2>/dev/null || true
  fi
  case "$sandbox" in
    "${TMPDIR:-/tmp}/${test_name}."*) chmod -R u+rwX "$sandbox" 2>/dev/null || true; rm -rf "$sandbox" ;;
    *) echo "Refusing to remove unexpected sandbox: $sandbox" >&2; exit 1 ;;
  esac
  exit "$original_exit"
}
trap cleanup EXIT

for command_name in curl jq rsync; do
  command -v "$command_name" >/dev/null || { echo "Missing required command: $command_name" >&2; exit 1; }
done
test -x "$vendor_source/bin/drush" || { echo "Run composer install in backend or set FAMTASTIC_BACKEND_VENDOR to a matching installed vendor directory." >&2; exit 1; }
runtime_vendor="$(cd -P "$vendor_source" && pwd)"
runtime_backend="$(cd "$runtime_vendor/.." && pwd)"
test -d "$runtime_backend/web/core" || { echo "The supplied Drupal runtime is incomplete." >&2; exit 1; }

mkdir -p "$sandbox/backend"
rsync -a --exclude vendor --exclude private --exclude 'web/sites/default/files' "$repo_root/backend/" "$sandbox/backend/"
rsync -aL "$runtime_vendor/" "$sandbox/backend/vendor/"
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
"${drush[@]}" site:install standard --db-url="sqlite://sites/default/files/.ht.sqlite" --account-name=admin --account-pass=admin --account-mail=admin@famtastic.local --site-name="Payment handoff fixture" --site-mail=no-reply@famtastic.local -y >/dev/null
"${drush[@]}" en -y famtastic_pipeline >/dev/null

# Rehearse the existing-site migration path, not only fresh hook_schema().
"${drush[@]}" sqlq 'DROP TABLE famtastic_payment_handoff_event; DROP TABLE famtastic_payment_handoff;'
"${drush[@]}" php:eval '\Drupal::keyValue("system.schema")->set("famtastic_pipeline", 8054);'
"${drush[@]}" updb -y >/dev/null
"${drush[@]}" cr >/dev/null

state="$sandbox/state.json"
FAMTASTIC_E2E_STATE="$state" FAMTASTIC_E2E_RUN="$run_id" \
  "${drush[@]}" php:script "$sandbox/backend/web/modules/custom/famtastic_pipeline/tests/fixtures/e2e-payment-handoff.php"
jq -e '
  .initial_mode == "disabled" and
  .initial_configured == false and
  .member_denied == true and
  .credential_url_denied == true and
  .disabled_public_absent == true
' "$state" >/dev/null

(
  cd "$sandbox/backend"
  exec "${drush[@]}" runserver "127.0.0.1:$port" >"$sandbox/drupal.log" 2>&1
) &
server_pid=$!
for _ in $(seq 1 80); do
  curl -sf "http://127.0.0.1:$port/robots.txt" >/dev/null 2>&1 && break
  sleep 0.25
done

base="http://127.0.0.1:$port"
organization="$(jq -r '.organization_public_id' "$state")"
other_organization="$(jq -r '.other_organization_public_id' "$state")"
site_key="$(jq -r '.site_key' "$state")"
owner_email="$(jq -r '.owner_email' "$state")"
owner_password="$(jq -r '.owner_password' "$state")"
member_email="$(jq -r '.member_email' "$state")"
member_password="$(jq -r '.member_password' "$state")"
owner_jar="$sandbox/owner.cookies"
member_jar="$sandbox/member.cookies"

test "$(curl -sS -o "$sandbox/absent.json" -w '%{http_code}' "$base/api/payment-handoff/$organization/$site_key")" = "404"
jq -e '.ok == false and .error == "payment_handoff_unavailable"' "$sandbox/absent.json" >/dev/null

test "$(curl -sS -o "$sandbox/owner-login.json" -w '%{http_code}' -c "$owner_jar" -H 'Content-Type: application/json' -d "{\"email\":\"$owner_email\",\"password\":\"$owner_password\"}" "$base/api/customer/login")" = "200"
test "$(curl -sS -o "$sandbox/member-login.json" -w '%{http_code}' -c "$member_jar" -H 'Content-Type: application/json' -d "{\"email\":\"$member_email\",\"password\":\"$member_password\"}" "$base/api/customer/login")" = "200"
test "$(curl -sS -o "$sandbox/member-owner.json" -w '%{http_code}' -b "$member_jar" -G --data-urlencode "organization=$organization" "$base/api/customer/payment-handoff")" = "404"
jq -e '.ok == false and .error == "payment_handoff_not_found"' "$sandbox/member-owner.json" >/dev/null

test "$(curl -sS -o "$sandbox/owner-empty.json" -w '%{http_code}' -b "$owner_jar" -G --data-urlencode "organization=$organization" "$base/api/customer/payment-handoff")" = "200"
jq -e '.ok == true and .configured == false and .payment_handoff.mode == "disabled"' "$sandbox/owner-empty.json" >/dev/null
csrf="$(curl -sS -b "$owner_jar" "$base/session/token")"

generic_payload="$(jq -cn --arg organization "$organization" '{organization:$organization,mode:"payment_link",destination_url:"pay.fixture.test/checkout",label:"Pay fixture directly",instructions:"Use the business payment page."}')"
test "$(curl -sS -o "$sandbox/generic.json" -w '%{http_code}' -X PUT -b "$owner_jar" -H 'Content-Type: application/json' -H "X-CSRF-Token: $csrf" -d "$generic_payload" "$base/api/customer/payment-handoff")" = "200"
jq -e '.ok == true and .configured == true and .payment_handoff.mode == "payment_link" and .payment_handoff.destination_url == "https://pay.fixture.test/checkout"' "$sandbox/generic.json" >/dev/null

public_payload="$(curl -sS "$base/api/payment-handoff/$organization/$site_key")"
jq -e '.ok == true and .payment_handoff.mode == "payment_link" and .payment_handoff.destination_url == "https://pay.fixture.test/checkout" and (.payment_handoff | has("payment_status") | not) and (.payment_handoff.disclosure | contains("does not confirm payment"))' <<<"$public_payload" >/dev/null
test "$(curl -sS -o "$sandbox/other-organization.json" -w '%{http_code}' "$base/api/payment-handoff/$other_organization/$site_key")" = "404"
jq -e '.ok == false and .error == "payment_handoff_unavailable"' "$sandbox/other-organization.json" >/dev/null

for event_surface in 'viewed starter' 'opened owner_desk'; do
  read -r event surface <<<"$event_surface"
  payload="$(jq -cn --arg event "$event" --arg surface "$surface" '{event:$event,surface:$surface}')"
  test "$(curl -sS -o "$sandbox/$event.json" -w '%{http_code}' -H 'Content-Type: application/json' -d "$payload" "$base/api/payment-handoff/$organization/$site_key/events")" = "201"
  jq -e --arg event "$event" '.ok == true and .event == $event and .meaning == ("payment_handoff_" + $event + "_not_purchase") and (has("payment_status") | not)' "$sandbox/$event.json" >/dev/null
done

qr_payload="$(jq -cn --arg organization "$organization" '{organization:$organization,mode:"qr",qr_image_url:"https://cdn.fixture.test/owner-qr.png",destination_url:"pay.fixture.test/accessible",label:"Scan the business QR"}')"
test "$(curl -sS -o "$sandbox/qr.json" -w '%{http_code}' -X PUT -b "$owner_jar" -H 'Content-Type: application/json' -H "X-CSRF-Token: $csrf" -d "$qr_payload" "$base/api/customer/payment-handoff")" = "200"
jq -e '.payment_handoff.mode == "qr" and .payment_handoff.qr_image_url == "https://cdn.fixture.test/owner-qr.png" and .payment_handoff.destination_url == "https://pay.fixture.test/accessible"' "$sandbox/qr.json" >/dev/null

cash_payload="$(jq -cn --arg organization "$organization" '{organization:$organization,mode:"cash_app",destination_url:"cash.app/$FixtureOnly",label:"Pay with Cash App"}')"
test "$(curl -sS -o "$sandbox/cash.json" -w '%{http_code}' -X PUT -b "$owner_jar" -H 'Content-Type: application/json' -H "X-CSRF-Token: $csrf" -d "$cash_payload" "$base/api/customer/payment-handoff")" = "200"
jq -e '.payment_handoff.mode == "cash_app" and .payment_handoff.destination_url == "https://cash.app/$FixtureOnly" and .payment_handoff.qr_image_url == ""' "$sandbox/cash.json" >/dev/null

invalid_payload="$(jq -cn --arg organization "$organization" '{organization:$organization,mode:"payment_link",destination_url:"https://user:secret@payments.fixture.test/checkout"}')"
test "$(curl -sS -o "$sandbox/invalid.json" -w '%{http_code}' -X PUT -b "$owner_jar" -H 'Content-Type: application/json' -H "X-CSRF-Token: $csrf" -d "$invalid_payload" "$base/api/customer/payment-handoff")" = "422"
jq -e '.ok == false and .error == "payment_handoff_destination_invalid"' "$sandbox/invalid.json" >/dev/null

disabled_payload="$(jq -cn --arg organization "$organization" '{organization:$organization,mode:"disabled",destination_url:"https://should-be-cleared.fixture.test",qr_image_url:"https://should-be-cleared.fixture.test/qr.png"}')"
test "$(curl -sS -o "$sandbox/disabled.json" -w '%{http_code}' -X PUT -b "$owner_jar" -H 'Content-Type: application/json' -H "X-CSRF-Token: $csrf" -d "$disabled_payload" "$base/api/customer/payment-handoff")" = "200"
jq -e '.payment_handoff.mode == "disabled" and .payment_handoff.destination_url == "" and .payment_handoff.qr_image_url == ""' "$sandbox/disabled.json" >/dev/null
test "$(curl -sS -o "$sandbox/disabled-public.json" -w '%{http_code}' "$base/api/payment-handoff/$organization/$site_key")" = "404"

event_counts="$("${drush[@]}" sqlq 'SELECT event_type || ":" || COUNT(*) FROM famtastic_payment_handoff_event GROUP BY event_type ORDER BY event_type;')"
grep -qx 'opened:1' <<<"$event_counts"
grep -qx 'viewed:1' <<<"$event_counts"

echo "PASS: update 8055 adds organization-scoped owner payment-handoff configuration and append-only viewed/opened events."
echo "PASS: the public model stays absent until an owner enables it; generic HTTPS, existing QR, and Cash App handoffs validate without merchant credentials."
echo "PASS: cross-organization isolation, disabled clearing, no auto-verification, and viewed/opened-not-purchase semantics are exercised through HTTP."
