#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DIST_DIR="$REPO_ROOT/frontend/dist"
DEPLOY_SCRIPT="$REPO_ROOT/scripts/deploy-frontend-godaddy.sh"
SANDBOX="$(mktemp -d "${TMPDIR:-/tmp}/famtastic-route-shells.XXXXXX")"

cleanup() {
  case "$SANDBOX" in
    "${TMPDIR:-/tmp}"/famtastic-route-shells.*)
      rm -rf "$SANDBOX"
      ;;
    *) echo "Refusing to remove unexpected sandbox: $SANDBOX" >&2 ;;
  esac
}
trap cleanup EXIT

test -f "$DIST_DIR/index.html"
test -f "$DIST_DIR/contact/index.html"

root_assets="$(grep -oE '(src|href)="/assets/[^"]+"' "$DIST_DIR/index.html" | sort -u)"
test -n "$root_assets"
while IFS= read -r route_shell; do
  route_assets="$(grep -oE '(src|href)="/assets/[^"]+"' "$route_shell" | sort -u)"
  if [[ "$route_assets" != "$root_assets" ]]; then
    echo "Route shell references a different release asset set: $route_shell" >&2
    exit 1
  fi
done < <(find "$DIST_DIR" -mindepth 2 -type f -name index.html -print)

grep -Fq -- "--exclude='/index.html' --exclude='/assets/'" "$DEPLOY_SCRIPT"

mkdir -p "$SANDBOX/source/contact" "$SANDBOX/source/assets" "$SANDBOX/target"
printf 'root\n' > "$SANDBOX/source/index.html"
printf 'contact\n' > "$SANDBOX/source/contact/index.html"
printf 'asset\n' > "$SANDBOX/source/assets/app.js"
rsync -a --exclude='/index.html' --exclude='/assets/' "$SANDBOX/source/" "$SANDBOX/target/"

test ! -e "$SANDBOX/target/index.html"
test ! -e "$SANDBOX/target/assets/app.js"
test "$(cat "$SANDBOX/target/contact/index.html")" = 'contact'

echo "PASS: generated route shells share the current asset set and the deploy filter promotes nested index files."
