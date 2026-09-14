# Owner Desk production release — 2026-09-14

Authority: Fritz explicitly requested deployment as soon as possible and usage emails to himself and Shay. This thread coordinates the release; Customer-Experience remains production-read-only.

## Scope and preflight

- Reviewed and fast-forwarded `0cd25c48` onto main. Previous live frontend `48cc88ce` and backend `a92b4ae1` are ancestors; both canonical deployment preflights passed.
- Canonical current-source customer proof passed: 41 boolean assertions, three proofs, no provider calls. Focused booking suite:35 tests/200 assertions. Local evidence is not production proof.
- Live source of ownership: generated site key `site-dffd4cb9c3aa47fd`, customer11/organization11, verified account UID11. The friendly fixture slug is not the production key. Do not provision a second binding under that slug.
- Confirmed recipient identities from existing Gmail correspondence and live customer records: Fritz and Shay. No new passwords, roles, paid providers or external calendar connections authorized or required.
- Corrected the presentation mapping to the verified generated site key; two focused tests cover real-key Ruby Signal and unrelated-business isolation. Generic captured component remains unchanged.

## Release checklist

- [x] Exact-source integration, preflight and recipient identification.
- [x] Backend migration/release marker and rollback paths. `0cd25c48` deployed at2026-09-14T10:36:21Z; update8062 completed; authoritative pending updates empty. Backups use20260914T103349Z plus the full SHA under the hosting account backups directory.
- [x] Frontend release: `d1b939f6ed619bc2f4c3f95c1bf023a8912dcae8` deployed at `2026-09-14T10:43:41Z`. Apex/www load `index-BqaIifQe.js`; fresh browser checks show no application console errors. Signed-out entry preserves the booking destination. Fritz's existing session stays on Owner Desk after reload at 390px with no overflow and correctly shows no linked owner site; this is isolation proof, not Shay's personal sign-in.
- [x] Safe production backend/authorization smoke and public-site regression.30 diagnostic assertions;5 separate-process synthetic lock assertions passed. All synthetic requests/appointments/events/outbox rows rolled back; no provider calls. Public Locs HTML/JS/CSS stayed byte-identical during release; availability API returns200 and empty windows; anonymous owner API denies access.
- [x] Instruction emails and provider receipts. Shay Gmail message `1a09f83041d03ca5` SENT; Fritz `1a09f8307a4613a8` SENT/INBOX. Provider acceptance is not a read receipt. No credentials included.
- [x] Final evidence and documentation mirror recorded; local Drive mirror is not a verified cloud-sync receipt.

Backend and frontend markers may name different exact source commits when the follow-up changes only frontend presentation and diagnostic/documentation files. Verify deployed runtime files against each intended source; never rewrite a release marker to pretend another SHA was deployed.

## Handoff and limits

Owner entry: https://famtasticdesigns.com/portal?section=booking . Usage: `docs/OWNER_DESK_QUICK_START.md`. Frontend rollback archive: `/home/xrdj7j99xhzt/backups/famtastic-frontend-20260914T104058Z-d1b939f6ed619bc2f4c3f95c1bf023a8912dcae8.tgz`.

Shay uses her existing verified account; her personal password sign-in remains recipient acceptance. No credentials reset or permissions expanded. Production diagnostic work used a reserved synthetic site and rollback, not real customer appointments or dispatched appointment emails. Separate-process resource-lock exclusion is proven, not simultaneous business-transaction load. External calendar sync, payments, SMS and teaching enrollment remain outside this release. Gmail SENT receipts prove provider acceptance, not recipient reading or inbox placement.
