---
name: famtastic-voice
description: Generate voice-over locally with Voicebox and mux it onto a rendered film. Use whenever a video needs narration, a script needs an audio read, or a render would otherwise ship silent. Local, free, no API key, no metered credits.
---

# FAMtastic Voice

Local text-to-speech for campaign narration. No API key, no per-use cost, Apple
Silicon GPU. It exists so narration stops being a metered resource.

## Why this exists

On 2026-09-05, three of the seven HyperFrames films shipped **silent** — one of
them documented in its own composition as *"silent by construction, not by
oversight: the repo holds no approved narration and no licensed music, and this
task authorised no provider spend."* HeyGen credits were the only voice in the
stack, and they are finite.

Voicebox removes that constraint. All eight renders now carry audio.

## When to use it

- A HyperFrames or Remotion film needs narration
- A script needs to be heard before it is approved
- A presenter take is too expensive for a beat that only needs a voice

**Do not** use it to impersonate a real person, and do not present a synthetic
read as a recording of the owner.

## Start the server

**It must be running.** It is headless — the Tauri GUI does not build on this
machine (`actool` needs full Xcode; only Command Line Tools are installed) and
is not needed.

```bash
~/Development/voicebox/tauri/src-tauri/binaries/voicebox-server-aarch64-apple-darwin --port 17493
```

First start takes about 60-70 seconds. Confirm before doing anything else:

```bash
curl -s http://127.0.0.1:17493/health
```

Healthy looks like `{"status":"healthy","gpu_available":true,"gpu_type":"MPS (Apple Silicon)","backend_type":"mlx"}`.
Stop it with `pkill -f voicebox-server-aarch64` when the run is done.

## One-time setup (already done, documented so it can be rebuilt)

```bash
# Download an engine. kokoro is the lightest and needs no voice cloning.
curl -s -X POST http://127.0.0.1:17493/models/download \
  -H 'Content-Type: application/json' -d '{"model_name":"kokoro"}'

# Create a profile bound to a preset voice.
curl -s -X POST http://127.0.0.1:17493/profiles -H 'Content-Type: application/json' -d '{
  "name":"FAMtastic Narrator","language":"en","voice_type":"preset",
  "preset_engine":"kokoro","preset_voice_id":"af_heart","default_engine":"kokoro"}'
```

The house profile is **FAMtastic Narrator**, kokoro / `af_heart`. List voices
with `GET /profiles/presets/kokoro`; list profiles with `GET /profiles`.

## Generate narration

```bash
echo "Your business should not live on borrowed land." \
  | python3 scripts/voice/voicebox-tts.py <profile_id> out.wav
```

Returns the generation id, duration and byte count. Roughly **150 words ≈ 45
seconds**; measure rather than assume, then trim the script to the picture.

## Mux it onto the film

```bash
./scripts/voice/narrate-film.sh film.mp4 out.wav 1.4 film-narrated.mp4
```

The third argument is a **lead-in in seconds** — narration should not start on
frame one. Values used on the shipped films: 1.4s (44s film), 0.7s (15s short),
0.4s (28s film, where the voice nearly fills the picture).

It prints the audio codec and `volumedetect` levels. Shipped films measure about
**mean −21 dB, peak −1.2 dB**, close to the HeyGen presenter's −19.6 dB.

## API gotchas — do not rediscover these

- **`GET /generate/{id}/status` is Server-Sent Events, not JSON.** Parsing it
  with `json.load` fails on the `data: ` prefix. Poll **`GET /history/{id}`**
  instead, which returns plain JSON.
- Audio comes from **`GET /history/{id}/export-audio`**, not from `audio_path`
  in the generate response — that field is empty while status is `generating`.
- `POST /generate` requires **both** `profile_id` and `text`. A profile must
  exist first; there is no default voice.
- Generation is asynchronous. The POST returns immediately with
  `status: "generating"`.

## Build gotchas, if it ever needs rebuilding

Hit in this order, all real:

1. `build-server.sh` calls `python`; this machine has only `python3`.
2. Homebrew Python refuses global pip installs under PEP 668. Use a venv —
   **never `--break-system-packages`**, which can break the Homebrew install.
3. **The hard one:** the `kokoro` dependency requires Python `>=3.10,<3.13` and
   the system runs 3.14.7. Not shimmable. Fix:
   `uv venv --python 3.12 backend/venv` — the path the build script expects.
4. The Tauri GUI fails at `actool failed to compile icon`. That is full Xcode,
   not Command Line Tools. **Skip it** — the sidecars are the whole agent-facing
   surface.

Rust 1.98.1 is installed at `~/.cargo/bin` as a build prerequisite.

## Writing the script

Narration must **match the film's own on-screen copy**. Read the composition
HTML and write to it; do not introduce a claim the picture does not make.

Verified product facts — `backend/config/famtastic-products.json`:

- **`FAM-FOOT-199`** ($199, "55 cents a day") = ONE focused landing-page website
  + ONE year of managed hosting + first-year domain registration, or connecting
  a domain the customer already owns. **That is the entire bundle.**
- Business email is **`FAM-BUSINESS-EMAIL`, a separate $99 product**.
- Maintenance is **`FAM-MAINTENANCE`, an upsell** ($49.99/mo).
- Local SEO setup is a separate $299 one-time product.

No invented statistics. Never promise a search ranking. Never name or attack a
competitor — educate. Spell numbers out for the reader: "one hundred and
ninety-nine dollars" reads correctly where "$199" may not.

## Verify before shipping

`ffprobe` for the audio stream, `volumedetect` for levels, and **listen** — or
at minimum confirm the duration relationship makes sense. A muxed file with a
silent track passes every structural check.

`apad` plus `-shortest` is deliberate: without the pad, a voice-over shorter
than the film truncates the picture to the length of the audio.
