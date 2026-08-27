# Product changelog

## 2026-08-27 — Booked & Branded founding-pilot proposal (draft only)

- Captured a proposed $199 founding package for solo barbers, braiders,
  stylists, and adjacent appointment businesses whose current public path is a
  booking-platform profile. The proposal includes a custom mobile website, a
  deliberately small phone Booking Desk, request-to-book, business-owned
  payment/QR handoff, fresh moderated reviews, and a Booksy-compatible bridge
  rather than an unsupported feature-parity promise.
- Defined the recommended founding boundary: five warm pilot operators, one
  operator/location, up to 12 services, request-to-book rather than unproven
  real-time scheduling, no payment custody, no platform scraping, and no
  multi-staff/POS/SMS behavior. Proposed pricing and renewal remain
  recommendations; no SKU, recurring charge, catalog entry, proof, or outreach
  was created.
- Added a three-email owner-gated outreach concept and a post-registration
  Booking Independence Plan. No campaign recipient, public page, or email was
  published or sent.

## 2026-08-27 — Drupal AI-powered Solution Finder and Project Intake Advisor

- Connected Drupal AI (`ai.provider`) to the React frontend with dedicated REST endpoints (`/api/v1/ai/solution-advisor` and `/api/v1/ai/brief-synthesizer`).
- Built `AiSolutionAdvisorService` to evaluate plain-English customer requests against FAMtastic's 16-SKU package ladder ($199–$6,999) with zero-downtime deterministic fallback.
- Upgraded `SolutionFinder.jsx` with an interactive AI consultation mode, real-time scope analysis, recommended sitemaps, included features, and direct 1-click package checkout.

## 2026-08-27 — FAMtastic Operations backend route fixes, manifest sync, and batch approvals

- Replaced raw `Url::fromUserInput` calls in `OperationsController` with proper route references (`commerce.admin_commerce`, `system.admin_content`, `entity.famtastic_prospect.edit_form`) to eliminate 404s under Drupal's `/web` base path in production.
- Added dynamic Postiz URL resolution via `PostizChannelsService::baseUrl()`, reading `Settings::get('famtastic_postiz_base_url')` instead of hardcoded `127.0.0.1:4007`.
- Added `SocialRecordSyncForm` (`/admin/famtastic/social-records/sync`) to allow in-admin 1-click sync of campaign manifest moments into `famtastic_social_record` while preserving database gate decisions.
- Added `SocialRecordBatchGateForm` (`/admin/famtastic/social-record/batch/{day}/{gate}/{direction}`) enabling 1-click batch approval of all moments and gates for any campaign day.

## 2026-08-27 — First-site verified-cold cohort guard (local-only; not deployed)

- The verified-cold seed now defaults to the explicit `first_site` campaign
  profile and rejects every lead unless its source-backed website observation
  is exactly `confirmed_absent` and its `website_url` is blank. A corroborated
  existing website, including `verified_present`, cannot enter even a dry run,
  Prospect import, proof delivery, or commercial-email path for the $199
  first-site campaign.
- The ingress repeats that check before its write boundary and freezes the
  campaign profile in the immutable cohort snapshot, audit event, and dry-run
  report. Existing-site redesign/upgrade outreach remains a separate,
  unimplemented campaign decision rather than a fallback interpretation of
  the first-site offer.
- Focused validator coverage and the local verified-cold fixture cover the
  defaulted profile, explicit first-site profile, nonblank URL rejection, and
  `verified_present`/other-status rejection. No cohort was imported, no
  provider was called, no proof was generated, and no email or production
  change occurred.

## 2026-08-27 — Drupal security maintenance deployed

- Production now runs backend release `aad97433f88e6f0a2724c556d0bdc9b4f820710b`.
  Drupal core moved from 11.4.4 to 11.4.5 and the enabled Entity API module
  moved from 1.6.0 to 1.8.0, resolving SA-CONTRIB-2026-113 / CVE-2026-81158.
- The governed dependency deployment completed with a fresh code, dependency,
  and database backup. Production Composer audit reports zero advisories and
  Drupal reports no pending database updates.
- The verified-cold pilot remains protected: the durable exact-dispatch lock is
  still `1`, no broad scheduler was re-enabled, and no proof, customer, or
  commercial email was sent by this maintenance release.

## 2026-08-27 — Verified-cold pilot foundation deployed (no outreach sent)

- Production now runs `d5435a19` for the owner-gated public-preview and
  verified-cold foundation. Updates 8041–8043 completed; the frontend also
  carries bounded React rewrites for signed preview rooms and customer
  `/login`, `/verify-email`, and `/reset-password` links.
- The release entered exact-pilot mode: broad lifecycle dispatch is durably
  locked, only its marker-owned cron was suspended, and the exact historical
  cold-260 job queue was quarantined (242 claimable jobs to zero). No generic
  notification outbox was guessed at or cancelled.
- Technical production proof is limited to routing, migration, lock, and
  quarantine evidence. No new cohort import, Gemini call, proof delivery, or
  customer/commercial email occurred.

## 2026-08-27 — Pilot deploy preflight argument preservation (local acceptance only; not deployed)

- The governed backend deployer now transports optional pilot confirmations as
  nonempty, shell-safe tokens before it invokes the remote script. OpenSSH
  flattens a remote command into shell text and otherwise drops blank
  positional values, which can shift the required arguments and fail before
  scheduler inspection. The local scheduler fixture now emulates that behavior.
- The deployment help path is side-effect free: documentation text no longer
  executes command substitutions when an operator asks for `--help`.

## 2026-08-27 — Verified-cold import and legacy-unsubscribe containment (local acceptance only; not deployed)

- `famtastic:proof-local-import` now rejects an exact runtime-bound
  `verified_cold` campaign before callback processing. The only allowed cold
  completion path is `famtastic:verified-cold-proof-import`, whose service
  operation rechecks the immutable delivery/job/event/Build-DNA tuple and
  records Build DNA plus the callback within one database transaction. The
  generic service callback independently fails closed for cold, so a future
  in-process caller cannot bypass the private importer.
- The fresh SQLite acceptance fixture supplied a syntactically valid a/b/c
  cold callback with signed-media-shaped assets and exact ingress IDs. The
  generic command and direct generic service call both rejected it with no
  proof variants, Build DNA rows, or delivery-state mutation; a separate
  ordinary local a/b/c import still completed successfully.
- The historical GET unsubscribe endpoint now rejects a
  `verified_cold_preview` key without changing its message or consent record.
  The cold confirmation POST remains the only mutating cold lane, while
  historical non-cold GET unsubscribe behavior remains compatible.
- Update `8042` now preflights populated cold cohort/ingress tables for
  missing required identity fields and NULL, blank, or duplicate declared
  `cohort_key`/`ingress_key` values before any DDL, then restores their
  canonical NOT NULL field definitions and missing declared unique keys through
  Drupal 11's Schema API. Disposable MariaDB
  rehearsal passed clean partial-table repair, duplicate insert rejection,
  and no-cold-DDL failures for malformed historical identity data.
## 2026-08-27 — Pre-promotion pilot scheduler and legacy-mail guard (source-only; not deployed)

- A governed exact-ID pilot now refuses active broad `drush cron`,
  `famtastic:lifecycle-run`, `famtastic:jobs-run`/`fjr`, and direct
  `php:eval`/`php:script`/`ev` or AutomationWorker scheduler entries before
  old production code can be promoted; it also refuses a matching in-flight
  owned process and repeats that assertion immediately before the code swap. An
  unmarked scheduler is never removed.
  Only one deliberately marker-owned, byte-exact lifecycle, Drupal-cron, or
  jobs-run pair with its exact repeated confirmation may be suspended; a
  mode-0600 backup is retained and there is no automatic restore on success or
  failure.
- Pilot preflight now requires Drupal's actual customer-facing configuration to
  be exactly `https://famtasticdesigns.com` and
  `https://famtasticdesigns.com/web`. It refuses localhost, staging, blank, or
  alternative same-origin bases rather than changing live configuration during
  deployment.
- The durable pilot lock also closes direct `famtastic:jobs-run`, raw
  `AutomationWorker::run()`, generic `CampaignMessageService::send()`, and
  shared `LifecycleOperationsService::dispatchNotifications()` paths. The
  owner-approved exact public-preview dispatcher remains separate; portal/auth
  mail is not globally disabled.
- The exact `cold-260-aug-2026` quarantine now inventories and fail-closes on
  active/unknown `proof.generate`, `outreach.prepare`, `outreach.send`, and
  campaign-owned generic email work. Its private receipt reports IDs and
  type/status counts. It intentionally leaves unattributable notification
  outbox rows untouched for a later manual inventory.
- Local shell fixtures passed for mismatch refusal, unmarked scheduler and
  in-flight-process refusal, noncanonical-base refusal, active generic-email
  refusal, read-only preflight, suspension before code promotion, mode-0600
  backup, and no automatic restore. No production scheduler, config, queue,
  code, email, proof, or deployment state was changed.

## 2026-08-27 — Verified-cold commercial-send safety gates (local acceptance only; not deployed)

- Verified-cold tracked click and unsubscribe URLs now use the canonical public
  Drupal document root (`https://famtasticdesigns.com/web/api/...`), with a
  same-origin `/web` API-base validation rather than assuming the SPA root can
  serve Drupal routes. A malformed or missing signed-room destination on a
  verified-cold click now returns a private 404; it cannot fall through to the
  legacy prospect-token flow.
- Exact-ID public-preview dispatch preflights every verified-cold set before
  changing a held outbox row. Default SMTP configuration denies the send;
  local memory rehearsal needs its own explicit test-only gate, while real
  SMTP needs both `FAMTASTIC_ALLOW_REAL_OUTREACH=true` and
  `FAMTASTIC_ALLOW_VERIFIED_COLD_REAL_OUTREACH=true`. No production email was
  sent by this change.
- Verified-cold staging now requires a customer-safe research teaser, cited
  source summary, and exact Build DNA research artifact. A shared public
  preview content guard redacts copied email addresses, phone numbers, and
  common credential-shaped values from cold source evidence and research
  before any builder packet, room snapshot, or invitation body can use them.
- Focused PHPUnit coverage (50 tests) and the fresh local verified-cold
  handoff fixture passed. The fixture proves redacted public evidence and no
  provider, SMTP, public-share, payment, deployment, or production action.

## 2026-08-27 — Exact-ID pilot runtime and legacy-queue safety lock (local acceptance only; not deployed)

- `FAMTASTIC_PILOT_EXACT_DISPATCH_ONLY=1` is now persisted as the durable
  `famtastic_pipeline.settings.pilot_exact_dispatch_only` switch during a
  governed pilot apply, then read by every fresh Drupal process. Both
  `hook_cron` and `famtastic:lifecycle-run` stop before protection, general
  automation, general outbox dispatch, or SLA mail when that switch (or the
  additive emergency environment lock) is active. A normal governed release
  explicitly clears and verifies the durable switch only after its code,
  update, and cache checks succeed.
- Pilot preflight reports active broad `drush cron` entries as well as the
  marker-owned lifecycle runner. It never edits an unmarked Drupal cron line;
  the verified runtime lock is the authority for that path. The release record
  captures durable-lock state, prior state, observed Drupal-cron count, and
  lifecycle scheduler evidence.
- A pilot now fails before release if the historical `cold-260-aug-2026`
  generic proof queue is nonzero. It can quarantine only that exact queue when
  both explicit campaign and repeated-confirmation environment values match;
  the narrow command runs only after the new module, dependencies, updates,
  cache, and durable lock are active, writes a private receipt, and rechecks
  the queue before release recording. Nothing is quarantined implicitly.
- The generic Site Studio HTTP callback now returns a private-import-required
  response for an explicitly declared or campaign-inferred `verified_cold`
  lane. Those artifacts must instead use the private exact-delivery Build DNA
  importer. Dynamic due-record scheduled cold release is disabled; its command
  may list only, while an execute token fails closed and exact owner-confirmed
  preview IDs remain the sole delivery boundary.
- `scripts/e2e-pilot-exact-dispatch-lock.sh` passed in a fresh SQLite Drupal
  sandbox with memory-only mail. It proves durable-config and fresh-env cron
  locks, normal behavior after both locks are off, declared and inferred
  callback rejection, and scheduled-release refusal. No SMTP, provider,
  customer, proof, deployment, or production state was used.

## 2026-08-27 — Exact-prompt Gemini Flash Lite cohort bridge (local acceptance only; not deployed)

- Imported the previously proven Gemini Flash Lite image worker with its source
  commit, Git blob, and SHA-256 provenance recorded beside the new worker.
  The verified-cold cohort adapter now writes one operator-only a/b/c worker
  input per canonically bound lead, preserving the exact prompt bytes and
  matching prompt SHA-256 rather than normalizing trailing whitespace.
- The worker has offline input and receipt validation modes that reject missing
  or duplicate directions/filenames, a changed prompt hash, absent provider
  usage evidence, and incomplete result sets. A fixture builds/binds one local
  cohort, validates the adapter output, and proves that the existing finalizer
  accepts the same prompt SHA only when it matches the source prompt file.
- This was synthetic local validation only. No macOS Keychain read, Gemini
  request, paid image generation, Drupal/Site Studio write, import, proof,
  production deployment, scheduler, or email action occurred.

## 2026-08-27 — Exact-ID preview deployment scheduler gate (not deployed)

- The backend deployer now supports `FAMTASTIC_PILOT_EXACT_DISPATCH_ONLY=1`.
  In that narrow public-preview mode, both preflight and apply refuse an active
  broad `famtastic:lifecycle-run` scheduler and never install one; exact
  owner-confirmed preview mail remains the only permitted delivery lane.
- When the known marked scheduler is already active, the separately explicit
  `FAMTASTIC_PILOT_SUSPEND_MARKED_LIFECYCLE_CRON=1` flag validates one exact
  marker/command pair, saves the full crontab under the private deployment
  directory, removes only that pair during apply, and refuses duplicate,
  unmarked, or altered lifecycle commands. Normal non-pilot deployments retain
  their existing scheduler behavior.
- A failed backend promotion now restores the prior `famtastic_customer` theme
  alongside the module and admin theme; successful releases remove its
  temporary prior-theme directory. This keeps public proof and portal UI paired
  with the restored backend code.
- This was source-only validation: no scheduler, production code, database,
  proof, lead, provider, or email state was changed.

## 2026-08-27 — Public-preview registration isolation (local acceptance only; not deployed)

- A valid signed public-preview continuation is now validated before the Drupal
  user-save hook runs. That request-scoped handoff prevents inherited Prospect
  discovery notes from automatically creating a submitted website request,
  queued request notifications, or a generic website-proof job before the
  recipient verifies the email.
- The continuation is recorded only as a non-advancing preview signup event.
  The exact same-email delivery remains unclaimed until verification; after
  the one-time token is consumed it is claimed idempotently and a truthful
  verified-registration owner alert is queued. No public proof selection, request,
  pricing, checkout, or customer email is added by the claim.
- Ordinary registrations without a valid continuation retain the existing
  discovery-to-request, customer/staff notification, and generic proof-job
  behavior. The preview-registration isolation test passed against a fresh
  local SQLite Drupal install and memory-only transactional mail, proving both
  lanes. No deployment, import, provider call, proof generation, outbox
  dispatch, or customer email occurred.
## 2026-08-27 — Canonical cold-proof Build DNA binding (local acceptance only; not deployed)

- Added a local-only, immutable per-lead runtime-binding contract for the Beauty / Hair / Braiding proof cohort. A prepared bundle is now explicitly non-importable until canonical ingress supplies the exact Drupal Prospect ID, Proof Campaign ID, public campaign ID, job ID, callback event ID, and recorded start time; the binder never creates or guesses those values.
- Binding writes a checksummed `runtime-binding.json`, replaces local placeholder manifest IDs, injects the complete `build-dna.run` projection expected by Drupal telemetry/public-preview staging, and rehashes the Build DNA artifact ledger. The finalizer and signed-asset callback serializer both reject absent, replayed, mismatched, or local-placeholder bindings.
- The local finalizer now accepts the receipt shape emitted by the existing authenticated Gemini Flash Lite worker (`famtastic.gemini-flash-lite-image-receipt.v1`) without a second image route or provider call. Its missing per-image start timestamp remains an honest partial timing record rather than a fabricated value.
- Added executable builder, runtime-binding, and finalizer tests for non-importable fixtures, no-mutation dry runs, exact ID/callback retention, receipt compatibility, Build DNA hash validation, and immutable replay rejection. No provider, Drupal, import, production, customer, or mail action occurred.

## 2026-08-27 — Public-preview migration 8041 rehearsal hardening (not deployed)

- Corrected the Drupal 11 `Schema::addIndex()` invocation in update `8041` and made its existing-table branch preflight all missing required fields and identity duplicates before any DDL. A recoverable partial table can receive a missing empty frozen-email snapshot; an unmappable Prospect/public ID/delivery key or duplicate identity now fails closed with an actionable update error.
- Hardened the same repair branch against present-but-invalid legacy identity values: blank or NULL public IDs/delivery keys and non-positive Prospect IDs now fail before any DDL, and valid existing identity columns are restored to canonical NOT NULL definitions before uniqueness is added.
- Added `backend/scripts/rehearse-preview-delivery-8041.php`, a guarded disposable-MariaDB rehearsal that proves clean creation, safe nonempty partial-table completion, and no-mutation failure for missing ownership or duplicate IDs. The observed production table is absent, so production will take the separately proven clean-create path.
- The rehearsal now also proves the malformed-identity case fails closed. It passed against disposable MariaDB 10.11.19 through Drupal's real MySQL Schema API.
- No migration was applied to production, no lead was imported, no proof was generated, and no email was sent. The normal backend release still requires current `main`, production preflight, `drush updb`, and schema inspection.

## 2026-08-27 — Signed proof-media contract (local acceptance only; not deployed)

- Added an optional, bounded `variants[].assets[]` callback contract for proof
  imagery. Every asset is validated as an explicitly named JPEG, PNG, WebP, or
  AVIF byte payload with a safe relative path, matching extension/MIME/magic
  bytes, exact SHA-256, per-file/per-direction limits, and no directory walk.
- The callback writes only validated bytes under a protected proof asset subtree
  and derives the byte-free `design_dna.asset_manifest` from the saved files.
  Base64 is never persisted in proof metadata.
- Signed public concept rooms freeze the normalized asset manifest alongside the
  HTML snapshot. The image controller checks the current signed share, frozen
  direction/profile, path, size, and SHA-256 on every read; stale, revoked, or
  tampered assets fail closed. Stored HTML is not rewritten: an asset-bearing
  response receives a signed proof-level `<base>` only at read time.
- The `verified_cold` Build DNA lane now requires at least one frozen signed
  asset per direction and every asset SHA in the immutable Build DNA manifest.
  Existing assetless, non-`verified_cold` rooms remain compatible.
- `scripts/e2e-signed-proof-assets.sh` passed in a fresh local SQLite sandbox:
  it covers malformed input, path/hash/MIME/magic rejection, protected signed
  delivery, relative `assets/...` resolution, tamper detection, revoke, and
  legacy assetless compatibility. No provider, SMTP, customer, payment,
  deployment, import, or production state was touched.

## 2026-08-26 — Owner-gated public preview delivery release candidate (not deployed)

- Forward-ported the signed, read-only three-concept room and same-email verified-account claim onto current `main`, including an industry-neutral public room, immutable per-delivery context/research/artifact snapshots, customer-only research retrieval, and redacted/allowlisted anonymous build context.
- Added migration `8041` for preview delivery records without reusing the stale feature branch’s colliding migration number. Staging now fails closed unless the registered Build DNA record matches the exact Prospect, proof campaign, public campaign ID, served proof hashes, and (when used) research artifact hash/role.
- Owner approval creates a **held** outbox record. The new `famtastic:preview-delivery-dispatch` command accepts only an exact confirmed list of one to ten held delivery IDs; it never runs the broad lifecycle dispatcher. A separate exact-campaign quarantine command can remove only the historical generic queued proof jobs for `cold-260-aug-2026`, with a reason hash and ledger events.
- Public and registered-request jobs now bind distinct proof campaigns before remote dispatch, so neither path can borrow another campaign for the same Prospect. Remote-dispatch retries preserve that campaign's idempotency key; callback retries complete artifact protection/owner routing. This release does not claim the future automatic six-direction registered-refinement family.
- The current XLSX/cold importer is deliberately **not** connected: it lacks verified source/recipient eligibility and the required personalized public-intake/research snapshot. A production-like MySQL migration validation is still required for the `8041` existing-table upgrade path.
- No migration was applied, no lead was imported, no proof was generated, no email was sent, and no production deployment occurred in this release-candidate worktree. The existing direct public-request SMTP acknowledgment and cold-email campaign compliance integration remain separate follow-up work.

## 2026-08-27 — Receipt-backed local proof finalizer added

- Added a deliberately local-only finalizer for Beauty / Hair / Braiding proof
  cohorts. It accepts only the `verified_cold` source lane and the current
  `anonymous_safe_medium_ultra_v1` Safe/Medium/Ultra package, requires all
  three directions, validates supplied Gemini Flash Lite image receipts against
  the exact generated prompt hashes and source-image hashes, and never invokes
  Gemini, Drupal, production, promotion, or email.
- The finalizer normalizes supplied PNG/JPEG heroes with local `cwebp` into
  portable `assets/hero.webp` files, replaces the generated SVG fallback, and
  emits `famtastic.signed-proof-assets.v1` stored manifests plus a local
  serializer for the canonical callback `assets[]` wire shape. Per-asset
  hashes, normalized receipt evidence, finalization report, QA, and Build DNA
  are recorded without claiming browser, owner, or customer delivery proof.
- Added an executable dry-run/fixture contract test that proves no mutation on
  dry run, rejects mismatched image receipts and a wrong source lane, validates
  final Build DNA hashes, asserts a linked WebP hero for each direction, and
  verifies the no-send callback-asset serialization shape.

## 2026-08-26 — Local Beauty / Hair / Braiding proof cohort preparation added

- Added a local-only first-ten cohort builder under
  website-delivery-swarm/cohorts/beauty-hair-braiding. It accepts an explicit
  operator-mapped JSON or CSV input rather than reading a lead spreadsheet,
  rejects records without source-backed research evidence, and produces
  exactly three distinct Safe, Medium FAMtastic, and Ultra FAMtastic
  self-contained proof directions per lead.
- Every prepared bundle carries redacted intake, research evidence, three
  Gemini Flash Lite Image prompt artifacts, static QA, promotion-readiness
  gates, a promotion-contract-compatible manifest, and a Build DNA skeleton
  with real local artifact hashes. The builder does not call a model, write
  Drupal, publish, or send email; it records those steps as open gates instead
  of manufacturing delivery evidence.
- Added synthetic JSON and CSV fixtures plus an executable local contract test
  that validates Build DNA, contact-data redaction, callback size/safety rules,
  and the existing proof-promotion dry-run contract.

## 2026-08-26 — Customer verification links work from a fresh browser

Added a deliberately narrow Apache SPA rewrite for `/verify-email`, `/login`,
and `/reset-password`. Account emails now reach the existing React verification
screen on a fresh mobile browser instead of Apache returning 404 before the
token handler can run. The rewrite does not create a broad SPA catch-all, so
Drupal, static campaigns, and existing proof-room boundaries remain isolated.

## 2026-08-26 — Unlisted proof-room route correction deployed

- Corrected the frontend Apache routing contract for dynamic, signed
  `/proofs/share/<request>/<signature>` rooms with a deliberately narrow React
  shell rewrite. Drupal, generated SEO shells, and static campaign experiences
  retain their existing routes; React can now validate the unlisted token rather
  than Apache returning a missing-path 404 first.
- Added the parallel `/proofs/preview/...` rule for the future preview lane,
  without treating that undeployed feature as live.
- Extended `scripts/e2e-frontend-route-shells.sh` to fail if either protected
  proof-room rule is removed or a broad SPA catch-all is introduced. The
  frontend deploy primitive now backs up and byte-verifies root `.htaccess` so
  this route-level change is rollback-safe. Production inspection showed the
  signed API endpoint returned 200 anonymously while the frontend share route
  returned Apache's bare 404; this is a route-shell defect, not a revoked-link
  or account-ownership defect.
- Frontend release `c119338b043a3ab907773344bccedcf3081387de` deployed at
  2026-08-26T18:32:22Z with rollback archive
  `~/backups/famtastic-frontend-20260826T183200Z-c119338b043a3ab907773344bccedcf3081387de.tgz`.
  The server verified the deployed `.htaccess` exactly. On both apex and `www`,
  a live signed room now returns 200, its anonymous API resolves six directions
  (`a` through `f`), and first/last signed proof pages return 200. An invalid
  signature remains a no-data 404. Browser checks verified the branded
  unavailable-proof state instead of Apache's generic 404, with no console
  errors.

## 2026-08-26 — Channel-health card live in production; four-day retrospective published

- Postiz channel-health card wired into production: API key minted via
  `/api/user/api-key/rotate`, `famtastic_postiz_api_key` + base URL written to
  prod `settings.local.php` (backup taken first); drush-verified — all five
  channels report connected. Dependency recorded in SYSTEMS.md: prod reaches
  Postiz through the Mac's ngrok tunnel, so the card errors when the Mac is
  down. (c2473ee3)
- `docs/RETROSPECTIVE-2026-08-22-25.md` published: day-by-day arc, CEO
  performance review with four shortfall→fix pairs, 14-item pitfall catalog
  each with a prevention, continuity model, and an ordered queue of next work
  (content verdict, channel binding, colima resize, Wave 0→1, secret rotation,
  /terms + /privacy, asset-factory plan). (693e3ae1)
- CHANGELOG entries added retroactively for both commits by the CEO heartbeat
  run per the doc-sync standing rule; no other surfaces moved (capability
  evidence levels unchanged — connection-proven ≠ publish-proven still holds).

## 2026-08-25 (late) — All five social channels connected

- X connected via OAuth 1.0a consumer keys (Postiz's X provider is 1.0a, not
  OAuth 2.0 — the 2.0 client creds are unused). Token has no expiry (2058
  placeholder), refresh present.
- YouTube connected via OAuth client on the `FAMtastic Site Studio` Google
  project (YouTube Data API v3 enabled, testing mode, owner as test user).
  Live `channels?mine=true` call returned HTTP 200. Access token auto-refreshes.
- TikTok runs on SANDBOX credentials (Production keys cannot initiate Login
  Kit pre-approval): tokens expire ~daily, re-auth required until app audit;
  posts may be self-only in unaudited mode.
- Diagnosed en route: Postiz OAuth handshake states expire after 60 minutes
  (stale links cause "Invalid state"); Google token-exchange failures surface
  as generic "Authentication failed" — validate credentials with a probe call
  to oauth2.googleapis.com/token (invalid_client = bad secret).
- Known issue opened: postiz orchestrator process OOM-killed (exit 137) inside
  the 3GB colima VM — scheduled-publishing worker may be affected; colima
  memory resize queued for next session (requires container restart, owner OK).

## 2026-08-25 — Heartbeat 14:43Z: C6 provenance lead via git stash (CEO, read-only)

- Found the provenance Fritz's C6 ruling was missing: `git stash@{0}` on branch
  `codex/shay-website-delivery-swarm` (created 2026-08-23 05:37 −0400, message
  "wip: abandoned preview runner refactor before PIT delivery") contains the
  near-complete preview-runner refactor — `services.yml` registers
  `preview_runner_client` → `FamtasticPreviewRunnerClient`, `routing.yml` adds
  `/api/pipeline/preview-runner/callback` → `PreviewRunnerCallbackController::handle`,
  `SiteStudioProofClient` deleted, e2e renamed to
  `e2e-preview-runner-callback.sh` (17 files). Stash predates the hidden on-disk
  copies (mtimes 08-24 17:35) and does not contain them. LEAD_TO_LAUNCH C6 row +
  change log updated with the two ruling options (delete stash+files vs resume
  stashed branch through normal review). Inspection strictly read-only; nothing
  restored, deleted, or committed from the stash.
- Verification sweep green this run: `campaign-readiness.py` READY/GATED PASS;
  `bash -n` clean on both deploy scripts; catalog drift-guard eval blocks intact
  (deploy-backend :334/:337/:343); tree clean before ledger edits.

## 2026-08-25 — Worker-late fix verified + publish executor built (fam-ops)

- Assignment 1 verification: the worker-late grace-window fix
  (`f623fdab`, contained in prod `aece5778+`) judges liveness off
  `last_finished` + 1800s grace (`LifecycleOperationsService.php:199-214`),
  not raw `next_due`. Guard `scripts/e2e-worker-late-guard.sh` PASS locally:
  stale alerted once, mid-run clean, second sweep idempotent. Receipt:
  `.artifacts/lifecycle-runs/1787659129-62485/evidence.json`.
- Publish executor `backend/scripts/publish-executor.php`: converts
  owner-approved Postiz drafts to schedule IN PLACE (`PUT /posts/{id}/status`,
  keeps id/content/media/date), verifies QUEUE state by fresh read-back,
  writes per-run evidence to `.artifacts/publish-executor/<run>/`. Hard double
  gate in code (`FAMTASTIC_MARKETING_PUBLISH=true` AND
  `--i-have-owner-publish-approval`) — all three refusal paths proven. Missing
  drafts adopted by utm_content or marked BLOCKED; rerun-safe
  (`already_scheduled` path). Schema: social-record columns
  `postiz_scheduled_id`/`provider_state`/`published_at` + index via update
  hook **8038**.
- Local validation against local Postiz v2.22.1 with synthetic records dated
  2099 (cannot fire on live integrations): 4 conversions verified, idempotency
  case proven, teardown leaves zero residue in Postiz and DB; real days 1–3
  drafts untouched (all 68 records still `approval_publish=0`). Evidence:
  `.artifacts/publish-executor/20260825T124006Z-4950/evidence.json`.
- New recipe `docs/playbook/RECIPES/AUTOMATION_RELIABILITY.md` (@fam-ops):
  race-fix receipt, executor runbook + remaining prod-run gates (owner
  bounded-batch approval + prod Postiz env keys), laptop-bound inventory,
  local Postiz failure modes. SOCIAL_POSTING steps 4–5 marked mechanism-ready.
- Not done by design: no deploy, no prod Postiz contact.

## 2026-08-25 — Revision loop step 9 complete + R1 renewal-charging research (fam-commerce)

- LEAD_TO_LAUNCH step 9 (revision requests & re-proof loop) completed locally:
  new immutable `famtastic_proof_version` history table (update hook **8036**).
  Every delivered proof set records a version row via
  `CustomerPortalService::recordProofVersion` (v1 `initial`; re-proofs carry
  `source=revision`, revision number, and notes snapshot); prior rows are never
  mutated or deleted; re-delivering the same campaign is idempotent.
- Revision add-on fulfillment (`FulfillmentService::fulfillRevisionAddOn`) now
  queues an owner operational alert and a customer transactional receipt via
  the existing outbox (`revision_addon:{order}:staff-sale|customer-receipt`);
  both deliver through the memory transport.
- New validator `scripts/e2e-revision-loop.sh`: proves request → allowance-gate
  402 → stub-gateway checkout → webhook-path payment → revision_limit 1→2 →
  owner notified → re-proof lands as v2 with v1 byte-identical. 15/15 checks,
  two consecutive runs; evidence `.artifacts/revision-loop/`.
- R1 research delivered: `docs/audits/R1-RENEWAL-CHARGING-RESEARCH.md` —
  commerce_stripe **2.2.1** already supports off-session renewals (SetupIntent
  `usage=off_session`, confirmed off-session PaymentIntents in `createPayment`,
  SCA soft-decline taxonomy); recommendation: self-hosted cron + off-session
  PaymentIntents over Stripe Billing; Fritz-gated rollout steps recorded.
- Scaffold `backend/scripts/renewals-cron.php`: finds hosting entitlements due
  in 7 days, dry-run by default, can materialize DRAFT `approval_required`
  renewal orders under a double gate (`--create-drafts` +
  `FAMTASTIC_RENEWALS_CRON_ACK=local_scaffold`). Contains no payment calls;
  idempotent per entitlement+cycle via ledger event key; not scheduled.
- Local only; no deploy; no live charges. Committed 30bbd17f + 24a54b9f.

## 2026-08-25 — UTM attribution persisted end to end, locally (fam-growth)

- Backend: new `AttributionService` snapshots `utm_source/medium/campaign/content/term`,
  `gclid`, `fbclid` (+ capture route/time) into one JSON field
  (`famtastic_prospect.utm_json`) at lead creation on both capture paths:
  `PublicRequestController::capture` (query string + JSON body; body wins) and
  portal `CustomerPortalService::createWebsiteRequest`. Update hook **8037**
  adds the prospect field and `famtastic_social_record.leads_count` via the
  standard schema API (MySQL-safe; 8036 was already taken by proof-version
  history).
- Social join: a lead whose `utm_content` matches a social record's
  `content_id` increments that record's `leads_count` by exactly one.
- Admin view: Marketing Command Center → Leads & attribution now renders a
  content-grain table (Content ID | Day | Leads | Requests | Paid revenue)
  joined live from `famtastic_social_record.content_id` ↔ prospect
  attribution snapshots (match resolved in PHP for MySQL/SQLite portability);
  the campaign/source-grain table is retained below it.
- Frontend: shared `collectUtmParams()` in `frontend/src/api/pipeline.js`
  forwards landing-page params on every capture call — Solution Finder quote,
  v1 contact form, and portal website-request form.
- Validator: `scripts/validate-utm-attribution.sh` proves query-carried and
  body-carried persistence, exact +1 counter behavior, portal-path service
  semantics, and cleans up all synthetic rows. PASS ×2;
  evidence `.artifacts/utm-attribution/1787660247` and `.artifacts/utm-attribution/1787660595`.
  All touched PHP `php -l` clean; frontend build green.
- Local only — prod effect lands with the next approved backend deploy;
  GA4 purchase event coverage remains open (LEAD_TO_LAUNCH C7).

## 2026-08-25 (heartbeat 08:17Z) — C6 escalation + ledger hygiene (no code changes)

- C6 re-verified with ignore-bypassed census: the "dead" preview-runner stack
  (`PreviewRunnerCallbackController`, `FamtasticPreviewRunnerClient`, router
  fixture) is present on disk but was never git-tracked and is hidden from
  status via `.git/info/exclude`; mtimes post-date the audit; both imported
  services still do not exist, zero routes/callers. Flagged for Fritz ruling
  (delete vs complete); nothing touched per provenance rule.
  `docs/playbook/RECIPES/LEAD_TO_LAUNCH.md` C6 row + change log updated.
- New lesson in `docs/SITE_LEARNINGS.md`: `.git/info/exclude` blinds standard
  clean-tree sweeps; orientation now checks it explicitly.
- Fixed HEARTBEAT.md defect: the 06:13Z entry had been concatenated onto the
  01:53Z line (missing newline), leaving one malformed mega-line.

## 2026-08-25 — Worker-late race fix + audit ledger corrections (heartbeat)

- `LifecycleOperationsService::runProtection()` no longer flags a worker late
  merely because `next_due` passed: the monitor now also requires no completed
  run within `WORKER_LATE_GRACE_SECONDS` (1800s). Root cause of 237 false
  "Automation worker late" alerts (237 of first 267 outbox sends) was a race
  between sibling every-5-minute crontab lines sharing one cadence — see
  `docs/audits/CEO-FULL-REVIEW-2026-08-24.md` gap #4. New regression harness
  `scripts/e2e-worker-late-guard.sh` proves stale-alerts/mid-run-silent/
  idempotent semantics; unified lifecycle validator re-run green. Local only —
  prod effect lands with the next approved backend deploy.
- New permanent remediation section in `docs/playbook/RECIPES/LEAD_TO_LAUNCH.md`
  for the CEO Full Review gaps (C4–C7), cross-referenced to R1–R4 where they
  overlap; R1 row corrected to record that prod already contains all 16 SKUs
  (verified in the audit) with an open receipt-trail question for Fritz.
- `docs/playbook/MASTER-PLAN.md` current-state table refreshed against
  verified reality (16 sellable SKUs, 80 live posts, 32 prod prospects, Phase
  A/B support status, campaign media-ready-but-gated) replacing the stale
  2026-08-22 snapshot.

## 2026-08-24 — Domain-verification runbook rewritten to proven methods (heartbeat)

- `docs/playbook/RUNBOOKS/instagram-standalone-onboarding.md`: TikTok domain
  verification now documents three methods ranked by what actually worked —
  DNS TXT (preferred; proven live on apex + www), verification file (deployed;
  requires the trailing-slash htaccess rule), meta tag. Every claim in the edit
  was independently verified before adoption: DNS TXT answers on both
  hostnames (`tiktok-developers-site-verification=Yul3…`), the htaccess rule
  exists at `frontend/public/.htaccess:23`, and prod serves the `jUD1…` file
  with HTTP 200. Two live artifacts is expected (see SITE_LEARNINGS same day);
  no tokens or secrets introduced.

## 2026-08-24 — Portal projects-flow redesign + portal crawl validator (@fam-admin-cx)

- Redesigned the customer portal projects intake for conversion (owner screenshot
  complaint: hero button → ~60-field wall of textareas → jumbled request list).
  Step 1 now asks only request name, build type, and goal; saving the draft
  reveals the full interview grouped into six labeled fieldsets with a sticky
  save bar. Every input name is unchanged — backend contract untouched. Build
  green; not deployed (operator lane). Evidence:
  `.artifacts/admin-cx/2026-08-24/` (before/after rendered captures, crawl JSON).
- Added `scripts/e2e-portal-links.sh` (+ `frontend/e2e/portal-links.crawl.mjs`):
  authenticated local crawler for the whole customer portal — seeds a controlled
  test customer via `backend/scripts/provision-e2e-customer.php`, walks every
  reachable section plus the `?start=website` flow and `/portal/:token`, and
  asserts per surface: render OK, no fake-affordance bold/arrow labels outside
  real anchors, no synthetic strings in customer-visible content, notices do not
  survive navigation, no horizontal overflow past the viewport marker, and the
  portal.css overflow guards exist. Idempotent and self-cleaning.
- Prod hygiene sweep (read-only SSH SELECTs) across `famtastic_portal_thread`,
  `famtastic_portal_message`, `famtastic_project_request`, and
  `famtastic_prospect`: one synthetic-marked row found — prospect id 7
  (`FAMtastic v3 Demo Proof`, `demo-proof-v3@example.test`, source
  `owner-acceptance`). It is Fritz's own acceptance-demo record; deletion or
  rename needs an owner-approved script. Threads, messages, and requests are clean.

## 2026-08-24 — Social publishing channels live; Postiz estate hardened

- Connected Instagram `@famtasticdesigns` via the `instagram-standalone`
  provider (Meta "API with Instagram Login" product). Token live and
  refresh-capable through 2026-10-21; BUSINESS account confirmed via
  `graph.instagram.com/me`. Facebook Page connection untouched.
- Replaced the ephemeral trycloudflare tunnel with a permanent ngrok static
  domain (`designate-vacation-shadiness.ngrok-free.dev`) after diagnosing the
  recurring Postiz login spinner: auth cookie is hostname-bound and OAuth
  callbacks require stable HTTPS.
- Added `scripts/restart-postiz-tunnel.sh`: one-command recovery that rotates
  nothing (static domain), rebuilds the env file with secret preservation,
  recreates only the postiz container, rewrites absolute Media paths to the
  current public URL (fixed all 56 broken-image rows), and verifies health.
- Added `docs/SYSTEMS.md` (systems inventory with health checks and known
  quirks) and
  `docs/playbook/RUNBOOKS/instagram-standalone-onboarding.md` (7-gate client
  onboarding procedure proven end-to-end).
- Playbook expanded earlier this window: MASTER-PLAN (five tracks), recipes
  for autonomous customer service / social posting / blog factory / product
  pipeline / pricing strategy, fam-ceo + workforce agents, campaign receipts.

## 2026-08-21 — Preview-provider doctrine and Antigravity boundary

- Declared the role-specific preview route: Gemini 3.7 Flash through an
  authenticated Antigravity bridge for reasoning/build work, Gemini Flash Lite
  Image for economical 1K proof art, explicit premium image escalation, and an
  independent final reviewer.
- Recorded the Antigravity desktop bridge as a discovered, local-attended
  candidate rather than an autonomous provider. It cannot become selectable
  until it produces a structured execution receipt, survives a clean-session
  retry, and demonstrates its declared fallback.
- Required all agents to use `website_proof.generate.v1`, provider preflight,
  and Build DNA rather than chat-only mockups or unrecorded model sessions.

## 2026-08-22 — Stateful Gemini Image interaction benchmark

- Added a project-shared, pinned marketing specialist core from
  `coreyhaines31/marketingskills`: product context, CRO, signup/onboarding,
  popups, RevOps, ad creative, marketing ideas/loops, sales enablement, AI SEO,
  analytics/experimentation, social, site architecture, schema, and offer
  design. The adoption manifest records source commit and per-skill hashes;
  FAMtastic doctrine, capability truth, and approval gates remain authoritative.
- Installed Google's `gemini-interactions-api` reference skill in the shared
  project-agent location and recorded its GEAP credential boundary separately
  from the existing Gemini Developer API image worker.
- Proved a two-step Gemini 3.1 Flash Lite Image Developer API interaction from
  the FAMU-adjacent visual canon: one new 16:9 reference-led scene in 6.096
  seconds and one 9:16 stateful companion revision in 5.028 seconds.
- Persisted interaction IDs, verbatim prompts, usage metadata, response and
  image hashes, receipt, and valid Build DNA under local artifact storage. The
  benchmark made no customer, Drupal, Site Studio, notification, or production
  mutation and did not run an independent creative-release review.

## 2026-08-20 — Clearer proof review, readable notifications, and media routing

- Replaced raw-JSON owner email presentation with a compact decision-ready
  intake summary and a safe responsive transactional email wrapper. The exact
  plain-text body remains in the outbox/test record; HTML is presentation only.
- Added a deterministic six-concept review hub to the preview runner: every
  proof now explains that it is six separate homepage concepts, gives a
  three-step compare/shortlist flow, exposes visual thumbnails, and asks for
  one or two favorites. Browser QA now rejects a hub that lacks the guide or
  does not link exactly to all six directions.
- Added a mobile Operations-mode notice for the custom Drupal staff surfaces.
  It keeps phone use focused on triage and record review while honestly warning
  that dense editors and wide record tables remain desktop-safe work.
- Added the media routing policy and updated the capability map: HyperFrames
  is the installed designed-motion lane, MoneyPrinterTurbo is a draft-only
  narrative-video candidate, and ACI AI remains an unverified image-volume
  candidate pending terms/API/rights/quota/quality evidence.
- Recorded the marketing split decision: only `marketing/engine/` is portable
  today; campaigns, FAMtastic brand data, Drupal/customer truth, evidence, and
  publishing adapters remain in this repository until explicit extraction gates
  pass.

## 2026-08-20 — Build DNA and low-cost Gemini Flash Lite image proof

- Added the versioned `famtastic.build-dna.v1` contract, checksum validator,
  searchable Drupal ledger projection commands, and common agent operating
  rule. Build DNA captures real stage/model/provider status, prompt/input/output
  lineage, cost/timing status, reviewers, artifacts, and Site Studio continuity
  without creating a second workflow engine.
- Recorded the complete Build DNA for the reference-led Gemini Flash Lite story:
  an inherited premium visual canon, five new 1K outputs, four distinct support
  scenes, prompts/usage receipts, expected USD 0.168 new-image cost, static
  source, responsive browser evidence, and open independent-review boundary.
- Added the provider-proven Gemini Flash Lite reference-led image sequence to
  the capability map. It establishes a low-cost route to benchmark further; it
  does not yet certify an invoice, customer release, Site Studio execution, or
  unattended independent visual quality.

## 2026-08-19 — Lean social-presence quality baseline

- Separated the `AND IF IT IS?` audience experience from its Lab DNA case
  study. The public interactive microsite now lives at
  `https://famtasticdesigns.com/and-if-it-is/`; the Lab remains the process,
  evidence, timing, QA, and conversion companion at `/lab/and-if-it-is/`.
- Upgraded Rattler Roll Call from a prototype preview to a working device-local
  generate, persist, reload, copy, and native-share/copy-fallback interaction.
  Production Playwright proof covers desktop, phone, all three social cards,
  metadata, disclosures, the Lab return link, and zero browser errors.
- Published the public FAMtastic Lab case study at
  `https://famtasticdesigns.com/lab/and-if-it-is/` through an isolated,
  allowlisted, atomic static lane, then anonymously verified desktop and phone
  rendering, assets, disclosures, live-experience links, and attributed intake.
- Added promotion metadata, structured data, GA4 `page_view` and `cta_clicked`
  events, PII-free campaign attribution, and a visible boundary between the
  marketing demand engine and core customer/Site Studio state.
- Added a machine-readable adjustable run blueprint, quality-and-speed
  contract, post-run latency review, live publication evidence, and a guarded
  two-review release budget. Provider resume now skips repair/re-review when
  the existing independent verdict already passes.
- Separated the provider-neutral `social_presence.generate.v1` production
  process from the `AND IF IT IS?` golden example, including its input/output
  contracts, nine-stage flow, capability routing, evidence package, time and
  cost discipline, retention rules, and FAMtastic Lab productization path.
- Added the `AND IF IT IS?` unofficial Rattler Lifers campaign as a one-direction social-presence baseline with one responsive hub, two original 2K graphics, The Lifer character system, three editable HTML social cards, and six governed draft content records.
- Preserved the verbatim brief, sourced research, exact prompts, provider/model/cost ledger, image-routing alternatives, desktop/mobile/social screenshots, self-review boundary, hashes, and one-command verifier.
- Recorded the 13-minute-03-second paid-generation-to-QA window, 315-credit OpenArt `gpt-image-2` cost, two first-pass image results, and one targeted overflow repair.
- Added a provider-neutral image-routing contract distinguishing OpenArt transport from the GPT Image 2 model and documenting direct OpenAI Image API, Responses API, managed, and alternate-model routes without claiming untested equivalence.
- Added a guarded atomic publisher for campaign-owned static proofs and verified the live unlisted URL anonymously at desktop and phone widths with loaded images, zero overflow, and no browser errors.
- Kept all social account, OAuth, scheduling, posting, and engagement behavior disabled behind explicit content, media, and publish approvals.

## 2026-08-17 — Public lead-to-member website proof funnel

- Repositioned Solution Finder as the short public lead-capture and starter-recommendation experience rather than the full design-proof intake.
- Added a Drupal-generated continuation URL and transactional acknowledgement explaining that a free account unlocks the detailed brief and working website demos.
- Prefilled the registration email and business name, preserved same-email Prospect and Intake claiming, and opened the detailed portal website request immediately after sign-in.
- Extended the authenticated Drupal website-request model and portal form for business model, research context, likes/dislikes, existing technology, domain fallback, business email, and unlisted custom needs.
- Added desktop and mobile browser proof for the anonymous lead → registration hook → detailed portal intake journey.

## 2026-08-17 — Website delivery swarm proof engine

- Added a provider-neutral `website.preview.v2` deterministic reference runner.
- Added specialist/provider registries, versioned brief and trace schemas, three intake fixtures, package/add-on reasoning, independent QA, and Playwright screenshot evidence.
- Added the callable `run-website-delivery-swarm` repository skill and scale-out implementation record.
- Added a reusable `human-experience-tester` specialist and callable skill with neutral control mode, opt-in Life Path lenses for 1–9/11/22/33, master-number calculation, protected-decision guardrails, and unit coverage for Life Paths 3 and 33.
- Added the first customer-specific artifact pilot with Safe/Wild/OMG barbershop proofs, generated hero media, desktop/mobile screenshots, explicit approval/build automation, payment-boundary stop, Gmail self-delivery, and local-versus-premium model benchmarking.
## 2026-08-12 — Campaign publishing proof and video evaluation

- Proved branded Facebook Page photo publishing and founder-profile sharing
  with account-specific copy and provider evidence.
- Corrected campaign destinations from a React fallback route to the canonical
  `/55-cents-a-day-website` experience with stable campaign UTMs.
- Added route-specific campaign-link validation so HTTP success alone cannot
  pass marketing preflight.
- Confirmed two 15-second vertical campaign videos, including an audio-enabled
  Remotion master, and initiated one controlled HeyGen presenter-video
  comparison on the connected free plan.
- Expanded the capability registry and capability-to-revenue strategy for
  campaign strategy, branded creative, short-form video, social publishing,
  marketing command centers, tutorials, and future service packaging.
- Added a controlled Adobe Firefly avatar-and-B-roll production brief with an
  offer-safe script, assembly instructions, and a common HeyGen/Firefly/Remotion
  evaluation scorecard.

## 2026-08-12 — Hybrid marketing production foundation

- Documented the lowest-cost credible production flow for the 17-day Web
  Basics campaign, including local, Poe, HeyGen, scheduling, email, analytics,
  approval, and platform-verification boundaries.
- Installed Ollama, Qwen3 8B, and FFmpeg locally for no-per-call drafting and
  dependable media encoding on the rebuilt 16 GB Apple Silicon workstation.
- Added a fail-closed marketing preflight and a generated 68-record campaign
  manifest with stable content IDs, UTM values, approvals, and evidence fields.
- Recorded why Kimi K2, LivePortrait, MuseTalk, Postiz, and HeyGen are optional
  or gated rather than silently treating every open repository as commercially
  deployable on this computer.
- Added GLM4 9B as a local multilingual/challenger model, Gemma 3 4B as the
  local vision lane, a fail-closed task router, and shared Shay/Claude/Codex
  rules distinguishing local execution from cloud invoked through a local CLI.
- Accepted the incubate-then-extract architecture, added a portable engine
  schema boundary and replaceable FAMtastic brand configuration, and created a
  fail-closed campaign readiness audit covering 68 records, UTMs, approvals,
  local tools, local models, and shared agent contracts.
## 2026-08-18 — Revocable unlisted proof rooms

- Added private-by-default, owner-controlled share links for approved three- or
  six-concept website proof sets.
- Added a branded anonymous proof room that exposes working previews only;
  selection, revisions, purchase, and all account or intake data remain behind
  the authenticated portal.
- Made links server-signed, non-indexable, non-cacheable, and immediately
  revocable, with separate controls to turn sharing off or replace an existing
  link.
- Suppressed analytics on unlisted review routes and added defensive path
  redaction so request UUIDs and share signatures never enter page-view data.
- Added customer-portal and staff-review controls plus acceptance coverage for
  ownership, anonymous privacy, rotation, revocation, and mobile rendering.

## 2026-08-18 — Account-safe proof email deep links

- Changed proof-ready notifications from a generic portal link to an exact
  request-scoped Projects link and told customers to use the same email address
  that received the notification.
- Made an unselected ready proof set the portal's immediate next action, so the
  older generic portal link still opens the concepts for the correct account.
- Added an explicit signed-in-account mismatch warning, visible account email,
  exact request highlighting, and desktop/mobile acceptance coverage instead
  of allowing a valid proof link to appear empty in the wrong workspace.

## 2026-08-17 — Owner-gated website proofs, reliable alerts, and private grants

- Versioned the complete `website_proof.generate.v1` standard and upgraded the
  account intake to `website_discovery_v3` with a 0-10 FAMtastic scale,
  structured color/emotion inputs, private visual references, and recorded AI
  enrichment choice.
- Connected submitted website requests to an exactly-three Safe/Wild/OMG proof
  job, a visible staff review surface, authenticated customer previews,
  selection/revision decisions, and an explicit owner-controlled email gate.
- Added an explicit owner-gated FAMtastic showcase pack that appends three
  original high-intensity working sites for a six-concept customer review.
- Decoupled lifecycle automation and notification dispatch from mailbox ingest,
  added worker/queue-age visibility, corrected the operational alert inbox, and
  made customer registration generate a staff alert.
- Added hashed grant-code classes, account/request/SKU scope, atomic redemption,
  a staff administration surface, and real zero-dollar Commerce fulfillment.
- Added private PNG/JPEG/WebP/PDF request assets with ownership, AI-use consent,
  MIME, size, checksum, and account ownership controls.

## 2026-08-12 — 17-day campaign and independent QA-agent contracts

- Added a 17-day, four-content-moment campaign plan covering 68 core pieces,
  platform adaptation, video formats, publishing stages, measurement, and
  approval boundaries.
- Defined independent Content QA and SEO/Discovery QA release contracts for
  product descriptions, articles, scripts, social copy, and rendered media.
- Recorded a hybrid video/distribution recommendation: selective HeyGen avatar
  explainers, reusable Remotion motion graphics, and a controlled scheduler
  pilot rather than unreviewed direct auto-publishing.

## 2026-08-12 — CMS-neutral editorial library and route scroll reset

- Rebuilt the 72 general-interest demand articles around distinct reader-decision
  lenses and removed the repeated Drupal/React implementation paragraph.
- Made customer-facing CMS guidance platform-neutral: FAMtastic recommends a
  hosted builder, general-purpose CMS, commerce platform, headless CMS, or
  custom application according to fit rather than promoting one default.
- Added validation that rejects CMS-biased boilerplate and long paragraphs
  reused across more than three non-campaign articles.
- Added client-side route scroll restoration so links open new pages at the top
  while preserving intentional in-page anchors.

## 2026-08-11 — 55 Cents a Day editorial and visual correction

- Rewrote all eight campaign posts after a scope audit found generic platform
  language that incorrectly implied systems beyond the $199 Web Basics offer.
- Added real buyer objections, concrete small-business examples, explicit
  ecommerce and custom-system boundaries, and a clear explanation of how an
  absent website can create a verification and trust gap.
- Added properly dated and qualified original-research findings from
  BrightLocal's 2025 U.S. consumer panel and Verisign's historical 2015 U.S.
  survey; no revenue-loss or guaranteed-outcome statistic was invented.
- Replaced general technology visuals with campaign-specific character,
  objection, trust, and 55-cent value graphics.

## 2026-08-11 — Package ladder naming and scope clarification

- Aligned the $199 and $499 page names with their canonical Commerce products:
  Web Basics Bundle and Business Website Bundle.
- Renamed the higher-scope offers so their value boundaries are visible:
  Custom Website, Business Growth System, Premium Website + AI System, Campaign
  Landing Page System, and Website Care & Maintenance.
- Clarified that the $1,499 Campaign Landing Page System includes campaign
  strategy, attribution, conversion measurement, routing, and follow-up and is
  not a duplicate of the $199 first-business-website offer.
- Removed stale 48-hour and AI-optimized promises from the Web Basics page.
- Added an idempotent Drupal package normalizer to the canonical deployment
  lane so package naming cannot drift between repository and production.
- Added two relevant, branded in-body campaign visuals to every article in the
  eight-part 55 Cents a Day series, in addition to each article header image.

## 2026-08-11 — $199 affordability campaign and complete package education

- Added the dedicated `/55-cents-a-day-website` campaign experience around
  “Cost is not one of them. Period.” with honest annualized math, scope,
  domain, hosting, renewal, fit, intake, and launch explanations.
- Expanded the demand library from 64 to 80 published articles across ten
  connected series, including eight package guides and eight $199 Web Basics
  education articles with 40 supporting FAQs total.
- Added four original black, charcoal, and lime campaign visual concepts,
  applied the real FAMtastic mark in presentation, and reused the images across
  the campaign articles where relevant.
- Added Drupal-backed related education to every service and package page.
- Stopped rendering unsupported legacy seed testimonials; only explicitly
  reviewed proof fields can now appear on service pages.
- Added correct `/about` metadata, Organization/WebSite structured data, the
  campaign route to SEO discovery, and `lastmod` dates to generated sitemaps.

## 2026-08-11 — Blogs label and production SEO baseline

- Changed the customer-facing section label from Blog/Insights to Blogs while
  preserving the established `/blog` URL and canonical paths.
- Added a production SEO audit covering 85 sitemap URLs, rendered/raw metadata,
  schema, crawlability, security, mobile/desktop Lighthouse, content quality,
  local visibility, and a prioritized remediation sequence.

## 2026-08-11 — Complete article imagery and Drupal-owned navigation

- Extended the branded series visual system to all 64 published articles so
  every blog card and article has a consistent, relevant visual treatment.
- Made Drupal's Main navigation order, labels, and top-level visibility the
  source of truth for both desktop and mobile React navigation.
- Preserved enhanced service and package dropdowns while positioning them at
  the locations configured by Drupal; the production menu places Home first
  and About second.

## 2026-08-11 — Public blog pagination repair

- Fixed the frontend JSON:API client to follow Drupal's absolute pagination
  links without duplicating the production `/web` base path.
- Restored all 64 published articles on the anonymous `/blog` listing and
  direct article routes.
- Rebuilt the frontend and reran SEO discovery acceptance before deployment.

## 2026-08-11 — Branded demand library publication

- Recorded Fritz's explicit approval to publish all 64 demand-library articles
  and 32 supporting FAQs while leaving price and promotional-send gates closed.
- Added eight original FAMtastic visual concepts, optimized them to responsive
  WebP assets, and applied them selectively to 32 articles.
- Added the real FAMtastic mark, descriptive image alternatives, branded
  captions, mobile-safe image treatment, card imagery, and Article image schema.
- Verified 64 cards, 32 illustrated cards, branded article presentation, image
  schema, and zero horizontal overflow at a 375 CSS-pixel mobile viewport.

## 2026-08-11 — Evidence-led demand engine

- Added one authoritative workflow that turns proven FAMtastic capabilities
  into ordered content series, reusable FAQs, controlled taxonomy, contextual
  CTAs, internal links, SEO metadata, and Drupal drafts.
- Expanded the eight-topic pilot into eight full pillar-and-spoke series with
  64 complete article drafts, 32 canonical FAQs, 67,100 article words, five
  customer-job categories, and fourteen controlled tags.
- Added per-article primary and secondary keywords, intent, template, audience,
  source records, evidence boundary, Open Graph data, canonical URL, schema
  declarations, review state, validated word count, and reciprocal link plans.
- Added idempotent Drupal seeding and validation with a fail-closed broad-
  publication gate; generated content remains unpublished until approved.
- Added mobile blog categories, tags, series navigation, related FAQs,
  contextual CTAs, canonical metadata, and article structured data.
- Installed a repository-owned demand skill and 31 pinned specialist skills
  for Codex, Claude, and Shay, with a repeatable shared installer and doctrine.
- Browser-proved the hub and pillar article at mobile width with no horizontal
  overflow, and verified a second seed produces no duplicate content.
- Corrected light node-preview panels and low-contrast field/meta text across
  the branded Drupal admin theme rather than patching one blog page.

## 2026-08-10 — Needs-led intake, $499 lifecycle, and private pricing

- Replaced the “new website means $199” shortcut with an exhaustive, versioned
  discovery interview and explainable package recommendation.
- Added the $499 Business Website Bundle, business hosting renewal, SKU-driven
  entitlements, and two-round project delivery contract.
- Added staff-administered, account/request-scoped private offers that preserve
  list price, customer price, reason, expiry, ownership, and accepted order.
- Added a common agent operating contract for Codex, Claude, Shay, and future
  CLIs plus an evidence-classified FAMtastic capability registry.
- Expanded synthetic acceptance to prove needs-led $199/$499 routing, ecommerce
  review gating, and an account-scoped $499-to-$199 private-price order.
- Browser-verified the 41-control discovery form at a 390×844 mobile viewport
  with no document overflow.

## 2026-08-08 — Drupal operations experience

- Replaced the campaign-only `/admin/famtastic` landing page with a task-based
  Operations Home for Analytics, customers, Commerce, support, content,
  services, referrals, and campaigns.
- Kept Website Analytics and Campaign Operations as distinct dashboards while
  making both immediately discoverable from the staff home.
- Added staff records for customer support conversations, referrals, and active
  service entitlements.
- Reworked custom Operations styling for the dark FAMtastic admin theme,
  removing mismatched white metric cards and improving responsive navigation.
- Sanitized personalized proof routes and sensitive query parameters before
  sending page paths or locations to Google Analytics.

## 2026-08-07 — Customer operating hub

- Replaced the fixed mobile portal navigation with an expandable, grouped
  hamburger drawer designed for an evolving service catalog.
- Added Drupal-backed personalized learning and FAQ surfaces populated from
  published Blog Post and FAQ content.
- Added durable customer topic subscriptions, educational-email choices,
  analytics digest frequency, and separate deals/promotions consent controls.
- Verified and corrected the Drupal merge write path so preference changes
  persist successfully under the production database driver.
- Added service-aware support entry points, searchable FAQs, activity/value
  history, owned-service cards, and evidence-based growth recommendations.
- Added privacy-safe customer referrals with permission confirmation, hashed
  referred-email storage, lifecycle history, and reward-ready status.
- Added customer-facing Google Analytics access, profile/team separation, and
  secure billing explanations.

## 2026-08-07 — Portal mobile and workflow QA

- Contained the customer workspace at phone and tablet widths so its horizontal
  navigation no longer widens the document beyond the viewport.
- Added 44-pixel touch targets, single-column mobile panels, compact workspace
  chrome, long-content wrapping, and accessible navigation state.
- Completed customer message-thread reading and replying with actionable error
  and busy states.
- Replaced prompt-based password recovery with an accessible inline form and
  labelled all customer account fields.
- Reject unknown organization workspace identifiers instead of silently
  returning another workspace available to the signed-in customer.

## 2026-08-07 — Customer lifecycle foundation

- Replaced permanent project-link login with a branded customer account model.
- Added verified customer identities, individual/business workspaces,
  memberships, resource ownership, entitlements, activity, and project/support
  conversations.
- Added customer registration, verification, sign-in, sign-out, recovery,
  profile, workspace, and message APIs backed by Drupal sessions.
- Added the full React customer workspace navigation for projects, purchases,
  services, domains/hosting, support, team, account preferences, contextual
  offers, and entitled analytics.
- Preserved prospect-token routes as a temporary pre-sale and compatibility
  path.
- Added Commerce catalog seeds for the $199 Foot in the Door product, $9.99
  monthly hosting renewal, and configurable Growth Analytics entitlement.
- Connected verified pipeline payments to customer-owned orders, projects, and
  first-year hosting entitlements.
- Added staff customer lookup to FAMtastic Operations.
# 2026-08-12 — Marketing command center

- Expanded Drupal Campaign Operations into a mobile-first owner command center
  for the 17-day, 68-moment campaign.
- Added approval readiness, publishing exceptions, attributed visits, leads,
  conversion, sales, and separate Postiz/GA4 workspace links.
- Added the complete 17-day Teach/Challenge/Prove/Invite calendar and preserved
  verified-event semantics: attempted posts do not count as delivered.
- Contained dense historical campaign tables in a mobile scroll region; 390px
  browser QA found no document-level horizontal overflow.
- Began the official Meta developer connection and paused at Facebook login for
  Fritz's password/2FA rather than handling or storing Facebook credentials.

## 2026-08-18 — Three-project six-direction swarm benchmark

- Added exact one-restrained, one-medium, four-ultra benchmark acceptance.
- Built 18 responsive websites for Bossy Nails by Pri, The Good Ole Candy Lady
  Shop, and The FAMU Corner under one customer identity and three request IDs.
- Added 36 direction screenshots, project review rooms, independent visual
  scoring, model/prompt ledgers, official-source research where required, and
  SHA-256 integrity evidence.
- Added a one-command clean rerun and combined multi-project evidence gate.
- Added a de-identified private template library that retains proof packages
  while blocking automatic reuse of customer copy/assets and public portfolio
  publication.
# 2026-08-18 — Autonomous preview-to-Site-Studio packet bridge

- Added versioned FAMtastic build-packet and Site Studio success-packet schemas.
- Added a provider-neutral autonomous pipeline with exact per-stage asked/given/returned journals, availability preflight, build classes, declared fallbacks, one-or-two-direction selection, portable assets, signatures, and portal-result emission.
- Added Drupal packet registration and signed success ingestion on the existing Site Studio callback boundary. Results are idempotent, project-scoped, ownership-aware, notification-backed, and forbidden from changing price, charging, purchasing domains, or publishing.
- Added three-run clean certification, tamper rejection, template retention, capability drift enforcement, a dated nine-part master plan, and Gandalf cross-repository notes.
- Site Studio's repository and build engine were not changed. Golden replay and a local Site Studio contract fixture remain explicitly distinct from real provider generation and real Site Studio execution.

## 2026-08-21 — FAMtastic Concierge event bridge

- Added a signature-verified, idempotent Inkbox lifecycle receiver for the
  FAMtastic Concierge identity and recorded public Solution Finder submissions
  in the shared Concierge timeline.
- The bridge stores only lifecycle metadata and lead matching facts; it does
  not send customer messages or alter pricing, grant, payment, domain, or
  deployment authority.
- Added the cross-CLI and Site Studio handoff contract. Production deployment,
  webhook subscription, signing-key configuration, and live certification are
  deliberately deferred.

## 2026-08-24 — Command-center completion pass (audit gaps 1–8)

- Hub: Proof QA, Campaign Gates, Support Drafts, Replies, Renewals-due-30d, Revenue-30d cards (live queries)
- Campaigns: revenue column (paid commerce totals via prospects); Services: renewals-soonest sort + due-soon flags
- Notifications: one-click Retry (requeue form); PurchasePage: server-driven renewal price, custom-scope honest notice; grant checkout completion notice; staff-login advert removed; checkout/user pages → Olivero customer theme; portal nav fully wired (Support/FAQs/Growth/Referrals/Settings), dead sections removed; portal crawler: geometric overlap detection + save-flow assertions
- Fixes en route: portable SQL aggregates (SQLite), legacy support rows, case-number route pattern
- Design brief for Codex: docs/design/UI-DESIGN-BRIEF.md (includes failed muapi prompt + rejection reasons)

## 2026-08-24 — Marketing Command Center (Codex feedback build)

- /admin/famtastic/marketing: unified staff workspace over the canonical manifest — Command, Content queue, Calendar, Channel health, Leads & attribution, Email center (inspectable body/message-ID/retry), Creative & media, Build DNA (prompt/input/output/SHA inspection per build run)
- Execution-truth banner on every tab (receipts required; Antigravity not headless; MuAPI needs approved direction; no state is publish/send/charge/launch approval)
- Attribution honest at campaign grain; content-ID join lands with UTM persistence (queued)

## 2026-08-25 — Gate links fixed; branded customer theme shipped

- Social-record gate route had double-brace params ({{gate}}/{{direction}}) — every approval link 404'd. Fixed; gate form verified 200; audit extended to gate routes, email inspect, Build DNA detail.
- famtastic_customer theme (Olivero subtheme, dark/lime brand) is now the default for customer surfaces (checkout, user pages); admin keeps famtastic_admin. Backend deploy promotes it with backup/rollback symmetry.
- Ops: prod disk-quota squeeze had silently killed cron (workers stale Aug 24–25); 8.4G freed (32 stale releases + duplicate backups); deploy retention now automatic; cron verified running again.
