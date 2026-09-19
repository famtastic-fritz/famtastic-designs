#!/usr/bin/env python3
"""Generate a new local Kokoro narration with frozen inputs and explicit brand IPA.

Run in the installed Kokoro Python environment. No network, download, cloned
voice, publication or email operations. Output must be a fresh repository path.
"""
from __future__ import annotations
import argparse, hashlib, importlib.metadata, json, os, re, subprocess, sys, time, wave
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / "marketing/engine/video_studio"))
from fam_video.brand_voice import phonemes_for_line
from fam_video.evidence import Ledger, sha256
from fam_video.narration_performance import join_pcm16, resolve_performance

MODEL_SHA = "7d5df8ecf7d4b1878015a32686053fd0eebe2bc377234608764cc0ef3636a6c5"
VOICES_SHA = "bca610b8308e8d99f32e6fe4197e7ec01679264efed0cac9140fe9c29f1fbf7d"


def _sha256_bytes(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def _read_bound_inputs(source_path: Path, performance_path: Path | None, *, speed: float, gap: float) -> dict:
    """Read the exact bytes that will be frozen, parsed, and used for speech."""
    source_bytes = source_path.read_bytes()
    lines = [line.strip() for line in source_bytes.decode("utf-8").splitlines() if line.strip()]
    if not lines or len(lines) > 100 or any(len(line) > 700 for line in lines):
        raise ValueError("Require 1–100 source lines, each at most 700 characters")
    performance_bytes = performance_path.read_bytes() if performance_path else None
    plan = json.loads(performance_bytes) if performance_bytes is not None else None
    source_sha256 = _sha256_bytes(source_bytes)
    resolved = resolve_performance(lines, source_sha256, plan, speed=speed, gap=gap)
    resolved_bytes = (json.dumps(resolved, indent=2, ensure_ascii=False) + "\n").encode("utf-8")
    return {"source_bytes": source_bytes, "source_sha256": source_sha256, "lines": lines,
            "performance_bytes": performance_bytes,
            "performance_source_sha256": _sha256_bytes(performance_bytes) if performance_bytes is not None else None,
            "resolved": resolved, "resolved_bytes": resolved_bytes,
            "resolved_sha256": _sha256_bytes(resolved_bytes)}


def _write_frozen_bytes(path: Path, data: bytes) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("xb") as handle:
        handle.write(data)
        handle.flush()
        os.fsync(handle.fileno())
    if path.read_bytes() != data:
        raise RuntimeError(f"Frozen input changed while writing: {path.name}")


def _line_cue(index: int, text: str, start_frame: int, end_frame: int,
              pause_frames: int, sample_rate: int) -> dict:
    return {"index": index, "text": text, "source_text": text,
            "start": start_frame / sample_rate, "end": end_frame / sample_rate,
            "pause_after_frames": pause_frames,
            "pause_after_seconds": pause_frames / sample_rate}


def _output_files(output: Path, ledger) -> list[Path]:
    ledger_path = Path(ledger.path).resolve()
    lock_path = getattr(ledger, "_lock_path", None)
    lock_path = Path(lock_path).resolve() if lock_path else None
    return [path for path in sorted(output.rglob("*"))
            if path.is_file() and not path.is_symlink()
            and path.resolve() != ledger_path and path.resolve() != lock_path]


def _write_failure_receipt(output: Path, ledger, failure: BaseException, registration_errors=()) -> Path:
    receipt_path = output / "failure-receipt.json"
    active_ledger = Path(ledger.path).resolve()
    active_lock = Path(ledger._lock_path).resolve()
    retained_files = []
    for path in sorted(output.rglob("*")):
        if not path.is_file() or path.is_symlink() or path == receipt_path or path.resolve() in {active_ledger, active_lock}:
            continue
        try:
            relative = path.relative_to(ROOT).as_posix()
        except ValueError:
            continue
        retained_files.append({"path": relative, "sha256": sha256(path), "size_bytes": path.stat().st_size})
    data = {"schema": "famtastic.local-narration-failure.v1", "status": "failed",
            "created_at": datetime.now(timezone.utc).isoformat(timespec="milliseconds").replace("+00:00", "Z"),
            "error": {"type": type(failure).__name__, "message": str(failure)[:1500]},
            "retained_files": retained_files,
            "artifact_registration_errors": [str(item)[:500] for item in registration_errors]}
    with receipt_path.open("x", encoding="utf-8") as handle:
        json.dump(data, handle, indent=2, sort_keys=True, ensure_ascii=False)
        handle.write("\n")
        handle.flush()
        os.fsync(handle.fileno())
    return receipt_path


def _record_run_completion(output: Path, ledger, failure: BaseException | None = None) -> list[str]:
    """Hash-register every retained file and finalize, preserving a prior error."""
    registration_errors = []
    if failure is not None:
        try:
            _write_failure_receipt(output, ledger, failure)
        except Exception as exc:
            registration_errors.append(f"failure receipt: {type(exc).__name__}: {exc}")

    def register_files():
        for path in _output_files(output, ledger):
            try:
                relative = path.relative_to(ROOT).as_posix()
                existing = next((item for item in ledger.data.get("artifacts", [])
                                 if item.get("path") == relative), None)
                if existing:
                    if existing.get("sha256") != sha256(path):
                        raise ValueError("previously registered bytes changed")
                    continue
                ledger.add_artifact(path, "narration_evidence", rights="local synthetic voice or retained run evidence")
            except Exception as exc:
                registration_errors.append(f"{path.name}: {type(exc).__name__}: {exc}")

    register_files()
    if registration_errors and failure is None:
        synthetic_failure = RuntimeError("Narration evidence registration failed")
        try:
            _write_failure_receipt(output, ledger, synthetic_failure, registration_errors)
        except Exception as exc:
            registration_errors.append(f"failure receipt: {type(exc).__name__}: {exc}")
        register_files()
    status = "failed" if failure is not None or registration_errors else "gated"
    qa = {"technical_audio": "failed" if status == "failed" else "passed",
          "human_listening_review": "pending", "publishing": "not_performed"}
    try:
        ledger.finalize(status, qa=qa)
    except BaseException as exc:
        if failure is None:
            raise
        registration_errors.append(f"ledger finalization: {type(exc).__name__}: {exc}")
    if registration_errors and failure is None:
        raise RuntimeError("Narration run retained, but some evidence could not be registered: "
                           + "; ".join(registration_errors))
    return registration_errors


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--script", type=Path, required=True)
    parser.add_argument("--output", type=Path, required=True)
    parser.add_argument("--speed", type=float, default=1.06)
    parser.add_argument("--gap", type=float, default=.18)
    parser.add_argument("--voice", default="am_michael")
    parser.add_argument("--performance", type=Path, help="Source-bound JSON phrase speeds and pauses")
    args = parser.parse_args()
    source = args.script.resolve(); output = args.output.resolve()
    if not output.is_relative_to(ROOT) or output.exists():
        parser.error("Output must be a NEW path inside this repository")
    if not .8 <= args.speed <= 1.3 or not 0 <= args.gap <= .6:
        parser.error("Use speed 0.8–1.3 and gap 0–0.6 seconds")
    if importlib.metadata.version("kokoro-onnx") != "0.6.1":
        parser.error("Pinned installed kokoro-onnx 0.6.1 is required")
    cache = Path.home() / ".cache/hyperframes/tts"
    model, voices = cache / "models/kokoro-v1.0.onnx", cache / "voices/voices-v1.0.bin"
    model_hash, voices_hash = sha256(model), sha256(voices)
    if model_hash != MODEL_SHA or voices_hash != VOICES_SHA:
        parser.error("Cached model or voices differ; no download attempted")
    try:
        bound = _read_bound_inputs(source, args.performance, speed=args.speed, gap=args.gap)
    except ValueError as exc:
        parser.error(str(exc))
    lines = bound["lines"]
    performance = bound["resolved"]
    runner_path = Path(__file__).resolve()
    brand_voice_path = ROOT / "marketing/engine/video_studio/fam_video/brand_voice.py"
    performance_module_path = ROOT / "marketing/engine/video_studio/fam_video/narration_performance.py"
    frozen_code = {"famtastic-local-narration.py": runner_path.read_bytes(),
                   "brand_voice.py": brand_voice_path.read_bytes(),
                   "narration_performance.py": performance_module_path.read_bytes()}
    frozen_code_hashes = {name: _sha256_bytes(data) for name, data in frozen_code.items()}
    frozen = output / "frozen"
    input_manifest = {
        "schema": "famtastic.local-narration-inputs.v1",
        "script": {"source_path": str(source), "sha256": bound["source_sha256"]},
        "performance_plan": ({"source_path": str(args.performance.resolve()),
                              "sha256": bound["performance_source_sha256"]}
                             if args.performance else None),
        "resolved_performance_sha256": bound["resolved_sha256"],
        "runtime_code": {name: {"source_path": str(path), "sha256": frozen_code_hashes[name]}
                         for name, path in (("famtastic-local-narration.py", runner_path),
                                            ("brand_voice.py", brand_voice_path),
                                            ("narration_performance.py", performance_module_path))},
        "model_assets": {"kokoro_onnx": {"path": str(model), "sha256": model_hash},
                         "voice_bank": {"path": str(voices), "sha256": voices_hash}},
    }
    input_manifest_bytes = (json.dumps(input_manifest, indent=2, ensure_ascii=False) + "\n").encode("utf-8")
    campaign = {"id": output.name, "schema": "famtastic.local-narration-request.v2",
        "script_sha256": bound["source_sha256"], "voice": args.voice,
        "speed": args.speed, "gap_seconds": args.gap, "sentence_pause": .12, "clause_pause": .055,
        "brand_ipa": "fæmtˈæstɪk", "model_sha256": model_hash, "voices_sha256": voices_hash,
        "model_assets": input_manifest["model_assets"],
        "runtime_code_sha256": frozen_code_hashes,
        "performance_plan_sha256": bound["performance_source_sha256"],
        "performance_sha256": bound["resolved_sha256"],
        "input_manifest_sha256": _sha256_bytes(input_manifest_bytes),
        "pause_semantics": "explicit inserted silence plus any silence inside generated phrases",
        "python": sys.version, "packages": {n: importlib.metadata.version(n) for n in
          ("kokoro-onnx", "onnxruntime", "phonemizer", "soundfile", "numpy")},
        "constraints": ["local inference only", "no paid provider", "not an owner voice clone", "review draft"]}
    ledger = Ledger(output / "run", ROOT, campaign)
    command = [str(Path(sys.executable)), *sys.argv]
    log = output / "ffmpeg.log"
    def run(argv):
        proc = subprocess.run(argv, text=True, capture_output=True, stdin=subprocess.DEVNULL, timeout=180)
        with log.open("a") as f: f.write(json.dumps(argv) + "\n" + proc.stdout + proc.stderr + "\n")
        if proc.returncode: raise RuntimeError(f"Command failed; see {log}")
        return proc
    failure = None
    try:
        frozen.mkdir()
        _write_frozen_bytes(frozen / "script.txt", bound["source_bytes"])
        if bound["performance_bytes"] is not None:
            _write_frozen_bytes(frozen / "performance.json", bound["performance_bytes"])
        _write_frozen_bytes(frozen / "resolved-performance.json", bound["resolved_bytes"])
        _write_frozen_bytes(frozen / "input-manifest.json", input_manifest_bytes)
        for name, data in frozen_code.items():
            _write_frozen_bytes(frozen / name, data)
        frozen_hashes = {path.name: sha256(path) for path in frozen.iterdir() if path.is_file()}
        expected_frozen_hashes = {"script.txt": bound["source_sha256"],
                                  "resolved-performance.json": bound["resolved_sha256"],
                                  "input-manifest.json": _sha256_bytes(input_manifest_bytes),
                                  **frozen_code_hashes}
        if bound["performance_bytes"] is not None:
            expected_frozen_hashes["performance.json"] = bound["performance_source_sha256"]
        if frozen_hashes != expected_frozen_hashes:
            raise RuntimeError("Frozen narration inputs differ from the exact bytes parsed before synthesis")
        for path in sorted(frozen.iterdir()):
            if path.is_file():
                ledger.add_artifact(path, "frozen_narration_input", rights="owner-authored source, runtime or performance evidence")

        with ledger.stage("local-tts", "phoneme_bound_local_speech", provider="local-kokoro-onnx",
          model_id="hexgrad/Kokoro-82M v1.0 ONNX / " + args.voice,
          model_status="hash verified cached model", command=command, inputs=campaign,
          prompt={"frozen_script": str((frozen / "script.txt").relative_to(ROOT))}) as stage:
            import soundfile as sf
            from kokoro_onnx import Kokoro
            engine = Kokoro(str(model), str(voices))
            rows = []; cues = []; phrase_cues = []; frames = 0
            start = time.monotonic()
            with wave.open(str(output / "narration-before-loudnorm.wav"), "wb") as joined:
                joined.setnchannels(1); joined.setsampwidth(2); joined.setframerate(24000)
                for index, line in enumerate(performance):
                    text = line["source_text"]
                    t = time.monotonic()
                    chunks = []; phrase_rows = []
                    for pindex, part in enumerate(line["phrases"]):
                        phonetic = phonemes_for_line(engine.tokenizer, part["text"])
                        samples, rate = engine.create(phonetic["phonemes"], voice=args.voice, speed=part["speed"],
                            lang="en-us", is_phonemes=True, trim=True, sentence_pause=.12, clause_pause=.055)
                        if rate != 24000: raise RuntimeError("Unexpected model sample rate")
                        part_wav = output / f"line-{index:02d}-phrase-{pindex:02d}.wav"
                        sf.write(str(part_wav), samples, rate, subtype="PCM_16")
                        with wave.open(str(part_wav), "rb") as w: pcm = w.readframes(w.getnframes())
                        chunks.append((pcm, part["pause_after_seconds"]))
                        phrase_rows.append({**phonetic, **part, "wav":part_wav.name, "wav_sha256":sha256(part_wav)})
                    pcm, bounds = join_pcm16(chunks)
                    wav = output / f"line-{index:02d}.wav"
                    with wave.open(str(wav), "wb") as w:
                        w.setnchannels(1); w.setsampwidth(2); w.setframerate(rate); w.writeframes(pcm)
                    count = len(pcm)//2
                    begin_frame = frames
                    joined.writeframesraw(pcm); frames += count
                    pause_frames = round(line["pause_after_seconds"] * rate) if index < len(lines)-1 else 0
                    # These fields describe inserted line silence for new review consumers.
                    # The separate walking-continuation campaign keeps its own fixed 0.18s guard.
                    cues.append(_line_cue(index, text, begin_frame, frames, pause_frames, rate))
                    for pindex, (part, bound) in enumerate(zip(phrase_rows, bounds)):
                        part["timing"] = bound
                        phrase_cues.append({"index":len(phrase_cues), "line_index":index, "phrase_index":pindex,
                            "text":part["source_text"], "source_text":part["source_text"],
                            "start":begin_frame/rate+bound["start"], "end":begin_frame/rate+bound["end"]})
                    row = {"index":index, "source_text":text, "wav":wav.name, "wav_sha256":sha256(wav),
                        "seconds":count/rate, "wall_seconds":time.monotonic()-t, "phrases":phrase_rows,
                        "brand_occurrences":sum(p["brand_occurrences"] for p in phrase_rows),
                        "pause_after_seconds":line["pause_after_seconds"]}
                    rows.append(row)
                    if pause_frames:
                        joined.writeframesraw(b"\0\0" * pause_frames); frames += pause_frames
                    print(json.dumps({"line": index, "seconds": row["seconds"], "wall_seconds": row["wall_seconds"]}), flush=True)
            receipt = {"campaign": campaign, "lines": rows, "tts_wall_seconds": time.monotonic()-start,
                       "duration_seconds": frames/24000, "provider_fee_usd": 0}
            (output / "synthesis-receipts.json").write_text(json.dumps(receipt, indent=2, ensure_ascii=False)+"\n")
            (output / "cues.json").write_text(json.dumps(cues, indent=2, ensure_ascii=False)+"\n")
            (output / "phrase-cues.json").write_text(json.dumps(phrase_cues, indent=2, ensure_ascii=False)+"\n")
            stage["execution"]["output"] = {"lines":len(rows), "duration_seconds":frames/24000,
                  "brand_occurrences":sum(r["brand_occurrences"] for r in rows), "status":"passed"}

            if sha256(model) != model_hash or sha256(voices) != voices_hash:
                raise RuntimeError("Cached model or voice-bank bytes changed during local synthesis")
            if any(sha256(frozen / name) != digest for name, digest in frozen_hashes.items()):
                raise RuntimeError("Frozen narration evidence changed during synthesis")

        with ledger.stage("loudness-and-aac", "two_pass_normalize_encode", provider="local-ffmpeg",
                      inputs={"target_lufs":-16,"target_true_peak_dbtp":-2}) as stage:
            pre = output / "narration-before-loudnorm.wav"
            probe = run(["ffmpeg","-hide_banner","-nostats","-i",str(pre),"-af",
                "loudnorm=I=-16:TP=-2:LRA=7:print_format=json","-f","null","-"])
            m = json.loads(re.findall(r'\{\s*"input_i".*?\}', probe.stderr,re.S)[-1])
            filt = ("loudnorm=I=-16:TP=-2:LRA=7:linear=false:print_format=json:"
                f"measured_I={m['input_i']}:measured_TP={m['input_tp']}:measured_LRA={m['input_lra']}:"
                f"measured_thresh={m['input_thresh']}:offset={m['target_offset']}")
            run(["ffmpeg","-hide_banner","-nostats","-n","-i",str(pre),"-af",filt,
                 "-ar","24000","-ac","1","-c:a","pcm_s16le",str(output/"narration.wav")])
            run(["ffmpeg","-hide_banner","-nostats","-n","-i",str(output/"narration.wav"),
                 "-c:a","aac","-b:a","128k","-movflags","+faststart",str(output/"narration.m4a")])
            meter = run(["ffmpeg","-hide_banner","-nostats","-i",str(output/"narration.m4a"),
                         "-af","ebur128=framelog=quiet:peak=true","-f","null","-"])
            lufs = float(re.findall(r"Integrated loudness:\s+I:\s+(-?[0-9.]+) LUFS",meter.stderr,re.S)[-1])
            peak = float(re.findall(r"True peak:.*?Peak:\s+(-?[0-9.]+) dBFS",meter.stderr,re.S)[-1])
            if not -17.5 <= lufs <= -14.5 or peak > -1: raise RuntimeError("Loudness QA failed")
            metrics = {"integrated_loudness_lufs":lufs,"true_peak_dbtp":peak,
                       "m4a_sha256":sha256(output/"narration.m4a"),"status":"passed"}
            (output/"loudness.json").write_text(json.dumps(metrics,indent=2)+"\n")
            stage["execution"]["output"] = metrics
    except BaseException as exc:
        failure = exc
        raise
    finally:
        try:
            registration_errors = _record_run_completion(output, ledger, failure)
            if registration_errors and failure is not None:
                print(json.dumps({"evidence_registration_errors": registration_errors}), file=sys.stderr)
        except BaseException as completion_error:
            if failure is None:
                raise
            print(f"Failed to finalize failure evidence without replacing {type(failure).__name__}: "
                  f"{type(completion_error).__name__}: {completion_error}", file=sys.stderr)
    print(json.dumps({"output":str(output),"duration_seconds":frames/24000,"build_dna":str(ledger.path)}))

if __name__ == "__main__": main()
