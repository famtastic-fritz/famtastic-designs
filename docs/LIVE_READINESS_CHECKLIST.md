# Live readiness checklist

This is the release boundary for every human or agent. The canonical application
is the React/Vite `frontend/` plus the Drupal `backend/`. The historical root
Nuxt/Directus project is not deployed and its commands are not release gates.

## Accepted baseline — 2026-07-31

- GitHub `main`, tag `v3.0.3`, production frontend marker, and production
  backend marker: `69a7b6cba4d53231d22493fc0c2b89b647a8479b`
- production Drupal: database connected, Drupal 11.4.4, no pending database
  updates at acceptance time
- autonomous acceptance: Essential 40/40 and Business 41/41
- apex and `www` React roots populated with no browser exceptions or failed
  requests
- portal token CORS verified for apex and `www`
- real outreach, live payment, customer deployment, and recurring-hosting
  provider gates remained disabled

This is the accepted baseline, not permission to skip validation. Resolve the
current GitHub `main` SHA and repeat all applicable checks for every release.

## Mandatory order

1. Obtain explicit approval to merge and deploy.
2. Review and merge the approved change.
3. Check out the exact resulting GitHub `main` SHA in a clean worktree.
4. Run `scripts/acceptance-autonomous-pipeline.sh`.
5. Run both deployment scripts without `--apply`; preflight must pass.
6. Configure approved provider credentials in the server environment or
   `settings.php`, never in Git.
7. Run `scripts/deploy-backend-godaddy.sh --apply`.
8. Run `scripts/deploy-frontend-godaddy.sh --apply`.
9. Confirm `.backend-release` and `.frontend-release` both contain the exact
   merged `main` SHA.
10. Complete the production-safe verification below.

Never upload individual source or build files by SSH/SCP as a normal release.
Never deploy a PR SHA directly. Both scripts reject anything other than current
GitHub `main`.

## Production configuration contract

Keep all live-action gates disabled until their specific approval is granted.

| Setting | Purpose | Safe pre-activation value |
|---|---|---|
| `FRONTEND_BASE_URL` | Customer return and portal links | `https://famtasticdesigns.com` |
| `FAMTASTIC_PUBLIC_BASE_URL` | Public FAMtastic links, including signed concept rooms | `https://famtasticdesigns.com` |
| `STRIPE_SECRET_KEY` | Stripe API; use test mode during verification | unset until approved |
| `STRIPE_WEBHOOK_SECRET` | Stripe signature verification | required before Stripe activation |
| `STRIPE_PRICE_ID_ESSENTIAL_199` | Authoritative $199 Checkout price | approved test/live Price ID |
| `STRIPE_PRICE_ID_BUSINESS_499` | Authoritative $499 Checkout price | approved test/live Price ID |
| `STRIPE_PRICE_ID_REVISION_ADDON_75` | Authoritative $75 revision price | approved test/live Price ID |
| `proof_runner_transport` | FAMtastic preview-runner route | `disabled` until a compatible FAMtastic-controlled runner is approved |
| `proof_runner_external_url` | FAMtastic-controlled preview-runner endpoint | unset until its real preflight is proven |
| `FAMTASTIC_PROOF_RUNNER_SECRET` | HMAC for FAMtastic preview-runner dispatch | unset until the approved runner is configured |
| `SITE_STUDIO_URL` | Retired preview-dispatch setting | **must remain unset** for preview delivery |
| `SITE_STUDIO_DISPATCH_SECRET` | Retired preview-dispatch HMAC | **must remain unset** for preview delivery |
| `SITE_STUDIO_CALLBACK_SECRET` | HMAC for a later selected-build success packet only | unset until the private build-packet return is approved |
| `FAMTASTIC_EMAIL_TRANSPORT` | Outreach adapter | `disabled` |
| `FAMTASTIC_EMAIL_WEBHOOK_SECRET` | Provider event HMAC | required before provider events |
| `FAMTASTIC_ALLOW_REAL_OUTREACH` | Separate bulk-send gate | `false` |
| `FAMTASTIC_ALLOW_PAYMENT_SIMULATION` | Browser payment simulation | `false` in production |
| `FAMTASTIC_CUSTOMER_RELEASE_ROOT` | Private immutable releases | approved path outside web root |
| `FAMTASTIC_CUSTOMER_DEPLOY_ROOT` | Isolated customer document roots | approved path |
| `FAMTASTIC_CUSTOMER_PUBLIC_BASE` | Customer-site URL base | approved HTTPS base |
| `FAMTASTIC_DEPLOY_TRANSPORT` | Customer-site deployment adapter | `disabled` |
| `FAMTASTIC_ALLOW_CUSTOMER_DEPLOYMENTS` | Separate customer deployment gate | `false` |
| `FAMTASTIC_DOMAIN_VERIFY_MODE` | Read-only DNS/TLS adapter | `disabled` until configured |
| `FAMTASTIC_HOSTING_MONTHLY_AMOUNT` | Disclosed month-13 price in minor units | `0` until approved |
| `FAMTASTIC_HOSTING_BILLING_PROVIDER` | Recurring billing adapter | `disabled`; only test `memory` exists today |

`memory`, `fixture`, `local`, and the stub payment gateway are test adapters,
not production providers.

Live recurring hosting remains intentionally unavailable until a separately
reviewed provider adapter is implemented. The current code proves consent,
scheduling, retry, cancellation, and suspension with the time-compressed
`memory` adapter; it cannot charge a real renewal.

## FAMtastic preview-path release — Site Studio remains private

This is an additional release gate for public/portal previews. It must pass
before an owner stages or approves a customer proof invitation.

1. Confirm the deployment includes the preview-delivery, proof-variant, Build
   DNA, and revision migrations, then verify them with Drupal after deploy.
2. Configure a FAMtastic-controlled proof runner only. Leave
   `site_studio_dispatch`, `SITE_STUDIO_URL`, and
   `SITE_STUDIO_DISPATCH_SECRET` disabled/unset.
3. Execute the runner's declared preflight and retain the real provider/model
   receipt, fallback result, and Build DNA preflight. A desktop login or old
   Studio fixture is not enough.
4. Complete one controlled preview run and verify that FAMtastic—not Site
   Studio—stores the HTML, screenshots, hashes, final Build DNA, browser QA,
   and independent visual-review decision in its proof slots.
5. Browser-test the FAMtastic owner review, signed concept room, unlisted
   revocation, same-email claim, authenticated portal, selection, and revision
   paths. Confirm no preview link points to Studio, localhost, or a workstation.
6. Stage the FAMtastic transactional invitation and verify its frozen proof set
   and outbox record. The named owner approval remains required before send.
7. Confirm Site Studio has no public listener, no preview provider credential,
   and no preview/campaign email authority. A selected-build packet handoff is
   a later, separate private/offline gate.
8. Record the exact `main` SHA, migration results, runner receipt, Build DNA,
   browser evidence, owner approval, and delivery evidence in
   `docs/PRODUCTION_DEPLOY_LOG.md`.

Do not deploy a preview release that depends on Site Studio returning preview
artifacts. The governing boundary is
`docs/architecture/FAMTASTIC_PREVIEW_TO_BUILD_BOUNDARY_V1.md`.

## Production-safe verification

These checks must not send outreach, charge a card, buy a domain, mutate DNS, or
create paid cloud resources:

- Confirm both release marker SHAs equal GitHub `main`.
- Run `drush status`, `drush updatedb:status`, and a cache rebuild.
- Confirm all pipeline entity definitions load.
- Verify the apex and `www` frontend in a real browser.
- Confirm populated React root, expected heading, no console exceptions, no
  failed asset requests, and JavaScript/CSS MIME types.
- Verify public quote/contact capture and token-invalid/expired responses.
- Confirm payment simulation, real outreach, customer deployment, domain
  mutation, and recurring billing gates remain disabled.
- Review recent Drupal error logs.
- Record the release SHA, backup paths, verifier, and results in
  `docs/PRODUCTION_DEPLOY_LOG.md`.

## Separate approvals still required

- qualified legal review of terms, privacy, outreach, refund, domain, hosting,
  renewal, suspension, and data-retention language;
- real outreach campaign and sending identity;
- live Stripe charges or subscriptions;
- customer-site production deployment;
- domain purchase or DNS mutation;
- paid cloud resources.

Passing test-mode acceptance does not grant any of these approvals.
