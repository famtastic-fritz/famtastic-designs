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
4. `website_proof.generate.v1` produces exactly three working directions named
   Safe, Wild, and OMG. Customer requests stop at an owner review gate before
   any proof-ready email or account disclosure.
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
- Append material decisions and evidence to `docs/SITE_LEARNINGS.md`,
  `docs/CHANGELOG.md`, and the FAMtastic Drive decision log.

## Shay

Shay is an orchestrator, not an alternate source of truth. Shay must use these
same definitions, proof gates, deployment primitives, and evidence labels. If
Shay encounters a contradiction, stop and reconcile the canonical product or
operating contract instead of silently creating a parallel workflow.
