# Product changelog

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
