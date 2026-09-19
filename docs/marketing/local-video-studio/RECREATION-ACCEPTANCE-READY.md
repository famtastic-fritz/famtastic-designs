# Two-movie recreation acceptance

Status: prepared; the owner's two original movies have not been supplied. The packaged technical patterns and pulse are fixtures, not substitutes for those movies. No creative score or owner acceptance is claimed.

Worktree: `/Users/famtastic-fritz/Documents/ChatGPT/famtastic-video-proof`.
Review branch: `codex/local-video-studio-proof`.

1. Put the exact originals and approved identity/clean-plate sources in ignored `artifacts/video-studio/originals/`. Record their hashes and provenance. Do not guess replacements from other campaign folders.
2. Probe both originals; review each complete movie with sound. Freeze scene boundaries, camera/framing, actions, text, transitions, timing, soundtrack and the qualities Fritz wants preserved.
3. Map each scene to reusable source footage, local compositing, or a genuinely unproven generation requirement. Author a versioned campaign JSON and source ledger. Extract approved source sound explicitly; imported video is muted by the compositor.
4. Recreate the hardest 6–10 seconds first. Do not build the full movie around an unproven identity or motion technique. A panned still is designed motion, not generated live action.
5. Render the complete candidate using the measured local compositor. Generate a comparison package for each pair:

```sh
python3 scripts/famtastic-video.py compare \
  artifacts/video-studio/originals/movie-01.mp4 \
  artifacts/video-studio/recreation-01/video.mp4 \
  --output artifacts/video-studio/recreation-01-review-v1
```

Repeat for movie 02 with fresh paths. The comparison command refuses to overwrite an existing review.

6. Watch both complete pairs with sound. Score identity, composition, motion, timing, typography, brand, audio and story from 0–5. Required: no hard failures and average >=4/5, exact agreed technical output, valid evidence hashes, zero paid-provider requests, and Fritz's acceptance of both results.
7. Hard failures include wrong identity, garbled text, omitted scenes or audio, unreadable crop, unsupported claims, and an incorrect logo. Retain rejected takes, measured generation/render times, attempts, accepted seconds and actual provider fees. Human work time and electricity remain unknown unless measured.

The workstation proof establishes local composition, supplied-media assembly and evidence handling. It does not establish realistic local diffusion, voice cloning, original-movie similarity, or finished creative acceptance. Subjective listening remains an explicit human review step; automated waveform and playback checks do not replace it. ComfyUI/model availability and storage limits are recorded in the workstation hardware report.
