# FAMtastic Designs v2 — Phase 2 Plan

## Phase 2: Authentication + Content Architecture + Deployment

Phase 1 (LIVE) delivered: Drupal 11.4 headless backend (JSON:API, SQLite at an
absolute path, `famtastic_admin` dark admin theme) + React 18/Vite SPA with an
anonymous JSON:API client (`frontend/src/api/drupal.js`). Phase 2 adds OAuth2
auth, three custom content types, role-based permissions, and deployment
configs. All steps below reference the actual scaffold files of this phase:

- `backend/phase2-setup.sh` — idempotent drush bootstrap (this plan's executor)
- `backend/config/phase2/*.yml` — content-type/field config, imported via
  `drush config:import --partial --source=<abs path>` (same proven pattern
  `backend/setup.sh` uses for core recipe config)
- `frontend/src/context/UserContext.jsx`, `frontend/src/components/ProtectedRoute.jsx`,
  `frontend/src/pages/LoginPage.jsx`, `frontend/src/pages/AdminDashboardPage.jsx`
- `backend/Dockerfile`, `frontend/Dockerfile`, `docker-compose.yml`, `.platform.app.yaml`

### 2.1 Authentication Layer

- **Module:** `drupal/simple_oauth` `^6.0` (declared in `backend/composer.json`;
  install manually with `composer require drupal/simple_oauth:^6.0` — the
  scaffold does not run composer). Simple OAuth 6 issues **OAuth2 access tokens
  as JWTs signed RS256**, so "OAuth2/JWT" is one mechanism here.
- **Keys:** `backend/phase2-setup.sh` generates a 2048-bit RSA keypair with
  `openssl` into `v2/private/` (outside the `backend/web/` docroot;
  `private.key` chmod 600, `public.key` chmod 644) and writes the absolute
  paths into `simple_oauth.settings` via `drush config:set`.
- **Client:** a `famtastic_spa` Consumer entity (public client, no secret,
  roles `authenticated`) created idempotently via `drush eval` in
  `phase2-setup.sh`.
- **Flow (password grant + refresh rotation):**
  1. `LoginPage.jsx` collects **email + password** and POSTs to
     `POST {DRUPAL_BASE}/oauth/token` with
     `grant_type=password&client_id=famtastic_spa&username=<email>&password=<pwd>`.
     Decision: Drupal usernames are set to the user's email at registration
     time, so the password grant works directly with email — no
     email→username resolution round-trip, no extra `access user profiles`
     permission for the SPA.
  2. Response `{ access_token, refresh_token, expires_in }` is handed to
     `UserContext.login()`.
  3. On 401, `drupal.js` silently calls `grant_type=refresh_token` once and
     retries; on failure it logs out and redirects to `/login`.
- **Token storage decision: secure `localStorage` (Bearer header), not httpOnly
  cookies.** Rationale: (a) the SPA and API live on different origins in dev
  (`localhost:5173` vs `localhost:8080`), so cookie auth requires
  `SameSite=None; Secure` + credentialed CORS — fragile and HTTPS-only even in
  dev; (b) simple_oauth returns tokens in a JSON body and never sets cookies
  itself, so a cookie flow would need a custom proxy/session bridge; (c) the
  existing anonymous client already uses `credentials: 'omit'` — attaching an
  `Authorization: Bearer` header is the minimal, non-invasive extension.
  Trade-off accepted: localStorage is XSS-readable. Mitigations: short
  `expires_in` (300s) with refresh-token rotation, strict CSP in the nginx
  frontend config, no third-party scripts, and React's default output escaping
  (never inject token-adjacent markup via `dangerouslySetInnerHTML`).
- **Authenticated requests in `drupal.js`:** add `login(email, password)`,
  `refreshTokens()`, `getValidToken()`, and extend `apiFetch()` to attach
  `Authorization: Bearer <token>` when a token exists (anonymous behavior and
  the stub fallback stay unchanged). New authenticated helpers:
  `getMyProjects()` (`/jsonapi/node/client_project?filter[field_client_user.id]=<me>`),
  `getPackages()`, `getTestimonials()`.

### 2.2 Custom Content Types

Defined in `backend/config/phase2/` (node.type + field.storage + field.field
YAML following Drupal 11 schema, per `web/core/recipes/*/config/` examples)
and imported by `phase2-setup.sh` step 8. No `promote` to frontpage; JSON:API
exposes them automatically once created.

| Type (machine) | Fields |
| --- | --- |
| **Client Project** `client_project` | `title`, `field_client_name` (string), `field_client_user` (entity_reference → user, for ownership), `field_status` (list_string: `active`/`on_hold`/`complete`), `field_budget` (decimal 10,2), `field_due_date` (date), `field_notes` (text_long) |
| **Service Package** `service_package` | `title`, `field_price` (decimal 10,2), `body` (description, text_with_summary), `field_features` (string, unlimited), `field_tier` (list_string: `basic`/`standard`/`premium`) |
| **Testimonial** `testimonial` | `title` (short label), `field_client_name` (string), `field_quote` (text_long), `field_project_ref` (entity_reference → `node:client_project`), `field_rating` (integer, 1–5) |

### 2.3 Content Permissions

Applied by `phase2-setup.sh` step 7 (`drush role:perm:add`, idempotent):

- **anonymous:** `access content` (core default — "view published content").
  JSON:API then serves published `service_package` and `testimonial` nodes
  read-only; unpublished and `client_project` content stays out of anonymous
  listings by access rules.
- **authenticated:** `access content`. Clients read **their own** projects:
  `getMyProjects()` filters JSON:API by `field_client_user` = current user.
  Server-side enforcement of the ownership rule (deny reading *other* clients'
  projects even with a guessed UUID) is a documented hardening follow-up via
  `hook_node_access()` in `backend/web/modules/custom/famtastic_access/` —
  Phase 2 ships the role/filter layer, not the custom module.
- **admin Fritz (uid 1):** full CRUD. uid 1 bypasses the permission system by
  core design; `phase2-setup.sh` prints this as a note (nothing to grant).
  Admin works through the Drupal UI at `/admin/content` (classic toolbar +
  `famtastic_admin` dark theme), not through the SPA.

### 2.4 Deployment Config

- **`backend/Dockerfile`** — multi-stage: `composer:2` build stage
  (`composer install --no-dev --optimize-autoloader`), then
  `php:8.3-fpm` + `nginx` in one image (nginx serves `web/` and proxies
  `*.php` to php-fpm over 127.0.0.1:9000; supervisord-free entrypoint starts
  both). Includes `v2/private/` keys via build-time copy or mounted volume.
- **`frontend/Dockerfile`** — multi-stage: `node:20-alpine` runs
  `npm ci && npm run build`, then `nginx:alpine` serves `dist/` with an SPA
  fallback (`try_files $uri /index.html`) and the strict CSP from §2.1.
- **`docker-compose.yml`** (v2 root) — services: `backend` (build
  `./backend`, publish `8080`, volume `./private:/app/private:ro`) and
  `frontend` (build `./frontend`, publish `5173:80`, env
  `VITE_DRUPAL_BASE_URL=http://localhost:8080`, `depends_on: backend`).
- **`.platform.app.yaml`** (v2 root) — Platform.sh single-app definition:
  `type: php:8.3`, docroot `backend/web`, build flavor composer, SQLite file
  on a writable mount (matching local dev), deploy hook runs
  `backend/setup.sh && backend/phase2-setup.sh`; companion
  `.platform/routes.yaml` + `.platform/services.yaml` route `/` to the
  frontend container and `/jsonapi|/oauth` to the backend.

### 2.5 React Updates

All under `frontend/src/`, matching Phase 1 conventions (Layout/Header, brand
tokens `--fam-bg #0a0a0a` … `--fam-lime #7CFC00`):

- **`context/UserContext.jsx`** — `{ user, token, login(email, pwd),
  logout(), hasRole() }`; persists token/user in localStorage under
  `famtastic.auth`, restores on mount, exposes `authFetch` via `drupal.js`.
- **`components/ProtectedRoute.jsx`** — wrapper: renders children when
  authenticated (optionally role-gated), else `<Navigate to="/login">` with
  the attempted path in location state for post-login redirect.
- **`pages/LoginPage.jsx`** — dark-themed email/password form using brand
  tokens; on success stores tokens via `UserContext.login()` and navigates
  back; inline error on 401/invalid grant.
- **`pages/AdminDashboardPage.jsx`** — protected route `/admin`: lists the
  user's own Client Projects (status/budget/due date), and surfaces Service
  Packages + Testimonials; reuses `NodeList` styling.
- **`App.jsx` / `Header.jsx`** — register `/login` and `/admin` routes, wrap
  the tree in `<UserProvider>`, add Login/Logout + Dashboard nav items driven
  by `UserContext`.

**Execution order:** (1) `composer require drupal/simple_oauth:^6.0` in
`backend/` (manual — scaffold runs no installs) → (2) scaffold
`backend/config/phase2/*.yml` → (3) run `backend/phase2-setup.sh` → (4)
scaffold the §2.5 React files → (5) `docker compose up --build` to validate
the §2.4 deployment configs.
