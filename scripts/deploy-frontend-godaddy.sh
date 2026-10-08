#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
SSH_TARGET="${FAMTASTIC_SSH_TARGET:-xrdj7j99xhzt@p3plzcpnl497512.prod.phx3.secureserver.net}"
REMOTE_ROOT="${FAMTASTIC_REMOTE_ROOT:-public_html}"
REMOTE_DEPLOY_BASE="${FAMTASTIC_REMOTE_DEPLOY_BASE:-deploy/famtastic-designs}"
REPOSITORY_URL="${FAMTASTIC_REPOSITORY_URL:-https://github.com/famtastic-fritz/famtastic-designs.git}"
APPLY=false
CREATOR_CREDIT_ONLY="${FAMTASTIC_CREATOR_CREDIT_ONLY:-0}"
PREPARED_DEPENDENCIES_LOCK_SHA256="${FAMTASTIC_PREPARED_DEPENDENCIES_LOCK_SHA256:-}"
PREBUILT_DIST_MANIFEST_SHA256="${FAMTASTIC_PREBUILT_DIST_MANIFEST_SHA256:-}"
[[ "$CREATOR_CREDIT_ONLY" == 0 || "$CREATOR_CREDIT_ONLY" == 1 ]] || exit 2
[[ -z "$PREPARED_DEPENDENCIES_LOCK_SHA256" || "$PREPARED_DEPENDENCIES_LOCK_SHA256" =~ ^[a-f0-9]{64}$ ]] || exit 2
[[ -z "$PREBUILT_DIST_MANIFEST_SHA256" || "$PREBUILT_DIST_MANIFEST_SHA256" =~ ^[a-f0-9]{64}$ ]] || exit 2
PREPARED_DEPENDENCIES_ARG="${PREPARED_DEPENDENCIES_LOCK_SHA256:-_}"
PREBUILT_DIST_ARG="${PREBUILT_DIST_MANIFEST_SHA256:-_}"

usage() {
  cat <<USAGE
Usage: $0 [--apply]

Without --apply, performs read-only local and remote preflight checks.
With --apply, builds the exact Git commit on the server, backs up the current
frontend, promotes the validated artifact, and verifies live asset MIME types.

On a constrained host, FAMTASTIC_PREPARED_DEPENDENCIES_LOCK_SHA256 may name the
exact frontend/package-lock.json hash after a separate private `npm ci`. Apply
then verifies the complete installed tree before building and still removes it.
FAMTASTIC_PREBUILT_DIST_MANIFEST_SHA256 may resume a server build that finished
before promotion; apply recomputes the complete relative-path/file hash ledger.
USAGE
}

case "${1:-}" in
  "") ;;
  --apply) APPLY=true ;;
  -h|--help) usage; exit 0 ;;
  *) usage >&2; exit 2 ;;
esac

for required_command in git ssh curl; do
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

echo "Deployment candidate: $COMMIT_SHA"
echo "Build location:       ~/$REMOTE_DEPLOY_BASE/releases/$COMMIT_SHA/frontend-source"
echo "Document root:        ~/$REMOTE_ROOT"

if [[ "$APPLY" != true ]]; then
  ssh -T "$SSH_TARGET" bash -s -- \
    "$REMOTE_ROOT" "$REMOTE_DEPLOY_BASE" "$REPOSITORY_URL" "$COMMIT_SHA" <<'REMOTE_PREFLIGHT'
set -euo pipefail
remote_root="$1"
deploy_base="$2"
repository_url="$3"
commit_sha="$4"

for command_name in git npm node rsync tar curl; do
  command -v "$command_name" >/dev/null || {
    echo "Remote prerequisite missing: $command_name" >&2
    exit 1
  }
done
test -r "$HOME/.nvm/nvm.sh" || {
  echo "Remote prerequisite missing: ~/.nvm/nvm.sh" >&2
  exit 1
}
test -d "$HOME/$remote_root" || {
  echo "Remote document root missing: ~/$remote_root" >&2
  exit 1
}
remote_sha="$(git ls-remote "$repository_url" refs/heads/main | awk '{print $1}')"
test "$remote_sha" = "$commit_sha" || {
  echo "Remote cannot resolve requested commit as current main." >&2
  exit 1
}
printf 'Remote Node: %s\n' "$(node --version)"
printf 'Remote npm:  %s\n' "$(npm --version)"
printf 'Free space:  %s\n' "$(df -h "$HOME" | awk 'NR == 2 {print $4}')"
printf 'Current release: '
if test -f "$HOME/$remote_root/.frontend-release"; then
  tr '\n' ' ' < "$HOME/$remote_root/.frontend-release"
  echo
else
  echo "unrecorded"
fi
echo "Preflight passed. No production files changed."
echo "Apply plan: private exact-SHA archive -> pinned Node build -> backup -> assets and route shells first -> root index.html last."
REMOTE_PREFLIGHT
  exit 0
fi

ssh -T "$SSH_TARGET" bash -s -- \
  "$REMOTE_ROOT" "$REMOTE_DEPLOY_BASE" "$REPOSITORY_URL" "$COMMIT_SHA" "$CREATOR_CREDIT_ONLY" "$PREPARED_DEPENDENCIES_ARG" "$PREBUILT_DIST_ARG" <<'REMOTE_APPLY'
set -euo pipefail
remote_apply() {
remote_root="$1"
deploy_base="$2"
repository_url="$3"
commit_sha="$4"
creator_credit_only="$5"
prepared_dependencies_lock_sha256="$6"
prebuilt_dist_manifest_sha256="$7"
[[ "$prepared_dependencies_lock_sha256" != _ ]] || prepared_dependencies_lock_sha256=''
[[ "$prebuilt_dist_manifest_sha256" != _ ]] || prebuilt_dist_manifest_sha256=''
[[ -z "$prebuilt_dist_manifest_sha256" || "$creator_credit_only" == 0 ]] || {
  echo "A prebuilt full frontend cannot be used for a creator-credit-only release." >&2
  exit 2
}
deploy_dir="$HOME/$deploy_base"
mirror_dir="$deploy_dir/repository.git"
release_dir="$deploy_dir/releases/$commit_sha"
source_dir="$release_dir/frontend-source"
frontend_dir="$source_dir/frontend"
dist_dir="$frontend_dir/dist"
production_dir="$HOME/$remote_root"
backup_dir="$HOME/backups"
timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
backup_path="$backup_dir/famtastic-frontend-$timestamp-$commit_sha.tgz"

# Frontend archives are code-only copies. Keep two recent attempts and the
# rollback archive named by the live receipt, even after repeated failures.
prune_frontend_backups() (
  trap - ERR
  set +e
  local receipt='' file i
  local -a files=()
  if [[ -f "$production_dir/.frontend-release" ]]; then
    receipt="$(sed -n 's/^backup=//p' "$production_dir/.frontend-release" | tail -1)"
  fi
  for file in "$backup_dir"/famtastic-frontend-*.tgz; do
    [[ -f "$file" && ! -L "$file" ]] && files+=( "$file" )
  done
  for (( i=0; i<${#files[@]}-2; i++ )); do
    [[ "${files[i]}" == "$receipt" ]] || rm -f -- "${files[i]}"
  done
)

# Dependencies are reproducible build inputs, not release artifacts. Always
# remove them when this remote apply exits, including after an interrupted or
# failed npm install, so per-account hosting quotas do not grow by roughly one
# node_modules tree for every commit deployed.
cleanup_build_dependencies() {
  prune_frontend_backups || true
  dependencies_dir="$frontend_dir/node_modules"
  expected_dependencies_dir="$HOME/$deploy_base/releases/$commit_sha/frontend-source/frontend/node_modules"
  [[ "$dependencies_dir" == "$expected_dependencies_dir" ]] || {
    echo "Refusing unexpected dependency cleanup target: $dependencies_dir" >&2
    return 1
  }
  rm -rf -- "$dependencies_dir"
}
trap cleanup_build_dependencies EXIT

mkdir -p "$deploy_dir/releases" "$backup_dir"
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

if [[ ! -f "$source_dir/commit.txt" ]] || ! grep -qx "$commit_sha" "$source_dir/commit.txt" || [[ ! -f "$source_dir/.nvmrc" ]] || [[ "$creator_credit_only" == 1 && ! -f "$source_dir/backend/web/modules/custom/famtastic_pipeline/famtastic_pipeline.info.yml" ]]; then
  rm -rf -- "$source_dir"
  mkdir -p "$source_dir"
  # GoDaddy may clear sparse worktrees between commands. Materialize only the
  # exact committed frontend inputs in this lane's normal private directory.
  # The checked mirror SHA remains authoritative; never copy working-tree or
  # generated content, or remove the backend source/other release artifacts.
  source_paths=(.nvmrc frontend scripts marketing/brands/famtastic/video-studio/whats-the-catch)
  [[ "$creator_credit_only" != 1 ]] || source_paths+=(backend/web/modules/custom/famtastic_pipeline)
  git --git-dir="$mirror_dir" archive "$commit_sha" "${source_paths[@]}" | tar -x -C "$source_dir"
  printf '%s\n' "$commit_sha" > "$source_dir/commit.txt"
fi
grep -qx "$commit_sha" "$source_dir/commit.txt"
test -f "$frontend_dir/package-lock.json"
test -f "$source_dir/scripts/creator-credit.mjs"

cd "$source_dir"
[[ -f .nvmrc ]] || {
  echo "Release is missing the repository .nvmrc runtime pin." >&2
  exit 1
}
# nvm is a shell function and must be loaded explicitly in noninteractive SSH.
export NVM_DIR="$HOME/.nvm"
requested_node="$(tr -d '[:space:]' < .nvmrc)"
installed_node_dir=''
if [[ "$requested_node" =~ ^[0-9]+$ ]]; then
  installed_node_dir="$(find "$NVM_DIR/versions/node" -mindepth 1 -maxdepth 1 -type d -name "v${requested_node}.*" -print 2>/dev/null | sort -V | tail -1)"
fi
if [[ -n "$installed_node_dir" && -x "$installed_node_dir/bin/node" && -x "$installed_node_dir/bin/npm" ]]; then
  export PATH="$installed_node_dir/bin:$PATH"
  [[ "$(node --version)" == "v${requested_node}."* ]]
  echo "Using installed Node $(node --version) from the .nvmrc major pin."
else
  # shellcheck disable=SC1090
  set +u
  . "$NVM_DIR/nvm.sh"
  nvm install
  nvm use
  set -u
fi

if [[ -n "$prebuilt_dist_manifest_sha256" ]]; then
  test -f "$dist_dir/index.html"
  actual_prebuilt_dist_manifest_sha256="$(cd "$dist_dir" && find . -type f -print0 | sort -z | xargs -0 sha256sum | sha256sum | awk '{print $1}')"
  [[ "$actual_prebuilt_dist_manifest_sha256" == "$prebuilt_dist_manifest_sha256" ]] || {
    echo "Prebuilt distribution manifest does not match the approved recovery hash." >&2
    exit 1
  }
  echo "Verified prebuilt distribution manifest $actual_prebuilt_dist_manifest_sha256."
else
  # The shared host can expose far more CPUs than this account's resource budget.
  # Bound both native worker pools and V8; resource limits affect build scheduling,
  # not authored inputs, runtime code, or publication validation.
  export RAYON_NUM_THREADS=2
  export TOKIO_WORKER_THREADS=2
  export NODE_OPTIONS="${NODE_OPTIONS:+$NODE_OPTIONS }--max-old-space-size=512"

  # The host can export production-mode npm configuration. The frontend build is
  # a release-time operation and Vite lives in devDependencies, so explicitly
  # retain build tooling instead of relying on the server environment.
  actual_lock_sha256="$(sha256sum "$frontend_dir/package-lock.json" | awk '{print $1}')"
  if [[ -n "$prepared_dependencies_lock_sha256" ]]; then
    [[ "$actual_lock_sha256" == "$prepared_dependencies_lock_sha256" ]] || {
      echo "Prepared dependency lock hash does not match this release." >&2
      exit 1
    }
    test -f "$frontend_dir/node_modules/.package-lock.json"
    npm --prefix "$frontend_dir" ls --include=dev --all >/dev/null
    echo "Verified prepared dependencies for lock $actual_lock_sha256."
  else
    npm --prefix "$frontend_dir" ci --include=dev
  fi
  npm --prefix "$frontend_dir" run build
fi

[[ -f "$dist_dir/index.html" ]] || {
  echo "Build rejected: frontend/dist/index.html is missing." >&2
  exit 1
}
if grep -qE '(src|href)="/src/' "$dist_dir/index.html"; then
  echo "Build rejected: index.html contains a raw /src/ reference." >&2
  exit 1
fi

asset_manifest="$release_dir/referenced-assets.txt"
grep -oE '(src|href)="/assets/[^"]+"' "$dist_dir/index.html" |
  sed -E 's/^(src|href)="\/(assets\/[^"]+)"$/\2/' > "$asset_manifest"
[[ -s "$asset_manifest" ]] || {
  echo "Build rejected: index.html references no compiled assets." >&2
  exit 1
}
while IFS= read -r asset_path; do
  [[ -f "$dist_dir/$asset_path" ]] || {
    echo "Build rejected: missing frontend/dist/$asset_path" >&2
    exit 1
  }
done < "$asset_manifest"

if [[ "$creator_credit_only" == 1 ]]; then
  node "$source_dir/scripts/deploy-creator-credit-existing.mjs" "$dist_dir" "$production_dir" "$release_dir" "$commit_sha"
  return
fi

# Fail before public promotion if a release-bound sender would become stale.
# This gate never signs input, changes cron, or dispatches a message.
/usr/local/bin/php "$source_dir/scripts/acquisition-frontend-release-guard.php" "$HOME" "$commit_sha"

backup_items=()
[[ -e "$production_dir/index.html" ]] && backup_items+=("index.html")
[[ -e "$production_dir/assets" ]] && backup_items+=("assets")
[[ -e "$production_dir/.htaccess" ]] && backup_items+=(".htaccess")
[[ -e "$production_dir/.frontend-release" ]] && backup_items+=(".frontend-release")
if [[ "${#backup_items[@]}" -gt 0 ]]; then
  tar -C "$production_dir" -czf "$backup_path" "${backup_items[@]}"
else
  tar -czf "$backup_path" --files-from /dev/null
fi

# Promote versioned assets and other public files before changing index.html.
# Never use --delete: public_html also contains Drupal and hosting runtime files.
mkdir -p "$production_dir/assets"
rsync -a "$dist_dir/assets/" "$production_dir/assets/"
# Anchor exclusions at the dist root. Route-specific SEO shells such as
# contact/index.html must be promoted; excluding every basename index.html
# leaves those routes loading stale JavaScript from an older release.
rsync -a --exclude='/index.html' --exclude='/assets/' "$dist_dir/" "$production_dir/"
install -m 0644 "$dist_dir/index.html" "$production_dir/index.html"
{
  printf 'commit=%s\n' "$commit_sha"
  printf 'deployed_at=%s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  printf 'node=%s\n' "$(node --version)"
  printf 'backup=%s\n' "$backup_path"
} > "$production_dir/.frontend-release"

route_shell_manifest="$release_dir/route-shells.txt"
find "$dist_dir" -mindepth 2 -type f -name index.html -print > "$route_shell_manifest"
[[ -s "$route_shell_manifest" ]] || {
  echo "Verification failed: build contains no route-specific SEO shells." >&2
  exit 1
}
while IFS= read -r route_shell; do
  relative_shell="${route_shell#"$dist_dir/"}"
  cmp -s "$route_shell" "$production_dir/$relative_shell" || {
    echo "Verification failed: route shell $relative_shell was not promoted exactly." >&2
    exit 1
  }
done < "$route_shell_manifest"
echo "Verified $(wc -l < "$route_shell_manifest" | tr -d ' ') route-specific SEO shell(s)."

cmp -s "$dist_dir/.htaccess" "$production_dir/.htaccess" || {
  echo "Verification failed: root .htaccess was not promoted exactly." >&2
  exit 1
}
echo "Verified root .htaccess."

while IFS= read -r asset_path; do
  live_url="https://famtasticdesigns.com/$asset_path"
  headers="$(curl -fsSI "$live_url")"
  content_type="$(
    printf '%s\n' "$headers" |
      awk -F': *' 'tolower($1) == "content-type" {print tolower($2)}' |
      tr -d '\r' |
      tail -1
  )"
  case "$asset_path" in
    *.js) [[ "$content_type" == *javascript* ]] ;;
    *.css) [[ "$content_type" == text/css* ]] ;;
  esac || {
    echo "Verification failed: $live_url returned $content_type" >&2
    exit 1
  }
done < "$asset_manifest"

echo "Deployment complete."
echo "Commit: $commit_sha"
echo "Node: $(node --version)"
echo "Backup: $backup_path"
}
remote_apply "$@"
REMOTE_APPLY

if [[ "$CREATOR_CREDIT_ONLY" == 1 ]]; then
  ssh -T "$SSH_TARGET" "cat ~/$REMOTE_ROOT/.creator-credit-release.json"
  exit 0
fi

DEPLOYED_COMMIT="$(
  ssh -T "$SSH_TARGET" \
    "sed -n 's/^commit=//p' ~/$REMOTE_ROOT/.frontend-release"
)"
if [[ "$DEPLOYED_COMMIT" != "$COMMIT_SHA" ]]; then
  echo "Deployment verification failed: production release record does not match." >&2
  echo "expected: $COMMIT_SHA" >&2
  echo "recorded: ${DEPLOYED_COMMIT:-missing}" >&2
  exit 1
fi

echo "Server-side deployment completed for $COMMIT_SHA."
echo "Complete real-browser acceptance for apex and www before closing the deployment."
