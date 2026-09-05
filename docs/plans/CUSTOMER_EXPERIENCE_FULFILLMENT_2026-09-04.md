# Customer experience and fulfillment — 2026-09-04

Title: Customer experience, starter-site promise, and Tighten Up Your Locs proof handoff
Purpose: Turn the FAMtastic approach into a precise, honest starter-site promise while proving the next real customer step from completed discovery to an owner-approved proof review.
Goal: The customer-facing offer clearly explains what is included and conditional, the intake-to-proof path is deployed only through the canonical release lane, and Fritz can use the exact Tighten Up Your Locs customer link to complete the next intentional step without pretending that a proof has already been delivered.

Tasks:
- [x] Re-anchor to the canonical delivery repository, contracts, current Git history, and prior Tighten Up Your Locs evidence.
- [ ] Reconcile the starter-site promise: domain branch, first-year hosting, SSL, analytics, email treatment, research summary, three directions, reset/re-direction round, and edit rounds. (Wiring assessment completed 2026-09-04; implementation remains pending the approved product contract.)
- [ ] Add an owner-only, read-only "Preview customer experience" for each website request. It must render the same account-owned workspace payload without impersonating the customer, sending mail, changing the customer session, or permitting selections, revisions, payments, uploads, or support replies. Existing raw owner proof previews are not a substitute.
- [x] Trace the real account → request → submission → proof job → owner review → customer proof lifecycle and identify the exact next customer action for Tighten Up Your Locs. (Request `dffd4cb9-c3aa-47fd-a184-52577053bc09` is a verified customer-owned `draft`; its completed deep-dive is linked, its prior job `301` failed at 5/5 attempts, and it has no proof campaign. The next intentional action is customer submission, not a retry of that legacy job.)
- [x] Integrate the proof-handoff repair with current `origin/main`, run the required proof suite, and deploy the reviewed release if the production gates pass. (Release `1e0f82cb` is deployed to backend and frontend; the staging-directory repair prevented the host's dot-directory removal from interrupting promotion. Targeted PHP, portal-design, frontend-build, and local browser checks passed; the full canonical runner remains locally database-blocked.)
- [x] Perform only authorized, non-destructive production checks; provide Fritz the correct customer-facing next-step link, not a fabricated proof link. (Production route resolves. An owner-session browser saw the deliberate cross-account refusal, so the link is customer-authenticated rather than a bearer proof URL.)
- [x] Record evidence, material decisions, capability status, and session closeout across the required repository surfaces and Drive mirror. (2026-09-04: branded account-owned Studio Review email locally proven; Drive mirror and repository records updated. Production/customer receipt remains an explicitly separate owner-authorized gate.)
- [x] Produce and reject an initial Tighten Up Your Locs owner-review candidate. It is retained at `docs/design/proofs/tighten-up-your-locs/` only as a failure example: a shared shell with theme changes, generic customer copy, one concept asset, and a non-functional local form. It does not satisfy the six-direction benchmark, client-specific research synthesis, booking/backend contract, independent visual review, or Build DNA gate and must not be sent, bound to the request, or represented as a customer proof.
- [x] Build a research-led Tighten Up Your Locs v2 private proof set with three independently authored journeys, a client-readable research snapshot, generated concept media truth labels, Build DNA, desktop/mobile browser checks, and a disabled-by-default owner-managed request-to-book backend slice. Local evidence: `docs/design/proofs/tighten-up-your-locs-v2/`; it is neither deployed nor customer-ready.
- [x] Reframe the Shay build around the actual “tech + grow” product: a self-contained mobile public front door (branded domain treatment, availability, direct request, Find Us, services), a separate no-fake-metrics phone Owner Desk, and a bounded 90-day growth plan that seeds future FAMtastic work from measured business needs rather than from a generic template.
- [x] Extend Shay’s visual system beyond a header image with a character-led journey, a phone-owner character, and a generated care-rhythm graphic; retain generated-media provenance and require owner approval/replacement before launch.
- [x] Reconcile the prior backend attempts and record the one path to finish: new request ledger → exact customer-account authorization → authenticated phone Owner Desk → owner-controlled response → separately approved provider actions. Do not reuse the Thirst Trap-only microsite service or static prototype state for Shay.
- [x] Reconcile Shay's authoritative portal-account email before changing the live request. Read-only production query on 2026-09-05 matched request `12` / `dffd4cb9-c3aa-47fd-a184-52577053bc09` for Tighten Up Your Locs to customer email `junyeismom@gmail.com`. The earlier manually supplied `juneyimoms@gmail.com` is not the account-of-record and must not be used for future customer delivery.
- [x] Prepare an owner-authorized temporary client review with a branded HTML email template, a non-live temporary review URL, and a phone Owner Desk review path. Send is explicitly authorized by Fritz on 2026-09-04; provider/Gmail receipt is recorded separately from the production proof-ready lifecycle.
- [x] Send the owner-authorized temporary review notice to `juneyimoms@gmail.com`. Gmail Sent receipt: message/thread `1a06f536cc43ac8d`; the review remains temporary and does not change Shay’s portal request or activate any client capability.
- [x] Create three local research-led candidate concepts: Care Rhythm, Appointment Desk, and Established Archive. These files are retained as local build evidence only; they are not a customer proof room, do not record a choice, and must not be delivered by external-link email.
- [x] Correct the recipient evidence using the authoritative production account record: request `12` belongs to `junyeismom@gmail.com`. Prior manual emails are out-of-system notices, not the account-owned proof delivery. The temporary public review tunnel was withdrawn on 2026-09-05.
- [x] Map the next client and operator portal architecture: Shay’s account needs Today, My Website, Owner Desk, Growth Plan, Files, Messages, and Account; Fritz needs Client 360, proof/delivery controls, and exception recovery. See `CLIENT_AND_OPERATOR_PORTAL_ARCHITECTURE_V1.md`.

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

**Activation and template follow-through (2026-09-04):** an explicit customer draft → submitted transition queues exactly one proof job and a separate Concierge “Design Review started” receipt. The subsequent owner-approved proof release remains a distinct Studio Review email. New outbox rows persist template ID/version, and `docs/templates/TRANSACTIONAL_EMAIL_TEMPLATE_REGISTRY_V1.md` is the versioned inventory and copy/claim contract. The committed source has local unit/static evidence; one owner-authorized preview was accepted by production SMTP for `fritz.medine@gmail.com` as `<thaQpdhEYtsPNgWGbnVIuMyUSVBEDyvRIrbiF7GPuo@default>`, using the exact source-rendered Tighten Up Your Locs payload. It did not change Shay’s draft request, create a job, send to Shay, or deploy the source/migration. Gmail inbox confirmation remains separate evidence.

**Visual alignment gate (2026-09-04):** the owner preview exposed that the v1 receipt was technically branded but visually generic. A review-only v2 mock now lives at `docs/design/mockups/2026-09-04-concierge-intake-email-v2.html` and is served locally for inspection. It proposes the Business Solutions Studio / Business Signal Map visual language while retaining honest lifecycle states. Do not translate it into the production renderer, re-send an owner preview, submit Shay, or send her mail until the visual direction is approved.

**Client-specific proof rebuild (2026-09-04):** the first visual proof was explicitly rejected as a re-skinned shell. `docs/design/proofs/tighten-up-your-locs-v2/` now contains three materially distinct pages: The Retightening Ledger (care continuity/rebooks), The Appointment Room (owner-reviewed availability), and The Established Archive (authorized portfolio-led trust). It includes the disclosed research rationale and an independently validated Build DNA record. The corresponding Drupal request endpoint persists a secure, rate-limited owner workflow but is off for all sites by default; no Booksy sync, Google Calendar event, payment, deployment, or customer mail occurred.

**Owned mobile business-system correction (2026-09-04):** the root v2 page now acts as Shay’s brand site rather than a proof chooser: `TightenUpYourLocs.com` is visually proposed but not represented as registered; self-contained availability, protected contact, directions, services, and a mobile quick-action dock remain visible. The client-facing route no longer links to Booksy. The owner-only `owner/` page models the future phone command center with truthful pending states, and `shay-growth-plan.md` turns research into an approval-gated, measured growth sequence.

**Implementation order:**

1. Approve the starter-site contract and bounded report schema: sources/date, observed facts, labeled opportunities/hypotheses, constraints, customer decisions, and no growth guarantees.
2. Add a versioned, immutable request research snapshot keyed to request, proof campaign, and version; retain a sanitized report, sources, snapshot hash, owner/time, Build DNA run/hash, and research artifact hash/role.
3. Add request-scoped create/read services that require complete bound proofs, owner review, customer ownership, consent where needed, and Build DNA manifest membership; return no-store and never expose raw interview answers or cross-account data.
4. Extend owner proof review to inspect and attest to the snapshot, atomically freeze it with the approved ready notice, and retain an exact message/delivery receipt.
5. Add a customer-owned “Research & opportunities” panel only when a snapshot exists. The ready email should say the three concepts and Business Opportunity Snapshot are available in the secure workspace; it must not include a bearer research link, marketing copy, or a buy prompt.
6. Keep the exact transactional outbox and owner approval gate. Verify queued → provider accepted → notified by exact outbox key; verify an inbox/provider-delivery event separately.

**Proof matrix before any customer promise:** service ownership/idempotency/invalid-evidence tests; controller 401/403/404/no-store tests; owner-form approval tests; synthetic journey extension that dispatches memory transport and asserts stored body, receipt, `notified`, same-account research access, cross-account denial; customer portal desktop/mobile tests; PHP pipeline validator, focused PHPUnit, Design DNA, frontend build, capability drift, diff check; then one owner-approved production release and an exact recipient/provider receipt check.

## Correction — account-owned proof decision (2026-09-05)

The temporary static proof links and their notification emails are retired as a
proof-delivery path. They took the decision outside Shay's account, so they
could not safely record a choice, design reset, or edit round. The public
tunnel has been stopped.

The canonical release gate now requires the authenticated portal review to
contain an owner-approved research snapshot with an overview and a rationale
for Safe, Wild, and OMG. The review screen displays the research and the
included terms: three directions, one concept-level design reset before a
selection, and three edit rounds after a selection. The existing selection API
records the decision against the request; a new implementation guard tracks
and limits those included requests. Email remains a branded, transactional
notice whose only proof CTA is the authenticated request workspace.

**Remaining Shay activation gate:** request `12` is still a draft with no
campaign. The local candidate pages cannot be copied straight into production:
the account-owned importer accepts an exact three-direction callback artifact
set, rejects active HTML, binds it only to a submitted request, and then holds
the set for owner review. Package the approved candidate content through that
callback contract, submit Shay's request intentionally, review the complete
bound set with its snapshot, and only then use the outbox-created Studio Review
notice to `junyeismom@gmail.com`.

## Reusable mobile command-center and proof packaging (2026-09-05)

The three strong preceding commits establish the reusable pattern: `e4d0b9b7`
made customer email templates versioned outbox records, `9817f18a` separated
the owner-review receipt from delivery claims, and `127a9a77` established the
more visual Concierge direction. This session carries that discipline into the
business system itself.

- `frontend/public/component-systems/mobile-command-center.v1.json` is the
  source-controlled reusable registry for customer front door, availability,
  protected request, Owner Desk, growth, and account-owned proof review
  components. It labels proof-built versus disabled-by-default backend slices
  rather than pretending a component is live.
- Shay’s `site-recipe.json` now binds every stable instance to that registry;
  `scripts/validate-mobile-command-center-recipe.mjs` resolves all instances
  and fails unknown/duplicated identity.
- `scripts/package-tighten-up-your-locs-proof.mjs --check` proves the three
  client-specific directions can enter the protected callback lane as `a`,
  `b`, and `c`. The write mode requires the exact campaign ID, job ID, unique
  callback event, and private callback path; it cannot guess a campaign or
  send mail.
- Account-owned proof artifacts now have customer-, owner-, and revocable
  share-scoped image routes. The reader rewrites only manifest-declared asset
  references and verifies the stored hash before it serves a byte. This closes
  the gap where a visually rich proof could render without its declared media
  after import.
