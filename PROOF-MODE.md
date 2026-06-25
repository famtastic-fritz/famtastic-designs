# FAMtastic Designs proof-mode hardening

This branch keeps FAMtastic Designs local-proof friendly.

What is active now
- Local content fallback from data/famtastic/* through data/famtastic/content.ts
- Local admin override proof through /admin-proof
- Local lead persistence to .data/famtastic-leads.json
- Mock payment mode with PayPal-first future documentation
- Provider-abstracted booking mode with safe fallbacks to /get-started
- Starter SEO/legal/cookie surfaces

What is not active now
- Live PayPal checkout
- Live Stripe checkout
- Live production Directus requirement
- Real authenticated client portal
- Production analytics/search verification

Proof-safe defaults
- NUXT_PUBLIC_CMS_MODE=local
- NUXT_PUBLIC_LEAD_STORAGE_MODE=local
- NUXT_PUBLIC_PAYMENT_MODE=mock
- NUXT_PUBLIC_PORTAL_MODE=preview
- BOOKING_PROVIDER=mock
- ADMIN_PROOF_PIN stays local only

Runtime data paths
- .data/famtastic-leads.json
- .data/famtastic-admin-content.json
- Start preview/dev from the repo root so INIT_CWD keeps proof data in project .data.

Runbook
- pnpm install
- pnpm typecheck
- pnpm lint
- pnpm build
- pnpm preview

Owner review before production
- Approve package ladder, pricing language, service copy, legal starter text, booking lane, payment lane, and any demo/proof labels.
