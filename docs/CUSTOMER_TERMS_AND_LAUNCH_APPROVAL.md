# Customer terms and launch approval

Status: **provisional business policy — not attorney-approved**

The canonical machine-readable promises are in
`backend/config/famtastic-deal-terms.json`. Every SKU has its own promise,
deliverables, exclusions, cancellation rule, refund rule, required consents, and
recurring disclosure when applicable. Commerce fulfillment stores a checksum
and the resolved deal snapshot so later edits cannot rewrite an older order.

## Web Basics Bundle decision record

- Price: $199 one time.
- Scope: one custom, focused single-page or landing-page website.
- Hosting: twelve months of basic managed hosting included from launch.
- New-domain path: first-year registration of one standard available domain is
  included. Premium, aftermarket, brokered, and unusually priced domains require
  a separate quote and approval.
- Existing-domain path: connect one customer-controlled domain; no FAMtastic
  domain-renewal charge is created.
- Domain ownership: the customer owns the domain.
- Hosting infrastructure: FAMtastic owns/manages the hosting environment and may
  present the service under FAMtastic branding or white label.
- Month 13 hosting: $9.99 monthly, activated only after separate affirmative
  authorization showing amount, interval, start date, and cancellation method.
- Year 2 domain: annual renewal is due prepaid. The actual registrar price is
  disclosed before payment because registry pricing varies. It is separate from
  hosting.
- Response target: normally within three business days; not a guaranteed
  resolution or completion time.

## Provisional cancellation and refund policy

- Before work begins, the FAMtastic service price is refundable.
- Once work begins, completed work may be deducted from an approved refund.
- Completed milestones, delivered digital work, registered domains, and
  irreversible third-party costs are nonrefundable.
- Recurring services may be cancelled before the next billing date. Cancellation
  stops future charges; paid service continues through the current period and is
  not prorated.
- Payment failures receive notices, bounded retries, and a grace workflow before
  suspension.

This language is operational and understandable, but it is not a substitute for
advice from a Florida attorney, particularly for refund rights, automatic
renewals, taxes, privacy, accessibility, AI services, and interstate sales.

## Checkout evidence required

- Customer and organization identity.
- Order, SKU, quantity, and exact price.
- Product/deal scope version and full snapshot checksum.
- Active terms version, exact body, and checksum.
- Acceptance timestamp, IP hash, and user-agent hash.
- Selected new-domain or existing-domain path.
- Recurring amount, interval, start date, and cancellation method when relevant.
- Promotional choice separately from transactional notices.
- Stripe customer, Checkout Session, Payment Intent, and subscription references
  without storing card data.

## Final owner approval sheet

- [ ] I approve the name, price, scope, deliverables, and exclusions for every
  published SKU.
- [ ] I approve the $199 Web Basics promise and revision allowance.
- [ ] I approve $9.99/month hosting beginning after the included year.
- [ ] I approve annual prepaid domain renewal at the separately disclosed price.
- [ ] I approve the three-business-day response target.
- [ ] I approve cancellation, refund, failure, grace, and suspension rules.
- [ ] I approve customer ownership and managed-hosting language.
- [ ] I approve transactional-email and optional-marketing language.
- [ ] Stripe test success, decline, 3DS, abandonment, duplicate webhook, refund,
  cancellation, and recurring-invoice scenarios have evidence.
- [ ] A qualified attorney reviewed the final wording, or I documented the
  business decision to launch without that review.
- [ ] I explicitly authorize the production gateway and public checkout.

Record the owner name, catalog checksum, terms checksum, approval time,
environment, notes, and signature or affirmation method.

The same checklist is available in Drupal at
`/admin/famtastic/launch-approval`. Saving it records the authenticated staff
user, timestamp, catalog/deal checksum, and active terms checksum. It never
turns on production billing automatically.

Run `scripts/stripe-sandbox-billing-acceptance.sh` for named provider evidence.
The script refuses live mode and proves $199 success, card decline, required
3DS, the $9.99 monthly subscription, and customer cancellation.
