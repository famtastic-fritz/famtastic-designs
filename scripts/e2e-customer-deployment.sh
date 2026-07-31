#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DRUSH="$REPO_ROOT/backend/vendor/bin/drush"
run_id="$(date +%s)-$$"
sandbox="$(mktemp -d "${TMPDIR:-/tmp}/famtastic-customer-deploy.XXXXXX")"
release_root="$sandbox/releases"
deploy_root="$sandbox/sites"
project_result="$sandbox/project.json"
deployment_result="$sandbox/deployment.json"
server_log="$sandbox/server.log"
server_pid=""
cleanup() {
  if test -n "$server_pid"; then
    kill "$server_pid" 2>/dev/null || true
    wait "$server_pid" 2>/dev/null || true
  fi
  case "$sandbox" in
    "${TMPDIR:-/tmp}"/famtastic-customer-deploy.*) rm -rf "$sandbox" ;;
    *) echo "Refusing to remove unexpected sandbox: $sandbox" >&2 ;;
  esac
}
trap cleanup EXIT
mkdir -p "$release_root" "$deploy_root"

"$DRUSH" eval "
  \$prospect = \\Drupal\\famtastic_pipeline\\Entity\\Prospect::create([
    'business_name' => 'Deployment Fixture $run_id',
    'public_email' => 'deployment-$run_id@example.test',
    'campaign' => 'deployment-e2e',
    'source' => 'synthetic',
    'status' => 'approved',
  ]);
  \$prospect->save();
  \$studio = json_encode([
    'schema_version' => '1.0',
    'business' => ['name' => 'Deployment Fixture $run_id', 'description' => 'Atomic customer deployment proof.'],
  ], JSON_UNESCAPED_SLASHES);
  \$source = hash('sha256', \$studio);
  \$release = hash('sha256', \$prospect->id() . ':' . \$source . ':$run_id');
  \$project = \\Drupal\\famtastic_pipeline\\Entity\\Project::create([
    'prospect_ref' => \$prospect->id(),
    'studio_json' => \$studio,
    'approval_status' => 'approved',
    'delivery_status' => 'approved',
    'approved_at' => \\Drupal::time()->getRequestTime(),
    'release_sha' => \$release,
    'artifact_checksum' => \$source,
  ]);
  \$project->save();
  \$ledger = \\Drupal::service('famtastic_pipeline.operational_ledger');
  \$ledger->enqueue('deployment.prepare:' . \$project->id() . ':' . \$release, 'deployment.prepare', [
    'project_id' => (int) \$project->id(),
    'release_sha' => \$release,
    'artifact_checksum' => \$source,
  ], (int) \$prospect->id());
  print json_encode(['project_id' => (int) \$project->id(), 'prospect_id' => (int) \$prospect->id()]);
" > "$project_result"
project_id="$(jq -r '.project_id' "$project_result")"

FAMTASTIC_CUSTOMER_RELEASE_ROOT="$release_root" \
FAMTASTIC_CUSTOMER_DEPLOY_ROOT="$deploy_root" \
  "$DRUSH" famtastic:jobs-run --type=deployment.prepare --limit=100 >/dev/null

FAMTASTIC_CUSTOMER_RELEASE_ROOT="$release_root" \
FAMTASTIC_CUSTOMER_DEPLOY_ROOT="$deploy_root" \
  "$DRUSH" eval "
    \$db = \\Drupal::database();
    \$deployment = \$db->select('famtastic_deployment', 'd')->fields('d')->condition('project_id', $project_id)->execute()->fetchAssoc();
    print json_encode(\$deployment, JSON_UNESCAPED_SLASHES);
  " > "$deployment_result"
deployment_id="$(jq -r '.id' "$deployment_result")"
customer_key="$(jq -r '.customer_key' "$deployment_result")"
target="$deploy_root/$customer_key"
mkdir -p "$target"
printf 'PREVIOUS RELEASE\n' > "$target/index.html"

port=$((10000 + ($$ % 300)))
FAMTASTIC_CUSTOMER_RELEASE_ROOT="$release_root" \
FAMTASTIC_CUSTOMER_DEPLOY_ROOT="$deploy_root" \
FAMTASTIC_CUSTOMER_PUBLIC_BASE="http://127.0.0.1:$port" \
FAMTASTIC_DEPLOY_TRANSPORT=local \
  "$DRUSH" famtastic:jobs-run --type=deployment.apply --limit=100 >/dev/null

php -S "127.0.0.1:$port" -t "$deploy_root" > "$server_log" 2>&1 &
server_pid=$!
for _ in $(seq 1 40); do
  curl -sf "http://127.0.0.1:$port/$customer_key/" >/dev/null && break
  sleep 0.2
done
page="$(curl -sf "http://127.0.0.1:$port/$customer_key/")"
case "$page" in
  *"Deployment Fixture $run_id"*) ;;
  *) echo "Browser verification did not return the deployed release." >&2; exit 1 ;;
esac
case "$page" in
  *'meta name="famtastic-release"'*) ;;
  *) echo "Browser verification did not expose the immutable release marker." >&2; exit 1 ;;
esac

FAMTASTIC_CUSTOMER_RELEASE_ROOT="$release_root" \
FAMTASTIC_CUSTOMER_DEPLOY_ROOT="$deploy_root" \
  "$DRUSH" eval "
    \$service = \\Drupal::service('famtastic_pipeline.customer_deployment');
    \$result = \$service->rollback($deployment_id);
    assert(\$result['status'] === 'rolled_back');
  "
test "$(cat "$target/index.html")" = "PREVIOUS RELEASE"

"$DRUSH" eval "
  \$db = \\Drupal::database();
  \$deployment = \$db->select('famtastic_deployment', 'd')->fields('d')->condition('id', $deployment_id)->execute()->fetchAssoc();
  assert(\$deployment['status'] === 'rolled_back');
  assert((int) \$deployment['rolled_back_at'] > 0);
  foreach (['deployment.prepared', 'deployment.deployed', 'deployment.rolled_back'] as \$event) {
    \$count = \$db->select('famtastic_event', 'e')->condition('event_type', \$event)->condition('project_id', $project_id)->countQuery()->execute()->fetchField();
    assert((int) \$count === 1, \$event);
  }
"

echo "PASS: immutable release, isolated target, backup, atomic deployment, browser verification, and rollback verified."
