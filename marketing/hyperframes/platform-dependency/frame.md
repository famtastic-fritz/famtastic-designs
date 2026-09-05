# frame.md — design truth for "Borrowed Land"

The single design source for this project. Compositions must not invent a colour,
size, or face that is not here.

## Concept angle

**The paper you own is laid over the ground you rent.** Every beat is a photograph
of a real, blank surface — a letter slot with no name, a sign bracket with no sign,
a drawer of unlabelled cards — with a warm paper band laid over it carrying the
type. Across the six beats the paper band grows and the photograph recedes, until
at the offer the photograph is a small framed card sitting *on* the paper. The
layout performs the argument: ownership arriving.

## Grade — measured, not specified

Source of truth: `marketing/creative/heygen/reference-tokens.json`, measured off
`take-a-platform-dependency.mp4`, the video this film must cut against.

| Property | Target | Why |
|---|---|---|
| Frame mean luminance | 150–175 (anchor take = 161.9) | The anchor is a LIGHT frame. A dark grade reads as a different piece. |
| Shadow floor | muted mauve-grey `#33272E` | Measured darkest decile. Never crushed black. |
| Accent | `#7FB449` at ~1–2% of frame area | The brand's `#7cfc00` *renders* as `#7fb449` under this lighting. Grade to the appearance. |
| Accent form | one small incident per frame | Never a green field, gradient, or wash. |

Plates were graded to this band by `scripts/stage-assets.sh` before authoring, and
the delivered frames are re-measured after render (`scripts/verify-render.mjs`).

## Palette

| Token | Value | Role |
|---|---|---|
| `--paper` | `#F2EFE9` | the owned surface; the band the type sits on |
| `--paper-warm` | `#E8E2D8` | paper's second tone, edges and rules |
| `--ink` | `#2A2126` | primary type; a mauve-black, never `#000` |
| `--ink-soft` | `#5C5057` | supporting type |
| `--muted` | `#8A7E82` | eyebrows, footnotes |
| `--shadow` | `#33272E` | the measured shadow floor |
| `--accent` | `#7FB449` | the one small olive incident |
| `--rule` | `#D5CEC2` | hairlines |

`#7cfc00` is reserved for unlit vector/type surfaces elsewhere in the brand and is
deliberately **not** used here — it would clash with the take.

## Type

Two registers plus a label voice. The tension is public signage against private
software: **Oswald** is a compressed poster/signage face — the lettering on
somebody else's shopfront. **Inter** is screen-native and humanist — the thing you
actually own and can change. The mono is the machine's voice: what a crawler sees.

| Role | Face | Weight | Size | Tracking |
|---|---|---|---|---|
| Eyebrow / label | JetBrains Mono | 700 | 30px | `0.22em`, uppercase |
| Headline | Oswald | 700 | 104–126px | `-0.02em`, uppercase, `line-height: 0.98` |
| Support | Inter | 400 | 40px | `-0.01em`, `line-height: 1.34` |
| Offer item | Inter | 400/900 | 44px | `-0.01em` |
| Footnote | Inter | 400 | 30px | `0` |
| URL | JetBrains Mono | 700 | 46px | `0.06em` |

All three families are pre-bundled by the HyperFrames compiler, so they embed as
data URIs and render offline with no build-time fetch. Sizes are in-feed sizes
(headlines ≥90px, body ≥32px, labels ≥24px), not web sizes.

## Layout — 1080 × 1920

- Side margin `84px`. Top safe `176px`. Bottom safe `250px`.
- Eyebrow at `y ≈ 196`. Headline block begins `y ≈ 268`.
- The paper band is top-anchored and full-width; its height is the beat's
  `--band` value. Its lower `120px` is a gradient to transparent so the join to
  the photograph is a fade, never a line.
- Plates are full-bleed at `1188 × 2128` (1.10× frame) so a 1.10 camera push
  never samples below delivered resolution.

## Motion

Composed from named rules in `/hyperframes-animation`:

- `waterfall-entry` — headline lines arrive as a cascade, binary `0→1` opacity via
  `tl.set`, `power4.out`, one direction (up), anchors travel further than fragments.
- `viewport-change` — a single continuous camera push on each plate, `1.00 → 1.06`
  over the beat. Subtle band per the rule's scale guide; the photograph must not
  announce itself.
- `sine-wave-loop` — the paper band's hairline rule breathes, finite repeats.
- `spring-pop-entrance` — the price and the accent marker, `back.out`.

Every entrance is `fromTo` with explicit endpoints so a seek to any frame is
correct. No `repeat: -1`, no clocks, no randomness.

## Accent discipline

Three of the four plates already carry their own small olive incident (a green tag
by the letter slot, a green latch on the drawer bank, a green clip beside the card
holder). The composition therefore adds only **one** accent element per beat — a
`200 × 12px` rule under the eyebrow, or the offer's price marker — so the total
green area stays inside the 1–2% budget rather than doubling it.
