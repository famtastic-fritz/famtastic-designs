# FAMtastic Designs — Real Stripe Test-Mode Activation Report

## Drupal Commerce sandbox path (2026-08-10)

The canonical Commerce sandbox is configured without checking credentials into
Git:

1. Authenticate Stripe CLI to the dedicated FAMtastic sandbox project.
2. Run `scripts/stripe-sandbox-catalog.sh` to synchronize the 14 SKU-keyed test
   products and prices. The script refuses a Stripe account reporting
   `livemode=true` and is safe to rerun.
3. Export the test-only Commerce Stripe credential variables at runtime.
4. Set `FAMTASTIC_STRIPE_GATEWAY_ENABLED=1` only during an attended sandbox
   checkout, then run
   `drush php:script scripts/setup-commerce-stripe-sandbox.php` from `backend/`.
5. Forward test events to
   `/payment/notify/famtastic_stripe_sandbox` and complete checkout with an
   official Stripe test card.
6. Verify the Commerce order and payment both report `completed`, the amount and
   currency match, and every forwarded webhook returns HTTP 200.
7. Disable the gateway when the attended test window closes.

Acceptance evidence recorded on 2026-08-10: one browser checkout for
`FAM-FOOT-199` completed at USD 199.00, Stripe returned a test PaymentIntent,
and the signed webhook deliveries were accepted. No live key or card was used.
This proves payment-provider integration in sandbox; it does not yet prove the
production deployment, fulfillment adapter, recurring renewal, refund, decline,
or customer-notification paths.

Branch: `feat/famtastic-prospect-pipeline-v1` — not pushed, merged, or deployed.
Mode: **Stripe TEST mode only.** No live credentials were used; `--live` was
never passed; live keys are hard-refused.

## Definition of done — MET

A genuine Stripe-hosted test checkout was completed by the account owner, and
the **real Stripe `checkout.session.completed` webhook marked the Drupal order
paid and unlocked the intake.** Not a stub — a live test-mode round-trip.

## Credential handling

- All Stripe access used the **authenticated Stripe CLI** (`stripe login`).
- The Stripe **secret key** and the **webhook signing secret** were provided to
  the running Drupal process via **environment variables only** — never written
  to any project file, never printed, never committed.
- Only the **price id** (not a secret) is stored, in the untracked
  `web/sites/default/settings.local.php`.
- The secret key was extracted read-only from the CLI config and guarded: any
  value containing `live` aborts.

## Stripe test resources (idempotent; created via `scripts/stripe-setup.sh` CLI mode)

| Resource | ID | Detail |
|---|---|---|
| Product | `prod_UuqjhoqtsycmAI` | "FAMtastic $199 Launch Site" |
| Price | `price_1Tv12SDDGtWR2WVNTOMjhh7L` | $199.00 USD, one-time, active |

Metadata on both: `famtastic_product=launch_site`, `famtastic_package=basic_199`,
`environment=test`. Re-running `stripe-setup.sh` reuses them (no duplicates).
A duplicate price created during a flag-fix was deactivated so exactly one active
$199 price remains.

## Live test-mode round-trip (verified)

1. Backend served the real `StripeGateway` (verified through the Vite proxy —
   the exact browser path): checkout returned a `cs_test_…` session redirecting
   to `checkout.stripe.com`.
2. Owner paid on the Stripe-hosted page with test card `4242 4242 4242 4242`.
3. **Stripe sent `checkout.session.completed`** — event `evt_1Tv1jYDDGtWR2WVN76O3syEy`,
   forwarded by `stripe listen` to `/api/pipeline/stripe/webhook`.
4. **Signature valid** → webhook responded **`[200]`** (an invalid signature
   returns 400; verified independently in unit tests).
5. **Order `pending → paid`** — order #16, real session
   `cs_test_a1nX2v…`, real `payment_intent pi_3Tv1jW…`, `paid_at` set,
   prospect status `paid`.
6. **Duplicate suppressed** — `stripe events resend evt_1Tv1jY…` re-delivered the
   same event (`[200]` again), but `paid_at` and the recorded event list were
   **unchanged** — no second fulfillment.
7. **Intake unlocked only after verified payment** — `POST /api/pipeline/intake`
   returned 402 before payment and succeeded (`intake_id 13`) after.
8. Remaining proof completed on the real paid order:
   - Site Studio **Markdown brief + machine JSON** generated (project #13,
     exported to `private/site-studio-requests/project-13.json`).
   - **Proof URL recorded** on the project.
   - Customer **approved** (`approval_status: approved`).

## Root-cause note (and fix)

The owner's *first* attempt paid through the stub gateway. Cause: a leftover
`php -S localhost:8080` backend from an earlier turn was bound to **IPv6 `::1`**,
while the real Stripe backend bound **IPv4 `127.0.0.1`**. Vite's proxy target
`localhost:8080` resolved to IPv6 and hit the stub. Fixed by killing the stray
backend and pinning the Vite proxy to `http://127.0.0.1:8080`; then verified the
real Stripe session **through the proxy** before the successful second attempt.

## Test results (this activation)

| Check | Result |
|---|---|
| Unit tests | 11 tests, 33 assertions — OK |
| E2E proof (stub regression) | 26 passed, 0 failed |
| Frontend build | built clean |
| Composer validate | valid |
| Composer audit | no advisories |

## Reproduce the real path (test mode)

```bash
# Stripe CLI must be authenticated: stripe login
scripts/stripe-setup.sh                 # → prints STRIPE_PRICE_ID (idempotent)
# store price id in web/sites/default/settings.local.php ($settings['stripe_price_id'])

# Terminal A — forward the event and note the signing secret it prints:
stripe listen --forward-to http://127.0.0.1:8080/api/pipeline/stripe/webhook \
  --events checkout.session.completed

# Terminal B — run Drupal with secrets in the ENVIRONMENT ONLY (never a file):
export STRIPE_SECRET_KEY=<sk_test_… from `stripe config`>   # test key only
export STRIPE_WEBHOOK_SECRET=<whsec_… printed by `stripe listen`>
cd backend && ./vendor/bin/drush runserver 127.0.0.1:8080

# Terminal C — frontend pinned to the IPv4 backend:
cd frontend && VITE_DRUPAL_PROXY_TARGET=http://127.0.0.1:8080 npm run dev

# Create a prospect, open /p/<token>, confirm, Pay $199, use test card 4242…
```
