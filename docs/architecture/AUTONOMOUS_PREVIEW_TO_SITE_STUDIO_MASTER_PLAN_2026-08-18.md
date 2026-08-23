# Autonomous Preview → Site Studio master plan

**Date:** 2026-08-18
**Owner:** FAMtastic Designs
**Goal:** One autonomous FAMtastic-side routine researches, creates, proves, and journals website previews; produces a portable build packet for Site Studio; validates Site Studio's signed success packet; then advances the owned project and queues the customer notification. Site Studio's build engine remains unchanged.

**Boundary clarification:** FAMtastic owns preview generation, artifact slots,
proof access, owner approval, email, and request/project truth. Site Studio
only consumes a selected immutable build packet and later returns a build-success
packet. See `FAMTASTIC_PREVIEW_TO_BUILD_BOUNDARY_V1.md`.

## Non-negotiable boundary

FAMtastic owns intake, research, preview generation, commercial truth, selection, evidence, packet creation, customer/project ownership, notifications, approvals, and payment boundaries. Site Studio receives an immutable build packet and returns an immutable success packet. Neither repository silently reaches into the other's database or rewrites the other's engine.

## Part 1 — Truth baseline and golden benchmarks

- [x] Preserve the FAMU Corner six-direction source, HTML, art, 1440px/390px screenshots, quality report, and evidence.
- [x] Export the source material to Google Drive.
- [x] Refuse to invent a same-brief pre-research artifact that was never captured.
- [ ] Run the unchanged brief through the current Site Studio recipe to create the true baseline comparator. **Owner: Site Studio lane.**

## Part 2 — Stage contracts and observability

- [x] Version provider-neutral build and success packet schemas.
- [x] Journal asked/given/returned values per FAMtastic stage.
- [x] Record hashes, route, execution classification, assertions, duration, fallback, and cost status.
- [x] Label legacy telemetry placeholders and absent historical prompts honestly.
- [ ] Replace `cost.status=not_recorded` with provider usage receipts when a paid provider is authorized.

## Part 3 — Provider-neutral routing and contingencies

- [x] Define free, low, medium, premium, premium-brain/free-worker, and custom build classes.
- [x] Make stages request capabilities instead of models.
- [x] Check installed commands and declared runtime capabilities before resolving a route.
- [x] Gate unproven authentication and prohibit silent simulation.
- [x] Keep deterministic commercial and approval decisions outside model control.
- [ ] Prove unattended authentication separately for each cloud CLI or API before marking it available.

## Part 4 — Research and creative swarm families

- [x] Define research-synthesis, creative-direction/IA, visual-art, construction, browser-QA, and visual-review capabilities.
- [x] Preserve canonical v1 prompts for the highest-value stage families.
- [x] Support specialist sub-swarms through capability routing rather than provider names.
- [x] Require one restrained, one medium, and four ultra-FAMtastic directions in the benchmark contract.
- [ ] Certify one new, unattended, non-replay business run with live research and generated art.

## Part 5 — Preview production, selection, and reuse

- [x] Validate six complete HTML directions and original hero assets.
- [x] Validate full-page screenshots at exactly 1440px and 390px.
- [x] Allow one or two selected directions in a Site Studio build packet.
- [x] Copy selected HTML, art, screenshots, brief, research, directions, and evidence into the packet.
- [x] Catalog every unique direction as a retained template candidate without reusing customer media or copy.

## Part 6 — Independent quality gates

- [x] Require an independent reviewer and no critical defects.
- [x] Enforce overall score ≥8 and no dimension below 7 from the saved benchmark.
- [x] Preserve browser and visual evidence in the packet.
- [x] Reject mismatched or tampered success packets.
- [ ] Add an automated repair adapter for fresh provider-generated candidates before declaring autonomous creative certification.

## Part 7 — Customer portal and operational continuation

- [x] Correlate packet, request, project, selection, and returned build IDs.
- [x] Add Drupal registration for the exact outbound packet.
- [x] Add signed success-packet ingestion to the FAMtastic selected-build
  callback boundary.
- [x] Enforce project ownership through the existing customer-resource map.
- [x] Update only the matching project, append portal activity, and queue one transactional notification idempotently.
- [x] Preserve multiple projects per customer because every result is project-scoped.
- [x] Forbid callback-driven charging, pricing, domain purchase, or production publication.

## Part 8 — Knowledge and capability governance

- [x] Add this dated, checkbox-based master plan.
- [x] Add Gandalf cross-repository notes.
- [x] Add a reusable template-library cataloger.
- [x] Add a repository-owned capability-drift check and pre-push hook.
- [x] Keep marketing extraction separate from customer/Drupal truth.
- [x] Define the marketing split as a one-way, versioned boundary: marketing may consume sanitized capabilities, proof candidates, and public portfolio assets; it may not own customer projects, prices, payments, approvals, transactional notifications, or Site Studio build state.
- [x] Keep campaign/outreach delivery separate from authenticated customer-project notifications and their idempotent outbox.
- [ ] Inventory the existing marketing code and artifacts, then decide in an approved change lane whether they remain in a dedicated worktree/package or move to a standalone repository.
- [ ] Define the only allowed return contract from marketing to core: a consent-aware lead/intake packet with source, campaign attribution, research boundaries, and deduplication identity.
- [ ] Enable the hook in sibling repositories only from each repository's approved change lane.

2026-08-19 status: the public FAMtastic Lab page proves the safe forward edge
of this boundary—a marketing-owned case study can launch a rights-reviewed
proof and route PII-free campaign parameters to the normal `/start` intake. It
does not yet close the return-contract item above because the stable content ID
is not persisted through the Drupal lead record and joined back to GA4. The
full extraction-gate status is machine-readable at
`marketing/campaigns/and-if-it-is-rattler-lifers/evidence/marketing-split-status.json`.

### Marketing split invariant

The preview/build system is the production factory; the marketing system is a demand engine. Marketing can request a public, explicitly unlisted lead proof and can reuse sanitized, unselected design systems after rights and customer-data checks. It cannot write a customer-owned project directly, send a transactional proof-ready message, choose a commercial package, approve a build, initiate payment, or represent Site Studio success. A campaign conversion crosses into FAMtastic core only through the versioned lead/intake contract; from that point forward, Drupal/customer-project truth and the normal preview-to-Site-Studio workflow take over.

## Part 9 — Certification and release

- [x] Provide one command that runs the FAMtastic packet bridge three times from clean output directories.
- [x] Verify signed success ingestion, tamper rejection, two-direction selection, journals, archives, and portal-event emission.
- [x] Keep the Site Studio repository untouched.
- [ ] Obtain a real Site Studio success packet for a selected immutable build
  packet. This is separate from and must not block the FAMtastic preview-room
  release.
- [ ] Run the Drupal kernel/browser path with a real owned project and notification worker.
- [ ] Run one fresh live-research, live-art, independently reviewed order without golden replay.
- [ ] Deploy only from a clean, pushed SHA after production approval.

## Evidence levels

- **Contract-autonomous, locally proven:** one command creates the preview build packet, simulates the external return strictly as a labeled contract fixture, validates signatures and correlation, and emits the portal update event.
- **Site Studio integration proven:** Site Studio consumes the packet without engine changes and returns a real signed success packet that FAMtastic accepts.
- **Creative autonomy proven:** a fresh request produces benchmark-quality work through real provider routes, independent review, repair, and final packet creation without a human constructing the sites.
- **Production proven:** deployed Drupal receives the real result, updates the correct owned project, sends the transaction email once, and shows the build in the customer portal.

## Drift rule

The unchecked external gates are not optional paperwork. The system must not relabel golden replay as live generation, installed CLI as authenticated provider access, a contract fixture as a Site Studio execution, or local Drupal code as production behavior.
