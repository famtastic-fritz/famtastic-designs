# FAMtastic mobile command center rebuild

Purpose: Replace the fragmented customer portal, staff operations, and native Drupal experience with one mobile-first, evidence-backed command center that preserves FAMtastic lifecycle truth.
Goal: Deliver a protected staging instance where customer and staff journeys work end to end on phone and desktop, with no production customer, payment, email, DNS, or deployment side effects during development.

Tasks:
- [x] Re-anchor on the current FAMtastic Designs source and isolate production work
- [x] Create one integration worktree and three non-overlapping worker worktrees
- [x] Audit the prior 48 hours of cross-repository work and record confirmed outcomes, rework, contract drift, remaining evidence gaps, and conditional skill candidates
- [ ] Reconcile the approved mockups and repository Design DNA into one shared token, component, navigation, and interaction contract
- [ ] Rebuild the authenticated customer portal around durable next actions, projects, proofs, staging review, billing, services, messages, support, growth, and account settings
- [ ] Rebuild the staff mobile command center and native Drupal theme so login, forms, queues, records, email, support, products, and lifecycle screens share one coherent system
- [ ] Close producer-to-consumer workflow gaps for consent, notifications, support, email history, proof selection, staging receipts, checkout, and paid fulfillment
- [ ] Add honest unavailable, empty, permission, stale, network-failure, retry, and recovery states for every supported action
- [ ] Create an isolated integration runtime with separate Drupal database, files, mail capture, Stripe test mode, and disabled production jobs/transports
- [ ] Run contract, unit, kernel, integration, accessibility, responsive, visual, and browser journey tests at mobile, tablet, and desktop widths
- [ ] Inventory every customer/staff link and action; prove a reachable route, durable result plus confirmation, or an honest unavailable state with recovery
- [ ] Compare the integrated mobile/desktop renders to the supplied Kimmy/Kimi references and record every match, intentional improvement, unacceptable drift, and missing state
- [ ] Run the canonical synthetic customer journey and verify every evidence assertion
- [ ] Complete independent cross-lane review, reconcile defects, and repeat gates until clean
- [ ] Commit and push the integrated source with changelog, capability evidence, site learnings, review, and resumable closeout records
- [ ] Create and verify a protected FAMtastic Designs staging deployment from the exact reviewed commit
- [ ] Notify Fritz in the Codex task and at the authoritative owner email with the staging link and evidence summary
- [ ] Schedule a one-time 48-hour post-review after staging completion to record evidence, research alternatives, and nominate only proven repeatable patterns for agent-agnostic skill extraction

Status: in_progress
Started: 2026-09-09 17:36 America/New_York
Ended:
Execution: parallel swarm — one coordinator/integrator plus customer, Drupal, and lifecycle workers
Research: yes — repository contracts, supplied prototypes, prior journey evidence, and `docs/research/`
Review: yes — independent implementation, security, accessibility, and end-to-end evidence review required before staging
Skills: prove-famtastic-customer-journey, famtastic-verified-revenue-loop
Blocked By: none
Branch: `codex/fd-command-center-integration`
Worktree: `/Users/famtastic-fritz/Development/worktrees/fd-command-center-integration`
Landing: reviewed integration branch first; no production merge or deployment is authorized by this plan

Proof:
- Production registrations and active customer work remain unchanged during development
- All supported controls have a durable record, authorized consumer, truthful response, and reachable recovery path
- Customer and staff journeys pass with real local Drupal persistence and captured test communications
- Portal and Drupal screens meet the shared Design DNA, keyboard, focus, touch-target, overflow, contrast, and responsive gates
- The canonical customer proof exits zero, emits all required markers, and records only true assertions
- Protected staging serves the exact reviewed commit and passes phone and desktop smoke tests
- Staging notification is sent once to the verified owner account with an auditable provider receipt

## Evidence checkpoint — 2026-09-09

The prior-48-hour retrospective is recorded in
`docs/evidence/MOBILE_COMMAND_CENTER_48_HOUR_RETROSPECTIVE_2026-09-09.md`.

Locally committed implementation exists for the mobile customer command center,
reusable Drupal administration theme, consent/outbox hardening, truthful
recovery states, immutable selected-artifact staging packets, exact
packet-bound receipts, staging-ready notification, durable customer staging
acceptance, and the checkout gate. Relevant integration commits are
`935d3c95`, `d9edd1b9`, `eda5fd3c`, `ccfcc50e`, `96168979`, `9e68e451`,
`b209c565`, `54b5a88c`, `e97e88e6`, and `728c75d6`.

This does **not** complete the unchecked acceptance tasks. The Drupal client
and Site Studio Next acceptance route now pass an actual HMAC-authenticated
PHP-to-Node loopback dispatch using the same immutable multi-file packet
contract. An empty runtime endpoint or secret still fails closed, and
acceptance returns only a waiting-for-callback receipt rather than claiming a
build or deploy. Protected runtime creation, standalone-repository/staging evidence, running
Drupal route/theme coverage, full responsive/accessibility/browser evidence,
mockup comparison, independent review, clean push, protected staging smoke, and
owner notification remain unproven.

The future one-time 48-hour post-staging review is intentionally unscheduled.
Schedule it only after protected staging serves the exact reviewed commit and
the owner notification has a provider receipt.
