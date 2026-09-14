#!/usr/bin/env bash
# Disposable full Drupal proof. Every account is synthetic; all mail is memory.
set -euo pipefail
repo_root="$(cd "$(dirname "$0")/.." && pwd)"
run_id="inbox-$(date -u +%Y%m%dT%H%M%SZ)-$$"
evidence="$repo_root/.artifacts/client-messaging/$run_id"
sandbox="$(mktemp -d "${TMPDIR:-/tmp}/famtastic-inbox.XXXXXX")"
server_pid=""
port=$((27800 + ($$ % 150)))
cleanup() {
  local result=$?
  trap - EXIT
  if [[ -n "$server_pid" ]]; then kill "$server_pid" 2>/dev/null || true; wait "$server_pid" 2>/dev/null || true; fi
  cp "$sandbox/drupal.log" "$evidence/drupal.log" 2>/dev/null || true
  case "$sandbox" in "${TMPDIR:-/tmp}"/famtastic-inbox.*) chmod -R u+rwX "$sandbox" 2>/dev/null || true; rm -rf "$sandbox" ;; esac
  exit "$result"
}
trap cleanup EXIT
mkdir -p "$sandbox/backend" "$evidence"
# Inbox/list fixtures do not use the 200 MB campaign artwork library.
rsync -a --exclude vendor --exclude private --exclude 'web/sites/default' --exclude 'web/modules/custom/famtastic_pipeline/assets/campaign' "$repo_root/backend/" "$sandbox/backend/"
rsync -aL "$repo_root/backend/vendor/" "$sandbox/backend/vendor/"
mkdir -p "$sandbox/backend/web/sites/default/files" "$sandbox/backend/private"
cp "$repo_root/backend/web/sites/default/default.settings.php" "$sandbox/backend/web/sites/default/default.settings.php"
cp "$repo_root/backend/web/sites/default/default.settings.php" "$sandbox/backend/web/sites/default/settings.php"
export FAMTASTIC_TRANSACTIONAL_EMAIL_TRANSPORT=memory FAMTASTIC_EMAIL_TRANSPORT=memory
export FAMTASTIC_TRANSACTIONAL_EMAIL_CAPTURE="$sandbox/mail.jsonl"
export FAMTASTIC_ALLOW_REAL_OUTREACH=false FAMTASTIC_DEPLOY_TRANSPORT=disabled FAMTASTIC_HOSTING_BILLING_PROVIDER=disabled
unset DB_HOST DB_NAME DB_USER DB_PASSWORD DB_PORT PLATFORM_RELATIONSHIPS STRIPE_SECRET_KEY
drush=(php -d memory_limit=512M "$sandbox/backend/vendor/drush/drush/drush.php" "--root=$sandbox/backend/web")
"${drush[@]}" site:install standard --db-url='sqlite://sites/default/files/.ht.sqlite' --account-name=admin --account-pass=fixture-only --account-mail=admin@example.test --site-name='Inbox Fixture' --site-mail=no-reply@example.test -y >"$evidence/install.log" 2>&1
"${drush[@]}" en -y famtastic_pipeline >>"$evidence/install.log" 2>&1
"${drush[@]}" config:set system.mail interface.default test_mail_collector -y >>"$evidence/install.log" 2>&1
FAMTASTIC_INBOX_STATE="$sandbox/state.json" "${drush[@]}" php:script "$sandbox/backend/web/modules/custom/famtastic_pipeline/tests/fixtures/e2e-client-messaging.php" >>"$evidence/install.log" 2>&1
# Rehearse a pre-8064 entity schema, preserving the existing prospect record.
"${drush[@]}" php:eval '$manager=\Drupal::entityDefinitionUpdateManager(); foreach (["staff_list_state","staff_list_changed_at","staff_list_changed_by"] as $name) $manager->uninstallFieldStorageDefinition($manager->getFieldStorageDefinition($name,"famtastic_prospect"));' >>"$evidence/install.log" 2>&1
# Prove the existing-site upgrade as well as the fresh-install schema.
"${drush[@]}" php:eval '$s=\Drupal::database()->schema(); $s->dropTable("famtastic_portal_read"); $s->dropUniqueKey("famtastic_portal_thread","source_key"); $s->dropIndex("famtastic_portal_thread","contact_claim"); foreach(["source_key","source_intake_id","prospect_id","contact_name","contact_email"] as $f) $s->dropField("famtastic_portal_thread",$f); $s->dropUniqueKey("famtastic_portal_message","client_key"); foreach(["client_key","notification_key"] as $f) $s->dropField("famtastic_portal_message",$f); \Drupal::keyValue("system.schema")->set("famtastic_pipeline",8062);' >>"$evidence/install.log" 2>&1
"${drush[@]}" updatedb -y >"$evidence/update.log" 2>&1
"${drush[@]}" cr >>"$evidence/update.log" 2>&1
"${drush[@]}" php:eval 'print json_encode(["schema"=>\Drupal::keyValue("system.schema")->get("famtastic_pipeline"),"list_state"=>\Drupal::database()->select("famtastic_prospect","p")->fields("p",["staff_list_state"])->execute()->fetchField(),"threads"=>(int)\Drupal::database()->select("famtastic_portal_thread","t")->countQuery()->execute()->fetchField(),"outbox"=>(int)\Drupal::database()->select("famtastic_notification_outbox","n")->countQuery()->execute()->fetchField()]);' >"$evidence/migration.json"
(
  cd "$sandbox/backend"
  exec "${drush[@]}" runserver "127.0.0.1:$port" >"$sandbox/drupal.log" 2>&1
) &
server_pid=$!
for _ in $(seq 1 100); do curl -sf "http://127.0.0.1:$port/robots.txt" >/dev/null 2>&1 && break; sleep 0.2; done
python3 "$repo_root/scripts/test-client-messaging-http.py" "http://127.0.0.1:$port" "$sandbox/state.json" "$evidence"
"${drush[@]}" php:eval 'print json_encode(["messages"=>(int)\Drupal::database()->select("famtastic_portal_message","m")->countQuery()->execute()->fetchField(),"outbox"=>\Drupal::database()->select("famtastic_notification_outbox","n")->fields("n",["notification_key","status","category","template_id"])->execute()->fetchAll(\PDO::FETCH_ASSOC)]);' >"$evidence/outbox.json"
FAMTASTIC_INBOX_STATE="$sandbox/state.json" "${drush[@]}" php:script "$sandbox/backend/web/modules/custom/famtastic_pipeline/tests/fixtures/assert-prospect-lists.php" >"$evidence/prospect-invariants.json"
echo "PASS: full Drupal messaging install, 8063/8064 upgrades and prospect list transitions, HTTP auth/CSRF, inbox/render/reply/read/isolation proof. Evidence: $evidence"
