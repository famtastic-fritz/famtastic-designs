#!/usr/bin/env bash
# Stage + grade existing campaign assets into this HyperFrames project.
#
# Nothing here GENERATES an asset. Every input already existed before this
# project. This script only crops, upscales, colour-grades and trims them so
# they sit inside the grading contract measured off the campaign's premium
# anchor (marketing/creative/heygen/reference-tokens.json):
#
#   mean luminance 150-175 (a LIGHT frame), shadows lifted to a muted
#   mauve-grey (#33272E), one small olive accent (#7FB449) - never a green field.
#
# Cost: $0. Local ffmpeg only, no provider call.
# Deterministic: same inputs + same filter strings => same bytes.
set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJ="$(dirname "$HERE")"
REPO="$(cd "$PROJ/../../.." && pwd)"

CAMP="$REPO/marketing/campaigns/cost-is-not-the-reason"
PLATES="$REPO/marketing/creative/plates/platform-dependency"
ANCHOR="$REPO/marketing/creative/anchors/pd-anchor-counter-16x9.png"

OUT="$PROJ/assets"
mkdir -p "$OUT"

# Shadow lift toward the measured floor #33272E (51,39,46). R > B > G keeps the
# mauve cast that the anchor take actually has.
LIFT="colorlevels=romin=0.085:gomin=0.062:bomin=0.076"
SHARP="unsharp=5:5:0.42:5:5:0.0"

# --- full-bleed plates ---------------------------------------------------
# The quote sheet lies ON a photograph, so every beat has a full-bleed plate
# behind it. Rendered at 1188x2128 (1.10x of the 1080x1920 frame) so the slow
# camera push never samples below delivered resolution.
BLEED="1188:2128"

# 1. The business that already exists. A 9:16 crop of the square campaign image
#    placed on the owner and her counter. Saturation is pulled hard: the source
#    is a warm coral-and-green lifestyle frame and this film is a document, so
#    it has to lose its advertising colour before it can carry a price.
ffmpeg -y -v error -i "$CAMP/images/05-openart-vibrant-artisan-bakery-1x1.png" \
  -vf "crop=765:1360:150:0,scale=$BLEED:flags=lanczos,eq=brightness=0.072:contrast=1.03:saturation=0.44,$LIFT,$SHARP" \
  -q:v 2 "$OUT/plate-owner.jpg"

# 2. The counter with the two blank cards on it: the close.
ffmpeg -y -v error -i "$ANCHOR" \
  -vf "crop=576:1024:280:0,scale=$BLEED:flags=lanczos,eq=brightness=0.145:contrast=1.05:saturation=0.90,$LIFT,$SHARP" \
  -q:v 2 "$OUT/plate-counter.jpg"

# --- narration -----------------------------------------------------------
# Reused verbatim from the campaign's own 15s commercial, TRIMMED at 10.45s.
# Two complete sentences, both supported by backend/config/famtastic-products.json
# and by the live package page:
#
#   "Of all the reasons you do not have a professional website yet,
#    cost is not one of them. At 55 cents a day, $199 for your entire
#    first year,"
#
# Everything after 10.45s is deliberately dropped. The next clause in the
# source is "3 custom design directions in 48 hours", which is NOT in
# famtastic-products.json and NOT on famtasticdesigns.com/packages/199-quick-start,
# so it is not repeated here. See README.md "What was cut from the narration".
# The source carries a continuous bed, so the tail is faded rather than cut.
ffmpeg -y -v error -ss 0 -t 10.45 \
  -i "$CAMP/videos/01-55-cent-myth-commercial-9x16.mp4" \
  -vn -af "afade=t=out:st=9.85:d=0.60" \
  -c:a aac -b:a 160k "$OUT/vo-cost-open.m4a"

echo "staged:"
ls -la "$OUT"
