#!/usr/bin/env bash
# FAMtastic Designs — FAMtastic Operations admin link audit (deep).
# Boots the local site, authenticates as uid 1, and requests every admin
# surface a real click could reach, INCLUDING second-level detail routes
# using live record ids from this database. Read-only GETs; local-only.
set -uo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DRUSH="$REPO_ROOT/backend/vendor/bin/drush"
PORT="${AUDIT_PORT:-8935}"
ART="$REPO_ROOT/.artifacts/admin-audit/$(date +%s)"
mkdir -p "$ART"

"$DRUSH" -r "$REPO_ROOT/backend/web" runserver "127.0.0.1:$PORT" >"$ART/server.log" 2>&1 &
SERVER_PID=$!
trap 'kill $SERVER_PID 2>/dev/null' EXIT
for i in $(seq 1 30); do curl -s -o /dev/null "http://127.0.0.1:$PORT" && break; sleep 1; done

ULI=$("$DRUSH" -r "$REPO_ROOT/backend/web" uli --uid=1 --no-browser 2>/dev/null | grep -m1 . | tr -d "[:space:]" )
ULI_PATH="${ULI#*default}"
curl -s -L -c "$ART/cookies.txt" -o /dev/null "http://127.0.0.1:$PORT$ULI_PATH"
AUTH=$(curl -s -b "$ART/cookies.txt" "http://127.0.0.1:$PORT/admin/famtastic" | grep -o "<title>[^<]*")
[[ "$AUTH" == *"Operations Home"* ]] || { printf 'FATAL: auth failed (%s)\n' "$AUTH"; exit 2; }
printf 'auth ok\n'

CAMPAIGN_KEY=$("$DRUSH" -r "$REPO_ROOT/backend/web" sqlq "SELECT campaign_key FROM famtastic_campaign ORDER BY id DESC LIMIT 1" 2>/dev/null | grep -m1 . | tr -d "[:space:]" )
MESSAGE_ID=$("$DRUSH" -r "$REPO_ROOT/backend/web" sqlq "SELECT id FROM famtastic_notification_outbox ORDER BY id DESC LIMIT 1" 2>/dev/null | grep -m1 . | tr -d "[:space:]" )
BUILD_ID=$("$DRUSH" -r "$REPO_ROOT/backend/web" sqlq "SELECT id FROM famtastic_build_run ORDER BY id DESC LIMIT 1" 2>/dev/null | grep -m1 . | tr -d "[:space:]" )
PROSPECT_ID=$("$DRUSH" -r "$REPO_ROOT/backend/web" sqlq "SELECT id FROM famtastic_prospect ORDER BY id DESC LIMIT 1" 2>/dev/null | grep -m1 . | tr -d "[:space:]" )
REQUEST_ID=$("$DRUSH" -r "$REPO_ROOT/backend/web" sqlq "SELECT id FROM famtastic_project_request ORDER BY id DESC LIMIT 1" 2>/dev/null | grep -m1 . | tr -d "[:space:]" )
GRANT_ID=$("$DRUSH" -r "$REPO_ROOT/backend/web" sqlq "SELECT id FROM famtastic_grant_code ORDER BY id DESC LIMIT 1" 2>/dev/null | grep -m1 . | tr -d "[:space:]" )
DRAFT_ID=$("$DRUSH" -r "$REPO_ROOT/backend/web" sqlq "SELECT id FROM famtastic_support_draft ORDER BY id DESC LIMIT 1" 2>/dev/null | grep -m1 . | tr -d "[:space:]" )

declare -a PATHS=(
  "/admin/famtastic"
  "/admin/famtastic/settings"
  "/admin/famtastic/campaigns"
  "/admin/famtastic/analytics"
  "/admin/famtastic/launch-approval"
  "/admin/famtastic/grant-code/add"
  "/admin/famtastic/metric/campaigns"
  "/admin/famtastic/metric/prospects"
  "/admin/famtastic/metric/customers"
  "/admin/famtastic/metric/website-requests"
  "/admin/famtastic/metric/proofs-ready"
  "/admin/famtastic/metric/emails-sent"
  "/admin/famtastic/metric/clicks"
  "/admin/famtastic/metric/paid-orders"
  "/admin/famtastic/metric/open-jobs"
  "/admin/famtastic/metric/open-exceptions"
  "/admin/famtastic/metric/support"
  "/admin/famtastic/metric/referrals"
  "/admin/famtastic/metric/services"
  "/admin/famtastic/metric/notifications"
  "/admin/famtastic/metric/replies"
  "/admin/famtastic/metric/support-drafts"
  "/admin/famtastic/metric/workers"
  "/admin/famtastic/metric/grant-codes"
  "/admin/commerce"
  "/admin/commerce/orders"
  "/admin/commerce/products"
  "/admin/content"
  "/admin/content?type=blog_post"
  "/admin/people"
)
[[ -n "$CAMPAIGN_KEY" ]] && PATHS+=("/admin/famtastic/campaign/$CAMPAIGN_KEY")
MSG_ID=$("$DRUSH" -r "$REPO_ROOT/backend/web" sqlq "SELECT id FROM famtastic_email_message ORDER BY id DESC LIMIT 1" 2>/dev/null | grep -m1 . | tr -d '[:space:]')
[[ -n "$MSG_ID" ]] && PATHS+=("/admin/famtastic/message/$MSG_ID")
[[ -n "$BUILD_ID"    ]] && PATHS+=("/admin/famtastic/build/$BUILD_ID")
[[ -n "$PROSPECT_ID" ]] && PATHS+=("/admin/famtastic/prospect/$PROSPECT_ID/workspace")
[[ -n "$REQUEST_ID"  ]] && PATHS+=("/admin/famtastic/website-request/$REQUEST_ID/offer") \
                      && PATHS+=("/admin/famtastic/website-request/$REQUEST_ID/proof-review")
[[ -n "$DRAFT_ID"    ]] && PATHS+=("/admin/famtastic/support-draft/$DRAFT_ID/decide")
PATHS+=("/admin/famtastic/social-record/55c-d01-teach/content/approve")
PATHS+=("/admin/famtastic/social-record/55c-d01-teach/media/revoke")
PATHS+=("/admin/famtastic/marketing/email/1")
PATHS+=("/admin/famtastic/marketing/build-dna/1")
PATHS+=("/web/user/1")

FAIL=0
printf '%-62s %-6s %-8s %s\n' "PATH" "HTTP" "BYTES" "VERDICT"
for p in "${PATHS[@]}"; do
  F="$ART/$(echo "$p" | tr '/?=' '___').html"
  CODE=$(curl -s -L -b "$ART/cookies.txt" -o "$F" -w "%{http_code}" --max-time 30 "http://127.0.0.1:$PORT$p")
  SIZE=$(wc -c < "$F" | tr -d ' ')
  TITLE=$(grep -o "<title>[^|<]*" "$F" | head -1 | cut -c8-40)
  if [[ "$CODE" != "200" ]]; then
    VERDICT="DEAD ($TITLE)"; ((FAIL+=1))
  elif grep -q "The website encountered an unexpected error" "$F"; then
    VERDICT="EXCEPTION"; ((FAIL+=1))
  elif [[ $SIZE -lt 4000 ]]; then
    VERDICT="EMPTY (${SIZE}b) [$TITLE]"; ((FAIL+=1))
  else
    VERDICT="ok [$TITLE]"
  fi
  printf '%-62s %-6s %-8s %s\n' "$p" "$CODE" "$SIZE" "$VERDICT"
done

printf '\nFailures: %d / %d\n' "$FAIL" "${#PATHS[@]}"
printf 'Artifacts: %s\n' "$ART"
exit $(( FAIL > 0 ? 1 : 0 ))
