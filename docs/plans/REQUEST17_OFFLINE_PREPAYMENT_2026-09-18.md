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
