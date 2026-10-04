# Inbound mail operations and scheduler-loss incident

Date: 2026-10-04

## Incident

Owner-provided audit evidence showed the five-minute support-Maildir import clock present on August 24, absent from an August 27 backup, and absent again on October 4. This establishes a scheduler-loss window between August 24 and August 27; it does not establish the exact loss time, uninterrupted outage duration, or which process removed the entry. Root cause of removal remains unknown. On October 4 production had 55 hello/new files and zero support/new files; support/cur contained prior import history. Receipt in a Maildir was functioning separately from ingestion. The original importer scanned support only and recognized support+thread UUID; ordinary hello replies had no exact conversation routing.

Prevention: marker-owned ingress schedule, deployment gate after Drupal activation, preserved cron backup and unrelated entries, durable Drupal heartbeat, and `famtastic:mail-schedule` plus `famtastic:mail-health` readback. An absent, altered, duplicate or unowned importer clock blocks release instead of being silently accepted. Drupal state records activation/baseline and survives normal configuration imports. No historical backlog activation is inferred from old timestamps or sender identity.

## Boundary and operator work

The cPanel clock calls only `famtastic:mail-tick` every five minutes using explicit CLI PHP and cwd. Drupal owns the bounded batch (25 messages, maximum 50 internally, 45-second scan budget), shared locks, file receipts, retry backoff, Message-ID idempotency, atomic portal message/draft persistence, and audit/health. It never runs `drush cron`, lifecycle-run, automation-tick, notification dispatch, or approval. The existing automation clock and exact-dispatch lock remain independent.

First activation requires `famtastic:mail-activate --confirm=preserve-existing-mail`; it snapshots existing new-file identities and imports nothing. Repeated activation preserves the original baseline. Both original Maildirs remain intact; captured files are not moved or deleted. The compatibility support scanner only invokes the same Drupal command. The signed delivery-time pipe shares the bounded MIME parser and existing HMAC endpoint.

Hello matching requires `In-Reply-To`/`References` to equal a recorded **sent** outbox provider Message-ID for the exact sender, plus one durable conversation binding. Supported bindings: portal message notification key, support-case reply key, approved support-draft key, and an existing `website-request:<id>:direct-project` thread. Multiple distinct threads, unsent/unknown receipts, sender-only matches and absent bindings remain unmatched. Active organization membership is checked after correlation; unclaimed contact conversations require the exact stored contact email. This is no guessing and no automatic creation of project bindings. Email clients that strip references need explicit plus-address routing or human review.

Staff reviews unmatched records at the existing Customer Replies metric and pending drafts at the existing support-draft queue. Approval and outbound dispatch remain separate actions. The scanner queues no unmatched notification and sends nothing. Original mail remains available for manual reconciliation. Historical hello backlog, including the known client reply, requires a separately scoped human decision.

Read-only operator commands from the production Drupal root:

```bash
/usr/local/bin/php vendor/bin/drush.php famtastic:mail-schedule
/usr/local/bin/php vendor/bin/drush.php famtastic:mail-health
```

Health separates new mailbox files, excluded historical baseline, pending/retry/captured files, matched/unmatched database totals, draft statuses, missing drafts, and heartbeat age. `recent` proves a completion within 15 minutes, not external delivery or human approval. Missing drafts or retries make the health command fail. An enabled clock with no heartbeat reports never_run; a missing clock fails the schedule check. The latest redacted command report is in the private deployment directory; Drupal persists the actual heartbeat. State/health can be degraded by historical missing drafts; do not bulk-create historical drafts merely to make the indicator green.

## Release and rollback

Run through the canonical primitive:

```bash
FAMTASTIC_INBOUND_MAIL_ONLY=1 ./scripts/deploy-backend-godaddy.sh
FAMTASTIC_INBOUND_MAIL_ONLY=1 ./scripts/deploy-backend-godaddy.sh --apply
```

Only a clean current main is eligible. Exact allowlist and baseline hashes gate eight runtime files. Private code, full database and cron backups precede promotion/activation. No schema, dependency or general-config migration runs. `.inbound-mail-release` records exact source, baseline, time and backup. The standard deployment path checks the ingress clock after activation; existing standard/scoped paths preserve their full scheduler snapshot.

Failure removes only the byte-exact owned ingress marker/line, restores prior code, and rebuilds caches; unrelated cron changes survive. No database restore runs automatically. The retained SQL backup covers config, state and customer records; database restore would be a separately approved destructive recovery. Activation baseline and ingress records should be preserved during code rollback. Rollback validation includes the surgical schedule round trip, local transaction rollback, hash gates, backup presence and release receipt; it is not a claim of having restored the production database.

## Validation and proof boundary

Isolated worktree uses its own copied dependencies/core and SQLite backup; reflection verified the new LifecycleOperationsService loads from this worktree. The first symlinked setup resolved canonical code and was rejected as evidence. New local end-to-end has 18 passing assertions covering backlog, matching, replay, drafts, retries, membership and tenant separation. PHP suite: 347 tests / 2066 assertions; existing Drupal/PHPUnit deprecations remain. Existing support triage: 12 checks. Scheduler classifier: 16 checks. Portal DNA: 34 checks. Email presentation: 88 assertions. No paid provider or SMTP call.

Production fixture inserts one clearly synthetic unclaimed conversation and simulated outbound receipt, then delivers one raw reply file into hello/new after activation. Actual cPanel cron must import it before verification. Signed HTTP replay proves the deployed receiver deduplicates it. All pre-existing Maildir file hashes and ingestion totals are compared, then only exact synthetic records/files are cleaned up. Proof covers Maildir-to-Drupal and signed replay; it does not claim a real external SMTP reply, client acceptance or an approved send.

## Review-handoff matrix (deployed; owner acceptance pending)

| capability_id | Job/role; entry/input | Saved result; visibility/reversal | Evidence | Provider | Owner acceptance | Consumption; gap |
| --- | --- | --- | --- | --- | --- | --- |
| inbound_mail_clock_v1 | Operator ensures incoming replies are imported; cPanel dedicated Drush clock | Drupal activation, baseline, file receipts, heartbeat; private; stop only owned marker/line | hosted_verified 2026-10-04, runtime 63d7dfb07f1a0127a494c499682972bf7ea2d238; actual clock imported the controlled fixture | verified dedicated cPanel execution; external SMTP reply separately unverified | pending | not_applicable; historical record review remains |
| hello_reply_correlation_v1 | Authorized client replies to traced hello conversation | Existing thread gets one portal message and one pending draft; same tenant/authorized staff; correction is human reviewed | local_tested including tenant tests; hosted_verified controlled fixture and signed HTTP replay, runtime 63d7dfb0 | not_applicable for local correlation; external SMTP reply unverified | pending | not_applicable; legacy/stripped-reference replies need human routing |
| inbound_mail_health_v1 | Staff distinguishes receipt/import/match/draft/approval; Drush health and existing reply/draft queues | Real Drupal state and totals; private; no approval/sending mutation | local_tested; hosted_verified runtime 63d7dfb0, recent heartbeat, zero pending/retry files | not_applicable | pending | not_applicable; physical owner task not observed |

Owner task `inbound_health_readback_v1`: Fritz/staff, production shell or existing owner operation surface; read schedule and health, inspect the pending draft, identify excluded backlog and unmatched reason without sending. Preconditions: deployed revision and staff permission. Expected: distinguish exact source/receipt/ingestion/match/draft/approval stages. Reversal: no writes during readback. Result: pending owner observation; developer fixture is separate evidence.

Post-evaluation: site_local lesson is that mailbox receipt cannot substitute for application ingestion proof. Proposed repeatable rule: gate an activated marker-owned clock at deployment and record excluded baseline before enablement. Cross-studio promotion/consumer retrieval not requested or proven. The existing Data Center post-evaluation API recorded and retrieved `posteval_inbound_mail_repair_20261004`, with exact runtime revision and this report pointer. Shared promotion remains proposed; no Studio consumption was inferred.


## Production verification and closeout — October 4

Classification: **deployed**. Owner acceptance: pending, not observed. Runtime source: `63d7dfb07f1a0127a494c499682972bf7ea2d238`; source baseline `8be9ecd30e88e262020a9052a00198000f154516`. Scoped release timestamp: 2026-10-04 15:02:52 UTC / 11:02:52 EDT. Actual cron completed at 15:05:02 UTC. Existing observe-only automation clock and exact-dispatch lock value `0` were preserved byte-for-byte/value-for-value. No broad cron or outbound dispatcher was invoked.

All eight hosted fixture assertions passed: one scheduler import, one exact portal reply, one pending unapproved draft, all 320 pre-existing Maildir files hash-preserved, exactly one new inbound record, no extra outbound receipts beyond the explicitly simulated fixture row, signed HTTP replay returning duplicate, and no repeated portal message. Original 55 hello/new files remained excluded. Source deployment verified all eight runtime hashes; database/code/cron backups are present and private. No production database restore was attempted.

The synthetic fixture and only its exact inbound/draft/outbox/thread/messages/raw file were removed after proof. Private verification receipts were retained. Final production readback: clock installed and recent; hello/new 55, support/new 0, excluded baseline 55, pending/retry files 0; original inbound totals restored to matched 1 / unmatched 3. Four historical inbound records have no draft rows. Accordingly `mail-health` deliberately returns failure for that historical completeness gap while displaying a recent healthy ingress heartbeat; schedule and eight new-path proof checks pass. Historical backlog reconciliation and draft creation are separate human-reviewed actions, not automatic cleanup or a reason to ingest old mail.

Rollback evidence: prior-runtime file backups, mode-restricted complete SQL backup, pre-install cron backup, runtime baseline hash gates, local transaction rollback and schedule removal round-trip tests, surgical code/schedule failure handler, unchanged unrelated-clock assertion. These are retained rollback mechanisms and validation, not a production database-restore drill.

The private production receipt directory lives beside the scoped release marker and is intentionally not copied into Git. All Git reports contain aggregate counts and synthetic-only test descriptions. Drive mirror: `2026-10-04-inbound-mail-repair.md` in the existing FAMtastic Designs mirror. Data Center record: `data-center/post-eval/posteval_inbound_mail_repair_20261004.json`; retrieval returned that exact ID and runtime revision. Resumable brief is completed. Root plan audit: clean, zero drift/conflicts/orphans. Canonical worktree's unrelated marketing work was preserved.
