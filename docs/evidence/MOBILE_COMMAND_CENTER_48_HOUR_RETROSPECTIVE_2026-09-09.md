# Mobile command center: prior-48-hour retrospective and completion audit

Date: 2026-09-09
Scope: FAMtastic Designs integration branch plus the FAMtastic platform,
Site Studio Next, FAMtastic Inc, FAMtastic Hosting, Tighten Up Your Locs, and
MBSH repositories.
Authority: read-only repository history, committed contracts, tests, and local
runtime evidence. Conversation or agent-memory claims were treated as leads,
not as proof.
Production effects: none from this audit.

## Executive finding

The prior 48 hours produced a coherent staging-first lifecycle and repaired the
most important proof-to-build identity gaps. The command center is not yet
complete: the real FAMtastic Designs -> Site Studio Next -> standalone Git
repository -> protected FAMtastic Inc staging -> authenticated callback path
still stops at an intentionally unavailable dispatch boundary. The integrated
portal, Drupal theme, and lifecycle also need one running-system acceptance
pass before their local-source claims can be promoted.

The current standard is:

`proof selected -> immutable build packet -> standalone repository -> protected staging -> authenticated packet-bound receipt -> customer staging acceptance -> checkout -> verified payment -> fulfillment -> production cutover`

Shay is a pilot exception whose historical order differs; the exception does
not redefine the standard.

## Confirmed work and evidence

### FAMtastic Designs

- `935d3c95` added the mobile customer command-center navigation and durable
  next-action presentation.
- `d9edd1b9` added the reusable `famtastic_admin` theme system for native Drupal
  forms, tables, messages, local tasks, Views, and entity administration.
- `eda5fd3c` hardened consent, durable support/outbox persistence, and retry
  boundaries.
- `ccfcc50e` removed query-string success claims and added truthful recovery and
  staging-first guidance.
- `96168979` made proof selection create the pre-payment project, build packet,
  and staging job.
- `9e68e451` added the source-level customer/staff action inventory.
- `b209c565` changed the canonical isolated journey to stop at staging before
  checkout.
- `54b5a88c` made the staging worker recognize its job type and fail closed while
  no approved authenticated destination is configured.
- `e97e88e6` bound staging receipts to the registered project packet, added a
  durable customer staging-acceptance action, and queued the staging-ready
  notification.
- `728c75d6` aligned the producer with the immutable selected-artifact and file
  manifest required by receipt verification.

These commits prove local source and local isolated-lifecycle behavior. They do
not prove external Site Studio dispatch, public staging, customer email
delivery, real payment, or production.

### Site Studio Next and shared delivery architecture

- `bad8994` established the learning spine, immutable artifact bundle, design
  contract, and FAMtastic Inc adapter foundation.
- `c3be23e` and `ec2598c` made hosting and Git delivery configurable rather than
  assuming a single shared-hosting target.
- `1f16e12` recorded staging-before-payment as the standard lifecycle.
- `5f519f1` added account-bound staging receipt semantics.
- `docs/research/parity-root-cause-2026-09-09.md` records that the first visual
  parity comparison was invalid because it compared different fixtures.
- `docs/research/artifact-parity-2026-09-09.md` records the repaired byte-for-byte
  artifact path and matching 390/768/1280 screenshots.

The adapter tests use an injected test transport. They prove fail-closed
validation and receipt construction, not a real Git remote, cPanel target,
public staging URL, or callback.

### Platform continuity and repository learning

- Platform commit `d9c0b6c01` added the agent interoperability contract and
  cross-repository continuity records.
- `docs/agent-startup/AGENT-INTEROP-CONTRACT.md` correctly makes repository and
  runtime truth authoritative over session memory, separates observation from
  interpretation, and requires proof before a lesson becomes a reusable rule.
- Site Studio Next `docs/contracts/CONTRACT-learning-spine.md` requires privacy
  review, a synthetic proof, a second unrelated site type, and owner promotion
  before a site-local lesson becomes shared doctrine.

Local Claude-memory observations were not used as authoritative evidence: the
recent FAMtastic Designs namespace contained unrelated research observations.
That confirms why every agent must re-anchor on the exact repository, branch,
contract, and runtime record.

### MBSH operational evidence

- `2e1c434` repaired the payment incident without inferring paid state.
- `cb48041` introduced Git-addressed releases; the safe release contract is in
  `docs/operations/GIT_PRODUCTION_RELEASES.md` in the MBSH repository.
- The sequence from `ae9a0ff` through `1b01fd5` repeatedly revised SSH/SCP
  transport. It is evidence that external delivery needs an explicit transport
  preflight, resumability, immutable release identity, and receipt verification.
- `9fc32ba` corrected `spec.json`; it no longer restores the obsolete
  registration-shaped/Netlify contract.
- `58cb061` ignores the attendee workbook and inspection exports containing
  names and email addresses. Those files remain private local evidence and are
  not learning-catalog inputs.

## Rework and failure patterns

1. **Unlike fixtures produced a false parity question.** Visual comparisons
   are meaningful only when both systems consume the same immutable source and
   render the same viewports.
2. **Lifecycle doctrine drifted across documents and tests.** Several surfaces
   still described selection -> checkout or payment -> proofs even after the
   staging-first decision.
3. **Source-string tests masked missing execution.** The first pre-payment
   staging test asserted that a job name existed without proving a worker could
   consume it.
4. **A signed callback was initially treated as sufficient identity.** HMAC
   proves the sender; packet ID, request/project, selected direction, source
   digest, and file-manifest digest must also match stored truth.
5. **A deployed staging receipt was initially treated as review.** Deployment
   and customer acceptance are separate durable facts.
6. **Local injected transports were easy to overstate.** They prove contracts,
   not external Git, hosting, callback, or customer delivery.
7. **Transport was debugged during real delivery.** The MBSH commit sequence
   shows why shared-host release transport needs a reusable rehearsal before a
   customer artifact enters it.
8. **Memory namespaces can contain unrelated work.** Session or memory summaries
   must not upgrade repository/runtime claims.

## Current completion audit

### Locally implemented, but awaiting integrated acceptance

- Customer portal mobile navigation, next-action hierarchy, proof/staging
  presentation, billing, support, growth, messages, and account surfaces.
- Reusable native Drupal administration theme and pre-auth login/reset theming.
- Consent and durable notification/support records.
- Immutable selected-artifact packet, exact packet-bound receipt validation,
  queued staging-ready notice, customer staging acceptance, checkout gate, and
  payment reuse of the pre-payment project.
- Source-level navigation/action inventory and focused contract tests.

### Unproven or incomplete

1. At committed HEAD for this audit, an approved authenticated Site Studio Next
   dispatch endpoint is not configured and
   `AutomationWorker::prepareSiteStudioStaging()` deliberately fails closed.
   Concurrent uncommitted integration work is introducing a configurable
   client; it remains outside this documentation commit and must earn its own
   review and runtime evidence.
2. No full runtime has yet proven the cross-repository handoff through an actual
   standalone Git repository and protected FAMtastic Inc staging target.
3. The protected integration environment still needs its own Drupal database,
   files, mail capture, Stripe test boundary, and disabled production
   transports/jobs.
4. Database updates must be exercised on a fresh isolated install and an
   upgrade-shaped fixture before protected staging.
5. The staging-ready outbox row is locally implemented; provider acceptance and
   owner/customer inbox delivery are not proven.
6. The full customer sequence must prove receipt replay, wrong packet/hash
   rejection, customer staging acceptance, checkout, payment replay, and
   exactly-once fulfillment.
7. Every portal and Drupal action still needs runtime route, permission,
   persistence, confirmation, failure, retry, and recovery proof. The static
   inventory intentionally cannot provide that.
8. The Drupal theme needs rendered coverage for core pages and representative
   contributed-module routes at phone, tablet, and desktop widths.
9. The integrated implementation has not yet been compared systematically with
   every supplied Kimi/Kimmy screen. Intentional improvements and unacceptable
   drift are not recorded.
10. Independent cross-lane accessibility, security, lifecycle, and evidence
    review is still pending.
11. The integration branch is not yet clean, pushed, or represented by the
    exact commit that a protected staging instance serves.
12. Protected staging browser smoke, rollback proof, and the authorized owner
    notification with provider receipt remain pending.

## Contract drift found in this audit

The active documents corrected with this audit were:

- `docs/AGENT_OPERATING_CONTRACT.md`
- `docs/WEBSITE_PROOF_PRODUCTION_STANDARD_V1.md`
- `docs/architecture/FAMTASTIC_PORTAL_SERVICE_SYSTEM.md`
- `docs/AUTONOMOUS_PIPELINE_ACCEPTANCE.md`
- `docs/qa/REVENUE_LOOP_SYSTEMS_SIGNOFF_V1.md`
- `docs/plans/SHAY_TIGHTEN_UP_YOUR_LOCS_DISCOVERY_AND_GROWTH_PLAN_2026-08-31.md`
- `docs/playbook/RECIPES/LEAD_TO_LAUNCH.md`
- `docs/CAPABILITY_REGISTRY.md`

Historical audits remain historical evidence and were not rewritten. The
superseded selection-to-checkout section in
`docs/plans/CUSTOMER_EXPERIENCE_FULFILLMENT_2026-09-04.md` is explicitly marked
non-executable.

The source-contract mismatch identified during this audit is now closed by a
separate implementation change: both website products enumerate
`verified_staging_receipt` and `customer_staging_acceptance` in
`payment.requires`, matching the checkout gate.

## Candidate reusable procedures after proof

These are nominations only; no skill was created:

- approved-artifact export, digest verification, and three-breakpoint parity;
- pre-payment staging orchestration and exact signed receipt validation;
- Drupal mobile-admin theme conformance across core and contributed modules;
- runtime visible-action/orphan-link audit;
- Git-addressed shared-host deployment with root-path rejection and rollback;
- privacy-safe lesson extraction and cross-repository promotion.

Promotion requires a passing protected-staging run, a second unrelated site or
module class, redacted evidence, documented failure behavior, and owner review.
The MBSH visual recipe and generic CMS/commerce recipes are not yet general
skills.

## Review timing

This document completes the requested review of work performed during the
prior 48 hours. A separate one-time review 48 hours after protected staging is
complete remains required but **unscheduled**. Its clock begins only after the
protected staging URL, exact commit, smoke evidence, and owner notification
receipt exist.
