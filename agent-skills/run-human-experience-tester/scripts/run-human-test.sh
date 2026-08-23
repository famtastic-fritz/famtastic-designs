#!/usr/bin/env bash
set -euo pipefail
REPO_ROOT="${1:?Usage: run-human-test.sh <repo-path> <input-json> [life-path]}"
INPUT="${2:?Input JSON is required}"
LIFE_PATH="${3:-}"
if [[ -n "$LIFE_PATH" ]]; then
  python3 "$REPO_ROOT/website-delivery-swarm/human_tester.py" --input "$INPUT" --opt-in --life-path "$LIFE_PATH"
else
  python3 "$REPO_ROOT/website-delivery-swarm/human_tester.py" --input "$INPUT"
fi
