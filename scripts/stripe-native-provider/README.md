# Native Commerce / Stripe synthetic bridge

This is a **test runner**, not a payment engine or an agency gateway. It exercises
the repository-locked `commerce_stripe` `stripe_payment_element` plugin in a new
SQLite installation. Agency modules, private16/17 allowlists, customer fixtures,
source settings and databases are not copied. The amount is a synthetic $199 USD;
it is not either client's order, charge, settlement or launch approval.

## Boundaries

- Explicit dedicated profile/account and test-only key. Stream only that profile's
  test field; never dump the CLI config. Recheck account through the actual SDK key
  and balance `livemode:false` before the first write. Existing keys are not rotated.
- Refuse any configured classic or v2 event destination, including incomplete
  inventories. Another installation must not receive these order IDs. Register,
  delete and disable operations are not part of this runner.
- One anonymous synthetic native order; one Visa test fixture; no raw card data.
  No customer object, receipt address, real identity, real merchant configuration,
  API version upgrade or hosted checkout activation.
- Keys and own-run signed payload pass through memory/stdin only. Native plugin
  configuration is changed in memory and never saved with credentials.
- Other PHP networking/mail is disabled; Drupal Guzzle is blocked; only the guarded
  Stripe SDK can reach the exact HTTPS host and allowed request shapes. TLS remains
  enabled; proxies/connected-account headers are refused. Automatic SDK retries off.
- Exact amount/currency, synthetic metadata, expected PI/event IDs, signed callbacks,
  15-minute lifetime, 35-request bound and stable intent/refund idempotency keys.
- Pre-dispatch journals are checked, locked, flushed and fsynced in `.artifacts`,
  outside disposable cleanup. Unknown outcomes require reconciliation; never restart
  a run merely to erase an uncertain result. Lookup by recorded run/intent first.
- SIGINT/SIGTERM stops subsequent phases and terminates the active child. Already
  dispatched network requests may still finish. SIGKILL cannot guarantee cleanup;
  no secret files exist and external journals survive the temporary runtime.
- The callback is genuinely forwarded by Stripe CLI over loopback and then passed
  to native `onNotify`. This is **not** hosted Apache middleware or browser proof.
- Successful native payment, replay, captured receipt and native full refund are
  separate assertions. The new bounded negative scenarios prove non-settlement,
  signed event handling/replay and exact test-intent cleanup, not browser challenge
  completion, browser abandonment detection or a customer recovery experience.
  Full private checkout, uncertainty recovery, agency entitlements, MySQL and the
  12+4 catalog matrix remain later gates.

## Run

```sh
node --test scripts/stripe-native-provider/test.mjs
FAMTASTIC_BACKEND_VENDOR=/absolute/matching/backend/vendor \
  php scripts/stripe-native-provider/test-guard.php

FAMTASTIC_BACKEND_VENDOR=/absolute/matching/backend/vendor \
FAMTASTIC_STRIPE_TEST_PROFILE=famtastic-sandbox-auth \
FAMTASTIC_STRIPE_EXPECTED_ACCOUNT=acct_1TqwE9DDGtWR2WVN \
  node scripts/stripe-native-provider/run.mjs --offline

# Only for the explicitly authorized finite synthetic-provider test:
FAMTASTIC_BACKEND_VENDOR=/absolute/matching/backend/vendor \
FAMTASTIC_STRIPE_TEST_PROFILE=famtastic-sandbox-auth \
FAMTASTIC_STRIPE_EXPECTED_ACCOUNT=acct_1TqwE9DDGtWR2WVN \
FAMTASTIC_STRIPE_NATIVE_TEST=1 \
  node scripts/stripe-native-provider/run.mjs

# Same explicit profile/account/vendor and test opt-in as above; one case at a time:
# node scripts/stripe-native-provider/run.mjs --scenario decline
# node scripts/stripe-native-provider/run.mjs --scenario action-required
# node scripts/stripe-native-provider/run.mjs --scenario abandonment
```

Default invocation refuses before providers. `--offline` proves the fresh native
installation and a fake-key nested-gateway reload with networking disabled, without
real credential resolution or provider access. The existing broad
`stripe-provider-e2e.sh` remains a scaffold; this narrower runner does not relabel it.
Do not enable network on `test-private-purchase-drupal.php` or weaken protected
staging's503 refusal. No rendering/screenshot or real customer mail is performed.

Negative scenarios use only official PaymentMethod fixtures, never raw card data:
`pm_card_visa_chargeDeclined`, `pm_card_threeDSecure2Required`, or no confirmation
at all for abandonment. A definite402 decline is journaled only after exact
test-mode/intent/run/order/store/amount/error binding; other unknown/error outcomes
still require reconciliation. Failed/unfinished intents must show zero received
amount, native draft/full balance, no payment and no captured receipt. Replay must
preserve those states. Exact unpaid intents are canceled and freshly read back;
this is test cleanup, not native order cancellation or a new production feature.
Native `requires_action` event handling is an ignored event; asserting no payment
does not claim the plugin implements challenge UX. Preserve each phase snapshot.
Any known intent without proved refund/cancellation is reconciliation-required.

`refund-failed-test.mjs <recorded-native-probe-run-id>` is only an explicitly opted-in
cleanup for a failed synthetic run that already created a payment. It rechecks
account/test mode, exact run/amount/order/store metadata, no real receipt/customer,
empty destination inventories and existing refunds. It never creates a payment.
Use `FAMTASTIC_STRIPE_NATIVE_CLEANUP=1` plus the same explicit profile/account.
Each attempt/receipt is immutable; replay of a completed refund makes no write.
Its receipt is CLI cleanup, not native Commerce refund acceptance.

References: [Stripe local webhook testing](https://docs.stripe.com/webhooks#test-locally)
and [Stripe testing](https://docs.stripe.com/testing). Preserving the existing native
plugin/locked SDK is an explicit task constraint, not a recommendation for new APIs.
