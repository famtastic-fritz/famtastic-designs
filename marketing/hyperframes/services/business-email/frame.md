# frame.md — design truth for "Two Different Jobs"

The single design source for this project. Compositions must not invent a
colour, size, or face that is not here.

## Concept angle

**A question, answered on the word.** The film opens on the question a customer
actually asks — *does the $199 website come with business email?* — with the
person who gets asked it standing in the frame. At 5.69 s, on the syllable, an
inverted block lands under the question carrying the answer: **It does not.**

Everything after that is the scope of the two products, side by side, ending on
a photograph of two blank cards: the same business, printed twice. Two cards is
the argument. Two jobs, two products, one business.

This film exists because a live scheduled drop in this repo says the $199
bundle includes email. It does not (`FAM-BUSINESS-EMAIL`, `$99`, its own SKU).
The correction is not a footnote here; it is the entire piece.

## Palette — `anchor-take-a`, argued

`marketing/creative/plates/prompt-library.json` files the business-email topic
(`fbe`) under `paper`. That would be a defensible choice for a typographic
film. **This film is not typographic** — it puts the campaign's HeyGen presenter
on screen for its first nine seconds, and `anchor-take-a` is the palette that
exists for exactly that case. Its own note in
`marketing/creative/templates/famtastic-social-frame.jsx` says so:

> "Measured from take-a. Light ground, olive accent as rendered. Match, do not
> spec." … "Use this palette when a still must sit next to THIS take."

A still — or a film — that carries the presenter and is graded to anything else
reads as two pieces cut together. So the palette is chosen from what is
physically in the frame, not from the topic label.

The ground is `anchor-take-a`'s `#F4F2EE` deepened to `#D2C9BE`, because a
near-white ground cannot hold the 150–175 luminance contract (the same finding
the platform-dependency film recorded). The ink is the anchor's own measured
darkest decile, `#33272E` — a mauve-black, never `#000`.

**`--paper` is not a design choice.** It is the measured background of the
graded presenter composite produced by `scripts/stage-assets.sh`. She is keyed
onto that exact value so the cut-out is invisible. Change one without the other
and she sits in a visible rectangle.

## Grade — measured, not specified

Source of truth: `marketing/creative/heygen/reference-tokens.json`.

| Property | Target | Why |
|---|---|---|
| Frame mean luminance | 150–175 (anchor take = 161.9 by that file's method, 155.4 by `signalstats` YAVG) | The anchor is a LIGHT frame. |
| Shadow floor | muted mauve-grey `#33272E` | Measured darkest decile. Never crushed black. |
| Accent | `#7FB449` at ~1–2% of frame area | The brand's `#7cfc00` *renders* as `#7fb449` under this lighting. |
| Accent form | one small incident per frame | Never a green field, gradient, or wash. |

**The presenter's jacket trim is the accent for the first nine seconds**, which
is what `reference-tokens.json` describes for the anchor itself ("It is trim on
the presenter's jacket, not a field of colour"). Measured after grading, that
trim averages `#90AD43` — olive, close to the target `#7FB449`, but not on it.
See README.md; take-b was rendered with the background removed and therefore
without take-a's ambient office light, so its trim reads brighter at source.

## Palette tokens

| Token | Value | Role |
|---|---|---|
| `--paper` | `#D2C9BE` | the ground; **must equal** the graded presenter composite's background |
| `--paper-edge` | `#C6BCAE` | the band's lower edge |
| `--ink` | `#33272E` | primary type; the anchor's measured darkest decile |
| `--ink-soft` | `#57484F` | figures, supporting type |
| `--muted` | `#625860` | eyebrows, footnotes |
| `--shadow` | `#33272E` | the measured shadow floor; the card's cast shadow |
| `--accent` | `#7FB449` | the one small olive incident |
| `--rule` | `#A49A91` | hairlines |

`--muted` began at `#6B6167` and was darkened after the contrast gate reported
`#b5-ft` at 2.96:1 against the vignetted ground — 0.04 under WCAG AA. The gate
caught it; the eye would not have.

## Type

The typographic system is constant across FAMtastic campaign work. Three
registers:

| Role | Face | Weight | Size | Tracking |
|---|---|---|---|---|
| Eyebrow / label | JetBrains Mono | 700 | 30px | `0.22em`, uppercase |
| Question headline | Oswald | 700 | 80px | `-0.02em`, uppercase, `line-height: 0.98` |
| Answer (reversed) | Oswald | 700 | 84px | `-0.02em`, uppercase |
| Scene headline | Oswald | 700 | 96–104px | `-0.02em`, uppercase |
| Price (hero) | Oswald | 700 | 160px | `-0.03em` |
| Row label | Inter | 400 | 40px | `-0.01em` |
| Row figure | JetBrains Mono | 700 | 38px | `0.02em`, tabular |
| Footnote | Inter | 400 | 29px | `0` |
| URL | JetBrains Mono | 700 | 40px | `0.06em` |

All three families are pre-bundled by the HyperFrames compiler, so they embed
as data URIs and render offline with no build-time fetch.

## Layout — 1080 × 1920

- Side margin `84px`. Top safe `176px`. Bottom safe `220px`.
- **Beat 1** is the only beat with no photograph: paper ground, type in the
  upper half, the keyed presenter at `340, 790` (`713 × 1160`). Every type
  element clears her box, because she is host-owned above the scene slots and
  cannot be layered under scene type.
- **Beats 2–3** are `split`: a full-width band of held colour cut across a
  full-bleed plate, its lower edge on a 50–60px diagonal so the frame does not
  read as two stacked rectangles. The photograph stays visible above **and**
  below the band — that is what separates this from a header band.
- **Beats 4–5** are `offer-card`: the photograph becomes an object, a `912 × 608`
  card with a real cast shadow (art direction's `famElevate` — a light ground
  takes a shadow, a dark ground takes a lit edge). The card holds its position
  and scale across the cut into the close; only the words under it change.
- The vignette is host-owned at `z-index: 40`, above the presenter. A
  scene-owned vignette would sit under her and she would read as pasted on
  instead of lit by the same light.
- Plates are full-bleed at `1188 × 2128` (1.10× frame); the anchor card is
  rendered at `1003 × 669` (1.10× the card).

## Motion

Composed from named rules in `/hyperframes-animation`:

- `waterfall-entry` — headline lines and scope rows arrive as a cascade, binary
  `0→1` opacity via `tl.set`, `power4.out`, one direction (up), anchors travel
  further than fragments.
- `viewport-change` — one continuous camera push per plate; beats 2 and 3 share
  the plate and the push runs `1.000 → 1.040 → 1.065` unbroken across the cut.
- `spring-pop-entrance` — the `$99`, the accent markers, `back.out`.

Every entrance is `fromTo` with explicit endpoints so a seek to any frame is
correct. No `repeat: -1`, no clocks, no randomness.

**Every cue is placed against the narration's measured word timings**, not
estimated — see STORYBOARD.md. The answer block wipes open at 5.50 s so the
words land into a block that is already there when the voice says them at 5.69.

## Accent discipline

The plates carry almost no olive of their own (`plate-holder` measures 0.01% by
the verify script's detector). The composition therefore supplies it: one
`260 × 16px` rule per beat, a `268 × 18px` marker under the price, and small
`16px` row markers. The presenter's jacket trim carries beat 1 on its own.

## Claims that may appear on screen

Every one traces to `backend/config/famtastic-products.json`, to the approved
narration in `marketing/creative/heygen/scripts/take-b-business-email-scope.json`,
and to the live page `https://famtasticdesigns.com/packages` (curled, HTTP 200):

- `$199`, one landing-page website, one year of managed hosting, your domain
  registered new or connected — `FAM-FOOT-199`.
- `$9.99 a month` after the first year — `FAM-HOST-999`, the SKU's
  `renewal_sku`, `activation: after_included_period`.
- `the domain renews separately` — `billing.domain_renewal_separate: true`.
- `$99, one time`, business email as a separate setup — `FAM-BUSINESS-EMAIL`.
- "You keep your own mailbox provider. We never resell mailboxes." —
  `marketing/campaigns/fam-business-email/landing-copy.md`, condensed.

Nothing else. No statistic, no percentage, no named competitor, no delivery
promise. **Nothing on screen states or implies that email is part of the $199
bundle**, and the close puts both products and both prices on the same frame so
the last image cannot be read as one.
