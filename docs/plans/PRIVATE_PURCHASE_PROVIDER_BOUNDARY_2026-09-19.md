# Private purchase: native entry versus provider evidence

## September19 06:33Z checkpoint

Source-only test extension on `codex/private-purchase-integration-20260919`, parent
e37e6a49. No financial/application/presentation source change. Production remains
deployed378c3d86; checkoutOFF. Requests8/16/17 still have no selected direction or
staging start. Outboxes772/773/775 remain sent once. Actual06:30:03Z explicit-CLI
health tick is observe_only with zero queue mutations, enrollment or reservations.

## Allowed native route is now locally proven

The earlier38 HTTP checks established native denial cases but did not follow the
successful private-form redirect. The expanded41-check run follows only the exact
local order and native step path, allows at most three redirects, rejects foreign
origins/fragments/query parameters, and never follows the fixture into production.

- Owning synthetic account: `/web/checkout/2`302 to
  `/web/checkout/2/order_information`200 with real native checkout form and CSRF.
- Foreign account: native checkout403.
- Financial/delivery projection unchanged. Native checkout may save its flow/step;
  this does NOT claim byte-identical database state or a globally side-effect-free GET.
- All prior denial, scope, replay, prepaid and disabled-flag checks still pass.

Receipt: `.artifacts/selected-staging-drupal/20260919T063524Z-87726/private-purchase-http.json`;
41/41true, exit0. Tracked sanitized copy:
`docs/evidence/private-purchase-checkout-entry-20260919.json`.
Native36/canonical/339-module/browser receipts remain the earlier separate runs;
they were not rerun or relabelled here. Three financial source hashes unchanged.
Syntax/diff checks pass. The exact disposable runtime was cleaned up.

This proves entry to order information, NOT successful provider checkout, a rendered
Stripe Payment Element, actual authorization, payment, webhook or customer fulfillment.
No browser interaction or screenshot is claimed for this additional HTTP-only test.
The checkout form-token assertion checks markup presence, not native checkout POST
CSRF enforcement. Earlier38 checks exercise the private companion form's POST CSRF.
Independent read-only review cleared this tests/docs change with those limits;
the final41-check receipt matches the exact current harness hash.

## Concrete provider boundary

Read-only `stripe whoami --format json` reports an authenticated FAMtastic Designs
account under profile `alreadybuilt-revenue-trial-20260918`, with both test-mode and
live-mode credentials available. No key values were read, printed, copied or reused.
Do not describe this as missing Stripe login, and do not adopt another lane's
profile/credentials implicitly merely because it is currently selected.

The repository's `scripts/stripe-provider-e2e.sh` remains an explicit scaffold.
Its matrix validates12 one-time and4 recurring products; even with its opt-in flag,
it exits2 before any Stripe call because provider execution is not implemented.
This was actually checked, not inferred from a filename or old status entry.

The documented protected-staging contract intentionally503-refuses payment and
webhook routes and uses a disabled gateway. Do not weaken that environment's safety
boundary to get a green private-checkout test. A separate provider-capable test
runtime and test-only credential binding are required. No such new binding or
environment was created here, and no credential is requested in chat.

The current offline fixture necessarily mirrors exact account/request identity to
exercise the private16/17 allowlist. Its real-looking email addresses, public request
IDs, source metadata and receipt text must never be forwarded to Stripe test objects.
The all-transports-disabled boundary remains mandatory for this fixture.

## Next finite implementation, before provider execution

1. Implement a separate fail-closed test-provider harness around existing Commerce,
   not another payment engine or changes to the production customer allowlist.
2. Provision synthetic provider identities and explicit test-only runtime/key
   binding; assert provider livemodefalse and refuse real customer emails/metadata,
   production callbacks, real mail, domains and production databases.
3. Capture the actual native payment/order, signature-verified provider callback,
   decline,3DS/action-required, abandonment/uncertain response, duplicate webhook,
   refund and customer receipt/entitlement state in one correlation chain.
4. Keep full portal-return/error-state and deployed middleware/MySQL proofs separate.

This is unfinished implementation/configuration, not an assertion that no Stripe
account exists or permission to enable checkout. Safe work can continue while
clients choose. Prioritize an authenticated exact17 selection immediately; no
proof regeneration, new notice, production completion code, second paid record,
real charge or final launch is authorized by these local test receipts.
