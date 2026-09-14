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
- [ ] Frontend release, apex/www browser checks and owner entry.
- [ ] Safe production backend/authorization smoke and public-site regression.
- [ ] Instruction emails and provider receipts.
- [ ] Final evidence and documentation mirror.

Backend and frontend markers may name different exact source commits when the follow-up changes only frontend presentation and diagnostic/documentation files. Verify deployed runtime files against each intended source; never rewrite a release marker to pretend another SHA was deployed.
