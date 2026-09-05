# Cheap Production Economics v1

**Version**: `famtastic.cheap-production.v1`
**Status**: Active — owner directive 2026-09-04
**Extends**: `docs/architecture/CAMPAIGN_ASSET_CASCADE_AND_DISTRIBUTION_V1.md`
**Companion**: `docs/playbook/RECIPES/ADOBE_CREATIVE_PRODUCTION.md`, `docs/marketing/PLATFORM_CREATIVE_THEMES_V1.md`

---

## The rule

> **Buy one premium thing per campaign. Recreate everything else for free, and remix.**

Owner directive, 2026-09-04: *"let's figure out how to do it for cheap and free,
buy a premium but recreate everything else for cheap, and remix and market."*

This is not a cost-saving compromise. It is the correct shape of the work: the
expensive tools are good at **originating** one distinctive thing, and terrible
value for **replicating** it twenty times. The free tools are the opposite. Pay
for origination, never for replication.

---

## What each surface actually costs

Costs are marginal — what one more asset costs, given what is already paid for.

### Free — $0 marginal, already paid for

| Tool | What it produces | Status |
|---|---|---|
| **Photoshop 2026** (MCP bridge) | Every still format, type set properly, layered PSD templates | **proven** 2026-09-04 |
| **Premiere Pro 2026** (MCP bridge) | Video assembly, text overlays, transitions, auto-reframe to every aspect | bridge present, proof in progress |
| **After Effects 2026** | Motion graphics, animated logo stings | installed, no bridge |
| **Audition 2026** | Voiceover cleanup, loudness to platform spec, music beds | installed, no bridge |
| **Media Encoder 2026** | Batch fan-out to every social spec via watch folders | installed, **no bridge needed** |
| **Illustrator / InDesign / Acrobat** | Vector art, proposals, lead magnets, customer PDFs | installed, no bridge |
| **Adobe Fonts** | Brand typography | subscription active, **zero fonts activated** |
| **Remotion** (local, ×2 installs) | Programmatic video from React — headless, deterministic, batchable | installed |
| **Blog art SVG engine** (`frontend/src/lib/blogArt.js`) | In-post visuals generated from content | shipped |
| **Postiz** (self-hosted) | Scheduling and publishing to 5 connected channels | running locally |

Eighteen Adobe applications are installed. As of 2026-09-04 exactly **one** was
under agent control. That is the largest single pool of unused capacity in the
whole operation, and it is already paid for.

### Cheap — cents per asset

| Tool | Cost | Use for |
|---|---|---|
| **Gemini Flash Lite (image)** | ~$0.0336 / image | Tier-2 multiplier: b-roll plates, backgrounds, texture. The workhorse. |
| **OpenAI `gpt-image-2`** | per-image, key present | When Gemini's composition is not good enough |
| **MUAPI** | keychain-authenticated, 59 skills | Specialist generation |

### Premium — finite, spend deliberately

| Tool | Budget | Use for |
|---|---|---|
| **HeyGen** | 488 credits remaining (2026-09-04); custom "FAMtastic Guide" photo avatar exists | **One** presenter/avatar spot per campaign. Never for variants. |
| **Poe** | needs `POE_API_KEY` | Escalation / bulk drafting tier |
| Flagship image or video model | per campaign | The one hero the campaign is built around |

**Unverified prices are marked as such.** Where a number is not measured, it says
so. Do not quote an estimated cost as a measured one.

---

## The remix engine

One campaign, produced correctly:

```
   ONE PREMIUM ANCHOR                       cost: the campaign's whole budget
   a HeyGen avatar spot, or one flagship hero image
                    │
                    ├── 4-8 cheap plates ................ ~$0.03 each = ~$0.20
                    │   (Gemini Flash Lite; dark, negative space, NO baked text)
                    │
                    ▼
   FREE COMPOSITION LAYER                   cost: $0
                    │
   ┌────────────────┼─────────────────┬──────────────────┐
   ▼                ▼                 ▼                  ▼
 PHOTOSHOP       PREMIERE          REMOTION          MEDIA ENCODER
 type + layout   assembly +        programmatic      batch fan-out
 every still     auto-reframe      variants at       to every spec
 format          to every aspect   scale
   │                │                 │                  │
   └────────────────┴────────┬────────┴──────────────────┘
                             ▼
        9:16  ·  4:5  ·  1:1  ·  16:9  ·  YouTube long  ·  Shorts
        TikTok  ·  X  ·  Facebook (still + video)  ·  IG feed + story
                             ▼
                  POSTIZ  (self-hosted, $0)
                             ▼
                    scorecard.json → what worked
                             ▼
              decides the NEXT campaign's one premium anchor
```

**Full campaign, every surface: well under $2.** That figure is the existing
cascade target and this model is how it is met.

### The premium asset is the REFERENCE, not just the first asset

Owner directive 2026-09-04: *"use premium asset, then build assets for cheap or
free using the premium as the reference. It's been working."*

This is the part that makes the model work, and it is easy to miss. The premium
anchor is not simply one expensive item sitting in a set of cheap ones. It is the
**style reference every cheap asset is matched against.** Buy the anchor first,
then derive.

Concretely, from the drop-06 rebuild that proved it:

1. **Buy the anchor.** One HeyGen presenter render off the existing "FAMtastic
   Guide" avatar. 12 credits.
2. **Read the anchor.** Its brand kit resolved the real accent (`#7CFC00`) and the
   real faces (Space Grotesk, Inter). Those values then governed everything
   downstream instead of being guessed.
3. **Match the cheap tier to it.** The five Gemini plates were brand-graded *in
   the prompt* — near-black ground, a single chartreuse practical, no text — so
   they were born matching the anchor rather than being colour-corrected toward
   it afterwards. $0.168.
4. **Compose free.** Remotion authored every word, colour and edge against the
   same reference. $0.

Total: **$0.168 + 12 credits** against a $2.00 target, and the result was
accepted where the previous unbranded attempt was rejected.

**Why the ordering matters.** If cheap assets are generated *before* the anchor
exists, they have nothing to match and you pay again to reconcile them — or worse,
ship a set that does not cohere. Cheap assets are cheap precisely because the
expensive decision was already made somewhere else. Generate the anchor, extract
its actual values (colour, type, grade, framing), then push those values into
every downstream prompt and composition as explicit constraints.

This also decides *what* to buy premium: buy the thing that carries the most
style information. A presenter take, a hero photograph, or a flagship key visual
sets colour, light and framing for a whole campaign. A single icon does not.

### Why generated text is never used

Every plate prompt must forbid baked-in text. Generated typography is unreliable
and always off-brand. **Type is set in Photoshop, over the plate.** This is also
why the ad frames in `marketing/creative/adobe-proofs/` carry deliberate empty
zones — those are plate slots, not unfinished layout.

---

## Reliability: what may sit in the critical path

Owner note, 2026-09-04: **Premiere has been known to freeze.**

A GUI application cannot be the backbone of a scheduled pipeline. It inherits
every failure mode of that application — hangs, modals, unresponsive waits.

| Need | Path | Why |
|---|---|---|
| Scheduled / batch / repeatable video | **Remotion** (headless, deterministic) | Runs unattended, reproducible from source, diffable |
| One-off hero, finishing, colour, audio | Premiere / After Effects / Audition | Best quality, human watching, a freeze is survivable |
| Stills | Photoshop bridge | Fast and proven — but still GUI-bound, so the timeout rule applies |

**Rules:**

- Bound every Adobe MCP call with a timeout and a retry limit. On exhaustion,
  **report and stop**.
- Never silently fall back to `ffmpeg` text burning. That silent fallback
  produced the unbranded drop-06 video that was rejected on 2026-09-04.
- A frozen app is a **BLOCKED / UNSETTLED** result, not a failed render. The work
  is unmeasured, not wrong. (Measurement Discipline, FAMtastic `CLAUDE.md`.)
- If a deadline is live and the GUI will not answer, fall back to **Remotion**.

---

## Where the money actually leaks

1. **Paying to replicate.** Generating twelve variants from a paid model when
   Premiere `auto_reframe_sequence` derives every aspect ratio from one master
   for free.
2. **Paying for what the subscription already covers.** Sixteen installed Adobe
   apps sat unreachable while cheaper, worse tools did their jobs.
3. **Re-researching.** Under `SERIES_FIRST_CONTENT_ORIGIN_V1`, the blog series'
   evidence list *is* the campaign's evidence list. Research is done once.
4. **Re-rendering because it was off-brand.** The most expensive asset is the one
   thrown away. Inter and Space Grotesk are still not installed, so every Adobe
   asset is currently one substitution away from the live site.

---

## Known gaps

- **Brand fonts are not installed.** Inter and Space Grotesk are absent; output
  substitutes HelveticaNeue-Condensed / AvenirNext. `SpaceGrotesk-*.woff2`
  already exist in `famtastic-hosting/public/fonts/` and need only conversion;
  Inter is on Adobe Fonts, included in the subscription and never activated.
- ~~No PNG optimizer installed~~ **CLOSED 2026-09-04.** `pngquant`, `optipng` and
  `zopflipng` are all absent, and none are needed. The bloat was self-inflicted:
  `doc.saveAs` with `PNGSaveOptions` writes uncompressed 24-bit PNG, while
  `doc.exportDocument(..., ExportType.SAVEFORWEB)` writes the same pixels
  compressed. Measured on identical artwork: **6.2 MB vs 48 KB**, roughly 130x,
  lossless, same dimensions. The four-format ad set went from 17 MB to 832 KB.
  `marketing/creative/templates/famtastic-social-frame.jsx` uses the correct
  path; never use `saveAs` for social output.
- **Premiere unproven**; auto-reframe is the load-bearing claim of this whole
  model and has not yet been demonstrated.
- **Media Encoder watch folders unbuilt** — the cheapest remaining win, requires
  no bridge.
- **Facebook video-in-post** capability not yet technically confirmed against the
  local Postiz stack.

---

## Change log

- 2026-09-04 — Created from owner directive. Grounded in the Adobe capability
  audit of the same day (18 apps installed, 1 reachable, 0 Adobe Fonts
  activated), the proven Photoshop bridge, and the existing 3-tier asset cascade.
