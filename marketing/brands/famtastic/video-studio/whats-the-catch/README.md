# What’s the catch? — an original FAMtastic film

Original 1920×1080 / 30 fps HyperFrames motion-graphics film using the owner’s complete supplied script, new local Kokoro narration, measured utterance timings, and canonical FAMtastic assets. The original reference movie is not an input to this film.

`user-script.txt` is the editorial source. `build_project.py` freezes the current HTML, CSS, GSAP motion, brand assets, measured audio/cues, transcript and optional WebVTT track into a versioned authored project. Original SVG foundation/step objects illustrate the philosophy; they do not represent measured customer results. Read `BRIEF.md`, `design.md` and `STORYBOARD.md` for the creative direction.

## Reproduce

Use the existing HyperFrames 0.8.29 installation recorded in the run receipts. Do not use an unpinned `npx` download. For future narration reproduction, follow [`VOICE-REPRODUCTION-V2.md`](VOICE-REPRODUCTION-V2.md) and use the versioned `*-v2.py` scripts. The original unversioned script pair is retained as historical evidence for the final narration Build DNA and is not the current reproduction entry point. Supply the existing pinned GSAP 3.14.2 file (SHA-256 is validated by the builder).

```sh
export HYPERFRAMES_CLI=/path/to/already-installed/hyperframes-0.8.29
export HYPERFRAMES_PYTHON=/path/to/local/python-3.11
python3 marketing/brands/famtastic/video-studio/whats-the-catch/synthesize-local-voice-v2.py \
  --output-dir artifacts/video-studio/no-catch-20260919/audio/reproduction-unique
python3 marketing/brands/famtastic/video-studio/whats-the-catch/assemble-local-voice-v2.py \
  --audio-dir artifacts/video-studio/no-catch-20260919/audio/reproduction-unique
python3 marketing/brands/famtastic/video-studio/whats-the-catch/build_project.py \
  --audio /absolute/path/to/narration.m4a \
  --cues /absolute/path/to/cues.json \
  --gsap /absolute/path/to/gsap-3.14.2.min.js \
  --output /absolute/path/to/fresh-project
python3 scripts/famtastic-video.py render-project /absolute/path/to/fresh-project \
  --hyperframes /absolute/path/to/existing/hyperframes \
  --quality delivery --timeout 900
```

The builder refuses an existing output directory, mismatched logo/GSAP bytes, non-AAC input, invalid or overlapping timings, and cues not bound to the exact supplied script. The existing renderer freezes every declared input before rendering, records Build DNA and measured stages, checks native layout/motion/contrast, and copies the declared AAC master losslessly into the final MP4. A second identical invocation proves verified cache reuse. Preserve failed attempts and fix authored sources before staging a new project version.

Captions use seven-word editorial chunks distributed within each measured utterance. This is phrase timing, not forced word alignment. Captions are burned into the movie; the page’s optional VTT track is not enabled by default, preventing duplicate captions.

## Publication

The source page is `frontend/src/pages/WhyFamtasticPage.jsx`, at `/why-famtastic/`. It includes the full transcript and explicit first-year scope, separate hosting authorization, domain renewal and optional-service terms sourced from the current catalog. It links to the existing research-first $199 funnel.

The three versioned media files are published separately from the clean server-side frontend build using the reviewed, dry-run-first `scripts/publish-no-catch-film.py` primitive. Generated media and private receipts remain under ignored `artifacts/video-studio/no-catch-20260919/`; never add them to Git merely to make a server build find them. The canonical frontend deployment preserves the independently published media. One exact owner notification uses the existing standard/v2 branded outbox path after public media and browser proof.

This user-authorized release is scoped to this film/page and one owner email. It creates no social schedule, charge, paid-provider generation, or broad publishing permission. Final hashes, timing, QA, deployment and send receipts are documented in `docs/marketing/local-video-studio/NO-CATCH-FILM-2026-09-19.md`.
