# FAMtastic Designs proofing guide

Repo path
- ~/famtastic/sites/site-famtastic-designs

Required local proof env
- ENABLE_ADMIN_PROOF=true
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
- ENABLE_ADMIN_PROOF=true ADMIN_PROOF_PIN=1234 NUXT_PUBLIC_SITE_URL=http://127.0.0.1:3001 NUXT_PUBLIC_CMS_MODE=local NUXT_PUBLIC_LEAD_STORAGE_MODE=local NUXT_PUBLIC_PAYMENT_MODE=mock NUXT_PUBLIC_PORTAL_MODE=preview BOOKING_PROVIDER=mock pnpm preview --port 3001

Expected local URLs
- Site: http://127.0.0.1:3001
- Admin proof: http://127.0.0.1:3001/admin-proof

Admin distinction
- /admin-proof is local proof only.
- It is not the production CMS.
- Production admin should be Directus or the selected CMS backend.
- /admin-proof should stay disabled unless ENABLE_ADMIN_PROOF=true.

Proof claims that are true
- Public content comes from the merged content API path, not just base fallback data.
- Admin-proof saved hero overrides show on homepage.
- Admin-proof saved package overrides show on /pricing and /packages.
- Lead capture can save locally in .data/famtastic-leads.json.
- Protected admin proof leads endpoint can read those saved leads with the proof PIN.

Still mocked
- Payments
- Scheduling providers
- Directus live CMS activation
- Client portal auth/integrations
