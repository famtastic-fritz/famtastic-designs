#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
repo_dir="$(cd "$script_dir/.." && pwd)"
artifact_root="$repo_dir/artifacts/website-delivery-swarm"
customer_email="fritz.medine@gmail.com"

projects=(
  "bossy-nails-by-pri"
  "good-ole-candy-lady-shop"
  "famu-corner"
)

for project in "${projects[@]}"; do
  pilot="$repo_dir/website-delivery-swarm/pilots/$project/prove_pilot.mjs"
  package="$artifact_root/${project}-20260818"
  if [[ ! -f "$pilot" ]]; then
    echo "FAIL: missing prover for $project" >&2
    exit 1
  fi
  node "$pilot" "$package"
done

node "$repo_dir/website-delivery-swarm/benchmarks/prove-six-direction-batch.mjs" \
  "$artifact_root/st-lucie-three-project-benchmark-20260818" \
  "$customer_email" \
  "$artifact_root/bossy-nails-by-pri-20260818" \
  "$artifact_root/good-ole-candy-lady-shop-20260818" \
  "$artifact_root/famu-corner-20260818"

node "$repo_dir/website-delivery-swarm/library/archive-template-ideas.mjs" \
  "$artifact_root/template-library" \
  "$artifact_root" \
  "$repo_dir/website-delivery-swarm/pilots"

echo "PASS: three-project benchmark and private template preservation completed"
echo "Review: $artifact_root/st-lucie-three-project-benchmark-20260818/index.html"
