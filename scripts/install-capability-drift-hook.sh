#!/usr/bin/env bash
set -euo pipefail
repo_root="$(cd "$(dirname "$0")/.." && pwd)"
if [[ "${1:-}" != "--apply" ]]; then
  echo "DRY RUN: git -C $repo_root config core.hooksPath .githooks"
  echo "Use --apply to enable the repository-owned capability drift hook."
  exit 0
fi
git -C "$repo_root" config core.hooksPath .githooks
echo "PASS: repository hook path set to .githooks"
