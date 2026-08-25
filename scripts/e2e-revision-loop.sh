#!/usr/bin/env bash
set -euo pipefail
repo_root="$(cd "$(dirname "$0")/.." && pwd)"
run_id="${FAMTASTIC_SYNTHETIC_RUN_ID:-$(date +%s)-$$}"
evidence_dir="$repo_root/.artifacts/revision-loop/$run_id"
capture="$evidence_dir/mail.jsonl"
mkdir -p "$evidence_dir"
FAMTASTIC_SYNTHETIC_RUN_ID="$run_id" \
FAMTASTIC_REVISION_LOOP_EVIDENCE_DIR="$evidence_dir" \
FAMTASTIC_TRANSACTIONAL_EMAIL_TRANSPORT=memory \
FAMTASTIC_TRANSACTIONAL_EMAIL_CAPTURE="$capture" \
  "$repo_root/backend/vendor/bin/drush" --root="$repo_root/backend/web" php:script "$repo_root/backend/scripts/e2e-revision-loop.php"
test -s "$evidence_dir/evidence.json"
jq -e '.status == "passed" and ([.checks[]] | all)' "$evidence_dir/evidence.json" >/dev/null
echo "PASS: revision loop evidence is complete and every assertion is true."
