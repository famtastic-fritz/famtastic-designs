# Account-owned complete-site review attachment

This source-only addition supports the owner's September 21 request to build Travel
Addicts Courier Express and place the completed website and research in its existing
customer account. It does not make a single completed site pretend to be three
directions, record customer selection or acceptance, imply payment, queue a notice,
or launch the customer's independent source repository.

## Reader and isolation

The existing customer portal uses a Drupal session cookie, not a URL bearer token.
`GET /web/api/customer/website-requests/{public_uuid}/full-site/{path}` checks the
authenticated Drupal user's customer record, exact request ownership and current
active organization membership on every file read. Anonymous and other customers
receive the same 404. A separate staff QA reader at
`/web/admin/famtastic/website-request/{numeric_id}/full-site/{path}` requires
`administer famtastic pipeline` in the route, controller and service. It reads the
explicit request and never impersonates the customer. Internal staff navigation
stays on that staff route. No public share URL or customer-route bypass is added.

The source package is copied to `private://famtastic-full-site-reviews/{uuid}/{digest}`.
The server accepts only declared static files with an exact path, byte length, SHA-256,
role and content type. It rejects symlinks, traversal, unknown paths, executable
server files, arbitrary documents and hidden configuration. Every served byte is
verified against the manifest. Private storage must already be configured outside
public serving; copying the package to a public folder is not this mechanism.

HTML is served in a CSP sandbox with scripts allowed and **without** same-origin
privilege. It cannot read agency storage/cookies, call APIs, submit forms, open
frames or workers, or navigate a parent window. User-activated external links can
open a new tab with no opener. Local CSS, classic JS, image and font resources are
inlined from verified files, avoiding cookie-dependent subresource loads from an
opaque-origin sandbox. Internal links are rewritten to the authenticated reader,
preserving bounded safe query parameters and fragments. Verified deferred scripts
run after the authored body, in their original order. Raw script/style text is
preserved through libxml serialization, with closing-tag rejection, so Unicode
JavaScript strings retain their intended values. Each HTML response pins its first
manifest digest and rejects a version change during subsequent asset reads.
The portal opens the review as a separate top-level tab; do not embed it in a
cross-site frame. Clipboard access may be restricted by the browser; site copy
tools must leave manually selectable text as a fallback.

Runtime resource references must be local and declared. CSS `url()` references
are supported; CSS imports, ES module imports, remote runtime scripts/fonts/images,
inline event attributes, embedded frames and active document formats are not.
Source HTML is preserved on disk; the response alone receives the isolation rewrite.
No-cache, nosniff, no-referrer and noindex headers apply to all responses.

## Trusted manifest

`famtastic.full-site-review.v1` requires:

- A unique lowercase version slug `review_id`, title, `entry_path`, actual 40-character
  `source_commit` and `build_id` already registered through Build DNA with that SHA.
- `pages: [{label, path}]`, including every HTML page. Pages use `index.html` or
  `page-slug/index.html` and remain navigable through the reader.
- `documents: [{label, path}]`, including every explicitly approved customer document.
  Only `.txt` and `.md` under `review-documents/` are accepted and served as plain text.
- `files: [{path, role, media_type, sha256, bytes}]`. Roles are `page`, `asset`,
  `document`; resources must live in `assets/`. Static types: HTML, CSS, classic
  JavaScript, PNG/JPEG/WebP/AVIF/ICO, WOFF/WOFF2 and explicit plain-text documents.
- Optional `research: {overview, researched_at, sources:[{title,url}]}`. Sources
  use HTTPS without embedded credentials. The current limits are 300 files, 10 MB
  per file, 64 MB total, 50 pages/documents per list and 20 cited sources.

## Attachment

The staff-only `FullSiteReviewService::attach()` requires exact expected customer,
organization and request identity plus the actual actor and owner-authority reference.
The request must remain draft/submitted without a proof job, active proof, project
or Commerce binding. The existing `staff_assisted_brief` metadata is preserved, with the current
review attached beneath it. Original customer answers and lifecycle states are not
changed. An immutable event retains the manifest, actor, authority and original
intake bytes/hash. Execution uid is a permission/audit fact, never customer approval.

The exact replay is a no-op after verifying the stored package. Reusing the same
review id with a different digest fails. A historical replay cannot replace a newer
current version. A new version gets a new review id; prior events and files remain.
No job or notification outbox APIs are called.

When no request exists, `createAndAttach()` creates an explicitly staff-authored
draft and attaches the package in one transaction. It requires an active exact
customer/organization membership, a project name and a unique staff request key.
The membership row lock and immutable binding event make retries idempotent;
conflicting bindings or an existing matching request are rejected. Failure rolls
back the draft and audit event. No prospect, customer submission/authorship,
selection, payment or notification is manufactured.

The private CLI requires a checksummed manifest and an explicit active staff
execution account because Drush otherwise starts as anonymous:

```text
drush famtastic:full-site-review-attach /private/review-manifest.json /private/site-dist \
  --request=<exact-request-public-uuid> --customer=<verified-id> --organization=<verified-id> \
  --staff-uid=<active-staff-execution-account> --actor=codex:<actual-task> \
  --authority='<exact owner instruction reference>' --checksum=<manifest-file-sha256>
```

For the atomic new staff draft, replace `--request=...` with
`--new-request-key=<unique-owner-authorized-key> --project-name='<exact project name>'`.
Keep the same verified customer, organization, staff uid, actor, authority and
manifest checksum. The two request modes are mutually exclusive. The receipt
returns the created request id and public UUID for exact subsequent verification.

Only the sanitized `full_site_review` DTO reaches the portal: real page count,
title, protected page/document links and research. Private paths, full file manifest
and internal actor evidence are excluded. Customer request edits cannot replace
server-stored `staff_assisted_brief`. The portal presents “Full website” and “Ready
for your review”; the existing brief can stay draft. It provides no selection,
acceptance, payment or launch controls for this separate review attachment.
Brief/domain mutation controls are removed from this review state; an existing
Messages link lets the customer request changes without sending anything on click.
The server rejects customer save/submit and claimed deep-dive resume before writes
or notifications. The central proof enqueue helper rereads the request under a row
lock, rejecting even a stale intake snapshot; the worker context also rejects the
attachment. Attachment and enqueue therefore serialize against the same request.

## Verification and release boundary

Focused test: `FullSiteReviewTest.php` uses actual SQLite records and temporary
private files to check quiet/idempotent attachment, conflicting versions, draft and
intake preservation, ownership/membership, anonymous session denial, traversal,
undeclared files, symlink/integrity rejection, safe HTML/resource rewriting and
sandbox headers. It also checks atomic staff draft creation/rollback, safe DTO
projection, all blocked mutation/dispatch paths and a real database version switch
during rendering. Combined with `WebsiteRequestAuditPreservationTest.php`, the
focused run passes **21 tests / 301 assertions** on PHP 8.5.9 and PHPUnit 11.5.56.
The reused runtime reports an existing Drupal integer-mode `fetchAll` deprecation
and an unwritable simpletest browser-output directory; the command exits zero.
`node --test scripts/test-full-site-review-flow.mjs` passes and renders the full
project view, stale editor state, review card and next-step helper. Portal DNA
validation passes 34/34. The frontend compiled with `publicDir:false`; that is a
compile check, not a deployable release bundle.

`scripts/full-site-review-browser-fixture.php` is a loopback-only CUA harness. It
requires explicit `FULL_SITE_REVIEW_FIXTURE_PACKAGE` and
`FULL_SITE_REVIEW_FIXTURE_MANIFEST` paths and synthetic `/fixture/owner`,
`/fixture/other`, `/fixture/out` sessions. It uses the real package reader/renderer
with a synthetic HttpOnly, SameSite=Lax cookie, never a Drupal customer account.

Native Chrome CUA on the actual 14-page, 5-document Travel Addicts package verified
the rendered fonts and artwork, Home → Starter navigation preserving the cookie
and query, preselected Starter plan, enabled fields, and a prepared unsent inquiry.
The corrected email subject retains its Unicode punctuation. Fixture assertions
visibly confirm blocked localStorage and cookie access. Both another synthetic
customer and a signed-out fixture session see “Review not found.” External contact
links were not clicked. Source commit was
`82a47502e01d07582c3b2721d5dae04aef5cfd91`; raw manifest SHA-256 was
`3d4d3ccd753ad1f98b0dbfff214663b73aa2b39aac145439c540fc8a0e54736a`.

These tests do not prove production cookies, deployed source or real customer-visible
artifact bytes. Before claiming account delivery, verify
the real authenticated session, all pages/documents, image/font rendering, navigation,
the static request composer, an anonymous denial and another-customer denial against
the served version. Preserve exact source and package hashes. No deployment or
production account mutation was performed as part of this source implementation.
