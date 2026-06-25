# Backend keys and config

Local proof env used in this branch
- ADMIN_PROOF_PIN: required for protected admin-proof content saves and lead reads.
- NUXT_PUBLIC_SITE_URL: set to http://127.0.0.1:3001 for local proof.
- NUXT_PUBLIC_CMS_MODE: local for proof mode.
- NUXT_PUBLIC_LEAD_STORAGE_MODE: local so forms save into .data/famtastic-leads.json.
- NUXT_PUBLIC_PAYMENT_MODE: mock.
- NUXT_PUBLIC_PORTAL_MODE: preview.
- BOOKING_PROVIDER: mock.

Behavior
- /api/famtastic-content returns merged content using base content plus admin-proof overrides.
- /api/admin-proof/content reads/writes local proof content.
- /api/admin-proof/leads requires the proof PIN and returns local leads.
- /api/leads writes local leads when lead storage mode is local.

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

What still needs real production config later
- PayPal credentials and live payment URLs
- Scheduling provider URLs
- Directus URL/token and collection mapping
- Production portal auth details