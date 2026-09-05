#!/usr/bin/env bash
# Mux each film's narration bed onto its silent render, then deliver stereo.
#
# The mux itself is the shared primitive, scripts/voice/narrate-film.sh, exactly
# as .agents/skills/famtastic-voice/SKILL.md specifies. The lead-in is 0 here
# because build-narration.py already bakes the lead silence into the bed, so the
# beat table and the audio stay in sync.
#
# The extra pass afterwards upmixes to stereo. Voicebox returns mono, and a mono
# AAC track is valid but some social players route it to one ear on headphones.
# Video is stream-copied, so the picture and its grade are untouched.
#
# Cost: $0. Local ffmpeg only.
set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJ="$(dirname "$HERE")"
REPO="$(cd "$PROJ/../../.." && pwd)"
NARRATE="$REPO/scripts/voice/narrate-film.sh"

for film in f1-thirty-years f2-know-where f3-not-technical f4-got-burned f5-too-expensive f6-retiring; do
  silent="$PROJ/$film/renders/$film-silent.mp4"
  bed="$PROJ/narration/$film.wav"
  tmp="$PROJ/$film/renders/.$film-mono.mp4"
  out="$PROJ/$film/renders/$film-1080x1920.mp4"

  [[ -f "$silent" ]] || { echo "BLOCKED: missing $silent — render it first." >&2; exit 1; }
  [[ -f "$bed" ]] || { echo "BLOCKED: missing $bed — run scripts/build-narration.py." >&2; exit 1; }

  echo "== $film"
  "$NARRATE" "$silent" "$bed" 0 "$tmp"
  # +3 dB restores the level the mono->stereo split costs: measured mean fell
  # from -20.7 to -23.7 dB and peak from -1.5 to -4.5 without it, which is
  # quieter than the HeyGen presenter these films cut against (-19.6 dB).
  # Peak lands back at -1.5 dB, so nothing clips.
  ffmpeg -v error -y -i "$tmp" -c:v copy -af "volume=3dB" -c:a aac -ac 2 -b:a 192k \
    -movflags +faststart "$out"
  rm -f "$tmp"

  ffprobe -v error -select_streams a:0 \
    -show_entries stream=codec_name,channels,sample_rate -of csv=p=0 "$out"
done
