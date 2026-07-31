# FAMtastic Pipeline

Attributed lead → three proofs → gated outreach → proof/package selection →
versioned terms → payment → intake → Site Studio fulfillment → revisions →
approval → deployment/domain/hosting/renewal pipeline for FAMtastic Designs.

## What it adds

- **Six content entities:** `famtastic_prospect`, `famtastic_order`,
  `famtastic_intake`, `famtastic_project`, `proof_campaign`, `proof_variant`
  (admin UIs under `/admin/famtastic/*`).
- **Token-scoped public API** (`/api/pipeline/*`) — the prospect authenticates
  with a link token (SHA-256 hashed at rest), never a Drupal account.
- **Stripe test-mode checkout** behind a `PaymentGatewayInterface`
  (`StripeGateway` when a key is set, `StubGateway` otherwise) + a
  **signature-verified, idempotent webhook**.
- **Site Studio request builder** — human brief + versioned machine JSON, behind
  a `SiteStudioAdapterInterface` (V1 = file export for manual handoff).
- **Drush commands:** `famtastic:prospect-create` (fpc), `famtastic:studio-generate` (fsg).

## API (all token-scoped via the `X-Prospect-Token` header)

| Method | Path | Purpose |
|---|---|---|
| GET  | `/api/pipeline/session` | Prospect-safe view + pipeline state |
| POST | `/api/pipeline/confirm` | Corrections + contact + authorization → lead |
| POST | `/api/pipeline/checkout` | Create Stripe test Checkout Session |
| GET  | `/api/pipeline/order-status` | Server-verified payment status |
| POST | `/api/pipeline/stripe/webhook` | Signature-verified fulfillment |
| POST | `/api/pipeline/stripe/simulate` | Local-test payment simulation; disabled unless explicitly enabled and always disabled with a real key |
| POST | `/api/pipeline/intake` | Save intake (requires paid order) |
| POST | `/api/pipeline/asset` | Upload logo/photo (requires paid order) |
| POST | `/api/pipeline/approval` | Approve or request a package-controlled revision |
| POST | `/api/pipeline/revision-checkout` | Purchase one separately consented revision add-on |
| POST | `/api/pipeline/hosting-renewal` | Separately authorize disclosed month-13 hosting |

Admin: `/admin/famtastic/{prospect,order,intake,project}` and
`/admin/famtastic/prospect/{id}/generate-studio`,
`/admin/famtastic/project/{id}/export.json|.md`.

## Proof Campaign

The **Proof Campaign** tier shows a prospect three AI-generated design
directions and lets them pick one plus a package before a 7-day expiry.

- **Entities:**
  - `proof_campaign` — `campaign_id` (unique string), `prospect_id` (ER →
    `famtastic_prospect`), `business_name`, `status`
    (active|expired|converted|archived, default active), `expires_at`
    (defaults to +7 days), `selected_variant` (a|b|c, nullable),
    `selected_package` (essential_199|business_499, nullable),
    `stripe_order_id`, `selected_at`, `created`, `changed`.
  - `proof_variant` — `campaign_id` (ER → `proof_campaign`), `direction_id`
    (a|b|c), `direction_name`, `artifact_path` (filesystem path to
    `web/proofs/<campaign_id>/<direction>/index.html`), `design_dna`
    (text_long JSON), `thumbnail_path` (nullable), `preview_url`
    (`<host>/web/proofs/<campaign_id>/<direction>/`), `created`.
- **Admin UI:** `/admin/famtastic/proof-campaigns` and
  `/admin/famtastic/proof-variants` under the FAMtastic Pipeline admin menu.
- **Token API:** `POST /api/pipeline/proof-campaign` (idempotent create →
  generates 3 variants), `GET /api/pipeline/proof-campaign`,
  `POST /api/pipeline/proof-campaign/select` — all behind the same
  `X-Prospect-Token` auth as the rest of the pipeline.
- **Stripe:** checkout session metadata carries
  `{campaign_id, selected_variant, selected_package}`; the webhook marks the
  campaign `converted`. The existing intake flow is untouched.
- **Cron:** `drush proof-campaign:expire` sets `status = 'expired'` where
  `expires_at < now` and `status = 'active'`.

## Environment variables (never commit real values)

Read from `getenv()` or Drupal settings; secrets never live in config.

| Variable | Used for | Default |
|---|---|---|
| `STRIPE_SECRET_KEY` | Real Stripe test API (sk_test_…). If unset → stub gateway. | *(unset → stub)* |
| `STRIPE_PRICE_ID_ESSENTIAL_199` | Optional pre-created Essential price. | *(inline price_data)* |
| `STRIPE_PRICE_ID_BUSINESS_499` | Optional pre-created Business price. | *(inline price_data)* |
| `STRIPE_PRICE_ID_REVISION_ADDON_75` | Optional pre-created revision add-on price. | *(inline price_data)* |
| `STRIPE_WEBHOOK_SECRET` | Webhook signature verification. | `whsec_local_dev_secret` (local dev only) |
| `FAMTASTIC_ALLOW_PAYMENT_SIMULATION` | Enables the local-only simulation endpoint when truthy. Never enable in production. | `false` |
| `FRONTEND_BASE_URL` | Success/cancel + outreach links. | `frontend_base_url` config (`http://localhost:5173`) |
| `FAMTASTIC_PUBLIC_BASE_URL` | Public tracking and Site Studio callback base. | configured frontend URL |
| `SITE_STUDIO_URL` | Remote proof-job endpoint; unset uses the local deterministic adapter. | *(unset)* |
| `SITE_STUDIO_DISPATCH_SECRET` | HMAC for remote proof dispatch. | *(unset)* |
| `SITE_STUDIO_CALLBACK_SECRET` | HMAC for asynchronous proof callbacks. | *(unset)* |
| `FAMTASTIC_EMAIL_TRANSPORT` | Campaign transport (`disabled`, `memory`, or configured real adapter). | `disabled` |
| `FAMTASTIC_EMAIL_WEBHOOK_SECRET` | HMAC for provider delivery events. | *(unset)* |
| `FAMTASTIC_ALLOW_REAL_OUTREACH` | Independent real-send approval gate. | `false` |
| `FAMTASTIC_CUSTOMER_RELEASE_ROOT` | Private immutable customer releases. | Drupal private path |
| `FAMTASTIC_CUSTOMER_DEPLOY_ROOT` | Isolated customer deployment roots. | *(required for deployment)* |
| `FAMTASTIC_CUSTOMER_PUBLIC_BASE` | Public customer-site URL base. | *(unset)* |
| `FAMTASTIC_DEPLOY_TRANSPORT` | Customer deployment transport. | `disabled` |
| `FAMTASTIC_ALLOW_CUSTOMER_DEPLOYMENTS` | Independent customer deployment gate. | `false` |
| `FAMTASTIC_DOMAIN_VERIFY_MODE` | Read-only DNS/TLS verifier. | `disabled` |
| `FAMTASTIC_HOSTING_MONTHLY_AMOUNT` | Disclosed recurring hosting price in minor units. | `0` (unavailable) |
| `FAMTASTIC_HOSTING_BILLING_PROVIDER` | Recurring billing adapter (`memory` is acceptance-test only; no live adapter exists yet). | `disabled` |

Non-secret settings live in `config/install/famtastic_pipeline.settings.yml`
(package name/price/inclusions, token TTL, support email).

## Quick start

```bash
cd backend
composer install
./setup.sh                      # installs Drupal (SQLite), enables the API stack
./vendor/bin/drush en famtastic_pipeline -y
./vendor/bin/drush fpc --business-name="Joe's Plumbing" --category="Plumber" --source=google
# → prints a secure link: http://localhost:5173/p/<token>
```

Run the full local proof from the repo:

```bash
scripts/acceptance-autonomous-pipeline.sh
```

## Tests

Unit tests (pure logic) live in `tests/src/Unit`. They need `drupal/core-dev`:

```bash
cd backend
composer require --dev drupal/core-dev:^11 -W
vendor/bin/phpunit -c web/core/phpunit.xml.dist web/modules/custom/famtastic_pipeline/tests/src/Unit
```

The end-to-end HTTP proof is `scripts/e2e-proof.sh`.
