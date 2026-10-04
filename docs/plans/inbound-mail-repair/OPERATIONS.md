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

## Review-handoff matrix (release proof pending)

| capability_id | Job/role; entry/input | Saved result; visibility/reversal | Evidence | Provider | Owner acceptance | Consumption; gap |
| --- | --- | --- | --- | --- | --- | --- |
| inbound_mail_clock_v1 | Operator ensures incoming replies are imported; cPanel dedicated Drush clock | Drupal activation, baseline, file receipts, heartbeat; private; stop only owned marker/line | local_tested; production pending exact `.inbound-mail-release` | configured_unconnected until clock observed | pending | not_applicable; actual clock proof pending |
| hello_reply_correlation_v1 | Authorized client replies to traced hello conversation | Existing thread gets one portal message and one pending draft; same tenant/authorized staff; correction is human reviewed | local_tested including tenant tests; production synthetic pending | not_applicable for local correlation; external SMTP reply unverified | pending | not_applicable; legacy/stripped-reference replies need human routing |
| inbound_mail_health_v1 | Staff distinguishes receipt/import/match/draft/approval; Drush health and existing reply/draft queues | Real Drupal state and totals; private; no approval/sending mutation | local_tested; production pending | not_applicable | pending | not_applicable; physical owner task not observed |

Owner task `inbound_health_readback_v1`: Fritz/staff, production shell or existing owner operation surface; read schedule and health, inspect the pending draft, identify excluded backlog and unmatched reason without sending. Preconditions: deployed revision and staff permission. Expected: distinguish exact source/receipt/ingestion/match/draft/approval stages. Reversal: no writes during readback. Result: pending owner observation; developer fixture is separate evidence.

Post-evaluation: site_local lesson is that mailbox receipt cannot substitute for application ingestion proof. Proposed repeatable rule: gate an activated marker-owned clock at deployment and record excluded baseline before enablement. Cross-studio promotion/consumer retrieval not requested or proven. Data Center post-evaluation tool is not exposed in this session; this source record is the pending index pointer, not a competing universal ledger.
