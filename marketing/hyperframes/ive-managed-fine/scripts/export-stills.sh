#!/usr/bin/env bash
# Export campaign stills and campaign video copies from the delivered films.
#
# Stills are EXTRACTED from the renders rather than designed separately, so the
# feed image and the film a prospect sees cannot drift apart. Videos are copied
# (not re-encoded) into the campaign directory, which is where
# posting-schedule.json's primary_media paths point.
#
# Cost: $0. Local ffmpeg only. Idempotent.
set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJ="$(dirname "$HERE")"
REPO="$(cd "$PROJ/../../.." && pwd)"

STILLS="$REPO/marketing/creative/campaign-assets/ive-managed-fine"
VIDEOS="$REPO/marketing/campaigns/ive-managed-fine/videos"
mkdir -p "$STILLS" "$VIDEOS"

# film-dir | still time (s) | still name | campaign video name
ROWS=(
  "f1-thirty-years|3.4|01-thirty-years-objection-9x16.jpg|01-thirty-years-9x16.mp4"
  "f2-know-where|3.4|02-know-where-objection-9x16.jpg|02-know-where-9x16.mp4"
  "f3-not-technical|3.0|03-not-technical-objection-9x16.jpg|03-not-technical-9x16.mp4"
  "f4-got-burned|3.4|04-got-burned-objection-9x16.jpg|04-got-burned-9x16.mp4"
  "f5-too-expensive|9.5|05-too-expensive-offer-9x16.jpg|05-too-expensive-9x16.mp4"
  "f6-retiring|3.4|06-retiring-objection-9x16.jpg|06-retiring-9x16.mp4"
)

for row in "${ROWS[@]}"; do
  IFS='|' read -r film at still video <<<"$row"
  src="$PROJ/$film/renders/$film-1080x1920.mp4"
  if [[ ! -f "$src" ]]; then
    echo "BLOCKED: $src is missing — render it before exporting." >&2
    exit 1
  fi
  ffmpeg -y -v error -ss "$at" -i "$src" -frames:v 1 -q:v 2 "$STILLS/$still"
  cp "$src" "$VIDEOS/$video"
  printf "  %-34s %s\n" "$still" "$(ffprobe -v error -select_streams v:0 \
    -show_entries stream=width,height -of csv=p=0 "$STILLS/$still")"
done

echo
echo "stills -> $STILLS"
echo "videos -> $VIDEOS"
ls -la "$VIDEOS"
