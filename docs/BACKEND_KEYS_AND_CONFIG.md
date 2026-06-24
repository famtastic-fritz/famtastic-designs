# Backend keys and config

Status
- Local payment mode for this repo stays mocked: `NUXT_PUBLIC_PAYMENT_MODE="mock"`
- Do not commit PayPal credentials.
- Do not copy secrets from `~/famtastic/sites/site-famtastic-hosting` into this repo.
- Stripe is optional/future only unless Fritz explicitly approves it.

## Existing PayPal setup currently lives here

Canonical repo owning the existing live PayPal checkout path:
- Repo: `~/famtastic/sites/site-famtastic-hosting`
- Branch inspected: `main`

Accessible implementation path in that repo:
- `src/lib/paypal/client.ts`
- `src/pages/api/checkout/create-order.ts`
- `src/pages/api/checkout/capture-order.ts`
- `src/pages/checkout.astro`
- `db/schema.sql`
- `DEPLOY-STATE.md`

What that hosting repo currently owns
- PayPal Orders API auth + create/capture flow.
- Browser checkout button rendering.
- Server-side order snapshotting before capture.
- Capture-time amount verification and replay protection.
- Orphan-payment recovery notes and known-gap documentation.

What this FAMtastic Designs repo owns right now
- Marketing/package CTA language.
- Local proof-mode routing.
- Mock payment posture.
- Documentation for future activation.

## Suggested local-only PayPal placeholders for this repo

Use placeholders only in local env files, never committed secrets:

```env
PAYPAL_ENV="sandbox"
PAYPAL_CLIENT_ID=""
PAYPAL_CLIENT_SECRET=""
PAYPAL_WEBHOOK_ID=""
PAYPAL_BUSINESS_EMAIL=""
PAYPAL_RETURN_URL=""
PAYPAL_CANCEL_URL=""
```

## Values needed later

Required before a real PayPal activation in this repo:
- `PAYPAL_ENV` — `sandbox` or `live`
- `PAYPAL_CLIENT_ID`
- `PAYPAL_CLIENT_SECRET`
- `PAYPAL_WEBHOOK_ID`
- `PAYPAL_BUSINESS_EMAIL`
- `PAYPAL_RETURN_URL`
- `PAYPAL_CANCEL_URL`

Still needed outside PayPal itself:
- approved package-to-payment mapping
- approved audit/deposit flow
- approved care-plan/monthly billing path
- booking link
- final domain/return path decision

## What works in local mock mode

Current local proof behavior in `site-famtastic-designs`:
- site runs with `NUXT_PUBLIC_PAYMENT_MODE="mock"`
- package CTAs route into `/get-started` instead of live checkout
- payment language can advertise future PayPal paths without moving money
- lead capture continues through local JSON persistence
- no PayPal or Stripe secrets need to exist for the proof pass

## What must be verified before production

Before any production claim or activation:
1. Confirm this repo should own a real payment flow instead of delegating to `site-famtastic-hosting`.
2. Confirm PayPal is the approved primary path for this repo.
3. Confirm return URL and cancel URL on the final domain.
4. Confirm whether this repo uses PayPal checkout/payment links, invoice flow, deposit flow, care-plan recurring flow, or a mix.
5. Verify webhook ownership and reconciliation strategy.
6. Verify no secret values are exposed to the client bundle or committed files.
7. Verify end-to-end browser flow in sandbox first.
8. Verify live business email, receipts, and follow-up workflow.
9. Verify that the current live PayPal setup in `site-famtastic-hosting` remains untouched.

## Current repo note

This repo intentionally does not import or reuse the hosting repo credentials. The audit above is documentation-only and preserves the current live payment setup by leaving it where it already lives.
