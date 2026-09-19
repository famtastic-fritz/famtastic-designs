# Native nonpayment cases — scoped test-provider verification

## Boundary

Extends the existing isolated native bridge over pushed `a593dc68`; no agency
application code, checkout activation, dependency/SDK upgrade or production writes.
The earlier successful payment/refund receipt remains immutable. These scenarios
must not be described as browser checkout, authenticated private purchase, actual
customer recovery, hosted webhook, agency fulfillment or complete catalog proof.

Three finite cases use one fresh synthetic runtime/intent each:

- **Decline:** the official generic-decline PaymentMethod yields a definite402 and
  `requires_payment_method`, zero received amount, no native payment/receipt.
- **Action required:** the official 3DS-required fixture yields `requires_action`;
  no authentication challenge is completed. This proves non-settlement only.
- **Abandonment model:** create but never confirm; explicitly cancel that exact
  unpaid intent. This does not prove detecting a closed browser or abandoned cart.

Each receives its actual signed CLI-forwarded event and replays it into native
`onNotify`, asserts draft/full balance/no native payment/no receipt, then reads
back exact canceled/unpaid provider state. Decline/action-required are canceled as
test cleanup after their callback; abandonment's callback is the canceled event.
No raw keys, client secrets, signed bodies, provider error bodies or customer
identities are written to evidence. Successful creation does not imply payment.

## Source findings and regression rule

Locked Commerce Stripe2.2.1 skips `requires_action` as unsupported. Failed/canceled
events can void an existing authorization, but do not create a payment or place an
order when no native payment exists. NULL/HTTP200 is therefore not financial proof.
Keep a nonempty signing secret and verify exact account/run/intent/amount binding
before `onNotify`; native failure lookup alone is by remote ID. Explicit provider
cancellation does not itself cancel the Drupal order.

A known, exactly bound generic402 decline is journaled before returning it to the
locked SDK's normal `CardException`. Unrecognized errors remain fail-closed and
uncertain, never promoted to an expected decline. Any known intent without a proved
refund or cancellation is reconciliation-required. Phase snapshots are not overwritten.

Official references checked September19:
[test PaymentMethods and 3DS](https://docs.stripe.com/testing?testing-method=payment-methods),
[cancel an unpaid intent](https://docs.stripe.com/api/payment_intents/cancel).

## Execution evidence

47 Node and55 PHP offline transport checks pass; fresh offline
bootstrap `native-probe-c6179437-66ef-4cc5-86c0-26996fb33b67` passed08:54:22Z with
network disabled, no real credentials and no provider objects. Independent source
review cleared the bounded runs. All three actual test-provider runs passed, with
six assertions each and matching exact source hashes. Sanitized complete phase/
request records: [suite evidence](../evidence/native-stripe-nonpayment-20260919.json).

| Case / completed UTC | Intent | Genuine signed event | Requests / writes |
| --- | --- | --- | --- |
| Decline /08:57:01Z | `pi_3UHK4pDDGtWR2WVN0c3UXSz2` | `evt_3UHK4pDDGtWR2WVN0XwXJKJr` | 11 /3 |
| Action required /08:57:39Z | `pi_3UHK5SDDGtWR2WVN06I0j2uN` | `evt_3UHK5SDDGtWR2WVN0xapWlEV` | 10 /3 |
| Unconfirmed abandonment /08:58:27Z | `pi_3UHK6EDDGtWR2WVN1UkHWBxm` | `evt_3UHK6EDDGtWR2WVN1nsbdvJd` | 9 /2 |

All30 attempts have30 response journal entries; decline includes one exactly bound
402 response. All20 native phase snapshots retained no payment/receipt and no stored
gateway credentials. Final fresh-process reads show canceled/zero received for all
three intents. All three disposable runtimes were removed. No pending test charge,
unknown outcome or repeated success/refund cycle was introduced in this pass.

Independent read-only receipt review matched all nine original receipt/journal
hashes, all four source hashes,30/30 request records and20 snapshots; no mismatch
or claim blocker. Parent separately checked no remaining probe runtime/listener and
no credential-pattern matches in scripts/evidence. This is source-only closeout.

No canonical agency/UI/email rerun solely for this separate tooling change; prior
receipts retain their exact source and coverage. The actual private-flow, browser,
uncertain-response reconciliation, hosted and full12+4 matrix gates remain open.

Next finite increment: prove an interrupted/uncertain confirmation and callback loss
can resume the same synthetic intent/order without a second charge or receipt. Keep
external journals and exact test identity; do not run another success-only cycle or
rerun these three passing cases merely for activity. Then connect the authenticated
native/private boundary using newly synthetic identities, never the account-mirroring
offline fixtures. Full browser3DS success/failure/return UX remains separate.

## Delivery and release

08:49UTC production read:8/16/17 customer_ready/unselected/staging not_started;
outboxes767/769/772/773/775 still sent once. Latest actual server tick08:45:02UTC
was CLI observe_only, zero mutations/enrollment/reservations. Private checkoutOFF.
Feature source only; production remains378c3d86 under docs-only mainceee698a.
Do not resend mail, regenerate proofs or invent a client choice while testing.
