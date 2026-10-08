October 8 extension: read [delivery incident lessons](DELIVERY-INCIDENT-LESSONS-20261008.md) and STATUS.md for the current full 50 schedule, permanent-failure suppression and reporting diagnosis. This dated October 7 record is retained.

# October 7 acquisition sending lessons

Recorded: 2026-10-07. Classification: `changed` internal operating documentation; lessons `site_local`, consumption `source_captured`. This review changes no sender, timer, contact, reserve, approval, provider or customer message. It extends the existing repair post-evaluation `posteval_2d7c3a261b59328f`; no second universal incident ledger is introduced.

## Findings and evidence limits

At 23:56:57Z (7:56 p.m. Eastern), a fresh native Drupal readback showed **349 customer SMTP acceptances overall, 105 on October 7**, zero unresolved dispatches, and 651 unused recipient keys. Five were accepted at 9 a.m.; the repaired evening windows accepted 50 at 6 p.m. and 50 at 7 p.m. All 293 industry acceptances had matching native invitation contexts across nine industries. Retained sanitized readback: [SENDING-READBACK-20261007-235657.json](SENDING-READBACK-20261007-235657.json). This is a dated transport snapshot, not a live dashboard or proof of inbox delivery, revenue or client acceptance.

Runtime: frontend `0fb17756af7e29e134827cae9e970cea39312459`, backend base `d27b6af475f394ad240e3a2ad02af657c1cd1b41`, scoped HVAC overlay `76c6faad5fe4b6fb199cb2842edca14f428d69d5`. Repair/guard source: `dae766308713cce0512a17ca864349cb52016652`. Authoritative repair evidence: [CLOCK-REPAIR-LIVE-20261007.json](CLOCK-REPAIR-LIVE-20261007.json); incident chronology and pending outcomes: [STATUS.md](STATUS.md). The earlier 299/55 repair receipt remains unchanged historical evidence.

Two different conditions must not be conflated:

1. **Reconstructed morning capacity constraint:** reconstructing persisted sent timestamps gives 245 observed account entries at 9 a.m. and 250 at 10/11/noon. The current conservative formula allowed five, then zero: `max(0, min(200, 500 - 250 reserve - max(Eastern-day usage, rolling-24-hour usage)))`. The hourly formula independently caps at 50 and reserves 400 against the published account-hour limit. Yesterday's evening sends still consume the rolling window the next morning. This matches the five recorded accepts and later absent window rows. Historical timer ticks and historical unresolved-state snapshots were not retained; actual invocation of those later ticks is unproved. This is our model, not a measured GoDaddy balance or a demonstrated provider rejection.
2. **Afternoon release failure:** the frontend changed at 1:24 p.m., after the morning windows. The signed sender still pinned the previous frontend. `window_exact_production_release_required` therefore blocked later preparation. Rebinding after live destination validation fixed that guard failure. The frontend change cannot explain morning deferrals.

## Independent specialist consultation

Applied shared Agency Agents profile **SRE (Site Reliability Engineer)**, ID `engineering/engineering-sre`, pinned catalog commit `765be42358100bf89d2faa567668a94c602f9a26`. A separate read-only SRE reviewer inspected the actual executor, capacity calculation, release guard/tests and repair records. Profiles provide guidance, not credentials, production authority or professional certification. FAMtastic foundational definition v1.0.0 was revisited.

The reviewer confirmed that zero budget can return before `reserveWindow`, so missing dispatch rows are not evidence of a broken cron. Preflight failure and budget deferral need durable records independent of contact reservations. The existing reason `provider_capacity_unavailable` can mislead because the capacity adapter explicitly reports `conservative_budget`, `verified=false`, and unknown outside usage. Preserve the original reason in historical receipts; future presentation should distinguish modeled allowance from provider rejection.

## Repeat, change and stop

| Lesson | Observation / cause | Process change | Evidence and scope |
| --- | --- | --- | --- |
| Plan using rolling usage | Midnight does not clear the conservative 24-hour count; an evening catch-up can constrain tomorrow morning. | Read both windows and reserves before promising volume. Show target, modeled availability and actual accepts separately. Preserve reserves until measured evidence supports an authorized change. | Native timestamp reconstruction plus `scripts/acquisition-window-capacity.php`; site-local. Forecasting automation remains proposed. |
| Release and sender are one operational handoff | A new frontend left signed sending pins stale. | Follow the pause → exact release → destination validation → signed rebind → check-only → restore schedule runbook. Never disable exact pins to keep sending. | Live repair passed; source promotion guard has 14 checks and is on main. Future deployment consumption of that guard remains unproved. |
| Installed is not healthy | The timer and active latch could remain present while preflight rejected execution. | Every status report must include configuration health, last completed/deferred window, reason, fresh allowance and next eligible window. | Source shows failure before execution/halt; richer health UI and alerts are proposed. |
| Keep the evidence before reservation | Zero allowance returns before a native window row; last-run log is overwritten. | Next implementation should persist an aggregate outcome for each eligible tick, including zero budget and preflight failures, without preparing or reserving contacts. | SRE/code review; not implemented by this document. |
| Recovery must not duplicate accepted mail | Retrying a failed or uncertain transport could resend customers. | Preserve used keys, snapshots, prior acceptances and uncertain rows. A fresh signed schedule is not permission to retry a consumed key. Check receipts before retrying anything ambiguous. | Repair fingerprints preserved all 249 prior accepts; evening receipts show no unresolved dispatches. |
| Communicate state rather than vague checks | Source tests, installed timer, live link checks and actual sends were repeatedly collapsed in status. | Use the four-line status recipe below. Say deferred, blocked, accepted or uncertain with an exact reason and timestamp. | Owner-reported friction and technical review; local operational rule, not a universal marketing claim. |

## Operational runbook — use now

### Before promising today's volume

1. Read native campaign acceptances by Eastern date, rolling account usage, last-hour usage, unresolved transports, suppression and unused keys. Do not export recipients or signing material.
2. Read installed schedule dates/hours/caps and compare its exact frontend/backend bindings with current release markers. A timer readback alone is insufficient.
3. Run the existing executor's **`--check-config` only** against the exact private signed input found in the installation receipt. Check-only must create zero reservations, prepare zero contacts and send zero mail. Do not treat a historical filename or report as current configuration.
4. Report separately: campaign target, remaining campaign ceiling, fresh modeled account allowance and unknown provider balance. Generic day-3/day-7 follow-ups are not currently enabled; never imply they are dispatching.
5. A morning-only schedule with insufficient rolling allowance needs a bounded scheduling decision; it is not solved by repeatedly launching the same window. Already-authorized finite catch-up must use its exact date/hours and cap. Future exceptions require authority covering their dates; record existing authority rather than repeatedly asking for the same action.

### When a release pin fails

1. Coordinate one sender owner and the deployment owner. Capture prior signed configuration, cron and installation receipt privately; stop only the exact owned sending entries during the maintenance window, preserving unrelated jobs. Establish that no sending process or running window remains before promotion; do not reset a running or uncertain outcome to make the change proceed.
2. Use canonical deployment scripts and a clean reviewed commit reachable from main. Never patch public source in `public_html`, reset the sender latch, drain broad queues or weaken signature/release checks.
3. Verify the served release, each affected invitation destination, recipient isolation and account continuation at desktop and phone widths. Keep tokens and private data out of Git and public logs.
4. Sign the approved schedule for the validated frontend/backend pair, preserve its existing dates/caps and all used keys, and run check-only. Restore/read back only the owned timer entries when the operation is coherent. If deployment/validation fails, leave the owned sender stopped and use the recorded rollback; do not silently resume mismatched sending.
5. Observe a real eligible window and reconcile new native accepts with SMTP acceptance receipts and zero unresolved outcomes. Installed schedules are future intent, not completed sends. Continue only within the existing allowance and stop controls.

### Four-line status recipe

- **Actual:** timestamp, today's and cumulative customer SMTP accepts, latest window count, unresolved count; exclude owner tests from customer totals but include their account usage where applicable.
- **Health:** installed yes/no, exact release validation pass/fail, current reason, evidence date.
- **Next:** exact scheduled hour/date, per-window and daily cap, fresh modeled allowance; disclose uncertain provider quota.
- **Outcome:** remaining keys and any shortfall; inbox/delivery/conversion are separate measured facts.

## Prioritized follow-up implementation

These are recommendations, not deployed capabilities or permission to introduce a new monitoring platform.

| Priority / owner | Work | Acceptance criteria |
| --- | --- | --- |
| P1 / native campaign maintainer | Durable eligible-window outcomes, including budget deferrals and preflight failures; distinguish modeled budget from provider rejection. | Each eligible tick produces a privacy-safe outcome even before reservation. Simulate zero budget, stale pin, expired schedule, repeated tick, failed/uncertain transport and success. Zero-budget and read-only checks create no contacts/reservations/mail. Duplicate ticks do not resend; attempted/accepted/deferred stay distinct. |
| P0 / release maintainer | Consume the published frontend guard on the next real release; rehearse coordinated stop/deploy/validate/rebind/restore. | Stale binding blocks promotion; unchanged exact binding passes; changed release cannot send until reviewed rebind; rollback preserves timers, keys and unrelated jobs. Record actual guard consumption, not just its source tests. |
| P1 / native reporting maintainer | One honest campaign health summary and deduplicated actionable failure notification through existing approved staff channels. | Show last attempted/completed/deferred window and reason, release health, fresh allowance and next time. An active timer with failed validation is unhealthy. Repeated unchanged deferrals do not spam; a missed execution or release failure is visible. No automatic customer mail from diagnostics. |
| P2 / campaign maintainer | Forecast normal and catch-up windows from rolling timestamps, respecting shared follow-up/probe traffic. | Replay the 9 a.m. five-available scenario and show the next modeled opening. Cover Eastern midnight, DST, concurrent reservations and unknown outside usage. Never label the forecast as provider-confirmed or change reserves or caps automatically. |

Existing capacity and release-guard regression suites remain the foundation. No new analytics subscription, provider replacement, reserve reduction or scheduler activation is part of this capture.

## Review, retrieval and handoff

The campaign README and agent operating contract point to this exact report. Registry and both site-learning surfaces distinguish published process from production functionality. The report is linked into the existing Data Center post-evaluation system through [SENDING-LESSONS-POST-EVAL-20261007.json](SENDING-LESSONS-POST-EVAL-20261007.json). Independent document review and local validation: [SENDING-LESSONS-REVIEW-20261007.json](SENDING-LESSONS-REVIEW-20261007.json). Cross-configuration validation and actual Component/Site Studio retrieval are **unverified**; no shared skill or universal reliability rule is promoted from this single incident. Owner-task acceptance is not applicable to these internal documents; existing customer inbox and physical owner acceptance remain pending.
