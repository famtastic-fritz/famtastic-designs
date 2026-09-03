# RECIPE: Social Posting Engine

**Outcome**: Campaign content flows to social channels on a schedule, gets published, verified, and attributed — without Fritz pushing each post.
**Trigger**: Active campaign calendar (first: the stalled 17-day sprint).
**Owner**: Social Ops agent (hire pending) · **Gates**: real publishes need Fritz approval per campaign, not per post.
**Grounded in**: Postiz local stack + Facebook provider-proven (CAPABILITY_REGISTRY), Instagram business conversion recorded 2026-08-12, `marketing/campaigns/55-cents-17-day/manifest.json` exists, HeyGen + Adobe pipelines documented in `docs/marketing/`.

## Steps

| # | Step | Owner | Definition of done | Evidence | Status |
|---|------|-------|--------------------|----------|--------|
| 1 | Audit 17-day manifest vs reality | Social Ops | Day-by-day table: asset exists (path) / missing; gaps listed | Table committed to `RECIPES/CAMPAIGN_17DAY.md` | ✅ DONE 2026-08-23 — 68/68 records audited; **0 channel-ready assets bound to content_ids**; all 204 approval gates false; 0 channels assigned. Receipt: `RECIPES/CAMPAIGN_17DAY.md` + `campaign-readiness.py` READY/GATED output. Generic seed creative exists but unmapped (`marketing/55-cent-campaign/assets/*.jpg`, `marketing/video/out/famtastic-55-cents-remotion.mp4`) — step 2 decision needed from Fritz: re-cut vs fresh. |
| 2 | Fill asset gaps via existing pipelines (HeyGen video, Adobe creative) | CMO + Social Ops | Every calendar day has ≥1 channel-ready asset w/ safe areas | Asset paths resolve on disk | ✅ DONE 2026-08-23 — days 4–17 completed via deterministic local re-cuts (`scripts/build-55-cent-days-4-17-assets.py`): 112 additional variants → **all 68 records `media_ready`**, 136 assets on disk (`marketing/campaigns/55-cents-17-day/assets/`), zero files <5k, dimensions verified (1080×1920 / 1080×1350). Manifest states updated in commit `932eff3b`. No publishing, no network sends. Receipt: manifest.json state counts + CEO disk verification 2026-08-23. |
| 3 | Channel matrix & credentials check | Fritz + CEO | FB live (proven), IG connected, others explicitly deferred w/ reason | Channel list in Postiz + screenshot | ✅ DONE 2026-08-25 — ALL FIVE channels connected (receipt: git 6a1a47b8 + docs/CHANGELOG.md "All five social channels connected"): facebook provider-proven (Integration row, token to 2026-10-10); Instagram, X (OAuth 1.0a), YouTube (testing mode, live `channels?mine=true` HTTP 200), TikTok (SANDBOX — tokens expire ~daily until app audit) connection-proven per owner session's documented probes. Caveats: no screenshot in repo; CEO has not independently re-verified the three new tokens; publish evidence still FB-only (step-5 scope). HARD RULE unchanged: Instagram connects @famtasticdesigns ONLY (never @famtstic). RESOLVED 2026-09-03 — this risk was REAL and was the single cause of nine days of total publishing failure. The resize was never done; the orchestrator stayed OOM-dead (exit 137, 13 restarts, last healthy boot 2026-08-25) and every scheduled post sat in QUEUE. Fixed by `colima stop && colima start --cpu 4 --memory 8` (host RAM 91.5% → 47.8%). LESSON: an open risk with no owner and no verification date is a prediction, not a mitigation. |
| 4 | Schedule batch in Postiz (approval-gated) | **Fritz approves** → Social Ops schedules | ≥3 days queued ahead at all times | Postiz queue state | ✅ DONE 2026-09-03 for `cost-is-not-the-reason` — 4 drops / 16 provider records created and converted to schedule, every record read back as QUEUE (13:00Z×5, 14:30Z×3, 17:00Z×4, 19:30Z×4). Campaign-agnostic runner `scripts/queue-campaign-drops.py --campaign <slug>` replaces per-campaign scripts. 17-day campaign remains ◐ PARTIAL 2026-08-25 — days 1–3 queued as DRAFTS (12 records, `postiz_draft_id` in manifest+DB). Executor built + locally validated (see AUTOMATION_RELIABILITY.md §2); conversion to schedule stays GATE→☐ until Fritz approves a bounded batch. |
| 5 | Publish + verify delivery per post | Social Ops | Post ID captured; delivery verified (existing FB verification pattern) | Ledger row per post | ✅ FACEBOOK + INSTAGRAM DONE 2026-09-03 — drop-01 (09:00 ET) and drop-02 (10:30 ET) both confirmed `PUBLISHED` by provider read-back, the first live posts this pipeline has produced. ◐ X BLOCKED (HTTP 402 credits depleted, isolated via minimal single-variable test 2026-09-03; billing action needed, not code). ◐ TIKTOK BLOCKED (token fixed 2026-09-03; app not approved for public posting is a separate TikTok developer-app audit status). ◐ YOUTUBE BLOCKED (OAuth token expired 2026-08-25, reconnect pending). Prior status below was written while the publishing worker had been dead since 2026-08-25, so "mechanism ready" was never testable. ◐ MECHANISM READY 2026-08-25 — `backend/scripts/publish-executor.php` converts approved drafts to schedule IN PLACE, verifies QUEUE by read-back, writes evidence; hard double-gated (env + owner flag), idempotent. Not yet run against real drafts. |
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
- 2026-08-25 — Steps 4–5 mechanism: publish executor (`backend/scripts/publish-executor.php`) built and locally validated — draft→schedule conversion IN PLACE (`PUT /posts/{id}/status`), QUEUE read-back verification, evidence JSON per run, hard double gate (`FAMTASTIC_MARKETING_PUBLISH=true` + `--i-have-owner-publish-approval`), idempotent reruns. Local proof: `.artifacts/publish-executor/20260825T124006Z-4950/evidence.json` + AUTOMATION_RELIABILITY.md §2. Real days 1–3 drafts untouched; prod run blocked on owner bounded-batch approval + prod Postiz env keys (currently unset).
- 2026-08-25 (heartbeat ~16:56Z) — Step 3 board corrected from facebook-only ◐ to ✅ five-channel DONE after reconciling operator commit 6a1a47b8 (registry row had already been updated to connection-proven ×5; this recipe board was never touched by that session — ledger drift found and fixed by CEO). TikTok sandbox daily re-auth + orchestrator OOM recorded as constraints on steps 4–5.

- 2026-09-03 — **ROOT CAUSE of all prior failure found.** The Postiz `orchestrator`
  (Temporal-backed publishing worker) had been OOM-killed (`exit code 137`) inside a
  3GiB colima VM since 2026-08-25 — 13 restarts, zero log output, last healthy boot
  predating the campaign. Scheduled posts accumulated in QUEUE and none ever
  published. Fixed by `colima stop && colima start --cpu 4 --memory 8` (host RAM
  91.5% → 47.8%); orchestrator booted clean, all Temporal task queues RUNNING.
  This is the risk recorded in step 3 on 2026-08-25 whose resize was never done.
- 2026-09-03 — Defects fixed above the worker, each real but none the cause:
  record approval gates opened to a single env arming switch
  (`FAMTASTIC_MARKETING_PUBLISH`); per-platform Postiz `settings` added (TikTok
  `privacy_level`/`duet`/`stitch`/`comment`/`brand_*`/`content_posting_method`/
  `autoAddMusic`, Instagram `post_type`, X `who_can_reply_post` — Facebook needs
  none, which is why the historical facebook-only script was the only one that ever
  worked); X copy cut from 434–685 chars to 260–273 against its 280 limit (it had
  passed draft validation and would have failed silently at publish);
  per-integration sibling scheduling (Postiz creates one record PER INTEGRATION and
  returns only the first id, so a 5-channel drop was scheduling 1 channel and the
  read-back check reported it as 4/4 verified); stale-date guard (a stored date
  survives the status change, so converting a backdated draft publishes instantly).
- 2026-09-03 — 20 stale duplicate records from an earlier attempt were soft-deleted
  before reviving the worker; a recovered worker drains its backlog, and ten of them
  were already past their publish time.

