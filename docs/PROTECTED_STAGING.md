# Protected FAMtastic Designs staging

This environment is a private rehearsal of the complete FAMtastic Designs
command center. It is not production and it never uses production customer
data, payment, mail, scheduler, DNS, hosting, or deployment providers.

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
- Access: valid TLS followed by Basic Auth; ACME challenges remain reachable
- Release: immutable SHA directory plus atomic `current` symlink and rollback
  receipt

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

## Release procedure

1. Provision and verify the cPanel subdomain at the exact protected docroot.
2. Create or verify the authoritative GoDaddy A record.
3. Publish an isolated ACME webroot, issue/install TLS, and verify the hostname
   appears in the certificate SAN.
4. Create stage-only Basic Auth credentials and keep them outside Git.
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
