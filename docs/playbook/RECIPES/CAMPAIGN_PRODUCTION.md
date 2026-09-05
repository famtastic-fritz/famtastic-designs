# RECIPE: Campaign Production

**Outcome**: A researched argument becomes a published series, then a full set of stills and videos across every platform, for well under $2 per campaign.
**Trigger**: A blog series worth distributing, or a campaign the business needs.
**Owner**: any agent · **Gates**: publishing, spend above the ceiling, and pricing claims. Producing assets needs no gate.
**Grounded in**: `docs/architecture/SERIES_FIRST_CONTENT_ORIGIN_V1.md`, `docs/marketing/CHEAP_PRODUCTION_ECONOMICS_V1.md`, `docs/marketing/CAMPAIGN_ART_DIRECTION_V1.md`, `docs/playbook/RECIPES/ADOBE_CREATIVE_PRODUCTION.md`, `.agents/skills/famtastic-creative-studio/SKILL.md`.

---

## The order, and why it is not negotiable

Two orderings were learned the hard way on 2026-09-04/05, and both are now rules.

**Series before campaign.** A live Facebook post linked to an article that had
never been written and 404'd for a full day. Campaign-first guarantees that class
of bug. The series is researched and published first; the campaign distributes
what already exists.

**Anchor before cheap tiers.** The premium asset is not one item in the set, it
is the style REFERENCE the cheap tiers are graded against. Generate cheap assets
before the anchor exists and they have nothing to match, so you pay twice to
reconcile them.

```
RESEARCH ─→ SERIES ─→ PUBLISH ─→ ANCHOR ─→ CHEAP TIERS ─→ ASSEMBLY ─→ SCHEDULE ─→ MEASURE
                                    │                                                │
                                    └────────── reference values ────────┐           │
                                                                          ▼           ▼
                                              every plate + still + video graded to it
                                                                                      │
                                          what actually drew traffic decides ─────────┘
                                                    the next series
```

---

## Steps

| # | Step | Command / tool | Cost | Gate |
|---|---|---|---|---|
| 1 | Research the argument once | `blog-cluster plan`, live SERP, primary sources | $0 | — |
| 2 | Write and publish the series | `scripts/publish-blog-draft.py --dry-run` then `--confirm` | $0 | **human `--confirm`** |
| 3 | Verify every link is live | `python3 scripts/qa-content-links.py` | $0 | — |
| 4 | **Buy the anchor** | HeyGen presenter take of the "FAMtastic Guide" avatar | ~12 credits | spend |
| 5 | **Extract reference values** from the anchor | accent, faces, grade, framing → a tokens file | $0 | — |
| 6 | Generate cheap plates, graded to the anchor **in the prompt** | `node marketing/creative/plates/generate-plates.mjs` (Gemini Flash Lite) | ~$0.034/image | — |
| 7 | Flagship image only where a plate will not do | `swift website-delivery-swarm/openai_image_worker.swift --execute --max-cost-usd <n>` | ~$0.20/image | spend ceiling |
| 8 | Compose stills, every format | `famRender({...})` through the Photoshop bridge | $0 | — |
| 9 | Assemble video, every format from one master | `npx remotion render src/index.ts <Composition> <out>` | $0 | — |
| 10 | Critique before shipping | `critique-typography`, `-visual-hierarchy`, `-composition`, `-color` | $0 | — |
| 11 | Schedule | `python3 scripts/queue-campaign-drops.py --campaign <slug>` | $0 | **owner approves the batch** |
| 12 | Measure → feeds step 1 of the next campaign | `scorecard.json` | $0 | — |

**Typical campaign: ~$0.20 plus about a dozen HeyGen credits.** The drop-06
rebuild came in at $0.168 plus 12 credits against a $2.00 target.

---

## Adding a video is adding a config object

The video system is data-driven on purpose. A new drop is one object in
`marketing/video/src/drops/`, not a new component:

```ts
export const myDrop: DropConfig = {
  slug, title, source,            // a drop with no source is not a drop
  palette, paletteArgument,       // argued from the subject, never a preference
  ctaUrl,                         // curl it BEFORE it is burned into pixels
  scenes: [ /* plate, presenter, split, stat, offer-card, checklist, statement, outro */ ],
};
```

Then render each aspect from the same master. If a drop needs a new component,
that is a signal the kit is missing an archetype — not that this drop is special.

**Rotate archetypes.** A drop that uses the same layout five times is the
cookie-cutter failure one level up.

---

## Tool routing

| Need | Tool | Cost |
|---|---|---|
| Stills, type, blog heroes | Photoshop bridge | $0 |
| Repeatable / batch video | **Remotion** | $0 |
| One-off hero, finishing, colour, audio | Premiere / After Effects / Audition | $0 |
| Plates, b-roll, texture | Gemini Flash Lite | ~$0.034 |
| Flagship image | OpenAI `gpt-image-2` direct (cost-capped worker) | ~$0.20 |
| Presenter anchor | HeyGen | credits |
| Design exploration, variants | `superdesign`, `ui-ux-pro-max` | login / $0 |
| Structured review | `critique-*` suite | $0 |

Never burn text with `ffmpeg drawtext` — the local build lacks it. Never let a
missing capability degrade silently into a weaker tool; that is what shipped an
unbranded video. A missing capability fails loudly.

---

## Gates

Producing assets needs no approval. These do:

1. **Publishing** — blog `--confirm`, and the campaign batch in Postiz.
2. **Spend** above the campaign ceiling. Use `--max-cost-usd` on the image worker;
   it is the only image route with a hard ceiling built in.
3. **Pricing and scope claims.** Verified from `backend/config/famtastic-products.json`:
   `FAM-FOOT-199` is **one focused landing-page website, one year of managed
   hosting, and first-year domain registration or connecting an owned domain.**
   Business email is a separate $99 SKU (`FAM-BUSINESS-EMAIL`); maintenance is an
   upsell (`FAM-MAINTENANCE`). Never claim either is included.
4. **Anything naming a competitor.** Educate, never attack. Describe market
   patterns generically; never state a competitor's prices as fact.

---

## Failure paths

| Where | Symptom | Do this |
|---|---|---|
| Photoshop bridge | `No such element` | No document open — `ps_new_document` first |
| Photoshop bridge | `Required value is missing` | Non-ASCII in text. Spell out "cents". Delete the empty layer it left |
| Photoshop bridge | AppleEvent timeout | Batching too many docs per call. Split to ~2 |
| Premiere MCP | `ping` times out | The CEP panel's **Start Bridge** button was not clicked this launch. No agent can click it without Screen Recording. Report `BLOCKED`, fall back to Remotion — never to ffmpeg |
| Keychain | read hangs | Query needs BOTH service and account. Service alone stalls on a picker |
| Gemini image | 404 | `/v1beta/interactions` is dead; use `:generateContent`. `imageSize: '2K'` is rejected by flash-lite |
| Any render | "it completed" | Completion is not correctness. ffprobe it, extract frames, and look |

---

## Verify by looking

Every defect found on 2026-09-04/05 was invisible in a tool's success message and
obvious in the image: an email address overflowing a card, a headline sliding
under artwork, a signature rendering at 640pt because a format tuple was indexed
one place off. Run the `critique-*` suite, and look at the frames.

An unsettled or in-flight measurement is reported `UNSETTLED`, never `failed`.

---

## Known gaps

- **The two tiers are typographically inconsistent.** Remotion renders real Inter
  (loaded from Google Fonts); Photoshop substitutes HelveticaNeue-Condensed
  because Inter is not installed as a system font. Adobe Fonts has Inter and zero
  activations. This is the cheapest open fix in the whole pipeline.
- **Premiere is unproven** — bridge blocked on a manual in-app click.
- **Media Encoder watch folders unbuilt** — would fan one master out to every
  social spec with no bridge at all.
- **Content QA is manual**, not scheduled.

## Change log

- 2026-09-05 — Created. Consolidates the series-first ordering, the
  premium-as-reference ordering, the tool routing, the verified product facts,
  and the failure paths learned across the 2026-09-04/05 session.
