#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SSH_TARGET="${FAMTASTIC_SSH_TARGET:-xrdj7j99xhzt@p3plzcpnl497512.prod.phx3.secureserver.net}"
REMOTE_ROOT="${FAMTASTIC_REMOTE_ROOT:-public_html}"
PROSPECT_ID=""
OUTPUT_DIR=""
APPLY=false
REFRESH_CAMPAIGN=""

usage() {
  cat <<'USAGE'
Usage: ./scripts/fetch-local-proof-job-godaddy.sh PROSPECT_ID OUTPUT_DIR [--refresh-campaign=ID] [--apply]

Dry-run is the default. --apply asks production Drupal to create an idempotent
offline Site Studio handoff, then downloads its private JSON and Markdown brief
over authenticated SSH. The local output directory is mode 0700 and its files
are mode 0600 because a paid-customer brief may contain private intake data.

Use --refresh-campaign=<exact-current-campaign-id> to replace an existing
image-free pilot in place after local review. Its current public proof remains
available until the validated replacement bundle is imported.
USAGE
}

for argument in "$@"; do
  case "$argument" in
    --apply) APPLY=true ;;
    --refresh-campaign=*) REFRESH_CAMPAIGN="${argument#*=}" ;;
    -h|--help) usage; exit 0 ;;
    *)
      if [[ -z "$PROSPECT_ID" ]]; then
        PROSPECT_ID="$argument"
      elif [[ -z "$OUTPUT_DIR" ]]; then
        OUTPUT_DIR="$argument"
      else
        usage >&2
        exit 2
      fi
      ;;
  esac
done

[[ "$PROSPECT_ID" =~ ^[1-9][0-9]*$ ]] || { echo "PROSPECT_ID must be a positive integer." >&2; exit 2; }
[[ -n "$OUTPUT_DIR" ]] || { usage >&2; exit 2; }
if [[ -n "$REFRESH_CAMPAIGN" && ! "$REFRESH_CAMPAIGN" =~ ^pc-[a-z0-9-]+$ ]]; then
  echo "--refresh-campaign must be an exact pc-* campaign id." >&2
  exit 2
fi

echo "Offline Site Studio request fetch"
echo "  production prospect: $PROSPECT_ID"
echo "  local destination:   $OUTPUT_DIR"
echo "  remote Drupal root:  ~/$REMOTE_ROOT"
[[ -z "$REFRESH_CAMPAIGN" ]] || echo "  refresh campaign:    $REFRESH_CAMPAIGN"

if [[ "$APPLY" != true ]]; then
  echo "Dry-run passed. Production and local files were not changed."
  exit 0
fi

for command_name in jq ssh scp; do
  command -v "$command_name" >/dev/null || { echo "Missing required command: $command_name" >&2; exit 1; }
done

if [[ -n "$REFRESH_CAMPAIGN" ]]; then
  handoff_json="$(ssh -T "$SSH_TARGET" "cd \"\$HOME/$REMOTE_ROOT\" && vendor/bin/drush famtastic:proof-local-refresh-export '$PROSPECT_ID' --confirm='$REFRESH_CAMPAIGN'")"
else
  handoff_json="$(ssh -T "$SSH_TARGET" "cd \"\$HOME/$REMOTE_ROOT\" && vendor/bin/drush famtastic:proof-local-export '$PROSPECT_ID'")"
fi
printf '%s' "$handoff_json" | jq -e '
  (.transport == "offline_ssh_bundle" or .transport == "offline_ssh_bundle_refresh") and
  (.prospect_id > 0) and
  (.project_id > 0) and
  (.campaign_id | type == "string" and length > 0) and
  (.job_id | type == "string" and length > 0) and
  (.request_location | type == "string" and length > 0)
' >/dev/null

request_path="$(printf '%s' "$handoff_json" | jq -r '.request_location')"
if [[ ! "$request_path" =~ ^/home/[A-Za-z0-9._-]+/[A-Za-z0-9/._-]*/site-studio-requests/project-[0-9]+\.json$ ]]; then
  echo "Production returned an unexpected private request path." >&2
  exit 1
fi
brief_path="${request_path%.json}.md"

mkdir -p "$OUTPUT_DIR"
chmod 700 "$OUTPUT_DIR"
scp -q "$SSH_TARGET:$request_path" "$OUTPUT_DIR/request.json"
scp -q "$SSH_TARGET:$brief_path" "$OUTPUT_DIR/request.md"
printf '%s\n' "$handoff_json" | jq '.' > "$OUTPUT_DIR/handoff.json"
chmod 600 "$OUTPUT_DIR/request.json" "$OUTPUT_DIR/request.md" "$OUTPUT_DIR/handoff.json"

jq -e '.' "$OUTPUT_DIR/request.json" >/dev/null
test -s "$OUTPUT_DIR/request.md"
echo "Fetch complete. Give request.json and request.md to the local Site Studio run."
echo "When its three directions pass review, promote the output with:"
echo "  $SCRIPT_DIR/promote-local-proof-godaddy.sh BUNDLE_DIR --apply"
