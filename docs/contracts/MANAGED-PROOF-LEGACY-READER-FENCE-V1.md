# Managed proof legacy-reader fence v1

September 21, 2026. Source-only candidate from `00e228d4`; no PHP execution,
tests, installation, network, providers, installed DB, route rebuild or activation.
Runtime verification belongs to main. This closes existing callers; it does not
install a receipt resolver or claim that managed proofs can now be served.

## Stored identity, never a feature flag or worker assertion

`FreshProofBinding::isManagedReadOnly` shares the exact request-marker predicate
with `isManaged`, without requesting `FOR UPDATE`. The original writer method
still requests its locking read. The new campaign read classifier also checks
request keys for requests bound to that campaign, so a malformed payload or
wrong event campaign column cannot reopen a token reader. Neither classifier
parses evidence, writes, starts transactions or treats missing schema as legacy.
Strict admission `read`, generic import denial and handoff are unchanged here.

## Existing callers are fenced

- `WebsiteRequestProofController`: managed account/staff HTML and assets reject
  before entity/path/DNA access. Public share/HTML/asset endpoints check identity
  before the legacy share helper. Existing authentication/permissions stay in
  place; new denials contain no paths, customer data or receipt metadata and use
  generic 404, private/no-store, nosniff, no-referrer and noindex headers.
- `ProofCampaignController`: after the existing token and latest-campaign
  lookup, reject the exact managed campaign before expiry mutation, variant
  serialization, selection or expired-campaign regeneration. Variant entity
  loading in the existing service is read-only and unchanged. The controller
  factory now injects `database`; repository-wide tracked constructor/call-site
  inventory found no other direct constructions before these new tests.
- `CustomerPortalService`: managed shared reads and proof/thumbnail metadata
  projections return no proof/share URLs before legacy variant/DNA handling.
  The project projection checks both a bound request and its fallback campaign.
  Enabling/rotating managed shares rejects. Explicit disabling still uses the
  original update/version/audit path after existing ownership/membership checks,
  even when proof evidence is incomplete or malformed. No GET revokes a share.

Unmanaged paths retain their existing source behavior, including generated
presentation credit `v1`, signed shares, expiry and selection. Managed canonical
credit `1` never reaches that presentation fallback. No package/receipt/path
input, media-cap exception, service registration, reader, new route or filesystem
placement is added. Prepared original/package bytes and full-site private review
readers are unchanged.

The separate token workspace summary (`PipelineController::safePayload`) is not
changed in this bounded slice; it still exposes its existing campaign summary,
not artifact bytes/DNA. A future receipt-aware reader must explicitly inventory
other callers rather than claim this fence provides a universal access policy.

## Tests authored, not executed

`ManagedProofLegacyReaderFenceTest.php` uses actual controllers, the final portal,
actual classifiers/SQL and in-memory SQLite. Account, token repository, entity
storage and campaign creation/selection services are declared doubles. Tiny
matching HTML/PNG files live only in an exact random test-owned temporary root.
Managed cases assert no entity/path lookup or DNA read, generic secure denial,
unchanged DB snapshots and zero requested write locks. Unmanaged positive cases
serve the planted exact image and expected legacy decorated HTML, share metadata,
and exercise token create/select/expiry. A classifier test records the original
writer's `forUpdate` request; it is not SQLite concurrency proof.

Coverage includes malformed/request/campaign-type/campaign-prefix markers,
unrelated/lookalike/no markers, disabled admission flag, valid share tokens,
share revocation despite invalid evidence, foreign owner/inactive membership,
project fallback, and invalid prospect tokens. Negative assertions occur outside
exception catches; there are no skips, broad error-to-success conversions or
missing-table fallbacks. Existing fixture schema gaps must be repaired in tests,
not by weakening production checks.

Main should run this file plus fresh-admission, portal, private-review and legacy
paired regressions through its existing network-denial/protected-data wrapper,
serially above 200 MiB free. This branch ran only source review and Git whitespace
checks. Syntax and test results remain unverified. Broad changelog/AGENTS/Drive
updates and runtime receipts are parent-owned by explicit task scope.
