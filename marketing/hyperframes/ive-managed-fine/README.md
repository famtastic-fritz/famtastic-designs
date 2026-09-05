# I've Managed Fine — six HyperFrames films

Six 9:16 campaign films for FAMtastic Designs, authored in HyperFrames (video
rendered from HTML) and rendered locally. One film per objection, 20-24 seconds
each, because the owner sends them one-to-one to individual prospects as well as
posting them.

The campaign that consumes them — manifest, posting schedule, send-direct guide,
and the full claims table — is `marketing/campaigns/ive-managed-fine/`.

**They rendered, and they carry sound.** Every file below was verified with
`ffprobe`, measured with `signalstats`, and looked at frame by frame.

| Film | Objection | Duration | Frames | Size | Mean YAVG |
|---|---|---|---|---|---|
| `f1-thirty-years` | "I've managed fine for thirty years." | 21.167s | 635 | 6.4 MB | **152.19** |
| `f2-know-where` | "My customers know where I am." | 21.100s | 633 | 3.3 MB | **155.90** |
| `f3-not-technical` | "I'm not technical." | 22.167s | 665 | 5.2 MB | **163.78** |
| `f4-got-burned` | "I tried before and got burned." | 19.800s | 594 | 2.5 MB | **159.70** |
| `f5-too-expensive` | "It's too expensive." | 23.833s | 715 | 2.8 MB | **163.37** |
| `f6-retiring` | "I'm too close to retiring." | 21.500s | 645 | 4.5 MB | **161.20** |

All six: 1080 × 1920, 30 fps, h264 High / yuv420p, AAC 48 kHz stereo, faststart.
Renders are gitignored; everything is reproducible from the repo with the
commands below.

## Cost

**$0.** No provider call of any kind. The plates were already on disk (generated
for the platform-dependency drop, before this campaign existed) and are only
copied, cropped and colour-graded here. Narration is local Voicebox / kokoro on
Apple Silicon — free, no API key, no metered credits. Grading, rendering, muxing
and still extraction are local ffmpeg and Chrome. HyperFrames renders locally;
its paid hosted `cloud render` path was not used.

## Rebuild

```bash
cd marketing/hyperframes/ive-managed-fine

# 1. Stage + grade the existing plates into the six projects. Idempotent, ~6s, $0.
#    Required after a fresh clone: assets/ is gitignored.
./scripts/stage-assets.sh

# 2. Narration. Needs the local Voicebox server running:
#    ~/Development/voicebox/tauri/src-tauri/binaries/voicebox-server-aarch64-apple-darwin --port 17493
#    Writes one bed per film plus narration/timing.json, the measured beat table
#    every composition's data-start / data-duration is authored against.
python3 scripts/build-narration.py

# 3. Gate + render each film. `check` runs lint, runtime, layout, motion and
#    WCAG contrast in one pass and must be clean before rendering.
for d in f1-thirty-years f2-know-where f3-not-technical \
         f4-got-burned f5-too-expensive f6-retiring; do
  (cd $d && npx hyperframes@0.8.29 check)
  (cd $d && npx hyperframes@0.8.29 render --quality high \
      --output renders/$d-silent.mp4)
done

# 4. Narration + stereo. Video is stream-copied, so the grade is untouched.
./scripts/mux-narration.sh

# 5. Stills and campaign video copies.
./scripts/export-stills.sh

# 6. Verify. Non-zero exit if a film leaves the grading contract or ships silent.
for d in f1-* f2-* f3-* f4-* f5-* f6-*; do node scripts/verify-render.mjs $d; done
```

Machine used: Apple M5, hardware GPU (Chrome reports `ANGLE Metal Renderer:
Apple M5`), 4 render workers, `hyperframes@0.8.29`, Node v24.19.0, ffmpeg 9.0.
A film renders in roughly 25-35s.

## Did the grade land?

The films have to cut against
`marketing/creative/heygen/renders/take-a-platform-dependency.mp4`, so they are
graded to that take's **measured** appearance
(`marketing/creative/heygen/reference-tokens.json`) rather than to the brand
spec. Same measurement across every delivered file, every frame:

| | mean YAVG | Δ vs anchor |
|---|---|---|
| HeyGen anchor take | **162.3** | — |
| f5-too-expensive | 163.37 | +1.1 |
| f3-not-technical | 163.78 | +1.5 |
| f6-retiring | 161.20 | −1.1 |
| f4-got-burned | 159.70 | −2.6 |
| f2-know-where | 155.90 | −6.4 |
| f1-thirty-years | 152.19 | −10.1 |
| **campaign mean** | **159.36** | **−2.9** |

The olive accent (`#7FB449`) averages **0.22-0.41 %** of frame and peaks at
**1.33-1.49 %** — inside the 1-2 % budget, and it is never a field, a gradient,
or type. Zero seconds in any film exceed 2 %.

`check` passes clean on all six: 0 lint errors, 0 lint warnings, 0 runtime
errors, 0 layout issues, 0 motion errors, and 17/17 · 23/23 · 22/22 · 34/34 ·
35/35 · 34/34 text checks passing WCAG AA.

## How the cut was timed

Narration is generated **one block per beat**, not as one long read
(`scripts/build-narration.py`). Each block is measured, then the bed is
assembled as `lead silence + block + gap + block + gap + block + tail`, and each
picture cut is placed half a gap after its block ends. Every boundary therefore
lands inside real silence and no cut falls mid-sentence. The measured table is
`narration/timing.json` and it is the source of truth for every `data-start` and
`data-duration` in the six compositions.

Lead-in 0.9s, inter-beat gap 0.45s, tail 1.1s. Delivered audio measures **mean
−20.6 to −20.7 dB, peak −1.1 to −2.6 dB**, in line with the HeyGen presenter
these films cut against (−19.6 dB).

## What is in here

```
BRIEF.md                     confirmed intent
frame.md                     design truth — palette, type, grade, accent budget, claims allowed
STORYBOARD.md                six films, eighteen beats, their timings and their images
scripts/stage-assets.sh      copy + crop + grade the existing plates ($0, local)
scripts/narration.json       the script — one block per beat
scripts/build-narration.py   local TTS + the measured beat table
scripts/mux-narration.sh     narration onto picture, stereo, level-matched
scripts/export-stills.sh     stills + campaign video copies
scripts/verify-render.mjs    measures a delivered file against frame.md's contract
f<N>-<slug>/index.html       one monolithic composition per film, three timed beats
f<N>-<slug>/renders/         delivered MP4 (gitignored)
f<N>-<slug>/verify/          per-film stills and contact sheet (gitignored)
```

Nothing outside `marketing/campaigns/ive-managed-fine/`,
`marketing/creative/campaign-assets/ive-managed-fine/` and
`marketing/hyperframes/ive-managed-fine/` was modified.

## Limitations hit — all real, none hypothetical

1. **Two automated checks returned false results, and both would have shipped.**
   - `verify-render.mjs` reported all six films **SILENT**. It read
     `volumedetect` from stdout; ffmpeg writes it to stderr, so the regex
     matched nothing and `meanDb` came back `NaN`. Six good soundtracks were
     labelled dead by a script that exited 0. Fixed by capturing stderr.
   - The first grade **passed an internal check and failed the actual spec**.
     The RGB-luma measurement in `verify-render.mjs` put every film inside
     150-175, while the `signalstats` YAVG command named in the brief put f1 at
     149.93 and f2 at 146.18 — below the floor. The two measure different
     things: full-range RGB luma against limited-range YUV Y. The brief's
     command is the contract. Both are now reported side by side.
2. **A near-white paper ground cannot sit at 162.** Paper `#cfc7b6` measured
   175.8 (f3) and 176.3 (f5) — above the ceiling. `#c3bba6` is what brought
   them back. `--muted` and `--ink-soft` had to darken with it or WCAG AA
   contrast slipped below 4.5:1 on body text.
3. **The dark night plates fought the band from the other side.** Graded to
   ~78 YAVG they dragged f1 to 149.93 and f2 to 146.18. Lifting them to ~118
   fixed it, at the cost of some of their darkness — they now read as faded
   printed photographs rather than as night, which is consistent with the
   paper system but is a real trade.
4. **The layout audit pads every text rect by ~0.25 em**, so an eyebrow 68px
   above a 172px price reads as `content_overlap` even with a visible gap. The
   fix is arithmetic, not silencing: `price_top − eb_top > 0.25 × price_size +
   line-height + 8`. Every close beat was re-spaced to satisfy it.
5. **Two `<img>` nodes with an identical `src` trip
   `duplicate_media_discovery_risk`**, even in different beats at different
   times. A beat that shows the same subject closer needs its own file, which is
   why `stage-assets.sh` produces `pd-a2-tight`, `pd-b2-tight`, `pd-b1-tight`,
   `pd-p-tight` and `pd-a1-band` as real reframes.
6. **Voicebox returns mono.** A mono AAC track is valid but some social players
   route it to one ear on headphones. `mux-narration.sh` upmixes to stereo and
   adds back the 3 dB the split costs — without it the films measured −23.7 dB
   mean instead of −20.7.
7. **Aspect ratio is baked into the composition.** `--resolution` scales within
   an aspect but cannot re-flow. 1:1 and 16:9 would mean authoring the eighteen
   beats again, or moving the campaign to the Remotion system in
   `marketing/video/`. These are portrait-only.
8. **No music bed.** The repo holds no licensed track and this task authorised no
   provider spend, so the films carry narration over silence between blocks.
   Ten minutes of work once a track exists.
9. **Telemetry feedback was deliberately not sent.** The CLI asks for a
   `hyperframes feedback` report after a verified render. That transmits to a
   public channel, which this task did not authorise.
