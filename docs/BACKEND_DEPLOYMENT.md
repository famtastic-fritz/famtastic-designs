# Backend Deployment

## Contract

The Drupal custom module in
`backend/web/modules/custom/famtastic_pipeline` is the canonical transactional
backend source. The checked-in `famtastic_admin` and `famtastic_customer`
themes are the canonical admin and customer presentation sources. Governed
editorial fields and the draft demand library are promoted by the same
deployment lane. Production is not edited or uploaded manually. Any authorized
agent uses the same checked-in deployment script.

Production has a mixed Drupal runtime in `~/public_html`, including the Drupal
project's root `composer.json`, `composer.lock`, and vendor tree. The deployment
lane treats the checked-in lock as a runtime contract: it validates the exact
backend dependency set in a private Git worktree, backs up the live dependency
tree, promotes the reviewed Composer files, and runs production
`composer install --no-dev`. A runtime dependency change is therefore a
reviewed platform release, not a manual server Composer command or an
admin-panel update.

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
   requirements, and PHP-lints the module in the private release;
4. backs up the current custom module, admin/customer themes, dependencies,
   configuration, and Drupal database;
5. stages and swaps the custom module and both themes, then promotes the exact
   reviewed Composer files and runs `composer install --no-dev` in the
   production runtime;
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

Composer validation and install use the deployment-owned writable temporary
directory at `~/deploy/famtastic-designs/tmp`; shared-host `/tmp` permissions
are not part of the release contract. A release that changes the lock must
finish with a production `composer audit --locked`, `drush updatedb:status`,
and targeted route smoke before it is recorded as complete.

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

Before a pilot is even eligible, its live Drupal configuration must resolve to
the canonical customer-facing bases exactly:

```text
frontend_base_url=https://famtasticdesigns.com
public_api_base_url=https://famtasticdesigns.com/web
```

The deployer reads and asserts those values; it never guesses or rewrites a
localhost, staging, empty, or other same-origin value. Correcting a noncanonical
live value is a separately authorized configuration operation. A normal
non-pilot apply explicitly clears and verifies the durable dispatch lock only
after its code, update, and cache checks pass.

#### Broad scheduler pre-promotion guard

The old production module cannot read a lock introduced by the new release, so
the pilot preflight refuses every active broad scheduler before old code is
promoted. It discovers these active crontab forms and also fails if a matching
process is already in flight (removing a line cannot stop a process that already
started):

- `famtastic:lifecycle-run`;
- `drush cron` (including a path-qualified Drush command);
- `famtastic:jobs-run` or `fjr`; and
- direct `drush php:eval`, `php:script`, `ev`, or an
  `automation[_:-]?worker` runner.

An unmarked line is never guessed at or deleted. Direct evaluator/worker lines
are never automatically suspended: the operator must manually make that class
empty, then rerun preflight. A lifecycle, Drupal-cron, or jobs-run entry can be
suspended only when it is the **one** documented marker immediately followed by
the byte-exact checked-in command and the corresponding explicit confirmation is
present. For example, a deliberately marker-owned Drupal cron line uses:

```text
# FAMTASTIC_DRUPAL_CRON_V1
*/5 * * * * cd /home/ACCOUNT/public_html && /home/ACCOUNT/public_html/vendor/bin/drush cron >/dev/null 2>&1
```

Replace `/home/ACCOUNT` with the actual remote home path shown by the deployer;
do not use `~`, a different interval, redirection, or wrapper. Its authorized
pilot invocation is:

```bash
FAMTASTIC_PILOT_EXACT_DISPATCH_ONLY=1 \
FAMTASTIC_PILOT_SUSPEND_MARKED_DRUPAL_CRON=1 \
FAMTASTIC_PILOT_SUSPEND_MARKED_DRUPAL_CRON_CONFIRM=FAMTASTIC_DRUPAL_CRON_V1 \
./scripts/deploy-backend-godaddy.sh --apply
```

The lifecycle and jobs-run forms use the equivalent
`FAMTASTIC_LIFECYCLE_CRON_V1` / `FAMTASTIC_JOBS_RUN_CRON_V1` marker, exact
confirmation, and standard command. Preflight with the same declarations only
validates; it changes nothing. Apply first writes a complete mode-0600 crontab
backup under `~/deploy/famtastic-designs/cron-backups/`, atomically removes only
the authorized marker/command pair(s), and proves all broad scheduler counts
are zero before it sets the durable pilot lock. It repeats the process/cron
assertion immediately before the old-code swap, after long validation and backup
work but before any production module/theme file changes.

The pilot deployer does **not** automatically restore the backup on either a
successful or failed deployment. Leaving the pair suspended is intentional: a
stale full-crontab restore could overwrite later operator work or reopen shared
dispatch before reconciliation. The explicit end-pilot procedure is to inspect
the recorded backup and current crontab, reconcile all failed/retry work,
inventory queued/retry `famtastic_notification_outbox` rows, then separately
reinsert only the reviewed marker and exact command. Do not apply the entire
backup with `crontab <backup>`.

### Historical generic-proof queue gate

The `cold-260-aug-2026` legacy campaign must have no active or unrecognized
claimable proof/mail work before an exact-ID pilot is recorded as safe. The
preflight inspects only exact campaign-attributed rows:

- `proof.generate:prospect:*` and `outreach.prepare:prospect:*` jobs for its
  attributed Prospect IDs;
- `outreach.send:message:<id>` jobs for its exact campaign message IDs; and
- generic `famtastic_email_message` rows whose `campaign_id` is that exact
  campaign.

It treats queued/retry (and staged/held for messages) as claimable; running,
claimed, processing, dispatching, or sending rows require manual
reconciliation; unknown statuses also fail closed. It never touches
`famtastic_notification_outbox` in this exact campaign quarantine because that
table has no campaign/prospect ownership key. The pilot dispatch lock holds that
global dispatcher; its queued/retry inventory is a mandatory manual end-pilot
check before any future normal release clears the lock.

If the owner explicitly authorizes that one narrow quarantine, repeat the exact
campaign key in both variables on the apply command:

```bash
FAMTASTIC_PILOT_EXACT_DISPATCH_ONLY=1 \
FAMTASTIC_PILOT_LEGACY_QUARANTINE_CAMPAIGN=cold-260-aug-2026 \
FAMTASTIC_PILOT_LEGACY_QUARANTINE_CONFIRM=cold-260-aug-2026 \
./scripts/deploy-backend-godaddy.sh --apply
```

The script only invokes the existing exact-campaign quarantine after the new
module, dependencies, updates, cache rebuild, and durable runtime lock are
active. It writes a private receipt with job/message IDs and counts by type and
status, rechecks that no exact claimable or active/unknown work remains, and
records the before/after counts plus receipt location in `.backend-release`. If
the running production release lacks that Drush command, the apply fails closed
rather than guessing or modifying the queue directly.

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
