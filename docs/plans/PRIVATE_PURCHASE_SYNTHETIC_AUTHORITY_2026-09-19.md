# Private purchase authority separation — source-only increment

## Purpose and immutable boundaries

Continue after the completed native response/callback recovery proof; do not rerun
those provider cases. Existing private16/17 fixtures mirror actual customer identity
and remain entirely network-disabled. No provider calls, customer mutations, checkout
activation, production deployment, new credentials or alternative payment engine are
part of this increment.

The existing private purchase service embedded its one reunion authority alongside
scope/selection/order validation. A trusted code dependency now supplies that closed
identity. Production `ApprovedPrivatePurchaseAuthority` retains exactly the same
request/customer/organization/email, owner authority, scope hash, policy, SKU and
event key. It is final, has no runtime settings/env/request selector, and is the only
implementation wired in production YAML. Stock prepaid authority remains unchanged.
The amount199USD,store1,no-recurring,no-client-acceptance/no-launch and source/scope/
member/account/offer/gateway guards remain. This is not a public custom-price API.

`PrivatePurchaseService` validates and freezes the dependency's exact typed snapshot
per instance. Default constructor and legacy static scope/selection wrappers retain
production behavior. Injected workflows use instance `assertScope`/`selectionSnapshot`.
The actual form and all three native guard paths consume the same container service.
The durable offer lookup that protects orders with stripped metadata uses the same
injected request ID; it is not bypassed for tests. Resource claims use organization
ID, never an assumed-equal customer ID.

## Separate synthetic consumer

Only the explicit `test-selected-staging-drupal.sh --private-purchase-synthetic`
mode copies the test module from `scripts/private-purchase-synthetic/` into a fresh
disposable SQLite installation. It is outside the deployed application module tree.
No production allowlist override, real request UUID, real account email, owner approval
impersonation or copied private scope is allowed. Test accounts use .test addresses,
new source/campaign/selection records and different customer/organization IDs.
PHP networking/mail and normal Drupal transports stay blocked; no Stripe key is read.

Verified assertions: default rejects synthetic and injected rejects production scope;
authenticated ownership/membership; exact current selection/source/hash; real native
order creation/rollback/replay; missing metadata guard; disabled feature/saved gateway;
no payment/delivered receipt; fresh-process persistence. Synthetic native form evidence
is not HTTP/login/CSRF or provider proof. Original default HTTP tests remain separate.
Executed-source hashes must include authority classes, service YAML and test module.

## Remaining gates

No synthetic provider context is authorized by simply passing these offline checks.
It still needs reviewed exact test account/credential/transport/object binding, real
authenticated HTTP/browser3DS/failure/return and partial native-fulfillment recovery,
agency receipt/entitlements, hosted middleware/MySQL and full12+4 product coverage.
Current portal private-link/catalog exclusions intentionally still use production
constants; synthetic direct-form/native proof is not portal/catalog parity.

Client delivery remains first:10:23UTC Drupal reads8/16/17 customer_ready,unselected,
staging not_started;767/769/772/773/775 sent once. Actual10:20:03UTC cron is CLI
observe_only,zero mutations/enrollment/reservations. CheckoutOFF; deployed378c3d86
and mainceee698a unchanged. No proof regeneration, resend or inferred acceptance.

## Evidence checkpoint

Default-authority regressions passed:

- PHPunit344 tests/2587 assertions, existing deprecation reports;
  `.artifacts/selected-staging-drupal/20260919T103057Z-2211/phpunit.xml`.
- Default native36 checks, including rollback and separate-process persistence;
  `.artifacts/selected-staging-drupal/20260919T103713Z-5199/private-purchase.json`.
- Default HTTP41 checks, actual cookie login/forms/CSRF/native owner entry;
  `.artifacts/selected-staging-drupal/20260919T103205Z-2349/private-purchase-http.json`.
- Installed customer-journey skill's full fresh SQLite/memory-mail/fixture-domain
  lifecycle and frontend build passed; `.artifacts/fresh-customer-proof/`
  `fresh-customer-proof-20260919T103325Z-3198/evidence.json`.

The default native/HTTP receipts bind the executed authority/service/form/guard/YAML
hashes, not merely the pre-edit Git HEAD. Canonical journey uses signed synthetic
payment and local deployment; it is not real merchant/provider evidence. Build
reported its existing unresolved portal hero asset and large-chunk warnings.
New synthetic-native fixture passed65/65 checks (59 initial,6 fresh-process) in
`.artifacts/selected-staging-drupal/20260919T104518Z-7595/private-purchase-synthetic.json`.
Actual container override, separate customer/organization identity, native form,
one unpaid order/replay, rollback and all three native guard entry points are covered.
No payment, captured receipt/mail, job, fulfillment or entitlement was created.
The separate process reloaded the same binding/order and exact17 source hashes.

Retained failed attempt:20260919T104238Z-6419 stopped after8 checks at strict binding
validation, before purchase. Parent integration used an earlier in-progress module
read and changed the seed suffix unnecessarily. Restoring the seed to the final
module's exact `.test` domain fixed the fixture; no guard or application constraint
was weakened. Failed evidence remains failed. Re-read delegated files after the
worker's final handoff before applying integration edits.

Production recheck10:41UTC:8/16/17 still unselected, notices still sent once;
actual10:40:03UTC observe-only tick reports zero mutations/reservations. Independent
production source review found no defects. Independent synthetic review verified
65 strict-true checks, matching log, all17 executed-source hashes, zero financial/
delivery side effects and unchanged guards between failed/passing attempts. It did
not rerun tests or independently verify cleanup; parent observed exit0. Evidence:
`docs/evidence/private-authority-synthetic-20260919.json`.
Feature source only; checkoutOFF, no deployment.
