# Series-First Content Origin Standard v1

**Version**: `famtastic.series-first-origin.v1`
**Status**: Proposed — owner directive 2026-09-04, not yet implemented
**Extends**: `docs/architecture/CAMPAIGN_ASSET_CASCADE_AND_DISTRIBUTION_V1.md`
**Audience**: Any agent or human producing campaigns, blog content, or creative

---

## 1. The problem: the process runs backwards

Today's production order is **campaign first, content second**:

```
campaign thesis → social drops → creative assets → (blog referenced as an afterthought)
```

Owner directive, 2026-09-04:

> "Blogs and blog series can also shape the campaigns that go out. I think our
> process was backwards. We define a campaign based on the well-thought-out
> series, then the research we run to build image gen and video for marketing —
> heck, even inclusion into the blog series itself."

### Evidence this is real, all from 2026-09-03/04

| Failure | Cause |
|---|---|
| A live Facebook post linked to `/blog/why-running-business-on-gmail-and-linktree-costs-revenue` for a full day, 404ing | The campaign referenced an article that had never been written. Campaign-first guarantees this class of bug. |
| Blog posts have **zero images**, while `cost-is-not-the-reason/` holds 7 videos and 10 images | Two pipelines that never talk. Campaign creative never reaches the blog; blog content never informs campaign creative. |
| Research is done twice | Blog briefs carry an "Evidence list (verified live)" and a claims policy; campaigns carry their own `research.json`. Same work, two stores, no shared source. |
| drop-06 was the one clean case | It was built **from** the published gmail/linktree post — blog → video → campaign. The one time the order was reversed, it worked. |
| Two incompatible "series" concepts | `series_id` (campaign arc) and `field_blog_series` (editorial journey) are unconnected despite meaning the same thing. |

---

## 2. The reversal

```
TIER 0 — EDITORIAL ORIGIN  (new; this document)
  A researched blog series is defined first: thesis, audience, customer job,
  the arc of N parts, and ONE research pass with sources and claims policy.
                              │
                              ▼
TIER 0.5 — CONTENT           (the series is written and published FIRST)
  N posts at 700-1000 words. Each part identifies the visual moments it needs
  (diagram, comparison, texture break) as a by-product of being written.
                              │
        ┌─────────────────────┼─────────────────────┐
        ▼                     ▼                     ▼
   BLOG ART            CAMPAIGN CREATIVE       VIDEO SCRIPTS
   (SVG blocks,        (1:1, 9:16 stills       (derived from the
   diagrams, back      derived from the        series' own argument,
   INTO the posts)     same research)          not written fresh)
        │                     │                     │
        └─────────────────────┼─────────────────────┘
                              ▼
TIER 1-3 — EXISTING ASSET CASCADE (unchanged)
  Flagship anchor → Gemini multiplier → local deterministic assembly.
  What changes is only that Tier 1 now knows WHAT to anchor on.
                              │
                              ▼
CAMPAIGN ASSEMBLY
  Campaign thesis = series thesis. Each drop maps to a part of the series.
  Each tracked link points at a post that ALREADY EXISTS AND IS LIVE.
                              │
                              ▼
MEASUREMENT → feeds the next series' Tier 0
  scorecard.json per campaign; which parts actually drew traffic decides
  what the next series argues.
```

**This extends the existing cascade rather than replacing it.** Tiers 1-3 of
`CAMPAIGN_ASSET_CASCADE_AND_DISTRIBUTION_V1` are unchanged and still correct.
The only change is that the flagship anchor is now chosen by a researched
editorial argument instead of by campaign intuition.

---

## 3. What this fixes structurally

- **A campaign can no longer link to an article that does not exist.** The
  series is published before the campaign that promotes it is assembled. The
  broken-link class of bug becomes impossible by ordering, not by vigilance.
- **Research is done once.** The series' evidence list and claims policy are
  the campaign's evidence list and claims policy.
- **Creative serves both surfaces.** Art produced from the series' argument
  goes into the posts *and* into the drops, instead of campaign assets sitting
  unused while blog posts run bare.
- **Depth precedes distribution.** A campaign inherits a thought-through
  argument instead of manufacturing one at posting time.

---

## 4. The two-series collision — must be resolved before implementing

Two unrelated concepts currently share the name "series":

| Concept | Field | Scope | Example |
|---|---|---|---|
| Campaign arc | `series_id` in `posting-schedule.json` | Groups campaigns | `cost-web-basics-launch` |
| Editorial journey | `field_blog_series` in Drupal | Groups posts | "The Small-Business Automation Series" |

Under a series-first model these must relate explicitly. Recommended:

- **The editorial series is the parent.** A campaign declares which blog series
  it distributes, e.g. `editorial_series: "The Small-Business Automation Series"`.
- Keep `series_id` for campaign-to-campaign sequencing (the ghost-town-follows-
  cost-is-not-the-reason relationship), which is a genuinely different axis.
- Never rename either field silently — both are load-bearing in shipped code
  (`BlogPostPage.jsx` reads `field_blog_series`; `queue-campaign-drops.py` and
  `score-campaign.py` read `series_id`).

---

## 5. Open questions before this becomes active

1. **Where does the series brief live?** Options: a new
   `marketing/blog/series/<slug>/series-brief.md`, or extend the existing
   per-post `brief.md` pattern upward. Needs a decision.
2. **Does the campaign manifest gain a required `editorial_series` field?**
   If yes, `new-campaign.py --validate` should enforce that the named series
   exists and has published parts before the campaign can be armed.
3. **Should a campaign be blocked from arming if its linked posts are not
   live?** This would have prevented the 2026-09-03 404 outright. Strong
   candidate for a hard gate in `queue-campaign-drops.py`.
4. **Art derivation**: is blog art generated from the same source as campaign
   stills, or produced separately with shared research? Affects whether the
   SVG art system and the image pipeline share a module.

---

## 6. Status

Proposed only. Nothing in this document is implemented. The existing
campaign-first path continues to work and is unchanged until a decision is
made on §5.

Recorded from owner directive 2026-09-04. Tracking item lives in
`~/Development/FAMtastic/plans/social-posting-capabilities/plan.md`.
