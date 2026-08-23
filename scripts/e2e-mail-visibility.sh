#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
RUN_ID="${FAMTASTIC_SYNTHETIC_RUN_ID:-$(date +%s)-$$}"
EVIDENCE_DIR="$REPO_ROOT/.artifacts/mail-visibility/$RUN_ID"
mkdir -p "$EVIDENCE_DIR"
FAMTASTIC_SYNTHETIC_RUN_ID="$RUN_ID" \
FAMTASTIC_MAIL_VISIBILITY_EVIDENCE_DIR="$EVIDENCE_DIR" \
FAMTASTIC_TRANSACTIONAL_EMAIL_TRANSPORT=memory \
FAMTASTIC_TRANSACTIONAL_EMAIL_CAPTURE="$EVIDENCE_DIR/outbox-capture.jsonl" \
  "$REPO_ROOT/backend/vendor/bin/drush" --root="$REPO_ROOT/backend/web" php:script "$REPO_ROOT/backend/scripts/e2e-mail-visibility.php"
test -s "$EVIDENCE_DIR/evidence.json"
jq -e '.status == "passed" and ([.checks[]] | all)' "$EVIDENCE_DIR/evidence.json" >/dev/null
echo "PASS: replies metric, proof-decision notifications, and attention banner evidence is green."
