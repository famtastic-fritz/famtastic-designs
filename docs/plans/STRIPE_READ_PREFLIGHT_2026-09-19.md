# Stripe test-profile read preflight — not checkout acceptance

## September19 07:00UTC

The repo's existing `stripe-sandbox-billing-acceptance.sh` and
`stripe-sandbox-catalog.sh` name `famtastic-sandbox-auth`. Read-only discovery
confirmed that dedicated profile, so the currently selected AlreadyBuilt-labelled
profile was not adopted and no new account/login/key was created.

New `scripts/stripe-provider-preflight.mjs` requires explicit profile and expected
account. It invokes only whoami, balance retrieve, then whoami, with fixed arguments
and a child environment limited to HOME/PATH/LANG/NO_COLOR. Ambient API keys,
connected-account overrides, proxies, alternate config paths and runtime hooks are
not inherited. It rejects absent/expired/unknown test credentials, wrong profile,
wrong account, ambiguous/live balance and changed identity metadata. It emits stable
error codes rather than raw CLI errors. It does not read credential values into the
harness or export keys; the installed CLI internally resolves its own credentials.

The authenticated read07:00:23Z returned balance livemodefalse for the exact dedicated
profile/account. It also reports live credentials available in that profile. Thus
this is test-mode read evidence, NOT a test-only secret bundle, mutation authority,
protected runtime, payment, callback, refund or end-to-end Commerce proof. Never
copy the CLI's whole configuration or use this receipt as a later authorization.

Sanitized receipt: `docs/evidence/stripe-read-preflight-20260919.json`.
Source hashes in that initial receipt bind the39-test source over parent34a02eee;
Git records the final tests/docs commit. Final expanded45 offline tests pass, with
subprocess-result sanitization and real wrapper refusal/JSON parsing coverage.
Independent review caught matrix diagnostics preceding JSON on wrapper stdout;
preflight now sends those diagnostics to stderr. The final real wrapper success
was parsed as JSON and is recorded separately in
`docs/evidence/stripe-read-preflight-wrapper-20260919.json`, with final source hashes.
No earlier receipt was silently relabelled. A real invocation
without explicit binding returns2 with `explicit_test_profile_required` before CLI
access. The existing full provider runner still validates12+4 catalog products and
exits2 under opt-in because mutating/native provider execution is unfinished.

Independent re-review cleared the wrapper fix and parser extraction at source
level; parent then verified45/45 tests and actual wrapper JSON success. The review
did not perform provider calls, tests or credential access. Checkout remainsOFF.

## Run safely

```sh
FAMTASTIC_STRIPE_TEST_PROFILE=famtastic-sandbox-auth \
FAMTASTIC_STRIPE_EXPECTED_ACCOUNT=acct_1TqwE9DDGtWR2WVN \
  bash scripts/stripe-provider-e2e.sh --preflight
node --test scripts/test-stripe-provider-preflight.mjs
```

The account identifier is not a key. No secrets belong in arguments, source, evidence
or chat. The read-only command never changes the selected CLI profile or enables a
gateway. Only use the existing authenticated context. Credential expiry is treated
conservatively: refusal begins at midnight UTC on its recorded date.

This supports, but does not complete, the finite work in
[the private provider boundary](PRIVATE_PURCHASE_PROVIDER_BOUNDARY_2026-09-19.md).
Next: an isolated synthetic native-Commerce/provider bridge with exact account/test
mode rechecks, test-only secret resolution, outbound identity allowlist, signed
callback correlation and uncertainty/replay/refund coverage. Do not enable network
on the account-mirroring private fixture or weaken protected staging.

## Boundaries and references

Clients8/16/17 remained unselected at06:55UTC; notices772/773/775 sent once. Real
06:55:03UTC CLI health remained observe_only, zero mutations/enrollment/reservations.
No deployment, payment/order/code, email, selected build or cloud activation.
Production checkoutOFF. No Drupal runtime or prior41/native36/canonical/module/
browser test reruns were needed for this separate read-only tooling; earlier
receipts retain their original scope and source hashes.

Stripe's [CLI reference](https://docs.stripe.com/cli) distinguishes authentication,
contexts and read/write commands. Its [testing guidance](https://docs.stripe.com/testing)
describes test facilities; neither source nor a successful balance read establishes
that our native workflow is complete. Existing locked Commerce/Stripe dependencies
were not upgraded or replaced by this preflight.
