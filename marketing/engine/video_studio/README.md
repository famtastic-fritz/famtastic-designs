# FAMtastic Local Video Studio

A working local production CLI: authored campaign → designed HyperFrames composition → MP4 + technical checks + canonical Build DNA. Python uses the standard library. HyperFrames is the primary compositor. MoneyPrinterTurbo is an explicit draft worker; ComfyUI is an optional shot supplier.

**Start here:** [setup and proof handoff](../../../docs/marketing/local-video-studio/CLI-HANDOFF.md). [Why this architecture](../../../docs/marketing/local-video-studio/DECISIONS.md). [Generation setup](../../../docs/marketing/local-video-studio/LOCAL-GENERATION.md).

## Workstation continuation

The imported implementation and its Mac proof are recorded in
[WORKSTATION-PROOF-2026-09-19.md](../../../docs/marketing/local-video-studio/WORKSTATION-PROOF-2026-09-19.md).
The existing HyperFrames 0.8.29 installation renders all four formats on the M5 Mac.
Use `--hyperframes` with the executable recorded there; the wrapper never silently
installs it. Semantic quality aliases follow the selected CLI's own help:
`looks` maps to `standard` and `delivery` to `high` on older releases; newer names
remain unchanged when advertised. The optional pinned dependency is preserved.

The existing MoneyPrinterTurbo 1.3.4 installation also passed a native local
draft through its older single-task CLI, using its prepared Python 3.11.15
environment. See the [native proof and limitations](../../../docs/marketing/local-video-studio/WORKSTATION-MPT-2026-09-19.md).
This installed version uses a nominal 480-pixel minimum on both axes, with a
10-pixel tolerance; the proof uses explicit 854×480 copies and preserves originals.
It does not reproduce designed layouts or captions, and does not support 4:5.

## Quick start from repository root

Requires Python 3.11+, Node 22+, FFmpeg/FFprobe, and an installed HyperFrames executable.

```bash
python3 scripts/famtastic-video.py doctor
python3 scripts/famtastic-video.py validate marketing/brands/famtastic/video-studio/demo.json
python3 scripts/famtastic-video.py render marketing/brands/famtastic/video-studio/demo.json
```

The wrapper finds the pinned repo-local HyperFrames installation first, then PATH. Pass `--hyperframes /absolute/path/to/hyperframes` to select an existing installation explicitly. There is no implicit `npx` install, cloud fallback or publishing operation. HTML compilation invokes the injected canonical creator-credit adapter and preserves the exact owner logo.

If HyperFrames is absent, install the exact toolchain deliberately:

```bash
ONNXRUNTIME_NODE_INSTALL_CUDA=skip npm --prefix marketing/engine/video_studio ci
HYPERFRAMES_NO_TELEMETRY=1 HYPERFRAMES_NO_UPDATE_CHECK=1 HYPERFRAMES_NO_AUTO_INSTALL=1 marketing/engine/video_studio/node_modules/.bin/hyperframes browser ensure
```

The CUDA opt-out is supported by the transitive ONNX installer and avoids an unnecessary NVIDIA download for composition. Browser installation requires internet once. After dependencies, browser, fonts and assets are available, local composition needs no provider credits. Installation is not bundled with rendering.

## Commands

| Command | Result |
| --- | --- |
| `doctor` | Actual host/tool inventory, separate from repo's documented workstation profile |
| `validate CAMPAIGN` | Checks timing, formats, local assets, declared rights and media duration |
| `plan CAMPAIGN --output NEW_DIR` | Frozen storyboard and generation prompt pack |
| `render CAMPAIGN` | One MP4, contact sheet, verification and Build DNA |
| `batch CAMPAIGN` | Four separately composed formats: 9:16, 4:5, 1:1, 16:9 |
| `voice SCRIPT.txt --output NEW.wav` | Already installed macOS system voice; recording yourself is preferred |
| `demo-audio --output NEW.wav --duration 12` | Original synthetic pulse for pipeline testing, not narration |
| `generate --workflow API.json --bindings B.json --values V.json --output artifacts/video-studio/NEW` | Validated local ComfyUI job; exact API export required |
| `resume artifacts/video-studio/NEW/comfy-receipt.json` | Polls original submitted job; no automatic resubmission |
| `mpt-draft CAMPAIGN --mpt-root PATH --mpt-python PATH` | Existing MoneyPrinterTurbo, supplied media/script/audio only |
| `verify VIDEO.mp4` | Metadata checks; no implied creative approval |
| `compare REFERENCE.mp4 CANDIDATE.mp4 --output NEW_DIR` | Paired contact sheets, measured metadata and manual scoring form |

`render`/`batch` accept `--quality draft|looks|delivery`, `--format`, `--scale 0.3–1`, `--timeout`, and `--no-cache`. A changed input, source asset, tool probe, code, brand or quality creates a different cache key. A cache hit requires the MP4 and retained evidence hashes to match.

Outputs live under ignored `artifacts/video-studio/`. Never commit personal references, generated videos, weights, runtime environments or credentials. Review, canonical campaign enrollment, Drupal projection and public publication are separate existing processes.

## Campaign contract

The examples are in `marketing/brands/famtastic/video-studio/`. Every scene has `id`, `duration` (whole-frame alignment), `layout`, and `headline`. Optional `body`, `eyebrow`, `background` and `accent` customize the composition. Layouts are `signal`, `split`, `monument`, `resolve`.

```json
{
  "schema": "video-studio.campaign.v1",
  "id": "example-campaign",
  "title": "Example campaign",
  "mode": "editorial",
  "format": "9:16",
  "fps": 30,
  "audio": "narration.wav",
  "scenes": [{
    "id": "opening",
    "duration": 4,
    "layout": "split",
    "headline": "One clear next step.",
    "media": "approved-shot.mp4",
    "media_kind": "video",
    "rights": "owned",
    "trim_start": 0,
    "focal_point": "50% 40%"
  }],
  "captions": [{"start": 0.2, "end": 3.6, "text": "One clear next step."}]
}
```

Asset paths are relative to the campaign JSON; absolute local paths also work. Remote assets are rejected. Declare source rights as `owned`, `licensed`, `generated` or `public_domain`; this declaration is not a legal clearance certificate. Brand paths are relative to the repository root. Every asset gets copied into the composition.

Modes are explicit: `motion_graphics` is designed type and objects; `editorial` uses supplied footage or stills; `ai_footage` uses supplied generated clips. Both media modes require media for every scene. A prompt alone never becomes footage. The thirty-second Unreasonable Reality example is labeled a **storyboard**, not a generated cinematic commercial.

Imported video is muted by design; campaign audio supplies the finished soundtrack. If a source contains audio and there is no campaign soundtrack, validation requires `source_audio: "discard"` on that scene. This makes audio loss intentional. Extract/edit a source track and supply it explicitly when recreating a movie. Source clips must cover `trim_start + duration`; the system never silently freezes or loops short footage.

Optional `captions` are ordered `{start,end,text}` cues; timings are seconds, nonoverlapping, inside the campaign. Transcription is separate. Review all captions against the recording. Default demos omit voice; `demo-audio` is a technical pulse bed only.

## Extension boundaries

Provider-neutral runtime lives here. Brand-specific rules/assets/examples live in `marketing/brands/famtastic/video-studio`; repository entrypoint is `scripts/famtastic-video.py`. No Drupal or customer data is imported. The creator-credit module is injected through brand configuration. Evidence uses the existing `famtastic.build-dna.v1` contract rather than a new customer/campaign authority.

The compositor exports HTML + assets that HyperFrames can edit. Adobe/Premiere may finish a hero manually. Existing Remotion projects remain useful; this system does not migrate them. A new layout should carry a distinct argument and composition, not just another palette.

## Validation

```bash
PYTHONPATH=marketing/engine/video_studio python3 -m unittest discover -s marketing/engine/video_studio/tests -v
node website-delivery-swarm/scripts/validate-build-dna.mjs artifacts/video-studio/RUN/build-dna.json .
node scripts/sync-brand-assets.cjs --check
node --test scripts/test-creator-credit.mjs
```

See the handoff receipt for what was actually rendered here and what requires proof on the owner's workstation. Passing a mocked provider test is not native model execution.
