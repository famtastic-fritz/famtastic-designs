# Shared worker root-transaction serialization

September 21, 2026. Source-only change from `f6621cfb6213ac69582b11a8cf267500456e03b0`.
Focused SQLite/SQL-contract verification below passes. Real MariaDB contention,
installed-site migration and integration regression remain separate gates.
No creative adapter, importer, paid-operation authority or activation is added.

## Correctness boundary

`WorkerCoordinatorMutex` uses the caller's primary Drupal Connection and active
transaction. Table `famtastic_worker_mutex` has only primary key `id`; code uses
the fixed value 1. A single insert-or-self-update both creates the row lazily and
obtains database write ownership. There is no authority payload, timestamp, TTL,
PHP ownership cache, deletion or manual release. MySQL/MariaDB uses `INSERT ...
ON DUPLICATE KEY UPDATE id = 1`; SQLite uses `ON CONFLICT (id) DO UPDATE SET id =
excluded.id`. Other drivers reject. Drupal's ordinary key-only MySQL upsert emits
INSERT IGNORE and is deliberately not used.

`run()` begins a transaction/savepoint and explicitly commits/releases that scope
or rolls it back on failure. A nested success cannot commit its caller's root
transaction; the mutex remains owned until root commit/rollback. Deadlock, timeout
or stale SQLite snapshot errors fail closed. Do not retry only a suffix of a
failed root transaction. No DDL or external/provider operation belongs inside it.
The outer caller must commit before exposing a claim for external execution.

The old advisory lock remains a busy hint, acquired AFTER the database mutex.
Its expiration or early PHP release is not the correctness mechanism. The default
Drupal database-lock backend can itself retain transactional semaphore locks;
early release alone was not proof of a race on every installed backend.

Order: request and existing tenant/rights authority -> singleton mutex -> job,
claim and budget decisions. Fresh proof admission acquires before history checks
or campaign/job allocation. Selected admission acquires before its locking
duplicate recheck and job insert. Managed reuse uses current job/claim reads.
Re-reading already-owned authority is permitted; never acquire a new upstream
request lock from a mutex-first worker operation. New callers must follow this
order rather than lock jobs first and then call the coordinator.

All coordinator decision reads request FOR UPDATE, including pending selection,
recovery, active fences and monthly budget base rows. Count/sum is performed on
locked base rows, not snapshot aggregates. Health remains nonlocking and does
not acquire/create a mutex row. Lease timestamps are sampled after waits; a month
change during the budget read rejects instead of booking against an old month.

Critical claim/job updates compare the previously locked generation/state,
identity and binding fields and require exactly one matched row. A mismatch
rolls back earlier reservation/claim writes. Expiry recovery also refuses a job
whose current status or payload differs. Existing replacement fences, retry
bounds, static defaults, $20 stop and immutable unknown-cost holds remain intact.
Proof completion remains closed.

Legacy claim, completion, failure and requeue also serialize before job access.
They exclude any existing claim, including exhausted/completed claims; exact-job
writers reject orphaned worker-prefixed statuses too. Ordinary unowned jobs retain
their legacy transitions and duplicate-completion behavior. This is not a new
legacy token/lease protocol. The existing quarantine command addresses only its
specific legacy prospect/outreach keys, not enrolled request/selected job keys.

## Schema and rollout

`WorkerCoordinatorSchema::tables()` includes the empty table for fresh installs.
New update 8066 creates only that table when absent: no seed, enrollment, refund,
settings change or service activation. It was unused at the assigned base.
No migration has been run. Missing schema fails closed, including legacy job
writers using this coordination boundary; install the schema before running the
new code. Existing services/constructor arguments are preserved; no DI change.

## Executed evidence, and limits

The owner-authorized serial wrapper was
`/tmp/famtastic-phase2-review.NVAfPl/run-integration-check.mjs`, with its 200 MiB
disk guard, external-network denial, protected-data denial and before/after
inventories. Composer manifest and lock matched the existing dependency runtime.
No install, live-site bootstrap, authoritative DB, container or provider was used.
Each receipt below reports `protectedDataUnchanged: true`.

- `worker-root-mutex-first.YKPNTM`: setup failed before PHPUnit because a nested
  sandbox could not be applied. The existing outer wrapper was retained.
- `worker-root-mutex-focused.wn5QFW`: 244 tests / 1,282 assertions / 10 errors.
  The new SQLite trigger fixture needed Drupal's explicit delimiter option for
  its single CREATE TRIGGER statement. Only that fixture was corrected.
- `worker-root-mutex-current.xR6s6k`: **244 tests / 1,312 assertions, all pass**;
  PHPUnit 11.5.56, PHP 8.5.9, 32 MiB; suite 0.574 s, guarded run 0.831 s.
  Six files: WorkerCoordinatorMutexTest, WorkerCoordinatorTest,
  WorkerCoordinatorControllerTest, SharedProofWorkerClaimsTest,
  FreshSelectedJobAdmissionTest and FreshProofAdmissionTest.
- `worker-root-mutex-baseline.6wSLkg`: only the old coordinator/ledger from
  `f6621cfb` loaded in memory; 17 selected regressions / 18 assertions / 17
  expected failures. No source files replaced. Historical Git is needed for
  this retained diagnostic replay, NOT for the normal tests or CI acceptance.
- `worker-root-mutex-syntax.irICQM`: all ten changed/new PHP files and whitespace
  checks pass. No runtime migration was invoked.

All receipts are beneath `/tmp/famtastic-phase2-review.NVAfPl/`. Disk remained
above 200 MiB; no cleanup was performed. Tests use actual in-memory SQLite,
triggered zero-row failures, stale-row CAS checks and deterministic observation
hooks. SQLite ignores FOR UPDATE: observing that API request is NOT evidence of
MySQL row locking. Native MySQL SQL is checked against the installed Drupal API,
not executed against a server. No production/concurrency capability is upgraded.

Required next proof, owned by the integrating lane: isolated real MariaDB with
two independent connections/processes; competing lazy insertion; outer commit
and rollback while another process waits beyond 30 seconds; a preexisting
repeatable-read snapshot; competing capabilities/budget reservation; renewal vs
recovery and legacy writers. Bounded lock timeouts, deterministic barriers,
fail-closed root rollback and retained failed receipts are required. Do not
start a database/container merely because its image is cached.

Drive mirroring, remote synchronization, full integration tests and production
release remain parent-owned. Sparse-omitted campaign assets were not hydrated.
