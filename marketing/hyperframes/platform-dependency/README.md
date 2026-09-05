# Borrowed Land — Platform Dependency (HyperFrames)

A 29-second 9:16 campaign film for the Platform Dependency drop, authored in
HyperFrames (video rendered from HTML) and rendered locally.

**It rendered.** Two files, both verified with `ffprobe` and by extracting frames
and looking at them:

| File | Dimensions | Duration | Frames | Video | Audio | Size | Render time |
|---|---|---|---|---|---|---|---|
| `renders/borrowed-land-1080x1920.mp4` | 1080 × 1920 | 29.000 s | 870 @ 30 fps | h264 High, yuv420p | aac LC, 48 kHz stereo | 14.9 MB | **31.1 s** |
| `renders/borrowed-land-2160x3840.mp4` | 2160 × 3840 | 29.000 s | 870 @ 30 fps | h264 High, yuv420p | aac LC, 48 kHz stereo | 40.6 MB | **1 m 29 s** |

Renders are gitignored (see `.gitignore`); both are reproducible from the repo
with the commands below.

## Cost

**$0.** Every provider call in this film happened before this project existed:
the gpt-image-2 anchor ($0.18) and the 21 Gemini plates ($0.7056) were already
on disk, and the HeyGen take was already rendered. This project generated no
image, no voice, no video, and no model call of any kind. All processing is
local — ffmpeg for the grade, Chrome + ffmpeg for the render. HyperFrames itself
is free to run locally; only its optional hosted `cloud render` path costs money,
and it was not used.

## Re-render

```bash
cd marketing/hyperframes/platform-dependency

# 1. Stage + grade the campaign assets into assets/ (idempotent, ~4s, $0).
#    Required after a fresh clone: assets/ is gitignored.
./scripts/stage-assets.sh

# 2. Gate. Runs lint, runtime, layout, motion and WCAG contrast in one pass.
npx hyperframes@0.8.29 check

# 3. Render.
npx hyperframes@0.8.29 render --quality high \
  --output renders/borrowed-land-1080x1920.mp4

npx hyperframes@0.8.29 render --quality high --resolution portrait-4k \
  --output renders/borrowed-land-2160x3840.mp4

# 4. Verify. Fails (exit 1) if any second falls outside the grading contract.
node scripts/verify-render.mjs renders/borrowed-land-1080x1920.mp4 verify
```

`npm run check` / `npm run render` are the same commands with the version pin
already applied. Review frames without a full render:

```bash
npx hyperframes@0.8.29 snapshot --at 1.75,4.85,9.35,16.5,22.3,27.0
npx hyperframes@0.8.29 preview --background   # Studio, for a human pass
npx hyperframes@0.8.29 preview --stop
```

Machine used: Apple M5, hardware GPU (Chrome reports `ANGLE Metal Renderer:
Apple M5`), 4 render workers, `hyperframes@0.8.29`, Node v24.19.0.

## Did the grade land?

This was the whole point: the film has to cut against
`marketing/creative/heygen/renders/take-a-platform-dependency.mp4`, so it is
graded to that take's **measured** appearance
(`marketing/creative/heygen/reference-tokens.json`), not to the brand spec.

Same measurement (`signalstats` YAVG, mean over every frame) across three files:

| File | mean YAVG | Δ vs anchor |
|---|---|---|
| HeyGen anchor take (the thing being matched) | **162.3** | — |
| **This film** | **165.8** | **+3.5** |
| Existing Remotion 9:16 for the same campaign | 212.1 | +49.8 |

`scripts/verify-render.mjs` also checks per-second: **0 of 29 seconds fall
outside** the 150–175 luminance band, and the olive accent (`#7FB449`) averages
**0.30 %** of frame area, peaking at **1.22 %** during the presenter inset —
inside the 1–2 % budget, and never a field, gradient or wash.

Three of the four plates already carry their own small olive incident (a green
tag beside the letter slot, a latch on the drawer bank, a clip beside the card
holder), so the composition adds only one accent element per beat rather than
doubling the green.

The grade was not achieved with a CSS filter. `scripts/stage-assets.sh` grades
each plate with ffmpeg before authoring — per-plate `eq`, a shadow lift toward
the measured `#33272E`, lanczos upscale, mild unsharp — and the compositions add
a radial edge falloff in the same measured shadow colour, because a real paper
surface is not a flat value.

## Accuracy

Every product claim traces to `backend/config/famtastic-products.json`:

- `$199` — `FAM-FOOT-199`, `price: "199.00"`, `published: true`.
- "55 cents a day" — 199 / 365 = $0.545.
- The three included items are the SKU's `summary` restated: one focused
  landing-page website, one year of managed hosting, first-year domain
  registration **or** connecting a domain the customer already owns.
- "Business email and maintenance are separate." is on screen, so nothing above
  it can be read as including them (`FAM-BUSINESS-EMAIL` $99,
  `FAM-MAINTENANCE` $49.99/mo are their own SKUs).

No statistics, no percentages, no competitor named. `famtasticdesigns.com` was
curled before it was set in type — HTTP 200 on both apex and `www`, title
`FAMtastic Designs | Agentic AI Business Solutions Engineering Studio`. No
`/web/` path appears anywhere.

The on-screen type is a condensed restatement of the approved HeyGen narration
(`marketing/creative/heygen/scripts/take-a-platform-dependency.json`), never a
new claim.

## How the cut was timed

`silencedetect` found nothing at −32, −25, −22 or −18 dB — the take carries
continuous room tone, so a dB gate never opens on it. A relative RMS envelope
was used instead (mono 16 kHz, 25 ms windows, gaps = runs below 15 % of peak
lasting ≥ 0.12 s), which resolved all seven sentences. Every scene boundary sits
**inside** one of those gaps, so no cut lands mid-sentence. Full table in
`scripts/vo-timing.md`.

The presenter appears once, as a muted framed inset at the turn, with sound
coming from a separate `<audio>` pointed at the same file (the framework's own
media contract). Lip-sync was verified by extracting render frames at 15.4 /
16.6 / 17.8 s and the source at those times minus 0.6 s, and comparing mouth
shapes: they match, so `data-media-start="14.3"` is correct.

## HyperFrames vs the existing Remotion system, for this job

Both were used on the same campaign, so this is a real comparison rather than a
feature list. `marketing/video/` is the Remotion system (2,542 lines of TS/TSX,
692 MB of `node_modules`, `remotion@4.0.509`); it already ships a
`platform-dependency` drop in three aspects.

**Where HyperFrames won here**

- *Nothing to compile, and the loop is short.* Author HTML, `check`, `render`.
  Thirty-one seconds for 870 frames of 1080 × 1920 with video and audio, on
  four workers. The whole project is 7 HTML files, one shell script and one
  verify script — no build step, no framework runtime to learn, no
  `node_modules` in the project at all.
- *`check` is a real gate, not a linter.* One command runs lint, runtime errors,
  failed requests, a layout audit at sampled times, motion assertions and WCAG
  AA contrast. It caught two defects I would have shipped: colliding numerals in
  the offer list, and text-block clearances that were tighter than they looked.
  Remotion has no equivalent; you look at frames or you don't find out.
- *Media handling is declarative and it worked first time.* `<audio>` pointed at
  the same MP4 as a muted `<video>`, with `data-media-start` for the source
  offset, gave a correct narration bed and a lip-synced inset with no code.
- *Fonts embed deterministically.* Oswald, Inter and JetBrains Mono are
  pre-bundled and inlined at build time; the render is offline and reproducible.

**Where Remotion is still ahead — and it is not close**

- **Multi-aspect.** This is the decisive one. `marketing/video/src/system/formats.ts`
  resolves layout *from the frame size at render time*: it re-flows type,
  re-breaks headlines and swaps in an aspect-native plate, producing 9:16, 1:1
  and 16:9 from one `DropConfig`. HyperFrames has no equivalent. `--resolution`
  only scales by an integer factor within the same aspect; asking for a square
  render off this composition is refused outright:

  > `outputResolution square (1080×1080) does not match the aspect ratio of the
  > composition (1080×1920).`

  So 4K portrait was free; 1:1 and 16:9 would mean authoring the six scenes
  again. That is why this deliverable is portrait-only.
- **Data-driven campaigns.** A Remotion drop is a typed `DropConfig` object;
  a new cut is a new data file against a shared scene kit. HyperFrames
  compositions are hand-authored HTML per scene. Its `--variables` / `--batch`
  path covers text and image swaps, but not "same argument, different scene
  count and different aspect".
- **Component reuse.** `src/system/kit.tsx` (621 lines) is a real component
  library with typed props. HyperFrames sub-compositions are transport
  containers, not components: shared style lives in each file or is inherited
  through CSS custom properties from the host.

**Honest verdict:** HyperFrames earns a place *alongside* Remotion, not instead
of it, and the split is clean. Remotion owns the repeatable multi-aspect
campaign engine, because it already solves the problem HyperFrames cannot
express. HyperFrames owns the one-off, design-led, single-aspect piece where
authoring speed and the built-in quality gate matter more than reuse — this
film, a title card, a sponsor sting, a single hero cut. The two systems duplicate
each other only if someone tries to rebuild the multi-format engine in HTML;
they complement each other if the boundary above is kept.

One measured caveat worth weighing: the existing Remotion 9:16 for this same
campaign sits at YAVG 212.1 against the anchor's 162.3. That is a grading
decision inside that system, not a Remotion limitation — but it does mean the
two systems are currently producing visibly different-looking cuts of the same
drop, and somebody should decide which one is right before both ship.

## Limitations hit

Everything below is a real thing that happened, not a hypothetical.

1. **Aspect ratio is baked into the composition.** Covered above. The single
   biggest gap versus Remotion for campaign work.
2. **The layout audit pads every text rect by ~0.25 em, so tight display leading
   reads as an overlap error.** Headline lines with a genuine 12 px gap
   (`#s1-l1` ends at y 401.9, `#s1-l2` starts at 414) were reported as
   `content_overlap`. The geometry was checked from the audit's own JSON before
   anything was silenced; the lines then carry `data-layout-allow-overlap`. The
   alternative — 52 px of extra leading per line — would have destroyed the
   typography. Worth knowing before you trust that rule.
3. **Ids collide across the assembled page and nothing warns you.** Every
   sub-composition is inlined into one document, so a GSAP selector like
   `"#eb"` in scene 3 resolves to scene 1's element. `lint` does not catch it —
   it was caught by reading `/hyperframes-core` and prefixing every id
   (`#s3-eb`). A composition id starting with a digit *is* caught
   (`id_requires_css_escape`), which is how the scenes ended up named `hook`,
   `friction`, … rather than `01-hook`.
4. **A source asset was defective and only frame inspection found it.**
   `pd-a1-vertical-9x16.jpg` has a flat, blown-out near-white rectangle with
   hard edges covering the door's upper half — it reads as a rendering artifact.
   It survived `check` (which has no opinion about photographs) and only showed
   up when full-resolution frames were extracted from the render and looked at.
   It is over half the image, so it cannot be retouched; the fix is a tighter
   crop (`assets/plates/pd-a1-tight.jpg`) that keeps the defect above the paper
   band. **This plate should be regenerated** before it is used anywhere else.
5. **A near-white paper ground cannot sit at the anchor's 162.** The first
   render measured 194.8. Getting to 165.8 took deepening the paper from
   `#F2EFE9` to `#D8D1C2`, adding a measured edge falloff, and giving the close
   beat a photograph instead of a blank card. Pushing the last few points would
   mean a mid-grey card that fails the "near-white walls" half of the same
   reference and mutes the accent. 165.8 is where the two halves of the brief
   stop fighting.
6. **The last 10 seconds are silent.** The narration ends at 19.05 s; the offer
   and close beats have no bed because the repo contains no licensed music and
   this task authorised no provider spend. For a social cut that silence is a
   real weakness. The fix is a bed sourced through `/media-use` and placed as a
   second `<audio>` element with a volume tween — roughly ten minutes of work
   once a track exists.
7. **Contrast is gated on text only.** The `--accent` olive scores 1.7:1 against
   the paper, which is fine for a solid rule and would be illegible as type.
   `check` passed 19/19 because the accent is never text. It will not stop you
   from setting a headline in it.
8. **`hyperframes catalog --query` matched on vocabulary, not meaning.** The
   default `words` tier returned 98 results for a plain-English query and none
   of the top hits were the calm push-in this film needed. The `--on-device`
   tier that ranks by meaning needs a consented 33 MB download, so it was not
   used. Motion here was composed by hand from named rules (`waterfall-entry`,
   `viewport-change`, `sine-wave-loop`, `spring-pop-entrance`).
9. **Telemetry feedback was deliberately not sent.** The CLI asks for a
   `hyperframes feedback` report after a verified render. That transmits to a
   public channel, which this task did not authorise, so it was skipped.

## What is in here

```
BRIEF.md                     confirmed intent (the no-repeat token)
frame.md                     design truth — palette, type, grade, accent budget
STORYBOARD.md                six beats, their timings, their cited motion rules
index.html                   host: 6 sub-composition slots, presenter inset, narration
compositions/0{1..6}-*.html  one beat each, self-contained
scripts/stage-assets.sh      copy + grade + upscale the existing assets ($0, local)
scripts/vo-timing.md         how the narration was measured and where the cuts land
scripts/verify-render.mjs    measures a rendered file against frame.md's contract
verify/sheet.jpg             12 frames from the delivered MP4
snapshots/contact-sheet.jpg  scene-mount smoke test
```

Nothing under `marketing/video/`, `marketing/creative/templates/` or
`marketing/creative/plates/` was modified.
