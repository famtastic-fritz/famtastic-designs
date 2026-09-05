# frame.md — design truth for "Found, Then Kept"

The single design source for this project. Compositions must not invent a
colour, size, or face that is not here.

## Concept angle

**A shop that is open, and a sign that isn't there.** One photograph carries
the whole film: a wrought-iron sign bracket with a hook, a chain, and no sign,
on weathered timber in low sun — with the faded rectangle where the sign used
to hang still on the wall. That is the local-search problem photographed
without a word of explanation: the business exists, and there is nothing to
read.

A ground plane rises from the bottom of the frame beat by beat and takes the
photograph's place as the film has more to say — then drops back at the close,
so the last thing on screen is the bracket again.

The film is deliberately about **two products, not one**. Local SEO Setup
(`FAM-LOCAL-SEO`, $299 one time) and Website Maintenance (`FAM-MAINTENANCE`,
$49.99/month) are separate SKUs and both are add-ons; neither is in the $199
bundle, and the close says so on screen.

## Palette — `ghost-town`, turned to its daylight half

`marketing/creative/plates/prompt-library.json` files the local-SEO topic
(`flseo`) under `trades` — blue-black and safety orange, "a row of shopfronts
at dusk with exactly one lit". That is a good argument for a dusk piece and it
**cannot be used here**: `trades` is `#0D1117`, a near-black ground, and this
film has to hold the anchor's 150–175 luminance band.

The palette that actually fits the subject is `ghost-town`, argued in the art
direction as "a business that exists but cannot be found — heat, absence,
weathering". That is this film's claim exactly. But `ghost-town` as shipped is
also dark: `#17120D` earth with a `#D9A441` amber accent.

**So it is used at its other end.** Ghost-town's own prompt clause describes
"everything else bleached, dry and desaturated: bone, sand, faded paint, grey
weathered timber" — a sun-bleached main street at the top of the day is as
faithful to that argument as a dark one, and it is a light frame. The ground
token is bone/sand (`#CCC2AC`), the ink is a dark brown-black lifted off
ghost-town's earth, and **the amber stays in the photograph** — it is the low
sun on the timber, which is where amber belongs — while the graphic accent is
the contract's measured olive.

### The conflict this exposes, stated plainly

Two live doctrines disagree, and it is not a detail:

- `docs/marketing/CAMPAIGN_ART_DIRECTION_V1.md` ships six palettes. Four of them
  (`famtastic`, `ghost-town`, `salon`, `trades`) have near-black grounds.
- `marketing/creative/heygen/reference-tokens.json` requires anything cutting
  against the campaign anchor to sit at 150–175 mean luminance.

**Only `paper` and `anchor-take-a` can satisfy both.** Any campaign film whose
subject argues for one of the other four has to either abandon the palette or
abandon the anchor match. This project chose to keep the anchor match and
re-derive the palette's daylight half, and that decision belongs to the owner,
not to a film. See README.md — it is the single most consequential thing these
three films turned up.

## Grade — measured, not specified

Source of truth: `marketing/creative/heygen/reference-tokens.json`.

| Property | Target | Why |
|---|---|---|
| Frame mean luminance | 150–175, measured as `signalstats` YAVG (anchor take = 155.4) | The anchor is a LIGHT frame. |
| Shadow floor | muted mauve-grey `#33272E` | Measured darkest decile. Never crushed black. |
| Accent | `#7FB449` at ~1–2% of frame area | The brand's `#7cfc00` *renders* as `#7fb449` under this lighting. |
| Accent form | one small incident per frame | Never a green field, gradient, or wash. |

The plate is graded into the band by `scripts/stage-assets.sh` before
authoring, and the delivered frames are re-measured after render
(`scripts/verify-render.mjs`).

## Palette tokens

| Token | Value | Role |
|---|---|---|
| `--paper` | `#CCC2AC` | the ground plane; ghost-town bleached to bone |
| `--paper-edge` | `#BEB298` | the ground's lower edge |
| `--ink` | `#2A2118` | primary type; ghost-town's `#17120D` earth, lifted off black |
| `--ink-soft` | `#4E4234` | units, supporting type |
| `--muted` | `#5C5040` | eyebrows, footnotes |
| `--shadow` | `#33272E` | the measured shadow floor, shared with every film in this contract |
| `--accent` | `#7FB449` | the one small olive incident |
| `--rule` | `#9C8E73` | hairlines and the horizon line |

Ghost-town's amber `#D9A441` appears **nowhere as a token**. It is in the
photograph, as light.

## Type

The typographic system is constant across FAMtastic campaign work.

| Role | Face | Weight | Size | Tracking |
|---|---|---|---|---|
| Eyebrow / label | JetBrains Mono | 700 | 30px | `0.22em`, uppercase |
| Headline | Oswald | 700 | 88–92px | `-0.02em`, uppercase, `line-height: 0.98` |
| Price (hero) | Oswald | 700 | 132–150px | `-0.03em` |
| Row | Inter | 400 | 36–38px | `-0.01em` |
| Footnote | Inter | 400 | 29px | `0` |
| URL | JetBrains Mono | 700 | 40px | `0.06em` |

`$49.99` is set at 132px against `$299`'s 150px so a six-character figure keeps
the same optical weight as a four-character one.

## Layout — 1080 × 1920

- Side margin `84px`. Top safe `176px`. Bottom safe `220px`.
- **The ground plane** is full-width, anchored to the bottom edge of the frame,
  with a `3px` hairline where it meets the photograph. It is a ground, not a
  card and not a fade: no side margins, no shadow, one hard horizon.
- Its top edge is the beat's variable: `1420 → 900 → 820 → 820 → 1020`. It
  rises as the film has more to say and drops back for the close.
- **Beat 1 has no accent rule.** The ground sits low there so the bracket clears
  it, which leaves room for the eyebrow and two lines of display type and
  nothing else; the accent is a `16px` square beside the eyebrow instead.
- The plate is full-bleed at `1188 × 2128` (1.10× frame), positioned at
  `top: -208px` — the top of its headroom. That 104px lift is what puts the
  bracket, hook and chain **above** the beat-1 ground line. At the default
  `-104` the ground cut the bracket in half and the film lost its subject; it
  was caught by looking at a snapshot, not by any gate.

## Motion

Composed from named rules in `/hyperframes-animation`:

- `waterfall-entry` — headline lines and rows arrive as a cascade, binary `0→1`
  opacity via `tl.set`, `power4.out`, one direction (up).
- `viewport-change` — **one unbroken push across the whole film**, `1.000 →
  1.020 → 1.045 → 1.066 → 1.085 → 1.098`. Each beat starts where the last one
  ended, so every cut is on the same frame and the five beats read as one held
  shot. `1.098` is the ceiling: the plate is delivered at 1.10× frame.
- `spring-pop-entrance` — the prices, the accent markers, `back.out`.

**The cascade is the pacing.** This film is silent, so the stagger between rows
is roughly 0.6 s rather than the 0.16 s a narrated film uses — the reader sets
the tempo and there is no voice to keep up with.

## Accent discipline

The plate carries no olive of its own. The composition supplies one incident
per beat: a `260 × 16px` rule under the eyebrow, a `268 × 18px` marker under
each price, and `14px` row markers. Measured over the delivered film: **0.30%
of frame area**, inside the 1–2% budget and never a field.

## Claims that may appear on screen

Every one traces to `backend/config/famtastic-products.json` and to the campaign
copy in `marketing/campaigns/fam-local-seo/` and `marketing/campaigns/fam-maintenance/`:

- `$299`, one time — `FAM-LOCAL-SEO`, `price: "299.00"`, `billing.kind: one_time`.
- Structured local data / core profiles / analytics verified — the campaign's
  own landing and email copy, condensed.
- `$49.99` a month — `FAM-MAINTENANCE`, `price: "49.99"`, `billing.kind:
  recurring`, `interval: month`.
- Updates checked / backups verified / small content touches —
  `marketing/campaigns/fam-maintenance/landing-copy.md`, condensed.
- "Neither one is included in the $199 bundle" — both are `upsells` of
  `FAM-FOOT-199`, not entitlements of it.

**No ranking promise anywhere**, and the film says so out loud: *"Nobody can
promise you a ranking. This is the part that can be done."* The campaign's own
H1 — *"Be the business local search finds first"* — was **not** used, because
"finds first" reads as a ranking claim and `BRAND.md` forbids one. Flagged in
README.md for the copy owner.

No statistic, no percentage, no named competitor.
