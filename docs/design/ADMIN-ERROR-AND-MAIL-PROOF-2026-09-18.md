# Admin error continuity and owner email proof — 2026-09-18

## Scope

Owner requested one updated copy of the historical worker notification and the
admin styling/address correction. No customer communication, worker repair,
queue drain, new renderer, logo changes, or frontend redesign.

## Email receipt

One exact-key standard/v2 notification sent through existing OutreachMailer and
the deployed BrandedEmail shell. Outbox ID 752, status sent, one attempt;
provider ID `<X4DtXAahfCm2lIYloJE2ngK2sNeXekWmWl3vAfdMRk0@default>`.
Recipient is the owner's configured notification address. Subject:
`PROOF — Automation worker late — notification_dispatch`.
Idempotency key: `owner-brand-proof:notification-dispatch:2026-09-18:v1`.
Max attempts is one; no automatic proof retries. Historical September 16 times
are explicitly labeled not a new incident. Old sent rows were not modified.
This proves SMTP acceptance, not inbox placement or Gmail/Outlook rendering.

## Root cause and fix

`/admin/user` was not a route; core People lives at `/admin/people`. Core error
routes lose the `_admin_route` metadata used by the custom shell. The default
customer theme combined dark text rules with Olivero's white surfaces.

- A GET-only legacy redirect uses the named core People destination, retaining
  its `administer users` permission and Drupal's `/web` base path.
- Caller-supplied destination parameters are discarded; no arbitrary redirect.
- Shared AdminErrorContext classifies only native 403/404 plus the original
  `/admin` boundary. Both theme negotiation and shell hooks consume it.
- Existing styling, content, access checks, and error HTTP status are preserved.
  No navigation/data access is granted by selecting a presentation theme.

## Local verification

- 226 module tests, 1,135 assertions pass with current-worktree PSR-4 mapping.
- One unrelated existing test excluded: its callback assembler lives outside
  tracked source (`website-delivery-swarm/.../assemble-verified-cold-callback.mjs`)
  and is absent in this clean worktree. This is not a full-suite pass.
- Existing PHPUnit metadata deprecations remain.
- 72 email presentation assertions pass; immutable brand assets match.
- Release/live verification pending at this source checkpoint.

## First live check and correction

Release `adb6118f` at 18:48:54Z fixed the original bookmark, confirmed in the
owner's signed-in Chrome session. A new missing-admin-route check exposed core
ThemeManager passing the master (unnamed) route to negotiators. The predicate
now reads the current error subrequest's route attribute while retaining the
original main-request path boundary. Added an explicit regression test.
This is why a successful People redirect alone is not error-theme proof.

The owner proof was found in Gmail's Inbox and visually inspected with its
original logo, approved shell, historical disclaimer and signature. This is
actual desktop Gmail evidence, not broad email-client certification.

## Completed release and live acceptance

- Backend `3f1169ae3d512a80beb2976b23327f9544ce7a67` deployed at
  `2026-09-18T18:54:55Z` through the canonical deployer. Backups in
  `/home/xrdj7j99xhzt/backups/` use timestamp `20260918T185327Z` and this SHA.
- No pending DB updates; existing pilot lock remained 0 and scheduler marker
  remained present. No broad worker dispatch or historical-message replay.
  Canonical release routines reseeded existing demand content and verified the
  package catalog, sitemap and entity definitions; no new dependency versions.
- Signed-in Chrome: original `/web/admin/user` redirects to `/web/admin/people`;
  People and an unknown admin page visually show the original logo, dark shell
  and readable text. No account records/permissions changed during verification.
- Anonymous HTTP: old People URL stays 403 with admin shell; unknown admin path
  stays 404 with admin shell; unknown public path stays 404 without admin shell.
- Final local suite: 227 tests / 1,136 assertions pass with the same one unrelated
  missing-external-fixture exclusion documented above. Theme contract passes.
- Existing Drupal security-update warning observed; not hidden or repaired by
  this presentation change. Dependency/security maintenance is a separate task.
- Drive status file written to the existing local sync folder. Cloud readback
  not verified. This follow-up evidence commit does not require redeployment.
