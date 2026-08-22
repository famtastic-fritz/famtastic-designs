#!/usr/bin/env bash
set -euo pipefail

# Fresh-sandbox public-proof lifecycle acceptance exercise.
#
# This is intentionally a LOCAL fixture and safety test. It does not contact a
# creative provider, SMTP server, payment gateway, production Drupal, or a
# customer mailbox. A signed callback carrying fixture evidence must be
# rejected; later workflow checks use clearly-labelled sandbox state setup so
# that claim, refined-request, owner-gate, selection, and revision boundaries
# can be exercised without pretending a provider completed work.

repo_root="$(cd "$(dirname "$0")/.." && pwd)"
sandbox="$(mktemp -d "${TMPDIR:-/tmp}/famtastic-public-preview-lifecycle.XXXXXX")"
run_id="$(date +%s)-$$"
base_port=$((22000 + ($$ % 1000)))
mock_port="$base_port"
drupal_port=$((base_port + 1000))
base_url="http://127.0.0.1:$drupal_port"
mock_url="http://127.0.0.1:$mock_port/jobs"
mock_secret="fixture-dispatch-$run_id"
callback_secret="fixture-callback-$run_id"
email="public-preview-$run_id@example.test"
unavailable_email="public-preview-unavailable-$run_id@example.test"
owner_email="owner-public-preview-$run_id@example.test"
mail_capture="$sandbox/transactional-email.jsonl"
mock_capture="$sandbox/site-studio-dispatch.jsonl"
cookie_jar="$sandbox/customer.cookies"
mock_pid=""
drupal_pid=""

cleanup() {
  for pid in "$drupal_pid" "$mock_pid"; do
    if test -n "$pid"; then
      kill "$pid" 2>/dev/null || true
      wait "$pid" 2>/dev/null || true
    fi
  done
  # Scope cleanup to the explicit fixture ports. Do not touch any developer
  # process on another port.
  for port in "$drupal_port" "$mock_port"; do
    while IFS= read -r listener_pid; do
      test -n "$listener_pid" && kill "$listener_pid" 2>/dev/null || true
    done < <(lsof -ti "tcp:$port" -sTCP:LISTEN 2>/dev/null || true)
  done
  if test "${FAMTASTIC_E2E_KEEP_SANDBOX:-0}" = "1"; then
    echo "Retained fixture sandbox for diagnosis: $sandbox" >&2
    return
  fi
  case "$sandbox" in
    "${TMPDIR:-/tmp}"/famtastic-public-preview-lifecycle.*)
      chmod -R u+rwX "$sandbox" 2>/dev/null || true
      rm -rf "$sandbox"
      ;;
    *)
      echo "Refusing to remove unexpected sandbox: $sandbox" >&2
      ;;
  esac
}
trap cleanup EXIT

fail() {
  echo "FAIL: $*" >&2
  exit 1
}

require_command() {
  command -v "$1" >/dev/null 2>&1 || fail "Required command is unavailable: $1"
}

assert_json() {
  local input="$1"
  shift
  jq -e "$@" <<<"$input" >/dev/null || fail "JSON assertion failed: $*"
}

assert_file_json() {
  local path="$1"
  shift
  jq -e "$@" "$path" >/dev/null || fail "JSON assertion failed for $path: $*"
}

http_code() {
  curl -s -o /dev/null -w '%{http_code}' "$@" || true
}

wait_for_http() {
  local url="$1"
  for _ in $(seq 1 80); do
    if test "$(http_code "$url")" != "000"; then
      return 0
    fi
    sleep 0.25
  done
  fail "Timed out waiting for $url"
}

sqlq() {
  "${drush[@]}" sqlq "$1" | tr -d '[:space:]'
}

post_callback() {
  local payload="$1"
  local output="$2"
  local signature
  signature="sha256=$(openssl dgst -sha256 -hmac "$callback_secret" "$payload" | sed 's/^.*= //')"
  curl -sS -o "$output" -w '%{http_code}' -X POST \
    -H 'Content-Type: application/json' \
    -H "X-FAMtastic-Signature: $signature" \
    --data-binary "@$payload" "$base_url/api/pipeline/site-studio/callback"
}

write_callback_html() {
  local prefix="$1"
  local directions="$2"
  for direction in $directions; do
    local path="$sandbox/evidence/${prefix}-${direction}.html"
    printf '<!doctype html><html><head><meta charset="utf-8"><title>%s %s</title></head><body><main><h1>%s %s</h1></main></body></html>' \
      "$prefix" "$direction" "$prefix" "$direction" >"$path"
  done
}

# Creates a provider-shaped callback body solely so the verifier can reject a
# specific safety condition. `fixture_execution` is deliberately propagated
# to formal Build DNA evidence and must receive HTTP 422. `provider_execution`
# is used only for the stale d/e/f cardinality rejection; it never completes.
make_callback() {
  local preflight="$1"
  local campaign_key="$2"
  local campaign_entity_id="$3"
  local job_id="$4"
  local prefix="$5"
  local directions="$6"
  local execution_class="$7"
  local output="$8"
  local build_id contract_sha source source_type lineage recipe variants='[]'
  build_id="$(jq -r '.manifest.build_id' "$preflight")"
  contract_sha="$(jq -r '.manifest.artifacts[0].sha256' "$preflight")"
  source="$(jq -c '.manifest.run.source_correlation // {}' "$preflight")"
  source_type="$(jq -r '.manifest.run.source_type // .manifest.run.source_correlation.type // ""' "$preflight")"
  lineage="$(jq -c '.manifest.lineage // {}' "$preflight")"
  recipe="$(jq -c '.manifest.recipe' "$preflight")"
  for direction in $directions; do
    local html path sha
    path="$sandbox/evidence/${prefix}-${direction}.html"
    html="$(<"$path")"
    sha="$(shasum -a 256 "$path" | awk '{print $1}')"
    variants="$(jq -cn --argjson existing "$variants" --arg direction "$direction" --arg html "$html" --arg sha "$sha" \
      '$existing + [{direction_id:$direction,html:$html,artifact_sha256:$sha,design_dna:{direction:$direction,version:"candidate"}}]')"
  done
  jq -n \
    --arg event "contract-attempt-$run_id-$prefix" \
    --arg campaign "$campaign_key" \
    --arg job "$job_id" \
    --arg build_id "$build_id" \
    --arg contract_sha "$contract_sha" \
    --arg source_type "$source_type" \
    --arg execution_class "$execution_class" \
    --argjson source "$source" \
    --argjson lineage "$lineage" \
    --argjson recipe "$recipe" \
    --argjson variants "$variants" \
    --argjson prospect_id "$prospect_id" \
    --argjson proof_campaign_id "$campaign_entity_id" '
      {
        event_id:$event,
        campaign_id:$campaign,
        job_id:$job,
        proof_runner:{build_id:$build_id,contract_sha256:$contract_sha},
        build_dna:{
          schema:"famtastic.build-dna.v1",
          build_id:$build_id,
          classification:"production_proof_completion",
          created_at:(now | todateiso8601),
          run:{
            run_id:$build_id,
            status:"completed",
            completion_state:"provider_completed",
            prospect_id:$prospect_id,
            proof_campaign_id:$proof_campaign_id,
            campaign_id:$campaign,
            source_type:$source_type,
            source_correlation:$source,
            execution_class:$execution_class,
            environment:"provider",
            provider_mode:"remote",
            evidence_level:"provider_receipt"
          },
          repository:{name:"provider-return",revision:"0123456789abcdef0123456789abcdef01234567"},
          recipe:$recipe,
          lineage:$lineage,
          stages:[
            {
              stage_id:"provider-execution",
              sequence:1,
              capability:"proof_generation",
              execution:{kind:"provider_execution",provider:{id:"creative-provider",mode:"remote",environment:"provider"},model:{id:"creative-model",status:"resolved"},timing:{status:"recorded"},cost:{status:"recorded"}},
              result:{status:"passed",evidence_class:"provider_receipt"}
            },
            {
              stage_id:"browser-quality",
              sequence:2,
              capability:"browser_qa",
              execution:{kind:"browser_execution",provider:{id:"browser-provider",mode:"remote",environment:"provider"},model:{id:"browser-engine",status:"resolved"},timing:{status:"recorded"},cost:{status:"recorded"}},
              result:{status:"passed",evidence_class:"browser_report"}
            },
            {
              stage_id:"independent-visual-decision",
              sequence:3,
              capability:"visual_review",
              execution:{kind:"visual_review",provider:{id:"visual-provider",mode:"remote",environment:"provider"},model:{id:"visual-reviewer",status:"resolved"},timing:{status:"recorded"},cost:{status:"recorded"}},
              result:{status:"passed",evidence_class:"review_decision"}
            }
          ],
          artifacts:($variants | map({role:"proof_html",direction_id:.direction_id,path:("remote/proofs/" + .direction_id + "/index.html"),sha256:.artifact_sha256,rights_status:"licensed",provenance:"provider_output"})),
          retrieval:{filesystem:{status:"registered",mode:"provider"},database:{status:"registered",mode:"provider"},site_studio:{status:"provider_completion",mode:"provider"}},
          integrity:{artifact_hash_algorithm:"sha256"},
          quality:{status:"passed",technical:{status:"passed",decision:"browser checks passed",reviewer:"quality-reviewer"},visual:{independent:true,status:"passed",decision:"independent review passed",reviewer:"independent-reviewer",review_type:"independent"}}
        },
        variants:$variants
      }
    ' >"$output"
}

require_command curl
require_command jq
require_command node
require_command openssl
require_command rsync
require_command shasum
test -x "$repo_root/backend/vendor/bin/drush" || fail "Run Composer install in backend before this local acceptance test."

# This exact source family must not change while the test is copying it. A
# partially written runner or revision integration produces misleading fixture
# evidence, so the script refuses to proceed rather than racing source edits.
runner_sources=(
  "$repo_root/backend/web/modules/custom/famtastic_pipeline/famtastic_pipeline.install"
  "$repo_root/backend/web/modules/custom/famtastic_pipeline/famtastic_pipeline.services.yml"
  "$repo_root/backend/web/modules/custom/famtastic_pipeline/src/Controller/CustomerPortalController.php"
  "$repo_root/backend/web/modules/custom/famtastic_pipeline/src/Controller/PublicRequestController.php"
  "$repo_root/backend/web/modules/custom/famtastic_pipeline/src/Controller/SiteStudioCallbackController.php"
  "$repo_root/backend/web/modules/custom/famtastic_pipeline/src/Service/AutomationWorker.php"
  "$repo_root/backend/web/modules/custom/famtastic_pipeline/src/Service/BuildTelemetryService.php"
  "$repo_root/backend/web/modules/custom/famtastic_pipeline/src/Service/CustomerPortalService.php"
  "$repo_root/backend/web/modules/custom/famtastic_pipeline/src/Service/ProofCampaignService.php"
  "$repo_root/backend/web/modules/custom/famtastic_pipeline/src/Service/ProofRevisionService.php"
  "$repo_root/backend/web/modules/custom/famtastic_pipeline/src/Service/ProofRunnerCallbackVerifier.php"
  "$repo_root/backend/web/modules/custom/famtastic_pipeline/src/Service/ProofRunnerContractService.php"
  "$repo_root/backend/web/modules/custom/famtastic_pipeline/src/Service/PublicPreviewDeliveryService.php"
  "$repo_root/backend/web/modules/custom/famtastic_pipeline/src/Service/SiteStudioProofClient.php"
)
for source in "${runner_sources[@]}"; do
  test -f "$source" || fail "Required source is unavailable: $source"
done
source_snapshot() {
  shasum -a 256 "$@" | awk '{print $1}' | tr '\n' ' '
}
runner_snapshot_before="$(source_snapshot "${runner_sources[@]}")"

echo "LOCAL FIXTURE ONLY: fresh public-preview lifecycle sandbox $run_id"
echo "No creative provider, SMTP server, payment gateway, production Drupal, or customer delivery will be used."

mkdir -p "$sandbox/backend" "$sandbox/evidence"
rsync -a \
  --exclude vendor \
  --exclude private \
  --exclude 'web/sites/default/files' \
  "$repo_root/backend/" "$sandbox/backend/"
cp -R "$repo_root/backend/vendor" "$sandbox/backend/vendor"
mkdir -p "$sandbox/backend/web/sites/default/files" "$sandbox/backend/private"
chmod -R u+rwX "$sandbox/backend/web/sites/default"
runner_snapshot_after="$(source_snapshot "${runner_sources[@]}")"
runner_copy_sources=()
for source in "${runner_sources[@]}"; do
  runner_copy_sources+=("$sandbox/backend${source#"$repo_root/backend"}")
done
runner_snapshot_copy="$(source_snapshot "${runner_copy_sources[@]}")"
test "$runner_snapshot_before" = "$runner_snapshot_after" && test "$runner_snapshot_before" = "$runner_snapshot_copy" \
  || fail "Runner/revision source changed during sandbox copy; retry after source stabilizes."

drush=("$sandbox/backend/vendor/bin/drush" "--root=$sandbox/backend/web")
(
  cd "$sandbox/backend"
  DB_URL="sqlite://sites/default/files/.ht.sqlite" ./setup.sh >/dev/null
)
actual_root="$("${drush[@]}" status --field=root)"
expected_root="$(cd "$sandbox/backend/web" && pwd -P)"
test "$actual_root" = "$expected_root" || fail "Fresh test bootstrapped the wrong Drupal root: $actual_root"
"${drush[@]}" en -y famtastic_pipeline >/dev/null
"${drush[@]}" updb -y >/dev/null
"${drush[@]}" cr >/dev/null
# The worker/outbox scheduler is intentionally absent from this isolated
# fixture. It would obscure which service boundary caused a state transition.
"${drush[@]}" pm:uninstall -y automated_cron >/dev/null

BASE_URL="$base_url" OWNER_EMAIL="$owner_email" "${drush[@]}" eval '
  $config = \Drupal::service("config.factory")->getEditable("famtastic_pipeline.settings");
  $config->set("frontend_base_url", getenv("BASE_URL"));
  $config->set("notification_to_email", getenv("OWNER_EMAIL"));
  $config->set("proof_runner_transport", "site_studio_dispatch");
  $config->set("studio_url", "");
  $config->save();
'

SITE_STUDIO_MOCK_SECRET="$mock_secret" \
SITE_STUDIO_MOCK_CAPTURE="$mock_capture" \
  php -S "127.0.0.1:$mock_port" \
  "$sandbox/backend/web/modules/custom/famtastic_pipeline/tests/fixtures/public-preview-site-studio-router.php" \
  >"$sandbox/site-studio-mock.log" 2>&1 &
mock_pid=$!
wait_for_http "$mock_url"

(
  cd "$sandbox/backend"
  exec env \
    SITE_STUDIO_CALLBACK_SECRET="$callback_secret" \
    FAMTASTIC_TRANSACTIONAL_EMAIL_TRANSPORT=memory \
    FAMTASTIC_TRANSACTIONAL_EMAIL_CAPTURE="$mail_capture" \
    "${drush[@]}" runserver "127.0.0.1:$drupal_port" >"$sandbox/drupal.log" 2>&1
) &
drupal_pid=$!
wait_for_http "$base_url/robots.txt"

# 1. Public intake creates only durable records: delivery, owner alert, and a
# canonical public a/b/c proof job. It sends nothing to the customer.
quote_payload="$(jq -nc --arg email "$email" '{
  source:"e2e-public-preview-lifecycle",
  branch:"website",
  answers:{
    email:$email,
    businessName:"Fixture Workforce Collective",
    industry:"Workforce development",
    location:"Atlanta, Georgia",
    businessDescription:"A local sandbox business requiring a focused one-page site.",
    referenceSites:"https://example.test/reference"
  }
}')"
quote_status="$(curl -sS -o "$sandbox/quote.json" -w '%{http_code}' -X POST \
  -H 'Content-Type: application/json' --data "$quote_payload" "$base_url/api/public/quote")"
test "$quote_status" = "202" || fail "Public intake returned HTTP $quote_status"
assert_file_json "$sandbox/quote.json" '.ok == true and .notification_queued == true and .notification_sent == false'
prospect_id="$(jq -r '.prospect_id' "$sandbox/quote.json")"
intake_id="$(jq -r '.request_id' "$sandbox/quote.json")"
test "$prospect_id" != "null" && test "$intake_id" != "null" || fail "Public intake did not create prospect and intake ids."
delivery_id="$(sqlq "SELECT id FROM famtastic_preview_delivery WHERE prospect_id = $prospect_id AND intake_id = $intake_id ORDER BY id DESC LIMIT 1;")"
delivery_public_id="$(sqlq "SELECT public_id FROM famtastic_preview_delivery WHERE id = $delivery_id;")"
test -n "$delivery_id" && test -n "$delivery_public_id" || fail "Public intake did not create a signed preview delivery."
test "$(sqlq "SELECT state FROM famtastic_preview_delivery WHERE id = $delivery_id;")" = "preview_requested" || fail "Public delivery was not held for owner review."
test "$(sqlq "SELECT COUNT(*) FROM famtastic_notification_outbox WHERE notification_key = 'preview-delivery:$delivery_id:owner-lead-captured' AND recipient = '$owner_email' AND status = 'queued';")" = "1" || fail "Public lead owner alert was not durably queued."
test "$(sqlq "SELECT COUNT(*) FROM famtastic_notification_outbox WHERE recipient = '$email';")" = "0" || fail "Public intake queued customer mail before owner approval."
initial_job_payload="$("${drush[@]}" sqlq "SELECT payload FROM famtastic_job WHERE job_key = 'website_proof.generate.v1:public-preview:$delivery_id';")"
assert_json "$initial_job_payload" '.routine == "website_proof.generate.v1" and .delivery_class == "public_initial" and .proof_count == 3 and .proof_mix == ["safe", "medium_famtastic", "ultra_famtastic"]'

# 2. The outbound route is a loopback dispatch contract only. It produces the
# preflight Build DNA and a waiting callback, not a proof or customer delivery.
SITE_STUDIO_URL="$mock_url" \
SITE_STUDIO_DISPATCH_SECRET="$mock_secret" \
FAMTASTIC_PUBLIC_BASE_URL="$base_url" \
  "${drush[@]}" famtastic:jobs-run --type=proof.generate --prospect="$prospect_id" --limit=1 >"$sandbox/public-job.json"
assert_file_json "$sandbox/public-job.json" 'length == 1 and .[0].status == "completed" and .[0].result.status == "waiting_callback"'
PUBLIC_PROSPECT_ID="$prospect_id" "${drush[@]}" eval '
  $ids = \Drupal::entityTypeManager()->getStorage("proof_campaign")->getQuery()->accessCheck(FALSE)
    ->condition("prospect_id", (int) getenv("PUBLIC_PROSPECT_ID"))->execute();
  $campaign = $ids ? \Drupal::entityTypeManager()->getStorage("proof_campaign")->load(reset($ids)) : NULL;
  if (!$campaign) throw new RuntimeException("Missing public runner campaign.");
  print json_encode(["id" => (int) $campaign->id(), "campaign_id" => (string) $campaign->get("campaign_id")->value, "job_id" => (string) $campaign->get("studio_job_id")->value, "status" => (string) $campaign->get("generation_status")->value], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
' >"$sandbox/public-runner-campaign.json"
assert_file_json "$sandbox/public-runner-campaign.json" '.status == "waiting_callback" and (.job_id | startswith("fixture-"))'
public_runner_campaign_id="$(jq -r '.id' "$sandbox/public-runner-campaign.json")"
public_runner_campaign_key="$(jq -r '.campaign_id' "$sandbox/public-runner-campaign.json")"
public_runner_job_id="$(jq -r '.job_id' "$sandbox/public-runner-campaign.json")"
PUBLIC_RUNNER_CAMPAIGN_ID="$public_runner_campaign_id" "${drush[@]}" eval '
  $record = \Drupal::service("famtastic_pipeline.build_telemetry")->loadBuildDnaForCampaign((int) getenv("PUBLIC_RUNNER_CAMPAIGN_ID"));
  if (!$record) throw new RuntimeException("Missing public proof runner preflight.");
  print json_encode($record, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
' >"$sandbox/public-preflight.json"
assert_file_json "$sandbox/public-preflight.json" --argjson delivery "$delivery_id" --argjson campaign "$public_runner_campaign_id" '
  .record.status == "preflight"
  and .manifest.classification == "proof_runner_preflight"
  and .manifest.run.status == "dispatched_waiting_callback"
  and .manifest.run.proof_campaign_id == $campaign
  and .manifest.recipe.routine == "website_proof.generate.v1"
  and .manifest.recipe.profile_id == "public_initial.v1"
  and .manifest.run.source_correlation.public_preview_delivery_id == $delivery
  and (.manifest.recipe.direction_contract | keys == ["a", "b", "c"])
'
public_runner_build_id="$(jq -r '.manifest.build_id' "$sandbox/public-preflight.json")"
if DELIVERY_ID="$delivery_id" CAMPAIGN_ID="$public_runner_campaign_id" BUILD_ID="$public_runner_build_id" "${drush[@]}" eval '
  \Drupal::service("famtastic_pipeline.public_preview_deliveries")->stage((int) getenv("DELIVERY_ID"), (int) getenv("CAMPAIGN_ID"), getenv("BUILD_ID"), str_repeat("0", 64));
' >"$sandbox/public-preflight-stage.out" 2>&1; then
  fail "Preflight-only Build DNA staged a public delivery."
fi

# A structurally complete, signed callback with fixture provenance must fail
# before it can create variants, owner-stage the room, or queue customer mail.
write_callback_html "public-fixture" "a b c"
make_callback "$sandbox/public-preflight.json" "$public_runner_campaign_key" "$public_runner_campaign_id" "$public_runner_job_id" "public-fixture" "a b c" "fixture_execution" "$sandbox/public-fixture-callback.json"
public_fixture_status="$(post_callback "$sandbox/public-fixture-callback.json" "$sandbox/public-fixture-result.json")"
test "$public_fixture_status" = "422" || fail "Fixture provenance callback returned HTTP $public_fixture_status; expected 422."
assert_file_json "$sandbox/public-fixture-result.json" '.error == "invalid_callback" and (.message | test("non-production fixture/mock/test evidence"))'
test "$(sqlq "SELECT COUNT(*) FROM proof_variant WHERE campaign_id = $public_runner_campaign_id;")" = "0" || fail "Rejected public fixture callback created variants."
test "$(sqlq "SELECT state FROM famtastic_preview_delivery WHERE id = $delivery_id;")" = "preview_requested" || fail "Rejected public fixture callback advanced delivery state."
test "$(sqlq "SELECT COUNT(*) FROM famtastic_notification_outbox WHERE recipient = '$email';")" = "0" || fail "Rejected public fixture callback queued customer mail."

# 3. Sandbox-only precondition for the rest of the state machine. This does
# NOT use the callback verifier, and the evidence file explicitly says so. It
# exists because the correct verifier just rejected local fixture provenance.
# Keep this deliberately-local build label free of an accidental ten-digit
# sequence: the source contract correctly rejects phone-shaped raw values.
PUBLIC_DELIVERY_ID="$delivery_id" PUBLIC_PROSPECT_ID="$prospect_id" PUBLIC_INTAKE_ID="$intake_id" PUBLIC_BUILD_ID="local-public-state-fixture-alpha" "${drush[@]}" eval '
  $deliveryId = (int) getenv("PUBLIC_DELIVERY_ID");
  $prospectId = (int) getenv("PUBLIC_PROSPECT_ID");
  $intakeId = (int) getenv("PUBLIC_INTAKE_ID");
  $buildId = (string) getenv("PUBLIC_BUILD_ID");
  $entities = \Drupal::entityTypeManager();
  $now = \Drupal::time()->getRequestTime();
  $campaignKey = "sandbox-public-" . substr(hash("sha256", $buildId), 0, 18);
  $campaign = $entities->getStorage("proof_campaign")->create([
    "campaign_id" => $campaignKey,
    "prospect_id" => $prospectId,
    "business_name" => "Sandbox public continuation",
    "status" => "active",
    "generation_status" => "ready",
    "ready_at" => $now,
  ]);
  $campaign->save();
  $contracts = [
    "a" => ["name" => "Safe", "intent" => "polished and low-risk"],
    "b" => ["name" => "Medium FAMtastic", "intent" => "expressive and differentiated"],
    "c" => ["name" => "Ultra FAMtastic", "intent" => "campaign-level visual idea"],
  ];
  $artifacts = [];
  foreach ($contracts as $direction => $contract) {
    $directory = \Drupal::root() . "/proofs/" . $campaignKey . "/" . $direction;
    if (!is_dir($directory) && !mkdir($directory, 0775, TRUE) && !is_dir($directory)) throw new RuntimeException("Could not create public sandbox artifact directory.");
    $html = "<!doctype html><html><body><h1>Sandbox public " . strtoupper($direction) . "</h1></body></html>";
    $path = $directory . "/index.html";
    file_put_contents($path, $html, LOCK_EX);
    $relative = "web/proofs/" . $campaignKey . "/" . $direction . "/index.html";
    $entities->getStorage("proof_variant")->create([
      "campaign_id" => (int) $campaign->id(),
      "direction_id" => $direction,
      "direction_name" => $contract["name"],
      "artifact_path" => $relative,
      "thumbnail_path" => "",
      "preview_url" => "/proofs/" . $campaignKey . "/" . $direction . "/",
      "design_dna" => json_encode(["direction" => $direction, "sandbox_state_setup" => TRUE], JSON_UNESCAPED_SLASHES),
    ])->save();
    $artifacts[] = ["role" => "proof_html", "direction_id" => $direction, "path" => $relative, "sha256" => hash_file("sha256", $path), "rights_status" => "sandbox-only"];
  }
  $dna = [
    "schema" => "famtastic.build-dna.v1",
    "build_id" => $buildId,
    "classification" => "production_proof_completion",
    "sandbox_state_setup_only" => TRUE,
    "created_at" => gmdate(DATE_ATOM, $now),
    "run" => [
      "run_id" => $buildId, "status" => "completed", "completion_state" => "provider_completed",
      "prospect_id" => $prospectId, "proof_campaign_id" => (int) $campaign->id(), "campaign_id" => $campaignKey,
      "source_type" => "public_solution_finder_intake",
      "source_correlation" => ["prospect_id" => $prospectId, "type" => "public_solution_finder_intake", "proof_phase" => "initial", "public_preview_delivery_id" => $deliveryId, "intake_id" => $intakeId],
    ],
    "repository" => ["name" => "sandbox", "revision" => "0123456789abcdef0123456789abcdef01234567"],
    "recipe" => ["routine" => "website_proof.generate.v1", "profile_id" => "public_initial.v1", "proof_count" => 3, "direction_contract" => $contracts],
    "lineage" => [],
    "stages" => [["stage_id" => "sandbox-state-setup", "capability" => "state_precondition", "execution" => ["provider" => ["id" => "sandbox"], "model" => ["id" => "none"]], "result" => ["status" => "not_provider_evidence"]]],
    "artifacts" => $artifacts,
    "retrieval" => ["database" => ["status" => "sandbox_state_only"]],
    "integrity" => ["artifact_hash_algorithm" => "sha256"],
    "quality" => ["status" => "not_proven", "open_gates" => ["real provider", "browser QA", "independent visual review"]],
  ];
  $telemetry = \Drupal::service("famtastic_pipeline.build_telemetry");
  $telemetry->recordBuildDna($dna);
  $record = $telemetry->loadBuildDna($buildId);
  $staged = \Drupal::service("famtastic_pipeline.public_preview_deliveries")->stage($deliveryId, (int) $campaign->id(), $buildId, (string) $record["record"]["artifact_checksum"]);
  print json_encode(["campaign_entity_id" => (int) $campaign->id(), "campaign_id" => $campaignKey, "build_id" => $buildId, "build_hash" => (string) $record["record"]["artifact_checksum"], "state" => (string) $staged["state"]], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
' >"$sandbox/local-public-state.json"
assert_file_json "$sandbox/local-public-state.json" '.state == "email_staged" and (.build_hash | test("^[a-f0-9]{64}$"))'
public_campaign_id="$(jq -r '.campaign_entity_id' "$sandbox/local-public-state.json")"
public_campaign_key="$(jq -r '.campaign_id' "$sandbox/local-public-state.json")"
public_build_id="$(jq -r '.build_id' "$sandbox/local-public-state.json")"
public_build_hash="$(jq -r '.build_hash' "$sandbox/local-public-state.json")"
jq -n --arg run "$run_id" --arg delivery "$delivery_public_id" --arg campaign "$public_campaign_key" --arg build "$public_build_id" '
  {classification:"sandbox_local_state_setup_not_provider_completion",run_id:$run,delivery_public_id:$delivery,campaign_id:$campaign,build_id:$build,callback_verifier_used:false,reason:"Required solely to exercise downstream claim and owner gates after the real verifier rejected fixture evidence."}
' >"$sandbox/evidence/local-public-state-setup.json"
test "$(sqlq "SELECT COUNT(*) FROM proof_variant WHERE campaign_id = $public_campaign_id;")" = "3" || fail "Sandbox public precondition lacks exactly a/b/c."
test "$(sqlq "SELECT proof_campaign_id FROM famtastic_preview_delivery WHERE id = $delivery_id;")" = "$public_campaign_id" || fail "Public delivery did not bind immutable C_public."

# Owner action queues exactly one public invitation, but no outbox worker is
# running and the local memory mailbox remains empty.
DELIVERY_ID="$delivery_id" "${drush[@]}" eval '
  $row = \Drupal::service("famtastic_pipeline.public_preview_deliveries")->approveAndQueue((int) getenv("DELIVERY_ID"), 1);
  print json_encode($row, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
' >"$sandbox/public-owner-approval.json"
assert_file_json "$sandbox/public-owner-approval.json" '.state == "email_queued" and (.email_outbox_id | tonumber > 0)'
test "$(sqlq "SELECT COUNT(*) FROM famtastic_notification_outbox WHERE notification_key = 'preview-delivery:$delivery_id:share:1' AND recipient = '$email' AND status = 'queued';")" = "1" || fail "Owner approval did not queue exactly one public proof invitation."
test ! -s "$mail_capture" || fail "Public owner approval reached the memory mailbox without an outbox worker."

# A second same-email public lead exists to prove that continuation claims one
# signed delivery, not an arbitrary/latest delivery for that email.
second_quote_payload="$(jq -nc --arg email "$email" '{source:"e2e-public-preview-lifecycle-second",branch:"website",answers:{email:$email,businessName:"Same Email Second Lead",industry:"Education",location:"Atlanta, Georgia",businessDescription:"A separate public lead held for exact-claim testing."}}')"
second_status="$(curl -sS -o "$sandbox/second-quote.json" -w '%{http_code}' -X POST -H 'Content-Type: application/json' --data "$second_quote_payload" "$base_url/api/public/quote")"
test "$second_status" = "202" || fail "Second same-email public lead returned HTTP $second_status"
second_intake_id="$(jq -r '.request_id' "$sandbox/second-quote.json")"
second_delivery_id="$(sqlq "SELECT id FROM famtastic_preview_delivery WHERE prospect_id = $prospect_id AND intake_id = $second_intake_id;")"
test -n "$second_delivery_id" && test "$second_delivery_id" != "$delivery_id" || fail "Second same-email public delivery was not isolated."
test "$(sqlq "SELECT state FROM famtastic_preview_delivery WHERE id = $second_delivery_id;")" = "preview_requested" || fail "Second public delivery unexpectedly advanced."

# 4. Same-email sign-up claims only the delivery named by its signed
# continuation. The second same-email delivery remains unbound.
continuation="$(DELIVERY_PUBLIC_ID="$delivery_public_id" "${drush[@]}" eval 'print getenv("DELIVERY_PUBLIC_ID") . "." . hash_hmac("sha256", "public-preview-continuation-v1|" . getenv("DELIVERY_PUBLIC_ID"), \Drupal\Core\Site\Settings::getHashSalt());')"
registration_payload="$(jq -nc --arg email "$email" --arg continuation "$continuation" '{email:$email,password:"Fixture-Public-Preview-Password!",name:"Fixture Customer",business_name:"Fixture Workforce Collective",source:"e2e-public-preview-lifecycle",marketing_opt_out:true,preview_continuation:$continuation}')"
registration_status="$(curl -sS -o "$sandbox/registration.json" -w '%{http_code}' -X POST -H 'Content-Type: application/json' --data "$registration_payload" "$base_url/api/customer/register")"
test "$registration_status" = "201" || fail "Same-email registration returned HTTP $registration_status"
assert_file_json "$sandbox/registration.json" '.ok == true and .verification_required == true'
for _ in $(seq 1 30); do
  test -s "$mail_capture" && break
  sleep 0.1
done
test -s "$mail_capture" || fail "Local memory mailbox did not capture account verification."
assert_file_json "$mail_capture" --arg email "$email" 'select(.to == $email and .subject == "Verify your FAMtastic Designs account")'
test "$(jq -s 'length' "$mail_capture")" = "1" || fail "Registration produced unexpected memory-mail records."
verification_url="$(jq -rsr --arg email "$email" '[.[] | select(.to == $email and .subject == "Verify your FAMtastic Designs account")] | last.body' "$mail_capture" | sed -nE 's#.*(https?://[^ ]+/verify-email\?token=[^ ]+).*#\1#p')"
verification_token="${verification_url##*token=}"
test -n "$verification_token" && test "$verification_token" != "$verification_url" || fail "Could not extract local verification token."
verification_status="$(curl -sS -o "$sandbox/verification.json" -w '%{http_code}' -X POST -H 'Content-Type: application/json' --data "$(jq -nc --arg token "$verification_token" '{token:$token}')" "$base_url/api/customer/verify")"
test "$verification_status" = "200" || fail "Local account verification returned HTTP $verification_status"
customer_id="$(sqlq "SELECT id FROM famtastic_customer WHERE email = '$email';")"
test -n "$customer_id" || fail "Verified customer was not created."
test "$(sqlq "SELECT state FROM famtastic_preview_delivery WHERE id = $delivery_id;")" = "account_verified_and_claimed" || fail "Signed delivery was not claimed after verification."
test "$(sqlq "SELECT customer_id FROM famtastic_preview_delivery WHERE id = $delivery_id;")" = "$customer_id" || fail "Signed delivery claim does not point to the customer."
test "$(sqlq "SELECT customer_id FROM famtastic_preview_delivery WHERE id = $second_delivery_id;")" = "" || fail "Same-email continuation claimed a different delivery."
test "$(sqlq "SELECT prospect_id FROM famtastic_customer WHERE id = $customer_id;")" = "$prospect_id" || fail "Claimed customer lost its original prospect identity."

login_payload="$(jq -nc --arg email "$email" '{email:$email,password:"Fixture-Public-Preview-Password!"}')"
login_status="$(curl -sS -c "$cookie_jar" -o "$sandbox/login.json" -w '%{http_code}' -X POST -H 'Content-Type: application/json' --data "$login_payload" "$base_url/api/customer/login")"
test "$login_status" = "200" || fail "Verified customer login returned HTTP $login_status"
assert_file_json "$sandbox/login.json" '.ok == true and .customer.verified == true and (.organizations | length) == 1'
organization_public_id="$(jq -r '.organizations[0].public_id' "$sandbox/login.json")"
csrf_token="$(curl -sS -b "$cookie_jar" "$base_url/session/token")"
test -n "$csrf_token" || fail "Could not obtain local CSRF token."

# 5. Detailed portal intake is distinct from C_public. It preserves exactly
# which public delivery was claimed, begins with no campaign, freezes its
# detailed facts/assets, and queues one new a-f refined job.
request_payload="$(jq -nc --arg organization "$organization_public_id" --arg delivery "$delivery_public_id" '{
  organization:$organization,
  source_preview_delivery:$delivery,
  action:"submit",
  project_name:"Fixture Workforce Collective website",
  business_name:"Fixture Workforce Collective",
  project_type:"new_website",
  domain_choice:"undecided",
  page_count:1,
  primary_goal:"Turn qualified workforce-development visitors into inquiries.",
  products_services:"Workforce readiness training and employer partnerships.",
  industry:"Workforce development",
  service_locations:"Atlanta, Georgia",
  desired_feeling:"Confident, clear, and future-forward.",
  preferred_colors:"blue, warm gold, and charcoal",
  visual_reference_notes:"Use supplied references only after the customer gives consent.",
  famtastic_level:8,
  allow_bolder_direction:true,
  recommendation_requested:true
}')"
request_status="$(curl -sS -b "$cookie_jar" -o "$sandbox/website-request.json" -w '%{http_code}' -X POST -H 'Content-Type: application/json' -H "X-CSRF-Token: $csrf_token" --data "$request_payload" "$base_url/api/customer/website-requests")"
test "$request_status" = "201" || fail "Detailed portal intake returned HTTP $request_status"
assert_file_json "$sandbox/website-request.json" '.ok == true and .website_request.status == "submitted"'
request_public_id="$(jq -r '.website_request.public_id' "$sandbox/website-request.json")"
request_id="$(sqlq "SELECT id FROM famtastic_project_request WHERE public_id = '$request_public_id';")"
test -n "$request_id" || fail "Detailed website request was not created."
REQUEST_ID="$request_id" "${drush[@]}" eval '
  $row = \Drupal::database()->select("famtastic_project_request", "r")->fields("r")->condition("id", (int) getenv("REQUEST_ID"))->execute()->fetchAssoc();
  print json_encode([
    "prospect_id" => (int) $row["prospect_id"], "source_preview_delivery_id" => (int) $row["source_preview_delivery_id"],
    "proof_campaign_id" => $row["proof_campaign_id"] === NULL ? NULL : (int) $row["proof_campaign_id"], "proof_review_status" => (string) $row["proof_review_status"],
    "proof_phase" => (string) $row["proof_phase"], "proof_profile_id" => (string) $row["proof_profile_id"],
    "parent_public_proof_campaign_id" => (int) $row["parent_public_proof_campaign_id"], "parent_public_campaign_key" => (string) $row["parent_public_campaign_key"],
    "parent_public_build_dna_id" => (string) $row["parent_public_build_dna_id"], "parent_public_build_dna_hash" => (string) $row["parent_public_build_dna_hash"],
    "detailed_intake_snapshot_sha256" => (string) $row["detailed_intake_snapshot_sha256"], "consented_asset_manifest_sha256" => (string) $row["consented_asset_manifest_sha256"]
  ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
' >"$sandbox/request-lineage.json"
assert_file_json "$sandbox/request-lineage.json" --argjson prospect "$prospect_id" --argjson delivery "$delivery_id" --argjson public_campaign "$public_campaign_id" --arg public_key "$public_campaign_key" --arg public_build "$public_build_id" --arg public_hash "$public_build_hash" '
  .prospect_id == $prospect
  and .source_preview_delivery_id == $delivery
  and .proof_campaign_id == null
  and .proof_review_status == "refinement_queued"
  and .proof_phase == "refined_six"
  and .proof_profile_id == "portal_refined_six.v1"
  and .parent_public_proof_campaign_id == $public_campaign
  and .parent_public_campaign_key == $public_key
  and .parent_public_build_dna_id == $public_build
  and .parent_public_build_dna_hash == $public_hash
  and (.detailed_intake_snapshot_sha256 | test("^[a-f0-9]{64}$"))
  and (.consented_asset_manifest_sha256 | test("^[a-f0-9]{64}$"))
'
refined_job_payload="$("${drush[@]}" sqlq "SELECT payload FROM famtastic_job WHERE job_type = 'proof.refined.generate' AND prospect_id = $prospect_id ORDER BY id DESC LIMIT 1;")"
assert_json "$refined_job_payload" --argjson request "$request_id" --arg request_public "$request_public_id" --argjson delivery "$delivery_id" '
  .routine == "website_proof.generate.v1"
  and .delivery_class == "authenticated_refined"
  and .proof_phase == "refined_six"
  and .requested_profile_id == "portal_refined_six.v1"
  and .website_request_id == $request
  and .website_request_public_id == $request_public
  and .source_preview_delivery_id == $delivery
  and .public_preview_delivery_id == $delivery
  and .proof_count == 6
  and .proof_mix == ["normal", "medium_famtastic", "ultra_famtastic_1", "ultra_famtastic_2", "ultra_famtastic_3", "ultra_famtastic_4"]
  and (.direction_contract | keys == ["a", "b", "c", "d", "e", "f"])
'
test "$(sqlq "SELECT proof_campaign_id FROM famtastic_preview_delivery WHERE id = $delivery_id;")" = "$public_campaign_id" || fail "Detailed intake mutated immutable C_public campaign linkage."

# 6. The real job path starts a NEW C_refined and dispatches one a-f contract.
# It must not append d/e/f to the historical public campaign.
SITE_STUDIO_URL="$mock_url" \
SITE_STUDIO_DISPATCH_SECRET="$mock_secret" \
FAMTASTIC_PUBLIC_BASE_URL="$base_url" \
  "${drush[@]}" famtastic:jobs-run --type=proof.refined.generate --prospect="$prospect_id" --limit=1 >"$sandbox/refined-job.json"
assert_file_json "$sandbox/refined-job.json" 'length == 1 and .[0].status == "completed" and .[0].result.status == "waiting_callback" and .[0].result.proof_count == 6 and .[0].result.profile_id == "portal_refined_six.v1"'
refined_campaign_key="$(jq -r '.[0].result.campaign_id' "$sandbox/refined-job.json")"
REFINED_CAMPAIGN_KEY="$refined_campaign_key" "${drush[@]}" eval '
  $ids = \Drupal::entityTypeManager()->getStorage("proof_campaign")->getQuery()->accessCheck(FALSE)->condition("campaign_id", getenv("REFINED_CAMPAIGN_KEY"))->execute();
  $campaign = $ids ? \Drupal::entityTypeManager()->getStorage("proof_campaign")->load(reset($ids)) : NULL;
  if (!$campaign) throw new RuntimeException("Missing refined campaign.");
  print json_encode(["id" => (int) $campaign->id(), "campaign_id" => (string) $campaign->get("campaign_id")->value, "job_id" => (string) $campaign->get("studio_job_id")->value, "status" => (string) $campaign->get("generation_status")->value], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
' >"$sandbox/refined-campaign.json"
assert_file_json "$sandbox/refined-campaign.json" '.status == "waiting_callback" and (.job_id | startswith("fixture-"))'
refined_campaign_id="$(jq -r '.id' "$sandbox/refined-campaign.json")"
refined_job_id="$(jq -r '.job_id' "$sandbox/refined-campaign.json")"
test "$refined_campaign_id" != "$public_campaign_id" || fail "Detailed C_refined reused immutable public C_public."
test "$(sqlq "SELECT COUNT(*) FROM proof_variant WHERE campaign_id = $public_campaign_id;")" = "3" || fail "Detailed job appended variants to C_public."
assert_file_json "$mock_capture" --arg campaign "$refined_campaign_key" '
  select(.routine == "website_proof.generate.v1" and .campaign_id == $campaign and .proof_phase == "refined_six" and .directions == ["a", "b", "c", "d", "e", "f"])
  | .proof_runner.profile_id == "portal_refined_six.v1"
    and (.proof_runner.build_id | length > 0)
    and (.proof_runner.contract_sha256 | test("^[a-f0-9]{64}$"))
'
REFINED_CAMPAIGN_ID="$refined_campaign_id" "${drush[@]}" eval '
  $record = \Drupal::service("famtastic_pipeline.build_telemetry")->loadBuildDnaForCampaign((int) getenv("REFINED_CAMPAIGN_ID"));
  if (!$record) throw new RuntimeException("Missing refined runner preflight.");
  print json_encode($record, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
' >"$sandbox/refined-preflight.json"
assert_file_json "$sandbox/refined-preflight.json" --argjson request "$request_id" --arg request_public "$request_public_id" --argjson delivery "$delivery_id" --argjson parent_campaign "$public_campaign_id" --arg parent_key "$public_campaign_key" --arg parent_build "$public_build_id" --arg parent_hash "$public_build_hash" '
  .record.status == "preflight"
  and .manifest.recipe.routine == "website_proof.generate.v1"
  and .manifest.recipe.profile_id == "portal_refined_six.v1"
  and .manifest.run.source_correlation.website_request_id == $request
  and .manifest.run.source_correlation.website_request_public_id == $request_public
  and .manifest.run.source_correlation.source_preview_delivery_id == $delivery
  and .manifest.run.source_correlation.public_preview_delivery_id == $delivery
  and .manifest.run.source_correlation.parent_public_proof_campaign_id == $parent_campaign
  and .manifest.run.source_correlation.parent_public_campaign_key == $parent_key
  and .manifest.run.source_correlation.parent_public_build_dna_id == $parent_build
  and .manifest.run.source_correlation.parent_public_build_dna_hash == $parent_hash
  and (.manifest.recipe.direction_contract | keys == ["a", "b", "c", "d", "e", "f"])
'
refined_build_id="$(jq -r '.manifest.build_id' "$sandbox/refined-preflight.json")"
request_intake_hash_before_revision="$(REQUEST_ID="$request_id" "${drush[@]}" eval '$value = \Drupal::database()->select("famtastic_project_request", "r")->fields("r", ["intake_data"])->condition("id", (int) getenv("REQUEST_ID"))->execute()->fetchField(); print hash("sha256", (string) $value);')"

# A stale d/e/f-only payload is rejected against the new a-f contract before
# it writes variants or reuses C_public. It has no fixture marker so this tests
# the direction/cardinality gate independently of the provenance gate.
write_callback_html "stale-showcase" "d e f"
make_callback "$sandbox/refined-preflight.json" "$refined_campaign_key" "$refined_campaign_id" "$refined_job_id" "stale-showcase" "d e f" "provider_execution" "$sandbox/refined-stale-callback.json"
stale_status="$(post_callback "$sandbox/refined-stale-callback.json" "$sandbox/refined-stale-result.json")"
test "$stale_status" = "422" || fail "Stale d/e/f callback returned HTTP $stale_status; expected 422."
assert_file_json "$sandbox/refined-stale-result.json" '.error == "invalid_callback" and (.message | test("contracted number|directions"))'
test "$(sqlq "SELECT COUNT(*) FROM proof_variant WHERE campaign_id = $refined_campaign_id;")" = "0" || fail "Stale d/e/f callback wrote refined variants."

# A complete a-f callback with explicit fixture evidence also fails closed,
# even while its top-level classification claims production completion.
write_callback_html "refined-fixture" "a b c d e f"
make_callback "$sandbox/refined-preflight.json" "$refined_campaign_key" "$refined_campaign_id" "$refined_job_id" "refined-fixture" "a b c d e f" "fixture_execution" "$sandbox/refined-fixture-callback.json"
refined_fixture_status="$(post_callback "$sandbox/refined-fixture-callback.json" "$sandbox/refined-fixture-result.json")"
test "$refined_fixture_status" = "422" || fail "Refined fixture provenance callback returned HTTP $refined_fixture_status; expected 422."
assert_file_json "$sandbox/refined-fixture-result.json" '.error == "invalid_callback" and (.message | test("non-production fixture/mock/test evidence"))'
test "$(sqlq "SELECT COUNT(*) FROM proof_variant WHERE campaign_id = $refined_campaign_id;")" = "0" || fail "Rejected refined fixture callback wrote variants."
test "$(sqlq "SELECT proof_campaign_id FROM famtastic_project_request WHERE id = $request_id;")" = "" || fail "Rejected refined callbacks attached a campaign to the request."
test "$(sqlq "SELECT proof_review_status FROM famtastic_project_request WHERE id = $request_id;")" = "refinement_queued" || fail "Rejected refined callbacks advanced request review state."

# 7. Local post-gate setup is explicitly NOT a provider completion. It uses
# the exact preflight source/lineage but is never submitted to the verifier.
# This lets the test prove owner approval, customer visibility, selection, and
# revision work items without claiming the fixture is a creative result.
REFINED_CAMPAIGN_ID="$refined_campaign_id" REFINED_BUILD_ID="$refined_build_id" REQUEST_ID="$request_id" "${drush[@]}" eval '
  $campaignId = (int) getenv("REFINED_CAMPAIGN_ID");
  $buildId = (string) getenv("REFINED_BUILD_ID");
  $requestId = (int) getenv("REQUEST_ID");
  $entities = \Drupal::entityTypeManager();
  $telemetry = \Drupal::service("famtastic_pipeline.build_telemetry");
  $preflight = $telemetry->loadBuildDna($buildId);
  if (!$preflight || (string) $preflight["record"]["status"] !== "preflight") throw new RuntimeException("Refined runner preflight is unavailable for local state setup.");
  $campaign = $entities->getStorage("proof_campaign")->load($campaignId);
  if (!$campaign) throw new RuntimeException("Refined campaign is missing.");
  $now = \Drupal::time()->getRequestTime();
  $campaignKey = (string) $campaign->get("campaign_id")->value;
  $contracts = [
    "a" => ["name" => "Normal", "intent" => "clear, credible, and tailored"],
    "b" => ["name" => "Medium FAMtastic", "intent" => "more expressive while conversion-led"],
    "c" => ["name" => "Ultra FAMtastic · Direction 1", "intent" => "strong visual campaign"],
    "d" => ["name" => "Ultra FAMtastic · Direction 2", "intent" => "distinct maximum-FAMtastic visual system"],
    "e" => ["name" => "Ultra FAMtastic · Direction 3", "intent" => "distinct maximum-FAMtastic visual system"],
    "f" => ["name" => "Ultra FAMtastic · Direction 4", "intent" => "distinct maximum-FAMtastic visual system"],
  ];
  $variants = [];
  $artifacts = [];
  foreach ($contracts as $direction => $contract) {
    $directory = \Drupal::root() . "/proofs/" . $campaignKey . "/" . $direction;
    if (!is_dir($directory) && !mkdir($directory, 0775, TRUE) && !is_dir($directory)) throw new RuntimeException("Could not create refined sandbox artifact directory.");
    $html = "<!doctype html><html><body><h1>Sandbox refined " . strtoupper($direction) . "</h1></body></html>";
    $path = $directory . "/index.html";
    file_put_contents($path, $html, LOCK_EX);
    $relative = "web/proofs/" . $campaignKey . "/" . $direction . "/index.html";
    $variant = $entities->getStorage("proof_variant")->create([
      "campaign_id" => $campaignId,
      "direction_id" => $direction,
      "direction_name" => $contract["name"],
      "artifact_path" => $relative,
      "thumbnail_path" => "",
      "preview_url" => "/proofs/" . $campaignKey . "/" . $direction . "/",
      "design_dna" => json_encode(["direction" => $direction, "sandbox_state_setup" => TRUE], JSON_UNESCAPED_SLASHES),
    ]);
    $variant->save();
    $variants[] = $variant;
    $artifacts[] = ["role" => "proof_html", "direction_id" => $direction, "path" => $relative, "sha256" => hash_file("sha256", $path), "rights_status" => "sandbox-only"];
  }
  $dna = (array) $preflight["manifest"];
  $dna["classification"] = "production_proof_completion";
  $dna["sandbox_state_setup_only"] = TRUE;
  $dna["created_at"] = gmdate(DATE_ATOM, $now);
  $dna["run"]["status"] = "completed";
  $dna["run"]["completion_state"] = "provider_completed";
  $dna["stages"] = [["stage_id" => "sandbox-state-setup", "capability" => "state_precondition", "execution" => ["provider" => ["id" => "sandbox"], "model" => ["id" => "none"]], "result" => ["status" => "not_provider_evidence"]]];
  $dna["artifacts"] = $artifacts;
  $dna["retrieval"] = ["database" => ["status" => "sandbox_state_only"]];
  $dna["integrity"] = ["artifact_hash_algorithm" => "sha256"];
  $dna["quality"] = ["status" => "not_proven", "open_gates" => ["real provider", "browser QA", "independent visual review"]];
  $telemetry->recordBuildDna($dna);
  $record = $telemetry->loadBuildDna($buildId);
  $campaign->set("generation_status", "ready")->set("ready_at", $now)->save();
  \Drupal::service("famtastic_pipeline.customer_portal")->attachWebsiteRequestProof($requestId, $campaign, $variants);
  $request = \Drupal::database()->select("famtastic_project_request", "r")->fields("r")->condition("id", $requestId)->execute()->fetchAssoc();
  print json_encode(["campaign_id" => $campaignId, "campaign_key" => $campaignKey, "build_id" => $buildId, "build_hash" => (string) $record["record"]["artifact_checksum"], "request_proof_campaign_id" => (int) $request["proof_campaign_id"], "request_status" => (string) $request["proof_review_status"]], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
' >"$sandbox/local-refined-state.json"
assert_file_json "$sandbox/local-refined-state.json" --argjson campaign "$refined_campaign_id" --arg build "$refined_build_id" '.campaign_id == $campaign and .request_proof_campaign_id == $campaign and .request_status == "owner_review" and .build_id == $build and (.build_hash | test("^[a-f0-9]{64}$"))'
jq -n --arg run "$run_id" --arg request "$request_public_id" --arg campaign "$refined_campaign_key" --arg build "$refined_build_id" '
  {classification:"sandbox_local_post_gate_state_not_provider_completion",run_id:$run,website_request_public_id:$request,campaign_id:$campaign,build_id:$build,callback_verifier_used:false,reason:"Required solely to exercise owner approval and customer workflow after verifier rejection of fixture evidence."}
' >"$sandbox/evidence/local-refined-state-setup.json"
test "$(sqlq "SELECT COUNT(*) FROM proof_variant WHERE campaign_id = $refined_campaign_id;")" = "6" || fail "Local refined state lacks exactly six proof variants."
test "$(sqlq "SELECT COUNT(*) FROM famtastic_notification_outbox WHERE notification_key LIKE 'website-request:$request_id:owner-proof-review:%' AND status = 'queued';")" = "1" || fail "Refined proof owner-review alert was not queued."
test "$(jq -s 'length' "$mail_capture")" = "1" || fail "Local state setup reached a mailbox."

# Owner approves the six refined directions. This queues one customer email,
# but the outbox worker is still disabled so it never becomes a send.
REQUEST_ID="$request_id" "${drush[@]}" eval '
  $row = \Drupal::service("famtastic_pipeline.customer_portal")->approveWebsiteRequestProof((int) getenv("REQUEST_ID"), 1);
  print json_encode($row, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
' >"$sandbox/refined-owner-approval.json"
assert_file_json "$sandbox/refined-owner-approval.json" '.proof_review_status == "customer_ready"'
test "$(sqlq "SELECT COUNT(*) FROM famtastic_notification_outbox WHERE notification_key = 'website-request:$request_id:proofs:$refined_campaign_id:6' AND recipient = '$email' AND status = 'queued';")" = "1" || fail "Six-proof owner approval did not queue one customer proof email."
test "$(jq -s 'length' "$mail_capture")" = "1" || fail "Proof owner approval sent mail directly instead of only queueing it."

# 8. The actual authenticated endpoint records a selection. Checkout remains
# gated; an incomplete request must not create an order or reach a gateway.
selection_status="$(curl -sS -b "$cookie_jar" -o "$sandbox/selection.json" -w '%{http_code}' -X POST -H 'Content-Type: application/json' -H "X-CSRF-Token: $csrf_token" --data '{"action":"select","direction":"f"}' "$base_url/api/customer/website-requests/$request_public_id/proof-decision")"
test "$selection_status" = "200" || fail "Authenticated proof selection returned HTTP $selection_status"
assert_file_json "$sandbox/selection.json" '.ok == true and .website_request.proof_review_status == "selected" and .website_request.proofs.selected_variant == "f"'
checkout_status="$(curl -sS -b "$cookie_jar" -o "$sandbox/checkout-gated.json" -w '%{http_code}' -X POST -H 'Content-Type: application/json' -H "X-CSRF-Token: $csrf_token" --data "$(jq -nc --arg organization "$organization_public_id" --arg request "$request_public_id" '{organization:$organization,website_request:$request,skus:["FAM-FOOT-199"],domain_choice:"undecided",accept_terms:false}')" "$base_url/api/customer/checkout")"
test "$checkout_status" = "422" || fail "Incomplete checkout gate returned HTTP $checkout_status"
# This error contract intentionally omits an `ok` field; 422 plus a declared
# prerequisite error is the fail-closed signal. Accept an explicit false or
# its documented omission, never a success response.
assert_file_json "$sandbox/checkout-gated.json" '(.ok == false or .ok == null) and (.error == "domain_choice_required" or .error == "terms_required" or .error == "website_request_review_required")'
test "$(sqlq "SELECT commerce_order_id FROM famtastic_project_request WHERE id = $request_id;")" = "" || fail "Gated checkout created an order."

# 9. The actual revision endpoint produces durable, immutable work items. Its
# selected-direction runner dispatch is exercised against the local wire double;
# a provider-shaped fixture callback is then rejected before it can elevate a
# revision candidate or send either notification.
revision_notes="Keep Direction F, but make the consultation path more prominent."
revision_payload="$(jq -nc --arg notes "$revision_notes" '{action:"revision",notes:$notes}')"
revision_status="$(curl -sS -b "$cookie_jar" -o "$sandbox/revision.json" -w '%{http_code}' -X POST -H 'Content-Type: application/json' -H "X-CSRF-Token: $csrf_token" --data "$revision_payload" "$base_url/api/customer/website-requests/$request_public_id/proof-decision")"
test "$revision_status" = "200" || fail "Authenticated revision request returned HTTP $revision_status"
assert_file_json "$sandbox/revision.json" '.ok == true and .website_request.proof_review_status == "revision_requested" and .website_request.proof_revision.status == "queued"'
revision_public_id="$(jq -r '.website_request.proof_revision.public_id' "$sandbox/revision.json")"
test -n "$revision_public_id" && test "$revision_public_id" != "null" || fail "Revision response has no public id."
REVISION_PUBLIC_ID="$revision_public_id" REQUEST_ID="$request_id" "${drush[@]}" eval '
  $db = \Drupal::database();
  $revision = $db->select("famtastic_proof_revision", "r")->fields("r")->condition("public_id", getenv("REVISION_PUBLIC_ID"))->execute()->fetchAssoc();
  if (!$revision) throw new RuntimeException("Revision row is missing.");
  $artifactCount = (int) $db->select("famtastic_proof_revision_artifact", "a")->condition("revision_id", (int) $revision["id"])->condition("artifact_role", "baseline")->countQuery()->execute()->fetchField();
  $job = $db->select("famtastic_job", "j")->fields("j")->condition("id", (int) $revision["runner_job_id"])->execute()->fetchAssoc();
  print json_encode(["revision" => $revision, "baseline_artifact_count" => $artifactCount, "job_type" => (string) $job["job_type"], "job_payload" => json_decode((string) $job["payload"], TRUE, 512, JSON_THROW_ON_ERROR)], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
' >"$sandbox/revision-contract.json"
assert_file_json "$sandbox/revision-contract.json" --arg public "$revision_public_id" --argjson request "$request_id" --argjson campaign "$refined_campaign_id" '
  .revision.public_id == $public
  and .revision.status == "queued"
  and .revision.direction_id == "f"
  and (.revision.revision_number | tonumber) == 1
  and .baseline_artifact_count == 1
  and .job_type == "proof.revision.generate"
  and .job_payload.routine == "website_proof.generate.v1"
  and .job_payload.proof_phase == "revision"
  and .job_payload.requested_profile_id == "portal_selected_direction_revision.v1"
  and .job_payload.proof_count == 1
  and .job_payload.selected_direction == "f"
  and .job_payload.direction_id == "f"
  and .job_payload.website_request_id == $request
  and .job_payload.proof_campaign_id == $campaign
  and .job_payload.source_correlation.website_request_id == $request
  and .job_payload.source_correlation.proof_campaign_id == $campaign
  and .job_payload.source_correlation.revision_public_id == $public
  and .job_payload.source_correlation.selected_direction == "f"
  and .job_payload.commercial_mutations_allowed == false
'
test "$(sqlq "SELECT COUNT(*) FROM famtastic_notification_outbox WHERE notification_key LIKE 'proof-revision:$revision_public_id:%' AND status = 'queued';")" = "2" || fail "Revision did not queue exactly owner/customer receipts."
test "$(REQUEST_ID="$request_id" "${drush[@]}" eval '$value = \Drupal::database()->select("famtastic_project_request", "r")->fields("r", ["intake_data"])->condition("id", (int) getenv("REQUEST_ID"))->execute()->fetchField(); print hash("sha256", (string) $value);')" = "$request_intake_hash_before_revision" || fail "Revision mutated detailed intake data."

# The canonical runner may dispatch only the selected refined direction. The
# local router checks that one-direction transport shape, but never generates
# a proof or customer-visible artifact.
SITE_STUDIO_URL="$mock_url" \
SITE_STUDIO_DISPATCH_SECRET="$mock_secret" \
FAMTASTIC_PUBLIC_BASE_URL="$base_url" \
  "${drush[@]}" famtastic:jobs-run --type=proof.revision.generate --prospect="$prospect_id" --limit=1 >"$sandbox/revision-runner-job.json"
assert_file_json "$sandbox/revision-runner-job.json" --arg public "$revision_public_id" '
  length == 1
  and .[0].status == "completed"
  and .[0].result.status == "waiting_callback"
  and .[0].result.proof_phase == "revision"
  and .[0].result.profile_id == "portal_selected_direction_revision.v1"
  and .[0].result.revision_public_id == $public
  and (.[0].result.studio_job_id | startswith("fixture-"))
  and (.[0].result.proof_runner_build_id | test("^proof-runner-[0-9a-f-]{36}$"))
'
revision_provider_job_id="$(jq -r '.[0].result.studio_job_id' "$sandbox/revision-runner-job.json")"
revision_build_id="$(jq -r '.[0].result.proof_runner_build_id' "$sandbox/revision-runner-job.json")"
assert_file_json "$mock_capture" --arg campaign "$refined_campaign_key" '
  select(.routine == "website_proof.generate.v1" and .campaign_id == $campaign and .proof_phase == "revision" and .directions == ["f"] and (.direction_contract | keys == ["f"]))
  | .proof_runner.profile_id == "portal_selected_direction_revision.v1"
    and (.proof_runner.build_id | test("^proof-runner-[0-9a-f-]{36}$"))
    and (.proof_runner.contract_sha256 | test("^[a-f0-9]{64}$"))
'
REVISION_BUILD_ID="$revision_build_id" "${drush[@]}" eval '
  $record = \Drupal::service("famtastic_pipeline.build_telemetry")->loadBuildDna(getenv("REVISION_BUILD_ID"));
  if (!$record) throw new RuntimeException("Missing selected-direction revision preflight.");
  print json_encode($record, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
' >"$sandbox/revision-preflight.json"
assert_file_json "$sandbox/revision-preflight.json" --arg public "$revision_public_id" --argjson request "$request_id" --argjson campaign "$refined_campaign_id" '
  .record.status == "preflight"
  and .manifest.classification == "proof_runner_preflight"
  and .manifest.run.status == "dispatched_waiting_callback"
  and .manifest.recipe.routine == "website_proof.generate.v1"
  and .manifest.recipe.profile_id == "portal_selected_direction_revision.v1"
  and .manifest.recipe.proof_count == 1
  and (.manifest.recipe.direction_contract | keys == ["f"])
  and .manifest.run.source_correlation.website_request_id == $request
  and .manifest.run.source_correlation.proof_campaign_id == $campaign
  and .manifest.run.source_correlation.revision_public_id == $public
  and .manifest.run.source_correlation.selected_direction == "f"
  and .manifest.run.source_correlation.direction_id == "f"
  and (.manifest.lineage.baseline_artifact_sha256 | test("^[a-f0-9]{64}$"))
  and (.manifest.lineage.revision_notes_sha256 | test("^[a-f0-9]{64}$"))
'
test "$(sqlq "SELECT status FROM famtastic_proof_revision WHERE public_id = '$revision_public_id';")" = "waiting_callback" || fail "Revision dispatch did not persist a waiting callback state."

# The deliberately-labelled callback must fail at the shared verifier. It
# cannot create a revision candidate, alter the immutable C_refined baseline,
# or send queued receipts straight to a mailbox.
write_callback_html "revision-fixture" "f"
make_callback "$sandbox/revision-preflight.json" "$refined_campaign_key" "$refined_campaign_id" "$revision_provider_job_id" "revision-fixture" "f" "fixture_execution" "$sandbox/revision-fixture-callback.json"
revision_fixture_status="$(post_callback "$sandbox/revision-fixture-callback.json" "$sandbox/revision-fixture-result.json")"
test "$revision_fixture_status" = "422" || fail "Revision fixture provenance callback returned HTTP $revision_fixture_status; expected 422."
assert_file_json "$sandbox/revision-fixture-result.json" '.error == "invalid_callback" and (.message | test("non-production fixture/mock/test evidence"))'
test "$(sqlq "SELECT status FROM famtastic_proof_revision WHERE public_id = '$revision_public_id';")" = "waiting_callback" || fail "Rejected revision fixture callback advanced the revision state."
test "$(sqlq "SELECT COUNT(*) FROM famtastic_proof_revision_artifact WHERE revision_id = (SELECT id FROM famtastic_proof_revision WHERE public_id = '$revision_public_id') AND artifact_role = 'candidate';")" = "0" || fail "Rejected revision fixture callback created a candidate artifact."
test "$(sqlq "SELECT COUNT(*) FROM famtastic_notification_outbox WHERE notification_key LIKE 'proof-revision:$revision_public_id:%' AND status = 'queued';")" = "2" || fail "Rejected revision fixture callback changed revision notification state."
test "$(jq -s 'length' "$mail_capture")" = "1" || fail "Revision runner fixture reached a mailbox."

# Same notes are idempotent under revision_requested (including a dispatched
# revision); different notes are rejected while the owner-gated revision is
# still open.
revision_retry_status="$(curl -sS -b "$cookie_jar" -o "$sandbox/revision-retry.json" -w '%{http_code}' -X POST -H 'Content-Type: application/json' -H "X-CSRF-Token: $csrf_token" --data "$revision_payload" "$base_url/api/customer/website-requests/$request_public_id/proof-decision")"
test "$revision_retry_status" = "200" || fail "Same-note revision retry returned HTTP $revision_retry_status"
assert_file_json "$sandbox/revision-retry.json" --arg public "$revision_public_id" '.ok == true and .website_request.proof_revision.public_id == $public'
different_revision_status="$(curl -sS -b "$cookie_jar" -o "$sandbox/revision-different.json" -w '%{http_code}' -X POST -H 'Content-Type: application/json' -H "X-CSRF-Token: $csrf_token" --data '{"action":"revision","notes":"A different second revision while the first remains open."}' "$base_url/api/customer/website-requests/$request_public_id/proof-decision")"
test "$different_revision_status" = "404" || fail "Different open revision returned HTTP $different_revision_status; expected a fail-closed response."
test "$(sqlq "SELECT COUNT(*) FROM famtastic_proof_revision WHERE website_request_id = $request_id;")" = "1" || fail "Open revision allowed a second revision row."
test "$(sqlq "SELECT commerce_order_id FROM famtastic_project_request WHERE id = $request_id;")" = "" || fail "Revision request started checkout."

# A revision makes checkout unavailable until its own provider callback and
# owner approval occur. This endpoint returns before Commerce/Stripe work.
revision_checkout_status="$(curl -sS -b "$cookie_jar" -o "$sandbox/revision-checkout-gated.json" -w '%{http_code}' -X POST -H 'Content-Type: application/json' -H "X-CSRF-Token: $csrf_token" --data "$(jq -nc --arg organization "$organization_public_id" --arg request "$request_public_id" '{organization:$organization,website_request:$request,skus:["FAM-FOOT-199"],domain_choice:"existing_domain",recurring_authorized:true,accept_terms:true,terms_version:"portal-v1"}')" "$base_url/api/customer/checkout")"
test "$revision_checkout_status" = "422" || fail "Revision-state checkout returned HTTP $revision_checkout_status; expected selection gate."
assert_file_json "$sandbox/revision-checkout-gated.json" '(.ok == false or .ok == null) and .error == "website_proof_selection_required"'

# 10. No declared provider route is a hard stop. This independent public lead
# gets a retryable/gated job with no campaign, proof, email, checkout, or
# fallback renderer.
unavailable_payload="$(jq -nc --arg email "$unavailable_email" '{source:"e2e-public-preview-unavailable",branch:"website",answers:{email:$email,businessName:"Unavailable Provider Fixture",industry:"Services",location:"Atlanta",businessDescription:"Provider preflight must fail closed."}}')"
unavailable_status="$(curl -sS -o "$sandbox/unavailable-quote.json" -w '%{http_code}' -X POST -H 'Content-Type: application/json' --data "$unavailable_payload" "$base_url/api/public/quote")"
test "$unavailable_status" = "202" || fail "Unavailable-provider public lead returned HTTP $unavailable_status"
unavailable_prospect_id="$(jq -r '.prospect_id' "$sandbox/unavailable-quote.json")"
SITE_STUDIO_URL='' SITE_STUDIO_DISPATCH_SECRET='' FAMTASTIC_PUBLIC_BASE_URL="$base_url" \
  "${drush[@]}" famtastic:jobs-run --type=proof.generate --prospect="$unavailable_prospect_id" --limit=1 >"$sandbox/unavailable-job.json"
assert_file_json "$sandbox/unavailable-job.json" 'length == 1 and .[0].status == "retry" and (.[0].error | test("preflight gated"))'
test "$(sqlq "SELECT COUNT(*) FROM proof_campaign WHERE prospect_id = $unavailable_prospect_id;")" = "0" || fail "Unavailable provider fell back to a proof campaign."
test "$(sqlq "SELECT COUNT(*) FROM famtastic_notification_outbox WHERE recipient = '$unavailable_email' AND category = 'transactional';")" = "0" || fail "Unavailable provider queued customer mail."

jq -n \
  --arg run_id "$run_id" \
  --arg public_delivery "$delivery_public_id" \
  --arg request "$request_public_id" \
  --arg revision "$revision_public_id" \
  --arg public_campaign "$public_campaign_key" \
  --arg refined_campaign "$refined_campaign_key" '
  {
    classification:"fresh_sandbox_local_acceptance_only",
    run_id:$run_id,
    public_delivery:$public_delivery,
    detailed_request:$request,
    revision:$revision,
    public_campaign:$public_campaign,
    refined_campaign:$refined_campaign,
    passed:[
      "public intake durable outbox and a-b-c job",
      "public fixture callback provenance rejection before staging",
      "explicit sandbox-only public state setup then owner queue",
      "same-email exact signed-delivery claim",
      "distinct detailed refined a-f job and lineage",
      "stale d-e-f callback rejection",
      "refined fixture callback provenance rejection",
      "explicit sandbox-only refined state setup then owner queue",
      "authenticated selection and checkout gating",
      "revision baseline/job/outbox/idempotency/no-commercial-mutation",
      "revision one-direction canonical dispatch and fixture callback rejection",
      "unavailable provider fails closed"
    ],
    not_proven:[
      "real creative provider execution",
      "real browser QA or independent visual review",
      "real SMTP/inbox delivery",
      "real customer proof email",
      "real Stripe checkout or paid handoff",
      "production deployment",
      "revision provider callback and owner-approved candidate"
    ],
    external_network_calls:0,
    smtp_sends:0,
    payment_gateway_calls:0,
    production_mutations:0
  }
' >"$sandbox/lifecycle-report.json"
assert_file_json "$sandbox/lifecycle-report.json" '.classification == "fresh_sandbox_local_acceptance_only" and .external_network_calls == 0 and .smtp_sends == 0 and .payment_gateway_calls == 0 and .production_mutations == 0'

echo "PASS: strict local public lifecycle safety contract verified."
echo "This is not a provider, SMTP, payment, customer-delivery, or production certification."
