# FAMtastic Designs Deploy Runbook

Last verified: 2026-06-19

## Scope
This runbook is the project-local truth for deploying the current FAMtastic Designs marketing site.

## Why this belongs here
Deploy truth for this site must live with this site. Future sessions and agents should not depend on chat memory, Chrome history, or tribal knowledge.

## Live target
- Domain: `famtasticdesigns.com`
- Live cPanel host: `p3plzcpnl497512.prod.phx3.secureserver.net`
- Live cPanel account: `xrdj7j99xhzt`
- Live docroot: `/home/xrdj7j99xhzt/public_html`
- Local canonical project: `/Users/famtasticfritz/famtastic/sites/site-famtastic-designs`
- Local deploy artifact lane: no committed static-export build directory currently lives in the canonical tree; the deploy docs are canonical here, but the legacy static export pipeline still needs to be migrated before the next rebuild can be done from this tree alone.

## Critical trap
There are multiple GoDaddy/cPanel surfaces in the ecosystem. The one above is the real live Designs host.

Do not assume older `.env` files, old cPanel tokens, or other GoDaddy hosts are the right target. Verify the live domain and host before touching production.

## Current deploy model truth
The live production lane that was last used is:
1. prepare the static site payload that was cut over on 2026-06-19
2. back up current `public_html`
3. replace `public_html` contents with the exported site
4. keep clean-URL routing via root `.htaccess`
5. smoke-test live URLs

This is the fastest honest lane right now. It is not a Vercel-first production path.

## Current local-repo truth
The canonical local tree for this site is the path above.

What is NOT true yet:
- the static-export app pipeline is not migrated into this canonical tree
- there is no committed `apps/web/out/` equivalent here right now

That means future sessions must either:
1. migrate the export/build pipeline into this canonical tree first, or
2. import a verified deploy artifact into a clearly documented canonical location before the next production cutover

Do not pretend this repo can currently rebuild the live static export end-to-end without that migration work.

## Secrets handling
Do not store real passwords or tokens in tracked docs.

Use:
- tracked template: `.env.deploy.example`
- local secret file: `.env.deploy.local`

Expected keys:
- `DEPLOY_DOMAIN`
- `DEPLOY_CPNL_HOST`
- `DEPLOY_CPNL_USER`
- `DEPLOY_DOCROOT`
- `DEPLOY_BUILD_DIR`
- `DEPLOY_BACKUP_PREFIX`
- `DEPLOY_FTP_USER`
- `DEPLOY_FTP_PASS`
- `DEPLOY_PROTOCOL`

## Build-step status
There is currently no committed build command in this canonical tree that regenerates the live static export.

Treat that as an active migration gap, not as implied functionality.

## Required root .htaccess
The deployed export root must include:

```apache
Options -MultiViews
DirectoryIndex index.html
RewriteEngine On
RewriteRule ^(.+)/$ /$1 [R=301,L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME}.html -f
RewriteRule ^(.+)$ $1.html [L]
ErrorDocument 404 /404.html
```

Without this, clean URLs like `/services` and `/the-famtastic-way` will fail.

## Backup convention
Before cutover, rename the current live docroot using:
- `public_html_backup_YYYYMMDD_HHMMSS`

Example used on 2026-06-19:
- `public_html_backup_20260619_130444`

## Production cutover sequence
1. Confirm live target still resolves to the expected host.
2. Prepare the deploy payload locally.
3. Confirm the deploy payload you intend to upload is the verified static export for this site.
4. Ensure export root includes `.htaccess`.
5. Authenticate to the real Designs host.
6. Rename current `public_html` to a timestamped backup.
7. Create a fresh `public_html`.
8. Upload the entire contents of the verified deploy payload into `public_html/`.
9. Smoke-test the live site.
10. Only after verification, consider cleanup of stale local or remote surfaces.

## Smoke-test URLs
These URLs were verified as live 200s on 2026-06-19 and should be checked after future deploys:
- `/`
- `/contact`
- `/services`
- `/the-famtastic-way`
- `/serve/nonprofits`
- `/gallery`
- `/proof/sample`
- `/claim/sample`

## Rollback
If the new deploy is bad:
1. remove or rename the broken `public_html`
2. rename the latest `public_html_backup_*` back to `public_html`
3. re-test the live URLs

## Known production history
- Old live stack was Drupal in `public_html`
- On 2026-06-19 the site was cut over to the static exported marketing build
- Temporary FTP users were created only to perform cutover and then removed

## Recommendation on doc location
This runbook belongs in the site repo because it is site-specific production truth.

If a broader FAMtastic ops index is needed later, add a short pointer there that links back here. Do not make the global doc the only source of deploy truth for this site.
