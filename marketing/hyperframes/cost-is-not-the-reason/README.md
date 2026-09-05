# Fifty-Five Cents — Cost Is Not The Reason (HyperFrames)

A 28-second 9:16 campaign film for the live `cost-is-not-the-reason` drop,
authored in HyperFrames (video rendered from HTML) and rendered locally.

**It rendered.** Verified with `ffprobe`, with a per-second grading measurement,
and by extracting frames and looking at them:

| File | Dimensions | Duration | Frames | Video | Audio | Size | Render time |
|---|---|---|---|---|---|---|---|
| `renders/fifty-five-cents-1080x1920.mp4` | 1080 × 1920 | 28.000 s | 840 @ 30 fps | h264 High, yuv420p | aac LC, 48 kHz stereo | 23.3 MB | **32.1 s** |

Renders and staged media are gitignored; both are reproducible from the repo
with the commands below.

## Cost

**$0.** No provider call of any kind was made by this project — no image, no
voice, no video, no model. Every input already existed on disk: the campaign's
own bakery image, the platform-dependency anchor, and ten seconds of the
campaign's own commercial. All processing is local (ffmpeg for the grade,
Chrome + ffmpeg for the render). HyperFrames is free to run locally; its
optional hosted `cloud render` path costs money and was not used.

## Re-render

```bash
cd marketing/hyperframes/cost-is-not-the-reason

# 1. Stage + grade the existing assets into assets/ (idempotent, ~3 s, $0).
#    Required after a fresh clone: assets/ is gitignored.
./scripts/stage-assets.sh

# 2. Gate. Lint, runtime, layout, motion and WCAG contrast in one pass.
npx hyperframes@0.8.29 check

# 3. Render.
npx hyperframes@0.8.29 render --quality high \
  --output renders/fifty-five-cents-1080x1920.mp4

# 4. Verify. Exits 1 if any second falls outside the grading contract.
node scripts/verify-render.mjs renders/fifty-five-cents-1080x1920.mp4 verify
```

Review frames without a full render:

```bash
npx hyperframes@0.8.29 snapshot --at 2.6,8.6,15.0,22.0,26.5
npx hyperframes@0.8.29 preview --background   # Studio, for a human pass
npx hyperframes@0.8.29 preview --stop
```

Machine: Apple M5, hardware GPU (Chrome reports `ANGLE Metal Renderer: Apple
M5`), 4 render workers, `hyperframes@0.8.29` (checked against
`npx hyperframes@latest --version` — 0.8.29 is current, no pin bump needed),
Node v24.19.0, ffmpeg 9.0.

## Did the grade land?

The film has to cut against
`marketing/creative/heygen/renders/take-a-platform-dependency.mp4`, so it is
graded to that take's **measured** appearance
(`marketing/creative/heygen/reference-tokens.json`), not to the brand spec.

Same command across four files — `ffmpeg -vf "signalstats,metadata=print:key=lavfi.signalstats.YAVG"`,
mean over every frame:

| File | mean YAVG | Δ vs anchor |
|---|---|---|
| HeyGen anchor take (the thing being matched) | **155.4** | — |
| Accepted `platform-dependency` film | 160.1 | +4.7 |
| **This film** | **160.8** | **+5.4** |
| Existing Remotion 9:16 for the same campaign | 212.1 | +56.7 |

`scripts/verify-render.mjs` also checks per-second: **0 of 28 seconds fall
outside** the 150–175 band, and the olive accent (`#7FB449`) averages **0.28 %**
of frame area — inside the 1–2 % budget, and never a field, gradient or wash.

The grade was not achieved with a CSS filter. `scripts/stage-assets.sh` grades
each plate with ffmpeg before authoring — per-plate `eq`, a shadow lift toward
the measured `#33272E`, lanczos upscale, mild unsharp — and the compositions add
a radial edge falloff in the same measured shadow colour, because a real paper
surface is not a flat value.

### A measurement trap worth recording

The first version of `verify-render.mjs` computed Rec.709 luma from decoded RGB
and gated it against the 150–175 band. **Those are two different scales.**
`signalstats` reads the decoded Y plane; the RGB conversion lands 6–8 points
higher on the same file. The RGB version failed 16 of 28 seconds on the
`local-seo` film, which measures 163.1 by the contract's own command — a gate
failing good work, which is worse than no gate. The script now gates on
`signalstats` and prints the RGB figure clearly labelled as reference only.
Both numbers already existed in this repo (`reference-tokens.json` uses its own
RGB sampling and reports the anchor at 161.9; the same take measures 155.4 by
`signalstats`), so this will bite again if it is not remembered.

## Palette — `paper`, and why not `famtastic`

`marketing/creative/plates/prompt-library.json` files this exact topic
(`cinr-55c`, "two quarters and a nickel, coins on black, lit once") under the
`famtastic` palette. That palette **cannot be used here**, and the reason
matters more than the workaround:

`famtastic` is `#070907` — a near-black ground. This film has to sit beside a
premium anchor take measured at 155.4 mean luminance. A near-black ground
cannot reach the 150–175 band at all. The palette and the anchor contract are
in direct conflict for this subject.

`paper` is argued in the art direction as the palette for "proposals,
documents, LinkedIn — anything that must read sober rather than as an ad." That
is exactly this film's job. **The one thing that makes a low price look like a
trick is packaging it like an advertisement.** A price a viewer is invited to
check should look like a quote. `paper` is chosen from the subject — a document
— and it also happens to be one of only two shipped palettes whose ground can
hold the band.

Where `paper` and the measurement disagree, the measurement wins: `paper`'s
accent is `#1F6F4A` (deep pine); this film's accent is `#7FB449`, because
`reference-tokens.json` measured that as how the brand accent actually renders
under the anchor's light. A palette is an argument; a measurement is a fact.

## Where the doctrines collide

This is the most consequential thing the three films in this batch turned up,
and it is an owner decision, not a design one.

`docs/marketing/CAMPAIGN_ART_DIRECTION_V1.md` ships six palettes. Four of them
— `famtastic`, `ghost-town`, `salon`, `trades` — have near-black grounds.
`marketing/creative/heygen/reference-tokens.json` requires anything that cuts
against the campaign anchor to sit at 150–175 mean luminance.

**Only `paper` and `anchor-take-a` satisfy both.** Every campaign whose subject
argues for one of the other four must either abandon the palette or abandon the
anchor match. `prompt-library.json` assigns a dark palette to most of its
topics, so this is not a corner case — it is most of the library.

There is a second, quieter consequence. **The luminance contract disqualifies
most of the plates already on disk.** Measured mean luminance of every existing
plate:

| Plate group | Range | Usable at 150–175? |
|---|---|---|
| `plate-01..08-*` (superseded v1, black-and-lime) | 14.2 – 27.6 | no |
| `pd-b1-*`, `pd-c1-*`, `pd-c2-square/vertical` | 25.6 – 52.7 | no |
| `cost-is-not-the-reason` broll (5 images) | 21.8 – 30.8 | no |
| OpenArt masters 01–04 | 39.6 – 60.9 | no |
| `pd-a1`, `pd-a2`, `pd-b2`, `pd-p`, anchor, OpenArt 05 | 127.9 – 211.9 | yes |

Roughly six images in the whole library can carry an anchor-matched film, and
four of them were already used by `platform-dependency`. That is the binding
constraint on this kind of work, and it is fixable only by generating light
plates — which needs owner-authorised spend.

## Accuracy

Every product claim traces to `backend/config/famtastic-products.json` and to
the live page, which was curled before anything was set in type
(`https://famtasticdesigns.com/packages/199-quick-start` → HTTP 200;
`https://famtasticdesigns.com` and `https://www.famtasticdesigns.com` → HTTP 200):

- `$199` — `FAM-FOOT-199`, `price: "199.00"`, `published: true`.
- `$0.545` and `55¢` — 199 ÷ 365 = 0.5452, shown as the division rather than
  asserted.
- The three included items — the SKU's `summary`, restated.
- `$9.99 / month` from year two — `FAM-HOST-999`, the SKU's `renewal_sku`,
  `activation: after_included_period`.
- `Your domain renews separately` — `billing.domain_renewal_separate: true`.
- `Business email $99, one time` — `FAM-BUSINESS-EMAIL`.
- `Website maintenance $49.99 / month` — `FAM-MAINTENANCE`.

**The renewal disclosure is on screen.** `BRAND.md` requires it whenever
first-year pricing is mentioned ("$199 first year, then $9.99/mo, plus the cost
of the domain"). The existing campaign assets do not carry it; this film gives
it a beat of its own.

No statistic, no percentage, no named competitor, no delivery promise, no
ranking promise. No `/web/` path appears anywhere — note that
`marketing/campaigns/*/email-draft.md` all contain
`https://famtasticdesigns.com/web/packages?...`, which `BRAND.md` says 404s
publicly. Those drafts need fixing; nothing from them was copied here.

### The claim this film does NOT make

`marketing/campaigns/cost-is-not-the-reason/posting-schedule.json` contains
live scheduled drops stating the $199 bundle includes "a branded business email
address" (drop-01 `facebook_instagram`, and "hosting, domain, email" in drop-01
`x_post`/`tiktok`, drop-03 and drop-04). **That is not what
`famtastic-products.json` says**, and it is under owner review. Nothing in this
film states or implies it; beat 4 says the opposite in as many words. The
companion film `../services/business-email/` is the full correction.

### What was cut from the narration, and why

The narration is reused verbatim from the campaign's own
`videos/01-55-cent-myth-commercial-9x16.mp4`, **trimmed at 10.45 s**, carrying
two complete sentences:

> "Of all the reasons you do not have a professional website yet, cost is not
> one of them. At 55 cents a day, $199 for your entire first year,"

Both are supported by `famtastic-products.json` and by the live package page.
The next clause in the source is *"3 custom design directions in 48 hours"*,
which appears in **neither** — the SKU's `fulfillment.milestones` are
`intake, proof, revision, approval, launch` with no count and no turnaround, and
the live page lists no such deliverable. It is not repeated here. The source
carries a continuous bed, so the tail is faded over 0.6 s rather than cut.

Word timings came from `hyperframes transcribe` (69 words, whisper) run against
the source, so the trim point is measured rather than estimated.

## Limitations hit

Everything below is a real thing that happened, not a hypothetical.

1. **The film is silent from 10.45 s** — 17.5 of 28 seconds. There is no
   approved narration in the repo for the scope and renewal beats, no licensed
   music, and this task authorised no provider spend. For a social cut that is a
   real weakness. The fix is either a bed sourced through `/media-use` or a
   short approved VO covering the scope and renewal lines.
2. **The `¢` glyph collided with the accent marker under it.** Oswald's cent
   sign has a descending stroke that crossed the `268 × 18px` olive bar at
   y 1092. `check` passed — the layout audit has no opinion about glyph
   descenders — and it was caught by extracting the frame and zooming in. Fixed
   by dropping the marker and everything below it 16 px.
3. **The first cut put the sheet over the photograph's subject.** Beats 3 and 4
   originally sat on `pd-p-vertical` (a blank card in a brass holder), whose
   subject is at 63 % of frame height — entirely under the quote sheet. The
   visible strip was a blank pale wall. The plate was staged, looked at, and
   dropped; the film now holds one photograph across beats 1–4 with a single
   unbroken camera push, which is a better piece anyway.
4. **The anchor's two-card subject sits too low to clear a bottom-anchored
   sheet.** `pd-anchor-counter-16x9` has both blank cards at ~66 % of frame
   height. In the close the sheet starts at y 1100, so the cards are behind it
   even with the plate lifted to the top of its headroom; the counter reads as a
   place, not as the concept object. It is used properly, as an object, in the
   `business-email` film, where the composition is a card rather than a bleed.
5. **Aspect ratio is baked into the composition.** `--resolution` only scales by
   an integer factor within the same aspect; a 1:1 or 16:9 cut of this film
   means authoring the five scenes again. This is the known HyperFrames gap
   versus the Remotion system in `marketing/video/`, recorded in the
   `platform-dependency` README and unchanged.
6. **The first render measured 141.5 mean, below the band.** Getting to 160.8
   took brightening both plates (`eq` brightness −0.040 → +0.072 on the owner
   plate) and lightening the vignette from 0.14/0.42 to 0.09/0.28. The intuition
   that a warm paper ground would run bright was wrong once photographs covered
   most of the frame — measure, do not assume.
7. **`--muted` had to be darkened in the sibling film for a 0.04 contrast
   miss.** Not in this film, but the same token system: the gate reported
   2.96:1 against WCAG AA's 3:1 on a footnote sitting over the vignetted ground.
   Worth knowing that the vignette changes the effective background under
   bottom-of-frame text.
8. **Telemetry feedback was deliberately not sent.** The CLI asks for a
   `hyperframes feedback` report after a verified render. That transmits to a
   public channel, which this task did not authorise.

## What is in here

```
BRIEF.md                     confirmed intent
frame.md                     design truth — palette, type, grade, accent budget
STORYBOARD.md                five beats, their timings, their cited motion rules
index.html                   host: 5 sub-composition slots + narration
compositions/0{1..5}-*.html  one beat each, self-contained
scripts/stage-assets.sh      crop + grade + trim the existing assets ($0, local)
scripts/verify-render.mjs    measures a rendered file against frame.md's contract
verify/sheet.jpg             12 frames from the delivered MP4
snapshots/contact-sheet.jpg  scene-mount smoke test
```

Nothing under `marketing/video/`, `marketing/creative/templates/`,
`marketing/creative/plates/`, `marketing/hyperframes/platform-dependency/`,
`marketing/hyperframes/ghost-town/` or
`marketing/hyperframes/campus-entrepreneurs/` was modified.
