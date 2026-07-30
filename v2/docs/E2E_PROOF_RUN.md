# FAMtastic Designs — Prospect Pipeline V1: Executed Local E2E Proof

This is the executed record for **deliverable 20**. The script
`v2/scripts/e2e-proof.sh` drives the entire customer transaction against a real,
locally-served Drupal 11 and asserts every step. It exits `0` only when all
assertions pass.

## What it proves (the required chain)

```
create prospect → generate secure link → confirm business → convert to lead →
present $199 offer → complete Stripe test payment → verify the webhook →
unlock intake → submit intake + assets → generate Site Studio prompt + JSON →
record a proof URL → request revision / approve → mark the project delivered
```

Plus the negative/security assertions: bad token → 404, intake before payment →
402 (paid gate), authorization required → 422, tampered webhook signature → 400,
duplicate webhook is idempotent (no double fulfillment), and internal discovery
notes never appear in the prospect-facing payload.

## How to run

```bash
# From the repo. No Stripe key needed — uses the stub gateway; the webhook is
# still verified with a real HMAC-SHA256 signature.
v2/scripts/e2e-proof.sh
```

Environment (optional): `PORT` (default 8899), `STRIPE_WEBHOOK_SECRET`
(default `whsec_local_dev_secret`). The script starts its own `drush runserver`,
creates a fresh prospect each run, and cleans up the server on exit.

The React UI for the same flow was additionally demonstrated in a browser
(landing → confirm → $199 offer → pay → server-verified return → intake +
upload → proof review → approve), and the admin records were viewed in the
Drupal admin at `/admin/famtastic/*`.

## Executed output (PASS)

```
0. Start local Drupal server on :8899
  ✓ PASS server responding
1. Create prospect + issue secure link (drush)
  ✓ PASS prospect #11 created, token issued
2. Prospect landing session shows discovered business (deliverable 3)
  ✓ PASS session returns business name
  ✓ PASS session does NOT leak internal notes
2b. Bad token is rejected (security)
  ✓ PASS bad token → 404 (404)
3. Paid gate BEFORE payment blocks intake (deliverable 12)
  ✓ PASS intake before pay → 402 (402)
4. Confirm business + contact + authorization → lead (deliverables 4,5)
  ✓ PASS confirm returns status lead
4b. Authorization is required (reject authorized=false)
  ✓ PASS confirm without authorization → 422 (422)
5. Present + purchase the $199 offer → Stripe test checkout (deliverables 6,8)
  ✓ PASS checkout returns a session id
6. Signature-verified webhook fulfills the order (deliverables 10,11)
  ✓ PASS webhook fulfilled (paid)
  ✓ PASS webhook newly processed
6b. Duplicate webhook is idempotent (deliverable 11)
  ✓ PASS duplicate not re-processed
6c. Tampered signature is rejected (deliverable 10)
  ✓ PASS bad signature → 400 (400)
7. Server-verified payment status (deliverable 9)
  ✓ PASS order-status paid
8. Intake unlocked after payment (deliverables 12,13)
  ✓ PASS intake accepted
9. Asset upload (logo) (deliverable 14)
  ✓ PASS asset stored + returns file id
10. Generate Site Studio request: brief + JSON (deliverables 15,16)
  ✓ PASS studio request generated
  ✓ PASS JSON has schema_version
  ✓ PASS JSON has positioning
  ✓ PASS JSON carries confirmed name
  ✓ PASS brief file exists
11. Admin records proof URL on the project (deliverable 17)
  ✓ PASS proof URL recorded
12. Customer proof-review page sees the proof (deliverable 18)
  ✓ PASS session exposes proof_url
13. Customer requests revision, then approves (deliverable 19)
  ✓ PASS request revision
  ✓ PASS approve
14. Mark the project delivered/launched (admin)
  ✓ PASS project marked delivered/launched

RESULT: 26 passed, 0 failed
ALL GREEN — full prospect→paid→intake→Site Studio→approval chain proven.
```

## Unit tests (pure-logic services)

```
cd v2/backend
composer require --dev drupal/core-dev:^11 -W   # one-time
vendor/bin/phpunit -c web/core/phpunit.xml.dist web/modules/custom/famtastic_pipeline/tests/src/Unit
# → 11 tests, 33 assertions, OK
```

Covers: token hashing/verification (`TokenManagerTest`), Stripe webhook
signature verify + tamper/expiry (`WebhookVerifierTest`), and the Site Studio
brief rendering (`SiteStudioBuilderTest`).

## Frontend build

```
cd frontend && npm run build   # → built, 0 errors
```
