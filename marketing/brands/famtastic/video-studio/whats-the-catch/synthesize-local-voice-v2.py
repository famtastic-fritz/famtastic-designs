#!/usr/bin/env python3
"""Synthesize the owner script locally with a hash-pinned Kokoro CLI."""
from __future__ import annotations

import argparse
import hashlib
import json
import os
import re
import shutil
import subprocess
import sys
import tempfile
import time
import wave
from pathlib import Path

ROOT = Path(__file__).resolve().parents[5]
PROJECT = Path(__file__).resolve().parent
SOURCE = PROJECT / "user-script.txt"
NORMALIZATIONS = PROJECT / "voice-tts-normalizations.json"
EXPECTED_SOURCE_SHA = "12a242dae7818a836495ebc92bb3ff0274ccddfc230df531c9016a8261c0676f"
EXPECTED_MODEL_SHA = "7d5df8ecf7d4b1878015a32686053fd0eebe2bc377234608764cc0ef3636a6c5"
EXPECTED_VOICES_SHA = "bca610b8308e8d99f32e6fe4197e7ec01679264efed0cac9140fe9c29f1fbf7d"
VOICE = "am_michael"
SPEED = "0.78"
LANG = "en-us"
VERSION = "0.8.29"
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


def child_environment(python: Path | None = None) -> dict[str, str]:
    env = {**os.environ, **ENV_GUARDS}
    if python is not None:
        env["HYPERFRAMES_PYTHON"] = str(python)
    return env


def decode_cli_json(output: str) -> dict:
    """Extract one JSON object despite notices or pretty-printed JSON output."""
    decoder = json.JSONDecoder()
    for offset, character in enumerate(output):
        if character != "{":
            continue
        try:
            value, _ = decoder.raw_decode(output[offset:])
        except json.JSONDecodeError:
            continue
        if isinstance(value, dict):
            return value
    raise ValueError("HyperFrames TTS did not return a JSON object")


def new_output_directory(repo_root: Path, raw_path: str) -> Path:
    requested = Path(raw_path).expanduser()
    output = (repo_root / requested).resolve() if not requested.is_absolute() else requested.resolve()
    try:
        output.relative_to(repo_root.resolve())
    except ValueError as exc:
        raise SystemExit("output directory must remain inside this repository") from exc
    if output.exists():
        raise SystemExit(f"refusing to overwrite existing output directory: {output.relative_to(repo_root)}")
    return output


def run_fixture_smoke_tests() -> None:
    pretty = 'HyperFrames notice\n{\n  "ok": true,\n  "durationSeconds": 1.25,\n  "outputPath": "line.wav"\n}\nfinished\n'
    parsed = decode_cli_json(pretty)
    assert parsed == {"ok": True, "durationSeconds": 1.25, "outputPath": "line.wav"}
    with tempfile.TemporaryDirectory() as temporary:
        root = Path(temporary).resolve()
        occupied = root / "existing-run"
        occupied.mkdir()
        try:
            new_output_directory(root, str(occupied))
        except SystemExit as exc:
            assert "refusing to overwrite" in str(exc)
        else:
            raise AssertionError("existing run directory was not refused")
    print("synthesis fixture smoke tests passed: multiline JSON and existing-output refusal")


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--output-dir", help="new repository-local artifact directory; must not exist")
    parser.add_argument("--python", default=os.environ.get("HYPERFRAMES_PYTHON") or sys.executable,
                        help="Python runtime for the pinned local HyperFrames TTS CLI")
    parser.add_argument("--self-test", action="store_true", help="run bounded local fixture tests without models or subprocesses")
    args = parser.parse_args()
    if args.self_test:
        run_fixture_smoke_tests()
        return
    if not args.output_dir:
        parser.error("--output-dir is required")

    output = new_output_directory(ROOT, args.output_dir)
    source_lines = [line.strip() for line in SOURCE.read_text(encoding="utf-8").splitlines() if line.strip()]
    manifest = json.loads(NORMALIZATIONS.read_text(encoding="utf-8"))
    source_hash = digest(SOURCE)
    if source_hash != EXPECTED_SOURCE_SHA or manifest.get("source_script_sha256") != EXPECTED_SOURCE_SHA:
        raise SystemExit("owner script hash changed; inspect before synthesis")
    rows = manifest.get("lines", [])
    if len(source_lines) != 21 or len(rows) != len(source_lines):
        raise SystemExit("expected 21 nonempty source lines and matching normalizations")
    for index, (source_text, row) in enumerate(zip(source_lines, rows)):
        if row.get("index") != index or row.get("source_text") != source_text:
            raise SystemExit(f"normalization/source binding mismatch at line {index}")

    cache = Path.home() / ".cache/hyperframes/tts"
    model = cache / "models/kokoro-v1.0.onnx"
    voices = cache / "voices/voices-v1.0.bin"
    if not model.is_file() or digest(model) != EXPECTED_MODEL_SHA:
        raise SystemExit("pinned local Kokoro model is absent or mismatched; no download attempted")
    if not voices.is_file() or digest(voices) != EXPECTED_VOICES_SHA:
        raise SystemExit("pinned local voice data is absent or mismatched; no download attempted")

    cli = os.environ.get("HYPERFRAMES_CLI") or shutil.which("hyperframes")
    if not cli or not Path(cli).is_file():
        raise SystemExit("set HYPERFRAMES_CLI to an already-installed local HyperFrames 0.8.29 executable")
    python = Path(args.python).expanduser().resolve()
    if not python.is_file():
        raise SystemExit("the selected local Python runtime does not exist")
    version_process = subprocess.run([cli, "--version"], cwd=ROOT, env=child_environment(python),
                                     stdin=subprocess.DEVNULL, text=True, capture_output=True, check=False)
    version_out = version_process.stdout.strip()
    if version_process.returncode != 0 or not re.fullmatch(r"0\.8\.29", version_out):
        raise SystemExit(f"expected exactly HyperFrames {VERSION}; got {version_out!r}; no upgrade attempted")

    output.mkdir(parents=True)
    runtime = subprocess.run([str(python), "--version"], text=True, capture_output=True, check=True, env=child_environment(python))
    campaign = {
        "schema": "famtastic.no-catch.local-voice-request.v1",
        "id": output.name,
        "source_script": {"path": SOURCE.relative_to(ROOT).as_posix(), "sha256": source_hash},
        "normalizations": {"path": NORMALIZATIONS.relative_to(ROOT).as_posix(), "sha256": digest(NORMALIZATIONS)},
        "voice": {"provider": "local Kokoro-ONNX", "id": VOICE, "speed": float(SPEED), "language": LANG},
        "model": {"id": "hexgrad/Kokoro-82M v1.0 ONNX full precision", "sha256": digest(model), "license": "Apache-2.0",
                  "source": "https://github.com/thewh1teagle/kokoro-onnx/releases/tag/model-files-v1.0"},
        "voice_data": {"sha256": digest(voices), "source": "https://github.com/thewh1teagle/kokoro-onnx/releases/tag/model-files-v1.0"},
        "runtime": {"hyperframes_cli_version": VERSION, "hyperframes_cli": str(Path(cli).resolve()),
                    "python": runtime.stdout.strip() or runtime.stderr.strip(), "python_path": str(python)},
        "execution_constraints": ["local inference only", "no paid or remote TTS", "no cloned voice", "no prior movie audio", "no publishing"],
        "approval_scope": "local narration draft only",
    }
    sys.path.insert(0, str(ROOT / "marketing/engine/video_studio"))
    from fam_video.evidence import Ledger

    run_dir = output / "run"
    ledger = Ledger(run_dir, ROOT, campaign)
    ledger.add_artifact(SOURCE, "owner_supplied_script", rights="owner_supplied", retention="immutable_input")
    ledger.add_artifact(NORMALIZATIONS, "TTS_normalization_contract", rights="agent_authored_from_owner_script", retention="run_evidence")
    frozen_scripts = run_dir / "source-scripts"
    frozen_scripts.mkdir(parents=True, exist_ok=True)
    frozen_runner = frozen_scripts / Path(__file__).name
    frozen_runner.write_bytes(Path(__file__).read_bytes())
    ledger.add_artifact(frozen_runner, "local_voice_synthesis_runner", rights="agent_authored", retention="run_evidence")

    command_plan = []
    for row in rows:
        wav_path = output / f"line-{row['index']:02d}.wav"
        command_plan.append([str(Path(cli).resolve()), "tts", row["tts_text"], "--voice", VOICE, "--speed", SPEED,
                             "--lang", LANG, "--output", str(wav_path), "--json"])
    started = time.monotonic()
    generated = []
    with ledger.stage("voice-synthesis", "21_line_local_synthetic_narration",
                      provider="local-hyperframes-kokoro-tts",
                      model_id=f"hexgrad/Kokoro-82M v1.0 ONNX + {VOICE}",
                      model_status="model and voice hashes verified against local cache",
                      command=command_plan,
                      inputs={"source_script_sha256": source_hash, "normalizations_sha256": digest(NORMALIZATIONS),
                              "model_sha256": digest(model), "voice_data_sha256": digest(voices),
                              "hyperframes_cli_version": VERSION, "python": runtime.stdout.strip() or runtime.stderr.strip(),
                              "voice": VOICE, "speed": float(SPEED), "language": LANG, "line_count": 21, "local_only": True},
                      prompt={"source": SOURCE.relative_to(ROOT).as_posix(), "normalization_contract": NORMALIZATIONS.relative_to(ROOT).as_posix()} ) as stage:
        for row, argv in zip(rows, command_plan):
            index = row["index"]
            wav_path = output / f"line-{index:02d}.wav"
            call_start = time.monotonic()
            proc = subprocess.run(argv, cwd=ROOT, env=child_environment(python), stdin=subprocess.DEVNULL,
                                  text=True, capture_output=True, check=False, timeout=300)
            elapsed = time.monotonic() - call_start
            log_path = output / f"line-{index:02d}.log"
            log_path.write_text(proc.stdout + "\n--- STDERR ---\n" + proc.stderr, encoding="utf-8")
            if proc.returncode != 0 or not wav_path.is_file():
                raise RuntimeError(f"HyperFrames TTS failed on line {index} (exit {proc.returncode}); see the retained line log")
            try:
                cli_receipt = decode_cli_json(proc.stdout)
            except ValueError as exc:
                raise RuntimeError(f"HyperFrames TTS returned no parseable receipt on line {index}") from exc
            if cli_receipt.get("ok") is not True or Path(str(cli_receipt.get("outputPath", ""))).resolve() != wav_path.resolve():
                raise RuntimeError(f"HyperFrames TTS receipt did not bind to the requested output on line {index}")
            with wave.open(str(wav_path), "rb") as audio:
                media = {"sample_rate_hz": audio.getframerate(), "channels": audio.getnchannels(),
                         "sample_width_bytes": audio.getsampwidth(), "frames": audio.getnframes(),
                         "duration_seconds": audio.getnframes() / audio.getframerate()}
            generated.append({"index": index, "source_text": row["source_text"], "tts_text": row["tts_text"],
                              "command": argv, "wall_seconds": round(elapsed, 3),
                              "wav_path": wav_path.relative_to(ROOT).as_posix(), "wav_sha256": digest(wav_path),
                              "media": media, "log_path": log_path.relative_to(ROOT).as_posix(), "cli_receipt": cli_receipt})
        if digest(model) != EXPECTED_MODEL_SHA or digest(voices) != EXPECTED_VOICES_SHA:
            raise RuntimeError("local model or voice data changed during generation")
        receipt = {"schema": "famtastic.no-catch.local-voice-reproduction.v1", "provider": "local Kokoro-ONNX only",
                   "voice": VOICE, "speed": float(SPEED), "language": LANG, "hyperframes_cli_version": VERSION,
                   "source_script_sha256": source_hash, "normalizations_sha256": digest(NORMALIZATIONS),
                   "model_sha256": digest(model), "voice_data_sha256": digest(voices), "line_count": len(generated),
                   "elapsed_seconds": round(time.monotonic() - started, 3), "lines": generated, "provider_fee_usd": 0}
        receipt_path = output / "synthesis-receipts.json"
        receipt_path.write_text(json.dumps(receipt, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
        stage["execution"]["output"] = {"status": "passed", "line_count": len(generated),
                                          "elapsed_seconds": receipt["elapsed_seconds"],
                                          "speech_duration_seconds": sum(row["media"]["duration_seconds"] for row in generated),
                                          "receipt_path": receipt_path.relative_to(ROOT).as_posix(),
                                          "line_wav_hashes": [{"index": row["index"], "path": row["wav_path"], "sha256": row["wav_sha256"]} for row in generated]}
    ledger.add_artifact(receipt_path, "local_voice_synthesis_receipt", rights="local_run_receipt", retention="run_evidence")
    for row in generated:
        ledger.add_artifact(ROOT / row["wav_path"], "generated_line_wav", rights="local_synthetic_voice", retention="run_evidence")
        ledger.add_artifact(ROOT / row["log_path"], "native_cli_json_and_line_log", rights="local_run_receipt", retention="run_evidence")
    print(json.dumps({"output_dir": output.relative_to(ROOT).as_posix(), "ledger": (run_dir / "build-dna.json").relative_to(ROOT).as_posix(),
                      "line_count": len(generated), "elapsed_seconds": receipt["elapsed_seconds"],
                      "receipt": receipt_path.relative_to(ROOT).as_posix()}, indent=2))


if __name__ == "__main__":
    main()
