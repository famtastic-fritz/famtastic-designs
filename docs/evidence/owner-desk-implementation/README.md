# Booking Owner Desk verification — 2026-09-13

Scope: local source implementation and reusable capture. No production deployment, real customer write, provider delivery, paid activation or live calendar synchronization performed.

## Verified

- PHP 8.5.9 / PHPUnit 11.5.56: appointment service, routing, owner verification, owner routing and request outbox suites: **35 tests, 200 assertions** passed. SQLite database lifecycle tests include rollback on outbox failure, expiring holds, idempotency and token revocation; mock advisory locks do not prove real concurrent production contention.
- Node 22.23.2: owner time conversion, exact vendor parity, appointment privacy and login return: **8 tests** passed.
- Designs production frontend build passed (existing large-chunk advisory remains).
- Owner browser fixtures at **390/768/1280** passed. Mobile tabs were corrected after visual inspection; actual top and bottom viewports confirm accessible controls and navigation clearance. Fixture API is not live backend proof.
- Component Studio: **17 tests** passed, including exact captured-file hashes and narrow booking recipe readiness boundaries.
- Site Studio Next: recipe discovery and kernel component inventory: **11 tests** passed. Discovery is GET-only; no runtime application import is claimed.
- Client Portal Design DNA: **34 checks** passed. Navigation inventory: **20 portal / 27 Drupal sources** passed.
- Canonical fresh-customer proof: PASS in an isolated disposable runtime, with memory mail, fixture DNS, synthetic payment webhook and sandbox deployment. Sanitized summary: `customer-journey-summary.json`. This broad regression used an earlier in-turn dirty source snapshot, not the final booking source; focused tests above cover final changes.

## Reproduce

Use Node 22 for Designs. Run `node --test frontend/tests/owner-desk-time.test.mjs frontend/tests/owner-desk-vendor.test.mjs frontend/tests/appointment-privacy.test.mjs frontend/tests/portal-return.test.mjs`, then `npm --prefix frontend run build` and `node frontend/tests/booking-owner-inbox.test.mjs`. Set optional `COMPONENT_STUDIO_DIR` for cross-repository parity verification. PHP suites live in `backend/web/modules/custom/famtastic_pipeline/tests/src/Unit/` and use `backend/web/core/tests/bootstrap.php`.

Run `npm test` in Component Studio. Run `npm test -- tests/component-recipe-discovery.test.js tests/kernel-component-inventory.test.js` in Site Studio Next.

Final browser screenshots are local temporary artifacts under `/var/folders/4z/76l8zpns7hvdlykrk2fkf8wr0000gn/T/booking-owner-qa-mK0GKT/`; durable source tests regenerate them.

## Release gates not satisfied by this evidence

Production deployment and inherited schema migration, production-equivalent lock contention, real owner account/site binding, real proposal email delivery and customer response, external calendar synchronization, personal blocks, teaching/classes/LMS. The owner route `/portal?section=booking` is implemented source, not a newly verified live endpoint.
