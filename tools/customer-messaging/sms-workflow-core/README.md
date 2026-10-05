# SMS workflow core 0.1.0

Reusable, provider-neutral reminder workflow for an independent service business. This is a **source candidate**, not an installed sender. It is disabled by default, has no credentials, contacts, provider account or scheduler, and sends nothing on import. The existing pinned `consent-sms-loop` Textbee adapter remains an isolated Fritz-only lab. A customer site must own its appointment truth, consent, suppression, durable outbox, provider account and admin interface.

## Install into a customer repository

1. Copy this entire versioned directory into the customer's own repository at a chosen path and record the FAMtastic source commit and file hashes. Run `npm test` here and in the customer copy. Do not import it at runtime from the agency repository.
2. Implement a customer-owned `store.reserve` transaction and `store.recordOutcome` audit. `reserve` must atomically check that the appointment is still confirmed at the expected version, the exact phone still has current SMS consent, no suppression exists, the idempotency key is unique, and the business/sender's daily quota has one unit available. It must create the outbox claim and reserve that quota unit in the same transaction. `recordOutcome` commits the reserved unit as used after acceptance, releases it only after definitive failure, and holds it for review on uncertainty. A boolean `atomicReserve: true` is an explicit adapter declaration, not proof; test the database implementation under concurrency.
3. Configure an approved business sender and a transport adapter. Never borrow Fritz's personal Textbee lab key/phone for a customer. The host must keep provider secrets outside source, limit sender permissions, and follow current provider and carrier requirements. Inject the transport only after this setup is verified.
4. Install a bounded scheduler that selects due confirmed appointments, calls `sendReminder`, and stops on `unknown` or `outcome_unknown`. Operator review and provider reconciliation precede any new attempt. Do not replay an uncertain send.
5. Add an authenticated owner admin: connection/sender state, quota used/reserved/remaining with reset time, editable approved template versions and preview, per-message audit/status, consent and STOP state, manual-send preview/confirmation, and a clearly labeled pause switch. A manual message uses the same consent, quota, idempotency and audit path, with its own reason and key.
6. Verify a release-matched phone journey, replies, STOP, quota exhaustion, failed delivery and rollback before enabling one customer. Record local tests, source push, hosted code, provider connection, actual send/delivery, and owner acceptance separately.

## Public API

`renderSmsTemplate(template, values)` accepts only `status: "approved"`, explicit `id`, `revision`, `fields` and a body of at most 160 Unicode code points after substitution. A reminder template should state the business, appointment time and reply instructions in its approved copy. Email's visual shell is not reused in SMS; only the underlying content/version approval and audit principles carry over.

`assessReminderEligibility({appointment, consent, suppression, now})` is an early refusal gate. It requires a future, durably confirmed appointment and explicit SMS opt-in tied to that phone. Email or newsletter consent never qualifies. The host must repeat these checks inside `reserve` immediately before send.

`assessQuota({used,reserved,limit})` computes a displayable remaining count. It is advisory; the transaction is authoritative. A zero limit pauses sending. Provider account quota and business policy quota are distinct; the lower effective allowance wins in the host.

`reminderKey` hashes business, appointment, version, template, revision and recipient into a stable idempotency key without printing the phone. A reschedule creates a different key, but the old unsent claim must be superseded by the host.

`createSmsWorkflow({enabled,store,transport,clock})` rejects every send unless explicitly enabled. `sendReminder` reserves before calling the transport once, then records the outcome. Accepted means provider accepted, not handset delivery. A timeout or audit-write failure becomes `unknown` / `outcome_unknown` and must not be retried automatically. `reconcileProviderStatus` allows signed, deduplicated, business-matched provider evidence to update state; contradictory terminal reports go to `needs_review`.

## Host ledger schema and boundaries

Recommended tables or equivalent durable records, all scoped by business: `sms_consent` (phone, state, exact wording/version, source, recorded/revoked times), `sms_suppression` (phone, reason, time), `sms_template` (ID, revision, body, field list, approval actor/time), `sms_outbox` (unique business+idempotency key, appointment/version, phone, message snapshot, claim/lease, state, provider reference, timestamps), `sms_quota_day` (business, sender, local day, used, reserved, limit), `sms_event` (unique provider event ID, raw-reference hash, verified signature result, transition), and `sms_reply` (matched message/appointment or owner-review queue). Encrypt or protect phone/message content according to the host's data policy; never log raw secrets.

Owner confirmation or cancellation from `YES`/`NO` replies requires an explicit business policy and durable appointment match. `STOP` updates suppression before any more sending. Provider webhook signature verification and duplicate handling are host obligations; the existing `consent-sms-loop` package offers a lab-oriented Textbee verifier, but no endpoint is installed by this package.

## Workflow states

`pending → reserved → provider_accepted → sent → delivered` is the happy path. `reserved → failed` is a definitive rejection. `reserved → unknown` and any failed audit write require review; do not create a second send. Duplicate, quota exhausted, invalid consent, suppression or changed appointment end in `not_sent`. A later provider `failed` versus `delivered` conflict ends in `needs_review`. Every transition records who/what caused it and when.

## Verification and limits

Run `npm test` from this directory. The suite uses fictional contacts and a fake transport; it proves disabled default, consent/suppression, template bounds, quota math, stable keys, reserve-before-one-send, duplicate/no-send, uncertain outcomes and status non-regression. It does **not** prove any customer's database transaction, scheduler, provider account, regulatory eligibility, phone delivery or owner usability. Those are per-install release gates.

Specialist guidance: `engineering/engineering-backend-architect`, `engineering/engineering-api-platform-engineer`, `specialized/specialized-workflow-architect`, and `testing/testing-api-tester` at pinned Agency Agents revision `765be42358100bf89d2faa567668a94c602f9a26`. Their reliability, quota, versioned-contract and testing prompts informed this package; claimed metrics and stack choices were not imported.
