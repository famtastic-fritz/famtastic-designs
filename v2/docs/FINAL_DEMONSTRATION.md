# FAMtastic Designs — Prospect Pipeline V1: Final Demonstration Record

Branch: `feat/famtastic-prospect-pipeline-v1` — not pushed, not merged, not deployed.

## Commit hashes (this branch)

| Hash | Commit |
|---|---|
| `9dfbb734` | fix: ensure files dir exists on clean checkout |
| `910137f8` | docs: record final local pipeline demonstration |
| `e52ae9e9` | test: add prospect pipeline end-to-end proof |
| `2696a22f` | feat: add prospect customer journey frontend |
| `c4a3d9f5` | chore: add v2 drupal backend base |
| `6d891dfc` | feat: pipeline tests, README, run docs |
| `1ec34c57` | fix: mark token-scoped GETs no-store |
| `7e8a7966` | feat: prospect pipeline backend module |
| `cc6a588c` | docs: add 20-deliverable traceability matrix |
| `be0bfb49` | docs: prospect pipeline V1 plan |

## What is committed

- **Frontend committed** — all five React screens (`ProspectLandingPage`,
  `PaymentReturnPage`, `PaymentCancelPage`, `IntakePage`, `ProofStatusPage`),
  `PipelineShell`, routes in `App.jsx`, API client `api/pipeline.js`, styles
  `pipeline.css`, Vite proxy in `vite.config.js`, and `.env.example`.
- **Scripts committed** — `scripts/e2e-proof.sh` and `scripts/stripe-setup.sh`.
- **Backend committed** — the `famtastic_pipeline` module plus the backend base
  (composer.json/lock, settings.php, services.yml, config, setup scripts, theme)
  needed to install and bootstrap from a clean checkout.
- **Docs committed** — plan + matrix (`FAMTASTIC_PROSPECT_PIPELINE_V1.md`), the
  executed E2E record (`E2E_PROOF_RUN.md`), and this file.

**Not committed (correctly ignored):** secrets/`.env`, the SQLite runtime DB,
uploaded test assets and generated private files (`/private/*`), `vendor/`,
`node_modules/`, `dist/`, and logs. Verified: `git ls-files v2` contains none of
these.

`git status` is clean for `v2/`. (The only pending changes in the wider repo are
pre-existing Nuxt marketing-site files under `pages/`, `data/`, `docs/` that
belong to a different branch of work and are intentionally untouched.)

## Test & build results

| Check | Result |
|---|---|
| Unit tests (`phpunit`, module `tests/src/Unit`) | **11 tests, 33 assertions — OK** |
| End-to-end proof (`scripts/e2e-proof.sh`) | **26 passed, 0 failed** |
| Frontend build (`npm run build`) | **built clean, 0 errors** |
| Composer validate | **`composer.json` is valid** |
| Composer audit | **No security vulnerability advisories** |

## Clean-checkout reproducibility

Performed in a fresh `git worktree` of `feat/famtastic-prospect-pipeline-v1`
(no `vendor/`, no `node_modules/`, no DB — everything rebuilt from committed
files):

1. `composer install` (backend) — ✅
2. `npm install` (frontend) — ✅
3. Bootstrap Drupal via committed `setup.sh` — ✅ (fresh SQLite install)
4. `drush en famtastic_pipeline` — ✅
5. Unit tests — ✅ 11/11
6. E2E proof — ✅ **26/26**
7. Frontend build — ✅
8. Composer validate — ✅
9. Composer audit — ✅ no advisories

This confirms the proof does not depend on any uncommitted file. A
reproducibility defect found during this step (a fresh checkout lacked the
`web/sites/default/files` directory, breaking the SQLite install) was fixed in
`9dfbb734` (tracked `.gitkeep` + `mkdir -p` in `setup.sh`).

## Stripe status

**Application flow proven with StubGateway; real Stripe test-mode checkout
remains unexecuted pending test credentials.**

- No `STRIPE_SECRET_KEY` / `STRIPE_WEBHOOK_SECRET` was present in the environment
  or Drupal settings, and no Stripe CLI is installed. Per policy, secret keys
  were not requested in chat.
- **Stripe test product/price: NOT created** (requires a `sk_test_` key). The
  repeatable script `scripts/stripe-setup.sh` is ready: it creates the
  "FAMtastic Basic Website" product + a $199 USD one-time price idempotently,
  prints the `price_...` id, refuses `sk_live_` keys, and dry-runs cleanly with
  no key.
- The **webhook signature verification and idempotent fulfillment** that the real
  path depends on ARE proven — the E2E posts a genuine HMAC-SHA256-signed
  `checkout.session.completed` event (same scheme Stripe uses), and unit tests
  cover valid/tampered/expired signatures. Only the live Stripe API round-trips
  (create product/price/session; receive a Stripe-originated webhook) are
  unexecuted.

### To execute the real Stripe test path later

```bash
export STRIPE_SECRET_KEY=sk_test_xxx
export STRIPE_WEBHOOK_SECRET=whsec_xxx           # from `stripe listen` or the dashboard
v2/scripts/stripe-setup.sh                       # creates product + $199 price → prints STRIPE_PRICE_ID
export STRIPE_PRICE_ID=price_xxx
# with the backend served and `stripe listen --forward-to <backend>/api/pipeline/stripe/webhook`,
# drive the /p/<token> flow and pay with test card 4242 4242 4242 4242.
```

## Known remaining limitations

- Real Stripe test-mode checkout not executed (no credentials) — stub path proven.
- Site Studio submission, outreach, deploy, domain, launch remain manual (per V1 scope).
- Email logs intent (no SMTP credentials assumed); `OutreachMailer` is the swap seam.
- `drupal/core-dev` is a dev-only dependency (added to run PHPUnit).

## Restart the local demonstration

```bash
# 1. Backend (Drupal on :8080)
cd v2/backend
composer install                      # first time only
./setup.sh                            # first time only (installs Drupal, SQLite)
./vendor/bin/drush en famtastic_pipeline -y
./vendor/bin/drush runserver 127.0.0.1:8080 &

# 2. Frontend (Vite on :5173)
cd ../../frontend
npm install                           # first time only
npm run dev &

# 3. Create a prospect and open the secure link
cd ../v2/backend
./vendor/bin/drush fpc --business-name="Sunrise Cafe" --category="Coffee Shop" --source=google
# → open the printed http://localhost:5173/p/<token>
#   confirm → Pay $199 (test) → intake + upload → (admin records proof) → approve

# 4. Or run the whole proof headless (no servers needed — it starts its own):
cd ..
bash scripts/e2e-proof.sh             # 26/26, exits 0
```
