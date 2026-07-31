# Hosting and renewal lifecycle

The website purchase includes twelve months of hosting beginning only after the
paid project has a deployed release and verified DNS and TLS.

Recurring monthly hosting is a separate transaction. It requires a distinct
recorded consent that states the amount, monthly interval, and first billing
date. The system will not silently convert the original website purchase into a
subscription.

The production billing provider defaults to disabled. The `memory` provider is
only for deterministic acceptance tests. Live subscription creation and charges
remain blocked until pricing is approved, Stripe recurring credentials are
configured, and a live-action approval is given.

Failed month-13 renewals enter `past_due`, retry twice with backoff, and become
`canceled` with hosting `suspended` after the third failure. A successful renewal
moves the entitlement to recurring hosting and schedules the next month.
