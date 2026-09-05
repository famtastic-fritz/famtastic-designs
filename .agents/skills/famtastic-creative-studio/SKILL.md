# FAMtastic Creative Studio

Produces every branded visual asset — stills, blog heroes, campaign frames — on
the Adobe subscription FAMtastic already pays for, and routes video, image and
avatar work to the cheapest tool that can actually do the job.

## Why this exists

On 2026-09-04 a campaign video shipped with no branding because it was rendered
by an `ffmpeg` build that has neither `drawtext` nor `subtitles`. At that moment
Photoshop 2026 was **running on the same machine with a live MCP bridge**, and
eighteen Adobe applications were installed on a paid subscription.

The agent was not careless — it read `marketing/providers.json` as required, and
that file marked all seven Adobe rows pending because it described only the Adobe
*cloud APIs*. So: **a capability absent from the first file agents read does not
exist.** That is the failure this skill prevents.

## When to invoke this

- Any campaign still, ad, story frame, blog hero, or social graphic
- Any time you are about to burn text into an image with `ffmpeg`, or reach for a
  paid image API to make something the design system can draw for free
- Before choosing any creative provider — read `marketing/providers.json` first,
  and remember a `*_pending` cloud-API row says nothing about the desktop app of
  the same name

## Required reading before producing anything

| Document | What it settles |
|---|---|
| `docs/marketing/CAMPAIGN_ART_DIRECTION_V1.md` | Palettes, layout archetypes, concept objects, image-model prompting |
| `docs/marketing/CHEAP_PRODUCTION_ECONOMICS_V1.md` | What each tool costs, and the premium-as-reference ordering rule |
| `docs/playbook/RECIPES/ADOBE_CREATIVE_PRODUCTION.md` | Tool sequences, known bugs, the GUI-reliability split |
| `BRAND.md` / `VOICE.md` | Audience, the educate-never-compete stance, house voice |

## The three rules that matter most

**1. Buy the premium anchor FIRST, then match everything cheap to it.**
The anchor is not one item in the set — it is the style reference. Generate it,
extract its real values (accent, faces, grade, framing), then push those into
every downstream prompt as explicit constraints. Cheap assets generated *before*
an anchor exists have nothing to match, so you pay twice to reconcile them.

**2. Vary the composition, not just the colour.**
Five palettes with one layout is the same poster in a different shirt. Rotate
archetypes across a campaign.

**3. The art argues the post's actual claim.**
`nodes`/`grid`/`orbit` are atmosphere — they set a mood and say nothing. A post
claiming a free email address costs credibility should show a **business card**,
because the card is the argument. If the best idea you have is an abstract
pattern, the concept work is not finished.

## How to produce a still

**Precondition: Adobe Photoshop must be RUNNING.** The bridge drives the live
app; it cannot launch it. Then:

```
mcp__photoshop-bridge__ps_run_script:
  $.evalFile("<repo>/marketing/creative/templates/famtastic-social-frame.jsx");
  return famRender({ slug, layout, palette, outDir, ... });
```

| Input | Values |
|---|---|
| `palette` | `famtastic` · `ghost-town` · `salon` · `trades` · `paper` |
| `layout` | `standard` · `offer-card` · `split` · `stat` · `checklist` · `comparison` · `monument` |
| `concept` | `business-cards` · `address-bar` (crisp objects that carry the argument) |
| `theme` | `nodes` · `grid` · `orbit` (atmosphere, dissolved under type) |
| `formats` | `story-9x16` · `feed-4x5` · `square-1x1` · `wide-16x9` · `blog-hero` |

Dimension comes from `famExtrudeRect` / `famExtrudeBar` (isometric solids with
shaded side faces) over `famPerspectiveGrid`, so objects have mass and stand on
something. Light is always upper-left; break that and the frame stops reading as
solid.

## Routing: which tool for which job

| Need | Tool | Cost |
|---|---|---|
| Stills, type, blog heroes, ad frames | **Photoshop bridge** (this skill) | $0 — subscription |
| Repeatable / scheduled / batch video | **Remotion** (headless, deterministic) | $0 |
| One-off hero video, finishing, colour, audio | Premiere / After Effects / Audition | $0 — subscription |
| Background plates, b-roll, texture | **Gemini Flash Lite** | ~$0.0336/image |
| Presenter / avatar anchor | **HeyGen** | credits — premium tier |
| Design exploration, variants, design-system extraction from a URL, decks | **superdesign** skill | account login required |

**superdesign vs the Photoshop path.** They are not competitors. `famtastic-social-frame.jsx`
renders exact, deterministic, on-brand frames at zero marginal cost and is the
right tool for a final campaign asset. Reach for superdesign when you need design
*judgement* — exploring directions, comparing variants across models, pulling a
design system off a reference URL, or building a deck. Bring what it finds back
into the deterministic renderer; do not ship its output as the campaign frame.

Two caveats: it needs an interactive `npx --yes @superdesign/cli@latest login`
that only the owner can complete, and its install carried a Snyk "Critical Risk"
rating — the skill itself is markdown with no executable code, but it instructs
you to execute an always-latest remote npm package with full permissions. See the
`superdesign` row in `marketing/providers.json`.

**gpt-image-2 has two routes.** OpenArt MCP is *proven* in this repo
(`marketing/campaigns/and-if-it-is-rattler-lifers/`, 315 credits) but its MCP
transport is not attached to every session — that is a transport gap, not a
missing capability. The same model is reachable directly through the OpenAI Image
API with a key already in the macOS Keychain under `FAMtastic.OpenAI.Image`.
Prefer the direct route when OpenArt credits are the only thing OpenArt adds.

**Never** burn text with `ffmpeg drawtext` (the local build lacks it) and never
silently fall back to a weaker tool — that silent fallback is what shipped the
unbranded video. A missing capability fails loudly.

**Never** ask an image model to bake in text. Generated typography is unreliable
and off-brand. Plates carry deliberate negative space; type is set in Photoshop.

## Bugs you do not need to rediscover

- **ASCII only** in `ps_create_text`. A non-ASCII character either throws
  `ExtendScript: Required value is missing` or renders mojibake (`55¢` came back
  as `55¬¢`). Spell out "cents". A failed call still leaves an empty text layer.
- Export with `exportDocument`/SaveForWeb, **never** `doc.saveAs` with
  `PNGSaveOptions` — identical artwork, 48 KB vs 6.2 MB.
- The parameter is `fontFamily` (a PostScript name), not `font`.
- `textItem.position` is the **baseline**, not the top edge.
- Format tuple order is `[W,H,M,ES,HS,HL,BS,FS,HT]` — `F[7]` is footer size,
  `F[8]` is headTop. Confusing them renders the signature at 640pt.
- Batching more than ~2 documents in one `ps_run_script` call trips the
  AppleEvent timeout. Split the calls.
- Premiere's MCP bridge needs a manual **Window → Extensions → MCP Bridge (CEP) →
  Start Bridge** click each launch. The app running is necessary but not
  sufficient, and no agent can click it without Screen Recording permission.

## Non-negotiable accuracy

Verified from `backend/config/famtastic-products.json` — do not contradict it:

- **`FAM-FOOT-199` ($199)** = ONE focused landing-page website + ONE year of
  managed hosting + first-year domain registration, or connecting a domain the
  customer already owns. That is the whole bundle.
- Business email is **`FAM-BUSINESS-EMAIL`, a separate $99 product.**
- Maintenance is **`FAM-MAINTENANCE`, an upsell.** Not included.

Also: no invented statistics (three unsourced figures were found and stripped
from a campaign draft on 2026-09-04); never name a competitor as a target or
state their prices as fact; `/web/` is the backend admin prefix and must never
appear in a public URL — curl every URL before burning it into a frame.

## Verify before claiming success

Look at the render. Every defect found on 2026-09-04 — an email address
overflowing a card, a headline sliding under artwork, a 640pt signature — was
invisible in the tool's success message and obvious in the image. A tool
reporting success is not evidence the asset is correct.

For anything animated or lazy-loaded, use
`site-studio-next/scripts/capture/render-capture.mjs` and report `UNSETTLED`
rather than `failed` when a measurement has not settled.
