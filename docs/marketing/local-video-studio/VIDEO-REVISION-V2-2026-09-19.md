# Video revision two — 2026-09-19

## Owner feedback and result

The owner rejected the first film’s slow narration and the pronunciation of FAMtastic. This supersedes the earlier ASR-based pronunciation claim. The original 103.933333-second film, audio, scripts and evidence remain unchanged.

The new film is **72.8 seconds** (2,184 frames, 1920×1080, 30 fps), with all 21 source lines retained. The new local Kokoro narrator uses speed **1.06**, versus 0.78 before, and 0.18-second inter-line gaps. Its 70.778667-second AAC master measures **−16.6 LUFS / −1.7 dBTP**. The engine receives explicit brand phonemes `fæmtˈæstɪk` (fam-TAS-tik); source/caption spelling is untouched. The installed phonemizer’s previous title-case output was `fæmtˈeɪstɪk`, explaining the owner’s fam-TAY-stik complaint.

This is the stock synthetic narrator `am_michael`, not a clone of Fritz. Listening preference and creative acceptance remain the owner’s review.

## Proof

- Local TTS inference: **17.241 seconds** for all 21 utterances.
- Native HyperFrames render: **31.952 seconds**; combined native check/render ledger stage: **82.778 seconds**.
- Identical project cache lookup returned the same final SHA in a **0.780-second command**.
- Full decode passed; final AAC packet hash and decoded PCM hash exactly equal the new master.
- Native motion validation: 300 samples, zero findings. Contrast: 42/42 passed.
- Local ASR contains all source content. It is a content check, not listening acceptance.
- CUA played the full 72.8 seconds with audio unmuted, ended=true and no media error. The optional caption track loaded with readyState=2.
- Video Studio: **97 tests passed**. Existing no-clobber publisher: **11 tests passed**. Brand asset check passed.

Final MP4 SHA-256: `41fa7b56d73db8a520a014d4084669ae2ce1d4fca5b82427f3c92d60f5438b79` (8,271,743 bytes).

## Source and evidence

Campaign: `marketing/brands/famtastic/video-studio/whats-the-catch-v2/`. Shared new pronunciation module: `marketing/engine/video_studio/fam_video/brand_voice.py`. Local voice runner: `scripts/famtastic-local-narration.py`; freezes script, runner and phonetic contract before inference, requires pinned installed model/package hashes, and refuses an existing output directory.

Local evidence root: `artifacts/video-studio/no-catch-v2-20260919/`. Render: `artifacts/video-studio/project-whats-the-catch-v2-20260919T182234Z-ac03a770/`. Narration, render and QA each have canonical Build DNA. The source-supplied copied campaign graphics are unchanged; scenes/captions are timed from the new PCM lengths.

## Delivery status

Prepared a bounded, dry-run-first publisher for only the two new fixed film basenames; it reuses the already tested private staging, hash verification and no-clobber promotion. The fixed owner notice uses a fresh content-bound outbox key and the shared standard/v2 renderer. Publication/SMTP/inbox receipts will be recorded only after they happen. The existing public v1 page remains intact during review.

## Remaining work in this revision

Presenter-led continuation and local voice-cloning assessment/proof are separate parts in progress. They do not inherit acceptance from this faster motion-graphics film. No paid provider, social scheduler, automatic publication or full-body diffusion generation has been enabled.
