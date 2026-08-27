# Backend Deployment

## Contract

The Drupal custom module in
`backend/web/modules/custom/famtastic_pipeline` is the canonical transactional
backend source. The checked-in `famtastic_admin` and `famtastic_customer`
themes are the canonical admin and customer presentation sources. Governed
editorial fields and the draft demand library are promoted by the same
deployment lane. Production is not edited or uploaded manually. Any authorized
agent uses the same checked-in deployment script.

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
4. backs up the current custom module, admin/customer themes, dependencies,
   configuration, and Drupal database;
5. stages and swaps the custom module and both themes;
6. runs `drush updatedb -y`, idempotently installs the governed demand-library
   fields through Drupal's entity API, and seeds the idempotent draft library;
7. rebuilds caches and verifies the sitemap route and pipeline entity definitions;
8. records the commit, timestamp, PHP version, code/config backup paths, database
   backup, and demand-manifest version in `~/public_html/.backend-release`.

The demand seed is intentionally fail-closed: generated content remains
unpublished unless both the item and the manifest-wide publication approval are
explicitly enabled. Re-running the deployment updates matching records rather
than duplicating them.

The field installer mirrors the checked-in field configuration but uses
Drupal's entity API. This avoids partial-import dependency failures caused by a
form display legitimately depending on configuration outside the small import
set.

Composer validation uses the deployment-owned writable temporary directory at
`~/deploy/famtastic-designs/tmp`; shared-host `/tmp` permissions are not part of
the release contract. The production runtime already owns its vendor tree, so a
module-only release does not duplicate all Drupal dependencies under every Git
release. Adding a new runtime dependency remains a separately reviewed platform
migration.

### Exact-ID public-preview pilot

For an owner-gated public-preview pilot, both preflight and apply must declare
the narrow delivery mode explicitly:

```bash
FAMTASTIC_PILOT_EXACT_DISPATCH_ONLY=1 ./scripts/deploy-backend-godaddy.sh
FAMTASTIC_PILOT_EXACT_DISPATCH_ONLY=1 ./scripts/deploy-backend-godaddy.sh --apply
```

In this mode the deployer persists and verifies
`famtastic_pipeline.settings.pilot_exact_dispatch_only=1` before code
promotion. That durable Drupal setting is the runtime authority: cPanel starts
each `drush cron` process with a fresh shell, so deploy-shell environment
variables alone cannot protect later automation. With the setting active,
`famtastic_pipeline_cron()` and `famtastic:lifecycle-run` stop before any
general protection, automation, outbox, SLA, or mail work. The pilot invitation
must instead be sent only by the exact-ID, owner-confirmed
`famtastic:preview-delivery-dispatch` command.

The deployer still refuses an active broad `famtastic:lifecycle-run` entry and
does not install one. It also records any active `drush cron` entries, but it
does not alter an unmarked Drupal cron line because that would be an unsafe
crontab rewrite; the durable runtime lock makes the module's hook a no-op after
promotion. A normal non-pilot apply explicitly clears and verifies the durable
setting only after its code, update, and cache checks pass.

If the only active broad scheduler is exactly the checked-in
`FAMTASTIC_LIFECYCLE_CRON_V1` marker followed immediately by its standard
`famtastic:lifecycle-run --limit=50` command, an explicitly authorized apply
can suspend it narrowly:

```bash
FAMTASTIC_PILOT_EXACT_DISPATCH_ONLY=1 \
FAMTASTIC_PILOT_SUSPEND_MARKED_LIFECYCLE_CRON=1 \
./scripts/deploy-backend-godaddy.sh --apply
```

Preflight with the same flags validates that exact pair without changing
production. Apply saves the complete pre-change crontab below
`~/deploy/famtastic-designs/cron-backups/`, removes only that pair, and refuses
to proceed if any other active lifecycle runner is present. The recorded backup
supports a separate, explicit scheduler restoration; a failed code deployment
does not automatically re-enable broad dispatch.

### Historical generic-proof queue gate

The `cold-260-aug-2026` generic proof queue must be empty before an exact-ID
pilot is recorded as safe. Pilot preflight counts only its exact historical
queued `proof.generate:prospect:*` jobs and refuses a nonzero count by default.
It never quarantines work implicitly.

If the owner explicitly authorizes that one narrow quarantine, repeat the exact
campaign key in both variables on the apply command:

```bash
FAMTASTIC_PILOT_EXACT_DISPATCH_ONLY=1 \
FAMTASTIC_PILOT_SUSPEND_MARKED_LIFECYCLE_CRON=1 \
FAMTASTIC_PILOT_LEGACY_QUARANTINE_CAMPAIGN=cold-260-aug-2026 \
FAMTASTIC_PILOT_LEGACY_QUARANTINE_CONFIRM=cold-260-aug-2026 \
./scripts/deploy-backend-godaddy.sh --apply
```

The script only invokes the existing exact-campaign quarantine after the new
module, dependencies, updates, cache rebuild, and durable runtime lock are
active. It writes a private receipt, rechecks the exact queue is zero, and
records the before/after count plus receipt location in `.backend-release`.
If the running production release lacks that Drush command, the apply fails
closed rather than guessing or modifying the queue directly.

If code promotion or a Drupal command fails, the script restores the prior
module plus both the admin and customer themes, then rebuilds cache. On a
successful deployment it removes the temporary prior-theme directories. Database
updates cannot be assumed reversible, so the pre-update SQL dump is retained and
its path is printed.

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
tar -xzf ~/backups/famtastic-admin-TIMESTAMP-SHA.tgz \
  -C ~/public_html/web/themes/custom
tar -xzf ~/backups/famtastic-customer-TIMESTAMP-SHA.tgz \
  -C ~/public_html/web/themes/custom
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
