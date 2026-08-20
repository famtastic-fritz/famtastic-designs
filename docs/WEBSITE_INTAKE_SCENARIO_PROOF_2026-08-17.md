# Website intake scenario proof — 2026-08-17

## Objective

Evaluate two website-discovery lanes against ten customer situations:

1. An anonymous prospect uses Solution Finder and may request a mockup before creating an account.
2. An authenticated customer creates a resumable website request in the client portal.

## Evidence classification

- Canonical customer lifecycle: **locally proven** through the repository proof runner.
- Authenticated portal scenarios: **locally proven** through ten synthetic Drupal service requests, with cleanup.
- Anonymous Solution Finder scenarios: **frontend contract validated** and frontend build proven.
- Anonymous automatic mockup fulfillment: **not yet proven / not connected**. The public request is captured, but it does not automatically invoke and deliver the existing three-variant proof campaign.
- Real domain availability, registrar purchase, mailbox provisioning, provider email delivery, and production submission: not proven by these local tests.

## Ten-scenario matrix

| # | Situation | Anonymous Solution Finder | Customer portal | Recommendation behavior |
|---|---|---|---|---|
| 1 | No web presence; logo exists | Captures no-site status and ready brand | Preserves ready brand | No logo add-on |
| 2 | No presence; no logo; logo declined | Explicit decline option | Explicit `no_logo_no_help` state | No logo add-on |
| 3 | No presence; wants a logo | Explicit help option | Explicit `help_needed` state | Suggests `FAM-BRAND` |
| 4 | Industry research needed | Free-text industry/location/business model | Stores industry and research questions/context | Research remains a human delivery responsibility |
| 5 | Existing business model | Required plain-language question | Stores current acquisition, sales, payment, and delivery model | Preserved for scoping |
| 6 | Domain and custom email | Captures desired/existing domain infrastructure and email needs | Captures desired names, fallbacks, ownership stack, mailboxes, and forwarding needs | Suggests `FAM-BUSINESS-EMAIL` when requested |
| 7 | Sites liked/disliked | Captures URLs and reasons | Stores references separately from reasons | Reasons are available to design/scoping; no automatic visual scoring claim |
| 8 | Existing domain/hosting/email/repos | One consolidated infrastructure field with password warning | Dedicated ownership/access field plus domain branch | Existing ownership remains distinct from FAMtastic hosting |
| 9 | Industry is not listed | Industry remains free text | Industry remains free text | Unknown industries are preserved, not coerced |
| 10 | Product/service is not listed | Open custom-needs field | Open custom-needs field | Portal recommendation fails safely to human custom-scope review |

## Add-on behavior demonstrated

- Logo help suggests Logo and Brand Starter; declining logo help does not.
- Business mailbox/forwarding needs suggest Business Email Setup.
- Copywriting, booking, and AI requirements continue to suggest their matching configured add-ons.
- Suggested portal add-ons are now shown with customer-facing titles.
- Suggested add-ons are preselected at checkout for review, display their prices, and remain individually optional.
- An unlisted need cannot be auto-purchased as though it were already defined; it goes to human scope review and may become a private offer or a newly onboarded product.

## Ten additional scenarios for the next round

1. A redesign customer whose current site, domain, hosting, and email are controlled by four different providers.
2. A regulated or safety-sensitive business that needs accessibility, privacy, licensing, or disclaimer review.
3. A multilingual business that needs translated pages and language-specific customer actions.
4. A local service business with several service areas, seasonal hours, and emergency versus routine leads.
5. A business with incomplete content that wants FAMtastic to write copy but already owns professional photos.
6. A customer needing booking, deposits, cancellation rules, reminders, and calendar synchronization.
7. A seller whose “online store” actually requires discovery for inventory, variants, tax, shipping, pickup, refunds, and staff operations.
8. A membership or portal request with customer roles, protected files, recurring access, and account migration.
9. A customer with a fixed launch event but dependencies they do not control, testing whether timing is realistic rather than promised.
10. One customer submitting website, brand, email, automation, and analytics needs together, testing deduplication and whether the recommendation remains understandable.

## Domain-unavailable and membership flow

The intake now records acceptable domain alternatives and the customer's preferred fallback. Domain availability must be verified before purchase; the form must not promise availability based on a typed name.

An anonymous prospect remains a prospect while evaluating a mockup or recommendation. Account creation should happen when the person wants a durable workspace: to save/continue the request, exchange files/messages, accept a private offer, or purchase. Once authenticated, follow-up belongs in the portal conversation history. The current public lane still needs an explicit invitation/handoff that connects the anonymous request to that account without creating a duplicate request.

## Commands and results

```text
prove-famtastic-customer-journey/scripts/run-proof.sh <repo>
PASS — canonical synthetic customer lifecycle

node scripts/validate-public-solution-finder-scenarios.mjs
PASS — 10 anonymous intake contract scenarios

backend/vendor/bin/drush scr scripts/e2e-website-intake-scenarios.php
PASS — 10 authenticated portal scenarios; synthetic records removed

npm --prefix frontend run build
PASS — production frontend build and SEO shell generation
```

## Next proof sequence

1. Connect a public `mockup` or `both` request to the existing proof-campaign job without requiring registration.
2. Deliver a secure, expiring mockup-review link by the approved transactional-email path.
3. Add an explicit “save this in my account” invitation that claims the existing prospect/request instead of duplicating it.
4. Browser-test both lanes at mobile and desktop widths, including save/resume and all conditional branches.
5. Prove domain-unavailable back-and-forth through a portal conversation and a revised recorded domain choice.
6. Keep domain purchase, mailbox provisioning, and production outreach behind provider-specific proof and approval gates.

The full specialist-agent, Shay routine, provider-routing, QA, monitoring, and
governed-learning architecture for this sequence is defined in
`docs/architecture/SHAY_WEBSITE_DELIVERY_SWARM.md`.
