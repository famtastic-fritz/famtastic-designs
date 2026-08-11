# Backend Deployment

## Contract

The Drupal custom module in
`backend/web/modules/custom/famtastic_pipeline` is the canonical transactional
backend source. The checked-in `famtastic_admin` theme is the canonical admin
presentation source. Governed editorial fields and the draft demand library are
promoted by the same deployment lane. Production is not edited or uploaded
manually. Any authorized agent uses the same checked-in deployment script.

Production currently has a mixed Drupal runtime in `~/public_html` with its
vendor tree but no root `composer.json`. The deployment lane therefore validates
the complete backend dependency lock in a private Git worktree and promotes the
custom module surface. A future change that adds a runtime dependency not
already installed on production is a separate reviewed platform migration; the
module release must fail preflight until that dependency is present.

## Preflight

Run from a clean checkout whose `HEAD` equals GitHub `main`:

```bash
./scripts/deploy-backend-godaddy.sh
```

This verifies GitHub main, remote PHP/Composer/Drush, Drupal bootstrap and
database connectivity, paths, and disk space without changing production.

## Apply

Production apply requires explicit authorization:

```bash
./scripts/deploy-backend-godaddy.sh --apply
```

The script:

1. resolves the exact current `main` SHA on both machines;
2. checks it out outside the document root;
3. validates `composer.json`/`composer.lock`, checks locked production platform
   requirements, and PHP-lints the module in the private release without
   installing a duplicate Drupal vendor tree;
4. backs up the current custom module, admin theme, dependencies, configuration,
   and Drupal database;
5. stages and swaps the custom module and admin theme;
6. runs `drush updatedb -y`, imports only the governed demand-library field
   configuration, and seeds the idempotent draft library;
7. rebuilds caches and verifies the sitemap route and pipeline entity definitions;
8. records the commit, timestamp, PHP version, code/config backup paths, database
   backup, and demand-manifest version in `~/public_html/.backend-release`.

The demand seed is intentionally fail-closed: generated content remains
unpublished unless both the item and the manifest-wide publication approval are
explicitly enabled. Re-running the deployment updates matching records rather
than duplicating them.

Composer validation uses the deployment-owned writable temporary directory at
`~/deploy/famtastic-designs/tmp`; shared-host `/tmp` permissions are not part of
the release contract. The production runtime already owns its vendor tree, so a
module-only release does not duplicate all Drupal dependencies under every Git
release. Adding a new runtime dependency remains a separately reviewed platform
migration.

If code promotion or a Drupal command fails, the script restores the prior
module and rebuilds cache. Database updates cannot be assumed reversible, so
the pre-update SQL dump is retained and its path is printed.

## Verification

After apply:

```bash
ssh "$FAMTASTIC_SSH_TARGET"
cat ~/public_html/.backend-release
cd ~/public_html
vendor/bin/drush updatedb:status
vendor/bin/drush watchdog:show --severity=Error --count=20
```

Then exercise the production-safe read paths and the public forms. Do not run a
live charge, real campaign, domain purchase, or DNS change without its explicit
approval.

## Rollback

Use the exact paths in `.backend-release`. Restore code first:

```bash
tar -xzf ~/backups/famtastic-pipeline-TIMESTAMP-SHA.tgz \
  -C ~/public_html/web/modules/custom
cd ~/public_html
vendor/bin/drush cr
```

Restore the database only when the failed release executed a database update
that is incompatible with the old code. This is destructive and requires a
separate explicit approval:

```bash
gunzip -c ~/backups/famtastic-database-TIMESTAMP-SHA.sql.gz |
  vendor/bin/drush sql:cli
vendor/bin/drush cr
```
