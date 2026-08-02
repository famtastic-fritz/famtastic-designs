#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DRUSH="$REPO_ROOT/backend/vendor/bin/drush"
MODE="${MODE:-new}"
[[ "$MODE" == "new" || "$MODE" == "refresh" ]] || { echo "MODE must be new or refresh." >&2; exit 2; }
run_id="$(date +%s)-$$"
campaign_key="e2e-local-proof-$run_id"
csv_path="$(mktemp "${TMPDIR:-/tmp}/famtastic-local-proof-lead.XXXXXX.csv")"
import_result="$(mktemp "${TMPDIR:-/tmp}/famtastic-local-proof-import.XXXXXX.json")"
payload="$(mktemp "${TMPDIR:-/tmp}/famtastic-local-proof-payload.XXXXXX.json")"
bundle="$(mktemp -d "${TMPDIR:-/tmp}/famtastic-local-proof-bundle.XXXXXX")"
cleanup() {
  rm -f "$csv_path" "$import_result" "$payload"
  rm -rf "$bundle"
}
trap cleanup EXIT

{
  echo 'source_record_id,business_name,business_category,business_description,email,website_url,service_area'
  echo "local-$run_id,Local Proof Fixture $run_id,Bakery,Neighborhood bakery,local-$run_id@example.test,,Test City"
} > "$csv_path"
"$DRUSH" famtastic:leads-import "$csv_path" --source=licensed-e2e --campaign="$campaign_key" > "$import_result"
prospect_id="$(jq -r '.rows[0].prospect_id' "$import_result")"
test "$prospect_id" != "null"

variant_ids_before=""
if [[ "$MODE" == "refresh" ]]; then
  FAMTASTIC_ALLOW_NO_IMAGE_PILOT_PROOFS=1 \
    "$DRUSH" famtastic:jobs-run --type=proof.generate --prospect="$prospect_id" --limit=10 >/dev/null
  current_campaign_id="$($DRUSH eval "
    \$storage = \\Drupal::entityTypeManager()->getStorage('proof_campaign');
    \$ids = \$storage->getQuery()->accessCheck(FALSE)->condition('prospect_id', $prospect_id)->sort('id', 'DESC')->range(0, 1)->execute();
    print \$storage->load(reset(\$ids))->get('campaign_id')->value;
  ")"
  variant_ids_before="$($DRUSH eval "
    \$campaign = \\Drupal::service('famtastic_pipeline.proof_campaign_service')->loadByCampaignId('$current_campaign_id');
    \$ids = \\Drupal::entityTypeManager()->getStorage('proof_variant')->getQuery()->accessCheck(FALSE)->condition('campaign_id', \$campaign->id())->sort('id')->execute();
    print implode(',', \$ids);
  ")"
  if "$DRUSH" famtastic:proof-local-refresh-export "$prospect_id" --confirm=wrong >/dev/null 2>&1; then
    echo "Pilot refresh unexpectedly accepted a mismatched campaign confirmation." >&2
    exit 1
  fi
  "$DRUSH" famtastic:proof-local-refresh-export "$prospect_id" --confirm="$current_campaign_id" >/dev/null
else
  "$DRUSH" famtastic:proof-local-export "$prospect_id" >/dev/null
fi
campaign_json="$($DRUSH eval "
  \$storage = \\Drupal::entityTypeManager()->getStorage('proof_campaign');
  \$ids = \$storage->getQuery()->accessCheck(FALSE)->condition('prospect_id', $prospect_id)->sort('id', 'DESC')->range(0, 1)->execute();
  \$campaign = \$storage->load(reset(\$ids));
  print json_encode(['id' => (int) \$campaign->id(), 'campaign_id' => \$campaign->get('campaign_id')->value, 'job_id' => \$campaign->get('studio_job_id')->value, 'generation_status' => \$campaign->get('generation_status')->value]);
")"
campaign_id="$(printf '%s' "$campaign_json" | jq -r '.campaign_id')"
job_id="$(printf '%s' "$campaign_json" | jq -r '.job_id')"
if [[ "$MODE" == "refresh" ]]; then
  test "$(printf '%s' "$campaign_json" | jq -r '.generation_status')" = "ready"
  [[ "$job_id" =~ ^local-refresh-[a-f0-9]{32}$ ]]
else
  test "$(printf '%s' "$campaign_json" | jq -r '.generation_status')" = "waiting_callback"
fi

jq -n \
  --arg campaign_id "$campaign_id" \
  --arg job_id "$job_id" \
  --arg event_id "local-proof-$run_id" \
  --arg source_sha "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa" \
  '{campaign_id:$campaign_id,job_id:$job_id,event_id:$event_id,provider:"site_studio_local",agent_name:"shay",flow_key:"site-studio-local-promotion",task_key:"proof.generate",prompt_snapshot:"Build three distinct, accessible bakery landing-page directions from the supplied brief.",input_snapshot:{fixture:true},source_sha:$source_sha}' > "$bundle/manifest.json"

png_base64='iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
for direction in a b c; do
  mkdir -p "$bundle/$direction"
  printf '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Direction %s</title></head><body><main><h1>Local Proof Direction %s</h1><p>Neighborhood bakery concept.</p></main></body></html>\n' "$direction" "$direction" > "$bundle/$direction/index.html"
  printf '%s' "$png_base64" | openssl base64 -d -A > "$bundle/$direction/thumbnail.png"
  jq -n --arg direction "$direction" '{direction:$direction,palette:"fixture"}' > "$bundle/$direction/design-dna.json"
done

"$REPO_ROOT/scripts/promote-local-proof-godaddy.sh" "$bundle" >/dev/null

telemetry="$(jq -c '{provider,agent_name,flow_key,task_key,prompt_snapshot,input_snapshot,source_sha}' "$bundle/manifest.json")"
variants='[]'
for direction in a b c; do
  html="$(<"$bundle/$direction/index.html")"
  thumbnail="$(base64 < "$bundle/$direction/thumbnail.png" | tr -d '\n')"
  dna="$(jq -c --argjson telemetry "$telemetry" '. + {source:"site_studio_local",telemetry:$telemetry}' "$bundle/$direction/design-dna.json")"
  variants="$(jq -c --arg direction "$direction" --arg html "$html" --arg thumbnail "$thumbnail" --argjson dna "$dna" '. + [{direction_id:$direction,html:$html,thumbnail_base64:$thumbnail,thumbnail_media_type:"image/png",design_dna:$dna}]' <<<"$variants")"
done
jq -n \
  --arg event_id "local-proof-$run_id" \
  --arg campaign_id "$campaign_id" \
  --arg job_id "$job_id" \
  --argjson variants "$variants" \
  '{event_id:$event_id,campaign_id:$campaign_id,job_id:$job_id,variants:$variants}' > "$payload"
checksum="$(openssl dgst -sha256 "$payload" | awk '{print $NF}')"

if "$DRUSH" famtastic:proof-local-import "$payload" --confirm="$campaign_id" --checksum="$(printf '0%.0s' {1..64})" >/dev/null 2>&1; then
  echo "Local proof import unexpectedly accepted a bad checksum." >&2
  exit 1
fi
first_result="$($DRUSH famtastic:proof-local-import "$payload" --confirm="$campaign_id" --checksum="$checksum")"
test "$(printf '%s' "$first_result" | jq -r '.newly_processed')" = "true"
second_result="$($DRUSH famtastic:proof-local-import "$payload" --confirm="$campaign_id" --checksum="$checksum")"
test "$(printf '%s' "$second_result" | jq -r '.newly_processed')" = "false"

"$DRUSH" eval "
  \$campaign = \\Drupal::entityTypeManager()->getStorage('proof_campaign')->load($(printf '%s' "$campaign_json" | jq -r '.id'));
  assert(\$campaign->get('generation_status')->value === 'ready');
  \$variants = \\Drupal::entityTypeManager()->getStorage('proof_variant')->getQuery()->accessCheck(FALSE)->condition('campaign_id', \$campaign->id())->execute();
  assert(count(\$variants) === 3);
  \$build = \\Drupal::database()->select('famtastic_build_run', 'b')->fields('b')->condition('proof_campaign_id', \$campaign->id())->condition('provider', 'site_studio_local')->execute()->fetchAssoc();
  assert(\$build !== false);
  assert(\$build['agent_name'] === 'shay');
  assert(str_contains(\$build['prompt_snapshot'], 'three distinct'));
  assert(\$build['source_sha'] === 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');
  \$request = \\Drupal::database()->select('famtastic_build_run', 'b')->condition('prospect_id', $prospect_id)->condition('task_key', 'request.export')->countQuery()->execute()->fetchField();
  assert((int) \$request === 1);
  if ('$MODE' === 'refresh') {
    \$ids = \\Drupal::entityTypeManager()->getStorage('proof_variant')->getQuery()->accessCheck(FALSE)->condition('campaign_id', \$campaign->id())->sort('id')->execute();
    assert(implode(',', \$ids) === '$variant_ids_before');
    foreach (\\Drupal::entityTypeManager()->getStorage('proof_variant')->loadMultiple(\$ids) as \$variant) {
      \$dna = json_decode((string) \$variant->get('design_dna')->value, true);
      assert((\$dna['source'] ?? '') === 'site_studio_local');
    }
  }
"

echo "PASS: offline $MODE request, bundle validation, checksum gate, exact-three import, replay safety, and Shay build telemetry verified."
