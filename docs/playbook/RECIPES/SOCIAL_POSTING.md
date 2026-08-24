# RECIPE: Social Posting Engine

**Outcome**: Campaign content flows to social channels on a schedule, gets published, verified, and attributed — without Fritz pushing each post.
**Trigger**: Active campaign calendar (first: the stalled 17-day sprint).
**Owner**: Social Ops agent (hire pending) · **Gates**: real publishes need Fritz approval per campaign, not per post.
**Grounded in**: Postiz local stack + Facebook provider-proven (CAPABILITY_REGISTRY), Instagram business conversion recorded 2026-08-12, `marketing/campaigns/55-cents-17-day/manifest.json` exists, HeyGen + Adobe pipelines documented in `docs/marketing/`.

## Steps

| # | Step | Owner | Definition of done | Evidence | Status |
|---|------|-------|--------------------|----------|--------|
| 1 | Audit 17-day manifest vs reality | Social Ops | Day-by-day table: asset exists (path) / missing; gaps listed | Table committed to `RECIPES/CAMPAIGN_17DAY.md` | ✅ DONE 2026-08-23 — 68/68 records audited; **0 channel-ready assets bound to content_ids**; all 204 approval gates false; 0 channels assigned. Receipt: `RECIPES/CAMPAIGN_17DAY.md` + `campaign-readiness.py` READY/GATED output. Generic seed creative exists but unmapped (`marketing/55-cent-campaign/assets/*.jpg`, `marketing/video/out/famtastic-55-cents-remotion.mp4`) — step 2 decision needed from Fritz: re-cut vs fresh. |
| 2 | Fill asset gaps via existing pipelines (HeyGen video, Adobe creative) | CMO + Social Ops | Every calendar day has ≥1 channel-ready asset w/ safe areas | Asset paths resolve on disk | 🔄 IN PROGRESS 2026-08-23 — days 1–3 done per CEO re-cut decision: 24 verified variants (12 records × 9x16+4x5) in `marketing/campaigns/55-cents-17-day/assets/`, states → `media_ready`, sidecar `asset-map.days-1-3.json`; days 4–17 still MISSING. No publishing, no network sends. |
| 3 | Channel matrix & credentials check | Fritz + CEO | FB live (proven), IG connected, others explicitly deferred w/ reason | Channel list in Postiz + screenshot | ◐ PARTIAL 2026-08-23 — facebook CONNECTED (DB-verified Integration row, token to 2026-10-10); channel-health card ships live state to Campaign Operations; other platform creds present in env, OAuth clicks pending Fritz; HARD RULE recorded: Instagram connects @famtasticdesigns ONLY (never @famtstic). |
| 4 | Schedule batch in Postiz (approval-gated) | **Fritz approves** → Social Ops schedules | ≥3 days queued ahead at all times | Postiz queue state | GATE→☐ |
| 5 | Publish + verify delivery per post | Social Ops | Post ID captured; delivery verified (existing FB verification pattern) | Ledger row per post | ☐ |
| 6 | Attribute: UTMs on all links; GA4 campaign views | CEO | Clicks/visits attributable per day+channel | GA4 report path | ☐ |
| 7 | Weekly review loop | CEO | What shipped vs planned; top post by attributed visits; next-week adjustments | Standup report section | ☐ |

## Rules
- A day with no asset is reported BLOCKED that day — never skipped silently. (This recipe exists because silence killed the last sprint.)
- Reuse campaign articles/blog posts as source content before creating new.
- Safe areas + branded templates already proven — do not reinvent.

## Failure paths

| Where | If it fails | Fallback |
|---|---|---|
| Publishing API error | Retry ×2 → mark failed → owner alert | Manual post w/ Fritz approval; log root cause |
| Asset pipeline outage | Missing asset for scheduled day | Pull from asset library backlog; flag day as degraded, still ship something on-brand |

## Approval gates
Step 4 initial schedule per campaign; any NEW channel connection.

## Change log
- 2026-08-22 — Created to unstick stalled 17-day sprint; codifies accountability that was missing.
- 2026-08-23 — Step 1 DONE: full 68-record audit landed in CAMPAIGN_17DAY.md. Reality: the campaign never had bound assets — it stalled at idea state. Social Ops agent hired (`fam-social-ops.md`).
- 2026-08-23 — Step 2 partial: days 1–3 re-cuts landed via `scripts/build-55-cent-day-assets.py` (local Pillow only, TIER 1). Receipt: CAMPAIGN_17DAY.md days 1–3 rows + `marketing/campaigns/55-cents-17-day/asset-map.days-1-3.json`.
