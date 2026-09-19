# Give your business a home — supplied-performance remake

A versioned 30.041667-second, 1080×1920, 24 fps HyperFrames composition.
`BRIEF.md`, `design.md`, `STORYBOARD.md` and `SOURCE-LEDGER.json` explain the
creative choices and source limits. This project requires the supplied source
movie and a locally prepared transparent presenter clip; they are retained as
private, ignored workstation artifacts, not embedded in Git.

From the repository root, using this workstation's retained dependencies:

```sh
python3 marketing/brands/famtastic/video-studio/business-home-remake/build_project.py \
  --original artifacts/video-studio/originals/FAMtastic-Designs-30sec-FINAL-COPY-FIXED.mp4 \
  --presenter artifacts/video-studio/recreation-20260919/segmentation-pilot/presenter-matte-lossless.webm \
  --gsap artifacts/video-studio/recreation-20260919/dependencies/gsap-3.14.2.min.js \
  --output artifacts/video-studio/recreation-20260919/project-new

HYPERFRAMES_NO_TELEMETRY=1 HYPERFRAMES_NO_UPDATE_CHECK=1 HYPERFRAMES_NO_AUTO_INSTALL=1 \
python3 scripts/famtastic-video.py render-project \
  artifacts/video-studio/recreation-20260919/project-new \
  --hyperframes /Users/famtastic-fritz/.npm/_npx/110f701c48e68d66/node_modules/.bin/hyperframes \
  --quality delivery --timeout 600
```

Choose a fresh staging folder. The builder checks the exact original and GSAP
3.14.2 digest, copies local fonts and canonical brand assets, removes audio from
the presenter derivative, and extracts the original AAC by stream copy. The
manifest declares that AAC as the final audio master, separately from native
HyperFrames audio. Both raw and mastered renders remain in the evidence run.

The native macOS mask helper is
`marketing/engine/video_studio/tools/segment-presenter.swift`; compile it with
`swiftc -parse-as-library -O INPUT.swift -o OUTPUT`. Its CLI accepts `--input`,
`--output-dir`, `--sample-times 2,6,10,18,26` or `--all-frames true`,
`--component-threshold 32`, `--soft-radius 6`, and `--no-previews`. It refuses
nonempty output directories and nonidentity video rotation. Mask extraction is
local Vision inference; it is not actor generation or recovery of occluded pixels.
Retained mask/alpha preparation commands and timings are in the segmentation
proof directory's `REPORT.md` and logs.

The selected tool path is an existing npm cache and may move if that cache is
cleaned. Select an installed executable explicitly; there is no automatic install
or cloud fallback. See the engine README and
`docs/marketing/local-video-studio/RECREATION-PROOF-2026-09-19.md` for validation,
playable output, exact hashes, source limitations and measured audio preservation.
