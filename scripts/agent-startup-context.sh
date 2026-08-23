#!/usr/bin/env bash
set -euo pipefail

# Read-only orientation for Codex, Claude Code, Shay, and other CLI agents.
# It intentionally performs no fetch, install, build, deployment, provider,
# customer-data, or production action.

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

required_files=(
  "AGENTS.md"
  "docs/AGENT_OPERATING_CONTRACT.md"
  "docs/architecture/FAMTASTIC_PREVIEW_TO_BUILD_BOUNDARY_V1.md"
  "docs/architecture/FAMTASTIC_PREVIEW_DELIVERY_OPERATING_ROUTINE_V1.md"
)

for required_file in "${required_files[@]}"; do
  if [[ ! -f "$REPO_ROOT/$required_file" ]]; then
    echo "Missing required FAMtastic startup contract: $required_file" >&2
    exit 1
  fi
done

branch="$(git -C "$REPO_ROOT" branch --show-current 2>/dev/null || true)"
branch="${branch:-detached-or-not-a-git-worktree}"
dirty_count="$(git -C "$REPO_ROOT" status --porcelain=v1 2>/dev/null | wc -l | tr -d ' ')"

cat <<EOF
FAMtastic agent startup context (read-only)

Git branch: $branch
Worktree entries already changed: $dirty_count

Read before customer, preview, email, proof, payment, or deployment work:
  1. AGENTS.md
  2. docs/AGENT_OPERATING_CONTRACT.md
  3. docs/architecture/FAMTASTIC_PREVIEW_TO_BUILD_BOUNDARY_V1.md
  4. docs/architecture/FAMTASTIC_PREVIEW_DELIVERY_OPERATING_ROUTINE_V1.md

Non-negotiable ownership:
  FAMtastic owns preview generation, proof slots/artifacts, share links,
  owner review, transactional outbox/email, and customer/project truth.
  Site Studio only receives a selected immutable build packet and returns a
  later build-success packet. It never delivers previews to customers.

Proof-package rule:
  Public/unregistered default: exactly 3 (Safe, Medium FAMtastic, Ultra).
  Verified detailed-request default: up to 6 (1 Normal, 1 Medium, 4 Ultra).
  Selected-direction revision default: exactly 1.
  The selected, versioned proof-package profile—not login state, UI copy, or
  a worker fallback—sets the actual count, mix, labels, access, and send gate.
  Freeze that exact direction contract at dispatch. Do not promise arbitrary
  package counts until the chosen runtime route supports and tests them.

Legacy-compatible production promotion:
  scripts/fetch-local-proof-job-godaddy.sh --apply
    -> private offline SSH bundle -> controlled local proof build
    -> scripts/promote-local-proof-godaddy.sh BUNDLE --apply
    -> FAMtastic Drupal import, artifact slots, owner gate, outbox/email.
  This is a FAMtastic-owned compatibility route despite old “Site Studio”
  wording in some script output. It supports only its declared contract and
  never authorizes a Site Studio preview host, direct SMTP, or a customer send.

No --apply, email, payment, grant, or deployment action is authorized by this
script. Inspect the exact run's Build DNA, QA/review state, owner gate, and
working-tree changes before acting.
EOF
