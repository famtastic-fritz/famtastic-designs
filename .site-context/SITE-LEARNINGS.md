# FAMtastic Designs site learnings

## 2026-09-04 — A staging directory is part of the production boundary

- Observation: GoDaddy removed a dot-prefixed backend staging directory after a release had completed validation and backups but before `rsync` could transfer code. The deployment stopped at exit 23 before the live module/theme swap; no frontend release was attempted. After moving staging to an explicit non-dot private path, the same reviewed release promoted cleanly.
- Guidance: stage under the private deployment area using explicit non-dot names and assert each directory exists immediately before transfer. A successful preflight and a fresh database backup do not prove code promotion; read the recorded release SHA and perform the planned browser check before claiming a deployment.

## 2026-09-04 — Three separate "no traffic depends on this" assumptions were all wrong

- Observation: campaign copy referenced a blog post
  (`why-running-business-on-gmail-and-linktree-costs-revenue`) that was never
  written. It sat 404ing behind a live, already-published Facebook post for a
  full day before anyone noticed — found only because Fritz personally
  clicked the link from his own feed (confirmed by the `fbclid` parameter
  Facebook appends at click time, not something we added).
  Guidance: a tracked link in a published post is production surface the
  moment it's posted, identical in blast radius to a live page — "it's just
  marketing copy" is not a reason to skip verifying every URL resolves
  before a post goes out, and campaign posting tooling should check this
  automatically rather than rely on someone happening to click through.
- Observation: `/onboarding`, the compact tracked-link destination
  (`DEFAULT_LANDING` in `scripts/queue-campaign-drops.py`) used by every
  single drop across the entire `cost-is-not-the-reason` campaign, was never
  a real route on this site — 404 since before this repo's git history
  begins. Every published post's call-to-action has been dead the whole
  time. Fixed with a redirect to `/buy` (the project's own documented CTA
  destination) rather than editing already-published post text, so old
  links start working without touching a live post.
  Guidance: a tracked-link base URL used by a campaign generator is exactly
  as load-bearing as a real site route and needs the same "does this
  actually resolve" check before the first campaign ever uses it — nobody
  checked this one when `DEFAULT_LANDING` was first written.
- Observation: every one of the 8 blog drafts (3 published this session, 5
  still pending) linked to `https://famtasticdesigns.com/web/packages/web-basics`.
  `/web/` is the Drupal backend route prefix (see `frontend/public/.htaccess`:
  "the Drupal backend root is not a landing surface"), never valid on the
  frontend — the real path is `/packages/199-quick-start`. A full audit of
  the 83 *already-published* posts found the same bug never actually reached
  production (it was caught before those posts went live), but a dead ICANN
  citation reused across 8 posts in the fifty-five-cents-a-day series had
  been live and broken since that series was seeded.
  Guidance: link correctness is not "probably fine because it's just a
  citation/reference" — every internal and external link a post ships with
  needs to resolve, checked mechanically, not by author confidence. This is
  why a content-QA audit is being promoted from "one ad hoc pass" to a
  designed, repeatable capability (see docs/CAPABILITY_REGISTRY.md).

## 2026-09-04 — Blog content writes have exactly one proven path: SSH + Drush, not JSON:API

- Observation: asked to publish two ready blog drafts via "authenticated
  JSON:API POST," a full-repo search for an existing JSON:API write
  credential for content (grep for jsonapi + POST/Authorization/Bearer
  patterns across `scripts/`, `backend/`, `frontend/`) found none. The only
  OAuth consumer in the codebase (`famtastic_spa`, simple_oauth password
  grant, `frontend/src/api/drupal.js`) authenticates a *customer's own*
  portal login (email+password they provide) — it is not a service account,
  and using it to publish blog content would mean either fabricating a fake
  user credential or hard-coding a real person's password into a script,
  both of which are exactly the invented-credential failure mode to avoid.
  A live `curl` against `https://famtasticdesigns.com/web/jsonapi/...` and
  `https://famtasticdesigns.com/jsonapi/...` also returned nothing — the
  collection isn't reachable the way the task brief assumed.
- Root cause: all 64 existing blog posts were created by
  `backend/scripts/seed-demand-content.php`, a Drush `php:script` run
  bootstrapped directly against Drupal (no HTTP auth needed) and invoked only
  from `scripts/deploy-backend-godaddy.sh` during a full, backed-up
  production deployment. There has never been an out-of-band, single-post
  publish path — deploy-time bulk seeding was the only mechanism.
- Guidance: when a task assumes an auth mechanism ("JSON:API POST") that
  doesn't actually exist in the repo, don't invent one — find what's really
  there. Here that meant reusing the SSH target
  (`FAMTASTIC_SSH_TARGET`/`xrdj7j99xhzt@p3plzcpnl497512.prod.phx3.secureserver.net`)
  already trusted by the deploy script, and writing a companion single-post
  Drush script (`backend/scripts/publish-single-blog-post.php`) that mirrors
  `seed-demand-content.php`'s idempotent-by-`field_content_key` upsert
  pattern instead of a new bulk manifest entry. See
  `scripts/publish-blog-draft.py` and `docs/CAPABILITY_REGISTRY.md`.
- Gotcha found while proving it live: calling `exit()` from inside a `drush
  php:script`-evaluated file makes Drush itself print "[warning] Drush
  command terminated abnormally" and return a non-zero exit code — even when
  the script's own JSON output on stdout is completely correct. Restructuring
  the script as if/elseif branches that fall through to natural end-of-file
  (no `exit(0)` on the success paths) eliminated the false failure. Confirmed
  by creating, updating in place, and deleting a real throwaway test node
  (nid 152-155) against production and reading its final state back.
- field_author on blog_post is an entity_reference to a Drupal `user`
  (cardinality 1), not a plain text/taxonomy field; `seed-demand-content.php`
  never actually sets it on any of the 64 posts. Default it to uid 1
  (`fritz.medine@gmail.com`, confirmed via `drush sql:query` against
  `users_field_data`) until a dedicated editorial account exists.

## 2026-09-03 — Campaign System V2 Shipped: Mutation Service, Scorecard, Admin UI

### Bare-content_id bug: Multi-platform drops need compound keys

- Observation: Earlier code keyed social records on `content_id` alone. When a campaign generated content for four platforms (FB, IG, X, TikTok), the Postiz queue created only ONE provider record instead of four — one per platform. Mutations and status tracking tried to operate on the single record and couldn't reach the other three platforms.
- Root cause: Campaign records are aggregated across platforms within a drop. A bare `content_id` is not unique across platforms. The Postiz integration, however, creates one post record per platform/integration pair. To track them, you must key on `campaign_id + content_id`, not just `content_id`.
- Guidance: **Always trace multi-platform aggregations back to the single-record case first.** When a drop says "3 posts generated for 4 platforms" and only 1 reaches the provider, first ask "what made these three disappear" (answer: they were consolidated under a single key), then trace the actual provider output by name/platform, not by count. Fix: `posting-schedule.json` schema now enforces program_id/series_id grouping (drops) and compound campaign_id+content_id keys.
- Fixed in Phase 1 (Mutation Service), verified in Phase 2 (Scorecard) by querying real Postiz records.

### Scorecard limitation: No clicks/conversions yet

- Observation: Campaign publish-state scorecard can read real Postiz states (QUEUE, PUBLISH, ERROR) but carries no click/impression/conversion/CTR/CPC fields.
- Root cause: Postiz `Post` table has no click metrics. GA4 cannot yet query `utm_content` dimension or conversion events. Attribution infrastructure exists (`AttributionService` joins leads→requests→revenue) but is not integrated into scorecard reporting.
- Guidance: A scorecard that reads real provider state is valuable even without attribution. Document honestly what data exists and what does not. The schema carries `clicks_conversions_available: false` with a required `gap_note` explaining why. This prevents future operators from assuming "all fields are available" and makes it obvious where the next upgrade belongs.
- Evidence: Phase 2 scorecard run 2026-09-04 against real `cost-is-not-the-reason` campaign: 16/18 provider records resolved with real states (7 published, 8 error, 1 queued), analytics integration deferred.

## 2026-09-03 — Check the worker is alive before debugging why the work did not happen

- Observation: nine days of total publishing failure. Four real defects were
  found and fixed above it (closed approval gates, missing per-platform Postiz
  `settings`, X copy at 2x its 280 limit, backdated drafts). None was the cause.
  The Postiz `orchestrator` — its Temporal-backed publishing worker — had been
  OOM-killed (`exit code 137`) inside a 3GiB colima VM since 2026-08-25: 13
  restarts, no log output, last healthy boot predating the campaign. Posts
  scheduled perfectly and sat in QUEUE forever. Every layer above reported
  success throughout.
- Operator checks, in this order, when scheduled work does not happen:
  1. `docker exec postiz pm2 list` — a high `↺` restart count with `online`
     status is a crash loop, not health.
  2. `docker inspect postiz --format '{{.State.OOMKilled}}'`
  3. `colima list` — the VM must have ≥8GiB. WordPress and Temporal share it,
     and Temporal is Postiz's own workflow engine, never something to stop to
     free memory.
  4. Age of the newest orchestrator log line. Stale by days = dead.
  5. Oldest QUEUE post past its publish time. Anything hours old = not draining.
- Guidance: `colima start` does not resize a running VM. Use
  `colima stop && colima start --cpu 4 --memory 8`. Everything restarts, so
  re-run `./scripts/restart-postiz-tunnel.sh` afterwards for the OAuth callback
  URL.
- Guidance: this exact failure was recorded as an OPEN RISK on 2026-08-25 with a
  resize "queued next session" that never happened and was never re-checked. An
  open risk with no owner and no verification date is a prediction, not a
  mitigation. Close it or schedule it.

## 2026-09-03 — Clear stale provider state BEFORE reviving a dead worker

- Observation: 20 duplicate posts from an earlier attempt sat in QUEUE, ten of
  them already past their publish time. Restarting the worker first would have
  fired all twenty immediately — the same campaign, at wrong times, to live
  accounts.
- Guidance: a dead queue accumulates a backlog. Audit and clear it before
  restoring the consumer, not after. Soft-delete (`deletedAt`) rather than hard
  delete, so a wrong call is reversible.

## 2026-09-03 — Trace the path to the provider before accepting "it is gated"

> **AMENDED same day**: the operative cause was the OOM-dead worker above, not
> anything in this entry. One inference below was also plain wrong.

- Observation: no campaign post has ever gone out, and the cause was assumed to
  be the publish gates. The gates were the last blocker, not the operative one:
  all 68 records of the 17-day campaign were unapproved so the executor selected
  zero candidates, and only 12 had ever reached Postiz. The media the newer
  campaign referenced was gitignored and existed only on the operator
  workstation.
- **CORRECTION**: "the newer campaign's schedule file was read by no code at
  all, therefore it never reached Postiz" — the first half was true, the
  conclusion was false. Twenty records for that campaign were already in the
  provider, created outside the repository. What the repo contains does not tell
  you what the provider holds.
- Guidance: name the first missing step between artifact and provider before
  changing a gate — then confirm it by querying the provider, not by reading the
  repository. An agent session that leaves its outputs on one machine has not
  shipped them; an audit that never asks the provider has not finished.

## 2026-09-03 — Open a gate and add the guard in the same change

- Observation: Postiz preserves a post's stored date across a draft-to-schedule
  conversion. Days 1–3 drafts still carried 2026-08-23 dates, so arming
  publishing would have blasted twelve backdated posts at once.
- Guidance: when removing a safety gate, identify what that gate was
  incidentally protecting against and replace it with a precise guard. Here:
  refuse to convert any draft whose stored date is in the past, and verify
  re-dating by read-back rather than trusting the provider's response.

## 2026-09-03 — Campaign variation belongs in validated data, not in new scripts

- Observation: every campaign had a bespoke queue script, so a campaign without
  one had no execution path. That is how a fully produced 4-drop launch reached
  delivery day with nothing queued.
- Guidance: one runner, one schema (`posting-schedule.json`), per-campaign
  overrides as data. Sending remains workstation-dependent until Postiz itself
  moves off the laptop — server cron alone cannot reach `127.0.0.1` on another
  machine. See `docs/marketing/CAMPAIGN_POSTING_ARCHITECTURE.md`.

## 2026-09-03 — A queued job is not a Site Studio handoff

- Observation: the former portal action queued a proof job and immediately told the customer that Site Studio was generating proofs, while the worker correctly rejects draft requests. The resulting record was neither a valid submitted brief nor a verified Studio packet, run, or callback.
- Guidance: gate proof work at the submitted brief boundary, expose the durable request/job/campaign state rather than naming an unverified provider action, and preserve owner review before customer proof visibility or notification.

## 2026-09-03 — Booking URLs should accept how customers actually paste them

- Observation: browser URL controls and direct server URL validation rejected a common pasted Booksy address without `https://`.
- Guidance: normalize a scheme-less public host to HTTPS on the client and server, but reject non-HTTP(S) schemes, credentials, malformed values, or implied account access. Help text must say that login, scraping, and booking-account changes are out of scope.

## 2026-08-31 — A provider receipt is not an account or project claim

- Observation: the Shay invitation has provider acceptance and a sent receipt,
  but its customer and website-request links remain empty by design.
- Guidance: do not treat a delivery receipt as an account, completed interview,
  or work authorization. Claim only after same-email verification.

## 2026-08-31 — Private React routes need an explicit shared-host rewrite

- Observation: a deployed deep-discovery page returned Apache 404 before the
  React application loaded because the shared GoDaddy document root admitted
  only pre-existing token route families.
- Guidance: add a narrow `/deep-dive/<id>` rewrite and route-contract test;
  do not create or mail a client invitation until the public path renders.

## 2026-08-31 — Container-injected services need a real container check

- Observation: PHP lint did not catch mismatched Drupal time and UUID interfaces
  in the invitation service; production failed safely before creating an invite.
- Guidance: type new services against the adjacent `@datetime.time` and `@uuid`
  component interfaces and retrieve the service before any customer command.

## 2026-08-31 — A production pop-up site needs a durable loop, not only more art

- Observation: a dramatic visual upgrade can win attention while leaving the
  merchant unable to change prices, publish the next stop, receive an inquiry,
  or retain a consented audience. Conversely, a generic CRUD console can make
  the experience operational while erasing the physical booth's recognition.
- Guidance: translate the booth into distinct reusable media jobs and native
  design components, then bind the changing facts to one owner-authenticated
  content model. Keep price text and events owner-authored, separate contact
  from marketing consent, store both durably, and leave mail, payment,
  inventory, delivery, calendar, and social publishing off until separately
  approved and proven.

## 2026-08-31 — Preserve a pop-up's recognition system while quarantining unsafe field details

- Observation: a real booth photograph can prove palette, material, category,
  and social identity while also showing children, bystanders, and contact text
  that conflicts with the owner's latest statement.
- Guidance: treat those facts as separate bindings. Preserve the recognizable
  physical cues in a reference-led visual system, but do not publish the raw
  image or choose between disputed email addresses. Use verified social
  destinations for the first conversion path, native/editable social graphics,
  and a local message builder that cannot send until the business approves an
  official contact and operating workflow.

## 2026-08-29 — Recommended values can make a proof concrete without becoming fake commerce

- Observation: a merchandise proof feels generic when it names categories but
  never helps the owner picture an offer. It becomes misleading when
  unconfirmed amounts are styled like live prices, sales, or inventory.
- Guidance: show owner-review values boldly enough to support a real sales
  conversation, repeat a plain demo-value disclosure at the hero and catalog,
  keep the amount editable in the owner prototype, and preserve the no-payment,
  no-inventory boundary until the merchant confirms price, stock, rights, and
  fulfillment. Use real owner photographs for product proof and generated art
  for atmosphere or campaign framing—not as evidence of inventory.

## 2026-08-29 — Release-cache retention is part of deployment correctness

- Observation: the host had ample filesystem capacity and inode headroom while
  the account still rejected dependency extraction at its user quota. Old
  private Git worktrees—not production assets—were the dominant footprint.
- Guidance: deployment preflight must consider account usage and per-release
  cache size, preserve the active and target releases, and remove only exact
  script-owned Git-reconstructable cache directories. A failed private build
  is not authority to bypass the release script or hand-edit production.

## 2026-08-29 — A flyer becomes more valuable when it opens a durable loop

- Observation: a strong handbill can still stop people while failing to give a
  traveling merchant a lasting identity, an updateable next stop, or a
  permission-based route back after the event.
- Guidance: treat the flyer as the first touch, not obsolete media. Pair it
  with one permanent link and QR; model feed, Story/Reel, and return content as
  separate reusable media slots; render readable messaging natively; and keep
  caption drafts local until the merchant approves the account, handle, facts,
  inventory, links, permissions, and publishing action.

## 2026-08-29 — A temporary table benefits from a permanent front door

- Observation: a pop-up merchant may change products, prices, and locations
  too quickly for a conventional fixed catalog, while the merchant's personal
  trust and sales experience remain durable.
- Guidance: make the first site a stable identity and event/interest layer:
  reusable category slots, a permanent QR, next-location fields, direct
  questions, optional marketing permission, and a phone-oriented owner view.
  Do not fabricate SKUs, inventory, prices, checkout, or confirmed events. Let
  the $199 foundation prove interest; add durable holds, inventory, messages,
  authentication, and analytics only as an explicitly scoped upgrade.

## 2026-08-28 — Preserve an approved direction before making a bolder fork

- Observation: an approved functional prototype can be valuable precisely
  because its composition is already working; overwriting it to answer a new
  request for more texture, typography, color, and operational detail removes
  the customer's ability to compare or return to the approved baseline.
- Guidance: freeze the approved route, storage key, and evidence; create a
  a creative brand look like an unformatted database table.
- Guidance: pair each expressive surface with durable operational control:
  an unauthenticated public view, an authenticated phone-friendly editor,
  consent capture, price bounds, flood protection, and clear scope.

## 2026-08-31 — An anonymous invitation must not bypass identity gates

- Observation: a raw deep-discovery link could easily be mistaken for a full
  portal account, but treating that address as a verified customer would create
  false ownership and unsafe automation.
- Guidance: Bind the invitation to the exact recipient email, keep its bearer
  secret out of URLs sent to servers, and wait for a same-email verification to
  create the account-owned draft. The draft asks for owner review before any
  Booksy change, payment display, proof work, customer mail, or deployment.

## 2026-08-31 — A local image dependency failure blocks runtime proof, not source proof
- Observation: Drupal Commerce requires PHP `bcmath`, but the local Docker
  image did not install it, so Composer stopped before the backend began.
- Guidance: Install required PHP extensions in both image stages, then repeat
  migration and token-scoped API checks. Do not label a syntax/build pass as a
  completed customer-account or email-delivery proof.

## 2026-08-28 — Website Bundles Require Unified Project Fulfillment, Not Disconnected Infrastructure Wizards
- Observation: Exposing managed cloud hosting and domain setup as a separate "My Products" infrastructure tab or multi-step wizard confused customers. Because hosting is provisioned automatically with the domain as part of the website package, fragmenting the mental model into unmanaged server panels led to confusion, and partial domain saves failed when stripped of their request context.
- Guidance: Unify website provisioning inside the project workspace (`/portal?tab=projects`). Model hosting and custom domain as an inclusive bundle benefit, provide inline domain selection (`new_domain` registration vs `existing_domain` DNS routing with 1-click copyable records), and ensure all partial request updates automatically merge default project metadata so domain saves never fail validation.

## 2026-08-28 — Client Portal Design DNA v1: Enforcing In-Portal Action Routing, Single Focus Glow, and Token Workspace Isolation
- Observation: When authenticated clients explore recommended studio add-ons or growth offers, linking to generic public `/contact` forms creates conversion drop-off and context fragmentation. Similarly, in token-scoped workspaces (`/portal/:token`), unconstrained brand links that route to `/` exit the private workspace and strand the user.
- Guidance: Establish an enforceable Client Portal Design DNA standard (`FAMTASTIC_CLIENT_PORTAL_DESIGN_DNA_V1`). Keep all product upgrade actions within the authenticated portal boundary routing directly to `/buy?sku=...` checkout or interactive modal workflows. Preserve token workspace URLs in brand navigation headers. Apply the strict "One Glow" rule (`box-shadow: 0 0 24px rgba(124,252,0,.35)`) to direct customer gaze to the single next best action, and validate all portal surfaces with automated guards (`validate-client-portal-design-dna.mjs`).

## 2026-08-28 — Modular Customer Portal Architecture with Governed AI Assistance Beats Monolithic Dashboards
- Observation: Housing 14+ customer lifecycle surfaces, guided multi-step brief wizards, proof review iframes, file management, and message threads inside a monolithic 500-line React component created code bloat and made individual subviews difficult to test, maintain, and evolve.
- Guidance: Structure the customer portal into dedicated modular sub-components under `components/portal/` coordinated by a single thin dashboard orchestrator. Keep the AI boundary explicit: Shay and AI assistants may summarize briefs, answer product questions, and draft support requests, but must never autonomously mutate accounts, alter billing, send messages, or approve deployments. Always maintain strict CSS containment guards (`.portal-app{overflow-x:clip}`, `.portal-grid > * { min-width: 0; }`, `.portal-conversation { overflow: hidden; }`) to guarantee flawless mobile viewport rendering.

## 2026-08-28 — Multi-Channel Social Operations Require Visual Day-by-Day Dispatch Grids
- Observation: Managing a multi-channel campaign across Facebook, YouTube, TikTok, Instagram, and X via raw SQL tables or disconnected JSON manifests leaves operators blind to what is actually going out on any given day, what visual artwork is attached, and which gates are open.
- Guidance: Unify campaign operations in a Daily Social Dispatch dashboard organized by Day (1 to 17) and Moment (Teach @ 08:00, Challenge @ 12:30, Prove @ 17:00, Invite @ 20:30) with embedded artwork previews (4x5 & 9x16), copy hooks, and 1-click batch gate approvals.

## 2026-08-28 — Bridge Decoupled Intakes to Native Drupal Views & Webforms Rather Than Re-Inventing Admin Surfaces
- Observation: Building custom SQL tables and standalone controllers for decoupled APIs works for headless throughput, but bypasses Drupal's native superpowers (Views exposed filters, Webform submissions audit, and built-in email handlers).
- Guidance: Always provide a `hook_views_data` (`.views.inc`) file for custom pipeline tables and bridge decoupled intake endpoints into Drupal's native `webform_submission` entity so operators can use standard administrative Views and Webform results.
## 2026-08-27 — Research must stay attached to the component decision

- Observation: a research-backed prompt can produce a strong page, but the
  reasoning disappears when only the rendered HTML survives. Another builder
  then sees styling without knowing which market fact, accessibility rule,
  primary study, or design judgment influenced the component.
- Guidance: freeze a source manifest and decision ledger beside the page recipe.
  Give every decision a stable ID, component scope, reason, source trail,
  confidence, and limitation; render the IDs on the proof and carry them into
  Site Studio as immutable context.

## 2026-08-27 — A reusable media family needs parent lineage and distinct jobs

- Observation: three near-duplicate images do not create a useful component
  library. A parent art direction becomes reusable when companion frames cover
  different content jobs while preserving the same visual world.
- Guidance: retain one premium parent plus separately generated environment,
  process, and result/detail companions. Hash the parent and every output,
  preserve exact prompts, keep readable UI native, and never invent an
  unreported model or price.

## 2026-08-27 — Reusable component evidence needs repository-wide doctrine

- Observation: a strong component proof can still be lost if only its niche
  implementation describes the rules; another builder may see the rendered
  page but miss stable identity, upgrade continuity, and the FAMtastic/Site
  Studio authority boundary.
- Guidance: keep one canonical page/component doctrine and link it from every
  agent entry point, Build DNA, and Site Studio handoff. Treat the niche registry
  as evidence, not the only definition of the system.

## 2026-08-27 — Sync is a reviewed operating step, not a background pull

- Observation: the component branch and `origin/main` each had five commits the
  other side did not contain, so continuing without a fetch would have hidden
  current Solution Finder, checkout, and proof-access work from the build lane.
- Guidance: fetch and inspect divergence at task start, before push, and before
  deploy. Reconcile incoming commits deliberately and rerun acceptance. Never
  auto-pull a dirty worktree or production document root, and never equate a
  pushed branch with deployed production.

## 2026-08-27 — One-page proof sections should be portable component instances

- Observation: generating several pages through one renderer reduces code
  duplication, but it does not by itself give the operator a component drawer,
  hide/reorder behavior, media slots, or a safe future multi-page upgrade.
- Guidance: give every page, section instance, component, field, and slot a
  stable source-defined ID. Treat a media replacement as a slot-binding change,
  not a new component, and test that every other byte remains frozen. When the
  starter grows, move the same component instance between page recipes rather
  than recreating it and risking a quality drop.

## 2026-08-27 — Comparison grids need aligned outer geometry

- Observation: a translated middle direction card looked accidentally
  misaligned even though the offset was intentional.
- Guidance: preserve a shared outer baseline for comparison cards and express
  direction-specific personality inside each card. Browser QA should compare
  the top and bottom coordinates, not only check for overflow.

## 2026-08-27 — Treat booking marketplaces as an acquisition channel, not the brand

- Observation: a beauty operator may reasonably keep Booksy for discovery while
  building an owned path for repeat booking, consent, reviews, referrals, and
  brand authority. Because Booksy's Boost fee applies to the first Boost visit
  and not later visits, the value of a direct-booking discount changes over the
  customer lifecycle.
- Guidance: run the transition as an evidence-led bridge. Keep Booksy available,
  make direct booking an honest customer choice, let the business approve any
  loyalty incentive, and measure whether it improves retention and ownership.
  Never scrape client data, copy platform reviews, or trade benefits for reviews.

## 2026-08-27 — Premium creative needs an options-and-finishing contract

- Observation: a single paid generation proves execution but not creative
  exploration or polish.
- Guidance: generate three materially different candidates for every premium
  visual position, select against the page's real composition, and finish the
  chosen asset. Preserve candidates, rationale, finished artifact, prompt,
  provider/model/cost truth, and hashes in Build DNA; keep page text native.

## 2026-08-27 — A low-cost starter should reveal the upgrade path, not lead with exclusions

- Observation: describing a proposed $19.99 renewal and dedicating a major
  sales section to what $199 “does not pretend to include” made the starter
  feel like a future bill and a list of missing features. That framing worked
  as an internal scope warning but weakened the customer value story.
- Guidance: lead with the smallest useful outcome, keep the canonical Web
  Basics hosting renewal at $9.99 monthly after the included year, and show
  optional upgrades as responses to observed business signals. A booking
  starter can link or embed an owner-controlled provider or use request-to-book
  without claiming that FAMtastic already operates a full scheduling backend.
  Keep provider credentials with the owner, validate external URLs and mobile
  behavior during setup, and preserve commercial truth in the contract and
  Build DNA instead of filling the sales page with fear-heavy exclusions.

This file records production behavior, deployment constraints, incident
findings, and operator guidance that should survive across agents and sessions.
Git-tracked documentation and deployment scripts remain the authoritative
source of truth.

## 2026-08-27 — Native design systems should own text; generated images should stay photographic

- Observation: a valid reference-led provider receipt did not stop Gemini from
  inventing poster words and interface overlays. The second photo-only prompt
  plus one targeted repair produced a clean 12-frame Booked & Branded series.
- Guidance: separate the agents by medium. Shape, Type, and Message directors
  define reusable native HTML/CSS composition; the image worker supplies only
  realistic photographic material and empty space. Inspect every output, record
  rejected attempts and their cost, and never promote generated text as UI or
  customer copy.

## 2026-08-27 — Customer-facing AI needs a named human-authority handoff

- Observation: Shay is useful as the business face when the email clearly names
  her as the FAMtastic Designs AI Business Concierge and immediately explains
  the boundary.
- Guidance: Shay may explain, gather, and coordinate. Fritz and the FAMtastic
  team retain pricing, scope, approval, payment, and launch decisions. Site
  Studio translations must preserve that boundary rather than silently giving
  a model business authority.

## 2026-08-27 — Product demos must separate visual proof from operational proof

- Observation: the four-business Booked & Branded showcase can accurately
  prove art direction, responsive layouts, email-to-room navigation, and the
  intended phone Booking Desk interaction model without claiming that booking,
  payments, QR handoff, or review storage are live.
- Guidance: keep the fictional disclosure visible on every route and carry the
  same boundary in Build DNA and release evidence. Treat the static showcase
  as a sales/product artifact; route any real prospect, account claim, email,
  or mutable workflow through the separately proven owner-gated CRM path.

## 2026-08-27 — Platform-only operators need a branded bridge, not an instant rip-and-replace

- Observation: the no-independent-site screen surfaced beauty and barber
  businesses whose booking-platform profiles are functional but visually and
  operationally separate from an owned brand experience. That is a distinct
  campaign and product hypothesis, not a generic “no website” diagnosis.
- Guidance: begin with a branded site and pluggable booking path. Keep the
  existing platform available during testing, use a bounded request-to-book
  Booking Desk for the $199 starter, and do not promise native live scheduling,
  payment custody, client migration, or review migration until each capability
  is separately implemented and proven.

## 2026-08-27 — First-site outreach must not use an existing-site cohort

- Observation: a review cohort selected for source-verification convenience
  contained businesses with independent websites. That may be valid research
  for a future redesign offer, but it contradicts the $199 first-site promise
  and would make the personalized proof and email premise untrustworthy.
- Guidance: verified-cold `first_site` seeds require a cited
  `confirmed_absent` observation and a blank `website_url`; reject every other
  status and any nonblank URL during validation and again at ingress. Freeze
  that campaign purpose with the cohort evidence. Keep existing-site
  redesign/upgrade targeting out of this lane until it has its own offer,
  copy, qualification, review, and approval contract. A rejected review is
  not an import or a send.

## 2026-08-27 — Security dependency releases preserve pilot locks

- Observation: the Entity API advisory required a locked Composer deployment,
  not a Drupal admin-panel update. The production release advanced core to
  11.4.5 and Entity API to 1.8.0 with no pending database update and a
  zero-advisory Composer audit.
- Guidance: validate and deploy the reviewed lock through the backend release
  script, then verify public routes and update status. Keep an active
  exact-dispatch pilot lock and its suspended broad scheduler state unchanged
  during security maintenance unless an owner separately authorizes a change.

## 2026-08-27 — Deployment evidence does not authorize a commercial send

- Observation: production now has the verified-cold schema, route shells,
  durable pilot lock, and exact legacy-campaign quarantine, but it has no
  imported pilot cohort, generated art receipt, staged room, recipient claim,
  or SMTP delivery receipt.
- Guidance: treat this as a technical foundation release only. Keep broad
  lifecycle/outbox paths locked and require the separate source validation,
  Build DNA, owner review, exact-ID approval, and explicit send decision
  before any recipient is contacted.

## 2026-08-27 — SSH is not an argv-preserving remote deployment transport

- Observation: optional empty pilot-confirmation values vanished when OpenSSH
  serialized the remote command, shifting later positional arguments and
  stopping a read-only deploy preflight before it could inspect the remote
  scheduler.
- Guidance: encode every remote deploy value as a nonempty shell-safe token,
  decode it inside the remote script, and make the local fixture mimic SSH's
  flattening. Keep deploy help text free of command substitutions as well.

## 2026-08-27 — Verified-cold is a private importer lane, not a generic callback flavor

- Observation: a generic local import can be structurally valid while still
  lacking the immutable Build DNA provenance required for a verified-cold
  public proof. A cold key also resembles the legacy unsubscribe key shape,
  so route choice—not token shape—must determine whether a mutation is legal.
- Guidance: reject verified-cold in the generic local command and generic
  service callback, then accept it only through the private exact-delivery
  importer transaction that validates the delivery/job/event/Build-DNA tuple.
  Test that a runtime-bound generic payload leaves variants, Build DNA, and
  delivery state unchanged, while non-cold local import remains compatible.
  Reject cold keys on the legacy GET unsubscribe route; only the one-click POST
  confirmation path may mutate consent. For update `8042`, validate nonempty
  cold identity fields and duplicates before any DDL, then restore canonical
  NOT NULL key fields and missing declared unique keys using Drupal's Schema
  API and a disposable MariaDB run.
## 2026-08-27 — Pre-promotion safety is a separate boundary from the durable lock

- Observation: an old live Drupal process can run before it has the newly
  deployed durable lock, and direct jobs/outbox/mail services can bypass a
  lifecycle-only guard. The cold-260 campaign may also have attributable
  proof, preparation, send, and generic-email rows beyond its original proof
  queue.
- Guidance: pilot preflight must prove broad lifecycle, Drupal cron, jobs-run,
  direct evaluator/worker schedulers, and already-running matching processes
  are absent. Only one explicitly marked
  byte-exact named line may be suspended with its matching confirmation; retain
  a mode-0600 backup and require an explicit end-pilot restore/reconciliation.
  Lock generic worker, campaign-mail, and shared-outbox boundaries while
  preserving the exact public-preview dispatch path. Quarantine only exact
  campaign-owned proof/mail rows, fail on active/unknown states, leave
  unattributable notification outbox rows for manual inventory, and require
  canonical public `/web` configuration before a customer-facing pilot.

## 2026-08-27 — A deploy-shell flag is not a cron safety boundary

- Observation: cPanel starts each scheduled `drush cron` and
  `famtastic:lifecycle-run` in a fresh shell. An environment variable passed to
  a deployment command can therefore disappear before the next scheduled run,
  allowing a shared general queue or outbox to resume during a supposedly
  exact-ID proof pilot. The historical cold-260 generic proof queue is an
  independent risk: a new lock does not remove already-queued jobs.
- Guidance: make a pilot lock durable Drupal configuration, with an
  environment value only as an additive emergency stop—not an override that
  can reopen it. Both scheduler routes must check the same lock before any
  protection, automation, outbox, SLA, or mail work. A governed pilot apply
  must suspend only marker-owned lifecycle lines, report unmarked `drush cron`
  lines, write the durable setting before promotion and verify it again after
  the new code is active, and record the state in the release receipt. Require the stale exact campaign
  queue to be zero; if quarantining is approved, do it only through the narrow
  exact command after promotion and preserve its receipt. Never let a due-date
  selector or a generic callback become a second send/import authority.

## 2026-08-27 — Proof-image prompts are byte-level evidence, not trim-safe copy

Observation: the local cohort builder deliberately ends generated art prompts
with a newline. A worker that uses `String(prompt).trim()` before its provider
request or SHA-256 receipt changes the evidence even though the visible prompt
looks the same, so the finalizer must correctly reject its receipt.

Guidance:

- Use `prompt.trim()` only to reject whitespace-only input. Preserve the
  original exact UTF-8 text for provider request serialization and the prompt
  hash. An adapter crossing JSON boundaries must prove the source prompt bytes
  round-trip before it writes an operator-only worker input.
- A public-preview image receipt is incomplete without one unique a/b/c
  direction and filename per planned prompt plus non-empty provider usage
  evidence. Validate that contract offline before any paid run, and retain the
  source worker provenance separately from a later provider-execution receipt.
- The local adapter/worker validation surface is not an image-generation,
  Build DNA registration, proof-import, production, or email authorization.
  Those gates remain separate even when the prompt and receipt hashes pass.

## 2026-08-27 — Public-preview continuation must isolate account signup from generic request automation

Observation: a public-preview recipient can share an email with a Prospect
that already has discovery notes. During user save, the ordinary insert helper
used to see that Prospect and create a submitted website request, two request
notifications, and a generic proof job before the recipient had verified the
account.

Guidance:

- Validate the signed continuation before saving the Drupal user and keep only
  an in-request intent so the hook can skip the automatic discovery path.
  Persist only the non-advancing preview signup event; the same-email preview
  gets a customer record only after token verification. Any deferred owner
  alert must state that verification completed.
- Preserve ordinary account behavior when the continuation is missing,
  malformed, revoked, expired, or bound to another email. A regression must
  prove both paths with local-only mail and a SQLite database before release.

## 2026-08-27 — Preview migration repair must validate legacy rows before DDL

Observation: the owner-gated preview migration's clean create was valid, but
the former existing-table fallback could fail late on a nonempty partial table:
Drupal 11 requires a schema definition when adding indexes, and a missing
`NOT NULL` column needs an explicit safe initial value before the Schema API
restores its constraint.

Guidance:

- Preflight missing, blank, NULL, or invalid required identity fields and
  future unique-key duplicates before changing a partial table. A migration
  may initialize an empty frozen email snapshot, but it must never invent a
  Prospect, public ID, or delivery key; stop before DDL and name the operator
  repair instead. Restore valid identity columns to their canonical NOT NULL
  definitions before adding uniqueness.
- Rehearse update `8041` with
  `backend/scripts/rehearse-preview-delivery-8041.php` against only the named
  disposable MariaDB database. PHP lint and a hand-written SQL sketch are not
  substitutes for Drupal's actual MySQL Schema API.

## 2026-08-27 — Receipt-backed proof art needs a portable asset contract

- A local proof bundle that merely says an image model was planned is not a
  receipt-backed proof. The finalizer requires a `verified_cold` cohort, the
  exact anonymous Safe/Medium/Ultra profile, every a/b/c direction, and a
  Gemini Flash Lite receipt whose selected result matches both the exact prompt
  artifact hash and supplied source-image hash/byte count.
- Do not base64 large hero artwork into proof HTML. Normalize the externally
  supplied PNG/JPEG locally to `assets/hero.webp`, record the individual asset
  hash and `relative_path: hero.webp` in each direction `assets.json` and the
  `famtastic.signed-proof-assets.v1` manifest, then let the canonical signed
  asset importer own protected serving. A local relative asset is not public
  delivery evidence by itself.
- The local serializer can form the exact callback `assets[]` objects
  (`asset_id`, `relative_path`, `media_type`, `base64`, `sha256`) without
  sending. It must not be mistaken for import or delivery: browser screenshots,
  independent visual/rights review, canonical Drupal import, owner approval,
  and transactional outbox remain separate gates.

## 2026-08-26 — Personalization requires corroborated evidence, not a raw lead row

Observation: a contact list alone cannot establish services, booking behavior,
pricing, policies, or a right to use a logo or image. A proof builder therefore
must not turn a row into a customer-facing claim by filling the gap with a
vertical template.

Guidance: the local Beauty / Hair / Braiding cohort preparation tool accepts
only an explicit mapped input with a source-backed fact and research teaser per
lead. It produces three distinct reusable directions, records unexecuted
Gemini-art, browser, visual-review, Drupal, promotion, and email stages as
gated in Build DNA, and redacts raw email from every artifact. A passing static
bundle proves only structural readiness for the existing promotion importer,
never customer delivery.

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

Production outcome: frontend release `c119338b` deployed successfully at
18:32:22Z. Both apex and `www` now hand valid shaped proof-share URLs to the
branded React state rather than Apache's generic 404. A live enabled share
resolved six anonymous directions; invalid signatures still return no data.

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
- Signed proof images are evidence, not public files: validate an explicit
  bounded asset list, store it below a denied asset subtree, freeze the
  SHA/MIME/path/size manifest, and serve it only through a current signed room
  controller that rehashes before read. A `verified_cold` room requires an
  asset per direction and matching Build DNA hashes; legacy assetless rooms
  remain compatible rather than silently bypassing the new quality gate.

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

## 2026-09-04 — blog draft completion + topic gap-finder

- Observation: the markdown→basic_html converter in
  `scripts/publish-blog-draft.py` (`markdown_to_basic_html`) only recognizes a
  numbered/bulleted list item written on a single physical line — a list item
  wrapped across multiple source lines (natural when hand-wrapping prose at
  ~80 chars) breaks the list into multiple single-item `<ol>`/`<ul>` blocks
  with the continuation text spilled into a stray `<p>`. Guidance: always
  write each list item as one unwrapped line in `draft.md`, however long, and
  verify by grepping the `--dry-run` output for `<li>` count matching the
  intended item count before trusting a draft is ready.
- Observation: production's bare `/jsonapi/...` path 404s; the real mount is
  `/web/jsonapi/...` (confirmed live via curl, matches
  `scripts/qa-content-links.py`'s existing `JSONAPI_BASE`). This is a
  **read-only API mount**, distinct from the separate, unrelated rule that
  `/web/...` is never a valid frontend *page* route — the two facts look
  contradictory at a glance but aren't: one is a backend API URL prefix that
  happens to work correctly, the other is a page URL prefix that never does.
  `scripts/suggest-next-blog-topic.py` reuses the same working endpoint.
## 2026-09-04 — systemic `/web/` canonical-URL defect on every blog_post

- Observation: every published `blog_post` node's canonical meta tag
  resolved to `https://famtasticdesigns.com/web/blog/<slug>` — Drupal's own
  backend document-root prefix — instead of the real public frontend route
  `https://famtasticdesigns.com/blog/<slug>`. Confirmed live via JSON:API
  across multiple posts (both a recent one and an older one), so this was
  systemic across the corpus, not a one-off bad node.
- Root cause: `metatag.metatag_defaults.node` (the one shared metatag default
  covering every node bundle — no `node__blog_post` override exists) sets
  `canonical_url: '[node:url]'`. Production Drupal answers every request
  under `/web` (this is genuinely where Drupal's own document root is
  reached publicly — see `famtastic_pipeline.settings:public_api_base_url`,
  which is literally `{frontend_base_url}/web`). Any Drupal-generated node
  URL token, absolute or relative, therefore carries `/web` baked in, because
  that really is Drupal's own base path. The frontend renders the same
  content one path segment up, at the bare origin, so the token-generated
  "canonical" URL pointed at a route that 404s publicly.
- Fix: `famtastic_pipeline_metatags_alter()` (`hook_metatags_alter()`) in
  `backend/web/modules/custom/famtastic_pipeline/famtastic_pipeline.module`
  rewrites the resolved `canonical_url` tag for any node, stripping a literal
  `/web` prefix and rebuilding it against
  `famtastic_pipeline.settings:frontend_base_url`. This hook is invoked
  identically by classic page rendering and by the JSON:API `metatag`
  computed field (`Drupal\metatag\Plugin\Field\MetatagEntityFieldItemList`),
  so a single change fixes the tag everywhere it is emitted, for every node
  bundle, present and future — no per-node field edit, no metatag config
  edit, and no seed-script change needed.
- Guidance: when a Drupal absolute/relative URL token looks wrong on a site
  where Drupal itself is reachable at a URL prefix (like `/web`) distinct
  from where the public frontend renders the same content, suspect the
  token first, not the stored data — check `metatag.metatag_defaults.*`
  before touching individual nodes. `hook_metatags_alter()` is the right
  place to correct it centrally since it fires for both page rendering and
  the JSON:API computed metatag field.
- Guidance: `backend/config/famtastic-content-series.json`'s "posts" array is
  the original 80-article content plan; as of this date all 80 are confirmed
  live via JSON:API. The `marketing/blog/drafts/` folder is a separate,
  ad-hoc mechanism for campaign-driven posts outside that plan (the gmail/
  linktree post, the 5 add-on-explainer posts) — a draft folder's slug not
  appearing in the plan is expected and not a bug.
