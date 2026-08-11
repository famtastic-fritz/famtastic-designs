# FAMtastic product onboarding pipeline

The canonical product contract is `backend/config/famtastic-products.json`.
It is the reviewable source for Commerce catalog setup, fulfillment, portal
access, customer communications, upsells, reporting, and acceptance coverage.

## Required definition areas

Every product must define all twelve areas before it can pass validation:

1. Identity: name, SKU, type, summary, price, and publication state.
2. Billing: one-time/recurring behavior, interval, included period, and renewal.
3. Eligibility: direct, organic, campaign, returning, or entitled customers.
4. Entitlements: exact capabilities granted after verified payment.
5. Intake: the versioned questionnaire/schema key.
6. Fulfillment: project template, milestones, and completion path.
7. Communications: receipt, staff alert, reminders, failures, and cancellation.
8. Portal: services, purchases, documentation, support, and billing controls.
9. Upsells: valid related SKUs, never an unfiltered catalog wall.
10. Reporting: revenue, acquisition, engagement, conversion, renewal, or churn.
11. Acceptance: success, failure, authorization, security, and mobile proofs.
12. Launch control: sandbox acceptance before publication or live activation.

The companion `backend/config/famtastic-deal-terms.json` is also mandatory. It
holds the exact customer promise, deliverables, exclusions, ownership boundary,
cancellation/refund treatment, renewal disclosure, and required consent keys for
every SKU. A SKU without exactly one matching deal definition fails closed.

Run `php scripts/validate-product-pipeline.php` before catalog synchronization.
Then run `drush php:script scripts/setup-commerce.php` from `backend/`. Both are
idempotent. Unknown upsells, renewal SKUs, malformed prices, duplicate SKUs, or
missing required lifecycle definitions fail closed.

## Launch website bundles

- `FAM-FOOT-199` is the Web Basics Bundle: one focused page, one included
  revision round, domain choice, and one included year of basic hosting;
  continued hosting is separately authorized through `FAM-HOST-999` at $9.99
  monthly.
- `FAM-BUSINESS-499` is the Business Website Bundle: up to five focused pages,
  lead capture, foundational SEO, analytics connection, two revision rounds,
  domain choice, and one included year of business hosting; continued hosting
  is separately authorized through `FAM-HOST-BUSINESS-1999` at $19.99 monthly.
- Ecommerce, membership, custom integrations, regulated requirements, and
  requests beyond five pages do not silently fall into either bundle. They are
  held for scope review and may lead to a custom product or approved private
  offer.
- Private prices are one-request, one-account records. Both list and offered
  amounts are snapshotted into the Commerce order; the base product scope and
  renewal price do not silently change with the initial discount.

## Commerce fulfillment contract

A completed Drupal Commerce order is the only trigger for new financial
fulfillment. `CommerceLifecycleService`:

- requires an authenticated Drupal customer;
- creates/reuses one customer and organization;
- records the Commerce billing profile bridge;
- snapshots every purchased SKU, product definition, customer deal, policy,
  checksum, and total;
- grants only entitlements listed by those SKU definitions;
- adds the Commerce order to portal Purchases;
- adds granted capabilities to portal Services;
- queues the customer receipt and Fritz sale alert idempotently;
- maps failed payments to attention state; and
- suspends order entitlements after refund or void.

The legacy custom-order flow remains readable for historic orders. New Commerce
orders are tagged separately as `commerce_order` resources so the portal can
show both during migration without creating a second financial truth.

## Support, mail, and worker contract

Portal support creates a case number, category, priority, owner, response target,
thread, customer acknowledgment, and Fritz alert. Replies use
`support+<thread-uuid>@famtasticdesigns.com`. The signed mail-pipe endpoint:

- validates the shared-secret HMAC;
- requires an authorized organization sender;
- deduplicates Message-ID;
- limits message and attachment sizes;
- permits only PNG, JPEG, WebP, PDF, and plain text;
- verifies decoded bytes, SHA-256, and detected MIME type;
- stores accepted attachments under Drupal private storage; and
- alerts Fritz when a reply cannot be matched.

The lifecycle worker dispatches the notification outbox with bounded retries and
dead-letter state. It monitors overdue support cases, lead follow-ups, stale
projects, upcoming renewals, late worker heartbeats, and daily delivery
exceptions. Transactional notifications are independent of promotional consent.

On cPanel, mail is stored in the `support` Maildir, including plus-addressed
case subfolders. The scheduled `bin/process-support-maildir.sh` worker imports a
bounded batch through the same signed endpoint and moves only accepted messages
from `new/` to `cur/`. Message-ID hashing keeps retries idempotent.

## Safety and activation

All automated acceptance runs use memory email, local Commerce records, fixture
DNS, isolated deployment paths, and Stripe sandbox/test mode. Product
publication, live charges, automatic renewals, and real domain purchases remain
separate owner-controlled launch gates.

Run the complete local contract with:

```bash
scripts/run-customer-proof-agent.sh
```

Evidence is written under `.artifacts/proof-runs/` and
`.artifacts/lifecycle-runs/`. A run passes only when every JSON check is true.
