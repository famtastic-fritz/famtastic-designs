# FAMtastic Designs proof-mode hardening

This branch converts the AgencyOS starter into a proof-first FAMtastic Designs sandbox that runs without a live Directus backend, real Stripe keys, or the original authenticated portal stack.

## What changed

- CMS: local fallback content powers the marketing pages when Directus is offline or unset.
- Leads: `POST /api/leads` writes submissions to `.data/famtastic-leads.json` for local funnel testing.
- Payments: audit/deposit CTAs stay in mock mode until real Stripe/payment links are supplied.
- Portal: public preview pages replace the original authenticated portal dependency chain.
- SEO stability: page-level meta and a static `public/robots.txt` keep the proof build index-safe and deterministic.
- Nuxt dev fix: `package.json` now includes an `imports` mapping for `#internal/nuxt/paths`, which resolves the dev-time 500 caused by `Package import specifier "#internal/nuxt/paths" is not defined`.

## Environment defaults

Copy `.env.example` to `.env` and keep these proof-safe defaults unless you are reconnecting live services:

```env
NUXT_PUBLIC_SITE_NAME="FAMtastic Designs"
NUXT_PUBLIC_SITE_URL="http://localhost:3000"
NUXT_PUBLIC_CMS_MODE="local"
NUXT_PUBLIC_LEAD_STORAGE_MODE="local"
NUXT_PUBLIC_PAYMENT_MODE="mock"
NUXT_PUBLIC_PORTAL_MODE="preview"
DIRECTUS_URL=""
DIRECTUS_TOKEN=""
STRIPE_SECRET_KEY=""
STRIPE_PUBLISHABLE_KEY=""
STRIPE_WEBHOOK_SECRET=""
STRIPE_AUDIT_PAYMENT_LINK="https://buy.stripe.com/REPLACE_WITH_REAL_LINK"
BOOKING_LINK="https://calendly.com/REPLACE_WITH_REAL_LINK"
```

## Runbook

```bash
pnpm install
pnpm dev
pnpm build
PORT=3200 node .output/server/index.mjs
```

- Dev: `http://localhost:3000`
- Preview: `http://localhost:3200`

## Manual proof checks

1. Home page loads in dev and preview.
2. Marketing routes load: `/services`, `/pricing`, `/work`, `/contact`, `/portal`, `/client-portal-login`, `/get-started`, `/thank-you`.
3. `POST /api/leads` returns `{ ok: true }` and appends a record to `.data/famtastic-leads.json`.
4. Contact and pricing CTAs stay on placeholder-safe routes/links instead of hitting missing live services.

## Reconnecting live services later

### Directus / CMS

1. Set `DIRECTUS_URL` and `DIRECTUS_TOKEN`.
2. Switch `NUXT_PUBLIC_CMS_MODE` from `local` to the desired live mode.
3. Replace local content helpers with real Directus fetches in `server/utils/cms.ts` and related composables.
4. Re-enable dynamic content pages only after the data contract is restored.

### Leads / CRM

1. Replace the local-file writer in `server/api/leads.post.ts` with the real CRM/Directus persistence path.
2. Preserve UTM/referrer/device fields during the swap.
3. Keep `.data/famtastic-leads.json` as a local fallback or migration audit log if useful.

### Payments

1. Supply `STRIPE_SECRET_KEY`, `STRIPE_PUBLISHABLE_KEY`, and `STRIPE_WEBHOOK_SECRET`.
2. Replace placeholder CTA links with real payment/session creation.
3. Restore webhook handling only after end-to-end verification succeeds.

### Portal

1. Reintroduce authenticated portal flows only after the auth model, project data, files, invoices, and tasks are wired back up.
2. Keep the public preview route as a non-auth marketing artifact even after the real portal returns.

## Current verification snapshot

- `pnpm build`: passes.
- `pnpm dev`: serves correctly at `http://localhost:3000` after the Nuxt imports fix.
- Preview server via `PORT=3200 node .output/server/index.mjs`: serves correctly.
- Lead API proof: verified by `curl` POST and file write to `.data/famtastic-leads.json`.
