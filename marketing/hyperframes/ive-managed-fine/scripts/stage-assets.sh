#!/usr/bin/env bash
# Stage + grade existing plates into the six "I've Managed Fine" film projects.
#
# Nothing here GENERATES an asset. Every input already existed on disk before
# this campaign; this script only copies, upscales and colour-grades so the
# films cut against the HeyGen anchor take measured in
# marketing/creative/heygen/reference-tokens.json:
#
#   mean luminance 150-175 (a LIGHT frame), shadows lifted toward a muted
#   mauve-grey (#33272E), one small olive accent (#7FB449) - never a green field.
#
# Cost: $0. Local ffmpeg only, no provider call, no model call.
# Deterministic: same inputs + same filter string => same bytes.
set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJ="$(dirname "$HERE")"
REPO="$(cd "$PROJ/../../.." && pwd)"

SRC="$REPO/marketing/creative/plates/platform-dependency"
STAGE="$PROJ/assets/plates"
mkdir -p "$STAGE"

# Shadow lift toward #33272E (51,39,46): R > B > G keeps the mauve cast.
LIFT="colorlevels=romin=0.085:gomin=0.062:bomin=0.076"
SHARP="unsharp=5:5:0.45:5:5:0.0"

# --- Bright plates: used full-bleed or as large cards. Upscale to 1188x2128
#     (1.10x of the 1080x1920 frame) so a 1.10 camera push never samples below
#     delivered resolution.
grade_full () { # id  eq
  ffmpeg -y -v error -i "$SRC/pd-$1-vertical-9x16.jpg" \
    -vf "scale=1188:2128:flags=lanczos,$2,$LIFT,$SHARP" \
    -q:v 2 "$STAGE/pd-$1.jpg"
}

# pd-a2 — empty iron sign bracket on sun-bleached siding. Source YAVG 134.3.
grade_full a2 "eq=brightness=0.075:contrast=0.96:saturation=0.90"

# pd-a2-tight — the same wall, closer. A SEPARATE file rather than a second
# <img> pointing at pd-a2.jpg: two media nodes with an identical src are
# discovered twice by the compiler (lint: duplicate_media_discovery_risk).
# Cropped to the bracket and letterboxed to the 1080x680 strip it fills.
ffmpeg -y -v error -i "$SRC/pd-a2-vertical-9x16.jpg" \
  -vf "crop=768:484:0:760,scale=1188:748:flags=lanczos,eq=brightness=0.075:contrast=0.96:saturation=0.90,$LIFT,$SHARP" \
  -q:v 2 "$STAGE/pd-a2-tight.jpg"

# pd-b2 — card-file drawer bank, open drawer of index cards. Source YAVG 165.0.
grade_full b2 "eq=brightness=-0.010:contrast=1.02:saturation=0.92"

# pd-p  — blank card in a brass holder, olive clip beside it. Source YAVG 179.0.
grade_full p "eq=brightness=-0.048:contrast=1.04:saturation=0.94"

# pd-a1 — brass letter slot. Its upper half is a flat blown-out generation
# defect (documented in marketing/hyperframes/platform-dependency/README.md
# limitation 4). Framed out with a tighter 1550x2776 crop rendered from the
# ORIGINAL, so the tight crop does not compound two upscales.
ffmpeg -y -v error -i "$SRC/pd-a1-vertical-9x16.jpg" \
  -vf "scale=1550:2776:flags=lanczos,eq=brightness=-0.026:contrast=1.03:saturation=0.93,$LIFT,$SHARP" \
  -q:v 2 "$STAGE/pd-a1-tight.jpg"

# --- Dark plates: night and interior-dark sources (YAVG 31-35). They are used
#     as PHOTOGRAPHIC CARDS on the paper ground, never full-bleed. The first
#     pass lifted them only to ~78 YAVG, which dragged f2 to 146.2 and f1 to
#     149.9 on the signalstats scale — BELOW the 150 floor. brightness=0.310
#     lands them at 117-121, which is what brought both films back inside the
#     band. They still read as night; they are printed photographs, not
#     daylight.
grade_card () { # id  eq  outw  outh
  ffmpeg -y -v error -i "$SRC/pd-$1-vertical-9x16.jpg" \
    -vf "scale=$3:$4:flags=lanczos,$2,$LIFT,$SHARP" \
    -q:v 2 "$STAGE/pd-$1-card.jpg"
}

# pd-b1 — open cash drawer, coins and bills under a hanging bulb.
grade_card b1 "eq=brightness=0.310:contrast=1.02:saturation=0.90" 1188 2128
# pd-c1 — bedside lamp, phone face-down on a nightstand.
grade_card c1 "eq=brightness=0.310:contrast=1.02:saturation=0.90" 1188 2128
# pd-c2 — A-frame board on the sidewalk outside a closed shopfront.
grade_card c2 "eq=brightness=0.310:contrast=1.02:saturation=0.88" 1188 2128

# --- Second crops. A beat that shows the same subject closer needs its OWN
#     file: two <img> nodes with an identical src are discovered twice by the
#     compiler (lint: duplicate_media_discovery_risk). Each of these is a real
#     reframe of the same photograph, not a duplicate.

# pd-b2-tight — the open drawer of index cards, filling a 1080x500 band.
ffmpeg -y -v error -i "$SRC/pd-b2-vertical-9x16.jpg" \
  -vf "crop=768:356:0:700,scale=1188:551:flags=lanczos,eq=brightness=-0.010:contrast=1.02:saturation=0.92,$LIFT,$SHARP" \
  -q:v 2 "$STAGE/pd-b2-tight.jpg"

# pd-b1-tight — the cash drawer, as a small elevated card.
ffmpeg -y -v error -i "$SRC/pd-b1-vertical-9x16.jpg" \
  -vf "crop=768:835:0:400,scale=506:550:flags=lanczos,eq=brightness=0.310:contrast=1.02:saturation=0.90,$LIFT,$SHARP" \
  -q:v 2 "$STAGE/pd-b1-tight.jpg"

# pd-p-tight — the brass card holder, filling a 1080x360 band.
ffmpeg -y -v error -i "$SRC/pd-p-vertical-9x16.jpg" \
  -vf "crop=768:256:0:800,scale=1188:396:flags=lanczos,eq=brightness=-0.048:contrast=1.04:saturation=0.94,$LIFT,$SHARP" \
  -q:v 2 "$STAGE/pd-p-tight.jpg"

# pd-a1-band — the brass letter slot, filling a 1080x440 band.
ffmpeg -y -v error -i "$SRC/pd-a1-vertical-9x16.jpg" \
  -vf "crop=768:313:0:640,scale=1188:484:flags=lanczos,eq=brightness=-0.026:contrast=1.03:saturation=0.93,$LIFT,$SHARP" \
  -q:v 2 "$STAGE/pd-a1-band.jpg"

# --- Distribute to the six film projects. Each project owns its own copy so a
#     render never reaches outside its own directory.
copy_to () { # film-dir  file...
  local dir="$PROJ/$1/assets/plates"; shift
  mkdir -p "$dir"
  for f in "$@"; do cp "$STAGE/$f" "$dir/$f"; done
}

copy_to f1-thirty-years   pd-a2.jpg pd-a2-tight.jpg
copy_to f2-know-where     pd-c2-card.jpg pd-c1-card.jpg
copy_to f3-not-technical  pd-b2.jpg pd-b2-tight.jpg
copy_to f4-got-burned     pd-b1-card.jpg pd-b1-tight.jpg
copy_to f5-too-expensive  pd-p.jpg pd-p-tight.jpg
copy_to f6-retiring       pd-a1-tight.jpg pd-a1-band.jpg

echo "staged:"
for f in "$STAGE"/*.jpg; do
  y=$(ffmpeg -v error -i "$f" -vf "signalstats,metadata=print:key=lavfi.signalstats.YAVG:file=-" \
        -f null /dev/null 2>&1 | grep -o 'YAVG=[0-9.]*' | head -1)
  d=$(ffprobe -v error -select_streams v:0 -show_entries stream=width,height -of csv=p=0 "$f")
  printf "  %-22s %-10s %s\n" "$(basename "$f")" "$d" "$y"
done
