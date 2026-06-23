# Local Directus proof for FAMtastic Designs

This folder provides the local backend/admin proof path for the FAMtastic Designs site.

Admin URL
- http://localhost:8055

Default local proof login
- Email: `admin@famtasticdesigns.co`
- Password: `change-this-password`

Change those values before doing anything beyond local proofing.

## Start Directus locally

From the repo root:

```bash
cd directus
docker compose up -d
```

Then open:
- http://localhost:8055

If Docker is unavailable, the website still works in local/mock mode. Use the docs in `docs/BACKEND_LOGIN_PROOF.md` to finish the setup later.

## Token setup

After logging in:
1. Open User Directory.
2. Open the admin user.
3. Generate a static token.
4. Save the user.
5. Copy the token into repo `.env` as `DIRECTUS_TOKEN="..."`.

## Modes

Local fallback mode:
- `NUXT_PUBLIC_CMS_MODE="local"`
- `NUXT_PUBLIC_LEAD_STORAGE_MODE="local"`

Directus mode:
- `NUXT_PUBLIC_CMS_MODE="directus"`
- `NUXT_PUBLIC_LEAD_STORAGE_MODE="directus"`
- `DIRECTUS_URL="http://localhost:8055"`
- `DIRECTUS_TOKEN="..."`

## Collections expected

See `directus/schema/collections.json` for the target collection/field model.
See `directus/seeds/sample-content.json` for seed-shaped sample content.

## What is wired today

- Website works without Directus.
- If Directus mode is enabled and a token is configured, lead submissions attempt to save into `leads` and fall back to local file storage if Directus fails.
- CMS content adapter path exists in `server/utils/cms.ts`; when Directus collections are populated, it can fetch services, pricing, FAQs, portfolio, testimonials, and site settings.
