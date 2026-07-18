<?php

// phpcs:ignoreFile

/**
 * @file
 * FAMtastic Designs v2 — Drupal 11 headless backend settings.
 *
 * This file ships with safe local-development defaults so the scaffold works
 * out of the box at http://localhost:8080. Environment-specific secrets
 * (real hash salt, production database credentials, etc.) belong in
 * settings.local.php, which is loaded at the bottom of this file and is
 * excluded from version control.
 */

/**
 * Database.
 *
 * No $databases is defined here on purpose: `drush site:install --db-url=...`
 * (see backend/README.md) lets the installer write the correct connection
 * for this environment into this file. The zero-config local path is SQLite:
 *
 *   vendor/bin/drush site:install standard \
 *     --db-url=sqlite://sites/default/files/.ht.sqlite \
 *     --site-name="FAMtastic Designs" \
 *     --account-name=admin --account-pass=admin -y
 *
 * For MySQL/MariaDB instead, uncomment the block below (or, better, define
 * it in settings.local.php) BEFORE running site:install:
 *
 * $databases['default']['default'] = [
 *   'database' => 'famtastic_v2',
 *   'username' => 'drupal',
 *   'password' => 'drupal',
 *   'host' => 'localhost',
 *   'port' => '3306',
 *   'driver' => 'mysql',
 *   'prefix' => '',
 *   'collation' => 'utf8mb4_general_ci',
 *   'init_commands' => [
 *     'big_selects' => 'SET SQL_BIG_SELECTS=1',
 *   ],
 * ];
 */

/**
 * Salt for one-time login links, cancel links, form tokens, etc.
 *
 * PLACEHOLDER — local development only. Generate a real per-environment salt:
 *   vendor/bin/drush php:eval "echo \Drupal\Component\Utility\Crypt::randomBytesBase64(55) . \"\n\";"
 * and set it in settings.local.php (never committed).
 */
$settings['hash_salt'] = 'YvFfCrU0zWtV_QvVQb-oGTn2oJ8YwYvpvHoKWL57Lfd4AxYteDsYWx6FhYtEEjJSI7uxbbG6OQ';

/**
 * Configuration sync directory.
 *
 * Relative to the Drupal docroot (web/), so this resolves to
 * backend/config/sync. See backend/config/README.md.
 */
$settings['config_sync_directory'] = '../config/sync';

/**
 * CORS configuration.
 *
 * NOTE (Drupal 11): CORS is NOT configured via $settings. The cors.config
 * container parameter is read from sites/default/services.yml — see that
 * file. It enables http://localhost:5173 (the Vite dev server) with
 * credentials support for JSON:API at http://localhost:8080/jsonapi.
 *
 * The line below registers that file with the container (this is what the
 * stock default.settings.php ships with; without it, services.yml is
 * silently ignored).
 */
$settings['container_yamls'][] = $app_root . '/' . $site_path . '/services.yml';

/**
 * Trusted host patterns.
 *
 * Ports are not part of the matched host, so localhost:8080 is covered by
 * the plain localhost pattern.
 */
$settings['trusted_host_patterns'] = [
  '^localhost$',
  '^127\.0\.0\.1$',
  '^::1$',
];

/**
 * Public and private file paths.
 *
 * The private path lives outside the docroot at backend/private/.
 */
$settings['file_public_path'] = 'sites/default/files';
$settings['file_private_path'] = '../private';

/**
 * Default mode for new directories and files created by Drupal.
 *
 * 0775 / 0664 keeps files group-writable for the local web server user.
 */
$settings['file_chmod_directory'] = 0775;
$settings['file_chmod_file'] = 0664;

/**
 * Access control for update.php and site rebuilds.
 */
$settings['update_free_access'] = FALSE;
$settings['rebuild_access'] = FALSE;

/**
 * Number of entities to process per batch during entity updates.
 */
$settings['entity_update_batch_size'] = 50;

/**
 * Keep permissions hardening on sites/default enabled.
 */
$settings['skip_permissions_hardening'] = FALSE;

/**
 * Load local environment overrides, if present.
 *
 * settings.local.php is gitignored; use it for real secrets and any
 * per-machine overrides of the values above.
 */
if (file_exists($app_root . '/' . $site_path . '/settings.local.php')) {
  include $app_root . '/' . $site_path . '/settings.local.php';
}
$databases['default']['default'] = array (
  // Absolute path via $app_root — SQLite resolves relative DSNs against the
  // process CWD, which is unstable under php -S with a router script.
  'database' => $app_root . '/sites/default/files/.ht.sqlite',
  'prefix' => '',
  'driver' => 'sqlite',
  'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite',
  'autoload' => 'core/modules/sqlite/src/Driver/Database/sqlite/',
);

/**
 * Phase 2 deployment scaffold — environment-driven database + host config.
 *
 * Local development keeps the SQLite default above. In deployed environments
 * the connection comes from the environment instead:
 *
 * - docker-compose (v2/docker-compose.yml) injects DB_HOST/DB_PORT/DB_NAME/
 *   DB_USER/DB_PASSWORD pointing at the MariaDB service;
 * - Platform.sh injects PLATFORM_RELATIONSHIPS (base64-encoded JSON) via the
 *   `database` relationship in v2/.platform.app.yaml.
 *
 * Whichever is present wins over SQLite; with neither set, nothing changes.
 */
if (getenv('DB_HOST')) {
  $databases['default']['default'] = [
    'database' => getenv('DB_NAME') ?: 'famtastic',
    'username' => getenv('DB_USER') ?: 'drupal',
    'password' => getenv('DB_PASSWORD') ?: '',
    'host' => getenv('DB_HOST'),
    'port' => getenv('DB_PORT') ?: '3306',
    'driver' => 'mysql',
    'prefix' => '',
    'collation' => 'utf8mb4_general_ci',
    'init_commands' => [
      'big_selects' => 'SET SQL_BIG_SELECTS=1',
    ],
  ];
}
elseif (getenv('PLATFORM_RELATIONSHIPS')) {
  $relationships = json_decode(base64_decode(getenv('PLATFORM_RELATIONSHIPS')), TRUE);
  $platform_db = $relationships['database'][0] ?? NULL;
  if ($platform_db) {
    $databases['default']['default'] = [
      'database' => $platform_db['path'],
      'username' => $platform_db['username'],
      'password' => $platform_db['password'],
      'host' => $platform_db['host'],
      'port' => $platform_db['port'],
      'driver' => 'mysql',
      'prefix' => '',
      'collation' => 'utf8mb4_general_ci',
      'init_commands' => [
        'big_selects' => 'SET SQL_BIG_SELECTS=1',
      ],
    ];
  }
  // Trust the environment domains (*.platformsh.site). Custom domains must
  // be appended here when they are attached to the project.
  $settings['trusted_host_patterns'][] = '^.+\.platformsh\.site$';
}
