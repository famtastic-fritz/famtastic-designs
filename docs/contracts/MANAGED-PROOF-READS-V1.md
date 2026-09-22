# Managed proof private reads v1

September 22, 2026. Source-only, independently reviewed. The reader and its first
QA/HTTP consumers exist; they are **not registered or enabled**. Import, independent
QA, release, read permission, selection and final acceptance are separate facts.
This supersedes earlier statements that no receipt-aware readers exist, not the
remaining release/provenance/installation gates in those historical checkpoints.

## Authority and API

`ManagedProofReader` accepts a trusted package factory, reviewer authenticator and
customer-release verifier. All default to NULL. None comes from request JSON,
worker paths, manifest data, a claimed reviewer ID or an approval boolean.

- `context(requestId, principal, reviewer|customer)` returns an opaque object held
  in an instance-private WeakMap. Copies, forged handles and foreign instances
  cannot supply authority; modifying public properties does not change the grant.
- `facts(handle)` verifies the actual package and returns curated current receipt,
  package, artifact, producer and actor facts. No filesystem paths, manifest,
  Build DNA, lease credential or write authority is returned.
- `readRole(handle, direction, role, assetId)` reads exactly HTML, declared asset,
  thumbnail or pinned system-logo bytes, with MIME/hash/size verification.
- `readRelativeAsset(handle, direction, path)` maps an exact declared `assets/`
  path to its role. No decoding, path aliases, arbitrary files or cap exceptions.

Every operation requires a committed connection outside a transaction. The reader
loads the authoritative import receipt and validates current account, verified
customer, active Drupal user/default-language record, organization, membership,
prospect ownership, request/campaign/expiry, current authored facts and complete
asset/rights snapshot. It locally reverses only proven import/release state when
using the strict freshness validator. Selected, revision and project transitions
remain closed until their own record-backed adapters exist.

Reviewer authentication must resolve a trusted principal to its canonical
`automation:<id>` and exclude **all actual recorded producers**. Customer identity
comes from Drupal's authenticated AccountInterface and must own this request.
Customer reads also require the separate authoritative release verifier to bind
receipt ID/hash, request/customer/campaign, package hash, producer IDs, current
review state/approval time, immutable QA-evidence hash and release-decision hash.
The later `MANAGED-PROOF-RELEASE-V1.md` source implements the stored atomic release
and customer grant, but remains unregistered with real principal/evidence resolvers
uninstalled. These read tests' synthetic attestor is not a deployable authority.

The package factory creates a new real package reader with a narrow internal
receipt/role resolver and trusted private roots/logo. Full original and credited
inventories are reverified. Current authority is re-read around file I/O and at
the resolver. Observed changes deny; this is not linearizable revocation across
concurrent commits or response delivery. Release writers still need a separate
short locked authority check. No long file processing belongs inside that lock.

## Existing consumers, preserved boundaries

`AutomatedProofRelease::context` and the portal wrapper now accept an optional
trusted principal. Managed context uses actual verified package hashes and the
import receipt instead of interpreting opaque references as legacy paths. Managed
`release` now delegates only when the optional receipt-bound release service and
trusted principal exist; otherwise it rejects without legacy fallback. The later
release contract owns that atomic research/decision/outbox operation.

Authenticated customer HTML/assets use the optional reader after existing owner
lookup. A file-shaped route preserves original relative links:

`/web/api/customer/website-requests/<public UUID>/proofs/<a|b|c>/index.html`

The older HTML entry redirects there only after an authorized byte read. HTML is
served exactly as packaged, with no URL rewrite or second creator-credit row.
Assets use the same current authorization. Responses are private/no-store,
noindex, no-referrer and nosniff, with a script/form-disabled CSP. Errors are generic
404 and **never fall back to legacy files**. Existing public/admin/token managed
read fences and private full-site review behavior remain. No service registration
or production route rebuild occurred. Portal metadata still withholds managed
proof URLs until an actual release projection is installed.

Customer email remains the existing branded renderer with a named button to the
account-bound `/portal/` project, never this internal API or an admin link. No new
mail architecture, automatic login bypass or customer message was introduced.

## Verification and limits

Tests compose real importer, committed receipt, private prepared/package files,
reader and existing consumers over disposable SQLite. Entity persistence,
provenance/reviewer identity and the customer release attestor are explicit doubles.
Coverage includes all three directions, exact ordinary image/logo bytes and MIME,
unchanged credited HTML, no DB writes/write locks, revoked rights, inactive users,
foreign accounts, changed briefs, producer self-review, forged handles, tampered
files, observed mid-read changes, default-closed configuration and legacy isolation.

The HTTP suite invokes actual controller methods and tests the route definitions
with Symfony's real URL matcher. It does not prove installed Drupal routing,
session/CSRF behavior, browser rendering or production release authentication.
The separate canonical installed disposable Drupal journey is legacy regression
with its existing owner-review fixture, **not managed unattended delivery**.
Exact receipts/counts are in `../evidence/MANAGED-PROOF-READS-2026-09-22.md`.

Next: install real independent principal/retained-evidence authority for the
unregistered atomic release; consume pending imports repeatably;
install metadata/read authorization and selected-source continuation; prove real
Mac producer provenance, installed lifecycle/tenant boundaries and cloud contention.
No local test total authorizes activation or substitutes for those results.
