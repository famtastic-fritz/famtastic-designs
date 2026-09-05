#!/usr/bin/env bash
#
# FAMtastic delivery cycle — the recurring half of the content engine.
#
# WHAT THIS DOES AND DOES NOT DO
#
# It does every mechanical step that does not require judgement: it checks how
# deep the publishing queue is, audits every live link, scores the running
# campaigns, finds the content gaps, and writes a dated, prioritised worklist.
#
# It does NOT write blog posts or design creative. Those need an agent, and
# pretending a shell script can do them would produce exactly the filler this
# blog's editorial test exists to refuse ("would this be worth reading by
# someone who will never buy?"). What it produces instead is a worklist an agent
# or a human can pick up cold.
#
# The point is the alert. On 2026-09-05 this repo held 98 live posts, 7 films
# and 38 stills with ZERO posts scheduled, and nothing anywhere said so. A queue
# running dry is the failure that matters and it is silent by default.
#
# Usage:
#   scripts/delivery/run-cycle.sh              # full cycle, writes a report
#   scripts/delivery/run-cycle.sh --quick      # queue depth only, no crawl
#
# Exit codes:
#   0  cycle ran, queue has runway
#   3  cycle ran, QUEUE BELOW THRESHOLD — this is the actionable one
#   1  a step failed hard
set -uo pipefail

REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$REPO"
QUICK=0; [ "${1:-}" = "--quick" ] && QUICK=1

MIN_QUEUED_DAYS=3          # fewer days of runway than this is an alert
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
DAY="$(date -u +%Y-%m-%d)"
OUT="marketing/delivery-reports/$DAY"
mkdir -p "$OUT"
REPORT="$OUT/cycle-$STAMP.md"
exec > >(tee -a "$REPORT") 2>&1

echo "# Delivery cycle — $STAMP"
echo
echo "Repo: \`$(git rev-parse --short HEAD 2>/dev/null || echo unknown)\` on \`$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo ?)\`"
echo

# ---------------------------------------------------------------- queue depth
echo "## Queue"
QUEUE_JSON=$(docker exec postiz-postgres psql -U postiz-user -d postiz-db-local -tAc "
  select coalesce(count(*),0), coalesce(to_char(min(\"publishDate\"),'YYYY-MM-DD'),'-'),
         coalesce(to_char(max(\"publishDate\"),'YYYY-MM-DD'),'-')
  from \"Post\"
  where \"deletedAt\" is null and state='QUEUE' and \"publishDate\" > now();" 2>/dev/null | head -1)

QUEUED=$(echo "$QUEUE_JSON" | cut -d'|' -f1); QUEUED=${QUEUED:-0}
FIRST=$(echo "$QUEUE_JSON" | cut -d'|' -f2)
LAST=$(echo "$QUEUE_JSON" | cut -d'|' -f3)

if [ -z "$QUEUE_JSON" ]; then
  echo "- **Postiz unreachable.** Cannot read queue depth; treat this cycle as UNMEASURED, not healthy."
  QUEUE_STATE="unknown"
else
  echo "- Scheduled ahead: **$QUEUED** post(s)"
  echo "- Window: $FIRST → $LAST"
  RUNWAY=0
  if [ "$LAST" != "-" ]; then
    RUNWAY=$(( ( $(date -j -f "%Y-%m-%d" "$LAST" +%s 2>/dev/null || echo 0) - $(date +%s) ) / 86400 ))
    [ "$RUNWAY" -lt 0 ] && RUNWAY=0
  fi
  echo "- Runway: **$RUNWAY day(s)**"
  if [ "$QUEUED" -eq 0 ] || [ "$RUNWAY" -lt "$MIN_QUEUED_DAYS" ]; then
    echo
    echo "> **ALERT: the queue is below $MIN_QUEUED_DAYS days.** Everything downstream"
    echo "> of this is finished work sitting still. Arm a campaign."
    QUEUE_STATE="low"
  else
    QUEUE_STATE="ok"
  fi
fi
echo

# --------------------------------------------------------------- link health
if [ "$QUICK" -eq 0 ]; then
  echo "## Link health"
  if timeout 1800 python3 scripts/qa-content-links.py > "$OUT/links-$STAMP.txt" 2>&1; then
    tail -2 "$OUT/links-$STAMP.txt" | sed 's/^/- /'
  else
    echo "- **Link QA failed or timed out.** See \`links-$STAMP.txt\`."
  fi
  echo
fi

# ------------------------------------------------------------- content gaps
echo "## Content gaps"
if timeout 600 python3 scripts/suggest-next-blog-topic.py > "$OUT/gaps-$STAMP.txt" 2>&1; then
  head -25 "$OUT/gaps-$STAMP.txt" | sed 's/^/    /'
else
  echo "- Gap finder failed; see \`gaps-$STAMP.txt\`."
fi
echo

# ------------------------------------------------------------- campaign score
echo "## Campaigns"
for m in marketing/campaigns/*/manifest.json; do
  [ -f "$m" ] || continue
  slug=$(basename "$(dirname "$m")")
  # Read the posting schedule, which is what actually queues, rather than the
  # manifest — manifests carry audiences/creative/articles, not drops.
  sched="$(dirname "$m")/posting-schedule.json"
  drops=$(python3 -c "
import json
try:
    d=json.load(open('$sched'))
    v=d if isinstance(d,list) else (d.get('drops') or d.get('schedule') or d.get('posts') or [])
    print(len(v))
except Exception: print('0')" 2>/dev/null)
  echo "- \`$slug\` — $drops scheduled drop(s)"
done
echo

# ------------------------------------------------------------------ worklist
echo "## Worklist"
echo
if [ "$QUEUE_STATE" = "low" ]; then
  echo "1. **Arm a campaign.** Queue runway is the binding constraint right now."
  echo "   \`python3 scripts/queue-campaign-drops.py --campaign <slug>\`"
fi
echo "2. Write the next post from the gap list above, then publish it:"
echo "   \`python3 scripts/publish-blog-draft.py --draft <slug> --dry-run\` then \`--confirm\`."
echo "3. **A published post is not a live page.** This frontend has no \`^blog/\`"
echo "   rewrite; posts are static shells built by \`vite build && generate-seo-shells.mjs\`."
echo "   After publishing, run \`./scripts/deploy-frontend-godaddy.sh\` then \`--apply\`."
echo "4. Derive campaign creative from the post, never the reverse (SERIES_FIRST_CONTENT_ORIGIN_V1)."
echo
echo "---"
echo "Report: \`$REPORT\`"

[ "$QUEUE_STATE" = "low" ] && exit 3
exit 0
