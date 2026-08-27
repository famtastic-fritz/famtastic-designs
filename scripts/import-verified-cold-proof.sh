#!/usr/bin/env bash
# Local-only operator wrapper for the narrow verified-cold callback import.
#
# This intentionally does NOT call the old local-proof/GoDaddy promoter. It
# computes only private-file checksums and the existing Site Studio callback
# HMAC, then invokes the exact-delivery Drush importer. Import records proof
# artifacts only; owner review, room staging, and customer email remain gated.

set -euo pipefail

usage() {
  cat <<'USAGE'
Usage:
  scripts/import-verified-cold-proof.sh \
    --delivery <exact-delivery-id> \
    --confirm <exact-campaign-id> \
    --callback <absolute-private-callback.json> \
    --build-dna <absolute-private-build-dna.json> [--dry-run]

The default verifies inputs and prints checksums only. Add --apply-local to
invoke the local Drupal Drush importer. This script never uploads, promotes,
publishes, dispatches an email, or calls an image provider.
USAGE
}

delivery=''
confirm=''
callback=''
build_dna=''
apply_local=0

while [[ $# -gt 0 ]]; do
  case "$1" in
    --delivery) delivery="${2:-}"; shift 2 ;;
    --confirm) confirm="${2:-}"; shift 2 ;;
    --callback) callback="${2:-}"; shift 2 ;;
    --build-dna) build_dna="${2:-}"; shift 2 ;;
    --apply-local) apply_local=1; shift ;;
    --dry-run) apply_local=0; shift ;;
    --help|-h) usage; exit 0 ;;
    *) printf 'Unknown argument: %s\n' "$1" >&2; usage >&2; exit 2 ;;
  esac
done

if [[ ! "$delivery" =~ ^[1-9][0-9]*$ ]] || [[ -z "$confirm" ]] || [[ "$confirm" =~ [[:space:]] ]] || [[ -z "$callback" ]] || [[ -z "$build_dna" ]]; then
  printf 'An exact --delivery, --confirm, --callback, and --build-dna are required.\n' >&2
  exit 2
fi
for path in "$callback" "$build_dna"; do
  if [[ "$path" != /* ]] || [[ -L "$path" ]] || [[ ! -f "$path" ]] || [[ ! -r "$path" ]]; then
    printf 'Every input must be an absolute, readable, non-symlink regular file.\n' >&2
    exit 2
  fi
done

callback_checksum="$(shasum -a 256 "$callback" | awk '{print $1}')"
build_dna_checksum="$(shasum -a 256 "$build_dna" | awk '{print $1}')"

printf 'Verified-cold local import plan\n'
printf 'delivery_id=%s\n' "$delivery"
printf 'campaign_id=%s\n' "$confirm"
printf 'callback_sha256=%s\n' "$callback_checksum"
printf 'build_dna_sha256=%s\n' "$build_dna_checksum"
printf 'status=%s\n' "$([[ "$apply_local" -eq 1 ]] && printf 'ready_for_local_import' || printf 'dry_run_no_import')"

if [[ "$apply_local" -ne 1 ]]; then
  printf 'No Drupal import, staging, publication, email, or provider call was performed.\n'
  exit 0
fi

if [[ -z "${SITE_STUDIO_CALLBACK_SECRET:-}" ]]; then
  printf 'SITE_STUDIO_CALLBACK_SECRET must be set for --apply-local.\n' >&2
  exit 2
fi

repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
drush="$repository_root/backend/vendor/bin/drush"
if [[ ! -x "$drush" ]]; then
  printf 'Local Drupal Drush is unavailable at the expected repository path.\n' >&2
  exit 2
fi

# The secret is never printed, persisted, or passed as a process argument.
# Read it from this process environment inside PHP instead of `openssl -hmac`,
# which would expose the literal secret to a local process listing.
signature="$(php -r '
  $secret = getenv("SITE_STUDIO_CALLBACK_SECRET");
  $body = file_get_contents($argv[1]);
  if (!is_string($secret) || $secret === "" || $body === false) {
    fwrite(STDERR, "Could not prepare verified-cold callback HMAC.\\n");
    exit(2);
  }
  echo "sha256=" . hash_hmac("sha256", $body, $secret);
' "$callback")"
exec "$drush" famtastic:verified-cold-proof-import "$callback" "$build_dna" \
  --delivery="$delivery" \
  --confirm="$confirm" \
  --callback-checksum="$callback_checksum" \
  --build-dna-checksum="$build_dna_checksum" \
  --callback-signature="$signature"
