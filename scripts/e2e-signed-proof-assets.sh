#!/usr/bin/env bash
set -euo pipefail

# Fresh, local-only signed proof asset acceptance. It deliberately uses a
# SQLite sandbox, synthetic @example.test records, held mail only, and no
# production host, creative provider, SMTP send, or payment action.

repo_root="$(cd "$(dirname "$0")/.." && pwd)"
test_name="famtastic-signed-proof-assets"
sandbox="$(mktemp -d "${TMPDIR:-/tmp}/${test_name}.XXXXXX")"
run_id="$(date +%s)-$$"
port=$((24000 + ($$ % 1000)))
server_pid=""

cleanup() {
  if [[ -n "$server_pid" ]]; then
    kill "$server_pid" 2>/dev/null || true
    wait "$server_pid" 2>/dev/null || true
  fi
  if [[ "${FAMTASTIC_E2E_KEEP_SANDBOX:-0}" == "1" ]]; then
    echo "Retained sandbox: $sandbox" >&2
  else
    case "$sandbox" in
      "${TMPDIR:-/tmp}/${test_name}."*)
        chmod -R u+rwX "$sandbox" 2>/dev/null || true
        rm -rf "$sandbox"
        ;;
      *) echo "Refusing to remove unexpected sandbox: $sandbox" >&2 ;;
    esac
  fi
}
trap cleanup EXIT

for command_name in curl jq rsync shasum; do
  command -v "$command_name" >/dev/null || { echo "Missing required command: $command_name" >&2; exit 1; }
done
test -x "$repo_root/backend/vendor/bin/drush" || { echo "Run composer install in backend before this local acceptance test." >&2; exit 1; }
runtime_vendor="$(cd -P "$repo_root/backend/vendor" && pwd)"
runtime_backend="$(cd "$runtime_vendor/.." && pwd)"
test -d "$runtime_backend/web/core" || { echo "The installed Drupal runtime is missing web/core." >&2; exit 1; }

mkdir -p "$sandbox/backend"
rsync -a --exclude vendor --exclude private --exclude 'web/sites/default/files' "$repo_root/backend/" "$sandbox/backend/"
# A worktree intentionally does not track Composer-scaffolded Drupal runtime
# files. Copy those dependencies into the disposable sandbox, then retain the
# worktree's custom module files as the code under test.
rsync -aL "$repo_root/backend/vendor/" "$sandbox/backend/vendor/"
rsync -a "$runtime_backend/web/core/" "$sandbox/backend/web/core/"
rsync -a --ignore-existing "$runtime_backend/web/modules/" "$sandbox/backend/web/modules/"
rsync -a --ignore-existing "$runtime_backend/web/profiles/" "$sandbox/backend/web/profiles/"
rsync -a --ignore-existing "$runtime_backend/web/themes/" "$sandbox/backend/web/themes/"
for runtime_file in .ht.router.php .htaccess autoload.php autoload_runtime.php index.php robots.txt update.php; do
  cp "$runtime_backend/web/$runtime_file" "$sandbox/backend/web/$runtime_file"
done
cp "$runtime_backend/web/sites/default/default.settings.php" "$sandbox/backend/web/sites/default/default.settings.php"
chmod -R u+rwX "$sandbox/backend/web/sites/default"
mkdir -p "$sandbox/backend/web/sites/default/files" "$sandbox/backend/private"

# The tracked developer settings file has a convenience SQLite connection for
# an already-installed local checkout. Remove that one generated connection in
# the disposable copy so Drupal's installer can create a truly new SQLite DB.
perl -0pi -e 's/\n\$databases\['\''default'\''\]\['\''default'\''\] = array \(\n.*?\n\);\n/\n/s' \
  "$sandbox/backend/web/sites/default/settings.php"

drush=("$sandbox/backend/vendor/bin/drush" "--root=$sandbox/backend/web")
# Keep this acceptance test independent of setup.sh's optional developer
# recipes. The signed asset contract needs only a fresh standard install and
# this custom module; importing editorial content types is unrelated.
"${drush[@]}" site:install standard \
  --db-url="sqlite://sites/default/files/.ht.sqlite" \
  --account-name=admin \
  --account-pass=admin \
  --account-mail=admin@famtastic.local \
  --site-name="FAMtastic Signed Asset Fixture" \
  --site-mail=no-reply@famtastic.local \
  -y >/dev/null
"${drush[@]}" en -y famtastic_pipeline >/dev/null
"${drush[@]}" updb -y >/dev/null
"${drush[@]}" cr >/dev/null

state="$sandbox/state.json"
FAMTASTIC_E2E_STATE="$state" FAMTASTIC_E2E_RUN="$run_id" \
  "${drush[@]}" php:script "$sandbox/backend/web/modules/custom/famtastic_pipeline/tests/fixtures/e2e-signed-proof-assets.php"
jq -e '.classification == "local_fixture_only" and .rich.public_id and .legacy.public_id' "$state" >/dev/null

(
  cd "$sandbox/backend"
  exec "${drush[@]}" runserver "127.0.0.1:$port" >"$sandbox/drupal.log" 2>&1
) &
server_pid=$!
for _ in $(seq 1 80); do
  curl -sf "http://127.0.0.1:$port/robots.txt" >/dev/null 2>&1 && break
  sleep 0.25
done
curl -sf "http://127.0.0.1:$port/robots.txt" >/dev/null || { echo "Timed out starting local Drupal." >&2; exit 1; }

rich_public="$(jq -r '.rich.public_id' "$state")"
rich_signature="$(jq -r '.rich.signature' "$state")"
legacy_public="$(jq -r '.legacy.public_id' "$state")"
legacy_signature="$(jq -r '.legacy.signature' "$state")"
base="http://127.0.0.1:$port/api/public-preview"
rich_proof="$base/$rich_public/$rich_signature/proofs/a"
rich_asset="$rich_proof/assets/hero-a.png"
legacy_proof="$base/$legacy_public/$legacy_signature/proofs/a"

curl -sS -D "$sandbox/rich-proof.headers" "$rich_proof" >"$sandbox/rich-proof.html"
test "$(head -1 "$sandbox/rich-proof.headers" | awk '{print $2}')" = "200"
grep -F "<base href=\"/web/api/public-preview/$rich_public/$rich_signature/proofs/a/\">" "$sandbox/rich-proof.html" >/dev/null
test "$(shasum -a 256 "$sandbox/backend/web/proofs/$(jq -r '.rich.campaign_id' "$state")/a/index.html" | awk '{print $1}')" = "$(jq -r '.rich.proof_sha256' "$state")"
curl -sS -D "$sandbox/rich-asset.headers" "$rich_asset" >"$sandbox/rich-asset.png"
test "$(head -1 "$sandbox/rich-asset.headers" | awk '{print $2}')" = "200"
grep -i '^Content-Type: image/png' "$sandbox/rich-asset.headers" >/dev/null
test "$(shasum -a 256 "$sandbox/rich-asset.png" | awk '{print $1}')" = "$(jq -r '.rich.asset_sha256' "$state")"
# The stored `assets/...` reference combines with the injected proof base only
# once; a base ending in `/assets/` would incorrectly request `/assets/assets`.
grep -F 'src="assets/hero-a.png"' "$sandbox/rich-proof.html" >/dev/null
test "$(curl -s -o /dev/null -w '%{http_code}' "$rich_proof/assets/hero-a.png")" = "200"

# The local-only promoter packs only explicitly declared assets into the
# callback contract; this dry run never opens SSH, SCP, a provider, or a
# network connection.
bundle="$sandbox/promoter-bundle"
mkdir -p "$bundle"
job_id="$(jq -r '.rich.job_id' "$state")"
campaign_id="$(jq -r '.rich.campaign_id' "$state")"
jq -n \
  --arg job_id "$job_id" \
  --arg campaign_id "$campaign_id" \
  --arg event_id "signed-assets-promoter-$run_id" \
  '{job_id: $job_id, campaign_id: $campaign_id, event_id: $event_id}' > "$bundle/manifest.json"
for direction in a b c; do
  mkdir -p "$bundle/$direction/assets"
  cp "$sandbox/backend/web/proofs/$campaign_id/$direction/index.html" "$bundle/$direction/index.html"
  cp "$sandbox/backend/web/proofs/$campaign_id/$direction/thumbnail.png" "$bundle/$direction/thumbnail.png"
  cp "$sandbox/backend/web/proofs/$campaign_id/$direction/assets/hero-$direction.png" "$bundle/$direction/assets/hero-$direction.png"
  jq -n \
    --arg asset_id "hero-$direction" \
    --arg relative_path "hero-$direction.png" \
    --arg media_type "image/png" \
    '[{asset_id: $asset_id, relative_path: $relative_path, media_type: $media_type}]' > "$bundle/$direction/assets.json"
done
"$repo_root/scripts/promote-local-proof-godaddy.sh" "$bundle" > "$sandbox/promoter-dry-run.txt"
grep -F 'variants: 3' "$sandbox/promoter-dry-run.txt" >/dev/null
grep -F 'Dry-run passed. No production files or data changed.' "$sandbox/promoter-dry-run.txt" >/dev/null

# Legacy assetless rooms get no response rewrite and remain stageable without a
# source_lane marker or asset entries.
curl -sS -D "$sandbox/legacy-proof.headers" "$legacy_proof" >"$sandbox/legacy-proof.html"
test "$(head -1 "$sandbox/legacy-proof.headers" | awk '{print $2}')" = "200"
if grep -q '<base href=' "$sandbox/legacy-proof.html"; then
  echo "Legacy assetless proof unexpectedly received an asset base." >&2
  exit 1
fi

# A changed byte makes the signed controller fail closed even before revoke.
asset_path="$(jq -r '.rich.asset_path' "$state")"
printf 'tampered' > "$asset_path"
test "$(curl -s -o /dev/null -w '%{http_code}' "$rich_asset")" = "404"

# Revoke rotates the share version; the previous proof and asset links both
# fail without exposing a mutable filename or database detail.
delivery_id="$(jq -r '.rich.delivery_id' "$state")"
DELIVERY_ID="$delivery_id" "${drush[@]}" eval '\Drupal::service("famtastic_pipeline.public_preview_deliveries")->revoke((int) getenv("DELIVERY_ID"), 1);'
test "$(curl -s -o /dev/null -w '%{http_code}' "$rich_proof")" = "404"
test "$(curl -s -o /dev/null -w '%{http_code}' "$rich_asset")" = "404"

echo "PASS: signed proof assets reject unsafe input, preserve stored HTML, serve only through a current signed room, fail on tamper, revoke immediately, and retain legacy assetless compatibility."
echo "This is a local SQLite fixture only; no provider, SMTP, customer, payment, deployment, or production state was used."
