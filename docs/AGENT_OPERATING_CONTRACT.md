# FAMtastic agent operating contract

This file is the common operating context for Codex, Claude Code, Shay, and any
future CLI agent. Read it before changing customer, product, Commerce, mail,
deployment, or proof behavior.

## Business model and customer promise

FAMtastic removes the price objection with an intentionally narrow $199 Web
Basics Bundle, then earns retention through useful delivery, support, education,
analytics, and relevant services. The $199 price is a product, not the default
answer to every website interview. Never force a request into a package because
the customer entered through a particular campaign.

Drupal is the operational system of record and staff GUI. React is the branded
customer experience. Drupal Commerce is the financial source of truth for all
new purchases. Stripe holds payment credentials; Drupal must not store cards.

## Intake to purchase decision

1. A customer owns reusable website requests inside their workspace.
2. `website_discovery_v3` captures goals, audience, pages, content, brand,
   integrations, SEO, accessibility/legal context, ecommerce, booking, AI,
   operations, timing, decision context, structured colors and references,
   optional private assets, AI-enrichment consent, and the 0-10 FAMtastic
   creative-intensity preference.
3. The deterministic recommendation is explainable:
   - focused one-page need: `FAM-FOOT-199`;
   - defined business site up to five pages: `FAM-BUSINESS-499`;
   - ecommerce, membership, custom integrations, regulated work, or more than
     five pages: staff scope review.
4. `website_proof.generate.v1` produces exactly three working core directions.
   Their stored labels are configured per run (the legacy defaults are Safe,
   Wild, and OMG); a public room must render those stored labels rather than
   assume one industry-specific formula. Customer requests stop at an owner
   review gate before any proof-ready email or account disclosure.
   An explicit showcase request may append exactly three maximum-FAMtastic
   directions (`d/e/f`) to that complete core set. The six-direction result
   returns to owner review and must not trigger customer delivery by itself.
5. The customer selects an owner-approved direction before checkout. Staff may
   override the package and create a one-account, one-request private offer.
   Never implement a private price as a public/shareable coupon.
6. Private service grants use explicit classes, hashed raw codes, exact account
   and request scope where required, atomic redemption, and Commerce orders.
   A fully sponsored order completes as a real zero-dollar Commerce order; it
   never bypasses fulfillment through a fake paid flag.
7. Checkout must validate ownership, proof selection, recommendation/private
   offer, grant scope, terms, domain branch, and renewal authorization
   server-side.
8. Completed Commerce orders create SKU-driven intake, project, entitlements,
   communications, renewal records, and portal history idempotently.

The complete proof and intake contract is versioned in
`docs/WEBSITE_PROOF_PRODUCTION_STANDARD_V1.md`.

## Page and component doctrine

Every website build follows
`docs/architecture/FAMTASTIC_PAGE_COMPONENT_DOCTRINE_V1.md`. A page is an
ordered recipe of stable component instances; a component is a versioned typed
contract made from fields, slots, repeaters, actions, parts, and optional
motion. One-page packages are starter recipes, not architectural dead ends.

Changing a media slot is not a new template. Upgrades move and extend the same
component instances while preserving their content, visual system, business
bindings, and Build DNA. FAMtastic owns the approved recipe and customer truth;
Site Studio consumes that immutable context and returns truthful continuation
evidence rather than silently redesigning or rerunning it.

Before creating a design, proof, customer portal surface, or recurring
transactional email, agents must read and apply the repository-root
`design.md` Experience System. It is the shared presentation and information
architecture contract; specialist Design DNA, component, proof, and email
contracts remain mandatory.

## Client portal design doctrine

All customer portal (`/portal`), token-scoped workspace (`/portal/:token`), and
prospect workspace surfaces must adhere to `docs/architecture/FAMTASTIC_CLIENT_PORTAL_DESIGN_DNA_V1.md`
and its JSON contract `docs/architecture/FAMTASTIC_CLIENT_PORTAL_DESIGN_DNA_V1.json`.
Validate changes with `node scripts/validate-client-portal-design-dna.mjs`.

- Derive all modules from durable records: orders, entitlements, project instances, and intakes.
- No synthetic numbers, fake affordances, or test data strings in customer-facing views.
- No external leakage: recommended services and offers route to in-portal actions or `/buy?sku=...`.
- One-glow rule: maximum one glowing pulse element per screen (`box-shadow: 0 0 24px rgba(124,252,0,.35)`).
- Strict mobile usability (min 44px touch targets, `overflow-x: clip`, fluid layout).
- Governed Shay boundary: explains, summarizes, and routes; never mutates billing or state without human confirmation.

## Preview-provider doctrine

`website_proof.generate.v1` is the only supported creative-preview routine.
It must read the declared capability route, run provider preflight, and create
the Build DNA record before it starts research, art, construction, or review.
An agent may not substitute a chat-only mockup, a fixture renderer, or an
unrecorded model session for that routine.

- Gemini 3.7 Flash through the Antigravity desktop bridge is the preferred
  **reasoning, research, direction, copy, and prototype-construction** lane
  when its local authenticated bridge passes a structured provider call. A
  desktop sign-in or command discovery alone is not execution proof; without
  the bridge address and a recorded success, the run must use its declared
  fallback or stop visibly.
- Gemini 3.1 Flash Lite Image is the preferred economical **preview-art** lane
  for original 1K proof artwork. Its prompt must carry the complete
  art-direction contract: composition, material, lighting, cast/action,
  negative space, aspect ratio, and exclusions. Its 1K limit is intentional
  for preview use, not an unstated final-production guarantee.
- Gemini Flash Image, Gemini Pro Image, and the image-only `gpt-image-2`
  route are explicit quality/resolution escalation lanes. They require the
  selected route, preflight, actual receipt, and Build DNA cost record; a
  failure in Lite must never silently spend on a premium model.
- The generator may not perform final visual approval. Browser QA and an
  independent review route remain required, even when Gemini creates both the
  art and prototype.
- The installed `.agents/skills/gemini-interactions-api` reference applies to
  Gemini Enterprise Agent Platform (GEAP): it needs a provisioned agent and
  Application Default Credentials. FAMtastic's existing image worker uses the
  separate Gemini Developer API image-only Keychain route. Do not mix their
  credential models or claim one proves the other.

The provider registry distinguishes desktop-attended execution, authenticated
API execution, and unattended service execution. Do not call a local desktop
bridge production-autonomous until it survives a clean-session and unavailable-
provider failure test with the real declared fallback.

## Product creation

`backend/config/famtastic-products.json` and
`backend/config/famtastic-deal-terms.json` are canonical. Follow
`docs/PRODUCT_ONBOARDING_PIPELINE.md`; no product is complete from a title and
price alone. Every product needs scope, exclusions, intake, fulfillment,
entitlements, portal behavior, communications, upsells, reporting, acceptance,
and renewal/cancellation treatment.

## Safety and proof

- Sandbox and live are separate launch decisions. Never interpret a stub,
  handcrafted webhook, or Stripe test card as proof of live charging.
- Never enable live charging with test credentials or without validating the
  live webhook, checkout, notifications, refund/failure handling, and exact
  approved terms.
- Use `scripts/run-customer-proof-agent.sh` and the acceptance contract in the
  installed `prove-famtastic-customer-journey` skill. Classify every claim as
  locally proven, test-provider proven, production smoke-tested, or blocked.
- Deploy only a clean, pushed SHA through the checked-in deployment scripts.
- Sync through `docs/GIT_SYNC_AND_RELEASE_DISCIPLINE.md`: fetch and inspect
  incoming commits before implementation, push, and deploy. Never equate a
  pushed branch with an approved or browser-proven production release.
- Append material decisions and evidence to `docs/SITE_LEARNINGS.md`,
  `docs/CHANGELOG.md`, and the FAMtastic Drive decision log.

## Shay

Shay is an orchestrator, not an alternate source of truth. Shay must use these
same definitions, proof gates, deployment primitives, and evidence labels. If
Shay encounters a contradiction, stop and reconcile the canonical product or
operating contract instead of silently creating a parallel workflow.

## FAMtastic Concierge and Connections

FAMtastic Concierge is the named customer-facing communications identity.
FAMtastic Connections is the canonical projection of lead, registration,
communication, proof, offer, and delivery status. Drupal remains the customer,
Commerce, project, and operational source of truth; Site Studio remains the
internal build/proof/QA surface. No CLI, including Shay, Codex, or Claude, may
create a competing CRM or deployment authority.

The Concierge webhook boundary accepts only verified Inkbox events and records
metadata/status facts. It must not copy customer message bodies into a new
shadow store, send a response, or turn a delivery event into commercial or
deployment approval. Human approval remains required before any outbound
message, price/offer/grant, payment, domain action, or release. The exact
handoff contract is `docs/architecture/FAMTASTIC_CONNECTIONS_CONCIERGE_CONTRACT_V1.md`.

For a public lead who receives pre-registration working concepts, use only
`docs/architecture/PUBLIC_PREVIEW_DELIVERY_V1.md`. A campaign email, a legacy
prospect token page, an account-owned proof share, or a manually copied URL is
not a substitute for the owner-gated public-preview delivery and verified
same-email claim.

Public-preview ownership is exact, not merely Prospect-based: bind the public
delivery to its new campaign before remote dispatch, and bind a later registered
request to a different campaign. Never let a generic worker or callback reuse a
public campaign for a commercial request, or use a generic/cold campaign for a
public room.
