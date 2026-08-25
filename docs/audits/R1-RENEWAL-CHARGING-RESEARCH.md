# R1 RESEARCH: PROVIDER-AWARE RENEWAL CHARGING — 2026-08-25

**Question** (CEO-AGENTS-AND-RESEARCH-2026-08-24 R1): Can commerce_stripe execute off-session
renewal charges against saved payment methods — including SCA/MIT handling — on our prod
module/gateway versions? Recommended architecture for the $9.99/$19.99 monthly hosting renewals.
**Owner**: @fam-commerce · **Status**: research complete; implementation is Fritz-gated.

---

## 1. Verified stack versions (composer.lock, 2026-08-25)

| Component | Version |
|---|---|
| Drupal core | 11.4.4 |
| Drupal Commerce | 3.3.8 |
| commerce_stripe | **2.2.1** |
| stripe/stripe-php | v15.10.0 |
| commerce_recurring | **not installed** |

Prod gateway (verified in CEO-FULL-REVIEW §6): config entity `famtastic_stripe_live`,
plugin **stripe_payment_element**, mode=live, enabled.

## 2. Answer: YES — off-session is supported by what we already run

Evidence from the vendored module (`backend/web/modules/contrib/commerce_stripe/`):

1. **Saving a card for later** — `PaymentMethodAddForm::buildCreditCardForm()` creates a
   SetupIntent with `'usage' => 'off_session'` whenever a payment method is added outside a
   checkout flow (src/PluginForm/Stripe/PaymentMethodAddForm.php:52-62). The gateway's
   configuration exposes `payment_method_usage: off_session` ("the site may process payments
   on the customer's behalf (e.g., recurring billing)", src/Plugin/Commerce/PaymentGateway/
   StripePaymentElement.php:446), and the review pane understands it.
2. **Charging off-session** — `StripePaymentElement::createPayment()`, when no checkout intent
   exists on the order, creates and immediately confirms a PaymentIntent with
   `'confirm' => TRUE, 'off_session' => TRUE` — exactly the merchant-initiated renewal call
   Stripe documents (src/Plugin/Commerce/PaymentGateway/StripePaymentElement.php:593-640).
3. **SCA / MIT handling is built in**: intent status `requires_action` → **SoftDeclineException**
   (customer must re-authenticate; module comment says so explicitly); `requires_payment_method`,
   issuer hard declines → HardDeclineException; an authentication-required decline additionally
   detaches the dead payment method. Webhook support includes `payment_intent.payment_failed`,
   `payment_intent.succeeded`, `charge.refunded` (README.md:108-116).

So no new payment provider and no gateway upgrade is required for MIT renewals. The gap is
entirely orchestration: deciding *who* is due, *how much* (our immutable deal snapshots),
retry/dunning, receipts, and entitlement extension — none of which commerce_stripe attempts.

## 3. Stripe API semantics that bind the design (docs.stripe.com/payments/save-and-reuse)

- Save PM without payment = Customer + SetupIntent (+ Payment Element client secret); consent
  terms must disclose: initiating payments on the customer's behalf, anticipated timing and
  frequency, how amount is determined, cancellation policy — and we must keep a record of the
  agreement. Our existing `recurring_hosting` consent ledger rows (with checksummed deal
  snapshot) satisfy the record side; portal copy must carry the four disclosures.
- Off-session charge = PaymentIntent with `off_session=true, confirm=true, customer,
  payment_method`. Stripe requests MIT exemptions using prior on-session history; if the
  exemption is refused the intent errors with decline code `authentication_required`.
- Failed off-session attempt → HTTP 402, intent `requires_payment_method`: notify the customer
  to return; for `authentication_required` bring them back **on-session** (confirmPayment with
  the declined intent's client secret). Never auto-retry an `requires_action` intent.
- Test cards cover both branches: `4242…` (no SCA ever), `4000 0025 0000 3155` (auth on setup
  only), `4000 0027 6000 3184` (**auth required on every subsequent off-session payment** —
  this is the dunning-path test card), `4000 0000 0000 9995` (setup decline).

## 4. Recommendation: self-hosted cron + off-session PaymentIntents (NOT Stripe Billing)

**Use the engine we already own.** Reasons, ranked:

1. **One source of truth.** Stripe Billing subscriptions would duplicate customer, price,
   schedule, and cancellation state outside `famtastic_subscription` / `famtastic_entitlement`
   and outside our checksummed deal snapshots — the same split-brain the CEO audit flagged as
   gap #9 for payments. Our consent ledger, outbox receipts, exception queue, and admin metrics
   would each need a Stripe-Billing mirror.
2. **Our shape is awkward for Billing anyway**: 12 months hosting included at purchase,
   monthly billing starting month 13, cancel-anytime, amounts fixed per deal snapshot.
   Expressible in Billing (trials/prices), but every mapping adds reconciliation surface for
   zero benefit at current scale (n≈dozens, not thousands).
3. **commerce_recurring is not installed**, and its scheduling machinery would duplicate
   `famtastic_subscription` (which already has provider, retry_count, next_attempt_at).
   Revisit only if dunning complexity outgrows a simple state machine.
4. **commerce_stripe already provides the hard part** (§2): saved-card lifecycle and confirmed
   off-session intents with correct decline taxonomy. We add ~one service class of glue.

### Target flow (all amounts read from catalog/deal snapshots — never hardcoded)

```
authorize (exists today)      POST /api/pipeline/hosting-renewal → consent row +
                              famtastic_subscription(status=scheduled, next_attempt_at=renews_at)
save card                     portal billing surface → commerce_payment method add form
                              (SetupIntent usage=off_session) → StripeCustomer + PM ids stored
cron (drush command)          subscriptions WHERE next_attempt_at <= now AND renews_at reached
                              → DRAFT commerce renewal order (FAM-HOST-999 | FAM-HOST-BUSINESS-1999)
                              → createPayment(off-session)
success                       order completed → entitlement renews_at += 1 month → receipt outbox
soft decline (SCA/action)     status=action_required → customer email w/ deep link to finish
                              on-session → retry ≤3 over 72h → then owner exception
hard decline                  owner exception + suspension path (existing reconcilePayment)
```

### Division of labor

| Already provided by commerce_stripe 2.2.1 | We build |
|---|---|
| SetupIntent `usage=off_session` card saving | Portal "saved card" billing surface + Stripe Customer linking |
| Confirmed off-session PaymentIntent in `createPayment()` | Due-date selection query + DRAFT renewal order factory |
| Soft/hard decline taxonomy incl. auto-detach on auth failure | Retry/dunning state machine on `famtastic_subscription` |
| Webhooks incl. `payment_intent.payment_failed`, refunds | Receipts/owner alerts via existing outbox; entitlement extension; subscription↔payment reconciliation |

## 5. Fritz-gated rollout steps (nothing here runs without him)

1. **Approve this recommendation** (architecture decision record).
2. **TEST-mode build**: set gateway `payment_method_usage=off_session`; ship the saved-card
   portal surface; implement the renewal service + drush command behind
   `FAMTASTIC_HOSTING_BILLING_PROVIDER=memory` (existing hard-disable stays); synthetic e2e
   validator covering success + `4000 0027 6000 3184` action-required paths in Stripe TEST mode.
3. **Sequencing rule**: founder-$1 first real charge happens BEFORE any renewal logic goes live
   (CEO sequencing, 2026-08-24) — renewals never become the first live experiment.
4. **Live gate**: flipping `FAMTASTIC_HOSTING_BILLING_PROVIDER` beyond `memory`, adding the
   crontab line, and any real customer charge are separate explicit owner approvals; agents may
   build, lint, and dry-run only. Scaffold exists at `backend/scripts/renewals-cron.php`
   (draft-orders only, never charges, disabled until enabled deliberately).
5. **Post-enable watch**: weekly review of dunning outcomes + dead-letter outbox for the first
   month of live renewals; rollback = flip provider back to `memory` (subscriptions pause, no
   data loss).

## Sources

- Vendored: `backend/web/modules/contrib/commerce_stripe/src/PluginForm/Stripe/PaymentMethodAddForm.php`,
  `.../PaymentGateway/StripePaymentElement.php` (config :446, createPayment :593-640),
  `README.md:95-116`; `backend/composer.lock`.
- Internal: `HostingLifecycleService.php:96-98` (billing hard-disable), `famtastic_subscription`
  schema, `/api/pipeline/hosting-renewal` consent flow, `famtastic-products.json`
  (FAM-HOST-999 $9.99, FAM-HOST-BUSINESS-1999 $19.99).
- Stripe docs: Save & reuse payment methods (Setup Intents + off-session charges, SCA/MIT,
  compliance disclosures, test cards) — https://docs.stripe.com/payments/save-and-reuse;
  decline codes — https://docs.stripe.com/declines/codes.
