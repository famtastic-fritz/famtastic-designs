# CAMPAIGN: Web Basics "55 Cents" 17-Day — Manifest vs Reality Audit

Implements **SOCIAL_POSTING Step 1** ("Audit 17-day manifest vs reality"). Owner: Social Ops.
Audit date: **2026-08-23**. Manifest audited: `marketing/campaigns/55-cents-17-day/manifest.json`
(schema_version 1, generated 2026-08-12, 68 records = 17 days × 4 daily moments,
`public_publish_enabled: false`). Manifest was NOT modified by this audit.

Reference contracts applied: `docs/marketing/FAMTASTIC_MARKETING_FLOW_2026-08-12.md`
(stable content IDs shared by manifest, asset filenames, UTM `utm_content`, scheduler),
`docs/marketing/HEYGEN_CAMPAIGN_AUTOMATION.md` (asset registry conventions),
`docs/marketing/LOCAL_MODEL_AND_AGENT_ROUTING_2026-08-12.md`.

## Summary

| Metric | Count |
|---|---|
| Records total (manifest) | 68 |
| Channel-ready asset found on disk (by content_id) | 0 |
| Records with asset MISSING | 68 (100%) |
| State breakdown | idea: 68 · briefed/drafted/content_qa/seo_qa/media_ready/approved/scheduled/published/verified/measured/learned: 0 each |
| Content approvals true | 0 / 68 |
| Media approvals true | 0 / 68 |
| Publish approvals true | 0 / 68 |
| Records with ≥1 channel assigned | 0 / 68 (all `channels: []`, UTM source `pending_channel`) |
| Records with any provider_ids | 0 / 68 (all `{}`) |
| Records with any evidence entries | 0 / 68 (all `[]`) |

### Where assets were searched (before declaring MISSING)

1. `marketing/campaigns/55-cents-17-day/` — contains only `manifest.json`; no `assets/` dir.
2. Repo-wide glob `**/*55c-d*` — zero matches.
3. Grep `55c-d` excluding `node_modules` — only `manifest.json` and its generator `scripts/generate-17-day-marketing-manifest.py`.
4. `marketing/55-cent-campaign/` (asset library) — generic creative only; no filename carries any content_id.
5. `marketing/video/out/`, `marketing/video/src/scenes/` — one generic Remotion render + one scene file, not per-moment.
6. `marketing/adobe-pipeline/campaigns/offer-launch-55-cents.md` + `creative/` + `proofs/videos/` — offer-level proof assets, not per-moment.
7. `marketing/FAMtastic-Marketing-Asset-Library-2026-08-14.zip` listing — mirrors the generic library above.
8. `docs/marketing/**` and `marketing/avatar/`, `marketing/brands/famtastic/` — documentation, brand tokens, avatar art; no per-record social assets.

## Day-by-day table

Legend: Approvals c/m/p = content/media/publish (`✗`=false). Channels `—` = empty list.
Provider IDs `—` = empty object. Asset paths are relative to `marketing/campaigns/55-cents-17-day/`
and map to both channel ratios via `asset-map.days-1-3.json`. Days 1–3 rows were filled by the
Step 2 partial (2026-08-23): deterministic local re-cuts of approved seed creative
(`scripts/build-55-cent-day-assets.py`, copy reused verbatim from
`build-55-cent-social-assets.py`; no new claims/prices). Every remaining asset cell below is
MISSING because no file anywhere in the repo carries that record's content_id.

| Day | Moment | content_id | State | Channels | Asset (path or MISSING) | Approvals (c/m/p) | Provider IDs |
|-----|--------|------------|-------|----------|-------------------------|-------------------|--------------|
| 1 | teach (declaration) | 55c-d01-teach | media_ready | — | assets/55c-d01-teach.9x16.png · assets/55c-d01-teach.4x5.png | ✗/✗/✗ | — |
| 1 | challenge (declaration) | 55c-d01-challenge | media_ready | — | assets/55c-d01-challenge.9x16.png · assets/55c-d01-challenge.4x5.png | ✗/✗/✗ | — |
| 1 | prove (declaration) | 55c-d01-prove | media_ready | — | assets/55c-d01-prove.9x16.png · assets/55c-d01-prove.4x5.png | ✗/✗/✗ | — |
| 1 | invite (declaration) | 55c-d01-invite | media_ready | — | assets/55c-d01-invite.9x16.png · assets/55c-d01-invite.4x5.png | ✗/✗/✗ | — |
| 2 | teach (excuses) | 55c-d02-teach | media_ready | — | assets/55c-d02-teach.9x16.png · assets/55c-d02-teach.4x5.png | ✗/✗/✗ | — |
| 2 | challenge (excuses) | 55c-d02-challenge | media_ready | — | assets/55c-d02-challenge.9x16.png · assets/55c-d02-challenge.4x5.png | ✗/✗/✗ | — |
| 2 | prove (excuses) | 55c-d02-prove | media_ready | — | assets/55c-d02-prove.9x16.png · assets/55c-d02-prove.4x5.png | ✗/✗/✗ | — |
| 2 | invite (excuses) | 55c-d02-invite | media_ready | — | assets/55c-d02-invite.9x16.png · assets/55c-d02-invite.4x5.png | ✗/✗/✗ | — |
| 3 | teach (domain_basics) | 55c-d03-teach | media_ready | — | assets/55c-d03-teach.9x16.png · assets/55c-d03-teach.4x5.png | ✗/✗/✗ | — |
| 3 | challenge (domain_basics) | 55c-d03-challenge | media_ready | — | assets/55c-d03-challenge.9x16.png · assets/55c-d03-challenge.4x5.png | ✗/✗/✗ | — |
| 3 | prove (domain_basics) | 55c-d03-prove | media_ready | — | assets/55c-d03-prove.9x16.png · assets/55c-d03-prove.4x5.png | ✗/✗/✗ | — |
| 3 | invite (domain_basics) | 55c-d03-invite | media_ready | — | assets/55c-d03-invite.9x16.png · assets/55c-d03-invite.4x5.png | ✗/✗/✗ | — |
| 4 | teach (hosting_basics) | 55c-d04-teach | idea | — | MISSING | ✗/✗/✗ | — |
| 4 | challenge (hosting_basics) | 55c-d04-challenge | idea | — | MISSING | ✗/✗/✗ | — |
| 4 | prove (hosting_basics) | 55c-d04-prove | idea | — | MISSING | ✗/✗/✗ | — |
| 4 | invite (hosting_basics) | 55c-d04-invite | idea | — | MISSING | ✗/✗/✗ | — |
| 5 | teach (one_page_anatomy) | 55c-d05-teach | idea | — | MISSING | ✗/✗/✗ | — |
| 5 | challenge (one_page_anatomy) | 55c-d05-challenge | idea | — | MISSING | ✗/✗/✗ | — |
| 5 | prove (one_page_anatomy) | 55c-d05-prove | idea | — | MISSING | ✗/✗/✗ | — |
| 5 | invite (one_page_anatomy) | 55c-d05-invite | idea | — | MISSING | ✗/✗/✗ | — |
| 6 | teach (trust) | 55c-d06-teach | idea | — | MISSING | ✗/✗/✗ | — |
| 6 | challenge (trust) | 55c-d06-challenge | idea | — | MISSING | ✗/✗/✗ | — |
| 6 | prove (trust) | 55c-d06-prove | idea | — | MISSING | ✗/✗/✗ | — |
| 6 | invite (trust) | 55c-d06-invite | idea | — | MISSING | ✗/✗/✗ | — |
| 7 | teach (mobile) | 55c-d07-teach | idea | — | MISSING | ✗/✗/✗ | — |
| 7 | challenge (mobile) | 55c-d07-challenge | idea | — | MISSING | ✗/✗/✗ | — |
| 7 | prove (mobile) | 55c-d07-prove | idea | — | MISSING | ✗/✗/✗ | — |
| 7 | invite (mobile) | 55c-d07-invite | idea | — | MISSING | ✗/✗/✗ | — |
| 8 | teach (fit) | 55c-d08-teach | idea | — | MISSING | ✗/✗/✗ | — |
| 8 | challenge (fit) | 55c-d08-challenge | idea | — | MISSING | ✗/✗/✗ | — |
| 8 | prove (fit) | 55c-d08-prove | idea | — | MISSING | ✗/✗/✗ | — |
| 8 | invite (fit) | 55c-d08-invite | idea | — | MISSING | ✗/✗/✗ | — |
| 9 | teach (ecommerce_boundary) | 55c-d09-teach | idea | — | MISSING | ✗/✗/✗ | — |
| 9 | challenge (ecommerce_boundary) | 55c-d09-challenge | idea | — | MISSING | ✗/✗/✗ | — |
| 9 | prove (ecommerce_boundary) | 55c-d09-prove | idea | — | MISSING | ✗/✗/✗ | — |
| 9 | invite (ecommerce_boundary) | 55c-d09-invite | idea | — | MISSING | ✗/✗/✗ | — |
| 10 | teach (lead_capture) | 55c-d10-teach | idea | — | MISSING | ✗/✗/✗ | — |
| 10 | challenge (lead_capture) | 55c-d10-challenge | idea | — | MISSING | ✗/✗/✗ | — |
| 10 | prove (lead_capture) | 55c-d10-prove | idea | — | MISSING | ✗/✗/✗ | — |
| 10 | invite (lead_capture) | 55c-d10-invite | idea | — | MISSING | ✗/✗/✗ | — |
| 11 | teach (follow_up) | 55c-d11-teach | idea | — | MISSING | ✗/✗/✗ | — |
| 11 | challenge (follow_up) | 55c-d11-challenge | idea | — | MISSING | ✗/✗/✗ | — |
| 11 | prove (follow_up) | 55c-d11-prove | idea | — | MISSING | ✗/✗/✗ | — |
| 11 | invite (follow_up) | 55c-d11-invite | idea | — | MISSING | ✗/✗/✗ | — |
| 12 | teach (customer_portal) | 55c-d12-teach | idea | — | MISSING | ✗/✗/✗ | — |
| 12 | challenge (customer_portal) | 55c-d12-challenge | idea | — | MISSING | ✗/✗/✗ | — |
| 12 | prove (customer_portal) | 55c-d12-prove | idea | — | MISSING | ✗/✗/✗ | — |
| 12 | invite (customer_portal) | 55c-d12-invite | idea | — | MISSING | ✗/✗/✗ | — |
| 13 | teach (support_retention) | 55c-d13-teach | idea | — | MISSING | ✗/✗/✗ | — |
| 13 | challenge (support_retention) | 55c-d13-challenge | idea | — | MISSING | ✗/✗/✗ | — |
| 13 | prove (support_retention) | 55c-d13-prove | idea | — | MISSING | ✗/✗/✗ | — |
| 13 | invite (support_retention) | 55c-d13-invite | idea | — | MISSING | ✗/✗/✗ | — |
| 14 | teach (analytics) | 55c-d14-teach | idea | — | MISSING | ✗/✗/✗ | — |
| 14 | challenge (analytics) | 55c-d14-challenge | idea | — | MISSING | ✗/✗/✗ | — |
| 14 | prove (analytics) | 55c-d14-prove | idea | — | MISSING | ✗/✗/✗ | — |
| 14 | invite (analytics) | 55c-d14-invite | idea | — | MISSING | ✗/✗/✗ | — |
| 15 | teach (automation) | 55c-d15-teach | idea | — | MISSING | ✗/✗/✗ | — |
| 15 | challenge (automation) | 55c-d15-challenge | idea | — | MISSING | ✗/✗/✗ | — |
| 15 | prove (automation) | 55c-d15-prove | idea | — | MISSING | ✗/✗/✗ | — |
| 15 | invite (automation) | 55c-d15-invite | idea | — | MISSING | ✗/✗/✗ | — |
| 16 | teach (ai_assistance) | 55c-d16-teach | idea | — | MISSING | ✗/✗/✗ | — |
| 16 | challenge (ai_assistance) | 55c-d16-challenge | idea | — | MISSING | ✗/✗/✗ | — |
| 16 | prove (ai_assistance) | 55c-d16-prove | idea | — | MISSING | ✗/✗/✗ | — |
| 16 | invite (ai_assistance) | 55c-d16-invite | idea | — | MISSING | ✗/✗/✗ | — |
| 17 | teach (recap_conversion) | 55c-d17-teach | idea | — | MISSING | ✗/✗/✗ | — |
| 17 | challenge (recap_conversion) | 55c-d17-challenge | idea | — | MISSING | ✗/✗/✗ | — |
| 17 | prove (recap_conversion) | 55c-d17-prove | idea | — | MISSING | ✗/✗/✗ | — |
| 17 | invite (recap_conversion) | 55c-d17-invite | idea | — | MISSING | ✗/✗/✗ | — |

## Existing (unmapped) creative that can seed Step 2 production

These are NOT channel-ready per-moment assets (no content_id naming, no per-record QA/approval
state), but they are reusable inputs:

- `marketing/55-cent-campaign/assets/` — 14 statics: facebook-feed-1200x628.jpg, instagram-carousel-01..05 (hook/excuses/trust/math/cta), instagram-feed-1080x1350.jpg, stories-reels-1080x1920.jpg, tiktok-cover-1080x1920.jpg, video-frame stills 01..05.
- `marketing/55-cent-campaign/video/famtastic-55-cent-campaign-15s.mp4` — generic 15s promo render.
- `marketing/video/out/famtastic-55-cents-remotion.mp4` + `marketing/video/src/scenes/FiftyFiveCentsPortrait.tsx` — Remotion 9:16-style template proven for branded motion.
- `marketing/55-cent-campaign/source/photoreal-*.png` — photoreal owner scenes.
- `marketing/55-cent-campaign/creative-preview/2026-08-12/` — ad previews + CREATIVE_REVIEW.md.
- `marketing/adobe-pipeline/proofs/videos/offer-launch-55-cents-proof.mp4` (+ qa frame) — offer-level video proof.
- `marketing/avatar/famtastic-guide-v1.png` — Guide cartoon avatar for presenter/cartoon lane.

## Readiness script output (2026-08-23)

`python3 scripts/campaign-readiness.py` — full output (all PASS):

```
PASS required file marketing/campaigns/55-cents-17-day/manifest.json
PASS required file marketing/local-models.json
PASS required file marketing/brands/famtastic/brand.json
PASS required file marketing/engine/schemas/campaign-manifest.schema.json
PASS 17 days x 4 content moments = 68 records
PASS content IDs are unique
PASS record count matches records
PASS public publishing defaults off
PASS UTM content IDs match records
PASS three approval gates exist on every record
PASS unapproved records cannot imply release authorization
PASS campaign begins in draft-production state
PASS local command foundation is installed
PASS Qwen, GLM, and Gemma local lanes are installed
PASS shared Codex, Claude, and Shay contracts exist
READY draft production and private-provider tests may begin
GATED public posts, promotional sends, social OAuth, paid plans, and ad spend still require Fritz
```

## Gaps & risks

| # | Gap | Severity | Evidence | Blocks |
|---|-----|----------|----------|--------|
| G1 | 0 of 68 records have a channel-ready asset named by their stable content_id (required convention per `docs/marketing/17-DAY-CONTENT-AND-VIDEO-SPRINT.md:185`). All 68 rows MISSING. | HIGH | Search log above; manifest.json (all `evidence: []`) | Steps 2→4 |
| G2 | All 204 approval gates false (content/media/publish × 68). No record may advance past drafting toward scheduling without Fritz. | HIGH | manifest.json `approval` blocks | Step 4 (Fritz gate) |
| G3 | No channels assigned on any record (`channels: []`; UTM `source: pending_channel` × 68). Channel matrix undecided in-repo; Postiz pilot has no connected providers. | MEDIUM-HIGH | manifest.json; `docs/marketing/SOCIAL_AUTOMATION_HANDOFF_2026-08-12.md` (OAuth pending) | Step 3 |
| G4 | No provider IDs or evidence entries anywhere (`provider_ids: {}`, `evidence: []` × 68): no HeyGen job IDs, no test-post IDs, no QA artifacts bound to records. | MEDIUM | manifest.json; HEYGEN_CAMPAIGN_AUTOMATION.md field list | Steps 5–7 |
| G5 | Existing generic creative (see seed list) is unapproved/unmapped and does not satisfy the per-moment contract; reusing it verbatim would violate the stable-content-ID rule and risks the silent-skip failure mode this recipe exists to prevent. Days 1–3 remain the mandated first production batch (assisted publishing rule). | MEDIUM | Seed-list paths above; FAMTASTIC_MARKETING_FLOW_2026-08-12.md "Distribution reality" | Step 2 scope |

Per SOCIAL_POSTING rules: every calendar day is currently **BLOCKED** (no asset), reported here explicitly — none skipped silently.

## Change log

- 2026-08-23 — Step 1 audit created by Social Ops dispatch.
- 2026-08-23 — Step 2 partial: days 1–3 re-cuts landed (24 variants); days 4–17 still MISSING.
