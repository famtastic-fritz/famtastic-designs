#!/usr/bin/env python3
"""Generate speech with a local Voicebox server and save a wav."""
import json, sys, time, urllib.request

BASE = "http://127.0.0.1:17493"

def post(path, payload):
    req = urllib.request.Request(BASE + path, data=json.dumps(payload).encode(),
                                 headers={"Content-Type": "application/json"})
    with urllib.request.urlopen(req, timeout=600) as r:
        return json.load(r)

def get(path):
    with urllib.request.urlopen(BASE + path, timeout=120) as r:
        return json.load(r)

def speak(profile_id, text, out_path, engine="kokoro"):
    gen = post("/generate", {"profile_id": profile_id, "text": text,
                             "engine": engine, "language": "en"})
    gid = gen["id"]
    for _ in range(360):
        h = get(f"/history/{gid}")
        st = h.get("status")
        if st == "completed":
            with urllib.request.urlopen(f"{BASE}/history/{gid}/export-audio", timeout=300) as r:
                data = r.read()
            open(out_path, "wb").write(data)
            return {"id": gid, "duration": h.get("duration"), "bytes": len(data), "path": out_path}
        if st in ("failed", "error"):
            raise SystemExit(f"generation failed: {h.get('error')}")
        time.sleep(4)
    raise SystemExit("timed out")

if __name__ == "__main__":
    pid, out = sys.argv[1], sys.argv[2]
    text = sys.stdin.read().strip()
    print(json.dumps(speak(pid, text, out), indent=2))
