# Backend command-center release handoff

Status: source ready for independent approval and feature-branch commit. No production apply. The exact candidate SHA will be the independently verified commit; no SHA is invented before commit. Canonical production deploy accepts only clean pushed current main, so this feature branch must first be reviewed/merged and refreshed under the existing contract.

## What changes

Campaign planning has editable Drupal drafts, lifecycle actions and common campaign context. Customer communications has recipes, durable drafts/revisions, exact-recipient branded previews and human-reviewed queueing. AI assistance explains defaults and explicitly enables individual tasks; no tasks enabled by this migration, no live model invoked. Mobile home has real work queues and touch-safe navigation. Updated lock patches core11.4.8, Webform6.3.1 and ProjectBrowser2.1.5; AI remains1.4.8.

## Before an authorized production apply

1. Fetch current main and check update8066 remains unused by incoming code; rebase/reconcile deliberately if needed. Repeat targeted checks for any changed source.
2. Record approved exact merged SHA, checked lock hash and deploy dry-run output. Do not skip existing backend dependency/platform checks.
3. Use canonical `scripts/deploy-backend-godaddy.sh` preflight; prepare its database/code/theme/dependency/config backups. Verify free disk for dependency archive plus immutable campaign JSON snapshot. No broad queue drain, cron activation or mail test required to prove this change.
4. Follow `PRODUCTION_READINESS_2026-09-30.md`: the configured ngrok endpoint is offline (HTTP404 / ERR_NGROK_3200), authenticated integrations are unreachable, and local4007/4040 refuse connections. The proposed migration example hostname does not resolve. URL normalization cannot repair this outage. Inspect and preserve existing Postiz data and pending schedules before authorized service/tunnel recovery; restarting may resume old jobs. Do not configure a guessed replacement URL. Authenticated healthy integrations prove connection only, not publication.
5. Review provider defaults with the owner: configured OpenAI model is supported by bounded adapter; other providers remain unavailable until equivalent timeout adapter verified. Enabling tasks is a separate explicit owner choice and may incur costs. Keep limits low and never claim configuration proves provider execution.

## Promotion and rollback

Canonical deploy stages exact Git JSON snapshot `campaign-releases/<SHA>` outside web root. `campaign-source.json` staged with module code points locator to that exact source only. Existing mutable CLI campaign files are never changed. Old module rollback selects old snapshot automatically. Snapshot metadata is labeled source SHA/sync time and not live scheduling evidence. Media is not included; unavailable media remains explicit until an exact approved media manifest exists.

Update8066 adds fields/tables non-destructively; all existing labels become active with no inferred tests. Save/history/queue transactions and retry keys preserve prior outbound records. Back up DB before update. Code rollback is automatic for deployment failures; DB restore is deliberate from recorded preupdate dump if needed, never blindly drop new draft tables after users author work. Keep previous snapshots for rollback; no automated snapshot deletion introduced.

## Verification evidence

Patched runtime `/private/tmp/famtastic-selected-drupal.hkmZ6c` on loopback28986: installed versions exactly match patched lock, unit333 tests1960 assertions pass (69 PHPUnit deprecations), email86 assertions,28 adapter/persistence/cache/snapshot checks plus generic DB-error injection test. Pre8066 upgrade rehearsal passes twice preserving existing campaign/thread rows in retired synthetic runtime. Snapshot/package helper29 checks pass. Composer strict, navigation inventory, theme, creator-credit and PHP/YAML/shell syntax checks pass.

CUA full lifecycle passed on first fixture at390/1440: campaign create/edit/duplicate/archive/restore; recipe/save/reload/preview; synthetic reviewed send queue1→2, reload stays2. Final patched-runtime critical home/campaign/create/draft-save/reload/preview smoke passed; queue remains1. UI replay was blocked by automatic approval and is NOT counted as browser proof; service duplicate-send/replay proof passed. No real recipient/transport/provider action.

## Pending external mirror approval

Repository docs updated. Automatic approval review rejected copying internal implementation evidence to Google Drive because exact payload/destination lacked explicit user authorization. No workaround was attempted.

Reviewable payload: `docs/implementation/INTEGRATION_EVIDENCE_2026-09-30.md`.
Proposed destination: `/Users/famtastic-fritz/Library/CloudStorage/GoogleDrive-fritz.medine@gmail.com/My Drive/FAMtastic/famtasticdesigns.com/2026-09-30-backend-command-center-local-integration.md`.
This mirror remains pending approval; it is not a production deployment blocker.

Owner usage guide: `OWNER_GUIDE_2026-09-30.md` explains model defaults, task enablement, mobile navigation, campaign planning, message drafts and explicit send review in ordinary language.
