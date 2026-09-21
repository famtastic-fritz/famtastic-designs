# Fresh proof admission and fenced import

Source milestone, September 21, 2026. Fresh portal admission is implemented but
default OFF. The importer and creative adapter below remain design, not active code.
It extends the established Mac creative process; it does not substitute static
packaging or the fictional six-direction benchmark for customer proof creation.
Read SHARED-PROOF-CLAIMS-V1.md and MAC-CREATIVE-WORKER-V1.md together.

## Managed resend isolation repair (source only)

Independent review found an admission escape in `abfd3fed`: admit a new request,
edit its submitted brief, then call the authenticated manual resend service.
Without fresh writer intent the queue helper created a second legacy queued
`proof.generate` job with max_attempts=5, outside the existing shared claim.
Disabling the admission flag did not prevent this escape.

The helper now owns a request-locking transaction and checks stored managed
identity BEFORE either fresh admission or any legacy fallback. The immutable
request admission key or bound campaign admission marker establishes identity,
not the flag, body, job prefix or successful evidence parsing. Malformed markers,
cleared campaign bindings and missing admission service/policy cannot reopen
legacy execution. Unchanged managed resend validates exact event/job/claim bytes,
current request, locked account/membership/prospect ownership and actual asset
rights rows, reviewed policy and campaign identity, then returns the original
job. It never reenrolls, resets attempts, reserves money or queues a new notice.
Changed input remains saved but resending fails closed pending replacement policy.

Existing-request deep-dive resume uses the same exact managed reuse before any
intake normalization. Its transaction also prevents a failed repair from leaving
partial writes. This is NOT new deep-dive enrollment. Managed revision rebuild
rejects under the request lock BEFORE expiring the old campaign, clearing its
binding or recording a replacement. Unmanaged legacy resend, deep-dive submission
and revision retain their prior queue behavior. Other new-request callers are
still ineligible for fresh enrollment; no new round is inferred from a helper call.

Final focused receipt: 224 PHP tests / 1,170 assertions pass, including 87 admission
tests / 506 assertions (44 added cases). Two pre-existing PHPUnit doc-comment
deprecations: VerifiedColdGenericLocalImportGuardTest and DeepDiveProofHandoffContractTest.
The rejection helper now fails outside its exception catch, so PHPUnit assertion
failures cannot masquerade as expected service exceptions. Four PHP syntax checks
and git whitespace checks pass. PHP 8.5.9 / PHPUnit 11.5.56, 26 MiB peak.
Disk remained above 200 MiB (roughly 889 MiB initially); no cleanup or install.
These use in-memory SQLite, real service writers and entity/lock doubles, NOT
installed-kernel, HTTP-authentication, MySQL concurrency or provider proof.
No authoritative DB, network, production, customer send, cloud or activation.

Run from this checkout with the existing matching Composer dependency runtime:

```sh
test "$(df -k . | awk 'NR==2 {print $4}')" -ge 204800 && \
FAMTASTIC_BACKEND_VENDOR=/Users/famtastic-fritz/Development/FAMtastic/worktrees/client-messaging-proof-rescue/backend/vendor \
sandbox-exec -p '(version 1)(allow default)(deny network*)' \
php /Users/famtastic-fritz/Development/FAMtastic/worktrees/client-messaging-proof-rescue/backend/vendor/phpunit/phpunit/phpunit \
  --bootstrap scripts/automation-test-bootstrap.php --no-configuration --do-not-cache-result --display-phpunit-deprecations \
  backend/web/modules/custom/famtastic_pipeline/tests/src/Unit/FreshProofAdmissionTest.php \
  backend/web/modules/custom/famtastic_pipeline/tests/src/Unit/WebsiteRequestProofHandoffTest.php \
  backend/web/modules/custom/famtastic_pipeline/tests/src/Unit/WebsiteRequestAuditPreservationTest.php \
  backend/web/modules/custom/famtastic_pipeline/tests/src/Unit/ProofAttachmentReplayTest.php \
  backend/web/modules/custom/famtastic_pipeline/tests/src/Unit/SharedProofWorkerClaimsTest.php \
  backend/web/modules/custom/famtastic_pipeline/tests/src/Unit/FreshSelectedJobAdmissionTest.php \
  backend/web/modules/custom/famtastic_pipeline/tests/src/Unit/VerifiedColdGenericLocalImportGuardTest.php \
  backend/web/modules/custom/famtastic_pipeline/tests/src/Unit/WorkerCoordinatorTest.php \
  backend/web/modules/custom/famtastic_pipeline/tests/src/Unit/WorkerCoordinatorControllerTest.php \
  backend/web/modules/custom/famtastic_pipeline/tests/src/Unit/DeepDiveProofHandoffContractTest.php
```

Retained red evidence: loading ONLY the three original service classes through
`git show abfd3fed` in memory, without replacing any file, produces four failures:
`on-edited-pending`, `on-edited-leased`, `off-edited-pending`, `off-edited-leased`.
An initial row-comparison run exposed the extra legacy job; after strengthening
the rejection helper the same four cases fail with `Expected rejection: differs
from current input` (4 tests / 4 assertions / 4 failures). Reproducer:

```sh
test "$(df -k . | awk 'NR==2 {print $4}')" -ge 204800 && \
FAMTASTIC_BACKEND_VENDOR=/Users/famtastic-fritz/Development/FAMtastic/worktrees/client-messaging-proof-rescue/backend/vendor \
sandbox-exec -p '(version 1)(allow default)(deny network*)' php -r '
require "scripts/automation-test-bootstrap.php";
foreach (["CustomerPortalService", "FreshProofAdmission", "FreshProofBinding"] as $class) {
  $source = shell_exec("git show abfd3fed:backend/web/modules/custom/famtastic_pipeline/src/Service/" . $class . ".php");
  eval("?>" . $source);
}
exit((new \PHPUnit\TextUI\Application())->run(["phpunit", "--no-configuration", "--do-not-cache-result", "--filter", "testPublicManualResendCannotEscapeManagedClaim.*edited", "backend/web/modules/custom/famtastic_pipeline/tests/src/Unit/FreshProofAdmissionTest.php"]));'
```

The importer mapping remains read-only follow-up, not implementation. Default-OFF,
empty production cost catalogs, closed proof finish/generic import and independent
QA/notice boundaries remain unchanged. Portal-DNA/browser checks, remote fetch and
Drive mirror remain deferred to parent integration; no frontend changed here.

## Implemented admission checkpoint (source only)

`FreshProofAdmission` is wired into `CustomerPortalService` for exactly two
trusted writer events: newly submitted `createWebsiteRequest`, and locked
`updateWebsiteRequest` when the prior status is exactly `draft`. The private
queue helper receives writer-created source/prior-status/customer intent, never
body authority. Other callers cannot newly enroll; existing managed requests obey
the stored-identity reuse guard above, while unmanaged callers remain legacy.
Freshness is recorded and hashed in the immutable request snapshot.

Settings `famtastic_fresh_proof_admission_enabled` must be boolean TRUE, and
both the admission service and coordinator must receive matching reviewed
constructor-injected recipe/tool/cost policy. Both production constructor
defaults remain EMPTY. No supplier cost catalog or Settings/body cost override
was installed. Turning on the boolean alone fails closed and rolls back a fresh
submission, rather than falling back to the legacy worker. Do not enable it.

The enabled create wrapper owns an outer database transaction; update retains
its existing transaction/request lock. Admission locks the request, verified
customer, active organization/membership, prospect ownership map, actual
prospect and claimed asset rows. Current locking reads exclude old jobs and
campaigns before allocation. A direct unique job insert deliberately does not
use the ledger's duplicate-swallowing helper. Inert campaign, request binding,
max-three job, enrollment, and immutable event commit together. Any exception
rolls back the writer, including its new prospect/request/outbox/activity rows.
There is no provider or send inside this transaction, and no cost is reserved
until the existing coordinator claims the job.

The exact request snapshot includes numeric tenant/campaign identities, public
request ID, project/business names, project type, domain fields, submitted time,
request/review/selection state, commercial bindings and the entire normalized
intake with authored content/consent. Asset rows are sorted by numeric ID and
retain file ID, checksum/bytes, role, owner, status, timestamps and each distinct
rights/likeness/AI consent field. Withdrawn records are retained as withdrawn;
they are not permission to use bytes. Active records require ownership and valid
integrity metadata. `FreshProofInput::wire` defines the JSON encoding used by
both SHA256 snapshots. Snapshots live in the existing immutable event; the strict
worker v1 payload carries their hashes, not extra unversioned top-level fields.

Asset serialization uses `FOR UPDATE` on the actual rows, not an assumption
that locking a request also locks withdrawal. A final locking reread rejects
changed input. Concurrent new uploads are not automatically claimed: on engines
without range locking they can arrive after the snapshot. Future provider and
import boundaries MUST compare current full authority again, load actual private
bytes and verify their hashes. These are admission-time database facts, not
permanent rights, file-byte verification or provider authorization. No upload or
withdrawal writer was broadened in this milestone.

The event key is `proof-admission:request:<id>`, type `proof.fresh_admitted.v1`,
with exact campaign/prospect columns, trusted freshness, both snapshots and exact
job wire/hash. Exact service retries require unchanged event bytes/shape,
request/assets, job/claim identity, reviewed policy and campaign callback ID.
They return the original job without creating or enrolling anything. Malformed
or conflicting evidence rejects. Historical jobs of any status, existing request
bindings, and ANY prior same-prospect campaign fail closed before allocation.
This intentionally also excludes claimed-preview prospects with old campaigns
until their separate lane is reconciled. Existing historical rows are not changed.
Idempotency is scoped to an exact request; this does not add a submission-token
protocol for two separate HTTP create requests that allocate distinct requests.

`FreshProofBinding::assertGenericImportAllowed` runs inside the shared callback
service before duplicate events, latest-request fallback, artifact writes or
delivery. It denies stored admission markers even when malformed or the feature
flag is later off, including request-bound evidence with an inconsistent campaign
column. This covers generic HTTP and CLI callers. No authoritative import escape
was added; proof `finish` remains closed. Unmanaged legacy and verified-cold lanes
retain their behavior.

### Status projection contract

New inert campaigns use `generation_status=queued`, not `waiting_callback`.
An opaque `studio_job_id` is correlation only, never a remote acceptance receipt.
The managed branch precedes all legacy inference and reads the immutable binding,
exact job and actual claim. `worker_queued` + pending claim means `queued`;
`worker_running` + live leased claim before its execution deadline means
`preparing` / worker assigned, explicitly not confirmed generation/import. Expired,
inconsistent, failed or allegedly completed records mean `needs_attention`.
No managed record reaches Studio-accepted, owner-review or customer-ready through
this milestone. A later importer must explicitly extend this projection.

### Focused verification receipt

177 PHPUnit tests / 926 assertions pass under OS network denial, including
43 new admission tests / 286 assertions. PHP 8.5.9, PHPUnit 11.5.56; 24 MiB peak.
One pre-existing doc-comment deprecation in VerifiedColdGenericLocalImportGuardTest.
All seven changed PHP files pass syntax checks; git whitespace checks pass.
Tests use real in-memory SQLite tables from the repository schema, real portal
writers/coordinator/transactions, entity-storage doubles with transactional SQL
persistence, and a mocked coordinator lock. Interleavings are deterministic
single-connection injections, NOT concurrent MySQL or installed Drupal kernel
proof. No fixture claims a complete customer journey, cloud execution or provider
pricing. No install, full suite/build, network, authoritative DB or credentials.

The existing vendor's composer.json and composer.lock matched this checkout.
Run from this worktree; keep the disk guard and serial invocation:

```sh
test "$(df -k . | awk 'NR==2 {print $4}')" -ge 204800 && \
FAMTASTIC_BACKEND_VENDOR=/Users/famtastic-fritz/Development/FAMtastic/worktrees/client-messaging-proof-rescue/backend/vendor \
sandbox-exec -p '(version 1)(allow default)(deny network*)' \
php /Users/famtastic-fritz/Development/FAMtastic/worktrees/client-messaging-proof-rescue/backend/vendor/phpunit/phpunit/phpunit \
  --bootstrap scripts/automation-test-bootstrap.php --no-configuration --do-not-cache-result --display-phpunit-deprecations \
  backend/web/modules/custom/famtastic_pipeline/tests/src/Unit/FreshProofAdmissionTest.php \
  backend/web/modules/custom/famtastic_pipeline/tests/src/Unit/WebsiteRequestProofHandoffTest.php \
  backend/web/modules/custom/famtastic_pipeline/tests/src/Unit/WebsiteRequestAuditPreservationTest.php \
  backend/web/modules/custom/famtastic_pipeline/tests/src/Unit/ProofAttachmentReplayTest.php \
  backend/web/modules/custom/famtastic_pipeline/tests/src/Unit/SharedProofWorkerClaimsTest.php \
  backend/web/modules/custom/famtastic_pipeline/tests/src/Unit/FreshSelectedJobAdmissionTest.php \
  backend/web/modules/custom/famtastic_pipeline/tests/src/Unit/VerifiedColdGenericLocalImportGuardTest.php \
  backend/web/modules/custom/famtastic_pipeline/tests/src/Unit/WorkerCoordinatorTest.php \
  backend/web/modules/custom/famtastic_pipeline/tests/src/Unit/WorkerCoordinatorControllerTest.php
```

Source inventory: new Service/FreshProofAdmission.php, FreshProofInput.php and
FreshProofBinding.php; CustomerPortalService hooks/projection; shared
ProofCampaignService deny guard; services wiring; FreshProofAdmissionTest and
the handoff test's event-table fixture. All PHP paths are beneath
backend/web/modules/custom/famtastic_pipeline. The coordinator/profile, selected
90/300/330 behavior, budget accounting and production schema are unchanged.

Disk stayed above the 200 MiB guard (roughly 1.1 GiB initially, 913 MiB at final
tests). No cleanup occurred. Full builds and browser/kernel validation were not
run. The portal-DNA script is sparse-omitted and no frontend changed; its execution
is deferred to parent integration. Remote fetch and Drive mirroring are also
deferred to the parent, keeping this source-only isolated milestone offline.

## Admission boundary

CustomerPortalService::queueWebsiteRequestProofJob is the canonical job seam,
but its callers include login repairs, manual resends and revision work. Calling
that method does not establish a fresh submission. The first implementation must
carry explicit fresh-submission intent from the request writer and recheck live
records under an outer transaction.

| Caller | Initial managed eligibility |
| --- | --- |
| createWebsiteRequest | Newly submitted request from a verified account |
| createWebsiteRequestFromDeepDive | NOT WIRED; later needs serialized invitation/request identity |
| submitClaimedDeepDiveRequest | NOT WIRED; never infer freshness from login/resume repair |
| updateWebsiteRequest | Locked draft-to-submitted transition only |
| createWebsiteRequestFromProspectDiscovery | Preserve legacy path; registration is not verification |
| sendWebsiteRequestToSiteStudio | Manual resend does not authorize new admission |
| prepareWebsiteRequestRevisionRebuild | Separate replacement-round contract required |

The HTTP controller currently authenticates and checks CSRF but does not require
verified email on every path. Registration can create a discovery request before
verification. Do not infer verified authority from method name or authenticated
session alone. A submitted request needs verified account, current organization
membership, prospect/resource ownership and pre-purchase eligibility checks.

Inside the request writer's transaction:

1. Insert or lock the exact request and prove the fresh transition.
2. Check campaign binding and previous request jobs before allocating anything.
   Exact managed repeats return their binding unchanged. Legacy or ambiguous
   history is preserved and requires reconciliation, not another campaign.
3. Create a non-dispatching queued request-bound campaign with opaque callback identity.
4. Freeze request/account/campaign, normalized brief and full authored scope,
   ordered asset authority, recipe, allowed tools and reviewed cost policy.
5. Insert one canonical proof.generate job, bounded to three attempts, and enroll
   it in the existing coordinator before commit. Commit campaign, request binding,
   job, enrollment and evidence together. Admission reserves no money.

OperationalLedger::enqueue currently returns existing IDs without comparing
payloads and catches duplicate-key races. A new narrow insertion-result seam or
exact returned-identity check is required so a losing transaction cannot commit
a newly allocated campaign. Never expose a committed transient queued row to
the legacy worker. Any admission failure rolls back the fresh transition.

Do not use ProofCampaignService::createForProspect for inert admission: it can
dispatch or render. createLocalHandoff and websiteRequestProofExport use
prospect-level reuse, not this request's immutable binding. Avoid a DI cycle:
the full proof service already depends on customer_portal. A small no-dispatch
factory can use database/entity storage without importing that full service.

Define request_binding_sha256 and asset_authority_sha256 over explicit versioned
canonical fields. SelectedSourceIntent::requestBinding is useful source context,
not by itself the complete fresh-proof contract. Asset withdrawal updates its
row before the selected-request lock: request locking alone does not serialize
rights. Admission, external tool authorization and import need compatible asset
locking/version checks. Reference permission is not AI-transformation permission.

## Import boundary

ProofCampaignService::acceptCallbackInternal is shared by signed Studio callbacks
and the local import command. It currently permits latest-request-for-prospect
fallback, duplicate event IDs before artifact comparison, and replacement of
fixed file paths. Preserve those legacy behaviors only outside managed campaigns.

Managed status comes from immutable stored campaign/request/job/payload identity,
never a callback flag. Reject managed campaigns through generic import/callback
paths before duplicate shortcuts or request fallback. Keep verified cold outreach
as its existing separate lane.

A dedicated importer must prepare bounded files privately, then use a short
coordinated final transaction to recheck worker identity, capability, token,
integer attempt, live lease, immutable payload, campaign, account, current brief
and asset rights. Bind all Build DNA and artifact hashes. Commit the exact import
receipt, variants, campaign/request association and worker completion together.
Do not expose check-now/import-later authority. Use create-only immutable files;
database rollback cannot reverse filesystem overwrites. The existing 30-second
coordinator lock must not wrap long file processing. Keep lock ordering consistent.

Exact retries return their persisted receipt only after matching callback, DNA
and artifact bytes. Stale attempts, changed content and cross-account inputs
reject. Lease credentials stay outside stored raw callbacks and captured source.

## Release boundary

Reuse attachWebsiteRequestProof without downgrading advanced review states.
Managed import cannot fall through to generic outreach.prepare. Independent
AutomatedProofRelease QA and its existing branded notification outbox remain
separate. Reviewer independence uses stored producer identity, not self-reported
strings. Notification acceptance is not readership; import is not customer release.

Keep the default-off gate, empty production cost catalog, existing $20 stop and
$25 monthly boundary until the real adapter, pre-provider authorization, durable
operation checkpoints, unknown-outcome recovery and fenced importer are proven.
Lease expiry alone never authorizes redoing uncertain paid work. Cloud and Mac
must contend for the same Drupal-owned job, not separate customer queues.

Verification must cover fresh request atomicity, duplicate/racing intake,
legacy exclusions, verification/membership changes, rights withdrawal, exact
retry receipts, stale workers, callback loss, independent QA, correct portal
links and one selected-design staging build. Synthetic SQLite claim checks do
not prove MySQL concurrency or an unattended customer journey.

Source review: CustomerPortalService, CustomerPortalController, registration
hook, OperationalLedger, ProofCampaignService, SiteStudioCallbackController,
PipelineCommands and AutomatedProofRelease, based on the integrated September 21
source. No production activation, new campaign, provider or customer send occurred.
