#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

node "$script_dir/prove-lab.mjs"

echo "PASS: FAMtastic Lab source and deterministic browser proof"
