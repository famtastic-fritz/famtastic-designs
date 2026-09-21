# Additive Mac and cloud delivery integration

Status: local repairs and verification; no live activation.

## Latest integrated source checkpoint

The complete pipeline module unit directory also passes locally against
`3bc4e9baf`: 482 PHP tests / 2,631 assertions, 68 existing PHPUnit deprecations,
no failures/skips. Receipt `designs-full-unit-baseline.s4a6TQ`, guarded duration
1.421 seconds, protected data unchanged. This uses the reviewed read-only
dependency runtime and isolated bootstrap, not an installed live site or hosted
CI's PHP 8.3 environment; local PHP is 8.5.9. It includes the narrower tests below
and must not be added to them as distinct coverage.

Fresh proof admission is now implemented in isolated source through 81ff57ee:
default-off, atomic fresh request/campaign/job/claim identity, strict managed
resend isolation, canonical `pc-*` campaign IDs, and denial of generic managed
imports. The production cost catalog remains empty. The login regression is
repaired in `eadc9a7`, integrated as `2785725f7`, and independently reviewed.
Managed deep-dive resume returns the existing owned request without retrying
proof work; explicit resend stays strict. Current parent verification passes
243 PHP tests / 1,427 assertions and nine bounded-worker Node tests in
`managed-login-integrated-final.PToRbb`. Credential/session finalization are
doubles, not installed-browser authentication. Two existing PHPUnit deprecations
remain; there are no test failures or skips in that focused run.

The newer combined run against Studio 2da81fa / Designs 81ff57ee reports
1,551 passed / 20 failed out of 1,571 tests in 134 files, plus 12 cascading
uncaught child-process errors. All failures originate in a manually bootstrapped
legacy PHP fixture missing the real FreshProofBinding class, not Drupal's
production autoloader. `complete-integrated-source.OCaFU5` retains the failure.
The repaired fixture explicitly loads the dependencies, supports the exact
request lookup, and rejects nonempty managed-event data rather than pretending
to model it. All 20 affected tests in three files pass in 118.447 seconds:
`legacy-fresh-binding-fixture.f9NJV0`. Both protected data inventories remain
unchanged. Production guards were not weakened; a new full rerun is required.

That full rerun is now green: Studio `2da81faf84f52d293758b7e0bb4715a46742dffd`
with Designs `2785725f7950ccdf1830c9eb95083029939725bd` passes 1,571 tests in
134 files, zero failed/skipped, both lints, both synthetic execution proofs and
whitespace checks. Suite: 249.12 seconds; guarded command: 250.572 seconds.
Evidence: `complete-integrated-repair-final.oVambR`. All paired fixtures were
enabled and both protected data inventories remain unchanged. This supersedes
the red run above for those source commits, not its retained diagnostic history.

The real bounded worker executable now survives macOS symlink paths instead of
silently exiting zero. Nine Node tests plus two companion Studio CLI/ingress
tests pass, including preserve-symlinks-main, silent imports, lost finish
acknowledgement, replay with one build/captured callback, and an unconfigured
selected-runtime rejection. Drupal claim authority and hosting remain doubles.
Evidence: /tmp/famtastic-phase2-review.NVAfPl/bounded-cli-final.28iJVQ. No installed
service, cloud or customer job changed. These focused additions postdate the
full-suite checkpoint below and are not added to its overlapping test total.

Review checkpoint pushed, not merged or deployed:
https://github.com/famtastic-fritz/famtastic-designs/pull/42 and companion
https://github.com/famtastic-fritz/famtastic-studio/pull/2 are drafts. GitHub
run 35660895111 reports that all three checks were not started because the
account is locked due to a billing issue. Hosted CI is blocked, not a passing
test receipt and not evidence of a source-test failure. No billing settings
or required-check protections were changed.
After pushing the newer checkpoint, run `35665612252` again failed to start;
backend check `106550570479` explicitly reports the same billing lock. No source
test was executed by that hosted job.

The earlier combined Studio verification passed 1,544 tests in 132 files with zero
skips, both lints and both execution proof scripts, using Studio 5ab65a2 and
Designs 3db3e01ed. Both protected data inventories remained unchanged. Evidence:
/tmp/famtastic-phase2-review.NVAfPl/full-integrated-source-final.Dp4s64. Initial
disk interruption and six obsolete assertions are retained in the Studio
evidence history. Exact canonical branding and separate invalid payload/signature
assertions replaced those expectations; production guards were not loosened.
This is a historical source checkpoint, not verification of the newer changes
or production activation.

The shared-claim source received a second independent read-only review with no
new confirmed bypass. Existing static finish trusts its authenticated worker's
dispatch receipt; that is a handoff assertion, not independent staging-readiness
proof. Proof completion remains closed. Cross-month cost holds remain recorded
in their original month, not counted as current-month availability deductions.
Real database contention and actual cost reconciliation remain unproven.

The earlier selected-route merge conflict is resolved in the isolated Studio
integration, preserving the Phase 1 mock firewall and separate real consumer.
Phase 2's independent review repairs pass 362 targeted tests; Phase 1 passes 42.
Seven normal continuation cases and 11 first-association cases pass across
the actual PHP/Node implementations, including interrupted callbacks and reuse
of completed pages. Signed creator attribution preserves original selections.
These are separate focused runs, not a new combined full-suite total.

Designs 7529dacdd integrates shared proof claim groundwork. Parent review read
the full production diff and tests; parent verification independently passes
88 PHP tests / 356 assertions and eight static Node worker tests. Evidence:
/tmp/famtastic-phase2-review.NVAfPl/shared-proof-claims-integrated.kKM80Q.
Both protected Studio data inventories remain unchanged. Four documentation
merge conflicts retained both histories; production source merged without conflict.

The existing Drupal coordinator owns both capability profiles. Selected-static
defaults and limits stay unchanged. Proof claims require explicit server grants,
an immutable account-bound input and a reviewed cost policy. The production
creative cost catalog is deliberately empty. Fresh creative admission now has
default-off source wiring; proof completion remains closed pending the
authoritative importer.
No synthetic cost fixture constitutes authorization for real model spending.

Still required: the actual Mac creative adapter and fenced import; unattended
QA/portal/notification/selection/staging proof; real additive
cloud execution and laptop-unavailable proof. The four-step goal is not complete.
Disk headroom and GCP project/sign-in remain external prerequisites. No production
job, service, customer message, payment or cloud resource has changed in this pass.

Earlier sections below are dated evidence history, not current release claims.

## September 21 autonomous-goal checkpoint

The owner authorized resolving all four milestones autonomously. The earlier
read-only merge conflict is no longer a request to wait for another Fritz gate.
Both integration worktrees preserve current main's independent customer
collection policy. Studio's real selected consumer is being reconciled behind
a separate internal route while the Phase 1 mock route remains unchanged.
Fresh admission remains disabled pending complete cross-repository verification.

Independent fixed-source Phase 2 review found a stale pre-submission lease gap
and three stored-ownership validation gaps. Repairs are underway in a separate
Studio worktree. They must pass independent regression checks before release.
Selected-consumer integration also exposed creator-credit derivative mismatches
at QA, hosting and source reuse; original selections must remain immutable.
Studio-origin first association still needs matching signed policy/verification
on both sides, not a broad asset or hash exception. No release is claimed here.

The read-only Mac workflow inventory verified historical artifact records for
requests 17 (81/82 current hashes; design.md matches its recorded historical
commit), 16 (34/34) and 8 (144/144). These were active-agent build and review
workflows with managed imagery, customer-specific build scripts and canonical
imports. They do not establish a single unattended generation command. The
six-direction provider benchmark is not their producer and must not silently
replace their routine. See [the Mac worker contract](../contracts/MAC-CREATIVE-WORKER-V1.md).

Storage fell below 200 MiB free; full render/build verification requires
headroom. gcloud has no signed-in account or selected project. Fritz has been
asked for 5 GiB free and the intended existing project/sign-in. No account,
cloud resource, credential, customer notice or historical job was changed.

## Owner intent

Keep the established laptop creative workflow. Automate its triggers, durable
ownership and recovery; cloud is an additional execution capability, not its
replacement. Routine green work must not wait for Fritz. Preserve the ordinary
client-acceptance-before-payment rule and recorded commercial exceptions.

## Four milestones

1. Review and repair Studio Phase 2. The isolated Studio branch now passes
   1,185 tests, both synthetic execution proofs and offline infrastructure checks.
   Full source review and live cloud canaries remain open. It is not activated.
2. Connect fresh intake/selection to the existing Mac capabilities under one
   Drupal ownership protocol. Do not bulk-enroll historical jobs. Static
   packaging is not creative generation, ecommerce or a complete backend.
3. Prove a complete unattended disposable journey, including independent QA,
   captured branded notification, authenticated selection, exactly one staging
   build and recovery. The existing canonical fixture passes but still exercises
   the legacy owner review gate; this is not the new unattended acceptance proof.
4. Prove a headless worker against the same claims with the laptop unavailable.
   No public Studio endpoint, provider spending or cloud activation in this pass.

## Verified runtime facts

Read-only production health at 2026-09-21T18:20:13Z reports CLI PHP,
observe_only, zero enrolled jobs, zero reserved cents and laptop independence
false. This is why a scheduler alone does not start fresh generation.
The running Mac service is healthy at 127.0.0.1:3400, PID 78266, using the
canonical site-studio-next checkout. Its Phase 1 mock endpoint must remain
separate from real selected-staging consumers.

The selected-staging consumer exists on Studio branch
codex/selected-staging-continuation at 9d0f6a2; it is not integrated by copying
old pipeline files over Phase 1. It requires explicit project/hosting bindings
and has no automatic target allocator. Preserve selected assets and send
unsupported capabilities to an implementation worker, never a fake success.

## Canonical proof harness repair

The frontend now imports the reviewed narration text outside frontend/. Both
disposable runners must copy this exact dependency, not the marketing tree.
Missing or symbolic-link source fails closed; cmp verifies the copied bytes.

The canonical fresh runner passed after that repair:
fresh-customer-proof-20260921T182630Z-87915. Its evidence reports all assertions
true, three fixture proofs, 34 captured messages, ownership isolation, Commerce,
support/mailbox lifecycle and hosting renewal. Payments are synthetic; domain
verification is a fixture; deployment is local; provider calls are disabled.
Raw evidence stays in .artifacts/fresh-customer-proof/ under that run ID.
Earlier failed runs are retained, not relabeled as successful.

The stricter selected-staging --canonical wrapper passes frontend compilation
but stops at its expected public-CMS network denial with public reads disabled
(run 20260921T183355Z-19761). Its safety gate was not weakened or called a pass.
The ordinary fresh canonical runner above reads public CMS content for SEO
shells; those GETs are not model calls, delivery or production writes.

## Fresh selection admission, local implementation

OperationalLedger now accepts the existing WorkerCoordinator service. With
Settings `famtastic_fresh_selected_admission_enabled` exactly TRUE, a freshly
inserted supported selected-static job is enrolled in the same transaction.
The temporary queued state never commits for a legacy worker to claim. Failure
rolls the new job back; an exact duplicate returns the existing job without
enrolling historical work. The shared capability predicate rejects backend,
functional-contract, planning, proof-generation and outreach jobs. No new queue,
provider, scheduler, send operation or target allocator was added.

Admission reserves no money. Actual claims retain the existing 25-cent minimum
reservation, $20 stop threshold, global concurrency limit and bounded leases.
This static dispatch cost reservation does not authorize model spending or
constitute a budget for ecommerce implementation.

Verification after the final source adjustment: 313 PHPUnit tests / 1,587
assertions pass, including eight fresh-admission tests. The suite reports one
deprecation and 68 PHPUnit deprecations; these are not failures. Retained run:
20260921T183834Z-24657. All 85 installed selected-staging assertions pass in
20260921T183835Z-24682, including fresh-process persistence, isolation, duplicate
selection/callback and rollback. That installed run retains the switch off;
enabled admission/shared claims are separately tested against in-memory SQLite.
The full fresh canonical journey also passes with the new service wiring in
fresh-customer-proof-20260921T183653Z-20885.

## Earlier integration stop (resolved in isolated source; activation still closed)

A read-only Studio merge-tree check against selected consumer 9d0f6a2 reports
conflicts in server/modules/pipeline/index.js and two documentation files. It
does not alter the worktree/index. Do not merge or deploy around this conflict:
the next integration must preserve both the Phase 1 mock firewall and the real
consumer's immutable source/tenant/target authority, with explicit routing.
Fresh admission stays disabled until that consumer is integrated and proven.
The actual creative-generation trigger and laptop-independent cloud execution
are still unfinished. The four milestones are not collectively complete.

## Chat Web alignment boundary

Drupal owns customer, commercial, selection, notification and job truth. Studio
owns generation/continuation evidence. The Phase 2 Firestore observation pilot
must not become a competing customer queue. Mission Control should expose this
verified state, not create another authority or replace the existing Mac tools.
Do not describe source tests, a handoff receipt, manual customer delivery or an
observe-only cron as unattended production execution.
