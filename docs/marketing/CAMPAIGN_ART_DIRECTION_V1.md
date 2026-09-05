# Campaign Art Direction v1

**Version**: `famtastic.campaign-art-direction.v1`
**Status**: Active — owner directives 2026-09-04
**Companions**: `docs/marketing/CHEAP_PRODUCTION_ECONOMICS_V1.md`, `docs/playbook/RECIPES/ADOBE_CREATIVE_PRODUCTION.md`, `docs/marketing/PLATFORM_CREATIVE_THEMES_V1.md`
**Implementation**: `marketing/creative/templates/famtastic-social-frame.jsx`

---

## Two corrections that created this document

Both were owner corrections to work produced on 2026-09-04, and both point at the
same failure: a system that produced *consistency* when it should have produced
*thought*.

> "I don't want to get stuck in this damn black and green. Campaigns are supposed
> to be thoughtful, not cookie cutter."

> "For blogs like this — *Why Gmail and Linktree Cost Your Business Revenue* — why
> isn't the title considered for the blog image creation? What graphic would be
> nice? Maybe a business card, a URL, something. Not enough thought is going here."

The first version of the template hardcoded `#070907` + `#7cfc00` and three
abstract geometries (nodes, grid, orbit), then stamped that combination on every
asset regardless of subject. It was internally consistent and said nothing.

---

## Rule 1 — Black and lime is the *site*, not every campaign

`#070907` + `#7cfc00` is the FAMtastic **site identity**. Design DNA v1 governs
the product surfaces: the portal, the app, the site itself. It is not an
instruction to make every campaign look like the site.

**What stays constant** — this is what makes an asset recognisably ours:

- the typographic system: condensed bold display, humanist sans body, tight
  tracking on display type, wide tracking on the eyebrow
- the layout grid: left-aligned, generous margin, eyebrow + rule, two-line
  headline, short body, signature block
- restraint: one glow at most, real negative space, art dissolving under type
  rather than competing with it
- the signature block: studio line + URL

**What varies by campaign**: the palette, the art concept, the texture.

### Shipped palettes

| Name | Ground / accent | Argued from |
|---|---|---|
| `famtastic` | `#070907` / `#7cfc00` | The site identity. Use when FAMtastic talks about FAMtastic. |
| `ghost-town` | `#17120d` / `#d9a441` | Amber dust on dark earth. A business that exists but cannot be found — heat, absence, weathering. |
| `salon` | `#1a1013` / `#e8b4b8` | Rose on plum. Skin-adjacent warmth for personal-service work; never clinical. |
| `trades` | `#0d1117` / `#ff7a1a` | Safety orange on blue-black. Work, not lifestyle. |
| `paper` | `#f4f1ea` / `#1f6f4a` | Ink on warm paper. Proposals, documents, LinkedIn — anything that must read sober rather than as an ad. |

A light ground is a first-class option, not an exception: the art system switches
from `SCREEN` to `MULTIPLY` automatically so graphics darken into paper instead
of vanishing.

**Adding a palette is an argument, not a preference.** Say what in the subject
produced it. "Amber because sun-bleached and abandoned" is a reason. "Blue
because it looks professional" is not.

---

## Rule 2 — The art comes from what the post actually argues

There are two kinds of graphic and they are not interchangeable.

**Atmosphere** (`nodes`, `grid`, `orbit`) sets a mood and belongs *behind* type,
dissolved into the ground. It does not say anything, and that is fine — a mood is
sometimes all a frame needs.

**Concept objects** are the subject of the image. They are drawn crisp at full
opacity because the reader is meant to look *at* them. They carry the argument.

### How to choose one

Read the post's **claim**, not its category, and ask what physical object the
claim is about.

| The post argues | The object | Implemented |
|---|---|---|
| A free email address costs you credibility | Two business cards: the same business, printed twice, once with an address anyone could have made and once with an address at a domain it owns | `business-cards` |
| Customers cannot find you / you do not own your address | A browser address bar with your own domain in it | `address-bar` |
| Booking commissions compound | A receipt or an invoice | not yet built |
| A link-in-bio page is not a website | A phone screen showing a list of links and nothing else | not yet built |

The `business-cards` object *is* the Gmail argument: nothing else has to be
explained. That is the standard to hit. If the best object you can think of is a
generic abstract pattern, the concept work is not finished.

**Every concept object takes real content**, not lorem text — the actual address,
the actual domain, the actual number. `cardBad: "elite_autocare24@gmail.com"`
against `cardGood: "quotes@eliteautocare.com"` is the whole post in one glance.

### Layout consequence

A concept object owns the right side of the frame, so the type gets the left
column only. The template measures this and shrinks the headline to fit
(`famFitSize`). Without it, a long headline slides under the artwork — which it
did, on the first ghost-town hero, and had to be caught by looking at the render
rather than by trusting the tool's success message.

---

## Rule 3 — Vary the composition, not just the colour

Owner correction 2026-09-04: *"there isn't enough variety in your designs. You
need to see how Cox does it or something."*

Correct, and it exposed that Rule 1 had only half-solved the problem. Every asset
still used **one composition** — eyebrow, rule, two-line headline, body,
signature — with the palette swapped. Changing colour is not variety. It is the
same poster in a different shirt.

### What cox.com actually does

Studied live. Across a single page they alternate at least seven compositions:
split panel with a photo bleeding to one edge, offer card with a badge chip and a
CTA pill, thin utility strip, three-up card grid, icon nav row, inline form band,
and price-as-hero.

The important part is *how* they get the variety: not bespoke art each time, but a
**small kit of reusable objects recombined** — chips, pills, tinted panels, cards,
rules. That is reproducible; taste is not.

### The component kit

| Component | What it does |
|---|---|
| `famPanel` | A block of held colour a section sits inside, optionally with an angled corner so the frame stops reading as stacked rectangles |
| `famChip` | Small-caps badge in a solid block — Cox's "SPECIAL OFFER" |
| `famPill` | A real CTA button object, instead of a bare URL in the footer |
| `famElevate` | Depth on a 2D surface. A dark ground cannot take a cast shadow, so it gets a lit edge; a light ground gets a real shadow. Same intent, opposite physics |
| `famText3D` | Extruded display type — a copy behind, offset, in accent |
| `famCircle` / angled `famPoly` | Non-rectangular shapes, so compositions are not all boxes |

### The archetypes

| Layout | Shape | Use when |
|---|---|---|
| `standard` | Left column, art right | The default essay-like statement |
| `offer-card` | Chip, headline, enormous price, terms, CTA pill, on an elevated angled panel | The campaign is actually asking for the sale |
| `split` | Full-bleed band of held colour with a diagonal cut, statement inside, explanation on the ground below | Fastest way out of "text on a field" |
| `stat` | One number very large, plus the sentence that gives it meaning | Only with a figure verifiable from the repo |
| `checklist` | Marker rows with hairlines, then a CTA pill | Answering "what do I actually get" |

Rotate archetypes across a campaign. A drop series that uses the same layout five
times is the cookie-cutter failure again, one level up.

**Owner note, same session:** *"start thinking in 3D effect on 2D surfaces, and
non-standard shapes and colors."* `famElevate`, `famText3D`, the angled panel
corner and the diagonal band cut are the first pass at this. It is not finished —
perspective, overlap, and true depth layering are still absent.

---

## Rule 4 — Prompting image models (OpenAI Cookbook techniques)

Applied from the [gpt-image-1.5 prompting guide](https://developers.openai.com/cookbook/examples/multimodal/image-gen-1.5-prompting_guide).
These upgrade `marketing/creative/plates/prompt-library.json`.

### Prompt structure

Order matters: **background/scene → subject → key details → constraints**, and
state the intended use ("this is an ad", "this is a blog hero") so the model sets
its own level of polish. For complex requests use short labelled segments or line
breaks, not one long paragraph. End with explicit exclusions — "no watermark",
"no extra text" — and an explicit preserve list.

### Photorealism

Use photography language, not quality adjectives:

- lens and framing: `50mm`, `35mm`, eye-level, close-up, wide, shallow depth of
  field, candid, unposed
- light: soft diffuse, golden hour, high-contrast, film grain
- material truth: real pores, fabric wear, weathering, honest everyday detail

**Avoid**: "8K", "ultra-detailed", "studio polish", heavy retouching, cinematic
grading, and anything implying staging. The guide's principle — *prompt as if a
real photo is being captured in the moment* — is the single most useful line in
it, and it is exactly right for an audience of working small businesses who can
smell a stock photo.

### Character consistency — brand characters

This is the route to a recurring FAMtastic character across a campaign:

1. Generate one **character anchor** image that fixes appearance, outfit,
   proportions and expression.
2. On every subsequent image, reference the anchor and state: *"Same character,
   new scene and action. Character appearance must remain unchanged."*
3. Lock identity explicitly and restate the lock **every iteration** to stop
   drift: *"Do not change her face, facial features, skin tone, body shape, pose,
   or identity in any way. Preserve her exact likeness, expression, hairstyle and
   proportions. Replace only the ..."*
4. Use `input_fidelity="high"` for larger scene edits.

The existing HeyGen "FAMtastic Guide" photo avatar is a *presenter*, which is a
different thing from an illustrated brand character. Both can exist; do not let
them drift into each other without deciding which is which.

### Text inside generated images

The guide says in-image text is viable with strict constraints: put literal text
in quotes or ALL CAPS, specify typography, demand *"verbatim rendering (no extra
characters)"*, spell tricky words letter-by-letter, and use `quality="high"` for
dense layouts.

**FAMtastic's rule stands anyway: type is set in Photoshop, not generated.** Not
because the model cannot do it, but because our type must be exact, editable,
re-renderable in five formats, and identical to the site. A generated headline is
a picture of a headline. Plates carry no text; they carry deliberate negative
space for type to land in.

### Style and brand control

Describe what must stay constant separately from what must change. For flat brand
graphics the guide's phrasing works well: *"clean, vector-like shapes, strong
silhouette, balanced negative space"*, plus *"flat design, minimal strokes, no
gradients unless essential"*. Add hard constraints on background and framing and
a "no extra elements" clause to prevent drift.

---

## Known gaps

- **Only two concept objects are built** (`business-cards`, `address-bar`). The
  receipt and phone-screen objects in the table above are named, not implemented.
- **`prompt-library.json` predates this document.** It was generated against the
  old black-and-green assumption and does not yet use the cookbook's structure,
  photorealism vocabulary, or anchor workflow. It needs a pass.
- **Brand faces are still missing.** Inter and Space Grotesk are not installed, so
  every palette renders in stand-in faces. HeyGen's brand kit already resolves the
  correct two, which makes the inconsistency more visible, not less.
- **No palette has been validated against a real audience.** These are argued,
  not tested. The campaign scorecard is where that gets settled.

## Change log

- 2026-09-04 — Created from two owner corrections: stop defaulting to black and
  green, and derive blog imagery from what the title actually argues. Palette and
  concept-object systems implemented in `famtastic-social-frame.jsx`; OpenAI
  Cookbook prompting techniques recorded for the plate pipeline.
