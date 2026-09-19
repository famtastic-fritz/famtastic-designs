# Local Video Studio workstation proof — 2026-09-19

Status: workstation composition, native MoneyPrinterTurbo draft and cache proof passed. This is a review-branch handoff; no production release, publishing or owner creative acceptance.

**Continuation:** the owner subsequently supplied the 30-second FAMtastic movie
and authorized merging after proof. This document preserves the earlier import
and four-format measurements. See
[RECREATION-PROOF-2026-09-19.md](RECREATION-PROOF-2026-09-19.md) for the authored
remake, exact soundtrack preservation, current tests and source integration.

## Import and isolation

- Package: `~/Downloads/FAMtastic-Local-Video-Studio.zip`; SHA-256 `760ec2e8520abe1d8ef1dc459738f26acdf3ed44c3fd85bc68b6f43d5d52263e`.
- All 55 manifest entries matched; all 46 source files matched the exact imported Git commit.
- Imported implementation: `f24eec09d3a08a667a80463670f188f49f641f5d`.
- Required base and `origin/main` at import: `076261ebb8ede3d60a7eb54ada44aa449794fafa`. No incoming divergence needed reconciliation.
- Isolated worktree: `/Users/famtastic-fritz/Documents/ChatGPT/famtastic-video-proof`. Review branch: `codex/local-video-studio-proof`. Existing checkouts and dirty work were preserved.
- Three user-requested cheaper `gpt-5.6-luna` agents handled core review, installed MoneyPrinterTurbo and hardware/ComfyUI, with bounded independent review. Heavy native jobs were serialized.

A later fetch found three new footer/documentation commits on `origin/main`,
ending at `8a79aa8e49bdd9c6355e17912aeba13702675ced`. They were reconciled by
merge, preserving the exact imported implementation in history. All eight incoming
files outside the three shared documentation files match that main commit
byte-for-byte; both sets of independent documentation entries were retained.
The incoming footer's focused suite passed **6/6 on Node 22.23.2**, using the
existing dependency installation with verified identical lockfile and dependency
declarations. An isolated module-link overlay keeps its cache local. The first
test invocation lacked Vite and ran no test cases; this setup failure is retained.
Video implementation hashes are unchanged by the reconciliation.

## Final validation and repairs

The complete Python suite passed **78/78 in 9.880 s** (9.986 s process wall time).
Canonical brand integrity passed, and creator-credit tests passed **5/5**. Logs
and exact commands are retained in `artifacts/video-studio/workstation-proof/final-checks/`.
The canonical validator also passed all **14 successful run ledgers** at handoff:
nine initial compositions, four unique cache runs and the native MPT draft.
Failed native attempts and deliberately damaged fault copies remain separately
labeled and are excluded from that successful-run count.
The original 66-test run had five macOS path-alias failures; repaired expectations
compare resolved paths. Later regressions cover evidence integrity and installed
tool compatibility. See `WORKSTATION-CORE-REVIEW-2026-09-19.md` for the independent
review and earlier checkpoints.

- HyperFrames quality selection checks the actual CLI's help and records requested/resolved quality.
- Cache hits require successful terminal canonical evidence, successful stages and intact artifact hashes.
- Verification rejects explicit zero-length video streams; contact-sheet creation preserves existing evidence.
- MoneyPrinterTurbo supports the inspected older single-task CLI, accepts startup notices before its result JSON, and binds outputs to new native UUID task directories. Existing tasks and symlink escapes are rejected; configuration remains unchanged.

The remote branch and final commit are the delivery record. Retained MP4s, private
inputs, environments and detailed logs stay in ignored local artifacts; the tracked
`WORKSTATION-EVIDENCE-2026-09-19.json` indexes the measured outputs and receipts.

## Measured outputs

Times below include HyperFrames check and native rendering, as measured by its adapter. Full command wall times were 103.079 s for the four demo formats and 58.942 s for the four imported-media formats. Each format was separately composed.

| Proof | Format | Dimensions | Duration | Render + check | Video |
| --- | --- | --- | --- | --- | --- |
| four-formats | 9:16 | 1080×1920 | 12 s | 26.724 s | `artifacts/video-studio/business-has-an-address-20260919T140605Z-f156f1c3/video.mp4` |
| four-formats | 4:5 | 1080×1350 | 12 s | 22.153 s | `artifacts/video-studio/business-has-an-address-20260919T140634Z-2d51372c/video.mp4` |
| four-formats | 1:1 | 1080×1080 | 12 s | 22.052 s | `artifacts/video-studio/business-has-an-address-20260919T140657Z-9db468b9/video.mp4` |
| four-formats | 16:9 | 1920×1080 | 12 s | 25.611 s | `artifacts/video-studio/business-has-an-address-20260919T140721Z-aedbce7e/video.mp4` |
| imported-formats | 9:16 | 1080×1920 | 4 s | 14.107 s | `artifacts/video-studio/imported-media-proof-20260919T141044Z-2b56d89a/video.mp4` |
| imported-formats | 4:5 | 1080×1350 | 4 s | 12.999 s | `artifacts/video-studio/imported-media-proof-20260919T141100Z-8dce9e14/video.mp4` |
| imported-formats | 1:1 | 1080×1080 | 4 s | 12.751 s | `artifacts/video-studio/imported-media-proof-20260919T141115Z-78cd7c2c/video.mp4` |
| imported-formats | 16:9 | 1920×1080 | 4 s | 12.806 s | `artifacts/video-studio/imported-media-proof-20260919T141129Z-495b5216/video.mp4` |

All nine initial MP4s (including the half-resolution preview) have 30 fps video plus AAC audio. Their canonical Build DNA validators passed. Exact video/ledger hashes, byte sizes and commands are retained in `artifacts/video-studio/workstation-proof/render-manifest.json` and run folders. The four demos are 12 s each; imported-media proofs are 4 s each, using the supplied source clip with a one-second source trim and supplied still.

## Toolchain and audio

Apple M5, 10 logical CPU cores, 16 GiB unified memory; FFmpeg/FFprobe 9.0, Python 3.14.7, Node 24.19.0 and npm 11.17.0. The existing cached HyperFrames 0.8.29 executable was explicitly selected; no global upgrade or package-pin change was made. `delivery` maps to its advertised `high` preset; `looks` maps to `standard`. The pinned 0.8.50 package remains an optional isolated install.

The `doctor` aggregate reported false because of a version notice and absent optional Kokoro/MusicGen; its browser and media tools were available and actual renders passed. This distinction is retained, not rewritten into a green doctor receipt.

The selected executable is
`/Users/famtastic-fritz/.npm/_npx/110f701c48e68d66/node_modules/.bin/hyperframes`.
To repeat either four-format proof from the worktree, use:

```sh
python3 scripts/famtastic-video.py batch \
  artifacts/video-studio/workstation-proof/inputs/demo-with-audio.json \
  --quality delivery \
  --hyperframes /Users/famtastic-fritz/.npm/_npx/110f701c48e68d66/node_modules/.bin/hyperframes \
  --timeout 600
```

Replace the campaign with `inputs/imported-media-captioned.json` under the same
proof directory for imported footage. Cached executable paths must be rechecked
if npm's cache is cleaned; the optional pinned installation remains available.

All four decoded demo soundtracks exactly cover 288,000 samples at 24 kHz and correlate 0.99997866 with the supplied pulse, with a peak of -18.568 dBFS. This is a synthetic pulse, not narration. Installed macOS speech also generated a 6.331667 s mono 48 kHz WAV locally from a prepared script. No voice cloning or network TTS was used. Subjective listening was not performed: the tool surface cannot consume audio input. Browser playback, signal measurements and source correlation do not substitute for human listening.

The four imported-media outputs each decode to 96,000 samples at 24 kHz (4 s),
with 0.99994268 correlation to the corresponding supplied pulse segment.

## Native MoneyPrinterTurbo

The existing `~/MoneyPrinterTurbo` project identifies as 1.3.4 and has no Git
revision metadata. Its missing environment was prepared from its lock using
Python 3.11.15; installed source, lock and configuration were preserved. The
native draft used supplied script, media and 12 s pulse audio with TTS, music,
subtitle generation and automatic upload disabled.

Its material preprocessor uses a nominal 480-pixel minimum with a 10-pixel
tolerance, rejecting either dimension below 470 pixels. The supplied
640×360 clip/still therefore failed; their originals and failed attempts were
retained. Explicit 854×480 derivatives passed material acceptance. The final
stock `scripts/famtastic-video.py mpt-draft` command passed in **22.042 s**,
producing a **12 s, 1920×1080** MP4 with audio:

`artifacts/video-studio/mpt-workstation-proof-20260919-derived-mpt-20260919T143214Z-68d25869/output/moneyprinter-draft-01.mp4`

SHA-256: `18555a0e94ed88efd3a8d42335fca0f4a97b1556f5e5548b327a5ec42ea509da`.
CUA browser playback reached exactly 12 s, unmuted, with no decoder error.
This proves supplied-media draft assembly; it does not preserve HyperFrames
scene layouts, exact scene durations or captions. Native 4:5 is unsupported.
Full setup, intermediate failures, native task binding and checks:
`WORKSTATION-MPT-2026-09-19.md`.

## Visual inspection

Independent review examined four contact sheets and 20 decoded frames including the encoded last frame. Main review inspected all eight contact sheets. Text, source patterns, captions, exact supplied logo and closing frames were present; no visible crop or overlap defect was found. The last subtitle repeats the headline, a small editorial redundancy. Full browser playback was checked separately; this is sampled visual review plus playback validation, not owner creative acceptance. See `WORKSTATION-VISUAL-REVIEW-2026-09-19.md`.

## Local generation and acceptance

No ComfyUI runtime/server or Wan model was found in the bounded installed-app/runtime/cache checks. No native generation was attempted. Official Comfy Wan2.2 5B workflow files total approximately 18.15 GB decimal before runtime/output storage; the sampled free data volume was 11 GiB. Its VRAM claims do not establish fit on this shared-memory Mac. See `WORKSTATION-HARDWARE-GENERATION-2026-09-19.md` for measured headroom, official sources and limits.

The two exact original movies remain owner-supplied inputs for the next acceptance stage. `RECREATION-ACCEPTANCE-READY.md` freezes the intake, hardest-passage-first comparison and scoring procedure. No identity recreation, photorealistic motion, similarity score or owner acceptance is claimed.

## Native cache fault proof

A separate 4 s, half-resolution imported-media campaign rendered in 14.556 s. Its
unchanged repeat returned `cache_hit: true` in 0.586 s, with identical video path
and SHA-256 `e346b59e73d515ea4bdd181d0fb1198ec0b327db88bad6b21a04fc16c4c972f2`.
Changing a copy field in a new campaign version produced a cache miss, different
video hash, and a 12.448 s render.

Tampered and missing contact sheets were tested on disposable copies of the
evidence, with the originals retained byte-identically. Both invalidated reuse
and triggered new native renders (12.646 s and 12.397 s). All four unique successful
cache-run ledgers pass the canonical validator; deliberately broken copies are
kept and labeled as fault injection, not successful evidence. Receipts and the
replayable driver are under `artifacts/video-studio/workstation-proof/cache-*`.

All eight full-resolution videos also reached their exact end times in the CUA
in-app browser with audio unmuted and expected dimensions. The observed state is
retained in `artifacts/video-studio/workstation-proof/browser-playback.json`.

## Side effects and limits

Only isolated source/docs, local runtime setup, local draft media, retained proof
and the authorized review-branch push were in scope. No paid-provider request,
automatic publishing, production deployment, customer communication or live
customer record change was performed. No model weights were downloaded. Network
traffic and electricity were not independently metered; local-input receipts do
not claim workstation-wide network isolation.

The repository's required dated status mirror is written to the mounted FAMtastic
Google Drive folder at handoff. A local mirror write is not proof of cloud sync.
