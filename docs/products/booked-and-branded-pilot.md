# Booked & Branded — $199 founding pilot

**Status:** Draft product proposal; not configured, published, purchasable, or
approved for outreach.

**Proposed product key:** `FAM-BOOKED-BRANDED-199`

**Proposed campaign profile:** `platform_independence`

**Primary audience:** Solo barbers, braiders, stylists, locticians, nail
professionals, and similar appointment businesses whose public customer path
currently depends on a marketplace or booking-platform profile.

## Product thesis

The customer already has a booking link. The problem is that the link does not
give the business a distinctive branded front door or a durable place to grow
beyond the platform.

Booked & Branded gives the operator:

- a custom mobile-first website under their business name;
- a small phone-friendly Booking Desk for daily work;
- a service menu, policies, portfolio, and directions in one owned experience;
- a simple booking-request path that does not require a full scheduler on day
  one;
- the business's own Cash App or existing payment QR displayed on the site; and
- a limited, moderated review showcase built from fresh customer submissions.

This is not a feature-for-feature Booksy replacement. It is the smallest useful
independent business system that can coexist with Booksy during transition and
grow through explicit additions.

This profile stays separate from the `first_site` campaign. A business belongs
here only when research verifies an active public appointment-platform profile,
an appointment-based operating model, and no independently hosted branded site.
The current ten-candidate review remains research material only; it must be
requalified for this profile, public contact provenance, suppression, and owner
approval before any import or proof generation.

## Why this segment is credible

Fritz reports strong anecdotal dissatisfaction among known Booksy users,
including Shay and four barbers. That is a promising founding-pilot group, not
a validated market statistic.

Booksy's official United States pricing page currently lists a $29.99 monthly
base subscription plus $20 monthly for each additional user. Its optional Boost
feature charges a one-time 30% commission on a Boost client's first visit, and
separate payment-processing rates apply. Booksy also includes materially more
than the proposed starter: full calendar tooling, reminders, marketing,
reports, staff management, payments, reviews, waitlists, memberships, and other
features. FAMtastic must compare scope honestly.

Square currently offers a no-monthly-fee solo appointments tier with processing
fees. Therefore the FAMtastic value proposition is not “the cheapest calendar.”
It is a distinctive owned brand experience, a connected customer path, and a
small operator surface that can evolve without rebuilding the website.

## Recommended founding offer

### Price recommendation

- **$199 one-time founding setup.**
- First 12 months of the site and starter Booking Desk included.
- One standard available domain for year one, or connection of one
  customer-controlled domain.
- Normal hosting renewal after month 12: **$9.99 per month**, only after
  separate recurring authorization.
- Payment-processor, messaging, premium-domain, and optional integration fees
  are paid directly by the business to its chosen providers and must be
  disclosed before purchase.

Deeper appointment scheduling remains the existing optional **$149 one-time**
setup rather than a forced renewal increase. SEO, analytics, reminders,
maintenance, business email, follow-up, and AI assistance remain optional
growth choices.

### Honest founding scarcity

Start with five founding operators: Shay plus the four barbers Fritz already
knows. Five is a real capacity and feedback boundary, not manufactured urgency.
Do not cold-launch the product until those five prove the phone workflow and
scope.

## Included in the $199 starter

1. One custom, responsive single-page website using the approved FAMtastic
   proof and revision process. Its content model, theme tokens, routes, and
   Booking Desk connection must be reusable when sections become separate
   pages or additional capabilities are purchased; the starter is not a
   disposable theme.
2. Up to 12 services with name, duration, price or “starting at” treatment,
   preparation notes, and optional customer-approved payment-QR instruction.
3. Portfolio, business story, contact, location/directions, hours, booking and
   cancellation policies, and accessibility/contact alternatives.
4. One operator and one location.
5. A mobile Booking Desk with:
   - today's requests;
   - request detail;
   - confirm, propose a change, cancel, complete, and mark no-show;
   - service visibility and basic availability settings;
   - blackout dates; and
   - payment-QR display, review, and business-profile settings.
6. A request-to-book flow. The customer asks for a service/time; the operator
   confirms it from the phone. This avoids claiming real-time availability or
   silently double-booking two calendars during the pilot.
7. One customer-supplied, customer-approved payment QR:
   - the business's Cash App QR; or
   - an existing QR from the payment provider the business already uses.
8. One booking QR placement and one payment QR placement. FAMtastic displays
   the approved QR but does not process, receive, settle, or reconcile payment.
9. Fresh post-appointment review requests, owner moderation, and up to 12
   published reviews on the starter site.
10. First-year site hosting and the existing Web Basics domain treatment.
11. One proof cycle, one included revision round, launch QA, and owner handoff.

## Explicitly outside the starter

- Multi-staff scheduling, payroll, commissions, tips, inventory, gift cards,
  memberships, packages, or full point-of-sale behavior.
- Real-time two-way Booksy synchronization.
- Automatic migration or republication of Booksy reviews.
- Automatic import of a Booksy client list without the business's authorized
  export, customer-data authority, consent mapping, and security review.
- Payment processing, card storage, payment settlement, reconciliation, or
  FAMtastic receiving money on the operator's behalf.
- Unlimited services, locations, reminders, reviews, revisions, or support.
- SMS reminders or marketing in the starter. Optional messaging requires
  consent and is paid by the business directly to its chosen provider.
- Guaranteed bookings, revenue, search placement, or savings.

## Pluggable booking strategy

The website and operator experience remain consistent while the booking engine
can advance by evidence.

### Mode A — Platform bridge

The new branded site sends booking to the operator's current Booksy, Google,
Cal.com, or other booking link. This is the lowest-risk launch and keeps the
old calendar active while the new brand experience goes live.

### Mode B — FAMtastic request-to-book

The website accepts a preferred service and time. The owner confirms or
proposes another time from the Booking Desk. This is the recommended $199
pilot mode because it is useful, phone-manageable, and does not pretend to be a
complete real-time scheduling engine.

### Mode C — Connected live calendar

A later add-on can connect a supported third-party calendar or a proven native
availability service. This must pass conflict, timezone, cancellation,
reminder, availability, and recovery testing before being sold as instant
booking.

## Payment and messaging boundary

The starter displays a customer-supplied Cash App QR or an existing QR from the
payment provider the business already uses. The payment stays directly between
the client, the business, and that provider.

- FAMtastic does not process, receive, settle, refund, or reconcile payment.
- FAMtastic does not collect card numbers, bank data, or merchant credentials.
- Payment-processing fees are paid directly by the business to its provider.
- Optional messaging fees are paid directly by the business to its provider.
- A future processing product would require a separate governed product,
  contract, implementation, proof, and customer authorization. It is not part
  of Booked & Branded.

## Technical product shape

### Drupal operational truth

- `business_booking_profile`
- `business_service`
- `business_availability_rule`
- `business_blackout`
- `appointment_request`
- `appointment_status_event`
- `business_payment_qr_display`
- `customer_review_request`
- `customer_review`

Every record is organization-scoped. Customer contact data is private. Payment
QR assets and destinations are allowlisted and revalidated. No client account
can read or change another business's calendar, contacts, services, payment QR,
or reviews.

### React owner surface

Installable phone-friendly web app, not a native-app promise:

- Today
- Requests
- Schedule
- Services
- Payment QR
- Reviews
- Business settings

The default view answers: “Who needs a response, what is next today, and what
can I finish in one tap?”

### Public branded site

- Business identity and visual world
- Service menu
- Portfolio
- Booking request
- Customer-approved payment QR display
- Policies and preparation
- Fresh approved reviews
- Location/directions and contact

## Booksy transition plan

1. **Mirror the public facts:** customer-approved services, prices, durations,
   policies, hours, portfolio, and business information.
2. **Launch bridge mode:** keep the Booksy booking path available while the
   branded site is tested.
3. **Pilot request mode:** owner handles a bounded number of FAMtastic booking
   requests from the phone.
4. **Validate:** confirm no lost requests, mobile usability, notification
   delivery, privacy, and the business-approved payment QR.
5. **Switch intentionally:** change the main booking destination only after the
   owner accepts the new workflow.
6. **Export by authority:** obtain client/appointment data through the
   operator's authorized export. Do not scrape platform data or copy reviews.

## Personalized proof package

Each prospect receives exactly three private directions:

1. **Clean chair:** fast service selection, availability/request CTA, and
   restrained editorial portfolio.
2. **Signature work:** image-led personal brand, specialty services, and
   preparation/policy flow.
3. **Neighborhood authority:** trust, directions, returning-client shortcuts,
   approved reviews, and the business's own booking/payment QR.

Each proof includes the same real functional contract. Direction differences
are visual and compositional, not fake features.

## First cold email draft

**Subject:** What if your booking page looked like your brand?

Hi {{first_name_or_there}},

I found {{business_name}} through your public {{booking_platform}} booking
profile. You already made it possible for clients to book—the opportunity is
giving them a place that looks and feels like *your* business before they choose
a service.

Your public profile puts {{source_backed_service_or_specialty}} front and
center. I used that—not a generic salon template—as the starting point for the
three directions.

I mapped a branded alternative for {{business_name}} around five decisions:
what you do, what it costs, how long it takes, how to request a time, and what a
client should know before the appointment.

The $199 Booked & Branded pilot includes:

- your own mobile-ready website;
- a phone-friendly Booking Desk;
- service and policy management;
- booking options and your own payment QR; and
- a small, owner-approved review showcase.

You can keep Booksy while you test it. Nothing needs to switch until the new
flow works for you.

Your payment stays yours. FAMtastic can display your approved Cash App or
existing payment QR, but FAMtastic does not process or receive the payment.
Payment-processing and optional messaging costs are paid by you directly to
the providers you choose.

I prepared three private directions for your brand:

**See {{business_name}}'s three directions → {{signed_preview_url}}**

— Fritz, FAMtastic Designs

The production email must retain the verified commercial footer, public-source
reason, physical address, and one-click unsubscribe. It must not claim the
recipient dislikes Booksy, overpays, loses revenue, or has agreed to migrate.

## Recommended three-email sequence

### Email 1 — The branded alternative

- Job: Earn the private-proof view.
- CTA: See your three directions.
- Send: Owner-approved batch only.

### Email 2 — What you control from your phone

- Send: Three business days after Email 1, only if not suppressed/replied.
- Show: request inbox, confirm/change status, services, the business's payment
  QR, and reviews.
- CTA: Open your private Booking Desk walkthrough.

### Email 3 — Keep Booksy while you test

- Send: Five business days after Email 2, only if not suppressed/replied.
- Explain bridge mode and the deliberate no-risk transition.
- CTA: Claim your private plan with same-email registration.
- Exit: No further cold sequence after this email. Continue only after an
  affirmative response, registration, or separate lawful nurture basis.

## Registration reward

After same-email registration, unlock a private **Booking Independence Plan**:

- recommended service hierarchy;
- request versus instant-booking recommendation;
- payment-QR display recommendation;
- customer-data and review migration cautions;
- the selected proof's extension path; and
- first 30-day transition checklist.

## Success measures

- Signed room opened
- Direction viewed/selected
- Same-email registration completed
- Booking Independence Plan opened
- Purchase started/completed
- First live booking request submitted
- Owner response time
- Confirmed/completed appointment
- Payment QR viewed or opened
- Review requested and approved

Email opens are diagnostic only. A delivered email is not a conversion.

## Acceptance before a sale

- A separate `platform_independence` campaign profile rejects businesses with
  an independent branded site and rejects any unverified platform-only claim.
- Product/deal contract exists and passes the product validator.
- Synthetic Commerce purchase creates correct entitlements and no hidden
  recurring charge.
- Owner and public surfaces work at 390px and desktop.
- Cross-account access is denied.
- Booking requests are idempotent and cannot silently disappear.
- Email notification failure stays visible and retryable.
- Payment QR resolves only to the customer's verified business-owned account.
- No payment credential is stored by Drupal and no payment is processed by
  FAMtastic.
- Review submission, consent, moderation, deletion, and abuse handling work.
- Booksy bridge and FAMtastic request modes cannot create an implied live sync.
- The first five operators complete owner acceptance before cold promotion.

## Product-factory status

- Offer definition: **drafted**
- Contract and terms: not created
- Commerce payment-processing product: not created and not part of this offer
- Fulfillment implementation: not built
- Client admin surface: not built
- Support playbook: not built
- Promotion kit: first email and sequence drafted only
- Analytics: events proposed only
- Capability evidence: current FAMtastic primitives exist; this combined
  product is **planned and unproven**
- Launch gate: not requested

## Sources

- [Booksy U.S. pricing](https://biz.booksy.com/pricing)
- [Booksy feature overview](https://biz.booksy.com/en-us/lp/how-it-works)
- [Booksy support and data export index](https://support.booksy.com/hc/en-us/categories/20662510816786-Support)
- [Google Calendar appointment schedules](https://support.google.com/calendar/answer/10729749?hl=en)
- [Cal.com pricing](https://cal.com/pricing)
- [Cal.com website embeds](https://cal.com/help/embedding/adding-embed)
