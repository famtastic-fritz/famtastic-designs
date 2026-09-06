# Product changelog

## 2026-09-06 — Web Basics naming and proof-ready email contract were standardized

- Moved the $199 offer text into a shared `webBasicsOffer.js` copy source so
  the page, portal, purchase flow, and option selector all say the same thing:
  `Starter Mobile Business Foundation (Web Basics)`.
- Added a visible “see it in motion” video section to the $199 page so the
  offer can explain itself with short films, not only text blocks. The page now
  treats the offer as a product story with proofs, research, and terms instead
  of a bare price card.
- Upgraded the proof-ready customer email registry entry to the new branded
  CTA contract, and aligned the mailer/test pair with a proof-set-open action
  instead of a raw “review directions” prompt.
- Kept the change scoped to design/copy/email presentation. No portal
  lifecycle, payment, domain, or deployment behavior was changed by this pass.

## 2026-09-06 — Portal projects and admin surfaces got a clearer control plane

- Reworked the customer project experience so the proof-ready state is explicit
  and the research/growth plan stays visible instead of hiding behind a dead-end
  summary. The proof email is framed as the doorway; the portal is where the
  three concepts are actually compared.
- Added actionable attention routing from the home dashboard and project detail
  to support/issue resolution, instead of leaving "needs attention" as a static
  label. Support is now a first-class next step.
- Gave the staff admin dashboard a dark hero and live summary strip so it reads
  like the same product family as the customer portal on mobile and desktop.
- Verified the client-portal DNA, a public-flow smoke, and the production build
  after the change set. No backend, payment, or production deployment behavior
  was altered by this UI pass.

## 2026-09-06 — Tighten Up Your Locs replacement proof set delivered

- Completed the existing request-12 replacement campaign rather than creating
  another campaign: three new, mobile-checked directions—Your Loc Journey,
  The Open Chair, and Royal Roots—are now attached to campaign 47.
- Saved the fresh customer-readable research snapshot, feedback lesson, source
  list, and 30/60/90-day growth path before customer delivery. The directions
  explicitly replace the rejected analytical experience with a warm,
  plain-language, self-contained care-request path; they do not link back to
  Booksy or claim a live calendar.
- Confirmed the authoritative customer recipient, corrected the account display
  name from an email address to Shay, and recorded an SMTP acceptance receipt
  for the versioned Concierge proof-ready notification. This is proof-review
  delivery only: no payment, domain, deployment, or booking action occurred.

## 2026-09-06 — Operations attention is now an actionable mobile queue

- Replaced the static Operations Home “Needs attention” count with a direct,
  tappable attention queue. It names the live open conversation subjects and
  opens the exact record instead of leaving the owner at a dead-end summary.
- Added phone-first attention and conversation views: each record states its
  real type, current context, update time, complete message history, and a
  reply action only when a real support case exists. Open project
  conversations are no longer mislabeled as support cases.
- Reworked Customer Replies from a horizontally overflowing raw table into
  readable inbound-email cards. Matched replies link to their conversation;
  unmatched replies clearly remain in the queue for connection.
- Removed the generic “desktop is safer” mobile tutorial from Operations. Each
  task screen now supplies its own concise context ahead of its actual action.

## 2026-09-06 — Profile routes use the administrative theme

- Marked the canonical authenticated user profile as an administrative route
  using a Drupal route subscriber. Drupal's native theme negotiator now selects
  `famtastic_admin` for profile view/edit/cancel pages rather than the public
  Olivero customer theme. This
  removes the duplicate public header beneath the toolbar on mobile and gives
  account-management pages the same dark, responsive admin surface as the rest
  of Drupal administration. Public sign-in, registration, and reset pages keep
  the customer theme.
- Added mobile-safe account-title sizing/wrapping in the admin theme so an
  email-based display name stays readable rather than creating a narrow,
  broken column.

## 2026-09-06 — Client proof revision, portal focus, and Concierge template standard

- Added root `design.md` as the shared FAMtastic Experience System. It now
  governs customer portal, proof, Site Studio, and transactional-email work
  alongside the specialized Design DNA and component contracts.
- Reworked the authenticated Projects experience from an all-in-one command
  center into a projects list and one-project detail with Today, Concepts,
  Research & growth plan, Setup, and More. Internal Build DNA is no longer a
  customer-path control; revision state cannot expose a selectable proof action
  that the server will reject.
- Added an owner-confirmed, auditable pre-purchase proof rebuild action and
  `famtastic:website-request-proof-rebuild`. It retains the rejected campaign,
  resets only the request-level design-reset allowance, records the reason and
  customer feedback, and queues a new proof job without email, payment, domain,
  or deployment side effects.
- Extended proof research snapshots with a customer-facing 30/60/90-day growth
  plan and feedback lesson. New intakes receive an unreserved proposed domain
  candidate from the business name, which customers can confirm or edit before
  any registration/connection action.
- Added Concierge `customer_revision_received` v1 and upgraded
  `customer_proof_ready` to v2. Both use a named graphical CTA and omit opaque
  portal URLs from the visible HTML body while retaining the plain-text outbox
  receipt and template version.
- Local verification passed PHP lint, focused 9-test PHPUnit set (61
  assertions), frontend production build, Portal Design DNA 30/30, and the
  portal link/crawl audit. This entry is not a production deployment or a new
  customer proof/email receipt.
- Hardened the frontend deployer to install its required Vite build tooling
  with `npm ci --include=dev`; a host production-mode npm setting otherwise
  omitted Vite and correctly stopped the promotion before public files changed.

## 2026-09-06 — Owner-authorized flood arm and Sep 5 backfill

- Opened the four September 6–13 campaign gates under an explicit
  `publish_arming.owner_override` after the owner accepted duplicate/stale-time
  risk. The live Postiz read-back adopted the existing 24 drops without
  creating a second copy; the four lanes contain 48 channel placements in the
  source plan.
- Added two one-channel Sep 5 backfill retries for the failed Cost Is Not The
  Reason drop, spaced at 04:15 and 04:45 ET. Facebook and Instagram accepted
  the retries; the original YouTube and TikTok misses remain blocked by an
  expired YouTube token and TikTok public-posting approval.
- Fixed the scheduler's sibling read-back to group by the exact UTM campaign
  and content marker, not timestamp. A retimed provider row can retain its old
  timestamp, and timestamp grouping previously risked scheduling an unrelated
  campaign's channel. The read window now includes two days of slack for
  deliberate provider/source retimes.
- This is a real provider action: Postiz records were read and scheduled; no
  customer email, checkout, domain, or deployment action was involved.

## 2026-09-05 — Campaign schedule ownership and lifecycle clocks made explicit

- Corrected the four September 6–13 campaign source schedules without making a
  Postiz request: six cross-campaign recorded-provider-ID collisions and three
  exact planned-time collisions are now removed from source. All 24 target
  drops retain `approval.publish=false`.
- Four corrected drops carry `provider_reconciliation: draft_retime_required`.
  This is intentionally not a provider-state claim: it says the source plan
  changed and a reviewed provider-draft retime is still required. The generic
  queue runner now refuses to adopt, queue, or schedule those drops; its
  explicit retime path also requires `--confirm` before it can touch a provider.
- Added the no-network `scripts/validate-campaign-schedule-hygiene.py` guard.
  It checks provider-ID ownership, exact timestamp uniqueness, closed publish
  gates, and reports pending provider reconciliation separately from a pass.
- Marketing Command Center now labels the social source plan as a source plan,
  not a live scheduler read-back, and adds per-campaign local-ledger clocks for
  draft/staged, queued, sent, replied, and paid. Fixture/smoke campaigns are
  labelled. Unlinked inbound support mail is shown as not campaign-attributable
  rather than as a fabricated zero replies.
- Local verification passed the four schema validations, schedule-hygiene
  validator, queue-runner dry run (no Postiz contact), PHP lint, Python compile,
  and whitespace check. No outreach email, social publication, scheduler state,
  provider record, or production system was changed.

## 2026-09-05 — Mobile project handoff now names the next owner and action

- Reworked the customer Projects surface around one question: “What do I do
  next?” Every request now says `Your turn` or `FAMtastic's turn`, with a
  single state-derived action for finishing the brief, choosing a direction,
  paying, or waiting for the studio.
- Research remains attached to the proof set but opens progressively; each
  direction carries its own research rationale. On phones, the three concepts
  use a swipeable snap rail while domain/hosting, files, sharing, and Build DNA
  stay available behind plain-language disclosures.
- Added customer-controlled Archive and Restore for unwanted request cards.
  Archiving retains the brief, research, proofs, files, selection, payment
  history, and workflow status; no record is deleted or work cancelled.
- Selection, payment, and build are now explicit separate gates. Email remains
  notification only and no customer message, charge, deployment, or selection
  was triggered by this change.

## 2026-09-05 — Completed deep dives now enter the three-proof handoff

- A verified, claimed deep dive now advances its linked account-owned website
  request to `submitted` and queues exactly one proof job for the normalized
  brief. Existing requests are resumed idempotently with a brief hash, so a
  stale failed job cannot block the completed customer brief.
- Added the owner-only `famtastic:deep-dive-proof-resume` command with exact
  request confirmation. It repairs the request/queue boundary only; proof
  generation, owner approval, email, payment, and deployment remain separate
  gates.
- The customer portal now honors the request UUID in the review link and
  safely lands on that request's proof room without a missing-session crash.
- Focused PHP contract tests, PHP lint, frontend production build, portal
  Design DNA validation, and the three-direction proof package check pass.
- Added `scripts/e2e-deep-dive-proof-handoff.sh` to the canonical acceptance
  suite. In a local Drupal runtime it proves completed brief -> submitted
  account request -> exhausted-job recovery -> three owner-approved directions
  -> customer selection, without dispatching email, charging, or deploying.

## 2026-09-05 — Catalog payment eligibility now fails closed

- Every one of the 16 published catalog services now declares its payment mode,
  customer-safe availability message, and server-enforced requirements. The
  Commerce checkout rejects a website bundle without its selected proof/request,
  project-only work without an owned active website/project, and all four
  recurring items from the generic one-time checkout.
- The customer purchase and portal service surfaces consume the same contract:
  unavailable direct links no longer silently fall back to a different package,
  renewal products route to billing rather than an order button, and
  project-only work routes to its project.
- Added a no-provider-call Stripe sandbox matrix covering the 12 one-time and
  four recurring catalog products, shared success/decline/3DS/abandon/replay
  evidence, and a fresh-runtime-only exact-account/exact-request $1 private
  offer fixture. It is a proof plan, not an active offer or a payment claim.
- Local proof: 96 PHP unit tests / 539 assertions, catalog/matrix validation,
  the disabled provider-run guard, and production frontend build passed. No
  Stripe API call, charge, send, deployment, or customer record was created.

## 2026-09-05 — Four-campaign flood queued, a queue-collision bug and a schedule gap fixed

- **Queued 48 posts across four campaigns** (`booked-and-losing`, `already-know-the-game`,
  `see-it-first`, `ive-managed-fine`) spanning 2026-09-06 through 09-13 on Facebook
  and Instagram, verified by reading the Postiz database directly rather than
  trusting `queue-campaign-drops.py`'s own report.
- **Found and fixed a real bug in that script**: it adopted a prior draft by its
  `utm_content` marker alone. All four campaigns numbered their drops
  `drop-01..drop-06`, so the second campaign queued silently adopted the
  first's live records and reported `PASS — adopted=6` while scheduling
  nothing of its own. Adoption is now scoped to `utm_campaign` + `utm_content`.
- **Found and fixed a schedule gap**: every campaign's first drop was scheduled
  for the next morning, leaving a 24+ hour silent gap after the last real
  publish. One drop per campaign was pulled forward to fire the same evening.
- Two shared-template bugs fixed upstream during the build, both of which
  would have hit every campaign lane: `famChip` ignored its own letter
  tracking (rendering "WEB BASIC|S"), and concept objects could clip the
  canvas edge.
- Whole-flood measured spend: `booked-and-losing` $0.504, `already-know-the-game`
  $0.3024, `see-it-first` $0.00, `ive-managed-fine` $0.00.
## 2026-09-06 — Canonical customer proof now runs from a fresh Drupal runtime

- The canonical customer-proof entry point now delegates to a disposable
  Drupal/SQLite runtime by default. It copies only the needed source and a
  matching vendor runtime, proves the full synthetic intake → research → three
  proofs → selection → signed checkout contract/webhook → fulfillment →
  portal/Owner Desk path, then removes the runtime.
- Each passing run retains a redacted `famtastic.fresh-customer-journey.v1`
  evidence bundle under `.artifacts/fresh-customer-proof/`, with the source SHA,
  safety profile, and linked journey/lifecycle evidence. The wrapper refuses
  live provider/transport values; no email, booking, charge, domain mutation,
  deployment, or production database is used.
- Fresh-install coverage now includes the proof-version table and catches
  catalog/contract drift. The checkout contract map is keyed by SKU, website
  discovery registration includes its public request identifier, and hosting
  renewal consent is tied to the original launch tier’s immutable renewal
  contract and price.

## 2026-09-05 — Durable owner-only revenue freshness controls prepared

- Added migration `8054` and the `famtastic_revenue_freshness` ledger. It records a stable task key, source state, deadline, severity, current/open-or-recovered status, and recovery evidence for submitted requests, held proof states, selected-but-unpaid requests, stale projects, and missing release receipts. Reconciliation is owner-task-only: it does not send mail, start proof work, create checkout, charge, publish, or change the source request/project.
- Added `drush famtastic:revenue-health` (`frh`) for structured
  `famtastic.revenue-health.v1` output; `famtastic:lifecycle-run` now refreshes
  this ledger through protection and includes its summary in the cycle record.
- Generic outbox delivery now performs a conditional atomic `dispatching`
  claim with a claim token before provider handoff, conditionally writes the
  receipt against that same token, and recovers only an abandoned claim after
  the bounded timeout. This prevents two concurrent generic workers from
  sending the same candidate at once; it does not broaden any mail authority.
- Focused PHP lint and the 91-test/462-assertion unit suite pass. The isolated
  original fresh-install blocker was the inherited PHP 8.5 `$configFactory`
  collision, now repaired in the booking controllers. The harness now mirrors
  the matching Drupal runtime beside a supplied Composer vendor tree and passes
  a fresh isolated install, module schema, revenue freshness recovery, offers,
  and terms check.

## 2026-09-05 — Verified revenue-loop completion gates tightened

- Website Commerce now carries the captured per-SKU `famtastic.offer-contract.v1`
  snapshot into fulfillment and fails closed if it is absent, so later catalog
  edits cannot silently rewrite a paid order's scope.
- Added the account-owner payment-handoff controls in the Growth portal and a
  first-party `/pay/:organization/:siteKey` consumer. QR-only remains usable;
  an outbound destination and `opened` event exist only when the owner supplied
  an optional fallback. None of these events represents payment or revenue.
- The isolated payment-handoff HTTP acceptance now passes from a fresh Drupal
  install, including update 8055, authorization/isolation, QR/Cash App/HTTPS
  validation, and explicit non-purchase semantics. No provider, charge, send,
  deployment, or live customer action occurred.
## 2026-09-05 — Public website research and request-continuation clarity

- Normalized Drupal-owned public `/web` CTA paths to their frontend routes
  while retaining backend API, admin, checkout, session, and user paths. The
  production rewrite allowlist now includes the public `/start` intake route.
- Removed public homepage conversion and revenue counters that did not carry
  source evidence. Public website CTAs now lead to contained first-party
  research or the $199 / $499 scope comparison, never a direct purchase path.
- Made Solution Finder success contingent on the existing intake API returning
  `ok` and a durable request ID. A failed save keeps the visitor's answers,
  exposes an error and retry action, and offers account continuation only when
  the server returns its registration URL.
- Added the public Starter ($199) versus Business Website ($499) comparison as
  a read-more experience. It describes only catalog-backed starting scope and
  directs visitors into research; an account-owned request and selected
  direction remain prerequisites for website checkout.
- Website purchase UI now treats the server-returned linked request as the
  eligibility signal, presents month-13 renewal as optional, and does not
  represent a public direct checkout or automatic recurring authorization.
- Verified locally with the public-flow contract script, portal design-DNA
  validator, production build, and mobile/desktop browser tests using mocked
  API responses. No deployment, payment, or customer communication occurred.
## 2026-09-05 — Customer-owned payment handoff, not a second checkout

- Added the reusable, organization-scoped `PaymentHandoff` module for Starter
  sites and Owner Desk. An active organization owner can configure only four
  deliberate states: disabled, direct Cash App, an existing QR image (with an
  optional accessible HTTPS fallback), or a generic HTTPS payment link.
- Public configuration is absent until the owner enables it, and a public
  render must pass the existing exact converted-request → booking-site →
  organization boundary. The public model exposes only the handoff destination,
  label, instructions, and a disclosure that it cannot confirm payment, create
  an order, or reserve a service.
  `viewed` and `opened` are anonymous, append-only interaction events—not
  purchases, payment attempts, or payment verification.
- Added update `8055`, the owner/private and public handoff APIs, and a fresh
  SQLite HTTP acceptance test. It exercises owner-role and
  cross-organization/site-binding isolation, disabled clearing, scheme-less
  HTTPS normalization, QR/Cash App/generic-link
  validation, and explicit non-purchase event semantics. No provider call,
  merchant credential, charge, deploy, or send occurred.
- Repaired two existing PHP 8.5 module-boot fatals: Booking Request and Booking
  Availability controllers no longer redeclare ControllerBase's inherited
  `$configFactory` property as readonly. Their injected setting dependency now
  has a distinct private name, so the full module can load during acceptance.

## 2026-09-05 — The films get a home of their own at /watch

- **Published the campaign films on our own domain instead of waiting on
  channels.** Eight finished films (119 MB) now live at `/watch` and
  `/watch/<slug>`, served straight from `frontend/public/video/`. YouTube's
  OAuth token is expired and TikTok is not approved for public posting, so
  until today the whole rendered library earned nothing. It no longer depends
  on either: the channels become distribution, not a precondition.
- **Every film carries a real `VideoObject`.** `name`, `description`,
  `thumbnailUrl`, `uploadDate`, `duration`, `contentUrl` and `embedUrl` are
  written into a static `dist/watch/<slug>/index.html` at build time, because a
  client-rendered SPA's structured data is not reliably read. `duration` is
  derived from `ffprobe` against the shipped file, never typed by hand.
- **Four films ship a verbatim transcript; four honestly do not.**
  `borrowed-land`, `not-a-home-base`, `fifty-five-cents` and
  `two-different-jobs` have approved scripts in the repo, so their words are on
  the page and in the schema. `the-sign-that-isnt-there`, `the-dm-trap`,
  `found-then-kept` and `somebody-elses-app` are narrated (or scored) from
  sources with no companion text, so they lead with their on-screen copy and
  claim no transcript. Every film's full on-screen copy is written out either
  way, so the argument is readable without playing anything.
- **The library is styled as part of the blog, not as a microsite.** The film
  body is literally `.v1-panel > .v1-prose`, and the section art comes from the
  existing `lib/blogArt.js` recipes — `artDayCost` on the price film,
  `artScopeBoundary` on the business-email film, `artOwnedVsRented` /
  `artOwnershipMarker` on the platform films, and nothing but a divider on the
  one film that has not earned a diagram.
- **Added the two rules that would have made this 404 in production**: `watch`
  entries in `frontend/public/.htaccess` (the rewrite list is an allowlist with
  no catch-all) and a `!frontend/public/video/*.mp4` exemption in `.gitignore`,
  where a repo-wide `*.mp4` rule would otherwise have dropped all eight films
  from the committed SHA the deploy script builds.
- Not deployed. Built and verified in `frontend/dist`; production remains an
  explicit owner decision.

## 2026-09-05 — Creative production system, 15 posts live, and a delivery cycle that alerts

- **Registered Adobe desktop as a working capability, which it always was.** An
  unbranded campaign video shipped from an `ffmpeg` build lacking `drawtext`
  while Photoshop 2026 sat running with a live MCP bridge. Root cause was
  `marketing/providers.json` marking all seven Adobe rows pending because it
  described only the *cloud* APIs. The registry now separates desktop-under-MCP
  from cloud-entitlement, and `AGENTS.md` states the general rule: a pending or
  missing registry row is not proof a capability is absent.
- **Built a still system in Photoshop at zero marginal cost.** Five palettes
  argued from subject matter, seven layout archetypes, concept objects that
  carry the argument rather than decorate it, isometric solids on a perspective
  floor for real depth, and a parametrised template at
  `marketing/creative/templates/famtastic-social-frame.jsx`. 38 stills produced.
- **Two video systems, both graded to one measured anchor.** Remotion renders
  9:16/1:1/16:9 from one master; HyperFrames renders HTML compositions. Seven
  HyperFrames films plus three Remotion aspects. The grade is measured, not
  eyeballed: the HeyGen anchor is 155.4 mean luminance, and a Remotion cut that
  measured 212.1 was corrected to 163.2 rather than shipped.
- **Voice is no longer metered.** Voicebox installed headless (Python sidecars
  build; the Tauri GUI needs full Xcode and is not required). Three silent films
  narrated at $0. Skill at `.agents/skills/famtastic-voice/SKILL.md`.
- **15 blog posts published and deployed.** The corpus went 83 → 98, route
  shells 162 → 177. Eleven were reassigned to existing series rather than forced
  into a new one, because `series_facts()` derives `evidence_boundary` and
  `sources` from existing members and those are claims governance, not fields to
  invent. "The Own Your Online Presence Series" is defined but unpopulated,
  pending an owner decision.
- **Whole campaign spend: about $1.** 21 Gemini plates at $0.7056, one
  gpt-image-2 flagship anchor at ~$0.18, 12 HeyGen credits. Everything else ran
  on tools already paid for.
- **Added `scripts/delivery/run-cycle.sh`, scheduled on launchd at 07:12 daily.**
  It reads queue depth from Postiz, audits every live link, finds content gaps
  and exits 3 when runway falls below three days. Its first run exited 3 against
  an empty queue — which is the point: this repo held 98 posts, 12 films and 38
  stills with nothing scheduled and nothing saying so.
- **Delivery boundary, stated plainly:** drop-06 published to Facebook and
  Instagram — the first confirmed delivery from this pipeline. YouTube failed on
  an expired OAuth token and TikTok on an unapproved app. Both need the owner;
  neither is a code defect.


## 2026-09-05 — Customer proof handoff and mobile Owner Desk release candidate

- Consolidated the research-first proof handoff work into `main`: submitted website intake starts the internal proof lane; customer email is notification only; authenticated FAMtastic workspace remains the proof comparison and decision surface.
- Added owner-approved research rationale, three-direction proof review, bounded revision/reset requests, exact customer-to-site Owner Desk authorization, first-party request/availability controls, and the Tighten Up Your Locs mobile proof set. These controls do not book appointments, charge customers, publish availability, or connect Booksy.
- Proof research is now retained separately from mutable intake data, customer direction choice is final until an explicit pre-checkout owner reopen action, and the synthetic lifecycle test now covers those facts plus cross-customer owner-desk denial.
- Release evidence: source checks, Portal DNA, mobile recipe validation, and frontend production build pass. The full Drupal lifecycle remains required against an initialized isolated database before production deployment or paid-project diagnosis.

## 2026-09-05 — Three HyperFrames films for the offer and the two add-on products

- **Authored and rendered three 9:16 films from HTML, at $0 total.**
  `marketing/hyperframes/cost-is-not-the-reason/` (Fifty-Five Cents, 28.0 s, 840
  frames, 23.3 MB, rendered in 32.1 s),
  `marketing/hyperframes/services/business-email/` (Two Different Jobs, 31.5 s,
  945 frames, 17.7 MB, 52.4 s) and
  `marketing/hyperframes/services/local-seo/` (Found, Then Kept, 28.0 s, 840
  frames, 14.8 MB, 25.8 s). No image, voice, video or model call was generated:
  every input already existed on disk. Renders and staged media are gitignored
  and reproducible from `scripts/stage-assets.sh` plus `npm run render`.
- **All three graded to the anchor's measured appearance.** Measured on
  identical `signalstats` YAVG: the HeyGen anchor take is 155.4, the accepted
  platform-dependency film is 160.1, and these are 160.8, 159.8 and 163.1. Every
  second of all three sits inside the 150-175 band (0/28, 0/32, 0/28) and the
  olive accent averages 0.28 %, 0.58 % and 0.30 % of frame area.
- **The films de-bundle the offer instead of blurring it.** Live scheduled drops
  in `posting-schedule.json` still say the $199 bundle includes a branded
  business email address; `famtastic-products.json` says it is
  `FAM-BUSINESS-EMAIL` at $99. All three films state the separateness on screen,
  and Two Different Jobs is built entirely around the approved take-b narration
  that says it in the studio's own voice. All three also carry the $9.99/month
  renewal disclosure that `BRAND.md` requires and the existing campaign assets
  omit.
- **Found and recorded a doctrine conflict.** Four of the six shipped campaign
  palettes have near-black grounds and cannot satisfy the anchor's 150-175
  luminance contract; only `paper` and `anchor-take-a` can. The same constraint
  disqualifies all but six images in the existing plate library. Full tables in
  the two lane READMEs — this is an owner decision, not a design one.
- **Deferred:** Found, Then Kept ships silent (no approved narration for either
  product, no licensed music, no authorised spend) and Fifty-Five Cents is silent
  after 10.45 s. All three are portrait only; HyperFrames bakes aspect into the
  composition. `docs/CAPABILITY_REGISTRY.md` is unchanged — these are rendered
  and locally verified, not published, so no classification moves.

## 2026-09-05 — Platform Dependency campaign film rendered in HyperFrames

- **Authored and rendered a 29-second 9:16 campaign film from HTML, at $0.**
  `marketing/hyperframes/platform-dependency/` is a HyperFrames project (six
  sub-compositions plus a host) that reuses the existing gpt-image-2 anchor, four
  of the 21 Gemini plates, and the approved HeyGen Take A. No image, voice, video
  or model call was generated. Delivered `renders/borrowed-land-1080x1920.mp4`
  (870 frames, 30 fps, h264 + AAC, 14.9 MB, rendered in 31.1 s) and a 4K portrait
  variant (1 m 29 s). Renders are gitignored and reproducible.
- **Graded to the anchor's measured appearance rather than the brand spec.**
  Measured on identical `signalstats` YAVG: the HeyGen take is 162.3, this film
  is 165.8, and the existing Remotion 9:16 for the same drop is 212.1. All 29
  seconds sit inside the 150-175 band and the olive accent averages 0.30 % of
  frame area (peak 1.22 %), inside the 1-2 % budget.
- **Cuts land inside the narration's own pauses.** `silencedetect` never opens on
  the take (continuous room tone), so sentence boundaries were measured from a
  relative RMS envelope; every scene boundary sits in a real gap. The presenter
  inset is a muted `<video>` with a separate `<audio>` on the same file, verified
  lip-synced by frame comparison.
- **Deferred:** the film is portrait only. HyperFrames bakes aspect into the
  composition and refuses a square render off a portrait one, so 1:1 and 16:9
  remain Remotion's job. The last 10 s are silent — no licensed bed exists in the
  repo and no provider spend was authorised.
- **Found a defective source asset.** `pd-a1-vertical-9x16.jpg` has a blown-out
  flat rectangle covering the door's upper half; it is framed around here, but
  should be regenerated before reuse.

## 2026-09-05 — Deterministic local texture library

- **Completed the missing texture-library lane without creating a new paid
  generation run.** Five editable, text-free SVG sources now cover the active
  paper, ghost-town, salon, trades, and FAMtastic palettes. They are local
  composition ingredients for Photoshop, Remotion, and HyperFrames rather than
  substitute campaign imagery.
- **Added a no-network verifier and inspected rendered previews.** It confirms
  every manifest entry has a matching scalable SVG, matching viewBox, and no
  embedded text. The existing plate generator also passes syntax validation and
  a 95-entry dry run; its displayed $3.1920 would be a future queued-run
  estimate, not spend made in this recovery.

## 2026-09-05 — HeyGen presenter receipt recovery and local source-node audit

- **Recovered the missing completed Take B without generating anything new.**
  The existing provider record was read through the official CLI and its
  29.480-second 1080p local render plus five sampled frames were saved beside
  the existing Take A proof. The audit records script/render hashes, technical
  media facts, and a provider receipt without committing expiring delivery
  URLs or credentials.
- **Kept the quality claim honest.** Both takes are consistent illustrated
  presenter footage, but neither contains a composed campaign story, captions,
  campaign typography, CTA, treatment receipt, factual-audio review, or owner
  acceptance. They are explicitly reusable source nodes, not premium-character
  proof or publishable video.

## 2026-09-05 — Draft-only, persona-led platform-dependency series package

- **Prepared a reviewable seven-part series instead of bulk-producing unlinked posts.** The new local package contains a proposed title, explicit publication boundary, reader-persona hypothesis with research gaps, source notes, a complete part map, and an expanded local pillar candidate that points to the six prepared spokes.
- **Kept the writing people-first and evidence-bounded.** The pillar candidate cites current Google documentation only for crawl/index/serve and people-first-content mechanics; it makes no ranking, conversion, platform-fee, contact-right, pricing, or legal promise. It does not alter the live pillar or create a taxonomy term.

## 2026-09-05 — Platform-dependency draft metadata is restored without weakening release gates

- **Completed the six missing local draft packages.** Each platform-dependency draft now has a source-bound `brief.md`, structural `seo-check.json`, and its cluster-plan category/tag/keyword classification. The local metadata no longer prevents a validation run.
- **Kept the meaningful series gate intact.** The drafts still deliberately have no series/order because their proposed new ordered arc has no decided manifest facts or production taxonomy term. A dry run now stops there, before SSH, rather than treating a missing brief or SEO file as a reason to bypass architecture.

## 2026-09-05 — Specialist-agent and local-model routing is explicit

- **Confirmed the existing specialist registry instead of inventing a duplicate.** `docs/playbook/ROSTER.md` plus `.opencode/agent/` remains the authoritative roster for 13 named FAMtastic roles; project and shared skills remain capability references, while provider proof remains in `marketing/providers.json`.
- **Made local Ollama selectable in the evidence-first copy route.** The route catalog now returns the locally listed `qwen3:8b`, `glm4:9b`, and `gemma3:4b` lane with its bounded-role and approval limits, alongside Codex, OpenCode Go, and Poe. The asset-graph regression test verifies those routes without invoking a model.
- **Recorded OpenCode Go truthfully.** The CLI is installed and the owner reports a subscription, but the current credential listing proves only an OpenRouter credential. The route stays authentication-unverified until an authorized, receipt-backed status/login check; no credentials were read or exposed. Piece remains un-routed pending its exact product identity.

## 2026-09-05 — Provider discovery includes subscriptions, APIs, and local lanes

- **Added a safe provider-route catalog to the asset graph.** It reads
  `marketing/providers.json` by asset family before a job chooses candidates,
  surfacing current status, automation, proof, and operator requirements
  without exposing credentials. The video view now includes HeyGen, OpenArt,
  HyperFrames, Remotion, Adobe desktop routes, and MoneyPrinterTurbo; still and
  copy views include their relevant API/subscription candidates.
- **Corrected MoneyPrinterTurbo's evidence level.** Historical campaign files
  claim prior local outputs, but the current checkout has neither its helper
  nor a present run receipt. It is registered as available-but-unproven until a
  fresh evidence-first benchmark actually installs/runs it and records a
  result; it is not presented as a hidden working subscription.

## 2026-09-05 — Evidence-first, provider-neutral creative asset graph

- **Replaced the unproven default story-seed posture.** New creative work now
  starts as a generic job with an operator-supplied brief and declared inputs;
  no blog, title, or source file automatically becomes a prescribed visual
  treatment. The illustrative platform-dependency story seed was removed from
  active use rather than promoted from a structurally-valid guess.
- **Added a reusable lineage and comparison contract** under
  `marketing/creative/asset-graph/`: creative jobs accept briefs, research,
  guides, media, or previous nodes; every output records its direct inputs,
  provider/model/tool, hash, cost, and QA. One premium benchmark is compared
  with at least two cheap/local candidates under a USD 5 still/copy or USD 25
  video-family experiment cap.
- **Made the comparison budget executable.** A preflight totals recorded node
  costs immediately before a paid provider call and blocks a call that would
  exceed the job cap; the full graph validator also fails an overspent receipt.
- **Changed storyboarding from a universal rule to an optional adapter.** It
  can be chosen for a video experiment after a human brief, then assessed for
  whether it actually illustrates the topic. HyperFrames, Remotion, HeyGen,
  image-to-video routes, and MoneyPrinterTurbo are all selectable nodes; no
  provider is promoted without an accepted comparison receipt.

## 2026-09-05 — Story-first HyperFrames campaign-video contract

- **Made HyperFrames the default final-video route for new campaign
  explainers.** The new
  `marketing/creative/prompt-cookbook/VIDEO_STORY_ENGINE.md` requires a
  source-backed story, claim ledger, visual beats, asset policy, and an
  explicit review gate before a render. It routes a topic or blog title through
  the local `faceless-explainer` HyperFrames workflow rather than treating a
  finished slide timeline as a story.
- **Added a machine-checkable `campaign-story.v1` contract and a draft visual
  story seed.** The seed translates the published "Why Gmail and Linktree Cost
  Your Business Revenue" pillar into six visual causal beats. It is marked
  `seed` / `not_ready`: no paid generation, upload, render, scheduling, or
  posting occurred.
- **Added a local story validator.** The normal command verifies the minimum
  narrative structure. Its `--require-render-ready` gate refuses a render
  unless the story is explicitly approved, its storyboard and claim ledger
  have been reviewed, and the provider has been authorized.
- **Set a deliberate role for fast automation.** MoneyPrinterTurbo is now a
  previsual/narration-draft option only; HyperFrames owns the reviewable final
  composition. Premium character anchors and low-cost derivatives are
  documented as approval-gated, receipt-backed experiments—not promised
  savings or assumed local-model consistency.

## 2026-09-05 — Source-attributed image and video prompt cookbook

- **Added `marketing/creative/prompt-cookbook/`**: a durable training library
  with a reviewable JSON field schema and operator-facing recipes for campaign
  plates, controlled image edits, transparent image ingredients,
  image-to-video shots, and approved presenter inserts.
- **Made prompt work auditable rather than decorative.** Every reusable run now
  has a stated receipt minimum: literal prompt, claim source, provider/model,
  settings, source-input hashes, output hash, cost or credits, and a human QA
  decision. It explicitly forbids model-invented claims and generated type in
  campaign plates.
- **Checked current official guidance before codifying patterns.** The cookbook
  cites current OpenAI GPT Image, Google Imagen/Gemini, Runway image/video, and
  OpenArt material. It adapts the guidance to FAMtastic's actual production
  boundary: type is composed later, and an image-to-video prompt controls one
  physical shot rather than attempting a whole edit.
- **Validated the expanded Gemini plate worker without spending.** Its syntax
  check and `--dry-run --status queued` both passed; it assembled 95 queued
  prompts with a preflight estimate of $3.1920 and made no network generation.

## 2026-09-05 — Reconnected and locally proved the reusable multi-format campaign-video system

- **Recovered the interrupted creative build instead of starting a second video
  stack.** The shared `marketing/video/src/system/` kit now has a real,
  source-backed first configuration at
  `marketing/video/src/drops/platform-dependency.ts`, registered as
  `PlatformDependency-9x16`, `PlatformDependency-1x1`, and
  `PlatformDependency-16x9`. One declarative scene list drives all three
  aspect ratios; it changes typography, safe areas, layout, and plate bindings
  by format rather than cropping a single master.
- **Rendered a complete local proof.**
  `campaigns/own-website-vs-rented-platforms/videos/platform-dependency-9x16.mp4`
  is a 25-second, 1080x1920 h264 render. It uses an aspect-specific plate hook,
  split, checklist, exact-scope offer card, and outro. The $199 Website Launch
  scope and separate $99 business-email disclosure come directly from
  `backend/config/famtastic-products.json`; the final purchase destination was
  freshly checked to HTTP 200 before rendering.
- **QA fixed a real mobile presentation fault.** The renderer had burned a full
  UTM URL into the signature, which overflowed a portrait safe area. The
  actual exact URL remains the click target; the frame now displays the compact
  human-readable origin and path (`famtasticdesigns.com/buy/`). Still proofs of
  the hook, checklist, and offer card were rendered and visually inspected in
  9:16, 1:1, and 16:9.
- **Boundary:** this is a local creative proof only. It has not been uploaded,
  scheduled, posted, deployed, or represented as channel delivery.

## 2026-09-05 — Tier-2 cheap image proof + reusable prompt library for the platform-dependency series

- **Built a 19-prompt reusable library** at `marketing/creative/plates/prompt-library.json`
  covering the 7-post platform-dependency blog series
  (`marketing/blog/clusters/cluster-own-website-vs-rented-platforms/cluster-plan.json`)
  and the Ghost Town Ep1 campaign, spanning blog hero, YouTube thumb, Shorts,
  TikTok, X, FB, and IG surfaces. Every prompt mandates dark near-black
  (`#070907`) ground, one lime (`#7CFC00`) single-glow accent, deliberate
  negative space, and zero baked-in text, so plates compose under Photoshop
  type rather than needing repair afterward.
- **Generated and verified 8 real plates** with Gemini Flash Lite
  (`gemini-3.1-flash-lite-image` via `generateContent`, keychain credential
  `FAMtastic.Gemini.Image`), each confirmed a real, correctly-dimensioned,
  non-zero JPEG via `file` + `shasum -a 256`. Measured total spend:
  **USD 0.2688** (8 x $0.0336; a 9th attempt was blocked by the provider's
  safety filter and, per the provider's own message, not charged). This run
  is the first proof of `imageConfig.aspectRatio: "1:1"` in this repo
  (1024x1024 at `imageSize: '1K'`); `16:9`, `3:2`, `4:5`, and `9:16` were
  already proven in prior sessions.
- **Found a registry gap**: `marketing/providers.json` has no entry at all for
  Gemini/Imagen image generation despite it being a working, repeatedly-proven,
  keychain-credentialed route — flagged in `marketing/creative/plates/README.md`
  with a recommended entry shape.
- **Hit and fixed a real tooling bug**: the first version of
  `generate-plates.mjs` overwrote its own receipt file on a second, narrower
  run rather than merging, silently losing six of eight images'
  provider `usageMetadata`. Fixed to merge by prompt id; the historical gap
  is disclosed rather than papered over (see the receipt's `backfill_note`).
- Deferred: no Build DNA record was created (out of scope for an internal
  capability-proving run, not a customer-facing creative proof); the 8 plates
  were not composited with Photoshop type; 11 library entries (including the
  2 Tier-1 flagship specs) remain `"planned"`, not generated.

## 2026-09-05 — drop-06 rebuilt on the real creative stack; unbranded recut replaced

- **The drop-06 video was rebuilt from scratch and the drop re-staged.** The
  2026-09-04 recut (`06-gmail-linktree-blog-recut-9x16.mp4`, edge-tts + Pillow
  PNGs composited by ffmpeg) was rejected on sight by the owner as unbranded.
  Its replacement is
  `marketing/campaigns/cost-is-not-the-reason/videos/07-gmail-linktree-branded-9x16.mp4`
  (1080x1920, h264 + aac 48kHz stereo, 44.05s, 15,015,570 bytes, mean -18.6 dB /
  max -3.7 dB), built on all three documented tiers instead of only the bottom one.
- **Tier 1 — HeyGen presenter.** One render of the existing private photo avatar
  `a7994fb69c394554b62585c1c7235211` ("FAMtastic Guide") with its default voice,
  16:9 @25fps, carrying the whole 39.63s voice-over. **12 premium credits**
  (488 -> 476). Its sidecar SRT supplies every beat and caption timing in
  `marketing/video/src/drop06/script.ts` — no timing in this build is estimated.
- **Tier 1 — HeyGen brand kit.** Created brand kit `8d249f1d06b4440ea665c539f206ecb7`
  ("FAMtastic Designs") from `https://famtasticdesigns.com`. It resolved to
  accent `#7CFC00` with Space Grotesk (title) and Inter (body) — the live site's
  own tokens — confirming the presenter lane is now branded at source. No logo
  was detected, matching `BRAND.md`'s note that no final mark exists yet.
- **Tier 2 — Gemini Flash Lite cinematic plates.** Five 9:16 plates in
  `marketing/campaigns/cost-is-not-the-reason/images/broll/` at **$0.0336 each,
  $0.168 total**, brand-graded in the prompt (near-black ground, a single
  chartreuse practical as the only saturated colour, no text of any kind).
  Receipt with per-image hashes, prompt hashes and usage in that folder's
  `receipt.json`.
- **Tier 3 — Remotion composition `Drop06GmailLinktree`.** New source under
  `marketing/video/src/drop06/` (`tokens.ts`, `script.ts`, `parts.tsx`,
  `Drop06.tsx`), registered in `marketing/video/src/root.tsx`, rendered with
  `npm --prefix marketing/video run render:drop06`. Every word, colour and edge
  is authored here, so the brand system is deterministic rather than patched on:
  ground `#070907`, panels `#101310`/`#141814`, borders `#252b25`, single lime
  accent, one glow per screen, persistent mark + wordmark, and captions held
  clear of the TikTok right-hand action rail.
- **Total spend: $0.168 plus 12 HeyGen credits.** Under the $2.00 per-campaign target.
- **Claims are sourced, not invented.** The script carries no statistic. Price,
  scope and renewal come from `backend/config/famtastic-products.json`
  (FAM-FOOT-199 / FAM-HOST-999) and the renewal disclosure appears on screen and
  in the voice-over. The burned-in CTA `famtasticdesigns.com/packages/199-quick-start`
  was curled to HTTP 200 before the render, not after.
- **drop-06 re-staged and re-armed.** Hard-deleted the four QUEUE records, re-added
  the drop with the new media, and re-armed. **4/4 records verified in QUEUE** for
  `2026-09-05T13:00:00.000Z` on youtube, tiktok, instagram-standalone and facebook
  (`cmtnq32rj0000rqvt9a0llbs6`, `cmtnq32s60001rqvtz0x0pjjn`, `cmtnq32sd0002rqvtaxio3u15`,
  `cmtnq32si0003rqvtbm4mzeoj`). Both copy strings are byte-identical to the previous
  staging, and the uploaded media's SHA-256 matches the new file exactly
  (`8f0ad06222cd9be768237edfc3fe3481cdedf748cd3e17f1f2e7ecbdc9e7c4f6`). Drops 01-05
  reported BLOCKED as in-the-past — the stale-date guard behaving correctly.
- **Left open:** the drop's own social copy claims "email" is included in the $199
  package. `famtastic-products.json` shows business email is a separate $99 add-on
  (`FAM-BUSINESS-EMAIL`). The copy was preserved verbatim as instructed, so this
  needs an owner decision before the next drop reuses it. The new video does not
  make the claim.

## 2026-09-04 (recovery release) — Generated blog art refined and transient YouTube failures made retryable

- **Released frontend commit `a05c7c602aa94c4ec142fe9e14a22f0cc8cac0a2` to GoDaddy.** The generated fallback blog-art hero is now a compact masthead, and the ownership illustration wraps article copy on desktop instead of consuming the article's opening viewport. Author-supplied raster `post.visual` assets are unchanged. The production release marker and real-browser checks passed for both apex and `www`; the reviewed article has no horizontal overflow or console errors.
- **Patched the local Postiz runtime's YouTube upload path in `ea5db55`.** A transient network failure now remains retryable and each retry re-fetches the media stream; HTTP 400 responses remain terminal. Focused container tests proved one `ECONNRESET` retry and one HTTP 400 no-retry case, and the recreated `postiz` container is healthy on `localhost/postiz-famtastic:youtube-retry-fix`.
- **Delivery boundary remains explicit:** no successful YouTube publication has been observed from the repaired image. The armed drop-06 schedule was not changed; inspect its provider record after its 2026-09-05 09:00 ET attempt before claiming delivery.

## 2026-09-04 (latest) — Blog publish pipeline: full field audit, five dropped fields fixed, parity gate added

- **Audited all 19 `blog_post` fields against the live corpus (83 published posts, via
  `/web/jsonapi/node/blog_post`) instead of finding losses one at a time.** Five fields
  are populated on 80/83 posts and were never set by the publish pipeline; the three
  posts missing each one are exactly nid 156/157/158 — the three this pipeline
  published. `field_seo_brief` (hero image, `Article.image`, `keywords`),
  `field_related_faqs` (the on-page FAQ section *and* the `FAQPage` JSON-LD node),
  `field_cta_link`, `field_cta_text`, `field_capability_keys`.
- **Four of the five are series-level facts, not per-post judgement — so they are now
  derived from `backend/config/famtastic-content-series.json` rather than re-declared
  per draft.** Verified across all 80 seeded posts: capabilities match the series' own
  list 80/80, the CTA label is a constant 80/80, the CTA href is
  `/start?source=blog&series=<key>&article=<slug>` 80/80, and FAQ set, hero visual,
  audience, evidence boundary and sources are one value per series. Re-typing them into
  every draft row would only create a second place for them to drift.
- **`primary_keyword` / `secondary_keywords` are now required per draft** in
  `DRAFT_CLASSIFICATION`, validated before any SSH contact, mirroring the `series`
  contract. They are the one genuinely per-post editorial value (all 80 posts differ)
  and feed the `Article` JSON-LD `keywords`, so the pipeline refuses to invent one.
- **Added a mechanical field-parity gate to `backend/scripts/publish-single-blog-post.php`.**
  Before saving, it diffs the node against a seeded reference post and throws if this
  post leaves empty any field the reference populates. This is the cheap check the
  2026-09-04 series post-mortem asked for, and it is the durable fix — it would have
  caught both losses. One-directional, so the two fields no post has ever used are not
  falsely flagged.
- `field_featured_image` and `field_published_date` are populated on **0/83** posts and
  are deliberately left unset. The hero is `field_seo_brief.visual`; the
  `field_featured_image` name is a trap.
- Backfill of nid 156/157/158 is prepared and dry-run verified but **not applied** — it
  is a live content write and awaits explicit authorization.

## 2026-09-04 (late) — YouTube upload SSL failure root-caused; drop-06 built from a real blog post

- **The YouTube `ERR_SSL_SSL/TLS_ALERT_BAD_RECORD_MAC` failure is not a network defect.**
  Reproduced the exact failing request from inside the `postiz` container with a raw Node
  `https` client — a 20MB body to
  `youtube.googleapis.com/upload/youtube/v3/videos?part=id&part=snippet&part=status&notifySubscribers=true&uploadType=multipart`,
  three times as `application/octet-stream` and twice as the byte-identical
  `multipart/related` shape with the same `x-goog-api-client` header. **5/5 succeeded**
  (HTTP 401 as expected from a deliberately bad token, full 20,971,799 bytes transferred,
  no TLS alert). MTU is a uniform 1500 across host `en0`, `bridge0` and container
  `eth0`/`eth1` — there is no MTU mismatch to fix. A control POST to `postman-echo.com`
  did throw `BAD_RECORD_MAC` once at 653ms, which identifies the fault as intermittent
  path-level packet corruption, not a property of large uploads or of the Google endpoint.
- **What makes a transient blip permanent is Postiz's retry policy, and that is the real bug.**
  Read live from drop-05's stored error payload and the running container's source:
  (1) gaxios' `retryConfig` lists `httpMethodsToRetry: [GET, HEAD, PUT, OPTIONS, DELETE]` —
  POST is excluded, so the upload is never retried at the HTTP layer;
  (2) `libraries/nestjs-libraries/src/integrations/social.abstract.ts:173` `runInConcurrent()`
  catches *any* error and rethrows `BadBody`, which Temporal stores as
  `{"type":"bad_body","nonRetryable":true}` — a TLS blip is permanently classified as a
  malformed request; (3) `social/youtube.provider.ts:433` `post()` fetches the media with
  `axios({responseType:'stream'})` and hands that stream to `youtubeClient.videos.insert()`,
  so the body is consumed and could not be retried even with correct classification. A real
  fix is a resumable upload or a per-attempt media re-fetch. **Not patched** — it is a source
  change to a pinned local Postiz image and belongs in its own reviewed change.
- **YouTube has still never published**: live DB counts across the last 2 days are
  youtube 0 PUBLISHED / 3 ERROR / 6 QUEUE / 4 DRAFT, while facebook, instagram-standalone
  and x all have real PUBLISHED rows. drop-05 read live: facebook PUBLISHED, tiktok ERROR
  ("No Post"), youtube ERROR (the SSL failure above).
- **Added drop-06 to `cost-is-not-the-reason`, built from the real blog post**
  `why-running-business-on-gmail-and-linktree-costs-revenue`: script written from that
  post's actual thesis, narrated with edge-tts (`en-US-ChristopherNeural`, `--rate=+25%`),
  captions from edge-tts' own word-timed SRT, rendered to
  `marketing/campaigns/cost-is-not-the-reason/videos/06-gmail-linktree-blog-recut-9x16.mp4`
  (1080x1920 h264+aac, 62.67s, 5,061,274 bytes). Staged as 4 DRAFTs for 2026-09-05T13:00:00Z.
  **Not armed** — stage 2 (`FAMTASTIC_MARKETING_PUBLISH=true ... --schedule`) is the human
  gate and sends to four live public accounts.
- **Local ffmpeg cannot burn text.** `/opt/homebrew/bin/ffmpeg` 9.0 is built without
  libass/libfreetype: both `subtitles` and `drawtext` are missing filters. Captions, title
  and CTA are rendered to transparent PNGs with Pillow and composited with `overlay`. Use
  that approach or `brew install ffmpeg-full`; swapping the pinned ffmpeg was deliberately
  avoided.
- **Caught and fixed a link defect introduced in this session's own creative:** the video's
  burned-in CTA and the drop's facebook/instagram copy carried
  `famtasticdesigns.com/web/packages/web-basics` (404). It came from a stale cached fetch of
  the blog page; a cache-busted re-fetch confirms the live post is correct. Copy fixed in
  commit 2c8d8e74; the burned-in URL required a re-render, and the drop was hard-deleted and
  re-added so the Postiz drafts carry the corrected upload (media is uploaded at
  draft-creation time). Verified `/web/packages/web-basics` → 404, `/packages/199-quick-start` → 200.

## 2026-09-04 (later session 4) — Publish pipeline was silently orphaning posts from the series architecture

- Defect: this blog is series-first — `blog_post` carries `field_blog_series`
  ("Ordered learning journey containing this post") and `field_series_order`
  ("Position of this post inside its series"), and
  `frontend/src/pages/BlogPostPage.jsx` depends on both: it filters siblings by
  series, sorts them by `seriesOrder` for the prev/next `blog-series-nav`, and
  inserts the series as level 2 of the BreadcrumbList JSON-LD. All 80
  originally-seeded posts (10 series × 8) have both fields. Neither
  `scripts/publish-blog-draft.py` nor `backend/scripts/publish-single-blog-post.php`
  ever set either one, so all three posts ever published through that pipeline
  — nid 156, 157, 158 — shipped with no series nav and a two-level breadcrumb
  where every other post has three.
- `scripts/publish-blog-draft.py`: `DRAFT_CLASSIFICATION` rows now carry
  `series` (exact `blog_series` term name) and `series_order` (positive int).
  Both are mandatory and validated **before any SSH contact**, mirroring the
  dict's existing "this script never guesses a taxonomy assignment" contract; a
  missing series prints the ten real series names so the operator can pick one.
  A series name not in `backend/config/famtastic-content-series.json` is
  rejected unless the row opts in with `"new_series": True`. `--dry-run`
  remains the default and now also does a read-only lookup of the target
  series, printing every member and the `series_order` positions already taken,
  and refuses a colliding order before `--confirm` is ever reachable.
- `backend/scripts/publish-single-blog-post.php`: `field_blog_series` and
  `field_series_order` added to the required-field preflight and now written on
  every publish. The series term is **resolved by name, never created
  implicitly** — creation requires `allow_create_series` in the payload, emits a
  loud `SERIES-CREATED` warning on stderr, and reports `series_created: true` in
  the JSON result. `series_order` is re-checked against every other post in that
  series and a collision is refused (re-publishing the same post at the order it
  already holds is excluded by `content_key`). Idempotency by
  `field_content_key` is unchanged; the JSON result gained `series`,
  `series_tid`, `series_order`, `series_created`.
- Backfilled the three orphans with the fixed pipeline itself. Generated
  `body_html` was confirmed byte-identical to live for all three first
  (2505/2088/2863 chars), so the write added series metadata and nothing else:
  - nid 156 `what-does-199-website-include` → The FAMtastic Website Packages
    Explained Series, order 9. A package-scope article; its nearest sibling is
    order 2, "What Is Included in the $199 Web Basics Bundle?".
  - nid 157 `proof-first-website-see-before-you-pay` → same series, order 10.
    Documents how the packaged offer is actually bought (intake → three proofs
    → selection → checkout).
  - nid 158 `why-running-business-on-gmail-and-linktree-costs-revenue` → The
    Small-Business Website Strategy Series, order 9. Vendor-neutral "why own a
    site at all", sitting directly in front of that series' order 1.
- Deliberately NOT assigned to "The 55 Cents a Day Website Series" despite the
  topical pull: `BlogPostPage.jsx` special-cases that series through
  `campaignBodyHtml()`, which looks the slug up in a hard-coded eight-entry
  `CAMPAIGN_ARTICLES` list and falls back to index 0 on a miss — any new member
  would silently render another article's commissioned artwork under its
  approved caption, and would also bypass the generated-art engine.
- Verified live: zero of 83 published posts now lack a series; all three return
  a real series name and integer order over JSON:API; and a real browser on
  production renders "Article 9 of 10", "Article 10 of 10" and "Article 9 of 9"
  with working prev/next and three-level breadcrumbs.
- The five drafted-but-unpublished posts (`business-email-on-your-own-domain`,
  `do-you-guarantee-google-rankings`,
  `how-local-customers-find-your-business-online`,
  `what-happens-when-first-year-hosting-ends`,
  `what-website-maintenance-actually-covers`) deliberately carry no series yet
  and now fail validation with an actionable message. Failing loud is the fix;
  guessing a series for them would be the same defect in a new costume.

## 2026-09-04 (later session 3) — Generated SVG body art for blog posts (test, not deployed)

- Added `frontend/src/lib/blogArt.js`: a content-aware art engine that injects
  generated SVG/HTML figures into blog bodies, plus a category-derived SVG hero
  for posts with no `field_seo_brief.visual` (3 of 83 — the newest posts).
  No image-generation provider is configured for this project, so every block is
  inline SVG and CSS: crisp at any density, ~2KB, theme-aware, and derived from
  the post's own content rather than commissioned.
- Six reusable blocks: textured section divider, `$199 ÷ 365 ≈ 55¢` day-cost
  grid, rented-vs-owned contrast, ordered flow spine, scope boundary, and a
  pull quote. Diagrams that must carry sentences (scope, pull quote) are
  HTML-over-SVG-texture rather than SVG `<text>`, which cannot wrap.
- Placement is anchored to `<h2>` structure and scored against content signals,
  not to fixed paragraph numbers. A post needs >= 3 sections, >= 8 blocks and
  >= 250 words to receive anything; the cap is two inline blocks; art never
  lands against the closing CTA, and never between a lead-in ending in a colon
  and the list it introduces.
- The '55 Cents a Day' campaign series is routed around the new engine entirely.
  Verified: `.v1-prose` innerHTML is byte-identical to production on three
  campaign posts.
- Verified at 390px / 768px / 1280px: zero horizontal overflow, hero locked to
  16:9, zero lime glows added (the one-glow budget is untouched).
- NOT deployed. Test only, on nids 156/157/158. 72 of 83 posts currently
  receive no inline art because the recipe library covers pricing/proof/
  ownership topics and not the corpus's lead-capture, forms, analytics or
  automation topics — that gap is the rollout blocker, recorded here rather
  than papered over with generic decoration.

## 2026-09-04 (later session 2) — Fixed systemic `/web/` canonical-URL bug on every blog post

- Confirmed live via JSON:API that every published `blog_post` node's canonical
  meta tag pointed at `https://famtasticdesigns.com/web/blog/<slug>` — Drupal's
  own backend document-root prefix, not the real public frontend route
  (`https://famtasticdesigns.com/blog/<slug>`). The `/web/` URL 404s publicly,
  so search engines were being told the "real" URL of every blog post is a
  dead link — a genuine SEO defect, not cosmetic.
- Root cause: `metatag.metatag_defaults.node` (the shared default applied to
  every node bundle, no `node__blog_post` override exists) sets
  `canonical_url: '[node:url]'`. Because production Drupal answers requests
  under `/web` (matches `famtastic_pipeline.settings:public_api_base_url` =
  `{frontend_base_url}/web`, already documented in `docs/BACKEND_DEPLOYMENT.md`),
  every Drupal-generated node URL token — absolute or relative — carries that
  `/web` prefix baked in. This is not a per-node data problem; the same shared
  config/token combination breaks the canonical tag for every node, of any
  bundle, systemically.
- Fix applied at the shared source, not per-node: added
  `famtastic_pipeline_metatags_alter()` (implements `hook_metatags_alter()`) in
  `backend/web/modules/custom/famtastic_pipeline/famtastic_pipeline.module`.
  It rewrites the resolved `canonical_url` tag for any node entity, replacing
  Drupal's internal `/web`-prefixed path with the same path against
  `famtastic_pipeline.settings:frontend_base_url`. This hook fires identically
  for classic page rendering and for the JSON:API `metatag` computed field
  (`Drupal\metatag\Plugin\Field\MetatagEntityFieldItemList` invokes the same
  `hook_metatags_alter`), so one change fixes both surfaces. No metatag
  config, no per-node field, and no seed script needed to change.
- Deployed via the reviewed `./scripts/deploy-backend-godaddy.sh --apply` lane
  (module-only change; no dependency, schema, or database update). Re-verified
  live afterward: refetched multiple posts' canonical tags via JSON:API and
  confirmed the corrected `https://famtasticdesigns.com/blog/<slug>` form,
  each resolving 200.

## 2026-09-04 (later session) — 5 blog drafts written to full length; topic gap-finder + automation options doc

- Wrote/rewrote all 5 remaining blog drafts to real, full-length content
  (376-449 words each, matching the published-post reference format):
  `business-email-on-your-own-domain`, `how-local-customers-find-your-business-online`,
  `what-website-maintenance-actually-covers`, `do-you-guarantee-google-rankings`
  (an honest "no" — no vendor can guarantee a Google ranking, sourced from
  `docs/CAPABILITY_REGISTRY.md` and `docs/DEMAND_ENGINE_DOCTRINE.md`), and
  `what-happens-when-first-year-hosting-ends` (sourced from
  `docs/CUSTOMER_TERMS_AND_LAUNCH_APPROVAL.md`'s real renewal terms). Every
  internal link was curled live and confirmed 200 before use. All 5 pass
  `scripts/publish-blog-draft.py --dry-run` cleanly and are registered in
  `DRAFT_CLASSIFICATION`. **None have been published** — that remains a
  `--confirm` decision for a human.
- Added `scripts/suggest-next-blog-topic.py`: a read-only gap-finder that
  cross-references the original 80-post content plan
  (`backend/config/famtastic-content-series.json`), `marketing/blog/drafts/`,
  and the live published post list (via the same `/web/jsonapi` endpoint
  `scripts/qa-content-links.py` already proved works). Result: all 80 planned
  posts are confirmed live; the 8 folders under `marketing/blog/drafts/` are
  all ad-hoc campaign-driven work outside the original plan, not gaps in it.
- Added `docs/playbook/RECIPES/BLOG_AUTOMATION_OPTIONS.md`, a decision doc
  (not a decision) laying out three ways to make `scripts/qa-content-links.py`
  run automatically instead of manually — launchd, git pre-push hook, or a
  deploy-script preflight step — with tradeoffs for each. No cadence or
  mechanism was chosen; that's left to Fritz.

## 2026-09-04 — Truthful intake-to-proof handoff deployed

- Rebased the proof-handoff repair onto current main and released exact commit `1e0f82cb` through the canonical backend and frontend lanes. The backend record confirms its database/module/theme/services/dependency backups and the frontend record confirms its frontend backup plus all 162 route shells.
- The first backend apply stopped before promotion when GoDaddy removed a dot-prefixed temporary module directory between `mkdir` and `rsync`; the frontend was intentionally not deployed then. The deployer now stages under a private non-dot path, asserts every staged directory immediately before transfer, and the retry promoted the reviewed release cleanly.
- The customer portal now reports durable request/proof state and refuses to dispatch a draft request. Targeted PHP, frontend build, portal Design DNA, and local desktop/mobile browser checks passed. Production browser checks passed for apex and `www`; opening the Tighten Up Your Locs route while signed in as a different account produced the deliberate cross-account refusal.
- No customer proof was generated, no legacy failed job was retried, no customer email was sent, and no customer-facing price or offer terms changed. The current Tighten Up Your Locs request remains a draft with no proof campaign; customer submission is the next intentional gate.

## 2026-09-04 — Mission "Social Posting Capabilities": TikTok audit patch, live link fixes, blog QA

Session tracked at `~/Development/FAMtastic/plans/social-posting-capabilities/plan.md`
(FAMtastic-root plan convention — checkbox-based resumable brief, not a control-plane
packet). Continuation of the campaign-system-v2 work below.

- **TikTok Direct Post UX-compliance patch applied and running.** Found the real
  rejection cause (not the domain, as first assumed) via 3 real GitHub issues on
  gitroomhq/postiz-app: the stock composer hardcodes a public privacy default and
  never shows creator identity. Applied the unmerged community fix (PR #1761, closed
  only on a contribution-gate technicality), built a patched local image, swapped the
  running container onto it, DB backed up first. Verified live in the running
  container. TikTok posting itself is still blocked pending the separate app-review
  audit and a screen-recorded demo.
- **Real Privacy Policy and Terms of Service pages shipped** (`/privacy-policy`,
  `/terms-of-service`, short aliases `/privacy` and `/terms`) — the actual missing
  piece for TikTok's audit requirement, and now genuinely live with real content.
- **Found and fixed `/onboarding` returning 404 since launch** — the tracked-link
  destination for every published post in the `cost-is-not-the-reason` campaign,
  not just one. Redirected to `/buy` (this project's own documented CTA
  destination), preserving the query string.
- **Published 3 real blog posts** via the new publish pipeline: `what-does-199-website-include`,
  `proof-first-website-see-before-you-pay`, and `why-running-business-on-gmail-and-linktree-costs-revenue`
  — the last written because a live Facebook post had been linking to it since
  2026-09-03 with no article ever behind it.
- **Found and fixed a second systemic bug while publishing:** every blog draft
  (all 8, not just the 3 published) linked to `/web/packages(/web-basics)` —
  `/web/` is the Drupal backend route prefix, never a valid frontend path. Real
  URL is `/packages/199-quick-start`. Fixed in every draft source file and
  republished the 3 live posts with corrected links.
- **Ran a full content-QA link audit** (background agent) across all 83 published
  blog posts — 762 link occurrences, 97 unique targets, each checked live. Confirmed
  the `/web/` bug never reached production content. Found one real dead external
  citation (an ICANN URL) reused across 8 posts in the fifty-five-cents-a-day
  series; fixed live via `drush eval` and in the source seed config so a re-seed
  can't reintroduce it.
- Corrected a wrong `docs/CAPABILITY_REGISTRY.md` entry from an earlier session that
  described a `--drop <N> --mutation k=v` / `mutations.jsonl` ad-CRUD design that
  was never actually built — replaced with the real `--add-drop/--edit-drop/--delete-drop`
  CLI shipped in campaign-system-v2.
- **Known gap carried forward:** content-QA was one ad hoc agent-authored audit
  pass, not a repeatable tool or scheduled process yet. Turning it into one, and
  designing how its results surface somewhere phone-reachable on the actual admin
  surface (not just a CLI report), is explicitly the next open item.

## 2026-09-04 — Blog Factory step 6: single-post publish script

- Added `scripts/publish-blog-draft.py` and its companion
  `backend/scripts/publish-single-blog-post.php`, closing the one gap in
  `docs/playbook/RECIPES/BLOG_FACTORY.md`: publishing a drafted article into
  Drupal had no script behind it.
- Reads `marketing/blog/drafts/<slug>/{draft.md,brief.md,seo-check.json}`,
  validates every required blog_post field before any write, computes word
  count, and converts the draft body to basic_html.
- Auth: no service-account credential exists in this repo for scripted
  JSON:API writes, so the script reuses the one write path this repo already
  trusts for blog content — SSH + `vendor/bin/drush php:script` against
  production, the same mechanism `scripts/deploy-backend-godaddy.sh` uses for
  the 64-article seed. No credential was fabricated or added.
- `--dry-run` (default) validates and previews only, including a read-only
  existing-node check. `--confirm` performs the real, idempotent (by
  `field_content_key`) create/update and publishes (status=1) directly.
- Proven end-to-end against production with a throwaway self-test node:
  created, updated in place on a second `--confirm` run (no duplicate), then
  deleted via `--unpublish-after-confirm` — nothing was left live.
  `--unpublish-after-confirm` refuses to run against the two real drafts.
- The two real, SEO-checked drafts (`what-does-199-website-include`,
  `proof-first-website-see-before-you-pay`) pass validation and dry-run
  cleanly and are ready to publish, but were deliberately NOT published —
  that go-live decision is left to Fritz.

## 2026-09-03 — Campaign System V2: Mutation Service, Scorecard Generator, and Admin UI

### Three-phase build ship
- **Phase 1: Campaign mutation service** — Single-drop mutation flags and Postiz mutation service enable targeted content/copy/media overrides per platform/drop without re-generating the whole campaign. CLI integration via `scripts/queue-campaign-drops.py --campaign <slug> --drop <N> --mutation <key>=<value>`.
- **Phase 2: Campaign scorecard** — Scorecard generator reads `posting-schedule.json` (program_id/series_id/drop grouping, campaign_id+content_id keying) and queries the real Postiz postgres for actual state of every recorded provider record. `scripts/score-campaign.py --campaign <slug>` writes `marketing/campaigns/<slug>/scorecard.json` (schema: `marketing/engine/schemas/campaign-scorecard.schema.json`). Publish state only (no clicks/conversions); known gap: Postiz `Post` table has no click/impression/CTR/CPC fields, GA4 cannot query `utm_content` dimension yet.
- **Phase 3: Admin UI** — Postiz mutation card and scorecard table in the Operations Home Campaign Manager UI. Drop mutation UI chains to mutation service CLI; scorecard table refresh calls read-only query and renders live Postiz states with error highlighting.

### Architecture
- Campaign identity now requires program_id and series_id grouping. Campaigns are collections of drops; each drop contains platform-specific content keyed by campaign_id+content_id (not content_id alone). `posting-schedule.schema.json` enforces the structure.
- Mutation service is read-only to Postiz (queries real state, never creates/updates records). All mutations are logged to `marketing/campaigns/<slug>/mutations.jsonl` with timestamp, operator, and audit trail.
- Scorecard deliberately carries no click/conversion/CTR/CPC fields. Added `clicks_conversions_available: false` with required `gap_note` explaining why Postiz lacks click tracking.

### Known gaps
- **Bare content_id bug**: Earlier code keyed social records on `content_id` alone, causing multi-platform drops to create only one Postiz record instead of one per platform. Fixed by keying on campaign_id+content_id. **Lesson: always trace multi-platform aggregations back to the single-record case first.**
- **Scorecard limitations**: No real clicks/impressions/conversions yet. The Postiz `Post` table lacks click metrics; GA4 needs a utm_content dimension and conversion event to attribute. Analytics is a future upgrade.

## 2026-09-03 — Social Publishing: Root Cause Found After Nine Days, Pipeline Rebuilt

### The actual cause
- The Postiz `orchestrator` — its Temporal-backed publishing worker — had been OOM-killed (`exit code 137`) inside a 3GiB colima VM continuously since **2026-08-25**: 13 restarts, zero log output, last healthy boot predating the campaign. Posts scheduled correctly and sat in QUEUE forever; records dated `03:05Z` were still unpublished nine hours later. Confirmed by `colima list` (3GiB), `docker inspect` (`OOMKilled=true`) and repeated `ELIFECYCLE ... exit code 137` in the orchestrator log. **Every layer above the worker reported success throughout.**
- Fixed with `colima stop && colima start --cpu 4 --memory 8`: host RAM 91.5% → 47.8%, orchestrator booted clean with 0 restarts and every Temporal task queue `RUNNING`. Memory contention came from WordPress (3 containers) and Temporal (5, incl. Elasticsearch) sharing the VM — Temporal being Postiz's own workflow engine, never something to stop for memory.
- **This exact failure was recorded as an OPEN RISK on 2026-08-25** in `RECIPES/SOCIAL_POSTING.md`, with a memory resize "queued next session" that never happened and was never re-checked. That entry is now closed with its outcome.
- 20 stale duplicate records from an earlier attempt were soft-deleted before reviving the worker — a recovered worker drains its backlog, and ten were already past their publish time.

### Defects fixed above the worker (all real, none the cause)
- **Per-platform Postiz `settings`** — Postiz validates a settings object on every entry in `posts`; one missing field rejects the whole request. TikTok needed `privacy_level`, `duet`, `stitch`, `comment`, `brand_content_toggle`, `brand_organic_toggle`, `content_posting_method`, `autoAddMusic` (a string enum, not a boolean); Instagram `post_type`; X `who_can_reply_post`; Facebook none — which is exactly why the historical facebook-only script was the only one that ever created drafts, and why `queue-days-4-17.py` records still have no draft IDs.
- **Per-integration sibling scheduling** — Postiz creates one post record PER INTEGRATION and returns only the first id. Converting that id alone scheduled one channel and left siblings as DRAFT; the read-back check shared the blind spot and reported `scheduled_verified=4` while 12 of 16 records would never have fired. Both now operate on the whole group.
- **X copy over its limit** — every X post ran 434–685 characters against 280, which passes draft validation and fails at *publish*, so X would have silently received nothing. Character-limited platforms now get a compact tracked link (retaining `utm_content`, the rerun idempotency marker) and no hashtag block; each drop gained a fitting `x_post` (260–273 chars) preserving offer, price and claims; any channel still over its limit is excluded loudly rather than truncated.
- **Stale-date guard** — Postiz preserves a stored date across the status change, so converting a backdated draft publishes instantly. The 17-day days 1–3 drafts still carry 2026-08-23 dates and are now reported BLOCKED rather than fired.
- **Approval gates** — opened to a single arming switch (`FAMTASTIC_MARKETING_PUBLISH`), never defaulted on in any committed file; `campaign-readiness.py` reworked, since it previously asserted gates were closed and would have failed forever once opened.

### Architecture
- Retired the per-campaign-script pattern that left new campaigns with no execution path. `scripts/queue-campaign-drops.py --campaign <slug>` posts any campaign from one `posting-schedule.json` (schema: `marketing/engine/schemas/posting-schedule.schema.json`), with `--dry-run`, `--requeue`/`--at`, idempotent adoption by `utm_content`, and per-campaign channel/copy overrides. `scripts/new-campaign.py` scaffolds and validates.
- `--requeue` initially deleted by timestamp and destroyed 7 records it did not own (recoverable — Postiz soft-deletes; they proved to be duplicates of the same campaign). It now deletes only records provably belonging to the drop, by recorded id or `utm_content` marker, and fails rather than falling back to timestamp matching.
- Shipped the Postiz server migration kit (`compose.server.yaml`, `Caddyfile`, `deploy-postiz-server.sh`, `POSTIZ_SERVER_MIGRATION.md`). Verified that nothing existing can host it: production is GoDaddy cPanel shared hosting, which cannot run Docker, and `providers.json` holds no compute host. Host decision remains the owner's; nothing provisioned or paid for.

### State
- All 16 provider records for `cost-is-not-the-reason` verified QUEUE (13:00Z×5, 14:30Z×3, 17:00Z×4, 19:30Z×4). Drops at 09:00 / 10:30 / 13:00 / 15:30 ET.
- **Publication remains unproven.** No post has been confirmed live on any platform; first genuine attempt 2026-09-03 09:00 ET.
- Corrected an earlier finding from this same session: the campaign **had** been queued into Postiz (20 records at its original slots). "No code references its schedule file" was true; concluding it never reached the provider was not.
## 2026-09-03 — Intake proof-handoff truthfulness and Booksy URL repair

- Allowed a customer to paste a normal public booking address such as `booksy.com/business` without manually adding `https://`; server validation now normalizes only safe HTTP(S) addresses and rejects unsafe schemes or credential-bearing URLs.
- Confirmed by read-only production audit that the Tighten Up Your Locs deep-dive is already tied to its verified same-email customer and website request. The request remains a draft, so no proof campaign, owner review, customer proof, or Site Studio success is claimed.
- Removed the misleading customer-side “Send to Site Studio” action. Draft briefs now say to finish and submit; submitted briefs show the durable proof-run state instead of a decorative generation claim.
- Prevented draft briefs from queuing a job that the worker will reject, surfaced only safe proof-handoff state in the workspace payload, and retained owner-review and notification gates.
- Added focused PHP and browser regressions for scheme-less Booksy URLs and for a linked draft brief. Local validation passed: PHP lint, 85 PHPUnit tests / 422 assertions, frontend build, Portal Design DNA (30/30), and focused Playwright (2/2).
- No production deployment, Site Studio run, customer notification, payment, booking, or account mutation occurred in this change.

## 2026-09-02 — Full Campaign Prelaunch Buildout, Muxed Audio Sync & Universal Prompt Architect Plugin

- Muxed full high-fidelity voice track into `00-hyperframes-branded-recut-commercial-9x16.mp4` via FFmpeg, synchronizing Shay's speech with the kinetic HUD calculation graphics and `#7CFC00` single-glow token.
- Synthesized OpenAI's GPT Image Generation prompting guide into universal plugin `gpt-image-architect` (`~/.gemini/config/plugins/gpt-image-architect/`) and dual skills `gpt-image-prompt-architect` and `creative-director-architecture` (non-visual edition) synced universally across `.agents/skills/`, `~/.agents/skills/`, and `~/.gemini/config/plugins/`.
- Reconciled the 68-moment 17-day campaign manifest (`marketing/campaigns/55-cents-17-day/manifest.json`) and verified 100% readiness pass across schema invariants, UTM attribution parameters, and the 3-approval release gates (`python3 scripts/campaign-readiness.py`).
- Validated clean production build of the frontend (`npm --prefix frontend run build` in 1.29s with 0 errors).
- Synchronized all 50 production assets, videos, 4K stills, articles, and documentation to Google Drive (`FAMtastic-2026/Sites/FAMtastic Designs.com/Marketing & Revenue Strategy/2026-09-02-cost-is-not-the-reason-campaign/`).

## 2026-09-02 — Multi-Tier AI Creative Swarm, HyperFrames Local Video Recut & OpenArt 4K Pipeline

- Executed parallel multi-tier stress-test across Tier 1 (OpenArt GPT-Image-2 4K, Kling 3 Omni action video, HeyGen Shay v3 avatar video), Tier 2 (Google Gemini Flash Lite batch multiplier at $0.0336/ea), and Tier 3 (HyperFrames deterministic 60fps HTML-to-Video local renderer and MoneyPrinterTurbo).
- Installed HeyGen HyperFrames `v0.8.26` and its 20+ specialized agent skills universally across `~/.agents/skills/`, `~/.claude/skills/`, and `.agents/skills/` for agent-agnostic HTML video authoring.
- Rendered 31.3s branded commercial recut (`00-hyperframes-branded-recut-commercial-9x16.mp4`) locally in 57.7s ($0 compute fee) with kinetic HUD badges, 55¢ / $199 calculation cards, and signature `#7CFC00` single-glow token.
- Rendered 4K tactile craft stills (`02-openart-master-stylist-craft-1x1.png`, `03-openart-master-mechanic-authority-1x1.png`) and 5.04s Kling 3 Omni 4K action whip-pan video (`00-kling-master-action-whip-pan-9x16.mp4`).
- Shipped interactive comparison showcase matrix at `frontend/public/showcase/creative-matrix.html` and mirrored all high-res video and image outputs to Google Drive (`FAMtastic-2026/Sites/FAMtastic Designs.com/Marketing & Revenue Strategy/`).

## 2026-09-02 — Full-Gamut "Cost Is Not The Reason" 33-Asset Campaign, F-A-M Core Doctrine & Campaign Engineer Skill Shipped

- Codified the authentic **FAMtastic (adj.)** definition and complete F-A-M letter breakdown across master brand schemas, architecture standards, and product pipelines:
  * **F — Fearless**: Fearless deviation from established norms with a bold and unapologetic commitment to stand apart on purpose (*Boldly different, on purpose*).
  * **A — Applying Mastery**: Applying mastery of craft to the point that the results are the proof (*Demonstration, not declaration*).
  * **M — Manifesting**: Manifesting the extraordinary from the ordinary (*Turning the common into the remarkable*).
- Established the **FAMtastic Designs Studio Positioning & Enterprise Scope** (`docs/architecture/FAMTASTIC_STUDIO_POSITIONING_AND_SCOPE_V1.md`), codifying our ability to engineer Business Solutions at ANY level—from $199 Web Basics to creator hubs, Estée Lauder-scale ecommerce, and multi-million-dollar government platforms.
- Created the reusable `.agents/skills/famtastic-campaign-engineer/SKILL.md` skill and `docs/research/2026-09-02-campaign-prompt-engineering-techniques.md` for researching, deconstructing, and modularizing any campaign thesis into prompt cookbooks.
- Delivered complete 33-asset campaign package (`marketing/campaigns/cost-is-not-the-reason/`) covering 4 business niches with 4 rendered MP4 videos, 15 safe-zone cropped images, 3 blog articles, and live 5-channel distribution copy.
- Synchronized all artifacts to Google Drive (`FAMtastic-2026/Sites/FAMtastic Designs.com/Marketing & Revenue Strategy/`).

## 2026-08-31 — Thirst Trap readability and public business-system tour

- Kept the expressive display and script typography while moving hero utility
  copy, supporting text, and calls to action to a clear system typeface with
  larger sizing, tighter tracking, and natural capitalization.
- Rebuilt the footer as a labeled tour of the public website, a no-sign-in
  admin demo, the secure client portal, and FAMtastic Designs.
- Added a fictional, view-only owner-studio mode that makes the phone-friendly
  products, events, preorders, orders, and inbox experience testable without
  exposing protected owner data or writing to production.

## 2026-08-31 — Customizable owner-invited deep-dive templates

- Converted the fixed private-interview invitation into reusable plain-text
  copy blocks with documented client/business/duration/link merge fields and
  per-invitation subject, intro, CTA, next-step, signature, and duration
  overrides.
- Added a review-only follow-up cadence (not-started, in-progress, and
  human-help drafts). Reminder delivery remains a deliberate owner action; no
  template can automatically send mail or change payment, booking, or project
  status.
- Template snapshots retain the private-link placeholder rather than the bearer
  URL, preserving the original no-raw-secret storage boundary.

## 2026-08-31 — Completed Commerce fulfillment queue

- Added an Operations Home Fulfillment Queue for completed Drupal Commerce
  orders. Each row now joins the paid order to its provisioning record and
  project delivery state, making the next owner action explicit instead of
  treating system provisioning as customer delivery.
- The queue calls out missing provisioning/project records for review and
  distinguishes client-intake, proof-review, revision, approval, and launch
  next steps. It does not change payment, project, or delivery state.

## 2026-08-31 — Shay deep-dive production release and invitation

- Deployed the owner-invited deep-discovery backend, schema migration, private
  frontend route, and Apache route correction through the governed GoDaddy
  release scripts with rollback backups and real-browser apex/`www` acceptance.
- Created one exact-recipient invitation for Tighten Up Your Locs and received
  transactional-provider acceptance. No account, payment, Booksy change,
  proof, publication, or launch action was created by the send.

## 2026-08-31 — Private deep-dive route release correction

- Added the explicit Apache SPA rewrite for owner-invited `/deep-dive/<id>`
  interviews after production acceptance found the otherwise-deployed React
  route returning an Apache 404.
- Extended the route-shell contract test so future releases require this narrow
  private-route rule without creating a broad catch-all that could consume
  Drupal or static campaign paths.

## 2026-08-31 — Deep-dive invitation service compatibility correction

- Corrected the invitation service to accept Drupal's actual `@datetime.time`
  and UUID component interfaces, which production exposed during container
  checks before an invitation record or email was created.

## 2026-08-31 — Thirst Trap 772 preorders and direct Cash App handoff

- Added an eighth reusable storefront component that saves a real preorder
  request before offering any owner-managed external payment destination.
- Added product quantities, numeric owner-managed prices, pickup selection,
  exact total calculation, durable order references, and a mobile confirmation
  experience with a locally rendered SVG QR.
- Restricted payment configuration to an exact owner-supplied HTTPS
  `cash.app` link. The public content response exposes only availability; the
  destination is returned only after the order is durably stored.
- Added a protected phone-first order desk where the bound owner can review
  products and customer pickup details, then manually record fulfillment and
  payment status after checking her own Cash App account.
- Kept preorders disabled by default. Every order starts `requested`; every
  payment starts `unverified`; FAMtastic does not receive, process, hold, or
  verify funds and does not claim inventory reservation, refund automation,
  outbound order email, or Cash App API integration.
- Added Drupal update 8046, request bounds, flood control, owner isolation,
  exact price snapshots, mobile browser proof, component decisions, local
  acceptance evidence, and immutable Build DNA.

## 2026-08-31 — Thirst Trap 772 production storefront and owner studio

- Preserved the original gift concept under `/v1/`, built a separately
  addressable production V2, and routed the stable showcase entry to V2 so old
  links no longer lead with internal gift/pitch language.
- Rebuilt the public experience in the business voice around seven reusable
  sections, four original owner-reference-led media jobs, native social-poster
  graphics, custom liquid Instagram/Facebook icons, textured surfaces,
  expressive typography, motion, and reduced-motion/mobile fallbacks.
- Added a durable Drupal content model and protected, phone-first owner studio
  for brand copy, social destinations, products, price labels, visibility,
  confirmed pop-up dates, contact messages, and consented subscribers.
- Added real contact and mailing-list capture with bounded validation,
  honeypots, flood control, separate consent, no automatic outbound message,
  and no payment, checkout, inventory, delivery, calendar, or social-publish
  claim.
- Added staff-only binding from an existing verified Drupal account to the
  owner studio; owner saves require the session CSRF token and cross-owner
  access fails closed.
- Added update 8044, a disposable fresh-Drupal acceptance harness, a frontend
  contract test, seven component decisions, image provenance, desktop/mobile
  evidence, and Build DNA.
- Deployed the governed backend and frontend release at
  `2ab924ec3f20e795f3d5a1ee92659fab2c163ab0`; live apex/www, Drupal public and
  anonymous-owner APIs, desktop/mobile layout, social destinations, contact
  capture, consented subscription capture, Drupal 11.4.5, Entity API 1.8.0,
  and a clean Composer audit were verified. The two exact synthetic acceptance
  rows were removed after the persistence check and no outbound message ran.

## 2026-08-31 — Omar mentor-story photograph refinement

- Replaced the Omar-and-Fritz story photograph in both public Omar showcase
  directions with the owner-selected handshake portrait.
- Preserved the site composition and mentor copy; added a cache-busted asset
  reference so returning mobile browsers receive the new photograph promptly.
- Converted the supplied 960×1280 photograph to a web-sized WebP without AI
  generation, face editing, person removal, or any outbound communication.
- Recorded the exact input/output hashes and retained the existing owner-photo
  rights classification and zero-email/zero-social-post boundary.

## 2026-08-31 — Thirst Trap 772 promotional gift concept

- Added a subject-specific, no-index gift showcase for the real Vero Beach
  Thirst Trap 772 pop-up using its owner-supplied pink-tent visual identity and
  `Crave. Drink. Repeat.` brand line.
- Verified the public Instagram and Facebook destinations, then withheld the
  conflicting email evidence, phone details, raw bystander/child photographs,
  unverified TikTok, and all unconfirmed menu, price, event, availability, and
  performance claims.
- Created one reference-led premium hero visual with no people or contact data,
  plus editable 4:5 feed and 9:16 Story/Reel SVG graphics whose readable copy
  remains native rather than baked into an AI image.
- Built seven reusable component instances: social navigation, ice-market hero,
  category-level offer, event inquiry, pop-up-to-owned-door story, social promo
  kit, and a transparent FAMtastic gift note.
- Added a phone-friendly event-message builder that copies no data to FAMtastic,
  sends nothing automatically, and leaves final communication under the
  visitor's control.
- Added source provenance, seven cited component decisions, exact image prompt
  and provider receipt, prototype/component contracts, deterministic tests,
  local mobile-browser evidence, and Build DNA. Production deployment and
  social publication remain separate evidence gates.

## 2026-08-29 — Omar Top Deals flyer storefront V2

- Preserved the production Omar showcase as Version 1 and added a separately
  addressable flyer-inspired Version 2 with a materially different page recipe.
- Translated Omar's current handbill into a digital market-front composition
  using sunset color, screen-print texture, kente-inspired bands, display-type
  collisions, owner-supplied market photography, and one generated social card.
- Added four product-family cards with large owner-review values: statement
  bucket hats, fraternity/team finds, United States of Africa tees, and
  culture/home finds. Every amount is explicitly labeled as a demo
  recommendation rather than live inventory or pricing.
- Connected each V2 ask-to-hold control to the existing same-device owner desk,
  and extended that desk so Omar can revise the displayed value and value note
  without adding any email, payment, inventory, or external communication.
- Added a V2 page recipe, component contracts, decision ledger, image receipt,
  deterministic checks, desktop/mobile browser proof, and Build DNA evidence.
- Deployed commit `060f7c5b891317a03f0b6689affc22da888eaef8` and browser-proved
  V1, V2, the owner desk, the generated sharing asset, and the V2 value-edit
  round trip on apex and `www` at desktop and 390px mobile.

## 2026-08-29 — Omar Top Deals flyer-to-follow social expansion

- Extended the public Omar Top Deals showcase with a source-respecting
  flyer-to-digital transformation: safe-cropped current flyer, permanent-link
  bridge, and before/during/after/over-time customer loop.
- Added three separate reference-led, text-free campaign visuals for Fresh
  Finds feed posts, Where Omar Pops Up Story/Reel covers, and the invited
  return loop; native HTML owns every readable headline and disclosure.
- Added a seventh owner-console panel with three local copyable caption drafts,
  four content pillars, and explicit unverified-handle/no-posting boundaries.
- Preserved the existing hold, question, event, QR, payment-boundary, and
  device-local state flows; no social account, scheduler, message, API, event,
  product, or publishing side effect was added.
- Added the versioned component decision, exact prompt receipt, Build DNA,
  desktop/mobile screenshots, deterministic checks, and mobile browser proof.
- Deployed commit `10c40f535f04247ab6ee34d9b8cc3442b736369b` and browser-proved the public and owner routes on apex and `www` at desktop and 390px mobile.
- Recovered a pre-promotion hosting-quota failure by removing twelve stale,
  Git-reconstructable private build releases; the active public release stayed
  intact until the governed retry completed with a fresh rollback backup.

## 2026-08-29 — Omar Top Deals market-front-door prototype

- Added a subject-specific public concept for Omar, a pop-up merchandise
  salesman and long-time mentor to Fritz, using owner-supplied photography and
  three new reference-led editorial image candidates.
- Built a reusable six-component one-page recipe around featured categories,
  a before/during/after pop-up loop, the mentor story, a stable event/QR front
  door, and optional-consent customer inquiries rather than an unverified
  full-commerce claim.
- Added a functional phone-first owner prototype for category visibility and
  status, hold/question handling, event details and directions, public/payment
  link planning, QR sharing, and a bounded $199 foundation with a separately
  scoped Market Day Control growth path.
- Connected the public and owner prototypes with device-local storage while
  keeping email, SMS, payment, inventory, authentication, domain, and database
  effects disconnected and visibly disclosed.
- Added official-source research, a twelve-decision component ledger, a public
  component-system contract, image-generation provenance, deterministic source
  checks, and mobile end-to-end proof of public-to-owner and owner-to-public
  state continuity.

## 2026-08-28 — Alex Signal Cut V2 refinement

- Preserved the production-proven Alex V1 prototype byte-for-byte and created
  `alex-touch-prototype-v2` as a separately addressable design direction.
- Replaced the internal ownership-positioning quote with client-facing proof
  language and pushed the V2 visual system through more explicit type
  collision, offset geometry, tactile grain, linework, signal color, and
  reduced-motion-safe animation.
- Added a source-labeled Floresta Centre map and an on-page contact form; both
  the contact form and booking dialog now feed the V2 same-device Touch Control
  request desk without sending email, text, payment, or calendar activity.
- Added editable V2 location fields to the owner prototype, linked both Alex
  versions from the Booked & Branded overview and Component Lab, and recorded a
  validated Build DNA refinement record.
- Browser-checked V2 at 1440×1000 and 390×844 with zero horizontal overflow or
  console errors, verified contact-to-owner flow, and separately proved V1 was
  unchanged.

## 2026-08-28 — Alex independent-chair functional prototype

- Added a five-section, subject-specific Booked & Branded sales prototype for
  Alex (`touchdabarber4150`) without claiming that he owns the barbershop.
- Added a paired phone-friendly `Touch Control` owner prototype for request
  status, service price/duration/visibility, chair hours, and owner-controlled
  booking/payment-link placement.
- Connected the public request form and owner console with same-device storage
  while keeping the prototype free of email, text, calendar, payment, customer
  database, and other external side effects.
- Created and bundled three original image-generation concepts for the brand,
  environment, and precision story; retained three public Instagram work
  references and documented every asset/source in a machine-readable manifest.
- Added a no-network contract test for the five public sections, five owner
  panels, six local assets, non-ownership language, responsive CSS, and zero
  external effects.
- Added an honest chair-side founding-offer panel and a five-minute conversion
  plan with discovery questions, a bounded $199 close, renewal/optional-cost
  disclosures, objections, follow-up, and evidence capture.
- Added route-local icon declarations so the public and owner prototypes do not
  inherit the mixed document root's absent `/favicon.ico` request.
- Reworked the phone layouts after visual QA at 360, 390, and 430 pixels: the
  owner dashboard now keeps all three signals in one glance, task tabs open
  directly on their working content, owner controls retain iPhone-safe input
  sizing and bottom-safe-area navigation, and the public request dialog uses a
  compact scrollable mobile composition without changing its local-only scope.
## 2026-08-31 — Owner-invited deep website discovery and verified account claim

- Added a private, token-scoped, one-question-at-a-time discovery journey for
  owner-invited website clients. The bearer secret lives in the link fragment
  and only its SHA-256 hash is persisted; responses are no-store, no-referrer,
  and no-index.
- Added a durable deep-dive invitation record plus explicit, exact-recipient
  Drush drafting/sending command. An invitation can create a prospect record
  and email draft, but cannot create a payment, booking integration, domain,
  deployment, proof run, or customer-visible proof by itself.
- Connected a completed interview to a customer workspace only after that same
  email verifies an account. It becomes an account-owned website-request draft
  with a six-direction request held at owner review; no proof is generated or
  delivered automatically.
- Captured Booksy bridge, request-to-book, owner payment-QR display, service,
  policy, local-search, review, content, brand, reference, consent, and growth
  inputs needed to scope a real appointment-business build without collecting
  credentials or payment data.
- Fixed the local Drupal image to install PHP `bcmath`, required by the locked
  Drupal Commerce dependency set before a local backend runtime can boot.
## 2026-09-02 — Full Campaign Prelaunch Buildout, Muxed Audio Sync & Universal Prompt Architect Plugin

- Muxed full high-fidelity voice track into `00-hyperframes-branded-recut-commercial-9x16.mp4` via FFmpeg, synchronizing Shay's speech with the kinetic HUD calculation graphics and `#7CFC00` single-glow token.
- Synthesized OpenAI's GPT Image Generation prompting guide into universal plugin `gpt-image-architect` (`~/.gemini/config/plugins/gpt-image-architect/`) and dual skills `gpt-image-prompt-architect` and `creative-director-architecture` (non-visual edition) synced universally across `.agents/skills/`, `~/.agents/skills/`, and `~/.gemini/config/plugins/`.
- Reconciled the 68-moment 17-day campaign manifest (`marketing/campaigns/55-cents-17-day/manifest.json`) and verified 100% readiness pass across schema invariants, UTM attribution parameters, and the 3-approval release gates (`python3 scripts/campaign-readiness.py`).
- Validated clean production build of the frontend (`npm --prefix frontend run build` in 1.29s with 0 errors).
- Synchronized all 50 production assets, videos, 4K stills, articles, and documentation to Google Drive (`FAMtastic-2026/Sites/FAMtastic Designs.com/Marketing & Revenue Strategy/2026-09-02-cost-is-not-the-reason-campaign/`).

## 2026-09-02 — Multi-Tier AI Creative Swarm, HyperFrames Local Video Recut & OpenArt 4K Pipeline

- Executed parallel multi-tier stress-test across Tier 1 (OpenArt GPT-Image-2 4K, Kling 3 Omni action video, HeyGen Shay v3 avatar video), Tier 2 (Google Gemini Flash Lite batch multiplier at $0.0336/ea), and Tier 3 (HyperFrames deterministic 60fps HTML-to-Video local renderer and MoneyPrinterTurbo).
- Installed HeyGen HyperFrames `v0.8.26` and its 20+ specialized agent skills universally across `~/.agents/skills/`, `~/.claude/skills/`, and `.agents/skills/` for agent-agnostic HTML video authoring.
- Rendered 31.3s branded commercial recut (`00-hyperframes-branded-recut-commercial-9x16.mp4`) locally in 57.7s ($0 compute fee) with kinetic HUD badges, 55¢ / $199 calculation cards, and signature `#7CFC00` single-glow token.
- Rendered 4K tactile craft stills (`02-openart-master-stylist-craft-1x1.png`, `03-openart-master-mechanic-authority-1x1.png`) and 5.04s Kling 3 Omni 4K action whip-pan video (`00-kling-master-action-whip-pan-9x16.mp4`).
- Shipped interactive comparison showcase matrix at `frontend/public/showcase/creative-matrix.html` and mirrored all high-res video and image outputs to Google Drive (`FAMtastic-2026/Sites/FAMtastic Designs.com/Marketing & Revenue Strategy/`).

## 2026-09-02 — Full-Gamut "Cost Is Not The Reason" 33-Asset Campaign, F-A-M Core Doctrine & Campaign Engineer Skill Shipped

- Codified the authentic **FAMtastic (adj.)** definition and complete F-A-M letter breakdown across master brand schemas, architecture standards, and product pipelines:
  * **F — Fearless**: Fearless deviation from established norms with a bold and unapologetic commitment to stand apart on purpose (*Boldly different, on purpose*).
  * **A — Applying Mastery**: Applying mastery of craft to the point that the results are the proof (*Demonstration, not declaration*).
  * **M — Manifesting**: Manifesting the extraordinary from the ordinary (*Turning the common into the remarkable*).
- Established the **FAMtastic Designs Studio Positioning & Enterprise Scope** (`docs/architecture/FAMTASTIC_STUDIO_POSITIONING_AND_SCOPE_V1.md`), codifying our ability to engineer Business Solutions at ANY level—from $199 Web Basics to creator hubs, Estée Lauder-scale ecommerce, and multi-million-dollar government platforms.
- Created the reusable `.agents/skills/famtastic-campaign-engineer/SKILL.md` skill and `docs/research/2026-09-02-campaign-prompt-engineering-techniques.md` for researching, deconstructing, and modularizing any campaign thesis into prompt cookbooks.
- Delivered complete 33-asset campaign package (`marketing/campaigns/cost-is-not-the-reason/`) covering 4 business niches with 4 rendered MP4 videos, 15 safe-zone cropped images, 3 blog articles, and live 5-channel distribution copy.
- Synchronized all artifacts to Google Drive (`FAMtastic-2026/Sites/FAMtastic Designs.com/Marketing & Revenue Strategy/`).

## 2026-08-28 — Streamlined Website Bundle & Domain Request Flow in Client Portal

- Replaced the redundant "My Products" tab with a unified **Website Bundle & Project Hub** in "My Projects" (`/portal?tab=projects`), explicitly presenting 1-Year Fast SSD Cloud Hosting & SSL, Custom Domain, and 3 Working Concepts as a single packaged bundle.
- Rebuilt domain management into `ProjectDomainHostingManager`, allowing clients to add or change their domain request (new .com/.org/.net registration vs existing domain DNS connection with 1-click copyable A-Record `198.71.232.3` and CNAME `@`) directly in their project card without entering a 4-step wizard.
- Hardened `saveWebsiteRequest` in `CustomerPortalDashboard.jsx` to automatically preserve project defaults (`project_name`, `project_type`, `business_name`) and accept explicit target request IDs, ensuring domain and partial brief updates save reliably without validation errors.
- Streamlined portal navigation into 10 cohesive sections across Workspace, Communications & AI, Knowledge & Growth, and Account & Billing.

## 2026-08-28 — Client Portal Design DNA v1 Standard, Automated Governance Guard, and Token Context Preservation

- Established the canonical **Client Portal Design DNA v1** architectural standard in `docs/architecture/FAMTASTIC_CLIENT_PORTAL_DESIGN_DNA_V1.md` and its machine-readable contract `docs/architecture/FAMTASTIC_CLIENT_PORTAL_DESIGN_DNA_V1.json`.
- Shipped the automated validation guard `scripts/validate-client-portal-design-dna.mjs` to enforce schema invariants, routing integrity, CSS containment, single-glow constraints, and zero synthetic data leaks.
- Fixed token-scoped prospect workspace (`/portal/:token`) brand navigation in `ClientPortalPage.jsx` to preserve token workspace state.
- Enforced zero external link leakage across authenticated portal modules: recommended services and growth offers route directly to in-portal actions or `/buy?sku=...` checkout.
- Enforced strict brand tokens across all portal surfaces: charcoal `#070907` background, glassmorphism `#101310`–`#141814` panels with 1px `#252b25` borders, signature lime `#7cfc00`, single glowing action card (`box-shadow: 0 0 24px rgba(124,252,0,.35)`), and 44px minimum touch targets.
- Embedded Client Portal Design DNA v1 compliance into `AGENTS.md` and `docs/AGENT_OPERATING_CONTRACT.md` as mandatory rules for all agent sessions.

## 2026-08-28 — Modular Customer Portal Architecture, My Products Hub, Guided Project Provisioning Wizard, and Site Studio Dispatch

- Added dedicated "My Products" hub (`/portal?tab=products`) giving customers a clear overview of active NVMe SSD cloud hosting (server IP `198.71.232.3`, SSL TLS 1.3), custom domain DNS records, and workspace command center status.
- Introduced the 4-step guided Project Provisioning Wizard (`ProjectProvisioningWizard`) inside "My Projects", guiding clients through Domain Configuration (new vs existing with 1-click copyable DNS records), Cloud Hosting health indicators, Design Brief & Brand Asset uploads, and Site Studio build execution.
- Implemented the direct "🚀 Send to Site Studio for Build" action (`/api/customer/website-requests/{website_request}/send-to-site-studio`), creating `famtastic.build-dna.v1` run records and triggering concept generation routines directly from the client portal.
- Rebuilt the client portal into a modular architecture across 15 dedicated subview components under `frontend/src/components/portal/` coordinated by a streamlined `CustomerPortalDashboard.jsx`.
- Implemented complete coverage for all 13+ portal modules: Command Center (Home), My Products & Infrastructure (Products), Project Provisioning & Brief Manager (Projects), My Services & SKU Marketplace (Services), Organization Asset Library (Files), Real Growth Telemetry & Performance Digest (Results), Contextual Message Threads (Messages), Governed Solutions Advisor (Shay), Structured Support Triage (Support), Searchable Knowledge Base (FAQ), Growth Recommendations (Grow), Client Referral Rewards Tracker (Referrals), Orders & Month-13 Disclosures (Billing), Contact & Team Roles (Account), and Notification Preferences (Settings).
- Integrated the governed AI workforce boundary (Shay & AI models propose, draft, summarize, and explain; human review remains mandatory before sending, charging, mutating DNS, or deploying).
- Added Build DNA provenance inspector (`famtastic.build-dna.v1`) to the Project Command Center, providing client-visible cryptographic verification of model stages and asset hashes without exposing backend credentials.
- Updated `docs/architecture/FAMTASTIC_PORTAL_SERVICE_SYSTEM.md` with the full 15-section architectural mapping, AI governance boundary, and Build DNA standard.
- Verified zero CSS horizontal overflow and full compliance with strict static CSS guards and crawler tests.

## 2026-08-28 — Drupal Views integration, Webform intake bridge, and expanded Private Offers

- Added `famtastic_pipeline.views.inc` exposing `famtastic_notification_outbox` and `famtastic_private_offer` to native Drupal Views with full field, filter, and sort definitions.
- Added `WebformIntakeBridgeService` to mirror decoupled website requests & Solution Finder intakes into Drupal's native `webform_submission` system for submission management and email handlers.
- Expanded `WebsiteRequestOfferForm` to support all 6 core FAMtastic packages ($199–$6,999) for custom staff quotes and private discount activation.
- Fixed Customer Portal direct checkout availability in `CustomerPortalService` to unlock 1-click purchase across all 6 packages ($199–$6,999) and active private offers.
- Added deep linking support for `?tab=...` in `CustomerPortalDashboard.jsx` so email notifications and direct links land immediately on the intended tab (e.g. `billing`, `projects`, `messages`, `support`).
- Rendered prominent Private Offer highlight banner with 1-click checkout directly inside the client portal project request viewer.
- Added an Active Order Fulfillment Roadmap banner in CustomerPortalDashboard and rich status descriptions for Hosting, Domain Registration/Connection, and Website entitlements.
- Launched the Service-Specific Direct Intake System (`/intake`) with dedicated shareable forms for Hosting & Domain Setup, AI Chatbots, Custom Client Portals, Website Care & Maintenance, and Custom Website Launches with 1-click link copying and Drupal intake synchronization.
- Added `Daily Dispatch` tab to Marketing Command Center (`/admin/famtastic/marketing/dispatch`) providing a unified multi-channel day-by-day command screen across Facebook, YouTube, TikTok, Instagram, and X with 4 daily moment cards, visual asset previews (4x5 & 9x16), copy hooks, and 1-click batch gate approvals.
- Added secure campaign asset endpoint (`/admin/famtastic/marketing/asset/{filename}`) to serve visual creative artwork directly within the dispatch interface.
- Enhanced Marketing Command Center's Email Center tab with live status metrics for sent, queued, and retry/dead-letter notifications.
## 2026-08-27 — Booked & Branded research proof and four new recipes

- Added four original one-page template recipes for distinct grooming/beauty
  emotional jobs, each retaining the complete owned-site foundation, current-
  provider booking bridge, phone-oriented owner concept, Shay explanation, and
  explicit upgrade path.
- Added an official-source competitor map, primary design/accessibility source
  manifest, and a sixteen-record component decision ledger. Color, shape,
  motion, and CTA research is labeled by confidence and limitation rather than
  presented as a conversion guarantee.
- Extracted only portable patterns from the user-supplied Kimi transcript and
  recorded its SHA-256 and exclusions; no Kimi HTML, CSS, prompts, or image
  bytes were copied, and the unavailable live page was not represented as
  visually verified.
- Generated four premium parent compositions and twelve separately generated
  reference-led companions, retaining source PNGs, WebP deliveries, prompts,
  parent lineage, and hashes. The provider did not report model/cost, so the
  receipt does not falsely label companions as cheaper.
- Added a Research Proof Lab, machine-readable component registry, repeatable
  builder/tests, and Playwright evidence across five pages at desktop/mobile.
  No prospect, email, provider connection, Site Studio import, production
  deployment, or publication occurred.

## 2026-08-27 — Page/component doctrine and Git sync discipline

- Promoted the Booked & Branded page/component proof into a general FAMtastic
  doctrine: every site is composed from page recipes, stable component
  instances, versioned components, typed bindings, and named parts; a one-page
  site is a starter recipe rather than a permanent architecture.
- Linked the doctrine through Claude, Codex, the shared agent contract, Build
  DNA, Site Studio integration, the Gandalf bridge, and repository source-of-
  truth guidance so all build agents receive the same continuity rules.
- Added an explicit Git synchronization and release contract requiring agents
  to fetch, inspect incoming commits, reconcile deliberately, test, and push
  reviewed work. A pushed feature branch remains distinct from an approved
  `origin/main` SHA and a browser-proven production deployment.
- Rebased the component proof branch onto the five newer `origin/main` commits,
  preserving the Solution Finder, proof-access, and checkout changes alongside
  the component work. No deployment or production mutation occurred.

## 2026-08-27 — Booked & Branded page/component/part proof

- Converted the twelve working proof pages from one embedded page function to
  a versioned one-page recipe with nine stable section-component instances and
  explicit page, section, component, field, slot, repeater, and action identity.
- Added a Component Lab that documents page → section → component → part,
  implemented versus planned components, hide/reorder/media rules, and the
  additive Site Studio translation boundary.
- Added four Velvet Coil image-only pages that freeze all copy, typography,
  palette, sections, fields, links, controls, ordering, and component variants;
  only the `hero-media.src` binding changes across the four existing images.
- Added a deterministic contract test requiring all four normalized pages to
  share one SHA-256 hash and the same nine component signatures. No provider
  call, image generation, email, customer record, deployment, or production
  surface changed.

## 2026-08-27 — Booked & Branded room-card alignment

- Removed the desktop-only vertical/rotational transform that pushed Direction
  B below Directions A and C in every three-proof room.
- Versioned the static showcase stylesheet reference so an already-open browser
  does not keep rendering the removed transform from cache.
- Added browser regression coverage across all four concept rooms requiring the
  three desktop cards to share the same top and bottom edges.
- No proof content, image, product, price, email, deployment, or production
  surface changed.

## 2026-08-27 — Booksy-to-Owned Growth Bridge strategy

- Reframed Booked & Branded as “Booksy for discovery; your website for
  ownership,” with a controlled Tighten Up Your Locs founding case study rather
  than an unsupported rip-and-replace promise.
- Added first-visit versus repeat-visit incentive economics, a consented owned-
  client growth loop, and an existing-catalog upgrade ladder while preserving
  the proposed $199 starter and current product prices.
- Named Shay AI the FAMtastic Designs AI Business Growth Guide and preserved the
  human authority boundary for price, scope, approval, payment, launch, and
  customer communication.
- Defined six reusable niche visual families and a premium rule requiring three
  materially different candidates, selection rationale, a finishing pass, and
  complete Build DNA evidence for every premium image position.
- Updated the initial outreach email to show how the platform and owned site can
  coexist. No live price, product, campaign, email, customer record, image run,
  deployment, or production surface changed.

## 2026-08-27 — Velvet Coil Ultra quality study

- Kept the existing Booked & Branded proof rooms and four Template Lab families
  as reusable functional baselines, then added a separate quality study rather
  than disguising another hero swap as a new direction.
- Reapplied the benchmark method from the FAMU Hill Brief, Strike Network, and
  Serpent Signal work: one dominant metaphor, compositional typography, a
  bespoke art world, and a distinct information architecture. “Every Coil Is
  Architecture” is organized as a Texture Atlas, Consultation Blueprint, Care
  Lab, Atelier Console, and booking ritual instead of the baseline repeated
  service/review/QR stack.
- Generated one reference-led 16:9 hero with the built-in image generation
  tool. The exact prompt, owned fictional reference hash, provider-original PNG,
  optimized WebP, and output hashes are retained. Provider model and cost were
  not reported; no customer data, paid external-provider call, email, booking,
  payment, deployment, or production action occurred.
- Preserved the complete starter foundation inside the concept: custom domain,
  branded forwarding email, contact form, service area or map, current booking
  bridge, services, consultation, and the owner's own payment QR. FAMtastic
  does not process or receive the payment.
- Local acceptance passed 24 routes at 1440px and 390px, 23 copy contracts, 48
  viewport checks, and 20 retained screenshots. The Ultra study is local-only;
  the already-published proof rooms remain unchanged.

## 2026-08-27 — Booked & Branded reusable Template Lab

- Added four materially different, research-led template families around the
  existing fictional proof businesses: Crown & Craft, Coil & Clay, Palmera
  Press, and Saltline Prism. Each records its own texture, motif, typography,
  shape, and adaptation rules; the current proof rooms remain intact.
- Created four reference-led material fields with the built-in image generation
  tool and retained a no-customer-data receipt with provider/model/cost truth.
  Native HTML and CSS continue to own all readable text, pricing, controls,
  forms, maps, accessibility, and responsive layout.
- Restored the complete website foundation to the proposed $199 Booked &
  Branded scope: domain, one branded forwarding address into the existing
  inbox, protected contact form, call/text/social, location and map when
  needed, services, prices, preparation, policies, gallery, booking, owner QR,
  hosting, SSL, responsive/accessibility/performance checks, and launch QA.
- Positioned Booksy and other existing booking providers as valid day-one
  bridges behind the owned branded front door. Deeper scheduling, hosted
  mailbox/sending-as, reminders, SEO, analytics, maintenance, automation, and
  AI help remain evidence-led upgrades rather than surprise requirements.
- Added a vendor-neutral Site Studio translation contract for identity,
  forwarding alias, contact form, location modes, foundation modules, booking,
  owner QR, upgrade tiers, media authority, and optional HyperFrames motion.
- Local acceptance passed 23 routes at 1440px and 390px, 22 copy contracts, 46
  viewport checks, and 16 retained screenshots. The Template Lab is local-only;
  no production deploy, email, booking account, payment, or customer record was
  created by this revision.

## 2026-08-27 — Booked & Branded owner-QR payment boundary

- Removed payment processing from the Booked & Branded offer. The starter now
  displays only a customer-supplied Cash App QR or an existing QR from the
  payment provider the business already uses; the payment goes directly to the
  business and its provider.
- Replaced processor-specific sales copy and deposit-status language across the
  emails, concept rooms, package, and twelve proofs with “Your QR. Your account.
  Your money.” FAMtastic does not process, receive, settle, or reconcile these
  payments.
- Recorded that payment-processing and optional messaging costs are paid
  directly by the business to its chosen providers. Added portable Site Studio
  fields for QR display mode, owner QR asset, no-FAMtastic-processing status,
  messaging mode, and direct-provider fee ownership.
- No payment integration, messaging provider, charge, account, email, or image
  generation was activated by this revision.

## 2026-08-27 — Booked & Branded starter-first value ladder

- Reframed the fictional Booked & Branded showcase around the smallest useful
  customer win: a proposed $199 one-time launch with one year of hosting, one
  reviewed starter booking path, and the normal $9.99 monthly basic-hosting
  renewal beginning in month 13 only after separate authorization.
- Removed fear-heavy “what $199 does not include” language from the emails,
  concept rooms, proofs, and package page. The customer-facing story now shows
  how clearer services, easier booking, owner-controlled payment direction,
  and fresh testimonials can help the business earn more appointments before
  asking it to buy more software.
- Added four provider-neutral starter paths: keep the current booking link,
  connect a supported personal Google appointment page, connect Cal.com, or
  use the FAMtastic request-to-book concept. Official Google and Cal.com
  documentation was checked on 2026-08-27; provider availability and paid
  features are rechecked during setup rather than hard-coded as promises.
- Made the existing $149 Appointment Scheduling product the first optional
  upgrade and positioned SEO, analytics, reminders, maintenance, business
  email, lead follow-up, and AI help as later choices triggered by real
  business need. No new SKU, checkout, subscription, provider account, or
  recurring charge was created or activated.
- Encoded vendor-neutral Site Studio fields for booking mode, provider,
  owner-controlled account, validated embed URL, owner-supplied payment QR, and
  upgrade tier. The contract is portable but has not been imported into Site
  Studio or connected to a live booking provider.
- Expanded the browser acceptance to 21 copy-contract checks in addition to
  all 22 routes at desktop and 390px. The rendered package and representative
  email were visually reviewed; no new image-provider call was required.

## 2026-08-27 — Booked & Branded four-business proof pilot

- Upgraded the pilot into a reusable creative and offer system: Shay now
  appears as the FAMtastic Designs AI Business Concierge, a truthful package
  page defines the proposed $199 starter and expansion boundary, and explicit
  Shape, Type, Message, and Gemini Lite Image Studio roles drive three
  materially different templates rather than color swaps.
- Added one distinct Gemini Flash Lite reference-led photograph to every proof
  direction. Primary review rejected generated poster/UI text in the first
  batch, repaired the photo-only prompt, and selected 12 clean frames from 25
  provider generations. Receipts retain interaction IDs, usage, timing, exact
  hashes, rejection reasons, and a cumulative USD 0.8400 estimate pending
  provider reconciliation; the USD 1.00 ceiling was not exceeded.
- Replaced the earlier noncanonical pilot evidence object with a validated
  `famtastic.build-dna.v1` record covering the creative specialists, provider
  route, prompt and output artifacts, responsive QA, retrieval state, and
  customer/Commerce/no-send boundaries.
- Published the unlisted showcase at
  `https://famtasticdesigns.com/showcase/booked-and-branded-pilot/` in frontend
  release `412a8f51451e684d987d2ba80971531acee4d067`. Real-browser acceptance
  covered the React homepage plus all 21 showcase routes on both apex and
  `www`; every route, image, no-index directive, disclosure, and responsive
  width passed. The deploy retained a timestamped frontend rollback archive.
- Built an unlisted, no-index product demonstration for four explicitly
  fictional Florida beauty operators: a Black barber in Port St. Lucie, a
  Black natural-hair stylist in Fort Pierce, a Latino barber in West Palm
  Beach, and a white colorist/stylist in Miami. Each business has an email
  preview, a three-direction concept room, and distinct editorial,
  high-energy, and operator-first proofs: 4 emails, 4 rooms, and 12 sites.
- The original four fictional-subject hero images remain the owned visual-canon
  references for the expanded 12-image series.
- Added a deterministic static builder and a Playwright acceptance runner.
  Twenty-two routes passed at 1440px and 390px, with 44 viewport checks, no
  broken images, no horizontal overflow, no console errors, and 14 retained
  screenshots. The standard synthetic customer-journey proof also passed in
  local DB, memory-email, stub-payment, fixture-DNS mode.
- Every page identifies itself as fictional. No customer data was used, no
  email was sent, no payment was enabled, and the phone Booking Desk is a
  visual product proof rather than a persistent production backend.

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

- Fixed checkout funnel routing by registering `/buy`, `/purchase`, and `/pricing` in React routes, adding them to `.htaccess` rewrite rules, generating static SEO shells, and enhancing `PurchasePage.jsx` to preselect package SKUs from `?bundle=` parameters.
- Replaced the embedded chat panel with a clean **Hero Entry Point** (`See what your market is doing in 20 seconds`) and a **Full-Screen Mobile / Desktop Centered Sheet Overlay** to optimize mobile conversion and eliminate layout clutter.
- Materialized the **Visible Artifact**: Instant Local Market Scan card followed by 3 guided scope questions, on-screen Scope Blueprint card with locked pricing ($199–$3,999) before asking for an email, and direct 1-click checkout.
- Rebuilt the intake flow as **FAMtastic Scout**, implementing a "give-before-extracting" market scanner that delivers real local competitive insights within 20 seconds of receiving a business type and city.
- Added a 4-step progressive roadmap (`Business & City` → `Market Scan` → `Custom Scope` → `Instant Blueprint`), on-screen payoff scope card rendered before asking for contact info, quick-tap chips on every step, and "Talk to a real human" hot-lead scoring.
- Upgraded the Solution Finder into a full **Conversational AI Project Interviewer**, collecting business name, industry, logo status, domain/email situation, feature needs, and contact email with live package pricing and smart suggestion chips.
- Added `conversationalTurn()` to `AiSolutionAdvisorService` for multi-turn slot extraction, dynamic discovery questioning, and automated intake brief recording.
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
