# Backend keys and config

Local proof env used in this branch
- ENABLE_ADMIN_PROOF: server-side gate for /admin-proof and admin-proof API routes. Keep false by default outside local proof/dev usage.
- ADMIN_PROOF_PIN: required for protected admin-proof content saves and lead reads.
- NUXT_PUBLIC_SITE_URL: set to http://127.0.0.1:3001 for local proof.
- NUXT_PUBLIC_CMS_MODE: local for proof mode.
- NUXT_PUBLIC_LEAD_STORAGE_MODE: local so forms save into .data/famtastic-leads.json.
- NUXT_PUBLIC_PAYMENT_MODE: mock.
- NUXT_PUBLIC_PORTAL_MODE: preview.
- BOOKING_PROVIDER: mock.

Behavior
- /api/famtastic-content returns merged content using base content plus admin-proof overrides.
- /api/admin-proof/content reads/writes local proof content only when ENABLE_ADMIN_PROOF=true.
- /api/admin-proof/leads requires the proof PIN and returns local leads only when ENABLE_ADMIN_PROOF=true.
- /api/leads writes local leads when lead storage mode is local.
- /admin-proof should 404 when ENABLE_ADMIN_PROOF is not enabled.

Protected/local-only outputs
- .data/famtastic-admin-content.json
- .data/famtastic-leads.json

Do not commit
- .env
- .env.local
- .data
- .output
- .nuxt
- node_modules
- directus/database
- directus/uploads

Production architecture reminder
- /admin-proof is not the real CMS.
- Recommended production admin host: admin.famtasticdesigns.com or cms.famtasticdesigns.com.
- Recommended client portal host: portal.famtasticdesigns.com.

What still needs real production config later
- PayPal credentials and live payment URLs
- Scheduling provider URLs
- Directus URL/token and collection mapping
- Production portal auth details
- Email provider for invite and forgot/reset password flows
