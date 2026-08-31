# Thirst Trap 772 — preorder + direct payment extension

Date: 2026-08-31
Implementation commit: `447e570c088fe6fe7ab016ab1a6c2e789ef48507`

## Outcome

The existing Thirst Trap 772 production microsite now has an eighth reusable
component: a mobile preorder request and direct-payment handoff. Visitors can
select active products, quantities, a confirmed pickup stop, and contact
details. Drupal stores the exact request and product-price snapshot before any
payment option appears.

When the owner has entered her exact Cash App payment link, the confirmation
screen renders that link as a local SVG QR and direct button. FAMtastic Designs
does not receive, hold, process, or verify funds. Every request starts as
`requested` and every payment starts as `unverified`; only the authorized owner
can record later status after checking her own Cash App account.

## Owner controls

The phone-first owner studio adds:

- numeric preorder price per product;
- explicit preorder activation;
- exact owner-generated `https://cash.app/...` payment-link validation;
- editable payment and pickup instructions;
- durable order detail with product quantities and customer pickup choice;
- manual fulfillment and payment-status controls.

Preorders are disabled by default. The public content API never exposes the
stored Cash App destination; it is returned only after a valid order has been
stored. No inventory reservation, automatic payment confirmation, outbound
order email, refund automation, or FAMtastic payment processing is claimed.

## Local proof

- Disposable Drupal update 8046 and preorder persistence: passed.
- Fail-closed disabled state, owner isolation, exact totals, and manual status:
  passed.
- Desktop and 390px mobile preorder/QR browser proof: passed.
- 390px owner order-desk browser proof: passed.
- FAMtastic pipeline unit suite: 82 tests / 417 assertions passed, with 50
  existing framework deprecations.
- Frontend production build: passed with the existing bundle-size warning.
- No payment, email, provider, customer, or production side effect occurred
  during local acceptance.

Evidence lives in `docs/evidence/thirst-trap-772-preorders-v2-1/` and the three
screenshots live under
`docs/evidence/thirst-trap-772-production-v2/screenshots/`.

## Production activation gate

Deploying the code does not activate payment or preorders. Activation still
requires the owner's verified FAMtastic account binding, real product prices,
the exact payment link created inside her Cash App, and explicit owner review.
