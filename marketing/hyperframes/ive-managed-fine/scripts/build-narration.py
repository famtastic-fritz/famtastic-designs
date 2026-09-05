#!/usr/bin/env python3
"""Generate one narration bed per film with local Voicebox, and emit the timing
table the compositions are authored against.

Why per-beat blocks: a single long read gives no control over where a picture
cut lands. Generating one block per beat, measuring each, and assembling with
explicit silence means every beat boundary sits in a real silence and no cut
falls mid-sentence. The measured table is written to narration/timing.json and
is the source of truth for data-start / data-duration in each film.

Local, free, no API key, no metered credits. Requires the Voicebox server:

  ~/Development/voicebox/tauri/src-tauri/binaries/voicebox-server-aarch64-apple-darwin --port 17493
"""
from __future__ import annotations

import json
import pathlib
import subprocess
import sys
import time
import urllib.request

BASE = "http://127.0.0.1:17493"
HERE = pathlib.Path(__file__).resolve().parent
PROJ = HERE.parent
OUT = PROJ / "narration"
SPEC = json.loads((HERE / "narration.json").read_text())


def api_get(path):
    with urllib.request.urlopen(BASE + path, timeout=120) as r:
        return json.load(r)


def api_post(path, payload):
    req = urllib.request.Request(
        BASE + path,
        data=json.dumps(payload).encode(),
        headers={"Content-Type": "application/json"},
    )
    with urllib.request.urlopen(req, timeout=600) as r:
        return json.load(r)


def profile_id() -> str:
    for p in api_get("/profiles"):
        if p["name"] == "FAMtastic Narrator":
            return p["id"]
    raise SystemExit(
        "profile 'FAMtastic Narrator' not found — create it per "
        ".agents/skills/famtastic-voice/SKILL.md"
    )


def speak(pid: str, text: str, out_path: pathlib.Path) -> float:
    gen = api_post("/generate", {"profile_id": pid, "text": text,
                                 "engine": "kokoro", "language": "en"})
    gid = gen["id"]
    for _ in range(300):
        h = api_get(f"/history/{gid}")
        if h.get("status") == "completed":
            with urllib.request.urlopen(
                f"{BASE}/history/{gid}/export-audio", timeout=300
            ) as r:
                out_path.write_bytes(r.read())
            return duration(out_path)
        if h.get("status") in ("failed", "error"):
            raise SystemExit(f"generation failed: {h.get('error')}")
        time.sleep(3)
    raise SystemExit("timed out waiting for generation")


def duration(p: pathlib.Path) -> float:
    out = subprocess.run(
        ["ffprobe", "-v", "error", "-show_entries", "format=duration",
         "-of", "csv=p=0", str(p)],
        capture_output=True, text=True, check=True,
    )
    return round(float(out.stdout.strip()), 3)


def main() -> None:
    pid = profile_id()
    OUT.mkdir(parents=True, exist_ok=True)
    lead = SPEC["lead_seconds"]
    gap = SPEC["gap_seconds"]
    tail = SPEC["tail_seconds"]
    table = {"generated_at": time.strftime("%Y-%m-%dT%H:%M:%S%z"),
             "engine": SPEC["engine"], "cost_usd": 0,
             "lead_seconds": lead, "gap_seconds": gap, "tail_seconds": tail,
             "films": {}}

    for film, blocks in SPEC["films"].items():
        d = OUT / film
        d.mkdir(exist_ok=True)
        durs, parts = [], []
        for i, text in enumerate(blocks, start=1):
            wav = d / f"beat-{i}.wav"
            if wav.exists():
                dur = duration(wav)
            else:
                dur = speak(pid, text, wav)
            durs.append(dur)
            parts.append(wav)
            print(f"  {film} beat-{i}: {dur:.3f}s  {text[:52]}…")

        # Assemble: lead silence + b1 + gap + b2 + gap + b3 + tail silence.
        concat = ["ffmpeg", "-y", "-v", "error"]
        for p in parts:
            concat += ["-i", str(p)]
        f = (
            f"anullsrc=r=24000:cl=mono,atrim=0:{lead}[lead];"
            f"anullsrc=r=24000:cl=mono,atrim=0:{gap}[g1];"
            f"anullsrc=r=24000:cl=mono,atrim=0:{gap}[g2];"
            f"anullsrc=r=24000:cl=mono,atrim=0:{tail}[tail];"
            "[0:a]aresample=24000[a0];[1:a]aresample=24000[a1];[2:a]aresample=24000[a2];"
            "[lead][a0][g1][a1][g2][a2][tail]concat=n=7:v=0:a=1[out]"
        )
        bed = OUT / f"{film}.wav"
        concat += ["-filter_complex", f, "-map", "[out]", str(bed)]
        subprocess.run(concat, check=True)

        # Beat windows, in film time. A beat starts one gap-half before its own
        # audio and ends one gap-half after, so every cut lands inside silence.
        half = gap / 2
        starts, t = [], lead
        for dur in durs:
            starts.append(round(t, 3))
            t += dur + gap
        total = round(lead + sum(durs) + 2 * gap + tail, 3)
        windows = []
        for i, (s, dur) in enumerate(zip(starts, durs)):
            begin = 0.0 if i == 0 else round(s - half, 3)
            end = total if i == len(durs) - 1 else round(s + dur + half, 3)
            windows.append({"beat": i + 1, "audio_start": s,
                            "audio_duration": dur,
                            "clip_start": begin,
                            "clip_duration": round(end - begin, 3)})
        table["films"][film] = {
            "bed": f"narration/{film}.wav",
            "bed_duration": duration(bed),
            "film_duration": total,
            "beats": windows,
            "text": blocks,
        }
        print(f"{film}: bed {duration(bed):.3f}s, film {total:.3f}s")

    (OUT / "timing.json").write_text(json.dumps(table, indent=2) + "\n")
    print(f"\nwrote {OUT / 'timing.json'}")


if __name__ == "__main__":
    sys.exit(main())
