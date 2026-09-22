# Private review / automatic pipeline compatibility

September 21, 2026. Local integration, not deployed or activated.

Agency main moved from f5bc140e to 7227ceb63f742e438f151b430bb6bc93a32cce41
while this isolated review branch was being verified. Preserve its private
full-site readers, nested routes, portal presentation, staff attachment and
deployment resource fixes. Do not replace either CustomerPortalService wholesale.

## Preserved lifecycle and access

The queue retains default-off fresh admission, trusted freshness intent, durable
managed identity, exact reuse and legacy isolation. Under its existing request
lock it now also checks exact public ID and rejects full-site markers in both
stored and supplied intake. Explicit editing, worker context and dispatch remain
rejected for attached full-site reviews; no proof job or notice is added.

After existing authorization, optional deep-dive login/verification repair leaves
a full-site-marked request unchanged. Marker presence is sufficient, including
malformed/null metadata: a failed presentation projection is not new-work authority.
Managed request no-op remains intact. Credentials, active user, verification,
flood and request-owner checks are unchanged. An account without active workspace
membership may still sign in, but receives no organization or private request
access; the existing repair skips that case. This is not restored membership.

## Attachment transaction order

- Existing draft retry: advisory event hint, then request -> current membership
  -> current event. Revalidate exact immutable draft identity; never adopt changed
  evidence or silently create a replacement.
- New draft: membership serializes first creation. No ordinary repeatable-read
  snapshot is established inside the new root before that lock. Reread the event
  with a current locking read. If another creator won, roll back and destroy the
  root before retrying request-first, at most once.
- New creation rejects an unknown caller transaction before locks/writes. A
  savepoint cannot refresh the caller's old snapshot or release its membership
  lock. Existing-request replay retains nesting and never commits caller work.
- Attachment locks current membership after request, and uses current job and
  prior-review event reads. Existing static package validation remains intact.
- Track closed transaction handles before destruction. A post-commit callback
  failure must propagate its original exception, not attempt an invalid rollback
  of already committed artifacts. No automatic retry follows that uncertainty.

Customer/active-organization existence reads during nested existing replay remain
ordinary reads as upstream; current organization state under a stale outer
snapshot is not newly proven. Private copies can remain unreferenced after a DB
failure. No automatic cleanup or crash-durability claim is added.

## Verification

All receipts below are under `/tmp/famtastic-phase2-review.NVAfPl/`, run serially
with external-network/protected-data denial, a 200 MiB disk guard and before/after
protected inventories. Every receipt reports protected data unchanged.

- `root-worker-integrated-php.omvnDf`: preceding root/harness merge, **661 tests /
  3,422 assertions**, 1.717s suite / 2.143s guarded, 54 MiB.
- `root-worker-paired.P9C8Ag`: **20 tests / three files**, 110.57s suite / 111.075s
  guarded, including actual asset writer integration, selected records and source
  association. Supersedes the interrupted asset-writer run for that source pair.
- `full-review-reconciled-focused.lwruw2`: intermediate **18 tests / 242 assertions**,
  before callback cases. Retain as history, not final combined totals.
- `private-review-integrated-php.kRnVff`: final combined **716 tests / 4,160
  assertions**, 1.943s suite / 2.365s guarded, 58 MiB; zero failures/skips, same
  68 existing PHPUnit deprecations. PHP 8.5.9 / PHPUnit 11.5.56.
- `private-review-frontend-contracts.PSJAD9`: actual portal SSR test **1/1**,
  existing portal DNA validator **34/34**, three deployment-script syntax checks
  and whitespace pass. No deployment script was executed.
- `private-review-upstream-negative.XnIpoD`: load only upstream 7227 service in
  memory, SHA256 `5d8b7c9957396702fe432f7c265cb3a0d69f90f159a48120442010ba6161c67a`.
  Six selected cases fail: two lock/nesting assertions and four actual
  post-commit `rollBack() on null` errors (6 tests / 34 assertions, exit 2).
  These are expected source regressions, not bootstrap or transport failures.

Retain `private-review-login-reconciled.OoOHKG` and
`private-review-auth-corrected.e5IjvS`: the new test initially expected denied
account login without membership, then expected false instead of the documented
NULL request result. Source inspection confirmed unchanged account-versus-project
authorization. Corrected tests assert successful sign-in without workspace access
and direct-helper denial; no production access check was weakened.

The tests run actual final services/controllers and SQLite transactions. File
interfaces and credentials/sessions are explicit doubles; an observed-select
fixture models a missing advisory hint and rollback but is not MariaDB contention.
Independent source review found and closed the post-commit exception defect.

## Remaining integration and deployment gates

The new full paired Studio rerun, real private-attachment/asset-writer contention,
installed Drupal migration and live sessions are separate evidence. Worker update
8066 must exist before coordinator/legacy helpers run. The upstream eight-file
private-review deploy lane intentionally cannot release this combined worker
change; do not bypass its scope restriction. No provider call, customer send,
production/source promotion, cloud resource or activation occurred here.
