#!/usr/bin/env python3
"""Stage a deterministic local HyperFrames continuation project; do not render or publish."""
from __future__ import annotations

import argparse
import hashlib
import html
import json
import shutil
import subprocess
from pathlib import Path

HERE = Path(__file__).resolve().parent
REPO = HERE.parents[4]
SOURCE_SHA = "95ae3ca64a6eaee00c772ddd70063c210b55dff03632b9690ec645ffa2bae49f"
MATTE_SHA = "b7f487cc6cc2e88452aa2fb90f8dc5c980ff4d1bb238525875ed2e99fa09254f"
BRIDGE_WAV_SHA = "428fd8a053d68fd1ba033047dc8c067d369c2b35367a2c5e87ba694a07c9d51e"
BRIDGE_M4A_SHA = "83c306a674a85acff2351549fca9aec5369f162d4bea60183f56413c88e17dc8"
GSAP_SHA = "c174bfce53a729418d57a8ad8625e7247c793a22fef8e2851e3cfa3de9cd8280"
LOGO_SHA = "ebb0477344132d32e449ba19e2b622921585aa71af0decdbcf8abfbe033fa950"
FPS = 24
FRAME_COUNT = 854
DURATION = FRAME_COUNT / FPS


def sha(path: Path) -> str:
    with path.open("rb") as stream:
        return hashlib.file_digest(stream, "sha256").hexdigest()


def city_svg() -> str:
    parts = ['<svg viewBox="0 0 1080 1000" xmlns="http://www.w3.org/2000/svg">']
    for layer, step in enumerate((76, 107, 149)):
        for column, x in enumerate(range(-65, 1160, step)):
            width = step - 10
            height = 185 + ((column * 83 + layer * 71) % (335 + layer * 45))
            y = 930 - height + layer * 21
            fill = ("#19261f", "#132019", "#0b130e")[layer]
            parts.append(f'<path d="M{x} {y+16} Q{x+width/2} {y-1} {x+width} {y+16} V950 H{x}Z" fill="{fill}" stroke="#354335" stroke-width="2"/>')
            for row, wy in enumerate(range(y + 32, 950, 30)):
                for col, wx in enumerate(range(x + 11, x + width - 7, 17)):
                    if (row * 7 + col * 5 + column * 3 + layer) % 8 < 3:
                        opacity = (0.23, 0.35, 0.48)[(row + col + layer) % 3]
                        color = ("#c84d43", "#dfbb6a", "#5576cc")[(row + col + column) % 3]
                        parts.append(f'<rect x="{wx}" y="{wy}" width="5" height="9" fill="{color}" opacity="{opacity}"/>')
    parts.append("</svg>")
    return "".join(parts)


def creator_credit() -> str:
    module = REPO / "scripts/creator-credit.mjs"
    command = ["node", "--input-type=module", "-e",
               'import {pathToFileURL} from "node:url"; const m=await import(pathToFileURL(process.argv[1])); process.stdout.write(m.creatorCreditHtml({embedded:true}));',
               str(module)]
    return subprocess.check_output(command, text=True)


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--original", type=Path, required=True)
    parser.add_argument("--presenter", type=Path, required=True, help="Supplied local VP9-alpha matte; its audio is stripped during staging.")
    parser.add_argument("--bridge-narration", type=Path, required=True, help="Separate local Kokoro narration evidence input.")
    parser.add_argument("--voice-cues", type=Path, required=True)
    parser.add_argument("--voice-receipt", type=Path, required=True)
    parser.add_argument("--voice-levels", type=Path, required=True)
    parser.add_argument("--bridge-alignment", type=Path, required=True)
    parser.add_argument("--source-transcription", type=Path, required=True)
    parser.add_argument("--soundtrack", type=Path, required=True, help="The already assembled 48 kHz stereo AAC final audio master.")
    parser.add_argument("--audio-cues", type=Path, required=True)
    parser.add_argument("--audio-receipt", type=Path, required=True)
    parser.add_argument("--gsap", type=Path, required=True, help="Pinned local GSAP 3.14.2 file; no network fetch.")
    parser.add_argument("--output", type=Path, required=True)
    args = parser.parse_args()
    paths = (args.original, args.presenter, args.bridge_narration, args.voice_cues, args.voice_receipt,
             args.voice_levels, args.bridge_alignment, args.source_transcription, args.soundtrack,
             args.audio_cues, args.audio_receipt, args.gsap)
    for path in paths:
        if not path.is_file():
            parser.error(f"Missing explicit local input: {path}")
    if args.output.exists():
        parser.error("Use a fresh project directory; previous source projects remain immutable.")
    expected = ((args.original, SOURCE_SHA, "supplied original"), (args.presenter, MATTE_SHA, "supplied presenter matte"),
                (args.bridge_narration, BRIDGE_M4A_SHA, "local bridge narration"), (args.gsap, GSAP_SHA, "pinned GSAP"))
    for path, wanted, label in expected:
        if sha(path) != wanted:
            parser.error(f"{label} SHA-256 does not match the reviewed input.")
    logo = REPO / "frontend/public/brand/famtastic-designs-logo-v1.png"
    if sha(logo) != LOGO_SHA:
        parser.error("Canonical FAMtastic logo digest mismatch.")

    audio_receipt = json.loads(args.audio_receipt.read_text(encoding="utf-8"))
    audio_cues = json.loads(args.audio_cues.read_text(encoding="utf-8"))
    if audio_receipt.get("status") != "passed" or audio_cues.get("schema") != "famtastic.walking-continuation-captions.v1":
        parser.error("Audio assembly or caption receipt is not in the expected passed schema.")
    if abs(float(audio_receipt["timeline"]["duration_seconds"]) - DURATION) > 1e-8:
        parser.error("Audio duration does not match this frame-aligned composition.")
    if sha(args.soundtrack) != audio_receipt["assembly"]["aac_master"]["sha256"]:
        parser.error("Soundtrack does not match the retained audio assembly receipt.")
    if sha(args.voice_cues) != audio_receipt["bridge_voice"]["voice_cues_sha256"]:
        parser.error("Voice cues do not match the audio assembly input receipt.")
    if sha(args.voice_receipt) != audio_receipt["bridge_voice"]["synthesis_receipt_sha256"]:
        parser.error("Synthesis receipt does not match the audio assembly input receipt.")
    if sha(args.voice_levels) != audio_receipt["bridge_voice"]["level_receipt_sha256"]:
        parser.error("Voice loudness data does not match the audio assembly input receipt.")
    if sha(args.bridge_alignment) != audio_receipt["bridge_voice"]["dtw_transcript_sha256"]:
        parser.error("Bridge alignment does not match the audio assembly input receipt.")

    args.output.mkdir(parents=True)
    assets = args.output / "assets"
    evidence_dir = args.output / "evidence"
    assets.mkdir()
    evidence_dir.mkdir()
    static_assets = {
        "gsap.min.js": args.gsap,
        "famtastic-designs-logo-v1.png": logo,
        "metropolis-bold.woff2": REPO / "frontend/public/showcase/booked-and-branded-pilot/assets/fonts/metropolis-bold.woff2",
        "metropolis-regular.woff2": REPO / "frontend/public/showcase/booked-and-branded-pilot/assets/fonts/metropolis-regular.woff2",
        "kaushan-script.woff2": REPO / "frontend/public/brand/fonts/kaushan-script-latin-v19.woff2",
        "original-source.mp4": args.original,
        "bridge-narration.m4a": args.bridge_narration,
        "soundtrack.m4a": args.soundtrack,
    }
    provenance = {
        "schema": "famtastic.walking-continuation-assets.v1",
        "source_movie": {"name": args.original.name, "sha256": SOURCE_SHA, "dimensions": "480x854", "fps": 24,
                         "duration_seconds": 30.041667},
        "performer": {"name": args.presenter.name, "source_sha256": MATTE_SHA,
                      "staged_path": "assets/presenter.webm", "native_dimensions": "480x854",
                      "rights_and_limit": "Supplied performance segmented locally with Vision; no generated actor, restored/occluded detail, or sustained walk claim."},
        "soundtrack": {"path": "assets/soundtrack.m4a", "sha256": sha(args.soundtrack),
                       "assembly_receipt": "evidence/audio-assembly-receipt.json",
                       "method": "One local AAC mix of exact source-dialogue excerpts and separate local narration, with a short silent end hold."},
        "voiceover": {"path": "assets/bridge-narration.m4a", "sha256": BRIDGE_M4A_SHA,
                      "voice": audio_receipt["bridge_voice"].get("voice"),
                      "model_sha256": audio_receipt["bridge_voice"].get("model_sha256"),
                      "voiceover_only": True, "not_owner_voice_clone": True},
        "gsap": {"version": "3.14.2", "sha256": GSAP_SHA, "staged_path": "assets/gsap.min.js"},
        "canonical_logo": {"path": "assets/famtastic-designs-logo-v1.png", "sha256": LOGO_SHA},
        "inputs": [],
        "provider_fee_usd": 0,
        "network_fetches": 0,
        "published": False,
    }
    for name, source in static_assets.items():
        shutil.copyfile(source, assets / name)
        provenance["inputs"].append({"path": f"assets/{name}", "sha256": sha(assets / name), "source_name": source.name})
    matte_output = assets / "presenter.webm"
    strip_command = ["ffmpeg", "-hide_banner", "-loglevel", "error", "-nostdin", "-y", "-i", str(args.presenter),
                     "-map", "0:v:0", "-c:v", "copy", "-an", str(matte_output)]
    strip_result = subprocess.run(strip_command, capture_output=True, text=True, timeout=120)
    (args.output / "presenter-audio-strip.log").write_text(strip_result.stdout + strip_result.stderr, encoding="utf-8")
    if strip_result.returncode:
        raise SystemExit("Could not remove every audio stream from the transparent source matte.")
    matte_probe = json.loads(subprocess.check_output(["ffprobe", "-v", "error", "-show_streams", "-of", "json", str(matte_output)], text=True))
    matte_streams = matte_probe.get("streams", [])
    matte_tags = {key.casefold(): str(value) for key, value in matte_streams[0].get("tags", {}).items()} if len(matte_streams) == 1 else {}
    matte_is_alpha_vp9 = (len(matte_streams) == 1
                          and matte_streams[0].get("codec_type") == "video"
                          and matte_streams[0].get("codec_name") == "vp9"
                          and ("yuva" in matte_streams[0].get("pix_fmt", "") or matte_tags.get("alpha_mode") == "1"))
    if not matte_is_alpha_vp9:
        raise SystemExit("Staged performer must remain one audio-free VP9 alpha video stream (verified by pixel format or alpha_mode tag).")
    provenance["inputs"].append({"path": "assets/presenter.webm", "sha256": sha(matte_output),
                                "source_name": args.presenter.name, "source_sha256": sha(args.presenter),
                                "derivation": "Video stream copied, all audio streams removed.",
                                "stream_probe": {"codec": matte_streams[0].get("codec_name"),
                                                 "pixel_format": matte_streams[0].get("pix_fmt"),
                                                 "alpha_mode": matte_tags.get("alpha_mode"),
                                                 "width": matte_streams[0].get("width"),
                                                 "height": matte_streams[0].get("height")}})

    evidence_inputs = {
        "audio-assembly-receipt.json": args.audio_receipt,
        "audio-cues.json": args.audio_cues,
        "voice-cues.json": args.voice_cues,
        "voice-synthesis-receipt.json": args.voice_receipt,
        "voice-levels.json": args.voice_levels,
        "bridge-narration-dtw.json": args.bridge_alignment,
        "source-transcription.json": args.source_transcription,
    }
    for name, source in evidence_inputs.items():
        shutil.copyfile(source, evidence_dir / name)
        provenance["inputs"].append({"path": f"evidence/{name}", "sha256": sha(evidence_dir / name), "source_name": source.name})
    provenance["original_source_sha256"] = sha(args.original)
    (args.output / "asset-provenance.json").write_text(json.dumps(provenance, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

    cue_markup = []
    for index, cue in enumerate(audio_cues.get("cues", [])):
        cue_markup.append(
            f'<div id="caption-{index:02d}" class="caption caption--{html.escape(cue["kind"])}" '
            f'data-cue-start="{float(cue["start"]):.6f}" data-cue-end="{float(cue["end"]):.6f}">'
            f'<span>{html.escape(cue["text"])}</span></div>')
    template = (HERE / "index.template.html").read_text(encoding="utf-8")
    rendered_html = (template.replace("{{CITY}}", city_svg())
                     .replace("{{CAPTIONS}}", "\n".join(cue_markup))
                     .replace("{{CREDIT}}", creator_credit())
                     .replace("{{MOTION}}", (HERE / "motion.js").read_text(encoding="utf-8"))
                     .replace("{{DURATION}}", f"{DURATION:.12f}"))
    (args.output / "index.html").write_text(rendered_html, encoding="utf-8")
    shutil.copyfile(HERE / "style.css", args.output / "style.css")
    motion_check = {
        "version": 1,
        "duration": DURATION,
        "assertions": [
            {"kind": "appearsBy", "selector": "#title-opening", "bySec": 0.9},
            {"kind": "appearsBy", "selector": "#website-panel", "bySec": 12.8},
            {"kind": "appearsBy", "selector": "#plan-panel", "bySec": 22.2},
            {"kind": "before", "a": "#title-opening", "b": "#plan-panel"},
            {"kind": "before", "a": "#plan-panel", "b": "#title-grow"},
            *[{"kind": "staysInFrame", "selector": f"#caption-{index:02d}"} for index in range(len(audio_cues.get("cues", [])))],
            {"kind": "keepsMoving", "withinSelector": "#city", "maxStaticSec": 2},
        ],
    }
    (args.output / "index.motion.json").write_text(json.dumps(motion_check, indent=2) + "\n", encoding="utf-8")
    (args.output / "hyperframes.json").write_text(json.dumps({"media": {"autoProxy": False}}, indent=2) + "\n", encoding="utf-8")
    files = sorted(path.relative_to(args.output).as_posix() for path in args.output.rglob("*")
                   if path.is_file() and path.name != "project.json" and path.name != "presenter-audio-strip.log")
    manifest = {
        "schema": "famtastic.local-hyperframes-project.v1",
        "id": "famtastic-walking-continuation-v1",
        "title": "Your vision, your business — supplied-performance continuation",
        "entrypoint": "index.html", "width": 1080, "height": 1920, "fps": FPS,
        "duration_seconds": DURATION, "files": files,
        "audio_master": {"path": "assets/soundtrack.m4a", "mode": "copy", "start_seconds": 0},
        "brand_logo": {"path": "assets/famtastic-designs-logo-v1.png", "sha256": LOGO_SHA},
        "creator_credit_marker": 'data-famtastic-creator-credit="v1"',
    }
    project_path = args.output / "project.json"
    project_path.write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(json.dumps({"status": "staged", "project": str(args.output.resolve()),
                      "duration_seconds": DURATION, "frame_count": FRAME_COUNT,
                      "file_count": len(files), "project_sha256": sha(project_path),
                      "rendered": False, "published": False}, indent=2))


if __name__ == "__main__":
    main()
