# Frame — I've Managed Fine

Design truth for the six films. Every value here is measured or argued; nothing
is a preference.

## Why this campaign looks the way it does

The audience is an established owner, usually older, who has run a good business
for years without a website and does not believe they need one. They are not
behind. They are pattern-matching on decades of evidence that word of mouth
worked — because it did.

That produces three hard design rules, and they outrank taste:

1. **No neon.** A black-and-lime tech poster reads as "this is for other
   people." The films are set on warm paper with real photographs of real
   objects — a sign bracket, a card-file drawer, a letter slot. Objects this
   viewer has touched.
2. **No urgency, no shame.** No countdown, no "still", no "finally", no "even
   you". The objection is stated first, in their words, in quotation marks, and
   conceded before it is answered.
3. **Legible at arm's length on a phone held by someone who wears readers.**
   Display type never drops below 56px on a 1080-wide frame; body never below
   38px.

## Palette

Graded to `marketing/creative/heygen/reference-tokens.json` — the *measured*
appearance of the HeyGen anchor take, not the brand spec, so these films cut
against it without looking like a different piece.

| Token | Value | Role |
|---|---|---|
| `--paper` | `#c3bba6` | Ground. Deeper than the reference film's `#d8d1c2` because these films carry more paper area and less photograph. The first pass used `#cfc7b6` and measured 175.8 (f3) and 176.3 (f5) — **above** the ceiling. `#c3bba6` is 12 luminance points darker and is what brought every film back inside the band. |
| `--paper-warm` | `#b8b09b` | Second surface — bands, card backs. |
| `--ink` | `#2a2126` | Display and body type. |
| `--ink-soft` | `#4e4348` | Secondary type. Darkened alongside the paper so AA contrast did not slip when the ground came down. |
| `--muted` | `#4f4448` | Eyebrows, terms, footers. 4.9:1 on the deepened paper — passes AA for body text, not only large text. |
| `--shadow` | `#33272e` | The anchor's measured darkest decile. Vignettes and cast shadows only. |
| `--accent` | `#7fb449` | The anchor's measured accent *as it appears*, not `#7cfc00`. Rules and markers only — it is 1.7:1 on paper and would be illegible as type. Budget: 1-2 % of frame. |
| `--rule` | `#b7ad9a` | Hairlines, dividers. |

## Grade contract

- **Mean luminance 150-175** across the whole film, measured with
  `signalstats` YAVG. Verified per film by `scripts/verify-render.mjs` and
  reported in `README.md`.
- **Accent 1-2 % of frame**, as a rule, a marker or a small square — never a
  field, never a gradient, never type.
- The vignette is a radial falloff in the measured shadow colour `#33272e`,
  not a black overlay. A real paper surface is not a flat value.

## Type

| Role | Face | Size | Tracking |
|---|---|---|---|
| Eyebrow | JetBrains Mono 700, uppercase | 30px | 0.22em |
| Display | Oswald 700, uppercase | 64-176px | -0.02em |
| Body | Inter 400 | 40-46px | -0.01em |
| Terms / footer | Inter 400 | 30-32px | 0 |

Left margin 84px, content width 912px, on a 1080 × 1920 frame at 30 fps.

## Composition — one archetype per film

Rule 3 of `docs/marketing/CAMPAIGN_ART_DIRECTION_V1.md`: rotating the palette is
not variety. These six rotate the *composition*.

| Film | Archetype | Plate |
|---|---|---|
| f1 thirty-years | Full-bleed plate under a paper band, then a split (type above, photo band below) | `pd-a2` — an empty iron sign bracket on sun-bleached siding |
| f2 know-where | Two photographic cards on paper, one per half of the argument | `pd-c2` night A-frame, `pd-c1` bedside phone |
| f3 not-technical | Standard — left type column, tall photo card on the right | `pd-b2` — a card-file drawer someone else built |
| f4 got-burned | Checklist — three struck rows, then the reversal | `pd-b1` open cash drawer, `pd-p` blank card |
| f5 too-expensive | Offer card — price as hero on an elevated panel | `pd-p` — a blank card in a brass holder |
| f6 retiring | Full-bleed plate, low horizon, type in the upper third | `pd-a1-tight` — a brass letter slot in a painted door |

## Beat shape

Every film is three beats, and the shape never changes because the argument
never changes:

1. **The objection**, in quotation marks, in their words. No commentary.
2. **The concession, then the mechanism.** What is true about the objection is
   said out loud before anything is answered.
3. **The offer**, plainly, with the renewal stated.

Beat boundaries are cut to the narration: each beat's audio is generated
separately and the cut lands in the silence between blocks
(`narration/timing.json`), so no cut falls mid-sentence.

## Claims allowed on screen

Only these, and only in these words:

- `$199` — `FAM-FOOT-199`, `price: "199.00"`, `published: true`.
- "55 cents a day" — 199 / 365 = $0.545.
- "One page. One year of hosting. A domain that is yours." — the SKU `summary`
  restated: one focused landing-page website, one year of managed hosting,
  first-year domain registration or connection of a domain already owned.
- "First year $199. Then $9.99 a month, plus the domain." — `FAM-HOST-999`,
  `price: "9.99"`, `interval: month`, `domain_renewal_separate: true`. This is
  BRAND.md's required renewal disclosure and appears on every film that names
  the price.
- "Business email and maintenance are separate." — `FAM-BUSINESS-EMAIL` and
  `FAM-MAINTENANCE` are their own SKUs.
- "Three real designs, reviewed by a person, before you pay." — the live
  proof-first flow at `/blog/proof-first-website-see-before-you-pay/`, plus
  BRAND.md's required reviewer disclosure.

Not allowed, and not present: any statistic, any percentage, any named
competitor, any ranking promise, any delivery-time promise, any `/web/` URL.
