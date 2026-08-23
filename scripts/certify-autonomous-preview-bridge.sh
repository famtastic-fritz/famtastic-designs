#!/usr/bin/env bash
set -euo pipefail

repo_root="$(cd "$(dirname "$0")/.." && pwd)"
artifact="$repo_root/artifacts/website-delivery-swarm/famu-corner-20260818"
intake="$repo_root/website-delivery-swarm/pilots/famu-corner/scenario.json"
output="${1:-$repo_root/artifacts/autonomous-preview-bridge-certification-20260818}"

if [[ -e "$output" ]]; then
  echo "FAIL: certification output already exists: $output" >&2
  exit 2
fi
mkdir -p "$output/runs"
export FAMTASTIC_SITE_STUDIO_PACKET_SECRET="local-certification-packet-secret"
export FAMTASTIC_SITE_STUDIO_SUCCESS_SECRET="local-certification-success-secret"

for iteration in 1 2 3; do
  run="$output/runs/run-$iteration"
  python3 "$repo_root/website-delivery-swarm/autonomous_pipeline.py" prepare \
    --intake "$intake" --artifact "$artifact" --output "$run" \
    --project-id project:famu-corner-certification \
    --select direction-e,direction-f \
    --build-class premium_brain_free_workers --golden-replay
  python3 "$repo_root/website-delivery-swarm/autonomous_pipeline.py" simulate-success \
    --packet "$run/site-studio-build-packet.json" --output "$run/site-studio-success.json"
  python3 "$repo_root/website-delivery-swarm/autonomous_pipeline.py" consume \
    --packet "$run/site-studio-build-packet.json" --success "$run/site-studio-success.json" \
    --output "$run/portal-update-event.json"
  jq -e '.stage_ledger | length == 9 and all(.status == "passed")' "$run/site-studio-build-packet.json" >/dev/null
  jq -e '(.selected_direction_ids == ["direction-e","direction-f"]) and ((.artifacts | length) > 0)' "$run/site-studio-build-packet.json" >/dev/null
  jq -e '.status == "site_studio_build_succeeded" and .signature_status == "verified"' "$run/portal-update-event.json" >/dev/null
  unzip -tq "$run/site-studio-build-packet.zip" >/dev/null
done

if python3 "$repo_root/website-delivery-swarm/autonomous_pipeline.py" prepare \
  --intake "$intake" --artifact "$artifact" --output "$output/unavailable-provider-run" \
  --project-id project:famu-corner-provider-gate \
  --select direction-e --build-class premium_brain_free_workers; then
  echo "FAIL: unavailable image provider did not gate a non-replay run" >&2
  exit 2
fi
jq -e '.resolved.image_generation.classification == "gated"' "$output/unavailable-provider-run/capability-preflight.json" >/dev/null

cp "$output/runs/run-1/site-studio-success.json" "$output/tampered-success.json"
jq '.packet_id = "packet-tampered"' "$output/tampered-success.json" > "$output/tampered-success.tmp"
mv "$output/tampered-success.tmp" "$output/tampered-success.json"
if python3 "$repo_root/website-delivery-swarm/autonomous_pipeline.py" consume \
  --packet "$output/runs/run-1/site-studio-build-packet.json" \
  --success "$output/tampered-success.json" --output "$output/should-not-exist.json"; then
  echo "FAIL: mismatched success packet was accepted" >&2
  exit 2
fi

python3 "$repo_root/website-delivery-swarm/template_library.py" \
  --artifacts "$repo_root/artifacts/website-delivery-swarm" \
  --output "$output/template-library.json"
python3 "$repo_root/scripts/check-capability-drift.py" --snapshot "$output/capability-drift.json" >/dev/null

jq -n \
  --arg generated_at "$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
  --arg output "$output" \
  --arg packet_sha "$(shasum -a 256 "$output/runs/run-1/site-studio-build-packet.zip" | awk '{print $1}')" \
  --argjson templates "$(jq '.template_count' "$output/template-library.json")" \
  '{schema:"famtastic.autonomous-preview-bridge-certification.v1",generated_at:$generated_at,classification:"locally_proven_contract_autonomy",runs:3,all_stage_journals_passed:true,signed_success_verified:true,tampered_packet_rejected:true,unavailable_provider_gated:true,two_direction_selection_proven:true,portal_event_emitted:true,site_studio_repository_modified:false,creative_generation_mode:"golden_replay",site_studio_execution_mode:"local_contract_fixture",packet_archive_sha256:$packet_sha,retained_template_count:$templates,output:$output,external_gates:["Site Studio consumer acceptance of build packet","real Site Studio success packet callback","live provider authentication and cost telemetry","production Drupal deployment"]}' \
  > "$output/evidence.json"

echo "PASS: three clean autonomous packet bridge runs"
echo "PASS: signed success ingestion and tamper rejection"
echo "PASS: unavailable provider fail-closed contingency"
echo "PASS: two-direction selection and template retention"
echo "Evidence: $output/evidence.json"
