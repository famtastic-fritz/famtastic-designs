# The Sign That Isn't There — Ghost Town Ep.1 (HyperFrames)

Two 9:16 campaign films for `marketing/campaigns/ghost-town-ep1/`, authored in
HyperFrames (video rendered from HTML) and rendered locally.

**They rendered.** Both verified with `ffprobe`, with a per-second grading pass,
and by extracting frames and looking at them.

| File | Dimensions | Duration | Frames | Video | Audio | Size | Render time |
|---|---|---|---|---|---|---|---|
| `renders/ghost-town-sign-1080x1920.mp4` | 1080 × 1920 | 44.000 s | 1320 @ 30 fps | h264 High, yuv420p, 3.86 Mb/s | **none** | 20.3 MB | **56.6 s** |
| `shorts/dm-trap/renders/ghost-town-dm-trap-1080x1920.mp4` | 1080 × 1920 | 15.000 s | 450 @ 30 fps | h264 High, yuv420p, 3.39 Mb/s | **none** | 6.1 MB | **15.7 s** |

Renders and staged assets are gitignored; both files are reproducible from the
repo with the commands below.

## Cost

**$0.** No provider call of any kind. Every input already existed on disk before
this project: six photographs from `marketing/creative/plates/platform-dependency/`,
re-graded locally with ffmpeg. No image was generated, no model was called, no
voice was synthesised, no hosted render was used. HyperFrames runs free locally;
only its optional `cloud render` path costs money and it was not used.

## Re-render

```bash
cd marketing/hyperframes/ghost-town

# 1. Stage + grade the existing plates into assets/ (idempotent, ~1s, $0).
#    Required after a fresh clone: assets/ is gitignored. This also seeds the
#    short cut's own assets/ directory.
./scripts/stage-assets.sh

# 2. Gate. Lint, runtime, layout, motion and WCAG contrast in one pass.
npx hyperframes@0.8.29 check --strict

# 3. Render the 44s master.
npx hyperframes@0.8.29 render --quality high \
  --output renders/ghost-town-sign-1080x1920.mp4

# 4. Verify. Exits 1 if any second falls outside the grading contract.
node scripts/verify-render.mjs renders/ghost-town-sign-1080x1920.mp4 verify

# 5. The 15s short cut is its OWN project (see Limitations #1).
cd shorts/dm-trap
npx hyperframes@0.8.29 check --strict
npx hyperframes@0.8.29 render --quality high \
  --output renders/ghost-town-dm-trap-1080x1920.mp4
node verify-render.mjs renders/ghost-town-dm-trap-1080x1920.mp4 verify
```

Review frames without a full render:

```bash
npx hyperframes@0.8.29 snapshot --at 3.6,11.2,19.0,26.8,34.6,41.2
npx hyperframes@0.8.29 preview --background   # Studio, for a human pass
npx hyperframes@0.8.29 preview --stop
```

Machine used: Apple M5, hardware GPU (Chrome reports `ANGLE Metal Renderer:
Apple M5`), 4 render workers, `hyperframes@0.8.29`, Node v24.19.0.
`npx hyperframes@latest upgrade --project . --check` reports the project current
at 0.8.29 (`updateAvailable: false`), so the pin was not moved.

## Why this film is NOT graded to the anchor

This is the deliberate divergence, and it is the first thing to check before
anyone reads the luminance numbers as a defect.

`docs/marketing/CAMPAIGN_ART_DIRECTION_V1.md` Rule 1 says the black-and-lime site
identity is **the site**, not every campaign, and it ships five palettes chosen
per campaign from what the campaign argues. Ghost Town's own entry is:

> `ghost-town` — `#17120d` / `#d9a441` — Amber dust on dark earth. A business
> that exists but cannot be found: heat, absence, weathering.

The `prompt-library.json` clause for the same palette is explicit that **"No
blue, no green, no neon, no cool light anywhere."** The HeyGen anchor grade,
meanwhile, is a *light* frame with an *olive-green* accent. The two are not
reconcilable, and the anchor grade governs assets that must cut against
`take-a-platform-dependency.mp4` — which these do not. They are a different
campaign, a different audience, and a different argument.

So this film follows the **campaign palette**, and the numbers are what a dark
earth ground produces:

| File | measured YAVG (ffmpeg `signalstats`) |
|---|---|
| HeyGen anchor take | **155.4** |
| Accepted `platform-dependency` film | **160.1** |
| **This master cut** | **54.3** |
| **This short cut** | **52.1** |

That gap is the palette, not a grading error. The film's own contract is a band
of **30–86**, and it is *derived*, not chosen: the palette ground `#17120D`
encodes to Y' ≈ 32, the graded plates measure 44.8–107.7, and every frame is a
mix of the two. `scripts/verify-render.mjs` checks each second against that band
and prints the 155.4 / 160.1 figures alongside every run so the divergence can
never be misread as a measurement.

**Result: 0 of 44 seconds and 0 of 15 seconds fall outside contract.** Amber
accent averages 0.01 % of frame on the master and 1.53 % on the short (the
bedside lamp itself is the short's single warm incident, which is exactly what
the palette asks for). Cool pixels: **0.00 %** on both — the palette forbids cool
light and the grade removed it, including from a source plate shot at blue hour.

The grade was not done with a CSS filter. `scripts/stage-assets.sh` grades each
plate with ffmpeg before authoring: a warm colour balance, a per-plate
`colortemperature` pull, a `colorlevels` floor that sets the darkest pixel of
every plate to literally `#17120D`, a highlight cap so nothing clips to white,
lanczos upscale, mild unsharp. The compositions add a radial edge falloff in the
same measured earth, because a real surface is not a flat value.

## Accuracy

Every product claim traces to `backend/config/famtastic-products.json`:

- `$199` — `FAM-FOOT-199`, `price: "199.00"`, `published: true`.
- "55 cents a day" — 199 / 365 = $0.545.
- The three included items are the SKU's `summary` restated: one focused
  landing-page website, one year of managed hosting, first-year domain
  registration **or** connecting a domain the customer already owns.
- "Then $9.99/mo hosting if you keep it, and only with your authorization" —
  `FAM-HOST-999`, `price: "9.99"`, `billing.activation: "after_included_period"`,
  summary: *"enabled only after recorded recurring authorization."*
- "Domain renewal, business email and maintenance are separate." — the SKU's
  `billing.domain_renewal_separate: true`, plus `FAM-BUSINESS-EMAIL` ($99) and
  `FAM-MAINTENANCE` ($49.99/mo), which are their own SKUs.

`famtasticdesigns.com` was curled before it was set in type: HTTP 200 on both
apex and `www`, title `FAMtastic Designs | Agentic AI Business Solutions
Engineering Studio`. No `/web/` path appears anywhere. No statistics, no
percentages, no competitor named — the hook is a live demonstration ("search it
yourself"), which is what the campaign's own production notes require.

### Two claims in the campaign's own source copy that these films do NOT make

Both are corrections against the backend, not stylistic choices:

1. **"A full first year of hosting *and a branded email address* included."**
   — `articles/why-independent-stylists-are-invisible-outside-the-app.md`.
   This is wrong. `FAM-BUSINESS-EMAIL` is a separate $99 SKU and is not in
   `FAM-FOOT-199`'s `summary` or `entitlements`. The films say the opposite, on
   screen, in the offer beat. **The article should be corrected.**
2. **"Built and live in 48 hours" / "see your 3 design directions in 48 hours."**
   — `video-scripts/EP1_FULL_SCRIPT.md`. There is no 48-hour SLA anywhere in
   `famtastic-products.json`; the SKU's `fulfillment.milestones` are
   `intake → proof → revision → approval → launch` with no duration attached, and
   `defaults.support_response_days` is 3. A delivery promise that the system of
   record cannot substantiate is not put on screen. If the 48-hour turnaround is
   real, it belongs in the product record first.

## How the cut was timed

There is no voiceover, so nothing was measured off an audio envelope. The
script's own beat timings (0–6, 6–16, 16–28, 28–42, 42–52, 52–58) are **narration**
timings for a 58s voiced cut; holding a silent frame for ten seconds is dead air,
not pacing. The six beats are re-timed to reading speed at 44s total, and that
re-timing is the only place the cut departs from the script. The argument, its
order, and its wording are the script's. Beat boundaries and copy sources are
tabulated in `STORYBOARD.md`.

## Limitations hit

Everything below actually happened.

1. **`check` takes a project directory and has no per-composition flag.** A
   standalone composition beside `index.html` can be rendered
   (`render -c shorts/dm-trap.html`) but never *gated* — `check --composition`
   is rejected as an unknown flag, and plain `check` only ever compiles
   `index.html`. The 15s short cut is therefore its own nested project under
   `shorts/dm-trap/`, with its own `package.json`, `meta.json`,
   `hyperframes.json` and a copied plate. The parent project's `check` is
   unaffected by the nested one. This is the single biggest friction in shipping
   a master cut plus short cuts, which is the normal shape of a social campaign.
2. **Anton and Barlow are not in the renderer's bundled font set, and nothing
   says so until `check` fails.** The set is 18 families
   (`inter, montserrat, outfit, nunito, oswald, league gothic, archivo black,
   space mono, ibm plex mono, jetbrains mono, eb garamond, playfair display,
   source code pro, noto sans jp, roboto, open sans, lato, poppins`) and it is
   not published in the CLI docs — it was read out of `dist/cli.js`
   (`FONT_ALIAS_MAP`). The film's display face became **League Gothic** and its
   support face **Roboto**. The substitution is defensible (League Gothic is a
   condensed poster face, which is what the film's shopfront subject wants), but
   it was a substitution, not a choice, and the same constraint is already
   recorded as a known gap in `docs/marketing/CAMPAIGN_ART_DIRECTION_V1.md`.
3. **A guessed accent detector reported a defect that did not exist.** The first
   version of `verify-render.mjs` used plausible-looking amber thresholds and
   reported 5–25 % amber coverage against a 3 % budget — 44 of 44 seconds
   "failing". The film was correct; the detector was measuring "warm and bright",
   which describes the entire palette. It was fixed by sampling actual pixels out
   of the delivered file (the shaft is `(212,162,65)`, sat 0.69; the worst false
   positive is sunlit timber at `(154,115,60)`, sat 0.61) and setting the floors
   between them. Reported amber then fell to 0.01 %. **This is the third
   recorded instance in this repo of an automated colour detector producing a
   false failure** (see `marketing/creative/plates/prompt-library.json`
   `defect_log`); do not trust one that was not calibrated against measured
   pixels from the file it is judging.
4. **A frame-space accent aligned to a photograph drifts under a camera push.**
   Beat 4's amber shaft was authored in frame coordinates on top of a plate that
   pushes 1.00 → 1.05 across the beat, so it lined up with the blank card's edge
   for exactly one instant. Moving the shaft *inside* the camera wrapper fixed
   it. Caught by extracting the beat's last frame, not by any gate.
5. **The offer beat's photograph was cropped onto the wrong part of the plate.**
   The first crop put two thirds of the card on a closed drawer front and
   squeezed the blank index cards into a strip. `check` has no opinion about what
   a photograph contains; it was found by rendering a frame and looking at it.
6. **Both films are silent.** There is no licensed music in the repo for this
   campaign, and the one bed that exists nearby belongs to a different campaign
   with a different tone. More to the point: **an agent cannot audition audio**,
   so choosing a track on tonal fit is not something this session could honestly
   do. The campus film in the sibling project carries a bed only because that
   campaign already ships one attached to that exact drop. For a 44-second social
   cut, silence is a real weakness. The fix is a bed sourced through `/media-use`
   and placed as an `<audio>` element — about ten minutes' work once a licensed
   track exists and a human has heard it.
7. **`plate-08-ghost-hook-square-1x1.jpg` is labelled for this campaign and is
   unusable for it.** It is a 1:1 near-black shopfront lit by a full-bleed lime
   window with a translucent ghost figure. It is in the `famtastic` site palette,
   not the `ghost-town` palette the same library assigns to this campaign, and
   the lime is a *field* rather than the single small incident the accent rules
   require. It is also square, and this deliverable is 9:16. **It should be
   regenerated in the ghost-town palette** before anyone reaches for it.
8. **`marketing/campaigns/ghost-town-ep1/images/` is empty**, and the
   `creative_assets` entries in its `manifest.json` are both
   `"status": "not_yet_created"`. Every plate in these films is therefore
   borrowed from the platform-dependency plate set and re-graded. They are
   photographs of blank surfaces, which suits the argument exactly, but this
   campaign has no photography of its own subject (a salon, a chair, a stylist's
   own work) and that is a real gap for a series that is supposed to run.
9. **The finished ad creatives in the sibling campaign cannot be used as
   plates.** `marketing/campaigns/cost-is-not-the-reason/images/01-hair-beauty-booksy-escape-ad.jpg`
   and `04-nail-salon-...` are the right audience and the right subject, but they
   carry baked-in type — including **"STOP PAYING 20% APP FEES"**, a percentage
   with no source in this repo, which this campaign's own production notes
   forbid. They were excluded on sight.
10. **A single lint error silently disables the layout and contrast audits.**
    The first `check` run reported `0 issues across 0 sample(s)` and
    `0/0 text checks pass WCAG AA` next to 7 font errors. That reads like a clean
    file. It means nothing ran. Clear lint errors before believing any other
    number in that report.
11. **Telemetry feedback was deliberately not sent.** The CLI asks for a
    `hyperframes feedback` report after a verified render. That transmits to a
    public channel, which this task did not authorise.

## What is in here

```
BRIEF.md                       confirmed intent
frame.md                       design truth — palette, type, grade, accent budget
STORYBOARD.md                  six beats + the short cut, timings, copy sources
index.html                     host: 6 sub-composition slots
compositions/0{1..6}-*.html    one beat each, self-contained
shorts/dm-trap/                the 15s short cut, as its own project (see #1)
scripts/stage-assets.sh        copy + grade + upscale existing plates ($0, local)
scripts/verify-render.mjs      measures a delivered MP4 against frame.md
verify/sheet.jpg               frames from the delivered master
snapshots/contact-sheet.jpg    scene-mount smoke test
```

Nothing under `marketing/video/`, `marketing/creative/templates/`,
`marketing/creative/plates/` or `marketing/hyperframes/platform-dependency/` was
modified.
