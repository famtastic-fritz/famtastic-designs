#!/usr/bin/env bash
# Stage + grade the existing campaign assets into this HyperFrames project.
#
# Nothing here GENERATES an asset. Every input already existed before this
# project; this script only crops, upscales, colour-grades and trims files that
# are already on disk.
#
# Grade target: the HeyGen anchor take's MEASURED appearance
# (marketing/creative/heygen/reference-tokens.json) —
#   mean luminance 150-175 (a LIGHT frame; the anchor is 161.9),
#   shadows lifted to a muted mauve-grey #33272E,
#   one small olive accent #7FB449, roughly 1-2% of frame, never a field.
#
# Unlike ghost-town, this film uses the anchor grade. See README.md ->
# "Why this film IS graded to the anchor (and ghost-town is not)".
#
# Cost: $0. Local ffmpeg only, no provider call, no network.
# Deterministic: same inputs + same filter string => same bytes.
set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJ="$(dirname "$HERE")"
REPO="$(cd "$PROJ/../../.." && pwd)"

CAMP="$REPO/marketing/campaigns/campus-entrepreneurs"
PLATES="$REPO/marketing/creative/plates/platform-dependency"

OUT="$PROJ/assets"
mkdir -p "$OUT/plates"

for f in "$CAMP/images/01-campus-quad-entrepreneur-1x1.jpg" \
         "$CAMP/videos/01-campus-dorm-entrepreneur-9x16.mp4" \
         "$PLATES/pd-p-vertical-9x16.jpg" \
         "$PLATES/pd-b2-vertical-9x16.jpg"; do
  test -f "$f" || { echo "missing: $f" >&2; exit 1; }
done

# Shadow lift toward the measured #33272E (51,39,46). R > B > G keeps the mauve
# cast the anchor actually has instead of a neutral grey.
LIFT="colorlevels=romin=0.085:gomin=0.062:bomin=0.076"
SHARP="unsharp=5:5:0.45:5:5:0.0"

# --- Still plates -------------------------------------------------------------
# 9:16 -> 1188x2128 (1.10x frame) so a 1.10 camera push never samples below
# delivered resolution. eq values are tuned against measured source YAVG:
# quad 122.8, dorm still 102.3, pd-p 179.0, pd-b2 165.0.

# The campus quad at golden hour. Sits as a CARD on the paper, not full bleed,
# so it is graded a little brighter than a full-frame plate would be.
ffmpeg -y -v error -i "$CAMP/images/01-campus-quad-entrepreneur-1x1.jpg" \
  -vf "scale=1584:1584:flags=lanczos,eq=brightness=0.140:contrast=1.02:saturation=0.94,$LIFT,$SHARP" \
  -q:v 2 "$OUT/plates/cm-quad.jpg"

# Blank business card in a brass holder with a small OLIVE clip beside it. The
# clip is the accent incident for the turn beat — the plate supplies it, so the
# composition does not have to add a second green element.
ffmpeg -y -v error -i "$PLATES/pd-p-vertical-9x16.jpg" \
  -vf "scale=1188:2128:flags=lanczos,eq=brightness=-0.018:contrast=1.04:saturation=0.96,$LIFT,$SHARP" \
  -q:v 2 "$OUT/plates/cm-card.jpg"

# Drawer of blank index cards against a window; carries its own olive latch.
ffmpeg -y -v error -i "$PLATES/pd-b2-vertical-9x16.jpg" \
  -vf "scale=1188:2128:flags=lanczos,eq=brightness=0.036:contrast=1.02:saturation=0.95,$LIFT,$SHARP" \
  -q:v 2 "$OUT/plates/cm-drawer.jpg"

# --- The campaign's own video, as a graded silent picture plate ---------------
# 01-campus-dorm-entrepreneur-9x16.mp4 is already 1080x1920 @ 30fps, which is
# exactly the composition size. It measures YAVG 102.0 over 828 frames — far
# below the anchor band — so it is graded here rather than in CSS, and the audio
# is stripped: the framework plays the bed from a separate <audio> element.
ffmpeg -y -v error -i "$CAMP/videos/01-campus-dorm-entrepreneur-9x16.mp4" \
  -an -vf "eq=brightness=0.268:contrast=1.02:saturation=0.93,$LIFT" \
  -c:v libx264 -profile:v high -pix_fmt yuv420p -crf 17 -preset medium \
  "$OUT/campus-dorm.mp4"

# --- The bed -----------------------------------------------------------------
# The same MP4's audio track, which the campaign already ships attached to this
# exact drop. Trimmed to the film's 27.5s, level-trimmed to sit under type
# rather than over it, and faded out over the last 1.6s so the film does not end
# on a hard cut. An agent cannot audition audio, so nothing here is a creative
# judgement about the track — only its level, length and tail.
ffmpeg -y -v error -i "$CAMP/videos/01-campus-dorm-entrepreneur-9x16.mp4" \
  -vn -t 27.5 -af "volume=0.55,afade=t=out:st=25.9:d=1.6" \
  -c:a aac -b:a 160k "$OUT/campus-bed.m4a"

echo "staged:"
for f in "$OUT/plates"/cm-*.jpg "$OUT/campus-dorm.mp4"; do
  y=$(ffmpeg -v error -i "$f" -vf "signalstats,metadata=print:key=lavfi.signalstats.YAVG:file=-" \
        -f null /dev/null 2>&1 | grep -o '=[0-9.]*$' | tr -d '=' \
        | awk '{s+=$1;n++} END{printf "%.1f", s/n}')
  printf "  %-26s YAVG %s\n" "$(basename "$f")" "$y"
done
printf "  %-26s %s\n" "campus-bed.m4a" \
  "$(ffprobe -v error -show_entries format=duration -of csv=p=0 "$OUT/campus-bed.m4a")s"
