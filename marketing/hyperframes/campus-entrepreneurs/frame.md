# frame.md — design truth for "Somebody Else's App"

The single design source for this project. Compositions must not invent a colour,
size, or face that is not here.

## Concept angle

**Everything on the desk becomes one address.**

A student running a side hustle between classes already has all the pieces — the
work, the customers, the money — scattered across a phone, a laptop, a DM thread
and a bio link. The film builds that literally: each beat lays a **card** on a
warm paper ground, slightly off-square, the way things land on a desk. Across the
beats the cards square up to the same left edge and the same width, until at the
offer they are one aligned block with a price on it. The last card is blank, with
a name about to go on it.

Three films, three different moves, one series: `platform-dependency` grows a
paper band until the photograph becomes a card; `ghost-town` walks a single light
down six blank surfaces; this one squares up a scattered desk.

## Grade — the anchor's measured appearance

Source of truth: `marketing/creative/heygen/reference-tokens.json`, measured off
`take-a-platform-dependency.mp4`.

| Property | Target | Why |
|---|---|---|
| Frame mean luminance | 150–175 (anchor take = 161.9) | The anchor is a LIGHT frame. |
| Shadow floor | muted mauve-grey `#33272E` | Measured darkest decile. Never crushed black. |
| Accent | `#7FB449` at ~1–2% of frame area | The brand's `#7cfc00` *renders* as `#7fb449` under this lighting. Grade to the appearance. |
| Accent form | one small incident per frame | Never a green field, gradient, or wash. |

**This film uses the anchor grade and `ghost-town` does not.** That is not
inconsistency; it is what the art-direction doctrine asks for. See README →
"Why this film is graded to the anchor".

Measured after `scripts/stage-assets.sh`:

| Asset | Source YAVG | Graded YAVG |
|---|---|---|
| `campus-dorm.mp4` (the campaign's own video, silent) | 102.0 | **141.3** |
| `cm-quad.jpg` (campus quad, golden hour) | 122.8 | **147.3** |
| `cm-card.jpg` (blank card, brass holder, olive clip) | 179.0 | **167.9** |
| `cm-drawer.jpg` (drawer of blank index cards, olive latch) | 165.0 | **166.0** |

The two full-bleed plates sit *below* the band deliberately: the paper ground
carries the frame mean up, so grading the photographs to 160 as well would push
the composite past the top of the band. See the README for the measured result.

## The navy-and-gold question

`LIVE_POSTING_PACKAGE_CAMPUS.md` proposes "collegiate varsity navy `#1D4ED8`,
sunburst gold `#F59E0B`". That palette is **not used**, and the reason is
`docs/marketing/CAMPAIGN_ART_DIRECTION_V1.md` Rule 1: *"Adding a palette is an
argument, not a preference. 'Amber because sun-bleached and abandoned' is a
reason. 'Blue because it looks professional' is not."* Varsity navy is a colour
borrowed from college sports merchandise, not from anything this campaign
argues, and it is not one of the five shipped palettes. What the package
describes underneath the colour names — *sunlit university brick, warm natural
desk lamp lighting* — is a **warm light frame**, which is exactly what the anchor
grade already is. So the warmth is kept and the varsity blue is dropped.

## Palette

| Token | Value | Role |
|---|---|---|
| `--paper` | `#DBD3C3` | the desk; the ground every card is laid on |
| `--paper-warm` | `#CEC5B3` | paper's second tone, card edges |
| `--ink` | `#241F1A` | primary type; a warm black, never `#000` |
| `--ink-soft` | `#544B42` | supporting type |
| `--muted` | `#6E6459` | eyebrows, footnotes |
| `--shadow` | `#33272E` | the measured shadow floor |
| `--accent` | `#7FB449` | the one small olive incident |
| `--rule` | `#B4AA97` | hairlines |

## Type

| Role | Face | Weight | Size | Tracking |
|---|---|---|---|---|
| Eyebrow / label | Space Mono | 700 | 30px | `0.2em`, uppercase |
| Headline | Archivo Black | 400 | 88–104px | `-0.025em`, `line-height: 1.0` |
| Support | Outfit | 400 | 40px | `-0.005em`, `line-height: 1.3` |
| Offer item | Outfit | 400 | 44px | `-0.005em` |
| Price | Archivo Black | 400 | 152px | `-0.035em` |
| Footnote | Outfit | 400 | 29px | `0` |
| URL | Space Mono | 700 | 44px | `0.04em` |

**Archivo Black** is a wide, heavy grotesque — it takes up room and sounds
certain, which is the register for an audience being told their side hustle is a
business. It is deliberately *not* condensed: `platform-dependency` uses Oswald
and `ghost-town` uses League Gothic, both compressed signage faces, and a third
condensed film would be the cookie-cutter failure one level up. **Outfit** is
geometric and contemporary. **Space Mono** has real character in its label
setting rather than reading as a terminal.

Because Archivo Black is wide, headlines here are 88–104px where the other two
films run 104–124px, and lines carry fewer words.

All three are in the renderer's bundled family set, so they inline as data URIs
and the render is offline and reproducible.

## Layout — 1080 × 1920

- Side margin `84px`. Top safe `176px`. Bottom safe `250px`.
- Cards are laid on the paper with a real cast shadow (a light ground can take
  one; `docs/marketing/CAMPAIGN_ART_DIRECTION_V1.md` `famElevate`).
- Cards start off-square and square up: beat 2's card is rotated `-1.4deg`,
  beat 3's `-0.7deg`, the offer's `0deg`. The rotation is on a wrapper, not on
  the timed element, and it is static per beat so no seek can land mid-rotation.
- Plates are full-bleed at `1188 × 2128` (1.10× frame); the video plate is
  native `1080 × 1920`.

## Motion

- `waterfall-entry` — headline lines cascade, binary `0→1` via `tl.set`,
  `power4.out`.
- `viewport-change` — one continuous push per plate, `1.00 → 1.045`.
- `spring-pop-entrance` — the price and the accent marker, `back.out`.
- Cards arrive with a short drop and settle, never a slide.

Every entrance is `fromTo` with explicit endpoints so a seek to any frame is
correct. No `repeat: -1`, no clocks, no randomness.

## Accent discipline

Two of the three still plates already carry their own small olive incident (the
clip beside the card holder, the latch on the drawer bank). The composition adds
one accent element per beat at most — a `200 × 12px` rule under the eyebrow, or
the offer's price marker — so the total stays inside the 1–2 % budget rather than
doubling the green. `scripts/verify-render.mjs` measures it per second.

## Sound

The film carries a music bed: the audio track of
`marketing/campaigns/campus-entrepreneurs/videos/01-campus-dorm-entrepreneur-9x16.mp4`,
which this campaign already ships attached to this exact drop. It is trimmed to
27.5s, level-trimmed to 0.55, and faded out over the last 1.6s. There is no
voiceover. See README → Limitations for what an agent can and cannot verify
about a soundtrack.
