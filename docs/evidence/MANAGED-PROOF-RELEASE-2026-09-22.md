# Managed independent release verification — September 22, 2026

Source-only, unregistered and not production-live. The existing portal/automated
release delegates to a receipt-bound root transaction only when the trusted
principal and real retained-evidence dependencies exist. Test identity/evidence
content are explicitly synthetic; no such production dependency was installed.
Contract: `../contracts/MANAGED-PROOF-RELEASE-V1.md`.

## Review findings repaired before this checkpoint

Independent review found two concrete pre-commit gaps, despite earlier green tests:

1. A silently ignored event insert or altered research/outbox row could leave reveal
   and queued mail committed before post-commit verification threw. The operation
   now reads back the exact planned request/decision/research and complete fresh
   queued, unclaimed, unsent notice under locks before committing.
2. A final event hook could alter variants, the receipt or account/asset authority
   after the earlier check. A separate final same-transaction assertion now checks
   the exact four-field reveal, reverses only that transition locally, and reuses
   the freshly locked pending graph. `lockPending` itself stays pending-only.

Actual SQLite `RAISE(IGNORE)`, late mutation and deletion triggers cover these
cases. Tests require a full unchanged pre-release DB snapshot and no research or
notice left behind; an exception after commit is not accepted as rollback. Final
targeted independent source re-review found no remaining concrete defect. This
is not MariaDB contention, installed authentication or live delivery proof.

## Retained guarded runs (overlapping totals, never sum)

Private root:
`/Users/famtastic-fritz/Development/FAMtastic/worktrees/autopipeline-recovery.ggJXc1`.
Guard SHA256: `150e3feaa6f0c02cbae46a032cd92ca8a486a9db5cb278f540186edd38b0cfd4`.
External network/MTA and authoritative config/data access were denied; protected
before/after inventories are identical in every listed run.

| Receipt directory | Result | Measured time |
| --- | --- | --- |
| `evidence-managed-release-first.lP2ch8` | 38 tests / 575 assertions, before review fixes | 7.313s suite / 7.587s guarded |
| `evidence-managed-release-regression-first.cDneDG` | 1,197 tests, one fixture unique-key error; not a pass | 40.324s / 40.705s |
| `evidence-managed-release-final-regression.NEon0v` | 1,197 / 13,056 after fixture repair, before review fixes | 40.820s / 41.201s |
| `evidence-managed-release-write-readback.WGbP32` | 64 / 991, first review fix only | 12.892s / 13.058s |
| `evidence-managed-release-readback-regression.60Ieau` | 1,223 / 13,472, first review fix only | 46.089s / 46.484s |
| `evidence-managed-release-final-authority-regression.Me3iag` | **1,232 / 13,616**, both review fixes | **48.325s / 48.707s** |
| `evidence-managed-release-final-authority-canonical.h77omc` | actual canonical disposable Drupal journey, root and both children pass | **36.037s guarded** |

Final full suite also passes portal DNA **34/34**, email presentation **86
assertions**, and whitespace. PHP8.5.9 / PHPUnit11.5.56; no failures/skips and the
same68 existing deprecations. Final suite allocator summary74MiB; maximum-content
package test separately captures **187,547,648 bytes** allocator peak for21files /
21,064,260 content bytes, 1.790s through four role reads. The smaller suite summary
is not the maximum workload peak. All new/changed runtime PHP syntax checks pass.

Designs baseline `4b3e17b14317e0634aa4a56db32bdf54a113d732` with dirty-diff hashes:

- Final full regression: `7ce902e84c56327ef429a5c937b1162aa4ff6c2758ddbd2600e2bc39c7dc5860`.
- Final canonical journey: `bfaeaeb00a4c241513fecd19ee62dbf90ef4dccad997ce915466d454c94f4ac9`.

Only documentation changed between those two final runtime runs. Studio is paired
clean at `0f2a656ba3403f8c2a2f40afeba92d649cee872b`; no Studio runtime change here.
The final source commit is the commit introducing this evidence document.

## Canonical installed regression remains the legacy journey

Run `fresh-customer-proof-20260922T122414Z-17822` retains root `evidence.json` and:

- `proof-runs/journey-essential_199-1790079876-18450/evidence.json`
- `lifecycle-runs/1790079885-19801/evidence.json`

Both child files match the embedded root records. All5root assertions,23journey
booleans plus exactly3proofs, and all14lifecycle booleans are true. Actual captures
contain4core messages (including probe) and34transactional messages. The wrapper's
output.log is empty; actual customer-proof.log and all three JSON files were
inspected, not inferred from wrapper stdout. No external email/provider call.

This runner uses the legacy owner-review fixture, synthetic payment/domain and
local deployment. It does **not** demonstrate managed unattended production/QA/
notification/selection or a laptop-off cloud run. Public CMS replay supplies only
frontend build data. Earlier pre-fix canonical receipts remain historical.

## Still required

Real authenticated reviewer and retained-evidence resolution; bounded repeatable
pending-review consumption; released portal projection/selected-source adoption;
actual Mac creative runner and durable provider provenance; installed controlled
managed journey and real concurrency/interruptions; cloud credentials, reviewed
create-only/containment apply and laptop-unavailable canary. No customer mail,
worker activation, service restart, merge, production or cloud deployment occurred.
The previously verified hosted CI billing lock is not a source test failure or a
CI pass. This checkpoint does not bypass that gate or change account settings.
