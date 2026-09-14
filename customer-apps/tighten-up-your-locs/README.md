# Tighten Up Your Locs independent owner application

This is a standalone Laravel12/PHP8.3 application. It has its own database, owner identity, sessions, booking authority, notification outbox and backup job. Drupal and the FAMtastic portal are not runtime dependencies. Source is temporarily versioned beside the site delivery artifact; this directory is the independently deployable boundary, not an agency portal module.

## Verification and runtime

- `cd application && composer install && php artisan test`
- `node --test tests/admin-ui.test.mjs`
- `composer audit --format=plain`
- Set local `.env`, migrate local SQLite and start `php artisan serve`. Production is dedicated MySQL with separate migration and CRUD-only application users; secrets/storage live outside public document root.
- Framework and third-party versions/licenses are pinned in application/composer.lock. No additional subscription purchased. Native Blade/JS admin needs no external JavaScript CDN or agency API.
- `/admin/login`, `/admin`, `/admin/forgot-password` and `/admin/reset-password/{token}` provide independent owner access. No public registration. Initial owner requires private password setup; agency passwords are never copied.
- Public intake/availability remain same-origin under `/api/`. Proposal review lives under `/appointment/`. Only explicit gateway PHP and static admin assets are public; environment/vendor/source/data stay private.

## Release sequence and rollback

1. Commit/push exact source and require all tests, audit and browser proof. Build from clean pushed main using build-release.mjs. Inspect package and source hashes.
2. Run provision.php read-only; `--apply-exact-locs` creates only nineoo_locs and scoped migration/runtime users. Credentials are preserved privately before provider calls. Reconcile uncertain responses rather than blind retries.
3. Configure private environment through configure.php and encrypted stdin using the existing verified Locs mailbox; never display credentials. Booking/mail begin disabled.
4. Install exact hashed package with install.php. It creates a database backup before migrations and records previous application path and public-file backups. Source origin is a reviewed pushed commit; the installer does not certify business acceptance.
5. Prove independent hosted auth, CSRF, DB isolation, own reservation conflicts, mail and backup. Freeze legacy Locs endpoints/binding before request export; stop if legacy appointment/opening inventory changed. Preserve all old records.
6. Import frozen request UUIDs via `locs:import-legacy`; verify count and hash. No alerts are regenerated. Recheck source counts after in-flight legacy requests settle; do not open independent writes if migration differs.
7. Enable independent mail/booking explicitly with activate.php, publish same-origin static config through the existing managed public-artifact publisher, and install an exact Locs-only scheduler cron. Retire only the old Locs worker marker; preserve all neighbors. Verify real live flow and recipient setup before final handoff.

Rollback before independent traffic: disable new public booking, restore the old public artifact and exact legacy binding/config; preserve new database for inspection. After independent requests exist, do NOT blindly restore the old booking authority: freeze both and reconcile records first. Never delete old records or reset owner passwords as rollback. Each code release retains previous application symlink and scoped public backups. `locs:backup` writes private SQL snapshots; restore must be tested separately.

## Notification truth

Business writes and outbox events commit atomically. `locs:dispatch-mail` sends only this database's queue. Sender rechecks appointment version, records provider Message-ID and never labels SMTP acceptance an inbox/read receipt. Expired sender or uncertain transport becomes `uncertain`; it is not automatically resent. An operator may inspect and explicitly retry one UUID using `--retry=UUID`, acknowledging possible duplicate delivery. Newer appointment state supersedes old unsent notices.

## Limits

No Google/Apple/Booksy synchronization, payments, SMS, teaching enrollment or LMS added by this separation. Owner browser acceptance is separate from automated authentication proof. Existing hosting is shared infrastructure, but application/database identity and operational dependencies are isolated. Shared account administrators retain hosting-level access; this is not a separate server or hosting-account transfer.
