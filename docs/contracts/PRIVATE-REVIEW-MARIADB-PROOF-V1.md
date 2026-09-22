# Private review / request-asset MariaDB proof

September 21, 2026. **LOCAL SYNTHETIC MARIADB VERIFIED / NOT ACTIVATED**, from
`4998be27695f5e3940212d9cebedb31d923c04a6`. Main executed the reviewed harness
serially in the protected-data/network wrapper: seven current cases / 84 checks
pass, and four frozen controls / 22 checks fail at the exact expected invariants.
PHP 8.5.9, MariaDB 10.11.19, REPEATABLE READ; metadata interfaces remain doubles.
No installed migration, provider, customer message or production change occurred.

Independent source-review follow-up: before rollback, the stale-event case now
compares B's own total events, review-event counts and activity count with its
established RR snapshot. Both asset races require exactly one attachment activity.
Nested retry and refusal must preserve B's own uncommitted sentinel, as well as
keep it invisible to A. Rollback cannot conceal those failures. Only the scenarios
hash changes in the separate lineage; root-worker pins and four expected negative
tags remain untouched. The helper authored source only; main ran the proof below.
Main reports the root-worker prerequisite on owned allocation `VcO6vU` passed
16 cases / 103 checks in 115.201 seconds; that is not a private-review case receipt.

## Scope and identity

Entrypoints are `backend/tests/private-review-mariadb/run.php` and `peer.php`,
outside `backend/web`. No route, registration, activation flag or production
code changes. The only existing harness edit is an optional fixed enum in
`worker-mariadb/process.php`: `worker` retains its old default; `private-review`
selects one fixed sibling. No arbitrary executable/path or shell is accepted.
The root-worker prerequisite and both private-review modes exercised this transport.

Reuse the **same exact generated connection.json / owned temp DB** allocated by
the unchanged worker provisioner. Do not create a second provider workflow or
substitute a site DSN. Its cached-image, loopback port, hostname, generated
database/user, owner marker, matching vendor manifests, resource envelope,
private config and positive-absence cleanup checks remain unchanged. The bridge
has NAT egress; it is not firewall-isolated. The reviewed outer wrapper must
deny external network and protected customer data. No Docker invocation occurs
inside this harness. No installed Drupal kernel/settings/Drush are loaded.

The unchanged worker lineage still pins `1ac3bc26` and the server allocation
marker. That marker is ownership, not a claim that current reviews were proved
at that commit. A separate `private-review-mariadb/lineage.json` pins the actual
4998be27 review/asset sources, borrowed bootstrap/provisioner/transport and all
new PHP harness files. There is no silent repinning of root-worker production
files. Source drift rejects; an integration source change needs explicit review
and a new recorded lineage, not a disabled check.

Frozen negative source is the exact 17,575-byte FullSiteReviewService from
`7227ceb63f742e438f151b430bb6bc93a32cce41`, SHA-256
`5d8b7c9957396702fe432f7c265cb3a0d69f90f159a48120442010ba6161c67a`.
It lives in `backend/tests/private-review-mariadb/fixtures/` and is loaded before
autoload only in explicit baseline children. Runtime needs no Git history.
All other production sources remain current, including asset writers/ledger.

## What the candidate exercises

Exactly two independent PHP child processes/PDOs at a time use installed Drupal
MySQL Connection and REPEATABLE READ on the identity-checked MariaDB 10.11.
The parent has no PDO. A wrapper around each already-validated PDO changes only
the table prefix and observes completed real SELECTs; it does not fake rows,
mask events, replace locking clauses or create a second client connection.
The holder observes exact peer-thread InnoDB lock waits on the expected table.

Seven current-source cases passed:

1. Same absent staff key: actual membership contention, one committed request,
   one draft binding event, one review event/activity and one readable exact copy.
   The waiting caller must reuse the first request after the bounded root restart.
2. Different key, same customer/project: real wait, then exact matching-project
   refusal; no duplicate request or attachment.
3. Nested caller sentinel: existing retry cannot commit outer work; rollback is
   invisible to the other connection. Absent-key nested creation rejects without
   closing the caller transaction.
4. Existing retry vs real controller upload on an initially empty asset set:
   request-first contention and both operations finish without lock inversion.
5. Existing retry vs real portal withdrawal: request-first contention, one
   durable withdrawn asset/audit, and same bytes subsequently return 409.
6. Old RR event snapshot: another connection attaches version 2; exact retry
   sees current event/request, creates no duplicate, preserves the caller root.
7. Old RR job snapshot: another connection commits the canonical proof job;
   attachment must reject via the current job guard without review authority.

All ordinary cases assert no outbox/job admission, selection, prospect, purchase,
submission or launch changes. The job-snapshot case inserts exactly one inert
synthetic job with all admission settings off; it never executes a worker.
Private package bytes are a tiny HTML page; upload is a one-pixel PNG. These are
concurrency fixtures, not branded/customer creative proof substitutes.

Four baseline cases must produce exactly these invariant tags:

- `upload_retry_lock_inversion` / `withdraw_retry_lock_inversion`: the old retry
  actually acquires membership while the actual asset method owns request.
  Record the inversion before deliberately creating a deadlock; a timeout or SQL
  deadlock is NOT the expected result.
- `stale_review_replay_rejected`: the exact immutable replay is refused by the
  old duplicate-event path, with version 2 independently committed and unchanged.
- `stale_job_bypassed`: the old service actually attaches inside its caller's old
  snapshot despite the other connection's committed canonical job. Roll back the
  deliberately invalid test authority afterwards; do not publish it.

Baseline exits 1 only after all four exact failures. Unexpected pass, assertion,
SQL/driver error, warning, protocol error, missing wait, disk stop or timeout
exits 2 and must remain a failed receipt, never green/expected-negative proof.
Current success exits 0. No skipped parity or generic-error success.

## Runtime receipt and harness repairs

All receipt directories below are under `/tmp/famtastic-phase2-review.NVAfPl/`.
All report `protectedDataUnchanged: true`; no protected store was used as fixture.

- `private-review-worker-prerequisite.jelC7i`: unchanged frozen worker source,
  16 cases / 103 checks, 115.201s guarded, before the extra review tables existed.
- `private-review-mariadb-sql-mode-fixed.uT79KZ`: seven cases / 84 checks, exit 0,
  5.320s guarded; independent connection pairs 53/54 through 65/66.
- `private-review-mariadb-negative.LEwtt6`: four exact negative controls /
  22 checks, expected exit 1, 1.894s guarded; pairs 67/68 through 73/74.
- Owned run `a8de7a3bded73307d39639e8`, directory `famtastic-worker-mariadb-VcO6vU`:
  exact-owned container/network deletion and absence verified, generated connection
  credentials removed, tiny fixture files and receipts retained. No other resource
  was removed. This disposable database is gone and cannot be reused.

Main retained every earlier red receipt. The first standalone peers lacked
PHPUnit's call-stack/configuration context for interface doubles. The neutral
TestCase wrapper and in-memory no-configuration bootstrap fix that harness defect;
they do not substitute production methods or SQL results. Closed diagnostic codes
retain source locations without dumping SQL, credentials or arbitrary messages.

The initial observer then missed real waits for two independent reasons:

1. MariaDB 10.11.19 refreshes the InnoDB metadata cache only after >100ms without
   a read; 20ms polling pinned an initially empty snapshot. The SQL observer now
   waits 250ms. Parent filesystem polling remains 20ms. See the exact
   [MariaDB source](https://raw.githubusercontent.com/MariaDB/server/mariadb-10.11.19/storage/innobase/trx/trx0i_s.cc).
2. Drupal's ANSI_QUOTES session reports fully qualified lock tables with double
   quotes. The observer now derives the exact quote from actual session SQL mode,
   retaining the exact owned database/table and holder/requester thread predicates.
   The failed observer receipt retained actual holder 51 RUNNING / contender 52
   LOCK WAIT and the differing quoted identifiers; no assertion was removed.

No timeout, unrelated wait or generic SQL error counts as successful contention.
This proves only the frozen sources named in the separate lineage, not later
paid-operation journal changes, installed storage, live sessions or delivery.

## Fixture and cleanup boundaries

All table creation/reset is restricted to the fixed `pr_` prefix in the existing
owned generated DB. The unprefixed worker rows/marker are not reset. Thirteen
small fixture tables use real source schema plus explicit file-metadata/usage
doubles and a private schema-owner marker. Unknown tables, partial prefixes,
foreign marker, source mismatch or non-InnoDB tables reject. This is serial
reuse, not concurrent harness operation. Run root-worker checks first: the
unchanged worker harness intentionally rejects the additional `pr_` tables.
Do not relax its table allowlist to rerun it on an expanded DB.

Actual final FullSiteReviewService, CustomerPortalService, upload controller,
OperationalLedger and package/renderer run. Account/clock/filesystem and file
entity/storage/usage interfaces are **explicit doubles**, not installed Drupal
entity storage. Metadata/usage doubles require the actual shared transaction and
write the same PDO; private file preparation must occur outside it. Unused portal
dependencies are not installed. This is not kernel, cookie/session, production
file-storage, real customer, provider, crash-durability or delivery evidence.
Existing unit tests separately cover reconciliation failure after withdrawal;
this narrow contention harness does not claim that additional fault injection.

Each case creates a unique 0700 child directory directly inside the validated
provisioner temp root. Fixed safe relative paths, per-component symlink checks,
create-only <=2 KiB fixture/barrier writes and 0600 files apply. Source and copied
private review bytes are verified by the real service. Files remain evidence;
the PHP harness deletes nothing and does not claim reboot/fsync durability.

Bounds: >=200 MiB disk checks, 64 MiB per PHP, 180-second overall deadline,
8-second expected-checkpoint wait, 12-second holder barrier, 15-second InnoDB
lock wait and 18-second server statement limit. The existing bounded pipe
transport retains its 10-second start / 55-second reply ceiling. Parent cleanup
terminates only its exact proc_open children (TERM, bounded wait, KILL); closing
connections rolls back open transactions. The outer wrapper owns process-group
termination on disk/time interruption. The unchanged provisioner's exact-owned
cleanup is a separate main action; never perform broad tmp/container cleanup.

## Reproduction — main only, after source review and disk guard

Use the existing approved wrapper, matching read-only vendor path and the exact
owned allocation. The completed allocation above is removed; a new explicitly
owned disposable allocation and root-worker prerequisite would be required:

```sh
FAMTASTIC_BACKEND_VENDOR=/Users/famtastic-fritz/Development/FAMtastic/worktrees/client-messaging-proof-rescue/backend/vendor /opt/homebrew/bin/php backend/tests/private-review-mariadb/run.php /EXACT/OWNED/RUN/connection.json current
FAMTASTIC_BACKEND_VENDOR=/Users/famtastic-fritz/Development/FAMtastic/worktrees/client-messaging-proof-rescue/backend/vendor /opt/homebrew/bin/php backend/tests/private-review-mariadb/run.php /EXACT/OWNED/RUN/connection.json baseline
```

Those inner commands are not authorization to bypass the outer wrapper. Retain
red and green receipts, exact source hashes, server/version/connection identities,
disk interruption and protected-data inventories. The frozen private-review
contention classification alone is upgraded by these receipts. Drive mirror and
integration remain parent-owned; no unattended/creative capability is promoted.
