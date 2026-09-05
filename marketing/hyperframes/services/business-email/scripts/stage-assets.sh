#!/usr/bin/env bash
# Stage + grade existing assets into this HyperFrames project.
#
# Nothing here GENERATES an asset. The presenter take, the plate and the anchor
# all existed before this project. This script keys, crops, upscales and
# colour-grades them into the contract measured off the campaign's premium
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
REPO="$(cd "$PROJ/../../../.." && pwd)"

TAKE_B="$REPO/marketing/creative/heygen/renders/take-b-business-email-scope.mp4"
PLATES="$REPO/marketing/creative/plates/platform-dependency"
ANCHOR="$REPO/marketing/creative/anchors/pd-anchor-counter-16x9.png"

OUT="$PROJ/assets"
mkdir -p "$OUT"

LIFT="colorlevels=romin=0.085:gomin=0.062:bomin=0.076"
SHARP="unsharp=5:5:0.42:5:5:0.0"

# --- presenter -----------------------------------------------------------
# take-b was rendered with remove_background:true, which did NOT produce an
# alpha channel - it produced a figure on a perfectly uniform #F4F5FA field
# (verified: all four corners and mid-frame sample identically). So the cut-out
# is done here, once, with colorkey, and the figure is laid onto the film's own
# paper ground rather than onto transparency.
#
# similarity 0.06 is not conservatism for its own sake. At 0.12 a patch of her
# forehead highlight keys out; at 0.20 it becomes a hole the size of an eyebrow.
# Both were caught by extracting a frame and looking at it, not by any error.
#
# The composite is graded AFTER the overlay, so the ground travels through the
# same grade as the figure. The value it lands on is #D2C9BE - that is why
# index.html's --paper is #d2c9be and not the value fed in here. Change one and
# the cut-out stops being invisible.
ffmpeg -y -v error -i "$TAKE_B" \
  -filter_complex "\
color=c=0xD5CEC3:s=664x1080:r=25[bg];\
[0:v]crop=664:1080:620:0,colorkey=0xF4F5FA:0.06:0.04[fg];\
[bg][fg]overlay=format=auto:shortest=1,\
eq=saturation=1.00:brightness=-0.025:contrast=1.05,$LIFT,unsharp=5:5:0.30:5:5:0.0[v]" \
  -map "[v]" -map 0:a \
  -c:v libx264 -crf 16 -preset medium -pix_fmt yuv420p \
  -c:a aac -b:a 160k "$OUT/presenter.mp4"

# --- full-bleed plate ----------------------------------------------------
# A blank card in a brass holder on a plain warm wall: a business with nothing
# written on it yet. 1188x2128 is 1.10x the frame, so the camera push never
# samples below delivered resolution.
ffmpeg -y -v error -i "$PLATES/pd-p-vertical-9x16.jpg" \
  -vf "scale=1188:2128:flags=lanczos,eq=brightness=-0.060:contrast=1.05:saturation=0.86,$LIFT,$SHARP" \
  -q:v 2 "$OUT/plate-holder.jpg"

# --- the two cards, as an object -----------------------------------------
# The anchor is natively 3:2 (1536x1024) and the film's image card is 912x608,
# also 3:2 - so it lands with no crop at all and BOTH blank cards stay in
# frame. That matters here: two cards is the argument. Rendered at 1.10x.
ffmpeg -y -v error -i "$ANCHOR" \
  -vf "scale=1003:669:flags=lanczos,eq=brightness=-0.040:contrast=1.04:saturation=0.90,$LIFT,$SHARP" \
  -q:v 2 "$OUT/card-two-cards.jpg"

echo "staged:"
ls -la "$OUT"
