# Backend login proof

Admin URL
- `http://localhost:8055`

How to start Directus locally
```bash
cd ~/famtastic/sites/site-famtastic-designs/directus
docker compose up -d
```

How to log in
- Email: `admin@famtasticdesigns.co`
- Password: `change-this-password`

If the first-run screen appears instead, create the first admin account there and keep the values in your local `.env` aligned.

How to generate a token
1. Log in to Directus.
2. Open User Directory.
3. Open the admin user.
4. Generate a static token.
5. Save the user.
6. Copy that token into `.env` as `DIRECTUS_TOKEN="..."`.

Where the token goes
- Repo root `.env`
- `DIRECTUS_TOKEN="..."`

How to switch to Directus mode
```env
NUXT_PUBLIC_CMS_MODE="directus"
NUXT_PUBLIC_LEAD_STORAGE_MODE="directus"
DIRECTUS_URL="http://localhost:8055"
DIRECTUS_TOKEN="paste-token-here"
```

How to confirm content reads from Directus
1. Populate the collections listed in `directus/schema/collections.json`.
2. Start the site with Directus mode enabled.
3. Hit `/api/famtastic-content` and confirm the payload reflects Directus values.
4. Refresh the site and confirm content changes render.

How to confirm leads save to Directus
1. Enable `NUXT_PUBLIC_LEAD_STORAGE_MODE="directus"`.
2. Ensure `DIRECTUS_TOKEN` has write access to `leads`.
3. Submit `/get-started`.
4. Confirm the new lead appears in Directus > leads.

What to do if Docker is unavailable
- Keep `NUXT_PUBLIC_CMS_MODE="local"` and `NUXT_PUBLIC_LEAD_STORAGE_MODE="local"`.
- The site remains fully usable in proof mode.
- Bring Directus online later using the files in `directus/`.
- If Docker reports `Cannot connect to the Docker daemon at unix:///Users/famtasticfritz/.docker/run/docker.sock`, the fix is to start Docker Desktop first.
