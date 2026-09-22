# Request-first reference-asset mutations

Status: local source and synthetic transaction verification only; not deployed.
This is a prerequisite for creative-worker rights checks, not a provider license.

## Shared authority order

Both customer writers now acquire the parent request row before current active
membership and the matching asset row. Locking the parent also serializes a first
upload into an empty asset set. `ownedWebsiteRequest(..., TRUE)` requires an
existing transaction and reads the membership row with `FOR UPDATE`; its default
read-only behavior and active-membership authorization policy are preserved.
Do not introduce a worker that checks rights, releases the lock, then treats that
old snapshot as permanent permission. Admission, provider authorization and
fenced import must revalidate their immutable asset-authority binding.

## Upload boundary

1. Validate the authenticated owner, explicit consent, supported file type and
   existing size bound. An initial duplicate lookup is advisory, never authority.
2. If preparation is necessary, use create-only random private filenames via
   `FileSystem::saveData`, outside the database transaction. This creates bytes,
   not a permanent managed-file entity.
3. Start a root transaction; lock the request, current membership and same-hash
   asset. Recheck ownership and duplicates. Withdrawn bytes cannot reactivate by
   upload, and active duplicates do not silently upgrade the original consent.
4. Only after that check, create/save the permanent file entity, insert the asset
   metadata and add file usage in the same transaction. Commit explicitly.
5. Reconcile any selected build separately. A losing race can leave an
   unreferenced private file, but no asset-use authority or managed-file record.
   No automatic destructive cleanup is included.

`FileRepository::writeData` is not filesystem-only preparation: the installed
implementation itself creates, marks permanent and saves an entity. Independent
review caught that distinction; moving only an explicit `setPermanent()` call
would not repair it. The controller no longer injects that unused repository.

## Withdrawal boundary

Withdrawal rejects an existing outer transaction: releasing a savepoint cannot
promise the root commit required here. Request/member/asset locks protect an
active-to-withdrawn compare-and-set and exactly-once audit insert. Revocation and
audit commit together. Repeated withdrawal retains the existing row/event.
Unexpected state or conflicting active-asset audit rejects without silent reuse.

Selected-build reconciliation runs **after** commit and outside the revocation
rollback catch. If it fails, rights remain withdrawn. The existing HTTP behavior
is retained: invalid input maps to 404; other reconciliation errors propagate.
Neither response is permission to use the asset again. A retry may reconcile the
already-withdrawn record without another revocation event.

## Verification receipt — September 21, 2026

Actual final controller and portal methods run against disposable in-memory
SQLite. File/entity interfaces are doubles; their managed metadata and usage
doubles write into the same test database so rollback is asserted, not inferred
from call counters. An explicit select hook models advisory-read interleavings;
this is **not** an independent-connection MariaDB contention proof or a Drupal
installed-kernel file-storage test.

- Focused: **20 tests / 135 assertions**, PHP 8.5.9 / PHPUnit 11.5.56, 0.090 seconds
  suite / 0.293 seconds guarded command, 16 MiB. Receipt
  `request-asset-final-focused.u1Kgwi`.
- Full module: **611 tests / 3,229 assertions**, zero failures/skips, same 68
  pre-existing PHPUnit deprecations. 1.578 seconds suite / 2.016 seconds guarded
  command, 50 MiB. Receipt `request-asset-final-php.QO2EtZ`.
- Both protected Studio inventories unchanged; whitespace and source syntax pass.
- Retain initial fixture failures: PHPUnit final `count()` helper collision,
  omitted required synthetic display name, then missing user-interface autoload.
  Fixes only repaired test setup. The earlier 14/81 and 605/3,175 passes predate
  the permanent-file review fix and are superseded, not additional coverage.
  The later 19/131 and 610/3,225 runs predate the fresh-active controller 404 case.

The paired legacy PHP fixture now uses the current constructor, current membership
lookup, private-path confinement before directory/file writes, distinct guarded
file-entity/usage dictionaries and nested commit/rollback tracking. An inline
probe verifies nested release is not root commit. Dictionary rollback does not
cover entity objects or ledger jobs; its query double ignores UPDATE predicates.
It cannot prove SQL locking or CAS failure handling; the real SQLite suite and
separate MariaDB harness cover those different boundaries.

First paired three-file run `asset-writer-paired-fixture.UTP9Yk` was stopped by
the 200 MiB disk guard after 87.372 seconds, not a test assertion failure. The source-
association file completed 12 tests before interruption; no full-pass claim. Both
protected inventories are unchanged. Final fixture confinement changed during
that incomplete run, so a frozen-source rerun remains required. The interrupted
synthetic fixture is archived recoverably; exact cleanup is recorded alongside
the receipt. Clean tracked campaign media was omitted from this temporary sparse
checkout only; canonical files and Git objects remain intact.

After final fixture repairs, `normal-selected-assets.test.js` passes its one
multi-step test (8.35 seconds suite / 9.082 seconds guarded) against the actual
upload/withdrawal methods, protected asset reuse and selected continuation.
Receipt `asset-writer-paired-assets.3wTL7B`; both protected inventories unchanged.
The three-file/full paired rerun remains required; this narrower pass is not it.

Receipts/commands/output: `/tmp/famtastic-phase2-review.NVAfPl/`. Standard CI uses
the local backend vendor tree; this workstation borrows the matching dependency
tree read-only via `FAMTASTIC_BACKEND_VENDOR`. No authoritative database is used.

Pending: independent-connection MySQL/MariaDB writer contention, actual installed
file-entity storage integration, pre-provider authorization/checkpoints, fenced
proof import and the complete unattended journey. No customer sends, service
restart, provider calls, cloud resources or live deployment occurred here.
