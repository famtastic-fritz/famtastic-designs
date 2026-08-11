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
2. `website_discovery_v2` captures goals, audience, pages, content, brand,
   integrations, SEO, accessibility/legal context, ecommerce, booking, AI,
   operations, timing, and decision context.
3. The deterministic recommendation is explainable:
   - focused one-page need: `FAM-FOOT-199`;
   - defined business site up to five pages: `FAM-BUSINESS-499`;
   - ecommerce, membership, custom integrations, regulated work, or more than
     five pages: staff scope review.
4. Staff may override the package and create a one-account, one-request private
   offer. Never implement a private price as a public/shareable coupon.
5. Checkout must validate ownership, recommendation/private offer, terms,
   domain branch, and renewal authorization server-side.
6. Paid Commerce orders create SKU-driven intake, project, entitlements,
   communications, renewal records, and portal history idempotently.

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
