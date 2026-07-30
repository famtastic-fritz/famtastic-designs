#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
FRONTEND_DIR="$REPO_ROOT/v2/frontend"
DIST_DIR="$FRONTEND_DIR/dist"
SSH_TARGET="${FAMTASTIC_SSH_TARGET:-xrdj7j99xhzt@p3plzcpnl497512.prod.phx3.secureserver.net}"
REMOTE_ROOT="${FAMTASTIC_REMOTE_ROOT:-public_html}"
APPLY=false

usage() {
  echo "Usage: $0 [--apply]"
  echo "Without --apply, builds and shows an rsync dry run."
}

case "${1:-}" in
  "") ;;
  --apply) APPLY=true ;;
  -h|--help) usage; exit 0 ;;
  *) usage >&2; exit 2 ;;
esac

for required_command in git npm rsync ssh curl; do
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
echo "Preparing frontend from commit $COMMIT_SHA"

npm --prefix "$FRONTEND_DIR" ci
npm --prefix "$FRONTEND_DIR" run build

if grep -qE '(src|href)="/src/' "$DIST_DIR/index.html"; then
  echo "Build rejected: dist/index.html contains a raw /src/ reference." >&2
  exit 1
fi

ASSET_PATHS=()
while IFS= read -r asset_path; do
  ASSET_PATHS+=("$asset_path")
done < <(
  grep -oE '(src|href)="/assets/[^"]+"' "$DIST_DIR/index.html" |
    sed -E 's/^(src|href)="\/(assets\/[^"]+)"$/\2/'
)

if [[ "${#ASSET_PATHS[@]}" -eq 0 ]]; then
  echo "Build rejected: dist/index.html contains no compiled assets." >&2
  exit 1
fi

VERIFY_BODY="$(mktemp)"
trap 'rm -f "$VERIFY_BODY"' EXIT

for asset_path in "${ASSET_PATHS[@]}"; do
  if [[ ! -f "$DIST_DIR/$asset_path" ]]; then
    echo "Build rejected: missing dist/$asset_path" >&2
    exit 1
  fi
done

echo "Transfer preview:"
rsync -az --itemize-changes --dry-run \
  "$DIST_DIR/" "$SSH_TARGET:~/$REMOTE_ROOT/"

if [[ "$APPLY" != true ]]; then
  echo "Dry run complete. Re-run with --apply after reviewing the transfer."
  exit 0
fi

BACKUP_NAME="famtastic-frontend-$(date -u +%Y%m%dT%H%M%SZ).tgz"
echo "Creating remote backup: ~/backups/$BACKUP_NAME"
ssh -T "$SSH_TARGET" \
  "set -eu; cd ~; mkdir -p backups; tar -czf \"backups/$BACKUP_NAME\" \"$REMOTE_ROOT/index.html\" \"$REMOTE_ROOT/assets\""

rsync -az --itemize-changes "$DIST_DIR/" "$SSH_TARGET:~/$REMOTE_ROOT/"
printf '%s\n' "$COMMIT_SHA" |
  ssh -T "$SSH_TARGET" "cat > ~/$REMOTE_ROOT/.frontend-release"

for asset_path in "${ASSET_PATHS[@]}"; do
  live_url="https://famtasticdesigns.com/$asset_path"
  headers="$(curl -fsSI "$live_url")"
  content_type="$(
    printf '%s\n' "$headers" |
      awk -F': *' 'tolower($1) == "content-type" {print tolower($2)}' |
      tr -d '\r' |
      tail -1
  )"

  case "$asset_path" in
    *.js)
      [[ "$content_type" == *javascript* ]] || {
        echo "Verification failed: $live_url returned $content_type" >&2
        exit 1
      }
      ;;
    *.css)
      [[ "$content_type" == text/css* ]] || {
        echo "Verification failed: $live_url returned $content_type" >&2
        exit 1
      }
      ;;
  esac

  curl -fsS "$live_url" -o "$VERIFY_BODY"
  if head -c 256 "$VERIFY_BODY" | grep -qi '<!doctype html'; then
    echo "Verification failed: $live_url returned HTML." >&2
    exit 1
  fi
done

echo "Deployment complete for commit $COMMIT_SHA"
echo "Rollback archive: ~/backups/$BACKUP_NAME"
echo "Complete real-browser acceptance for apex and www before closing the deployment."
