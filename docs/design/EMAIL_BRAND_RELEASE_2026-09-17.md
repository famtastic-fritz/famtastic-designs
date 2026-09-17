# Approved email-brand release — September 17, 2026

Owner explicitly approved the rendered email and customer footer, requested docs,
commit/push, live publication and one email to Valerie. This supersedes the local
preview stop in earlier records, not customer acceptance or final site launch.

Approved scope: unchanged logo hosted at `/brand/famtastic-designs-logo-v1.png`,
`customer_staging_review_ready/v1`, exact preview copy to the verified request 9
customer, and the Pros In Training explanatory footer. Existing notifications
remain unchanged. No broad outbox dispatch, checkout, DNS or access changes.

Release completed; receipts below. Actual Gmail/Outlook/Apple Mail
client rendering remains untested; owner approved proceeding from browser proof.
The normal PHP renderer now defaults to the approved canonical hosted PNG URL;
an explicit invalid override still fails closed. Logo bytes remain unchanged.

Use existing frontend/backend deployment scripts. Customer revisions use the
customer repo's expected-hash/backup-gated `scripts/deploy-footnote.mjs`.
One-off email script `backend/scripts/send-approved-valerie-staging.php` verifies
request/customer/direction binding and hosted site/logo hashes, queues one stable
artifact-scoped key, then calls the existing exact-key dispatcher. Re-execution
of a sent key reports its existing receipt and never sends again.

Next lane: implement agency website logo usage without redesigning Valerie's site
or replacing customer branding. Website layout changes are a separate preview.

## Verified release and send

- Agency source/main/frontend/backend: `20448c71523f0f9d895d8e52a094f91a9bf464f5`.
  Backend release timestamp `2026-09-17T13:29:00Z`; no pending database updates.
  Code, database, dependency and theme backups were recorded by the deployer.
- Frontend verified 216 route shells and root routing. Apex and www return 200,
  render the React app and have no uncaught browser errors in the acceptance check.
- Hosted PNG returns 200, image/png, with unchanged hash
  `ebb0477344132d32e449ba19e2b622921585aa71af0decdbcf8abfbe033fa950`.
- Pros In Training deployed source `e5edab937f43ef1b85fa5dcaa3fda7ca3eeb42d1`;
  index hash `1a0cec52ac1091cd8b48cdb92a30311c5b3c058a5d873504eb8543c87c536bd6`.
  390/768/1280px live browser checks pass, noindex and password-free access retained.
  Customer repository owns its full backup/deployment receipt.
- Exact authorized email: outbox **704**, template `customer_staging_review_ready/v1`,
  request/customer 9 verified; dispatch processed 1, sent 1, failed 0, retried 0.
  Provider Message-ID `<NbgCQL26xpsS33tkMmJdMWWLC9ws7YQZZuqyLw8LGS8@default>`;
  `sent_at=1789651749`. Original approved fixture remains the immutable plain text.
- SMTP acceptance is proven. Inbox placement, reading and final customer site
  acceptance are not claimed. No charging or final site launch occurred.
