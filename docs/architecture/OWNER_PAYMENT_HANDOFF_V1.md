# Owner payment handoff v1

## Purpose and boundary

`PaymentHandoff` is a reusable, organization-scoped way for a Starter site or
authenticated Owner Desk to show the business owner's existing payment route.
It is not a FAMtastic checkout, Commerce payment method, merchant account, or
payment-verification system.

FAMtastic never stores provider credentials, card or bank details, payment
provider responses, payment status, settlement status, or fulfillment status in
this capability. A configured destination is only a handoff to the business's
own provider.

## Configuration

One row belongs to one active `famtastic_organization`. Only an active
membership with role `owner` can read or replace it.

| Mode | Required field | Optional field | Meaning |
| --- | --- | --- | --- |
| `disabled` | none | none | Public model is absent. Existing destinations are cleared. |
| `cash_app` | `destination_url` on exact host `cash.app` | label, instructions | Direct business-owned Cash App destination. |
| `payment_link` | public HTTPS `destination_url` | label, instructions | Generic business-owned payment destination. |
| `qr` | public HTTPS `qr_image_url` | public HTTPS `destination_url`, label, instructions | Existing owner-controlled QR image, with an optional accessible link fallback. |

All supplied URLs normalize ordinary scheme-less public input to HTTPS and
reject credentials, non-HTTPS schemes, localhost, and IP-address destinations.
The module does not fetch, create, alter, or inspect those external URLs.

## API contract

| Surface | Route | Contract |
| --- | --- | --- |
| Owner read | `GET /api/customer/payment-handoff?organization=<organization-public-id>` | Signed-in, verified owner only. An absent row returns a private disabled model. |
| Owner replace | `PUT /api/customer/payment-handoff` | Signed-in, verified owner only, with CSRF. Body includes `organization`, `mode`, and the fields for that mode. |
| Public read | `GET /api/payment-handoff/<organization-public-id>/<site-key>` | Requires the site's existing active converted-request → booking-site → organization binding, then returns only an enabled public-safe model. An absent, disabled, or cross-organization binding is `404 payment_handoff_unavailable`. |
| Interaction event | `POST /api/payment-handoff/<organization-public-id>/<site-key>/events` | Requires that same binding. Accepts only `{event: "viewed"|"opened", surface: "starter"|"owner_desk"}` and appends an anonymous interaction record. |

The public model carries the explicit disclosure: it does not confirm payment,
create an order, or reserve a service. A consumer calls `viewed` after rendering
the handoff and `opened` immediately before opening its destination; a QR scan
outside the site cannot be observed and must never be inferred.

The `opened` event means only that a handoff action was requested. Neither event
is a purchase, payment attempt, receipt, payment confirmation, appointment, or
fulfillment signal.

## Composition boundary

This capability has no site-key algorithm and no link to Commerce. Public
rendering and interaction require the existing converted-request / booking-site
ownership binding to match the organization exactly, but that binding remains
responsible for site authorization and carries its own stable key contract.

## Local acceptance

`scripts/e2e-payment-handoff.sh` installs a disposable SQLite Drupal site,
rehearses update `8055`, and exercises the real HTTP routes. It proves owner
isolation, public absence until owner configuration, Cash App/generic-link/QR
validation, disabled clearing, and viewed/opened-not-purchase events. It makes
no provider call, charge, account creation, verification, delivery, or deploy.
