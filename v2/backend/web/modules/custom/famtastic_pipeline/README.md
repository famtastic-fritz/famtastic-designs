# FAMtastic Pipeline

Prospect → confirm → pay ($199 test) → intake → Site Studio request →
project/approval pipeline for FAMtastic Designs. This is the bounded local proof
(V1) described in `v2/docs/FAMTASTIC_PROSPECT_PIPELINE_V1.md`.

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
| POST | `/api/pipeline/stripe/simulate` | Stub-mode-only test payment (refuses when a real key is set) |
| POST | `/api/pipeline/intake` | Save intake (requires paid order) |
| POST | `/api/pipeline/asset` | Upload logo/photo (requires paid order) |
| POST | `/api/pipeline/approval` | Approve / request one revision |

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
| `STRIPE_PRICE_ID` | Optional pre-created $199 price (from `scripts/stripe-setup.sh`). | *(inline price_data)* |
| `STRIPE_WEBHOOK_SECRET` | Webhook signature verification. | `whsec_local_dev_secret` (local dev only) |
| `FRONTEND_BASE_URL` | Success/cancel + outreach links. | `frontend_base_url` config (`http://localhost:5173`) |

Non-secret settings live in `config/install/famtastic_pipeline.settings.yml`
(package name/price/inclusions, token TTL, support email).

## Quick start

```bash
cd v2/backend
composer install
./setup.sh                      # installs Drupal (SQLite), enables the API stack
./vendor/bin/drush en famtastic_pipeline -y
./vendor/bin/drush fpc --business-name="Joe's Plumbing" --category="Plumber" --source=google
# → prints a secure link: http://localhost:5173/p/<token>
```

Run the full local proof from the repo:

```bash
v2/scripts/e2e-proof.sh          # 26 assertions, exits 0 when green
```

## Tests

Unit tests (pure logic) live in `tests/src/Unit`. They need `drupal/core-dev`:

```bash
cd v2/backend
composer require --dev drupal/core-dev:^11 -W
vendor/bin/phpunit -c web/core/phpunit.xml.dist web/modules/custom/famtastic_pipeline/tests/src/Unit
```

The end-to-end HTTP proof is `v2/scripts/e2e-proof.sh`.
