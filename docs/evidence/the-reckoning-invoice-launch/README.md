# The Reckoning invoice-to-launch evidence

## Fritz review gate — 2026-10-02

The implementation and exact customer materials are prepared for Fritz's review. The package under `review-package/` includes the exact staging release, page checklist, invoice preview, release evidence, and branded Shay email copy. Its normalized manifest digest is `b6816a4ba1b76726e6bb33098a51e8b708526f6db100caa20ddcffdb1e6af14d`.

- Raw review manifest SHA-256: `df11cf067ee1c984738a1c2ace5dc40066bfb7e77ce4f6054af26087968bdd73`
- External-stage import packet SHA-256: `cb12cd61de2725fff7a6e5f223db65c7c421e5bf8ccb3c5feb86dd37b6cc2012`
- Build DNA SHA-256: `14c0ed23a3c4df16a34de36f33cc405c5e564a86ac83b897056b25378adea9c9`

The Build DNA and external-stage import packet are complete, but they have not been registered or attached to Kofi's account. Invoice `TR-KAO-001` has not been issued. No Kofi-specific account label, customer record, outbox row, email, payment, hosting-access record, DNS setting, or owner-host deployment was changed. `post-review-activation.md` records the exact activation order after Fritz approves the finished package.

## Stripe sandbox rehearsal — 2026-10-02

The exact `$100.00 USD` amount passed provider-side Stripe TEST checks for success, decline, an unconfirmed abandoned intent, cancellation, and full refund. The source receipt is `stripe-test-evidence.json`; it contains test object identifiers and no key, card data, customer identity, or live-mode object.

This provider rehearsal does not prove Drupal Commerce linkage, signed webhook replay, immutable invoice activation, receipt dispatch, portal state, a real customer payment, or revenue. Those require the separate application and live evidence described in the release record.

Application checks cover exact cent arithmetic, scope mismatch, amount mismatch, duplicate payment evidence, refund replay, payment-gated hosting access, and secret rejection. The live customer invoice remains `issued` until an exact completed Commerce payment supplies matching account, request, order, amount, currency, provider, and provider-event evidence.
