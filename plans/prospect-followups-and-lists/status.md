# Prospect follow-ups and staff lists — 2026-09-14

## Scope and authorization

Fritz requested branded follow-ups for his son, DTravelAddics and Valerie, each with owner-selected special discounted-pricing language and a Shay sign-off. He also requested reversible prospect archive/completed lists. Existing customer records determine recipients and continuation links; request numbers and prospect numbers are separate identifiers. No discount amount, deadline or payment state is inferred.

## Customer communication evidence

- Son: website request 8 belongs to prospect 21. The existing conversation now asks for business name, services/area, preferred customer action and assets. Message 22 / exact outbox 635 was SMTP-accepted once on September 14, with no broad queue drain.
- Valerie / Pros In Training: website request 9 belongs to prospect 30. Three customer-visible concepts were ready with no selected direction. Message 23 / exact outbox 636 was SMTP-accepted once and points to her existing conversation and linked proofs.
- Both messages say Fritz personally selected the project for special discounted pricing, with a scoped quote confirmed before payment, and end with Shay / FAMtastic Designs. Delivery to the recipient inbox or a customer read is not claimed.
- DTravelAddics: no confirmed match in Drupal prospects, customer requests/intakes, repository records, Gmail or 271 older hello-mailbox files. A name/email/site clarification is pending; no message has been sent to an inferred address.

Private raw recipient records, exact previews and provider receipts are in ignored `.artifacts/outreach-20260914/`. No private mailbox dump is committed.

## Staff list implementation — locally proven

Separate Active, Completed, Archived and All tabs preserve the original workflow status. Native staff confirmation forms archive, complete and restore with permission checks, CSRF and an auditable compare-and-set transition. Search/status/date filters apply before pagination. Only active prospects participate in staff first-response/follow-up reminders. Customer messages, project status, proof selections, pricing and payments do not change when moving a lead.

Database update 8064 installs the three staff-placement fields and initializes prior leads to Active. No real prospect has been archived/completed by this task. Production release and acceptance remain pending this checkpoint.

## Exact mail receipts

| Prospect / request | Saved message | Outbox | SMTP acceptance (UTC) | Attempts |
| --- | --- | --- | --- | --- |
| 21 / request 8 | 22 | 635 | 2026-09-14T21:58:59+00:00 | 1 |
| 30 / request 9 | 23 | 636 | 2026-09-14T21:58:59+00:00 | 1 |

Both were accepted at 5:58:59 PM EDT. Provider message IDs and exact customer-visible rendering remain in the private evidence directory. Verified customer-scoped reads found each saved sent message linked to the correct request; the checks rolled back without changing customer read markers. First-response timestamps and an idempotent staff-follow-up event record the authorized contact. The commercial/project stage, quote amount and selection remain unchanged.

## Operator instructions

Open `/web/admin/famtastic/metric/prospects` (the existing `/web/admin/famtastic/prospect` entry shows the same lists). Select Active, Completed, Archived or All. Search by business/contact/email/campaign; status and created-date filters run before pagination. Each row and lead workspace provides Mark completed, Archive and Restore to active as appropriate. Confirm the native staff form to move the record. Completed means staff follow-up is finished; project delivery and payment still have their own statuses.

## Test notes

The full module unit suite passes 217 tests and 1,129 assertions (66 existing PHPUnit deprecation notices); Design DNA passes 34 checks. The disposable SQLite/memory-mail harness rehearses removal and reinstallation of update8064 fields on an existing prospect, then exercises real login, list filters, native CSRF forms, complete/archive/restore, tampered and stale form submission, permission denial, audit history and preservation of all original prospect fields. All 66 HTTP assertions and 14 data/audit invariants passed in `.artifacts/client-messaging/inbox-20260914T221705Z-33536/`.

Local runtime reconstruction must include Drupal's `autoload_runtime.php` and standard scaffold files; otherwise CLI bootstrap can pass while HTTP fails. No credentials or customer files were copied.

## Native form concurrency lesson

Drupal does not preserve ordinary form state on GET, and explicitly enabling that cache on a safe method is rejected. Keep the original list in a session-bound signed form snapshot (record + target + original state), validate it on POST, and compare-and-set in the database. A stale or tampered form cannot overwrite a newer list move. These are real authenticated HTTP assertions, not source-string checks.
