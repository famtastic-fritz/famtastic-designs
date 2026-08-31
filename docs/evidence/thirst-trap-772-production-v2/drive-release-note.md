# Thirst Trap 772 — production storefront release record

Date: 2026-08-31
Implementation commit: `0d843d41edd2997d76d6803e8a21b22188eec503`

## Outcome

Thirst Trap 772 now has a production-oriented V2 storefront built in the
business's own voice. The original gift concept remains archived under `/v1/`.
The stable showcase path is configured to lead visitors to V2 after release.

The public experience contains seven reusable component contracts: hero,
menu, brand texture, pop-up schedule, contact, social, and consented mailing
list. It uses four original owner-reference-led visual assets, native poster
graphics, custom themed social icons, expressive type, layered texture,
responsive motion, reduced-motion fallbacks, and a phone-first layout.

## Durable business controls

Drupal update 8044 adds a durable microsite and message store. A verified,
staff-bound owner account can use the protected phone-first owner studio to
edit the brand introduction, service area, Instagram and Facebook links,
products, price labels, product visibility, confirmed events, and message
statuses. Public contact requests and explicitly consented subscribers are
stored separately.

This release does not activate outbound email, payment processing, checkout,
inventory, delivery, calendar syncing, or social publishing. Those remain
separate product capabilities that require their own authority and proof.

## Routes after production release

- Public: `https://famtasticdesigns.com/showcase/thirst-trap-772/`
- Versioned public page: `https://famtasticdesigns.com/showcase/thirst-trap-772/v2/`
- Owner studio: `https://famtasticdesigns.com/showcase/thirst-trap-772/v2/owner/`
- Preserved first concept: `https://famtasticdesigns.com/showcase/thirst-trap-772/v1/`

## Acceptance evidence

- Disposable Drupal acceptance passed update 8044, content/product/event
  persistence, public visibility filtering, contact capture, subscriber
  consent and idempotence, owner isolation, anonymous denial, and public reads.
- FAMtastic pipeline unit suite passed: 82 tests and 417 assertions.
- Production frontend build passed; the pre-existing bundle-size warning remains.
- Desktop 1440×1000 and mobile 390×844 browser reviews passed with zero
  horizontal overflow and zero page errors.
- PHP lint, routing/services YAML parsing, Composer validation, Bash syntax,
  the frontend contract test, JSON validation, and Git diff checks passed.

The repository Build DNA, component decisions, generation receipt, screenshots,
and local acceptance record live under
`docs/evidence/thirst-trap-772-production-v2/`.

## Production acceptance

The governed backend and frontend lanes deployed revision
`2ab924ec3f20e795f3d5a1ee92659fab2c163ab0` on 2026-08-31. Apex and www,
the public content API, anonymous owner denial, original media, desktop and
mobile layout, correct social destinations, live contact capture, and live
consented subscriber capture passed. The two exact synthetic persistence rows
were removed immediately after verification, leaving zero test rows and
triggering no outbound message.

Production is running Drupal 11.4.5 and Entity API 1.8.0 with zero locked
Composer advisories and no pending database updates. Owner-studio handoff still
requires binding this microsite to the owner's verified FAMtastic account.
