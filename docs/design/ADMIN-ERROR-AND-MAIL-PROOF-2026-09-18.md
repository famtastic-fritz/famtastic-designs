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
