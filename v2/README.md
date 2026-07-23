# FAMtastic Designs — v2 (Phase 1 Evaluation Scaffold)

A headless CMS monorepo: **Drupal 11** serves content over **JSON:API**, and a **React 18 + Vite** single-page application consumes it. This repository is a Phase 1 evaluation scaffold — it proves the end-to-end architecture (content modeling in Drupal, anonymous API delivery, SPA rendering, custom dark admin theme) before any hardening, auth, or deployment work begins.

| Concern | Value |
| --- | --- |
| Backend | Drupal 11 (headless), docroot at `backend/web` |
| Backend URL | `http://localhost:8080` |
| JSON:API base | `http://localhost:8080/jsonapi` |
| Frontend | React 18 + Vite SPA |
| Frontend dev URL | `http://localhost:5173` |
| Admin theme | `FAMtastic Admin` (machine name `famtastic_admin`, base theme `claro`) |
| API access | Anonymous read access only (no OAuth in Phase 1) |

---

## Directory map

```
v2/
├── README.md                          ← you are here
├── .gitignore
│
├── backend/                           # Drupal 11 headless CMS
│   ├── composer.json                  # Drupal 11 project + module deps (drush, jsonapi_extras, consumers, admin_toolbar)
│   ├── setup.sh                       # Post-install bootstrap: enables modules, sets admin theme
│   ├── config/
│   │   └── sync/                      # Exported Drupal configuration (drush cex target)
│   └── web/                           # Drupal docroot (web server document root)
│       ├── sites/default/settings.php # Trusted hosts, sync dir, container_yamls registration
│       ├── sites/default/services.yml # CORS (cors.config → http://localhost:5173)
│       └── themes/custom/famtastic_admin/    # Custom dark admin theme (base theme: claro)
│           ├── famtastic_admin.info.yml
│           ├── famtastic_admin.libraries.yml
│           ├── famtastic_admin.theme
│           ├── css/famtastic-admin.css
│           └── logo.svg
│
└── frontend/                          # React 18 + Vite SPA
    ├── package.json
    ├── vite.config.js                 # Dev server :5173 + /jsonapi proxy fallback
    ├── index.html
    ├── .env.example                   # VITE_DRUPAL_BASE_URL template (copy to .env)
    ├── .env                           # Local env (git-ignored)
    └── src/
        ├── main.jsx                   # React root mount
        ├── App.jsx                    # Router shell
        ├── index.css                  # Global styles
        ├── api/
        │   └── drupal.js              # JSON:API fetch client (base URL, serializers)
        ├── components/
        │   ├── Layout.jsx             # Page shell
        │   ├── Header.jsx             # Site header / nav
        │   ├── NodeList.jsx           # Article list from JSON:API
        │   └── NodeView.jsx           # Single article view
        └── pages/
            ├── HomePage.jsx           # Landing page (NodeList)
            └── ContentPage.jsx        # Article detail page (NodeView)
```

---

## Prerequisites

| Tool | Version | Check |
| --- | --- | --- |
| PHP | **8.3+** (CLI, with `gd`, `pdo_mysql`/`pdo_sqlite`, `mbstring`, `xml`) | `php -v` |
| Composer | **2.x** | `composer --version` |
| Node.js | **18+** (npm 9+) | `node -v` |
| Database | SQLite (quickest for evaluation) or MySQL/MariaDB 10.6+ | — |

> **DDEV alternative:** if you use DDEV, point the project docroot at `backend/web` and skip the `php -S` step — DDEV will serve the backend on its own URL. Adjust `VITE_DRUPAL_BASE_URL` and `cors.config` in `backend/web/sites/default/services.yml` accordingly (then `drush cr`).

---

## Backend setup

All commands run from `v2/backend/` unless noted.

1. **Install PHP dependencies** (Drupal core + contrib: `drupal/jsonapi_extras`, `drupal/consumers`, `drupal/admin_toolbar`, `drush/drush`):

   ```bash
   cd backend
   composer install
   ```

2. **Serve the docroot.** The Drupal docroot is `backend/web`:

   ```bash
   php -S localhost:8080 -t web web/.ht.router.php
   ```

   (Or configure nginx/Apache/DDEV with document root `backend/web`.)

3. **Install Drupal** (standard profile is fine for evaluation; SQLite keeps it zero-config):

   ```bash
   vendor/bin/drush site:install standard \
     --db-url=sqlite://sites/default/files/.ht.sqlite \
     --site-name="FAMtastic Designs" \
     --account-name=admin --account-pass=admin -y
   ```

4. **Run the scaffold bootstrap script.** `backend/setup.sh` enables the required modules and sets the custom admin theme:

   ```bash
   ./setup.sh
   ```

   It performs the equivalent of:

   ```bash
   vendor/bin/drush en -y jsonapi serialization rest jsonapi_extras consumers admin_toolbar
   vendor/bin/drush config:set -y system.theme admin famtastic_admin
   vendor/bin/drush cache:rebuild
   ```

5. **Create demo content.** Log in at `http://localhost:8080/user` (admin/admin if you used the command above) and create a few **Article** nodes (`/node/add/article`) so the SPA has something to render. Anonymous users must be able to read published content for JSON:API to return it — the standard profile grants this via the "access content" permission.

---

## Frontend setup

All commands run from `v2/frontend/`.

```bash
cd frontend
cp .env.example .env     # sets VITE_DRUPAL_BASE_URL=http://localhost:8080
npm install
npm run dev              # Vite dev server on http://localhost:5173
```

Open `http://localhost:5173` — the home page lists Drupal articles; clicking one loads the detail page. The React app fetches from the JSON:API base URL defined in `.env` via `src/api/drupal.js`.

---

## JSON:API endpoints

Once modules are enabled, Drupal exposes its content model automatically — no custom code required:

| Endpoint | Purpose |
| --- | --- |
| `http://localhost:8080/jsonapi` | Entry point / resource index |
| `http://localhost:8080/jsonapi/node/article` | Article collection (list) |
| `http://localhost:8080/jsonapi/node/article/{uuid}` | Single article |
| `http://localhost:8080/jsonapi/node/article/{uuid}?include=field_image,uid` | Article with related image + author |
| `http://localhost:8080/jsonapi/taxonomy_term/tags` | Example taxonomy collection |

Notes:

- JSON:API addresses entities by **UUID**, not numeric node ID. `src/api/drupal.js` handles the numeric-ID → UUID dance where needed.
- Collection responses are paginated (`links.next`) — default page size is 50.
- `jsonapi_extras` is available to customize resource names/fields if the evaluation requires it (no customizations are enabled by default in Phase 1).
- Access is **anonymous read-only**: unauthenticated `GET` requests work for published content; write operations and unpublished content require authentication.

---

## Custom admin theme: FAMtastic Admin

The backend ships a custom admin theme — **FAMtastic Admin** (`web/themes/custom/famtastic_admin/`) — a premium dark theme extending core `claro` with a lime accent.

### Enable / verify

`setup.sh` already sets it as the admin theme. To verify or set it manually:

- **UI:** `Appearance` (`/admin/appearance`) — confirm `famtastic_admin` is installed, then at `/admin/appearance/settings` set **Administration theme** to *FAMtastic Admin* (and ensure "Use the administration theme when editing or creating content" is checked).
- **Drush:**

  ```bash
  vendor/bin/drush theme:enable -y famtastic_admin
  vendor/bin/drush config:set -y system.theme admin famtastic_admin
  vendor/bin/drush cache:rebuild
  ```

- **Check:** any `/admin/*` page should render dark surfaces with lime hover/focus accents on toolbar icons, admin menu items, and form elements.

### Brand color spec

| Token | Hex | Usage |
| --- | --- | --- |
| `--fam-bg` | `#0a0a0a` | Page / admin background |
| `--fam-surface` | `#141414` | Cards, toolbar, raised surfaces |
| `--fam-border` | `#2a2a2a` | Borders, dividers, input outlines |
| `--fam-lime` | `#7CFC00` | Accent — hover/focus on toolbar icons, admin menu items, form controls, primary buttons, links |
| `--fam-text` | `#ffffff` | Primary text |
| `--fam-text-muted` | `#888888` | Secondary text, descriptions, placeholders |

---

## CORS & Vite proxy

Two complementary mechanisms let the SPA at `:5173` talk to Drupal at `:8080`:

1. **CORS (primary).** `backend/web/sites/default/services.yml` sets Drupal's `cors.config` container parameter, allowing cross-origin requests from the Vite dev server:

   ```yaml
   parameters:
     cors.config:
       enabled: true
       allowedHeaders: ['Content-Type', 'Authorization', 'X-CSRF-Token', 'Accept', 'X-Requested-With']
       allowedMethods: ['GET', 'POST', 'PATCH', 'DELETE', 'OPTIONS']
       allowedOrigins: ['http://localhost:5173']
       supportsCredentials: true
   ```

   > **Drupal 11 gotcha:** `$settings['cors.config']` in `settings.php` is a
   > no-op. The parameter only takes effect from `sites/default/services.yml`,
   > which must be registered via `$settings['container_yamls'][]` in
   > `settings.php` (already wired). Run `vendor/bin/drush cr` after edits.

2. **Vite proxy (fallback).** `frontend/vite.config.js` proxies `/jsonapi` to `http://localhost:8080`, so if CORS is ever misconfigured the app can fall back to same-origin requests by pointing `VITE_DRUPAL_BASE_URL` at the dev server itself (or leaving it empty so `drupal.js` uses relative `/jsonapi` paths).

If you change either port, update **both** places.

---

## Phase 1 scope — explicitly NOT included

This scaffold intentionally omits:

- **No OAuth / token auth** — anonymous JSON:API read access only (`consumers` is installed but not configured).
- **No deployment or CI configuration** — no Docker, pipelines, hosting, or environment promotion.
- **No automated tests** — no PHPUnit, Behat, Vitest, or Cypress suites.
- **No content migration** — demo content is created manually.
- **No custom JSON:API resource overrides** — stock core resource naming (`node--article`) is in effect; `jsonapi_extras` customizations are out of scope for Phase 1.
- **No production hardening** — default `admin/admin` credentials and permissive local settings are for local evaluation only.

---

## Phase 2 deployment (scaffold)

> **Not yet tested.** The files below are a Phase 2 scaffold — no images have been built and nothing has been pushed to a platform. The local flow from the sections above (`php -S :8080` + SQLite, `vite dev :5173`) is unchanged and remains the source of truth.

New files:

```
v2/
├── docker-compose.yml               # backend (:8080) + frontend (:5173) + MariaDB 10.11
├── .platform.app.yaml               # Platform.sh app (source.root: backend, PHP 8.3, composer flavor)
├── .platform/
│   ├── services.yaml                # mariadb:10.11 service
│   └── routes.yaml                  # {default} → backend:http
├── backend/
│   ├── Dockerfile                   # composer stage → php:8.3-fpm-alpine + nginx, non-root www-data
│   ├── .dockerignore
│   └── docker/
│       ├── nginx.conf               # Drupal vhost: clean URLs, /sites/default/files → index.php fallback
│       ├── php-fpm.conf             # www pool on /run/php-fpm/php-fpm.sock, clear_env=no
│       └── entrypoint.sh            # php-fpm (background) + nginx (foreground)
└── frontend/
    ├── Dockerfile                   # node:20-alpine build (npm ci + vite build) → nginx:alpine
    ├── .dockerignore
    └── docker/
        └── nginx.conf               # SPA fallback + /jsonapi & /oauth proxy → backend:80
```

`backend/web/sites/default/settings.php` gained a small bridge: `DB_*` env vars (Compose) or `PLATFORM_RELATIONSHIPS` (Platform.sh) override the local SQLite default when present.

### Docker Compose

```bash
cd v2
docker compose up --build
```

- Backend: `http://localhost:8080` (nginx + PHP-FPM 8.3 against the `db` MariaDB service, named volume `db_data`; public/private files on named volumes).
- Frontend: `http://localhost:5173` (static Vite build; same-origin `/jsonapi` + `/oauth` proxied to the `backend` service — no CORS or `VITE_DRUPAL_BASE_URL` needed inside Compose).
- First run: install Drupal against MariaDB, then import config:

  ```bash
  docker compose exec backend vendor/bin/drush site:install standard \
    --site-name="FAMtastic Designs" --account-name=admin --account-pass=admin -y
  docker compose exec backend vendor/bin/drush -y config:import cache:rebuild
  ```

### Platform.sh

```bash
cd v2
platform project:set-remote <project-id>
platform push
```

- `.platform.app.yaml` builds `backend/` (composer flavor, PHP 8.3), serves `web/`, mounts `sites/default/files` + `private`, and runs `updatedb` + `config:import` on deploy. DB credentials arrive via `PLATFORM_RELATIONSHIPS` (bridged in `settings.php`); cron runs `drush cron` every 20 min.
- The React SPA is deployed separately (static hosting/CDN) with `VITE_DRUPAL_BASE_URL` pointing at the Platform.sh origin — add that origin to `allowedOrigins` in `backend/web/sites/default/services.yml` (then `drush cr`).
