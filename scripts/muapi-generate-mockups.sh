#!/usr/bin/env bash
# FAMtastic Designs — UI mockup generation via muapi.ai (flux-schnell).
# Key comes from the OS keychain (muapi-cli service) at runtime; never
# committed, never echoed. Outputs land in marketing/design-mockups/<run>/.
set -uo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="$REPO_ROOT/marketing/design-mockups/$(date +%Y-%m-%d)"
mkdir -p "$OUT"

KEY="$(security find-generic-password -s muapi-cli -w 2>/dev/null)"
if [[ -z "$KEY" ]]; then
  echo "FAIL: muapi-cli key not found in keychain" >&2
  exit 1
fi

BRAND='FAMtastic Designs brand system: near-black charcoal background #070907, signature lime green #7cfc00 accent, soft lime glows, Inter typeface, rounded 18px panels with 1px dark borders, subtle clay-warmth textures, dark-mode professional SaaS aesthetic, clean information hierarchy, generous whitespace, desktop 16:10 UI screenshot, crisp legible text labels, no lorem ipsum'

declare -a NAMES=(
  "admin-email-center"
  "admin-marketing-center"
  "admin-proof-qa"
  "portal-projects"
)

declare -a PROMPTS=(
  "$BRAND. Admin EMAIL CENTER for a web-design agency operations dashboard: left sidebar navigation (Operations Home, Campaign Operations, Email Center highlighted), main area shows an outbound notification queue table with columns Recipient, Subject, Status badge pills (sent=green, queued=amber, dead-letter=red), filter tabs (All, Queued, Sent, Failed), a compose panel on the right with template picker and merge-token chips, top KPI cards showing Sent today, Open rate, Click rate, Dead letters zero. Realistic dense data rows with customer names and subjects."
  "$BRAND. Admin MARKETING CENTER for a web-design agency: campaign calendar strip across the top showing 17 days with four content moments per day as small lime chips (teach, challenge, prove, invite), approval gate columns (Content, Media, Publish) with approve toggle switches, a Postiz scheduling status panel with connected Facebook channel badge and token expiry date, per-record cards with asset thumbnails and UTM tags, KPI header: records ready, awaiting approval, queued drafts, published."
  "$BRAND. Admin PROOF QA REVIEW screen for a web-design agency pipeline: three website proof concept previews side by side in browser-frame cards, each with a direction name label and quality checklist (mobile-safe, brand tokens, content complete) with pass/fail ticks, an approve-and-send-to-customer primary button with a warning strip that customer visibility is a gate, below them a build pipeline timeline showing generation status per concept, owner-only watermark badge on each preview."
  "$BRAND. Customer PORTAL PROJECTS PAGE for small-business clients of a web-design agency: request switcher chips across the top, one expanded website request with three live website concept preview thumbnails rendered as mini browser windows inside cards, each card has direction name, Available pill, Choose this direction lime button, a progress stepper (Brief, Concepts, Selection, Purchase, Launch), friendly plain-language status copy, a purchase panel showing Web Basics Bundle price with secure checkout button."
)

failures=0
for i in "${!NAMES[@]}"; do
  NAME="${NAMES[$i]}"
  PROMPT="${PROMPTS[$i]}"
  BODY=$(printf '{"prompt":%s,"width":1600,"height":1024,"num_images":1,"sync":true}' "$(python3 -c "import json,sys;print(json.dumps(sys.argv[1]))" "$PROMPT")")
  RESP=$(curl -sS --max-time 180 -X POST "https://api.muapi.ai/api/v1/flux-schnell-image" \
    -H "x-api-key: $KEY" -H "Content-Type: application/json" -d "$BODY")
  URL=$(printf '%s' "$RESP" | python3 -c "import json,sys;d=json.load(sys.stdin);r=d.get('result') or d.get('outputs') or d.get('url');print((r[0] if isinstance(r,list) else r) or '')" 2>/dev/null)
  if [[ -z "$URL" ]]; then
    echo "FAIL $NAME: $RESP" | head -c 300; echo
    failures=$((failures+1))
    continue
  fi
  curl -sS --max-time 120 -o "$OUT/$NAME.png" "$URL" \
    && echo "PASS $NAME -> marketing/design-mockups/$(basename "$OUT")/$NAME.png" \
    || { echo "FAIL $NAME download"; failures=$((failures+1)); }
done

echo "Done with $failures failure(s). Output: $OUT"
exit $(( failures > 0 ? 1 : 0 ))
