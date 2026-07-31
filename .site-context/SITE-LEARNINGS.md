# FAMtastic Designs site learnings

This file records production behavior, deployment constraints, incident
findings, and operator guidance that should survive across agents and sessions.
Git-tracked documentation and deployment scripts remain the authoritative
source of truth.

## 2026-07-31 — Autonomous lead-to-launch acceptance

Observation:

- Independent component tests can all pass without proving that one attributed
  lead actually travels through the complete business lifecycle.
- A revision add-on that only returns `402` and advertises a price is not
  purchasable until checkout, signed fulfillment, idempotency, and allowance
  mutation are connected.
- Returning lifecycle fields from an API is insufficient if the customer
  frontend does not render them or cannot submit recurring authorization.

Action taken:

- Added correlated $199 and $499 journeys that retain the same campaign,
  prospect, proof selection, orders, project, deployment, domain, hosting
  entitlement, and subscription from import through renewal.
- Added authoritative $75 revision add-on checkout and signed webhook
  fulfillment that increases the project revision allowance exactly once.
- Added persisted payment and proof-conversion events and corrected analytics
  so add-on revenue does not inflate new-site conversion counts.
- Added customer-visible deployment, domain, DNS, SSL, included-hosting, and
  subscription status.
- Added separate customer recurring-hosting authorization. The monthly price is
  server-configured and the endpoint remains unavailable when pricing or the
  billing provider is not configured.
- Added bounded retry/exhaustion evidence for the actionable exception queue.

Permanent rules:

- A full-journey acceptance claim requires correlated identifiers across every
  stage; a collection of unrelated fixtures is supporting evidence only.
- New-site sales and add-on purchases are separate commercial events. Revenue
  may include both, but conversion and cost-per-sale use new-site orders only.
- Recurring hosting may never be inferred from the original website purchase.
  It requires a separately disclosed amount, start date, and customer consent.
- CI must operate on the canonical `frontend/` and `backend/` trees. The
  historical root Nuxt/pnpm project is not a production quality gate; retaining
  its old `pnpm lint` and `pnpm typecheck` workflows falsely implies that it is
  still an active application.
- Live providers, outreach, DNS mutation, domain purchase, and production
  release remain explicit approval gates.

## 2026-07-30 — Blank page caused by flattened Vite assets

Observation:

- Production returned HTTP 200, but the React application rendered a blank
  page.
- `index.html` requested `/assets/index-D_uAwkFB.js` and
  `/assets/index-DiIN1rSa.css`.
- Those bundles had been deployed as `public_html/index-D_uAwkFB.js` and
  `public_html/index-DiIN1rSa.css`, without the required `assets/` directory.
- The missing JavaScript request was handled by the SPA fallback, which
  returned `index.html` with `text/html`. The browser rejected that response as
  a JavaScript module, so React never mounted.
- Server timestamps showed NVM/Node installation at 14:24:38 on July 23,
  branch checkout at 14:26:08, and the malformed deployment at 14:27:00.
- Historical script commit `7825009e` used
  `cp -r v2/frontend/dist/assets/ .`, which flattened the directory. Production
  commit `1654cf22` confirmed that the hashed JS and CSS files were committed
  at repository root.

Interpretation:

- The quote/contact and SEO commits were not themselves defective. They were
  initially transported through direct SSH/SCP, which created Git/production
  drift but was not the immediate blank-page mechanism.
- The immediate cause was the later deployment script flattening the Vite
  artifact.
- The systemic cause was the lack of one validated Git-to-production release
  boundary. `public_html` had been used as both a Git checkout and a mixed
  runtime directory containing frontend, Drupal, Composer, and hosting files.
- HTTP 200 is not sufficient SPA verification because a fallback can return
  HTML for missing JavaScript and CSS URLs.
- Local source and `public_html` should not have identical directory
  structures. Git source belongs in a private checkout; `public_html` should
  contain only the runtime artifact plus the existing Drupal/hosting files it
  owns.

Action taken:

- Restored the compiled bundles under `public_html/assets/` to recover the
  live site.
- Promoted the former `v2` application source into the canonical repository
  structure and removed the obsolete `v2` directory.
- Replaced manual/local artifact upload with
  `scripts/deploy-frontend-godaddy.sh`.
- The shared script builds the exact merged `main` commit in
  `~/deploy/famtastic-designs/releases/<commit>/source`, outside
  `public_html`.
- Added a repository `.nvmrc` pin for Node 22 and a matching frontend engine
  constraint.
- Added clean-worktree/current-main checks, remote preflight, referenced-asset
  validation, frontend-only backup, structure-preserving promotion, assets
  before `index.html`, MIME verification, and a machine-readable
  `.frontend-release` record.
- Fixed GoDaddy-specific NVM nounset and `/dev/fd` incompatibilities discovered
  during the first fresh release. These fixes were merged through PRs 11–13.
- Successfully deployed commit `ebbbfa0c0e521e7d9de675eaafaf4cdf2a4e39ca`
  using Node `v22.23.2`.
- Verified apex and `www` with a real browser: populated `#root`, correct
  heading, no console errors, no page exceptions, and no failed requests.

Permanent rules:

- Git `main` is the only source of deployable truth.
- Every agent and human uses the same Git-tracked deployment script.
- Never build from or run `git pull` inside `public_html`.
- Never flatten `frontend/dist`; preserve `dist/assets/*` as
  `public_html/assets/*`.
- Never use `rsync --delete` against the mixed document root.
- Promote compiled assets before replacing `index.html`.
- Require a rollback archive and matching `.frontend-release` commit.
- Verify both apex and `www` in a real browser after every applied release.
- Treat `.wolf` as supplemental workspace memory, not the canonical site
  incident or deployment record.

Open follow-up:

- The canonical React frontend dependency audit now reports zero
  vulnerabilities after the separately tested React, router, and Vite upgrade.
- The Vite build reports a legacy root Nuxt TypeScript configuration warning.
  It does not block the React build, but the configuration boundary should be
  cleaned up separately.
- The legacy Git checkout and stale root-level bundles in `public_html` are no
  longer part of the release lane. Remove them only through a separately
  backed-up ownership audit so Drupal and hosting files are not disturbed.
