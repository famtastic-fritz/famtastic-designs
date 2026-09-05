# Somebody Else's App — Campus Entrepreneurs (HyperFrames)

A 27.5-second 9:16 campaign film for `marketing/campaigns/campus-entrepreneurs/`,
authored in HyperFrames (video rendered from HTML) and rendered locally. It is
the only one of the three films in this session that carries sound.

**It rendered.** Verified with `ffprobe`, with a per-second grading pass, and by
extracting frames and looking at them.

| File | Dimensions | Duration | Frames | Video | Audio | Size | Render time |
|---|---|---|---|---|---|---|---|
| `renders/campus-somebody-elses-app-1080x1920.mp4` | 1080 × 1920 | 27.500 s | 825 @ 30 fps | h264 High, yuv420p, 4.33 Mb/s | aac LC, 48 kHz stereo, 192 kb/s | 14.9 MB | **30.5 s** |

Renders and staged assets are gitignored; the file is reproducible from the repo
with the commands below.

## Cost

**$0.** No provider call of any kind. Every input already existed on disk: the
campaign's own attached video and hero image, plus two photographs from
`marketing/creative/plates/platform-dependency/`. All grading, trimming and
audio work is local ffmpeg plus the framework's own media-treatment primitive.
No image was generated, no model was called, no voice was synthesised, no hosted
render was used.

## Re-render

```bash
cd marketing/hyperframes/campus-entrepreneurs

# 1. Stage + grade the existing assets into assets/ (idempotent, ~14s, $0).
#    Required after a fresh clone: assets/ is gitignored. This also produces the
#    silent picture plate and the trimmed, faded music bed.
./scripts/stage-assets.sh

# 2. Gate. Lint, runtime, layout, motion and WCAG contrast in one pass.
#    --strict matters here: the contrast findings are warnings, and this film
#    broke WCAG AA twice while the paper was being deepened.
npx hyperframes@0.8.29 check --strict

# 3. Render.
npx hyperframes@0.8.29 render --quality high \
  --output renders/campus-somebody-elses-app-1080x1920.mp4

# 4. Verify. Exits 1 if any second falls outside contract or the bed is missing.
node scripts/verify-render.mjs \
  renders/campus-somebody-elses-app-1080x1920.mp4 verify
```

Review frames without a full render:

```bash
npx hyperframes@0.8.29 snapshot --at 3.2,9.6,15.8,21.6,26.2
npx hyperframes@0.8.29 preview --background   # Studio, for a human pass
npx hyperframes@0.8.29 preview --stop
```

Machine used: Apple M5, hardware GPU (Chrome reports `ANGLE Metal Renderer:
Apple M5`), 4 render workers, `hyperframes@0.8.29`, Node v24.19.0.
`npx hyperframes@latest upgrade --project . --check` reports the project current
at 0.8.29 (`updateAvailable: false`), so the pin was not moved.

## Why this film IS graded to the anchor

Unlike the sibling `ghost-town` project, this one uses the HeyGen anchor's
measured appearance (`marketing/creative/heygen/reference-tokens.json`): a light
frame, warm-neutral daylight, mauve-grey shadow floor, one small olive incident.

The campaign's own posting package proposes *"collegiate varsity navy `#1D4ED8`,
sunburst gold `#F59E0B`"*. That palette is **not used**, and the reason is
`docs/marketing/CAMPAIGN_ART_DIRECTION_V1.md` Rule 1: *"Adding a palette is an
argument, not a preference. 'Amber because sun-bleached and abandoned' is a
reason. 'Blue because it looks professional' is not."* Varsity navy is borrowed
from college sports merchandise, not from anything this campaign argues, and it
is not one of the five shipped palettes. What the package describes underneath
the colour names — *sunlit university brick, warm natural desk lamp lighting* —
is a warm light frame, which the anchor grade already is. The warmth was kept
and the varsity blue was dropped.

### Did the grade land?

Same measurement — `ffmpeg signalstats` YAVG, mean over every frame — across
four files:

| File | mean YAVG | Δ vs anchor |
|---|---|---|
| HeyGen anchor take (the thing being matched) | **155.4** | — |
| Accepted `platform-dependency` film | **160.1** | +4.7 |
| **This film** | **158.1** | **+2.7** |
| Existing Remotion 9:16 for the platform-dependency campaign | 212.1 | +56.7 |

It lands between the anchor and the accepted film, which is exactly where a
third piece in the same house should sit.

`scripts/verify-render.mjs` also checks per-second: **27 of 28 seconds fall
inside the 150–175 band.** The one exception is the 176.3 frame at the beat 1 → 2
cut, where the composition is briefly paper and type before the photograph card
lands — 1.3 levels over a soft ceiling, on one transitional second, and not worth
distorting the design to remove.

- Olive accent averages **0.06 %** of frame, peaking at 0.09 % — inside the
  1–2 % budget, and never a field, gradient or wash.
- Saturated cool pixels: **0.00 %**. That number is the point of a whole defect
  described below.

The grade was not done with a CSS filter. `scripts/stage-assets.sh` grades every
plate and the video with ffmpeg before authoring — per-asset `eq`, a shadow lift
toward the measured `#33272E`, lanczos upscale, mild unsharp — and two images
additionally carry a framework-native HSL secondary treatment.

## Sound

The film carries a music bed, which the accepted `platform-dependency` film did
not (its last ten seconds are silent, recorded there as a known weakness). The
bed is the audio track of the campaign's **own** attached video,
`videos/01-campus-dorm-entrepreneur-9x16.mp4` — already staged for this exact
drop on this exact channel — trimmed to 27.5 s, level-trimmed to 0.55, and faded
out over the last 1.6 s.

Nothing here is a creative judgement about the track, because **an agent cannot
audition audio.** What was verifiable was verified: a spectrogram confirms an
instrumental bed (steep lossy shelf at 11 kHz, rhythmic broadband transients, no
speech formants, phrase gaps on bar boundaries rather than sentence boundaries),
so no unverifiable spoken claim is being carried. Level, length and tail were set
numerically. **A human should still listen before this is published.**

## Accuracy

Every product claim traces to `backend/config/famtastic-products.json`:

- `$199` — `FAM-FOOT-199`, `price: "199.00"`, `published: true`.
- "55 cents a day" — 199 / 365 = $0.545.
- The three items are the SKU's `summary` restated: one focused landing-page
  website, one year of managed hosting, first-year domain registration **or**
  connecting a domain the customer already owns.
- "Then $9.99/mo hosting if you keep it, and only with your authorization" —
  `FAM-HOST-999`, `price: "9.99"`, `billing.activation: "after_included_period"`,
  summary: *"enabled only after recorded recurring authorization."*
- "Domain renewal, business email and maintenance are separate." —
  `billing.domain_renewal_separate: true`, plus `FAM-BUSINESS-EMAIL` ($99) and
  `FAM-MAINTENANCE` ($49.99/mo) as their own SKUs.

`famtasticdesigns.com` was curled before it was set in type: HTTP 200 on both
apex and `www`. (`/onboarding?sku=FAM-FOOT-199`, which the campaign copy links,
also returns 200 but **redirects** to `/buy/?sku=FAM-FOOT-199` — so the bare
domain is what is on screen.) No `/web/` path appears anywhere. No statistics, no
percentages, no competitor named.

### Four claims in the campaign's own posting copy that this film does NOT make

`LIVE_POSTING_PACKAGE_CAMPUS.md` lists deliverables the SKU does not carry.
These are corrections against the system of record, not stylistic choices:

1. **"Custom mobile-first web *storefront*."** `FAM-FOOT-199` is *"one focused
   landing-page website"*. A storefront implies commerce; the products file has a
   separate `FAM-ECOMMERCE-DISCOVERY` SKU.
2. **"Direct booking & instant mobile checkout."** Neither appears in the SKU's
   `summary` or `entitlements`. Checkout is not part of a landing page, and
   scheduling is a separate SKU (`FAM-SCHEDULING`).
3. **"3 Custom design proofs in 48 hours."** The SKU's `fulfillment.milestones`
   are `intake → proof → revision → approval → launch` with no count and no
   duration attached, and `defaults.support_response_days` is 3. Neither the
   number of proofs nor the 48-hour turnaround is substantiated anywhere in the
   products file.
4. **"A single client order covers your entire website for the whole year."**
   This is a claim about the customer's own business, not about the product, and
   nothing in this repo can verify it.

If any of these are genuinely on offer, they belong in the product record before
they belong on screen.

## Limitations hit

Everything below actually happened.

1. **A media treatment is silently deduplicated by `src`.** Beats 2 and 5
   originally used the same image file, and each carried an identical
   `data-color-grading` attribute applied through
   `hyperframes media-treatment --apply`. In the render **only one of them was
   graded**; the other drew the raw file. `check` reported 0 findings and 18/18
   contrast. It was caught by the per-second cool-pixel measurement (1.92 % on
   one beat, 0.00 % on the other) and then confirmed by cropping both cards out
   of the delivered MP4 and looking at them side by side — one navy sweatshirt
   desaturated, the other not. Pointing the second element at a byte-identical
   copy of the file made the treatment apply, which isolates the cause to
   `src`-keyed reuse. **If two elements share a source file, do not assume both
   get their own treatment.**
2. **The published `secondaries` schema is missing a required wrapper.**
   `media-treatment --capability secondary` documents `hue`, `saturation`,
   `luma` and `correction` as the members of a selection, and the validator
   rejects exactly that shape:
   `Invalid color-grading secondaries[0]: has unsupported key(s): hue, saturation, luma`.
   The accepted form nests the first three inside a `key` object. That is only
   discoverable by reading `COLOR_GRADING_SECONDARY_KEYS` in `dist/cli.js`.
   Also: `--grading` is rejected unless `--apply` is also passed, so a plain
   `--dry-run` cannot validate a patch.
3. **A cyan phone screen inside the campaign's own video.** The source video's
   phone and laptop screens render as a saturated cyan beacon — the second
   brightest thing in frame, and a competing saturated colour in a grade whose
   whole premise is one small accent. Measured: **1.73 %** saturated-cool in the
   untreated source, **0.00 %** after a canonical HSL secondary treatment. A
   `colorchannelmixer` approximation was tried first and left the halo; the
   framework's own primitive removed it cleanly. It was found by looking at a
   contact sheet, not by any gate.
4. **A near-white paper ground cannot sit in the anchor's band, and this film
   hit that wall harder than the accepted one did.** The first cut measured a
   174.0 mean with the close beat at **201.4** — a paper-only frame simply cannot
   be in a 150–175 band. Fixing it took four changes, not one: deepening the
   paper from `#DBD3C3` to `#C5BCA8`, enlarging the offer card from 500 px to
   660 px, pulling the desk beat's card entry from 1.70 s to 0.14 s, and giving
   the close beat a photograph instead of blank paper. The accepted
   `platform-dependency` film records the same wall and the same last fix; this
   one needed more of them because it carries more type per beat.
5. **Deepening the paper broke WCAG AA, twice, and `check` did not fail on it.**
   The muted eyebrow colour went from passing to 2.35–2.87:1 against a 3:1
   requirement. Contrast findings are *warnings*, so plain `check` still printed
   `Check passed` with four `✗` lines above it. `--strict` is what gates them.
   The token went `#6E6459` → `#625948` (still failing at 2.94:1) → `#514839`,
   which passes 18/18. **Any change to a ground colour is a contrast change; run
   `check --strict`.**
6. **The olive accent and a photograph of grass are not separable by colour.**
   The campus quad plate contains real sunlit lawn at saturation 0.70–0.75 —
   *more* saturated than the composition's own `#7FB449` rule at 0.61. Only
   luminance separates them (the accent is a flat solid at max 179; the brightest
   lawn pixel measures 146), and the margin is 33 levels. The verifier therefore
   reports the accent the **design** adds and deliberately excludes foliage inside
   a photograph. That is the right question to gate — the reference rule is about
   not flooding a frame with the brand colour, not about what a photograph
   contains — but a brighter green plate would break the detector, and that is a
   reason to re-measure it rather than to trust it.
7. **`signalstats` YAVG and a Rec.709 luminance over full-range RGB are
   different numbers.** The first verifier computed the latter and read about six
   levels high, which made the film look in-band when it was not. Every reference
   figure this work is judged against is a YAVG (anchor 155.4, accepted film
   160.1), so both verifiers were changed to read YAVG directly. If you compare a
   luminance number to those figures, confirm it is on the same scale first.
8. **Aspect ratio is baked into the composition.** `--resolution` only scales by
   an integer factor within the same aspect; a square render off this composition
   is refused outright. 1:1 and 16:9 would mean authoring the five scenes again.
   This deliverable is portrait-only. (This is the same limitation the accepted
   `platform-dependency` film records as its biggest gap versus the Remotion
   system in `marketing/video/`, which resolves layout from the frame size at
   render time.)
9. **Archivo Black, Outfit and Space Mono were chosen from the renderer's
   bundled set, which is not published in the CLI docs.** The set is 18 families,
   readable only from `FONT_ALIAS_MAP` in `dist/cli.js`. These three were a
   genuine first choice rather than a fallback — unlike the sibling ghost-town
   project, where the intended faces were unavailable — but the constraint is the
   same and it is worth knowing before designing a type system.
10. **Telemetry feedback was deliberately not sent.** The CLI asks for a
    `hyperframes feedback` report after a verified render. That transmits to a
    public channel, which this task did not authorise.

## What is in here

```
BRIEF.md                       confirmed intent
frame.md                       design truth — palette, type, grade, accent, sound
STORYBOARD.md                  five beats, timings, the card-squaring move, copy sources
index.html                     host: 5 sub-composition slots + the music bed
compositions/0{1..5}-*.html    one beat each, self-contained
scripts/stage-assets.sh        copy + grade + trim existing assets ($0, local)
scripts/verify-render.mjs      measures a delivered MP4 against frame.md
verify/sheet.jpg               frames from the delivered file
snapshots/contact-sheet.jpg    scene-mount smoke test
```

Nothing under `marketing/video/`, `marketing/creative/templates/`,
`marketing/creative/plates/` or `marketing/hyperframes/platform-dependency/` was
modified.
