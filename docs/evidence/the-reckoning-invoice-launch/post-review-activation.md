# Post-review activation — not executed

This is the exact order after Fritz approves the finished package. None of these customer-specific actions are part of the code deployment.

1. Confirm request `fb3c723f-072b-4458-a91f-e3dea54f840c` still belongs to customer `13`, organization `13`, and verified account `info@kofioliverphotography.com`.
2. Correct the account display name to `Kofi A. Oliver` and workspace label to `The Reckoning` while preserving both authorized email identities. Record the prior and final values before continuing.
3. Register Build DNA `the-reckoning-owner-switch-aef343a-20261002`.
4. Attach the private full-site review package using its raw manifest checksum and exact package directory.
5. Attach `external-staging-import.json` using its file checksum. The resulting immutable staging-receipt hash becomes the invoice binding.
6. Verify Kofi's authenticated portal shows the exact release, nine-page checklist, consolidated feedback action, and no invoice yet.
7. Issue private invoice `TR-KAO-001` against that exact staging-receipt hash.
8. Verify the private invoice shows `$499.00`, a `$399.00` sponsorship credit, `$100.00` due, owner-hosted terms, and one card action. Checkout must remain locked until Kofi accepts the exact release.
9. Render the final standard/v2 message with the generated invoice URL. Compare its subject, both authorized recipients, body, primary payment button, secondary staging and portal links, transparent branding, and Shay signature to the approved preview.
10. Send only after Fritz approves this package. Dispatch only the two invoice-specific outbox keys. Record each outbox row, provider Message-ID, and provider acceptance separately from inbox delivery.

Payment remains customer-initiated. Payment success must come from a matching completed Drupal Commerce payment with Stripe provider evidence. It may open the hosting-access checklist; it may not change DNS, deploy to Kofi's host, activate his Stripe account, or expose credentials.
