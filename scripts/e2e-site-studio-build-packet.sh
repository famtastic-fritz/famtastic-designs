#!/usr/bin/env bash
set -euo pipefail

repo_root="$(cd "$(dirname "$0")/.." && pwd)"
sandbox="$(mktemp -d "${TMPDIR:-/tmp}/famtastic-build-packet-e2e.XXXXXX")"
cleanup() {
  case "$sandbox" in
    "${TMPDIR:-/tmp}"/famtastic-build-packet-e2e.*)
      chmod -R u+rwX "$sandbox" 2>/dev/null || true
      rm -rf "$sandbox"
      ;;
    *) echo "Refusing to remove unexpected sandbox: $sandbox" >&2 ;;
  esac
}
trap cleanup EXIT

mkdir -p "$sandbox/backend"
rsync -a --exclude vendor --exclude private --exclude 'web/sites/default/files' "$repo_root/backend/" "$sandbox/backend/"
cp -R "$repo_root/backend/vendor" "$sandbox/backend/vendor"
mkdir -p "$sandbox/backend/web/sites/default/files" "$sandbox/backend/private"
chmod -R u+rwX "$sandbox/backend/web/sites/default"

(
  cd "$sandbox/backend"
  export DRUSH_ROOT="$sandbox/backend/web"
  drush=(vendor/bin/drush --root="$DRUSH_ROOT")
  DB_URL="sqlite://sites/default/files/.ht.sqlite" ./setup.sh >/dev/null
  "${drush[@]}" en -y famtastic_pipeline >/dev/null
  "${drush[@]}" updb -y >/dev/null
  "${drush[@]}" cr >/dev/null

  PROJECT_IDS_PATH="$sandbox/project-ids.json" "${drush[@]}" eval '
    $now = \Drupal::time()->getRequestTime();
    $db = \Drupal::database();
    $uid = (int) \Drupal::entityTypeManager()->getStorage("user")->getQuery()->accessCheck(FALSE)->condition("name", "admin")->execute()[1];
    $customer = $db->insert("famtastic_customer")->fields(["public_id" => \Drupal::service("uuid")->generate(), "uid" => $uid, "display_name" => "Packet Customer", "email" => "packet-customer@example.test", "phone" => "", "acquisition_source" => "e2e", "marketing_status" => "subscribed", "verified_at" => $now, "created" => $now, "changed" => $now])->execute();
    $organization = $db->insert("famtastic_organization")->fields(["public_id" => \Drupal::service("uuid")->generate(), "type" => "individual", "name" => "Packet Customer", "status" => "active", "created" => $now, "changed" => $now])->execute();
    $db->insert("famtastic_membership")->fields(["organization_id" => $organization, "customer_id" => $customer, "role" => "owner", "status" => "active", "created" => $now, "changed" => $now])->execute();
    $storage = \Drupal::entityTypeManager()->getStorage("famtastic_project");
    $project = $storage->create(["studio_json" => json_encode(["request_id" => "local:famu-corner-20260818-fritz-001"]), "delivery_status" => "draft"]); $project->save();
    $other = $storage->create(["studio_json" => json_encode(["request_id" => "request-other-e2e"]), "delivery_status" => "draft"]); $other->save();
    foreach ([$project, $other] as $item) $db->insert("famtastic_customer_resource")->fields(["organization_id" => $organization, "resource_type" => "project", "resource_id" => $item->id(), "created" => $now])->execute();
    file_put_contents(getenv("PROJECT_IDS_PATH"), json_encode(["project" => (int) $project->id(), "other" => (int) $other->id()]));
  '

  project_id="$(jq -r .project "$sandbox/project-ids.json")"
  other_id="$(jq -r .other "$sandbox/project-ids.json")"
  python3 "$repo_root/website-delivery-swarm/autonomous_pipeline.py" prepare \
    --intake "$repo_root/website-delivery-swarm/pilots/famu-corner/scenario.json" \
    --artifact "$repo_root/artifacts/website-delivery-swarm/famu-corner-20260818" \
    --output "$sandbox/packet-run" --project-id "$project_id" \
    --select direction-e,direction-f --build-class medium --golden-replay >/dev/null
  "${drush[@]}" famtastic:site-studio-packet-register "$sandbox/packet-run/site-studio-build-packet.json" > "$sandbox/register.json"
  test "$(jq -r .newly_registered "$sandbox/register.json")" = true
  "${drush[@]}" famtastic:site-studio-packet-register "$sandbox/packet-run/site-studio-build-packet.json" > "$sandbox/register-replay.json"
  test "$(jq -r .newly_registered "$sandbox/register-replay.json")" = false

  python3 "$repo_root/website-delivery-swarm/autonomous_pipeline.py" simulate-success \
    --packet "$sandbox/packet-run/site-studio-build-packet.json" \
    --output "$sandbox/success.json" >/dev/null
  port=$((18800 + ($$ % 500)))
  SITE_STUDIO_CALLBACK_SECRET=build-packet-e2e-secret "${drush[@]}" runserver "127.0.0.1:$port" > "$sandbox/runserver.log" 2>&1 &
  server_pid=$!
  trap 'kill "$server_pid" 2>/dev/null || true; wait "$server_pid" 2>/dev/null || true' EXIT
  for _ in $(seq 1 80); do
    curl -sf "http://127.0.0.1:$port/robots.txt" >/dev/null && break
    sleep 0.25
  done
  payload="$(jq -c . "$sandbox/success.json")"
  signature="sha256=$(printf '%s' "$payload" | openssl dgst -sha256 -hmac build-packet-e2e-secret | sed 's/^.*= //')"
  curl -sf -X POST -H 'Content-Type: application/json' -H "X-FAMtastic-Signature: $signature" -d "$payload" "http://127.0.0.1:$port/api/pipeline/site-studio/callback" > "$sandbox/import.json"
  test "$(jq -r .newly_processed "$sandbox/import.json")" = true
  curl -sf -X POST -H 'Content-Type: application/json' -H "X-FAMtastic-Signature: $signature" -d "$payload" "http://127.0.0.1:$port/api/pipeline/site-studio/callback" > "$sandbox/import-replay.json"
  test "$(jq -r .newly_processed "$sandbox/import-replay.json")" = false

  jq --arg other "$other_id" '.project_id = $other' "$sandbox/success.json" > "$sandbox/wrong-project.json"
  wrong_payload="$(jq -c . "$sandbox/wrong-project.json")"
  wrong_signature="sha256=$(printf '%s' "$wrong_payload" | openssl dgst -sha256 -hmac build-packet-e2e-secret | sed 's/^.*= //')"
  if [[ "$(curl -s -o /dev/null -w '%{http_code}' -X POST -H 'Content-Type: application/json' -H "X-FAMtastic-Signature: $wrong_signature" -d "$wrong_payload" "http://127.0.0.1:$port/api/pipeline/site-studio/callback")" != 422 ]]; then
    echo "Wrong-project success packet was accepted" >&2
    exit 2
  fi
  kill "$server_pid" 2>/dev/null || true
  wait "$server_pid" 2>/dev/null || true

  PROJECT_ID="$project_id" OTHER_ID="$other_id" "${drush[@]}" eval '
    $project = \Drupal::entityTypeManager()->getStorage("famtastic_project")->load((int) getenv("PROJECT_ID"));
    $other = \Drupal::entityTypeManager()->getStorage("famtastic_project")->load((int) getenv("OTHER_ID"));
    assert($project->get("delivery_status")->value === "proof_delivered");
    assert(str_starts_with((string) $project->get("studio_job_id")->value, "studio-build-"));
    assert(strlen((string) $project->get("artifact_checksum")->value) === 64);
    assert($other->get("delivery_status")->value === "draft");
    $db = \Drupal::database();
    assert((int) $db->select("famtastic_event", "e")->condition("event_type", "site_studio.build_succeeded")->countQuery()->execute()->fetchField() === 1);
    assert((int) $db->select("famtastic_portal_activity", "a")->condition("event_type", "site_studio_build_ready")->countQuery()->execute()->fetchField() === 1);
    assert((int) $db->select("famtastic_notification_outbox", "n")->condition("category", "project_build_ready")->condition("recipient", "packet-customer@example.test")->countQuery()->execute()->fetchField() === 1);
  '
)

echo "PASS: isolated Drupal packet registration, replay safety, project scoping, success ingestion, portal activity, and transactional notification verified."
