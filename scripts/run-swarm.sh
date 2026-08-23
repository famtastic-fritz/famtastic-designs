#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="${1:-$(cd "$(dirname "$0")/.." && pwd)}"
OUTPUT="${2:-$REPO_ROOT/artifacts/website-delivery-swarm/latest}"

exec "$REPO_ROOT/scripts/run-website-delivery-swarm.sh" "$OUTPUT"
