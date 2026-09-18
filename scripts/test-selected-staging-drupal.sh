#!/usr/bin/env bash
# Real installed Drupal/SQLite proof; no shared DB, credentials or providers.
# The selected proof is offline; canonical network access is bounded below.
# FAMTASTIC_BACKEND_VENDOR=/path/to/matching/backend/vendor bash "$0"
# Append --canonical or --phpunit to run those suites in separate fresh runtimes.
# FAMTASTIC_FRONTEND_DEPENDENCIES may name existing frontend modules.
# Canonical defaults offline. Only with explicit authorization, set
# FAMTASTIC_CANONICAL_PUBLIC_CMS_READ=1 for sitemap public JSON:API GETs.
set -euo pipefail

mode="${1:-selected}"
[[ "$mode" == selected || "$mode" == --canonical || "$mode" == --phpunit ]] || { echo 'Usage: test-selected-staging-drupal.sh [--canonical|--phpunit]' >&2; exit 2; }

repo_root="$(cd "$(dirname "$0")/.." && pwd -P)"
vendor_source="${FAMTASTIC_BACKEND_VENDOR:-$repo_root/backend/vendor}"
runtime_backend="$(cd "$vendor_source/.." && pwd -P)"
php_bin="$(command -v "${PHP_BIN:-php}")"
for required in rsync cmp git; do command -v "$required" >/dev/null; done
test -f "$vendor_source/drush/drush/drush.php"
test -f "$runtime_backend/web/core/lib/Drupal.php"
cmp "$repo_root/backend/composer.lock" "$runtime_backend/composer.lock" || {
  echo 'ERROR: supplied runtime composer.lock does not match this worktree.' >&2
  exit 1
}

run_id="$(date -u +%Y%m%dT%H%M%SZ)-$$"
evidence="$repo_root/.artifacts/selected-staging-drupal/$run_id"
# macOS TMPDIR plus a full database filename exceeds Drupal's 128-character
# install-form limit. Use a short, uniquely allocated path and an absolute DB URL.
sandbox="$(mktemp -d /tmp/famtastic-selected-drupal.XXXXXX)"
sandbox="$(cd "$sandbox" && pwd -P)"
mkdir -p "$evidence" "$sandbox/backend/web/sites/default/files" "$sandbox/backend/private" "$sandbox/home" "$sandbox/tmp" "$sandbox/scripts"
cleanup() {
  local result=$?
  trap - EXIT
  if [[ "$result" != 0 ]]; then
    echo "FAIL: retained diagnostics: $evidence" >&2
    tail -n 35 "$evidence/install.log" "$evidence/test.log" "$evidence/canonical.log" 2>/dev/null || true
  fi
  # The exact mktemp directory is the only deletion target, even on failure.
  case "$sandbox" in
    */famtastic-selected-drupal.??????)
      chmod -R u+rwX "$sandbox" 2>/dev/null || true
      rm -rf -- "$sandbox"
      ;;
    *) echo "Refusing unexpected cleanup target: $sandbox" >&2; result=1 ;;
  esac
  exit "$result"
}
trap cleanup EXIT

# Explicit source/runtime allowlist. Never copy sites/, settings.php, .env,
# private files, Drush configuration, source databases, or borrowed custom code.
cp "$repo_root/backend/composer.json" "$repo_root/backend/composer.lock" "$sandbox/backend/"
rsync -a --exclude assets/campaign "$repo_root/backend/web/modules/custom/famtastic_pipeline/" "$sandbox/backend/web/modules/custom/famtastic_pipeline/"
mkdir -p "$sandbox/backend/config"
cp "$repo_root/backend/config/famtastic-products.json" "$repo_root/backend/config/famtastic-deal-terms.json" "$sandbox/backend/config/"
# Drush redispatches through its own vendor directory: never symlink it.
rsync -aL "$vendor_source/" "$sandbox/backend/vendor/"
rsync -a "$runtime_backend/web/core/" "$sandbox/backend/web/core/"
rsync -a "$runtime_backend/web/modules/contrib/" "$sandbox/backend/web/modules/contrib/"
for kind in profiles themes libraries; do
  if [[ -d "$runtime_backend/web/$kind" ]]; then
    rsync -a "$runtime_backend/web/$kind/" "$sandbox/backend/web/$kind/"
  fi
done
for runtime_file in .ht.router.php .htaccess autoload.php autoload_runtime.php index.php robots.txt update.php; do
  if [[ -f "$runtime_backend/web/$runtime_file" ]]; then
    cp "$runtime_backend/web/$runtime_file" "$sandbox/backend/web/$runtime_file"
  fi
done
cp "$sandbox/backend/web/core/assets/scaffold/files/default.settings.php" "$sandbox/backend/web/sites/default/default.settings.php"
rsync -a "$repo_root/scripts/" "$sandbox/scripts/"
rsync -a "$repo_root/backend/tests/" "$sandbox/backend/tests/"
mkdir -p "$sandbox/backend/web/sites/simpletest/browser_output"
# One unit contract inspects this tracked assembler outside the sparse tree.
assembler=website-delivery-swarm/cohorts/beauty-hair-braiding/assemble-verified-cold-callback.mjs
if [[ -f "$repo_root/$assembler" ]]; then
  mkdir -p "$sandbox/$(dirname "$assembler")"
  cp "$repo_root/$assembler" "$sandbox/$assembler"
else
  git -C "$repo_root" archive HEAD "$assembler" | tar -x -C "$sandbox"
fi

# Allowlist the child environment; do not inherit provider credentials or DB
# variables. Disable PHP network transports as a second independent boundary.
isolated=(env -i "PATH=$PATH" "HOME=$sandbox/home" "TMPDIR=$sandbox/tmp" "LANG=C"
  npm_config_update_notifier=false
  "SELECTED_DRUPAL_SANDBOX=$sandbox" "SELECTED_DRUPAL_EVIDENCE=$evidence"
  "SELECTED_DRUPAL_SOURCE_SHA=$(git -C "$repo_root" rev-parse HEAD)"
  FAMTASTIC_TRANSACTIONAL_EMAIL_TRANSPORT=memory FAMTASTIC_EMAIL_TRANSPORT=memory
  "FAMTASTIC_TRANSACTIONAL_EMAIL_CAPTURE=$sandbox/mail.jsonl"
  FAMTASTIC_ALLOW_REAL_OUTREACH=false FAMTASTIC_DEPLOY_TRANSPORT=disabled
  FAMTASTIC_HOSTING_BILLING_PROVIDER=disabled
  SITE_STUDIO_CALLBACK_SECRET=selected-drupal-disposable-secret
  FAMTASTIC_STUDIO_DISPATCH_SECRET=selected-drupal-disposable-artifact-secret
)
php_args=(-d memory_limit=512M -d allow_url_fopen=0
  -d disable_functions=curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_connect,mail
  -d sendmail_path=/usr/bin/false)
drush=("${isolated[@]}" "$php_bin" "${php_args[@]}" "$sandbox/backend/vendor/drush/drush/drush.php" "--root=$sandbox/backend/web" --uri=http://selected-drupal.example.test)

"${isolated[@]}" SELECTED_DRUPAL_PHASE=configure "$php_bin" "${php_args[@]}" "$sandbox/scripts/test-selected-staging-drupal.php"
"${drush[@]}" site:install standard "--db-url=sqlite://localhost/$sandbox/backend/web/sites/default/files/.ht.sqlite" --sites-subdir=default \
  --account-name=admin --account-pass=disposable-only --account-mail=admin@example.test \
  --site-name='Selected staging disposable proof' --site-mail=no-reply@example.test -y >"$evidence/install.log" 2>&1
actual_root="$("${drush[@]}" status --field=root 2>>"$evidence/install.log")"
[[ "$actual_root" == "$sandbox/backend/web" ]] || { echo "ERROR: Drush bootstrapped another root: $actual_root" >&2; exit 1; }
test -s "$sandbox/backend/web/sites/default/files/.ht.sqlite"
"${drush[@]}" en -y famtastic_pipeline >>"$evidence/install.log" 2>&1
if [[ "$mode" == --canonical ]]; then
  # Same untouched canonical runner, with source-only dependencies copied into
  # this disposable repository. No source settings or credentials are imported.
  rsync -a "$repo_root/scripts/" "$sandbox/scripts/"
  rsync -a "$repo_root/backend/scripts/" "$sandbox/backend/scripts/"
  rsync -a "$repo_root/backend/config/" "$sandbox/backend/config/"
  cp "$repo_root/backend/setup-commerce.sh" "$sandbox/backend/"
  rsync -a "$repo_root/docs/architecture/" "$sandbox/docs/architecture/"
  rsync -a --exclude '.env*' --exclude node_modules --exclude dist --exclude public/video --exclude public/showcase "$repo_root/frontend/" "$sandbox/frontend/"
  frontend_dependencies="${FAMTASTIC_FRONTEND_DEPENDENCIES:-$repo_root/frontend/node_modules}"
  if [[ -d "$frontend_dependencies" ]]; then ln -s "$frontend_dependencies" "$sandbox/frontend/node_modules"; fi
  # A private empty Git repository meets the skill entrypoint's path guard.
  git -C "$sandbox" init -q
  "${isolated[@]}" SELECTED_DRUPAL_PHASE=canonical-prepare "$php_bin" "${php_args[@]}" "$sandbox/scripts/test-selected-staging-drupal.php"
  status=0
  "${isolated[@]}" "PATH=$sandbox/bin:$PATH" FAMTASTIC_PROOF_RUNTIME_READY=1 \
    "NODE_OPTIONS=--require=$sandbox/no-network.cjs" \
    "SELECTED_CANONICAL_PUBLIC_CMS_READ=${FAMTASTIC_CANONICAL_PUBLIC_CMS_READ:-0}" \
    FAMTASTIC_DOMAIN_VERIFY_MODE=fixture FAMTASTIC_DEPLOY_TRANSPORT=local FAMTASTIC_HOSTING_BILLING_PROVIDER=memory \
    STRIPE_WEBHOOK_SECRET=whsec_selected_disposable_only \
    "EVIDENCE_DIR=$evidence/canonical-proof-runs" "FAMTASTIC_LIFECYCLE_EVIDENCE_ROOT=$evidence/canonical-lifecycle-runs" \
    "FAMTASTIC_E2E_DIAGNOSTIC_DIR=$evidence/diagnostics" "PORT=$((28900 + ($$ % 300)))" \
    bash "$sandbox/scripts/run-customer-proof-agent.sh" 2>&1 | tee "$evidence/canonical.log" || status=$?
  "${isolated[@]}" SELECTED_DRUPAL_PHASE=canonical-report "SELECTED_CANONICAL_EXIT=$status" \
    "SELECTED_CANONICAL_PUBLIC_CMS_READ=${FAMTASTIC_CANONICAL_PUBLIC_CMS_READ:-0}" \
    "$php_bin" "${php_args[@]}" "$sandbox/scripts/test-selected-staging-drupal.php"
  echo "Canonical runner exit: $status; evidence: $evidence/canonical.json"
  exit "$status"
fi
if [[ "$mode" == --phpunit ]]; then
  unit_status=0
  (
    cd "$sandbox/backend"
    "${isolated[@]}" "$php_bin" "${php_args[@]}" vendor/phpunit/phpunit/phpunit \
      -c web/core/phpunit.xml.dist --log-junit "$evidence/phpunit.xml" web/modules/custom/famtastic_pipeline/tests/src/Unit
  ) >"$evidence/phpunit.log" 2>&1 || unit_status=$?
  tail -n 8 "$evidence/phpunit.log"
  echo "PHPUnit exit: $unit_status; evidence: $evidence/phpunit.xml"
  exit "$unit_status"
fi
"${drush[@]}" php:script "$sandbox/scripts/test-selected-staging-drupal.php" 2>&1 | tee "$evidence/test.log"
# A new PHP process proves durable persistence independently of entity caches.
"${isolated[@]}" SELECTED_DRUPAL_PHASE=verify "$php_bin" "${php_args[@]}" \
  "$sandbox/backend/vendor/drush/drush/drush.php" "--root=$sandbox/backend/web" --uri=http://selected-drupal.example.test \
  php:script "$sandbox/scripts/test-selected-staging-drupal.php" 2>&1 | tee -a "$evidence/test.log"
"$php_bin" -r '$e=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR); if (($e["status"]??"")!=="passed" || count($e["checks"]) < 40 || in_array(false,$e["checks"],true)) exit(1);' "$evidence/evidence.json"
echo "PASS: real installed Drupal selected-staging integration (disposable SQLite; no providers)."
echo "Evidence: $evidence/evidence.json"
