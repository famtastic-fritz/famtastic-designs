# Isolated MariaDB shared-worker proof harness

September 21, 2026. **PHP contention proof unrun; parent provisioning failed.**
At original source checkpoint `0972b8f7`, no PHP/Node execution, syntax check,
test, container, pull, install, site bootstrap, provider, notification or migration
was performed by this lane. The later parent-owned attempts are retained below.
Read-only source/fixture hash and Git whitespace checks are not runtime proof.
The integrating parent separately reports frozen candidate full PHP 532 tests /
2,824 assertions passing. That receipt does not establish MariaDB contention.

## Retained parent provisioning failures

Main reports its first attempt stopped before the container process started:
container `cd0947f68e9d11cc78b6cf5626fb803f1faf8bd5f06990f2eb115a053e2d7497`,
status `created`, PID 0, exit 128. Error:
`failed to initialize logging driver: compression cannot be enabled when max file count is 1`.
Retain the main-owned run directory ending in
`/T/famtastic-worker-mariadb-3Fh2DZ`; the full temp-root prefix is not supplied here.
This is a provisioning failure, not a PHP assertion or concurrency result.

The source-only repair adds `--log-opt compress=false`, preserving the local
driver's `max-size=1m` and `max-file=1` bounds and every other orchestration guard.
No agent runtime was used to validate the repair. Exact-owned cleanup of the old
allocation and any reviewed rerun remain main-owned; neither is claimed complete.

Main reports the second attempt, with run-directory suffix
`/T/famtastic-worker-mariadb-t5wRpU`, started MariaDB but failed the
`unsafe_published_port` guard. Inspection showed requested HostIp `127.0.0.1`,
empty requested HostPort, actual `NetworkSettings.Ports["3306/tcp"]` null and
network `Internal: true`. No PHP database test ran. This report establishes
container process startup, not host-loopback connectivity or contention proof.

The next source-only revision removes `--internal` and requires `Internal: false`
on the dedicated bridge. The exact `127.0.0.1` actual-published-port gate remains
unchanged; no missing/null publication is accepted. Main owns cleanup of t5wRpU
using the pre-change `ed94d9bf` provisioner, which requires its internal bridge.
The new guard intentionally does not accept that old network. No cleanup is
claimed here. Independent orchestration review precedes any new allocation;
this lane has not executed the revised provisioner or PHP runner.

## Frozen inputs and exact new source

All executable harness code is outside the document root, in
`backend/tests/worker-mariadb/`:

| File | Purpose |
| --- | --- |
| `provision.mjs` | Main-owned, separately authorized Docker allocation and exact-owned cleanup only |
| `bootstrap.php` | Private loopback configuration, source/vendor checks, real Drupal MySQL driver, server identity gate |
| `inputs.php` | Synthetic static/proof payloads, explicit 100-cent reservation policy, injected clock and ineffective busy hint |
| `peer.php` | One independent PHP process / one real PDO, actual coordinator and ledger calls |
| `process.php` | Two bounded child processes, pipe barriers, owned process termination, disk/time guard |
| `scenarios.php` | Sixteen current-source cases and four selected negative controls |
| `run.php` | Serial orchestrator, sanitized JSON receipt and exact exit semantics |
| `lineage.json` | Source, image and byte-hash manifest |
| `fixtures/WorkerCoordinator.f6621cfb.fixture` | Exact old coordinator, explicitly loaded only for controls; never autoloaded |

Production source is unchanged. Source under test is
`1ac3bc26bfc5c60462bf48a9556336880d2b556e`. `lineage.json` freezes the coordinator,
mutex, policy, schema, ledger, install definitions and Composer manifests. A
source mismatch refuses before fixture writes; do not silently regenerate hashes
against an unreviewed integration. Source re-anchoring requires another review.

The baseline fixture is the complete `WorkerCoordinator.php` from
`f6621cfb6213ac69582b11a8cf267500456e03b0`: **17,542 bytes**, SHA-256
`88cde8bce9ad1f69ae553df7713ea56f63125900eab717cedbb58cb4a6310f70`.
Its exact bytes/hash were compared with the Git blob during source preparation.
Runtime does not call Git or need historical commits. Only the coordinator is
replaced in memory; other dependencies remain the frozen candidate. This is a
targeted old-coordinator control, not a replay of the entire historical checkout.

The only permitted image is the already cached ARM64 MariaDB 10.11 image:
`sha256:ce66c7be32a03aabe7241d0a10993a2db827ef652a35d25727d92a832ac8ef73`.
The provisioner pins the reported local Colima Unix socket
`unix:///Users/famtastic-fritz/.colima/default/docker.sock`, not ambient context.
Those image/engine facts were supplied by the main lane, not newly exercised here.

## Orchestration review gate

Do not run these files merely because the source exists. Main must first review
the exact commit, current free host disk (at least 200 MiB), current free engine
RAM for the additional 768 MiB cap, and exclusive runtime ownership. Reported
engine total RAM is not free RAM. Do not touch existing owner containers, volumes,
WordPress, Postiz, Temporal or any installed Drupal database.

The isolated resource envelope is one dedicated ordinary bridge (`Internal: false`)
and one generated-name,
run-labeled container: one CPU, 768 MiB memory with no additional swap allowance,
128 PIDs, read-only rootfs and no-new-privileges. All database state is tmpfs:
256 MiB `/var/lib/mysql`, 8 MiB `/run/mysqld`, 32 MiB `/tmp`. The first two mounts
are 0700; container-only `/tmp` is bounded 1777 for the unprivileged mysql process.
There are no bind/host/anonymous data volumes. Container logs are capped at one
1 MiB file with compression explicitly disabled. Main reports process startup on
the second attempt; successful end-to-end provisioning remains unproven here.

Publication is one dynamically assigned `127.0.0.1` port, excluding 3306/3400.
The ordinary bridge permits container NAT egress: **container egress is not
firewall-disabled**. No external traffic, external SQL, provider operation or
remote connection is requested by this harness, but no zero-egress observation
or firewall-denial proof is claimed. The DB contains only generated synthetic
credentials and fixture data. PHP children still inherit the unchanged wrapper's
external-network denial and protected-database exclusions. This revision makes
no global Docker, host-firewall or sandbox-policy change.
No external port, existing socket database or ambient DSN is accepted. Creation
uses `--pull=never`. Buffer pool is 32 MiB, redo log 16 MiB, max connections eight,
performance schema and binary log disabled. Each operation checks the inspected
image, exact name/label, mount, resource, network and port identities. Allocation
has one monotonic 60-second overall deadline including Docker calls; each call
is capped at the remaining time and 15 seconds (three seconds for readiness).
Timeout kills the owned Docker client, NOT an assumed completed daemon operation.

Readiness uses TCP inside the container, not the entrypoint's temporary socket
server. Host-loopback connectivity is then verified independently by each PHP
child before ANY fixture schema/data writes: generated account/database, exact
owner marker/run/image/source, server hostname, MariaDB 10.11, REPEATABLE READ.
Provisioner-owned marker creation is the sole earlier SQL write to its new DB.
No fallback endpoint or settings.php is loaded. `PROCESS` is granted only to this
synthetic account on this disposable server so it can observe actual InnoDB
blocking/waiting connection IDs and the mutex table.

## Future commands, main-owned and serial

These are instructions for a later authorized run, **not executed receipts**.
From the reviewed checkout, main provisions OUTSIDE the PHP sandbox:

```sh
node backend/tests/worker-mariadb/provision.mjs create --approve-ephemeral-mariadb
```

Retain the returned exact run root, provision receipt and private connection
file. Do not print/copy connection.json or Docker inspect/environment output into
receipts. Passwords are generated synthetic values; no owner credentials are read.

The existing reviewed wrapper remains unchanged. It allows loopback but denies
external networking and protected databases, checks before/after protected-data
inventories, and watches the 200 MiB disk floor every 500 ms. It may deny Docker
Unix sockets, so do not put provisioning inside it or broaden its policy. Its
existing 600-second ceiling supplements the PHP runner's 240-second ceiling,
55-second response timeout and MariaDB 45-second lock timeout. PHP guard polling
also checks the disk floor; all child processes inherit the sandbox. The two
database processes use the explicit host PHP binary and receive no secret-bearing
parent environment. Each PHP process has a 64 MiB memory limit. No install or
Composer execution occurs.

Replace only `/EXACT/OWNED/RUN/connection.json` below with the printed connection
path. The existing vendor must retain matching composer.json and composer.lock;
the bootstrap refuses a mismatch. These paths describe this isolated checkpoint:

```sh
node /tmp/famtastic-phase2-review.NVAfPl/run-integration-check.mjs worker-mariadb-current "cd /tmp/famtastic-fresh-admission.k98Gm4/designs && FAMTASTIC_BACKEND_VENDOR=/Users/famtastic-fritz/Development/FAMtastic/worktrees/client-messaging-proof-rescue/backend/vendor /opt/homebrew/bin/php backend/tests/worker-mariadb/run.php /EXACT/OWNED/RUN/connection.json current"
node /tmp/famtastic-phase2-review.NVAfPl/run-integration-check.mjs worker-mariadb-baseline "cd /tmp/famtastic-fresh-admission.k98Gm4/designs && FAMTASTIC_BACKEND_VENDOR=/Users/famtastic-fritz/Development/FAMtastic/worktrees/client-messaging-proof-rescue/backend/vendor /opt/homebrew/bin/php backend/tests/worker-mariadb/run.php /EXACT/OWNED/RUN/connection.json baseline"
```

Run commands individually, never concurrently with each other or the main suite.
`current` must exit **0**, report all 16 cases and `status: pass`. `baseline`
must exit **1** with `status: expected_negative_failures` and all four exact tags
below. Unexpected assertion failures exit 1 with `failed_not_negative_proof`;
bootstrap/protocol/timeout/unexpected-pass failures exit **2**, never a useful
negative proof. These are runner exit codes: the existing wrapper itself returns
1 for any child failure, so inspect its recorded child status and JSON output,
not the wrapper exit alone. Keep every red receipt. No PHP case receipt or
syntax/bootstrap success exists here; the parent provisioning failures above are
retained separately. Record the harness commit, wrapper
inventory result, actual versions, case outcomes and resource cleanup separately.

## Assertions and honest scope

| Cases | Actual invariant exercised by the source |
| --- | --- |
| root commit / rollback / real-semaphore commit | Start with no mutex row; two PHP/MySQL connections race lazy insertion. Observe an actual InnoDB mutex wait while caller root stays open at least 35 real seconds; inner success cannot commit it. Releasing root controls the result, one reservation and singleton row; post-wait rollback winner gets a fresh lease. |
| stale active / budget / recovery snapshots | Establish B's real nonlocking REPEATABLE READ view before A commits. Current reads must exclude a second capability, honor the 2,000-cent stop after A's dispatch completion, and preserve A's renewed lease/token. |
| duplicate enqueue commit / rollback | Real selected ledger enqueue under competing root transactions returns/reuses one enrollment on commit or allocates after rollback; no premature budget hold. |
| recovery before renewal | Recovery retains the original full replacement fence; the previous worker's renewal fails without row mutation. |
| claim/job CAS helpers | B changes a stored generation after A captures it; actual private CAS helper rejects and rolls back an earlier sentinel reservation in the same transaction. This is helper-level injection, not an independently staged public-method race. |
| static / proof / real-semaphore static bounds | Preserve 90/300/330 and 180/1800/1830 profiles, heartbeat 30, same-second matched-row renewal, renewal clamping and maximum three attempts. Old generation is rejected, unknown holds retained, proof cannot finish with a selected dispatch receipt. Time progression here is an injected clock, not 30 minutes of wall execution. |
| prior-month hold / legacy writers | Never refund old unknown holds; only current-month holds count toward stop. Existing managed jobs are excluded from legacy claim/complete/fail/requeue. |

Most cases deliberately supply an **ineffective LockBackendInterface busy hint**:
the actual database mutex must carry correctness. Separate current-source cases
compose Drupal's actual DatabaseLockBackend and semaphore schema. Real semaphore
row locks can mask the old coordinator flaw on some installed backends; the
negative controls do not assert universal reproduction with every lock backend.

Baseline expected tags are `ownership_escaped`, `stale_active_admitted`,
`budget_over_stop`, `renewal_overwritten`. Ownership control confirms two leased
rows after both roots commit; budget control confirms 2,100 cents of holds before
reporting the defect. A busy/SQL/bootstrap error is not accepted instead. The old
source may escape immediately; the 35-second hold requirement applies to the
passing current implementation, not to artificially delayed negative results.

Only actual schema definitions for job/event/exception, worker tables and semaphore
are created in the marker-validated ephemeral DB, before transactions. No installed
site/kernel, entity storage, fresh request/account/rights admission, migration,
controller authentication, producer, provider, paid operation, canonical import,
QA, sending, cloud execution or customer delivery is established by this harness.
Synthetic reservation policy is not a supplier cost catalog. All activation gates
remain closed. The minimal schema rejects unexpected existing tables; resets are
restricted to this owned fixture DB. No SQLite stand-in or mocked SQL connection.

## Exact-owned cleanup and failure recovery

After PHP children are stopped, main invokes cleanup OUTSIDE the sandbox with
the exact printed run directory, whether the run passed or failed:

```sh
node backend/tests/worker-mariadb/provision.mjs cleanup /EXACT/OWNED/RUN --approve-ephemeral-mariadb
```

Before allocation the private 0700 host temp directory records the exact intended
container/network names, random run label and phase. Returned IDs and any later
recovered IDs are create-only records. Cleanup reconciles only those exact names,
validates full IDs/labels/image/envelope, then removes only the owned container and
empty owned network. It never searches by prefix/label alone, adopts an unrelated
resource, prunes, removes volumes or recursively deletes a host directory.

A successful filtered Docker listing plus exact identity comparison can confirm
an already removed recorded resource. Thus a retry after container removal and a
network-removal error can finish safely. Inspect/list failure is uncertainty, not
absence. If a timed-out allocation has neither acknowledged/recovered ID nor an
observable exact-name resource, cleanup refuses to claim success: creation may
still be in flight. Main must retain the journal, inspect/reconcile that exact
intent and retry; do not invent IDs or erase an uncertainty marker. Recovered IDs
are recorded before deletion so the next cleanup attempt can confirm absence.

Cleanup removes the synthetic connection file only after both resources have
confirmed absence. All other journals and wrapper receipts remain. There is no
automatic cleanup or later adoption. Journals use create-only files but **no file
or directory fsync**; no crash/power-loss durability or autonomous recovery claim
is made. Lost/corrupt journals fail closed and need main-owned reconciliation.
Provisioning checks disk at entry/allocation/readiness steps, not continuously;
the continuous 500 ms guard applies to PHP wrapper runs. Cleanup is not prevented
by low disk, since removing the exact owned ephemeral resources may be necessary.

Drive mirror, remote synchronization, execution and integration receipts remain
parent-owned. No production capability has been upgraded by this source checkpoint.
