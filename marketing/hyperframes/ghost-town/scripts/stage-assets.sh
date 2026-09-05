#!/usr/bin/env bash
# Stage + grade existing repo assets into this HyperFrames project.
#
# Nothing here GENERATES an asset. Every input already existed before this
# project; this script only crops, upscales and colour-grades photographs that
# are already on disk, so they read in the Ghost Town palette:
#
#   ground  #17120D  warm near-black earth  (docs/marketing/CAMPAIGN_ART_DIRECTION_V1.md)
#   accent  #D9A441  one warm amber incident
#   "Everything else bleached, dry and desaturated: bone, sand, faded paint,
#    grey weathered timber. No blue, no green, no neon, no cool light anywhere."
#     -- marketing/creative/plates/prompt-library.json, palettes."ghost-town"
#
# This is DELIBERATELY NOT the anchor grade used by
# marketing/hyperframes/platform-dependency. See README.md -> "Why this film is
# not graded to the anchor".
#
# Cost: $0. Local ffmpeg only, no provider call, no network.
# Deterministic: same inputs + same filter string => same bytes.
set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJ="$(dirname "$HERE")"
REPO="$(cd "$PROJ/../../.." && pwd)"

SRC="$REPO/marketing/creative/plates/platform-dependency"
OUT="$PROJ/assets/plates"
mkdir -p "$OUT"

for f in a2 b1 b2 c1 c2 p; do
  test -f "$SRC/pd-$f-vertical-9x16.jpg" || { echo "missing: $SRC/pd-$f-vertical-9x16.jpg" >&2; exit 1; }
done

# --- The ghost-town look, as three composable ffmpeg stages -------------------
#
# 1. DRY THE LIGHT OUT. Kill every cool cast the source carries. c2 in
#    particular is a blue-hour photograph and the palette forbids blue outright,
#    so the blue channel is pulled hard and the reds pushed at both ends.
DRY="colorbalance=rs=0.11:gs=-0.01:bs=-0.19:rm=0.10:gm=0.01:bm=-0.17:rh=0.07:gh=0.02:bh=-0.12"

# 1b. PULL THE WHITE POINT TO A LOW SUN. colorbalance alone left a green-teal
#    sky on a2 and a blue-hour cast on c2 that the palette forbids outright;
#    both survived until the graded plates were looked at. A per-plate
#    colortemperature pass is what actually removes them. Lower Kelvin = warmer.
TEMP () { echo "colortemperature=temperature=$1:mix=1"; }

# 2. SET THE FLOOR AT THE PALETTE GROUND. #17120D = (23,18,13)/255 =
#    (0.090, 0.071, 0.051). colorlevels' *omin raises the OUTPUT minimum, so
#    the darkest pixel in every plate becomes literally the palette's earth
#    rather than an arbitrary black. Highlights are capped below pure white so
#    nothing in a "sun-bleached" frame clips to paper white.
FLOOR="colorlevels=romin=0.090:gomin=0.071:bomin=0.051:romax=0.960:gomax=0.930:bomax=0.880"

# 3. Sharpen last, after the upscale, at the same strength the accepted film used.
SHARP="unsharp=5:5:0.45:5:5:0.0"

# 9:16 plates -> 1188x2128 (1.10x of the 1080x1920 frame), so a 1.10 camera push
# never samples below delivered resolution. Per-plate eq is tuned against a
# measured YAVG of the SOURCE (a2 134.3, b1 30.8, b2 165.0, c1 32.5, c2 35.1,
# p 179.0) toward the per-plate targets recorded in frame.md.
grade () { # id  kelvin  eq
  ffmpeg -y -v error -i "$SRC/pd-$1-vertical-9x16.jpg" \
    -vf "scale=1188:2128:flags=lanczos,$3,$DRY,$(TEMP "$2"),$FLOOR,$SHARP" \
    -q:v 2 "$OUT/gt-$1.jpg"
}

# a2  weathered timber, empty iron sign bracket, chain, blank painted board.
#     The brightest plate in the film and the one the argument rests on, so it
#     comes down only as far as it has to. 4200K just to kill a green sky.
grade a2 4200 "eq=brightness=-0.175:contrast=1.06:saturation=0.72"
# c2  blank A-frame board at BLUE HOUR -> pulled all the way to sodium dusk.
grade c2 2600 "eq=brightness=0.045:contrast=1.05:saturation=0.62"
# b1  near-empty cash drawer under a single bulb.
grade b1 3400 "eq=brightness=0.040:contrast=1.04:saturation=0.66"
# c1  phone face-down beside a bedside lamp.
grade c1 3200 "eq=brightness=0.036:contrast=1.03:saturation=0.64"
# b2  card-index drawer of blank cards against a window. Used only as a small
#     card on the earth ground, never full bleed.
grade b2 3800 "eq=brightness=-0.255:contrast=1.05:saturation=0.68"
# p   blank business card in a brass holder. This is the beat where the name
#     arrives, so it keeps the most light of the close-up plates.
grade p  3800 "eq=brightness=-0.235:contrast=1.04:saturation=0.70"

# The 15s short cut is its own project (shorts/dm-trap) because `check` takes a
# project DIRECTORY and has no per-composition flag — a standalone composition
# sitting beside index.html can be rendered with `render -c` but never gated.
# It needs exactly one plate, copied rather than symlinked so the compiler
# resolves it inside its own project root.
mkdir -p "$PROJ/shorts/dm-trap/assets/plates"
cp "$OUT/gt-c1.jpg" "$PROJ/shorts/dm-trap/assets/plates/gt-c1.jpg"

echo "staged:"
for f in "$OUT"/gt-*.jpg; do
  y=$(ffmpeg -v error -i "$f" -vf "signalstats,metadata=print:key=lavfi.signalstats.YAVG:file=-" \
        -f null /dev/null 2>&1 | grep -o '=[0-9.]*$' | tr -d '=')
  printf "  %-28s YAVG %s\n" "$(basename "$f")" "$y"
done
