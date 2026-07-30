# Configuration sync

This directory is the Drupal **configuration sync (export/import) path** for
the FAMtastic Designs backend.

- Declared in `web/sites/default/settings.php` as
  `$settings['config_sync_directory'] = '../config/sync';`
  (relative to the Drupal docroot `web/`).
- Export site configuration: `vendor/bin/drush config:export` (aliases: `drush cex`).
- Import site configuration: `vendor/bin/drush config:import` (aliases: `drush cim`).
- `sync/` ships empty in the scaffold (only a `.gitkeep`); it is populated on
  the first `drush cex`.
- Keep `sync/` under version control — it is the canonical source of truth for
  site configuration shared across environments.
