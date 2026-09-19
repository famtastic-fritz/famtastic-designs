# Native recovery probe — exact injected-fault scope

## Design and safety boundary

Extends the separate native test harness over4e9a630b. No agency runtime changes,
production deployment/checkout activation, customer writes or new payment engine.
The established success and nonpayment receipts stay unchanged, not rerun for activity.

One fresh anonymous synthetic order and one test PaymentIntent model two failures:

1. The confirm transport dispatches, then the child PHP process deliberately exits86
   before parsing/journaling/SDK observation. The durable attempt survives without
   a fabricated response entry. This is a controlled child exit, not an actual
   provider outage or whole-machine crash.
2. The receiver verifies the genuine signed success event, ACKs it and discards the
   body without native processing. Only Event ID/hash survives. This is handler
   processing loss, not complete non-delivery before any event ID is known.
3. A fresh process verifies account/test mode and the exact same intent's succeeded
   amount/currency/run/order/store/charge, then retries only that original confirm
   with identical body/idempotency key. Require provider `Idempotent-Replayed:true`
   and unchanged charge. A separate reconciliation receipt covers only that gap.
4. Fetch the genuine Event by its exact ID through the authenticated Stripe API;
   validate it and invoke the existing native `processWebHook`. This is API event
   reconciliation, NOT a signed redelivery or an invented success event. Replay
   that same event, verify one payment/receipt, then native refund and fresh reload.

New journal sequence IDs match every attempt/response. Resolution must bind the
one missing confirm, same run/account/PI, request hash and idempotency hash, subsequent
read and provider-replayed result. Extra gaps, foreign/malformed/stale/mismatched
evidence fail closed. Unknown outcomes remain visible and block further scenarios.
Keys/raw event bodies/client secrets stay memory-only. All other existing profile,
remote-destination, network, request-count, expiry and cleanup boundaries remain.

## Source findings

The native return handler is not independently idempotent and native payment save
precedes metadata update/order placement. A blind return retry after partial
persistence can duplicate a local payment; an already-completed payment's later
webhook does not necessarily place a still-draft order. This is a remaining
partial-fulfillment/concurrency release gate, not claimed fixed by this probe.
Our exact event recovery refuses an unexpected existing payment on initial entry;
replay requires the same already-completed payment/order. Never weaken those guards
to label a partial native failure successful.

SDK15 response headers use `CaseInsensitiveArray`. Casting it to a PHP array loses
logical header keys; use its iterator/array-access contract. Caught and corrected
offline before provider execution, with a real SDK-type regression check.

References checked September19:
[uncertain-response and idempotency semantics](https://docs.stripe.com/error-low-level),
[provider idempotent requests](https://docs.stripe.com/api/idempotent_requests),
[undelivered-event processing](https://docs.stripe.com/webhooks/process-undelivered-events).

## Current evidence

The first provider attempt `native-probe-4d564d1a-8287-4c45-8123-82273b000618`
failed09:33:49UTC after the deliberate confirmation interruption, before recovery.
Intent `pi_3UHKeTDDGtWR2WVN0es4eJUP` is fully refunded via exact CLI cleanup
`re_3UHKeTDDGtWR2WVN0vAqoe3J`, receipt09:34:33Z. The original failed receipt and
seq4 response gap remain immutable; separate cleanup is not native recovery proof.
No new provider scenario was attempted before that exact outcome was settled.

Root cause: locked Drush13.7.6 includes the PHP script, then its incomplete-command
shutdown handler overwrites exit86 with its stored default failure code. That is a
source-backed diagnosis; the failed receipt did not retain the actual child status.
The direct-PHP fake-transport test missed the wrapper. The fix sets Drush's runtime
exit code immediately after fsync of the fault marker, without marking completion.
It still runs shutdown handlers, so it is not abrupt process-kill evidence.

94 Node/61 PHP offline checks now pass. Fresh fully network-disabled native runtime
`native-probe-c177d196-1dd3-4960-a194-5852a0735851` passed09:58:11Z through the
actual Drush path: exit86, stdout0bytes, stderr48bytes (not retained), no signal/kill,
exact missing fake response, unchanged unpaid native order, zero provider calls or
objects and removed runtime. Diagnostics retain only status/byte counts, not raw
secrets/errors. Marker/attempt/identity/parameters/idempotency must match; broad exit1
acceptance is forbidden. This offline receipt binds the same five executed source
hashes as the successful provider run below; the Git commit also contains its docs.

## Actual recovery passed — 10:00:07UTC

Run `native-probe-efdffb3a-6068-4b08-969f-85dac1fef2a6` passed all9 checks after
independent source review and the real-Drush offline test. Sanitized full evidence:
[recovery suite receipt](../evidence/native-stripe-recovery-20260919.json).

| Stage | Observed evidence |
| --- | --- |
| Same synthetic intent | `pi_3UHL3qDDGtWR2WVN13ZFkoqt`,19900cents/USD,test mode |
| Controlled interruption | Confirm seq4: exit86, no stdout/signal/kill; original response remains absent |
| Before recovery | Native order draft/full199 balance,no payment/no receipt; one unresolved seq4 |
| Exact reconciliation | SDK account/mode reread; PI read seq7 then identical confirm seq8 with provider replay header; unchanged charge `ch_3UHL3qDDGtWR2WVN1bXPuoKj` |
| Dropped callback | Genuine signed event `evt_3UHL3qDDGtWR2WVN1BssVGsK` acknowledged/body discarded; handler not called |
| API Event recovery/replay | Existing native processWebHook receives authenticated retrieved Event; one completed order/payment,zero balance,one captured receipt through replay |
| Native full refund | `re_3UHL3qDDGtWR2WVN1vE79BqN`, fresh process confirms refunded199 |

14 attempts/13 recorded responses,5 POST attempts (including one idempotent confirm
replay). The missing seq4 is not fabricated: separate exact reconciliation covers
it, leaving zero unresolved outcomes.7 native state snapshots have no persisted
credentials; the eighth phase is the interruption. Runtime removed, test payment
fully refunded. No customer identity or message/payment changes, no production
activation or deployment. The failed first run remains separately recorded/refunded.

Independent read-only receipt review found no mismatch: all five current source
hashes match both actual/offline receipts;14/13 journal entries, the sole seq4 gap,
one payment/receipt through replay, full refund and separate failed cleanup agree.
No credential patterns in the15 inspected artifacts. Body/signature were deliberately
discarded, so their signature cannot be independently reverified from saved files;
the capture-time check is source-bound runtime evidence. Parent verified no remaining
owned probe process or temporary runtime. Implementation commit `b0bebd46` is pushed
on the feature branch; production/main are unchanged. No broad agency/UI/email rerun
for this isolated tooling change; their earlier receipts retain their own source.

This proves only controlled response-observation and callback-processing loss in
the anonymous native bridge. It does not prove a restarted parent/host, callback
loss before Event ID observation, browser3DS/return, private request authority,
partial native payment-save/order-placement recovery, concurrency/MySQL, hosted
middleware, agency receipt/entitlements or full12+4 matrix. Keep checkoutOFF.

Next finite increment: connect the already-tested private/native boundary to fresh
synthetic identities and provider-safe runtime without exporting real-account
fixtures or weakening production allowlists. Define failure/return/partial-save
and receipt/entitlement assertions before provider writes. Do not repeat passing
native cases for activity. Browser/hosted/full catalog gates remain separately open.

Source pointer for that next boundary: `PrivatePurchaseService::context()` embeds
the two approved public request IDs, customer/org IDs and exact account emails;
`selection()`/`assertReunionScope()` retain the exact reunion authority. Existing
`test-private-purchase-drupal.php` mirrors those identities and must remain entirely
offline. Do not simply enable its transports, replace its emails after creating an
order, or bypass `PrivateScopeCheckoutGuard`. A synthetic provider integration needs
an explicitly reviewed separation between the unchanged production authority and
synthetic-only test context; swapping test identities is not itself production
authorization proof. No such separation is implemented in this recovery increment.

## Delivery status

09:54UTC read:8/16/17 remain unselected/customer_ready/staging not_started;
original/corrected notices767/769/772/773/775 remain sent once.09:50:04UTC actual CLI
tick observe_only, zero mutation/enrollment/reservation. Mainceee698a/deployed378c3d86,
private checkoutOFF. Selected client delivery takes priority over these finite tests.
