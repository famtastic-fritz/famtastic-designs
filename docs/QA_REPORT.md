# QA report

Date
- 2026-06-24

Proof URLs
- Site: http://127.0.0.1:3001
- Admin proof: http://127.0.0.1:3001/admin-proof

Environment used
- ENABLE_ADMIN_PROOF=true
- ADMIN_PROOF_PIN=1234
- NUXT_PUBLIC_SITE_URL=http://127.0.0.1:3001
- NUXT_PUBLIC_CMS_MODE=local
- NUXT_PUBLIC_LEAD_STORAGE_MODE=local
- NUXT_PUBLIC_PAYMENT_MODE=mock
- NUXT_PUBLIC_PORTAL_MODE=preview
- BOOKING_PROVIDER=mock

Build + local runtime
- pnpm install: pass
- pnpm typecheck: pass
- pnpm lint: pass
- pnpm build: pass
- pnpm preview --port 3001: pass

Admin/backend distinction
- /admin-proof now clearly labels itself as Local Proof Admin — Not Production CMS.
- /admin-proof now clearly states this page is for local proofing only.
- /admin-proof now clearly states production admin should be Directus or the selected CMS backend.
- /admin-proof is removed from sitemap generation.
- /admin-proof is server-gated by ENABLE_ADMIN_PROOF and should be disabled by default outside proof/dev usage.

Content-render override proof
- Saved hero override through admin-proof content endpoint.
- Verified /api/famtastic-content returned the saved hero headline.
- Refreshed homepage and confirmed the visible H1 updated correctly.
- Saved package/pricing override through admin-proof content endpoint.
- Verified /api/famtastic-content returned the saved package price label.
- Refreshed /pricing and /packages and confirmed the visible override updated correctly.

Portal preview distinction
- /portal uses client-facing preview language only.
- /client-portal-login uses client-facing preview language only.
- Portal auth is still mocked and not represented as production-complete.
- Future invite, forgot-password, reset-password, and dashboard surfaces are documented but not yet implemented as real auth.

What remains mocked
- PayPal checkout, invoice, and recurring billing
- Booking provider integrations
- Directus live CMS / live DB writes
- Client portal production auth/integrations
