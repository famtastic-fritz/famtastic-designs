#!/usr/bin/env bash
# Stage + grade existing plates into this HyperFrames project.
#
# Nothing here GENERATES an asset. Both plates existed before this project.
# This script crops, upscales and colour-grades them into the contract measured
# off the campaign's premium anchor
# (marketing/creative/heygen/reference-tokens.json):
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

PLATES="$REPO/marketing/creative/plates/platform-dependency"

OUT="$PROJ/assets"
mkdir -p "$OUT"

LIFT="colorlevels=romin=0.085:gomin=0.062:bomin=0.076"
SHARP="unsharp=5:5:0.42:5:5:0.0"

# --- the one photograph --------------------------------------------------
# pd-a2 vertical: a wrought-iron sign bracket with a hook, a chain, and no
# sign, on weathered timber in low sun. The faded rectangle where the sign used
# to be is still on the wall. That is the entire hook in one photograph - the
# shop is there and there is nothing to read - and it is the plate
# marketing/creative/plates/prompt-library.json itself files under the
# `ghost-town` palette for exactly this claim.
#
# Brightened into the anchor's band without touching its warmth: the low sun is
# the subject, so saturation stays near source.
#
# This is the ONLY plate in the film. pd-a2's 16:9 variant (an unmarked door in
# a sun-bleached alley) was staged as a second full-bleed plate and cut: a 9:16
# slice of a 1376x768 source is a 2.75x enlargement, and at frame size the door
# panel reads mushy. The 9:16 source is 768x1376, which supports exactly one
# full-bleed framing at 1.55x and no tighter reframe. See README.md.
ffmpeg -y -v error -i "$PLATES/pd-a2-vertical-9x16.jpg" \
  -vf "scale=1188:2128:flags=lanczos,eq=brightness=0.070:contrast=1.02:saturation=0.90,$LIFT,$SHARP" \
  -q:v 2 "$OUT/plate-bracket.jpg"

echo "staged:"
ls -la "$OUT"
