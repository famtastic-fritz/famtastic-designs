# FAMtastic Designs — Backend (Drupal 11 Headless)

Headless Drupal 11 CMS serving JSON:API to the React SPA in `../../frontend/`.
Full monorepo docs live in [`../README.md`](../README.md); this file is the
backend-only quickstart.

## Prerequisites

- PHP 8.3+ (with `pdo_sqlite` for the zero-config install, or MySQL/MariaDB)
- Composer 2

## Quickstart

```bash
cd backend

# 1. Install Drupal core + contrib (jsonapi_extras, consumers, admin_toolbar, drush)
composer install

# 2. Serve the docroot at http://localhost:8080
php -S localhost:8080 -t web web/.ht.router.php

# 3. Install Drupal (SQLite = zero config; see root README for MySQL option)
vendor/bin/drush site:install standard \
  --db-url=sqlite://sites/default/files/.ht.sqlite \
  --site-name="FAMtastic Designs" \
  --account-name=admin --account-pass=admin -y

# 4. Bootstrap: enable modules + set FAMtastic Admin as the admin theme
./setup.sh
```

`setup.sh` runs the equivalent of:

```bash
vendor/bin/drush en -y jsonapi serialization rest jsonapi_extras consumers admin_toolbar
vendor/bin/drush theme:enable famtastic_admin
vendor/bin/drush config:set -y system.theme admin famtastic_admin
vendor/bin/drush cache:rebuild
```

## JSON:API endpoints

| URL | Returns |
| --- | --- |
| `http://localhost:8080/jsonapi` | Resource index (all entity types) |
| `http://localhost:8080/jsonapi/node/article` | Article collection |
| `http://localhost:8080/jsonapi/node/article/{uuid}` | Single article |

Anonymous read access works out of the box (standard profile grants
"access content"). Create demo articles at `/node/add/article` after logging
in at `/user` (admin/admin).

## Custom admin theme

**FAMtastic Admin** (`web/themes/custom/famtastic_admin/`) — Claro sub-theme,
dark surfaces + lime accent (`#7CFC00`). Enabled as the admin theme by
`setup.sh`. To toggle manually:

```bash
vendor/bin/drush config:set -y system.theme admin famtastic_admin   # enable
vendor/bin/drush config:set -y system.theme admin claro             # revert
```

## Key files

- `composer.json` — Drupal 11 + headless module dependencies
- `web/sites/default/services.yml` — CORS (`cors.config` → `http://localhost:5173`);
  Drupal 11 reads the parameter here, not from `$settings` (registered via
  `$settings['container_yamls']` in settings.php; `drush cr` after edits)
- `web/sites/default/settings.php` — `config_sync_directory: ../config/sync`,
  trusted host patterns, file perms, hash salt
- `config/sync/` — configuration export target (`drush cex`)
- `setup.sh` — one-command post-install bootstrap (idempotent; `DB_URL` env
  var overrides the default SQLite connection)

## Phase 1 scope

Anonymous JSON:API only — no OAuth (`consumers` installed, unconfigured),
no deploy/CI, no tests, no production hardening.
