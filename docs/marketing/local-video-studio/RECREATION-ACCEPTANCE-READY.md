# Supplied-movie recreation acceptance

Status on 2026-09-19: the owner supplied **one** original,
`FAMtastic-Designs-30sec-FINAL-COPY-FIXED.mp4`, and requested a better remake plus
source integration into main after proof. That movie now has a complete authored
1080×1920 candidate with the original performance, newly drawn graphics, phrase
captions and the original AAC soundtrack. See
[RECREATION-PROOF-2026-09-19.md](RECREATION-PROOF-2026-09-19.md) for measured
technical, visual, playback and evidence results. A second original has not been
supplied; it is a future acceptance input, not a blocker for this authorized
single-movie implementation and merge.

Worktree: `/Users/famtastic-fritz/Documents/ChatGPT/famtastic-video-proof`.
Review branch: `codex/local-video-studio-proof`.

## Completed first-movie handoff

- Original bytes are preserved in ignored `artifacts/video-studio/originals/` and
  SHA-256 bound to the authored project's source ledger.
- Apple Vision extracts the actual supplied presenter; the same face, actions and
  source timing are retained. The 480×854 source has limited detail and baked-in
  occlusion; this is not a newly generated actor or restored unseen anatomy.
- HyperFrames renders 721 frames at 24 fps. An explicit recorded mastering step
  copies the original AAC track without another audio encode. Native attempts
  that failed source-audio correlation remain retained and rejected for delivery.
- Canonical branding, contact sheets, motion checks, metadata, source/hash
  integrity, cache reuse and unmuted browser playback are measured separately.
- The comparison package is under ignored
  `artifacts/video-studio/recreation-20260919/comparison-final-v8/`.
  Automated and agent visual review are not Fritz's creative acceptance.

## Procedure for the next original or a creative revision

1. Preserve the exact new original and any clean plates in a fresh ignored folder;
   record hashes and provenance. Do not guess replacements from campaign folders.
2. Probe the movie and review all scenes. A person should listen to the entire
   soundtrack: this agent's tool surface cannot consume audio, and signal checks
   do not replace subjective listening.
3. Freeze scene boundaries, identity, camera, text, transitions, timing and sound.
   Map each scene to supplied footage, local composition or an explicitly unproven
   generation requirement. Keep a versioned source ledger and brief.
4. Prove the hardest 6–10 seconds first. Do not build around unproven identity or
   generation claims. A panned still is designed motion, not generated live action.
5. Render to a fresh run, preserving prior videos and evidence. Generate comparison:

```sh
python3 scripts/famtastic-video.py compare \
  artifacts/video-studio/originals/movie-02.mp4 \
  artifacts/video-studio/recreation-02/video.mp4 \
  --output artifacts/video-studio/recreation-02-review-v1
```

6. For creative acceptance, Fritz can score identity, composition, motion, timing,
   typography, brand, audio and story from 0–5; suggested target is average ≥4/5
   with no hard failures. Record the decision against the exact video hash.
   Publication requires its own authorization; repository integration does not
   publish a movie or approve an advertisement.
7. Hard failures include wrong identity, garbled text, missing scenes/audio,
   unreadable crop, unsupported claims or an incorrect logo. Retain rejected takes,
   actual timings and fees. Do not invent electricity or human-work costs.

The workstation proof establishes local composition, supplied-performance
recomposition, supplied-media draft assembly and evidence handling. Local
photorealistic diffusion and voice cloning remain unproven. No ComfyUI/model was
installed or run; see the measured hardware and generation report.
