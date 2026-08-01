---
name: famtastic-graphics
description: Generate on-brand FAMtastic Designs imagery — social posts, blog and OG images, and placeholder artwork for proof sites and customer builds. Use whenever a task needs a graphic, a hero image, a social card, an OG/share image, or a stand-in for a photo that does not exist yet. Also use when asked what visuals are possible, or to change the look of existing generated graphics.
---

# FAMtastic graphics

Everything visual in this repo is rendered from code — HTML/CSS through Chromium
for raster output, or SVG emitted directly. There is no image-generation model
available. Read the constraint below before promising anything.

## The one hard constraint

**You cannot generate photographs or illustrations of people, places, or
objects.** No stock imagery, no AI photos. Everything here is typography,
geometry, gradients, and colour.

That is usually fine and sometimes better — a large honest `$199` reads at
thumbnail size in a feed where a stock photo turns to mush. But say so plainly
rather than implying a photo is coming. When a task genuinely needs photography,
the answer is real photos the business owns: for FAMtastic specifically, the
strongest asset is **before/after screenshots of sites actually delivered**,
which outperform any generated graphic and cost nothing.

## Two tools, different jobs

### 1. Social / marketing graphics — `tools/social-graphics/`

Raster PNGs at exact platform pixel sizes. Copy and design live together in
`posts.json`, so the caption posted and the image uploaded can't drift apart.

```bash
cd tools/social-graphics
node src/render.mjs                  # everything → out/
node src/render.mjs --id=price-led   # one post
node src/render.mjs --size=square    # one size
```

Sizes: `square` 1080×1080, `portrait` 1080×1350, `story` 1080×1920,
`link` 1200×630 (also the correct blog/OG share image).

To add a graphic, append an entry to `posts.json` — `id`, `seed`, `eyebrow`,
optional `price`, `headline`, optional `support`, `sizes`, `caption`. A single
`*starred phrase*` in a headline renders in lime; that is the only markup.

### 2. Placeholder artwork — `tools/social-graphics/src/placeholder.mjs`

Deterministic abstract SVG, seeded from a business name. Use where a real photo
does not exist yet: proof sites, customer builds before assets arrive, blog
headers, empty states.

```js
import { placeholderSvg, placeholderDataUri } from './src/placeholder.mjs';

placeholderSvg({ seedText: 'Rivera Plumbing', variant: 'a', width: 1600, height: 900 });
placeholderDataUri({ seedText: 'Rivera Plumbing', variant: 'b' });  // for <img src>
```

`variant` is `a` | `b` | `c`, matched to the three proof directions, or pass an
explicit `palette` of `{bg, accent, second}`. Same inputs always produce the
same artwork, and the three variants differ from each other — so a business's
three proof designs look like three designs, not one recoloured.

Note the generated proof sites in `ProofCampaignService` currently contain **no
images at all** (grep `<img` returns nothing). That is the gap this exists to
fill.

## Brand

Tokens mirror `frontend/src/index.css`. Change one place, change the other, or
the site and its marketing drift apart.

| Token | Value |
|---|---|
| Background | `#0a0a0a` |
| Surface | `#111111` |
| Lime accent | `#7cfc00` |
| Sky accent | `#38bdf8` |
| Display face | Space Grotesk (site) / Outfit (bundled stand-in) |
| Body face | Inter (site) / Instrument Sans (bundled stand-in) |

`tools/social-graphics/fonts/` holds the stand-ins with their OFL licences.
For exact brand match, drop `SpaceGrotesk-Bold.ttf` and Inter into that folder
and update the three filenames in `loadFonts()` in `src/render.mjs`.

## Always look at the output

**Render it, then open the PNG or SVG and actually view it.** This is not
optional and it is not satisfied by the command exiting zero.

Three real defects shipped past a clean exit code during this tool's
construction, each invisible without looking:

- the price glyph collided with the eyebrow pill and pushed the footer off the
  canvas entirely;
- 1200×630 could not fit price, headline, and support in its ~470px of usable
  height, so text overlapped the footer rule;
- three placeholder variants all rendered the same colour, because their SVG
  gradient `id`s collided in a shared document.

Every one of those exits 0 and writes a file. If you render six graphics, view
six graphics — not one and an assumption about the rest.

Layout sizes are budgeted against available height, so a much longer headline
can still overflow. After any copy change, look again.

## Where each artefact belongs

| Need | Use | Size |
|---|---|---|
| Facebook / Instagram feed post | social-graphics | `square` or `portrait` |
| Story | social-graphics | `story` |
| Blog post header / OG share image | social-graphics | `link` |
| Proof-site hero, per direction | placeholder | 1600×900, `variant` a/b/c |
| Customer build, assets not supplied yet | placeholder | seed on business name |

## Scope

Generating and committing graphics is ordinary work. Publishing them — posting
to social accounts, deploying to production — is not covered here and follows
`docs/FRONTEND_DEPLOYMENT.md` and its authorization rules.
