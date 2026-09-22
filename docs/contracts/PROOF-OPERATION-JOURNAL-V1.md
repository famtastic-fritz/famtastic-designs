# Managed proof paid-operation journal v1

September 21, 2026. Source groundwork from fixed
`4f139cf0ab71d78a7bfd887c97616eb25f690522`. Parent verified the `aad9e14e`
baseline below; three follow-up regression cases remain UNRUN. This helper ran
no PHP, PHPUnit, Docker, provider, installation, network or authoritative DB work.
The parent owns the runtime slot, disk guard, integration and Drive mirror.
This is not provider exactly-once execution, connected automation or activation.

## Scope and reuse

`ProofOperationJournal` has no service registration, route, CLI or caller. Its
catalog and both verifier closures default empty; every operation then rejects.
No production recipe slots, prices, provider adapter or verifier is supplied.
The Mac remains the existing agent-authored creative routine, not the fictional
six-direction benchmark, static packet bridge or Firestore shadow workflow.

`FreshProofAdmission::reuse()` delegates to a transaction-only record-returning
`lockCurrentBinding()`. Checks and legacy behavior stay unchanged. The coordinator
adds `lockOwnedProofClaim()`, delegating to its actual ownership checks. Both new
seams require the exact same Connection as their caller. Neither returned record
is authority for an external effect after the transaction.

`WorkerCoordinatorSchema` adds `famtastic_proof_operation`; update 8067 was free
at the fixed base and creates only that empty table. No migration was executed.
Recheck update-number allocation against newer parent source before integration.
No historical enrollment, record conversion, budget change or settings change.

## Immutable authorization

The unique `(job_id, slot)` key, and its deterministic operation SHA256, exclude
worker, attempt, prompt and provider. A retry cannot mint another identity for
the same slot. Only source-injected recipe slots are accepted. Their exact recipe,
tool allowlist and cost policy must match the frozen job; the full slot-policy
digest remains identical across every operation for that job. Slots explicitly
name the adapter, tool, positive cost ceiling, timeout and lease headroom.
Repair/fallback slots need prior review too; caller-created slots reject.

`authorizeSubmission()` validates bounded prepared-input digests through a trusted
local verifier OUTSIDE the transaction. That verifier must prove complete sealed
input/prompt bytes, exact request/admission correlation and exhaustive asset IDs;
the journal cannot establish those facts from caller hashes. No verifier is wired.
Inside its OWN root transaction it takes current request/account/assets, then
the existing mutex, then job/claim/journal/budget locks. It rechecks admission,
campaign, server-granted proof capability, token, attempt and live deadline.
Claimed asset use conservatively requires all recorded ownership, AI, subject and
transformation consents and likeness version/time. It does not infer consent or
provide a looser non-likeness media policy. Current rights are checked on resume too.

All job generations share max_calls and the sum of operation cost ceilings.
Operations allocate beneath existing immutable attempt holds, not a second global
spending ledger. A new submission needs its exact current-month attempt hold.
Prior-month holds stay unchanged. Existing $20 stop/$25 authorization, selected
90/300/330 and proof 180/1800/1830 profiles, max-three claims, static defaults and
legacy isolation remain unchanged. A resumed checkpoint consumes no operation slot;
a replacement claim still incurs the coordinator's existing conservative hold.

Before permission is returned, an immutable `submission_unknown` row commits.
The state means submission MAY occur, not that it happened. Both commit and Drupal
post-transaction callbacks must return successfully before the caller receives
`submit_once`. An ignored insert, root rollback, uncertain commit acknowledgement,
callback failure, aged lease or month change cannot return a permit. A persisted
unknown row survives such failures and a retry returns reconciliation_required.
No permission is returned from a nested transaction/savepoint.

There is no reset, delete, auto-refund or permission-renewal operation. Any unknown
operation blocks NEW paid operations globally, even after its old claim expires.
This deliberately conservative gate affects only the new paid-operation seam,
not the existing static claim workflow. Caller resubmission of a completed slot
returns its checkpoint/known failure, never another permit. Three exhausted claims
stay exhausted; receipt storage does not grant a fourth attempt.

## Receipt and checkpoint boundary

`recordReceipt()` is evidence-only. It accepts exact bounded metadata: operation
and input digests, adapter ID, opaque provider request ID, terminal outcome, explicit
unknown/verified cost, nullable actual cents and a small checkpoint hash inventory.
At most 64 KiB serialized, 32 unique items, identifier names and 1..65536-byte item
declarations. No free text, media, prompt body, credentials, path or URL fields.
It is not a secret-content scanner; opaque IDs must also be sanitized by the adapter.
There are no artifact reads/writes or generic asset-cap changes.

The separately injected local receipt verifier must authenticate the recorder,
original provider/operation binding and terminal evidence. It runs outside the
transaction; the journal then rechecks immutable bytes under its mutex and uses
an exact-row CAS. Worker-supplied success/zero-cost assertions are not a verifier.
Late verified evidence can be recorded without an expired lease or withdrawn
rights becoming usable again. Original claim worker/attempt remain immutable;
the first receipt recorder is a separate identity and duplicates cannot replace it.
Conflicting receipts, unsupported identities or cost exceeding the ceiling reject
and retain the unknown hold. No hold is reduced, even for a verified low/zero cost.

`resumeCheckpoint()` requires a CURRENT live claim and unchanged full authority.
It returns sanitized receipt/checkpoint metadata, not recovered media bytes or
permission to publish. Missing/corrupt metadata rejects. Unknown billing remains
null and held. Verified terminal execution can resolve submission uncertainty
without pretending billing is known. All claim/job/campaign/review/outbox state
is untouched. Generic managed imports and proof finish remain closed.

## Verification source and precise remaining gaps

`ProofOperationJournalTest` uses actual admission/coordinator and real in-memory
SQLite tables. Campaign entities and local verifiers are synthetic. It covers
one-permit replay, replacement-generation positive resume, producer/recovery
identity, call/cost bounds, current account/rights, stale claim, receipt conflicts,
closed receipt fields/bounds, original month, ignored insert, receipt CAS,
root rollback, real-commit acknowledgement loss and actual post-commit callback
failure. The connection fixture surrounds actual Drupal SQLite commit APIs with
deterministic faults. Lock API observation is NOT MariaDB contention proof.
Parent-reported baseline receipt, exact `aad9e14e2d63a4b2287eae75f8794786ec1d187e`:
257 tests / 1,689 assertions PASS, 0.707 seconds suite / 0.957 seconds guarded,
34 MiB; protected inventories unchanged. Evidence:
`/tmp/famtastic-phase2-review.NVAfPl/proof-operation-journal-first.H8Yaww`.
This is synthetic SQLite evidence, not provider or real journal contention proof.

Follow-up source adds three cases, NOT covered by that baseline receipt:

- `testUnknownOperationBlocksSecondJobUntilVerifiedTerminalReceipt`: actually
  admit/claim another request/job after the first deadline, assert the global
  unknown guard, then positively authorize it after terminal receipt evidence.
  Unknown billing and both original budget holds remain unchanged.
- `testThreeExhaustedGenerationsRetainReceiptWithoutFourthClaim`: use actual
  recovery/backoff for all three claims; late evidence retains the original
  identity and three holds without reopening a claim, checkpoint or next slot.
- `testPostCommitMonthRolloverWithLiveLeaseRetainsUnknownWithoutPermit`: advance
  the clock through the real post-transaction callback, retaining 178 seconds of
  lease. The exact month guard withholds permission after committed unknown;
  retry reconciles and September holds are not moved into October.

Only test-source/docs changed in this follow-up. No runtime or syntax checks ran;
the parent owns the next guarded run. No production defect is established by
source inspection, and no activation or capability upgrade follows from it.

Parent-only next command, through its reviewed network/protected-data/disk wrapper:

```sh
FAMTASTIC_BACKEND_VENDOR=/Users/famtastic-fritz/Development/FAMtastic/worktrees/client-messaging-proof-rescue/backend/vendor \
/opt/homebrew/bin/php /Users/famtastic-fritz/Development/FAMtastic/worktrees/client-messaging-proof-rescue/backend/vendor/phpunit/phpunit/phpunit \
  --bootstrap scripts/automation-test-bootstrap.php --no-configuration --do-not-cache-result \
  backend/web/modules/custom/famtastic_pipeline/tests/src/Unit/ProofOperationJournalTest.php \
  backend/web/modules/custom/famtastic_pipeline/tests/src/Unit/FreshProofAdmissionTest.php \
  backend/web/modules/custom/famtastic_pipeline/tests/src/Unit/SharedProofWorkerClaimsTest.php \
  backend/web/modules/custom/famtastic_pipeline/tests/src/Unit/WorkerCoordinatorMutexTest.php
```

Before any activation: independent source review, guarded tests/lints, real journal
MariaDB contention, installed migration, reviewed actual pricing/slot policies,
complete private-input verification and receipt provenance/recovery adapter.
A real adapter must disable implicit SDK retries, immediately recheck lease/time
against the full provider timeout plus headroom, and enforce exact submitted bytes.
No DB transaction may contain provider HTTP, lookup, file processing or sends.
The transaction-to-HTTP suspension/race cannot be eliminated here. Automatic
ambiguous-outcome retry needs real provider idempotency or authoritative receipt
lookup; without it, stop and retain uncertainty. No provider is selected or invented.

Checkpoint metadata is not an immutable media store. Larger output recovery,
canonical server-owned logo policy, fenced import, Build DNA projection,
independent QA and separate notification authority remain unfinished. There is
no portal projection wiring for this unregistered journal. Any future adapter
must expose unresolved work truthfully rather than imply generation/delivery.
