#!/usr/bin/env bash
# Real installed Drupal/SQLite proof; no shared DB, credentials or providers.
# FAMTASTIC_BACKEND_VENDOR=/path/to/backend/vendor bash "$0"
# Optional ACQUISITION_INSTALL_LOCKED_DEPENDENCIES=1 installs only the task lock
# in the copied fixture when the borrowed runtime differs. No scripts run.
# ACQUISITION_KEEP_SANDBOX=1 retains private .test browser fixture after success.
# ACQUISITION_GENERIC_PROOF=1 also exports the supplied-context candidate journey.
# ACQUISITION_GENERIC_D0_PROOF=1 + ACQUISITION_GENERIC_D0_BUNDLE=/private/candidate/bundle
# proves D0 source against private bundle only (synthetic data, no cohort export).
set -euo pipefail


repo_root="$(cd "$(dirname "$0")/.." && pwd -P)"
vendor_source="${FAMTASTIC_BACKEND_VENDOR:-$repo_root/backend/vendor}"
runtime_backend="$(cd "$vendor_source/.." && pwd -P)"
php_bin="$(command -v "${PHP_BIN:-php}")"
for required in rsync cmp git; do command -v "$required" >/dev/null; done
test -f "$vendor_source/drush/drush/drush.php"
test -f "$runtime_backend/web/core/lib/Drupal.php"
runtime_lock_matches=1
if ! cmp -s "$repo_root/backend/composer.lock" "$runtime_backend/composer.lock"; then
  runtime_lock_matches=0
  [[ "${ACQUISITION_INSTALL_LOCKED_DEPENDENCIES:-0}" == 1 ]] || { echo 'Borrowed runtime lock differs; explicit copied-runtime dependency install option required.' >&2; exit 1; }
fi

run_id="$(date -u +%Y%m%dT%H%M%SZ)-$$"
evidence="$repo_root/.artifacts/acquisition-sample-drupal/$run_id"
# macOS TMPDIR plus a full database filename exceeds Drupal's 128-character
# install-form limit. Use a short, uniquely allocated path and an absolute DB URL.
sandbox="$(mktemp -d /tmp/famtastic-acquisition-drupal.XXXXXX)"
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
  if [[ "${ACQUISITION_KEEP_SANDBOX:-0}" == 1 ]]; then
    echo "Retained private synthetic fixture: $sandbox"
    exit "$result"
  fi
  case "$sandbox" in
    */famtastic-acquisition-drupal.??????)
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
mkdir -p "$sandbox/marketing/campaigns/acquisition-199"
rsync -a "$repo_root/marketing/campaigns/acquisition-199/" "$sandbox/marketing/campaigns/acquisition-199/"
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
if [[ "$runtime_lock_matches" == 0 ]]; then
  composer_bin="$(command -v composer)"
  (cd "$sandbox/backend" && env -i "PATH=$PATH" "HOME=$sandbox/home" "TMPDIR=$sandbox/tmp" "LANG=C" COMPOSER_CACHE_DIR="$sandbox/composer-cache" "$php_bin" "$composer_bin" install --no-interaction --prefer-dist --no-scripts) >"$evidence/dependency-install.log" 2>&1
fi
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
  "ACQUISITION_DRUPAL_SANDBOX=$sandbox" "ACQUISITION_DRUPAL_EVIDENCE=$evidence"
  "ACQUISITION_DRUPAL_SOURCE_SHA=$(git -C "$repo_root" rev-parse HEAD)"
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
drush=("${isolated[@]}" "$php_bin" "${php_args[@]}" "$sandbox/backend/vendor/drush/drush/drush.php" "--root=$sandbox/backend/web" --uri=http://acquisition-drupal.example.test)

"${isolated[@]}" ACQUISITION_DRUPAL_PHASE=configure "$php_bin" "${php_args[@]}" "$sandbox/scripts/acquisition-sample-drupal.php"
"${drush[@]}" site:install standard "--db-url=sqlite://localhost/$sandbox/backend/web/sites/default/files/.ht.sqlite" --sites-subdir=default \
  --account-name=admin --account-pass=disposable-only --account-mail=admin@example.test \
  --site-name='Selected staging disposable proof' --site-mail=no-reply@example.test -y >"$evidence/install.log" 2>&1
actual_root="$("${drush[@]}" status --field=root 2>>"$evidence/install.log")"
[[ "$actual_root" == "$sandbox/backend/web" ]] || { echo "ERROR: Drush bootstrapped another root: $actual_root" >&2; exit 1; }
test -s "$sandbox/backend/web/sites/default/files/.ht.sqlite"
"${drush[@]}" en -y famtastic_pipeline >>"$evidence/install.log" 2>&1
"${drush[@]}" php:script "$sandbox/scripts/acquisition-sample-drupal.php" >"$evidence/test.log" 2>&1
mkdir -p "$evidence/supplemental"
"${isolated[@]}" "ACQUISITION_DRUPAL_EVIDENCE=$evidence/supplemental" ACQUISITION_DRUPAL_PHASE=supplemental "$php_bin" "${php_args[@]}" "$sandbox/backend/vendor/drush/drush/drush.php" "--root=$sandbox/backend/web" --uri=http://acquisition-drupal.example.test php:script "$sandbox/scripts/acquisition-sample-drupal.php" >"$evidence/supplemental.log" 2>&1
if [[ "${ACQUISITION_GENERIC_PROOF:-0}" == 1 ]]; then
  mkdir -p "$evidence/generic"
  "${isolated[@]}" "ACQUISITION_DRUPAL_EVIDENCE=$evidence/generic" "ACQUISITION_GENERIC_EXPORT=$evidence/generic" ACQUISITION_DRUPAL_PHASE=generic "$php_bin" "${php_args[@]}" "$sandbox/backend/vendor/drush/drush/drush.php" "--root=$sandbox/backend/web" --uri=http://acquisition-drupal.example.test php:script "$sandbox/scripts/acquisition-sample-drupal.php" >"$evidence/generic.log" 2>&1
  cat "$evidence/generic.log"
fi
if [[ "${ACQUISITION_GENERIC_D0_PROOF:-0}" == 1 ]]; then
  test -f "${ACQUISITION_GENERIC_D0_BUNDLE:?Explicit reviewed candidate bundle required}/manifest.json"
  mkdir -p "$sandbox/backend/private/generic-d0" "$evidence/generic-d0"
  rsync -a "$ACQUISITION_GENERIC_D0_BUNDLE/" "$sandbox/backend/private/generic-d0/"
  "${isolated[@]}" "ACQUISITION_DRUPAL_EVIDENCE=$evidence/generic-d0" ACQUISITION_DRUPAL_PHASE=generic_delivery "$php_bin" "${php_args[@]}" "$sandbox/backend/vendor/drush/drush/drush.php" "--root=$sandbox/backend/web" --uri=http://acquisition-drupal.example.test php:script "$sandbox/scripts/acquisition-sample-drupal.php" >"$evidence/generic-d0.log" 2>&1
  cat "$evidence/generic-d0.log"
fi
cat "$evidence/test.log" "$evidence/supplemental.log"
echo "Evidence: $evidence/evidence.json"
echo "Fixture: $sandbox/fixture.json"
