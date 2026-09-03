# Intake, proof handoff, and Tighten Up Your Locs QA repair

Purpose: Make the customer intake path understandable, bind the owner-invited Tighten Up Your Locs deep dive to a verified account, and replace the false Site Studio completion claim with a durable, observable handoff state.

Goal:

An authenticated customer can finish a clear intake, paste a public Booksy link with or without a URL scheme, see which request belongs to their account, and receive a truthful proof-handoff status that is tied to a durable request/job/packet record.

Tasks:

- [x] Re-anchor the active FAMtastic Designs source lane and inspect the portal, deep-dive, proof, and Studio contracts.
- [x] Reproduce the source-level URL validation and Site Studio handoff defects.
- [x] Audit the Tighten Up Your Locs deep-dive/account/request linkage in the authoritative available environment.
- [x] Implement guarded URL normalization, durable handoff status, and truthful portal messaging.
- [x] Add focused regression tests and execute browser/API/build validation.
- [x] Record evidence, update required documentation/Drive mirror, and close the plan.

Status: complete
Started: 2026-09-02 EDT
Ended: 2026-09-03 EDT
Execution: isolated branch `fix/intake-proof-handoff` in `/Users/famtastic-fritz/Development/FAMtastic/sites/famtastic-wt-intake-proof-handoff`; no deployment, customer send, payment, or production mutation is authorized by this task.
Research: current portal, customer-service, deep-dive, Build DNA, Concierge, and website-proof contracts reviewed. Read-only authoritative production state confirms the completed Tighten Up Your Locs interview is claimed by the verified same-email customer and attached to request 12; that request is still a draft with no proof campaign.
Review: source repair complete. The former manual dispatch allowed a draft-only job to be queued even though the worker rejects drafts; it now blocks that invalid action and the portal reports only durable, observed state.
Skills: onboarding (activation and dead-end review)
Blocked By: no source blocker. Deployment and any recovery of the existing production draft/job need separate release and owner authorization.

Proof:

- Current source shows `sendWebsiteRequestToSiteStudio()` queues a proof job then the UI says concepts are generating without a verified Studio packet or job result.
- Current deep-dive URL validation uses `FILTER_VALIDATE_URL` directly and rejects a public Booksy URL without `https://`.
- Customer proof visibility and outbound notification remain behind the owner-review gate.
- Production read-only audit: the account link is already present; the open request is a draft and its old queued job cannot validly produce proofs. No production mutation was performed.
- Validation: PHP lint clean; 85 PHPUnit tests / 422 assertions green; Vite production build green; 30/30 Portal Design DNA checks green; focused desktop Playwright regression (2/2) green.
- Follow-up finding: the older public-lead Playwright specification targets a retired Solution Finder UI (`Website` button) and times out before starting. It should be rewritten against the current scoped-chat intake as separate maintenance, not interpreted as proof of a production failure in this repair.
