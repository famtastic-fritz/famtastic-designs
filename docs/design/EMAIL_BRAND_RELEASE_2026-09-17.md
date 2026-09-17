# Approved email-brand release — September 17, 2026

Owner explicitly approved the rendered email and customer footer, requested docs,
commit/push, live publication and one email to Valerie. This supersedes the local
preview stop in earlier records, not customer acceptance or final site launch.

Approved scope: unchanged logo hosted at `/brand/famtastic-designs-logo-v1.png`,
`customer_staging_review_ready/v1`, exact preview copy to the verified request 9
customer, and the Pros In Training explanatory footer. Existing notifications
remain unchanged. No broad outbox dispatch, checkout, DNS or access changes.

Release is pending until receipts are appended. Actual Gmail/Outlook/Apple Mail
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
