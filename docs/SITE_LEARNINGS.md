# FAMtastic Designs site learnings

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
