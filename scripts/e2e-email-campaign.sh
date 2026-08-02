#!/usr/bin/env bash
set -euo pipefail

# This acceptance fixture intentionally exercises the deterministic image-free
# pilot path. Customer outreach remains blocked unless a test opts in.
export FAMTASTIC_ALLOW_STUB_OUTREACH=1

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DRUSH="$REPO_ROOT/backend/vendor/bin/drush"
run_id="$(date +%s)-$$"
campaign="e2e-email-$run_id"
email="email-$run_id@example.test"
csv_path="$(mktemp "${TMPDIR:-/tmp}/famtastic-email-lead.XXXXXX.csv")"
import_result="$(mktemp "${TMPDIR:-/tmp}/famtastic-email-import.XXXXXX.json")"
message_result="$(mktemp "${TMPDIR:-/tmp}/famtastic-email-message.XXXXXX.json")"
server_log="$(mktemp "${TMPDIR:-/tmp}/famtastic-email-server.XXXXXX.log")"
server_pid=""
cleanup() {
  if test -n "$server_pid"; then
    kill "$server_pid" 2>/dev/null || true
    wait "$server_pid" 2>/dev/null || true
  fi
  rm -f "$csv_path" "$import_result" "$message_result" "$server_log"
}
trap cleanup EXIT

"$DRUSH" cr >/dev/null

{
  echo 'source_record_id,business_name,email,website_url'
  echo "email-$run_id,Email Campaign Fixture $run_id,$email,"
} > "$csv_path"

"$DRUSH" famtastic:leads-import "$csv_path" --source=licensed-e2e --campaign="$campaign" > "$import_result"
prospect_id="$(jq -r '.rows[0].prospect_id' "$import_result")"
test "$prospect_id" != "null"
unrelated_jobs_before="$("$DRUSH" eval "
  print (int) \\Drupal::database()->select('famtastic_job', 'j')
    ->condition('status', 'queued')
    ->condition('prospect_id', $prospect_id, '<>')
    ->countQuery()->execute()->fetchField();
")"

"$DRUSH" famtastic:jobs-run --type=proof.generate --prospect="$prospect_id" --limit=100 >/dev/null
"$DRUSH" famtastic:jobs-run --type=outreach.prepare --prospect="$prospect_id" --limit=100 >/dev/null

if "$DRUSH" famtastic:campaign-approve "$campaign" --confirm=wrong >/dev/null 2>&1; then
  echo "Campaign approval unexpectedly accepted a mismatched confirmation." >&2
  exit 1
fi
"$DRUSH" famtastic:campaign-approve "$campaign" --confirm="$campaign" >/dev/null
message_id="$("$DRUSH" eval "
  print (int) \\Drupal::database()->select('famtastic_email_message', 'm')->fields('m', ['id'])->condition('prospect_id', $prospect_id)->execute()->fetchField();
")"
test "$message_id" -gt 0
FAMTASTIC_EMAIL_TRANSPORT=real \
FAMTASTIC_ALLOW_REAL_OUTREACH=true \
FAMTASTIC_OUTREACH_POSTAL_ADDRESS= \
  "$DRUSH" eval "
    try {
      \\Drupal::service('famtastic_pipeline.campaign_messages')->send($message_id);
      throw new \\RuntimeException('Real outreach unexpectedly accepted a missing postal address.');
    }
    catch (\\RuntimeException \$error) {
      assert(\$error->getMessage() === 'Real outreach requires a valid physical postal address.', \$error->getMessage());
    }
  "
FAMTASTIC_EMAIL_TRANSPORT=memory "$DRUSH" famtastic:jobs-run --type=outreach.send --prospect="$prospect_id" --limit=100 >/dev/null
"$DRUSH" famtastic:campaign-snapshot-backfill "$campaign" \
  --confirm="$campaign" \
  --postal-address="1 Acceptance Test Street, Test City, FL 00000" >/dev/null

"$DRUSH" eval "
  \$db = \\Drupal::database();
  \$message = \$db->select('famtastic_email_message', 'm')->fields('m')->condition('prospect_id', $prospect_id)->execute()->fetchAssoc();
  print json_encode(\$message, JSON_UNESCAPED_SLASHES);
" > "$message_result"

test "$(jq -r '.status' "$message_result")" = "delivered"
test "$(jq -r '.provider' "$message_result")" = "memory"
test "$(jq -r '.recipient_address' "$message_result")" = "$email"
test "$(jq -r '.from_address' "$message_result")" = "hello@famtasticdesigns.com"
test "$(jq -r '.proof_campaign_id > 0' "$message_result")" = "true"
test "$(jq -r '.body_snapshot | contains("Advertisement from FAMtastic Designs")' "$message_result")" = "true"
test "$(jq -r '.body_snapshot | contains("/unsubscribe/")' "$message_result")" = "true"
unrelated_jobs_after="$("$DRUSH" eval "
  print (int) \\Drupal::database()->select('famtastic_job', 'j')
    ->condition('status', 'queued')
    ->condition('prospect_id', $prospect_id, '<>')
    ->countQuery()->execute()->fetchField();
")"
test "$unrelated_jobs_after" = "$unrelated_jobs_before"
tracking_key="$(jq -r '.tracking_key' "$message_result")"
unsubscribe_key="$(jq -r '.unsubscribe_key' "$message_result")"
provider_message_id="$(jq -r '.provider_message_id' "$message_result")"

port=$((9100 + ($$ % 500)))
caller_dir="$PWD"
cd "$REPO_ROOT/backend"
FAMTASTIC_EMAIL_WEBHOOK_SECRET=e2e-email-secret \
  "$DRUSH" runserver "127.0.0.1:$port" > "$server_log" 2>&1 &
server_pid=$!
cd "$caller_dir"
for _ in $(seq 1 40); do
  curl -sf "http://127.0.0.1:$port/robots.txt" >/dev/null && break
  sleep 0.25
done

test "$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:$port/api/pipeline/email/open/$tracking_key")" = "200"
test "$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:$port/api/pipeline/email/click/$tracking_key")" = "302"

payload="{\"event_id\":\"evt-$run_id\",\"provider_message_id\":\"$provider_message_id\",\"type\":\"bounced\"}"
signature="sha256=$(printf '%s' "$payload" | openssl dgst -sha256 -hmac e2e-email-secret | sed 's/^.*= //')"
test "$(curl -s -o /dev/null -w '%{http_code}' -X POST -H 'Content-Type: application/json' -H "X-FAMtastic-Signature: $signature" -d "$payload" "http://127.0.0.1:$port/api/pipeline/email/provider-event")" = "200"
duplicate="$(curl -s -X POST -H 'Content-Type: application/json' -H "X-FAMtastic-Signature: $signature" -d "$payload" "http://127.0.0.1:$port/api/pipeline/email/provider-event")"
test "$(printf '%s' "$duplicate" | jq -r '.newly_processed')" = "false"
test "$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:$port/api/pipeline/email/unsubscribe/$unsubscribe_key")" = "200"

"$DRUSH" eval "
  \$ledger = \\Drupal::service('famtastic_pipeline.operational_ledger');
  assert(\$ledger->isSuppressed('$email') === TRUE);
  \$db = \\Drupal::database();
  foreach (['email.sent', 'email.delivered', 'email.opened', 'email.clicked', 'email.bounced', 'consent.unsubscribed'] as \$type) {
    \$count = \$db->select('famtastic_event', 'e')->condition('event_type', \$type)->condition('prospect_id', $prospect_id)->countQuery()->execute()->fetchField();
    assert((int) \$count >= 1, \$type);
  }
"

echo "PASS: scoped workers, campaign approval, exact message snapshot, missing-address fail-close, delivery lifecycle, and suppression verified."
