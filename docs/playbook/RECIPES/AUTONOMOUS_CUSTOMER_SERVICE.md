# RECIPE: Autonomous Customer Service

**Outcome**: Every customer message gets a correct, fast response with zero Fritz minutes for routine cases — and Fritz sees all inbound/outbound mail in one admin view.
**Trigger**: Customer email reply, portal support case, or proof/portal event.
**Owner**: Support Triage agent (hire pending) · **Escalation**: Fritz
**Grounded in**: mail trace 2026-08-22 (OutreachMailer fail-closed transport; `famtastic_inbound_message` table exists w/ NO admin UI; revision emails queue but die in broken outbox; selection events notify nobody).

## Phase A — Visibility & delivery integrity (P0, prerequisite for everything)

| # | Step | Owner | Definition of done | Evidence | Status |
|---|------|-------|--------------------|----------|--------|
| A1 | Verify/fix prod SMTP (`smtp.settings`) + send test mail | Fritz (ops) + CEO prep | Test mail delivered to owner inbox | Watchdog "OUTREACH EMAIL accepted" + inbox receipt | 🔄 PREP DONE 2026-08-23 — exact read-only diagnostics, fix commands, rollback in `RUNBOOKS/A1-prod-mail-integrity.md`. **Awaiting Fritz execution** (gate). |
| A2 | Fix cron: keep stderr, verify lifecycle-run cadence | CEO | Heartbeat fresh <10min on `/admin/famtastic/metric/workers` | Screenshot/log path | ⚠️ blocked behind A1 verification |
| A3 | Requeue dead-lettered notifications | CEO | Outbox dead-letters = 0 at `/admin/famtastic/metric/notifications` | List view state | ⚠️ blocked: review dead-letter list BEFORE requeue — requeue = real sends to real customers → Fritz gate |
| A4 | Build replies list view (`/admin/famtastic/metric/replies`) | Dev hire | Inbound messages visible: sender, subject, match status, received | Route + screenshot | ☐ |
| A5 | Owner notification on proof-selection; customer ack on select/revision | Dev hire | Both events produce outbox rows that deliver | Synthetic run receipts | ☐ |
| A6 | "Needs attention" banner for dead-letters/queue age | Dev hire | Banner shows when counts >0 | UI check | ☐ |

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
- 2026-08-23 — A1 prep complete (RUNBOOKS/A1-prod-mail-integrity.md): diagnostics grounded in OutreachMailer's four fail-closed modes + watchdog success string. A2/A3 explicitly gated behind A1. Lead workspace for owner-side triage shipped (`efbef789`).
