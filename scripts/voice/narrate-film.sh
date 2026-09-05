#!/usr/bin/env bash
# Mux a generated narration track onto a silent HyperFrames render.
#
#   ./narrate-film.sh <video.mp4> <narration.wav> <lead_seconds> <out.mp4>
#
# The lead offset stops narration landing on frame one, and apad extends the
# track with silence so a shorter voice-over does not truncate the picture
# (-shortest would otherwise cut the film to the length of the audio).
set -euo pipefail
video="$1"; wav="$2"; lead="$3"; out="$4"
ffmpeg -v error -i "$video" -itsoffset "$lead" -i "$wav" \
  -filter_complex "[1:a]aresample=48000,apad[a]" \
  -map 0:v -map "[a]" -shortest \
  -c:v copy -c:a aac -b:a 192k -movflags +faststart "$out" -y
ffprobe -v error -select_streams a:0 -show_entries stream=codec_name -of csv=p=0 "$out"
ffmpeg -hide_banner -i "$out" -af volumedetect -f null /dev/null 2>&1 | grep -E "mean_volume|max_volume"
