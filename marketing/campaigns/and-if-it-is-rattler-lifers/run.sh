#!/usr/bin/env bash
set -euo pipefail

campaign_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
repo_root="$(cd "$campaign_dir/../../.." && pwd)"

jq empty \
  "$campaign_dir/brief.json" \
  "$campaign_dir/formula.json" \
  "$campaign_dir/research.json" \
  "$campaign_dir/prompts.json" \
  "$campaign_dir/image-routing.json" \
  "$campaign_dir/manifest.json" \
  "$campaign_dir/evidence/live-publication.json" \
  "$campaign_dir/evidence/run-ledger.json" \
  "$campaign_dir/evidence/visual-review.json"

node "$campaign_dir/validate.mjs"
node "$campaign_dir/prove.mjs"

(
  cd "$campaign_dir"
  find . -type f \
    ! -path './evidence/artifact-hashes.sha256' \
    -print0 | sort -z | xargs -0 shasum -a 256 > evidence/artifact-hashes.sha256
)

echo "PASS: AND IF IT IS? campaign proof"
echo "Evidence: $campaign_dir/evidence/browser-results.json"
echo "Review: $campaign_dir/evidence/visual-review.json"
echo "Ledger: $campaign_dir/evidence/run-ledger.json"
echo "Repository: $repo_root"
