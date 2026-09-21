# StockandShip98 proof reminder — September 21, 2026

Owner authorized one new customer reminder after reviewing the established email
templates and standards. Request 17, customer 15, campaign 56 are the exact scope.
Use the existing production outbox and `customer_proof_ready/v4` shared branding,
one account-bound portal button, a usable plain-text fallback, and Shay sign-off.

Current source baseline: f5bc140e4fcab1205c9d5f6fda724a2843a1909a.
Historical outboxes 769 and 772 must remain immutable. Do not release proofs again,
run broad notification dispatch, select a concept, charge, or launch a site.

Completed: verified live customer ownership, all three concepts and research brief,
no recorded selection, and earlier receipts 769/772. Passed 42 protected controller
and asset checks. Exact production-rendered HTML passed Chrome inspection at
1920px and 390px, with no horizontal overflow, a 58px review button and the approved
logo bytes. This is not a new customer browser login or actual email-client test.

Sent one new reminder, **outbox 842**, at **2026-09-21 19:06:43 UTC / 3:06:43 PM EDT**.
Subject: **Your StockandShip98 website concepts are ready to review**.
Exact immutable key: `website-request:17:proofs:56:owner-reminder-20260921-v1`.
The existing `customer_portal.queueNotification` and exact-key
`lifecycle_operations.dispatchNotifications(1, [key])` path recorded SMTP
acceptance, one attempt, no failure or retry. This row has a one-attempt limit.
An independent readback confirmed the result. Historical outboxes 769/772 and the
request remained unchanged. No renderer, deployment, proof or selection changed.

The original customer handoff had omitted correction outbox 772 from September 19
at 01:02:52 UTC. Its live receipt was reconciled; it was not resent. The staff UI's
not-queued label remains a known lookup defect, not delivery truth.

[Redacted SMTP receipt](../evidence/stockandship98-reminder-2026-09-21/reminder-receipt.redacted.json)
and companion readback/access/presentation evidence are retained. Inbox placement,
reading and client approval remain unverified. Recipient and private message copy
remain in Drupal and private operational storage, outside Git.

Customer knowledge was updated in the canonical independent repository at
`/Users/famtastic-fritz/Development/FAMtastic/sites/site-stockandship98`, including
`docs/PROOF-REMINDER-2026-09-21.md` and the startup handoff. No new reminder is
authorized by this completion record.
