#!/usr/bin/env bash
set -euo pipefail
REPO_ROOT="${1:?Usage: run-swarm.sh <repo-path> [output-path]}"
OUTPUT="${2:-$REPO_ROOT/artifacts/website-delivery-swarm/latest}"
"$REPO_ROOT/scripts/run-website-delivery-swarm.sh" "$OUTPUT"
