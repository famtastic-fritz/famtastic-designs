# Request 17 — offline prepayment, not another checkout

## Authority and scope

Fritz explicitly confirmed receipt of $200 by Zelle for StockandShip98 and
authorized one private request-bound order/payment. Evidence is that owner
confirmation, **not a bank receipt or API verification**. The bank transaction
reference and actual received date remain null. Commerce's payment completion
timestamp is the time the bookkeeping entry is recorded, not a claimed transfer
date. No new card charge, Stripe event, comp, coupon, email or launch is authorized
by this operation. Main delivery owns the proof email.

## Record model

- Native Drupal Commerce `manual` gateway, disabled for checkout selection.
- One unplaced, non-cart, locked order; one $200 custom order item; one completed
  manual payment; $0 balance. The order remains draft because terms/domain and
  final site acceptance have not happened. Money received and order placed are
  independent states.
- One private offer with a deterministic request-specific public ID and status
  `prepaid_held`; it binds the same order to the request/customer/organization.
  The immutable expanded scope and owner-confirmation evidence are in order data.
- The normal request `commerce_order_id` stays empty at this phase because old
  revision logic treats that field as checkout/conversion. The private offer and
  order data are the durable bidirectional prepayment binding. Normal checkout
  detects this held purchase and must not create another order.
- A row-level request lock plus one transaction, unique offer UUID, orphan-order
  refusal and exact replay reconciliation prevent duplicate receipts. Replays
  return the same order and payment; mismatches stop rather than silently repair.
- A completion code is random, hashed, expiring, single-use and exact-account/
  request/order/scope bound. Its authenticated CSRF-protected endpoint only saves
  terms/domain confirmation to the existing order. No code is issued or sent in
  the recording operation. Neither code use nor payment constitutes site approval.
- Native order-placement and generic SKU fulfillment remain held; final custom
  scope promotion requires a separately tested readiness integration. Do not
  remove the hold merely to make an operations table look complete.

## Side-effect review

Live paid-order listeners include Commerce's offsite-only order placement.
Manual is not an offsite gateway. Native receipts/Stripe-on-place subscribers
listen to `commerce_order.place.post_transition`, which this operation never
invokes. FAMtastic's payment reconciliation calls fulfillment, but fulfillment
already returns for a draft order. Additional checked-in marker guards prevent
future accidental placement/fulfillment after the coordinated module release.

The private empty project conversation uses the existing portal tables and an
assigned support-case owner. No invented customer message or welcome notification
is created. Existing exact project conversations are reused. Reply persistence
uses `ClientMessagingService`; customer replies route to the configured owner
notification address, not a published phone number.

## Operation

`backend/scripts/record-request17-prepayment.php` defaults to rollback-only
`dry-run`. Invoke through `/usr/local/bin/php vendor/bin/drush.php php:script`
from the existing production Drupal root, with a private immutable checked-in
source copy outside the webroot. Set `FAMTASTIC_PREPAYMENT_MODE=apply` and the
exact `FAMTASTIC_PREPAYMENT_CONFIRM` string documented in the script only after
the dry run and fresh identity/side-effect checks pass. `receipt` is read-only.
Do not run any general lifecycle/outbox worker.

The dry run tests same-order/payment replay, wrong-account recording, same-order
code completion, wrong-account/used-code denial, private conversation ownership,
and full rollback. It writes no synthetic messages. A separate source test covers
expired/wrong code, changed terms/scope, unsafe domains and exact verified identity.

## Evidence at implementation checkpoint

- Source tests: 18 tests / 95 assertions (offline policy plus existing durable
  messaging suite), using the existing dependency runtime without new installs.
- No production payment yet at this checkpoint. Append the actual receipt below.
- Backend endpoint/portal display/placement guards are **not deployed** until the
  main orchestrator's coordinated backend release. No frontend code-entry UI yet.
- Full unattended pipeline and final custom-scope fulfillment are outside this
  bounded payment lane. Do not call them proven by a receipt.

## Durable production receipt

Recorded at 2026-09-18T23:37:39Z via immutable source `87861944`, private CLI
operation only. Native **order21 / FAM-2609-0021**, **payment5 completed**, private
offer `8acdaeb2-9a7e-5c89-a712-6de7ea979807`: $200.00 received, $0.00 balance.
Fresh-process replay returned `existing=true` with the same order/payment.
One offer, one payment, zero fulfillment rows, zero order notifications.
Manual gateway is disabled for checkout; order is locked/non-cart/draft.
The recorded time is not an invented bank-transfer date.

Thread22 `b434c7ee-e5f9-4174-887d-305651f1bdfb` is the private request17 project
conversation. It has zero fabricated messages and an assigned support case with
`owner_uid=1`. The thread table itself has no assignee column. Owner notification
routing reads the existing configured address; no notification was sent.

The preceding dry-run order20/payment4/thread21 were fully rolled back; independent
readback proved all absent, including the temporary gateway configuration. Those
sequence IDs must never be reported as actual purchases.

## Request 16 supplemental scope lane

`record-request16-private-scope.php` records one $199 one-time private offer and
an immutable scope/approval event. It does not create an order or payment, inherit
`FAM-CUSTOM-1999` terms, authorize renewals or change original intake data.
Dates, venue, prices, photos, committee and domain remain customer confirmations.
The original thread20 is reused; a support-case owner is added rather than a
duplicate conversation. Generic checkout deliberately cannot buy this unknown
private SKU: a selected-direction, scope-exact payment step remains a separate
validated integration. Do not send a generic buy URL or promise checkout is ready.
The nullable offer expiry requires the included portal projection fix to show
the no-expiry owner-approved scope; no arbitrary deadline is invented.

Request16 offer is now durably recorded as
`5561e5d8-1b85-5858-a690-df186d47d57d`, SKU `PRIVATE-REUNION16-199`, $199.00,
scope hash `478a57b7673dc087f657bd273cc48b428ab5497e1a454f8161685ddf472db408`.
Audit event: `private-scope:request:16:class-of-2000-v1`. No order/payment/mail.
Original intake hash is unchanged; thread20 remains the same with an added
support-case assignment to owner UID1. Bank receipt/payment is not applicable.
Fresh-process replay returned `existing=true`, the same offer and thread20,
with `order_created=false`, `payment_created=false`, `email_queued=false`.

## Selection compatibility

After recording the real payment, fresh production readback confirmed request17
is still submitted, `commerce_order_id=NULL`, unselected, and not accepted.
The repair branch's `A paid request cannot start pre-payment staging` guard
therefore does not block it. Payment binding lives in the private offer/order.
Do not fill the request field just to populate an operations column.

`permitsSelectedStaging()` is a defense-in-depth bridge if a later exact caller
has already bound that field: it reconciles the native completed manual payment,
zero balance/refunds, held draft order, same request/customer/organization,
special scope/version and owner-confirmation evidence. It does not permit other
paid requests or infer client acceptance. When merging the selected-staging
repair's newer lock/recheck block, replace only its paid-field rejection with
`!empty($row['commerce_order_id']) && !(new OfflinePrepaymentService())->permitsSelectedStaging($row)`;
retain its request row lock and all QA/artifact/selection checks.

`verify-request17-selection-rollback.php` performs the exact native selection
and one-job assertion inside a rollback-only transaction after the real proof
set is customer-ready. It rejects cross-account selection and proves acceptance
is not inferred. It refuses to manufacture ready status or commit a choice.
As of the latest live check the proofs are not yet imported/ready, so this
specific selection rehearsal remains pending the main lane's QA/import.

The focused suite now passes **21 tests / 133 assertions**, including altered
scope hashes, mismatched ownership/order, pending payment, nonzero balance,
released hold and inferred acceptance denials. One existing PHPUnit deprecation
comes from `PrepaymentStagingContractTest` doc-comment metadata; no test failed.
PHP lint and `git diff --check` passed. Browser/HTTP completion and actual
selection remain separate release checks, not claims made by these unit tests.
