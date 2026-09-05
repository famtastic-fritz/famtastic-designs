#!/usr/bin/env bash
# Stage + grade the existing campaign assets into this HyperFrames project.
#
# Nothing here GENERATES an asset. Every input already existed before this
# project; this script only copies, upscales and colour-grades them so they cut
# against the HeyGen anchor take measured in
# marketing/creative/heygen/reference-tokens.json:
#
#   mean luminance ~162/255 (a LIGHT frame), shadows lifted to a muted
#   mauve-grey (#33272E), one small olive accent (#7FB449) - never a green field.
#
# Cost: $0. Local ffmpeg only, no provider call.
# Deterministic: same inputs + same ffmpeg filter string => same bytes.
set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJ="$(dirname "$HERE")"
REPO="$(cd "$PROJ/../../.." && pwd)"

SRC_PLATES="$REPO/marketing/creative/plates/platform-dependency"
SRC_ANCHOR="$REPO/marketing/creative/anchors/pd-anchor-counter-16x9.png"
SRC_TAKE="$REPO/marketing/creative/heygen/renders/take-a-platform-dependency.mp4"

OUT="$PROJ/assets"
mkdir -p "$OUT/plates"

# Shadow lift toward #33272E (51,39,46): R > B > G keeps the mauve cast.
LIFT="colorlevels=romin=0.085:gomin=0.062:bomin=0.076"
SHARP="unsharp=5:5:0.45:5:5:0.0"

# 9:16 plates -> 1188x2128 (1.10x of the 1080x1920 frame, so a 1.10 camera
# push never samples below the delivered resolution). Per-plate eq lands each
# one in the 150-175 luminance band measured off the anchor.
grade_v () { # id  eq
  ffmpeg -y -v error -i "$SRC_PLATES/pd-$1-vertical-9x16.jpg" \
    -vf "scale=1188:2128:flags=lanczos,$2,$LIFT,$SHARP" \
    -q:v 2 "$OUT/plates/pd-$1.jpg"
}

grade_v a1 "eq=brightness=-0.030:contrast=1.03:saturation=0.94"

# pd-a1 carries a generation defect: the door's upper half is a flat, blown-out
# near-white rectangle with hard edges that reads as a rendering artifact, not a
# painted panel. It is over half the image, so it cannot be retouched out - the
# fix is framing. This tighter 1395x2500 crop is placed so the defect sits above
# the paper band in scenes 1 and 6 and never reaches the visible frame. Rendered
# from the ORIGINAL rather than from the graded 1188px copy, so the tighter crop
# does not compound two upscales.
ffmpeg -y -v error -i "$SRC_PLATES/pd-a1-vertical-9x16.jpg" \
  -vf "scale=1550:2776:flags=lanczos,eq=brightness=-0.030:contrast=1.03:saturation=0.94,$LIFT,$SHARP" \
  -q:v 2 "$OUT/plates/pd-a1-tight.jpg"
grade_v a2 "eq=brightness=0.072:contrast=0.97:saturation=0.92"
grade_v b2 "eq=brightness=-0.012:contrast=1.02:saturation=0.93"
grade_v p  "eq=brightness=-0.042:contrast=1.04:saturation=0.94"

# 16:9 flagship anchor -> 1584x891 (1.10x of the 1440x810 card it sits in).
ffmpeg -y -v error -i "$SRC_ANCHOR" \
  -vf "scale=1584:891:flags=lanczos,eq=brightness=0.052:contrast=1.02:saturation=0.95,$LIFT,$SHARP" \
  -q:v 2 "$OUT/anchor-counter.jpg"

# Presenter take, verbatim. Carries BOTH the muted picture inset and, through a
# separate <audio> element pointed at this same file, the narration bed.
cp "$SRC_TAKE" "$OUT/take-a-platform-dependency.mp4"

echo "staged:"
ls -la "$OUT" "$OUT/plates"
