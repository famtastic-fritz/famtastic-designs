# Production Deploy Log

## 2026-08-02 — Operations telemetry, contact correction, and route-shell release

- Repository: `famtastic-fritz/famtastic-designs`
- Source branch: `main`
- Released implementation commit:
  `06725ab88b06c70234ba24365fb43c9d1f303c45`
- Clickable operations metric implementation commit:
  `84a21e799a7df52f2167fe78a0b47b980fbe3322`
- Final frontend and backend release markers are required to match the current
  GitHub `main` commit after this evidence entry is committed.
- Frontend release record: `~/public_html/.frontend-release`
- Backend release record: `~/public_html/.backend-release`
- Frontend rollback archive:
  `~/backups/famtastic-frontend-20260802T183407Z-06725ab88b06c70234ba24365fb43c9d1f303c45.tgz`
- Backend database rollback archive:
  `~/backups/famtastic-database-20260802T183439Z-06725ab88b06c70234ba24365fb43c9d1f303c45.sql.gz`
- Operations drill-down frontend rollback archive:
  `~/backups/famtastic-frontend-20260802T190554Z-84a21e799a7df52f2167fe78a0b47b980fbe3322.tgz`
- Operations drill-down backend module rollback archive:
  `~/backups/famtastic-pipeline-20260802T190534Z-84a21e799a7df52f2167fe78a0b47b980fbe3322.tgz`
- Operations drill-down database rollback archive:
  `~/backups/famtastic-database-20260802T190534Z-84a21e799a7df52f2167fe78a0b47b980fbe3322.sql.gz`
- Result: successful

### Released behavior

- Operations summary tiles are semantic drill-down links. Campaigns,
  prospects, ready proofs, sent emails, clicks, paid orders, open jobs, and
  open exceptions each expose the exact admin-only records behind their count.
- The public contact flow is email-first, uses
  `hello@famtasticdesigns.com`, contains no storefront hours, and requires no
  sales call.
- The duplicate `Book a Call` header action is replaced by `Start a Project`;
  the desktop and mobile navigation have distinct project and contact anchors.
- Authenticated Drupal operations pages expose campaign, recipient message,
  proof, build, prompt, agent, task, job, event, and sale evidence.
- The first pilot campaign has ten exact historical email snapshots and ten
  build records correctly attributed to the deterministic renderer with agent
  `none`.
- An offline, checksum-gated Site Studio bridge supports new proof bundles and
  in-place refreshes without treating the local workstation as an Internet
  service.

### Route-shell deployment finding and permanent control

The first frontend apply updated the root `index.html`, but `/contact/` still
loaded an older JavaScript bundle. Vite generates route-specific SEO shells
such as `dist/contact/index.html`; the deployment filter
`--exclude='index.html'` excluded every matching basename, so those nested
shells were never promoted.

The canonical deployment now excludes only `/index.html` at the artifact root,
promotes nested route shells before the root cutover, and verifies every route
shell byte-for-byte through a normal manifest that works on GoDaddy without
`/dev/fd`. The acceptance suite also proves that all generated route shells
reference the current release assets and that the anchored rsync filter copies
nested `index.html` files.

### Private release dependency cleanup

The final evidence-marker apply initially stopped during `npm ci`, before any
live frontend mutation, with hosting system error `-122`. Filesystem capacity
and inodes were healthy; private release worktrees had accumulated eight
reproducible `frontend/node_modules` trees totaling roughly 800 MB. Those build
caches were removed without touching source, compiled releases, production,
proofs, customer data, or backups. The canonical frontend deploy now removes
its release-local `node_modules` on every remote exit, including failed builds,
so every agent receives the same quota-safe behavior.

### Production acceptance evidence

- Frontend and backend release markers both matched the final commit.
- Seven route-specific SEO shells were promoted and verified.
- Apex and `www` `/contact/` returned 200, populated React `#root`, loaded the
  current JavaScript bundle, and produced no console errors or failed requests.
- Desktop and mobile showed the correct email, no hours, no `Book a Call`, and
  no horizontal overflow; the opened mobile menu showed both `Start a Project`
  and `Contact`.
- Drupal reported no pending database updates. The operations route, dashboard,
  message drill-down, build drill-down, message/build schema, and corrected
  contact source all rendered successfully.
- All eight operations metric links and their exact-record pages rendered on
  the production Drupal runtime. **Paid Orders** matched and exposed the one
  verified paid order in production; anonymous dashboard access returned 403.
- Chrome rendered populated React roots with the production heading on both
  apex and `www` after the metric release.
- Campaign `chandler-landing-pilot-2026-08-01-b1` reported ten messages, ten
  sent, ten exact snapshots, ten ready proof sets, and ten build records.

## 2026-07-30 — Fresh Git-to-GoDaddy frontend release completed

- Repository: `famtastic-fritz/famtastic-designs`
- Source branch: `main`
- Deployed commit:
  `ebbbfa0c0e521e7d9de675eaafaf4cdf2a4e39ca`
- Build location:
  `~/deploy/famtastic-designs/releases/ebbbfa0c0e521e7d9de675eaafaf4cdf2a4e39ca/source`
- Production document root: `~/public_html`
- Build runtime: Node `v22.23.2`, selected from repository `.nvmrc`
- Deployment command: `./scripts/deploy-frontend-godaddy.sh --apply`
- Release record: `~/public_html/.frontend-release`
- Rollback archive:
  `~/backups/famtastic-frontend-20260730T192809Z-ebbbfa0c0e521e7d9de675eaafaf4cdf2a4e39ca.tgz`
- Result: successful

### Why this release was required

The July 30 blank page traced to historical script commit `7825009e`, which
copied `v2/frontend/dist/assets/` into the production root. Production
`index.html` correctly requested `/assets/<bundle>`, but the bundles existed at
`/<bundle>`. The SPA fallback returned HTML for the missing JavaScript request,
so the browser rejected the module and React did not mount.

The earlier direct SSH/SCP lane was a contributing source-of-truth problem but
was not the immediate path error. Full evidence and the five-whys analysis are
in `docs/INCIDENT-2026-07-30-BLANK-PAGE-RCA.md`.

### Permanent release controls

1. Git `main` is the only deployable source of truth.
2. The exact commit is built in a private server directory outside
   `public_html`.
3. Node is pinned by `.nvmrc`.
4. Every asset referenced by `dist/index.html` must exist before promotion.
5. The frontend is backed up without touching Drupal or hosting runtime files.
6. Assets and other public files are promoted before `index.html`.
7. The deployment never flattens `dist` and never uses `rsync --delete`.
8. `.frontend-release` must match the requested commit before success is
   reported.
9. Apex and `www` require real-browser acceptance after deployment.

### Production acceptance evidence

Both `https://famtasticdesigns.com` and
`https://www.famtasticdesigns.com` returned HTTP 200 and rendered:

- a populated React `#root`;
- heading `Agentic AI Business Solutions Engineering Studio`;
- no browser console errors;
- no page exceptions;
- no failed network requests;
- JavaScript as JavaScript and CSS as CSS.

### Nonblocking follow-up

- `npm audit` reported three moderate and one high finding.
- Vite reported a legacy root Nuxt TypeScript configuration warning.
- The legacy checkout and stale root bundles in `public_html` require a
  separately backed-up ownership audit before removal.

## 2026-06-25 — Public rescue deploy attempt (blocked before live)
- Timestamp: 2026-06-25 local session
- Repo: `~/famtastic/sites/site-famtastic-designs`
- Deploy source branch: `famtastic/prod-public-rescue`
- Deploy source base: `famtastic/site-v1-production-proof`
- Source commit before rescue edits: `75e3fcde`
- Intended production URL: `https://famtasticdesigns.com`
- Intended host/lane: GoDaddy-hosted site for `famtasticdesigns.com`
- Verified domain DNS: `famtasticdesigns.com -> 107.180.51.234`
- Referenced cPanel/host lane from prior repo/session truth: `p3plzcpnl497512.prod.phx3.secureserver.net`
- Referenced account/user from prior repo/session truth: `xrdj7j99xhzt`
- Live deploy status: blocked before live

### Why deployment was blocked
1. Production lane could not be authenticated from this session. Direct SSH probe to `xrdj7j99xhzt@p3plzcpnl497512.prod.phx3.secureserver.net` returned `Permission denied (publickey,password)`.
2. No verified cPanel, SFTP, rsync, Git auto-deploy, or process-manager path was available in this session to replace the live site safely.
3. Hard rule held: no deploy without a verified production lane, no deploy with disappearing leads, and no deploy that risks exposing `/admin-proof` or incomplete backend surfaces.

### Local production-safe rescue changes prepared on this branch
- Disabled admin override application unless `ENABLE_ADMIN_PROOF=true`, so local `.data` proof overrides cannot leak into normal public rendering.
- Removed the static `public/robots.txt` override and routed robots through the guarded server route.
- `robots.txt` now disallows `/admin-proof` and `/payment-proof`.
- Public contact and portal copy were cleaned up to remove proof/mock leakage while staying honest about preview-only backend areas.
- Consultation forms were switched to manual email-draft fallback so the public rescue build does not pretend server-side lead capture is live.
- Payment and booking language were shifted to consultation/manual handling instead of implying live checkout.

### Local QA proof used before the live attempt was blocked
- `pnpm install`
- `pnpm typecheck`
- `pnpm lint`
- `pnpm build`
- Local preview via `node .output/server/index.mjs` with production-safe flags
- Browser verification of `/`, `/portal`, `/client-portal-login`, `/robots.txt`, `/admin-proof`

### Rollback / backup
- Live rollback was not needed because no production deploy occurred.
- Backup path: not created yet because authenticated production access was unavailable.
- Rollback command/method: pending once the real deploy lane is authenticated and inventoried.

### Next required step before live cutover
Provide verified production access for the actual FAMtastic Designs host lane (cPanel/SFTP/SSH/process manager or documented auto-deploy path), then re-run backup -> deploy -> live verification from this prepared branch.
