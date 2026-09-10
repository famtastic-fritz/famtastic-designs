#!/usr/bin/env bash
set -euo pipefail

ROOT="$(mktemp -d "${TMPDIR:-/tmp}/protected-staging-test.XXXXXX")"
trap 'rm -rf -- "$ROOT"' EXIT
mkdir -p "$ROOT/repo/scripts" "$ROOT/repo/backend/web/modules/custom/famtastic_pipeline/src/Service" "$ROOT/repo/backend/web/modules/custom/famtastic_pipeline/src/EventSubscriber" "$ROOT/repo/backend/web/modules/custom/famtastic_pipeline/src/Plugin/Mail"
cp "$(dirname "$0")/deploy-protected-staging.sh" "$ROOT/repo/scripts/"
chmod +x "$ROOT/repo/scripts/deploy-protected-staging.sh"
touch "$ROOT/repo/backend/composer.lock"
touch "$ROOT/repo/backend/web/modules/custom/famtastic_pipeline/src/Service/DisabledPaymentGateway.php"
touch "$ROOT/repo/backend/web/modules/custom/famtastic_pipeline/famtastic_pipeline.module"
touch "$ROOT/repo/backend/web/modules/custom/famtastic_pipeline/src/EventSubscriber/ProtectedStagingRequestSubscriber.php"
touch "$ROOT/repo/backend/web/modules/custom/famtastic_pipeline/src/Plugin/Mail/FamtasticBlackholeMail.php"
printf 'fixture\n' > "$ROOT/repo/README.md"
git -C "$ROOT/repo" init -q
git -C "$ROOT/repo" config user.email test@example.invalid
git -C "$ROOT/repo" config user.name protected-staging-test
git -C "$ROOT/repo" add .
git -C "$ROOT/repo" commit -qm fixture
git init --bare -q "$ROOT/origin.git"
git -C "$ROOT/repo" remote add origin "$ROOT/origin.git"
git -C "$ROOT/repo" push -q origin HEAD:refs/heads/staging-test
git -C "$ROOT/repo" checkout -qb staging-test
SHA="$(git -C "$ROOT/repo" rev-parse HEAD)"
EVIDENCE="$ROOT/evidence"

(
  cd "$ROOT/repo"
  env FAMTASTIC_STAGING_REF=refs/heads/staging-test FAMTASTIC_STAGING_REPOSITORY_URL="$ROOT/origin.git" FAMTASTIC_STAGING_EVIDENCE_ROOT="$EVIDENCE" ./scripts/deploy-protected-staging.sh --dry-run > "$ROOT/pass.out"
)
grep -q "Protected staging preflight passed: $SHA" "$ROOT/pass.out"
grep -q 'Exact pushed ref: refs/heads/staging-test' "$ROOT/pass.out"
test -f "$EVIDENCE/$SHA-"*'.release.json'

if (
  cd "$ROOT/repo"
  env FAMTASTIC_STAGING_REF=refs/heads/staging-test FAMTASTIC_STAGING_REPOSITORY_URL="$ROOT/origin.git" FAMTASTIC_STAGING_APPLY_CONFIRM="DEPLOY_PROTECTED_STAGING:$SHA" ./scripts/deploy-protected-staging.sh --apply
) >/dev/null 2>&1; then
  echo 'expected apply to reject absent cPanel target' >&2
  exit 1
fi

rm "$ROOT/repo/backend/web/modules/custom/famtastic_pipeline/src/EventSubscriber/ProtectedStagingRequestSubscriber.php"
if (
  cd "$ROOT/repo"
  env FAMTASTIC_STAGING_REF=refs/heads/staging-test FAMTASTIC_STAGING_REPOSITORY_URL="$ROOT/origin.git" ./scripts/deploy-protected-staging.sh --preflight
) >/dev/null 2>&1; then
  echo 'expected protected-staging route guard refusal' >&2
  exit 1
fi

echo 'protected staging tests passed'

grep -F 'payload.get("data") or (payload.get("result") or {}).get("data")' "$ROOT/repo/scripts/deploy-protected-staging.sh" >/dev/null
