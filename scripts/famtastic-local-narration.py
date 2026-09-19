#!/usr/bin/env python3
"""Generate a new local Kokoro narration with frozen inputs and explicit brand IPA.

Run in the installed Kokoro Python environment. No network, download, cloned
voice, publication or email operations. Output must be a fresh repository path.
"""
from __future__ import annotations
import argparse, importlib.metadata, json, os, re, shutil, subprocess, sys, time, wave
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / "marketing/engine/video_studio"))
from fam_video.brand_voice import phonemes_for_line
from fam_video.evidence import Ledger, sha256

MODEL_SHA = "7d5df8ecf7d4b1878015a32686053fd0eebe2bc377234608764cc0ef3636a6c5"
VOICES_SHA = "bca610b8308e8d99f32e6fe4197e7ec01679264efed0cac9140fe9c29f1fbf7d"


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--script", type=Path, required=True)
    parser.add_argument("--output", type=Path, required=True)
    parser.add_argument("--speed", type=float, default=1.06)
    parser.add_argument("--gap", type=float, default=.18)
    parser.add_argument("--voice", default="am_michael")
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
    if sha256(model) != MODEL_SHA or sha256(voices) != VOICES_SHA:
        parser.error("Cached model or voices differ; no download attempted")
    lines = [s.strip() for s in source.read_text().splitlines() if s.strip()]
    if not lines or len(lines) > 100 or any(len(s) > 700 for s in lines):
        parser.error("Require 1–100 source lines, each at most 700 characters")
    output.mkdir(parents=True)
    frozen = output / "frozen"; frozen.mkdir()
    shutil.copyfile(source, frozen / "script.txt")
    shutil.copyfile(__file__, frozen / "famtastic-local-narration.py")
    shutil.copyfile(ROOT / "marketing/engine/video_studio/fam_video/brand_voice.py", frozen / "brand_voice.py")
    campaign = {"id": output.name, "schema": "famtastic.local-narration-request.v2",
        "script_sha256": sha256(frozen / "script.txt"), "voice": args.voice,
        "speed": args.speed, "gap_seconds": args.gap, "sentence_pause": .12, "clause_pause": .055,
        "brand_ipa": "fæmtˈæstɪk", "model_sha256": MODEL_SHA, "voices_sha256": VOICES_SHA,
        "python": sys.version, "packages": {n: importlib.metadata.version(n) for n in
          ("kokoro-onnx", "onnxruntime", "phonemizer", "soundfile", "numpy")},
        "constraints": ["local inference only", "no paid provider", "not an owner voice clone", "review draft"]}
    ledger = Ledger(output / "run", ROOT, campaign)
    for p in frozen.iterdir(): ledger.add_artifact(p, "frozen_narration_input", rights="owner_copy_or_authored_source")
    command = [str(Path(sys.executable)), *sys.argv]
    log = output / "ffmpeg.log"
    def run(argv):
        proc = subprocess.run(argv, text=True, capture_output=True, stdin=subprocess.DEVNULL, timeout=180)
        with log.open("a") as f: f.write(json.dumps(argv) + "\n" + proc.stdout + proc.stderr + "\n")
        if proc.returncode: raise RuntimeError(f"Command failed; see {log}")
        return proc
    with ledger.stage("local-tts", "phoneme_bound_local_speech", provider="local-kokoro-onnx",
          model_id="hexgrad/Kokoro-82M v1.0 ONNX / " + args.voice,
          model_status="hash verified cached model", command=command, inputs=campaign,
          prompt={"frozen_script": str((frozen / "script.txt").relative_to(ROOT))}) as stage:
        import soundfile as sf
        from kokoro_onnx import Kokoro
        engine = Kokoro(str(model), str(voices))
        rows = []; cues = []; frames = 0
        start = time.monotonic()
        with wave.open(str(output / "narration-before-loudnorm.wav"), "wb") as joined:
            joined.setnchannels(1); joined.setsampwidth(2); joined.setframerate(24000)
            for index, text in enumerate(lines):
                row = phonemes_for_line(engine.tokenizer, text)
                t = time.monotonic()
                samples, rate = engine.create(row["phonemes"], voice=args.voice, speed=args.speed,
                    lang="en-us", is_phonemes=True, trim=True, sentence_pause=.12, clause_pause=.055)
                if rate != 24000: raise RuntimeError("Unexpected model sample rate")
                wav = output / f"line-{index:02d}.wav"
                sf.write(str(wav), samples, rate, subtype="PCM_16")
                with wave.open(str(wav), "rb") as w:
                    count = w.getnframes(); pcm = w.readframes(count)
                begin = frames / rate; joined.writeframesraw(pcm); frames += count
                cues.append({"index": index, "text": text, "source_text": text,
                             "start": begin, "end": frames / rate})
                row.update(index=index, wav=wav.name, wav_sha256=sha256(wav),
                           seconds=count/rate, wall_seconds=time.monotonic()-t)
                rows.append(row)
                if index < len(lines)-1:
                    gap = round(args.gap * rate)
                    joined.writeframesraw(b"\0\0" * gap); frames += gap
                print(json.dumps({"line": index, "seconds": row["seconds"], "wall_seconds": row["wall_seconds"]}), flush=True)
        receipt = {"campaign": campaign, "lines": rows, "tts_wall_seconds": time.monotonic()-start,
                   "duration_seconds": frames/24000, "provider_fee_usd": 0}
        (output / "synthesis-receipts.json").write_text(json.dumps(receipt, indent=2, ensure_ascii=False)+"\n")
        (output / "cues.json").write_text(json.dumps(cues, indent=2, ensure_ascii=False)+"\n")
        stage["execution"]["output"] = {"lines":len(rows), "duration_seconds":frames/24000,
              "brand_occurrences":sum(r["brand_occurrences"] for r in rows), "status":"passed"}
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
    for p in sorted(output.iterdir()):
        if p.is_file(): ledger.add_artifact(p,"narration_evidence",rights="local_synthetic_voice_or_run_receipt")
    ledger.finalize("gated",qa={"technical_audio":"passed", "pronunciation":"explicit_IPA_fam_TAS_tik",
        "human_listening_review":"pending", "owner_voice_clone":False, "publishing":"not_performed"})
    print(json.dumps({"output":str(output),"duration_seconds":frames/24000,"build_dna":str(ledger.path)}))

if __name__ == "__main__": main()
