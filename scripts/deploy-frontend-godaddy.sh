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
With --apply, builds the exact Git commit on the server, backs up the current
frontend, promotes the validated artifact, and verifies live asset MIME types.
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
echo "Build location:       ~/$REMOTE_DEPLOY_BASE/releases/$COMMIT_SHA/source"
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
echo "Apply plan: private Git worktree -> pinned Node build -> backup -> assets and route shells first -> root index.html last."
REMOTE_PREFLIGHT
  exit 0
fi

ssh -T "$SSH_TARGET" bash -s -- \
  "$REMOTE_ROOT" "$REMOTE_DEPLOY_BASE" "$REPOSITORY_URL" "$COMMIT_SHA" <<'REMOTE_APPLY'
set -euo pipefail
remote_apply() {
remote_root="$1"
deploy_base="$2"
repository_url="$3"
commit_sha="$4"
deploy_dir="$HOME/$deploy_base"
mirror_dir="$deploy_dir/repository.git"
release_dir="$deploy_dir/releases/$commit_sha"
source_dir="$release_dir/source"
frontend_dir="$source_dir/frontend"
dist_dir="$frontend_dir/dist"
production_dir="$HOME/$remote_root"
backup_dir="$HOME/backups"
timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
backup_path="$backup_dir/famtastic-frontend-$timestamp-$commit_sha.tgz"

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

if [[ ! -d "$source_dir/.git" && ! -f "$source_dir/.git" ]]; then
  rm -rf "$release_dir"
  mkdir -p "$release_dir"
  git --git-dir="$mirror_dir" worktree add --detach "$source_dir" "$commit_sha"
fi

cd "$source_dir"
[[ -f .nvmrc ]] || {
  echo "Release is missing the repository .nvmrc runtime pin." >&2
  exit 1
}
# nvm is a shell function and must be loaded explicitly in noninteractive SSH.
export NVM_DIR="$HOME/.nvm"
# shellcheck disable=SC1090
set +u
. "$NVM_DIR/nvm.sh"
nvm install
nvm use
set -u

npm --prefix "$frontend_dir" ci
npm --prefix "$frontend_dir" run build

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

backup_items=()
[[ -e "$production_dir/index.html" ]] && backup_items+=("index.html")
[[ -e "$production_dir/assets" ]] && backup_items+=("assets")
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
