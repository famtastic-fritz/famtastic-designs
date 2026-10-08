#!/usr/bin/env bash
set -euo pipefail
repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
apply=0
case "${1:-}" in '') ;; --apply) apply=1 ;; *) echo 'Usage: deploy-acquisition-batch50.sh [--apply]' >&2; exit 2 ;; esac
cd "$repo_root"
[[ -z "$(git status --porcelain)" ]] || { echo 'Refusing dirty source'; exit 1; }
commit="$(git rev-parse HEAD)"
[[ "$(git ls-remote origin refs/heads/main | awk '{print $1}')" == "$commit" ]] || { echo 'HEAD must be current main'; exit 1; }
ssh -T xrdj7j99xhzt@p3plzcpnl497512.prod.phx3.secureserver.net bash -s -- "$commit" "$apply" <<'REMOTE'
set -euo pipefail
commit="$1"; apply="$2"
[[ "$commit" =~ ^[a-f0-9]{40}$ && "$apply" =~ ^[01]$ ]] || exit 2
mirror="$HOME/deploy/famtastic-designs/repository.git"
git --git-dir="$mirror" fetch origin main
[[ "$(git --git-dir="$mirror" rev-parse FETCH_HEAD)" == "$commit" ]] || exit 1
source="$HOME/deploy/famtastic-designs/releases/$commit/batch50-source"
mkdir -p "$source"
git --git-dir="$mirror" archive "$commit" scripts/acquisition-batch50-promote.php backend/web/modules/custom/famtastic_pipeline/src/Service/AcquisitionWindowExecutor.php | tar -x -C "$source"
printf '%s\n' "$commit" > "$source/commit.txt"
baseline="$source/executor-baseline.php"
git --git-dir="$mirror" show d27b6af475f394ad240e3a2ad02af657c1cd1b41:backend/web/modules/custom/famtastic_pipeline/src/Service/AcquisitionWindowExecutor.php > "$baseline"
/usr/local/bin/php -l "$source/scripts/acquisition-batch50-promote.php"
/usr/local/bin/php -l "$source/backend/web/modules/custom/famtastic_pipeline/src/Service/AcquisitionWindowExecutor.php"
cd "$HOME/public_html"
FAMTASTIC_BATCH50_SOURCE_COMMIT="$commit" FAMTASTIC_BATCH50_APPLY="$apply" /usr/local/bin/php vendor/bin/drush.php php:script "$source/scripts/acquisition-batch50-promote.php"
REMOTE
