# RECIPE: Autonomous Customer Service

**Outcome**: Every customer message gets a correct, fast response with zero Fritz minutes for routine cases — and Fritz sees all inbound/outbound mail in one admin view.
**Trigger**: Customer email reply, portal support case, or proof/portal event.
**Owner**: Support Triage agent (hire pending) · **Escalation**: Fritz
**Grounded in**: mail trace 2026-08-22 (OutreachMailer fail-closed transport; `famtastic_inbound_message` table exists w/ NO admin UI; revision emails queue but die in broken outbox; selection events notify nobody).

## Phase A — Visibility & delivery integrity (P0, prerequisite for everything)

| # | Step | Owner | Definition of done | Evidence | Status |
|---|------|-------|--------------------|----------|--------|
| A1 | Verify/fix prod SMTP (`smtp.settings`) + send test mail | Fritz (ops) + CEO prep | Test mail delivered to owner inbox | Watchdog "OUTREACH EMAIL accepted" + inbox receipt | ✅ DONE 2026-08-23 — CEO executed read-only diagnostics then single owner-inbox test (`fritz.medine@gmail.com`): SMTP accepted, message-ID `<oufx4zDejJ959Q6o79A0spUCXHsLNBFoQEEzPc5zSo@default>`. Prod config verified complete (smtp_on, host:465/ssl, creds set). Inherited hypothesis (unconfigured SMTP) REFUTED — outbox clean: sent=184/superseded=18/dead_letter=0. |
| A2 | Fix cron: keep stderr, verify lifecycle-run cadence | CEO | Heartbeat fresh <10min on `/admin/famtastic/metric/workers` | Screenshot/log path | ✅ DONE 2026-08-23 — ROOT CAUSE FOUND & FIXED: cron resolved `/usr/bin/php` (cPanel CGI wrapper) so every drush line failed preflight silently; logs had been discarded. Fix: crontab `PATH=/usr/local/bin:/usr/bin:/bin` + per-job log files + daily trim (backups: `~/backups/crontab-before-A2*.txt`). Post-fix: lifecycle `[success]` logged, `cron_last` fresh <10min. One incident during change (sed gutted crontab) restored from backup within minutes — lesson: never edit from filtered output. |
| A3 | Requeue dead-lettered notifications | CEO | Outbox dead-letters = 0 at `/admin/famtastic/metric/notifications` | List view state | ✅ DONE 2026-08-23 — dead letters already 0 (outbox group-by: sent=184, superseded=18, no queued/retry/dead_letter). Nothing to requeue; no gate crossed. |
| A4 | Build replies list view (`/admin/famtastic/metric/replies`) | Dev hire | Inbound messages visible: sender, subject, match status, received | Route + screenshot | ✅ DONE 2026-08-23 — `famtastic_pipeline.operations_metric` route regex now matches `replies`; `/admin/famtastic/metric/replies` renders sender (hash-resolved against customer directory), subject, match status + rejection reason, thread, received. Evidence: `.artifacts/mail-visibility/1787485539-65891/evidence.json` (`route_replies_matches`, `replies_shows_sender_subject_match_received`). |
| A5 | Owner notification on proof-selection; customer ack on select/revision | Dev hire | Both events produce outbox rows that deliver | Synthetic run receipts | ✅ DONE 2026-08-23 — `decideWebsiteRequestProof` enqueues via existing `queueNotification` outbox merge: owner "Customer selected proof X" + customer Concierge ack on select; customer Concierge revision ack alongside existing owner revision alert. Delivered through existing `dispatchNotifications` (memory transport locally; nothing new auto-sends). Receipts: `.artifacts/mail-visibility/1787485539-65891/outbox-capture.jsonl` (4/4). |
| A6 | "Needs attention" banner for dead-letters/queue age | Dev hire | Banner shows when counts >0 | UI check | ✅ DONE 2026-08-23 — `.famtastic-ops__attention-banner` on all Operations pages via shared `page()` wrapper; renders only when outbox has retry>0, dead_letter>0, or oldest queued row ≥30 min (`NOTIFICATION_QUEUE_ATTENTION_SECONDS`). Both branches proven. Evidence: same evidence.json (`banner_absent_when_healthy`, `banner_present_on_stale_queue_age`, `banner_present_on_dead_letter`). Validator: `scripts/e2e-mail-visibility.sh`. |

## Phase B — Triage autonomy ladder

| # | Step | Owner | Definition of done | Evidence | Status |
|---|------|-------|--------------------|----------|--------|
| B1 | Classify inbound into intents (status, revision, billing, technical, other) | Support agent | Classifier rules documented + tested on ≥20 real historical messages | Labeled test set results | ☐ |
| B2 | Draft-only mode (L0): every reply drafted, Fritz approves in admin | Support agent | Draft queue UI exists; nothing auto-sends | UI + approval log | ☐ |
| B3 | Auto-send low-risk intents (status questions, office hours) (L1) | Fritz approves policy | Auto-replies logged w/ template version; escalation on low confidence | Send log sample | GATE→☐ |
| B4 | SLA clock + breach alerts (first response targets by intent) | CEO | Breach → owner alert via working outbox | Synthetic breach test | ☐ |

## Failure paths

| Where | If it fails | Fallback |
|---|---|---|
| Any send | Transport error | Retry ×3 → dead_letter → banner + daily summary (second channel once A1–A3 prove transport) |
| Classification confidence < threshold | Wrong auto-reply risk | Escalate to human draft queue, never guess |
| Unmatched reply address | Thread lost | Existing unmatched-reply handler + visible in A4 list within 5 min |

## Approval gates
B3 policy change; anything sent to a real customer during Phase A/B testing.

## Change log
- 2026-08-22 — Created from production incident (first customer's proof email lost) + mail trace findings.
- 2026-08-23 — A1 ✅ A2 ✅ A3 ✅ (CEO autonomous run under Fritz's build-the-company grant; details in step rows). Phase A COMPLETE → Phase B (Support Triage hire) unblocked. Production mail transport verified healthy end-to-end; the 2026-08-22 "all sends fail" premise was stale — real defect was the cron CGI-wrapper failure, now fixed. Lead workspace shipped (`efbef789`); A4–A6 shipped (`277bb5e8`).
- 2026-08-23 — A4 ✅ / A5 ✅ / A6 ✅ (local, uncommitted working tree; validator `scripts/e2e-mail-visibility.sh` green on 3 consecutive idempotent runs). A4: `replies` added to operations_metric route regex + `replyMetric()` in OperationsController resolving sender hashes against `famtastic_customer`. A5: `CustomerPortalService::decideWebsiteRequestProof` now queues owner select alert (`website-request:{id}:owner-proof-selected:{dir}`, supersedes prior pending), customer select ack, and customer revision ack through the existing `queueNotification` outbox mechanism only. A6: attention banner in `OperationsController::page()` driven by retry/dead_letter counts + 30-minute queue-age constant; styled in css/operations.css. Synthetic run receipts: `.artifacts/mail-visibility/1787485539-65891/` (evidence.json all-checks-passed + outbox-capture.jsonl with 4 provider message IDs `<famtastic-test-…@memory.invalid>`). No production deployment; no real email sent.
