# Acquisition delivery incident and lessons — October 8, 2026

The existing morning schedule is active after Fritz's direct current-chat approval to restore it. The full 50 admission gate is deployed. Independent mailbox correlation verified **468 permanent failures among 497 SMTP-sent customer messages (94.2%)**; every confirmed failed contact now has native bounced suppression. The remaining 29 have unknown delivery, and the 503 unused queue contacts have unknown deliverability.

Classification: **deployed** for the executor overlay, restored clock and dated native suppression reconciliation; **changed / source_captured / site_local** for these lessons and the deployment receipt comparison correction. Owner acceptance remains pending. These findings extend the [October 7 runbook](SENDING-LESSONS-20261007.md); they do not rewrite its historical receipts.

## Actual execution and authority

- Current frontend 0fb17756af7e29e134827cae9e970cea39312459; backend base d27b6af475f394ad240e3a2ad02af657c1cd1b41; existing HVAC overlay 76c6faad5fe4b6fb199cb2842edca14f428d69d5; executor overlay c6d8970bad40431048df8b89e2583e369035c328. Base release markers were preserved.
- October 7 finished 200 customer SMTP acceptances: 5 morning plus 50 at 18/19/20 and 45 at 21 Eastern. Its date-only catch-up is expired. October 8 has 53: 48 at 09 and 5 at 10. The earlier partial sizes came from slicing the available rolling-account allowance before reservation.
- Full 50 admission applied 16:46:42Z: signed batch_size: 50 plus exact executor hash. Below 50 available queue/quota/account room, defer before reserving keys or preparing email. Mid-batch safety changes still stop dispatch and can yield fewer than 50 actual acceptances.
- Temporary delivery-incident hold applied 16:49:10Z. Automatic approval rejected clearing it based on retrieved other-chat authorization. Fritz then directly answered “Restore existing schedule” here after the 468/497 finding was disclosed. Exact incident-only restoration succeeded 16:52:33Z; no technical/uncertain halt was bypassed.
- Active schedule retains 9/10/11 a.m. and noon Eastern, 200 total campaign messages/day, 50/window/hour, existing 250/day and 400/hour reserves and October 16 end date. No October 8 evening exception. Next modeled approved opening is October 9 09:00, conditional on fresh allowance, releases, history, suppression and no safety stop.
- Independent verification 17:03:08Z, suppression 17:03:39Z, replay 17:05:07Z and runtime readback 17:05:09Z. All 497 sent rows and immutable snapshots stayed unchanged; unresolved dispatches 0. These operations sent 0 messages.

## Delivery evidence and limitations

Read only hello/support Maildir new+cur, deduplicating 477 recent messages by Message-ID or raw digest. 468 DSNs matched the native SMTP recipient, exact original subject and receipt after dispatch. 320 explicitly say the account does not exist; another 14 have recipient-rejection code 5.1.1; 134 are other permanent rejections. Among the other notices, 61 contain 5.5.0 mailbox unavailable. The 134 are not all established as invalid addresses; sender/provider policy or recipient-system causes require further diagnosis.

The original parser missed GoDaddy's “recipients failed permanently” text and bullet recipient format. Its zero result was rejected as a parser problem after an aggregate/redacted format audit. The corrected parser matched 468, with zero unmatched permanent notices or invalid correlations. Do not infer zero bounces from a parser that has never been checked against actual transport formats.

The one dated reconciliation used existing OperationalLedger.recordEvent(email.bounced) and recordConsent(bounced) APIs. Each failure gets one stable event key, private DSN digests and native contact suppression in a transaction. It verifies the exact current sent row and unchanged Maildir digest before writing. Native stopContact blocks held/staged/queued future sequence messages while retaining sent dispatch history. Replay produced 0 new events/consents and 468 already-reconciled contacts; all 468 remained suppressed.

This does not install recurring DSN ingestion. Future failures still need an evidenced reconciliation. No raw mailbox content, recipient addresses or individual contact hashes were exported into Git; the failed-recipient manifest remains private on the host. No mailbox moves or read-flag changes occurred.

## Reporting diagnosis

The safe count is 10 distinct sent-message raw email.opened ledger events, 0 raw clicks and 0 campaign-attributed reply events in the checked sources. Human engagement is unknown. A pixel request may be a privacy proxy or scanner, including on a rejected email; it does not prove delivery or a person reading.

CampaignMessageService.track deliberately appends events without changing acquisition dispatch status or timestamps. AcquisitionCampaignReportService.project reads opened_at/clicked_at, both still 0 for these messages. This explains the dashboard/event discrepancy. The report also lacks a permanent-bounce projection, although 468 native email.bounced events are now recorded. Reporting has not been changed or deployed in this diagnosis.

LifecycleOperationsService.handleInbound associates replies/stops using the sender hash. A mailer-daemon DSN's failed recipient differs from that sender, so normal inbox handling alone does not suppress the failed campaign address. Recurring DSN processing needs explicit failed-recipient correlation and idempotency, separately from human replies.

## Lessons and operating changes

1. Treat SMTP acceptance, recipient delivery, machine tracking and human response as separate facts. Permanent failures belong beside send counts in operating readback.
2. Syntax and MX availability cannot establish a working mailbox or source provenance. The 999-row queue carries workbook digest and source_row lineage but no primary collection URL. Earlier bounded research examined30 of300 initial candidates and 18 later rows; it did not validate all 1000. Do not claim the list is verified or fabricated from these facts alone.
3. Preserve the approved unsent schedule while investigating under the owner's current authority; suppress confirmed permanent failures through native APIs before follow-up/retry. An incident-only latch can be cleared only with current scoped authority and evidence that no technical or uncertain halt is bypassed.
4. “50 per batch” needs admission logic, not just a50 maximum. Keep a smaller final tail deferred unless an explicit tail policy is decided. Do not increase reserves, hours or daily ceilings to manufacture50 sends.
5. Project raw engagement from bound native events without replacing immutable SMTP dispatch status. Exclude pre-send, held, wrong-campaign/prospect and controlled-test events; deduplicate by message. Human classification remains unknown until independently proved.
6. Compare substantive cron contents when checking preservation. The frozen deployment receipt's other_cron_preserved:false arose solely from trailing blank lines added by Schedule.install. Independent readback proved normalized contents equal; the future helper comparison is corrected in source. The original receipt remains unchanged.

## Proof and remaining work

[Aggregate live receipts](DELIVERY-INCIDENT-LIVE-20261008.json) contain actual deployment, hold, restoration, verification, suppression, replay and readback evidence. Full 50 source proof:36 SQLite tests/1961 assertions, 28 capacity checks, 14 frontend guard checks. Native consent/bounce recipient isolation fixtures:2 tests/13 assertions, no network/mail functions. An expanded48-test sample run is **not a passing gate**: after restoring missing public fixture assets it retained one missing preexisting HVAC private-bundle error and 13 warnings. No HVAC source changes were made to resolve that unrelated fixture. PHP syntax, normalized-cron regression fixture and scoped diff/privacy checks support the changed helper.

Recurring DSN ingestion, event-based dashboard projection and durable deferred-tick telemetry remain proposed. The next actual50 admission/outcome, remaining cohort provenance/deliverability and uncoached physical owner task remain open. Shared skill or Studio promotion is unverified; this is a site-local capture in the existing learning surfaces and Data Center.
