# Tighten Up Your Locs — backend consolidation plan

## What already exists

| Existing attempt | What it proves | Why it is not Shay’s live path |
| --- | --- | --- |
| Booked & Branded phone Booking Desk prototype | A strong mobile interaction and business-control product shape. | It is static/local prototype state, not durable customer records or an authenticated production desk. |
| `MicrositeService` / `MicrositeController` | Durable inbound captures, ownership checks, rate limiting, honeypot, owner status updates, and manual payment boundaries. | Its allowed-site list is currently intentionally limited to `thirst-trap-772`; extending it blindly would mix two client products and their records. |
| `PublicRequestController` | FAMtastic lead/quote/contact intake and proof-delivery workflow. | It creates a FAMtastic prospect/intake, not a business-owned appointment request for Shay. |
| `BookingRequestService` + `BookingRequestController` added in this build | Durable request record, UUID reference, honeypot, IP/email flood limits, public capture, admin owner inbox, and workflow states. | It is un-deployed and disabled by configuration. Its owner endpoint is staff-permission-only, not Shay’s future authenticated client workspace. |

## One path to finish

```text
Client site
  → POST /api/booking-request/tighten-up-your-locs
  → famtastic_booking_request (durable request)
  → exact owner/account authorization
  → authenticated phone Owner Desk
  → owner responds / proposes alternative / declines
  → optional approved notification and calendar action
```

## Required implementation decisions

1. **Site registration** — replace the bare enabled-key list with an exact site binding: `site_key`, owning customer account/request, selected booking mode, approved public contact destination, and enabled state. A code deployment alone must never activate capture.
2. **Owner authorization** — move the current staff-only request list/status route behind the same authenticated customer-account ownership check used by the customer portal. Do not rely on a public site key or a browser-only “owner” link.
3. **Phone Owner Desk** — build the desk in the existing branded customer portal system, not as a new static page. Show actual request states, availability-window status, and next actions; no made-up metrics.
4. **Owner response** — add a durable response/proposed-time record before sending a customer notification. “Responded” cannot mean “calendar event created.”
5. **Provider choices** — the owned request-to-book path is the first launch route. Google Calendar, Google appointment schedules, Cal.com, SMS, forwarding mailboxes, or payment QR each require their own selected provider, owner credentials, privacy review, and launch test.
6. **Measurement** — record the action source and outcome: availability view, window selection, directions tap, request start, request saved, owner response. Do not call a request a booking conversion until the owner confirms it.

## Acceptance criteria before enabling Shay’s form

- Database migration applied and exact site binding created.
- Anonymous invalid-input, honeypot, flood, disabled-site, and no-store tests pass.
- Shay’s authenticated account can view only her own requests; cross-account access is denied.
- Owner can set only valid workflow states and those writes are auditable.
- Client form is configured with the exact deployed endpoint and proves a durable request record without creating calendar, payment, or email side effects.
- Owner explicitly approves the public domain, address/map, contact destination, booking mode, privacy copy, and notification behavior.
- Desktop and phone acceptance cover Book, Contact, Find Us, request completion, owner review, and recovery states.

## Current truthful status

**Built locally:** request ledger/schema/controller, availability ledger/schema/controller, public-site form contract, phone Owner Desk shape, directions link, asset/component records.

**Not built or enabled:** production migration, site registration, customer-account authorization, authenticated Owner Desk, notification delivery, calendar connection, final domain registration, mailbox/text routing, analytics connection, or deployment.
