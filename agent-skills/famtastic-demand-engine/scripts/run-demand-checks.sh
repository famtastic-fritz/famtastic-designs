#!/usr/bin/env bash
set -euo pipefail

repo=${1:-}
if [[ -z "$repo" || "$repo" != /* ]]; then
  echo "usage: run-demand-checks.sh /absolute/path/to/site-famtastic-designs" >&2
  exit 2
fi

git -C "$repo" rev-parse --is-inside-work-tree >/dev/null
python3 "$repo/scripts/validate-demand-content.py"
npm --prefix "$repo/frontend" run build
echo "Demand engine checks passed."
