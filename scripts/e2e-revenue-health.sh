#!/usr/bin/env bash
set -euo pipefail

repo_root="$(cd "$(dirname "$0")/.." && pwd)"
run_id="${FAMTASTIC_SYNTHETIC_RUN_ID:-$(date +%s)-$$}"

# This acceptance script creates and removes only local synthetic Drupal rows.
# It does not invoke lifecycle dispatch, send mail, call a provider, or charge.
FAMTASTIC_SYNTHETIC_RUN_ID="$run_id" \
  "$repo_root/backend/vendor/bin/drush" --root="$repo_root/backend/web" php:script "$repo_root/backend/scripts/e2e-revenue-health.php"
