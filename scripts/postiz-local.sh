#!/usr/bin/env bash
set -euo pipefail

repo="$(git rev-parse --show-toplevel)"
postiz_checkout="${POSTIZ_CHECKOUT:-/Users/famtastic-fritz/Development/FAMtastic/tools/postiz-docker-compose}"
runtime_dir="${POSTIZ_RUNTIME_DIR:-$HOME/.config/famtastic-marketing}"
env_file="$runtime_dir/postiz.env"
base_compose="$postiz_checkout/docker-compose.yaml"
override_compose="$repo/marketing/engine/postiz/compose.override.yaml"

if [[ ! -f "$base_compose" ]]; then
  printf 'Postiz checkout missing: %s\n' "$base_compose" >&2
  exit 1
fi

mkdir -p "$runtime_dir"
chmod 700 "$runtime_dir"

if [[ ! -f "$env_file" ]]; then
  umask 077
  jwt_secret="$(openssl rand -hex 48)"
  db_password="$(openssl rand -hex 32)"
  {
    printf 'POSTIZ_JWT_SECRET=%s\n' "$jwt_secret"
    printf 'POSTIZ_DB_PASSWORD=%s\n' "$db_password"
    printf 'POSTIZ_DISABLE_REGISTRATION=false\n'
    printf 'FACEBOOK_APP_ID=\nFACEBOOK_APP_SECRET=\n'
    printf 'THREADS_APP_ID=\nTHREADS_APP_SECRET=\n'
    printf 'TIKTOK_CLIENT_ID=\nTIKTOK_CLIENT_SECRET=\n'
    printf 'YOUTUBE_CLIENT_ID=\nYOUTUBE_CLIENT_SECRET=\n'
    printf 'LINKEDIN_CLIENT_ID=\nLINKEDIN_CLIENT_SECRET=\n'
    printf 'PINTEREST_CLIENT_ID=\nPINTEREST_CLIENT_SECRET=\n'
    printf 'X_API_KEY=\nX_API_SECRET=\n'
  } > "$env_file"
fi

compose() {
  DOCKER_HOST="unix://$HOME/.colima/docker.sock" docker-compose \
    --project-name famtastic-postiz \
    --env-file "$env_file" \
    --file "$base_compose" \
    --file "$override_compose" "$@"
}

case "${1:-status}" in
  start)
    colima start --cpu 4 --memory 6 --disk 50 >/dev/null
    compose up --detach
    ;;
  stop)
    compose stop
    ;;
  status)
    compose ps
    ;;
  logs)
    compose logs --tail 200 postiz
    ;;
  config)
    compose config --quiet
    printf 'PASS Postiz configuration is valid; secrets are stored outside Git.\n'
    ;;
  *)
    printf 'Usage: %s {start|stop|status|logs|config}\n' "$0" >&2
    exit 2
    ;;
esac

