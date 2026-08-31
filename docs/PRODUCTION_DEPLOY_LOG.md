# Production Deploy Log

## 2026-08-31 — Thirst Trap 772 free social promo gift

- Repository/branch: `famtastic-fritz/famtastic-designs` / `main`
- Frontend release: `2d33a568dfdee78538a35754e12d229e42b22ee6`
- Frontend rollback archive:
  `/home/xrdj7j99xhzt/backups/famtastic-frontend-20260831T162238Z-2d33a568dfdee78538a35754e12d229e42b22ee6.tgz`
- Scope: published an unlisted, no-index, mobile-first promotional gift concept
  for Thirst Trap 772 with verified Instagram and Facebook exits, one
  owner-reference-led generated hero, two editable native-text social graphics,
  seven reusable page components, and a local event-message builder.
- Authority boundary: no email address or phone number was published because
  the owner-supplied reference and the requested Yahoo address conflict. No
  email, social post, order, payment, account, event, or customer record was
  created.
- Result: successful.

### Acceptance evidence

- Apex and `www` returned HTTP 200; the hero returned `image/webp`; both social
  graphics returned `image/svg+xml`; Omar Top Deals V2 remained HTTP 200.
- A real browser at 390×844 rendered with zero horizontal overflow, loaded the
  hero and both graphics, exposed the verified social destinations, and opened
  the event-message builder with a generated message.
- Browser monitoring recorded zero warnings or errors. The page advertises
  `noindex,noarchive,noimageindex` and states that the business must approve
  every real menu, price, event, contact, payment, and launch detail.
- Machine-readable receipt:
  `docs/evidence/thirst-trap-772-gift-promo/production-acceptance.json`.

## 2026-08-28 — Alex Signal Cut V2 alongside preserved V1

- Repository/branch: `famtastic-fritz/famtastic-designs` / `main`
- Release: `2cb023cedcee01d641c7f18d0ccd7c76ec032814`
- Frontend rollback archive:
  `/home/xrdj7j99xhzt/backups/famtastic-frontend-20260828T200102Z-2cb023cedcee01d641c7f18d0ccd7c76ec032814.tgz`
- Scope: published the separately addressable Alex Signal Cut V2 with stronger
  texture/type/shape composition, a source-labeled map, a same-device contact
  form, editable owner location fields, and V1/V2 lab links. V1 remained
  unchanged. No customer record, outbound message, appointment, payment,
  calendar event, or Drupal write occurred.
- Result: successful.

### Acceptance evidence

- Apex and `www` V2 public and owner routes returned HTTP 200 with the V2 title,
  five sections, map, contact form, five owner panels, and zero HTTP errors,
  console errors, or horizontal overflow at 390×844.
- A live-browser V2 contact message stored a masked reply value locally and
  appeared in the V2 owner request desk on the same browser profile.
- The original V1 route still rendered its original title, original quote, and
  no V2 signal rail. The Booked & Branded lab rendered distinct V1 and V2 links.

## 2026-08-28 — Alex independent-chair prototype and conversion walkthrough

- Repository/branch: `famtastic-fritz/famtastic-designs` / `main`
- Prototype implementation: `3d7ae9ca1496e96c209406466780469a38c760cf`
- Chair-side offer and conversion plan: `ef646b9bca07c8c3d37db0ab262969ecefc4f895`
- Route-icon acceptance fix: `7e389c8edd4211d8563be5f54327542c25340a98`
- Mobile visual and owner-flow polish: `2eafbdcde1e4f1c7c9cb2f80af5d60f64f9a171c`
- Frontend release record: 2026-08-28T19:38:40Z, Node v22.23.2
- Frontend rollback archive:
  `~/backups/famtastic-frontend-20260828T193840Z-2eafbdcde1e4f1c7c9cb2f80af5d60f64f9a171c.tgz`
- Scope: published the static five-section Alex sales prototype, same-device
  functional request/owner-console walkthrough, three generated concepts,
  three public-work references, route-local icon declarations, and the honest
  $199 founding-chair proposal. No customer record, real appointment, message,
  payment, calendar event, or provider account was created.
- Result: successful.

### Acceptance evidence

- Apex and `www` public and owner routes returned HTTP 200; the generated hero
  returned `image/webp`; the account-protected `/buy` destination resolved.
- Fresh headless Chrome at true 360×844, 390×844, and 430×932 phone emulation
  rendered all five public sections, the compact request dialog, all five owner
  tabs, the three-signal dashboard row, 48-pixel bottom-nav targets, and the
  corrected 44×24 service toggles with zero horizontal overflow.
- A live-browser prototype request stored only a masked final-four contact on
  that isolated browser profile and appeared in the owner console as pending.
- The owner console rendered the $199 proposal, renewal disclosure, and secure
  FAMtastic `/buy` destination. It did not send or persist that test request to
  Drupal or any external service.
- Browser monitoring recorded no page exceptions, failed network requests, or
  HTTP error responses after the route-local icon correction.

## 2026-08-27 — Drupal core and Entity API security maintenance

- Repository/branch: `famtastic-fritz/famtastic-designs` / `main`
- Backend release: `aad97433f88e6f0a2724c556d0bdc9b4f820710b`
- Backend release record: 2026-08-27T11:34:26Z, PHP 8.3.32
- Scope: Drupal core 11.4.4 → 11.4.5 and Entity API 1.6.0 → 1.8.0.
  Entity API is enabled alongside JSON:API; the latter update resolves
  SA-CONTRIB-2026-113 / CVE-2026-81158.
- Result: the locked production dependency install, cache rebuild, and normal
  update-status check completed. Production Composer audit reports zero
  advisories; no database updates remain pending.
- Pilot safety remained intact: `pilot_exact_dispatch_only=1`, no broad
  lifecycle/Drupal-cron/jobs-run scheduler was active, and no cohort, proof,
  provider, customer message, or commercial message was created by this release.

### Acceptance evidence

- Production reports Drupal 11.4.5 and locked Entity API 1.8.0.
- The public home page and filtered public JSON:API article collection returned
  HTTP 200 after deployment; an anonymous `client_project` collection returned
  a private 404 rather than a server error. This is not a substitute for a
  separate authenticated customer-dashboard journey check.

## 2026-08-27 — Exact-ID verified-cold pilot foundation

- Repository/branch: `famtastic-fritz/famtastic-designs` / `main`
- Backend and frontend release: `d5435a19e80344a8f0194705e3f867ee2674bac2`
- Backend release record: 2026-08-27T08:55:30Z, PHP 8.3.32
- Frontend release record: 2026-08-27T08:56:35Z, Node v22.23.2
- Scope: deployed the owner-gated public-preview/verified-cold foundation,
  its signed asset route, same-email registration isolation, exact-ID
  commercial-send gates, and the bounded account-route shell rewrites.
  Drupal updates 8041–8043 completed and an authoritative update-status check
  found no remaining database updates.
- Operational protection: set the durable exact-pilot lock to `1`, suspended
  only the marker-owned lifecycle cron (with a retained private backup), and
  quarantined the exact `cold-260-aug-2026` legacy work after promotion:
  242 claimable jobs became 0; active/unknown work and campaign-owned generic
  messages were 0 before and after. No notification-outbox row was
  heuristically altered.
- Result: successful technical release. No cohort was imported, no Gemini
  image was generated, no public room was staged, and no customer or
  commercial email was sent by this release.

### Acceptance evidence

- A real browser on apex and `www` loaded `/verify-email?token=<synthetic>`
  into the React account page and rendered its expected invalid/expired state,
  rather than Apache's prior 404. An iPhone Safari user-agent request returned
  the same app shell with HTTP 200.
- A syntactically valid synthetic signed preview URL reached the branded
  unavailable-room state, proving the new narrow SPA rewrite; its backing API
  returned 404 with no data. Raw legacy proof storage remained HTTP 403.
- Browser and HTTP acceptance are route proof only. A real owner-approved
  preview, Build DNA, anonymous recipient room, same-email claim, provider
  receipt, and explicit exact-ID commercial send remain separate evidence.

## 2026-08-26 — Signed anonymous proof-room routing

- Repository/branch: `famtastic-fritz/famtastic-designs` / `main`
- Frontend release: `c119338b043a3ab907773344bccedcf3081387de`
- Release record: 2026-08-26T18:32:22Z, Node v22.23.2
- Frontend rollback archive:
  `~/backups/famtastic-frontend-20260826T183200Z-c119338b043a3ab907773344bccedcf3081387de.tgz`
- Scope: frontend `.htaccess` routing plus deploy backup/verification only; no
  Drupal schema, proof record, share-token, customer email, payment, or other
  production data mutation.
- Result: successful.

### Acceptance evidence

- Before release, a valid anonymous signed proof API and its proof artifacts
  returned 200, while its human-facing `/proofs/share/...` room returned
  Apache's bare 404 on apex and `www`.
- After release, the server release marker and deployed root `.htaccess` match
  `c119338b`; valid shaped share URLs load the React proof-room route on both
  hostnames.
- The enabled signed proof payload exposes the expected six direction IDs;
  first and last proof pages resolve anonymously, while an invalid signature
  remains a 404 with no proof data.
- Browser acceptance loaded the branded unavailable-proof state for a synthetic
  invalid signature on both hostnames with `#root` populated and no console
  errors, proving the dynamic route reaches the application rather than Apache
  404ing before JavaScript starts.

## 2026-08-07 — Customer lifecycle portal and Commerce catalog

- Repository/branch: `famtastic-fritz/famtastic-designs` / `main`
- Customer platform implementation: `756bffc558681888a2cc7a96cde42f3fc3304f97`
- Runtime corrections: `c9aa182a`, `781733f1`, `45adc9e0`
- Drupal update `famtastic_pipeline_update_8012` created nine customer,
  organization, entitlement, collaboration, and activity tables.
- Commerce catalog created `FAM-FOOT-199` ($199), `FAM-HOST-999` ($9.99), and
  inactive/configurable `FAM-ANALYTICS`.
- Backend database rollback archive:
  `~/backups/famtastic-database-20260807T202048Z-781733f1ea40e1d560abb6fc5e49ffe4995213ac.sql.gz`
- Frontend rollback archive:
  `~/backups/famtastic-frontend-20260807T202122Z-45adc9e0973c52d06c5580a06f30be84db9cfddd.tgz`
- Final frontend and backend release markers must match the Git commit that
  records this evidence entry.
- Result: successful

### Acceptance evidence

- Apex and `www` display the branded email/password customer login; the legacy
  private-link wording is removed.
- Anonymous `/portal` access redirects to `/login`; anonymous session API
  returns 401 and invalid registration returns a structured 422.
- A verified production QA customer authenticated through Drupal session
  cookies and opened its organization-scoped React workspace.
- Home, project, file/approval, message, purchase, service, domain/hosting,
  support, team, account, growth, and analytics-entitlement surfaces rendered.
- CSRF-protected persistent project threads were created and read back.
- CSRF-protected profile update and sign-out completed successfully.
- Mobile acceptance at 390×844 showed no horizontal overflow; the apex and
  `www` login routes used the current release assets.
- All changed PHP passed PHP 8.3 syntax validation; Composer/platform checks,
  Drupal database updates, cache rebuild, npm audit, and Vite build passed.

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
