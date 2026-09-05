# frame.md — design truth for "The Sign That Isn't There"

The single design source for this project. Compositions must not invent a colour,
size, or face that is not here.

## Concept angle

**A name arriving on a surface that has been blank for a long time.**

Every beat is a photograph of a real place where a business name *should* be and
isn't: an iron sign bracket with an empty hook and a hanging chain, an A-frame
board scrubbed blank, a till nobody walked in to fill, a phone face-down beside a
bed, a business-card holder holding one blank card. The type sits on a band of
dark earth laid over the photograph, and one amber hairline — the last of the low
sun — travels down the frame across the six beats. At the offer it stops on the
blank card. At the close it is on the empty bracket, and the film sets a name
where a sign would hang. The layout performs the argument: **being findable is a
surface with your name on it.**

This is the second film in the series and it deliberately does not reuse the
first one's move. `platform-dependency` argued *ownership arriving* by growing a
paper band until the photograph became a card. This one argues *visibility
arriving* by moving a single light across six blank surfaces.

## Grade — the campaign's palette, not the anchor's

Source of truth: `docs/marketing/CAMPAIGN_ART_DIRECTION_V1.md` Rule 1 and the
`ghost-town` entry in `marketing/creative/plates/prompt-library.json`.

| Property | Target | Why |
|---|---|---|
| Ground | `#17120D` warm near-black earth | The palette's own ground. It is the band the type sits on and the floor every plate is levelled to. |
| Accent | `#D9A441` amber, one incident per beat | "Low late sun, a sodium lamp, or dust caught in a single shaft of light." |
| Cool light | **none** | The palette says it outright: "No blue, no green, no neon, no cool light anywhere." |
| Everything else | bleached, dry, desaturated — bone, sand, faded paint, grey weathered timber | |
| Frame mean luminance | **50–95**, plate-dependent | A consequence of a dark-earth ground, not a target borrowed from elsewhere. See the README on why this film is not in the anchor's 150–175 band. |

`scripts/stage-assets.sh` grades every plate to this before authoring: a warm
colour balance, a per-plate `colortemperature` pull that removes the cool cast
(a2 arrived with a green sky, c2 is a blue-hour photograph), a `colorlevels`
floor that sets the darkest pixel in every plate to literally `#17120D`, and a
highlight cap so nothing in a "sun-bleached" frame clips to paper white.

Measured after grading:

| Plate | Source YAVG | Graded YAVG | What it is |
|---|---|---|---|
| `gt-a2` | 134.3 | **90.9** | empty iron sign bracket, blank painted board, weathered timber |
| `gt-c2` | 35.1 | **44.8** | A-frame board scrubbed blank, sodium dusk |
| `gt-b1` | 30.8 | **44.8** | near-empty cash drawer under one bulb |
| `gt-c1` | 32.5 | **47.0** | phone face-down beside a bedside lamp |
| `gt-b2` | 165.0 | **96.6** | drawer of blank index cards |
| `gt-p` | 179.0 | **107.7** | one blank card in a brass holder |

## Palette

| Token | Value | Role |
|---|---|---|
| `--earth` | `#17120D` | the ground; the band the type sits on |
| `--earth-deep` | `#0D0A07` | edge falloff, the deepest corner |
| `--bone` | `#E3D6BB` | primary type on earth — bleached, never `#FFF` |
| `--sand` | `#B6A482` | secondary type, rules |
| `--dust` | `#8A7C63` | eyebrows, footnotes |
| `--amber` | `#D9A441` | the one warm incident: the light shaft, the marker, the price |
| `--rule` | `#3B3125` | hairlines on earth |

`#7cfc00` and `#7fb449` do not appear in this film. Green is forbidden by this
campaign's palette, and the film is not cut against the HeyGen anchor take.

## Type

Three registers, none of them shared with the first film — the two pieces are the
same series, not the same poster in a different shirt.

| Role | Face | Weight | Size | Tracking |
|---|---|---|---|---|
| Eyebrow / label | IBM Plex Mono | 600 | 30px | `0.22em`, uppercase |
| Headline | Anton | 400 | 100–124px | `0.005em`, uppercase, `line-height: 0.94` |
| Support | Barlow | 400 | 40px | `-0.005em`, `line-height: 1.32` |
| Offer item | Barlow | 400 | 44px | `-0.005em` |
| Price | Anton | 400 | 176px | `0.005em` |
| Footnote | Barlow | 400 | 29px | `0` |
| URL | IBM Plex Mono | 600 | 46px | `0.06em` |

**Anton** is a single-weight condensed poster face — the closest thing in the
bundled set to painted signwriting on a shopfront, which is the object the film
is about. **Barlow** is slightly grotesque and a little utilitarian; it reads dry
where Inter reads clean. **IBM Plex Mono** is the machine's voice: the search box,
and the address a crawler either finds or does not.

All three are pre-bundled by the HyperFrames compiler, so they inline as data
URIs and the render is offline and reproducible.

## Layout — 1080 × 1920

- Side margin `84px`. Top safe `176px`. Bottom safe `250px`.
- Eyebrow at `y ≈ 196`. Headline block begins `y ≈ 268`.
- The earth band is top-anchored and full-width; its lower `160px` fades to
  transparent so the join to the photograph is never a line.
- Plates are full-bleed at `1188 × 2128` (1.10× frame) so a 1.10 camera push
  never samples below delivered resolution.

## Motion

Composed from named rules in `/hyperframes-animation`:

- `waterfall-entry` — headline lines arrive as a cascade, binary `0→1` opacity
  via `tl.set`, `power4.out`, one direction, anchors travelling further than
  fragments.
- `viewport-change` — one continuous camera push per plate, `1.00 → 1.05`.
- `spring-pop-entrance` — the price and the amber marker, `back.out`.
- The film's own move: **the light shaft.** A `4px` amber hairline that enters
  from the left, holds, and leaves — placed one step lower in the frame on each
  successive beat, so across the six beats a single low sun descends the film. It
  is the accent budget for the whole piece.

Every entrance is `fromTo` with explicit endpoints so a seek to any frame is
correct. No `repeat: -1`, no clocks, no randomness.

## Accent discipline

The amber accent is one element per beat and never a field, gradient or wash:
the light shaft in beats 1–4, the price marker in beat 5, the shaft again on the
empty bracket in beat 6. `scripts/verify-render.mjs` measures amber-dominant
pixels per second against a 3% ceiling — looser than the anchor film's 1–2%
because this palette makes amber the campaign's own signal colour rather than a
borrowed trim, but still an incident, not a ground.
