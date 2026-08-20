#!/usr/bin/env bash
set -euo pipefail

PILOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$PILOT_DIR/../../.." && pwd)"
OUTPUT="${1:-$REPO_ROOT/artifacts/website-delivery-swarm/bossy-nails-by-pri-20260818}"

node "$PILOT_DIR/prove_pilot.mjs" "$OUTPUT"
