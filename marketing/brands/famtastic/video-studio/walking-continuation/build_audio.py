#!/usr/bin/env python3
"""Assemble the source-bound dialogue and supplied local voiceover into one AAC master.

This script performs only local FFmpeg work. It never calls a speech service,
changes the source movie, or overwrites an earlier assembly directory.
"""
from __future__ import annotations

import argparse
import array
import hashlib
import json
import math
import shutil
import subprocess
import sys
import wave
from pathlib import Path

HERE = Path(__file__).resolve().parent
REPO = HERE.parents[4]
SOURCE_SHA = "95ae3ca64a6eaee00c772ddd70063c210b55dff03632b9690ec645ffa2bae49f"
BRIDGE_SHA = "32bfac2cada414fa1b6144d35c25a704d63f9d947b92f7b9af314e7382bbc1c6"
NARRATION_WAV_SHA = "428fd8a053d68fd1ba033047dc8c067d369c2b35367a2c5e87ba694a07c9d51e"
SOURCE_TRANSCRIPT_SHA = "c47c3d8981a535774f213954454347bc9a477192dae501a032c0cec723df79d5"
VOICE_CUES_SHA = "4d2e82eac20f2da16733b8b361aadfc8f7ccc12499900da61454ba4e01943e9d"
VOICE_RECEIPT_SHA = "c4c64a49651838f66f1b73d8958843e5c0f7140a2c03516adf88afefc785234c"
VOICE_LEVELS_SHA = "6b60c07e596dde9311a5b7ab9bf92697199b4887d2268c8eb4aebe807aef2ccc"
BRIDGE_ALIGNMENT_SHA = "2d4fcf431167ae30a0f2c0791dcc3e2862ced82d2f170cfa7c2475003f6c621a"
FPS = 24
FRAME_COUNT = 854
DURATION = FRAME_COUNT / FPS
SAMPLE_RATE = 48_000
SOURCE_RANGES = [
    {"role": "opening", "source_start": 0.0, "source_end": 5.92, "output_start": 0.0},
    {"role": "website_gesture", "source_start": 7.28, "source_end": 12.40, "output_start": 16.136},
    {"role": "closing", "source_start": 26.24, "source_end": 29.70, "output_start": 31.344},
]


def digest(path: Path) -> str:
    with path.open("rb") as stream:
        return hashlib.file_digest(stream, "sha256").hexdigest()


def command_output(command: list[str], *, timeout: int = 120) -> str:
    result = subprocess.run(command, capture_output=True, text=True, timeout=timeout, check=True)
    return result.stdout


def probe(path: Path, executable: str) -> dict:
    return json.loads(command_output([executable, "-v", "error", "-show_streams", "-show_format", "-of", "json", str(path)]))


def tokens_for_text(text: str) -> list[str]:
    import re
    return [re.sub(r"[^a-z0-9']", "", match.group(0).casefold().replace("’", "'"))
            for match in re.finditer(r"[a-z0-9]+(?:['’][a-z0-9]+)?(?:\.[a-z0-9]+)+|[a-z0-9]+(?:['’][a-z0-9]+)?", text, re.I)]


def timed_words(transcription: dict) -> list[dict]:
    """Join whisper.cpp BPE fragments into words while retaining DTW intervals."""
    words = []
    for segment in transcription.get("transcription", []):
        current = None
        for token in segment.get("tokens", []):
            piece = str(token.get("text", ""))
            if not piece or piece.startswith("[_"):
                continue
            stamp = token.get("timestamps", {})
            try:
                start = _time(stamp["from"])
                end = _time(stamp["to"])
            except (KeyError, ValueError):
                continue
            if piece.startswith(" "):
                if current:
                    words.append(current)
                current = {"text": piece.strip(), "start": start, "end": end}
            elif current is not None:
                current["text"] += piece
                current["end"] = max(current["end"], end)
        if current:
            words.append(current)
    return words


def _time(value: str) -> float:
    # whisper.cpp timestamp form: HH:MM:SS,mmm
    hours, minutes, remainder = value.split(":")
    seconds, millis = remainder.split(",")
    return int(hours) * 3600 + int(minutes) * 60 + int(seconds) + int(millis) / 1000


def group_caption_words(text: str) -> list[int]:
    """Chosen phrase groups keep portrait captions to three-to-six words."""
    known = {
        "You've got the hustle. Still no website? FAMtastic Designs gives your business a home.": [4, 3, 3, 4],
        "Show your work. Explain your services and collect inquiries while you're busy.": [4, 5, 3],
        "Give your business a home.": [5],
        "At FAMtasticDesigns.com.": [2],
        "Your vision. Your hustle. Your grind. The business you’re trying to build.": [4, 4, 4],
        "So instead of asking you to spend a fortune just to get started, we invest in your vision first.": [4, 5, 4, 6],
        "We help you build the plan. We give you the tools and strategy to become a stronger, better business.": [6, 5, 4, 4],
        "Why? Because we’re confident in what we do.": [4, 4],
        "You grow. We grow.": [4],
    }
    try:
        groups = known[text]
    except KeyError as exc:
        raise ValueError(f"No reviewed caption grouping for spoken phrase: {text}") from exc
    count = len(tokens_for_text(text))
    if sum(groups) != count or any(size < 2 or size > 6 for size in groups):
        raise ValueError(f"Caption group counts do not match words in phrase: {text} ({count})")
    return groups


def chunks_for_line(text: str, words: list[dict], *, start: float, end: float,
                    kind: str, cue_index: int | None = None) -> list[dict]:
    expected = tokens_for_text(text)
    actual = [tokens_for_text(word["text"])[0] if tokens_for_text(word["text"]) else "" for word in words]
    if actual != expected:
        raise ValueError(f"Local ASR word sequence did not match frozen source text: {text!r}; got {actual!r}")
    boundaries = group_caption_words(text)
    measured_start = words[0]["start"]
    measured_end = words[-1]["end"]
    scale = (end - start) / max(measured_end - measured_start, 1e-6)
    result = []
    offset = 0
    display_words = text.split()
    for count in boundaries:
        selected = words[offset:offset + count]
        phrase = " ".join(display_words[offset:offset + count])
        begin = start + (selected[0]["start"] - measured_start) * scale
        finish = start + (selected[-1]["end"] - measured_start) * scale
        item = {"kind": kind, "text": phrase, "start": round(max(start, begin), 6),
                "end": round(min(end, finish), 6),
                "timing_basis": "local whisper.cpp small.en DTW word timestamps, line-fit to frozen speech cue"}
        if cue_index is not None:
            item["voice_cue_index"] = cue_index
        result.append(item)
        offset += count
    return result


def write_s16_comparison(receipt: dict, source_pcm: Path, output_pcm: Path) -> None:
    def load(path: Path) -> tuple[array.array, int, int]:
        with wave.open(str(path), "rb") as wav:
            if wav.getsampwidth() != 2:
                raise ValueError(f"Expected 16-bit PCM in {path.name}")
            channels, rate = wav.getnchannels(), wav.getframerate()
            samples = array.array("h")
            samples.frombytes(wav.readframes(wav.getnframes()))
            if sys.byteorder != "little":
                samples.byteswap()
            return samples, channels, rate

    src, src_channels, src_rate = load(source_pcm)
    out, out_channels, out_rate = load(output_pcm)
    if (src_channels, src_rate) != (2, SAMPLE_RATE) or (out_channels, out_rate) != (2, SAMPLE_RATE):
        raise ValueError("Comparison PCM must be 48 kHz stereo S16")
    comparisons = []
    for region in SOURCE_RANGES:
        source_start = round(region["source_start"] * SAMPLE_RATE) * 2
        output_start = round(region["output_start"] * SAMPLE_RATE) * 2
        count = round((region["source_end"] - region["source_start"]) * SAMPLE_RATE) * 2
        left = src[source_start:source_start + count]
        right = out[output_start:output_start + count]
        if len(left) != count or len(right) != count:
            raise ValueError(f"PCM comparison window was truncated: {region['role']}")
        # Signal correlation is computed without retaining or printing samples.
        n = len(left)
        mean_a = sum(left) / n
        mean_b = sum(right) / n
        var_a = sum((value - mean_a) ** 2 for value in left)
        var_b = sum((value - mean_b) ** 2 for value in right)
        covariance = sum((a - mean_a) * (b - mean_b) for a, b in zip(left, right))
        correlation = covariance / math.sqrt(var_a * var_b) if var_a and var_b else None
        comparisons.append({
            **region,
            "samples_per_channel": count // 2,
            "decoded_pcm_pearson_correlation": correlation,
            "maximum_absolute_sample_delta": max(abs(a - b) for a, b in zip(left, right)),
            "scope": "Independently decoded source AAC versus the assembled pre-AAC PCM; signal evidence, not subjective listening.",
        })
    receipt["source_dialogue_pcm_comparison"] = comparisons


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--original", type=Path, required=True)
    parser.add_argument("--voice-master", type=Path, required=True,
                        help="The supplied, loudness-measured narration.wav; not a new TTS request.")
    parser.add_argument("--voice-cues", type=Path, required=True)
    parser.add_argument("--voice-receipt", type=Path, required=True)
    parser.add_argument("--voice-levels", type=Path, required=True)
    parser.add_argument("--bridge-alignment", type=Path, required=True,
                        help="Local whisper.cpp small.en DTW transcript for the supplied bridge WAV.")
    parser.add_argument("--transcription", type=Path, required=True,
                        help="Retained local source ASR JSON with word timestamps.")
    parser.add_argument("--output-dir", type=Path, required=True,
                        help="Must be a new directory; prior audio assemblies are immutable.")
    args = parser.parse_args()

    ffmpeg, ffprobe = shutil.which("ffmpeg"), shutil.which("ffprobe")
    if not ffmpeg or not ffprobe:
        parser.error("Local ffmpeg and ffprobe are required.")
    for path in (args.original, args.voice_master, args.voice_cues, args.voice_receipt,
                 args.voice_levels, args.bridge_alignment, args.transcription):
        if not path.is_file():
            parser.error(f"Missing explicit local input: {path}")
    if args.output_dir.exists():
        parser.error("Use a fresh output directory; earlier audio assemblies are retained.")
    if digest(args.original) != SOURCE_SHA:
        parser.error("Supplied source SHA does not match the fixed source-performance timing.")
    if digest(args.voice_master) != NARRATION_WAV_SHA:
        parser.error("Voice master SHA is not the reviewed local narration WAV.")
    if digest(args.transcription) != SOURCE_TRANSCRIPT_SHA:
        parser.error("Source transcription SHA differs from the retained local ASR evidence.")
    if digest(HERE / "bridge-script.txt") != BRIDGE_SHA:
        parser.error("Bridge script bytes changed after local voice synthesis.")
    voice_cues_hash = digest(args.voice_cues)
    if voice_cues_hash != VOICE_CUES_SHA:
        parser.error("Voice cue bytes do not match the reviewed synthesis run.")
    if digest(args.voice_receipt) != VOICE_RECEIPT_SHA:
        parser.error("Voice synthesis receipt differs from the reviewed local run.")
    if digest(args.voice_levels) != VOICE_LEVELS_SHA:
        parser.error("Voice level receipt differs from the reviewed local run.")
    if digest(args.bridge_alignment) != BRIDGE_ALIGNMENT_SHA:
        parser.error("Bridge ASR bytes differ from the retained local alignment pass.")

    narration_cues = json.loads(args.voice_cues.read_text(encoding="utf-8"))
    lines = (HERE / "bridge-script.txt").read_text(encoding="utf-8").splitlines()
    if len(narration_cues) != 5 or [row.get("source_text") for row in narration_cues] != lines:
        parser.error("Narration cues must bind byte-for-byte to the five frozen bridge-script lines.")
    synthesis_receipt = json.loads(args.voice_receipt.read_text(encoding="utf-8"))
    synthesis_lines = synthesis_receipt.get("lines", [])
    if len(synthesis_lines) != len(narration_cues):
        parser.error("The frozen synthesis receipt does not contain all five line records.")
    for index, (cue, receipt_line) in enumerate(zip(narration_cues, synthesis_lines)):
        expected_start = float(narration_cues[index - 1]["end"]) + 0.18 if index else 0.0
        if (cue.get("index") != index or receipt_line.get("index") != index
                or cue.get("source_text") != receipt_line.get("source_text")
                or abs(float(cue.get("start", -1)) - expected_start) > 1e-6
                or abs(float(cue.get("end", -1)) - (float(cue.get("start", -1)) + float(receipt_line.get("seconds", -99)))) > 1e-6):
            parser.error(f"Voice cue {index} differs from its exact script/text/timing in the frozen synthesis receipt.")
    split = float(narration_cues[2]["start"])
    voice_end = float(narration_cues[-1]["end"])
    if abs(split - 10.216) > 1e-6 or abs(voice_end - 20.304) > 1e-6:
        parser.error("Narration split/duration differs from the retained local synthesis receipt.")
    with wave.open(str(args.voice_master), "rb") as wav:
        if (wav.getframerate(), wav.getnchannels(), wav.getsampwidth()) != (24_000, 1, 2):
            parser.error("Expected the supplied 24 kHz mono S16 narration master.")
        voice_duration = wav.getnframes() / wav.getframerate()
    if abs(voice_duration - voice_end) > 1 / 24:
        parser.error("Voice master duration does not match its retained cue end.")

    src_probe = probe(args.original, ffprobe)
    audio_streams = [stream for stream in src_probe.get("streams", []) if stream.get("codec_type") == "audio"]
    if len(audio_streams) != 1 or audio_streams[0].get("codec_name") != "aac":
        parser.error("Expected exactly one AAC dialogue stream in the supplied original.")
    transcript = json.loads(args.transcription.read_text(encoding="utf-8"))
    transcript_segments = transcript.get("transcription", [])
    if len(transcript_segments) < 5:
        parser.error("Retained source ASR must include the verified opening, gesture, and close segments.")
    bridge_transcript = json.loads(args.bridge_alignment.read_text(encoding="utf-8"))
    bridge_words = timed_words(bridge_transcript)
    bridge_word_counts = [len(tokens_for_text(line)) for line in lines]
    if len(bridge_words) != sum(bridge_word_counts):
        parser.error(f"Local bridge DTW word count {len(bridge_words)} differs from the frozen script ({sum(bridge_word_counts)}).")
    bridge_line_words = []
    cursor = 0
    for text, count in zip(lines, bridge_word_counts):
        group = bridge_words[cursor:cursor + count]
        if [tokens_for_text(word["text"])[0] for word in group] != tokens_for_text(text):
            parser.error(f"Local bridge DTW text does not exactly match frozen line {len(bridge_line_words)}.")
        bridge_line_words.append(group)
        cursor += count

    # The first voice segment includes the source TTS pause following line 2;
    # line 3 resumes at its recorded 10.216 s cue. No voice pixels or old movie
    # voiceover are synthesized here.
    first_voice_duration = split
    second_voice_duration = voice_end - split
    base_duration = 5.92 + first_voice_duration + 5.12 + second_voice_duration + 3.46
    tail_seconds = DURATION - base_duration
    if tail_seconds <= 0:
        parser.error("Frame-aligned final hold is not positive.")

    args.output_dir.mkdir(parents=True)
    master_wav = args.output_dir / "soundtrack-assembly.wav"
    master_m4a = args.output_dir / "soundtrack.m4a"
    decoded_source = args.output_dir / "original-dialogue-decoded.wav"
    voice_receipt = synthesis_receipt
    voice_levels = json.loads(args.voice_levels.read_text(encoding="utf-8"))
    filter_complex = (
        f"[0:a:0]atrim=start=0:end=5.92,asetpts=PTS-STARTPTS,aresample={SAMPLE_RATE},"
        "aformat=sample_fmts=fltp:channel_layouts=stereo[opening];"
        f"[1:a:0]atrim=start=0:end={split:.9f},asetpts=PTS-STARTPTS,aresample={SAMPLE_RATE},"
        f"pan=stereo|c0=c0|c1=c0,aformat=sample_fmts=fltp:channel_layouts=stereo[bridge_a];"
        f"[0:a:0]atrim=start=7.28:end=12.40,asetpts=PTS-STARTPTS,aresample={SAMPLE_RATE},"
        "aformat=sample_fmts=fltp:channel_layouts=stereo[gesture];"
        f"[1:a:0]atrim=start={split:.9f}:end={voice_end:.9f},asetpts=PTS-STARTPTS,aresample={SAMPLE_RATE},"
        f"pan=stereo|c0=c0|c1=c0,aformat=sample_fmts=fltp:channel_layouts=stereo[bridge_b];"
        f"[0:a:0]atrim=start=26.24:end=29.70,asetpts=PTS-STARTPTS,aresample={SAMPLE_RATE},"
        "aformat=sample_fmts=fltp:channel_layouts=stereo[close];"
        "[opening][bridge_a][gesture][bridge_b][close]concat=n=5:v=0:a=1[body];"
        f"[body]apad=pad_dur={tail_seconds:.9f},atrim=duration={DURATION:.12f},"
        "asetpts=PTS-STARTPTS,asplit=2[pcm][aac]"
    )
    ffmpeg_cmd = [ffmpeg, "-hide_banner", "-loglevel", "info", "-nostdin", "-y",
                  "-i", str(args.original), "-i", str(args.voice_master),
                  "-filter_complex", filter_complex,
                  "-map", "[pcm]", "-c:a", "pcm_s16le", "-ar", str(SAMPLE_RATE), "-ac", "2", "-threads", "1", str(master_wav),
                  "-map", "[aac]", "-c:a", "aac", "-b:a", "192k", "-ar", str(SAMPLE_RATE), "-ac", "2", "-threads", "1", "-movflags", "+faststart", str(master_m4a)]
    (args.output_dir / "ffmpeg-command.json").write_text(json.dumps(ffmpeg_cmd, indent=2) + "\n", encoding="utf-8")
    result = subprocess.run(ffmpeg_cmd, capture_output=True, text=True, timeout=300)
    (args.output_dir / "ffmpeg-assembly.log").write_text(result.stdout + result.stderr, encoding="utf-8")
    if result.returncode:
        raise SystemExit(f"Local audio assembly failed with ffmpeg exit {result.returncode}; see {args.output_dir / 'ffmpeg-assembly.log'}")

    decode_cmd = [ffmpeg, "-hide_banner", "-loglevel", "error", "-nostdin", "-y", "-i", str(args.original),
                  "-map", "0:a:0", "-vn", "-ar", str(SAMPLE_RATE), "-ac", "2", "-c:a", "pcm_s16le", str(decoded_source)]
    decode_result = subprocess.run(decode_cmd, capture_output=True, text=True, timeout=180)
    (args.output_dir / "source-audio-decode.log").write_text(decode_result.stdout + decode_result.stderr, encoding="utf-8")
    if decode_result.returncode:
        raise SystemExit("Could not decode the original AAC dialogue for bounded correlation checks.")

    with wave.open(str(master_wav), "rb") as wav:
        pcm_probe = {"sample_rate": wav.getframerate(), "channels": wav.getnchannels(),
                     "sample_width_bytes": wav.getsampwidth(), "frames": wav.getnframes(),
                     "duration_seconds": wav.getnframes() / wav.getframerate()}
    expected_frames = round(DURATION * SAMPLE_RATE)
    if pcm_probe["frames"] != expected_frames:
        raise SystemExit(f"PCM master length is {pcm_probe['frames']} frames, expected {expected_frames}.")
    m4a_probe = probe(master_m4a, ffprobe)
    m4a_streams = [stream for stream in m4a_probe.get("streams", []) if stream.get("codec_type") == "audio"]
    if len(m4a_streams) != 1 or m4a_streams[0].get("codec_name") != "aac":
        raise SystemExit("Final audio master did not encode to exactly one AAC stream.")
    m4a_duration = float(m4a_probe["format"]["duration"])
    if m4a_duration > DURATION + 1 / FPS or abs(m4a_duration - DURATION) > 1 / FPS:
        raise SystemExit(f"AAC master duration {m4a_duration} differs from the frame-aligned canvas by more than one frame.")

    regions = [
        {"role": "opening", "source_start": 0.0, "source_end": 5.92, "output_start": 0.0},
        {"role": "voiceover_before_gesture", "voice_start": 0.0, "voice_end": split,
         "output_start": 5.92, "output_end": 5.92 + split},
        {"role": "website_gesture", "source_start": 7.28, "source_end": 12.40,
         "output_start": 5.92 + split},
        {"role": "voiceover_after_gesture", "voice_start": split, "voice_end": voice_end,
         "output_start": 5.92 + split + 5.12},
        {"role": "closing", "source_start": 26.24, "source_end": 29.70,
         "output_start": 5.92 + split + 5.12 + second_voice_duration},
        {"role": "silent_final_hold", "start": base_duration, "end": DURATION,
         "seconds": tail_seconds},
    ]
    out_ui_start = 5.92 + split
    out_bridge_after = out_ui_start + 5.12
    out_close_start = out_bridge_after + second_voice_duration
    source_words = timed_words(transcript)
    source_phrases = [
        ("You've got the hustle. Still no website? FAMtastic Designs gives your business a home.", [4, 3, 3, 4], 0.0, 0.0, "source_dialogue"),
        ("Show your work. Explain your services and collect inquiries while you're busy.", [3, 3, 3, 3], 7.28, out_ui_start - 7.28, "source_dialogue"),
        ("Give your business a home.", [5], 26.24, out_close_start - 26.24, "source_dialogue"),
        ("At FAMtasticDesigns.com.", [2], 27.71, out_close_start - 26.24, "source_dialogue"),
    ]
    captions = []
    source_text_groups = [
        ["You've got the hustle.", "Still no website?", "FAMtastic Designs gives", "your business a home."],
        ["Show your work. Explain", "your services and collect inquiries", "while you're busy."],
        ["Give your business a home."], ["At FAMtasticDesigns.com."],
    ]
    source_indexes = [(0, 0.0, 5.92), (1, 7.28, 12.40), (3, 26.24, 27.60), (4, 27.60, 29.70)]
    source_phrase_cursor = 0
    for (text, groups, source_begin, output_shift, kind), (segment_index, segment_begin, segment_end) in zip(source_phrases, source_indexes):
        groups = group_caption_words(text)
        segment = transcript_segments[segment_index]
        segment_words = timed_words({"transcription": [segment]})
        phrase_words = []
        for word in segment_words:
            if word["start"] < source_begin or word["start"] >= segment_end:
                continue
            phrase_words.append(word)
        expected = tokens_for_text(text)
        if [tokens_for_text(word["text"])[0] for word in phrase_words] != expected:
            # Source punctuation/ASR names may vary; maintain spoken text and use
            # the captured token bounds only where the words themselves agree.
            parser.error(f"Source ASR tokens no longer match the reviewed source-caption phrase: {text}")
        offset = 0
        display_words = text.split()
        for count in groups:
            selected = phrase_words[offset:offset + count]
            captions.append({"kind": kind, "text": " ".join(display_words[offset:offset + count]),
                             "start": round(selected[0]["start"] + output_shift, 6),
                             "end": round(selected[-1]["end"] + output_shift, 6),
                             "source_start": selected[0]["start"], "source_end": selected[-1]["end"],
                             "timing_basis": "retained local Whisper small.en token timestamps"})
            offset += count
        source_phrase_cursor += 1

    for index in range(5):
        cue = narration_cues[index]
        begin, end = float(cue["start"]), float(cue["end"])
        output_start = (5.92 + begin) if index < 2 else (out_bridge_after + begin - split)
        output_end = (5.92 + end) if index < 2 else (out_bridge_after + end - split)
        captions.extend(chunks_for_line(cue["source_text"], bridge_line_words[index], start=output_start,
                                        end=output_end, kind="local_voiceover", cue_index=index))
    captions.sort(key=lambda item: (item["start"], item["end"]))
    captions_json = {"schema": "famtastic.walking-continuation-captions.v1", "duration_seconds": DURATION,
                     "cues": captions}
    captions_path = args.output_dir / "audio-cues.json"
    captions_path.write_text(json.dumps(captions_json, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    shutil.copyfile(args.bridge_alignment, args.output_dir / "bridge-narration-dtw.json")
    shutil.copyfile(args.transcription, args.output_dir / "source-transcription.json")

    receipt = {
        "schema": "famtastic.walking-continuation-audio-assembly.v1",
        "status": "passed",
        "source": {"name": args.original.name, "sha256": digest(args.original),
                   "video_duration_seconds": 30.041667, "audio": "single AAC dialogue stream"},
        "bridge_voice": {"name": args.voice_master.name, "sha256": digest(args.voice_master),
                         "voice_cues_sha256": voice_cues_hash,
                         "synthesis_receipt_sha256": digest(args.voice_receipt),
                         "level_receipt_sha256": digest(args.voice_levels),
                         "voice": voice_receipt.get("campaign", {}).get("voice"),
                         "model_sha256": voice_receipt.get("campaign", {}).get("model_sha256"),
                         "duration_seconds": voice_duration,
                         "source_line_count": len(lines),
                         "provider_fee_usd": 0,
                         "level_receipt": voice_levels,
                         "dtw_transcript_sha256": digest(args.bridge_alignment),
                         "dtw_model": bridge_transcript.get("model", {}).get("type"),
                         "dtw_timing_note": "Local Whisper small.en DTW token timestamps are line-fit to the frozen Kokoro utterance cue bounds.",
                         "exact_script_asr_check": "all five aligned spoken word sequences match the frozen bridge text after case/punctuation normalization"},
        "timeline": {"fps": FPS, "frame_count": FRAME_COUNT, "duration_seconds": DURATION,
                     "audible_program_end_seconds": base_duration, "silent_final_hold_seconds": tail_seconds,
                     "regions": regions},
        "assembly": {"command_file": "ffmpeg-command.json", "log_file": "ffmpeg-assembly.log",
                     "method": "Single local FFmpeg filtergraph, 48 kHz stereo S16 PCM intermediate, one AAC encode at 192 kb/s.",
                     "pcm_master": {"path": master_wav.name, "sha256": digest(master_wav), **pcm_probe},
                     "aac_master": {"path": master_m4a.name, "sha256": digest(master_m4a),
                                    "duration_seconds": m4a_duration,
                                    "codec": m4a_streams[0].get("codec_name"),
                                    "sample_rate": int(m4a_streams[0].get("sample_rate", 0)),
                                    "channels": int(m4a_streams[0].get("channels", 0)),
                                    "bit_rate": m4a_streams[0].get("bit_rate")}},
        "caption_file": captions_path.name,
        "source_transcript_sha256": digest(args.transcription),
        "subjective_listening": "Not performed by the tool surface; no listening claim is made.",
        "publication": "not performed",
    }
    write_s16_comparison(receipt, decoded_source, master_wav)
    receipt_path = args.output_dir / "audio-assembly-receipt.json"
    receipt_path.write_text(json.dumps(receipt, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

    level_cmd = [ffmpeg, "-hide_banner", "-nostats", "-i", str(master_m4a), "-filter_complex", "ebur128=peak=true", "-f", "null", "-"]
    levels = subprocess.run(level_cmd, capture_output=True, text=True, timeout=180)
    (args.output_dir / "level-probe.log").write_text(levels.stdout + levels.stderr, encoding="utf-8")
    print(json.dumps({"status": "passed", "audio_master": str(master_m4a.resolve()),
                      "audio_master_sha256": digest(master_m4a), "pcm_duration_seconds": pcm_probe["duration_seconds"],
                      "aac_duration_seconds": m4a_duration, "timeline_seconds": DURATION,
                      "dialogue_correlation": receipt["source_dialogue_pcm_comparison"]}, indent=2))


if __name__ == "__main__":
    main()
