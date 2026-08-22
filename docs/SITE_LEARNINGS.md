# FAMtastic Designs site learnings

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
