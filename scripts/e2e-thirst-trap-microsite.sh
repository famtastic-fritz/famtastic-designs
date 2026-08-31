#!/usr/bin/env bash
set -euo pipefail

# Fresh local-only acceptance for the production Thirst Trap microsite. It
# uses a disposable SQLite site and synthetic example.test records. It does
# not contact production, send mail, call a creative provider, or publish.

repo_root="$(cd "$(dirname "$0")/.." && pwd)"
test_name="famtastic-thirst-trap-microsite"
sandbox="$(mktemp -d "${TMPDIR:-/tmp}/${test_name}.XXXXXX")"
run_id="$(date +%s)-$$"
port=$((25500 + ($$ % 500)))
server_pid=""

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
test -x "$repo_root/backend/vendor/bin/drush" || { echo "Run composer install in backend first." >&2; exit 1; }
runtime_vendor="$(cd -P "$repo_root/backend/vendor" && pwd)"
runtime_backend="$(cd "$runtime_vendor/.." && pwd)"
test -d "$runtime_backend/web/core" || { echo "The installed Drupal runtime is incomplete." >&2; exit 1; }

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
"${drush[@]}" site:install standard --db-url="sqlite://sites/default/files/.ht.sqlite" --account-name=admin --account-pass=admin --account-mail=admin@famtastic.local --site-name="Thirst Trap fixture" --site-mail=no-reply@famtastic.local -y >/dev/null
"${drush[@]}" en -y famtastic_pipeline >/dev/null

# Rehearse the real existing-site 8046 update path after the already-live
# content/message tables, not only a fresh module install.
"${drush[@]}" sqlq 'DROP TABLE famtastic_microsite_order;'
"${drush[@]}" php:eval '\Drupal::keyValue("system.schema")->set("famtastic_pipeline", 8045);'
"${drush[@]}" updb -y >/dev/null
"${drush[@]}" cr >/dev/null

state="$sandbox/state.json"
FAMTASTIC_E2E_STATE="$state" FAMTASTIC_E2E_RUN="$run_id" \
  "${drush[@]}" php:script "$sandbox/backend/web/modules/custom/famtastic_pipeline/tests/fixtures/e2e-thirst-trap-microsite.php"
jq -e '
  .site_key == "thirst-trap-772" and
  .owner_uid == 1 and
  .public_product_count == 1 and
  .public_event_count == 1 and
  .product_name == "Fixture Pouch" and
  .updated_product_name == "Fixture Pouch" and
  .contact_status == "received" and
  .subscriber_status == "subscribed" and
  .duplicate_subscriber == true and
  .message_count == 2 and
  .order_count == 1 and
  .order_reference_prefix == "TT772-" and
  .order_total_cents == 1000 and
  .cash_app_available == true and
  .cash_app_url == "https://cash.app/$FixtureOnly" and
  .order_status == "confirmed" and
  .payment_status == "confirmed" and
  .cross_owner_denied == true and
  .disabled_preorder_denied == true
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
base="http://127.0.0.1:$port/api/microsite/thirst-trap-772"
public_response="$(curl -sS "$base")"
jq -e '.ok == true and .site.products[0].name == "Fixture Pouch" and (.site.products | length) == 1 and .site.payments.preorders_enabled == true and .site.payments.cash_app_available == true and (.site.payments | has("cash_app_url") | not)' <<<"$public_response" >/dev/null
test "$(curl -sS -o "$sandbox/preorder.json" -w '%{http_code}' -H 'Content-Type: application/json' -d '{"name":"HTTP Fixture","email":"http-fixture@example.test","items":[{"product_id":"fixture-pouch","quantity":1}],"pickup_event_id":"coordinate"}' "$base/preorder")" = "200"
jq -e '.ok == true and .status == "requested" and .order.total_cents == 500 and .order.payment_status == "unverified" and .payment.available == true and .payment.url == "https://cash.app/$FixtureOnly"' "$sandbox/preorder.json" >/dev/null
test "$(curl -sS -o "$sandbox/owner.json" -w '%{http_code}' "$base/owner")" = "401"
jq -e '.ok == false and .error == "authentication_required"' "$sandbox/owner.json" >/dev/null
test "$(curl -sS -o "$sandbox/consent.json" -w '%{http_code}' -H 'Content-Type: application/json' -d '{"email":"no-consent@example.test"}' "$base/subscriber")" = "422"
jq -e '.ok == false and .error == "consent_required"' "$sandbox/consent.json" >/dev/null

echo "PASS: updates 8044 and 8046 create the durable Thirst Trap content, capture, and preorder stores."
echo "PASS: owner content, products, numeric prices, events, contacts, subscribers, preorder requests, and manual payment status persist with owner isolation."
echo "PASS: public API hides inactive products, keeps owner access private, requires mailing consent, and never auto-confirms external payment."
