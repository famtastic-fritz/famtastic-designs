# Visual DNA release — September 17, 2026

Owner explicitly requested: “Please Commit and push live the site changes please!”

Approved scope: existing public, client portal and admin branding, canonical
source-derived crown/favicon family and selective public cursive headings.
Implementation commits: 06b55b0a, ed87204b; social export tooling 3270c828 is included
as source only, without updating any social accounts. Existing email rendering is
unchanged; no email send, customer acceptance, payment or customer-site deployment.

Pre-release: clean branch, fetched main has no incoming commits; shared assets,
34 portal DNA assertions, admin contract, metadata stub and 35 non-sending email
assertions pass. Browser tests are not Gmail/Outlook certification.

## Production receipts

Both runtime markers verified by SSH: `9d4e000d4294461808f5a69068af778320f6b568`.
Frontend deployed 2026-09-17T16:58:55Z; backend 2026-09-17T16:59:13Z.
Git main and feature branch were fast-forward pushed before either apply.
Historical review-only notes describe the earlier milestone, not current status.

Existing deployment scripts created frontend, module, theme, dependency,
commercial-config and database backups under `/home/xrdj7j99xhzt/backups/`.
Frontend backup prefix: `famtastic-frontend-20260917T165719Z-9d4e000d`.
Backend/database backup timestamp: `20260917T165601Z`, same full SHA.
Use documented restoration procedure; database restore is not implied by code rollback.

Production evidence:
- Apex and www browser checks at390/1440: three cursive phrases, font loaded,
  no horizontal overflow or page exceptions; source-derived icon links present.
- Apex logo/navigation acceptance at320/390/768/960/1100/1440 passes.
- Anonymous Drupal login emits only agency ICO/PNG/apple icons, no legacy core icon.
- Authenticated existing-owner portal and Operations Home load original2172px
  logo and new material styles; desktop has no horizontal overflow. These are
  presentation checks, not ordinary-customer lifecycle or payment acceptance.
- Font and32px icon return200 with font/woff2 and image/png respectively.
- No pending database updates; durable pilot lock remains0 before/after.
- Node22.23.2 build succeeds; existing bundle-size warning remains.

Local evidence: `.local-email-preview/live-brand-release-results.json`,
`live-dna-logo-results.json`, `live-dna-*.png`. Existing logo remains immutable.

The first frontend attempt started while the shared server checkout was incomplete;
it failed at npm ci before promotion. After checkout completion the retry succeeded.
Serialize shared-checkout initialization on future releases. Drush updatedb emitted
a cold-start nonzero status; the deploy guard and independent post-release command
both confirmed no pending database updates before declaring success.

Remaining unrelated operational notices: authenticated admin still displays the
previous Drupal security-update warning and an aged notification-queue alert.
Neither was repaired or hidden by this branding release. No email was explicitly
sent by this task; the existing scheduled lifecycle remains enabled unchanged.
Social profile PNG exports remain local/manual-upload assets.
