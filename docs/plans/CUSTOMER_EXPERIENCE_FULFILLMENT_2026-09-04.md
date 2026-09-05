# Customer experience and fulfillment — 2026-09-04

Title: Customer experience, starter-site promise, and Tighten Up Your Locs proof handoff
Purpose: Turn the FAMtastic approach into a precise, honest starter-site promise while proving the next real customer step from completed discovery to an owner-approved proof review.
Goal: The customer-facing offer clearly explains what is included and conditional, the intake-to-proof path is deployed only through the canonical release lane, and Fritz can use the exact Tighten Up Your Locs customer link to complete the next intentional step without pretending that a proof has already been delivered.

Tasks:
- [x] Re-anchor to the canonical delivery repository, contracts, current Git history, and prior Tighten Up Your Locs evidence.
- [ ] Reconcile the starter-site promise: domain branch, first-year hosting, SSL, analytics, email treatment, research summary, three directions, reset/re-direction round, and edit rounds. (Wiring assessment completed 2026-09-04; implementation remains pending the approved product contract.)
- [x] Trace the real account → request → submission → proof job → owner review → customer proof lifecycle and identify the exact next customer action for Tighten Up Your Locs. (Request `dffd4cb9-c3aa-47fd-a184-52577053bc09` is a verified customer-owned `draft`; its completed deep-dive is linked, its prior job `301` failed at 5/5 attempts, and it has no proof campaign. The next intentional action is customer submission, not a retry of that legacy job.)
- [x] Integrate the proof-handoff repair with current `origin/main`, run the required proof suite, and deploy the reviewed release if the production gates pass. (Release `1e0f82cb` is deployed to backend and frontend; the staging-directory repair prevented the host's dot-directory removal from interrupting promotion. Targeted PHP, portal-design, frontend-build, and local browser checks passed; the full canonical runner remains locally database-blocked.)
- [x] Perform only authorized, non-destructive production checks; provide Fritz the correct customer-facing next-step link, not a fabricated proof link. (Production route resolves. An owner-session browser saw the deliberate cross-account refusal, so the link is customer-authenticated rather than a bearer proof URL.)
- [x] Record evidence, material decisions, capability status, and session closeout across the required repository surfaces and Drive mirror. (2026-09-04: branded account-owned Studio Review email locally proven; Drive mirror and repository records updated. Production/customer receipt remains an explicitly separate owner-authorized gate.)

Status: in_progress
Started: 2026-09-04 00:00 America/New_York
Ended:
Execution: parallel research plus one controlled release lane
Research: yes — docs/AGENT_OPERATING_CONTRACT.md; docs/WEBSITE_PROOF_PRODUCTION_STANDARD_V1.md; docs/CAPABILITY_REGISTRY.md; doctrine/DESIGN-SPEC.md
Review: yes — customer journey proof, deployment preflight, exact live-record inspection, and browser acceptance
Skills: prove-famtastic-customer-journey
Blocked By: none

Proof:
- A versioned starter-site contract with explicit inclusions, limits, conditions, renewal treatment, and customer-facing terms that do not overclaim provider capability.
- A clean, current `origin/main` release lineage, recorded deployment receipt, and production browser/API checks when a release is applied.
- An authoritative Tighten Up Your Locs record showing the real lifecycle state and a safe customer-facing URL for the next action.
- Completed docs/CHANGELOG.md, docs/CAPABILITY_REGISTRY.md when evidence changes, .site-context/SITE-LEARNINGS.md, and required Drive mirror status.

## Background wiring assessment — post-proof-ready client delivery

**Correct path for Tighten Up Your Locs:** authenticated account-owned website request → submitted request → bound proof campaign → complete proofs → owner review → exact transactional outbox → provider acceptance → account-owned review. Do not reuse either the public-preview delivery path or `CampaignMessageService`; those are pre-registration/marketing lanes with different identity and commercial rules.

**Existing ready notice:** `CustomerPortalService::approveWebsiteRequestProof()` requires an owner-reviewed, complete three- or six-direction set, changes the request to `customer_ready`, and queues the exact authenticated portal link to the verified customer email. `LifecycleOperationsService::dispatchNotifications()` records provider acceptance and only then changes the request to `notified`. A provider acceptance is not inbox-delivery proof.

**Current gap:** the account-owned request has intake research context but no immutable, evidence-bound Business Opportunity Snapshot; no customer API/panel; and no proof-ready email content that references one. The separate public-preview research snapshot is a good boundary pattern but cannot be reused because it is a different pre-registration identity model.

**Implemented local improvement (2026-09-04):** the proof-ready outbox notice now selects a dedicated FAMtastic Concierge HTML Studio Review template from its durable notification key. It retains the authenticated account portal link and plain-text fallback, is not used for leads/campaigns, and intentionally does not claim that a research snapshot is available. Focused memory-transport email evidence passed; the full local journey cannot currently start because the checkout's Drupal database is unreachable.

**Activation and template follow-through (2026-09-04):** an explicit customer draft → submitted transition queues exactly one proof job and a separate Concierge “Design Review started” receipt. The subsequent owner-approved proof release remains a distinct Studio Review email. New outbox rows persist template ID/version, and `docs/templates/TRANSACTIONAL_EMAIL_TEMPLATE_REGISTRY_V1.md` is the versioned inventory and copy/claim contract. This has local unit/static evidence only; moving Shay from draft to submitted, sending mail, and releasing a production migration remain intentional owner-authorized actions.

**Implementation order:**

1. Approve the starter-site contract and bounded report schema: sources/date, observed facts, labeled opportunities/hypotheses, constraints, customer decisions, and no growth guarantees.
2. Add a versioned, immutable request research snapshot keyed to request, proof campaign, and version; retain a sanitized report, sources, snapshot hash, owner/time, Build DNA run/hash, and research artifact hash/role.
3. Add request-scoped create/read services that require complete bound proofs, owner review, customer ownership, consent where needed, and Build DNA manifest membership; return no-store and never expose raw interview answers or cross-account data.
4. Extend owner proof review to inspect and attest to the snapshot, atomically freeze it with the approved ready notice, and retain an exact message/delivery receipt.
5. Add a customer-owned “Research & opportunities” panel only when a snapshot exists. The ready email should say the three concepts and Business Opportunity Snapshot are available in the secure workspace; it must not include a bearer research link, marketing copy, or a buy prompt.
6. Keep the exact transactional outbox and owner approval gate. Verify queued → provider accepted → notified by exact outbox key; verify an inbox/provider-delivery event separately.

**Proof matrix before any customer promise:** service ownership/idempotency/invalid-evidence tests; controller 401/403/404/no-store tests; owner-form approval tests; synthetic journey extension that dispatches memory transport and asserts stored body, receipt, `notified`, same-account research access, cross-account denial; customer portal desktop/mobile tests; PHP pipeline validator, focused PHPUnit, Design DNA, frontend build, capability drift, diff check; then one owner-approved production release and an exact recipient/provider receipt check.
