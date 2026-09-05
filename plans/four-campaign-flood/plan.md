# Four-Campaign Flood + Delivery Automation

**Purpose**: Turn a full warehouse into a running delivery system. As of 2026-09-05 there are 98 live blog posts, 7 films, 38 stills and 21 plates — and **zero posts scheduled**. That is the gap this closes.

**Goal**: Four campaigns built and queued across the next 8 days, plus a recurring job that keeps producing blogs, campaigns and creative without being asked. Success = a non-empty queue every day for 8 days, every asset traceable to a live blog post, and a cron that refills it.

## Tasks

- [ ] C1 — Booksy-client campaign (barbers, nail techs, braiders, estheticians)
- [ ] C2 — Commerce-instinct campaign (grunge; implied audience, never named)
- [ ] C3 — Late-adopter campaign (video-heavy; also for 1:1 sending)
- [ ] C4 — Proof-first campaign ("see it before you pay")
- [ ] Automation: recurring blog → campaign → creative → queue job
- [ ] Schedule all four across an 8-day calendar
- [ ] Post-run review: what shipped, what failed, what to adjust

**Status**: active
**Started**: 2026-09-05
**Ended**: —
**Branch / worktree**: `main`, no worktree — marketing content only, no frontend or backend source changes.

## Execution

Four campaign lanes in parallel, one automation lane. Each campaign owns its own
directory under `marketing/campaigns/<slug>/` and its own creative under
`marketing/creative/campaign-assets/<slug>/`. No lane writes to another's paths.

## Hard constraints carried from this session

**Channels.** Only Facebook and Instagram publish today. YouTube's OAuth token is
expired and TikTok is not approved for public posting — both need the owner.
Queue to the working channels; do not manufacture failures.

**Product truth**, verified in `backend/config/famtastic-products.json`:
`FAM-FOOT-199` ($199, "55 cents a day") = ONE focused landing-page website + ONE
year of managed hosting + first-year domain registration, or connecting a domain
the customer already owns. **That is the whole bundle.** Business email is a
separate $99 SKU. Maintenance is an upsell. Never imply either is included.

**No invented statistics.** Three unsourced figures were stripped from a draft
today. Argue the mechanism instead — it is the house style and needs no citation.

**Never name or attack a competitor.** Booksy is the *context* for C1's audience,
not a target. Describe market patterns generically; never state a competitor's
fees as fact. BRAND.md: educate, never compete.

**No unbacked promises.** A "within 48 hours" delivery claim is live on five old
posts with no SLA anywhere in the catalog. Do not repeat it.

**Every URL curled before use.** `/web/` is the backend admin prefix and must
never appear in a public URL.

**Series-first.** A campaign may only link to a blog post that is already live.
98 posts exist; use them.

**Grade to the anchor.** `marketing/creative/heygen/reference-tokens.json` —
mean luminance 150-175, olive `#7FB449` at 1-2% of frame. Campaigns with their
own argued palette (ghost-town, grunge) may diverge, but must say so in writing.

**Verify by looking.** Six automated checks written today returned false results,
including one that reported 15 passes when zero had passed. `ffprobe` and a green
exit are not evidence. Extract frames and look at them.

## Research

Blog corpus: 98 posts, 11 series. `docs/marketing/CAMPAIGN_ART_DIRECTION_V1.md`
(palettes, archetypes, concept objects), `docs/marketing/CHEAP_PRODUCTION_ECONOMICS_V1.md`
(premium anchor as style reference), `docs/playbook/RECIPES/CAMPAIGN_PRODUCTION.md`.

## Review

Post-run: which drops queued, which errored, cost per campaign, and one
adjustment each lane recommends for the next run.

## Skills

`famtastic-creative-studio`, `famtastic-voice`, `hyperframes`, `blog-cluster`,
`blog-strategy`, `gpt-image-2-style-library`, `diagram-design`, `critique-*`.

## Proof

Queue read back from the Postiz API, not from a script's own report. Asset paths
with dimensions. Measured spend per campaign.
