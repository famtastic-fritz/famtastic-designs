#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
repo_dir="$(cd "$script_dir/.." && pwd)"
ssh_target="${FAMTASTIC_SSH_TARGET:-xrdj7j99xhzt@p3plzcpnl497512.prod.phx3.secureserver.net}"
remote_root="${FAMTASTIC_REMOTE_ROOT:-public_html}"
public_origin="${FAMTASTIC_PUBLIC_ORIGIN:-https://famtasticdesigns.com}"
site_dir=""
slug=""
apply=false
replace=false

usage() {
  echo "Usage: $0 SITE_DIR --slug=and-if-it-is [--apply] [--replace]" >&2
}

for argument in "$@"; do
  case "$argument" in
    --slug=*) slug="${argument#*=}" ;;
    --apply) apply=true ;;
    --replace) replace=true ;;
    -h|--help) usage; exit 0 ;;
    *)
      [[ -z "$site_dir" ]] || { usage; exit 2; }
      site_dir="$argument"
      ;;
  esac
done

[[ -n "$site_dir" ]] || { usage; exit 2; }
[[ "$slug" == "and-if-it-is" ]] || { echo "Only the reviewed and-if-it-is experience slug is allowed by this publisher." >&2; exit 2; }
site_dir="$(cd "$site_dir" && pwd)"
expected_site="$repo_dir/marketing/campaigns/and-if-it-is-rattler-lifers/site"
[[ "$site_dir" == "$expected_site" ]] || { echo "SITE_DIR must be the reviewed AND IF IT IS experience source." >&2; exit 2; }

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
[[ "$script_tag_count" == "2" ]] || { echo "Expected one JSON-LD block and one controlled JavaScript entrypoint." >&2; exit 1; }
rg -q '<script type="application/ld\+json">' "$site_dir/index.html" || { echo "Expected the reviewed JSON-LD block." >&2; exit 1; }
rg -q '<script src="app\.js" defer></script>' "$site_dir/index.html" || { echo "Unexpected JavaScript entrypoint." >&2; exit 1; }

if rg -n 'fetch\s*\(|XMLHttpRequest|WebSocket|sendBeacon|document\.cookie|window\.open' "$site_dir/app.js"; then
  echo "Unapproved network or browser-escape behavior found." >&2
  exit 1
fi
unapproved_origins="$(rg -o 'https://[A-Za-z0-9.-]+' "$site_dir/app.js" | sort -u | rg -v '^https://(www\.googletagmanager\.com|famtasticdesigns\.com)$' || true)"
[[ -z "$unapproved_origins" ]] || { echo "Unapproved JavaScript origin: $unapproved_origins" >&2; exit 1; }
rg -q 'https://www\.googletagmanager\.com/gtag/js' "$site_dir/app.js" || { echo "Only the approved GA4 script origin is allowed." >&2; exit 1; }
rg -q 'https://famtasticdesigns\.com' "$site_dir/app.js" || { echo "Expected the canonical FAMtastic page location." >&2; exit 1; }

file_count="$(find "$site_dir" -type f | wc -l | tr -d ' ')"
total_bytes="$(find "$site_dir" -type f -exec stat -f '%z' {} \; | awk '{sum += $1} END {print sum + 0}')"
[[ "$file_count" -le 50 ]] || { echo "Experience exceeds 50 files." >&2; exit 1; }
[[ "$total_bytes" -le 15728640 ]] || { echo "Experience exceeds 15 MiB." >&2; exit 1; }

archive="$(mktemp /tmp/famtastic-experience.XXXXXX.tar.gz)"
trap 'rm -f "$archive"' EXIT
COPYFILE_DISABLE=1 tar --no-xattrs -C "$site_dir" -czf "$archive" .
checksum="$(openssl dgst -sha256 "$archive" | awk '{print $NF}')"

echo "FAMtastic experience publication candidate"
echo "  files:    $file_count"
echo "  bytes:    $total_bytes"
echo "  sha256:   $checksum"
echo "  target:   $public_origin/$slug/"

if [[ "$apply" != true ]]; then
  echo "Dry-run passed. No production files changed."
  exit 0
fi

remote_target="$remote_root/$slug"
remote_stage="$remote_root/.staging-$slug"
remote_archive=".config/famtastic-experience-$slug.tar.gz"

ssh -T "$ssh_target" bash -s -- "$remote_target" "$remote_stage" "$remote_archive" "$replace" <<'REMOTE_PREFLIGHT'
set -euo pipefail
target="$1"
stage="$2"
archive="$3"
replace="$4"
[[ "$target" == "public_html/and-if-it-is" ]] || { echo "Unsafe target" >&2; exit 1; }
[[ "$stage" == "public_html/.staging-and-if-it-is" ]] || { echo "Unsafe staging target" >&2; exit 1; }
[[ "$archive" == ".config/famtastic-experience-and-if-it-is.tar.gz" ]] || { echo "Unsafe archive" >&2; exit 1; }
if [[ -e "$HOME/$target" && "$replace" != true ]]; then
  echo "Experience target already exists; pass --replace for a recoverable atomic revision." >&2
  exit 1
fi
test ! -e "$HOME/$stage" || { echo "Experience staging target already exists." >&2; exit 1; }
if [[ -e "$HOME/$archive" ]]; then
  test -f "$HOME/$archive"
  test ! -L "$HOME/$archive"
fi
mkdir -p "$HOME/public_html"
REMOTE_PREFLIGHT

uploaded=false
for attempt in 1 2 3 4 5; do
  if rsync -az --partial "$archive" "$ssh_target:$remote_archive"; then
    uploaded=true
    break
  fi
  echo "Transfer attempt $attempt interrupted; resuming..." >&2
  sleep 2
done
[[ "$uploaded" == true ]] || { echo "Resumable upload failed." >&2; exit 1; }

ssh -T "$ssh_target" bash -s -- "$remote_target" "$remote_stage" "$remote_archive" "$checksum" "$file_count" "$replace" <<'REMOTE_APPLY'
set -euo pipefail
target="$1"
stage="$2"
archive_path="$3"
expected_checksum="$4"
expected_files="$5"
replace="$6"
archive="$HOME/$archive_path"
[[ "$target" == "public_html/and-if-it-is" ]] || { echo "Unsafe target" >&2; exit 1; }
[[ "$stage" == "public_html/.staging-and-if-it-is" ]] || { echo "Unsafe staging target" >&2; exit 1; }
actual_checksum="$(openssl dgst -sha256 "$archive" | awk '{print $NF}')"
test "$actual_checksum" = "$expected_checksum"
mkdir "$HOME/$stage"
tar -xzf "$archive" -C "$HOME/$stage"
test -s "$HOME/$stage/index.html"
test "$(find "$HOME/$stage" -type f | wc -l | tr -d ' ')" = "$expected_files"
find "$HOME/$stage" -type d -exec chmod 755 {} +
find "$HOME/$stage" -type f -exec chmod 644 {} +
if [[ -e "$HOME/$target" ]]; then
  [[ "$replace" == true ]] || { echo "Existing experience target requires --replace." >&2; exit 1; }
  backup="$HOME/public_html/.backup-and-if-it-is-$(date -u +%Y%m%dT%H%M%SZ)"
  test ! -e "$backup"
  mv "$HOME/$target" "$backup"
  if ! mv "$HOME/$stage" "$HOME/$target"; then
    mv "$backup" "$HOME/$target"
    exit 1
  fi
  printf '%s\n' "$backup" > "$HOME/$target/.previous-release"
else
  mv "$HOME/$stage" "$HOME/$target"
fi
rm "$archive"
printf '%s\n' "$expected_checksum" > "$HOME/$target/.publication-sha256"
REMOTE_APPLY

echo "PASS: FAMtastic experience published atomically"
echo "URL: $public_origin/$slug/"
echo "Archive SHA-256: $checksum"
