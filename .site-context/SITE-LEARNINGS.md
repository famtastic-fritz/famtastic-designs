# FAMtastic Designs site learnings

This file records production behavior, deployment constraints, incident
findings, and operator guidance that should survive across agents and sessions.
Git-tracked documentation and deployment scripts remain the authoritative
source of truth.

## 2026-08-26 — Dynamic proof links need an Apache shell fallback, not a static route directory

Observation: an enabled signed proof share could be resolved anonymously by
Drupal's `/web/api/proof-shares/...` endpoint, while the customer-facing
`/proofs/share/...` URL returned Apache's bare 404 before React loaded. The
frontend had the `ProofSharePage` route, but no physical directory can exist
for every signed request URL and the deployed root `.htaccess` had no fallback.

Guidance:

- Use a narrow `/proofs/share/<uuid>/<signature>` rewrite, not a generic SPA
  fallback. This document root also hosts Drupal and static campaign experiences
  that must retain their own routing behavior.
- Include root `.htaccess` in every frontend-route deploy backup and verify its
  exact promotion. A JavaScript-only rollback does not restore Apache behavior.
- Treat a direct `GET /web/api/proof-shares/... = 200` plus a public-room 404
  as routing-shell evidence, not as proof that a token was revoked or a client
  lacks permission. Verify both anonymous API resolution and the public browser
  route after each frontend deployment.

## 2026-08-25 — Heartbeat runs must append + commit their own log line before exit

Observation: two CEO heartbeat sessions stranded their HEARTBEAT.md append
(08:17Z left work uncommitted; 14:43Z's edits were absorbed into operator
commit 6a1a47b8, leaving a log gap found only by cross-referencing change-log
citations against HEARTBEAT.md).

Rule: every heartbeat run appends its dated line AND commits its ledger unit in
the same session. Reconciliation sweeps grep recipe change-logs for "heartbeat
HH:MMZ" citations missing from HEARTBEAT.md.

## 2026-08-25 — Local Postiz answers 502 while "healthy"; concurrent agents share one tree

Observation:
During publish-executor validation the local Postiz container reported docker
health=healthy while every API call returned nginx 502. Root cause chain: the
v2.22.1 backend lazily creates `mastra_*` tables at boot, and under host CPU
~100% the cold-start DDL raced/dropped its PG connection
(`MASTRA_STORAGE_PG_CREATE_TABLE_FAILED`, then "Connection terminated
unexpectedly"), killing pm2's `backend` process while `frontend` stayed up.
A second flare hit mid-session as request-level hangs (curl timeouts) without
container restart.

Guidance:
- Never trust the Postiz container health flag for API availability — probe
  `/api/public/v1/is-connected`. The health check reflects the frontend, not
  the pm2 backend on port 3000 inside the same container.
- Fix for the mastra cold-start failure: `docker exec postiz pm2 restart backend`
  once; tables persist afterwards. Symptom trail lives in
  `/root/.pm2/logs/backend-error.log` (inside the container).
- Any client of this stack must retry HTTP ≥500/timeouts with exponential
  backoff and treat a persistent 502 as provider-DOWN → BLOCKED report, never
  a silent skip. `publish-executor.php` encodes this policy.
- Two agents edited one working tree simultaneously today (@fam-ops +
  attribution work). Update-hook numbers collided invisibly until read-back
  (8036/8037 taken → shifted to 8038); shared-file commits must be selective
  (`git apply --cached` with only own hunks). Rule: before adding an update
  hook or editing a dirty shared file in this repo, re-read it from disk and
  check `git status` first.

## 2026-08-25 — Alert floods train the operator to ignore alerts; "late" needs a grace window

Observation:
237 of the first 267 outbox sends were false-positive "Automation worker late"
alerts. The monitor flagged any worker whose `next_due` had passed, but three
workers share one every-5-minute crontab cadence, so a due-but-running worker
was paged on nearly every cycle. The real automation queue processed zero jobs
during the same window — the alerting loop was the only thing "working", and it
was crying wolf.

Guidance:
- A health check that fires during normal operation is mis-specified, not
  informative. "Late" must mean *no sign of life within a grace window*
  (here: `last_finished` older than 1800s), never merely an expired schedule
  time when siblings share the cron line.
- When an alert channel's false-positive ratio exceeds ~50%, treat the channel
  itself as incident #1: fix the detector before trusting anything it says.
- PHP gotcha of the run: writing `*/5` inside a docblock terminates the comment
  (`*/` closes it) and produces a confusing parse error; say "every-5-minute"
  in comments. Caught by `php -l` before execution.
- Fix landed locally with regression harness
  `scripts/e2e-worker-late-guard.sh`; prod behavior changes only at the next
  approved backend deploy (gate).

## 2026-08-24 — Postiz public URL is now permanent; five failure modes documented

Observation:

- The self-hosted Postiz scheduler previously ran behind an ephemeral
  trycloudflare tunnel. Every tunnel rotation broke (a) the login session,
  (b) OAuth callback whitelists in four developer portals, and (c) every media
  row, because `Media.path` stores absolute URLs. The recurring "infinite
  spinner" was the auth cookie being hostname-bound while config pointed at a
  dead host.
- Resolution: permanent ngrok static domain + `scripts/restart-postiz-tunnel.sh`
  which rebuilds env with secret preservation and rewrites Media paths on every
  run. Full detail lives in `docs/SYSTEMS.md` (systems inventory — agents must
  probe systems rather than trust stale docs; this incident started from a
  handoff doc claiming OAuth "never completed" when Facebook had been connected
  for two weeks).

Operator guidance:

- If Postiz spins or images break: run the restart script, then hard-refresh on
  the ngrok hostname exactly.
- Instagram connections use the standalone provider only; dev-mode Meta apps
  require the account to hold an ACCEPTED tester invite (Instagram → Settings →
  Website permissions → Apps and websites → Tester Invites).
- Never assert channel/connection state without querying the Integration table;
  docs lag reality.

## 2026-08-02 — Operator totals require exact-record drill-downs

Observation:

- The first operations dashboard exposed useful totals but rendered its metric
  tiles as static containers. An operator could see a paid-order count without
  being able to open the orders that produced it.
- A reporting number without its records is not sufficient evidence for
  operating or auditing the pipeline.
- The same production release also showed that Vite's route-specific
  `index.html` shells are first-class deployment artifacts: excluding every
  basename named `index.html` left nested routes on stale JavaScript even when
  the root route was current.
- GoDaddy reported ample filesystem space and inodes while the account still
  rejected `npm ci` writes with system error `-122`. Eight private release
  worktrees retained about 800 MB of reproducible `node_modules` data, so the
  effective constraint was the hosting account quota rather than the disk.

Action taken:

- Made all eight dashboard metrics semantic links to admin-only, paginated,
  filtered record pages.
- Added exact paid-order details and equivalent evidence views for campaigns,
  prospects, proofs, send/click events, jobs, and exceptions.
- Added permanent acceptance that renders every metric page and verifies that
  the paid-order tile count agrees with the stored paid orders.
- Anchored the frontend deploy exclusion to the artifact-root `index.html`,
  promoted nested route shells, and verified them using a normal manifest that
  works on the GoDaddy host without `/dev/fd`.
- Removed only the disposable dependency trees from superseded/failed private
  release worktrees and added an exit trap to the shared deploy script so each
  future build cleans its own `node_modules`, whether it succeeds or fails.

Permanent rules:

- Every operations KPI must link to the exact records represented by its
  current filter; totals alone are not an operator source of truth.
- Use semantic URLs and links for board navigation so keyboard access, browser
  history, direct links, and authorization all behave normally.
- Keep metric definitions and drill-down filters identical and acceptance-test
  their count-to-record relationship.
- Route-specific frontend shells belong to the release artifact and must be
  promoted and verified with the bundles they reference.
- Private release source and compiled `dist` output may be retained for audit;
  `node_modules` is a reproducible build cache and must be removed after every
  remote deployment attempt to prevent account-quota exhaustion.
- Historical deterministic proof builds must remain attributed to the
  deterministic renderer with agent `none`; never invent a Shay prompt or
  agent run that did not occur.
- Local Site Studio refreshes become production truth only through the
  checksum-gated export/import lane. Existing public proof URLs can be updated
  in place without emailing a new link.

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

## 2026-08-24 — admin-cx session: portal crawl + projects flow

- Observation: owner screenshot showed messages panels overflowing and the
  projects intake as a ~60-field wall. Overflow guards for messages already
  landed (BRUTAL-REVIEW followup); the wall needed IA work, not CSS.
- Guidance: progressive disclosure (3-field step 1 → draft save → six grouped
  fieldsets + sticky bar) is now the pattern for any long customer-facing form;
  keep backend field names byte-identical when re-flowing forms and verify with
  a name-parity diff against HEAD (done: 64/64 preserved).
- Guidance: `scripts/e2e-portal-links.sh` is the required green gate before any
  portal CX change ships toward deploy; treat its UNREACHABLE warnings as
  findings for Fritz, not noise.
- Incident note: during this session a shell cwd drift caused a `git stash pop`
  to run in the parent vault repo (`~/Development/FAMtastic`). Repaired by
  surgically restoring exactly the seven stash-touched paths to HEAD (stash
  entry left intact); unrelated live edits were untouched. Rule: always pin
  repo-relative operations with `git -C <repo>` or the bash tool's workdir;
  never bare `cd ..` chains before git writes.
