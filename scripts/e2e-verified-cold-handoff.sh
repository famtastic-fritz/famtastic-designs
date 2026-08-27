#!/usr/bin/env bash
set -euo pipefail

# Fresh local-only proof that a verified-source seed creates canonical IDs and
# a safe runner handoff. It never invokes a creative provider, callback, SMTP,
# public share, owner approval, payment, deployment, or production host.

repo_root="$(cd "$(dirname "$0")/.." && pwd)"
sandbox="$(mktemp -d "${TMPDIR:-/tmp}/famtastic-verified-cold-handoff.XXXXXX")"
run_id="$(date +%s)-$$"

cleanup() {
  local original_exit=$?
  local cleanup_exit=0
  trap - EXIT
  case "$sandbox" in
    "${TMPDIR:-/tmp}/famtastic-verified-cold-handoff."*)
      # Drupal hardens sites/default after installation. Restore only the
      # disposable fixture's owner access so cleanup neither leaves debris nor
      # replaces the test's real pass/fail status.
      if ! chmod -R u+rwX "$sandbox" 2>/dev/null; then
        echo "Could not restore fixture permissions before cleanup: $sandbox" >&2
        cleanup_exit=1
      fi
      if ! rm -rf "$sandbox"; then
        echo "Could not remove fixture sandbox: $sandbox" >&2
        cleanup_exit=1
      fi
      ;;
    *)
      echo "Refusing to remove unexpected sandbox: $sandbox" >&2
      cleanup_exit=1
      ;;
  esac
  if [ "$original_exit" -ne 0 ]; then
    exit "$original_exit"
  fi
  exit "$cleanup_exit"
}
trap cleanup EXIT

for command_name in jq openssl rsync; do
  command -v "$command_name" >/dev/null || { echo "Missing required command: $command_name" >&2; exit 1; }
done
test -x "$repo_root/backend/vendor/bin/drush" || { echo "Run composer install in backend before this local acceptance test." >&2; exit 1; }
runtime_vendor="$(cd -P "$repo_root/backend/vendor" && pwd)"
runtime_backend="$(cd "$runtime_vendor/.." && pwd)"
test -d "$runtime_backend/web/core" || { echo "The installed Drupal runtime is missing web/core." >&2; exit 1; }

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
"${drush[@]}" site:install standard --db-url="sqlite://sites/default/files/.ht.sqlite" --account-name=admin --account-pass=admin --account-mail=admin@famtastic.local --site-name="FAMtastic cold handoff fixture" --site-mail=no-reply@famtastic.local -y >/dev/null
"${drush[@]}" en -y famtastic_pipeline >/dev/null
# The profile was added after the module had long been enabled in production.
# Rehearse exactly that upgrade gap: config/install is not reapplied to an
# existing module, so update 8043 must restore a wholly absent profile before
# verified-cold ingress can create a delivery. This sandbox is disposable.
"${drush[@]}" config:delete -y famtastic_pipeline.proof_cohorts >/dev/null
"${drush[@]}" php:eval '\Drupal::keyValue("system.schema")->set("famtastic_pipeline", 8042);'
"${drush[@]}" updb -y >/dev/null
"${drush[@]}" cr >/dev/null
"${drush[@]}" php:eval '
  $profile = \Drupal::service("famtastic_pipeline.proof_cohort_profiles")->resolveAnonymous();
  if (
    ($profile["id"] ?? "") !== "anonymous_safe_medium_ultra_v1"
    || (int) ($profile["direction_count"] ?? 0) !== 3
    || array_keys((array) ($profile["directions"] ?? [])) !== ["a", "b", "c"]
  ) {
    throw new \RuntimeException("Upgrade 8043 did not restore the canonical anonymous proof cohort profile.");
  }
'
# A second rehearsal proves that the update does not overwrite a real active
# configuration. Keep the allowed fixture status while adding a sentinel that
# must survive the rerun, then remove only the synthetic sentinel.
"${drush[@]}" php:eval '
  $config = \Drupal::configFactory()->getEditable("famtastic_pipeline.proof_cohorts");
  $statuses = (array) $config->get("cold.website_observation_statuses");
  $statuses[] = "fixture_operator_status";
  $config->set("cold.website_observation_statuses", array_values(array_unique($statuses)))->save(TRUE);
  \Drupal::keyValue("system.schema")->set("famtastic_pipeline", 8042);
'
"${drush[@]}" updb -y >/dev/null
"${drush[@]}" php:eval '
  $config = \Drupal::configFactory()->getEditable("famtastic_pipeline.proof_cohorts");
  $statuses = (array) $config->get("cold.website_observation_statuses");
  if (!in_array("fixture_operator_status", $statuses, TRUE)) {
    throw new \RuntimeException("Upgrade 8043 overwrote an active proof cohort configuration.");
  }
  $config->set("cold.website_observation_statuses", array_values(array_filter($statuses, static fn ($status): bool => $status !== "fixture_operator_status")))->save(TRUE);
'

state="$sandbox/state.json"
FAMTASTIC_E2E_STATE="$state" FAMTASTIC_E2E_RUN="$run_id" \
  "${drush[@]}" php:script "$sandbox/backend/web/modules/custom/famtastic_pipeline/tests/fixtures/e2e-verified-cold-handoff.php"

jq -e '
  .lead.status == "preview_requested" and
  (.lead.prospect_id | type == "number") and
  (.lead.preview_delivery_id | type == "number") and
  (.lead.proof_campaign_id | type == "number") and
  (.lead.proof_job_id | type == "number") and
  .duplicate_reimport == "already_ingressed" and
  .draft_owner_gate == "rejected_without_partial_hold" and
  .cold_one_click_unsubscribe == "get_safe_post_suppressed" and
  .cold_legacy_unsubscribe == "rejected_without_mutation" and
  .cold_generic_service == "rejected_without_state_change" and
  .cold_dispatch_gate == "denied_before_claim" and
  .public_brief_pii == "redacted" and
  .bundle.schema == "famtastic.verified-cold-proof-handoff.v1" and
  (.bundle.deliveries | length) == 1 and
  .bundle.deliveries[0].source_lane == "verified_cold" and
  .bundle.deliveries[0].job.job_type == "public_preview.generate" and
  .bundle.deliveries[0].build_dna_run.source_lane == "verified_cold" and
  (.bundle.deliveries[0].job_id | startswith("cold-preview-")) and
  (.bundle.deliveries[0].callback_event_id | startswith("cold-proof-callback-")) and
  (.bundle.deliveries[0].run_started_at | type == "string") and
  .bundle.deliveries[0].build_dna_run.job_id == .bundle.deliveries[0].job_id and
  .bundle.deliveries[0].build_dna_run.callback_event_id == .bundle.deliveries[0].callback_event_id and
  .bundle.deliveries[0].build_dna_run.run_started_at == .bundle.deliveries[0].run_started_at
' "$state" >/dev/null

delivery_id="$(jq -r '.lead.preview_delivery_id' "$state")"
proof_campaign_id="$(jq -r '.lead.proof_campaign_id' "$state")"
campaign_id="$(jq -r '.bundle.deliveries[0].campaign_id' "$state")"
job_id="$(jq -r '.bundle.deliveries[0].job_id' "$state")"
callback_event_id="$(jq -r '.bundle.deliveries[0].callback_event_id' "$state")"
for immutable_id in "$delivery_id" "$proof_campaign_id"; do
  case "$immutable_id" in
    ''|*[!0-9]*) echo "Fixture returned an invalid numeric immutable ID." >&2; exit 1 ;;
  esac
done

# The generic local importer must reject a real runtime-bound verified-cold
# callback before it can create proof variants, register Build DNA, or move a
# delivery to owner review. Construct a syntactically valid a/b/c payload with
# exact ingress-created campaign/job/event identities and signed-media-shaped
# assets; this remains entirely in the disposable SQLite fixture.
generic_asset="$sandbox/verified-cold-generic-guard.png"
generic_asset_base64='iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
printf '%s' "$generic_asset_base64" | openssl base64 -d -A > "$generic_asset"
generic_asset_sha="$(openssl dgst -sha256 "$generic_asset" | awk '{print $NF}')"
generic_payload="$sandbox/verified-cold-generic-local-import.json"
jq -n \
  --arg campaign_id "$campaign_id" \
  --arg job_id "$job_id" \
  --arg event_id "$callback_event_id" \
  --argjson prospect_id "$(jq '.lead.prospect_id' "$state")" \
  --argjson proof_campaign_id "$proof_campaign_id" \
  --argjson public_preview_delivery_id "$delivery_id" \
  --arg run_started_at "$(jq -r '.bundle.deliveries[0].run_started_at' "$state")" \
  --arg runtime_binding_sha256 "$(jq -r '.bundle.deliveries[0].build_dna_run.binding_sha256' "$state")" \
  --arg asset_base64 "$generic_asset_base64" \
  --arg asset_sha256 "$generic_asset_sha" \
  '
    ["a", "b", "c"] as $directions
    | {
        schema: "famtastic.verified-cold-proof-callback.v1",
        source_lane: "verified_cold",
        event_id: $event_id,
        campaign_id: $campaign_id,
        job_id: $job_id,
        prospect_id: $prospect_id,
        proof_campaign_id: $proof_campaign_id,
        public_preview_delivery_id: $public_preview_delivery_id,
        run_started_at: $run_started_at,
        runtime_binding_sha256: $runtime_binding_sha256,
        variants: [
          $directions[] as $direction
          | {
              direction_id: $direction,
              html: ("<!doctype html><html lang=\"en\"><head><meta charset=\"utf-8\"><title>Verified-cold generic guard " + $direction + "</title></head><body><main><h1>Direction " + $direction + "</h1><img src=\"assets/hero-" + $direction + ".png\" alt=\"Fixture visual\"></main></body></html>"),
              design_dna: {
                source: "fixture_verified_cold_generic_payload",
                direction_name: ("Fixture " + $direction)
              },
              assets: [{
                asset_id: ("hero-" + $direction),
                relative_path: ("hero-" + $direction + ".png"),
                media_type: "image/png",
                base64: $asset_base64,
                sha256: $asset_sha256
              }]
            }
        ]
      }
  ' > "$generic_payload"
generic_checksum="$(openssl dgst -sha256 "$generic_payload" | awk '{print $NF}')"
variants_before="$("${drush[@]}" sql:query "SELECT COUNT(*) FROM proof_variant WHERE campaign_id = ${proof_campaign_id}" | tr -d '[:space:]')"
build_runs_before="$("${drush[@]}" sql:query "SELECT COUNT(*) FROM famtastic_build_run WHERE proof_campaign_id = ${proof_campaign_id}" | tr -d '[:space:]')"
delivery_state_before="$("${drush[@]}" sql:query "SELECT state FROM famtastic_preview_delivery WHERE id = ${delivery_id}" | tr -d '[:space:]')"
generic_output="$sandbox/verified-cold-generic-local-import.out"
if "${drush[@]}" famtastic:proof-local-import "$generic_payload" --confirm="$campaign_id" --checksum="$generic_checksum" >"$generic_output" 2>&1; then
  echo "Generic local importer unexpectedly accepted a verified-cold runtime-bound payload." >&2
  exit 1
fi
rg -q 'Verified-cold proof imports require famtastic:verified-cold-proof-import' "$generic_output" || {
  echo "Generic local importer failed for the wrong reason; the verified-cold guard was not observed." >&2
  cat "$generic_output" >&2
  exit 1
}
variants_after="$("${drush[@]}" sql:query "SELECT COUNT(*) FROM proof_variant WHERE campaign_id = ${proof_campaign_id}" | tr -d '[:space:]')"
build_runs_after="$("${drush[@]}" sql:query "SELECT COUNT(*) FROM famtastic_build_run WHERE proof_campaign_id = ${proof_campaign_id}" | tr -d '[:space:]')"
delivery_state_after="$("${drush[@]}" sql:query "SELECT state FROM famtastic_preview_delivery WHERE id = ${delivery_id}" | tr -d '[:space:]')"
test "$variants_before" = "$variants_after"
test "$build_runs_before" = "$build_runs_after"
test "$delivery_state_before" = "$delivery_state_after"
test "$delivery_state_after" = "preview_requested"

# The narrow cold denial must leave the established local proof import lane
# intact. Create one ordinary local fixture campaign in the same disposable
# Drupal database and import a valid core a/b/c payload through the generic
# command. This is not a provider, public, or production operation.
legacy_state="$sandbox/legacy-local-import-state.json"
FAMTASTIC_E2E_LEGACY_STATE="$legacy_state" FAMTASTIC_E2E_RUN="$run_id" \
  "${drush[@]}" php:eval '
    $run = (string) getenv("FAMTASTIC_E2E_RUN");
    $state = (string) getenv("FAMTASTIC_E2E_LEGACY_STATE");
    $prospect = \Drupal::entityTypeManager()->getStorage("famtastic_prospect")->create([
      "business_name" => "Legacy Local Import Fixture " . $run,
      "business_category" => "Fixture studio",
      "public_email" => "legacy-local-" . $run . "@example.test",
      "campaign" => "fixture-legacy-local-" . $run,
      "source" => "local-fixture",
    ]);
    $prospect->save();
    $campaign = \Drupal::service("famtastic_pipeline.proof_campaign_service")->createLocalHandoff($prospect);
    file_put_contents($state, json_encode([
      "prospect_id" => (int) $prospect->id(),
      "proof_campaign_id" => (int) $campaign->id(),
      "campaign_id" => (string) $campaign->get("campaign_id")->value,
      "job_id" => (string) $campaign->get("studio_job_id")->value,
    ], JSON_THROW_ON_ERROR));
  '
legacy_proof_campaign_id="$(jq -r '.proof_campaign_id' "$legacy_state")"
legacy_campaign_id="$(jq -r '.campaign_id' "$legacy_state")"
legacy_job_id="$(jq -r '.job_id' "$legacy_state")"
case "$legacy_proof_campaign_id" in
  ''|*[!0-9]*) echo "Legacy local fixture returned an invalid campaign ID." >&2; exit 1 ;;
esac
legacy_payload="$sandbox/legacy-local-import.json"
jq -n \
  --arg event_id "legacy-local-import-${run_id}" \
  --arg campaign_id "$legacy_campaign_id" \
  --arg job_id "$legacy_job_id" \
  '
    ["a", "b", "c"] as $directions
    | {
        event_id: $event_id,
        campaign_id: $campaign_id,
        job_id: $job_id,
        variants: [
          $directions[] as $direction
          | {
              direction_id: $direction,
              html: ("<!doctype html><html lang=\"en\"><head><meta charset=\"utf-8\"><title>Legacy local import " + $direction + "</title></head><body><main><h1>Legacy direction " + $direction + "</h1></main></body></html>"),
              design_dna: {
                source: "fixture_legacy_local_import",
                direction_name: ("Legacy " + $direction)
              }
            }
        ]
      }
  ' > "$legacy_payload"
legacy_checksum="$(openssl dgst -sha256 "$legacy_payload" | awk '{print $NF}')"
legacy_result="$("${drush[@]}" famtastic:proof-local-import "$legacy_payload" --confirm="$legacy_campaign_id" --checksum="$legacy_checksum")"
test "$(printf '%s' "$legacy_result" | jq -r '.newly_processed')" = "true"
test "$("${drush[@]}" sql:query "SELECT COUNT(*) FROM proof_variant WHERE campaign_id = ${legacy_proof_campaign_id}" | tr -d '[:space:]')" = "3"
test "$("${drush[@]}" sql:query "SELECT generation_status FROM proof_campaign WHERE id = ${legacy_proof_campaign_id}" | tr -d '[:space:]')" = "ready"

private_root="$("${drush[@]}" php:eval 'echo \Drupal::service("file_system")->realpath("private://");')"
test -n "$private_root"
mkdir -p "$private_root/famtastic"
output="$private_root/famtastic/cold-handoff.json"
"${drush[@]}" famtastic:cold-proof-handoff-export --ids="$delivery_id" --output="$output" --confirm="$delivery_id" >/dev/null
test "$(stat -f '%Lp' "$output")" = "600"
jq -e '.source_lane == "verified_cold" and (.deliveries | length == 1)' "$output" >/dev/null

test "$("${drush[@]}" sql:query "SELECT COUNT(*) FROM famtastic_notification_outbox WHERE status = 'held' OR status = 'dispatching'" | tr -d '[:space:]')" = "0"
test "$("${drush[@]}" sql:query "SELECT COUNT(*) FROM famtastic_notification_outbox WHERE status = 'cancelled'" | tr -d '[:space:]')" = "1"
test "$("${drush[@]}" sql:query "SELECT COUNT(*) FROM famtastic_email_message WHERE status = 'held'" | tr -d '[:space:]')" = "0"
test "$("${drush[@]}" sql:query "SELECT COUNT(*) FROM famtastic_email_message WHERE status = 'staged'" | tr -d '[:space:]')" = "0"
test "$("${drush[@]}" sql:query "SELECT COUNT(*) FROM famtastic_job WHERE job_type = 'proof.generate'" | tr -d '[:space:]')" = "0"
test "$("${drush[@]}" sql:query "SELECT COUNT(*) FROM famtastic_job WHERE job_type = 'public_preview.generate'" | tr -d '[:space:]')" = "1"

echo "PASS: verified-cold ingress creates exact prospect/delivery/campaign/job identities and a private runner handoff; a runtime-bound syntactically valid generic local import is rejected with no variants, Build DNA, or delivery-state mutation; an ordinary local core import still completes; malformed cold clicks fail closed; the legacy GET rejects a cold key without mutation while one-click POST suppresses only that cold record; legacy non-cold GET remains compatible; default SMTP cannot claim a held cold delivery; and a draft owner gate leaves no active outbox or commercial message. No provider, SMTP send, public share, payment, or production action occurred."
