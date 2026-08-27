#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
SSH_TARGET="${FAMTASTIC_SSH_TARGET:-xrdj7j99xhzt@p3plzcpnl497512.prod.phx3.secureserver.net}"
REMOTE_ROOT="${FAMTASTIC_REMOTE_ROOT:-public_html}"
REMOTE_DEPLOY_BASE="${FAMTASTIC_REMOTE_DEPLOY_BASE:-deploy/famtastic-designs}"
REPOSITORY_URL="${FAMTASTIC_REPOSITORY_URL:-https://github.com/famtastic-fritz/famtastic-designs.git}"
# A public-preview pilot must never rely on the general lifecycle runner: that
# runner claims every eligible automation job and notification in the shared
# queues.  The normal deployment path retains its existing scheduler behavior;
# an operator must explicitly opt into this narrower release mode.
PILOT_EXACT_DISPATCH_ONLY="${FAMTASTIC_PILOT_EXACT_DISPATCH_ONLY:-0}"
# This is deliberately separate from the exact-dispatch declaration. It is an
# explicit, reversible operations action for the one checked-in cron entry; it
# must never become a broad crontab editor.
PILOT_SUSPEND_MARKED_LIFECYCLE_CRON="${FAMTASTIC_PILOT_SUSPEND_MARKED_LIFECYCLE_CRON:-0}"
APPLY=false

usage() {
  cat <<USAGE
Usage: $0 [--apply]

Without --apply, performs read-only local and remote preflight checks.
With --apply, validates the exact current main commit in a private server
worktree, backs up the database and current custom code, promotes the module and
admin theme, imports the demand-library field configuration, seeds the governed
draft library, runs Drupal database updates and cache rebuild, and records the
release.

Set FAMTASTIC_PILOT_EXACT_DISPATCH_ONLY=1 only for an owner-gated public-preview
pilot. In that mode the deployment refuses to proceed while an active broad
famtastic:lifecycle-run cron entry exists, and it never installs one. Exact
owner-approved preview delivery must use famtastic:preview-delivery-dispatch.

If that known scheduler is active, an authorized apply may suspend only the
marked checked-in entry by also setting
FAMTASTIC_PILOT_SUSPEND_MARKED_LIFECYCLE_CRON=1. Preflight validates the exact
marker/next-line pair first; apply backs up the crontab under the private deploy
directory and refuses any other active lifecycle-run line.
USAGE
}

case "${1:-}" in
  "") ;;
  --apply) APPLY=true ;;
  -h|--help) usage; exit 0 ;;
  *) usage >&2; exit 2 ;;
esac

case "$PILOT_EXACT_DISPATCH_ONLY" in
  0|1) ;;
  *)
    echo "FAMTASTIC_PILOT_EXACT_DISPATCH_ONLY must be 0 or 1." >&2
    exit 2
    ;;
esac

case "$PILOT_SUSPEND_MARKED_LIFECYCLE_CRON" in
  0|1) ;;
  *)
    echo "FAMTASTIC_PILOT_SUSPEND_MARKED_LIFECYCLE_CRON must be 0 or 1." >&2
    exit 2
    ;;
esac

if [[ "$PILOT_SUSPEND_MARKED_LIFECYCLE_CRON" == "1" && "$PILOT_EXACT_DISPATCH_ONLY" != "1" ]]; then
  echo "FAMTASTIC_PILOT_SUSPEND_MARKED_LIFECYCLE_CRON=1 requires FAMTASTIC_PILOT_EXACT_DISPATCH_ONLY=1." >&2
  exit 2
fi

for required_command in git ssh; do
  command -v "$required_command" >/dev/null || {
    echo "Missing required command: $required_command" >&2
    exit 1
  }
done

cd "$REPO_ROOT"
if [[ -n "$(git status --porcelain)" ]]; then
  echo "Refusing deployment from a dirty Git worktree." >&2
  git status --short >&2
  exit 1
fi

COMMIT_SHA="$(git rev-parse HEAD)"
REMOTE_MAIN_SHA="$(git ls-remote "$REPOSITORY_URL" refs/heads/main | awk '{print $1}')"
if [[ "$COMMIT_SHA" != "$REMOTE_MAIN_SHA" ]]; then
  echo "Refusing deployment: local HEAD is not the current origin/main commit." >&2
  echo "local HEAD:  $COMMIT_SHA" >&2
  echo "origin/main: $REMOTE_MAIN_SHA" >&2
  exit 1
fi

echo "Backend deployment candidate: $COMMIT_SHA"
echo "Private validation source:    ~/$REMOTE_DEPLOY_BASE/releases/$COMMIT_SHA/source/backend"
echo "Drupal runtime:               ~/$REMOTE_ROOT"
if [[ "$PILOT_EXACT_DISPATCH_ONLY" == "1" ]]; then
  echo "Dispatch mode:                exact owner-approved preview only (broad lifecycle cron forbidden)"
  if [[ "$PILOT_SUSPEND_MARKED_LIFECYCLE_CRON" == "1" ]]; then
    echo "Scheduler action:             suspend only the known marked lifecycle cron during apply"
  fi
fi

remote_mode="preflight"
[[ "$APPLY" == true ]] && remote_mode="apply"

ssh -T "$SSH_TARGET" bash -s -- \
  "$remote_mode" "$REMOTE_ROOT" "$REMOTE_DEPLOY_BASE" "$REPOSITORY_URL" "$COMMIT_SHA" "$PILOT_EXACT_DISPATCH_ONLY" "$PILOT_SUSPEND_MARKED_LIFECYCLE_CRON" <<'REMOTE'
set -euo pipefail

mode="$1"
remote_root="$2"
deploy_base="$3"
repository_url="$4"
commit_sha="$5"
pilot_exact_dispatch_only="$6"
pilot_suspend_marked_lifecycle_cron="$7"
production_dir="$HOME/$remote_root"
deploy_dir="$HOME/$deploy_base"
mirror_dir="$deploy_dir/repository.git"
release_dir="$deploy_dir/releases/$commit_sha"
source_dir="$release_dir/source"
backend_dir="$source_dir/backend"
source_module="$backend_dir/web/modules/custom/famtastic_pipeline"
production_module="$production_dir/web/modules/custom/famtastic_pipeline"
source_admin_theme="$backend_dir/web/themes/custom/famtastic_admin"
production_admin_theme="$production_dir/web/themes/custom/famtastic_admin"
source_customer_theme="$backend_dir/web/themes/custom/famtastic_customer"
production_customer_theme="$production_dir/web/themes/custom/famtastic_customer"
source_services="$backend_dir/web/sites/default/services.yml"
production_services="$production_dir/web/sites/default/services.yml"
source_product_config="$backend_dir/config/famtastic-products.json"
source_deal_config="$backend_dir/config/famtastic-deal-terms.json"
source_demand_manifest="$backend_dir/config/famtastic-content-series.json"
source_demand_fields="$backend_dir/scripts/install-demand-content-fields.php"
source_demand_seed="$backend_dir/scripts/seed-demand-content.php"
source_package_normalizer="$backend_dir/scripts/normalize-package-ladder.php"
production_config_dir="$production_dir/config"
drush="$production_dir/vendor/bin/drush"

case "$pilot_exact_dispatch_only" in
  0|1) ;;
  *)
    echo "Remote pilot-dispatch mode is invalid." >&2
    exit 2
    ;;
esac
case "$pilot_suspend_marked_lifecycle_cron" in
  0|1) ;;
  *)
    echo "Remote pilot scheduler mode is invalid." >&2
    exit 2
    ;;
esac
if [[ "$pilot_suspend_marked_lifecycle_cron" == "1" && "$pilot_exact_dispatch_only" != "1" ]]; then
  echo "Remote marked scheduler suspension requires exact-dispatch-only mode." >&2
  exit 2
fi

# The general lifecycle runner claims all eligible jobs and notifications. It
# is intentionally prohibited for a controlled exact-ID preview pilot, where
# `famtastic:preview-delivery-dispatch` is the only allowed mail entry point.
# Reading the scheduler is not optional: an unreadable non-empty crontab is
# unsafe because the release cannot prove the broad worker is inactive.
current_crontab=''
lifecycle_cron_backup=''
lifecycle_cron_record=''
scheduler_timestamp=''

load_current_crontab() {
  if ! current_crontab="$(crontab -l 2>&1)"; then
    if ! printf '%s\n' "$current_crontab" | grep -qi 'no crontab'; then
      echo "Pilot exact-dispatch-only deployment refused: unable to inspect the current crontab." >&2
      return 1
    fi
    current_crontab=''
  fi
}

active_global_lifecycle_count() {
  printf '%s\n' "$current_crontab" | awk '
    /^[[:space:]]*#/ { next }
    /famtastic:lifecycle-run/ { count++ }
    END { print count + 0 }
  '
}

assert_no_active_global_lifecycle_cron() {
  load_current_crontab
  if printf '%s\n' "$current_crontab" | awk '
    /^[[:space:]]*#/ { next }
    /famtastic:lifecycle-run/ { found = 1 }
    END { exit found ? 0 : 1 }
  '; then
    echo "Pilot exact-dispatch-only deployment refused: an active broad famtastic:lifecycle-run cron entry exists." >&2
    echo "It can dispatch unrelated queued jobs or notifications. Disable it through an explicitly authorized scheduler change, then rerun this deployment." >&2
    return 1
  fi
  echo "Pilot exact-dispatch-only: no active broad lifecycle cron entry found."
}

# Validates the sole line that the optional pilot suspension is allowed to
# remove. A marker is not enough: the immediately following line must match
# exactly what the ordinary deployment path writes, and no other active broad
# lifecycle entry may exist anywhere in the crontab.
validate_marked_global_lifecycle_cron() {
  local active_count marker_count marker_line active_entry active_line_number active_line expected_line
  load_current_crontab
  active_count="$(active_global_lifecycle_count)"
  if [[ "$active_count" == "0" ]]; then
    return 0
  fi
  if [[ "$active_count" != "1" ]]; then
    echo "Pilot scheduler suspension refused: found $active_count active famtastic:lifecycle-run entries; only one exact marked entry may be suspended." >&2
    return 1
  fi
  cron_marker='# FAMTASTIC_LIFECYCLE_CRON_V1'
  marker_count="$(printf '%s\n' "$current_crontab" | awk -v marker="$cron_marker" '$0 == marker { count++ } END { print count + 0 }')"
  if [[ "$marker_count" != "1" ]]; then
    echo "Pilot scheduler suspension refused: the active lifecycle runner does not have exactly one FAMTASTIC_LIFECYCLE_CRON_V1 marker." >&2
    return 1
  fi
  marker_line="$(printf '%s\n' "$current_crontab" | awk -v marker="$cron_marker" '$0 == marker { print NR; exit }')"
  active_entry="$(printf '%s\n' "$current_crontab" | awk '
    /^[[:space:]]*#/ { next }
    /famtastic:lifecycle-run/ { print NR "\t" $0; exit }
  ')"
  active_line_number="${active_entry%%$'\t'*}"
  active_line="${active_entry#*$'\t'}"
  expected_line="$(printf '*/5 * * * * cd %q && %q famtastic:lifecycle-run --limit=50 >/dev/null 2>&1' "$production_dir" "$drush")"
  if [[ "$active_line_number" != "$((marker_line + 1))" || "$active_line" != "$expected_line" ]]; then
    echo "Pilot scheduler suspension refused: the marker is not followed immediately by the exact checked-in lifecycle command." >&2
    return 1
  fi
}

# This deliberately changes only the exact marker and its immediately following
# exact command after validation. Its backup is retained for an explicitly
# authorized later restore; a failed code deployment must not silently re-enable
# broad dispatch on a shared queue.
suspend_marked_global_lifecycle_cron() {
  local cron_stage cron_backup_dir
  validate_marked_global_lifecycle_cron
  if [[ "$(active_global_lifecycle_count)" == "0" ]]; then
    echo "Pilot exact-dispatch-only: no active broad lifecycle cron entry needed suspension."
    return 0
  fi
  scheduler_timestamp="${scheduler_timestamp:-$(date -u +%Y%m%dT%H%M%SZ)}"
  cron_backup_dir="$deploy_dir/cron-backups"
  lifecycle_cron_backup="$cron_backup_dir/famtastic-crontab-before-pilot-suspension-$scheduler_timestamp-$commit_sha.txt"
  mkdir -p "$cron_backup_dir" "$deploy_dir/tmp"
  if [[ -e "$lifecycle_cron_backup" ]]; then
    echo "Pilot scheduler suspension refused: crontab backup path already exists: $lifecycle_cron_backup" >&2
    return 1
  fi
  (umask 077; printf '%s\n' "$current_crontab" > "$lifecycle_cron_backup")
  test -s "$lifecycle_cron_backup" || {
    echo "Pilot scheduler suspension refused: crontab backup was not written." >&2
    return 1
  }
  cron_stage="$deploy_dir/tmp/famtastic-crontab-pilot-suspended-$scheduler_timestamp"
  if ! printf '%s\n' "$current_crontab" | awk -v marker='# FAMTASTIC_LIFECYCLE_CRON_V1' -v expected="$(printf '*/5 * * * * cd %q && %q famtastic:lifecycle-run --limit=50 >/dev/null 2>&1' "$production_dir" "$drush")" '
    $0 == marker {
      if ((getline next_line) <= 0 || next_line != expected) {
        exit 70
      }
      removed++
      next
    }
    { print }
    END { if (removed != 1) exit 71 }
  ' > "$cron_stage"; then
    rm -f "$cron_stage"
    echo "Pilot scheduler suspension refused: exact marked cron entry changed before it could be removed." >&2
    return 1
  fi
  if ! crontab "$cron_stage"; then
    rm -f "$cron_stage"
    echo "Pilot scheduler suspension failed before the new crontab could be installed; backup retained at $lifecycle_cron_backup." >&2
    return 1
  fi
  rm -f "$cron_stage"
  assert_no_active_global_lifecycle_cron
  echo "Pilot exact-dispatch-only: suspended the marked lifecycle cron; backup: $lifecycle_cron_backup"
}

prepare_pilot_lifecycle_mode() {
  if [[ "$pilot_suspend_marked_lifecycle_cron" == "1" ]]; then
    validate_marked_global_lifecycle_cron
    if [[ "$mode" == "apply" ]]; then
      suspend_marked_global_lifecycle_cron
    elif [[ "$(active_global_lifecycle_count)" == "1" ]]; then
      echo "Pilot exact-dispatch-only preflight: the exact marked lifecycle cron is active and would be suspended during an authorized apply."
    else
      echo "Pilot exact-dispatch-only preflight: no active broad lifecycle cron entry needs suspension."
    fi
    return 0
  fi
  assert_no_active_global_lifecycle_cron
}

for command_name in git php composer tar rsync crontab; do
  command -v "$command_name" >/dev/null || {
    echo "Remote prerequisite missing: $command_name" >&2
    exit 1
  }
done
test -d "$production_dir" || {
  echo "Remote Drupal root missing: $production_dir" >&2
  exit 1
}
test -x "$drush" || {
  echo "Remote Drush missing: $drush" >&2
  exit 1
}
test -d "$production_module" || {
  echo "Production custom module missing: $production_module" >&2
  exit 1
}
test -d "$production_admin_theme" || {
  echo "Production custom admin theme missing: $production_admin_theme" >&2
  exit 1
}
test -f "$production_services" || {
  echo "Production Drupal services file missing: $production_services" >&2
  exit 1
}
remote_sha="$(git ls-remote "$repository_url" refs/heads/main | awk '{print $1}')"
test "$remote_sha" = "$commit_sha" || {
  echo "Remote cannot resolve requested commit as current main." >&2
  exit 1
}

cd "$production_dir"
"$drush" status --fields=bootstrap,db-status,drupal-version --format=list
if [[ "$pilot_exact_dispatch_only" == "1" ]]; then
  prepare_pilot_lifecycle_mode
fi
# A deployment must never land on (or silently leave) a maintenance-mode site.
# Maintenance mode lives in STATE (not config) - Drupal core key.
maint="$("$drush" sget system.maintenance_mode --format=string 2>/dev/null || echo 0)"
if [ "$maint" = "1" ] || [ "$maint" = "true" ]; then
  if [ "$mode" = "apply" ]; then
    "$drush" sset system.maintenance_mode 0 >/dev/null
    echo "Maintenance mode was ON - disabled before deployment."
  else
    echo "WARNING: site is in MAINTENANCE MODE (preflight only - not changed)."
  fi
fi
printf 'Remote PHP: %s\n' "$(php -r 'echo PHP_VERSION;')"
printf 'Free space: %s\n' "$(df -h "$HOME" | awk 'NR == 2 {print $4}')"
printf 'Current backend release: '
if test -f "$production_dir/.backend-release"; then
  tr '\n' ' ' < "$production_dir/.backend-release"
  echo
else
  echo "unrecorded"
fi

if [[ "$mode" == "preflight" ]]; then
  echo "Preflight passed. No production files changed."
  echo "Apply plan: exact Git SHA -> locked Composer validation -> database/code/dependency backups -> dependency and code promotion -> updatedb -> cache rebuild -> release record."
  exit 0
fi

mkdir -p "$deploy_dir/releases" "$deploy_dir/tmp" "$HOME/backups"
if [[ ! -d "$mirror_dir" ]]; then
  git clone --mirror "$repository_url" "$mirror_dir"
else
  git --git-dir="$mirror_dir" remote set-url origin "$repository_url"
  git --git-dir="$mirror_dir" fetch --prune origin
fi
git --git-dir="$mirror_dir" cat-file -e "$commit_sha^{commit}"
resolved_main="$(git --git-dir="$mirror_dir" rev-parse refs/heads/main)"
[[ "$resolved_main" == "$commit_sha" ]] || {
  echo "Refusing deployment: requested commit is no longer current main." >&2
  exit 1
}
if [[ ! -e "$source_dir/.git" ]]; then
  rm -rf "$release_dir"
  mkdir -p "$release_dir"
  git --git-dir="$mirror_dir" worktree add --detach "$source_dir" "$commit_sha"
fi
test -f "$backend_dir/composer.lock"
test -f "$source_module/famtastic_pipeline.info.yml"
test -f "$source_admin_theme/famtastic_admin.info.yml"
test -f "$source_services"
test -f "$source_product_config"
test -f "$source_deal_config"
test -f "$source_demand_manifest"
test -f "$source_demand_fields"
test -f "$source_demand_seed"
TMPDIR="$deploy_dir/tmp" COMPOSER_TEMP_DIR="$deploy_dir/tmp" composer --working-dir="$backend_dir" validate \
  --no-check-publish --no-interaction
TMPDIR="$deploy_dir/tmp" COMPOSER_TEMP_DIR="$deploy_dir/tmp" composer --working-dir="$backend_dir" check-platform-reqs \
  --lock --no-dev
find "$source_module" -type f -name '*.php' -print0 |
  xargs -0 -n1 php -l >/dev/null

timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
module_backup="$HOME/backups/famtastic-pipeline-$timestamp-$commit_sha.tgz"
admin_theme_backup="$HOME/backups/famtastic-admin-$timestamp-$commit_sha.tgz"
customer_theme_backup="$HOME/backups/famtastic-customer-$timestamp-$commit_sha.tgz"
services_backup="$HOME/backups/famtastic-services-$timestamp-$commit_sha.yml"
commercial_config_backup="$HOME/backups/famtastic-commercial-config-$timestamp-$commit_sha.tgz"
commercial_config_backup_stage="$deploy_dir/tmp/commercial-config-$timestamp"
database_backup="$HOME/backups/famtastic-database-$timestamp-$commit_sha.sql.gz"
database_dump_target="${database_backup%.gz}"
dependency_backup="$HOME/backups/famtastic-dependencies-$timestamp-$commit_sha.tgz"
stage_module="$production_dir/web/modules/custom/.famtastic_pipeline-$commit_sha"
stage_admin_theme="$production_dir/web/themes/custom/.famtastic_admin-$commit_sha"
stage_customer_theme="$production_dir/web/themes/custom/.famtastic_customer-$commit_sha"
settings_dir="$production_dir/web/sites/default"
settings_mode="$(stat -c '%a' "$settings_dir")"
stage_services="$production_dir/web/sites/default/.services-$commit_sha.yml"
previous_module="$production_dir/web/modules/custom/.famtastic_pipeline-previous-$timestamp"
previous_admin_theme="$production_dir/web/themes/custom/.famtastic_admin-previous-$timestamp"
previous_customer_theme="$production_dir/web/themes/custom/.famtastic_customer-previous-$timestamp"
previous_services="$production_dir/web/sites/default/.services-previous-$timestamp.yml"

tar -C "$(dirname "$production_module")" -czf "$module_backup" "$(basename "$production_module")"
tar -C "$(dirname "$production_admin_theme")" -czf "$admin_theme_backup" "$(basename "$production_admin_theme")"
tar -C "$(dirname "$production_customer_theme")" -czf "$customer_theme_backup" "$(basename "$production_customer_theme")" 2>/dev/null || true
tar -C "$production_dir" -czf "$dependency_backup" vendor web/core web/modules/contrib composer.json composer.lock
cp -p "$production_services" "$services_backup"
mkdir -p "$production_config_dir"
rm -rf "$commercial_config_backup_stage"
mkdir -p "$commercial_config_backup_stage"
test ! -f "$production_config_dir/famtastic-products.json" || cp -p "$production_config_dir/famtastic-products.json" "$commercial_config_backup_stage/"
test ! -f "$production_config_dir/famtastic-deal-terms.json" || cp -p "$production_config_dir/famtastic-deal-terms.json" "$commercial_config_backup_stage/"
tar -C "$commercial_config_backup_stage" -czf "$commercial_config_backup" .
rm -rf "$commercial_config_backup_stage"
cd "$production_dir"
"$drush" sql:dump --gzip --result-file="$database_dump_target"
test -s "$database_backup" || {
  echo "Database backup was not created at the recorded rollback path: $database_backup" >&2
  exit 1
}

rm -rf "$stage_module"
mkdir -p "$stage_module"
rsync -a "$source_module/" "$stage_module/"
rm -rf "$stage_admin_theme"
mkdir -p "$stage_admin_theme"
rsync -a "$source_admin_theme/" "$stage_admin_theme/"
rsync -a "$source_customer_theme/" "$stage_customer_theme/"
chmod u+w "$settings_dir"
trap 'chmod "$settings_mode" "$settings_dir" 2>/dev/null || true' ERR
install -m 0644 "$source_services" "$stage_services"
mv "$production_module" "$previous_module"
mv "$stage_module" "$production_module"
mv "$production_admin_theme" "$previous_admin_theme" 2>/dev/null || true
mv "$stage_admin_theme" "$production_admin_theme"
mv "$production_customer_theme" "$previous_customer_theme" 2>/dev/null || true
mv "$stage_customer_theme" "$production_customer_theme"
mv "$production_services" "$previous_services"
mv "$stage_services" "$production_services"
install -m 0644 "$source_product_config" "$production_config_dir/famtastic-products.json"
install -m 0644 "$source_deal_config" "$production_config_dir/famtastic-deal-terms.json"
chmod "$settings_mode" "$settings_dir"
trap - ERR

rollback_code() {
  # Failed deploys still leave releases + backups behind; prune to the same
  # retention as success paths or repeated failures re-exhaust quota.
  (
    trap - ERR
    set +e
    cd "$deploy_dir/releases" 2>/dev/null || exit 0
    keep=( "$commit_sha" )
    previous=$(ls -td */ 2>/dev/null | grep -v "^$commit_sha/" | head -1 | tr -d '/')
    [ -n "$previous" ] && keep+=( "$previous" )
    for d in */; do
      sha="${d%/}"
      [[ " ${keep[*]} " == *" $sha "* ]] || rm -rf "$sha"
    done
    cd "$HOME/backups"
    ls -t famtastic-database-*.sql.gz 2>/dev/null | tail -n +3 | xargs -r rm -f 2>/dev/null
    for btype in dependencies module admin_theme services commercial_config; do
      ls -t famtastic-${btype}-*.tgz 2>/dev/null | tail -n +2 | xargs -r rm -f 2>/dev/null
    done
  ) 2>/dev/null || true
  if [[ -d "$previous_module" ]]; then
    chmod u+w "$settings_dir" 2>/dev/null || true
    failed_module="$production_dir/web/modules/custom/.famtastic_pipeline-failed-$timestamp"
    mv "$production_module" "$failed_module" 2>/dev/null || true
    mv "$previous_module" "$production_module"
    if [[ -d "$previous_admin_theme" ]]; then
      failed_admin_theme="$production_dir/web/themes/custom/.famtastic_admin-failed-$timestamp"
      mv "$production_admin_theme" "$failed_admin_theme" 2>/dev/null || true
      mv "$previous_admin_theme" "$production_admin_theme"
    fi
    if [[ -d "$previous_customer_theme" ]]; then
      # Keep the customer-facing theme paired with the restored backend code.
      # A failed promotion must not leave its new portal/proof UI live.
      failed_customer_theme="$production_dir/web/themes/custom/.famtastic_customer-failed-$timestamp"
      mv "$production_customer_theme" "$failed_customer_theme" 2>/dev/null || true
      mv "$previous_customer_theme" "$production_customer_theme"
    fi
    tar -C "$production_dir" -xzf "$dependency_backup" vendor web/core web/modules/contrib composer.json composer.lock 2>/dev/null || true
    if [[ -f "$previous_services" ]]; then
      mv "$production_services" "$production_services.failed-$timestamp" 2>/dev/null || true
      mv "$previous_services" "$production_services"
    fi
    rm -f "$production_config_dir/famtastic-products.json" "$production_config_dir/famtastic-deal-terms.json"
    tar -C "$production_config_dir" -xzf "$commercial_config_backup" 2>/dev/null || true
    chmod "$settings_mode" "$settings_dir" 2>/dev/null || true
    "$drush" cr >/dev/null 2>&1 || true
  fi
  echo "Code was restored after a failed deployment." >&2
  echo "Database backup (manual restore if an update partially ran): $database_backup" >&2
}
trap rollback_code ERR

install -m 0644 "$backend_dir/composer.json" "$production_dir/composer.json"
install -m 0644 "$backend_dir/composer.lock" "$production_dir/composer.lock"
TMPDIR="$deploy_dir/tmp" COMPOSER_TEMP_DIR="$deploy_dir/tmp" composer --working-dir="$production_dir" install \
  --no-dev --no-interaction --prefer-dist --optimize-autoloader
echo "Backend dependencies promoted."

# Retention: releases and per-deploy backups accumulate (~230MB per release,
# ~50MB+ per backup set). Keep the current release plus one rollback, and the
# newest backup of each type (two newest database dumps). Failure-tolerant:
# retention must never abort a deployment.
(
  trap - ERR
  set +e
  cd "$deploy_dir/releases" || exit 0
  keep=( "$commit_sha" )
  previous=$(ls -td */ 2>/dev/null | grep -v "^$commit_sha/" | head -1 | tr -d '/')
  [ -n "$previous" ] && keep+=( "$previous" )
  for d in */; do
    sha="${d%/}"
    [[ " ${keep[*]} " == *" $sha "* ]] || rm -rf "$sha"
  done
  cd "$HOME/backups"
  ls -t famtastic-database-*.sql.gz 2>/dev/null | tail -n +3 | xargs -r rm -f 2>/dev/null
  for btype in dependencies module admin_theme services commercial_config; do
    ls -t famtastic-${btype}-*.tgz 2>/dev/null | tail -n +2 | xargs -r rm -f 2>/dev/null
  done
  echo "Retention applied: releases kept=$(ls "$deploy_dir/releases" | wc -l)."
)
# Drush exits 255 on this cPanel host even when the update run succeeds. Disable
# the rollback trap only while capturing that unreliable status, then restore it
# before the authoritative pending-update check and every remaining apply step.
trap - ERR
set +e
"$drush" updatedb -y --strict=0
updatedb_exit=$?
set -e
trap rollback_code ERR
if [[ "$updatedb_exit" -ne 0 ]]; then
  echo "Database update command returned $updatedb_exit after dependency cold start; verifying authoritative pending-update status."
fi
pending_updates="$($drush updatedb:status --format=json)"
if [[ -n "$pending_updates" && "$pending_updates" != "[]" && "$pending_updates" != "{}" ]]; then
  echo "Database updates remain pending after apply: $pending_updates" >&2
  exit 1
fi
echo "Database updates verified."
"$drush" pm:enable commerce_stripe metatag redirect simple_sitemap key ai ai_dashboard ai_api_explorer ai_agents ai_automators ai_logging ai_provider_openai -y
echo "Required Drupal modules enabled."
"$drush" php:script "$source_demand_fields"
echo "Demand fields verified."
"$drush" php:script "$source_demand_seed"
echo "Demand content verified."
"$drush" php:script "$source_package_normalizer"
echo "Package ladder verified."
# Catalog drift guard: Commerce variations must always match the advertised
# catalog (BRUTAL-REVIEW-2026-08-24 critical #1 - $499 tier was unsellable).
"$drush" php:script "$backend_dir/scripts/assert-catalog-parity.php" "$backend_dir/config/famtastic-products.json"
# Proof artifacts live under the web docroot but must never be directly
# fetchable - the auth-gated API routes are the only reader
# (BRUTAL-REVIEW-2026-08-24 critical #1).
if [ -d "$production_dir/web/proofs" ]; then
  install -m 0644 "$backend_dir/config/proofs-htaccess" "$production_dir/web/proofs/.htaccess"
  echo "Proofs directory direct access denied."
fi
"$drush" cr
# A second process-level rebuild is required on this host after first-time
# module discovery; otherwise the sitemap writer can see stale router state.
"$drush" cr
"$drush" eval '\Drupal::service("router.route_provider")->getRouteByName("simple_sitemap.sitemap_xsl"); print "Sitemap route verified.\n";'
"$drush" simple-sitemap:generate
echo "Sitemap generation verified."
"$drush" eval '
  foreach (["famtastic_prospect", "famtastic_order", "famtastic_intake", "famtastic_project", "proof_campaign", "proof_variant"] as $entity_type_id) {
    \Drupal::entityTypeManager()->getDefinition($entity_type_id);
  }
  print "Pipeline entity definitions verified.\n";
'
"$drush" eval '
  foreach (["key", "ai", "ai_dashboard", "ai_api_explorer", "ai_agents", "ai_automators", "ai_logging", "ai_provider_openai"] as $module) {
    if (!\Drupal::moduleHandler()->moduleExists($module)) {
      throw new \RuntimeException("Required AI foundation module is not enabled: " . $module);
    }
  }
  print "Drupal AI foundation verified.\n";
'

if [[ "$pilot_exact_dispatch_only" == "1" ]]; then
  # Recheck immediately before release recording: another process must not
  # activate the global worker halfway through an exact-ID pilot deployment.
  assert_no_active_global_lifecycle_cron
  lifecycle_cron_record='disabled:pilot-exact-dispatch-only'
  echo "Exact preview pilot: broad lifecycle scheduler remains disabled."
else
  # Ordinary deployments retain the independent lifecycle runner. Mailbox
  # ingestion may fail without suppressing notification dispatch, proof jobs,
  # protection, or heartbeats.
  cron_marker='# FAMTASTIC_LIFECYCLE_CRON_V1'
  cron_stage="$deploy_dir/tmp/famtastic-crontab-$timestamp"
  crontab -l > "$cron_stage" 2>/dev/null || true
  if ! grep -Fq "$cron_marker" "$cron_stage"; then
    {
      printf '\n%s\n' "$cron_marker"
      printf '*/5 * * * * cd %q && %q famtastic:lifecycle-run --limit=50 >/dev/null 2>&1\n' "$production_dir" "$drush"
    } >> "$cron_stage"
    crontab "$cron_stage"
  fi
  rm -f "$cron_stage"
  crontab -l | grep -F "$cron_marker" >/dev/null
  lifecycle_cron_record='FAMTASTIC_LIFECYCLE_CRON_V1'
  echo "Independent lifecycle scheduler verified."
fi

{
  printf 'commit=%s\n' "$commit_sha"
  printf 'deployed_at=%s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  printf 'php=%s\n' "$(php -r 'echo PHP_VERSION;')"
  printf 'module_backup=%s\n' "$module_backup"
  printf 'admin_theme_backup=%s\n' "$admin_theme_backup"
  printf 'customer_theme_backup=%s\n' "$customer_theme_backup"
  printf 'services_backup=%s\n' "$services_backup"
  printf 'database_backup=%s\n' "$database_backup"
  printf 'dependency_backup=%s\n' "$dependency_backup"
  printf 'commercial_config_backup=%s\n' "$commercial_config_backup"
  printf 'demand_manifest_version=2\n'
  printf 'lifecycle_cron=%s\n' "$lifecycle_cron_record"
  printf 'lifecycle_cron_backup=%s\n' "${lifecycle_cron_backup:-none}"
} > "$production_dir/.backend-release"

rm -rf "$previous_module"
rm -rf "$previous_admin_theme"
rm -rf "$previous_customer_theme"
chmod u+w "$settings_dir"
rm -f "$previous_services"
chmod "$settings_mode" "$settings_dir"
trap - ERR
echo "Backend deployment complete."
cat "$production_dir/.backend-release"
REMOTE
