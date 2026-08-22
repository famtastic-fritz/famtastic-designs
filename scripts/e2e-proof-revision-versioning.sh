#!/usr/bin/env bash
set -euo pipefail

# Local-only revision lineage acceptance fixture. This creates an isolated
# Drupal/SQLite installation and performs no provider call, SMTP send, checkout,
# or deployment. It proves the durable service contract, not production proof
# delivery or creative quality.

repo_root="$(cd "$(dirname "$0")/.." && pwd)"
sandbox="$(mktemp -d "${TMPDIR:-/tmp}/famtastic-proof-revision.XXXXXX")"
fixture_id="$(date +%s)-$$"

cleanup() {
  case "$sandbox" in
    "${TMPDIR:-/tmp}"/famtastic-proof-revision.*)
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

command -v rsync >/dev/null 2>&1 || fail "rsync is required"
command -v jq >/dev/null 2>&1 || fail "jq is required"
test -x "$repo_root/backend/vendor/bin/drush" || fail "Run Composer install in backend before this local fixture."

echo "LOCAL FIXTURE ONLY: proof revision/versioning $fixture_id"
echo "No provider, SMTP send, checkout, or production mutation will run."

mkdir -p "$sandbox/backend"
rsync -a \
  --exclude vendor \
  --exclude private \
  --exclude 'web/sites/default/files' \
  "$repo_root/backend/" "$sandbox/backend/"
cp -R "$repo_root/backend/vendor" "$sandbox/backend/vendor"
mkdir -p "$sandbox/backend/web/sites/default/files" "$sandbox/backend/private"
chmod -R u+rwX "$sandbox/backend/web/sites/default"

(
  cd "$sandbox/backend"
  export DRUSH_ROOT="$sandbox/backend/web"
  drush=(vendor/bin/drush --root="$DRUSH_ROOT")
  DB_URL="sqlite://sites/default/files/.proof-revision.sqlite" ./setup.sh >/dev/null
  actual_root="$("${drush[@]}" status --field=root)"
  expected_root="$(cd "$DRUSH_ROOT" && pwd -P)"
  test "$actual_root" = "$expected_root" || fail "fixture bootstrapped the wrong Drupal root: $actual_root"
  "${drush[@]}" en -y famtastic_pipeline >/dev/null
  "${drush[@]}" updb -y >/dev/null
  # Exercise the additive update path against an already-created schema too;
  # this catches accidental non-idempotent index/field migration changes.
  "${drush[@]}" eval 'require_once \Drupal::service("extension.list.module")->getPath("famtastic_pipeline") . "/famtastic_pipeline.install"; $updateSandbox = []; famtastic_pipeline_update_8035($updateSandbox);' >/dev/null
  "${drush[@]}" cr >/dev/null

  REVISION_FIXTURE_ID="$fixture_id" "${drush[@]}" eval '
    $fixture = getenv("REVISION_FIXTURE_ID");
    $db = \Drupal::database();
    $entities = \Drupal::entityTypeManager();
    $time = \Drupal::time()->getRequestTime();
    $uuid = \Drupal::service("uuid");
    $assert = static function (bool $condition, string $message): void {
      if (!$condition) {
        throw new \RuntimeException("ASSERTION FAILED: " . $message);
      }
    };

    $settings = \Drupal::configFactory()->getEditable("famtastic_pipeline.settings");
    $settings->set("frontend_base_url", "https://fixture.famtasticdesigns.test");
    $settings->set("notification_to_email", "owner-" . $fixture . "@example.test");
    $settings->save();

    $prospect = $entities->getStorage("famtastic_prospect")->create([
      "business_name" => "Revision Fixture " . $fixture,
      "business_category" => "Fixture",
      "business_description" => "Local-only proof revision lineage fixture.",
      "service_area" => "Local",
      "campaign" => "proof-revision-" . $fixture,
      "status" => "lead",
    ]);
    $prospect->save();

    $campaignKey = "revision-fixture-" . $fixture;
    $campaign = $entities->getStorage("proof_campaign")->create([
      "campaign_id" => $campaignKey,
      "prospect_id" => (int) $prospect->id(),
      "business_name" => "Revision Fixture " . $fixture,
      "status" => "active",
      "generation_status" => "ready",
    ]);
    $campaign->save();

    $basePaths = [];
    foreach (["a", "b", "c", "d", "e", "f"] as $direction) {
      $directory = \Drupal::root() . "/proofs/" . $campaignKey . "/" . $direction;
      if (!is_dir($directory) && !mkdir($directory, 0775, TRUE) && !is_dir($directory)) {
        throw new \RuntimeException("Unable to create fixture artifact directory.");
      }
      $html = "<!doctype html><html><body><h1>Baseline " . strtoupper($direction) . "</h1></body></html>";
      $path = $directory . "/index.html";
      file_put_contents($path, $html);
      $basePaths[$direction] = $path;
      $variant = $entities->getStorage("proof_variant")->create([
        "campaign_id" => (int) $campaign->id(),
        "direction_id" => $direction,
        "direction_name" => "Fixture direction " . strtoupper($direction),
        "artifact_path" => "web/proofs/" . $campaignKey . "/" . $direction . "/index.html",
        "thumbnail_path" => "",
        "preview_url" => "/proofs/" . $campaignKey . "/" . $direction . "/",
        "design_dna" => json_encode(["direction" => $direction, "fixture" => TRUE], JSON_UNESCAPED_SLASHES),
      ]);
      $variant->save();
    }

    $customerId = (int) $db->insert("famtastic_customer")->fields([
      "public_id" => $uuid->generate(),
      "uid" => 1,
      "prospect_id" => (int) $prospect->id(),
      "display_name" => "Revision Customer",
      "email" => "customer-" . $fixture . "@example.test",
      "acquisition_source" => "fixture",
      "marketing_status" => "subscribed",
      "created" => $time,
      "changed" => $time,
    ])->execute();
    $organizationId = (int) $db->insert("famtastic_organization")->fields([
      "public_id" => $uuid->generate(),
      "type" => "business",
      "name" => "Revision Fixture Workspace",
      "status" => "active",
      "created" => $time,
      "changed" => $time,
    ])->execute();
    $db->insert("famtastic_membership")->fields([
      "organization_id" => $organizationId,
      "customer_id" => $customerId,
      "role" => "owner",
      "status" => "active",
      "created" => $time,
      "changed" => $time,
    ])->execute();

    $requestPublicId = $uuid->generate();
    $requestId = (int) $db->insert("famtastic_project_request")->fields([
      "public_id" => $requestPublicId,
      "organization_id" => $organizationId,
      "customer_id" => $customerId,
      "prospect_id" => (int) $prospect->id(),
      "source_preview_delivery_id" => 1,
      "proof_phase" => "refined_six",
      "proof_profile_id" => "portal_refined_six.v1",
      "proof_campaign_id" => (int) $campaign->id(),
      "proof_review_status" => "selected",
      "selected_proof_direction" => "f",
      "selected_proof_at" => $time,
      "status" => "submitted",
      "project_name" => "Revision Fixture Website",
      "business_name" => "Revision Fixture",
      "project_type" => "new_website",
      "domain_choice" => "undecided",
      "intake_data" => json_encode(["schema_version" => "website_discovery_v3", "recommendation" => []], JSON_UNESCAPED_SLASHES),
      "submitted_at" => $time,
      "created" => $time,
      "changed" => $time,
    ])->execute();

    $telemetry = \Drupal::service("famtastic_pipeline.build_telemetry");
    $baselineBuildId = "fixture-baseline-" . $fixture;
    $telemetry->recordBuildDna([
      "schema" => "famtastic.build-dna.v1",
      "build_id" => $baselineBuildId,
      "classification" => "production_proof_completion",
      "recipe" => ["routine" => "website_proof.generate.v1", "profile_id" => "portal_refined_six.v1"],
      "run" => [
        "status" => "completed",
        "completion_state" => "provider_completed",
        "prospect_id" => (int) $prospect->id(),
        "proof_campaign_id" => (int) $campaign->id(),
        "source_correlation" => [
          "website_request_id" => $requestId,
          "website_request_public_id" => $requestPublicId,
          "proof_phase" => "refined_six",
        ],
      ],
      "stages" => [[
        "stage_id" => "fixture-baseline",
        "execution" => ["provider" => ["id" => "local-fixture"], "model" => ["id" => "fixture"]],
      ]],
    ]);

    $originalFHash = hash_file("sha256", $basePaths["f"]);
    $portal = \Drupal::service("famtastic_pipeline.customer_portal");
    $notes = "Keep this selected direction, but make the consultation path more prominent.";
    $first = $portal->decideWebsiteRequestProof($customerId, $requestPublicId, ["action" => "revision", "notes" => $notes]);
    $assert((string) $first["proof_review_status"] === "revision_requested", "portal did not enter revision_requested");
    $assert((string) ($first["proof_revision"]["status"] ?? "") === "queued", "serialized revision state is not queued");
    $second = $portal->decideWebsiteRequestProof($customerId, $requestPublicId, ["action" => "revision", "notes" => $notes]);
    $assert((string) ($second["proof_revision"]["public_id"] ?? "") === (string) $first["proof_revision"]["public_id"], "same-note revision retry was not idempotent");

    $revision = $db->select("famtastic_proof_revision", "r")->fields("r")
      ->condition("website_request_id", $requestId)->condition("direction_id", "f")->range(0, 1)->execute()->fetchAssoc();
    $assert((bool) $revision, "revision record was not created");
    $assert((string) $revision["status"] === "queued", "revision did not queue");
    $assert((int) $revision["revision_number"] === 1, "first revision number is not one");
    $assert((string) $revision["baseline_artifact_sha256"] === $originalFHash, "baseline hash is not the original selected artifact");
    $assert((int) $db->select("famtastic_proof_revision_artifact", "a")->condition("revision_id", (int) $revision["id"])->condition("artifact_role", "baseline")->countQuery()->execute()->fetchField() === 1, "baseline lineage artifact missing");
    $assert(hash_file("sha256", $basePaths["f"]) === $originalFHash, "revision request rewrote the original artifact");

    $job = $db->select("famtastic_job", "j")->fields("j")
      ->condition("id", (int) $revision["runner_job_id"])->range(0, 1)->execute()->fetchAssoc();
    $jobPayload = json_decode((string) $job["payload"], TRUE, 512, JSON_THROW_ON_ERROR);
    $assert((string) $job["job_type"] === "proof.revision.generate", "revision job type is not canonical");
    $assert((string) $jobPayload["routine"] === "website_proof.generate.v1", "revision invented a separate creative routine");
    $assert((string) $jobPayload["proof_phase"] === "revision" && (string) $jobPayload["requested_profile_id"] === "portal_selected_direction_revision.v1", "revision phase/profile mismatch");
    $assert((int) $jobPayload["proof_count"] === 1 && (string) $jobPayload["selected_direction"] === "f", "revision job is not constrained to selected direction");
    $assert(($jobPayload["commercial_mutations_allowed"] ?? TRUE) === FALSE, "revision job permits commercial mutation");
    $assert((int) $db->select("famtastic_notification_outbox", "n")->condition("notification_key", "proof-revision:" . $revision["public_id"] . ":%", "LIKE")->condition("status", "queued")->countQuery()->execute()->fetchField() === 2, "request did not queue owner and customer receipts");

    $revisions = \Drupal::service("famtastic_pipeline.proof_revisions");
    $providerJobId = "fixture-provider-" . $fixture;
    $dispatchBuildId = "fixture-dispatch-" . $fixture;
    $revisions->markRunnerDispatched((string) $revision["public_id"], $providerJobId, $dispatchBuildId, str_repeat("a", 64));

    $callbackBuildId = $dispatchBuildId;
    $telemetry->recordBuildDna([
      "schema" => "famtastic.build-dna.v1",
      "build_id" => $callbackBuildId,
      "classification" => "production_proof_completion",
      "recipe" => ["routine" => "website_proof.generate.v1", "profile_id" => "portal_selected_direction_revision.v1"],
      "run" => [
        "status" => "completed",
        "completion_state" => "provider_completed",
        "prospect_id" => (int) $prospect->id(),
        "proof_campaign_id" => (int) $campaign->id(),
        "source_correlation" => $jobPayload["source_correlation"],
      ],
      "stages" => [[
        "stage_id" => "fixture-revision",
        "execution" => ["provider" => ["id" => "local-fixture"], "model" => ["id" => "fixture"]],
      ]],
    ]);
    $callbackDna = $telemetry->loadBuildDna($callbackBuildId);
    $assert((bool) $callbackDna, "callback Build DNA was not registered");
    $verification = [
      "status" => "verified",
      "build_id" => $callbackBuildId,
      "build_dna_hash" => (string) $callbackDna["record"]["artifact_checksum"],
      "profile_id" => "portal_selected_direction_revision.v1",
      "proof_phase" => "revision",
      "source_correlation" => $jobPayload["source_correlation"],
    ];
    $candidateHtml = "<!doctype html><html><body><h1>Revision F</h1><p>Consultation path is prominent.</p></body></html>";
    $badSourceRejected = FALSE;
    try {
      $badVerification = $verification;
      $badVerification["source_correlation"]["selected_direction"] = "e";
      $revisions->acceptVerifiedCandidate((string) $revision["public_id"], "fixture-bad-source-" . $fixture, $providerJobId, [
        "direction_id" => "f",
        "html" => $candidateHtml,
        "artifact_sha256" => hash("sha256", $candidateHtml),
        "design_dna" => ["fixture" => TRUE],
      ], $badVerification);
    }
    catch (\InvalidArgumentException) {
      $badSourceRejected = TRUE;
    }
    $assert($badSourceRejected, "callback accepted mismatched source correlation");
    $badDirectionRejected = FALSE;
    try {
      $revisions->acceptVerifiedCandidate((string) $revision["public_id"], "fixture-bad-" . $fixture, $providerJobId, [
        "direction_id" => "e",
        "html" => $candidateHtml,
        "artifact_sha256" => hash("sha256", $candidateHtml),
        "design_dna" => ["fixture" => TRUE],
      ], $verification);
    }
    catch (\InvalidArgumentException) {
      $badDirectionRejected = TRUE;
    }
    $assert($badDirectionRejected, "callback accepted a non-selected direction");

    $ownerReview = $revisions->acceptVerifiedCandidate((string) $revision["public_id"], "fixture-callback-" . $fixture, $providerJobId, [
      "direction_id" => "f",
      "html" => $candidateHtml,
      "artifact_sha256" => hash("sha256", $candidateHtml),
      "design_dna" => ["fixture" => TRUE, "direction" => "f", "revision" => 1],
    ], $verification);
    $assert((string) $ownerReview["status"] === "owner_review", "verified callback bypassed owner review");
    $assert($revisions->activeArtifactForRequest($requestId, "f", FALSE) === NULL, "unapproved candidate became customer-visible");
    $ownerArtifact = $revisions->activeArtifactForRequest($requestId, "f", TRUE);
    $assert((bool) $ownerArtifact && (string) $ownerArtifact["visibility"] === "owner_review", "owner cannot inspect the gated candidate");
    $assert((int) $db->select("famtastic_proof_revision_artifact", "a")->condition("revision_id", (int) $ownerReview["id"])->condition("artifact_role", "candidate")->condition("direction_id", "f")->countQuery()->execute()->fetchField() === 1, "selected candidate artifact missing");
    $assert((int) $db->select("famtastic_proof_revision_artifact", "a")->condition("revision_id", (int) $ownerReview["id"])->condition("artifact_role", "candidate")->condition("direction_id", "e")->countQuery()->execute()->fetchField() === 0, "callback stored an unselected direction");

    $approved = $revisions->approveRevision((int) $ownerReview["id"], 1);
    $assert((string) $approved["status"] === "customer_ready", "owner approval did not make candidate ready");
    $requestAfter = $db->select("famtastic_project_request", "r")->fields("r")->condition("id", $requestId)->range(0, 1)->execute()->fetchAssoc();
    $assert((string) $requestAfter["proof_review_status"] === "selected", "owner approval did not restore selected proof state");
    $assert($requestAfter["commerce_order_id"] === NULL, "revision approval created a commerce order");
    $customerArtifact = $revisions->activeArtifactForRequest($requestId, "f", FALSE);
    $assert((bool) $customerArtifact && (string) $customerArtifact["visibility"] === "customer_visible", "owner-approved candidate is not customer-visible");
    $assert(hash_file("sha256", $basePaths["f"]) === $originalFHash, "callback or approval rewrote the original proof");
    $assert((int) $db->select("famtastic_private_offer", "o")->condition("website_request_id", $requestId)->countQuery()->execute()->fetchField() === 0, "revision created or changed a private offer");
    $assert((int) $db->select("famtastic_notification_outbox", "n")->condition("notification_key", "proof-revision:" . $revision["public_id"] . ":%", "LIKE")->condition("status", "sent")->countQuery()->execute()->fetchField() === 0, "revision service sent mail instead of only queuing it");

    print json_encode([
      "fixture" => $fixture,
      "request_id" => $requestId,
      "revision_public_id" => (string) $revision["public_id"],
      "revision_status" => (string) $approved["status"],
      "baseline_sha256" => $originalFHash,
      "candidate_sha256" => (string) $customerArtifact["artifact_sha256"],
      "job_type" => (string) $job["job_type"],
      "routine" => (string) $jobPayload["routine"],
      "phase" => (string) $jobPayload["proof_phase"],
      "profile" => (string) $jobPayload["requested_profile_id"],
      "provider_calls" => 0,
      "smtp_sends" => 0,
      "checkout_mutations" => 0,
    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
  ' >"$sandbox/evidence.json"
)

jq -e '
  .revision_status == "customer_ready" and
  .job_type == "proof.revision.generate" and
  .routine == "website_proof.generate.v1" and
  .phase == "revision" and
  .profile == "portal_selected_direction_revision.v1" and
  .provider_calls == 0 and .smtp_sends == 0 and .checkout_mutations == 0
' "$sandbox/evidence.json" >/dev/null || fail "revision fixture evidence is incomplete"

echo "PASS: local revision lineage, selected-direction callback, owner gate, and no-checkout/no-send behavior verified."
