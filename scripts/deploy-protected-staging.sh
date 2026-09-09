#!/usr/bin/env bash
set -euo pipefail

# Protected staging release primitive. This command is deliberately local and
# non-deploying: it prepares an exact-commit release plan and evidence bundle
# for owner review. It never opens SSH, changes DNS, writes public_html, or
# starts a scheduler/provider/transport.

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
MODE="${1:-}"
STAGING_HOST="${FAMTASTIC_STAGING_HOST:-staging.famtasticdesigns.com}"
STAGING_DOCROOT="${FAMTASTIC_STAGING_DOCROOT:-$REPO_ROOT/.protected-staging/$STAGING_HOST/docroot}"
STAGING_DB="${FAMTASTIC_STAGING_DB_NAME:-famtastic_staging}"
STAGING_CONFIG="${FAMTASTIC_STAGING_CONFIG_PATH:-$REPO_ROOT/.protected-staging/$STAGING_HOST/config}"
PRIVATE_ROOT="${FAMTASTIC_STAGING_PRIVATE_ROOT:-$REPO_ROOT/.protected-staging/$STAGING_HOST/private}"
EVIDENCE_ROOT="${FAMTASTIC_STAGING_EVIDENCE_ROOT:-$REPO_ROOT/.protected-staging/evidence}"
PUSHED_SHA="${FAMTASTIC_STAGING_PUSHED_SHA:-}"
REMOTE_SHA="${FAMTASTIC_STAGING_REMOTE_SHA:-}"

fail() { echo "protected-staging: $*" >&2; exit 2; }

case "$MODE" in
  --preflight|--dry-run) ;;
  --apply|--deploy) fail "remote deployment is not supported by this primitive; use --preflight or --dry-run" ;;
  *) echo "Usage: $0 --preflight|--dry-run" >&2; exit 2 ;;
esac

[[ -n "$PUSHED_SHA" && "$PUSHED_SHA" =~ ^[0-9a-f]{40}$ ]] || fail "FAMTASTIC_STAGING_PUSHED_SHA must be the exact 40-character pushed commit SHA"
[[ -n "$REMOTE_SHA" && "$REMOTE_SHA" =~ ^[0-9a-f]{40}$ ]] || fail "FAMTASTIC_STAGING_REMOTE_SHA must be the exact pushed commit SHA observed by the caller"
cd "$REPO_ROOT"
[[ -z "$(git status --porcelain)" ]] || fail "refusing a dirty worktree"
HEAD_SHA="$(git rev-parse HEAD)"
[[ "$HEAD_SHA" == "$PUSHED_SHA" && "$HEAD_SHA" == "$REMOTE_SHA" ]] || fail "HEAD, pushed SHA, and observed remote SHA must match exactly"

[[ "$STAGING_HOST" =~ ^[a-z0-9][a-z0-9.-]+$ ]] || fail "staging host is malformed"
[[ "$STAGING_HOST" != *famtasticdesigns.com ]] || [[ "$STAGING_HOST" == staging.famtasticdesigns.com ]] || fail "staging host must be a dedicated non-production subdomain"
[[ "$STAGING_DOCROOT" != "/" && "$STAGING_DOCROOT" != "$HOME/public_html" && "$STAGING_DOCROOT" != *"/public_html" ]] || fail "public_html and production document roots are forbidden"
[[ "$STAGING_DOCROOT" == *"$STAGING_HOST"* ]] || fail "docroot must be scoped to the configured staging host"
[[ "$STAGING_CONFIG" == *"$STAGING_HOST"* && "$PRIVATE_ROOT" == *"$STAGING_HOST"* ]] || fail "config and private paths must be isolated to the staging host"
[[ "$STAGING_DB" == *staging* ]] || fail "database name must identify the isolated staging database"

[[ "${FAMTASTIC_STAGING_REMOTE_MUTATION:-0}" == 0 ]] || fail "remote mutation is permanently disabled"
[[ "${FAMTASTIC_STAGING_DNS_MUTATION:-0}" == 0 ]] || fail "DNS mutation is permanently disabled"
[[ "${FAMTASTIC_STAGING_EMAIL_TRANSPORT:-blackhole}" == blackhole ]] || fail "email transport must be blackhole"
[[ "${FAMTASTIC_STAGING_PAYMENT_MODE:-disabled}" == disabled ]] || fail "payment mode must be disabled"
[[ "${FAMTASTIC_STAGING_SCHEDULER:-disabled}" == disabled ]] || fail "scheduler must be disabled"
[[ -z "${FAMTASTIC_SSH_TARGET:-}" ]] || fail "SSH target is forbidden in protected staging mode"

RELEASE_ID="${HEAD_SHA}-$(date -u +%Y%m%dT%H%M%SZ)"
TMP_ROOT="$(mktemp -d "${TMPDIR:-/tmp}/famtastic-staging.XXXXXX")"
trap 'rm -rf -- "$TMP_ROOT"' EXIT
SOURCE_ROOT="$TMP_ROOT/source"
mkdir -p "$SOURCE_ROOT"
git archive --format=tar "$HEAD_SHA" | tar -xf - -C "$SOURCE_ROOT"

mkdir -p "$EVIDENCE_ROOT"
RECEIPT="$EVIDENCE_ROOT/$RELEASE_ID.release.json"
ROLLBACK="$EVIDENCE_ROOT/$RELEASE_ID.rollback.json"
PREVIOUS=""
if [[ -f "$STAGING_DOCROOT/.protected-staging-release" ]]; then
  PREVIOUS="$(head -n 1 "$STAGING_DOCROOT/.protected-staging-release" || true)"
fi
printf '{"schema":"famtastic.protected-staging-release.v1","release_id":"%s","commit":"%s","host":"%s","docroot":"%s","database":"%s","config_path":"%s","private_root":"%s","email_transport":"blackhole","payment_mode":"disabled","scheduler":"disabled","remote_mutation":false,"dns_mutation":false,"status":"planned","source_archive":"exact_git_archive"}\n' \
  "$RELEASE_ID" "$HEAD_SHA" "$STAGING_HOST" "$STAGING_DOCROOT" "$STAGING_DB" "$STAGING_CONFIG" "$PRIVATE_ROOT" > "$RECEIPT"
printf '{"schema":"famtastic.protected-staging-rollback.v1","release_id":"%s","target_host":"%s","target_docroot":"%s","previous_release":"%s","rollback_action":"restore the prior protected-staging release only after owner approval","executed":false}\n' \
  "$RELEASE_ID" "$STAGING_HOST" "$STAGING_DOCROOT" "$PREVIOUS" > "$ROLLBACK"

echo "Protected staging preflight passed: $HEAD_SHA"
echo "Mode: $MODE (no remote or production mutation)"
echo "Release evidence: $RECEIPT"
echo "Rollback evidence: $ROLLBACK"
echo "Exact source archive prepared in an isolated temporary workspace."
