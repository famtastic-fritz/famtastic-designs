#!/usr/bin/env bash
# Runs the canonical synthetic customer journey in a disposable Drupal runtime.
#
# This is deliberately a local-only proof harness. It never reuses the
# worktree's database, mail transport, deployment target, or provider account.
set -euo pipefail

repo_root="$(cd "$(dirname "$0")/.." && pwd)"
vendor_source="${FAMTASTIC_BACKEND_VENDOR:-$repo_root/backend/vendor}"
test -x "$vendor_source/bin/drush" || {
  echo "ERROR: install backend dependencies or set FAMTASTIC_BACKEND_VENDOR to a matching Drupal vendor directory." >&2
  exit 1
}
runtime_backend="$(cd -P "$vendor_source/.." && pwd)"
test -d "$runtime_backend/web/core" || {
  echo "ERROR: supplied Drupal runtime is incomplete (missing web/core)." >&2
  exit 1
}

for command_name in jq rsync; do
  command -v "$command_name" >/dev/null || {
    echo "ERROR: $command_name is required." >&2
    exit 1
  }
done

for variable in STRIPE_SECRET_KEY FAMTASTIC_STRIPE_SECRET_KEY FAMTASTIC_EMAIL_TRANSPORT FAMTASTIC_DOMAIN_VERIFY_MODE FAMTASTIC_DEPLOY_TRANSPORT; do
  value="${!variable:-}"
  case "$value" in
    sk_live_*|rk_live_*|smtp|dns|ssh|production)
      echo "ERROR: unsafe environment value detected in $variable." >&2
      exit 1
      ;;
  esac
done

run_id="fresh-customer-proof-$(date -u +%Y%m%dT%H%M%SZ)-$$"
evidence_root="${FAMTASTIC_PROOF_EVIDENCE_ROOT:-$repo_root/.artifacts/fresh-customer-proof}"
evidence_dir="$evidence_root/$run_id"
sandbox="$(mktemp -d "${TMPDIR:-/tmp}/famtastic-customer-proof.XXXXXX")"
runtime_repo="$sandbox/repo"
server_port="${FAMTASTIC_PROOF_PORT:-8930}"

cleanup() {
  local original_exit=$?
  trap - EXIT
  case "$sandbox" in
    "${TMPDIR:-/tmp}"/famtastic-customer-proof.*)
      chmod -R u+rwX "$sandbox" 2>/dev/null || true
      rm -rf "$sandbox"
      ;;
    *) echo "Refusing to remove unexpected sandbox: $sandbox" >&2 ;;
  esac
  exit "$original_exit"
}
trap cleanup EXIT

mkdir -p "$runtime_repo"

# The customer journey only writes its backend, artifacts, and frontend build
# output. Keep all three inside the sandbox, while preserving a small retained
# evidence bundle under the reviewed worktree.
rsync -a \
  --exclude vendor \
  --exclude private \
  --exclude 'web/sites/default/files' \
  --exclude 'web/sites/default/settings.local.php' \
  "$repo_root/backend/" "$runtime_repo/backend/"
rsync -a "$repo_root/scripts/" "$runtime_repo/scripts/"
rsync -a "$repo_root/docs/architecture/" "$runtime_repo/docs/architecture/"
rsync -a \
  --exclude node_modules \
  --exclude dist \
  --exclude 'public/video' \
  --exclude 'public/showcase' \
  "$repo_root/frontend/" "$runtime_repo/frontend/"
ln -s "$repo_root/frontend/node_modules" "$runtime_repo/frontend/node_modules"

# Build a complete matching Drupal runtime beside the copied module source.
rsync -aL "$vendor_source/" "$runtime_repo/backend/vendor/"
rsync -a "$runtime_backend/web/core/" "$runtime_repo/backend/web/core/"
rsync -a --ignore-existing "$runtime_backend/web/modules/" "$runtime_repo/backend/web/modules/"
rsync -a --ignore-existing "$runtime_backend/web/profiles/" "$runtime_repo/backend/web/profiles/"
rsync -a --ignore-existing "$runtime_backend/web/themes/" "$runtime_repo/backend/web/themes/"
for runtime_file in .ht.router.php .htaccess autoload.php autoload_runtime.php index.php robots.txt update.php; do
  cp "$runtime_backend/web/$runtime_file" "$runtime_repo/backend/web/$runtime_file"
done
cp "$runtime_backend/web/sites/default/default.settings.php" "$runtime_repo/backend/web/sites/default/default.settings.php"
chmod -R u+rwX "$runtime_repo/backend/web/sites/default"
mkdir -p "$runtime_repo/backend/web/sites/default/files" "$runtime_repo/backend/private" "$evidence_dir"

drush=("$runtime_repo/backend/vendor/bin/drush" "--root=$runtime_repo/backend/web")

# This assertion is intentionally before all workflow operations: a false
# isolation claim is worse than an unrun proof.
env -u DB_HOST -u DB_NAME -u DB_USER -u DB_PASSWORD -u DB_PORT -u PLATFORM_RELATIONSHIPS -u STRIPE_SECRET_KEY \
  "${drush[@]}" site:install standard \
  --db-url="sqlite://sites/default/files/.ht.sqlite" \
  --account-name=admin \
  --account-pass=admin \
  --account-mail=admin@famtastic.local \
  --site-name="FAMtastic Fresh Customer Proof" \
  --site-mail=no-reply@famtastic.local \
  -y >/dev/null
actual_root="$(env -u DB_HOST -u DB_NAME -u DB_USER -u DB_PASSWORD -u DB_PORT -u PLATFORM_RELATIONSHIPS "${drush[@]}" status --field=root)"
expected_root="$(cd "$runtime_repo/backend/web" && pwd -P)"
if [[ "$actual_root" != "$expected_root" || ! -s "$runtime_repo/backend/web/sites/default/files/.ht.sqlite" ]]; then
  echo "ERROR: fresh customer proof did not bootstrap its sandbox runtime." >&2
  exit 1
fi

run_status=0
env -u DB_HOST -u DB_NAME -u DB_USER -u DB_PASSWORD -u DB_PORT -u PLATFORM_RELATIONSHIPS -u STRIPE_SECRET_KEY \
  FAMTASTIC_PROOF_RUNTIME_READY=1 \
  FAMTASTIC_EMAIL_TRANSPORT=memory \
  FAMTASTIC_DOMAIN_VERIFY_MODE=fixture \
  FAMTASTIC_DEPLOY_TRANSPORT=local \
  FAMTASTIC_HOSTING_BILLING_PROVIDER=memory \
  STRIPE_WEBHOOK_SECRET=whsec_fresh_synthetic_only \
  EVIDENCE_DIR="$evidence_dir/proof-runs" \
  FAMTASTIC_LIFECYCLE_EVIDENCE_ROOT="$evidence_dir/lifecycle-runs" \
  FAMTASTIC_E2E_DIAGNOSTIC_DIR="$evidence_dir/diagnostics" \
  PORT="$server_port" \
  "$runtime_repo/scripts/run-customer-proof-agent.sh" | tee "$evidence_dir/customer-proof.log" || run_status=$?

if [[ "$run_status" -ne 0 ]]; then
  mkdir -p "$evidence_dir/diagnostics"
  env -u DB_HOST -u DB_NAME -u DB_USER -u DB_PASSWORD -u DB_PORT -u PLATFORM_RELATIONSHIPS -u STRIPE_SECRET_KEY \
    "${drush[@]}" php:eval '
      $rows = \Drupal::database()->select("watchdog", "w")->fields("w")->orderBy("wid", "DESC")->range(0, 20)->execute()->fetchAll(\PDO::FETCH_ASSOC);
      foreach ($rows as $row) {
        $row["variables"] = unserialize($row["variables"], ["allowed_classes" => FALSE]);
        print json_encode($row, JSON_UNESCAPED_SLASHES) . PHP_EOL;
      }
    ' > "$evidence_dir/diagnostics/watchdog.jsonl" 2>&1 || true
  echo "ERROR: fresh customer journey failed; sandbox watchdog retained at $evidence_dir/diagnostics/watchdog.jsonl" >&2
  exit "$run_status"
fi

proof_evidence="$(find "$evidence_dir/proof-runs" -name evidence.json -type f -print -quit)"
lifecycle_evidence="$(find "$evidence_dir/lifecycle-runs" -name evidence.json -type f -print -quit)"
test -n "$proof_evidence"
test -n "$lifecycle_evidence"
jq -e '.schema == "famtastic.synthetic-proof.v1" and .status == "passed" and .checks.proofs == 3 and .checks.website_request_commerce_binding == true and .checks.account_proof_selection == true and .checks.portal_ownership == true and ([.checks[]] | all)' "$proof_evidence" >/dev/null
jq -e '.status == "passed" and ([.checks[]] | all)' "$lifecycle_evidence" >/dev/null

jq -n \
  --arg run_id "$run_id" \
  --arg source_sha "$(git -C "$repo_root" rev-parse HEAD)" \
  --arg generated_at "$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
  --arg proof_evidence "${proof_evidence#$evidence_dir/}" \
  --arg lifecycle_evidence "${lifecycle_evidence#$evidence_dir/}" \
  --slurpfile proof "$proof_evidence" \
  --slurpfile lifecycle "$lifecycle_evidence" \
  '{
    schema: "famtastic.fresh-customer-journey.v1",
    status: "passed",
    run_id: $run_id,
    source_sha: $source_sha,
    generated_at: $generated_at,
    safety: {
      drupal_runtime: "fresh_sqlite_sandbox",
      email: "memory",
      payment: "signed_synthetic_webhook",
      domain: "fixture",
      deployment: "local_sandbox",
      provider_calls: false
    },
    assertions: {
      research_and_exactly_three_proofs: ($proof[0].checks.proofs == 3),
      selected_proof_enables_bound_checkout: $proof[0].checks.website_request_commerce_binding,
      account_and_portal_ownership: $proof[0].checks.portal_ownership,
      lifecycle_operations: $lifecycle[0].status == "passed"
    },
    artifacts: {
      customer_journey: $proof_evidence,
      lifecycle: $lifecycle_evidence
    },
    customer_journey: $proof[0],
    lifecycle: $lifecycle[0]
  }' > "$evidence_dir/evidence.json"

echo "PASS: fresh isolated customer journey verified."
echo "Evidence: $evidence_dir/evidence.json"
