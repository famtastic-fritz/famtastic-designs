# FAMtastic marketing and portfolio master plan

**Started:** August 19, 2026
**Goal:** Turn proven FAMtastic work into an impressive, measurable demand engine with a usable operator workflow, while preserving approval, rights, customer, and production boundaries.
**Resume rule:** Read this plan, `docs/marketing/MARKETING_OPERATOR_GUIDE_2026-08-19.md`, and the linked evidence before changing status. A checked item requires an artifact or verification record.

## Milestone 1 — Make the existing machinery understandable

- [x] Document the actual campaign flow from idea through learning.
- [x] Separate autonomous draft work from Fritz approval gates.
- [x] Record the commands that prove draft readiness.
- [x] Verify the current 68-content manifest, approval fields, UTMs, local lanes, content library, and frontend build.
- [ ] Add this operator guide to the staff-facing Marketing workspace.

**Exit:** An operator can request a campaign without knowing repository structure, and the system returns a complete approval packet.

## Milestone 2 — Collect the work without losing it

- [x] Create an initial machine-readable public-portfolio registry for six representative worlds, screenshots, and evidence.
- [ ] Expand the registry to every retained demo and proof direction with industry, intensity, capabilities, reusable elements, and full run lineage.
- [x] Include the reunion experience, a church concept, FAMU Corner, Bossy Nails by Pri, Good Ole Candy Lady Shop, and Rattler Lifers in the first public set.
- [x] Mark private/customer-specific items as private or sanitized; never imply fictional concepts are customer outcomes.
- [ ] Deduplicate artifacts while preserving hashes, prompts, and run lineage.

**Exit:** Every eligible sample is discoverable and has an honest use/rights status.

## Milestone 3 — Replace the broken Our Work experience

- [x] Audit the live button, route, Drupal dependency, and empty/error states.
- [x] Build a motion-rich FAMtastic experience rather than a conventional card grid.
- [x] Organize work as immersive capability worlds with color, texture, depth, and progressive reveal.
- [x] Support touch, keyboard, reduced motion, small screens, and direct links.
- [x] Distinguish live work and fictional concept labs; add proof worlds and systems engineering as the registry expands.
- [x] Connect the first six items to their actual story, CTA, and rights-safe media.
- [x] Pass build, 1440/390 browser, overflow, image, exception, link, and reduced-motion QA.
- [ ] Deploy only a clean pushed SHA and verify both production hostnames.

**Exit:** “Our Work” always opens a compelling, useful production page with no Drupal-empty dead end.

## Milestone 4 — Create the FAMtastic Marketing workspace

- [ ] Add staff-only Campaigns, Queue, Media, Approvals, Calendar, Channels, Results, and Lessons views.
- [ ] Create a guided brief form with audience, goal, offer, channels, dates, intensity, references, restrictions, and evidence.
- [ ] Generate canonical manifests and stable content IDs from the form.
- [ ] Show real channel previews and asset-rights status.
- [ ] Expose content, media, and publish decisions separately.
- [ ] Keep Drupal as campaign/customer/consent truth and the portable engine provider-neutral.

**Exit:** Fritz can create, inspect, approve, and understand a campaign from FAMtastic Designs without navigating repository files.

## Milestone 5 — Prove two-channel delivery safely

- [ ] Connect one official channel through least-privilege OAuth.
- [ ] Prove draft/private submission, provider ID capture, and deletion.
- [ ] Repeat for a second channel.
- [ ] Prove crop, caption, link, UTM, schedule, bounded retry, duplicate prevention, failure alert, live verification, and rollback.
- [ ] Keep public publishing disabled until Fritz approves the exact batch.

**Exit:** Two channels each have two fully evidenced posts from draft through rollback.

## Milestone 6 — Join demand to business results

- [ ] Carry stable content IDs through social, email, GA4, Drupal leads, requests, checkout, and purchases.
- [ ] Add privacy-safe campaign and CTA events.
- [ ] Show reach, engagement, qualified actions, leads, projects, and revenue without overstating causality.
- [ ] Feed verified lessons into the next content batch.

**Exit:** A campaign can be traced from content record to business outcome and back to a documented lesson.

## Milestone 7 — Earn automation

- [ ] Run a complete 17-day campaign with durable evidence.
- [ ] Prove approvals, idempotency, retries, alerts, and duplicate protection.
- [ ] Prove the same workflow with Shay, Codex, and Claude.
- [ ] Prove a second unrelated brand without editing reusable engine source.
- [ ] Establish provider fallback tests and cost ceilings.
- [ ] Require human approval for public sends until an explicitly approved policy says otherwise.

**Exit:** Routine campaign preparation, QA, scheduling, verification, measurement, and learning run unattended except at declared gates.

## Milestone 8 — Decide the marketing split

- [ ] Complete the extraction manifest.
- [ ] Verify no FAMtastic customer data, secrets, OAuth tokens, private pricing, or Drupal truth enters the portable set.
- [ ] Move only reusable schemas, state machine, provider contracts, QA, and adapters into `famtastic-marketing-engine`.
- [ ] Keep FAMtastic brand, campaigns, customers, commerce, consent, analytics, and credentials with FAMtastic Designs.
- [ ] Consume a versioned marketing-engine release from the FAMtastic repository.

**Exit:** The split reduces coupling without creating two sources of truth.

## Immediate next release

1. Finish and review the new portfolio experience.
2. Complete the portfolio registry and rights audit.
3. Add the staff Marketing workspace shell using existing manifest/approval contracts.
4. Prepare—not publish—the first portfolio-launch campaign with days 1–3 assets.
5. Present one exact release packet to Fritz for content, media, and publish decisions.

## August 19 portfolio build evidence

- Source: `frontend/src/pages/WorkHubPage.jsx`, `frontend/src/pages/WorkHubPage.css`, `frontend/src/seo.js`, and `frontend/public/portfolio/`.
- Diagnosis: `/work` and its button were wired, but production Drupal returned zero `case_study` records, leaving only the empty-state message.
- Resolution: six curated worlds render independently; future Drupal case studies append when available.
- Evidence: `artifacts/portfolio-experience-20260819/`.
- Drive: `FAMtastic Portfolio Experience — 2026-08-19`.
- Status: source complete and locally browser-proven; not deployed.

## Drift checks

- Run `python3 scripts/campaign-readiness.py` before campaign production.
- Run `agent-skills/famtastic-demand-engine/scripts/run-demand-checks.sh "$PWD"` before completion claims.
- Update capability maturity only when evidence changes.
- Update this plan after each milestone with artifact paths, production URLs, dates, and unresolved gates.
- Do not equate a local pass, a provider connection, or a draft with public delivery.
