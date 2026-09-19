# Private purchase completion — follow-up after e692d890

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
has been issued in production. Scope version/hash are retained in server-side
form state and revalidated on POST. Codes stay out of URL/query strings.

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
