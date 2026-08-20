#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUTPUT="${1:-$REPO_ROOT/artifacts/website-delivery-swarm/fort-pierce-black-church-six-20260818}"
PILOT="$REPO_ROOT/website-delivery-swarm/pilots/fort-pierce-black-church-showcase/prove_pilot.mjs"

if command -v fnm >/dev/null 2>&1; then
  exec fnm exec --using=22 node "$PILOT" "$OUTPUT"
fi

major="$(node -p 'process.versions.node.split(".")[0]')"
if [[ "$major" != "22" ]]; then
  echo "Node 22 is required; current major is $major and fnm is unavailable." >&2
  exit 2
fi
exec node "$PILOT" "$OUTPUT"
