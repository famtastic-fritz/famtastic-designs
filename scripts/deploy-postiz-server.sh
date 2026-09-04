#!/usr/bin/env bash
# deploy-postiz-server.sh — bring the server-hosted Postiz stack up, or check it.
#
# Why this exists: sending currently depends on the operator Mac being awake with
# colima and an ngrok tunnel running. This script stands the same Postiz up on a
# host that never sleeps, so scheduled drops fire without a human present.
#
# Dry by default, like every other deployment primitive in this repository.
# Nothing is created, started, or changed without --apply.
#
#   ./scripts/deploy-postiz-server.sh                 # preflight only
#   ./scripts/deploy-postiz-server.sh --apply         # pull, start, wait, verify
#   ./scripts/deploy-postiz-server.sh --status        # what is running
#   ./scripts/deploy-postiz-server.sh --backup        # dump db + uploads
#
# Run ON the server. Secrets live in an env file outside every repository
# (default ~/.config/famtastic/postiz-server.env, 0600) and are never printed.
# Full procedure: docs/marketing/POSTIZ_SERVER_MIGRATION.md

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
COMPOSE_FILE="$REPO_ROOT/marketing/engine/postiz/compose.server.yaml"
CADDYFILE="$REPO_ROOT/marketing/engine/postiz/Caddyfile"
ENV_FILE="${POSTIZ_SERVER_ENV:-$HOME/.config/famtastic/postiz-server.env}"
BACKUP_DIR="${POSTIZ_BACKUP_DIR:-$HOME/backups/postiz}"
PROJECT="famtastic-postiz"

APPLY=false
MODE="deploy"
for arg in "$@"; do
  case "$arg" in
    --apply)  APPLY=true ;;
    --status) MODE="status" ;;
    --backup) MODE="backup" ;;
    -h|--help) sed -n '2,20p' "$0"; exit 0 ;;
    *) printf 'unknown argument: %s\n' "$arg" >&2; exit 64 ;;
  esac
done

fail() { printf 'FAIL %s\n' "$1" >&2; exit 1; }
pass() { printf 'PASS %s\n' "$1"; }
info() { printf 'INFO %s\n' "$1"; }

compose() {
  docker compose --project-name "$PROJECT" --env-file "$ENV_FILE" \
    --file "$COMPOSE_FILE" "$@"
}

# ---------------------------------------------------------------------------
# Preflight. Every check runs in every mode; none of them mutate anything.
# ---------------------------------------------------------------------------
command -v docker >/dev/null 2>&1 || fail "docker is not installed on this host"
docker compose version >/dev/null 2>&1 || fail "docker compose v2 plugin is not available"
pass "docker and compose v2 present"

[[ -f "$COMPOSE_FILE" ]] || fail "missing $COMPOSE_FILE"
[[ -f "$CADDYFILE" ]] || fail "missing $CADDYFILE"
pass "compose file and Caddyfile present"

if [[ ! -f "$ENV_FILE" ]]; then
  fail "missing env file $ENV_FILE
     Create it (0600, outside every repo) with at least:
       POSTIZ_DOMAIN=postiz.example.com
       POSTIZ_ACME_EMAIL=you@example.com
       POSTIZ_JWT_SECRET=\$(openssl rand -hex 48)
       POSTIZ_DB_PASSWORD=\$(openssl rand -hex 32)
       POSTIZ_DISABLE_REGISTRATION=true
     plus the per-platform OAuth app credentials.
     Never commit it. See docs/marketing/POSTIZ_SERVER_MIGRATION.md"
fi

perms="$(stat -c '%a' "$ENV_FILE" 2>/dev/null || stat -f '%A' "$ENV_FILE")"
if [[ "$perms" != "600" ]]; then
  fail "env file $ENV_FILE has mode $perms; expected 600 (chmod 600 \"$ENV_FILE\")"
fi
pass "env file present and 0600"

# Required keys must be non-empty. Values are never echoed.
missing=()
for key in POSTIZ_DOMAIN POSTIZ_ACME_EMAIL POSTIZ_JWT_SECRET POSTIZ_DB_PASSWORD; do
  value="$(grep -E "^${key}=" "$ENV_FILE" | head -1 | cut -d= -f2- || true)"
  [[ -n "$value" ]] || missing+=("$key")
done
[[ ${#missing[@]} -eq 0 ]] || fail "env file is missing values for: ${missing[*]}"
pass "required secrets are set (values not printed)"

DOMAIN="$(grep -E '^POSTIZ_DOMAIN=' "$ENV_FILE" | head -1 | cut -d= -f2-)"
info "public hostname: $DOMAIN"

# An open registration on a publicly reachable instance lets a stranger create
# an org. Warn loudly rather than refusing: it must be open briefly at setup.
if grep -qE '^POSTIZ_DISABLE_REGISTRATION=false' "$ENV_FILE"; then
  printf 'WARN registration is OPEN on a public host. Close it (POSTIZ_DISABLE_REGISTRATION=true)\n'
  printf 'WARN and re-run --apply as soon as the owner account exists.\n'
fi

compose config --quiet || fail "compose configuration is invalid"
pass "compose configuration valid"

# DNS must already point at this host or Caddy cannot get a certificate.
if command -v dig >/dev/null 2>&1; then
  resolved="$(dig +short "$DOMAIN" A | tail -1)"
  if [[ -z "$resolved" ]]; then
    printf 'WARN %s does not resolve yet; Caddy cannot issue a certificate until it does.\n' "$DOMAIN"
  else
    info "$DOMAIN resolves to $resolved"
  fi
fi

case "$MODE" in
  status)
    compose ps
    exit 0
    ;;

  backup)
    if ! $APPLY; then
      info "DRY RUN — would back up the Postiz database and uploads to $BACKUP_DIR"
      info "re-run with --apply to write the backup"
      exit 0
    fi
    mkdir -p "$BACKUP_DIR"
    stamp="$(date -u +%Y%m%dT%H%M%SZ)"
    compose exec -T postiz-postgres \
      pg_dump -U postiz-user -d postiz-db-local > "$BACKUP_DIR/postiz-db-$stamp.sql"
    docker run --rm \
      -v "${PROJECT}_postiz-uploads:/uploads:ro" \
      -v "$BACKUP_DIR:/backup" alpine \
      tar czf "/backup/postiz-uploads-$stamp.tar.gz" -C /uploads .
    chmod 600 "$BACKUP_DIR"/postiz-*-"$stamp".*
    pass "backup written to $BACKUP_DIR (db + uploads, $stamp)"
    exit 0
    ;;
esac

# ---------------------------------------------------------------------------
# Deploy.
# ---------------------------------------------------------------------------
if ! $APPLY; then
  printf '\nDRY RUN — nothing was created, started, or changed.\n'
  printf 'Would run:\n'
  printf '  docker compose --project-name %s --file %s pull\n' "$PROJECT" "${COMPOSE_FILE#"$REPO_ROOT"/}"
  printf '  docker compose --project-name %s --file %s up --detach\n' "$PROJECT" "${COMPOSE_FILE#"$REPO_ROOT"/}"
  printf '  then poll https://%s/api/public/v1/is-connected until healthy\n' "$DOMAIN"
  printf '\nre-run with --apply to deploy\n'
  exit 0
fi

# Back up before mutating an existing stack; a first deploy has nothing to save.
if compose ps --quiet postiz-postgres 2>/dev/null | grep -q .; then
  info "existing stack detected — taking a backup before changing it"
  "$0" --backup --apply
fi

info "pulling images"
compose pull

info "starting stack"
compose up --detach

info "waiting for Postiz to answer over HTTPS (up to 180s; first run also issues a certificate)"
ok=false
for _ in $(seq 1 60); do
  if curl --fail --silent --max-time 5 "https://$DOMAIN/api/public/v1/is-connected" >/dev/null 2>&1; then
    ok=true
    break
  fi
  sleep 3
done

if $ok; then
  pass "Postiz is reachable at https://$DOMAIN"
  printf '\nNext:\n'
  printf '  1. Create the owner account, then set POSTIZ_DISABLE_REGISTRATION=true and re-run --apply.\n'
  printf '  2. Re-authorize each social channel; their OAuth redirect URLs now point at https://%s.\n' "$DOMAIN"
  printf '  3. Copy the org API key into the operator environment as FAMTASTIC_POSTIZ_API_KEY.\n'
  printf '  4. Point the campaign scripts at it:\n'
  printf '     export FAMTASTIC_POSTIZ_BASE_URL=https://%s/api/public/v1\n' "$DOMAIN"
  printf '  Full procedure: docs/marketing/POSTIZ_SERVER_MIGRATION.md\n'
else
  compose logs --tail 60 postiz caddy || true
  fail "Postiz did not become reachable at https://$DOMAIN within 180s (logs above).
     Common causes: DNS not pointing here yet, ports 80/443 blocked by the host
     firewall, or the certificate challenge failing. Caddy needs BOTH 80 and 443."
fi
