# MoneyPrinterTurbo workstation proof — 2026-09-19

## Result

The stock FAMtastic wrapper produced one local draft through the existing MoneyPrinterTurbo 1.3.4 CLI. The successful render used the supplied audio and locally resized copies of the supplied video and still image. The original images are 640×360; this installation rejects local material unless both dimensions are at least 470 px (480 px nominal, with a 10 px tolerance), so the first native attempt stopped at material preprocessing. The source files remain unchanged.

The successful run is `artifacts/video-studio/mpt-workstation-proof-20260919-derived-mpt-20260919T143214Z-68d25869/`. Its Build DNA is `gated`, integrity passed, and review remains pending. This proves draft assembly and media playback; it is not creative approval or rights clearance.

## Existing installation and safety boundary

- Installation: `/Users/famtastic-fritz/MoneyPrinterTurbo`, project version `1.3.4`; no Git revision was available.
- Runtime: CPython `3.11.15` in the installation’s ignored `.venv`, uv `0.12.2`, FFmpeg `9.0`. The environment was populated from the existing lockfile with `uv sync --locked --python 3.11 --no-dev`; `uv lock --check` passed. No MPT source, lockfile, or config was changed. The host Python 3.14 could not import this release.
- The adapter detected the installed single-task CLI and schema. It bound the returned video path to the fresh native task ID `61d4ee9b-c1ee-4f9b-8569-4f3396aa0826` and confirmed the exact output beneath that task’s managed directory.
- Publishing flags were `upload_post_enabled=false` and `upload_post_auto_upload=false`. The CLI’s `--stop-at video` stage may run configured cross-posting, so the false auto-upload setting is the effective boundary checked by the adapter. No publishing was observed or requested; no credentials were printed or changed.
- The MoviePy distribution metadata reports `2.2.1`, while imported `moviepy.__version__` reports `2.1.2`. This discrepancy was recorded without upgrading or changing the installation.

## Inputs and native material acceptance

The copied proof inputs and their original hashes are:

| Input | Properties | SHA-256 |
|---|---|---|
| `artifacts/video-studio/workstation-proof/mpt/inputs/source-fixture.mp4` | 640×360, 4 s | `0404486d5844b7bc84cfc3e93a66f7a27763c4006bbf36f92b2da4bcbe457650` |
| `artifacts/video-studio/workstation-proof/mpt/inputs/source-fixture.png` | 640×360 | `97e6219e09cfa1182712b1dfd7c5a018a3868fe4fd9a740bf493729c7648ad68` |
| `artifacts/video-studio/workstation-proof/mpt/inputs/pulse.wav` | mono, 24 kHz, 12 s | `2731e0faf089666b4a66d021ad0c89f1c0d076638271ff5823ce9e5595474d73` |

The first native run is preserved at `artifacts/video-studio/mpt-workstation-proof-20260919-mpt-20260919T141300Z-5a6b62ef/` with failed Build DNA. Its sanitized diagnostic records stage `materials` and `no valid local video materials were found`. Inspection of the installed `app/services/video.py` showed a nominal 480 px minimum in each dimension with a 10 px tolerance (effective cutoff 470 px); the supplied visuals are 360 px high.

For the proof, FFmpeg 9.0 made local 854×480 copies in `artifacts/video-studio/workstation-proof/mpt/derived/`, preserving the near-16:9 framing. The supplied WAV was used unchanged. The derived media hashes are:

- `source-fixture-854x480.mp4`: `c6ef53c095141de2d8764219971651df55dacfd770fc4671565d800439812712` (4 s).
- `source-fixture-854x480.png`: `84a65fa0453069def3bc5e2fb4db84a66cd0662f0e0900f1b07756a5c729f980`.

A native `--stop-at materials` probe accepted both copies in 2.291 s and returned two prepared materials. Its sanitized receipt is `artifacts/video-studio/workstation-proof/mpt/materials-diagnostic-derived.json`. One early wrapper preflight used relative media paths and stopped before MPT launch; the campaign was corrected to absolute local paths before the successful run.

## Successful command and output

The stock repository wrapper ran with:

```text
python3 scripts/famtastic-video.py mpt-draft \
  /Users/famtastic-fritz/Documents/ChatGPT/famtastic-video-proof/artifacts/video-studio/workstation-proof/mpt/campaign-derived.json \
  --mpt-root /Users/famtastic-fritz/MoneyPrinterTurbo \
  --mpt-python /Users/famtastic-fritz/MoneyPrinterTurbo/.venv/bin/python \
  --timeout 900
```

The wrapper completed in 22.042 s (native render receipt: 21.781 s). The adapter supplied a prepared script, both local materials, and the custom WAV, with no voice, subtitles, or background music. It used sequential assembly, one output, two threads, and a fresh task UUID. No automatic provider fallback was used.

The verified draft is `artifacts/video-studio/mpt-workstation-proof-20260919-derived-mpt-20260919T143214Z-68d25869/output/moneyprinter-draft-01.mp4`: H.264, 1920×1080, 30 fps, 360 frames, 12.0 s; AAC stereo 44.1 kHz, 12.0 s; 3,179,907 bytes; SHA-256 `18555a0e94ed88efd3a8d42335fca0f4a97b1556f5e5548b327a5ec42ea509da`. The receipt is alongside the video. The native task’s `storage/tasks/61d4ee9b-c1ee-4f9b-8569-4f3396aa0826/final-1.mp4` was bound to the returned task ID before copying into the canonical artifact directory.

`python3 scripts/famtastic-video.py verify <draft.mp4>` passed metadata checks. The retained contact sheet, `artifacts/video-studio/workstation-proof/mpt/mpt-contact-sheet.jpg`, samples the output at 0, 4, and 8 seconds; it shows the supplied synthetic color-bar footage assembled without subtitles. A read-only browser playback record at `artifacts/video-studio/workstation-proof/mpt/browser-playback.json` shows playback reached 12.0 s, ended, unmuted, at 1920×1080, with no decoder error. A technical 100 ms RMS-envelope comparison between the supplied WAV and decoded output audio produced Pearson correlation `0.999998` at zero offset. No subjective listening claim is made.

The final wrapper run used the stock `scripts/famtastic-video.py` entry point with no `PYTHONPATH` observer or `sitecustomize` shim. An earlier failure-only diagnostic shim summarized the original material-stage error; it was absent from the successful run.

## Validation, cost, and limits

The MoneyPrinter adapter unit tests passed 15/15. The parent’s integrated video-studio suite passed 78/78. Native logs were kept in memory and not written to disk because dependency diagnostics can expose credentials; sanitized error summaries, the adapter receipt, output hashes, and Build DNA are retained. The render receipt records local provider fees of `$0`; electricity and network activity were not measured.

MoneyPrinterTurbo produced a draft montage, not the FAMtastic campaign’s exact scene layouts or individual scene timings. The installed CLI lacks an explicit `video_fit_mode` option, so its native fit behavior was used. Visual and rights review remain pending, and subjective listening was not performed. Supporting checks are in `artifacts/video-studio/workstation-proof/mpt/mpt-proof-checks.json`.
