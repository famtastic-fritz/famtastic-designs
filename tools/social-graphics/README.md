# Social graphics

Renders on-brand promo graphics for the $199 offer at exact platform pixel
sizes. Copy and design live together in `posts.json`, so the caption you paste
and the image you upload can't drift apart.

```bash
cd tools/social-graphics
node src/render.mjs                  # everything → out/
node src/render.mjs --id=price-led   # one post
node src/render.mjs --size=square    # one size
```

No install step. It uses the Chromium already on the machine (override with
`CHROMIUM_PATH`) and has no npm dependencies at all.

## What comes out

| Size | Pixels | Use |
|---|---|---|
| `square` | 1080×1080 | Facebook and Instagram feed |
| `portrait` | 1080×1350 | Instagram feed — taller posts get more screen, so more reach |
| `story` | 1080×1920 | Facebook and Instagram stories |
| `link` | 1200×630 | Link previews / Open Graph |

Each post lists the sizes it should render at, and carries its `caption` in the
same entry.

## What this can and can't produce

It produces **typographic and graphic design** — your colors, your type, real
layout. For a price-led offer that is the right format: a big honest `$199`
reads at thumbnail size in a feed, where a stock photo does not.

It **cannot produce photographs or illustrations**. There is no image model
behind this — every pixel is drawn from CSS and SVG. If you want a picture of a
person, that has to be a real photo you own.

Which points at the asset that will actually outperform all of these: **before
and after screenshots of sites you have really built**. That is genuine proof,
costs nothing, and beats any designed graphic for this offer. Get the customer's
permission and use it.

## Editing

**Copy** — edit `posts.json`. A single `*starred phrase*` in a headline renders
in lime; that is the only markup.

**Design** — edit `src/templates.mjs`. Brand tokens at the top mirror
`frontend/src/index.css`, so changing a color there and here keeps site and
social in agreement.

**After any copy change, open the PNG.** Sizes are budgeted against the
available height, and a much longer headline or support line can still push the
layout. The three ratios behave differently — the 1200×630 link format has only
~470px of usable height and deliberately drops the support line.

## Fonts

`fonts/` holds Outfit (display) and Instrument Sans (body), both SIL Open Font
License, licenses included. They stand in for the site's Space Grotesk and
Inter, which are not bundled here.

For an exact brand match, drop `SpaceGrotesk-Bold.ttf` and `Inter-Regular.ttf` /
`Inter-Bold.ttf` into `fonts/` and update the three filenames in `loadFonts()`
in `src/render.mjs`. Fonts are inlined as base64 data URIs, so rendering never
touches the network and output is reproducible.

## Reproducibility

The background constellation is seeded per post (`seed` in `posts.json`), so
re-running produces the same image rather than quietly generating a new one.
Change a post's `seed` if you want a different background for that post.
