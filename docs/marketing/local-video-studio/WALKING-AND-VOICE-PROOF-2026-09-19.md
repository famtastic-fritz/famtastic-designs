# Presenter continuation and local voice proof — 2026-09-19

## Delivered scope

The owner asked for a faster second ad, a continuation closer to the supplied presenter film, free local voice-cloning research and milestone emails. The faster 72.8-second ad is already published and its notice reached Gmail; see [the revision report](VIDEO-REVISION-V2-2026-09-19.md).

The new presenter continuation is **35.583333 seconds, 854 frames, portrait 1080×1920 at 24 fps**. Three original passages preserve matching picture/dialogue; a separate synthetic narrator reads the owner's 62-word bridge. Its 23 phrase captions have a minimum display duration of 0.663843 seconds. The final composition uses unoccluded wide source inserts during voiceover, with an explicit voiceover marker. It creates no new acting or lip synchronization. Native presenter detail remains 480×854.

The **32.8-second square voice comparison** has three labeled chapters: original synthetic narrator, synthetic reference, and local conversion. The source and target are Kokoro stock voices, not Fritz's voice. Official OpenVoice V2 converted the complete 13.437-second source in **7.674 seconds**, with **1.63 GB process peak RSS**, on two CPU threads. The final ASR recognized the intended brand closing line; this is not pronunciation or identity acceptance. See [research and reproduction](VOICE-CLONING-RESEARCH-2026-09-19.md).

## Measured proof

| Output | Native renderer time | Check + render stage | Final SHA-256 |
| --- | ---: | ---: | --- |
| Presenter continuation | 68.096 s | 123.980 s | `e1a131b4a47a68d4af3c45301d023f98908b351c01520f112e9a0ae4d2fce309` |
| Voice comparison | 13.517 s | 66.541 s | `feceb73c880568fdff4ca1d496e4cb0b49af5c38f56a5d0082921967705e0b6d` |

Both final MP4s fully decode and contain one AAC track whose packet and decoded PCM hashes exactly match its declared master. The continuation master measures −14.6 LUFS / −1.9 dBTP. Its three reused source-dialogue regions have independently decoded PCM correlations above 0.9999999998. Caption repair left audio bytes unchanged.

Both native runs passed 300 motion samples with zero motion findings. Continuation contrast passed 44/44; comparison 25/25. Lint and runtime errors/warnings are zero. The continuation retains 80 layout warnings exclusively for deliberately clipped background-city SVG geometry; no text-overlap errors remain. The comparison has zero layout findings. Final contact sheets and complete unmuted browser playback are retained separately from human creative/listening acceptance.

The expanded Video Studio suite passes **105 tests** (13.688 s), including eight focused conversion/provenance/frozen-input tests. Fixed-edition publisher collision checks pass for all three editions; canonical brand and creator-credit checks pass. PHP syntax checks pass for both fixed-key owner notices. Remote GitHub Actions remain unavailable because the account is billing-locked; local validation is not described as green remote CI.

## Repairs and retained evidence

Failed attempts remain available. Repairs cover alpha metadata casing after VP9 remux, missing GSAP exit resets, a class/ID selector mismatch, ambiguous caption assertions, overlapping headlines, low-contrast small labels, square head crops, use of price-card-occluded footage and an 80 ms caption caused by duplicated grouping definitions. The final project uses the shared reviewed grouping and unoccluded source ranges.

The conversion runner snapshots itself, its exact runtime inventory, speech/reference/provenance, pinned model files and pinned upstream source before inference. Its successful full-length run supersedes an earlier mutable-source attempt and a separate five-second mechanics test. An upstream watermark constructor bug is bypassed without loading the optional separate watermark dependency; the output is explicitly unwatermarked. The final comparison is an experiment, not the ad's narrator.

Disk prerequisites briefly stopped rendering. Two bounded cleanup receipts record removal of completed, regenerable HyperFrames frame-extraction caches only. Source films, models, completed renders and evidence were preserved. The machine has 16 GiB unified memory; larger new-body video generation was assessed against actual storage and runtime limits and was **not proved**. See [the walking-generation assessment](WALKING-GENERATION-ASSESSMENT-2026-09-19.md).

Evidence roots:

- Final continuation: `artifacts/video-studio/walking-continuation-20260919/final-v6/`; immutable source project `project-v6`; render `project-famtastic-walking-continuation-v1-20260919T191546Z-a68336f4`.
- Comparison: `artifacts/video-studio/local-voice-proof-20260919/`; immutable source `project-v3`; render `project-local-voice-proof-20260919T191106Z-d0a6eb5b`.
- Conversion: `artifacts/video-studio/voice-clone-research-20260919/openvoice-v2-local-proof/conversion/run-v5/` and `conversion-attempt-05.json`.
- Release and delivery receipts: `artifacts/video-studio/revision-parts-20260919/release/`.

## Publication and notices

The versioned review assets use the existing fixed-file, no-clobber publication lane. The original `/why-famtastic/` page and first-film assets remain unchanged. The comparison is already public with matching HTTP hashes/MIME, 206 byte ranges and complete unmuted browser playback. Continuation publication and both exact-key standard/v2 owner notices are being completed from this proven source; actual receipts will be appended here after delivery. No broad email worker, social scheduler, paid creative provider or frontend deployment is enabled.
