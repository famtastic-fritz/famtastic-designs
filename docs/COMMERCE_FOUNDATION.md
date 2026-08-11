# Drupal Commerce foundation

## Production sandbox acceptance — 2026-08-11

The branded account checkout is now the active financial path; the legacy token
checkout is disabled outside local proof runs. Production completed Commerce
order `1` in Stripe test mode for Web Basics plus one revision add-on ($274).
The acceptance proved billing address, Payment Element, completed/paid state,
immutable deal selection, website/hosting/domain-connection/revision
entitlements, prospect/intake/project creation, customer receipt, and Fritz sale
notification. Real charging remains intentionally blocked until the owner
changes the gateway from test to live after reviewing a fresh launch-gate
report.

## 2026-08-10 catalog contract

The setup script now seeds an idempotent catalog. The $199 customer-facing offer
is **Web Basics Bundle — Website Launch**; legacy SKU `FAM-FOOT-199` is retained
to protect historical references. Published items are the $199 bundle, $9.99
monthly hosting renewal, and $75 revision add-on. The initial add-on catalog now
uses provisional launch prices selected for testing and can be edited in Drupal.
Variations store entitlement keys and an intake schema identifier.

Drupal Commerce is now the financial source of truth for new purchases. The
custom order ledger remains readable for historical proof-pipeline records but
cannot start payment outside local testing. Provider-sandbox decline, 3DS,
subscription, cancellation, and refund behavior is covered by the repeatable
launch gate; live money remains separately gated.

## Provisional launch pricing (2026-08-10)

- Additional website page: $149
- Copywriting assistance: $199
- Logo and brand starter: $249
- Appointment scheduling: $149
- Lead automation: $299
- AI website agent setup: $499
- Growth Analytics: $29.99/month
- Local SEO setup: $299
- Website maintenance: $49.99/month
- Business email setup: $99
- Ecommerce discovery: $149

These are administrator-editable catalog prices and working launch assumptions,
not a promise that recurring Commerce billing is enabled. Operational terms v2
records the $199 scope, the separate $9.99 hosting renewal authorization,
domain-renewal separation, cancellation, notification categories, and postal
address. It remains explicitly marked for qualified legal review before live
recurring charges.

The matching Stripe sandbox catalog is managed by
`scripts/stripe-sandbox-catalog.sh`. The script first verifies `livemode=false`,
then idempotently creates products and one-time/monthly prices keyed by the
Drupal SKU. It never passes Stripe's `--live` flag and records non-secret product
and price IDs under `.artifacts/stripe/`.

`backend/scripts/setup-commerce-stripe-sandbox.php` creates the Drupal Commerce
Payment Element gateway from environment-only test credentials. It rejects live
key prefixes, configures Commerce mode `test`, verifies Stripe reports
`livemode=false`, and disables the gateway automatically if that proof fails.
The script defaults to a disabled gateway until checkout and webhook tests pass.

On 2026-08-10 the sandbox path was browser-proven with a real Stripe test-mode
Payment Element transaction: a $199 `FAM-FOOT-199` Commerce order reached
`completed`, its Commerce payment reached `completed`, and Stripe CLI forwarded
the signed `payment_intent`, `charge`, `customer`, and `payment_method` events to
Drupal's gateway webhook. Drupal returned HTTP 200 for every event. This is test
provider proof only; it does not authorize or activate production charges.

Drupal Commerce is the catalog, cart, promotion, checkout, and order foundation
for FAMtastic offers and upsells. On 2026-08-11 the production Commerce gateway
was explicitly promoted to Stripe live mode after the sandbox browser proof.
The live gateway is `famtastic_stripe_live`; the historical sandbox gateway is
retained but disabled. Its dedicated live webhook is
`/web/payment/notify/famtastic_stripe_live` and subscribes to the seven events
required by Commerce Stripe. Credentials remain server-owned and are never
stored in Git.

This is **production configuration proof**, not proof of a completed live
financial transaction. No real card was charged during promotion. The first
real order must therefore be monitored in Stripe and Drupal through payment,
webhook, receipt, fulfillment, and notification before live transaction proof
can be claimed.

## Product architecture

- **Service**: the primary website, application, consulting, or implementation
  purchase.
- **Add-on**: an optional upgrade or post-purchase upsell.
- Both use Commerce's default order item and digital/default order workflow.
- Commerce Promotions supplies coupons, order discounts, and Buy X Get Y rules.

Keeping Service and Add-on as separate product types makes upsell eligibility
and reporting explicit without forcing the existing pipeline order entity to
change before the Commerce checkout is proven.

## Initial setup

Install the locked dependencies, then provide the real business address and
store email through environment-owned values:

```bash
export FAMTASTIC_COMMERCE_STORE_EMAIL='store@example.com'
export FAMTASTIC_COMMERCE_STORE_ADDRESS_JSON='{"country_code":"US","address_line1":"...","locality":"...","administrative_area":"FL","postal_code":"..."}'
./setup-commerce.sh
```

The setup is idempotent. It enables the Commerce foundation, creates one online
USD store, and creates the Service and Add-on product types. It never creates a
live payment gateway or stores payment credentials in Git.

## Payment migration gate

Before sending any customer through Commerce checkout:

1. Add the stable `drupal/commerce_stripe` package compatible with Drupal 11.
2. Configure Stripe Payment Element in **test** mode.
3. Configure and verify the Commerce Stripe webhook.
4. Test successful, declined, abandoned, duplicate-webhook, refund, and zero
   dollar order behavior.
5. Map a paid Commerce order to the pipeline project/fulfillment record.
6. Obtain explicit approval before changing to live credentials or charging a
   real customer.

Steps 1–6 were completed for gateway activation on 2026-08-11. Promotion also
found and repaired missing `stripe_card` payment-method field tables after a
timestamped pre-change database backup. The live gateway verified its server
credential against a Stripe response with `livemode=true`; the sandbox gateway
was then disabled. A real live charge remains intentionally unperformed.

## Customer lifecycle catalog

`backend/scripts/setup-commerce.php` idempotently seeds:

- `FAM-FOOT-199`: $199 Foot in the Door single-page website, including one
  year of managed hosting and either first-year new-domain registration or an
  existing-domain connection.
- `FAM-HOST-999`: $9.99 monthly managed-hosting renewal after the included year.
- `FAM-ANALYTICS`: an inactive/configurable Growth Analytics add-on; publish and
  price it only after its packaging is approved.

Domain and hosting are separate entitlements. Domains remain customer-owned and
renew annually at the disclosed registrar price. Hosting is FAMtastic-managed
and may renew monthly only after explicit recurring-payment authorization.

## Production constraint

Commerce is a runtime dependency addition, so it requires a reviewed platform
migration in addition to the custom-module deployment. Production must have
enough disk headroom for Composer installation, backup creation, database
updates, and rollback artifacts before the migration starts.

The canonical backend deployment performs that migration from the exact locked
Git release. It archives the live Composer tree, installs the locked dependency
set into the runtime with rollback protection, runs database updates, enables
Commerce Stripe, and records the dependency backup in `.backend-release`. A
gateway remains disabled until test credentials
or an approved Stripe Connect authorization are supplied; installing the module
must never activate live charging.

## Branded account checkout

New direct and organic purchases enter through `/buy`. The customer must create
and verify a branded account before the API will create a Drupal Commerce order.
`POST /api/customer/checkout` accepts only published catalog SKUs, requires an
owned organization, records the new-domain/existing-domain branch, captures the
active terms version and separate marketing choice, and requires the disclosed
month-13 hosting authorization for the Web Basics Bundle. The resulting order
is the order sent to Drupal Commerce checkout; the legacy prospect order is not
created for this path.

The payment gateway stays in test mode and disabled outside a controlled proof.
Turning it on is not a launch approval. Stripe webhooks, payment outcomes,
fulfillment, notification delivery, portal visibility, and browser acceptance
must all pass before a gateway can remain enabled.

## Reusable portal website requests

A verified customer can start, save, resume, and submit any number of website
requests from **Portal → Projects & approvals**. Each request belongs to the
selected customer organization and creates its own Drupal prospect/lead. It is
not stored as one mutable “customer intake,” because one customer may own
several businesses and websites.

The pre-purchase interview captures project type, business, goals, audience,
products/services, requested features, content readiness, style, references,
timing, domain intent, and notes. Submitting it queues a customer
acknowledgement and Fritz notification. A submitted request can be handed to
`/buy?request=<public UUID>` when it is a direct Web Basics request with no
recommendation pending. Redesigns, online stores, and recommendation requests
remain in review until FAMtastic presents the appropriate scope or private
offer; they cannot silently purchase the $199 scope as if it included those
features. Checkout validates ownership, records the request
UUID in the immutable order context, and reserves that request for one Commerce
order. Completed fulfillment reuses the request's distinct lead, copies its
answers into the staff-visible intake, links the resulting project, and marks
the request converted. A second request remains independent and resumable.

The synthetic proof covers draft/save, submit, repeat requests, cross-customer
write rejection, Commerce order binding, conversion, intake transfer, and
portal visibility. No sequential request identifier is exposed to customers.
