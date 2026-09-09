#!/usr/bin/env bash
set -euo pipefail

ROOT="$(mktemp -d "${TMPDIR:-/tmp}/protected-staging-test.XXXXXX")"
trap 'rm -rf -- "$ROOT"' EXIT
mkdir -p "$ROOT/repo/scripts"
cp "$(dirname "$0")/deploy-protected-staging.sh" "$ROOT/repo/scripts/"
chmod +x "$ROOT/repo/scripts/deploy-protected-staging.sh"
printf 'fixture\n' > "$ROOT/repo/README.md"
git -C "$ROOT/repo" init -q
git -C "$ROOT/repo" config user.email test@example.invalid
git -C "$ROOT/repo" config user.name protected-staging-test
git -C "$ROOT/repo" add README.md scripts/deploy-protected-staging.sh
git -C "$ROOT/repo" commit -qm fixture
SHA="$(git -C "$ROOT/repo" rev-parse HEAD)"
EVIDENCE="$ROOT/evidence"

env FAMTASTIC_STAGING_PUSHED_SHA="$SHA" FAMTASTIC_STAGING_REMOTE_SHA="$SHA" \
  FAMTASTIC_STAGING_EVIDENCE_ROOT="$EVIDENCE" \
  "$ROOT/repo/scripts/deploy-protected-staging.sh" --dry-run > "$ROOT/pass.out"
grep -q 'Protected staging preflight passed' "$ROOT/pass.out"
test -f "$EVIDENCE/$SHA-"*'.release.json'

if env FAMTASTIC_STAGING_PUSHED_SHA="$SHA" FAMTASTIC_STAGING_REMOTE_SHA="$SHA" \
  FAMTASTIC_STAGING_DOCROOT="$ROOT/repo/public_html" \
  "$ROOT/repo/scripts/deploy-protected-staging.sh" --preflight > /dev/null 2>&1; then
  echo 'expected public_html rejection' >&2
  exit 1
fi
if env FAMTASTIC_STAGING_PUSHED_SHA="$SHA" FAMTASTIC_STAGING_REMOTE_SHA="$SHA" \
  "$ROOT/repo/scripts/deploy-protected-staging.sh" --apply > /dev/null 2>&1; then
  echo 'expected apply rejection' >&2
  exit 1
fi
echo 'protected staging tests passed'
