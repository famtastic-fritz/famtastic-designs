Title: Restore the approved schedule and reconcile permanent delivery failures
Purpose: Continue authorized unsent outreach while preventing known failed contacts from entering follow-ups or retries.
Goal: Verify the incident, restore only the temporary incident hold under direct authority, apply exact native suppression, explain metrics and capture reusable lessons.

Tasks:
- [x] Verify the current release, partial-batch cause and signed full 50 executor deployment.
- [x] Restore the existing schedule under direct current-chat approval with technical/uncertain guards preserved.
- [x] Independently correlate permanent DSNs with native recipients, subjects and post-dispatch timing.
- [x] Apply native suppression and verify idempotency plus immutable sent history.
- [x] Diagnose the engagement-report discrepancy and capture site-local lessons/post-evaluation.
- [ ] Observe the next approved full 50 window's actual outcome; future sends remain conditional.

Status: checkpoint_complete
Started: 2026-10-08 12:47 America/New_York
Ended: 2026-10-08 13:05 America/New_York (runtime checkpoint)
Execution: Existing codex/acquisition-199-20261005 worktree; no new branch/worktree. Main landing: scoped agency commit. Runtime executor overlay c6d8970b; manual DSN reconciliation uses existing ledger APIs. Existing normal 9/10/11/noon, 200/day, 50/window preserved.
Research: Independent read-only hello/support new+cur correlation; native event/timestamp/report seams; queue provenance fields. Raw recipient manifests and mail remain host-private.
Review: Deployed runtime and dated native suppression; lessons source_captured/site_local; report correction and recurring ingestion proposed; owner_accepted not established. Capability/owner-task surfaces updated without claiming new UI proof.
Skills: Previously applied famtastic-build-review and client-owner-training; canonical FAMtastic definition revisited.

Proof:
- DELIVERY-INCIDENT-LIVE-20261008.json:497 SMTP sent, 468 permanent failures all suppressed; replay 0 duplicates; all sent/snapshot fingerprints unchanged; restored timer active; 0 operational sends.
- CONSISTENT-BATCH-DECISION-20261008.md:36 tests/1, 961 assertions; 250/400 reserves and approved caps/hours unchanged.
- DELIVERY-INCIDENT-LESSONS-20261008.md records reporting diagnosis, parser correction, cron receipt correction and verification limits.
