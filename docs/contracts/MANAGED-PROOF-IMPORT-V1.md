# Managed proof import v1

September 21, 2026. **Unrun source candidate**, based on
`66434a3fea9d7e0025d3c523cf2df9d769a6498b`. Main owns independent review, all
runtime verification, integration and documentation mirrors. No installed schema,
provider, customer, cloud, notification or production action occurred here.

## Scope and authority

`ManagedProofImporter` is unregistered and default unconfigured. No routes,
scheduler, service wiring, real producer verifier or production completion policy
are supplied. Generic managed callbacks remain denied. Existing legacy imports,
selected-worker profiles, budgets, lease rules and FreshProofInput are unchanged.
No DB migration is needed: receipt key `proof-import:job:<id>` uses the existing
unique famtastic_event index and event type `proof.managed_imported.v1`.

`importPrepared(requestId, jobId, worker, token, attempt, serverGrants, packageId,
packageHash, bundleId, preparedHash, provenanceId, provenanceHash)` first verifies
the existing package and original bundle outside a transaction. It accepts only
server IDs, never file paths. Credited derivatives remain separate from originals;
the exact canonical logo exception stays solely in the existing server-owned
package role. No asset caps, static callback restrictions or credit markers change.

The trusted local provenance resolver takes `(provenanceId, provenanceHash)` and
returns the closed `famtastic.managed-proof-provenance.v1` envelope defined by
ManagedProofImportContract. It MUST authenticate an immutable producer record,
validate the complete existing Build DNA schema and referenced evidence bytes,
establish exhaustive asset use and producer identities, and bind the exact
admission/payload/package/callback/operation receipts. It cannot echo worker DNA,
trust caller booleans, run a provider, or fill missing evidence retrospectively.
No such real resolver is installed. Envelope <=256 KiB; receipt <=64 KiB.

All producer IDs are canonical `automation:<worker-id>`, sorted and unique;
every operation producer must be included. Importing worker and original producer
are distinct; recovery recorder identities remain separately retained. Future QA
must exclude ALL stored producers, not compare only an evidence-supplied string.
Build DNA requires explicit UTC start/end times and managed request/campaign/job
correlation. Its projection preserves the existing manifest bytes and mappings.

Source-configured completion policy maps the frozen cost-policy ID to exactly
`operation_policy_sha256` and `required_success_slots` (1..32 unique reviewed
slots). The digest binds the journal's whole reviewed catalog entry. All existing
operations are locked and exactly matched against trusted provenance, including
original attempt/month holds and current asset rights. Required slots must have
succeeded. Optional terminal failures may remain. Unresolved submission blocks
import; trusted terminal receipts with UNKNOWN BILLING remain valid, with null
actual cost and all full holds unchanged. No refunds, fabricated zero, new paid
permits, fourth attempt or provider exactly-once claim is introduced.

## One root commit, no filesystem rollback illusion

Importer refuses an existing transaction. Order is current request/account/rights
through FreshProofAdmission, root mutex, owned proof claim, journal/budget reads.
Then create-only Build DNA and A/B/C variants, campaign ready, request owner_review,
unique receipt, and coordinator completion commit together. Each mutable writer
uses CAS. Coordinator loads the ACTUAL persisted receipt, checks its bound rows,
and rechecks live token/attempt/lease/execution deadline immediately before CAS.
It does not accept success flags. The retained token hash is fingerprinted; the
raw credential never enters artifacts, receipt or Build DNA.

Campaign/job identity is not recreated; existing variants/Build DNA/import history
cannot be adopted. Claim ends `proof_imported`, job `completed`, with exact receipt
hash/result and zero lease/deadline. Job attempts retains the coordinator's existing
failed-attempt count; claim attempt is the current generation. No budget changes.
`owner_review` is the existing internal pending-QA value, not an additional Fritz
gate, a successful QA decision, or evidence that a QA job has been queued.
No approval, selection, notification, activity or outbox writer is invoked.

Variants store opaque `managed-proof:mp-<id>:<direction>:<role>` references and
nested worker description separate from server projection facts. They are not
legacy filesystem paths, source_capture records, or generic asset manifests.
Receipt-aware HTTP/QA/selection adapters remain absent. A known receipt cannot
serve artifacts through the package's still-unconfigured trusted read resolver.

Files are read before the short root transaction; no file processing or network
call belongs under the mutex. Originals/packages are neither overwritten nor
cleaned on rollback. Private same-UID storage is not hostile-host isolation and
has no directory-fsync/power-loss guarantee. DB commit does not make filesystem
bytes transactional. The final lease sample is not a guarantee that wall time
cannot advance during commit. Commit acknowledgment/post-commit callback failures
withhold success; committed work may then need exact historical acknowledgment.

## Read-only facts versus historical acknowledgment

`ManagedProofImportReceipt::committed(Connection, jobId, expectedHash = NULL)`
requires no active transaction and returns `{receipt_id, receipt_sha256, receipt}`.
It verifies canonical receipt bytes, immutable admission/job/claim, current
request association, campaign, variants, Build DNA and exact journal inventory.
Missing, tampered, forged or nonterminal records throw. It is not tenant/read
authorization or current brief/rights eligibility. It intentionally does not
compare advanced review/selection/brief fields to the original fresh snapshot.

`acknowledgeCommittedImport(jobId, receiptHash, authenticatedIdentity)` additionally
requires a separate trusted authorization closure, default NULL. That dependency
must authenticate current access to this exact historical account/receipt. ACK
does not renew a claim, import again, grant reads or alter later QA/selection.
Public reads MUST NOT use ACK as their authorization. Main owns the separate
receipt-aware handoff projection; FreshProofBinding is untouched by this slice.

## Unrun test source and remaining gates

ManagedProofImporterTest composes actual admission/coordinator/journal/importer,
real in-memory SQLite, and real private preparation/package files. Entity storage
and provenance/ACK verifiers are explicitly synthetic, NOT Drupal-kernel or real
producer evidence. Seeded real outbox/activity schemas are snapshotted. Tests cover
live authority, old/new attempts, unknown billing, required slots, writer/CAS
rollback, missing/forged receipt, root commit uncertainty, callback failure,
immutable files and exact historical ACK after changed QA/selection/rights.
Fixture protected `import`, `snapshot`, `rows`, `reject` support main's separate
handoff tests. No synthetic case establishes deliverability or provider execution.

Main may run, ONLY through its reviewed network/protected-data/disk guard:

```sh
FAMTASTIC_TEST_CANONICAL_LOGO=/tmp/famtastic-phase2-review.NVAfPl/designs/frontend/public/brand/famtastic-designs-logo-v1.png \
FAMTASTIC_BACKEND_VENDOR=/Users/famtastic-fritz/Development/FAMtastic/worktrees/client-messaging-proof-rescue/backend/vendor \
/opt/homebrew/bin/php -d memory_limit=256M /Users/famtastic-fritz/Development/FAMtastic/worktrees/client-messaging-proof-rescue/backend/vendor/phpunit/phpunit/phpunit \
  --bootstrap scripts/automation-test-bootstrap.php --no-configuration --do-not-cache-result \
  backend/web/modules/custom/famtastic_pipeline/tests/src/Unit/ManagedProofImporterTest.php
```

Still required: lint/focused and integration tests, independent source review,
real MariaDB import contention/root rollback, installed entity/cache/hook behavior
and memory headroom, immutable producer/evidence resolver and real policy review,
receipt-aware protected reads, independent QA/release adapter and separate approved
notification. No activation follows from this source checkpoint. No runtime tests,
fixture media staging, installs, network, containers or cleanup ran in this helper.
