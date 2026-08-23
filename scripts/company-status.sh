#!/usr/bin/env bash
# FAMtastic Designs — company status generator.
# Reads the playbook recipes as the single source of status truth and emits
# the raw material for THE STANDUP REPORT. CEO (or any agent) runs:
#   ./scripts/company-status.sh
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PLAYBOOK="$REPO_ROOT/docs/playbook"

printf 'COMPANY STATUS — generated %s\n' "$(date '+%Y-%m-%d %H:%M %Z')"
printf 'Repo: %s @ %s\n\n' "$(basename "$REPO_ROOT")" "$(git -C "$REPO_ROOT" rev-parse --short HEAD)"

printf 'RECIPE STEP BOARD (status counts per file)\n'
total_done=0; total_progress=0; total_blocked=0; total_open=0
for recipe in "$PLAYBOOK"/RECIPES/*.md; do
  name="$(basename "$recipe" .md)"
  done_n=$(grep -c '✅' "$recipe" || true)
  prog_n=$(grep -c '🔄\|GATE→' "$recipe" || true)
  blocked_n=$(grep -c '⚠️' "$recipe" || true)
  open_n=$(grep -c '| ☐ |' "$recipe" || true)
  printf '  %-32s ✅%-3s 🔄%-3s ⚠️%-3s ☐%-3s\n' "$name" "$done_n" "$prog_n" "$blocked_n" "$open_n"
  total_done=$((total_done+done_n)); total_progress=$((total_progress+prog_n))
  total_blocked=$((total_blocked+blocked_n)); total_open=$((total_open+open_n))
done
printf '  %-32s ✅%-3s 🔄%-3s ⚠️%-3s ☐%-3s\n\n' "TOTAL" "$total_done" "$total_progress" "$total_blocked" "$total_open"

printf 'OPEN ⚠️ STEPS (revenue-critical first read)\n'
grep -Hn '⚠️' "$PLAYBOOK"/RECIPES/*.md | grep -v '⚠️.*✅' | sed 's|.*/RECIPES/||' | head -20 || true

printf '\nWORKFORCE (active roster rows)\n'
awk '/^\| CEO /{on=1} /^## Vacancies/{on=0} on && /^\|/ && !/\|---/' "$PLAYBOOK/ROSTER.md" | awk -F'|' 'NF > 4 {gsub(/^ +| +$/,"",$2); if ($2 != "") print "  - " $2}' || true

printf '\nRECENT RECEIPTS (last 8 commits)\n'
git -C "$REPO_ROOT" log --oneline -8

printf '\nUNCOMMITTED STATE\n'
dirty=$(git -C "$REPO_ROOT" status --porcelain | wc -l | tr -d ' ')
if [ "$dirty" = "0" ]; then echo "  clean"; else git -C "$REPO_ROOT" status --short | sed 's/^/  /'; fi
