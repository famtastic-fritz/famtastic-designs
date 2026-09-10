# Protected FAMtastic Designs staging

This environment is a private rehearsal of the complete FAMtastic Designs
command center. It is not production and it never uses production customer
data, payment, mail, scheduler, or production deployment providers. Its
isolated cPanel host and authoritative GoDaddy staging DNS are infrastructure,
not permission to operate on production.

## Infrastructure contract

- Host: `staging.famtasticdesigns.com`
- Root: `/home/<cpanel-user>/famtastic-staging`
- Document root: `<root>/current/public`, never `public_html`
- Source: one exact clean commit already present on the configured remote ref
- Database: fresh cPanel database created with the `fdstg` prefix; its 0600
  cPanel JSON receipt remains server-private
- Storage: dedicated files, private, mail-capture, secrets, backup, and release
  directories
- DNS: authoritative GoDaddy record; cPanel zone data is not authority
- Access: valid TLS; optional HTTP Basic Auth is configurable for sensitive
  rehearsals, but the owner-review default is no extra browser-level password.
  Drupal and customer accounts keep their normal application authentication.
- Release: immutable SHA directory plus atomic `current` symlink and rollback
  receipt
- Scope: the command-center application and Drupal runtime. Large public film,
  showcase, and campaign-creative libraries are production marketing assets,
  not command-center dependencies, and are excluded from this capacity-limited
  protected environment rather than linked to production storage.

## Application safety matrix

Protected staging must assert all of the following before review:

- `famtastic_protected_staging=TRUE`
- disabled payment mode and `DisabledPaymentGateway`
- request-level 503 refusal for native Commerce, custom checkout, revision
  checkout, simulation, and webhook routes
- `system.mail` default set to `famtastic_blackhole`
- direct outreach exits to digest-only capture before transport selection
- SMTP, Stripe, Site Studio, deployment, DNS, hosting, and customer transports
  unset or disabled
- cron exits before lifecycle, outbox, SLA, or provider work; no staging cron is
  installed

## Hosted incident checkpoint — 2026-09-10

The dedicated host/document root, database, files, private storage, and
staging-only secrets were created without touching production. The first
hosted passes established these additional release constraints:

- repeated SSH resets require scoped gzip artifacts split into 512 KiB chunks,
  SHA-256 verification for each chunk and the reassembled archive, and resume
  of missing or mismatched chunks only;
- the account has a 250,000-inode cap, so the deployer checks headroom before
  upload and Composer work, and retained material is archived before deletion;
- the fresh Drupal/Webform runtime requires a stage-only 512 MB PHP limit
  rather than the host's 128 MB default;
- Apache must be able to traverse the directory containing the bcrypt Basic
  Auth file; the password source remains in private staging secrets;
- CSP must include the observed inline Drupal script hash
  `sha256-CaN42Zi+a+oATitdYvGRVlyS6mCZIxrLFXhTbgp6HCI=`, the required inline
  application style allowance, `https://fonts.googleapis.com` for styles, and
  `https://fonts.gstatic.com` plus `data:` for fonts;
- non-file, non-directory frontend routes outside `/web` must fall back to
  `/index.html` so direct SPA entry works;
- clean installation must install and verify `commerce_checkout` before
  enabling `famtastic_pipeline`.

Sanitized customer API and authenticated browser evidence passed login,
Operations Home, and Portal at 390, 768, and 1280 pixels. At the time of this
checkpoint, the final exact-source redeploy, hosted runtime no-side-effect
checks, and rollback rehearsal were still pending. The environment is not yet
release-verified and the capability registry must not be upgraded.

## Release procedure

1. Provision and verify the cPanel subdomain at the exact protected docroot.
2. Create or verify the authoritative GoDaddy A record.
3. Publish an isolated ACME webroot, issue/install TLS, and verify the hostname
   appears in the certificate SAN.
4. For a sensitive rehearsal, opt into stage-only Basic Auth and keep its
   credentials outside Git. Leave it disabled for ordinary owner review.
5. Push the reviewed integration commit.
6. Run `scripts/deploy-protected-staging.sh --preflight` against that exact ref.
7. Run `--apply` only with `DEPLOY_PROTECTED_STAGING:<exact-SHA>` and all explicit
   staging paths and credentials.
8. Verify unauthenticated 401, authenticated HTTPS 200, noindex/CSP headers,
   branded Drupal routes, responsive portal views, and the exact release SHA.
9. Probe every payment/mail/cron boundary and compare database/outbox counts
   before and after. Any change or provider receipt fails the release.
10. Record the release and rollback receipts. Rehearse rollback on staging only.

No protected-staging step authorizes production deployment, customer messages,
charges, domain cutover, or production scheduler changes.
