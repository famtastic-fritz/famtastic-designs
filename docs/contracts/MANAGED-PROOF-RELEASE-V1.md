# Managed independent proof release v1

September 22, 2026. Source-only, unregistered and disabled. This extends the existing
AutomatedProofRelease/portal operation; it is not another mail system, generation
routine or installed scheduler. No live release, send or worker activation occurred.

## Trusted dependencies, not worker assertions

`ManagedProofRelease::release` receives the existing normalized research, policy
evidence and authorized personal notification, plus a trusted principal. The
receipt-aware reader authenticates that principal for the exact request and
excludes every producer recorded in the authoritative import. A reviewer string
in a body cannot become the principal. Missing dependencies fail closed.

The trusted local `verifyEvidence(evidence, context)` dependency must inspect the
actual retained evidence for all nine existing checks: desktop/mobile, accessibility,
links, functional behavior, rights, claims, no live checkout and distinct directions.
It verifies their exact hashes/references and reviewer/import binding; booleans alone
are insufficient. This resolver and real reviewer provenance are **not installed**
by the source tests. Test bytes and authentication are explicitly synthetic.

`authenticateReplay(principal, committed, request)` separately authenticates access
to an already committed historical decision. It does not grant customer reads,
refresh approval, retry SMTP or reset selection/revisions. Both dependencies are
local/read-only, run outside transactions and cannot make provider/network calls
or recursively invoke this release. They are never passed through HTTP input.

## One operation, one existing outbox

Before writing, the service verifies actual package bytes through the reader,
current receipt/producers, normalized research and every required policy/evidence
binding. It checks files again after the evidence resolver, outside a transaction.

Then one root transaction:

1. Lock current request/account/active Drupal user/membership/prospect/asset rights,
   then the shared worker mutex, job/claim and committed receipt graph. Validate
   the actual receipt and strict original snapshot, reversing only the recorded
   pending-import review transition locally. Selected/revised work is not fresh QA.
2. Match the exact verified customer email, request/campaign notification key and
   single account-bound `/portal/` destination. Existing history is never adopted.
3. Insert the research snapshot with independent reviewer/policy attribution and
   uid0, not Fritz. Use the existing `queueNotification` and
   `customer_proof_ready/v4` renderer; retain personal copy unchanged. Exactly one
   scoped queued row with max_attempts1; this operation does not send it.
4. Recheck already-owned authority after dependent writers, CAS pending state to
   customer_ready with NULL human approver, and insert the immutable managed release
   event containing import binding, policy/evidence, research/outbox hashes and time.
5. Before commit, perform locked DB readbacks of the exact planned request,
   release decision, research and complete fresh outbox row (including queued,
   unclaimed, unsent state). Revalidate the receipt graph and all current account/
   asset authority after the final write. Ignored writes and late hooks must still
   roll back everything. Evidence/file callbacks never run under these locks.
6. Commit the root, verify the committed records and return a receipt. Lost commit
   acknowledgment withholds success; a new authenticated exact retry reconciles it.

Late failures roll back research, outbox, reveal and event together. No job/claim,
paid reservation, historical reminder, provider, payment, selection or deployment
is modified. No long file processing occurs under the database mutex. Existing
legacy owner-approval/research-save methods reject managed campaigns, so they cannot
stamp a human approval or bypass this combined operation. Unmanaged behavior remains.

## Shared current authority, not a parallel validator

`ManagedProofCurrentBinding::read` returns unnormalized DB facts outside transactions.
The reader still authenticates reviewer/customer release **before** reversing the
known review field and invoking FreshProofBinding's strict input comparison. This
read result is not lifecycle permission or a later write grant.

`lockPending` requires the caller's primary connection and active transaction; it
does not own/commit one. It accepts only owner_review with no approval/notification/
selection/archive, and reuses FreshProofBinding/FreshProofInput rather than inventing
a relaxed snapshot. It rejects expired campaigns and time preceding import.
`ManagedProofImportReceipt::committedLocked` reuses the existing receipt verifier,
separating lock acquisition from pending-versus-completed state. Its caller must
already own upstream request/authority/mutex. Expected request/hash prevents it
from acquiring a different upstream request from an untrusted job hint. Existing
committed and pendingLocked meanings remain unchanged.

`assertNewReleaseUnchanged` is a separate final transaction invariant, not a read
or release grant and not an expansion of `lockPending`. It requires the exact
original pending request plus only this operation's four reveal fields. It locally
reverses those four fields, reuses the locked graph, and compares the whole result
with the original pending snapshot. A late variant/receipt, consent, account,
membership or campaign mutation therefore rejects while rollback remains possible.

These are database and service tests, not proof of installed Drupal hooks, real
MariaDB release contention, full browser authentication or linearizable revocation.
Private immutable file validation and DB commit are distinct durability boundaries.

## Read grant and retry are different

`customerGrant` supplies the existing reader's exact contract from the **stored**
managed release: receipt/package/producers, request/customer/campaign, current
release state/time and immutable evidence/release hashes. It verifies policy,
research snapshot, exact outbox body/template/key and retained evidence; flags alone
never grant reads. The reader additionally checks current account/rights and bytes.

Exact repeat release returns `historical_acknowledgment_only: true` and the original
outbox without writes, even after selection or withdrawal. It reauthenticates the
reviewer, verifies the committed import/release/research/outbox and retained evidence,
and requires identical input. It does **not** claim the current files remain readable,
that the customer may still select, or that mail was sent. Changed input rejects;
foreign/unconfigured replay does not adopt a decision. Current customer reads can
remain denied while an authorized historical acknowledgment succeeds.

All receipts say `email_sent_by_this_operation: false`. Actual SMTP acceptance,
uncertain delivery and readership remain separate; no database transaction can
guarantee external SMTP exactly-once behavior.

## Remaining integration gates

Runtime registration of the real principal/evidence dependencies; repeatable bounded
pending-import review consumption; released portal metadata; selection/adoption of
the actual winning package; existing Mac creative runner/provenance; installed
whole-journey, interruption/duplicate/contention proof; cloud authorization and
laptop-unavailable canary. A queued notice must not be dispatched until its actual
protected portal proof access is installed and verified. No activation follows from
this source checkpoint. Exact executed evidence is recorded in
`../evidence/MANAGED-PROOF-RELEASE-2026-09-22.md`.
