# Private purchase completion — follow-up after e692d890

Latest06:33Z: expanded41 real HTTP checks now also prove the owner reaches native
order information, foreign checkout denial and unchanged financial projections.
The Stripe runner is still scaffold-only; authentication exists, but the isolated
provider path is unfinished. CheckoutOFF. See
[the exact boundary](PRIVATE_PURCHASE_PROVIDER_BOUNDARY_2026-09-19.md).

## September19 06:10Z follow-up — HTTP and native presentation locally proven

The follow-up on `codex/private-purchase-integration-20260919` now passes38 real
authenticated HTTP checks,36 native Commerce assertions and the complete synthetic
journey/frontend build. CUA confirms scoped native branding at320/390/1280px and
the same-paid-purchase page without another charge button. Exact current receipts,
source hashes, retained failures and screenshots are in
[the HTTP/browser checkpoint](PRIVATE_PURCHASE_HTTP_2026-09-19.md).

This supersedes the older local HTTP/browser gap below, NOT provider or hosted
activation gates. Successful gateway checkout, provider/webhook/3DS/failure/refund,
full portal-return flow and deployed Apache/MySQL still need proof. Private checkout
remainsOFF; deployed378c3d86, existing paid order21/payment5 and sent mail unchanged.
The integration checkpoint below is historical evidence, not a second pending merge.

## September 19 integration checkpoint — local, not activated

Follow-up dc3eae6d was cherry-picked as 3b650349 onto current main ceee698a,
preserving deployed378c3d86 and newer routing/service/doc changes. Work continues
on `codex/private-purchase-integration-20260919`; no release or production code
issuance, payment, email or customer state change is implied.

Current implementation binds the producer's canonical scope/content/active assets,
real campaign/variant/project, persisted selected-source revision, actual artifact
hashes, source association and requested changes. A fresh form cannot silently
rebase an old purchase. Missing order metadata cannot bypass the durable private
offer guard at checkout/gateway/placement. Request16 still permits payment after
direction selection, with staging `not_started`; no accepted receipt is invented.

The form uses an account/request-bound HMAC-signed displayed-scope snapshot with
a six-hour lifetime, separately from native Form API CSRF. GET remains non-mutating.
Unconditional FormState caching is invalid on GET in this Drupal version; local
tests caught and replaced that approach, not Drupal's safety check.

Installed native Commerce evidence: **36/36 assertions**, fresh SQLite and a second
PHP process, real custom-price order, injected late transaction rollback, retry
reuse, read-only GET, unchanged prepaid receipt/completion and no new financial or
delivery effects. Native Form API GET builds signed scope and CSRF controls, but
this is **not authenticated HTTP POST/CSRF or browser evidence**.
Receipt: `.artifacts/selected-staging-drupal/20260919T044014Z-76953/private-purchase.json`
(includes tested file hashes and harness hash; HEAD alone precedes local edits).
The sanitized native receipt is also retained in version control at
`docs/evidence/private-purchase-native-20260919.json` for review across machines.

Focused signed-snapshot/service/form tests now pass34 tests/973 assertions,
including the final raw-input and saved-gateway regressions. Portal DNA34
and existing email presentation86 pass. The full synthetic runner now passes:
`.artifacts/selected-staging-drupal/20260919T043524Z-74021/canonical.json`, including
the frontend production build/SEO shells and account/portal/payment-stub/lifecycle
flow. The first strict-offline attempt refused public CMS reads; the next exposed an
isolated settings override pointing at agency.example.test rather than loopback.
Only canonical sandbox frontend settings were corrected. Production legacy
checkout policy was not changed. Failed evidence is retained. The build reports
an existing unresolved portal PNG reference and a large-chunk advisory; this is
not browser or hosted visual approval. Only allowlisted public CMS GETs were
permitted for SEO output; provider transactions and external mail stayed disabled.

Final full module suite: **339 tests/2537 assertions**, exit0 in a fresh isolated runtime,
`.artifacts/selected-staging-drupal/20260919T044446Z-77314/phpunit.xml`. It reports
one deprecation and68 PHPUnit deprecations; these are not hidden as a clean-warning
run. This evidence is for the tested worktree changes, not activation of checkout.

Independent review caught two native-processing seams before that final rerun:
normalized hidden-field defaults could replace an omitted snapshot, and a saved
gateway could become disabled before resumed checkout. Fixed by requiring the raw
submitted snapshot string and freshly validating saved gateway status/plugin on
entry, gateway filtering and placement. The reviewer cleared those source fixes;
native36 includes disabled-saved-gateway denial without provider invocation.
The unchanged selected-staging suite also passed85 installed assertions at
`.artifacts/selected-staging-drupal/20260919T043844Z-76608/evidence.json`.

Read-only production refresh:8/16/17 remain customer_ready with no selected direction
or staging start; outboxes772/773/775 remain sent/attempts1. Real04:35:03Z and04:40:03Z
scheduled CLI ticks remain observe_only, zero queue mutations/reservations. No
second notice, order/payment mutation or live source change occurred in this pass.

Still unproven: native authenticated HTTP/CSRF and customer route middleware,
browser/mobile rendering, gateway/webhook/3DS/uncertain-payment/refund, production
MySQL concurrency and actual client transactions. Checkout remains **OFF**. The
companion `/web/customer/private-purchase/` route must be verified as an allowed
customer path through deployed middleware; do not email admin URLs or claim that
source routing alone proves access.

## Source-only implementation

This follows the completed bookkeeping lane, not a second record operation.
Order21/payment5 and both private offers remain unchanged by this implementation.
No customer email, charge, code issuance, deployment or financial mutation was
performed while implementing these forms. Main owns the production release.

Authenticated Drupal Form API companion route:
`/web/customer/private-purchase/{website_request_public_id}`. Existing Projects
Setup and Billing request cards link to it. It inherits the customer theme,
uses native CSRF/form validation, no-cache responses, plain-text scope items,
44px primary controls and the existing Commerce checkout. It is not a public
coupon page, a new storefront or a second mail/payment system.

### Request17: same paid purchase

The page reconciles the existing native manual receipt and shows $200 received,
$0 outstanding, evidence source and unknown bank date. It shows honest states
for not-issued, expired, consumed and active completion codes. Only an active
code reveals the scope/domain acknowledgment form. GET never issues or emails
a code. POST consumes the account-bound code using `OfflinePrepaymentService`
and updates the same order, with a no-charge action. No recurring authorization,
new order, order placement, client site acceptance or launch is inferred.

The existing staff-only issuance method remains the only code issuer. No code
has been issued in production. Scope version/hash are HMAC-bound to the displayed
form, account and request and revalidated on POST. Codes stay out of URL/query strings.

### Request16: exact $199 private scope

The page reads the durable `private-scope:request:16:class-of-2000-v1` event,
verifies its exact scope hash, authority, customer/org/request and private offer,
and displays the one-time scope. It does not inherit FAM-CUSTOM-1999 or standard
bundle renewal terms. Missing dates/venue/prices/photos/committee/domain remain
customer confirmations, not supplied facts.

After an authenticated client selects a direction and acknowledges this scope,
a POST can create one native draft Commerce order with a custom price-overridden
item ($199 USD). A request row lock and existing-offer/order checks reuse that
order on retry and stop on orphan/conflicting purchases. This method does not
create a payment, initialize a gateway, create a subscription or call Stripe.
The customer then continues to the existing native Commerce checkout.

The private offer binds the order; `request.commerce_order_id` remains empty,
so payment cannot close the selected-staging/revisions workflow. The exact
selection is captured and revalidated on checkout entry, gateway resolution and
placement. Changed/revoked selection or scope fails closed. Gateway filtering
allows only existing enabled native Stripe gateways, not manual/test stand-ins.
Existing gateway mode is not changed or guessed by this implementation.

Catalog fulfillment is deliberately held for this special order: no duplicate
proof/build, generic SKU entitlement, renewal or accepted-site flag. Native
payment events are audited independently (including failures/refunds); native
Commerce remains payment truth. The portal derives special-order paid status
from `isPaid()`, not merely `state=completed`. A completed order with a nonzero
balance requires reconciliation, not an automatic second payment.

## Activation gate — intentionally OFF by default

`$settings['famtastic_private_reunion_checkout_enabled'] = TRUE` is a one-time
release gate, not a per-client Fritz approval. It has NOT been set by this lane.
`famtastic_payment_mode=disabled` overrides it. Until native checkout/provider
tests are verified, the form honestly says the payment connection is being
verified, and selected staging may continue. Do not tell the customer checkout
is enabled merely because an offer or this source exists.

Before activation, main must verify the exact merged source in an isolated
Commerce test environment: customer-owned order, native checkout rendering,
configured gateway/mode, authenticated callback/webhook, failed/uncertain payment,
refund, repeated POST/reload, and no proof/build/renewal side effects. Then verify
the intended production gateway/webhook and deliberately enable the setting in
the coordinated release. Do not run a real card charge as a test. Native receipt
email behavior when a customer eventually pays remains the existing Commerce
configuration, not an authorization for agent-sent mail now.

Final acceptance/launch and permanent entitlements still require an explicit
scope-aware transition in the delivery lane; this implementation does not pretend
that native paid status is a finished website. No new public product is created.

## Verification and limits

- Focused suite: 30 tests / 247 assertions, passing. One pre-existing PHPUnit
  doc-comment metadata deprecation in PrepaymentStagingContractTest.
- Nine new SQLite/form/gateway tests cover immutable scope, read-only GET,
  same-order replay, no payment creation/provider invocation, account isolation,
  revoked membership, absent/changed selection, tampered prices/SKU/terms,
  unsafe domains, recurring authorization rejection, protected-mode refusal,
  and #17 code UI states without another order/pay button.
- Native order/gateway entities are mocked in this suite; it is NOT a successful
  Stripe/native checkout/browser proof. Earlier #17 native manual-payment and
  one-use completion rollback evidence remains in its original receipt document.
- Portal Design DNA: 34/34 passed. Both touched JSX modules pass Vite/Oxc
  transformation using existing dependencies; no packages installed/copied.
- Full frontend production build, browser layouts, authenticated HTTP/CSRF forms
  and provider checkout remain main's integration/release gates. No hosted UI
  screenshot is claimed from Form API array tests.
- Read-only private views use Commerce `loadUnchanged()`, since ordinary draft
  order loading may refresh/save the entity. No silent GET-driven order refresh.

Main should cherry-pick only this follow-up commit after the previously deployed
87861944/6426ae13/e692d890 equivalents, preserving newer QA/selection safeguards.
Do not re-run the paid record operation or resend the proof email for this change.
