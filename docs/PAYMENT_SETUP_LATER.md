# Payment setup later

Status for this production-proof pass
- Keep payment mode mocked locally.
- Do not deploy.
- Do not push.
- Do not commit PayPal credentials.
- Do not copy secrets from `site-famtastic-hosting` into this repo.
- Do not break the current live PayPal setup.

## Existing PayPal integration path

Audited source of truth:
- Repo: `~/famtastic/sites/site-famtastic-hosting`
- Current branch inspected: `main`

Relevant files:
- `src/lib/paypal/client.ts`
- `src/pages/api/checkout/create-order.ts`
- `src/pages/api/checkout/capture-order.ts`
- `src/pages/checkout.astro`
- `db/schema.sql`
- `DEPLOY-STATE.md`

Observed implementation summary:
- Uses PayPal Orders API v2.
- Supports `sandbox` and `live` via `PAYPAL_ENV`.
- Creates checkout snapshots before capture.
- Verifies capture amount against stored subtotal.
- Tracks known gap: webhook reconciliation is not yet implemented there.

## What this repo needs later

Local-only placeholder env values:

```env
PAYPAL_ENV="sandbox"
PAYPAL_CLIENT_ID=""
PAYPAL_CLIENT_SECRET=""
PAYPAL_WEBHOOK_ID=""
PAYPAL_BUSINESS_EMAIL=""
PAYPAL_RETURN_URL=""
PAYPAL_CANCEL_URL=""
```

Decision checklist before real activation here:
- Which package uses PayPal checkout/payment link?
- Which package uses PayPal invoice/payment flow?
- Which offer uses audit/deposit payment?
- Which care plan uses the monthly payment path?
- Does this repo own the payment logic, or should it hand off to another repo/page?
- Who owns reconciliation and webhook handling?

## Current local mock behavior

What works now:
- package CTAs route to `/get-started`
- pricing/home pages document future PayPal paths without taking payment
- lead form still works locally
- no live credentials are required

What does not work yet by design:
- live PayPal checkout in this repo
- PayPal invoice automation in this repo
- recurring care-plan billing in this repo
- webhook verification in this repo
- production reconciliation in this repo

## Verification required before production

1. Confirm the final payment architecture and ownership.
2. Confirm approved package CTA mapping to each payment path.
3. Add real PayPal values locally only.
4. Verify sandbox checkout or invoice flow in-browser.
5. Verify return and cancel URLs on the real domain.
6. Verify receipts/follow-up process.
7. Verify no secrets are committed or copied from the hosting repo.
8. Verify the existing live hosting checkout still works unchanged.
9. Only after all of that, discuss production activation.

## Stripe note

Stripe stays optional/future only. Do not treat it as the default payment path for FAMtastic Designs unless Fritz explicitly approves it.
