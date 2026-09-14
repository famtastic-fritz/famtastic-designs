# Client messaging production release — September 14, 2026

## Delivered behavior

- One durable inbox exposes original contact/quote submissions and replies in
  [Drupal admin](https://famtasticdesigns.com/web/admin/famtastic/messages) and
  [the signed-in portal](https://famtasticdesigns.com/portal/?tab=messages).
  Unread, needs reply, waiting, queued email and SMTP acceptance are separate.
- Staff permissions and verified customer membership govern access. Existing
  staff can sign in through the portal without a customer record. Orders also
  expose request/proof progress before purchase and staff access to client orders.
- Seven operations lists have database search/status/date filters before
  pagination. Website Requests has five readable columns, expandable briefs,
  full desktop width and explicit archived-request retrieval.
- Drupal AI is updated from 1.4.6 to 1.4.8 and Composer from 2.10.2 to 2.10.3.
  Production locked audit returns zero advisories and zero abandoned packages.
  Drupal core remains 11.4.5; database updates are complete.

## Source and deployment

Worktree: `codex/client-messaging-proof-rescue`, based on main `598aa8ff`.
Implementation commit: `4599a22be878a6c7c9a4e4109b2fb2787703580a`.
Archive correction and final runtime source:
`fea57649a781bfc8440131327f980bd08a388315`.
Both were integrated through normal fast-forward pushes to main.

Canonical checked-in deployment scripts installed the exact reviewed source:

| Component | Source | Release marker time (UTC) |
| --- | --- | --- |
| Frontend | `fea57649a781bfc8440131327f980bd08a388315` | 2026-09-14T20:54:11Z |
| Backend | `fea57649a781bfc8440131327f980bd08a388315` | 2026-09-14T20:57:05Z |

Migration 8063 imported 15 existing public requests without sending notifications.
The final database update check reports no pending updates. Production PHP is
8.3.32 and the frontend was built with Node 22.23.2. Both release markers were
read back from the host after deployment. Canonical backups and database dump
paths are retained in the private deployment receipts. Broad cron/outbox
processing was not used for this release or either customer send.

Final documentation/signature guidance is a later docs-only checkpoint. It
changes no runtime source; the deployed markers remain the exact runtime SHA
above. Do not interpret the documentation commit as another deployment.

## Customer communication receipts

Kesline's detailed request 14/campaign 53 has three distinct working concepts.
Independent desktop/mobile review and protected customer media/controller
checks passed before the exact notice. Request 13 is the thin registration
duplicate and was reversibly archived, retaining its original records.

| Notice | Exact outbox | Accepted by SMTP (UTC) | Local time (EDT) |
| --- | --- | --- | --- |
| Three concepts ready | 633 | 2026-09-14T20:27:27Z | 4:27:27PM |
| Clarify the other website request | 634 | 2026-09-14T20:53:46Z | 4:53:46PM |

The original intake 18 explicitly requested two websites, but neither saved
website request identifies a second business. The owner-authorized clarification
asks whether the other request is a duplicate or a separate website and, if
separate, for its name/purpose. It adds no deadline, price or project promise.
Message 21 is saved beside original message 20 in thread 18, linked to canonical
request 14. Each notice was dispatched once through its exact outbox key.

SMTP acceptance and account visibility are proven; recipient inbox placement,
reading, reply, direction selection and a customer website launch are not
claimed. See `kesline-proof-delivery.md` for the proof-specific receipt.

The owner's later signing instruction is now shared in
`docs/architecture/CLIENT_MESSAGE_SIGNATURE_CONVENTION.md`, root agent guidance
and cross-session memory. Future agent correspondence signs Shay or Shay-Shay.
No already-sent message was edited or resent.

## Validation

- Backend module: 203 unit tests, 1,078 assertions; existing PHPUnit docblock
  deprecations remain. Full disposable Drupal HTTP smoke: 40/40 checks,
  including migration 8062→8063, real staff credentials, CSRF, tenant denials,
  idempotent reply/outbox, all seven rendered filters and archived visibility.
- Frontend build, Design DNA 34/34 and loader/redirect 8/8 passed. Responsive
  browser suite: 11 passed at 390/768/1280; one deliberate duplicate tablet
  case skipped. These UI checks use mocked APIs.
- Fresh isolated customer lifecycle passed all assertions: exactly three
  proofs, ownership, order/project binding and lifecycle progression. That
  fixture uses memory mail, synthetic payment and local deploy/DNS fixtures.
  Its source_sha names the base HEAD while the tested changes were uncommitted;
  it is not production provider or deployment evidence.
- Actual signed-in production admin displayed the imported original inquiry,
  sent clarification, linked proof request and provider-accepted state.
  Owner reading updated only the owner's unread count as designed.
- Actual signed-in Fritz portal displayed the same conversation and delivery
  state. Desktop 1920 and mobile 390 were visually reviewed without page overflow;
  the reply composer fits mobile. No extra message was sent during UI checks.
- For Kesline, Website Requests default/search kept request 14 and excluded
  archived request 13. Explicit Archived returned 13; filter values visibly
  persisted. At 1920 the table uses 1615px with five readable columns, rather than the former 1150px cap and
  nine squeezed columns. The full brief remains available by disclosure.
- Apex and www loaded the new `index-CR3_MONk.js` frontend; www rendered the
  public site without the services-unavailable state or JavaScript errors.
  Drupal update metadata refresh removed the stale security warning without
  running general cron.

## Evidence locations and remaining boundary

Private local evidence lives under `.artifacts/`, including
`final-messaging-release-markers.txt`, the frontend/backend deployment logs,
`production-security-verification.log`, `update-metadata-refresh.log`,
`client-messaging/inbox-20260914T204840Z-11033/http-results.json`,
`fresh-customer-proof/fresh-customer-proof-20260914T203448Z-4518/evidence.json`,
`kesline-proof-rescue-20260914/` and
`kesline-other-request-audit-20260914/`. Native live browser screenshots were
reviewed in this task. Raw private dumps and credentials remain outside
tracked files. Hosted CI is not claimed.

The unattended creative-worker connection remains unconfigured. A job dispatch
marked completed previously meant only a waiting callback and did not contain
proof artifacts. This release makes stalled proof states visible and completes
Kesline's exact recovery; it does not claim future unattended proof generation
is connected. This limitation is separate from the tested and deployed inbox.

The required Drive status file is
`2026-09-14-client-messaging-production-release.md` under the existing
`My Drive/FAMtastic/famtasticdesigns.com/` mirror. The local mirror copy is
verified; remote Drive synchronization is not separately attested.
