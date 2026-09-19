#!/usr/bin/env python3
"""Assemble frozen line WAVs, bind source cues, normalize loudness, and encode AAC."""
from __future__ import annotations

import argparse
import hashlib
import json
import os
import re
import subprocess
import sys
import tempfile
import wave
from pathlib import Path

ROOT = Path(__file__).resolve().parents[5]
PROJECT = Path(__file__).resolve().parent
SOURCE = PROJECT / "user-script.txt"
NORMALIZATIONS = PROJECT / "voice-tts-normalizations.json"
GAPS = [0.35, 0.55, 0.30, 0.35, 0.60, 0.42, 0.35, 0.35, 0.45, 0.35, 0.30,
        0.45, 0.35, 0.30, 0.35, 0.55, 0.40, 0.50, 0.35, 0.55, 0.0]
FFMPEG = "/opt/homebrew/bin/ffmpeg"
FFPROBE = "/opt/homebrew/bin/ffprobe"
ENV_GUARDS = {
    "HYPERFRAMES_NO_TELEMETRY": "1",
    "HYPERFRAMES_NO_UPDATE_CHECK": "1",
    "HYPERFRAMES_NO_AUTO_INSTALL": "1",
    "DO_NOT_TRACK": "1",
}


def digest(path: Path) -> str:
    h = hashlib.sha256()
    with Path(path).open("rb") as handle:
        for block in iter(lambda: handle.read(1024 * 1024), b""):
            h.update(block)
    return h.hexdigest()


def child_environment() -> dict[str, str]:
    return {**os.environ, **ENV_GUARDS}


def ensure_assembly_outputs_absent(folder: Path) -> None:
    outputs = ["assembly-commands.log", "narration-before-loudnorm.wav", "narration.wav", "narration.m4a",
               "cues.json", "assembly-receipt.json", "ffprobe-m4a.json", "loudness-final.json"]
    existing = [name for name in outputs if (folder / name).exists()]
    if existing:
        raise SystemExit("refusing to overwrite existing assembly outputs: " + ", ".join(existing))


def run(argv: list[str], log_path: Path) -> tuple[str, str]:
    proc = subprocess.run(argv, cwd=ROOT, env=child_environment(), stdin=subprocess.DEVNULL,
                          text=True, capture_output=True, check=False, timeout=300)
    with log_path.open("a", encoding="utf-8") as log:
        log.write("$ " + json.dumps(argv, ensure_ascii=False) + "\n" + proc.stdout + "\n" + proc.stderr + "\n")
    if proc.returncode:
        raise RuntimeError(f"local media command failed with exit {proc.returncode}; see {log_path.relative_to(ROOT)}")
    return proc.stdout, proc.stderr


def run_fixture_smoke_tests() -> None:
    with tempfile.TemporaryDirectory() as temporary:
        folder = Path(temporary)
        log = folder / "assembly-commands.log"
        sentinel = b"prior receipt must remain intact"
        log.write_bytes(sentinel)
        try:
            ensure_assembly_outputs_absent(folder)
        except SystemExit as exc:
            assert "assembly-commands.log" in str(exc)
        else:
            raise AssertionError("existing assembly log was not refused")
        assert log.read_bytes() == sentinel, "repeat refusal modified the retained log"
    print("assembly fixture smoke test passed: repeat-output refusal preserves existing log")


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--audio-dir", help="directory created by synthesize-local-voice-v2.py")
    parser.add_argument("--self-test", action="store_true", help="run a bounded output-refusal fixture without media tools")
    args = parser.parse_args()
    if args.self_test:
        run_fixture_smoke_tests()
        return
    if not args.audio_dir:
        parser.error("--audio-dir is required")
    folder = (ROOT / args.audio_dir).resolve() if not Path(args.audio_dir).is_absolute() else Path(args.audio_dir).resolve()
    try:
        folder.relative_to(ROOT)
    except ValueError as exc:
        raise SystemExit("audio directory must remain inside this repository") from exc
    # Refuse before creating/truncating any file, including the command log.
    ensure_assembly_outputs_absent(folder)
    receipt_path = folder / "synthesis-receipts.json"
    receipt = json.loads(receipt_path.read_text(encoding="utf-8"))
    lines = [line.strip() for line in SOURCE.read_text(encoding="utf-8").splitlines() if line.strip()]
    normalization = json.loads(NORMALIZATIONS.read_text(encoding="utf-8"))
    if digest(SOURCE) != receipt.get("source_script_sha256") or receipt.get("normalizations_sha256") != digest(NORMALIZATIONS):
        raise SystemExit("script/normalization hashes do not match synthesis receipt")
    if len(lines) != 21 or receipt.get("line_count") != 21 or len(receipt.get("lines", [])) != 21:
        raise SystemExit("expected all 21 source lines")
    for index, (source_text, normalized, line_receipt) in enumerate(zip(lines, normalization["lines"], receipt["lines"])):
        if normalized.get("index") != index or normalized.get("source_text") != source_text:
            raise SystemExit(f"normalization/source binding failed at line {index}")
        if line_receipt.get("index") != index or line_receipt.get("source_text") != source_text:
            raise SystemExit(f"synthesis receipt/source binding failed at line {index}")

    run_dir = folder / "run"
    campaign_path = run_dir / "campaign.snapshot.json"
    if not campaign_path.is_file():
        raise SystemExit("canonical synthesis ledger snapshot is missing; run synthesis first")
    campaign = json.loads(campaign_path.read_text(encoding="utf-8"))
    if campaign.get("schema") != "famtastic.no-catch.local-voice-request.v1":
        raise SystemExit("campaign snapshot is not the expected frozen local voice request")
    if campaign.get("source_script", {}).get("sha256") != digest(SOURCE) or campaign.get("normalizations", {}).get("sha256") != digest(NORMALIZATIONS):
        raise SystemExit("campaign snapshot no longer matches the source or normalization contract")
    if campaign.get("runtime", {}).get("hyperframes_cli_version") != "0.8.29":
        raise SystemExit("campaign does not freeze HyperFrames CLI 0.8.29")
    sys.path.insert(0, str(ROOT / "marketing/engine/video_studio"))
    from fam_video.evidence import Ledger
    ledger = Ledger(run_dir, ROOT, campaign)
    frozen_scripts = run_dir / "source-scripts"
    frozen_scripts.mkdir(parents=True, exist_ok=True)
    frozen_runner = frozen_scripts / Path(__file__).name
    frozen_runner.write_bytes(Path(__file__).read_bytes())
    ledger.add_artifact(frozen_runner, "local_voice_assembly_runner", rights="agent_authored", retention="run_evidence")

    log_path = folder / "assembly-commands.log"
    log_path.write_text("", encoding="utf-8")
    pre = folder / "narration-before-loudnorm.wav"
    final = folder / "narration.wav"
    m4a = folder / "narration.m4a"
    cues_path = folder / "cues.json"
    probe_path = folder / "ffprobe-m4a.json"
    meter_path = folder / "loudness-final.json"
    assembly_receipt_path = folder / "assembly-receipt.json"
    cues = []
    line_inputs = []
    total_frames = 0
    speech_frames = 0
    gap_frames = 0
    command_plan = []
    first_command = [sys.executable, str(Path(__file__).resolve()), "--audio-dir", folder.relative_to(ROOT).as_posix()]
    with ledger.stage("audio-assembly-normalization", "bind_source_cues_normalize_and_encode_local_audio",
                      provider="local-ffmpeg", model_status="deterministic signal processing",
                      command=[first_command],
                      inputs={"source_script_sha256": digest(SOURCE),
                              "normalizations_sha256": digest(NORMALIZATIONS),
                              "synthesis_receipt_sha256": digest(receipt_path),
                              "line_inputs": [{"index": row["index"], "path": row["wav_path"], "sha256": row["wav_sha256"]} for row in receipt["lines"]],
                              "loudness_targets": {"integrated_loudness_lufs": -16.0, "true_peak_dbtp": -2.0, "lra_lu": 7.0}},
                      prompt={"policy": "Keep each caption source_text verbatim; apply only the frozen TTS normalizations."}) as stage:
        log_path.write_text("", encoding="utf-8")
        with wave.open(str(pre), "wb") as output:
            output.setnchannels(1)
            output.setsampwidth(2)
            output.setframerate(24000)
            for index, source_text in enumerate(lines):
                row = receipt["lines"][index]
                wav_path = folder / f"line-{index:02d}.wav"
                if (ROOT / row["wav_path"]).resolve() != wav_path.resolve() or digest(wav_path) != row["wav_sha256"]:
                    raise RuntimeError(f"line {index} output path/hash does not match the synthesis receipt")
                with wave.open(str(wav_path), "rb") as audio:
                    params = (audio.getnchannels(), audio.getsampwidth(), audio.getframerate())
                    if params != (1, 2, 24000):
                        raise RuntimeError(f"line {index} must be 24 kHz mono PCM S16, got {params}")
                    frames = audio.getnframes()
                    pcm = audio.readframes(frames)
                start = total_frames / 24000
                output.writeframesraw(pcm)
                total_frames += frames
                speech_frames += frames
                end = total_frames / 24000
                cues.append({"index": index, "text": source_text, "source_text": source_text,
                             "start": round(start, 6), "end": round(end, 6)})
                line_inputs.append({"index": index, "wav_path": wav_path.relative_to(ROOT).as_posix(),
                                    "sha256": digest(wav_path), "duration_seconds": frames / 24000})
                if index < len(lines) - 1:
                    frames_of_gap = round(GAPS[index] * 24000)
                    output.writeframesraw(b"\0\0" * frames_of_gap)
                    total_frames += frames_of_gap
                    gap_frames += frames_of_gap
        cues_path.write_text(json.dumps(cues, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

        targets = {"I": -16.0, "TP": -2.0, "LRA": 7.0}
        analysis = [FFMPEG, "-hide_banner", "-nostats", "-i", str(pre), "-af",
                    f"loudnorm=I={targets['I']}:TP={targets['TP']}:LRA={targets['LRA']}:print_format=json", "-f", "null", "-"]
        _, analysis_stderr = run(analysis, log_path)
        matches = re.findall(r'\{\s*"input_i".*?\}', analysis_stderr, re.S)
        if not matches:
            raise RuntimeError("could not parse FFmpeg loudness first-pass measurements")
        measured = json.loads(matches[-1])
        loudnorm_filter = (
            f"loudnorm=I={targets['I']}:TP={targets['TP']}:LRA={targets['LRA']}:"
            f"measured_I={measured['input_i']}:measured_TP={measured['input_tp']}:"
            f"measured_LRA={measured['input_lra']}:measured_thresh={measured['input_thresh']}:"
            f"offset={measured['target_offset']}:linear=false:print_format=json"
        )
        normalize = [FFMPEG, "-hide_banner", "-nostats", "-n", "-i", str(pre), "-af", loudnorm_filter,
                     "-ar", "24000", "-ac", "1", "-c:a", "pcm_s16le", str(final)]
        _, normalize_stderr = run(normalize, log_path)
        matches = re.findall(r'\{\s*"input_i".*?\}', normalize_stderr, re.S)
        normalized_measurements = json.loads(matches[-1]) if matches else {}
        encode = [FFMPEG, "-hide_banner", "-nostats", "-n", "-i", str(final), "-vn", "-c:a", "aac",
                  "-b:a", "128k", "-ar", "24000", "-ac", "1", "-movflags", "+faststart", str(m4a)]
        run(encode, log_path)
        probe_command = [FFPROBE, "-v", "error", "-show_streams", "-show_format", "-of", "json", str(m4a)]
        probe_stdout, _ = run(probe_command, log_path)
        probe = json.loads(probe_stdout)
        probe_path.write_text(json.dumps(probe, indent=2) + "\n", encoding="utf-8")
        meter_command = [FFMPEG, "-hide_banner", "-nostats", "-i", str(m4a), "-af",
                         "ebur128=framelog=quiet:peak=true", "-f", "null", "-"]
        _, meter_stderr = run(meter_command, log_path)
        integrated = re.findall(r"Integrated loudness:\s+I:\s+(-?[0-9.]+) LUFS", meter_stderr, re.S)
        peaks = re.findall(r"True peak:.*?Peak:\s+(-?[0-9.]+) dBFS", meter_stderr, re.S)
        lra = re.findall(r"LRA:\s+(-?[0-9.]+) LU", meter_stderr)
        meter = {"target": targets, "first_pass": measured, "normalized_wav": normalized_measurements,
                 "final_m4a": {"integrated_loudness_lufs": float(integrated[-1]) if integrated else None,
                               "true_peak_dbtp": float(peaks[-1]) if peaks else None,
                               "lra_lu": float(lra[-1]) if lra else None},
                 "filter": loudnorm_filter}
        meter_path.write_text(json.dumps(meter, indent=2) + "\n", encoding="utf-8")
        assembly_receipt = {
            "schema": "famtastic.no-catch.local-voice-assembly.v1",
            "source_script_sha256": digest(SOURCE), "normalizations_sha256": digest(NORMALIZATIONS),
            "synthesis_receipt_sha256": digest(receipt_path), "line_count": len(lines),
            "speech_duration_seconds": speech_frames / 24000, "gap_duration_seconds": gap_frames / 24000,
            "duration_seconds": total_frames / 24000, "sample_rate_hz": 24000, "channels": 1,
            "cues_path": cues_path.relative_to(ROOT).as_posix(), "cues_sha256": digest(cues_path),
            "line_inputs": line_inputs, "pre_normalized_sha256": digest(pre),
            "normalized_wav_sha256": digest(final), "m4a_sha256": digest(m4a),
            "m4a_duration_seconds": float(probe["format"]["duration"]),
            "loudness": meter, "ffprobe": probe, "provider_fee_usd": 0, "final_hold_seconds": 0,
            "commands": {"analysis": analysis, "normalize": normalize, "aac_encode": encode,
                          "probe": probe_command, "final_loudness": meter_command},
        }
        assembly_receipt_path.write_text(json.dumps(assembly_receipt, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
        stage["execution"]["command"] = [first_command, analysis, normalize, encode, probe_command, meter_command]
        stage["execution"]["output"] = {"status": "passed", "line_count": len(lines),
                                         "duration_seconds": assembly_receipt["duration_seconds"],
                                         "m4a_sha256": assembly_receipt["m4a_sha256"],
                                         "normalized_wav_sha256": assembly_receipt["normalized_wav_sha256"],
                                         "cues_sha256": assembly_receipt["cues_sha256"],
                                         "final_m4a_lufs": meter["final_m4a"]["integrated_loudness_lufs"],
                                         "final_m4a_true_peak_dbtp": meter["final_m4a"]["true_peak_dbtp"]}

    for path, role in [(Path(__file__), "local_voice_assembly_runner"), (pre, "pre_normalized_narration_pcm"),
                       (final, "loudness_normalized_narration_pcm"), (m4a, "aac_narration_master"),
                       (cues_path, "source_bound_timing_cues"), (log_path, "ffmpeg_command_and_logs"),
                       (probe_path, "m4a_ffprobe_receipt"), (meter_path, "final_loudness_measurements"),
                       (assembly_receipt_path, "audio_assembly_receipt")]:
        ledger.add_artifact(path, role, rights="local_generated_or_verified_audio", retention="run_evidence")
    ledger.finalize("gated", qa={"source_script_integrity": "passed", "voice_synthesis": "passed",
                                  "source_bound_cues": "passed", "loudness_and_AAC": "passed",
                                  "subjective_human_audio_review": "pending", "visual_and_owner_review": "pending",
                                  "publishing": "not_performed"})
    print(json.dumps({"audio_dir": folder.relative_to(ROOT).as_posix(),
                      "build_dna": (run_dir / "build-dna.json").relative_to(ROOT).as_posix(),
                      "duration_seconds": assembly_receipt["duration_seconds"],
                      "speech_duration_seconds": assembly_receipt["speech_duration_seconds"],
                      "gap_duration_seconds": assembly_receipt["gap_duration_seconds"],
                      "m4a_sha256": assembly_receipt["m4a_sha256"],
                      "loudness": meter["final_m4a"]}, indent=2))


if __name__ == "__main__":
    main()
