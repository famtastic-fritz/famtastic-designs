#!/usr/bin/env zsh
# restart-postiz-tunnel.sh — one-command recovery for the Postiz public URL.
#
# Why this exists: Postiz needs an HTTPS public URL for OAuth callbacks
# (Meta/X/YouTube/TikTok require https redirects). DNS is on GoDaddy by owner
# decision, so a custom subdomain is not available. ngrok FREE static domain
# provides a permanent hostname — whitelisted ONCE in each developer portal.
# If Postiz becomes unreachable or the spinner returns, run this script.
#
# Env file lives OUTSIDE all repos at ~/.config/famtastic/postiz.env (0600).
# Never commit or print secrets.

set -euo pipefail

NGROK_DOMAIN="designate-vacation-shadiness.ngrok-free.dev"

COMPOSE_DIR="$HOME/Development/FAMtastic/tools/postiz-docker-compose"
OVERRIDE="$HOME/Development/FAMtastic/sites/site-famtastic-designs/marketing/engine/postiz/compose.override.yaml"
ENV_FILE="$HOME/.config/famtastic/postiz.env"
LOGDIR="$HOME/.local/state/famtastic"
LOGFILE="$LOGDIR/cloudflared-postiz.log"

mkdir -p "$LOGDIR"

echo "[1/5] Capturing current secrets from running containers..."
PROJECT=$(docker inspect postiz --format '{{index .Config.Labels "com.docker.compose.project"}}')
echo "      compose project: $PROJECT"
JWT=$(docker exec postiz printenv JWT_SECRET)
DBPW=$(docker exec postiz-postgres printenv POSTGRES_PASSWORD)

echo "[2/5] Starting ngrok tunnel on static domain $NGROK_DOMAIN..."
pkill -f "ngrok http.*$NGROK_DOMAIN" 2>/dev/null || true
pkill -f "cloudflared tunnel --url http://localhost:4007" 2>/dev/null || true
: > "$LOGFILE"
nohup ngrok http --url="$NGROK_DOMAIN" --log "$LOGFILE" --log-format=json 4007 >/dev/null 2>&1 &

NEW_URL=""
for i in {1..20}; do
  ONLINE=$(curl -s http://127.0.0.1:4040/api/tunnels 2>/dev/null | grep -o "\"public_url\":\"https://[^\"]*\"" | head -1 | cut -d'"' -f4 || true)
  [ -n "$ONLINE" ] && NEW_URL="$ONLINE" && break
  sleep 1
done
if [ -z "$NEW_URL" ]; then echo "FAIL: tunnel did not come up; see $LOGFILE (is your authtoken configured? 'ngrok config check')"; exit 1; fi
echo "      Tunnel live: $NEW_URL"

echo "[3/5] Writing env file ($ENV_FILE)..."
{
  echo "POSTIZ_PUBLIC_URL=$NEW_URL"
  echo "POSTIZ_DISABLE_REGISTRATION=true"
  echo "POSTIZ_JWT_SECRET=$JWT"
  echo "POSTIZ_DB_PASSWORD=$DBPW"
  docker inspect postiz --format '{{range .Config.Env}}{{println .}}{{end}}' \
    | grep -E '^(FACEBOOK_APP_ID|FACEBOOK_APP_SECRET|THREADS_APP_ID|THREADS_APP_SECRET|TIKTOK_CLIENT_ID|TIKTOK_CLIENT_SECRET|YOUTUBE_CLIENT_ID|YOUTUBE_CLIENT_SECRET|LINKEDIN_CLIENT_ID|LINKEDIN_CLIENT_SECRET|PINTEREST_CLIENT_ID|PINTEREST_CLIENT_SECRET|X_API_KEY|X_API_SECRET)=' || true
} > "$ENV_FILE"
chmod 600 "$ENV_FILE"

echo "[4/5] Recreating postiz container with new URLs..."
docker-compose -p "$PROJECT" --env-file "$ENV_FILE" \
  -f "$COMPOSE_DIR/docker-compose.yaml" -f "$OVERRIDE" \
  up -d --no-deps postiz

echo "[5/5] Verifying..."
sleep 4
LOCAL=$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:4007 || true)
TUNNEL=$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 "$NEW_URL" || true)
BACKEND=$(docker exec postiz printenv NEXT_PUBLIC_BACKEND_URL)
echo "      local=$LOCAL tunnel=$TUNNEL backend_url=$BACKEND"
if [ "$LOCAL" = "200" ] || [ "$LOCAL" = "307" ]; then
  echo "DONE. Log in at $NGROK_DOMAIN — this hostname is permanent."
else
  echo "Container may still be starting; re-run verification in ~15s."
fi