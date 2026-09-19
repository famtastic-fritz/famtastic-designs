# Supplied-movie remake and local production proof — 2026-09-19

The supplied FAMtastic movie has a complete **1080×1920, 24 fps, 721-frame,
30.041667-second** remake. It preserves the actual presenter and the original AAC
soundtrack, adds newly authored graphics and phrase captions, and retains the
exact canonical logo and creator credit. Local technical proof passed; Fritz's
subjective creative acceptance and human listening are separate. No movie was
published and no production runtime was deployed.

## Playable delivery and source

- Convenient delivery: `/Users/famtastic-fritz/Downloads/FAMtastic-Designs-30sec-REMAKE-20260919.mp4`.
- Canonical local output: `artifacts/video-studio/project-business-home-remake-20260919T160957Z-5fa4bd31/video.mp4`.
- Final SHA-256: `f3e7b2dd4e0860f7f70cd2808743eacb59afe26f13ce2297ba5c5518ee8f6c23`; 22,186,105 bytes, H.264/yuv420p plus AAC stereo 32 kHz.
- Owner input: `~/Downloads/FAMtastic-Designs-30sec-FINAL-COPY-FIXED.mp4`; preserved byte-for-byte under ignored `artifacts/video-studio/originals/`.
- Original SHA-256: `95ae3ca64a6eaee00c772ddd70063c210b55dff03632b9690ec645ffa2bae49f`; 480×854, 24 fps, 721 frames, 30.041667 s video and 29.952 s audio.
- Authored source: `marketing/brands/famtastic/video-studio/business-home-remake/`, including brief, storyboard, source ledger, captions, template, styles, timeline and deterministic builder.
- Machine-readable index: [RECREATION-EVIDENCE-2026-09-19.json](RECREATION-EVIDENCE-2026-09-19.json).

The actor is not regenerated or face-swapped. Apple Vision separates supplied
pixels; HyperFrames composites them over a new city/house stage, inbox/profile
fragments, an explicitly labelled website illustration, timed benefits, clearer
pricing and an unobstructed closing URL. The footage's 480-pixel detail remains;
1080p graphics do not make the presenter native HD. A new price panel deliberately
covers the original card's occlusion of the right sleeve/torso. It disappears at
25.25 seconds, once that original card is gone. No hidden anatomy is recovered.
The first-year/renewal copy was checked against the repository product catalog;
the 55¢ amount is a rounded $199/365 illustration, not a daily payment product.

## What was repaired and measured

`render-project` supports continuous trusted local HyperFrames compositions with
an explicit manifest, confined listed assets, exact canonical branding, immutable
source snapshots and a separate rendering worktree. Native check/render, hashes,
metadata, contact sheets, failures and cache reuse enter canonical Build DNA.
Static resource checking is not a JavaScript security sandbox or network firewall.

HyperFrames readiness now separates required rendering tools from advisory update,
TTS/music, transcription and Docker checks. The installed 0.8.29 CLI's native
rendering succeeds despite its aggregate doctor reporting optional deficiencies.
Its `delivery` preset resolves to advertised `high`; no render-tool upgrade was
required. The existing hardware/toolchain and four-format results remain in
[WORKSTATION-PROOF-2026-09-19.md](WORKSTATION-PROOF-2026-09-19.md).

The first authored exports did **not** match the original audio signal, despite
similar codec metadata, RMS and loudness. A silent visual clip still exhibited the
mismatch. The cause inside native rendering was not established; duplicate alpha
clip audio is not claimed as the cause. Those diagnostic outputs are retained.

The repaired explicit `audio_master` option keeps `hyperframes-render.mp4` and
uses a separately logged FFmpeg stage to stream-copy its video plus the frozen
source AAC into the final `video.mp4`. Source declaration/bytes participate in the
cache key. Validation permits only one AAC stream, copy mode, offset zero and a
positive finite duration no longer than the composition plus one frame. Errors
preserve the raw render, log and failed evidence.

Independent final comparisons establish:

- **All 937 AAC packet payload hashes, PTS/DTS and durations match the original.**
- Source and final decode to byte-identical stereo float PCM at 32 kHz:
  958,464 samples/channel; SHA-256 `a282772992f45f832328dcfae11fb99757064f132b1d19b01bf1d3d128ce8a77`.
- Correlation 1.0, gain 1.0, peak −2.613 dBFS, RMS −20.785 dBFS; integrated −17.3 LUFS.
- **All 721 H.264 packet payload hashes and timestamps match the retained raw
  render**, proving the mastering step did not re-encode the picture.
- The original AAC priming/skip behavior and its 89.667 ms shorter duration than
  video are preserved. There is no newly lost audio tail or added soundtrack.

Detailed receipts: `artifacts/video-studio/recreation-20260919/audio-review/final-audio-master-proof.{json,md}`.
This proves soundtrack preservation without claiming subjective listening.

## Native times and checks

| Operation | Measured result |
| --- | --- |
| Vision, all 721 frames | 37.33 s wall; 317,390,848 bytes max RSS; zero process swaps |
| Lossless VP9 alpha encode | 41.58 s wall; 267,419,648 bytes max RSS; zero process swaps |
| Final native HyperFrames pipeline | 53.911 s, including 46.876 s frame capture |
| Final check plus render stage | 106.678 s |
| AAC/video stream-copy master stage | 0.069 s |
| Full canonical run through evidence completion | 107.936 s |
| Unchanged final project repeat | 0.956 s command wall, cache hit, identical path and SHA-256 |
| Native MPT with actual original media/audio | 61.237 s adapter; 61.54 s command wall |

These are separate stage measurements, not a claimed total project labor time.
The system uses Apple M5, 10 CPU cores, 16 GiB unified memory, FFmpeg/FFprobe 9.0,
Python 3.14.7 and Node 24.19.0. MPT uses its prepared Python 3.11.15 environment;
repository frontend validation uses available Node 22.23.2.

The Video Studio regression suite passes **93/93**. Coverage includes installed
CLI compatibility, canonical evidence/cache integrity, project input confinement,
branding, immutable snapshots, audio-master validation/fingerprinting and failure
retention. The Swift helper also passed eight negative CLI cases, rejects rotated
inputs before inference and preserves nonempty output folders. A hardened-helper
25.25-second frame matched the earlier full-pass mask byte-for-byte.

Canonical brand sync passed, creator-credit tests passed **5/5**, and all **19
completed technical run ledgers** passed canonical schema and artifact integrity
checks, including the final V8. Diagnostic takes remain excluded from creative
delivery even when their metadata/ledger is structurally valid. Three incomplete
or failed attempts and two deliberately damaged cache copies were excluded.
The exact CI `git grep` secrets command was killed with exit 137; a streaming
working-tree scan with the same pattern and exclusions passed. This is reported
as an equivalent local scan, not a successful execution of the killed command.
Full results are retained under `recreation-20260919/final-checks/`.

Final HyperFrames check: zero errors or warnings; 300 motion samples covering 12
authored assertions; 34/34 contrast checks; nine layout samples. One informational
finding concerns a deliberately entering fragment symbol beyond the canvas.
Scoped alpha-layer overlap/decoration declarations are documented in source; no
blanket check disable was used. Earlier transition-focused diagnostics and rejected
attempts remain under `artifacts/video-studio/recreation-20260919/`.

Parent inspection reviewed final encoded midpoints and ten critical frames at
13.8–14.5, 24.5–26 and 29–30 seconds. Independent inspection of the equivalent V7
layout reviewed ten other encoded timestamps: face visible, offer readable, panel
gone at 25.25 and closing roof below the URL. Source resolution creates soft/pale
matte edges, especially near hair, sleeves and reflections. Captions use locally
transcribed, editorial phrase timing; they are not word-perfect karaoke captions.

CUA's in-app browser played the original, final remake and native MPT proof to
their exact ends with audio unmuted and no decoder errors. It reported 480×854 for
the original and 1080×1920 for both new outputs. Playback receipts and original/
final contact-sheet comparisons are retained. Tool playback and signal measures
do not constitute a person's listening review or a subjective superiority score.

## Native MoneyPrinterTurbo and four formats

The installed MoneyPrinterTurbo 1.3.4 produced a separate 1080×1920, 30 fps,
29.966667 s montage from this exact supplied original and extracted soundtrack:

`artifacts/video-studio/mpt-real-source-20260919-mpt-20260919T154638Z-1863a08c/output/moneyprinter-draft-01.mp4`

SHA-256: `bc13af7009171657fda3c8a3ee7f5f33278c5c4c62f42dc6ad8772aedafd577c`.
Its source/configuration and private native tasks were preserved; no TTS,
subtitles, generated music or upload was enabled. Source-audio correlation is
0.999788 at zero offset after common-rate decoding. Native montage clip policy
can trim/repeat footage; this is installation proof, separate from the designed
remake. See [MPT-ORIGINAL-SOURCE-PROOF-2026-09-19.md](MPT-ORIGINAL-SOURCE-PROOF-2026-09-19.md).

The earlier native 9:16, 4:5, 1:1 and 16:9 compositions, audio/captions/imported
footage and cache fault-injection results remain valid and retained. They are
indexed by [WORKSTATION-EVIDENCE-2026-09-19.json](WORKSTATION-EVIDENCE-2026-09-19.json).
The 30-second authored remake has one carefully composed vertical format; this
report does not claim four completed adaptations of that new movie.

## Generation and boundaries

Local Vision segmentation worked within the actual workstation's capacity.
ComfyUI/Wan diffusion did not run: no installed ComfyUI/model was found, and the
referenced Wan2.2 5B files alone total about 18.15 GB before runtime/output versus
roughly 11–13 GiB sampled free disk. Shared 16 GiB memory does not establish fit
for a workflow's discrete-GPU claim. No weights were downloaded. These are
bounded observations, not a claim that every smaller local model is impossible.
See [WORKSTATION-HARDWARE-GENERATION-2026-09-19.md](WORKSTATION-HARDWARE-GENERATION-2026-09-19.md).

There were zero metered creative-provider requests in these local renders.
Agent account usage, electricity and human work time are not represented by that
provider-fee figure. No publishing, customer messaging, live customer records,
production deployment or paid-provider fallback was activated. HyperFrames logs
its font-cache/compiler behavior; workstation-wide network isolation is not claimed.

## Integration and CI

The exact imported implementation `f24eec09d3a08a667a80463670f188f49f641f5d`
and required base `076261ebb8ede3d60a7eb54ada44aa449794fafa` remain ancestors of
review branch `codex/local-video-studio-proof`. Incoming main through
`8a79aa8e49bdd9c6355e17912aeba13702675ced` was preserved. Work occurred in the
isolated `famtastic-video-proof` worktree; the canonical checkout's unrelated dirty
work was not reset or switched. The owner explicitly authorized main integration
after proof; this source merge does not deploy or publish anything.

GitHub Actions is externally blocked. Main run
[35448391411](https://github.com/famtastic-fritz/famtastic-designs/actions/runs/35448391411)
and two earlier main runs show all three jobs with no steps and no runner. Their
annotation says: “The job was not started because your account is locked due to a
billing issue.” No code-test failure was produced, no billing change was made,
and no checks or protection were weakened. Local results are the merge evidence;
GitHub CI is **not** reported green. The workflow contains acceptance jobs only,
with no deployment step.

All required changelog/capability/learning surfaces are updated. A dated summary
is written and read back in the existing mounted Google Drive folder; local
write verification does not establish cloud sync. Large videos, personal inputs,
masks, models and detailed runtime logs remain ignored; tracked reports bind their
exact hashes and retrieval paths. The original two-movie acceptance plan is now
updated for one supplied movie, with the second remaining a future input.
