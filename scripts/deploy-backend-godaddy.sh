#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
SSH_TARGET="${FAMTASTIC_SSH_TARGET:-xrdj7j99xhzt@p3plzcpnl497512.prod.phx3.secureserver.net}"
REMOTE_ROOT="${FAMTASTIC_REMOTE_ROOT:-public_html}"
REMOTE_DEPLOY_BASE="${FAMTASTIC_REMOTE_DEPLOY_BASE:-deploy/famtastic-designs}"
REPOSITORY_URL="${FAMTASTIC_REPOSITORY_URL:-https://github.com/famtastic-fritz/famtastic-designs.git}"
APPLY=false

usage() {
  cat <<USAGE
Usage: $0 [--apply]

Without --apply, performs read-only local and remote preflight checks.
With --apply, validates the exact current main commit in a private server
worktree, backs up the database and current custom module, promotes the module,
runs Drupal database updates and cache rebuild, and records the release.
USAGE
}

case "${1:-}" in
  "") ;;
  --apply) APPLY=true ;;
  -h|--help) usage; exit 0 ;;
  *) usage >&2; exit 2 ;;
esac

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

remote_mode="preflight"
[[ "$APPLY" == true ]] && remote_mode="apply"

ssh -T "$SSH_TARGET" bash -s -- \
  "$remote_mode" "$REMOTE_ROOT" "$REMOTE_DEPLOY_BASE" "$REPOSITORY_URL" "$COMMIT_SHA" <<'REMOTE'
set -euo pipefail

mode="$1"
remote_root="$2"
deploy_base="$3"
repository_url="$4"
commit_sha="$5"
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
source_services="$backend_dir/web/sites/default/services.yml"
production_services="$production_dir/web/sites/default/services.yml"
source_product_config="$backend_dir/config/famtastic-products.json"
source_deal_config="$backend_dir/config/famtastic-deal-terms.json"
production_config_dir="$production_dir/config"
drush="$production_dir/vendor/bin/drush"

for command_name in git php composer tar rsync; do
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
TMPDIR="$deploy_dir/tmp" COMPOSER_TEMP_DIR="$deploy_dir/tmp" composer --working-dir="$backend_dir" validate \
  --no-check-publish --no-interaction
TMPDIR="$deploy_dir/tmp" COMPOSER_TEMP_DIR="$deploy_dir/tmp" composer --working-dir="$backend_dir" check-platform-reqs \
  --lock --no-dev
find "$source_module" -type f -name '*.php' -print0 |
  xargs -0 -n1 php -l >/dev/null

timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
module_backup="$HOME/backups/famtastic-pipeline-$timestamp-$commit_sha.tgz"
admin_theme_backup="$HOME/backups/famtastic-admin-$timestamp-$commit_sha.tgz"
services_backup="$HOME/backups/famtastic-services-$timestamp-$commit_sha.yml"
commercial_config_backup="$HOME/backups/famtastic-commercial-config-$timestamp-$commit_sha.tgz"
commercial_config_backup_stage="$deploy_dir/tmp/commercial-config-$timestamp"
database_backup="$HOME/backups/famtastic-database-$timestamp-$commit_sha.sql.gz"
database_dump_target="${database_backup%.gz}"
dependency_backup="$HOME/backups/famtastic-dependencies-$timestamp-$commit_sha.tgz"
stage_module="$production_dir/web/modules/custom/.famtastic_pipeline-$commit_sha"
stage_admin_theme="$production_dir/web/themes/custom/.famtastic_admin-$commit_sha"
settings_dir="$production_dir/web/sites/default"
settings_mode="$(stat -c '%a' "$settings_dir")"
stage_services="$production_dir/web/sites/default/.services-$commit_sha.yml"
previous_module="$production_dir/web/modules/custom/.famtastic_pipeline-previous-$timestamp"
previous_admin_theme="$production_dir/web/themes/custom/.famtastic_admin-previous-$timestamp"
previous_services="$production_dir/web/sites/default/.services-previous-$timestamp.yml"

tar -C "$(dirname "$production_module")" -czf "$module_backup" "$(basename "$production_module")"
tar -C "$(dirname "$production_admin_theme")" -czf "$admin_theme_backup" "$(basename "$production_admin_theme")"
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
chmod u+w "$settings_dir"
trap 'chmod "$settings_mode" "$settings_dir" 2>/dev/null || true' ERR
install -m 0644 "$source_services" "$stage_services"
mv "$production_module" "$previous_module"
mv "$stage_module" "$production_module"
mv "$production_admin_theme" "$previous_admin_theme"
mv "$stage_admin_theme" "$production_admin_theme"
mv "$production_services" "$previous_services"
mv "$stage_services" "$production_services"
install -m 0644 "$source_product_config" "$production_config_dir/famtastic-products.json"
install -m 0644 "$source_deal_config" "$production_config_dir/famtastic-deal-terms.json"
chmod "$settings_mode" "$settings_dir"
trap - ERR

rollback_code() {
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
"$drush" updatedb -y
"$drush" pm:enable commerce_stripe metatag redirect simple_sitemap -y
"$drush" cr
# A second process-level rebuild is required on this host after first-time
# module discovery; otherwise the sitemap writer can see stale router state.
"$drush" cr
"$drush" eval '\Drupal::service("router.route_provider")->getRouteByName("simple_sitemap.sitemap_xsl"); print "Sitemap route verified.\n";'
"$drush" simple-sitemap:generate
"$drush" eval '
  foreach (["famtastic_prospect", "famtastic_order", "famtastic_intake", "famtastic_project", "proof_campaign", "proof_variant"] as $entity_type_id) {
    \Drupal::entityTypeManager()->getDefinition($entity_type_id);
  }
  print "Pipeline entity definitions verified.\n";
'

{
  printf 'commit=%s\n' "$commit_sha"
  printf 'deployed_at=%s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  printf 'php=%s\n' "$(php -r 'echo PHP_VERSION;')"
  printf 'module_backup=%s\n' "$module_backup"
  printf 'admin_theme_backup=%s\n' "$admin_theme_backup"
  printf 'services_backup=%s\n' "$services_backup"
  printf 'database_backup=%s\n' "$database_backup"
  printf 'dependency_backup=%s\n' "$dependency_backup"
  printf 'commercial_config_backup=%s\n' "$commercial_config_backup"
} > "$production_dir/.backend-release"

rm -rf "$previous_module"
rm -rf "$previous_admin_theme"
chmod u+w "$settings_dir"
rm -f "$previous_services"
chmod "$settings_mode" "$settings_dir"
trap - ERR
echo "Backend deployment complete."
cat "$production_dir/.backend-release"
REMOTE
