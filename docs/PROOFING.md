# FAMtastic Designs proofing guide

Repo path
- ~/famtastic/sites/site-famtastic-designs

Required local proof env
- ADMIN_PROOF_PIN=1234
- NUXT_PUBLIC_SITE_URL=http://127.0.0.1:3001
- NUXT_PUBLIC_CMS_MODE=local
- NUXT_PUBLIC_LEAD_STORAGE_MODE=local
- NUXT_PUBLIC_PAYMENT_MODE=mock
- NUXT_PUBLIC_PORTAL_MODE=preview
- BOOKING_PROVIDER=mock

Local proof commands
- pnpm install
- pnpm typecheck
- pnpm lint
- pnpm build
- ADMIN_PROOF_PIN=1234 NUXT_PUBLIC_SITE_URL=http://127.0.0.1:3001 NUXT_PUBLIC_CMS_MODE=local NUXT_PUBLIC_LEAD_STORAGE_MODE=local NUXT_PUBLIC_PAYMENT_MODE=mock NUXT_PUBLIC_PORTAL_MODE=preview BOOKING_PROVIDER=mock pnpm preview --port 3001

Expected local URLs
- Site: http://127.0.0.1:3001
- Admin proof: http://127.0.0.1:3001/admin-proof

Proof claims that are now true
- Public content comes from the merged content API path, not just base fallback data.
- Admin-proof saved hero overrides show on homepage.
- Admin-proof saved package overrides show on /pricing and /packages.
- Lead capture can save locally in .data/famtastic-leads.json.
- Protected admin proof leads endpoint can read those saved leads with the proof PIN.

Still mocked
- Payments
- Scheduling providers
- Directus live CMS activation
- Production auth/integrations