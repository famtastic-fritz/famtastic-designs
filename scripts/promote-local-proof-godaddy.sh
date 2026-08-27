#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
SSH_TARGET="${FAMTASTIC_SSH_TARGET:-xrdj7j99xhzt@p3plzcpnl497512.prod.phx3.secureserver.net}"
REMOTE_ROOT="${FAMTASTIC_REMOTE_ROOT:-public_html}"
APPLY=false
SHOWCASE=false
LOCAL_IMPORT=false
BUNDLE_DIR=""
DIRECTIONS="a,b,c"

usage() {
  cat <<'USAGE'
Usage: ./scripts/promote-local-proof-godaddy.sh BUNDLE_DIR [--directions=a,b,c|d,e,f] [--showcase] [--local|--apply]

BUNDLE_DIR must contain manifest.json plus the selected three direction
directories. `--showcase` is shorthand for `--directions=d,e,f`. Each direction
requires index.html and thumbnail.png or thumbnail.jpg. A direction may also
include `assets.json` plus its declared files under `assets/`; see
docs/SITE_STUDIO_INTEGRATION.md for the signed proof-asset shape. Dry-run is the default.
--apply uploads only the validated callback payload to a private server inbox
and imports it through Drupal's exact-three callback validator.
--local imports into the repository's local Drupal database for acceptance
testing and cannot be combined with --apply.

manifest.json requires campaign_id, job_id, and event_id. Optional telemetry:
provider, agent_name, flow_key, task_key, prompt_snapshot, input_snapshot,
source_sha.
USAGE
}

for argument in "$@"; do
  case "$argument" in
    --apply) APPLY=true ;;
    --directions=*) DIRECTIONS="${argument#*=}" ;;
    --showcase) SHOWCASE=true; DIRECTIONS="d,e,f" ;;
    --local) LOCAL_IMPORT=true ;;
    -h|--help) usage; exit 0 ;;
    *)
      if [[ -n "$BUNDLE_DIR" ]]; then
        usage >&2
        exit 2
      fi
      BUNDLE_DIR="$argument"
      ;;
  esac
done

if [[ "$APPLY" == true && "$LOCAL_IMPORT" == true ]]; then
  echo "--local and --apply are mutually exclusive." >&2
  exit 2
fi

[[ -n "$BUNDLE_DIR" ]] || { usage >&2; exit 2; }
[[ "$DIRECTIONS" == "a,b,c" || "$DIRECTIONS" == "d,e,f" ]] || { echo "Directions must be a,b,c or d,e,f." >&2; exit 2; }
BUNDLE_DIR="$(cd "$BUNDLE_DIR" && pwd)"
manifest="$BUNDLE_DIR/manifest.json"

for command_name in jq openssl base64 rg ssh scp; do
  command -v "$command_name" >/dev/null || { echo "Missing required command: $command_name" >&2; exit 1; }
done
jq -e '
  (.campaign_id | type == "string" and length > 0) and
  (.job_id | type == "string" and length > 0) and
  (.event_id | type == "string" and length > 0) and
  (.provider == null or (.provider | type == "string" and length <= 128)) and
  (.agent_name == null or (.agent_name | type == "string" and length <= 128)) and
  (.flow_key == null or (.flow_key | type == "string" and length <= 128)) and
  (.task_key == null or (.task_key | type == "string" and length <= 128)) and
  (.prompt_snapshot == null or (.prompt_snapshot | type == "string" and length <= 100000)) and
  (.input_snapshot == null or (.input_snapshot | type == "object")) and
  (.source_sha == null or (.source_sha | type == "string" and (length == 0 or test("^[a-f0-9]{40}$") or test("^[a-f0-9]{64}$"))))
' "$manifest" >/dev/null

campaign_id="$(jq -r '.campaign_id' "$manifest")"
job_id="$(jq -r '.job_id' "$manifest")"
event_id="$(jq -r '.event_id' "$manifest")"
[[ "$campaign_id" =~ ^pc-[a-z0-9-]+$ ]] || { echo "Invalid campaign_id." >&2; exit 1; }
if [[ "$DIRECTIONS" == "d,e,f" ]]; then
  [[ "$job_id" =~ ^local-showcase-[a-f0-9]{32}$ ]] || { echo "Showcase promotion requires a local-showcase job_id." >&2; exit 1; }
  directions=(d e f)
  proof_set="FAMtastic showcase"
else
  [[ "$job_id" =~ ^local-(refresh-)?[a-f0-9]{32}$ ]] || { echo "Invalid local job_id." >&2; exit 1; }
  directions=(a b c)
  proof_set="core"
fi
[[ "$event_id" =~ ^[a-zA-Z0-9._:-]+$ ]] || { echo "Invalid event_id." >&2; exit 1; }

temporary_dir="$(mktemp -d /tmp/famtastic-proof-promotion.XXXXXX)"
payload="$temporary_dir/payload.json"
max_asset_bytes=1500000
max_assets_per_variant=4
max_asset_bytes_per_variant=3000000
max_callback_bytes=$((24 * 1024 * 1024))
cleanup() { rm -rf "$temporary_dir"; }
trap cleanup EXIT

variants_file="$temporary_dir/variants.json"
printf '[]\n' > "$variants_file"
IFS=',' read -r -a selected_directions <<< "$DIRECTIONS"
for direction in "${directions[@]}"; do
  html_path="$BUNDLE_DIR/$direction/index.html"
  [[ -s "$html_path" ]] || { echo "Missing $direction/index.html" >&2; exit 1; }
  html_bytes="$(wc -c < "$html_path" | tr -d ' ')"
  [[ "$html_bytes" -le 500000 ]] || { echo "$direction/index.html exceeds 500 KB." >&2; exit 1; }
  if rg -qi '<(script|iframe|object|embed|base)\b|\son[a-z]+\s*=|javascript\s*:' "$html_path"; then
    echo "$direction/index.html contains disallowed active content." >&2
    exit 1
  fi
  thumbnail_path=""
  media_type=""
  for candidate in "$BUNDLE_DIR/$direction/thumbnail.png" "$BUNDLE_DIR/$direction/thumbnail.jpg"; do
    if [[ -s "$candidate" ]]; then
      thumbnail_path="$candidate"
      [[ "$candidate" == *.png ]] && media_type="image/png" || media_type="image/jpeg"
      break
    fi
  done
  [[ -n "$thumbnail_path" ]] || { echo "Missing thumbnail for direction $direction." >&2; exit 1; }
  thumbnail_bytes="$(wc -c < "$thumbnail_path" | tr -d ' ')"
  [[ "$thumbnail_bytes" -le 1500000 ]] || { echo "Thumbnail $direction exceeds 1.5 MB." >&2; exit 1; }
  design_dna='{}'
  [[ ! -f "$BUNDLE_DIR/$direction/design-dna.json" ]] || design_dna="$(jq -c '.' "$BUNDLE_DIR/$direction/design-dna.json")"
  telemetry="$(jq -c '{provider: (.provider // "site_studio_local"), agent_name: (.agent_name // "shay"), flow_key: (.flow_key // "site-studio-local-promotion"), task_key: (.task_key // "proof.generate"), prompt_snapshot: (.prompt_snapshot // ""), input_snapshot: (.input_snapshot // {}), source_sha: (.source_sha // "")}' "$manifest")"
  design_dna="$(jq -c --argjson telemetry "$telemetry" '. + {source: "site_studio_local", telemetry: $telemetry}' <<<"$design_dna")"

  # Assets are explicit, byte-addressed proof evidence. Do not infer them by
  # walking a directory: that would make an accidental or private local file a
  # customer-facing artifact. The server repeats all path, SHA, MIME, and magic
  # checks before storage.
  assets_file="$temporary_dir/$direction.assets.json"
  printf '[]\n' > "$assets_file"
  declared_assets="$BUNDLE_DIR/$direction/assets.json"
  if [[ -f "$declared_assets" ]]; then
    jq -e '
      type == "array" and length <= 4 and
      all(.[];
        type == "object" and
        (.asset_id | type == "string" and test("^[a-z][a-z0-9_-]{0,63}$")) and
        (.relative_path | type == "string" and test("^[A-Za-z0-9][A-Za-z0-9._-]{0,95}(/[A-Za-z0-9][A-Za-z0-9._-]{0,95}){0,5}$")) and
        (.relative_path | startswith(".") | not) and
        (.media_type | type == "string" and (. == "image/jpeg" or . == "image/png" or . == "image/webp" or . == "image/avif"))
      )
    ' "$declared_assets" >/dev/null || { echo "Invalid $direction/assets.json." >&2; exit 1; }
    declared_count="$(jq 'length' "$declared_assets")"
    [[ "$declared_count" -le "$max_assets_per_variant" ]] || { echo "Too many assets for direction $direction." >&2; exit 1; }
    declared_total=0
    while IFS= read -r declared_asset; do
      asset_id="$(jq -r '.asset_id' <<<"$declared_asset")"
      relative_path="$(jq -r '.relative_path' <<<"$declared_asset")"
      media_type="$(jq -r '.media_type' <<<"$declared_asset")"
      asset_path="$BUNDLE_DIR/$direction/assets/$relative_path"
      [[ -f "$asset_path" && ! -L "$asset_path" ]] || { echo "Missing or unsafe asset $direction/assets/$relative_path." >&2; exit 1; }
      asset_bytes="$(wc -c < "$asset_path" | tr -d ' ')"
      [[ "$asset_bytes" -ge 1 && "$asset_bytes" -le "$max_asset_bytes" ]] || { echo "Asset $direction/assets/$relative_path exceeds 1.5 MB." >&2; exit 1; }
      declared_total=$((declared_total + asset_bytes))
      [[ "$declared_total" -le "$max_asset_bytes_per_variant" ]] || { echo "Assets for direction $direction exceed 3 MB." >&2; exit 1; }
      asset_sha="$(openssl dgst -sha256 "$asset_path" | awk '{print $NF}')"
      asset_base64="$temporary_dir/$direction.$asset_id.asset.base64"
      base64 < "$asset_path" | tr -d '\n' > "$asset_base64"
      asset_json="$temporary_dir/$direction.$asset_id.asset.json"
      jq -n --arg asset_id "$asset_id" --arg relative_path "$relative_path" --arg media_type "$media_type" --rawfile base64 "$asset_base64" --arg sha256 "$asset_sha" \
        '{asset_id:$asset_id,relative_path:$relative_path,media_type:$media_type,base64:$base64,sha256:$sha256}' > "$asset_json"
      next_assets="$temporary_dir/$direction.$asset_id.assets.next.json"
      jq -c --slurpfile asset "$asset_json" '. + [$asset[0]]' "$assets_file" > "$next_assets"
      mv "$next_assets" "$assets_file"
    done < <(jq -c '.[]' "$declared_assets")
  fi

  thumbnail_base64="$temporary_dir/$direction.thumbnail.base64"
  variant_file="$temporary_dir/$direction.variant.json"
  next_variants="$temporary_dir/$direction.variants.json"
  base64 < "$thumbnail_path" | tr -d '\n' > "$thumbnail_base64"
  jq -n --arg direction "$direction" --rawfile html "$html_path" --rawfile thumbnail "$thumbnail_base64" --arg media_type "$media_type" --argjson design_dna "$design_dna" --slurpfile assets "$assets_file" '{direction_id: $direction, html: $html, thumbnail_base64: $thumbnail, thumbnail_media_type: $media_type, design_dna: $design_dna, assets: $assets[0]}' > "$variant_file"
  jq -c --slurpfile variant "$variant_file" '. + [$variant[0]]' "$variants_file" > "$next_variants"
  mv "$next_variants" "$variants_file"
done

jq -n --arg event_id "$event_id" --arg campaign_id "$campaign_id" --arg job_id "$job_id" --slurpfile variants "$variants_file" '{event_id: $event_id, campaign_id: $campaign_id, job_id: $job_id, variants: $variants[0]}' > "$payload"
checksum="$(openssl dgst -sha256 "$payload" | awk '{print $NF}')"
payload_bytes="$(wc -c < "$payload" | tr -d ' ')"
[[ "$payload_bytes" -le "$max_callback_bytes" ]] || { echo "Combined callback payload exceeds the signed proof asset limit." >&2; exit 1; }

echo "Local proof promotion candidate"
echo "  proof set: $proof_set"
echo "  campaign: $campaign_id"
echo "  job:      $job_id"
echo "  event:    $event_id"
echo "  variants: 3"
echo "  bytes:    $payload_bytes"
echo "  sha256:   $checksum"

if [[ "$LOCAL_IMPORT" == true ]]; then
  "$REPO_ROOT/backend/vendor/bin/drush" famtastic:proof-local-import "$payload" --confirm="$campaign_id" --checksum="$checksum"
  exit 0
fi

if [[ "$APPLY" != true ]]; then
  echo "Dry-run passed. No production files or data changed."
  exit 0
fi

remote_dir=".config/famtastic/proof-inbox"
remote_file="$remote_dir/${event_id}-${checksum}.json"
ssh -T "$SSH_TARGET" "mkdir -p \"\$HOME/$remote_dir\" && chmod 700 \"\$HOME/$remote_dir\""
scp -q "$payload" "$SSH_TARGET:$remote_file"
ssh -T "$SSH_TARGET" "chmod 600 \"\$HOME/$remote_file\" && cd \"\$HOME/$REMOTE_ROOT\" && vendor/bin/drush famtastic:proof-local-import \"\$HOME/$remote_file\" --confirm='$campaign_id' --checksum='$checksum'"
echo "Production import complete. Private audit payload retained at ~/$remote_file"
