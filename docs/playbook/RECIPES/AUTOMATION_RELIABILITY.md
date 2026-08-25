# RECIPE: Automation Reliability

**Outcome**: The cron/queue/alert layer is trustworthy — alerts mean something, publishes execute under owner gates, and everyone knows what dies when the MacBook closes.
**Trigger**: AUTOMATION_RELIABILITY work, alert storms, publish execution, queue jobs.
**Owner**: @fam-ops · **Gates**: publishing requires BOTH `FAMTASTIC_MARKETING_PUBLISH=true` env AND `--i-have-owner-publish-approval` flag backed by Fritz's bounded-batch approval; never deploy from this lane without separate authorization.

## 1. Worker-late race fix — VERIFIED 2026-08-25

CEO review gap #4 ("automation mostly automates alerts about itself": 237 of 267
outbox sends were false-positive worker-late pages) is fixed and proven:

- **Judgment**: a worker is late only when `next_due < now` **AND**
  `last_finished < now - 1800s` (`WORKER_LATE_GRACE_SECONDS`,
  `LifecycleOperationsService.php:199-214`). A due-but-running worker on the
  shared */5 cadence no longer trips. 1800s covers two full cycles of the
  slowest worker (lifecycle_protection, +900s) plus jitter.
- **Provenance**: committed `f623fdab` (on origin/main); prod deploy
  `aece5778+` contains it (`aece5778..f623fdab` is empty → prod at-or-newer).
- **Guard**: `scripts/e2e-worker-late-guard.sh` (wraps
  `backend/scripts/e2e-worker-late-guard.php`) — PASS locally:
  stale worker alerted once / mid-run worker not flagged / second sweep
  idempotent. Evidence:
  `.artifacts/lifecycle-runs/1787659129-62485/evidence.json`.
- Rule going forward: any new liveness check must key off completion
  telemetry + grace, never raw `next_due`.

## 2. Publish executor — BUILT + LOCALLY VALIDATED 2026-08-25

`backend/scripts/publish-executor.php` (drush php:script) closes SOCIAL_POSTING
steps 4–5 mechanically: approved drafts → scheduled posts → verified delivery
state. It does NOT decide anything; it only executes what the DB says the owner
already approved.

### Selection
`approval_publish = 1 AND postiz_draft_id <> '' AND postiz_scheduled_id = ''
AND provider_state NOT IN ('scheduled','published')`, ordered by day,
bounded by `--limit` (default 12, hard cap 25). Bounded batches are part of
the gate contract.

### Conversion (in place)
Primary mechanism is Postiz's dedicated endpoint
`PUT /public/v1/posts/{id}/status {"status":"schedule"}` (docs.postiz.com/
public-api/posts/change-status): keeps the stored post id, content, media, and
date, restarts the publishing workflow. No delete+recreate needed on any
supported build. States seen in read-back: DRAFT / QUEUE / PUBLISHED / ERROR
(QUEUE = scheduled).

Resilience paths, all recorded per record:
- Draft already QUEUE → counted as success with zero mutations
  (`already_scheduled`) — makes reruns after a mid-run crash safe.
- Draft id missing → adopted by `utm_content=` marker match; else record is
  marked `provider_state='missing_draft'` and reported BLOCKED (never silent).
- Conversion succeeded but read-back ≠ QUEUE → `provider_state='verify_failed_*'`.
- HTTP ≥500 / transport errors retried with backoff (local Postiz cold starts).

### Bookkeeping
Schema (`update_8038`): `postiz_scheduled_id`, `provider_state`
('' | 'scheduled' | 'published' | 'missing_draft' | 'verify_failed_*'),
`published_at` (set only when an actual PUBLISHED state is confirmed), index
`publish_state`. Manifest sync mirrors `postiz_scheduled_id` +
`postiz_scheduled` evidence entries into
`marketing/campaigns/55-cents-17-day/manifest.json` when records convert.

### Gates (hard, in code)
Refuses to run — printing exactly which gates are missing, exit non-zero,
zero reads/sends/mutations — unless BOTH:
1. env `FAMTASTIC_MARKETING_PUBLISH=true`
2. CLI flag `--i-have-owner-publish-approval`

All three refusal combinations were exercised and print the refusal.

### Provider config
- `FAMTASTIC_POSTIZ_BASE_URL` (default `http://127.0.0.1:4007/api/public/v1`)
- `FAMTASTIC_POSTIZ_API_KEY`; on loopback hosts only, falls back to fetching
  the org key from the local Postiz postgres container (queue-script pattern;
  `POSTIZ_PG_CONTAINER` to override container name). Off-loopback without an
  env key → hard fail. Keys are never printed or committed.

### Local validation evidence (2026-08-25)
Against local Postiz v2.22.1 (127.0.0.1:4007), synthetic records
`55c-synth-exec-{a,b,c}` dated **2099** (cannot fire even if abandoned):

| Proof | Result |
|---|---|
| Gate refusals (no gates / flag-only / env-only) | REFUSED each time, nothing touched |
| Draft→schedule conversion ×4 total | `scheduled_in_place`, read-back QUEUE |
| Read-back verification | fresh listing asserted QUEUE for every conversion |
| Idempotency (pre-scheduled draft, missing bookkeeping) | `already_scheduled`, zero mutations |
| Selftest teardown | revert→DRAFT (workflow terminated), DELETE, fresh-listing absence check, DB rows removed — zero residue in Postiz and DB |

Evidence JSONs (schema `famtastic.publish-executor.v1`):
`.artifacts/publish-executor/20260825T123855Z-4385/evidence.json` (first clean PASS)
and `.artifacts/publish-executor/20260825T124006Z-4950/evidence.json`
(PASS including idempotency case). `--selftest` reproduces the whole loop any
time (gate-protected like every other mode).

### What remains before the real prod run (owner-gated)
1. **Owner approval**: Fritz reviews the queued week (days 1–3 drafts), sets
   bounded-batch approvals (`approval_publish=1` on chosen records via
   Campaign Gates), states the batch limit out loud.
2. **Prod env**: set `FAMTASTIC_POSTIZ_BASE_URL` + `FAMTASTIC_POSTIZ_API_KEY`
   (+ `FAMTASTIC_POSTIZ_BASE_URL` pointing at the prod Postiz) in prod
   settings/env — currently unset by design. Never commit keys.
3. Run `drush php:script backend/scripts/publish-executor.php -- --limit=<approved>`
   WITH both gates on prod; evidence lands in `.artifacts/publish-executor/<run>/`.
4. Days 1–3 real drafts stay untouched until then; executor candidates exclude
   them while `approval_publish=0` (verified: all 68 records still have
   `approval_publish=0` post-validation).

## 3. Laptop-bound inventory (what dies when the lid closes)

| Surface | Dies? | Consequence |
|---|---|---|
| Postiz local stack (docker) | YES | Scheduled posts stop firing; executor can't reach provider |
| Publish executor runs | YES | Only run manually/on demand anyway |
| CEO heartbeat agent (launchd, 2h) | YES | No session reports |
| Local Studio builds | YES | Build workers idle |
| Prod crons (lifecycle-run, maildir import, drush cron, cert renew) | no | Keep running server-side |
| Notification outbox dispatch | no | Server-side |

Mitigation direction (not built): move Postiz to an always-on host before
publishing becomes load-bearing. Until then treat every schedule as
"fires only while the laptop is open" — schedule days ahead, verify daily.

## 4. Local Postiz failure modes (observed 2026-08-25)

- **502-while-healthy**: nginx answers 502 while docker health = healthy —
  the API backend (pm2 `backend`, port 3000 inside the container) is down or
  booting; frontend (4200) being up proves nothing about the API.
- **Mastra cold-start DDL race**: image v2.22.1 lazily creates `mastra_*`
  tables at boot; concurrent first-boot DDL can fail
  (`MASTRA_STORAGE_PG_CREATE_TABLE_FAILED`, "Connection terminated
  unexpectedly") and kill the backend. Symptom trail lives in
  `/root/.pm2/logs/backend-error.log`. Fix observed: `docker exec postiz pm2 restart backend`
  (tables exist afterwards; subsequent boots fine). Under host CPU ~100% this recurred.
- Executor policy: retry 5xx/timeouts ×4 with exponential backoff; treat
  repeated 502 as provider-down → BLOCKED report, never silent skip.

## Failure paths

| Where | If it fails | Fallback |
|---|---|---|
| Provider unreachable (502 loop) | Executor marks run failed, writes evidence | Check pm2 logs, restart backend, rerun — idempotency makes rerun safe |
| Draft vanished from provider | Adopted by utm_content, else `missing_draft` BLOCKED | Requeue drafts via queue script; reconcile manifest |
| Read-back ≠ QUEUE | `verify_failed_*` on record | Inspect Postiz UI; do NOT re-approve blind |

## Change log
- 2026-08-25 — Created. Worker-late grace fix verified end-to-end (guard green,
  prod contains f623fdab). Publish executor built, gated, locally validated
  (conversion + read-back + idempotency + cleanup proofs); prod run awaits
  owner approval + prod env keys.
