# Execution proof — September19,2026

This is measured evidence from the isolated implementation host, not a benchmark of Fritz’s Mac. Software: HyperFrames0.8.50, Node24.19.0, local Chrome Headless Shell and FFmpeg6.1.1. No paid-provider request was made.

## Completed checks

- 66 Python tests passed, including real FFmpeg media and a fake local ComfyUI HTTP server.
- 5 canonical creator-credit contracts passed; shared brand asset hashes match.
- All six render runs passed the existing canonical Build DNA validator.
- Repeating the wide render returned a cache hit with identical video hash.
- Storyboard/prompt export and paired-video comparison completed.
- All previews contain H.264 video and AAC audio; preview audio decodes to nonzero samples.
- Layout/runtime checks passed. Agent inspected sampled frame sheets; owner acceptance and full audiovisual review remain pending.

## Render evidence

| Output | Dimensions | Seconds | Render stage | Result |
| --- | --- | --- | --- | --- |
| 9:16 motion_graphics | 540×960 | 12 | 24.245s | Passed |
| 4:5 motion_graphics | 540×676 | 12 | 22.551s | Passed |
| 1:1 motion_graphics | 540×540 | 12 | 23.919s | Passed |
| 16:9 motion_graphics | 960×540 | 12 | 25.273s | Passed |
| 16:9 editorial | 960×540 | 4 | 21.767s | Passed |
| 9:16 delivery | 1080×1920 | 12 | 38.145s | Passed |

Render-stage duration includes the adapter’s check/render/probe work. It is not a generation-speed claim. The half-scale4:5 proof uses540×676 because H.264 requires even dimensions; delivery uses1080×1350. Exact hashes and run names are in [proof-summary.json](proof-summary.json).

The imported-media test uses an original FFmpeg test pattern, including a trimmed video and a separate still on the resolve layout. It proves media routing and composition, not cinematic quality. The12-second marketing demo uses an original synthetic pulse with test captions, not voice narration.

## Honest limits

- ComfyUI protocol/resume and MoneyPrinterTurbo adapter behavior are tested; no native GPU/model or owner-installed MPT run is claimed.
- macOS system speech is implemented for the owner’s workstation, not executed on this Linux host.
- The two specific original movies were not supplied, so recreation quality is untested.
- The broad campaign-readiness script remains blocked on missing local command foundation/Ollama here; its manifest and approval-state checks pass.
- Local provider fees were zero. Electricity, hardware allocation and operator time are unmeasured.
- No production deployment, external campaign publication or customer notice occurred.

## Defects caught and repaired

The actual runtime required a WAAPI root annotation omitted from the documentation example. Caption space initially pushed compact-format objects outside the canvas; compact formats now have a distinct composition and bounded fitting. Cache checks now retain all evidence integrity, not just video bytes. Source clips require enough duration and explicit audio handling; an image cannot be labeled AI video. Generation freezes source inputs and resumes the original prompt after timeout.

## Reproduce

Use [CLI-HANDOFF.md](CLI-HANDOFF.md). Run directories are ignored local artifacts; they are not committed to a public repository. Regenerate them from the CLI and retain fresh canonical evidence on the target workstation.
