# The Reckoning invoice-to-launch evidence

## Fritz review gate — 2026-10-02

The implementation and exact customer materials are prepared for Fritz's review. The package under `review-package/` includes the exact staging release, page checklist, invoice preview, release evidence, and branded Shay email copy. Its normalized manifest digest is `2dc052dd8d600fda3975fc468b282a43db320bc6d9c27cb8fa5f543f87d8afa4`.

- Raw review manifest SHA-256: `55a8cebc4c8af8ff84436b2e0906ed723c03c49d9b58731626033e641cccea42`
- External-stage import packet SHA-256: `bc531287bb40df2f69649557ee4ac058e6eb998e250d726000676cab4ac4c0b9`
- Build DNA SHA-256: `3f8f3555728b8dfe34c968cdbc1492d9b3c63ea712882819823bbf58c99cc262`

The Build DNA and external-stage import packet are complete, but they have not been registered or attached to Kofi's account. Invoice `TR-KAO-001` has not been issued. No Kofi-specific account label, customer record, outbox row, email, payment, hosting-access record, DNS setting, or owner-host deployment was changed. `post-review-activation.md` records the exact activation order after Fritz approves the finished package.

## Stripe sandbox rehearsal — 2026-10-02

The exact `$100.00 USD` amount passed provider-side Stripe TEST checks for success, decline, an unconfirmed abandoned intent, cancellation, and full refund. The source receipt is `stripe-test-evidence.json`; it contains test object identifiers and no key, card data, customer identity, or live-mode object.

This provider rehearsal does not prove Drupal Commerce linkage, signed webhook replay, immutable invoice activation, receipt dispatch, portal state, a real customer payment, or revenue. Those require the separate application and live evidence described in the release record.

Application checks cover exact cent arithmetic, scope mismatch, amount mismatch, duplicate payment evidence, refund replay, payment-gated hosting access, and secret rejection. The live customer invoice remains `issued` until an exact completed Commerce payment supplies matching account, request, order, amount, currency, provider, and provider-event evidence.
