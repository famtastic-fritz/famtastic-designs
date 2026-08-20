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
requires index.html and thumbnail.png or thumbnail.jpg. Dry-run is the default.
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
  thumbnail_base64="$temporary_dir/$direction.thumbnail.base64"
  variant_file="$temporary_dir/$direction.variant.json"
  next_variants="$temporary_dir/$direction.variants.json"
  base64 < "$thumbnail_path" | tr -d '\n' > "$thumbnail_base64"
  jq -n --arg direction "$direction" --rawfile html "$html_path" --rawfile thumbnail "$thumbnail_base64" --arg media_type "$media_type" --argjson design_dna "$design_dna" '{direction_id: $direction, html: $html, thumbnail_base64: $thumbnail, thumbnail_media_type: $media_type, design_dna: $design_dna}' > "$variant_file"
  jq -c --slurpfile variant "$variant_file" '. + [$variant[0]]' "$variants_file" > "$next_variants"
  mv "$next_variants" "$variants_file"
done

jq -n --arg event_id "$event_id" --arg campaign_id "$campaign_id" --arg job_id "$job_id" --slurpfile variants "$variants_file" '{event_id: $event_id, campaign_id: $campaign_id, job_id: $job_id, variants: $variants[0]}' > "$payload"
checksum="$(openssl dgst -sha256 "$payload" | awk '{print $NF}')"
payload_bytes="$(wc -c < "$payload" | tr -d ' ')"
[[ "$payload_bytes" -le 8388608 ]] || { echo "Combined callback payload exceeds 8 MB." >&2; exit 1; }

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
