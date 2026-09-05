# Already Know The Game — creative record

**Campaign**: `marketing/campaigns/already-know-the-game/`
**Lane**: C2 of `plans/four-campaign-flood/plan.md`
**Built**: 2026-09-05 · **Window**: 2026-09-06 → 2026-09-13 (8 days, 6 drops)
**Channels**: `facebook`, `instagram-standalone` only
**Measured spend**: **$0.3024** against a $1.50 ceiling

---

## The line the whole campaign is built to land

> You already run a business. You just don't own the address it runs on.

Everything here — palette, plates, archetypes, copy — exists to make that one
sentence land without ever explaining who it is for.

---

## Tone contract, and how it was enforced

Four rules, taken from the lane brief and treated as pass/fail rather than as
guidance:

1. **Address the skill, never the past.** Copy speaks only to present
   capability. Nothing references any history, circumstance, institution, or
   "second chance", directly or in code. There is no redemption arc because
   nothing here needs redeeming.
2. **Never name the audience.** No drop names, hints at, or bounds who this is
   for. Every line reads as a message to any self-taught operator.
3. **Respect, never pity.** The reader is competent and is being told something
   practical. No encouragement, no uplift, no congratulation.
4. **Competence first.** Every drop opens by stating something the reader
   already does well, then names the administrative gap.

### Tone audit — every headline and eyebrow, read against the rules

| Drop | Eyebrow | Headline | Opens on |
|---|---|---|---|
| 1 | YOU ALREADY RUN A BUSINESS | You just don't own / the address. | pricing, retention, reading a slow week |
| 2 | THE LIST IS THE BUSINESS | Access is a permission. | a list earned one customer at a time |
| 3 | YOU ALREADY KNOW YOUR PRICES | So why does / everyone ask? | pricing a job in your head while working |
| 4 | WHAT A BOOKABLE PAGE NEEDS | Two questions, answered. | (mechanism, not the reader) |
| 5 | IT CAN ALL BE CLEARED | Keep the accounts. / Own one address. | "you already know this" |
| 6 | NOT A NEW SKILL | Paperwork you have not done yet | pricing, demand, reputation, getting paid |

Zero occurrences across all captions, headlines and file names of: crime,
record, prison, street(s), reform, redemption, second chance, legitimate,
"going straight", "turn your life around", "started from", or any
before/after framing. Checked by reading, not by grep — a coded synonym does
not grep.

The one line that came closest to breaking rule 1 was an early draft of drop
01: *"Nobody taught you that in a classroom."* Rewritten to *"None of that came
out of a textbook."* The first version implies something about where the reader
was instead of about how the knowledge was acquired. Self-taught is a method;
it is safe. Anything that gestures at a location or a period is not.

---

## Palette: `shutter` — the argument

Added to `PALETTES` in `marketing/creative/templates/famtastic-social-frame.jsx`.
It did not exist before this campaign (checked before adding; the block held
`famtastic`, `ghost-town`, `salon`, `trades`, `anchor-take-a`, `paper`). That
entry is the **only** change this lane made to any shared file.

| Role | Hex | RGB |
|---|---|---|
| ground | `#16181A` | 22, 24, 26 |
| accent | `#E4C227` | 228, 194, 39 |
| head | `#EDEAE3` | 237, 234, 227 |
| body | `#96938C` | 150, 147, 140 |
| hair | `#34363A` | 52, 54, 58 |

**Argued from the subject, per Rule 1 of `CAMPAIGN_ART_DIRECTION_V1`:**

The ground is not "dark because grunge is dark". It is the literal material
this business is standing on and in front of: cold rolled steel and grey
concrete in shadow. A roller shutter, a loading bay, a poured wall, a steel
counter. The campaign's claim is that the business is already physically real,
so the ground has to be a real surface rather than a field of colour.

The accent is not "yellow because yellow pops". Every street where commerce
already happens has one colour system already installed on it — loading-bay
stencil paint, curb markings, shutter warning stripes, hand-lettered price
boards. `#E4C227` is that paint after a few winters: chalky, bleached, not the
clean cadmium it started as. It is the colour the reader's own working
environment is **already** marked in, which is exactly the campaign's argument:
nothing here is new to them.

It is also deliberately not adjacent to the two palettes it could be confused
with. `ghost-town` amber `#D9A441` is warm and gold and reads as sun and dust;
`#E4C227` is colder, more acidic, and reads as pigment. `trades` safety orange
`#FF7A1A` is a hazard colour; this is a marking colour. Different arguments,
different hues.

### Divergence from the anchor — measured, and recorded so it is never mistaken for drift

The premium HeyGen anchor (`marketing/creative/heygen/reference-tokens.json`)
measures at **mean luminance 150–175 of 255**, with olive `#7FB449` covering
1–2% of frame.

These frames were **measured**, not estimated — all 18 finished PNGs,
downsampled to 200×200 and averaged with the Rec.601 luma weights:

| | anchor band | this campaign, measured |
|---|---|---|
| ground swatch luminance | — | **24** / 255 |
| finished-frame mean luminance | **150–175** | **51.4** (range 40.7 – 60.8) |
| accent coverage of frame | 1–2% | **2.03%** (range 0.12% – 6.82%) |

**That is a ~100–125 point deliberate luminance divergence, not an error and
not drift.** The plan's grading rule allows a campaign with its own argued
palette to diverge provided it says so in writing; this is that statement.

Three consequences that must be honoured by anyone reusing these assets:

- **These frames are not anchor-graded.** A `shutter` still must never be cut
  in beside the `take-a` presenter video or any `anchor-take-a` asset. Side by
  side they will read as two different campaigns, because they are.
- **Accent coverage is on-band on average but not per-frame.** The set averages
  2.03%, which sits at the top of the anchor's range, but drop 02's
  `comparison` slab measures **6.7–6.8%** — three times the band. That is the
  archetype doing its job on a dark ground and it is in-palette; it is not a
  licence to raise accent coverage on anchor-graded work.
- **The floor, not just the mean, is low.** Drop 05 at 4:5 measures 40.7. On a
  phone in daylight these frames rely on the type contrast, not on the plate,
  which is why every headline carries a torn band or a held panel behind it.

Reproduce the measurement:

```bash
cd marketing/creative/campaign-assets/already-know-the-game/stills
python3 -c "
from PIL import Image; import statistics, glob
for p in sorted(glob.glob('*.png')):
    px=list(Image.open(p).convert('RGB').resize((200,200)).getdata())
    print(p, round(statistics.mean(0.299*r+0.587*g+0.114*b for r,g,b in px),1))
"
```

The note is duplicated in three places so it cannot be lost: the `PALETTES`
comment block, `manifest.json` → `creative_direction.anchor_divergence`, and
here.

---

## Textures: checked before generating

`marketing/creative/textures/` was inspected first, as instructed. It holds
five procedural SVGs: `famtastic-grid-quiet`, `ghost-town-dust`,
`paper-fibre-light`, `salon-soft-grain`, `trades-workshop-scratch`. Each is
built for a specific existing palette and each is a soft atmospheric field —
`trades-workshop-scratch` is the closest and is still a gentle scratch wash on
blue-black, not a photocopy.

None supplies what this campaign needs: halftone dot breakup, toner blotch,
drum banding and a torn edge. So **one** texture was generated
(`akg-tex-xerox-9x16.jpg`, $0.0336) rather than five, and it is reused across
all 18 frames at SOFT_LIGHT 26–30%.

---

## Plates — 7 generated, 9 billed

`node marketing/creative/plates/generate-plates.mjs` against a campaign-local
library. Provider Gemini Flash Lite (`gemini-3.1-flash-lite-image`), keychain
credential `FAMtastic.Gemini.Image`, `$0.0336` per 1K image.

```bash
cd marketing/creative/plates
node generate-plates.mjs \
  --library ../campaign-assets/already-know-the-game/prompt-library.json \
  --out-root ../campaign-assets/already-know-the-game \
  --receipt ../campaign-assets/already-know-the-game/generation-receipt.json \
  --max-cost-usd 0.30 --concurrency 3 --retries 1
```

| id | argues | 1st pass | final |
|---|---|---|---|
| `akg-01-shutter-9x16` | The building is yours; the marking on the concrete belongs to the block | rejected | ok |
| `akg-02-slats-9x16` | atmosphere — comparison geometry carries it | ok | ok |
| `akg-03-board-9x16` | the board and the paint exist; the price is written nowhere public | ok | ok |
| `akg-04-counter-9x16` | atmosphere — the working surface | ok | ok |
| `akg-05-pole-9x16` | paste-up torn to staples, one clean rectangle where a sheet came off whole | ok | ok |
| `akg-06-desk-9x16` | atmosphere — the desk the paperwork happens on | rejected | ok |
| `akg-tex-xerox-9x16` | photocopy tooth | ok | ok |

**All 9 generations at $0.0336 = $0.3024.** Note that
`generation-receipt.json` reports **$0.2352** because it merges results by `id`
and therefore counts each plate once. The receipt is not wrong about what
exists; it under-reports what was billed. **$0.3024 is the figure to trust.**

### The two rejections, and why they were rejections

Both were caught **by looking at the image**, not by any check. Both passed
generation with HTTP 200 and a valid JPEG.

- **`akg-01`, first pass** — the "worn stencilled bay marking" came back as
  scuffed **letterforms** reading roughly `…AY`. The library's absolute no-text
  clause was already in the prompt and the model produced text anyway, because
  "bay marking" is a phrase whose most common referent is painted words. Fixed
  by naming the geometry instead of the object: *"one plain unbroken rectangle
  outline and one solid painted bar, nothing else … no letterforms, numerals,
  characters, glyphs, arrows, symbols or anything that could be mistaken for
  writing."* Second pass clean.
- **`akg-06`, first pass** — a yellow paint mark rendered as a glyph resembling
  a `7`, plus faint scratched marks in the lower left reading as handwriting,
  plus visible desk edges and dark corner vignetting (an excluded "border").
  Fixed by pressing the camera into the surface (*"no edge, rim, corner, side,
  leg or background is visible … the metal is the entire frame"*) and
  specifying *"one short straight chalky yellow paint dash — a plain solid
  stroke with no letterform, numeral, character, glyph or symbol shape to it."*
  Second pass clean, with one residual: a faint darker strip survives at the
  extreme left and right edges. It is invisible under the composited scrim in
  all three finished formats but is disclosed here rather than described as
  perfect.

**Lesson worth carrying:** a blanket "render zero text" clause is not enough
when the *subject noun* implies writing. Name the shape you want, not the
object that usually carries the shape.

---

## Stills — 18 files, 6 drops × 9:16 / 4:5 / 1:1

Composited through the Photoshop MCP bridge (Photoshop 2026 running).
All 18 are 8-bit PNGs exported via `ExportType.SAVEFORWEB`, ~20 MB total.

| Drop | Archetype | Plate | 9:16 | 4:5 | 1:1 |
|---|---|---|---|---|---|
| 01 address | `standard` | 01 shutter | 1080×1920 | 1080×1350 | 1080×1080 |
| 02 client list | `comparison` | 02 slats | 1080×1920 | 1080×1350 | 1080×1080 |
| 03 price | `standard` | 03 board | 1080×1920 | 1080×1350 | 1080×1080 |
| 04 bookable | `checklist` | 04 counter | 1080×1920 | 1080×1350 | 1080×1080 |
| 05 what survives | `standard` | 05 pole | 1080×1920 | 1080×1350 | 1080×1080 |
| 06 paperwork | `offer-card` | 06 desk | 1080×1920 | 1080×1350 | 1080×1080 |

Four archetypes across six drops, so no layout appears more than twice —
Rule 3 of the art direction, applied at campaign level.

### `grunge-frame.jsx` — why a campaign-local compositor exists

`famtastic-social-frame.jsx` composites a full-bleed plate only in its
`standard` path (`famApplyArt`). Every archetype layout — `offer-card`,
`split`, `stat`, `checklist`, `comparison`, `monument` — builds its own
document and draws onto flat palette ground, so a plate never reaches them.
This campaign needs **both**: rotated archetypes *and* a photographic grunge
surface under every frame.

Three other campaign lanes were rendering against the shared template at the
same time, so rather than change shared behaviour, `grunge-frame.jsx` evaluates
the template, reuses its component kit and archetype functions verbatim, and
adds only the compositing order the treatment needs:

```
ground → plate (cover-scaled) → ground scrim → xerox texture (SOFT_LIGHT)
       → torn band behind the type block → archetype or standard type stack
```

New objects it contributes: `agPlaceCover` (cover-scale + vertical shift),
`agScrim`, `agTornBand` (deterministic jagged paste-up strip), `agPaintMark`
(a stencil bar with a ragged end, replacing the hairline rule under the
eyebrow). All jitter is seeded — no `Math.random` — so a re-render is
byte-identical.

### Two shared-template defects found by looking, patched locally

Both were invisible in the bridge's success message and obvious in the render.
Both are **overridden inside `grunge-frame.jsx`**, not patched in the shared
file, because of the concurrent lanes. They should be fixed upstream later.

1. **`famChip` ignores its own tracking.** It sizes the badge block at
   `text.length * size * 0.62` and then sets the label at 140/1000-em tracking.
   `WEB BASICS` ran its final `S` clean off the right edge of the yellow block.
   The honest advance at that tracking is `0.62 + 0.14 = 0.76`.
2. **`famLayoutOfferCard` collides at 1:1.** Headline at `0.34 × panelHeight`
   and price baseline at `0.58` with the price sized off the *format's* display
   size means that on a short 1080×1080 panel the `$` cap climbed into the
   headline. The override spaces rows off panel height and caps the price
   against the panel rather than the format.

### Three composition defects found by looking, fixed in this lane

3. **Centre-crop swallowed every object plate.** A 9:16 plate cover-scaled into
   4:5 or 1:1 crops from the centre, which dropped the bay marking, the
   signboard and the flyer pole straight under the signature scrim — the
   accent disappeared from the frame entirely. Caught on the first square
   render. Fixed with a per-format `plateShift`.
4. **`famLayoutComparison` clips a second body line.** Its body block starts at
   `baseY + depth + 0.095H`, which at 4:5 and 1:1 puts the second line under the
   signature scrim; descenders were sliced. Fixed by giving drop 02 a single
   body line at 9:16 and none at 4:5 / 1:1, where the `PERMISSION` /
   `POSSESSION` labels carry it alone.
5. **Labels landed on the plate's own yellow stripe.** At 4:5 and 1:1 the
   comparison labels sat directly on the shutter's painted warning stripe —
   grey type on yellow paint. Fixed with `plateShift` (150 / 180) so the stripe
   sits behind the bars instead of behind the type.

### Per-frame values, and why they are not uniform

`bandOpacity` is tuned per drop and, for drop 03, per format (87 at 9:16, 96 at
4:5 and 1:1). This is not sloppiness: the crop changes what sits behind the
type. At 9:16 the torn band has empty concrete behind it and the translucency
is the point; at 1:1 the top edge of the yellow signboard rises into the band
and ghosts through at 87. Values are recorded in this file and in the render
calls rather than averaged into a single wrong number.

---

## Every claim, and where it comes from

| Claim, as it appears in copy | Source |
|---|---|
| "$199 for the first year … one focused website, a full year of managed hosting, and your domain registered for you, or the one you already own connected" | `backend/config/famtastic-products.json` → `FAM-FOOT-199`, `summary` + `entitlements` |
| "After the first year it is $9.99 a month" | same file → `FAM-HOST-999`, `price: 9.99`, `billing.activation: after_included_period` |
| "plus what the domain costs to renew" | same file → `FAM-FOOT-199.billing.domain_renewal_separate: true` |
| "business email on your own domain is a separate product" | same file → `FAM-BUSINESS-EMAIL`, `$99.00`, one-time |
| "ongoing maintenance is an add-on" | same file → `FAM-MAINTENANCE`, `$49.99/month`, recurring |
| "you can usually see it, you can often export some version of it, and you do not control it" | live post `who-owns-your-client-list-booking-app`, opening paragraph |
| "Access is a permission … not the same thing as a possession" | same post, same paragraph |
| "A name you cannot reach is a memory, not a client list" | same post, "What a client list actually is" |
| "a fifteen-minute audit" | same post: "in about fifteen minutes" |
| "on a marketplace … choosing between the listings in front of them. On your own page … whether to hire you at all" | live post `what-a-bookable-page-actually-needs`, opening |
| "two questions" + "trust here is mundane. Will they answer. Will the price hold. Will they be where they said they would be" | same post, "The two things a stranger is deciding" |
| "A domain name is that address … everything else hangs off it" | live post `what-is-a-domain-name` |
| "the scope is narrow on purpose" | live post `199-website-inclusions-and-boundaries`: "The offer is intentionally narrow so the price can remain accessible" |
| Platform-removal mechanism in drop 05 (policy change, automated flag, category decision) | Stated as a mechanism, not a statistic. No company named, no rate or frequency claimed. |

**No statistic appears anywhere in this campaign.** No conversion rate, no
percentage, no "X% of customers", no delivery-time promise. Every argument is
carried by a mechanism, which is house style and needs no citation.

**No competitor is named in prose, and no competitor's fees are stated.** Drop
02 and drop 04 describe marketplace and booking-platform behaviour generically,
taken from the live posts, which themselves say "what any specific platform may
do with your data is set by the agreement you accepted."

One brand name does appear in the campaign, and only as a URL: drop 05 links to
the already-published post `linktree-vs-real-website-what-you-trade-away`. The
caption prose says "a link-in-bio page", never the product name. The slug is an
existing live editorial asset, not a new competitive claim made by this
campaign — but it is disclosed here rather than left for someone to find.

All six blog URLs and the landing URL were curled on 2026-09-05 and return
HTTP 200. No `/web/` path appears in any published string — `/web/` is used
only for the read-only JSON:API query that enumerated the corpus.

---

## Open issues to hand back

1. **A live post contradicts the product config.** `what-does-199-website-include`
   describes Web Basics as "five core pages" and "A complete small-business
   website". `backend/config/famtastic-products.json` says **one focused
   landing-page website**. This lane followed the config, and routed drop 06 to
   `199-website-inclusions-and-boundaries` instead, which matches the config
   ("one focused business website … intentionally narrow"). **The contradiction
   itself is unresolved and needs an owner decision** — one of the two surfaces
   is wrong, and the live page is meant to be the contract.
2. **Two shared-template bugs are patched locally only.** `famChip` tracking
   and `famLayoutOfferCard` 1:1 spacing (above). Any other campaign using those
   components at those sizes will still hit them.
3. **Brand faces are still missing.** Inter and Space Grotesk are not installed,
   so every frame renders in `HelveticaNeue-CondensedBold` / `AvenirNext`. The
   Remotion tier loads real Inter, so these stills and any video are
   typographically inconsistent. Pre-existing, unchanged by this lane.
4. **The palette is argued, not tested.** Per the art direction's own known-gaps
   section, no palette in this system has been validated against a real
   audience. `shutter` is no exception.
5. **Cost is the published per-image rate, not an invoice line.** The Gemini
   `generateContent` response carries no dollar amount. $0.3024 = 9 successful
   generations × the provider's published $0.0336 for a 1K image, counted from
   the receipt.

## Not done here, on purpose

- **Nothing was queued to Postiz.** A later step of the flood plan owns that.
  `approval.publish` is `false` on all six drops. `scripts/queue-campaign-drops.py
  --dry-run` was run to validate the schedule shape; it makes no network call to
  Postiz and queued nothing.
- **No video.** This lane is stills only.
- **No scorecard.** There is no performance data yet; an empty scorecard would
  be a file pretending to be evidence.
