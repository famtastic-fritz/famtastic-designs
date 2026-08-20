#!/usr/bin/env bash
set -euo pipefail
REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUTPUT="${1:-$REPO_ROOT/artifacts/website-delivery-swarm/latest}"
node "$REPO_ROOT/website-delivery-swarm/prove.mjs" "$OUTPUT"
