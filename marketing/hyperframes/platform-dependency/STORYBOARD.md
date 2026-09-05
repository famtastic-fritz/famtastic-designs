---
project: platform-dependency
title: Borrowed Land
message: "The platform helps people find you, but the address they find isn't yours — own the one they type."
aspect: 1080x1920
fps: 30
duration: 29.0
mode: autonomous
design: frame.md
---

# Borrowed Land

Six beats. The cuts land inside the narration's own pauses, measured off the take's
RMS envelope (`scripts/vo-timing.md`) rather than estimated. Narration is the
approved HeyGen take; the on-screen type is a condensed restatement of it, never a
new claim.

| beat | scene | start | dur | plate | narration it carries |
|---|---|---|---|---|---|
| hook | 01-hook | 0.00 | 3.55 | pd-a1 blank letter slot | s1 + s2 |
| friction | 02-friction | 3.55 | 2.60 | pd-a2 empty sign bracket | s3 |
| mechanism | 03-mechanism | 6.15 | 6.40 | pd-b2 drawer of blank cards | s4 + s5 |
| turn | 04-turn | 12.55 | 7.05 | pd-p blank card, brass holder | s6 + s7 |
| offer | 05-offer | 19.60 | 5.40 | anchor as framed card | — |
| close | 06-close | 25.00 | 4.00 | paper only | — |

The paper band grows across the film (42% → 46% → 60% → 58% → full → full) and the
photograph recedes behind it. By the offer the photograph is a small card sitting
*on* the paper. That progression is the argument.

## Frame 1

- id: `01-hook`
- src: `compositions/01-hook.html`
- status: outline
- motion: `waterfall-entry` (headline cascade) + `viewport-change` (1.00 → 1.055 push)
- band: 42%

Two statements the viewer will not argue with, so the third lands. Eyebrow
`PLATFORM DEPENDENCY`. `YOUR BUSINESS / IS REAL.` cascades at 0.85; `YOUR CUSTOMERS /
ARE REAL.` at 2.20. The plate is a front door whose brass letter slot has a blank,
unengraved plate — the address exists and carries no name.

## Frame 2

- id: `02-friction`
- src: `compositions/02-friction.html`
- status: outline
- motion: `waterfall-entry` + `viewport-change` (1.00 → 1.05)
- band: 46%

The turn against the hook, isolated so it lands. Eyebrow `THE ADDRESS`.
`THE PLACE THEY / FIND YOU ISN'T / YOURS.` cascades at 3.75. The plate is a wrought-iron
sign bracket with no sign on it — the fixture is there, the name is gone.

## Frame 3

- id: `03-mechanism`
- src: `compositions/03-mechanism.html`
- status: outline
- motion: `waterfall-entry` + `viewport-change` (1.00 → 1.06) + `sine-wave-loop` (rule breathe)
- band: 60%

The longest beat, because it carries the whole mechanism. Eyebrow `THE MECHANISM`.
`A PROFILE IS AN / ADDRESS INSIDE / SOMEBODY ELSE'S / BUILDING.` cascades at 6.45.
Support at 9.85: *Their rules. Their ranking. Their reach.* Then the crawler readout
in mono at 11.05 — the machine's own voice, a third register: `WHAT A SEARCH ENGINE
SEES:` / `A LIST OF LINKS. ALMOST NOTHING.` The plate is a wall of filing drawers,
one pulled open on blank cards.

## Frame 4

- id: `04-turn`
- src: `compositions/04-turn.html`
- status: outline
- motion: `waterfall-entry` + `viewport-change` (1.00 → 1.05)
- band: 58%

Eyebrow `THE FIX`. `A SITE OF YOUR OWN / WORKS DIFFERENTLY.` cascades at 12.70.
Support at 14.60: *It sits at a domain you own, and it answers the question at two
in the morning — without you.* The presenter take appears here as a muted framed
inset (host-root, `04` window only), lip-synced to the narration bed by
`data-media-start`. The plate is a blank business card in a brass holder.

## Frame 5

- id: `05-offer`
- src: `compositions/05-offer.html`
- status: outline
- motion: `waterfall-entry` (list) + `spring-pop-entrance` (price, accent marker)
- band: full paper

The photograph is now a framed card on the paper: the flagship anchor, a workbench
counter with two blank cards. Eyebrow `WEB BASICS BUNDLE`. `$199` pops at 20.05 with
the accent marker; `55 CENTS A DAY` under it. Three included items cascade at 21.1.
Footnote at 23.4: *Business email and maintenance are separate.* Every line is
`backend/config/famtastic-products.json` verbatim in substance; nothing is implied.

## Frame 6

- id: `06-close`
- src: `compositions/06-close.html`
- status: outline
- motion: `waterfall-entry` + `spring-pop-entrance` (accent dot)
- band: full paper

`OWN THE ADDRESS / THEY TYPE.` at 25.15, then the accent dot and
`famtasticdesigns.com` at 26.2. The URL was curled (HTTP 200 on both apex and www)
before it was set in type.
