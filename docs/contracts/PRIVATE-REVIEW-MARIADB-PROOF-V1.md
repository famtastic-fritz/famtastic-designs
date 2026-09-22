# Private review / request-asset MariaDB proof candidate

September 21, 2026. **SOURCE ONLY / UNRUN**, from
`4998be27695f5e3940212d9cebedb31d923c04a6`. No PHP execution (including lint),
Docker, installation, network, provider, migration or production operation was
performed while authoring this candidate. Main owns the exclusive runtime slot.
The 716/4,160 integration unit receipt and earlier root-worker MariaDB receipt
are not evidence that this new harness passes.

## Scope and identity

Entrypoints are `backend/tests/private-review-mariadb/run.php` and `peer.php`,
outside `backend/web`. No route, registration, activation flag or production
code changes. The only existing harness edit is an optional fixed enum in
`worker-mariadb/process.php`: `worker` retains its old default; `private-review`
selects one fixed sibling. No arbitrary executable/path or shell is accepted.
This shared transport extension itself is unrun.

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

Seven current-source cases are planned, not reported passed:

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
Current success would exit 0. No skipped parity or generic-error success.

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

## Deferred commands — main only, after source review and disk guard

Use the existing approved wrapper, matching read-only vendor path and the exact
already-owned allocation. No installation or fresh allocation is implied:

```sh
FAMTASTIC_BACKEND_VENDOR=/Users/famtastic-fritz/Development/FAMtastic/worktrees/client-messaging-proof-rescue/backend/vendor /opt/homebrew/bin/php backend/tests/private-review-mariadb/run.php /EXACT/OWNED/RUN/connection.json current
FAMTASTIC_BACKEND_VENDOR=/Users/famtastic-fritz/Development/FAMtastic/worktrees/client-messaging-proof-rescue/backend/vendor /opt/homebrew/bin/php backend/tests/private-review-mariadb/run.php /EXACT/OWNED/RUN/connection.json baseline
```

Those inner commands are not authorization to bypass the outer wrapper. Retain
red and green receipts, exact source hashes, server/version/connection identities,
disk interruption and protected-data inventories. Runtime/API/syntax validity
remains unverified until main executes them. Source inspection, exact frozen-file
hash comparison and Git whitespace checking are the only authoring checks.
No capability status is upgraded. Drive mirror and integration remain parent-owned.
