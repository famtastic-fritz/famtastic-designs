# Owner Dashboard — mobile-first campaign / click / lead command center

**Status**: PARKED — planned, not yet approved by the owner. Recovered from session transcript 2026-09-06 (`b2e02462-417e-463a-8a6e-675c62e2280f`) after the owner pivoted mid-approval to the UGC Character Flood task (`plans/ugc-character-flood/plan.md`). Resume when the owner returns to it — do not start executing without a fresh approval pass (ExitPlanMode on this plan was rejected once already, before the pivot, for unrelated reasons — re-review before acting).

**Title**: Owner Dashboard v1 (React `/admin` shell + Drupal `/api/owner/*` + Drupal admin nav-depth fix)
**Purpose**: The owner cannot see, from a phone, which campaigns are producing leads and revenue, whether the social queue is actually firing, or which leads need a reply. The React `/admin` page is a single client-projects table with zero drill-downs; the Drupal admin lists things but most rows dead-end. Attribution SQL exists but is trapped in server-rendered HTML.
**Goal**: One phone-first surface at `famtasticdesigns.com/admin` that answers, in order: what needs me now → how each campaign is doing (drops, publish state, leads, requests, paid revenue) → every lead we run (inbound + outbound cohorts) with status/notes editable from the phone → queue health that distinguishes *stalled* from *error* from *unreachable* → traffic (GA4) when configured. Every list row opens a detail; every detail links to the Drupal page where the action lives. Numbers come from real tables or render an explicit unknown state — never zero-by-default.

**Tasks**
- [ ] P0 — Preflight production truth (read-only)
- [ ] P1 — Backend: owner auth + `/api/owner/summary` + `OwnerMetricsService`
- [ ] P2 — Backend: campaigns endpoints + compound-key attribution fix
- [ ] P3 — Backend: leads, queue health (Postiz live + stalled), traffic (GA4 slot)
- [ ] P4 — Frontend shell: nested routes, sign-in gate, tab bar, `admin.css`, Today, validator, Playwright
- [ ] P5 — Frontend: Campaigns list → campaign → drop
- [ ] P6 — Frontend: Leads, Queue, Traffic
- [ ] P7 — Lead actions from the phone (status + notes) — backend PATCH + UI
- [ ] P8 — Production data path + deploy (backend → frontend → campaign-file sync)
- [ ] P9 — Drupal HTML admin: every row opens a detail; dashboard discoverability
- [ ] P10 (optional) — Stall/unreachable alert via outbox; HyperFrames "weekly numbers" film

**Started**: 2026-09-06
**Ended**: —
**Execution**: Two parallel lanes — backend {P1→P2→P3→P7-api} and frontend {P4→P5/P6→P7-ui}; P9 after P2; P8 after P3+P6+P7; P10 last. Each phase is self-contained: re-read §B before starting it. Intended executor: a cheaper model, one phase per fresh session.
**Research**: three read-only exploration passes (frontend, backend, skills/tooling) + live Postiz/Temporal read-back on 2026-09-06; all cited as `path:line`.
**Review**: per-phase verification checklists (below); design review lane = `critique-affordance` + `critique-information-density` on rendered frames. Never run `better-*` on the same artifact (providers.json: "pick one").
**Skills**: `better-interface` family and `ui-ux-pro-max` as reference only; `hyperframes-creative` (P10 only); `diagram-design` not needed.
**Branch / worktree**: `main`, no worktree (repo convention; deploys require HEAD == origin/main).
**Blocked By**: nothing for P0–P7. P8 `--apply` steps and any GA4/Postiz `settings.local.php` change are owner-gated.

**Proof**: defined per phase; production proof = owner walks Today → Campaigns → drop → Leads → lead → Queue on a real phone at apex + www, recorded in `.artifacts/owner-dashboard/prod-<date>.md`.

---

## A. Context — what is actually true today (verified 2026-09-06)

1. **`/admin` has no drill-downs at all.** `frontend/src/pages/AdminDashboardPage.jsx` (288 lines) is one CRUD table over `node--client_project`; the only navigation is `to="/login?redirect=%2Fadmin"` (`:97`). `frontend/src/App.jsx:111-118` mounts it inside `<Layout/>`; no route in the app uses a param-nested `<Outlet/>`.
2. **`/admin` is not reachable by URL in production.** `frontend/public/.htaccess` is an allowlist with no catch-all; `admin` is absent and `frontend/dist/admin/` does not exist → refresh/deep-link 404s. `/portal` survives only because `generate-seo-shells.mjs:433-441` writes a physical shell.
3. **There is no working owner sign-in path.** `frontend/src/pages/LoginPage.jsx:17` calls `customerLogin()` (cookie session, `POST /api/customer/login`, which rejects users without a `famtastic_customer` row — `CustomerPortalController.php:139-162`). `useUser().login` (OAuth password grant) is called nowhere (`grep -rn 'login(' frontend/src` → only the docblock at `UserContext.jsx:19`). Local DB has only `default_consumer`. So `/admin` → `ProtectedRoute` → `/login` → customer login → `/admin` → bounce.
4. **Every metric already exists as SQL, but only as server-rendered HTML.** `OperationsController.php` and `MarketingCommandController.php` contain zero `JsonResponse` calls. Attribution grains live at `MarketingCommandController.php:541-586` and `:706-750`.
5. **Attribution is keyed wrong.** `contentGrainRows()` matches leads on `utm_content` alone (`:714-717`, `:727`); every campaign scaffolds `drop-01…`, so campaigns cross-credit each other. The schema comment (`famtastic_pipeline.install:1509-1514`) and `AttributionService::recordSocialLead()` (`:95-118`) use the compound `(campaign_id, content_id)`.
6. **`utm_content ≠ content_id` and `utm_campaign ≠ slug ≠ campaign_id`.** Verified from `marketing/campaigns/*/posting-schedule.json`: `booked-and-losing` → `campaign_id booked_and_losing`, drops `bl-drop-01` with `utm.content drop-01`; `already-know-the-game` → `campaign_id already-know-the-game` (hyphenated!), drops `akg-drop-01`/`drop-01`; `cost-is-not-the-reason` carries two `utm.campaign` values; `ghost-town-ep1` drops have no `utm` at all. Attribution must resolve through each drop's own `utm` block, never string equality.
7. **Campaign JSON files do not exist in production.** `scripts/deploy-backend-godaddy.sh:1104-1108` rsyncs only the module and two themes; `CampaignFileLocator.php:30-33` looks at `dirname(Drupal::root(),2)/marketing/campaigns` (= `~/marketing/campaigns` on the host) → empty. Every file-backed admin tab is blank in prod.
8. **Postiz is on the Mac behind ngrok.** Prod `settings.local.php` holds `famtastic_postiz_base_url` (ngrok) + `famtastic_postiz_api_key` (`docs/SYSTEMS.md:40`); the public API (verified live) exposes `posts[].{id,group,integration{providerIdentifier,name},publishDate,releaseURL,state}` and `integrations[].{identifier,name,disabled}` — no `error`, no `refreshNeeded`, no `deletedAt`.
9. **Real incident this morning:** 4 Instagram posts sat in `QUEUE` 7–9 h past `publishDate`, no error, no open Temporal workflow (two closed at 22:29–22:31Z before firing, two never existed); the Facebook halves published. Postiz logged zero lines in 12 h. `.site-context/SITE-LEARNINGS.md:730-756` records the earlier "worker dead, QUEUE forever, every layer reported success" incident. **Update:** these four posts self-resolved and published later the same day (13:30–15:00 UTC) — no requeue was needed. The derived state **`stalled`** is still a first-class requirement; this incident is exactly what it must catch next time.
10. **GA4 reporting API is almost certainly unconfigured in prod.** `GoogleAnalyticsReportingService.php:33-37` needs `famtastic_google_analytics_property_id` + `_credentials_path`; those keys appear nowhere in `docs/` (`providers.json ga4.status = site_tracking_configured`). Owner said: "I thought this was set up already; if not, defer." → P0 checks; if unset, the Traffic tab ships with an honest `not_configured` state and an owner runbook.
11. **Leads we run** = `famtastic_prospect` (system of record; 12-value status vocabulary at `src/Entity/Prospect.php:150-152`) + outbound cohorts in `famtastic_lead_import` (`qualified|unqualified|invalid|duplicate|suppressed`, imported via `drush famtastic:leads-import`) + email lifecycle in `famtastic_email_message` (sent/delivered/clicked/bounced; opens not measurable — `docs/campaigns/2026-08-01-chandler-pilot.md:137`) + `famtastic_event` (`email.replied`, `proof.*`, `deployment.deployed`).
12. **Two revenue definitions exist**: `famtastic_commerce_fulfillment.status='fulfilled'` (Marketing Command KPIs, attribution) vs legacy `famtastic_order.payment_status='paid'` (`PipelineAnalyticsService::report()`, N+1 per prospect). The dashboard uses **Commerce fulfillment everywhere** and prints the definition.

## Owner decisions (2026-09-06)
- Fix **both** admins (React `/admin` becomes the phone-first dashboard; Drupal `/web/admin/famtastic` rows get real links).
- **Include lead status + notes** from the phone (P7).
- GA4: verify; **defer with a built slot** if it needs the owner.
- **Requeue the 4 stalled IG posts** after plan approval — moot: they self-resolved before this was actioned.

---

## B. Phase 0 block — "Allowed APIs / verified facts" (paste at the top of every phase session)

```
FRONTEND (React 19.2, react-router 8.3, Vite 8, framer-motion 12.42; NO Tailwind / CSS modules / chart lib / unit runner — Playwright only)
- Route table: frontend/src/App.jsx:54-122. /portal is OUTSIDE <Layout/> (:64). /admin is INSIDE (:111-118) — P4 moves it outside.
- OAuth ctx (leave alone): frontend/src/auth/UserContext.jsx:38-136. Cookie API helper to COPY: frontend/src/api/customer.js:1-22
  (WEB_PREFIX = import.meta.env.DEV ? '' : '/web'; credentials:'same-origin'; CSRF via GET `${WEB_PREFIX}/session/token`, header X-CSRF-Token).
- Portal kit to COPY: frontend/src/components/portal/PortalShared.jsx — Panel :42-50, Empty :52-54, money :33-37 (minor/100), date :39-40 (unix s), title :28-31.
- portal.css to COPY: tokens (line 1: .portal-app{--p-bg:#070907;--p-panel:#101310;--p-line:#252b25;--p-lime:#7cfc00}), containment :80-88, drawer :176 block,
  reduced motion :103/:116/:122/:137, snap scroller :422, 44px pill tabs :420. Existing loading/empty/error copy: AdminDashboardPage.jsx:225-242.
- Overflow assertion helper to COPY: frontend/e2e/customer-launch.spec.js:15-24. Mocked-route pattern: frontend/e2e/public-lead-to-portal.spec.js:23-30.
  Playwright project "mobile-chromium" (iPhone 13, 390×844) already exists: frontend/playwright.config.js:17 — reuse, do not add a config.
- Vite dev proxy: frontend/vite.config.js:22-30 (/api,/jsonapi,/oauth,/session → VITE_DRUPAL_PROXY_TARGET, default http://localhost:8080). Dev boot pattern: scripts/e2e-portal-links.sh:103-112.
- .htaccess allowlist: frontend/public/.htaccess (copy :45 pattern; :62-67 !-f/!-d guard). robots Disallow /admin already: frontend/scripts/generate-seo-shells.mjs:495.
- GA4 client is write-only (lib/googleAnalytics.js trackPageView/trackEvent). Never read GA4 from the browser.
- Build: npm --prefix frontend run build (vite build + generate-seo-shells). Deploy: ./scripts/deploy-frontend-godaddy.sh then --apply (clean tree, HEAD == origin/main, verify apex + www in a real browser).
- Design DNA (hard gate): docs/architecture/FAMTASTIC_CLIENT_PORTAL_DESIGN_DNA_V1.{md,json} — bg #070907, panel #101310→#141814, line #252b25, accent #7cfc00 (single accent),
  text #f6f8f6/#aeb6ac/#8e998e, radius 18px, transition 0.2s cubic-bezier(0.16,1,0.3,1), Inter/system-ui, eyebrow uppercase .14em; 44px min targets;
  MAX ONE glow per view = box-shadow: 0 0 24px rgba(124,252,0,.35); overflow-x:clip; reduced-motion; no invented numbers.
  design.md:9-22 (where am I / what is happening / what do I do now — ONE primary action / why on demand); :38-44 (list → item → destinations; secondary under More).
- Portal validator to COPY structure from: scripts/validate-client-portal-design-dna.mjs (assert/pass/fail, exit 1). It does NOT inspect admin files.

BACKEND (Drupal 11; module backend/web/modules/custom/famtastic_pipeline; permission 'administer famtastic pipeline')
- Routing: famtastic_pipeline.routing.yml — admin HTML :920-1158; customer JSON patterns to COPY: :317-324 (login POST, _access TRUE, no_cache), :347-355 (logout, _csrf_request_header_token), :358-365 (session GET).
- Auth provider ids: cookie (core) and oauth2 (backend/web/modules/contrib/simple_oauth/simple_oauth.services.yml:32). ALWAYS declare options._auth explicitly on owner routes.
- Controller DI + JSON helpers to COPY: src/Controller/CustomerPortalController.php:34-64 (constructor/create), :139-162 (login: user.auth->authenticate + flood 5/900s + user_login_finalize), :206-211 (session 401), :597-600 body(), :606-609 noStore() → Cache-Control: no-store, private.
- Services registry: famtastic_pipeline.services.yml (patterns :92-94 google_analytics_reporting, :192-194 lifecycle_operations, :207-209 postiz_channels).
- Metric SQL to MOVE verbatim into OwnerMetricsService: MarketingCommandController.php kpis :283-302; campaign/source grain :543-552; lifecycle clocks :626-693; isFixtureCampaign :695-697;
  contentGrainRows :706-750 (BUG at :714-717,:727). OperationsController.php campaignRevenue :240-248; renewalsDueCount :269-275; revenueLast30Days :278-284;
  prospectNeedsHumanReply :1114-1120; countProspectsNeedingHumanReply :1123-1130; notificationAttentionBanner :1491-1519; metric() dispatcher :522-546.
  DEAD CODE — never build on: OperationsController.php:251-266 social.* event counts (nothing writes them); "68 planned moments" hardcoded :145.
- 44px Drupal link pattern: MarketingCommandController.php:447-458 ('#type'=>'link', '#attributes'=>['class'=>['button','button--small']]). Theme enforces min-height 44px on .button ≤720px (famtastic_admin/css/famtastic-admin.css:1345-1348).
- Postiz: src/Service/PostizChannelsService.php (baseUrl :37-39 Settings famtastic_postiz_base_url default http://127.0.0.1:4007; key :58 Settings famtastic_postiz_api_key ?? env FAMTASTIC_POSTIZ_API_KEY; GET {base}/api/public/v1/integrations header Authorization:<key> timeout 5 :67-70).
  Posts endpoint (verified live 2026-09-06): GET {base}/api/public/v1/posts?startDate=<ISO Z>&endDate=<ISO Z> → {posts:[{id,content,creationMethod,group,integration{id,name,picture,providerIdentifier},intervalInDays,publishDate,releaseId,releaseURL,state,tags}]}; states QUEUE|PUBLISHED|ERROR|DRAFT; NO error text, NO deletedAt.
  Integrations payload: [{disabled,id,identifier,name,picture,profile}] — NO refreshNeeded (expired YouTube shows only as disabled=true).
- GA4: src/Service/GoogleAnalyticsReportingService.php (dashboardReport :28-84; guard :33-37 returns {available:false,message}; accessToken :86-110; runReport :112-119 POST analyticsdata.googleapis.com/v1beta/properties/{id}:runReport; cache TTL 900).
  Settings key NAMES: famtastic_google_analytics_property_id, famtastic_google_analytics_credentials_path. Dimension names must be confirmed against GET …/properties/{id}/metadata before use (expected: sessionCampaignName, sessionManualAdContent, sessionSourceMedium).
- Campaign files: src/Utility/CampaignFileLocator.php (candidates :30-35; readJson :59-66; listCampaignSlugs :74-97; findDrop :107-114; knownProviderIds :126-140). Prod Drupal root ~/public_html/web → first candidate ~/marketing/campaigns (NOT synced by deploy-backend-godaddy.sh:1104-1108).
- Schema (famtastic_pipeline.install): famtastic_campaign :901-919; famtastic_event :983-1006; famtastic_commerce_fulfillment :1412-1425; famtastic_notification_outbox :1426-1448; famtastic_revenue_freshness :1449+;
  famtastic_social_record :1505-1537 (unique (campaign_id,content_id); no clicks column); famtastic_email_message :1875-1921; famtastic_lead_import :1967-1996; update 8037 (utm_json + leads_count) :2074-2091.
- Prospect entity: src/Entity/Prospect.php (status vocabulary :150-152: new, viewed, confirmed, lead, paid, intake_started, intake_complete, submitted_to_studio, proof_ready, revision_requested, approved, launched;
  utm_json :174; next_followup_due, first_response_due, first_responded_at, lost_reason, nurture_eligible, owner_uid :86-185). Workspace route famtastic_pipeline.prospect_workspace = /admin/famtastic/prospect/{id}/workspace.
- Attribution: src/Service/AttributionService.php PARAMS :34-42 (utm_source, utm_medium, utm_campaign, utm_content, utm_term, gclid, fbclid, captured_via, captured_at); recordSocialLead compound key :95-118. Proven by scripts/validate-utm-attribution.sh (asserts update 8037).
- Tracked links: scripts/queue-campaign-drops.py:463-477 tracked_link() = landing_url + utm_source&utm_medium&utm_campaign={utm.campaign}&utm_content={utm.content}; DEFAULT_LANDING :71 (/onboarding → 301 /buy, .htaccess:96-102).
- Outbox writer to COPY: LifecycleOperationsService.php queue() :727-733 (merge on notification_key); runProtection :267-330; hook_cron famtastic_pipeline.module:215-262; drush revenueHealth JSON pattern PipelineCommands.php:878-895.
- Contract-test pattern: tests/src/Unit/RevenueLoopContractTest.php (file_get_contents + assertStringContainsString). Run: backend/vendor/bin/phpunit -c backend/web/core/phpunit.xml.dist backend/web/modules/custom/famtastic_pipeline/tests/src/Unit
- Admin link audit: scripts/e2e-admin-links.sh (drush runserver :8935, uli cookie jar :19-21, PATHS :34-78, verdict loop :80-98).
- Local truth 2026-09-06: 486 prospects (0 with utm_json), campaigns all journey-* fixtures, social_record 68 rows (web_basics_55_cents_17d), lead_import qualified 203/unqualified 23/invalid 22/suppressed 23/duplicate 1, uid 1 = admin@famtastic.local, consumers: default_consumer only.

DOCTRINE
- Never invent numbers. `unavailable` / `unreachable` / `not_instrumented` / `stale` are explicit states, never 0. Single accent; max ONE glow per view.
- Skills are reference only (AGENTS.md:88-114); repo doctrine wins; one review lane per artifact.
- `/web/` never appears in a public link; SPA→Drupal admin links are built server-side with Url::fromRoute()->setAbsolute().
- Commits: repo rule = no AI attribution trailers (AGENTS.md "Commit Attribution"). Deploys only from a clean tree at origin/main; `--apply` is owner-authorized.
- Doc sync after each phase (AGENTS.md:3-16): docs/CHANGELOG.md; docs/CAPABILITY_REGISTRY.md only with proof; .site-context/SITE-LEARNINGS.md; Drive mirror folder.
```

## C. Global conventions (every phase)

**JSON envelope — every `/api/owner/*` success:**
```json
{ "ok": true, "schema": "famtastic.owner-api.<name>.v1", "generated_at": 1788700000,
  "sources": ["famtastic_commerce_fulfillment", "postiz:/api/public/v1/posts"],
  "data": {},
  "gaps": [ { "key": "queue.postiz", "state": "unreachable", "reason": "Postiz unreachable: cURL error 28 (3s).", "action": { "label": "Open queue", "href": "/admin/queue" } } ] }
```
- `gaps[].state` ∈ `unavailable` (not configured) · `unreachable` (provider down/timeout) · `not_instrumented` (no capture exists) · `stale` (from cache/scorecard, carries `as_of`).
- Errors: `{ "ok": false, "error": "<code>", "message": "<one sentence naming the recovery>" }`; codes `authentication_required` 401, `owner_permission_required` 403, `invalid_credentials` 403, `validation_failed` 422, `not_found` 404.
- Units: money `*_minor` int + `currency` (`usd`); timestamps `*_at` unix seconds int; `*_seconds` int; rates floats 0–1 named `*_rate`. `null` only under a parent that carries a `state`.
- Every response: `Cache-Control: no-store, private` (copy `noStore()`), route `options.no_cache: 'TRUE'`.
- Drupal links in payloads are absolute (`Url::fromRoute(...)->setAbsolute()->toString()`), so prod gets `/web/` automatically; the SPA never prefixes `/web` for admin links and never puts `/web` in a public link.

**Frontend conventions:** all owner code under `frontend/src/pages/owner/`, `frontend/src/components/owner/`, `frontend/src/api/owner.js`, `frontend/src/admin.css` (scoped `.owner-app`). Only `--p-*` tokens. Changing numbers get `.owner-num` (`tabular-nums`). Every list row is a `<Link>`; every detail has a 44px "Do it in Drupal" link. State = text + glyph, never colour alone. Motion opt-in under `@media (prefers-reduced-motion: no-preference)`. Filters live in the URL (`useSearchParams`), never only in component state.

**Verification gate (before closing any phase):**
```
node scripts/validate-client-portal-design-dna.mjs            # untouched portal must stay green
node scripts/validate-owner-dashboard-dna.mjs                 # from P4 on
npm --prefix frontend run build
backend/vendor/bin/phpunit -c backend/web/core/phpunit.xml.dist backend/web/modules/custom/famtastic_pipeline/tests/src/Unit
find backend/web/modules/custom/famtastic_pipeline -name '*.php' -print0 | xargs -0 -n1 php -l
./scripts/e2e-admin-links.sh                                  # from P1 on (extended with JSON paths)
./scripts/e2e-owner-dashboard.sh                              # from P4 on
```

---

## P0 — Preflight: production truth (read-only, ~2h)

**Goal:** know before code whether prod can serve the dashboard. Output `.artifacts/owner-dashboard/phase0/report.md` (gitignored dir). SSH conventions from `scripts/deploy-frontend-godaddy.sh:6-8`; prod Drupal root `~/public_html/web`; drush `~/public_html/vendor/bin/drush`. Prod drush may exit 255 even on success (`docs/SYSTEMS.md:41`) — parse output, never exit codes. Never print secret values.

```
# schema: update 8037 present?
ssh "$SSH_TARGET" 'cd ~/public_html && vendor/bin/drush -r ~/public_html/web php:eval "\$s=\Drupal::database()->schema(); var_export([\"utm_json\"=>\$s->fieldExists(\"famtastic_prospect\",\"utm_json\"), \"leads_count\"=>\$s->fieldExists(\"famtastic_social_record\",\"leads_count\"), \"schema\"=>\Drupal::keyValue(\"system.schema\")->get(\"famtastic_pipeline\")]);"'
# owner account + permission (which uid/email will sign in?)
ssh "$SSH_TARGET" 'cd ~/public_html && vendor/bin/drush -r ~/public_html/web php:eval "\$u=\Drupal\user\Entity\User::load(1); var_export([\$u->id(), \$u->getEmail(), \$u->hasPermission(\"administer famtastic pipeline\")]);"'
# settings present? (booleans only)
ssh "$SSH_TARGET" 'cd ~/public_html && vendor/bin/drush -r ~/public_html/web php:eval "use Drupal\Core\Site\Settings; var_export([\"postiz_base\"=>(bool)Settings::get(\"famtastic_postiz_base_url\"), \"postiz_key\"=>(bool)Settings::get(\"famtastic_postiz_api_key\"), \"ga_prop\"=>(bool)Settings::get(\"famtastic_google_analytics_property_id\"), \"ga_cred_readable\"=>is_readable((string)Settings::get(\"famtastic_google_analytics_credentials_path\",\"\"))]);"'
# campaign files + htaccess + routes
ssh "$SSH_TARGET" 'cd ~/public_html && vendor/bin/drush -r ~/public_html/web php:eval "var_export(\Drupal\famtastic_pipeline\Utility\CampaignFileLocator::listCampaignSlugs());"'   # expect [] today
ssh "$SSH_TARGET" 'ls ~/marketing/campaigns 2>/dev/null || echo ABSENT; grep -n "admin" ~/public_html/.htaccess | head'
curl -s -o /dev/null -w '%{http_code}\n' https://famtasticdesigns.com/admin                 # expect 404 today
curl -s -o /dev/null -w '%{http_code}\n' https://famtasticdesigns.com/web/api/owner/session # expect 404 today
curl -s https://famtasticdesigns.com/robots.txt | grep -n 'Disallow: /admin'
```
Local: dump the campaign mapping table (slug → campaign_id → utm.campaign values → content_id/utm.content pairs) with the python one-liner from §A.6 into the report. P2 derives the same in PHP; no hand-maintained table.

**Deliverable / go-no-go:** if `utm_json` is FALSE, P8's backend deploy (`drush updatedb`) must precede any owner use of attribution screens (code still ships with the `unavailable` gap). If uid 1 lacks the permission, a role grant is a prod change → owner authorization. If GA settings are absent → Traffic ships `not_configured` + runbook (owner decision already made).

**Anti-patterns:** running `updatedb` in P0; printing secrets; trusting exit codes.

---

## P1 — Backend foundation: owner auth + summary + `OwnerMetricsService` (~6h)

**Create**
- `src/Service/OwnerMetricsService.php`
- `src/Controller/OwnerApiController.php`
- `tests/src/Unit/OwnerApiContractTest.php`

**Modify**
- `famtastic_pipeline.routing.yml` (append after `:1158`), `famtastic_pipeline.services.yml` (append)
- `MarketingCommandController.php`: `kpis()` body (`:283-302`) → `return $this->ownerMetrics->kpis();`; inject via constructor `:52-58` / `create()` `:60-68`.
- `OperationsController.php`: `revenueLast30Days()` (`:278-284`), `renewalsDueCount()` (`:269-275`) delegate to the service; inject (`:30-49`).
- `scripts/e2e-admin-links.sh`: JSON path loop (below).

**`OwnerMetricsService` (P1 surface; later phases add methods)**
```php
final class OwnerMetricsService {
  public function __construct(Connection $database, TimeInterface $time, EntityTypeManagerInterface $entityTypeManager,
    PostizChannelsService $postizChannels, CacheBackendInterface $cache, LoggerInterface $logger) {}
  public function kpis(): array;               // COPY MarketingCommandController::kpis() :284-301 verbatim
  public function revenueLast30Days(): array;  // COPY OperationsController :279-283 → ['amount_minor','orders','currency'=>'usd','definition'=>'famtastic_commerce_fulfillment.status = fulfilled AND fulfilled_at >= now-30d']
  public function renewalsDueCount(): int;     // COPY :270-274
  public function outboxHealth(): array;       // COPY notificationAttentionBanner math :1492-1501 → ['queued','retry','dead_letter','failed','oldest_queued_minutes']
  public function workers(): array;            // SELECT worker_key,status,last_started,last_finished,next_due,processed,failed FROM famtastic_worker_heartbeat; late = next_due>0 && next_due<now && last_finished<now-1800
  public function leadsPulse(): array;         // ['total','new_7d' (created>=now-604800),'needs_reply' (COPY countProspectsNeedingHumanReply :1124-1129)]
  public static function isFixtureCampaign(string $key): bool; // MOVE :695-697
  public function summary(): array;            // composes the above; queue => ['state'=>'not_implemented'] + gap until P3
  public function spaUrl(string $path): string;// rtrim(Settings::get('famtastic_spa_base_url', request scheme+host),'/') . $path  — used by P9
}
```
services.yml: `famtastic_pipeline.owner_metrics: class: Drupal\famtastic_pipeline\Service\OwnerMetricsService; arguments: ['@database','@datetime.time','@entity_type.manager','@famtastic_pipeline.postiz_channels','@cache.default','@logger.channel.famtastic_pipeline']`.

**`OwnerApiController`** — constructor/`create()` copied from `CustomerPortalController.php:34-64` with `user.auth`, `flood`, `current_user`, `entity_type.manager`, `datetime.time`, `famtastic_pipeline.owner_metrics`, `logger.channel.famtastic_pipeline`. Copy `body()`, `error()`, `noStore()`; add `envelope(string $schema, array $data, array $sources, array $gaps = []): JsonResponse`.
- `session()`: anonymous → 401 `authentication_required`; authenticated without permission → 403 `owner_permission_required`; else `{ok, schema:'famtastic.owner-api.session.v1', generated_at, user:{uid,email,name}, owner:true}`.
- `login(Request)`: COPY `:139-149` (flood id `'owner-login:'.hash('sha256',$email.'|'.$ip)`, event `famtastic_owner_login`, 5/900s). After `authenticate()`: if the user lacks the permission → 403 **without** `user_login_finalize`. Else finalize, clear flood, return the session payload.
- `logout()`: COPY `:203-206`.
- `summary()`: `data = summary()`; `sources = ['famtastic_commerce_fulfillment','famtastic_prospect','famtastic_notification_outbox','famtastic_worker_heartbeat','famtastic_social_record']`.

**Summary contract (`famtastic.owner-api.summary.v1`)**
```json
"data": {
  "revenue_30d": { "amount_minor": 19900, "orders": 1, "currency": "usd", "definition": "…" },
  "leads": { "total": 486, "new_7d": 3, "needs_reply": 1 },
  "gates_open": 5,
  "outbox": { "queued": 0, "retry": 40, "dead_letter": 1, "failed": 0, "oldest_queued_minutes": 0 },
  "workers": [ { "worker_key": "lifecycle_protection", "status": "healthy", "last_finished": 1788682996, "next_due": 1788683896, "late": false } ],
  "queue": { "state": "ok", "stalled": 0, "error": 0, "queued": 4, "checked_at": 1788700000, "cache_age_seconds": 40 },
  "next_action": { "kind": "dead_letters", "title": "1 notification is dead-lettered", "detail": "Retry or inspect it before it ages further.", "href": "/admin/queue" }
}
```
`queue.state` ∈ `ok|attention|unreachable|not_configured` (P1: `not_implemented` + gap). `next_action.kind` — first match wins: `stalled_posts` → `postiz_unreachable` → `dead_letters` → `needs_reply` → `worker_late` → `gates_open` (href `/admin/campaigns`) → `none` (`title: "Nothing needs you right now"`, `href: null`).

**Routes (append exactly; same shape for every later owner route):**
```yaml
famtastic_pipeline.owner_session:
  path: '/api/owner/session'
  defaults: { _controller: 'Drupal\famtastic_pipeline\Controller\OwnerApiController::session' }
  methods: [GET]
  requirements: { _access: 'TRUE' }
  options: { _auth: ['cookie', 'oauth2'], no_cache: 'TRUE' }
famtastic_pipeline.owner_login:
  path: '/api/owner/login'
  defaults: { _controller: 'Drupal\famtastic_pipeline\Controller\OwnerApiController::login' }
  methods: [POST]
  requirements: { _access: 'TRUE' }
  options: { no_cache: 'TRUE' }
famtastic_pipeline.owner_logout:
  path: '/api/owner/logout'
  defaults: { _controller: 'Drupal\famtastic_pipeline\Controller\OwnerApiController::logout' }
  methods: [POST]
  requirements: { _access: 'TRUE', _csrf_request_header_token: 'TRUE' }
  options: { _auth: ['cookie', 'oauth2'], no_cache: 'TRUE' }
famtastic_pipeline.owner_summary:
  path: '/api/owner/summary'
  defaults: { _controller: 'Drupal\famtastic_pipeline\Controller\OwnerApiController::summary' }
  methods: [GET]
  requirements: { _permission: 'administer famtastic pipeline' }
  options: { _auth: ['cookie', 'oauth2'], no_cache: 'TRUE' }
```

**Contract test** (`OwnerApiContractTest.php`, pattern `RevenueLoopContractTest.php`): routing.yml contains each `/api/owner/` path; every owner route except login carries `_auth: ['cookie', 'oauth2']`; every non-session/login/logout route carries `_permission: 'administer famtastic pipeline'`; `MarketingCommandController.php` contains `return $this->ownerMetrics->kpis();`; `OwnerApiController.php` contains `owner_permission_required` and `user_login_finalize`.

**Verification**
```
backend/vendor/bin/drush -r backend/web cr -y
backend/vendor/bin/drush -r backend/web runserver 127.0.0.1:8935 >/dev/null 2>&1 & sleep 3
curl -s -w '\n%{http_code}\n' http://127.0.0.1:8935/api/owner/session                 # JSON authentication_required, 401
curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8935/api/owner/summary       # 403
ULI=$(backend/vendor/bin/drush -r backend/web uli --uid=1 --no-browser | grep -m1 . | tr -d '[:space:]'); curl -s -L -c /tmp/owner.jar -o /dev/null "http://127.0.0.1:8935${ULI#*default}"
curl -s -b /tmp/owner.jar http://127.0.0.1:8935/api/owner/session | jq '.ok,.owner,.user.uid'   # true true 1
curl -s -b /tmp/owner.jar http://127.0.0.1:8935/api/owner/summary | jq '.schema,.generated_at,.sources,.data.revenue_30d,.data.next_action,.gaps'
curl -s -I -b /tmp/owner.jar http://127.0.0.1:8935/api/owner/summary | grep -i cache-control      # no-store, private
backend/vendor/bin/drush -r backend/web user:password admin 'local-owner-pass-2026-rotate'
curl -s -c /tmp/o2.jar -H 'Content-Type: application/json' -d '{"email":"admin@famtastic.local","password":"local-owner-pass-2026-rotate"}' http://127.0.0.1:8935/api/owner/login | jq '.ok,.owner'   # true true
curl -s -H 'Content-Type: application/json' -d '{"email":"admin@famtastic.local","password":"wrong"}' -w '\n%{http_code}\n' http://127.0.0.1:8935/api/owner/login   # invalid_credentials 403
backend/vendor/bin/drush -r backend/web user:create owner-neg@qa.famtasticdesigns.com --mail=owner-neg@qa.famtasticdesigns.com --password='local-neg-pass-2026-rotate'
curl -s -c /tmp/neg.jar -H 'Content-Type: application/json' -d '{"email":"owner-neg@qa.famtasticdesigns.com","password":"local-neg-pass-2026-rotate"}' http://127.0.0.1:8935/api/owner/login | jq '.error'   # owner_permission_required
curl -s -b /tmp/neg.jar -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8935/api/owner/summary    # 403 (no session finalized)
# HTML parity after the kpis() move: render /admin/famtastic/marketing before and after; only timestamps may differ
./scripts/e2e-admin-links.sh && backend/vendor/bin/phpunit -c backend/web/core/phpunit.xml.dist backend/web/modules/custom/famtastic_pipeline/tests/src/Unit
```
Extend `scripts/e2e-admin-links.sh` after the HTML loop:
```bash
declare -a JSON_PATHS=("/api/owner/session" "/api/owner/summary")
for p in "${JSON_PATHS[@]}"; do
  BODY=$(curl -s -b "$ART/cookies.txt" --max-time 30 "http://127.0.0.1:$PORT$p")
  if printf '%s' "$BODY" | jq -e '.ok == true and (.generated_at|type=="number") and (.sources|type=="array")' >/dev/null; then VERDICT="ok [json]"; else VERDICT="DEAD (json)"; ((FAIL+=1)); fi
  printf '%-62s %-6s %-8s %s\n' "$p" "-" "${#BODY}" "$VERDICT"
done
```

**Anti-pattern guards:** finalizing a login for a non-owner; HTML 403 on `/session` (must be JSON 401); missing `no_cache`/`no-store` (page cache would leak one owner's JSON to the next visitor); SQL in the controller; changing HTML admin numbers while moving SQL (diff proves parity); logging credentials.
**Parallel:** with P4 (fixtures).

---

## P2 — Backend: campaigns endpoints + compound-key attribution fix (~8h)

**Create** `src/Service/CampaignIndexService.php` (pure derivation, unit-testable):
```php
final class CampaignIndexService {
  public function __construct(Connection $database, CacheBackendInterface $cache, TimeInterface $time) {}
  public function index(): array;      // keyed by canonical campaign key; cached 300s 'famtastic_pipeline:owner:campaign_index', busted when max(filemtime) changes
  public function utmMap(): array;     // keyed "utm_campaign|utm_content" → {key, slug, content_id}
  public static function buildFromSchedules(array $schedulesBySlug, ?array $manifest55): array; // pure → ['index'=>…,'map'=>…]
  public static function utmKey(string $campaign, string $content): string { return trim($campaign).'|'.trim($content); }
  public function resolveDrop(string $utmCampaign, string $utmContent): ?array;
}
```
`buildFromSchedules` algorithm (encode exactly):
1. For each slug from `CampaignFileLocator::listCampaignSlugs()`: `schedule = readJson(slug,'posting-schedule.json')`; `key = schedule['campaign_id']`; `index[key] = {key, slug, name: campaign_name, status, lane:'social', landing_url, files:true, aliases:[], drops}`. For each drop with `utm.campaign` and `utm.content`: `map[utmKey(utm.campaign, utm.content)] = {key, slug, content_id: drop.content_id ?? drop.drop_id}`; if `utm.campaign !== key` push to `aliases`.
2. Manifest: `readJson('55-cents-17-day','manifest.json')` → `key = manifest.campaign ?? records[0].campaign` (expect `web_basics_55_cents_17d`); normalize records to drops (content_id, scheduled_at from day + suggested_time_et, theme, channels, utm); map entries from `record.utm`.
3. DB: `SELECT DISTINCT campaign_id FROM famtastic_social_record` → ensure entry (`files:false`, lane social). `SELECT campaign_key,name,status,channel FROM famtastic_campaign` → lane `email` (or `fixture` via `isFixtureCampaign`). `SELECT DISTINCT campaign FROM famtastic_prospect WHERE campaign<>''` → lane `lead_source`.

**`OwnerMetricsService` additions**
- `utmSnapshots()` — `SELECT id, utm_json FROM famtastic_prospect WHERE utm_json IS NOT NULL`; if `schema()->fieldExists('famtastic_prospect','utm_json')` is FALSE return `['state'=>'unavailable']` → gap `{key:'attribution', state:'unavailable', reason:'Prospect attribution snapshots (update 8037) are not installed on this site.'}`.
- `contentGrain()` — REPLACES `contentGrainRows()`: `$leadsByKey[utmKey(utm_campaign, utm_content)][] = id`; additionally resolve via `resolveDrop()` and credit `utmKey(resolved.key, resolved.content_id)`. Rows for every `famtastic_social_record` (`campaign_id|content_id`) AND every file drop; zero-lead rows kept; requests/revenue per row = COPY `:731-739`. Returns `{campaign_key, slug, content_id, day, leads, requests, revenue_minor, lead_ids}`.
- `campaignSourceGrain(int $limit=25)` — COPY `:543-552`, assoc rows. `campaignLifecycleClocks()` — MOVE `:626-693`.
- `campaignList()`, `campaignDetail(string $key)`, `dropDetail(string $key, string $contentId)` — provider records from `scorecard.json` in P2 (`provider.state='scorecard'`, `as_of=scorecard.generated_at`) or `none`; P3 adds `live`.

**Refactor `MarketingCommandController::tabAttribution()` (`:541-586`)**: rows from the service; content-grain table gains a leading **Campaign** column (the only intended HTML change — note in CHANGELOG); description at `:573` → "matched on utm_campaign + utm_content (compound key)".

**Contracts**

`GET /api/owner/campaigns` → `famtastic.owner-api.campaigns.v1`
```json
"data": { "campaigns": [ {
  "key": "booked_and_losing", "slug": "booked-and-losing", "name": "Booked and losing", "status": "armed_for_scheduling", "lane": "social", "files": true, "aliases": [],
  "drops": { "total": 6, "published": 0, "queued": 0, "stalled": null, "error": 0, "not_found": 0, "provider_state": "scorecard", "as_of": 1788600000 },
  "attribution": { "basis": "utm_campaign", "leads": 0, "requests": 0, "revenue_minor": 0, "currency": "usd", "state": "ok" },
  "email": null, "next_drop_at": 1788784200, "last_activity_at": 1788700000,
  "links": { "drupal_campaign": null, "scorecard": "https://…/web/admin/famtastic/marketing/scorecard/booked-and-losing", "drops_tab": "https://…/web/admin/famtastic/marketing/drops?campaign=booked-and-losing" }
} ],
"definitions": { "attribution.leads": "prospects whose utm_json.utm_campaign equals the campaign key or an alias", "revenue_minor": "famtastic_commerce_fulfillment.status = fulfilled joined on prospect_id", "drops.stalled": "state QUEUE and publish time more than 900s ago (live provider read only)" },
"hidden_fixtures": 12 }
```
Lane `email`: `attribution.basis="prospect.campaign"`, `email:{staged,queued,sent,replied:null,paid_orders,paid_minor}` from lifecycle clocks, `drops:null`. `stalled` is `null` unless `provider_state==='live'`. Sort: `stalled>0` → `error>0` → `next_drop_at` asc (nulls last). Fixtures hidden unless `?fixtures=1`.

`GET /api/owner/campaigns/{key}` → `famtastic.owner-api.campaign.v1`: list object + `drops_list[]`:
```json
{ "content_id": "bl-drop-01", "drop_id": "drop-01", "scheduled_at": 1788784200, "theme": "…", "headline": "…", "state": "media_ready",
  "channels_requested": ["facebook","instagram"], "approval": { "content": true, "media": true, "publish": false },
  "utm": { "source": "social", "medium": "social", "campaign": "booked_and_losing", "content": "drop-01" },
  "tracked_link": "https://famtasticdesigns.com/…?sku=FAM-FOOT-199&utm_source=social&utm_medium=social&utm_campaign=booked_and_losing&utm_content=drop-01",
  "provider": { "state": "scorecard", "as_of": 1788600000, "records": [ { "postiz_post_id": "cmt…", "integration": "facebook", "integration_name": "FAMtastic Designs", "state": "PUBLISHED", "derived": "published", "publish_at": 1788784200, "overdue_seconds": null, "release_url": null } ] },
  "attribution": { "leads": 0, "requests": 0, "revenue_minor": 0, "state": "ok" } }
```
`tracked_link` = COPY of `queue-campaign-drops.py:463-477` semantics; drops without `utm` → gap `not_instrumented`. Plus `by_drop_leads[]`.

`GET /api/owner/campaigns/{key}/drops/{content_id}` → `famtastic.owner-api.drop.v1`: one drop + `leads[]` (`{id, business_name, status, created, request|null, paid_minor}`) + `links {edit, delete, scorecard}` (absolute; only when `slug` set).

**Routes:** `owner_campaigns` `/api/owner/campaigns`; `owner_campaign` `/api/owner/campaigns/{key}` (`key: '[a-zA-Z0-9._-]+'`); `owner_drop` `/api/owner/campaigns/{key}/drops/{content_id}` (`content_id: '[a-z0-9-]+'`). Same `_permission`/`_auth`/`no_cache` block as P1. Unknown → 404 `not_found`.

**Tests:** `tests/src/Unit/CampaignIndexServiceTest.php` — two fake schedules (`booked_and_losing` with `bl-drop-01`/utm `drop-01`; `see_it_first` with `drop-01`/utm `drop-01`) → `map['booked_and_losing|drop-01'].content_id === 'bl-drop-01'`, `map['see_it_first|drop-01'].content_id === 'drop-01'`, no collision, aliases recorded. Contract test: `MarketingCommandController.php` must NOT contain `$leadsByContent[$contentId][]`; `OwnerMetricsService.php` contains `utmKey(`.

**Verification**
```
curl -s -b /tmp/owner.jar 'http://127.0.0.1:8935/api/owner/campaigns' | jq '.data.campaigns[] | {key,slug,lane,drops:.drops.total,leads:.attribution.leads}'
curl -s -b /tmp/owner.jar 'http://127.0.0.1:8935/api/owner/campaigns/booked_and_losing' | jq '.data.drops_list[0] | {content_id,utm,tracked_link,provider:.provider.state}'
curl -s -b /tmp/owner.jar -o /dev/null -w '%{http_code}\n' 'http://127.0.0.1:8935/api/owner/campaigns/does-not-exist'   # 404
# compound-key proof (synthetic, cleaned up)
backend/vendor/bin/drush -r backend/web php:eval '$s=\Drupal::entityTypeManager()->getStorage("famtastic_prospect"); foreach ([["booked_and_losing","OWNERTEST BL"],["see_it_first","OWNERTEST SIF"]] as [$c,$n]) { $p=$s->create(["business_name"=>$n,"campaign"=>"owner_test","status"=>"lead","utm_json"=>json_encode(["utm_campaign"=>$c,"utm_content"=>"drop-01"])]); $p->save(); }'
curl -s -b /tmp/owner.jar 'http://127.0.0.1:8935/api/owner/campaigns/booked_and_losing/drops/bl-drop-01' | jq '.data.attribution.leads'   # 1, not 2
curl -s -b /tmp/owner.jar 'http://127.0.0.1:8935/api/owner/campaigns/see_it_first/drops/drop-01' | jq '.data.attribution.leads'          # 1
backend/vendor/bin/drush -r backend/web php:eval '$s=\Drupal::entityTypeManager()->getStorage("famtastic_prospect"); foreach ($s->loadByProperties(["campaign"=>"owner_test"]) as $p) $p->delete();'
./scripts/e2e-admin-links.sh   # add the three JSON paths to JSON_PATHS
./scripts/validate-utm-attribution.sh   # still passes (recordSocialLead untouched)
```
**Anti-patterns:** matching `utm_content == content_id`; keying by `content_id` alone; treating `campaign_id` and slug as equal; calling `PipelineAnalyticsService::report()` from HTTP (N+1); reading `scorecard.json` as live; dropping zero-lead rows; rendering no-utm drops as zero leads instead of `not_instrumented`.

---

## P3 — Backend: leads, queue health (Postiz live + stalled), traffic slot (~8h)

**Create** `src/Service/PostizPostsService.php` (structure copied from `PostizChannelsService.php:21-99`):
```php
final class PostizPostsService {
  private const TIMEOUT_SECONDS = 5; private const CACHE_TTL = 120; private const NEGATIVE_TTL = 60; public const STALL_GRACE_SECONDS = 900;
  public function configured(): bool; public function baseUrl(): string;               // COPY :58 / :37-39
  public function posts(int $start, int $end, int $timeout = self::TIMEOUT_SECONDS): array;
  // GET {base}/api/public/v1/posts?startDate=<gmdate('Y-m-d\TH:i:s.000\Z',$start)>&endDate=<…$end>; headers Authorization:<key>, 'ngrok-skip-browser-warning'=>'1'
  // cache 'famtastic_pipeline:postiz:posts:{start}:{end}'; failure caches {reachable:false} for NEGATIVE_TTL; non-JSON body → unreachable with first 160 chars
  public static function deriveState(array $post, int $now, int $grace = self::STALL_GRACE_SECONDS): string;
  // PUBLISHED→published; ERROR→error; DRAFT→draft; QUEUE→(publish_at < now-grace ? 'stalled' : 'queued'); else 'unknown'
  public static function normalize(array $post, int $now): array;
  // {id, group, state, derived, publish_at, overdue_seconds (stalled only), integration:{id,identifier:providerIdentifier,name}, release_url, content_preview (80 chars, tags stripped)}
  public function defaultWindow(): array;  // start = floor((now-172800)/3600)*3600; end = start + 9*86400 (hour-aligned → stable cache key)
}
```
services.yml: `famtastic_pipeline.postiz_posts` (`@http_client`, `@datetime.time`, `@cache.default`); append `@famtastic_pipeline.postiz_posts` and `@famtastic_pipeline.google_analytics_reporting` to `owner_metrics`.

**GA4:** add `GoogleAnalyticsReportingService::ownerReport()` reusing `accessToken()`/`runReport()`: guard as `:33-37` → `{available:false, state:'not_configured', message}`; when configured: totals (5 metrics), `pages`, `channels`, `campaigns` (dim `sessionCampaignName`, metrics `sessions,keyEvents`, limit 25), `content` (dim `sessionManualAdContent`) **only if** the metadata probe (`GET …/properties/{id}/metadata`, cached 86400s) lists it, else `content:null` + gap `not_instrumented`. Cache `famtastic_pipeline:google_analytics:owner` 900s. Exception → `{available:false, state:'unreachable'}`.

**`OwnerMetricsService` additions**
- `queueHealth(int $timeout = 5)` — channels (`PostizChannelsService::channels()`), `posts(defaultWindow())`, map post ids → `{campaign_key, content_id}` via `CampaignIndexService` (`knownProviderIds()` + scorecard `records[].postiz_post_id`), counts by derived state, `outboxHealth()`, `workers()`, static `runbook[]` (from SITE-LEARNINGS 2026-09-03: pm2 list → OOMKilled → colima ≥8GiB → newest orchestrator log age → oldest overdue QUEUE post; add: "a QUEUE row with no open Temporal workflow never fires — reschedule through the API, never SQL"). `state`: `not_configured` (no key) → `unreachable` → `attention` (stalled>0 || error>0 || dead_letter>0) → `ok`.
- `leadsList(array $filters)` — filters `status`, `campaign`, `needs_reply=1`, `q` (LIKE business_name/public_email), `page` (50/page); base query on `famtastic_prospect` (sort/pager style `:998-1001`), LEFT JOIN latest `famtastic_project_request` (`:1044-1050`), fulfilled sum per prospect, `needs_reply` = COPY `:1114-1120`, `utm` decoded, `status_label = title(status)`.
- `leadCohorts()` — `SELECT campaign_key, source_name, status, COUNT(*), MIN(imported_at), MAX(imported_at) FROM famtastic_lead_import GROUP BY 1,2,3`.
- `emailFunnel()` — per `famtastic_campaign`: `famtastic_email_message` counts (staged, sent_at>0, delivered_at>0, clicked_at>0, bounced_at>0, unsubscribed_at>0) + `email.replied` events (`:671-674`); `opened: null` + definition "not measurable (no tracking pixel)".
- `leadDetail(int $id)` — prospect fields (business_name, business_category, contact_name, public_email, public_phone, website_url, service_area, business_description, campaign, source, status, created, changed, first_response_due, first_responded_at, next_followup_due, lost_reason, nurture_eligible, owner_uid), request row, fulfillments, email rows (`id, subject, status, sent_at, delivered_at, clicked_at`), last 50 events (`event_type, provider, occurred_at, payload_keys`; `owner.note` events include `payload.text`), links `workspace`, `edit`, `proof_review` (absolute).
- `summary()` now fills `queue` from `queueHealth(3)`.

**Contracts**

`GET /api/owner/leads?status=&campaign=&needs_reply=&q=&page=` → `famtastic.owner-api.leads.v1`
```json
"data": { "total": 486, "page": 1, "per_page": 50, "has_more": true,
  "filters": { "statuses": ["new","viewed","confirmed","lead","paid","intake_started","intake_complete","submitted_to_studio","proof_ready","revision_requested","approved","launched"], "campaigns": ["public_quote","customer_portal","chandler-landing-pilot-2026-08-01-b1"] },
  "leads": [ { "id": 481, "business_name": "…", "status": "lead", "status_label": "Lead", "campaign": "public_quote", "source": "solution_finder", "created": 1788600000,
               "needs_reply": true, "first_response_due": 1788610000, "next_followup_due": null, "utm": { "utm_campaign": "booked_and_losing", "utm_content": "drop-01" } | null,
               "request": { "public_id": "…", "status": "…", "proof_review_status": "…" } | null, "paid_minor": 0 } ],
  "cohorts": [ { "campaign_key": "chandler-landing-pilot-2026-08-01-b1", "source_name": "city-of-chandler", "qualified": 10, "unqualified": 0, "invalid": 0, "suppressed": 0, "duplicate": 0, "imported_from": 1785400000, "imported_to": 1785400000 } ],
  "email": [ { "campaign_key": "…", "staged": 0, "sent": 10, "delivered": 10, "opened": null, "clicked": 1, "replied": 0, "bounced": 0, "unsubscribed": 0, "definitions": { "opened": "not measurable (no tracking pixel)" } } ] }
```
`GET /api/owner/leads/{id}` → `famtastic.owner-api.lead.v1` (`id: \d+`): `leadDetail()` output; 404 when absent.
`GET /api/owner/queue` → `famtastic.owner-api.queue.v1`: `{ postiz: {configured, reachable, state, checked_at, cache_age_seconds, host}, counts: {stalled, error, queued, published, draft} (ABSENT when unreachable), posts: [normalized + campaign_key/content_id|null], channels: [{identifier,name,state,detail}], outbox, workers, runbook[] }`. `host` = ngrok hostname only (no path/token).
`GET /api/owner/traffic` → `famtastic.owner-api.traffic.v1`: `ownerReport()`; when `available:false` add `setup_steps[]` (static: create service account → enable Analytics Data API → add its email as Viewer on the GA4 property (numeric id, not `G-T2ENFBZR4K`) → place key file outside the web root → set the two `settings.local.php` keys) + gap `{key:'traffic', state:'unavailable'}`.

**Routes:** `owner_leads` `/api/owner/leads`; `owner_lead` `/api/owner/leads/{id}`; `owner_queue` `/api/owner/queue`; `owner_traffic` `/api/owner/traffic`.

**Tests:** `tests/src/Unit/PostizPostsServiceTest.php` — `deriveState` QUEUE −1000s → `stalled`, +1000s → `queued`, ERROR → `error`, PUBLISHED → `published`; `normalize` overdue math. Contract: `OwnerMetricsService.php` contains `'state' => 'unreachable'` and the unreachable branch does not emit `counts`.

**Verification**
```
curl -s -b /tmp/owner.jar http://127.0.0.1:8935/api/owner/queue | jq '.data.postiz,.data.counts,(.data.posts|length),.gaps'
# unreachable proof: set famtastic_postiz_base_url = http://127.0.0.1:9 in backend/web/sites/default/settings.local.php + any key, restart runserver:
curl -s -b /tmp/owner.jar http://127.0.0.1:8935/api/owner/queue | jq '.data.postiz.state,(.data|has("counts")),.gaps[0].state'   # unreachable false unreachable
# stalled proof (Mac with Postiz up, real base/key): the 4 IG posts (if not yet requeued) show derived "stalled"
curl -s -b /tmp/owner.jar http://127.0.0.1:8935/api/owner/queue | jq '[.data.posts[]|select(.derived=="stalled")|{integration:.integration.identifier,overdue_seconds,campaign_key,content_id}]'
curl -s -b /tmp/owner.jar 'http://127.0.0.1:8935/api/owner/leads?needs_reply=1' | jq '.data.total,.data.leads[0].status_label'
curl -s -b /tmp/owner.jar "http://127.0.0.1:8935/api/owner/leads/$(backend/vendor/bin/drush -r backend/web sqlq 'SELECT MAX(id) FROM famtastic_prospect')" | jq '.data.links,(.data.events|length)'
curl -s -b /tmp/owner.jar http://127.0.0.1:8935/api/owner/traffic | jq '.data.available,.data.state,.gaps'   # false not_configured [...]
time curl -s -o /dev/null -b /tmp/owner.jar http://127.0.0.1:8935/api/owner/summary   # ≤4s with Postiz down; faster on negative cache
./scripts/e2e-admin-links.sh   # JSON_PATHS += leads, queue, traffic
```
**Anti-patterns:** treating `QUEUE` as healthy; positive Postiz cache >120s; leaking the API key or a tokenized ngrok URL; counting `social.*` events; hardcoded "68"; emitting `counts` of zero when unreachable; calling GA4 from the browser.

---

## P4 — Frontend shell: routes, sign-in gate, layout, tab bar, `admin.css`, Today, validator, Playwright (~10h)

**Create**
- `frontend/src/admin.css`; `frontend/src/api/owner.js`; `frontend/src/components/owner/OwnerShared.jsx`, `useOwnerFetch.js`
- `frontend/src/pages/owner/OwnerLayout.jsx`, `OwnerGate.jsx`, `OwnerTodayPage.jsx`, `OwnerMorePage.jsx`, `OwnerProjectsPage.jsx`; placeholders `OwnerCampaignsPage/OwnerCampaignPage/OwnerDropPage/OwnerLeadsPage/OwnerLeadPage/OwnerQueuePage/OwnerTrafficPage` rendering `<Skeleton/>` + a `GapCard` "Ships in P5/P6" (replaced later)
- `scripts/validate-owner-dashboard-dna.mjs`, `scripts/e2e-owner-dashboard.sh`
- `frontend/e2e/owner-dashboard.spec.js` + `frontend/e2e/fixtures/owner/{session,session-401,summary,summary-none,campaigns,campaign,drop,leads,lead,queue,queue-unreachable,traffic}.json` (hand-written from the P1–P3 contracts, exact envelope shape)

**Modify**
- `frontend/src/App.jsx`: delete `:111-118`; insert after `:64` (outside `<Layout/>`):
```jsx
<Route path="/admin" element={<OwnerLayout />}>
  <Route index element={<OwnerTodayPage />} />
  <Route path="campaigns" element={<OwnerCampaignsPage />} />
  <Route path="campaigns/:key" element={<OwnerCampaignPage />} />
  <Route path="campaigns/:key/drops/:contentId" element={<OwnerDropPage />} />
  <Route path="leads" element={<OwnerLeadsPage />} />
  <Route path="leads/:id" element={<OwnerLeadPage />} />
  <Route path="queue" element={<OwnerQueuePage />} />
  <Route path="traffic" element={<OwnerTrafficPage />} />
  <Route path="more" element={<OwnerMorePage />} />
  <Route path="projects" element={<OwnerProjectsPage />} />
  <Route path="*" element={<Navigate to="/admin" replace />} />
</Route>
```
- `frontend/public/.htaccess` after line 45:
```
  # Owner dashboard (React, authenticated). Deep links and phone refreshes must load the SPA shell.
  # Drupal's own admin lives under /web/admin/ and never matches this rule.
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteCond %{REQUEST_FILENAME} !-d
  RewriteRule ^admin(?:/.*)?$ /index.html [L]
```
- `Header.jsx:61` unchanged (legacy OAuth-gated link). Discoverability is P9.

**`frontend/src/api/owner.js`** — COPY `customer.js:1-22`:
```js
const WEB_PREFIX = import.meta.env.DEV ? '' : '/web';
const API = `${WEB_PREFIX}/api/owner`;
export class OwnerApiError extends Error { constructor(message, status, code) { super(message); this.status = status; this.code = code; } }
async function request(path, options = {}) { /* as customer.js request(): same-origin cookies, CSRF when options.csrf, throw OwnerApiError(payload.message || 'Please try again.', status, payload.error) */ }
export const ownerSession   = () => request('/session');
export const ownerLogin     = (email, password) => request('/login', { method: 'POST', body: JSON.stringify({ email, password }) });
export const ownerLogout    = () => request('/logout', { method: 'POST', csrf: true });
export const ownerSummary   = () => request('/summary');
export const ownerCampaigns = (fixtures = false) => request(`/campaigns${fixtures ? '?fixtures=1' : ''}`);
export const ownerCampaign  = (key) => request(`/campaigns/${encodeURIComponent(key)}`);
export const ownerDrop      = (key, contentId) => request(`/campaigns/${encodeURIComponent(key)}/drops/${encodeURIComponent(contentId)}`);
export const ownerLeads     = (params = {}) => request(`/leads?${new URLSearchParams(params)}`);
export const ownerLead      = (id) => request(`/leads/${encodeURIComponent(id)}`);
export const ownerQueue     = () => request('/queue');
export const ownerTraffic   = () => request('/traffic');
```
**`useOwnerFetch(loader, deps)`** → `{state:'loading'|'ready'|'error', data, error, reload}`; 401 → `useOutletContext().onSignedOut()`; 403 → `ErrorState` "This account is not an owner account."; 404 → "Not found — it may have been removed. Go back."; else "Could not reach FAMtastic. Check the connection, then Try again." with a 44px `Try again`.

**`OwnerShared.jsx` exports:** `money/date/title` (copied), `Panel`, `Empty({children, action})` (action required), `Skeleton({lines})` (`aria-busy`, rendered empty first), `ErrorState({message,onRetry,backTo})`, `GapCard({gap})` (Unavailable / Unreachable / Not instrumented / Stale (as of …) + reason + one link), `Stat({label,value,unit,sub,to})`, `Bar({label,value,max,format})` (SVG rect from zero, value labelled directly, no axis), `BarList({rows,max})` (descending), `Sparkline({points,label})`, `StateBadge({state})` (glyph map ok "●", attention "▲", danger "■", unknown "?" — text always present), `ListRow({to,title,meta,right,flag})` (min-height 56px, whole row tappable, trailing "›"), `DetailHeader({eyebrow,title,meta,back})` (44px "‹ Back"), `TableAlt({caption,columns,rows})` inside `.owner-table-wrap` (`overflow-x:auto`), `relative()`, `dateTime()`, `Freshness({generated_at,sources,cache_age_seconds})`.

**`OwnerLayout.jsx`**: `<div className="owner-app">` → `<OwnerGate>` → `<OwnerTopBar/>`, `<main id="owner-main" className="owner-main" tabIndex="-1"><Outlet context={{session,onSignedOut,summary}}/></main>`, `<OwnerTabBar/>`. Title from first path segment (`Today, Campaigns, Leads, Queue, More`); `document.title = \`${title} · Owner · FAMtastic\``; imports `'../../admin.css'` (pattern `CustomerPortalDashboard.jsx:22`). Fetches summary once for the tab badge.
**`OwnerGate.jsx`**: `checking` (Skeleton, no redirect flash) → `signed_out` (`<OwnerSignIn/>`: email `type=email inputMode=email autoComplete=username`, password `autoComplete=current-password`, both `font-size:16px; min-height:44px`; submit "Open owner dashboard"; `role="alert"` error with the API message; footer "Customer? Use the Client Portal sign-in" → `/login`) → `forbidden` (message + 44px Sign out) → `ready`.
**`OwnerTabBar.jsx`** (inside OwnerLayout file or separate): `<nav aria-label="Owner sections" class="owner-tabs">` 5 `NavLink`s: Today (`/admin`, `end`), Campaigns, Leads, Queue, More — each `min-height:56px; min-width:44px`, 13px label + 20px inline SVG glyph (`aria-hidden`). Queue tab badge `<b class="owner-tab-badge" aria-label="{n} stalled">` when `summary.data.queue.stalled > 0`.
**`OwnerTodayPage`** (answers where/what/what-now/why in order): (1) `.owner-next` card — eyebrow "Next", `<h2>{title}</h2>`, `<p>{detail}</p>`, ONE `<Link class="owner-btn owner-btn--primary">` (label by kind: stalled_posts/dead_letters/worker_late/postiz_unreachable "Open queue", needs_reply "Open leads", gates_open "Open campaigns"); only when `kind !== 'none'` render `<span class="owner-glow" aria-hidden="true"/>` as first child; `none` → no glow, "Nothing needs you right now", secondary "See campaigns". (2) `.owner-stats` ×3: Revenue 30d, Leads (new · need reply), Queue (state word + stalled count when live). (3) `Freshness`. (4) `gaps.map(GapCard)`. (5) Workers panel. (6) `<details>` "Why these numbers" with `definitions`.
**`OwnerMorePage`**: rows → `/admin/projects`, `/admin/traffic`, Drupal links via `WEB_PREFIX` (`/admin/famtastic`, `/admin/famtastic/marketing`, `/admin/famtastic/campaigns`, `/admin/famtastic/metric/notifications`, `/admin/famtastic/analytics`), Postiz UI (`https://{host}` from queue payload, new tab, caption "Runs on the Mac tunnel"), Sign out.
**`OwnerProjectsPage`**: `const {token} = useUser()`; with `token?.access_token` → `<AdminDashboardPage/>`; else Panel "JSON:API session not available" + 44px link `${WEB_PREFIX}/admin/content?type=client_project` "Open in Drupal". Do not wire OAuth here.

**`admin.css` — include these rules verbatim (mobile-first):**
```css
.owner-app{--p-bg:#070907;--p-panel:#101310;--p-panel-2:#141814;--p-line:#252b25;--p-lime:#7cfc00;--p-text:#f6f8f6;--p-text-2:#aeb6ae;--p-muted:#8e998e;--p-radius:18px;--p-ease:cubic-bezier(0.16,1,0.3,1);
  min-height:100vh;min-height:100dvh;background:var(--p-bg);color:var(--p-text);font-family:Inter,system-ui,-apple-system,sans-serif;font-size:16px;line-height:1.5}
.owner-app,.owner-app *{box-sizing:border-box}
.owner-app{width:100%;max-width:100%;overflow-x:clip}
.owner-main,.owner-panel,.owner-row,.owner-stat{min-width:0}
.owner-panel h2,.owner-panel p,.owner-row strong,.owner-link{overflow-wrap:anywhere}
.owner-app a,.owner-app button,.owner-app input,.owner-app select{min-height:44px;touch-action:manipulation}
.owner-app input,.owner-app select{font-size:16px;width:100%;background:#080b08;color:#fff;border:1px solid var(--p-line);border-radius:9px;padding:.75rem}
.owner-app :focus-visible{outline:2px solid var(--p-lime);outline-offset:2px}
.owner-num{font-variant-numeric:tabular-nums}
.owner-eyebrow{color:var(--p-lime);font-size:13px;text-transform:uppercase;letter-spacing:.14em;font-weight:800}
.owner-top{position:sticky;top:0;z-index:20;height:56px;display:flex;align-items:center;gap:.5rem;padding:0 1rem;border-bottom:1px solid var(--p-line);background:rgba(8,10,8,.97)}
.owner-main{padding:1rem 1rem calc(72px + 1rem)}
.owner-tabs{position:fixed;z-index:30;left:0;right:0;bottom:0;display:grid;grid-template-columns:repeat(5,minmax(0,1fr));height:64px;padding-bottom:8px;border-top:1px solid var(--p-line);background:rgba(8,10,8,.97)}
.owner-tabs a{display:grid;place-items:center;gap:2px;min-height:56px;min-width:44px;color:var(--p-text-2);font-size:13px;text-decoration:none}
.owner-tabs a[aria-current="page"]{color:var(--p-lime);font-weight:800}
.owner-panel{padding:1rem;border:1px solid var(--p-line);border-radius:var(--p-radius);background:var(--p-panel);margin-bottom:1rem}
.owner-next{position:relative;isolation:isolate;border-color:rgba(124,252,0,.45);background:linear-gradient(145deg,rgba(124,252,0,.12),var(--p-panel))}
.owner-glow{position:absolute;inset:0;border-radius:inherit;box-shadow:0 0 24px rgba(124,252,0,.35);pointer-events:none;z-index:-1}
.owner-btn{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:.7rem 1rem;border:1px solid var(--p-line);border-radius:9px;background:transparent;color:var(--p-text);font:inherit;font-weight:800;text-decoration:none;cursor:pointer;transition:border-color .2s var(--p-ease),background .2s var(--p-ease)}
.owner-btn--primary{background:var(--p-lime);border-color:var(--p-lime);color:#071000}
.owner-row{display:grid;grid-template-columns:minmax(0,1fr) auto 20px;align-items:center;gap:.75rem;min-height:56px;padding:.5rem 0;border-bottom:1px solid var(--p-line);color:inherit;text-decoration:none}
.owner-stats{display:grid;grid-template-columns:minmax(0,1fr);gap:.5rem}
.owner-badge{display:inline-flex;align-items:center;gap:.35rem;padding:.2rem .5rem;border:1px solid var(--p-line);border-radius:999px;font-size:13px}
.owner-badge.is-danger{border-color:#e35d5d}.owner-badge.is-attention{border-color:#e2ac5f}.owner-badge.is-ok{border-color:rgba(124,252,0,.6)}
.owner-scroller{display:flex;gap:8px;overflow-x:auto;scroll-snap-type:x mandatory;padding:0 24px 8px 0;-webkit-overflow-scrolling:touch}
.owner-scroller>*{flex:0 0 calc(100% - 28px);scroll-snap-align:start}
.owner-table-wrap{overflow-x:auto;max-width:100%}
.owner-skeleton{min-height:96px;border-radius:var(--p-radius);background:var(--p-panel-2)}
@media (min-width:360px){.owner-stats{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media (min-width:760px){.owner-main{max-width:760px;margin:0 auto}.owner-grid-2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}}
@media (hover:hover){.owner-row:hover,.owner-btn:hover{border-color:rgba(124,252,0,.55)}}
@media (prefers-reduced-motion:no-preference){.owner-skeleton{animation:owner-pulse 1.2s ease-in-out infinite}@keyframes owner-pulse{50%{opacity:.6}}}
@media (prefers-reduced-motion:reduce){.owner-app *{transition:none!important;animation:none!important;scroll-behavior:auto!important}}
```
Type roles: `.owner-display{font-size:36px;line-height:1.1}`, `.owner-title{font-size:24px;line-height:1.2}`, `h3{font-size:18px}`, captions 13px. Spacing only 4/8/16/24/32.

**`scripts/validate-owner-dashboard-dna.mjs`** (COPY assert/pass/fail scaffolding from `validate-client-portal-design-dna.mjs:8-27,134-141`). Checks: (1) owner files exist; (2) `App.jsx` has `path="/admin" element={<OwnerLayout />}`, `campaigns/:key/drops/:contentId`, `leads/:id`, `queue`, and NOT `/<ProtectedRoute>\s*<AdminDashboardPage/`; (3) `admin.css` contains `.owner-app{--p-bg:#070907`, `--p-lime:#7cfc00`, `overflow-x:clip`, `min-height:44px`, `tabular-nums`, `touch-action:manipulation`, `font-size:16px`, `prefers-reduced-motion:reduce`, `(hover:hover)`, `:focus-visible`, `cubic-bezier(0.16,1,0.3,1)`; NOT `--fam-`, `transition:all`, `transition: all`; exactly ONE `0 0 24px rgba(124,252,0,.35)` and it is inside `.owner-glow{`; (4) owner JSX: no `--fam-`; `owner-glow` appears in exactly one file (`OwnerTodayPage.jsx`) once; no `section ===`; no imports of `recharts|chart\.js|d3|victory|nivo|echarts` and none in `package.json`; list pages import `ListRow`, detail pages import `DetailHeader`; `OwnerShared.jsx` exports `GapCard, Skeleton, ErrorState, TableAlt`; each page references `gaps`; (5) `.htaccess` has the `^admin(?:/.*)?$` rule preceded by both `RewriteCond`s; `generate-seo-shells.mjs` has `Disallow: /admin`; (6) routing.yml has every `/api/owner/` path with `_permission`+`_auth`; controller/service files exist. Exit 1 on any failure.

**`frontend/e2e/owner-dashboard.spec.js`** (`--project=mobile-chromium`, `page.route('**/api/owner/**')` → fixtures; helpers `assertNoHorizontalOverflow` (COPY `customer-launch.spec.js:15-24`), `assertTouchTargets` (all visible `a,button` ≥44×44), `glowCount`). Tests: (1) signed-out shows sign-in, inputs ≥16px, zero glow; (2) Today with `stalled_posts` → exactly one glow, ≤5 tabs, no overflow, targets ok, screenshot `today.png`; (3) `summary-none` → zero glow + "Nothing needs you right now"; (4) `queue-unreachable` → text "Unreachable", no `/0 stalled/`; (5) `/admin` → tap Leads → tap first row → lead heading + link containing `/admin/famtastic/prospect/` (2 taps); (6) campaigns → row → drop row → link containing `/admin/famtastic/marketing/drop/`; (7) deep link `/admin/campaigns/booked_and_losing/drops/bl-drop-01` renders; `goBack()` works; (8) every screen: overflow + targets + screenshot.
**`scripts/e2e-owner-dashboard.sh`**: boot Vite only (COPY `e2e-portal-links.sh:103-112`, `PORT_UI=8938`, `VITE_DRUPAL_PROXY_TARGET=http://127.0.0.1:9`), run the spec; `LIVE=1` boots `drush runserver :8935`, disables mocks (`OWNER_E2E_LIVE=1`), uses `FAMTASTIC_E2E_OWNER_EMAIL/PASSWORD` (never printed).

**Verification**
```
node scripts/validate-owner-dashboard-dna.mjs && node scripts/validate-client-portal-design-dna.mjs
npm --prefix frontend run build && grep -c "owner-app" frontend/dist/assets/*.css
./scripts/e2e-owner-dashboard.sh   # green; look at .artifacts/playwright-results/**/today.png at 390px
# phone check: VITE_DRUPAL_PROXY_TARGET=http://127.0.0.1:8935 npm --prefix frontend run dev -- --host 0.0.0.0 ; open http://<mac-ip>:5173/admin on the phone
```
Review lane: `critique-affordance` + `critique-information-density` on `today.png`, `signin.png`.
**Anti-patterns:** `--fam-*`; `transition: all`; a second glow (site `.btn--lime` has its own glow at `index.css:526` — never use `.btn` inside `.owner-app`); hover-only affordances; `?tab=` state nav; redirecting during `checking`; `position:fixed` bar without `.owner-main` bottom padding; rendering gaps as zeros; inputs <16px; 4+ stat columns; chart legends/gridlines; adding a chart dependency.

---

## P5 — Frontend: Campaigns list → campaign → drop (~8h)

**Create** `OwnerCampaignsPage.jsx`, `OwnerCampaignPage.jsx`, `OwnerDropPage.jsx`.
- **List**: lane chips scroller (All · Social · Email · Lead sources · "Show fixtures (n)"); `ListRow` per campaign — `title=name||key`, `meta="{drops.total} drops · {published} published · {stalled ?? '—'} stalled · {error} errors"` (social) / `"{sent} sent · {paid_orders} paid"` (email); `right=<b class="owner-num">{money(revenue_minor)}</b><small>{leads} leads</small>`; `flag=StateBadge` (danger stalled>0, attention error>0, unknown when `provider_state!=='live'` → "As of {date}" / "No provider data"). Empty → "Open Marketing Command Center". Gaps above the list.
- **Campaign**: `DetailHeader` (eyebrow lane, title name, meta `{key} · {status} · files yes/no`, back `/admin/campaigns`); stats ×3 Leads/Requests/Revenue; row 2 Published/Queued/Stalled-or-"—"; Panel "Leads per drop" → `BarList` (desc) + `<details>` "Table view" → `TableAlt`; Panel "Drops" → `ListRow` per drop (`title="{content_id} — {theme}"`, `meta=dateTime(scheduled_at)+' · '+channels`, `right="{published}/{records} live"|"scorecard {date}"|"not queued"`, `flag` stalled/error); Panel "Do it in Drupal" → `links.*` 44px; `Freshness`; gaps.
- **Drop**: `DetailHeader`; Panel "Provider records" (integration_name, `StateBadge derived` — stalled shows "Stalled · overdue {relative}", `dateTime(publish_at)`, `release_url` → "View post" new tab; caption "Live from Postiz, checked {relative}" / "From scorecard as of {…}" / "No records"); Panel "Results" (Leads/Requests/Revenue + attribution gap); Panel "Tracked link" (`<code class="owner-link">` wrapping, full value always readable + utm `<dl>`); Panel "Leads" → `ListRow` → `/admin/leads/{id}`; Panel "Do it in Drupal" → `links.edit` "Edit drop", `links.delete` "Delete drop" (plain `owner-btn`, never primary), `links.scorecard`.

**Verification:** validator + build + spec additions: rows are links (count equals fixture length); bar chart has a "Table view" details; first `<rect>` x === 0. Live: walk Campaigns → booked_and_losing → bl-drop-01 → "Edit drop" opens `/web/admin/famtastic/marketing/drop/booked-and-losing/bl-drop-01/edit`.
**Anti-patterns:** ascending bars; legends; 6-panel walls; `stalled:null` as 0; "Delete drop" styled primary; scorecard data without "As of"; truncated `tracked_link` with no full view.

---

## P6 — Frontend: Leads, Queue, Traffic (~8h)

- **`OwnerLeadsPage`** (`/admin/leads?status=&campaign=&needs_reply=&q=`, all in `useSearchParams`): chips "All", "Needs reply ({n})", statuses; `<select>` campaign (16px); `<input type="search">` debounced 300ms; rows `title=business_name`, `meta="{status_label} · {campaign} · {relative(created)}"`, `right=paid_minor?money:''`, `flag=needs_reply?<StateBadge attention "Needs reply"/>:null`; "Load more" 44px. Below: Panel "Cohorts we ran" → `TableAlt` (campaign_key, source, qualified, unqualified, invalid, suppressed, duplicate, imported range); Panel "Email results" → per campaign `BarList` sent/delivered/clicked/replied, `opened` rendered as text "not measurable", + `TableAlt`.
- **`OwnerLeadPage`**: `DetailHeader` (eyebrow campaign, title business_name, meta `{status_label} · created {dateTime}`); Panel "What we know" `<dl>`; Panel "Next" (text like `OperationsController:1057-1059`) + ONE primary 44px link `links.workspace` "Open lead workspace" (no glow); Panel "Customer continuation" (request → `links.proof_review`); Panel "Payments"; Panel "Emails"; Panel "Timeline" (events, `owner.note` shows text); `<details>` More → `links.edit`. P7 adds the Status/Note controls here.
- **`OwnerQueuePage`**: header Panel `StateBadge(postiz.state)` + copy: ok "Postiz is reachable and nothing is stalled" / attention "Something needs you" / unreachable "Postiz is unreachable (Mac asleep or tunnel down). Counts are unknown, not zero." / not_configured "Postiz is not configured on this server"; `checked_at` + `cache_age_seconds`; counts row only when `data.counts` exists; sections: Stalled (integration, `content_preview`, "overdue {relative}", link to drop when mapped) → Errors → Queued (7 days) → `<details>` Published; Panel "Channels" (`StateBadge` connected/disabled + detail); Panel "Notifications" (outbox + Drupal link); Panel "Workers"; Panel "If posts are stalled" → `runbook[]`; Postiz UI link (new tab) when configured; gaps.
- **`OwnerTrafficPage`**: `available:false` → `GapCard` + Panel "Connect GA4 reporting (owner, ~15 min)" listing `setup_steps` + "Open Google Analytics"; `available:true` → totals (3+2 stats), `BarList` pages / channels / campaigns (+`TableAlt`), "As of {generated_at} · cached up to 15 min".

**Verification:** validator + build + specs: filters survive reload (URL carries `?needs_reply=1`); unreachable queue hides counts; traffic not configured shows setup steps and no numbers; lead detail has exactly one primary button and zero glow. Live: `curl` `needs_reply` count equals the screen.
**Anti-patterns:** filters only in state; colour-only status; `opened` as 0; counts when unreachable; a "Requeue" button (stays in Postiz/Drupal); Temporal/Postiz internals in the primary path.
**Parallel:** P5 ∥ P6.

---

## P7 — Lead actions from the phone: status + notes (owner-chosen, ~6h)

**Backend**
- Route `famtastic_pipeline.owner_lead_update`: `PATCH /api/owner/leads/{id}` (`id: \d+`), `_permission`, `_auth: ['cookie','oauth2']`, **`_csrf_request_header_token: 'TRUE'`**, `no_cache`.
- `OwnerApiController::updateLead(int $id, Request)` — body `{ "status"?: string, "next_followup_due"?: int|null, "lost_reason"?: string|null, "note"?: string }`. Validate: `status` ∈ `Prospect.php:150-152` vocabulary else 422 `validation_failed` (`message` lists allowed values); `note` ≤ 2000 chars, trimmed, non-empty when present; `next_followup_due` int ≥ 0 or null. Load via `entity_type.manager` storage `famtastic_prospect`; 404 when absent. Apply field changes and `save()`; write append-only ledger rows to `famtastic_event` through the existing event writer (executor: grep `famtastic_event` inserts in `src/Service` — e.g. the method used by `AttributionService`/`LeadIngestionService` — and COPY that call; never raw-insert if a writer exists): `event_type='owner.status_changed'` payload `{from,to,uid}`, `event_type='owner.note'` payload `{text,uid}`, `event_type='owner.followup_set'` payload `{at,uid}`. Response: `envelope('famtastic.owner-api.lead.v1', leadDetail($id), …)`.
- No schema change (notes live in the ledger; the timeline already renders events).

**Frontend** (`OwnerLeadPage`): Panel "Update" with a `<select>` Status (options from `filters.statuses` labels, current selected), `<input type="datetime-local">` Next follow-up, `<textarea>` Note (16px, min-height 88px), one 44px `owner-btn` "Save" (NOT `--primary`; the page's single primary stays "Open lead workspace"). Optimistic off; on success replace `data` with the response and `role="status"` "Saved"; on 422 show the message inline `aria-describedby`; on 403 → `ErrorState`. `ownerUpdateLead(id, body)` → `request(`/leads/${id}`, {method:'PATCH', csrf:true, body})`.

**Verification**
```
TOKEN=$(curl -s -b /tmp/owner.jar http://127.0.0.1:8935/session/token)
ID=$(backend/vendor/bin/drush -r backend/web sqlq 'SELECT MAX(id) FROM famtastic_prospect')
curl -s -b /tmp/owner.jar -X PATCH -H "X-CSRF-Token: $TOKEN" -H 'Content-Type: application/json' -d '{"status":"viewed","note":"owner test note"}' "http://127.0.0.1:8935/api/owner/leads/$ID" | jq '.data.status,(.data.events|map(select(.event_type=="owner.note"))|length)'
curl -s -b /tmp/owner.jar -X PATCH -H "X-CSRF-Token: $TOKEN" -H 'Content-Type: application/json' -d '{"status":"bogus"}' -w '\n%{http_code}\n' "http://127.0.0.1:8935/api/owner/leads/$ID"   # validation_failed 422
curl -s -b /tmp/owner.jar -X PATCH -H 'Content-Type: application/json' -d '{"status":"viewed"}' -o /dev/null -w '%{http_code}\n' "http://127.0.0.1:8935/api/owner/leads/$ID"   # 403 (no CSRF header)
backend/vendor/bin/drush -r backend/web sqlq "SELECT event_type, occurred_at FROM famtastic_event WHERE prospect_id=$ID ORDER BY id DESC LIMIT 3"
# restore the prospect's original status afterwards; spec: 'lead update saves and shows in timeline' (mocked PATCH fixture)
```
**Anti-patterns:** PATCH without CSRF; free-text status; deleting or rewriting history (ledger is append-only); making "Save" the glowing/primary element; mutating anything other than the four fields; sending email or changing Commerce/proof state from this endpoint.

---

## P8 — Production data path + deploy (~4h + owner-gated windows)

**Create** `scripts/sync-campaign-state-godaddy.sh` (header/`SSH_TARGET`/`--apply` gate copied from `deploy-frontend-godaddy.sh:1-27`; no clean-tree requirement — generated files):
- validate every `marketing/campaigns/*/{manifest,posting-schedule,scorecard}.json` with `python3 -c 'import json,sys; json.load(open(sys.argv[1]))'`;
- dry: `rsync -avn --include='*/' --include='manifest.json' --include='posting-schedule.json' --include='scorecard.json' --exclude='*' marketing/campaigns/ "$SSH_TARGET:marketing/campaigns/"`; `--apply` drops `-n`; **never `--delete`**, never media;
- verify: `ssh … drush php:eval "print json_encode(\Drupal\famtastic_pipeline\Utility\CampaignFileLocator::listCampaignSlugs());"` equals the local slug list, then `drush cache:rebuild` (busts the 300s index cache); artifacts under `.artifacts/campaign-sync/<epoch>/`.
**Modify** `scripts/delivery/run-cycle.sh` after the Campaigns loop (`:103-118`), opt-in:
```bash
if [[ "${SCORE_CAMPAIGNS:-0}" == 1 && "$QUEUE_STATE" != "unknown" ]]; then for s in marketing/campaigns/*/posting-schedule.json; do python3 scripts/score-campaign.py --campaign "$(basename "$(dirname "$s")")" || true; done; fi
if [[ "${SYNC_CAMPAIGN_STATE:-0}" == 1 ]]; then ./scripts/sync-campaign-state-godaddy.sh --apply; fi
```
**Deploy sequence (owner authorizes each `--apply`):**
1. Commit + push P1–P7 (clean tree; `HEAD == origin/main`; repo commit rule: no attribution trailers).
2. `./scripts/deploy-backend-godaddy.sh` preflight → `--apply` (backups, then `drush updatedb` — applies 8037 if P0 found it missing).
3. `curl -s -w '\n%{http_code}\n' https://famtasticdesigns.com/web/api/owner/session` → JSON 401; `…/summary` → 403.
4. `./scripts/deploy-frontend-godaddy.sh` preflight → `--apply`.
5. `curl -s -o /dev/null -w '%{http_code}\n' https://famtasticdesigns.com/admin/leads/1` → 200 (SPA shell); `https://www.famtasticdesigns.com/admin` → 200; `curl -sL https://famtasticdesigns.com/web/admin/famtastic | grep -c owner-app` → 0 (Drupal admin untouched); `robots.txt` still `Disallow: /admin`.
6. `./scripts/sync-campaign-state-godaddy.sh` → `--apply`.
7. Owner signs in on the phone (apex + www), walks Today → Campaigns → drop → Leads → lead (save a note) → Queue. Record to `.artifacts/owner-dashboard/prod-<date>.md`.
8. Docs: CHANGELOG; CAPABILITY_REGISTRY row "Owner dashboard (React /admin + /api/owner)" classified only per evidence ("Production smoke-tested" after step 7, with the artifact path); SITE-LEARNINGS entries (no owner sign-in path existed; `utm_content ≠ content_id`; SQL reschedule orphans Temporal workflows); Drive mirror summary.
**Anti-patterns:** frontend before backend; `rsync --delete`; syncing media; dirty tree; trusting prod drush exit codes; editing prod files by hand; changing `settings.local.php` without the owner.

---

## P9 — Drupal HTML admin: every row opens a detail; discoverability (~6h)

Use the existing 44px pattern (`MarketingCommandController.php:447-458`) and existing routes. `spaUrl()` from P1.
| Surface | Today | Change |
|---|---|---|
| `OperationsController::dashboard()` campaigns table `:184-202` | bare key link | add "Open" `.button button--small` → `famtastic_pipeline.operations_campaign` |
| `metric('prospects')` `:993-1012` | text link | render as button; keep text |
| `metric('campaigns')` `:960-985` | key link | add "Open" button column |
| `websiteRequestMetric` `:548-588` | link list | buttons; add "Lead workspace" when `prospect_id` (workspace route, not entity edit) |
| `supportMetric` `:590-603` | Reply only | button; add "Thread" if a thread route exists (grep routing.yml first) |
| `notificationMetric` `:605-612` | Retry only | add "Inspect" → `famtastic_pipeline.marketing.email_inspect` |
| `socialRecordsMetric` `:353-385` | gate links | add "Results" → `spaUrl("/admin/campaigns/{campaign_id}/drops/{content_id}")` |
| `MarketingCommandController::tabQueue` `:343-378` | tiny text links | buttons + SPA drop link |
| `tabDrops` `:435-459` | Edit/Delete | add "Scorecard" + "Results" (SPA drop) |
| `tabAttribution` content grain | text | Content ID → SPA drop; Campaign → `operations_campaign` when a `famtastic_campaign` row exists else SPA campaign |
| `campaignLifecycleClockRows` | text | Campaign → `operations_campaign` |
| `hub()` cards `:60-81` | — | card "Owner dashboard (mobile)" → `spaUrl('/admin')` |
**Portal discoverability:** `CustomerPortalController::sessionPayload()` (`:577-583`) adds `'owner' => hasPermission('administer famtastic pipeline')`; `PortalNav.jsx` renders one extra 44px `<Link to="/admin">Owner dashboard</Link>` under "Account & Billing" when `session.customer.owner === true`. Portal validator must stay green (no contract sections added).
**Verification:** `./scripts/e2e-admin-links.sh` (add `/admin/famtastic/metric/social-records`, `/admin/famtastic/marketing/attribution`); rendered-row check (`grep -c '<tr'` vs `grep -c 'class="button'` per page); contract test for `'#title' => $this->t('Open')`; 390px check of `/web/admin/famtastic/metric/prospects` (theme enforces 44px ≤720px).
**Anti-patterns:** `Link::fromTextAndUrl()->toRenderable()` without `.button` (sub-44px); linking entity edit forms instead of the workspace; hardcoding `/web/` in SPA links; a second campaign system.

---

## P10 (optional) — Stall/unreachable alert via outbox; HyperFrames "weekly numbers" film (~6h)

**Alert (3h):** in `LifecycleOperationsService::runProtection()` (`:267-330`), gated by `Settings::get('famtastic_owner_stall_alerts', FALSE)`: inject `famtastic_pipeline.owner_metrics`; `queueHealth(3)`; if `attention` with `stalled>0` → `queue('postiz:stalled:'.gmdate('YmdH'), $admin, 'Postiz: N post(s) stalled in QUEUE', <list + spaUrl('/admin/queue') + runbook pointer>)`; if `unreachable` and drops due within 24h → `queue('postiz:unreachable:'.gmdate('YmdH'), …)`. Hour-bucketed keys + `queue()`'s merge (`:727-733`) = max one per hour per condition. Add `drush famtastic:owner-queue-health` (COPY `revenueHealth :878-895`). Verify: `scripts/e2e-lifecycle-operations.sh` still green; with dead base + setting on, `drush famtastic:lifecycle-run` twice → exactly one `postiz:%` outbox row.
**Film (3h+):** `drush famtastic:owner-report` → `marketing/reports/owner-weekly/data.json` (committed, deterministic); `/hyperframes` skill; rules: 2–3 metrics side by side, every metric paired with a proportional bar, GSAP+SVG only, no fetch/`Date.now`/random, tokens `#070907/#7cfc00`, one glow per scene; 4 scenes (Revenue 30d, Leads new/need reply, Top 3 campaigns bars, Queue state); render to `.artifacts/owner-report/<date>.mp4`; do not publish.

---

## D. Dashboard & tooling sanity check

**Exists today — keep, link, don't rebuild:** Drupal Operations Home + metric drill-downs + Campaign Operations + Marketing Command Center (10 tabs) + prospect workspace (`/web/admin/famtastic/*`) = source of truth for actions; Postiz UI (Mac/ngrok) + Temporal UI (127.0.0.1:8080) = the posting action surface; GA4 property UI (site tracking works); `drush famtastic:analytics-report` / `famtastic:revenue-health` / delivery-cycle markdown / scorecards = agent tooling; "By The Numbers" app = stale, out of scope.

**Still needed (recommendation · cost · who):**
1. GA4 Data API service account — owner · free · ~15 min (P3 `setup_steps`). Unlocks Traffic + per-campaign sessions.
2. **Postiz off the Mac** — owner decision · ~$6–12/mo VPS (`docs/marketing/CAMPAIGN_POSTING_ARCHITECTURE.md:102-143`, `POSTIZ_SERVER_MIGRATION.md`). This is the real fix for stalled/unreachable; the dashboard only makes it visible.
3. Stall/unreachable alert — build (P10) · free · reuses the outbox.
4. Search Console verification — owner · free · 10 min (DNS TXT or the `.htaccess:23` file pattern). Not consumed by code yet; enables query-level SEO data for the 98 posts later.
5. Uptime monitor on the public site — owner · free tier · 5 min. Never point it at the ngrok URL.
6. Looker Studio / Supermetrics / Metabase / Plausible — **not now**: one operator, data already in Drupal + Postiz (+ GA4 later); a BI layer adds cost and a second truth and needs the GA4 API anyway. Revisit after ≥3 months of GA4-API data. (Supermetrics MCP is present but unauthenticated and unregistered in `providers.json`.)

**Do not build:** a second campaign system; a click redirector/shortener (UTM + GA4 + Drupal attribution cover it; a redirector invites `/web/` public links); GA4 reads from the browser; requeue/approve from the phone (those stay behind their gates in Postiz/Drupal); team/billing SaaS chrome; a native app; a chart library.

## E. Risk register
| Risk | Impact | Mitigation |
|---|---|---|
| No owner sign-in path (OAuth never wired; only `default_consumer`) | `/admin` unreachable | P1 cookie owner routes + P4 gate; P0 confirms the owner uid has the permission |
| Prod missing update 8037 | attribution empty | schema guard → `unavailable` gap; P8 backend deploy first |
| Mac-hosted Postiz unreachable | Today/Queue blank or slow | 3s/5s timeouts, 120s/60s caches, explicit `unreachable`, P10 alert, VPS recommendation |
| ngrok interstitial | HTML instead of JSON | `ngrok-skip-browser-warning: 1`; non-JSON → `unreachable` |
| Two revenue definitions | numbers disagree | Commerce fulfillment everywhere + printed `definition`; drush report documented as legacy |
| `utm_campaign ≠ slug ≠ campaign_id`, `utm_content ≠ content_id` | wrong attribution | `CampaignIndexService` map from each drop's `utm`; unit test with two campaigns sharing `drop-01` |
| `recordSocialLead()` matches `content_id = utm_content` — never true for prefixed ids → `leads_count` stays 0 | counter wrong | dashboard recomputes from `utm_json`; log as follow-up in SITE-LEARNINGS (out of scope) |
| Validator blind spots (string checks) | false green | Playwright runtime assertions (overflow, 44px, glow count, unreachable-not-zero) |
| First-load session check | redirect flash/loop | `checking` renders a skeleton; inline sign-in form |
| Bottom tab bar vs `SiteFooter` | overlap | `/admin` mounted outside `Layout`; `.owner-main` bottom padding |
| iOS Safari `100dvh` | clipped bar | `100vh`+`100dvh` pair; 8px bottom pad; `viewport-fit=cover` deliberately not added (site-wide) |
| Drupal page cache on JSON | cross-session leak | `no_cache` + `no-store, private` (asserted by `curl -I`) |
| Brute force / CSRF | auth abuse | flood 5/900s; logout + PATCH require `X-CSRF-Token` |
| `journey-*` fixtures | noise | `isFixtureCampaign()` lane, hidden by default |
| `.htaccess ^admin` vs `/web/admin` | Drupal admin broken | anchored at root + `!-f/!-d`; P8 curl checks both |
| Postiz posts API caps | missing posts | window ≤9 days; ≥100 posts → gap `stale` "window truncated" |
| Prod drush exit 255 | false failures | parse output, never exit codes |
| Harness commit attribution vs repo rule | policy conflict | repo rule wins (AGENTS.md); flag to owner if the harness insists |

## F. Effort & lanes (mid-level engineer)
A0 0.25h · P0 2h · P1 6h · P2 8h · P3 8h · P4 10h · P5 8h · P6 8h · P7 6h · P8 4h (+ deploy windows) · P9 6h · P10 6h ≈ **72h**. Lanes: backend {P1→P2→P3→P7-api} ∥ frontend {P4→P5/P6→P7-ui}; P9 after P2; P8 after P3+P6+P7; P10 last.

## G. Closeout
- Update this file's task checkboxes as each phase lands (resumability surface).
- Docs sync every phase; closeout packet via `node scripts/plans/closeout.js` when P8 proof exists.
- Open follow-ups to record, not build here: `recordSocialLead()` compound-key mismatch for prefixed ids; `PostizChannelsService` never emits `expiring`; `social.*` dead event types; Postiz hosting decision.
