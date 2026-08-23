#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
repo_dir="$(cd "$script_dir/.." && pwd)"
artifact_root="$repo_dir/artifacts/website-delivery-swarm"
ssh_target="${FAMTASTIC_SSH_TARGET:-xrdj7j99xhzt@p3plzcpnl497512.prod.phx3.secureserver.net}"
remote_root="${FAMTASTIC_REMOTE_ROOT:-public_html}"
token="${1:-}"

if [[ ! "$token" =~ ^[a-f0-9]{32}$ ]]; then
  echo "Usage: $0 <32-character-hex-token>" >&2
  exit 2
fi

packages=(
  "bossy-nails-by-pri-20260818"
  "good-ole-candy-lady-shop-20260818"
  "famu-corner-20260818"
  "st-lucie-three-project-benchmark-20260818"
)

for package in "${packages[@]}"; do
  test -s "$artifact_root/$package/index.html" || {
    echo "Missing review package: $package" >&2
    exit 1
  }
done

for package in "${packages[@]:0:3}"; do
  manifest="$artifact_root/$package/manifest.json"
  jq -e '.direction_count == 6 and (.directions | length) == 6' "$manifest" >/dev/null
  while IFS= read -r entry; do
    [[ "$entry" =~ ^[a-z0-9-]+/index\.html$ ]] || {
      echo "Unsafe direction entry: $entry" >&2
      exit 1
    }
    html="$artifact_root/$package/$entry"
    test -s "$html" || { echo "Missing direction: $package/$entry" >&2; exit 1; }
    if rg -qi '<(script|iframe|object|embed|base)\b|\son[a-z]+\s*=|javascript\s*:' "$html"; then
      echo "Active content is not allowed: $package/$entry" >&2
      exit 1
    fi
    hero="${html%/index.html}/assets/hero.png"
    test -s "$hero" || { echo "Missing hero: $hero" >&2; exit 1; }
  done < <(jq -r '.directions[].entry' "$manifest")
done

archive_dir="$(mktemp -d /tmp/famtastic-unlisted-showcase.XXXXXX)"
archive="$archive_dir/showcase.tar.gz"
cleanup() { rm -rf "$archive_dir"; }
trap cleanup EXIT

payload_root="$archive_dir/payload"
mkdir "$payload_root"
for package in "${packages[@]:0:3}"; do
  mkdir "$payload_root/$package"
  cp "$artifact_root/$package/index.html" "$payload_root/$package/index.html"
  while IFS= read -r entry; do
    direction="${entry%/index.html}"
    cp -R "$artifact_root/$package/$direction" "$payload_root/$package/$direction"
    sips -s format jpeg -s formatOptions 84 \
      "$payload_root/$package/$direction/assets/hero.png" \
      --out "$payload_root/$package/$direction/assets/hero.jpg" >/dev/null
    rm "$payload_root/$package/$direction/assets/hero.png"
    perl -pi -e 's#assets/hero\.png#assets/hero.jpg#g' "$payload_root/$package/$direction/index.html"
  done < <(jq -r '.directions[].entry' "$artifact_root/$package/manifest.json")
  perl -pi -e 's#assets/hero\.png#assets/hero.jpg#g' "$payload_root/$package/index.html"
done
mkdir "$payload_root/st-lucie-three-project-benchmark-20260818"
cp "$artifact_root/st-lucie-three-project-benchmark-20260818/index.html" "$payload_root/st-lucie-three-project-benchmark-20260818/index.html"
tar -C "$payload_root" -czf "$archive" "${packages[@]}"
checksum="$(openssl dgst -sha256 "$archive" | awk '{print $NF}')"
remote_base="$remote_root/proofs/unlisted/$token"
remote_stage="$remote_root/proofs/unlisted/.staging-$token"
remote_archive=".config/famtastic-unlisted-$token.tar.gz"

ssh -T "$ssh_target" bash -s -- "$remote_base" "$remote_stage" "$remote_archive" <<'REMOTE_PREFLIGHT'
set -euo pipefail
target="$1"
stage="$2"
archive="$3"
case "$target" in public_html/proofs/unlisted/[a-f0-9][a-f0-9]*) ;; *) echo "Unsafe target" >&2; exit 1 ;; esac
test ! -e "$HOME/$target" || { echo "Unlisted target already exists." >&2; exit 1; }
test ! -e "$HOME/$stage" || { echo "Unlisted staging target already exists." >&2; exit 1; }
test ! -e "$HOME/$archive" || { echo "Unlisted archive target already exists." >&2; exit 1; }
mkdir -p "$HOME/public_html/proofs/unlisted"
REMOTE_PREFLIGHT

uploaded=false
for attempt in 1 2 3 4 5; do
  if rsync -az --partial --append-verify "$archive" "$ssh_target:$remote_archive"; then
    uploaded=true
    break
  fi
  echo "Transfer attempt $attempt interrupted; resuming..." >&2
  sleep 2
done
[[ "$uploaded" == true ]] || { echo "Resumable upload failed." >&2; exit 1; }
ssh -T "$ssh_target" bash -s -- "$remote_base" "$remote_stage" "$token" "$checksum" <<'REMOTE_APPLY'
set -euo pipefail
target="$1"
stage="$2"
token="$3"
expected_checksum="$4"
archive="$HOME/.config/famtastic-unlisted-$token.tar.gz"
case "$target" in public_html/proofs/unlisted/[a-f0-9][a-f0-9]*) ;; *) echo "Unsafe target" >&2; exit 1 ;; esac
case "$stage" in public_html/proofs/unlisted/.staging-[a-f0-9][a-f0-9]*) ;; *) echo "Unsafe stage" >&2; exit 1 ;; esac
actual_checksum="$(openssl dgst -sha256 "$archive" | awk '{print $NF}')"
test "$actual_checksum" = "$expected_checksum"
mkdir "$HOME/$stage"
tar -xzf "$archive" -C "$HOME/$stage"
test "$(find "$HOME/$stage" -path '*/index.html' -type f | wc -l | tr -d ' ')" = "22"
test "$(find "$HOME/$stage" -path '*/assets/hero.jpg' -type f | wc -l | tr -d ' ')" = "18"
find "$HOME/$stage" -type d -exec chmod 755 {} +
find "$HOME/$stage" -type f -exec chmod 644 {} +
mv "$HOME/$stage" "$HOME/$target"
rm "$archive"
printf '%s\n' "$expected_checksum" > "$HOME/$target/.publication-sha256"
REMOTE_APPLY

echo "PASS: unlisted collection published atomically"
echo "Token: $token"
echo "Archive SHA-256: $checksum"
