#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
repo_dir="$(cd "$script_dir/.." && pwd)"
ssh_target="${FAMTASTIC_SSH_TARGET:-xrdj7j99xhzt@p3plzcpnl497512.prod.phx3.secureserver.net}"
remote_root="${FAMTASTIC_REMOTE_ROOT:-public_html}"
public_origin="${FAMTASTIC_PUBLIC_ORIGIN:-https://famtasticdesigns.com}"
site_dir=""
token=""
apply=false

usage() {
  echo "Usage: $0 SITE_DIR --token=<32-hex-token> [--apply]" >&2
}

for argument in "$@"; do
  case "$argument" in
    --token=*) token="${argument#*=}" ;;
    --apply) apply=true ;;
    -h|--help) usage; exit 0 ;;
    *)
      [[ -z "$site_dir" ]] || { usage; exit 2; }
      site_dir="$argument"
      ;;
  esac
done

[[ -n "$site_dir" ]] || { usage; exit 2; }
[[ "$token" =~ ^[a-f0-9]{32}$ ]] || { echo "Token must be exactly 32 lowercase hexadecimal characters." >&2; exit 2; }
site_dir="$(cd "$site_dir" && pwd)"

case "$site_dir" in
  "$repo_dir"/marketing/campaigns/*/site) ;;
  *) echo "SITE_DIR must be a campaign site directory inside this repository." >&2; exit 2 ;;
esac

for command_name in find rg tar openssl ssh rsync; do
  command -v "$command_name" >/dev/null || { echo "Missing required command: $command_name" >&2; exit 1; }
done

test -s "$site_dir/index.html"
test -s "$site_dir/styles.css"
test -s "$site_dir/app.js"
[[ -z "$(find "$site_dir" -type l -print -quit)" ]] || { echo "Symbolic links are not allowed." >&2; exit 1; }
[[ -z "$(find "$site_dir" -type f \( -name '*.php' -o -name '.htaccess' -o -name '*.cgi' -o -name '*.pl' -o -name '*.sh' -o -name '*.py' \) -print -quit)" ]] || { echo "Executable or server-side files are not allowed." >&2; exit 1; }

while IFS= read -r file_path; do
  relative_path="${file_path#"$site_dir"/}"
  [[ "$relative_path" =~ ^[A-Za-z0-9._/-]+$ ]] || { echo "Unsafe path: $relative_path" >&2; exit 1; }
  [[ "$relative_path" =~ \.(html|css|js|jpg|jpeg|png|webp|svg|woff2|json|txt)$ ]] || { echo "Unsupported file type: $relative_path" >&2; exit 1; }
done < <(find "$site_dir" -type f -print)

if rg -n -i 'javascript\s*:|\son[a-z]+\s*=|<(iframe|object|embed|base)\b' "$site_dir" --glob '*.html'; then
  echo "Unsafe active HTML found." >&2
  exit 1
fi

script_tag_count="$(rg -o '<script\b[^>]*>' "$site_dir" --glob '*.html' | wc -l | tr -d ' ')"
[[ "$script_tag_count" == "1" ]] || { echo "Expected exactly one controlled script tag." >&2; exit 1; }
rg -q '<script src="app\.js" defer></script>' "$site_dir/index.html" || { echo "Unexpected script entrypoint." >&2; exit 1; }

if rg -n 'fetch\s*\(|XMLHttpRequest|WebSocket|sendBeacon|document\.cookie|window\.open' "$site_dir/app.js"; then
  echo "Network or browser-escape behavior is not allowed in an unlisted proof." >&2
  exit 1
fi

file_count="$(find "$site_dir" -type f | wc -l | tr -d ' ')"
total_bytes="$(find "$site_dir" -type f -exec stat -f '%z' {} \; | awk '{sum += $1} END {print sum + 0}')"
[[ "$file_count" -le 100 ]] || { echo "Proof exceeds 100 files." >&2; exit 1; }
[[ "$total_bytes" -le 31457280 ]] || { echo "Proof exceeds 30 MiB." >&2; exit 1; }

archive="$(mktemp /tmp/famtastic-static-proof.XXXXXX.tar.gz)"
trap 'rm -f "$archive"' EXIT
COPYFILE_DISABLE=1 tar -C "$site_dir" -czf "$archive" .
checksum="$(openssl dgst -sha256 "$archive" | awk '{print $NF}')"

echo "Unlisted static proof candidate"
echo "  files:    $file_count"
echo "  bytes:    $total_bytes"
echo "  sha256:   $checksum"
echo "  target:   $public_origin/proofs/unlisted/$token/"

if [[ "$apply" != true ]]; then
  echo "Dry-run passed. No production files changed."
  exit 0
fi

remote_target="$remote_root/proofs/unlisted/$token"
remote_stage="$remote_root/proofs/unlisted/.staging-$token"
remote_archive=".config/famtastic-static-$token.tar.gz"

ssh -T "$ssh_target" bash -s -- "$remote_target" "$remote_stage" "$remote_archive" <<'REMOTE_PREFLIGHT'
set -euo pipefail
target="$1"
stage="$2"
archive="$3"
case "$target" in public_html/proofs/unlisted/[a-f0-9][a-f0-9]*) ;; *) echo "Unsafe target" >&2; exit 1 ;; esac
case "$stage" in public_html/proofs/unlisted/.staging-[a-f0-9][a-f0-9]*) ;; *) echo "Unsafe staging target" >&2; exit 1 ;; esac
test ! -e "$HOME/$target" || { echo "Unlisted target already exists." >&2; exit 1; }
test ! -e "$HOME/$stage" || { echo "Unlisted staging target already exists." >&2; exit 1; }
test ! -e "$HOME/$archive" || { echo "Unlisted archive already exists." >&2; exit 1; }
mkdir -p "$HOME/public_html/proofs/unlisted"
REMOTE_PREFLIGHT

uploaded=false
for attempt in 1 2 3 4 5; do
  if rsync -az --partial --append "$archive" "$ssh_target:$remote_archive"; then
    uploaded=true
    break
  fi
  echo "Transfer attempt $attempt interrupted; resuming..." >&2
  sleep 2
done
[[ "$uploaded" == true ]] || { echo "Resumable upload failed." >&2; exit 1; }

ssh -T "$ssh_target" bash -s -- "$remote_target" "$remote_stage" "$remote_archive" "$checksum" "$file_count" <<'REMOTE_APPLY'
set -euo pipefail
target="$1"
stage="$2"
archive_path="$3"
expected_checksum="$4"
expected_files="$5"
archive="$HOME/$archive_path"
case "$target" in public_html/proofs/unlisted/[a-f0-9][a-f0-9]*) ;; *) echo "Unsafe target" >&2; exit 1 ;; esac
case "$stage" in public_html/proofs/unlisted/.staging-[a-f0-9][a-f0-9]*) ;; *) echo "Unsafe stage" >&2; exit 1 ;; esac
actual_checksum="$(openssl dgst -sha256 "$archive" | awk '{print $NF}')"
test "$actual_checksum" = "$expected_checksum"
mkdir "$HOME/$stage"
tar -xzf "$archive" -C "$HOME/$stage"
test -s "$HOME/$stage/index.html"
test "$(find "$HOME/$stage" -type f | wc -l | tr -d ' ')" = "$expected_files"
find "$HOME/$stage" -type d -exec chmod 755 {} +
find "$HOME/$stage" -type f -exec chmod 644 {} +
mv "$HOME/$stage" "$HOME/$target"
rm "$archive"
printf '%s\n' "$expected_checksum" > "$HOME/$target/.publication-sha256"
REMOTE_APPLY

echo "PASS: unlisted static proof published atomically"
echo "URL: $public_origin/proofs/unlisted/$token/"
echo "Archive SHA-256: $checksum"
