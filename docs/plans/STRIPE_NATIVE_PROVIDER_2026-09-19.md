# Native Stripe bridge: one real synthetic cycle, not private checkout release

## Verified September19 08:21UTC

The separate `scripts/stripe-native-provider/` harness has completed an actual
native Commerce / Stripe **test-mode** cycle. It is not another payment engine.
It preserves the locked native `stripe_payment_element` plugin and SDK. The
existing broad12+4 matrix runner remains a scaffold and production checkout isOFF.

Source is on `codex/private-purchase-integration-20260919`, built over0caefa93.
The sanitized [receipt](../evidence/native-stripe-provider-20260919.json) binds the
exact four executed runner hashes; all still match the successful run. Git records
the subsequent source/tests/docs commit. No application code or production release.

| Observed stage | Exact evidence |
| --- | --- |
| Dedicated account | `famtastic-sandbox-auth`, `acct_1TqwE9DDGtWR2WVN`; SDK account and test balance verified using resolved test-only key |
| Synthetic payment | `pi_3UHJWCDDGtWR2WVN0OT071g6`, $199 USD test amount; anonymous `buyer@example.test`, no provider customer object |
| Real signed event | `evt_3UHJWCDDGtWR2WVN0f406UDd`, received by CLI loopback receiver, SDK signature check and same-account event retrieval |
| Native state | One disposable Commerce order1/payment1; completed, zero balance, one captured native receipt |
| Exact event replay | Still the same order/payment; payment count1 and captured receipt count1 |
| Native refund | `re_3UHJWCDDGtWR2WVN0aOYMVsQ`, full199; persisted as refunded in a new PHP process |
| Cleanup | No remaining disposable probe runtimes or Stripe listener processes observed; no credentials saved to raw gateway config |

All8 provider-cycle assertions pass. Node33 and PHP transport-guard34 offline
checks also pass. Runtime `native-probe-6cc1b4fb-c129-48cb-bfba-f8ed4501fbd4` finished
08:21:14Z. Four provider writes: intent create, test-fixture confirm, native metadata
update, native refund. Source and redacted request IDs are retained. No real charge,
customer message, order21/payment5 mutation, completion code, domain or deployment.

## Safety boundary and failures retained

Fresh copied allowlisted dependencies, fresh SQLite and synthetic-only store/order;
agency modules and account-mirroring private fixtures are never installed. Normal
PHP mail/network and Drupal Guzzle are blocked. Only the guarded Stripe transport
may reach fixed HTTPS API endpoints, exact amounts/metadata and correlated objects.
The existing test key is resolved privately, never printed or written; raw signed
payload and key pass by stdin/memory only. Credentials remain absent from stored
gateway configuration in all six fresh-process phases.

Both classic and v2 registered destination inventories must be complete and empty
before listening and again before creation. Otherwise another installation could
receive synthetic native order IDs. No remote destination is created/disabled.
This is point-in-time inventory, not a lock against another operator changing it;
coordinate the dedicated test profile and do not run concurrent other listeners.

Independent review required external durable pre-dispatch journals, uncertain-
outcome reporting, exception-safe cleanup, async phase cancellation and owned
listener process-group termination. All are implemented with regression checks.
The journals survive cleanup and receipt-write failures; never blindly create
another payment after an uncertain result. SIGKILL cannot guarantee runtime cleanup,
but no key/raw-event file exists. Ordinary cancellation stops subsequent phases.

The actual attempts retained three pre-write refusals: Price normalization (`199`
versus`199.00`), SDK15's empty default account header, and native ACH instant-
verification options. Use native Price equality, strip only empty account/context
headers, and allow only the exact existing native option—not arbitrary parameters.
These were harness mismatches, not claimed production defects.

One later attempt created/confirmed test intent`pi_3UHJQMDDGtWR2WVN1LLmilcB` but
native callback processing failed: nested gateway loads reset a key assigned only
to one in-memory entity. An offline reproduction showed before=true/after=false.
The fix is a per-process Drupal config override populated from stdin; raw storage
remains key-free. A fresh network-disabled fake-key test then proved nested loads
retain the override. Do not save real keys merely to make this harness pass.

That failed attempt is not relabelled success. Exact account/test/amount/run binding
was re-read; existing CLI refunded only it as`re_3UHJQMDDGtWR2WVN1ZrRooE9`. A later
cleanup replay found that same refund, made no write and saved a separate immutable
receipt. This cleanup is **not** the native refund proof in the table above. Both
synthetic payments are fully refunded; original receipts are unchanged.

## Limits and next finite work

- A genuine signed CLI-forwarded event reaches native `onNotify` in process. This
  does not prove deployed Apache/cPanel routing or real provider endpoint delivery.
- Anonymous synthetic native order only—not request16's signed private scope,
  authenticated browser/CSRF checkout, request17's prepaid completion, portal return,
  Stripe Payment Element rendering, or agency receipt/entitlement fulfillment.
- Native Drupal mail is captured, not branded standard/v2 SMTP/inbox proof.
- 3DS/action-required, decline, abandonment, uncertain-provider reconciliation,
  concurrent callbacks/MySQL and the complete12+4 product matrix still need proof.
- Refund persisted; a fresh reload changes balance back to199 while order state
  remains completed. Do not equate refund state with cancellation or entitlement
  revocation; agency behavior is a separate integration layer.

Do not rerun this passing success cycle just for activity. Next extend the isolated
failure/recovery cases, then the authenticated native/private-flow boundary without
exporting the offline real-account fixtures or weakening protected staging's503.
The [runner README](../../scripts/stripe-native-provider/README.md) has local commands.
See [Stripe's testing](https://docs.stripe.com/testing) and
[local webhook guidance](https://docs.stripe.com/webhooks) for provider semantics.

## Client delivery remains first

08:22UTC production read:8/16/17 still customer_ready, no authenticated choice,
staging not_started. Notices772/773/775 still sent once. Actual08:20:03UTC explicit-
CLI health tick remains observe_only, zero mutations/enrollment/reservations.
Private checkout remainsOFF; mainceee698a/deployed378c3d86 unchanged. No proof
regeneration or email resend. Resume exact selected customer source immediately
when the owner-authenticated choice arrives; this test does not invent one.
