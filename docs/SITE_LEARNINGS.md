# FAMtastic Designs site learnings

## 2026-08-31 — A React route is not live until the shared Apache boundary admits it

- Observation: the deep-discovery component, its frontend bundle, and its
  backend endpoint can all deploy successfully while Apache returns a plain
  404 before the SPA starts if the shared document-root rewrite list omits the
  route family.
- Guidance: for every token-scoped React route, add one deliberately narrow
  rewrite rule and a deterministic route-shell assertion. Verify the actual
  public route in a browser before creating or sending an invitation.

## 2026-08-31 — PHP syntax is not a dependency-injection compatibility check

- Observation: the deep-dive service passed PHP lint but typed `@datetime.time`
  as the Core interface while the live container injects the Component time
  implementation, so construction failed before any invite or mail side effect.
- Guidance: mirror the interface used by adjacent services and retrieve new
  services from the real Drupal container before performing a customer action.

## 2026-08-31 — Production quality joins visual identity to owner-controlled truth

- Observation: generated atmosphere, layered texture, expressive type, and
  motion can make a pop-up brand memorable, but they become stale marketing if
  every price and event requires a developer. A phone editor without a strong
  public visual system has the opposite problem: utility without demand.
- Guidance: treat hero, menu, texture, event, social, contact, and consent as
  stable component contracts. Give each visual its own job; keep readable
  labels native; load changeable business facts from an authenticated durable
  store; preserve a truthful static fallback; and require explicit owner saves.
  Capture inquiries and marketing consent separately, and never infer outbound
  email, payment, inventory, or publishing authority from the presence of an
  admin screen.

## 2026-08-31 — A surprise gift site should amplify a public signal without exposing the field photo

- Observation: the strongest source material for a neighborhood pop-up may
  simultaneously contain its most recognizable brand cues, bystanders or
  children, and outdated or conflicting contact details.
- Guidance: separate recognition from disclosure. Use the booth palette,
  material, product category, and verified public social handle as reusable
  bindings; withhold the raw photograph when people or disputed contact data
  remain visible; create new reference-led atmosphere art; keep all readable
  social copy native and editable; and make the public concept no-index until
  the business accepts its menu, contact, event, and official-site details.

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

## 2026-08-29 — Free filesystem space does not prove cPanel user-quota headroom

- Observation: frontend preflight reported hundreds of gigabytes free on the
  filesystem, but npm extraction still failed with system error `-122` because
  fourteen private release worktrees consumed roughly 11 GB inside the hosting
  user's quota. Production was safe because failure occurred before promotion.
- Guidance: before a server-side frontend build, inspect both filesystem space
  and the user-owned release-cache footprint. Retain the active release and
  current target, but age out stale Git-reconstructable build directories using
  exact validated paths. Never treat `df` alone as quota proof, and never edit
  `public_html` manually to work around a private-build failure.

## 2026-08-29 — Modernize the handbill by giving it somewhere permanent to lead

- Observation: Omar's existing flyer is not a weak starting point; its density,
  color, and hand-to-hand context already earn attention. The missing value is
  continuity after the paper changes hands, not a cleaner flyer by itself.
- Guidance: preserve the recognizable print energy, remove unsafe contact data
  from public proof, and make one stable owner-controlled link the destination
  for flyers, QR signs, social posts, event updates, and optional follow-up.
  Build native-copy social formats around real customer moments, keep generated
  products illustrative, and do not imply a handle, account, inventory, event,
  engagement result, post, schedule, or campaign exists before owner approval.

## 2026-08-29 — Pop-up commerce starts with continuity, not a fake catalog

- Observation: the strongest first proof for a traveling merchant is not a
  fabricated ecommerce store; it is a permanent, memorable front door around
  a real person whose table, categories, and locations change.
- Guidance: use a stable public URL and QR, swappable category components,
  editable event/directions fields, local prototype requests, and distinct
  consent. Keep money in the owner's approved account. Represent device-local
  controls as a functional prototype until authentication, persistence,
  notification, inventory, and payment ownership are separately approved.

## 2026-08-28 — Preserve an approved direction before making a bolder fork

- Observation: visual refinement was initially applied directly to an already
  approved Alex prototype even though the original had independent value as a
  functional, restrained direction.
- Guidance: preserve approved routes and device state, then create a versioned
  sibling when composition, texture, typography, or operational components
  materially change. Keep the parent available in the lab, label the new
  direction, and verify that the parent remains byte-for-byte unchanged.
## 2026-08-31 — A deeper discovery link must be private, resumable, and separate from commercial action
- Observation: A generic public intake can collect a few requirements, but it
  does not establish a safe, durable path from a client’s booking/brand choices
  to their eventual workspace and proof review. Putting a private bearer token
  in a query string also risks disclosure through referrers and logs.
- Guidance: Use an owner-created, exact-recipient invitation with its secret in
  the URL fragment and only a persisted hash. Save one validated answer at a
  time, require verification of the same email before account claim, and create
  only a portal draft. Keep Booksy bridge, payment display, proof generation,
  proof delivery, prices, and launch behind their independent owner gates.

## 2026-08-31 — Local Commerce validation needs the same PHP extensions as production
- Observation: The local backend container installed Composer dependencies from
  the locked Drupal Commerce set without PHP `bcmath`, causing the image build
  to fail before Drupal could run migrations or API checks.
- Guidance: Keep `bcmath` in both the Composer and PHP-FPM Docker stages.
  Treat a successful frontend build and PHP syntax check as source validation,
  not a replacement for the pending Drupal/MariaDB runtime check.

## 2026-08-28 — Modular Customer Portal Architecture with Governed AI Assistance Beats Monolithic Dashboards
- Observation: Housing 14+ customer lifecycle surfaces, guided multi-step brief wizards, proof review iframes, file management, and message threads inside a monolithic 500-line React component created code bloat and made individual subviews difficult to test, maintain, and evolve.
- Guidance: Structure the customer portal into dedicated modular sub-components under `components/portal/` coordinated by a single thin dashboard orchestrator. Keep the AI boundary explicit: Shay and AI assistants may summarize briefs, answer product questions, and draft support requests, but must never autonomously mutate accounts, alter billing, send messages, or approve deployments. Always maintain strict CSS containment guards (`.portal-app{overflow-x:clip}`, `.portal-grid > * { min-width: 0; }`, `.portal-conversation { overflow: hidden; }`) to guarantee flawless mobile viewport rendering.

## 2026-08-28 — Multi-Channel Social Operations Require Visual Day-by-Day Dispatch Grids
- Observation: Managing a multi-channel campaign across Facebook, YouTube, TikTok, Instagram, and X via raw SQL tables or disconnected JSON manifests leaves operators blind to what is actually going out on any given day, what visual artwork is attached, and which gates are open.
- Guidance: Unify campaign operations in a Daily Social Dispatch dashboard organized by Day (1 to 17) and Moment (Teach @ 08:00, Challenge @ 12:30, Prove @ 17:00, Invite @ 20:30) with embedded artwork previews (4x5 & 9x16), copy hooks, and 1-click batch gate approvals.

## 2026-08-28 — Bridge Decoupled Intakes to Native Drupal Views & Webforms Rather Than Re-Inventing Admin Surfaces
- Observation: Building custom SQL tables and standalone controllers for decoupled APIs works for headless throughput, but bypasses Drupal's native superpowers (Views exposed filters, Webform submissions audit, and built-in email handlers).
- Guidance: Always provide a `hook_views_data` (`.views.inc`) file for custom pipeline tables and bridge decoupled intake endpoints into Drupal's native `webform_submission` entity so operators can use standard administrative Views and Webform results.
## 2026-08-27 — Research evidence belongs in the reusable recipe

- Observation: a beautiful research-backed build is not reproducible when its
  sources and reasoning exist only in an agent conversation or image prompt.
- Guidance: keep an official source manifest, primary design/accessibility
  references, clean-room provenance, and a cited component decision ledger next
  to the recipe. Carry stable decision IDs through rendered proof, Build DNA,
  and any later Site Studio packet.

## 2026-08-27 — Reference-led images should form a component-ready story

- Observation: a premium hero plus three visual duplicates increases asset
  count without increasing page value.
- Guidance: make the premium image the parent art direction, then generate
  separate environment, process, and result/detail companions from it. Retain
  prompts, lineage, hashes, and provider truth; native HTML owns all legible
  text, UI, and calls to action.

## 2026-08-27 — Component architecture belongs in every agent's doctrine

- Observation: the Booked & Branded Component Lab proved a useful model, but a
  proof-specific document alone would not reliably teach Claude, Codex, Shay,
  or Site Studio how to preserve a customer's theme during future upgrades.
- Guidance: use one repository-wide doctrine for site → page recipe → component
  instance → component → part. Link the same rules from agent entry points,
  Build DNA, and the Site Studio bridge; keep individual niche registries as
  implementations and evidence.

## 2026-08-27 — Multi-agent Git work needs explicit synchronization points

- Observation: this feature branch was five commits ahead of its old base while
  `origin/main` also had five newer commits. Without reading and reconciling the
  incoming history, either the component work or current Solution Finder,
  checkout, and proof-access work could have been omitted from the next handoff.
- Guidance: fetch and inspect remote divergence at task start, before push, and
  before deploy; preserve understood changes during rebase/merge; rerun tests;
  and report branch, pushed SHA, merged-main status, and production release
  evidence separately. Never schedule blind pulls into dirty worktrees.

## 2026-08-27 — Solution Finder UX: Hero Entry Point & Modal Takeover Beat Embedded Forms

- Observation: A permanently embedded chatbot panel on landing pages consumes excessive screen real estate—especially on mobile—and feels like a traditional form wearing a chat costume. Visitors hesitate to engage because the interface demands answers before demonstrating competence.
- Guidance: Replace embedded chat widgets with a clean Hero Entry Point that makes a single promise ("See what your market is doing in 20 seconds") with one tap-to-start action. When triggered, open a focused full-screen overlay on mobile and a centered modal sheet on desktop. Structure the consultation to "give before extracting": deliver an instant Local Market Scan within 20 seconds, ask 3 guided scope questions via touch-friendly chips, and materialize the Scope Blueprint artifact with locked pricing directly on screen before asking for an email address.

## 2026-08-27 — Reusable sites need stable component identity below the page

- Observation: the Booked & Branded builder already repeated one HTML function
  across twelve proofs, but a repeated function alone did not expose which
  sections could be hidden, moved, split into another page, or independently
  templated. That made the reuse real in code but invisible to Site Studio.
- Guidance: model page → section instance → versioned component → field, slot,
  repeater, and action. Emit stable IDs from the structured recipe, never from
  CSS position. Prove one controlled variable at a time: the first experiment
  freezes nine component instances and permits only the hero-media source to
  change. Later component variants must preserve typed field contracts and
  Build DNA lineage so a one-page starter can become a multi-page upgrade
  without visual or content loss.

## 2026-08-27 — Card personality must not break comparison alignment

- Observation: translating and rotating the middle proof card added energy but
  made the three options read as a broken grid rather than a deliberate set.
- Guidance: keep card-level geometry aligned when the user's job is comparison.
  Put visual personality inside the card through imagery, type, texture, shape,
  and content composition; require desktop top and bottom edge parity in browser
  QA. Version static stylesheet references when the release surface can retain a
  previously cached visual defect.

## 2026-08-27 — Marketplace discovery and owned retention can coexist

- Observation: “leave Booksy” is too blunt. Booksy can still produce discovery
  while an owned site gives the operator brand control, direct rebooking,
  explicit consent, fresh reviews, referrals, and an upgrade path. Booksy's
  current Boost model also charges 30% on the first Boost-acquired visit and 0%
  on later visits, so a permanent direct discount is not automatically cheaper.
- Guidance: keep the platform live during the pilot, let the operator choose a
  time-bounded direct incentive or perk, and measure channel, repeat, consent,
  workload, and completed-appointment outcomes. Never exchange a benefit for a
  review or market to platform-derived contacts without applicable consent.

## 2026-08-27 — Premium image execution needs three choices and a finish

- Observation: one generated image can satisfy a receipt without proving that
  the strongest creative direction was explored or selected.
- Guidance: every premium image position gets three materially different
  candidates, a documented selection/rejection decision, and a finishing pass
  for crop, tonal balance, contrast, cleanup, sharpening, and artifacts. Retain
  all candidates, the finished derivative, prompts, provider truth, cost, and
  hashes in Build DNA. Native HTML/CSS continues to own readable text and UI.

## 2026-08-27 — A QR display is not a payment-processing product

- Observation: naming Square, Stripe, Cash App, payment links, and deposit
  states together made the Booked & Branded starter sound like FAMtastic would
  operate or reconcile payments. That adds perceived complexity and liability
  to an offer whose job is to provide a branded front door and upgrade path.
- Guidance: let the business supply its own approved Cash App or existing
  payment-provider QR and display it on the site. Payment stays directly
  between the client, business, and provider. FAMtastic does not process,
  receive, settle, refund, or reconcile it. Payment-processing and optional
  messaging costs are paid directly by the business to its chosen providers.
  Carry the same no-processing boolean and fee-ownership fields into Site
  Studio so a future build cannot accidentally turn display into processing.

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

## 2026-08-27 — Reference-led image generation still needs a visual rejection loop

- Observation: the first 12-image Gemini reference-led pass preserved casting,
  palette, and business context but several frames invented poster text or UI
  overlays even though exclusions prohibited them. Hashes and provider receipts
  proved execution; they did not prove visual fitness.
- Guidance: keep typography, shapes, and interface language in native HTML/CSS.
  Prompts should demand one uninterrupted photograph, remove layout/type jargon,
  turn phone screens away or dark, and reserve empty photographic space for real
  page typography. Visually inspect every frame; reject the full batch or a
  targeted artifact when needed, retain the receipt/reason, and include rejected
  generations in the cumulative cost record.

## 2026-08-27 — Shay can be the business face without becoming business authority

- Observation: naming Shay the FAMtastic Designs AI Business Concierge makes
  the outreach and proof handoff feel guided instead of automated, while the
  email stays more trustworthy when it states what Shay does and when a person
  takes over.
- Guidance: let Shay explain proofs, collect decisions, and organize setup.
  Keep pricing, scope, approval, payment, and launch authority with Fritz and
  the FAMtastic team, and carry that same boundary into Site Studio handoffs.

## 2026-08-27 — A platform-independence offer needs to show brand and operations together

- Observation: a branded homepage alone does not prove the Booked & Branded
  idea. The persuasive unit is an actual outreach email leading to three
  visible choices, with one direction showing how booking requests, deposits,
  reviews, and daily decisions could feel on the operator's phone.
- Guidance: demonstrate the emotional upgrade and the operating upgrade in the
  same proof set, while keeping capability language exact. Label sample people,
  appointments, reviews, payments, and QR codes as fictional; do not turn a
  static Booking Desk into a backend claim. Keep generated-image provider,
  prompt, artifact hash, cost status, browser QA, and reviewer state in Build
  DNA. Public product demonstrations may use unlisted static routes, but real
  recipient proofs must still use the CRM-bound signed delivery lane.

## 2026-08-27 — Platform-only leads are a distinct product opportunity

- Observation: correcting the first-site cohort exposed a more specific need:
  appointment businesses may already have functional booking profiles but
  still lack a distinctive owned customer experience and a flexible growth
  path. Treating them as simply “offline” would miss the actual problem and
  make the outreach feel generic.
- Guidance: market an owned branded front door plus a small phone-manageable
  operating layer, not an unsupported feature-for-feature platform replacement.
  Keep the booking engine pluggable: bridge the current platform first,
  introduce request-to-book as the bounded starter, and sell real-time
  scheduling only after conflict/recovery proof. Keep processor accounts owned
  by the business and describe reported platform dissatisfaction as a pilot
  hypothesis until interviews and usage evidence validate it.

## 2026-08-27 — Drupal AI integration in decoupled frontend must have deterministic fallbacks

- Observation: Connecting decoupled React interfaces (like the Solution Finder) to Drupal AI provides natural-language understanding, but AI provider API keys, model rate limits, or external latency must not become single points of failure for public visitor conversions.
- Guidance: Wrap Drupal AI calls in a dedicated service layer that falls back seamlessly to high-accuracy deterministic catalog matching when external AI providers are unconfigured or unavailable. The visitor experience must always complete with a clean recommendation.

## 2026-08-27 — Drupal admin URL generation must use routes, not raw userInput paths

- Observation: When Drupal is deployed in a subpath (e.g. `/web` behind a React frontend at `/`), `Url::fromUserInput('/admin/...')` generates URLs targeting `https://famtasticdesigns.com/admin/...` rather than `https://famtasticdesigns.com/web/admin/...`, resulting in 404s for Commerce, Content, and Entity edit links.
- Guidance: Always use `Url::fromRoute()` (such as `commerce.admin_commerce`, `system.admin_content`, `entity.<entity_type>.edit_form`) in controllers and forms. Drupal's route system automatically prepends the correct active base path in both local and production environments.

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

## 2026-08-27 — Drupal security fixes are full locked-dependency releases

- Observation: the active Entity API advisory applied because Entity API and
  JSON:API were both enabled in production. A Drupal security fix was not a
  module-only copy: the governed deployer promoted the reviewed
  `composer.lock`, installed the exact production dependency set, retained
  rollback archives, rebuilt caches, and then proved a zero-advisory audit.
- Guidance: handle Drupal/Composer security work in a clean current-main
  worktree. Validate the exact lock on the production PHP platform, use the
  governed backend deployer, verify `composer audit --locked`, Drupal version,
  update status, and a minimal affected public route afterward. Preserve any
  active pilot dispatch lock and do not treat a dependency release as
  authorization to resume schedulers or customer communication.

## 2026-08-27 — Pilot activation must be a sequence, not a feature switch

- Observation: the verified-cold code could be safely deployed only after the
  public API base was explicitly canonical, the historical exact-campaign
  queue was accounted for, and the one marked broad lifecycle scheduler was
  suspended before old code could run. The deployed database and route proof
  still do not constitute a recipient proof or a commercial-send proof.
- Guidance: retain the durable exact-pilot lock until the separate owner-gated
  cohort has passed Build DNA, room, claim, and explicit send acceptance. Use
  only the exact dispatcher for that later delivery; never reopen or substitute
  a broad lifecycle run merely because the release itself succeeded.

## 2026-08-27 — Remote deploy arguments must survive SSH command serialization

- Observation: `ssh host command arg...` is interpreted as a remote shell
  command, not an argv-preserving transport. Empty optional pilot-confirmation
  arguments disappeared, shifting later values and causing the remote deploy
  script to stop before it could inspect a scheduler.
- Guidance: encode every remote deploy argument into a nonempty shell-safe
  token and decode it only inside the remote script. The deployment fixture
  must emulate SSH's command flattening so it catches empty-argument regressions.
  Operator help output must likewise treat backticks as plain prose, not shell
  substitutions.

## 2026-08-27 — A cold callback cannot inherit the generic local importer

- Observation: the generic local proof importer could call the normal callback
  service for a persisted `verified_cold` campaign. Even a valid-looking
  payload could therefore bypass the private Build DNA/HMAC import contract;
  a copied cold unsubscribe key could also reach the historical mutating GET
  route.
- Guidance: deny the cold lane both at the generic command and at the generic
  service entry point. Give the private cold importer one transaction that
  rechecks the exact delivery/job/event/Build-DNA binding before it records
  either projection or proof artifacts. Prove an exact runtime-bound generic
  payload changes no variants, Build DNA, or delivery state, while an ordinary
  local import still works. The legacy GET route must reject cold keys without
  consent mutation; only the confirmation POST may suppress cold mail.
- Guidance: migration repair is a safety boundary too. Before `8042` changes
  a populated cold table, reject missing, NULL, blank, or duplicate immutable
  cohort/ingress keys, then use Drupal's Schema API to restore the canonical
  NOT NULL field definition and a missing declared unique key. Rehearse the actual update on disposable MariaDB rather
  than infer behavior from source inspection.
## 2026-08-27 — A new durable lock cannot protect code that has not been promoted

- Observation: the prior pilot guard made the new Drupal durable lock
  authoritative after promotion, but an old production `drush cron`, direct
  jobs runner, or service-level evaluator can act before that code exists. The
  historical cold-260 risk also spans more than queued proof jobs: a campaign
  can hold proof preparation, send jobs, and generic message rows in different
  claimable or active states.
- Guidance: before an exact-ID pilot crosses the code boundary, enumerate every
  broad scheduler and fail closed on unmarked, duplicate, altered, direct-eval,
  active direct-worker, or already-running broad-process forms. Automatic
  suspension may remove only one
  marker immediately followed by its byte-exact named command and an explicit
  repeated confirmation; retain a mode-0600 backup but never auto-restore a
  stale full crontab. Keep the scheduler suspended until a separately authorized
  end-pilot reconciliation verifies the durable lock, current crontab, and
  queued/retry notification outbox inventory.
- Guidance: exact-campaign quarantine must classify all attributable proof and
  commercial-mail jobs plus campaign-owned generic messages by type/status;
  active and unknown rows are manual reconciliation, not a reclassification
  opportunity. Never cancel notification outbox rows heuristically when they
  lack a campaign ownership key. Treat canonical frontend and `/web` API bases
  as a deployment precondition rather than silently promoting localhost or a
  staging route into customer email.

## 2026-08-27 — A held cold-proof email is not permission to use SMTP

- Observation: the exact public-preview dispatcher called the transactional
  mailer directly. A verified-cold delivery could therefore bypass the legacy
  real-outreach gate, and its tracked URLs assumed `/api/...` even though
  public Drupal routes are mounted beneath `/web`.
- Guidance: preflight every verified-cold batch before claiming any held
  outbox row. Default SMTP must deny; a local capture requires an explicit
  memory gate, while real dispatch requires both the global and lane-specific
  owner gates. Construct customer-facing click/unsubscribe links from the
  canonical same-origin `/web` API base. A malformed cold destination is a
  404, never a reason to mint a legacy prospect token.
- Guidance: cold research and copied listing text are public-bound content,
  not trusted internal notes. Require a research teaser, cited source summary,
  and Build DNA research artifact before cold staging; pass source evidence and
  research through the shared public-content guard so email, phone, and
  credential-shaped text are redacted before a room, builder packet, or email.

## 2026-08-27 — Exact-ID pilots need a durable cron lock and a clean legacy queue

- Observation: a deployment-shell environment flag cannot control the next
  cPanel `drush cron` process, and a newly added lock does not erase an old
  generic proof queue. Leaving either gap open makes an owner-gated pilot
  vulnerable to unrelated automation or the historical cold-260 work.
- Guidance: persist the lock in Drupal config, enforce it at the start of both
  broad runtime routes, and treat environment `1` only as an additive emergency
  stop. A governed pilot release must inspect the lifecycle and Drupal cron
  forms, only suspend an exact marker-owned lifecycle command, and record the
  durable lock state. Require the historical exact queue to be zero; any
  quarantine is a separately confirmed exact campaign action after the new
  runtime is active, with a receipt and zero-count recheck. Never use generic
  callbacks or dynamic due-date selection as alternate import/send paths.

## 2026-08-27 — A public-preview signup is an account claim, not a request submission

- Observation: Drupal's user-insert hook sees the same-email Prospect during
  account creation. Its ordinary convenience path could immediately turn
  existing discovery notes into a submitted website request, enqueue both
  customer/staff request notifications, and add a generic proof job before the
  public-room recipient had verified control of the email.
- Guidance: validate a signed public-preview continuation before saving the
  Drupal user and carry only a request-scoped, non-persistent intent through
  the insert hook. Record the non-advancing signup event, skip the generic
  discovery auto-create path, and clear the intent after the controller
  returns. Claim the matching preview only from the consumed verification
  token; any owner registration alert belongs after verification and must say
  that verification completed. Do not
  suppress normal registrations with no valid continuation.
## 2026-08-27 — A local proof bundle cannot stand in for a canonical build run

- Observation: the first-ten cohort builder could produce structurally valid
  pages, prompts, and a Build DNA skeleton before Drupal had created the real
  Prospect, Proof Campaign, public campaign, job, or callback event. Reusing
  those local IDs at promotion time would let an otherwise valid asset package
  be projected against the wrong campaign—or an invented placeholder.
- Guidance: keep preparation visibly non-importable. Before receipt-backed
  finalization, bind every selected lead through one immutable, checksummed
  sidecar that carries exact canonical IDs and a recorded job start time;
  mirror the same values into the manifest, `build_dna.run`, correlation, and
  Build DNA artifact ledger. Finalization and callback serialization must
  reject a missing, mismatched, replayed, or `local-*`/`beauty-proof:*`
  placeholder binding. The binder is evidence preparation only, never a
  Drupal, provider, publish, or email authority.
- Guidance: use the existing Gemini Flash Lite worker receipt rather than
  creating a parallel image route. When its receipt lacks a per-image start
  timestamp, retain `partial-receipt-recorded` timing rather than guessing a
  value; exact hashes, prompt hashes, byte counts, and provider evidence still
  remain mandatory.

## 2026-08-27 — A Drupal schema upgrade must preflight nonempty partial tables

- Observation: update `8041` originally created the absent-table path correctly, but its attempted partial-table repair passed too few arguments to Drupal 11's `Schema::addIndex()` and could let the Schema API restore `NOT NULL` on a missing field before legacy rows had a valid value. That turns an intended recovery branch into a late SQL failure.
- Guidance: before any DDL on a nonempty partial table, identify missing, blank, NULL, or invalid required identity fields and duplicate future unique-key values. Populate only values that are semantically safe and explicit (the un-staged frozen-email snapshot may be empty); never manufacture ownership, public IDs, or delivery keys. Fail before mutation with an operator repair message when the legacy record cannot be mapped, and restore valid identity columns to their canonical NOT NULL definitions before adding uniqueness. Rehearse the actual update hook through Drupal's MySQL Schema API against the production-compatible major/minor database, not only with PHP lint or hand-written SQL.

## 2026-08-26 — Preview claims must not consume delivery state

- Observation: the earlier preview branch represented signup and same-email claim by replacing the delivery state. A customer who registered before proof generation could therefore stop matching the preview-ready handoff and fall into generic outreach; a revoked delivery could also lose its reusable Prospect link.
- Guidance: delivery state describes the room/email lifecycle, while `signup_started_at`, `customer_id`, `claimed_at`, and `website_request_id` describe account continuation. Preserve those independently. Stage only from immutable, exact Build DNA evidence; include the exact prospect, proof campaign, public campaign ID, served-artifact hashes, and research artifact role where research copy is shown.
- Guidance: a held targeted preview email must never become a broad lifecycle candidate. Do not permit revocation while it is in SMTP dispatch, and treat cold outreach as a separate compliant campaign-message lane until postal/unsubscribe/provider-event receipts exist.
- Guidance: one Prospect can have public and registered proof work, so prospect lookup is not campaign ownership. Bind public and request proof campaigns before remote dispatch; retries must use that exact binding. A staged room must snapshot and rehash its served paths, not re-query mutable `proof_variant` rows.

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

## 2026-08-26 — A lead list is not enough evidence for a personalized proof

- Observation: a source list can contain business names and email addresses
  without proving a service, policy, price, booking channel, audience, or
  visual right. A generic template mailer can use that data to send outreach
  but cannot honestly claim it has prepared a personalized website proof.
- Guidance: the Beauty / Hair / Braiding cohort builder now takes a separate,
  explicit mapped input. It fails closed until every selected lead has at least
  one source-backed fact and a short source-backed research teaser. It keeps
  raw contact email out of artifacts and emits three reusable vertical systems
  that vary with real source evidence, palette, motif, and deterministic seed.
  Gemini art, browser proof, independent visual approval, Drupal registration,
  promotion, and email are individual named gates, never implied by static
  HTML generation.

## 2026-08-26 — A ledger claim can go stale mid-run when sessions run concurrently (heartbeat 03:16Z)

- Observation: the 03:16Z heartbeat oriented at 03:13Z on two uncommitted
  backend files (order-number hook + OperationsController render fixes),
  verified them, wrote its line flagging both as unknown-provenance/uncommitted,
  and committed — landing on top of `005d1b92`, which a concurrent operator
  session had created at 03:15:30Z committing BOTH files. The worktree was
  clean before the heartbeat's own commit finished; two ledger claims were
  stale on arrival. This is pitfall #13 (two agents editing one tree,
  RETROSPECTIVE-2026-08-22-25.md) manifesting live against the CEO process.
- Guidance: immediately before appending a heartbeat/standup line, re-run
  `git log --oneline -3 && git status --short` and write claims about
  worktree/commit state as of THAT timestamp, not orientation time; if state
  changed under you, append a same-run corrective line rather than amending —
  amend is unsafe while another session may be committing on the same ref.
  Uncommitted-code flags must always name their verification timestamp.

## 2026-08-25 — Heartbeat runs must append + commit their own log line before exit

- Observation: two CEO heartbeat sessions stranded their HEARTBEAT.md append.
  The 08:17Z run left its work uncommitted (recovered by the 10:24Z run), and
  the 14:43Z run's recipe/CHANGELOG edits were silently absorbed into operator
  commit 6a1a47b8 — leaving a heartbeat-log gap that surfaced only because a
  later sweep cross-referenced "(heartbeat 14:43Z)" change-log text against
  HEARTBEAT.md and found no matching entry.
- Rule: every heartbeat run appends its dated line AND commits its ledger unit
  in the same session, before any other exit path. Reconciliation sweeps should
  grep recipe change-logs for "heartbeat HH:MMZ" citations missing from
  HEARTBEAT.md — a citation without a heartbeat line means an orphaned session.

## 2026-08-25 — Provenance audits must enumerate git stashes (C6 preview-runner stack)

- Observation: the C6 escalation ("who owns the hidden preview-runner WIP?") sat
  unanswered for a day because provenance hunting stopped at `git status`,
  `.git/info/exclude`, and file mtimes. The answer was sitting in
  `git stash list`: stash@{0} on branch `codex/shay-website-delivery-swarm`
  (2026-08-23, "abandoned preview runner refactor before PIT delivery") holds the
  near-complete stack wiring — service registration, callback route, client swap,
  e2e rename — that the hidden on-disk files lack. Stashes carry author identity,
  branch context, timestamps, and an owner-written message; they are the richest
  provenance artifact in the repo and were checked last.
- Rule: any provenance investigation into untracked/hidden/unknown-provenance
  files MUST run `git stash list` + `git log -1 --format='%H|%an|%ad|%s'
  stash@{N}` + branch-name inspection BEFORE escalating to Fritz for a ruling.
  Also check `git log --all --oneline` for candidate branches by name. Escalating
  without a stash sweep risks asking Fritz to rule on facts already in the repo.

## 2026-08-25 — Validator pitfalls: PDO typing + outbox dispatch starvation (fam-commerce, revision loop)

- Observation: the revision-loop validator failed 2 of 15 checks with rows that
  were visibly unchanged. Root cause: Drush/PDO returns integer columns as
  **strings**, so `$row['recorded_at']` (string) never strict-matched a stored
  snapshot cast to `(int)`. Rule: in every e2e validator that snapshots DB
  values and re-compares them, normalize BOTH sides at capture time
  (`(int)` / `(string)`) — never rely on PHP's numeric-string equality.
- Observation: `dispatchNotifications(25)` left freshly queued synthetic rows
  unsent on the shared local dev database because unrelated queued rows
  consumed the batch. The mail-visibility validator already parks pre-existing
  outbox rows for this reason. Rule: either park foreign queued/retry rows
  around the assertion window or dispatch in a bounded drain loop until the
  synthetic rows leave the queue; assert per-row status after, not the batch count.
- Guidance: when two agents share a working tree, whole-file `git add` on a
  co-edited module file imports the other agent's hunks. Stage HEAD+your-hunks
  via `git hash-object -w` + `update-index --cacheinfo`, then lint the staged
  blob (`git cat-file -p … | php -l`) before committing.

## 2026-08-25 — Update-hook numbering and raw key_value edits (fam-growth, UTM attribution)

- This module's `.install` keeps update hooks in TWO places: the numbered run
  near the top AND later hooks appended after the schema helper functions at
  the bottom (`update_8033`–`8036` live there). Before adding a new hook,
  `grep 'function famtastic_pipeline_update_'` the whole file — a duplicate
  number fatals every PHP load of the module ("Cannot redeclare function"),
  not just updatedb. New attribution hook therefore shipped as 8037.
- Editing `key_value.system.schema` with raw SQL requires the exact
  PhpSerialize format including the trailing semicolon (`i:8036;`, not
  `i:8036`). A malformed value makes Drupal silently treat the module as
  having no readable schema version — updates report "No pending updates"
  while warnings (`unserialize(): Error at offset`) scroll past. If a raw
  edit is ever needed, verify by re-running updatedb afterward.
- Attribution join design note: matching social content IDs to prospect JSON
  snapshots is resolved in PHP over the bounded snapshot set instead of SQL
  `JSON_EXTRACT`/`CONCAT`-LIKE so the same Marketing Command Center query
  works on MySQL production and SQLite local. The per-record `leads_count`
  counter stays authoritative-fast for dashboards; the tab recomputes live
  from snapshots so drift self-heals on render.

## 2026-08-20 — A proof link needs a review task, not six unexplained demos

- “Six directions” is internal shorthand. A recipient needs to hear that they
  have six complete alternative homepages, that each link opens one whole
  concept, and that the immediate task is to compare then shortlist one or two
  favorites. The review hub now owns that explanation deterministically;
  creative workers cannot omit it.
- Email evidence and email presentation are different concerns. Preserve the
  readable plain-text body in the outbox and test transport, but render a
  safely escaped, mobile-readable HTML wrapper for inbox use. Never send a raw
  form JSON dump to the owner when a triage summary is what enables action.
- A responsive custom Operations surface can support mobile triage, but it
  cannot make every dense Drupal entity editor good on a phone. Tell the owner
  which actions are safe there, retain touch-safe review controls, and send
  detailed editing to desktop until a dedicated mobile editor is designed and
  tested.
- Motion and high-volume video need different tools. Use HyperFrames when
  layout, type, and controlled motion are part of the design; use
  MoneyPrinterTurbo for reviewed narrative assemblies. Record both in Build DNA
  and keep publishing separate. A subscription advertisement, including a
  reported ACI AI image plan, is a candidate—not a capability claim—until it
  passes a real benchmark with receipts and rights review.

## 2026-08-20 — Build DNA makes quality repeatable instead of memorable

- A beautiful output is not a reproducible system until the build records the
  actual routine, provider/model status, rendered prompts, normalized inputs,
  outputs, asset hashes, timing, cost status, fallback, review decision, and
  retrieval path while the work happens. Missing data must remain explicitly
  missing; backfilling a polished story later creates false telemetry.
- One Build DNA JSON should point to the complete filesystem evidence, be
  projected into the searchable Drupal build ledger, and travel into the Site
  Studio packet. Those are retrieval surfaces for one record, not three
  competing sources of truth.
- The first Gemini Flash Lite story test preserved a premium art-direction
  reference's emotional grammar without copying pixels by describing the
  camera, group layout, architecture, light, palette, material, negative space,
  uniqueness requirements, and prohibited output in concrete terms. “Premium”
  alone was not the quality instruction.
- The five new 1K Lite images have an expected USD 0.168 image-output cost;
  the provider did not return an invoice, so the ledger labels it an estimate.
  The inherited OpenArt `gpt-image-2` source image was not charged to the new
  Lite story build.
- The actual image results, prompts, per-image generation durations, source
  hashes, page source, browser evidence, and same-operator review are saved in
  `marketing/campaigns/and-if-it-is-rattler-lifers/experiments/lite-image-story-20260820/build-dna.json`.
  The browser technical gate passed at 1440px and 390px; independent visual
  approval remains an open gate for any customer or public release.

## 2026-08-19 — A lean social baseline can preserve the design magic

- A public Lab and a public audience experience are different products. The
  experience must protect immersion, story, emotion, and working interactions;
  the Lab owns recipe, telemetry, QA, evidence, and conversion. Combining them
  weakens both even when the visual design is strong.
- “Live” means more than a public 200 response. The Rattler Roll Call is now
  tested in production for generation, device-local persistence, reload,
  clipboard copy, and share fallback, with desktop and phone screenshots.
- The public Lab should sell the method without disguising the evidence
  boundary. Showing the 13:03 observed window, excluded research time,
  self-review status, and marketing/core split made the case study more
  credible than a vague “AI made this fast” claim.
- The recent six-direction Rattler run exposed orchestration churn: eight
  visual-review calls consumed about 29.5 aggregate minutes. Resume logic must
  reuse a passing verdict, default to one consolidated repair, and stop after
  two total visual-review calls unless a human explicitly opens a new change
  lane.
- Quality and speed are compatible when preview scope is bounded. Keep
  research, thesis, expressive typography, subject-native texture/depth, and
  browser/visual gates; move noncritical polish into the selected-direction
  refinement phase.
- One researched cultural truth, two deliberate images, one deep brand system, three editable social cards, and one browser gate produced a stronger and faster social-presence proof than multiplying weak directions.
- The measured production window was 13 minutes 03 seconds from first paid image request to passing QA. Research time was not instrumented and must not be hidden inside that number.
- Graphic quality came from constraints, not volume: the prompts named story, composition, material, light, negative space, prohibited official marks, and common visual failures. Both images passed on their first generation.
- Typography and surface construction must be explicit acceptance dimensions. Outline type, italic serif contrast, rotation, scale patterns, grain, concrete, wool, leather, and atmospheric depth prevented the familiar flat bold/color-only result.
- OpenArt was the transport and `gpt-image-2` was the model. Direct OpenAI Image API access can preserve the same model without OpenArt; Responses API or a different provider/model is a separate route that needs its own cost, authentication, and golden-prompt benchmark.
- This is one coherent social proof, not the six-direction website contract. The distinction must remain visible in the manifest, evidence, and customer language.
- An unlisted public URL proves anonymous static delivery, not social account creation or posting. Content, media, and publication approvals remain closed.
- A new skill or agent was intentionally not created. Promote the formula only after a second unrelated brand reproduces the quality floor; add orchestration only for a measured missing capability.

## 2026-08-17 — Public intake should earn the detailed brief

- The anonymous Solution Finder is strongest as a short lead-capture experience: collect enough information for a starter recommendation, save the Drupal Prospect and Intake, and explain the next useful action.
- A public planning range is not a finished design proof. Working customer-specific demos require the richer authenticated brief and should be described that way.
- Registration must continue the journey instead of restarting it. Matching the verified account by email lets Drupal claim the existing prospect resources into the customer organization.
- The conversion invitation should promise a free workspace and detailed brief, not free finished production work. Payment remains a later, separately authorized boundary.
- Unknown industries and unlisted requirements need open text and human scope review rather than forced catalog classification.

## 2026-08-17 — A model is not the website-delivery engine

- Deterministic schemas, package/add-on rules, independent assertions, and browser evidence own acceptance; models remain replaceable workers.
- The first `website.preview.v2` fixture proof passed three scenarios across anonymous Solution Finder and member portal lanes with six traced specialist stages and three creative directions each.
- Installed subscriptions are not callable automation by default. Ollama is locally available; unattended Codex, Claude, Gemini/Antigravity, and Kimi routes still need auth/cost/privacy proof, while Poe and Z.ai are not currently callable locally.
- The signed Site Studio callback is the scale-out seam. The enriched brief and per-agent trace still need a Drupal persistence adapter before integration is proven.
- Browser screenshots must be content-bearing and hashed. A 1×1 transport fixture proves callback mechanics, not visual quality.

## 2026-08-17 — Personality lenses need a control and protected decisions

- A reusable human tester is more useful when its personality is stable: curious, warm, observant, and constructively skeptical.
- Numerology can be offered as an opt-in creative framing device, but it must be disclosed as non-scientific and cannot infer facts about a person.
- Life Path 3 may receive more visual, storytelling, and idea-generation prompts; Life Path 33 may receive more service, community, education, and responsible-creative prompts.
- Every material finding needs a neutral control comparison. The lens cannot affect price, eligibility, priority, risk, legal terms, accessibility requirements, or approval.
- Prefer receiving the derived Life Path number. Do not persist a birth date merely to personalize a mockup or test.

## 2026-08-17 — First customer-specific swarm artifact pilot

- Three direction contracts are insufficient proof; screenshots must show actual customer-specific rendered websites and loaded media.
- The first screenshot pass caught a broken generated-image path that DOM-only assertions missed. Asset load dimensions are now an explicit assertion.
- Local Qwen and GLM reviews completed much faster than the premium repository-aware review, but disagreed on OMG versus Safe. Provider voting must not replace explicit customer choice or deterministic gates.
- The preview simulator can prove selection, approval, build, and payment stop, but it cannot be represented as the current Drupal purchase lifecycle until an adapter and the production ordering decision are implemented.
## 2026-08-17 — Website discovery must preserve decisions, not just collect contact details

- Anonymous and account-owned website discovery need the same decision vocabulary
  for brand status, business model, industry context, domains, hosting, email,
  inspiration, existing technology, and unlisted needs, even when the public
  version uses fewer questions.
- “No logo” is not one state. A customer who declines logo work must not receive
  a brand add-on; a customer who wants help should receive the configured option.
- Example-site URLs are weak evidence without the reasons behind them. Capture
  what the customer wants to borrow or avoid and preserve that context for design.
- Domain names typed into intake are preferences, not availability proof. Record
  acceptable alternatives and the back-and-forth decision before purchase.
- An unlisted product, service, industry, or workflow must remain representable.
  Unknown industry text is preserved; an unlisted deliverable routes to human
  scope review rather than being discarded or forced into a packaged website.
- A public form that records “mockup requested” is not a proven anonymous mockup
  pipeline until it triggers the proof job, delivers a secure review link, and
  can hand the same request into an account without duplication.

## 2026-08-12 — Marketing production and local AI

- A successful provider test is not a successful campaign post. The first
  Facebook proof used text-only test content and an invalid route; acceptance
  required the approved branded asset, account-specific copy, a canonical
  landing page, UTMs, provider delivery evidence, and a visual check.
- HTTP 200 does not prove a React campaign destination exists. A fallback shell
  can return success for an unknown route, so campaign preflight must verify a
  route-specific title, canonical, heading, or content marker.
- One master creative should produce channel-native variants, but publishing
  must retain separate evidence for the business identity and founder identity.
  Cross-posting convenience never replaces verification of the actual account,
  media, caption, destination, and audience.
- Evaluate paid AI video with a controlled A/B test against the programmable
  baseline. Subscribe only when presenter quality, trust, production speed, or
  revision savings materially exceed the Remotion workflow.
- Adobe Firefly is a useful comparison, not a perfect HeyGen substitute. Its
  Text to Avatar feature can create stock-presenter videos, while Generate
  Video produces short cinematic scenes that still need Express or Premiere
  assembly, captions, real brand assets, and offer QA. Existing Creative Cloud
  access may make that extra work economically sensible.
- Consumer Creative Cloud access and server automation are separate rights and
  credentials. Adobe's Audio/Video and Firefly APIs require an Adobe Developer
  Console project, server-to-server credentials, and applicable service access;
  never assume a desktop subscription automatically authorizes API automation.
- Every internal implementation lesson should enter a reuse loop: record the
  capability, classify its proof, teach the customer problem, package the
  deliverable, define billing, publish useful education, and capture a future
  case study without inventing outcomes.

- Open weights do not imply local fit. Kimi K2 activates 32 billion of one
  trillion total parameters and is not a practical 16 GB laptop model; select
  models by measured memory, latency, license, and task quality.
- Kimi K3 increases the mismatch to 2.8 trillion total and roughly 104 billion
  active parameters. Ollama's `:cloud` tag is a transport label, not local
  inference; tool location and compute location must be recorded separately.
- Local agents need task routing rather than one favorite model. Qwen handles
  routine text, GLM provides an independent multilingual challenger, and a
  smaller Gemma vision model preserves memory for the browser and media tools.
- A presenter generator is one production format, not the campaign engine.
  Branded motion, diagrams, screen proof, founder voice, articles, email, and
  landing pages must share one campaign record and offer truth.
- Free on GitHub requires separate review of code, model weights, training
  assets, dependencies, and commercial use. LivePortrait's bundled InsightFace
  detector is an example of a transitive non-commercial restriction.
- Social automation cannot bypass provider OAuth, app review, visibility, and
  posting limits. Prove private/draft delivery and rollback before public
  scheduling, and keep generated content behind explicit approval states.
- Four daily content moments should become channel-native adaptations, not the
  same post blasted four times to every network. Email frequency remains
  relationship-based, not synchronized to social volume.
- Reusable marketing logic should be incubated beside the first real proof but
  separated structurally from brand, customer, Commerce, Drupal, and credential
  data. Extract only after a second brand and real delivery evidence prove the
  abstraction; early repository duplication creates competing truths.
## 2026-08-18 — Public proof access should be unlisted, revocable, and read-only

- Requiring registration before every proof view creates friction for a lead
  campaign and prevents a customer from asking another decision-maker for
  feedback. A signed unlisted link is the appropriate middle state between a
  private account and an indexable public portfolio page.
- Possession of a view link must not grant the customer action surface. Proof
  viewing can be anonymous while selection, revisions, pricing, checkout, and
  account data remain authenticated and ownership-checked.
- Store link state and a version, not a reusable raw secret. Disabling or
  replacing a link must invalidate the previous signature immediately, and the
  anonymous failure response must not confirm which customer or project existed.
- `noindex` alone is not a privacy boundary. Unlisted proof responses also need
  no-store caching, no-referrer handling, minimal payloads, and a default-off
  publication control after the human quality gate.

## 2026-08-18 — Transactional links must preserve customer intent and identity

- A successfully delivered email and a valid authenticated portal can still
  create a broken journey when the browser is signed into a different customer
  account. The portal must show the active account email and explain an
  ownership mismatch rather than displaying an apparently empty workspace.
- Proof-ready messages must carry the account-owned request UUID and open the
  Projects surface directly. A generic `/portal/` link discards the customer's
  reason for arriving and forces them to rediscover the action promised in the
  email.
- Backward compatibility matters for messages already in an inbox. When the
  correct account has one unselected approved proof set, a plain portal visit
  should surface that set automatically without weakening organization-scoped
  authorization.

## 2026-08-17 — A queued alert is not a delivered alert

- Portal submission, outbox persistence, SMTP delivery, and owner receipt are
  separate states. Monitor queue age and worker heartbeat, then record the SMTP
  message id before claiming an alert was delivered.
- Never couple the core lifecycle runner to an unrelated mailbox command with
  `&&`; a mail-ingest failure can otherwise suppress receipts, staff alerts,
  proof jobs, and escalation for every customer.
- Proof generation and proof delivery need separate gates. Exactly three
  artifacts may be ready while remaining invisible to the customer until the
  owner reviews all three and explicitly authorizes account disclosure.
- A free customer purchase is still a Commerce order. Use a hashed, scoped,
  auditable grant redemption and zero-dollar fulfillment rather than marking an
  unpaid order as paid or bypassing entitlement creation.
- A creative intake is incomplete when it only asks for generic style notes.
  Structured intensity, preferred/avoided colors, desired feeling, references,
  asset consent, and model-enrichment boundaries belong in the canonical brief.

## 2026-08-11 — Headless navigation must have one ordering authority

- Fetching a CMS menu while hardcoding the familiar links in React does not
  make the menu CMS-controlled. Render the ordered top-level records returned
  by Drupal, then enhance recognized destinations such as Services and
  Packages in place.
- Desktop and mobile navigation must consume the same ordered collection so a
  Drupal menu edit cannot produce two different customer experiences.
- A series-level visual is useful brand architecture: applying its image to
  every article makes the full library visually complete while keeping each
  eight-part learning journey recognizable.

## 2026-08-11 — Drupal JSON:API pagination links include the backend base path

- Production Drupal returns absolute `links.next` URLs containing `/web`.
  Request those links directly; prefixing `VITE_DRUPAL_BASE_URL` again creates
  `/web/web/jsonapi` and can make a successful first page appear empty.
- Public collections expected to exceed Drupal's 50-item page limit must be
  acceptance-tested in an anonymous browser, not only counted through the API
  or sitemap.

## 2026-08-11 — Demand creation as a governed product system

- A large editorial library still feels unfinished when its presentation is
  text-only. Build the visual system as part of the content contract: original
  series art, selective use rather than mechanical repetition, real brand assets
  over model-generated lettering, descriptive alt text, mobile aspect ratios,
  compressed delivery formats, and structured-data image references.
- Dynamic sitemap discovery must use the deployed Drupal bundle name and follow
  JSON:API pagination. Querying the legacy `article` bundle or accepting the
  first 50 records silently excludes a large governed blog library from search
  discovery even though the articles are publicly reachable.

- Content generation needs the same source-of-truth discipline as Commerce.
  A canonical manifest prevents taxonomy drift, duplicate posts, disconnected
  CTAs, and claims that outrun capability proof.
- A series is a customer learning journey, not a pile of posts. Store sequence,
  pillar relationship, intent, evidence boundary, FAQs, internal links, and
  the next action together.
- Draft-first safety must be enforced at persistence time. In the lean local
  fixture, `Node::setPublished(FALSE)` retained a bundle default; explicitly
  setting the status base field to zero made the publication gate reliable.
- Shared agent behavior requires both installation and repository authority.
  Pinned third-party skills provide specialist techniques, while the local
  doctrine controls claims, products, publication, and commercial gates.
- Mobile QA should verify document geometry as well as appearance. Matching
  body scroll width to the layout viewport caught no horizontal overflow on
  the blog hub or article, while semantic checks proved the CTA, FAQ, series,
  canonical URL, and structured data were actually present.
- Eight topics are not a demand library. When each topic represents a durable
  buyer problem, promote it into its own pillar-and-spoke series and require
  enough depth, unique intent, reciprocal links, and editorial metadata for
  the resulting drafts to be genuinely reviewable.
- Character-count validation allowed thin drafts to look complete. The demand
  gate now measures body words, heading depth, keywords, intent, canonical and
  social metadata, schemas, sources, FAQs, and inbound/outbound link coverage.
- Dark admin theming must set both foreground and surface colors on rendered
  nodes, field wrappers, metadata, tables, and preview containers. Setting text
  color alone lets Claro's light contextual backgrounds create unreadable
  white-on-light combinations.

## 2026-08-11 — Stripe live activation

- Promote Commerce by creating a separate live gateway and disabling the test
  gateway, rather than overwriting the historical sandbox entity. This keeps
  old test orders intelligible and gives live webhooks a stable, explicit URL.
- Reuse the server-owned live credential already held outside configuration;
  never move it through Git or documentation. Verify Stripe itself returns
  `livemode=true` before enabling the gateway.
- Gateway activation exposed missing Commerce Stripe bundle-field tables even
  though Drupal reported no pending database updates. The safe repair was a
  timestamped database backup followed by the module's bundle installer and a
  cache rebuild.
- “Live enabled” is production configuration proof. It is not live transaction
  proof until a real customer payment, signed webhook, Commerce payment/order,
  receipt, fulfillment, and staff notification are observed together.

## 2026-08-11 — Reusable website requests

- Intake belongs to a project/request, never directly to a customer. A durable
  customer can own several businesses, domains, purchases, and website builds.
- Pre-purchase questions must be resumable and useful for recommendations,
  while payment still controls activation and purchased-service fulfillment.
- A submitted portal request creates a distinct Drupal lead. Commerce records
  its public UUID in the immutable checkout snapshot and fulfillment converts
  that exact request into its own intake and project.
- Account ownership is checked both when editing a request and when attaching it
  to checkout. The canonical proof now rejects a second customer's attempt to
  modify the first customer's request.

## 2026-08-10/11 — Commerce and launch-gate closure

- A configured catalog does not prove checkout. Production was missing the
  default order-item type, checkout flow, customer checkout permissions,
  payment remote-ID field, and Stripe payment-method storage. Update hooks
  8020–8024 now repair those install-time dependencies idempotently.
- Stripe Connect had authorized a different account than the authenticated
  FAMtastic sandbox. Commerce now uses the controlled sandbox credentials,
  remains in `test` mode, and has a signed endpoint for supported webhooks.
- A real mobile sandbox purchase proved a $274 order ($199 Web Basics plus a
  $75 revision), recurring consent, Payment Element completion, exact
  entitlements, customer receipt, and Fritz alert.
- Fulfillment now creates and links prospect, intake, and project records. A
  payment therefore becomes staff-operable onboarding work instead of stopping
  at an order and entitlement.
- The parallel prospect-token payment path is disabled outside localhost.
  Personalized links remain pre-sale proof routes; purchasing uses the branded,
  account-owned `/buy` and Drupal Commerce flow.
- `backend/config/famtastic-scenarios.json` is the canonical “what happens if”
  registry. `scripts/run-launch-gate.sh` produces dated, classified evidence.
- GA4 reporting is connected and returning real data. Personalized URLs are
  normalized before analytics dispatch so new token values are not reported as
  page paths.

## 2026-08-10 — Product factory and unified lifecycle

- Product configuration is incomplete until the customer-facing deal is also
  versioned. Store the full per-SKU promise and checksum with fulfillment; a
  title, price, and entitlement list cannot prove what was sold.
- Product setup cannot stop at a Commerce SKU and price. A valid product now
  requires billing, eligibility, entitlement, intake, fulfillment,
  communication, portal, upsell, reporting, acceptance, and launch definitions.
- Commerce fulfillment is now idempotent and SKU-driven. Completed orders join
  one permanent customer workspace; failed/refunded states are reconciled rather
  than treated as unrelated webhook events.
- Support and email are one timeline only when outgoing messages contain a
  thread address and inbound messages verify both the Message-ID and sender's
  organization membership.
- Worker success is not enough without heartbeat, bounded retry, dead-letter,
  overdue-case, lead-follow-up, project-staleness, renewal, and exception-summary
  visibility.
- Provider proof and fixture proof remain separately classified. The local
  lifecycle runner proves behavior; the Stripe sandbox checkout proves the
  payment provider; live activation is still an explicit gate.

## 2026-08-10 — Commerce Stripe sandbox proof

- Stripe authentication must be classified by environment, not merely by
  whether a connector responds. The installed connector exposed live mode, so
  all mutations were refused and an isolated Stripe sandbox plus official CLI
  test authentication were used instead.
- A payment foundation is not proven by a handcrafted webhook. The stronger
  proof is a real Payment Element browser checkout, a completed Commerce order
  and payment, and signed provider events accepted by Drupal.
- Test credentials remain runtime-only. Catalog scripts must verify
  `livemode=false`, be idempotent by SKU, and never contain a `--live` path.
- The review page exposed a noisy SQLite shutdown error after rendering, but the
  Payment Element and checkout still completed. Production uses MySQL; the
  SQLite-only failure remains a local test-runtime defect to eliminate rather
  than a reason to claim the full launch gate has passed.

## 2026-08-10 — Opportunity protection defaults

- Operational alerts initially route to `fritz.medine@gmail.com`, but the
  recipient and response deadline are editable at `/admin/famtastic/settings`.
- New public leads receive a three-day first-response deadline. Drupal cron
  sends one overdue alert and records the alert timestamp to prevent duplicates.
- A controlled Gmail message proved the connector can send to Fritz; production
  SMTP receipt and two-way reply ingestion remain separate proofs.

## 2026-08-10 — Customer proof pipeline hardening

- The canonical proof now covers account creation, captured verification email,
  verification, cookie login, organization-scoped workspace, preferences,
  support confirmation, and durable evidence—not only payment.
- The journey found two dormant portal defects: a Merge query used `key()` with
  an array, and FAQ retrieval sorted on a field that is not installed.
- Revision add-ons were incorrectly granting another base website and hosting
  entitlement. They now remain in order history without duplicating services.
- Transactional email now has deterministic memory capture for safe tests.
  Quote/contact submissions acknowledge customers; support requests notify
  staff and acknowledge customers without losing a saved request if mail fails.
- Commerce and the custom proof checkout remain separate financial paths.
  Catalog consistency is proven; Commerce-order and Stripe test-mode convergence
  remain launch gates.

This is the canonical record for reusable production discoveries and product
lessons. Implementation facts still belong in their subsystem runbooks and
architecture decisions; this file captures what future work must remember.

## Customer lifecycle portal

- A prospect-access link is valuable before purchase because it removes account
  friction and safely scopes a personalized proof. It is not a durable customer
  identity and should not carry a lifetime account relationship.
- Customer retention requires one identity that survives multiple campaigns,
  purchases, projects, domains, and services. Drupal user records authenticate;
  separate customer and organization records model the commercial relationship.
- The portal is a retention surface: status, files, approvals, billing, support,
  renewals, and contextual offers belong together. Upsells should follow facts
  about what the customer owns and needs, not appear as generic advertisements.
- Customer-facing language and URLs must not reveal Drupal or ask customers to
  enter the CMS administration interface.
- A horizontally scrolling mobile navigation must be contained by `min-width: 0`
  at every grid/flex boundary. Without that containment, the navigation's
  min-content width can silently make the entire page desktop-width on a phone.
- A portal section is not complete when its summary data renders. QA must follow
  the action through its full read/write loop—for example: list a conversation,
  open it, reply, refresh it, and verify authorization failures.
- Organize the customer portal around customer jobs—get help, understand owned
  services, learn, grow, and manage the relationship—not around internal product
  tables. A grouped hamburger drawer supports future capabilities without
  reserving mobile space for services the customer does not own.
- Content is a retention surface. Published Drupal articles and FAQs should
  appear inside the authenticated experience, while topic subscriptions and
  promotional consent remain explicit and independently customer-controlled.
- Referrals must be durable and privacy-safe: require permission confirmation,
  avoid exposing the referred person's activity, and store only what the reward
  lifecycle actually needs.

## Commerce and recurring services

- Website intake must recommend from needs, not acquisition price. A campaign
  may introduce $199, but page count, ecommerce, integrations, risk, and desired
  outcomes decide whether the customer receives Web Basics, Business Website,
  or a custom scope review.
- Relationship pricing is safest as an expiring, account/request-scoped offer.
  Preserve list and offered prices in the Commerce snapshot; reusable public
  discount codes are the wrong primitive for a one-customer promise.
- The responsive intake proof at 390×844 rendered 41 controls with no horizontal
  overflow (`body.scrollWidth` 375 at an inner viewport width of 390). Mobile QA
  must still validate the browser-visible form, not infer usability from CSS.
- A Commerce order item's constructor price is recalculated from its purchased
  variation unless the special unit price is explicitly marked overridden.
  Account-scoped prices must call `setUnitPrice($price, TRUE)` and retain their
  list/offer snapshot for audit.

- The $199 Foot in the Door offer is a normal Commerce product reachable from
  any acquisition source, not a campaign-only transaction path.
- Domain ownership and hosting service are distinct. The customer owns the
  domain; FAMtastic manages hosting. Existing-domain customers must never be
  charged an unnecessary domain renewal.
- The first hosting year is included. Continued basic hosting is a separately
  disclosed $9.99 monthly subscription with explicit authorization, advance
  notice, cancellation, retry, and grace handling.
- Analytics access is modeled as an entitlement so its price and package rules
  can change without rebuilding the portal.

## Drupal production assets

- Drupal CSS/JS aggregation can fail when generated files under
  `sites/default/files` are denied by the host. A styled source page with 403
  aggregate assets is an asset-delivery problem, not a theme regression.
- On this host, disabling aggregation restored all admin stylesheets. Retain the
  known-good file permissions and verify stylesheet response codes after cache
  or deployment changes.
- A technically complete reporting page is still undiscoverable if the staff
  landing page is named for one subsystem. The Drupal operations home should
  route by staff job, while detailed campaign and website analytics reports
  remain separate to avoid mixing unlike metrics.
- Analytics page-view events must redact personalized route tokens and
  verification/recovery query parameters from both `page_path` and
  `page_location`; client-side routing otherwise leaks secret-bearing URLs into
  third-party reporting.

## Deployment discipline

- Git `main` and an exact clean commit remain the only deployable source.
- Frontend and backend deploy through the checked-in scripts; never edit the
  public document root or production module files directly.
- Runtime dependency additions require a platform backup and migration, not
  only a custom-module code deployment.
- Proof means browser-visible behavior plus server/API evidence, not a successful
  upload alone.
- A React catch-all can make missing crawler files look healthy by returning
  HTTP 200 with application HTML. Acceptance must verify the content type and
  XML/text body of `/sitemap.xml` and `/robots.txt`, not only their status code.
- Composer declarations are not production capability. Backend deployment now
  backs up the live dependency tree and installs the exact locked tree with a
  rollback path before enabling modules. Building a duplicate dependency tree
  on inode-limited shared hosting can fail even when disk-capacity checks pass.
- After first-time module discovery on this host, sitemap generation requires a
  fresh router rebuild and an explicit route assertion before queue processing;
  otherwise Simple Sitemap can fail on a stale XSL route cache.
- A primary-route sitemap is not enough for a content-driven React frontend.
  The production build now discovers published Drupal service, package, work,
  and blog aliases, emits route-specific canonical shells, and includes those
  aliases in the public XML sitemap.
- Account-required Commerce checkout closes the financial-source-of-truth seam:
  customer, organization, selected SKUs, domain branch, approved terms,
  recurring authorization, and marketing choice are captured before handing
  the same order to Drupal Commerce checkout.

## Six-direction proof production

- A creative benchmark needs an explicit mix, not only a count: one restrained,
  one medium, and four ultra-FAMtastic directions prevents six high-energy
  palette swaps and preserves a usable comparison baseline.
- Multi-project continuity should be proved with one customer identity and
  unique request IDs. Three simultaneous local fixtures produced 18 unique
  HTML/artifact hashes without collapsing into one project.
- Full-page desktop/mobile screenshots plus deterministic browser checks are
  necessary but not sufficient. A separate reviewer must inspect the actual
  renders, record dimension scores, identify advisories, and leave customer
  approval unresolved.
- Unselected proofs are valuable inventory, but retention is not publication.
  Archive and hash every package; de-identify the candidate catalog; block
  customer copy/assets; and require owner/rights review before portfolio use.
- Model ledgers must state the real boundary. Separate specialist sessions,
  managed image generation, and Playwright are repeatable roles, but they do
  not equal six independent model providers or unattended production proof.
# Autonomous preview-to-Site-Studio bridge learning — 2026-08-18

The reusable seam is an immutable packet boundary, not another build engine.
FAMtastic can own research-first previews, selection, commercial truth, evidence,
and customer operations while Site Studio keeps its existing recipe engine. The
four correlation fields (`packet_id`, `idempotency_key`, `request_id`, and
`project_id`) plus the selected direction set are the minimum safe continuation
contract.

The historical six-direction runs preserved excellent HTML, art, screenshots,
structured outputs, and review decisions, but not complete conversational
prompts, real costs, or real durations. New runs must journal the rendered prompt,
normalized input, raw and parsed output, route, fallback, cost status, and review
decision at creation time. Retrofitting those facts later creates fake telemetry.

Installed CLIs are not authenticated providers. Capability routing must mark
command presence, authentication, runtime capability, and fixture adapters as
different states. Golden replay is valuable for deterministic regression but
cannot certify fresh creative autonomy. Likewise, a locally generated Site
Studio success fixture proves validation and portal continuation, not Site
Studio execution.

Supporting one or two chosen directions belongs in the packet contract; it does
not require duplicating projects. Multiple concurrent customer projects remain
safe when every packet is registered on one owned project and every result is
validated against that project's exact packet before any activity or mail is
queued.

## 2026-08-21 — FAMtastic Concierge is a channel identity, not a new source of truth

- FAMtastic Concierge is the customer-facing communication identity. FAMtastic
  Connections is the shared operational view of the lead and status timeline;
  Drupal remains the customer, Commerce, project, and delivery source of truth.
- Public Solution Finder capture records `concierge.lead_received` after the
  Prospect and Intake persist. A timeline failure is logged but must not reject
  or discard the customer submission.
- Inkbox callback handling is metadata-only: the receiver verifies
  `X-Inkbox-Request-ID`, `X-Inkbox-Timestamp`, and `X-Inkbox-Signature`, rejects
  replay-window failures, uses the provider event ID as the idempotency key, and
  writes the channel, direction, delivery state, provider IDs, hashed contact,
  and matched Prospect. Customer message bodies stay in the channel inbox.
- Concierge has no authority to auto-send, set a price, issue a grant, charge,
  buy a domain, or publish/deploy. Those remain human approval gates with the
  existing deterministic lifecycle services.
- Current evidence is locally proven for the signature verifier and code path.
  Live deployment, Inkbox webhook subscriptions, real signing-key storage, and
  a clean canonical customer proof remain separate launch gates.

## 2026-08-22 — Shared marketing skills must be pinned supporting methods

- A global agent-skill installation does not preserve FAMtastic behavior for
  Shay, Claude, or the next Codex session. Project-shared copies, a source
  commit, individual content hashes, and a compact product-marketing context
  make the method retrievable and auditable across agents.
- Upstream marketing skills improve task routing, but they do not create a
  second product catalog, customer database, pricing authority, or publishing
  lane. FAMtastic's evidence registry, product/terms records, Build DNA, and
  approval rules always override generic advice.
- Keep the installed core narrow. Conversion, intake, RevOps, campaign,
  discovery, and measurement skills cover the current gaps; outreach, email,
  spend, and customer-data execution skills remain explicit on-demand choices
  until their authority and lifecycle proof are reviewed.

## 2026-08-27 — Signed proof images are evidence, not public files

- A proof HTML page and its image bytes have different access boundaries. Store
  each explicitly declared image under a denied asset subtree, freeze its
  normalized SHA/MIME/path/size manifest with the room, and rehash it through a
  signature-scoped controller on every request. Never solve relative image URLs
  by rewriting the stored HTML or by exposing a static `/proofs/.../assets/...`
  path.
- For the `verified_cold` lane, image evidence is a quality gate: every frozen
  direction needs at least one asset and every asset SHA must already exist in
  the registered Build DNA manifest. Assetless legacy rooms remain a deliberate
  compatibility lane, not a reason to weaken new cold proof checks.
## 2026-08-24 — Catalog truth must live in exactly one place (and reconcile at deploy)

- Prod Commerce held 14 variations while `famtastic-products.json` advertised 16; the $499 tier was unsellable and checkout died with `product_unavailable` AFTER intake+proofs. Rule: backend deploys run setup-commerce.php + fail on advertised≠sellable SKU drift. Never add a SKU to config without seeding, or vice versa.

## 2026-08-24 — Validators must exercise the money step, not simulate it

- e2e-autonomous-journey asserted checkout URL + totals then force-transitioned state and called fulfill() directly. Zero strangers had ever paid. Rule: payment coverage = real gateway interaction (test mode or owner-executed $1 live purchase); label claims "fulfillment-proven" vs "checkout-proven" distinctly.

## 2026-08-24 — Admin presentation must be generated from system state

- Calendar hardcoded "0/4 approved" + static times; attention items were bold text styled as buttons; badges shipped without CSS rules. Rule: every number shown in /admin/famtastic comes from a query; every action-styled element is a real link/form; new CSS classes ship WITH their rules (deep audit script enforces).

## 2026-08-24 — Check claims against artifacts before writing them down

- Two false-blocked/false-absent claims in one day ("Meta OAuth never completed" — Integration table had facebook live; "Stripe step blocked" — price IDs existed in .artifacts/stripe). Rule: grep artifacts + DB before declaring anything blocked/missing.

## 2026-08-24 — Deploy lanes are separate; say which one you mean

- "Fully approved" executed frontend-only because backend deploys through a different primitive (backups+updatedb). Rule: launch approvals enumerate lanes explicitly; agents confirm lane coverage before reporting DONE.

## 2026-08-24 — Drupal table cells need 'data' wrapping for renderables

- Bare Link::toRenderable() as a table row cell explodes Attribute rendering (500 blank pages). House pattern: $this->linkCell(Link::...) everywhere.

## 2026-08-24 — Drush/php quirks that cost time today

- drush php:script treats explicit exit() as abnormal termination (use return).
- drush sqlq appends trailing blank line (tail -1 grabs emptiness — use grep -m1 .).
- uli URLs use host 'default' (strip scheme+host, not 127.0.0.1).
- php:script extra args arrive as $extra[], not $args[].
## 2026-08-24 — Commerce promotion/coupon gotchas (headless)

- commerce plugin-item fields need target_plugin_id/target_plugin_configuration keys; flat arrays silently drop the offer and promotions become inert.
- Per-customer usage limits make promotions unavailable to orders WITHOUT an email — headless probes must set mail.
- Coupon entities default their own start_date; timezone skew can leave them future-dated and inert for hours. Always pin start_date explicitly.
- Headless total verification: set coupons field -> commerce_order.order_refresh -> save -> loadUnchanged -> getTotalPrice().

## 2026-08-24 — The tree can be dirty again one run after "clean"

- 17:33Z heartbeat recorded a clean tree; at 19:35Z `restart-postiz-tunnel.sh` carried an uncommitted +18 env-merge block whose mtime (~17:55Z local) postdates that claim — someone/something (Fritz or another agent session sharing the repo) edited files outside any tracked dispatch. Rule: every heartbeat re-runs `git status` itself; never inherit "clean" from the previous run's claim. Unknown-provenance diffs get syntax-checked + safety-reviewed, then flagged for Fritz — never committed by the CEO.

## 2026-08-24 — Portal crawler lessons (admin-cx first assignment)

- Vite dev proxy covered `/jsonapi`, `/api`, `/oauth` but NOT `/session` —
  `src/api/customer.js` fetches its CSRF token from `/session/token`, so every
  CSRF-protected portal action (draft save, thread create, profile save)
  silently failed in local dev while working in prod (same-origin `/web`).
  Rule: when adding a same-origin Drupal path to `api/*.{js,customer.js}`, add
  its prefix to the vite proxy list the same commit.
- The Messages nav button's accessible name includes the unread badge
  ("Messages 2"), so Playwright locators must match by prefix, not
  `exact: true`.
- In bash validators under `set -e`: a `grep -m1 .` pipeline returns 1 on empty
  input — every drush sqlq wrapper needs an explicit `|| true` or DELETE-style
  calls kill the script mid-cleanup; and an EXIT trap that can fail flips a
  scripted `exit 0` into rc=1. Guard trap bodies.
- Eight CustomerPortalDashboard sections (activity, performance, support,
  learn, faq, grow, referrals, settings) render code paths but no affordance
  reaches them: GROUPS nav exposes only six sections and `?section=` accepts
  only those. Dead surfaces are a fake-affordance class defect; nav change is
  an owner decision, flagged not fixed unilaterally.
## 2026-08-24 — Structure crawls pass while geometry fails: card grids need overlap assertions

- Portal Projects rendered five proof-review panels in auto-fit columns, each crushing a fixed 3-column concept grid into ~90px cells — titles overflowed, badges scattered, cards overlapped. The text/structure crawler passed every check. Rule: any card-grid surface gets geometric assertions (sibling bounding-box intersections > 4px² fail; scrollWidth ≤ viewport+2) in e2e-portal-links.sh, and new grids default to fluid `repeat(auto-fit,minmax(min-card,1fr))` instead of fixed column counts.
## 2026-08-24 — muapi.ai integration facts

- Key lives in macOS keychain (service `muapi-cli`, account `api-key`) — retrieve with `security find-generic-password -s muapi-cli -w`; never print or commit. The `muapi` CLI binary is NOT installed despite setup output suggesting it; call the REST API directly.
- flux image endpoints require width/height as multiples of 64 (128–2048); 1000-height fails with invalidHeight.
- Generated UI mockups carry AI-gibberish text — use them as layout/hierarchy comps, never as copy specs.

## 2026-08-24 — Domain verification can leave TWO live artifacts; check before calling it drift

- Rotating TikTok domain verification across methods left both live at once: the
  deployed file (`tiktokjUD1….txt`, HTTP 200 on famtasticdesigns.com) from the
  file method AND a different token in DNS TXT (`Yul3…`, apex + www) from the
  newer preferred DNS method. Different prefixes across commit message, repo
  file, and DNS is the intended end-state of "DNS preferred", not repo↔prod
  drift. Guidance: identify which method each artifact belongs to before
  rotating or deleting anything; only retire the deployed verification file via
  a normal frontend deploy AFTER the portal confirms DNS-method verification.
- Heartbeat practice that caught this: before adopting an uncommitted doc edit
  into the ledger, verify every factual claim against live systems (here:
  `dig TXT` both hostnames, `curl` of the deployed file, `grep` of the cited
  htaccess rule). Provenance-by-context plus independently verified claims is
  the standard for adopting handoff work; neither blind trust nor blind rejection.
## 2026-08-24 — NotebookLM is wired for company research

- notebooklm-py venv at `~/Development/FAMtastic/tools/nblm-venv`; auth = Chrome cookie extraction (`browser_cookie3.chrome(domain_name=".google.com")` wrapped in `httpx.Cookies`) → `_fetch_tokens_with_jar` → `save_cookies_to_storage(jar, original_snapshot=snapshot, path=~/.notebooklm/profiles/default/storage_state.json)`. Signed in as fritz.medine@gmail.com.
- Runner: `scripts/notebooklm-deep-dive.py` — creates the growth notebook, uploads planning/audit docs, asks score-improvement questions, saves to `docs/research/`.
- Gotchas: `research.start` web mode failed (likely premium-gated); `save_cookies_to_storage` is SYNC and needs original_snapshot; cookie refresh will be needed periodically (re-run login flow when 401s appear).

## 2026-08-25 — `.git/info/exclude` is a blind spot in every "clean tree" sweep

- Observation: heartbeat census of C6 found three never-tracked files
  (`PreviewRunnerCallbackController.php`, `FamtasticPreviewRunnerClient.php`,
  `tests/fixtures/preview-runner-router.php`) hidden from `git status` AND from
  ripgrep via three `.git/info/exclude` lines (fixture path listed twice).
  Mtimes (2026-08-24 17:35 local) post-date the audit that declared the stack
  dead — i.e., an unknown session wrote WIP and then silenced status on it.
  This is the second provenance-unknown incident (after restart-postiz-tunnel.sh,
  flagged 2026-08-24T19:40Z, resolved when its operator committed with docs).
- Guidance: `git status --short` and rg are blind to info/exclude by design.
  Heartbeat orientation must include `cat .git/info/exclude` or
  `git status --ignored --short <custom-modules>`; any non-default entry there
  is a flag requiring owner confirmation, not silent background state.
  Provenance rule unchanged: unknown-provenance work is flagged to Fritz and
  left untouched on disk — never deleted, completed, or committed by CEO.
