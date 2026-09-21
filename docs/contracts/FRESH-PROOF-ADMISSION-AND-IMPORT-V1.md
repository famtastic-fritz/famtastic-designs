# Fresh proof admission and fenced import

Implementation design, September 21, 2026. This contract is not active code.
It extends the established Mac creative process; it does not substitute static
packaging or the fictional six-direction benchmark for customer proof creation.
Read SHARED-PROOF-CLAIMS-V1.md and MAC-CREATIVE-WORKER-V1.md together.

## Admission boundary

CustomerPortalService::queueWebsiteRequestProofJob is the canonical job seam,
but its callers include login repairs, manual resends and revision work. Calling
that method does not establish a fresh submission. The first implementation must
carry explicit fresh-submission intent from the request writer and recheck live
records under an outer transaction.

| Caller | Initial managed eligibility |
| --- | --- |
| createWebsiteRequest | Newly submitted request from a verified account |
| createWebsiteRequestFromDeepDive | New request; serialized invitation/request identity |
| submitClaimedDeepDiveRequest | Proven first draft submission, never login/resume repair |
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
3. Create a non-dispatching request-bound campaign with opaque callback identity.
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
