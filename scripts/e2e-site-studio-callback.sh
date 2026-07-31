#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DRUSH="$REPO_ROOT/backend/vendor/bin/drush"
run_id="$(date +%s)-$$"
campaign_key="e2e-studio-$run_id"
email="studio-$run_id@example.test"
csv_path="$(mktemp "${TMPDIR:-/tmp}/famtastic-studio-lead.XXXXXX.csv")"
import_result="$(mktemp "${TMPDIR:-/tmp}/famtastic-studio-import.XXXXXX.json")"
campaign_result="$(mktemp "${TMPDIR:-/tmp}/famtastic-studio-campaign.XXXXXX.json")"
mock_log="$(mktemp "${TMPDIR:-/tmp}/famtastic-studio-mock.XXXXXX.log")"
drupal_log="$(mktemp "${TMPDIR:-/tmp}/famtastic-studio-drupal.XXXXXX.log")"
mock_pid=""
drupal_pid=""
cleanup() {
  for pid in "$mock_pid" "$drupal_pid"; do
    if test -n "$pid"; then
      kill "$pid" 2>/dev/null || true
      wait "$pid" 2>/dev/null || true
    fi
  done
  rm -f "$csv_path" "$import_result" "$campaign_result" "$mock_log" "$drupal_log"
}
trap cleanup EXIT

mock_port=$((9600 + ($$ % 150)))
drupal_port=$((9800 + ($$ % 150)))
SITE_STUDIO_MOCK_SECRET=dispatch-e2e-secret \
  php -S "127.0.0.1:$mock_port" "$REPO_ROOT/backend/web/modules/custom/famtastic_pipeline/tests/fixtures/site-studio-router.php" > "$mock_log" 2>&1 &
mock_pid=$!
for _ in $(seq 1 40); do
  curl -s "http://127.0.0.1:$mock_port/" >/dev/null 2>&1 && break
  sleep 0.2
done

{
  echo 'source_record_id,business_name,email,website_url'
  echo "studio-$run_id,Studio Callback Fixture $run_id,$email,"
} > "$csv_path"
"$DRUSH" famtastic:leads-import "$csv_path" --source=licensed-e2e --campaign="$campaign_key" > "$import_result"
prospect_id="$(jq -r '.rows[0].prospect_id' "$import_result")"

SITE_STUDIO_URL="http://127.0.0.1:$mock_port/jobs" \
SITE_STUDIO_DISPATCH_SECRET=dispatch-e2e-secret \
FAMTASTIC_PUBLIC_BASE_URL="http://127.0.0.1:$drupal_port" \
  "$DRUSH" famtastic:jobs-run --type=proof.generate --limit=100 >/dev/null

"$DRUSH" eval "
  \$storage = \\Drupal::entityTypeManager()->getStorage('proof_campaign');
  \$ids = \$storage->getQuery()->accessCheck(FALSE)->condition('prospect_id', $prospect_id)->execute();
  \$campaign = \$storage->load(reset(\$ids));
  print json_encode([
    'id' => (int) \$campaign->id(),
    'campaign_id' => \$campaign->get('campaign_id')->value,
    'job_id' => \$campaign->get('studio_job_id')->value,
    'generation_status' => \$campaign->get('generation_status')->value,
  ]);
" > "$campaign_result"
test "$(jq -r '.generation_status' "$campaign_result")" = "waiting_callback"
campaign_id="$(jq -r '.campaign_id' "$campaign_result")"
job_id="$(jq -r '.job_id' "$campaign_result")"

"$DRUSH" cr >/dev/null
caller_dir="$PWD"
cd "$REPO_ROOT/backend"
SITE_STUDIO_CALLBACK_SECRET=callback-e2e-secret \
  "$DRUSH" runserver "127.0.0.1:$drupal_port" > "$drupal_log" 2>&1 &
drupal_pid=$!
cd "$caller_dir"
for _ in $(seq 1 40); do
  curl -sf "http://127.0.0.1:$drupal_port/robots.txt" >/dev/null && break
  sleep 0.25
done

payload="$(jq -nc \
  --arg event "callback-$run_id" \
  --arg campaign "$campaign_id" \
  --arg job "$job_id" \
  '{
    event_id:$event,
    campaign_id:$campaign,
    job_id:$job,
    variants:[
      {direction_id:"a",html:"<!doctype html><html><body><h1>Bold</h1></body></html>",design_dna:{palette:"bold"}},
      {direction_id:"b",html:"<!doctype html><html><body><h1>Professional</h1></body></html>",design_dna:{palette:"trust"}},
      {direction_id:"c",html:"<!doctype html><html><body><h1>Local</h1></body></html>",design_dna:{palette:"local"}}
    ]
  }')"
endpoint="http://127.0.0.1:$drupal_port/api/pipeline/site-studio/callback"
test "$(curl -s -o /dev/null -w '%{http_code}' -X POST -H 'Content-Type: application/json' -H 'X-FAMtastic-Signature: sha256=bad' -d "$payload" "$endpoint")" = "400"
partial="$(printf '%s' "$payload" | jq -c '.event_id = "partial-callback" | .variants = .variants[0:2]')"
partial_signature="sha256=$(printf '%s' "$partial" | openssl dgst -sha256 -hmac callback-e2e-secret | sed 's/^.*= //')"
test "$(curl -s -o /dev/null -w '%{http_code}' -X POST -H 'Content-Type: application/json' -H "X-FAMtastic-Signature: $partial_signature" -d "$partial" "$endpoint")" = "422"
signature="sha256=$(printf '%s' "$payload" | openssl dgst -sha256 -hmac callback-e2e-secret | sed 's/^.*= //')"
result="$(curl -s -X POST -H 'Content-Type: application/json' -H "X-FAMtastic-Signature: $signature" -d "$payload" "$endpoint")"
test "$(printf '%s' "$result" | jq -r '.variant_count')" = "3"
duplicate="$(curl -s -X POST -H 'Content-Type: application/json' -H "X-FAMtastic-Signature: $signature" -d "$payload" "$endpoint")"
test "$(printf '%s' "$duplicate" | jq -r '.newly_processed')" = "false"

"$DRUSH" eval "
  \$campaign = \\Drupal::entityTypeManager()->getStorage('proof_campaign')->load($(jq -r '.id' "$campaign_result"));
  assert(\$campaign->get('generation_status')->value === 'ready');
  \$variants = \\Drupal::entityTypeManager()->getStorage('proof_variant')->getQuery()->accessCheck(FALSE)->condition('campaign_id', \$campaign->id())->execute();
  assert(count(\$variants) === 3);
  \$outreach = \\Drupal::database()->select('famtastic_job', 'j')->condition('job_type', 'outreach.prepare')->condition('prospect_id', $prospect_id)->countQuery()->execute()->fetchField();
  assert((int) \$outreach === 1);
"

echo "PASS: signed Site Studio dispatch, async exactly-three callback, isolation, and callback idempotency verified."
