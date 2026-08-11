# FAMtastic Designs site learnings

## 2026-08-11 — Demand creation as a governed product system

- A large editorial library still feels unfinished when its presentation is
  text-only. Build the visual system as part of the content contract: original
  series art, selective use rather than mechanical repetition, real brand assets
  over model-generated lettering, descriptive alt text, mobile aspect ratios,
  compressed delivery formats, and structured-data image references.

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
