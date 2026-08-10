# FAMtastic Designs site learnings

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
  installs the locked dependency tree in a private release, backs up the live
  dependency tree, and promotes it with rollback paths before enabling modules.
