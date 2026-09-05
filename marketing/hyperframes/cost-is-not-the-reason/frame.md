# frame.md — design truth for "Fifty-Five Cents"

The single design source for this project. Compositions must not invent a
colour, size, or face that is not here.

## Concept angle

**A quote sheet, laid on the business it is for.** Every beat is a real
photograph with a sheet of paper lying on it, carrying the figures. The sheet
has four visible edges and a real cast shadow — it is an object on the
photograph, not a wash over it. Across the film the sheet grows upward as more
of the arithmetic is written on it, and the last thing written is the part most
price films leave off: what happens after the first year.

The layout performs the argument. The film is not asking to be believed; it is
asking to be **checked**.

## Palette — `paper`, argued

`docs/marketing/CAMPAIGN_ART_DIRECTION_V1.md` ships six palettes.
`marketing/creative/plates/prompt-library.json` records `famtastic` for this
exact topic (`cinr-55c`, "coins on black, lit once"). **That palette cannot be
used here**, and the reason is worth recording rather than quietly working
around:

- `famtastic` is `#070907` — a near-black ground. This film has to cut against
  the campaign's premium anchor take, which is a **light** frame (measured mean
  luminance 161.9, `marketing/creative/heygen/reference-tokens.json`). A
  near-black ground cannot land inside the 150–175 contract at all. The two
  live doctrines genuinely collide here; see README.md "Where the doctrines
  collide".
- `paper` is argued in the art direction as the palette for "proposals,
  documents, LinkedIn — anything that must read sober rather than as an ad."

That is exactly this film's job. The one thing that makes a low price look like
a trick is packaging it like an advertisement. A price the viewer is invited to
check should look like a **quote**, not a promotion. `paper` is chosen from the
subject — a document — and it is also the only shipped palette whose ground can
hold the anchor's luminance band.

**Where `paper` and the anchor disagree, the measurement wins.** `paper`'s
accent is `#1F6F4A` (deep pine). The accent here is `#7FB449`, because
`reference-tokens.json` measured that as how the brand accent actually renders
under the anchor's light. A palette is an argument; a measurement is a fact.

## Grade — measured, not specified

Source of truth: `marketing/creative/heygen/reference-tokens.json`.

| Property | Target | Why |
|---|---|---|
| Frame mean luminance | 150–175 (anchor take = 161.9) | The anchor is a LIGHT frame. A dark grade reads as a different piece. |
| Shadow floor | muted mauve-grey `#33272E` | Measured darkest decile. Never crushed black. |
| Accent | `#7FB449` at ~1–2% of frame area | The brand's `#7cfc00` *renders* as `#7fb449` under this lighting. |
| Accent form | one small incident per frame | Never a green field, gradient, or wash. |

Plates are graded into the band by `scripts/stage-assets.sh` before authoring,
and the delivered frames are re-measured after render
(`scripts/verify-render.mjs`).

## Palette tokens

| Token | Value | Role |
|---|---|---|
| `--paper` | `#D5CEBE` | the quote sheet |
| `--paper-edge` | `#C6BEAC` | the sheet's own lower edge, where it curls |
| `--ink` | `#211F1A` | primary type; `paper`'s near-neutral ink, lifted off black |
| `--ink-soft` | `#4A453C` | supporting type |
| `--muted` | `#635C50` | eyebrows, footnotes, figures' leaders |
| `--shadow` | `#33272E` | the measured shadow floor; the sheet's cast shadow |
| `--accent` | `#7FB449` | the one small olive incident |
| `--rule` | `#A79E8B` | hairlines on the sheet |

`#7cfc00` is reserved for unlit vector/type surfaces elsewhere in the brand and
is deliberately **not** used — it would clash with the take.

## Type

The typographic system is constant across FAMtastic campaign work (art
direction Rule 1: the type, the grid and the restraint are what make an asset
recognisably ours; the palette and the art are what vary). Three registers:

| Role | Face | Weight | Size | Tracking |
|---|---|---|---|---|
| Eyebrow / label | JetBrains Mono | 700 | 30px | `0.22em`, uppercase |
| Headline | Oswald | 700 | 96–112px | `-0.02em`, uppercase, `line-height: 0.98` |
| Figure (hero) | Oswald | 700 | 176px | `-0.03em` |
| Ledger row | Inter | 400 | 40px | `-0.01em` |
| Ledger figure | JetBrains Mono | 700 | 40px | `0.02em`, tabular |
| Footnote | Inter | 400 | 29px | `0` |
| URL | JetBrains Mono | 700 | 42px | `0.06em` |

All three families are pre-bundled by the HyperFrames compiler, so they embed
as data URIs and render offline with no build-time fetch. Sizes are in-feed
sizes, not web sizes.

## Layout — 1080 × 1920

- Side margin `84px`. Top safe `176px`. Bottom safe `220px`.
- The quote sheet is `912px` wide, left edge `84px`, bottom edge `1700px`. Its
  **top** edge is the beat's variable: the sheet grows upward as the film
  writes more on it.
- The sheet carries a real cast shadow, not a lit edge — art direction's
  `famElevate` rule: a light ground takes a shadow, a dark ground takes a lit
  edge. Same intent, opposite physics.
- Plates are full-bleed at `1188 × 2128` (1.10× frame) so a 1.10 camera push
  never samples below delivered resolution.

## Motion

Composed from named rules in `/hyperframes-animation`:

- `waterfall-entry` — headline lines and ledger rows arrive as a cascade,
  binary `0→1` opacity via `tl.set`, `power4.out`, one direction (up), anchors
  travel further than fragments.
- `viewport-change` — a single continuous camera push on each plate,
  `1.00 → 1.05` over the beat. The photograph must not announce itself.
- `spring-pop-entrance` — the hero figure and the accent marker, `back.out`.

The sheet itself rises into place on `power3.out` and never moves again within
a beat: a document that drifts is not a document.

Every entrance is `fromTo` with explicit endpoints so a seek to any frame is
correct. No `repeat: -1`, no clocks, no randomness.

## Accent discipline

Two of the three plates carry their own small olive incident (the green clip
beside the card holder, the clip at the edge of the counter). The composition
therefore adds only **one** accent element per beat — a `200 × 12px` rule under
the eyebrow, or the hero figure's marker — so the total green area stays inside
the 1–2% budget rather than doubling it.

## Claims that may appear on screen

Every one traces to `backend/config/famtastic-products.json` and to the live
page `https://famtasticdesigns.com/packages/199-quick-start` (curled, HTTP 200):

- `$199` — `FAM-FOOT-199`, `price: "199.00"`, `published: true`.
- `55 cents a day` — 199 ÷ 365 = 0.545.
- One focused landing-page website / one year of managed hosting / first-year
  domain registration, or connecting a domain already owned — the SKU's
  `summary`, restated.
- `$9.99 a month after the first year` — `FAM-HOST-999`, `renewal_sku` of
  `FAM-FOOT-199`, `activation: after_included_period`.
- `the domain renews separately` — `billing.domain_renewal_separate: true`.
- `Business email is a separate product, $99 one time` — `FAM-BUSINESS-EMAIL`.
- `Website maintenance is a separate product, $49.99 a month` —
  `FAM-MAINTENANCE`.

Nothing else. No statistic, no percentage, no named competitor, no delivery
promise, no ranking promise.
