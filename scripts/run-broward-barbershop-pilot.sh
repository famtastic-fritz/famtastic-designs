#!/usr/bin/env bash
set -euo pipefail
REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
node "$REPO_ROOT/website-delivery-swarm/pilots/broward-barbershop/prove_pilot.mjs"
