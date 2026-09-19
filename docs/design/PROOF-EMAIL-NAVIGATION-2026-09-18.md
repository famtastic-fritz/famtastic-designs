# Requests17 and16 proof email navigation correction

Owner explicitly requested an immediate corrected resend and reiterated that
customers must enter the portal, not Drupal's `/web` surfaces.

## Delivered correction

- Request17/customer15/campaign56 verified from Drupal; recipient resolved from
  that verified account and matched against the original notice, never guessed.
- Original outbox769/body SHA40b7e3101e75e42ced6bfc36b1fbabcc234572ebc6f7b8fb202c2bef088d938c
  remains byte-for-byte unchanged, including its original SMTP receipt.
- New key `website-request:17:proofs:56:portal-link-correction-v1`, outbox772.
- Existing `customer_proof_ready/v4` adapter, shared BrandedEmail shell, personal
  copy retained, brief apology/sign-in instructions added, AlwaysFAMtastic/Shay.
- One HTML button: Open your proof set. Destination:
  `https://famtasticdesigns.com/portal/?section=projects&request=4940a4fd-91af-40c4-b8a5-2b4dad1a3b95`.
- Body SHA `6f226c8d6846b95d03a1788f24be08fb09b672050721baa308f403c1f7982dc8`;
  rendered SHA `39ec1712cac6242ee8ccf9a8a8c6555c2a1e3a3138f61d70115e501d9983b143`.
- SMTP accepted at2026-09-19T01:02:52Z (September18 local), one attempt,
  provider `<xLIJ5AA0BQO04sGsmbD4dSAk2Hd25qIqIi2WTXVpg@default>`.
  No other mail was dispatched, no payment/selection/build state changed.
  Inbox placement/readership/real-email-client rendering are not verified.

The correction was sent using already-deployed source; it did not wait for the
recurrence repair below. Runtime scripts reuse the existing exact-key dispatcher.

After a separate explicit owner confirmation, request16/customer14/campaign57
received the same portal-button correction. Outbox773, key
`website-request:16:proofs:57:portal-link-correction-v1`, SMTP-accepted once at
2026-09-19T01:13:10Z; provider
`<cZ8IG92x2WmTjGZKFsBuxbyoRZMhUnbmaDyMFZkXsks@default>`.
Original767 unchanged. Reunion directions and private$199scope preserved.
Body SHA `4e83c4d1a4d85430e5cf0049c9f83f2f830426be7b980f42383f2408476a47f7`;
HTML SHA `3877bbf73f26edd978f7ac842fc8d1b4552bebb6a8829d91546d382a835696db`.
Six additional responsive/images-disabled cases passed before this send.

## Root cause and durable repair

The independent-QA release chose generic standard/v2 for personal copy, despite
an existing proof-ready adapter. Its autolinker printed raw protected proof URLs.
Protected bytes passed account/controller tests, but the email entry point did
not lead a signed-out client through login. Branding alone did not prove usability.

New QA releases require exactly one account-bound portal URL and use the existing
proof-ready/v4 adapter. Missing, foreign, admin/API and additional URLs fail closed
before any reveal/outbox. Exact historical retries stay intact. Standard rendering
keeps ordinary valid links as named44px buttons, rejects credential/markup-bearing
URLs and maps old queued customer-proof API links to portal entry. No historical
body, template snapshot or delivered email is rewritten.

Portal project deep links preserve login return, bring Concepts into view and
focus it. The formerly missing Review3 target now exists. Account protections
and internal API/iframe paths remain unchanged; no magic login credential added.
The development StrictMode test exposed a late401 race: the second callback read
the already-navigated login URL and replaced the saved destination with `/portal`.
Capture the destination before loading and cancel stale callbacks on cleanup.

## Verification

- Before renderer changes:72PHP presentation assertions and48 six-template
  browser cases passed. Corrected email:6 responsive/images-disabled cases,
  one correct button, no raw visible URLs, plain-text fallback and sign-off.
- Live anonymous login redirect passed on390px and1440px. Browser-intercepted
  login/workspace responses proved the deployed frontend returns to the request
  and renders three proofs. No real customer credentials or session were used.
- Fresh canonical synthetic journey at57c1d29c passed at01:00:48Z, all recorded
  assertions true: SQLite/memory-mail/stub-payment only, not provider proof.
- Current source:84PHP presentation assertions,39PHP tests/175assertions,
  34portalDNA assertions, frontend build. Final source/release checks appended below.
- Fresh synthetic rerun with the repair passed at01:08:29Z, all assertions true:
  `fresh-customer-proof-20260919T010746Z-3756/evidence.json`.
- Independent browser regression passed all4 owner/wrong-account desktop/mobile
  cases at01:12:08Z, including exact return, Concepts focus~80px, valid Review3
  anchor, three previews and signed-in reload. No real session/provider mutations.
  Initial failing StrictMode receipts are retained, not relabeled as passing.
  Existing nonblocking UI issue: a mismatch error can show another project owned
  by that signed-in account; no foreign project is exposed.

Evidence: ignored `.artifacts/proof-navigation/`, `.local-email-preview/`, and
`.artifacts/fresh-customer-proof/fresh-customer-proof-20260919T010006Z-1357/evidence.json`.
Private operational receipt also retained under server `.config/famtastic/client-delivery/`.

## Release status

Both corrected emails are sent. The recurrence repair and Concepts focus are live:

- Exact source `8eb1220912393fab824070a2a01a7556415fdf83`, pushed to origin/main.
- Canonical backend release at2026-09-19T01:20:09Z, CLI PHP8.3.32; frontend
  release at01:22:41Z, Node22.23.2. Both authoritative release markers match.
  Database backup, module/theme/dependency/config backups and frontend rollback
  archive retained. No pending database updates. The first cold-start updatedb
  command warned after reporting no updates; a separate authoritative pending-update
  check succeeded before promotion continued. The legacy scheduler was preserved;
  this release does not claim to activate the separate automation-reliability lane.
- Deployed OutreachMailer SHA256
  `2176f695067287f9d52417a1297845ec15aadabba2bf5cd548816cbaf71b3fa7`
  and AutomatedProofRelease SHA256
  `352d02105e0e8297bace87cd568ce81e14733195f4092b008d7b6b71ffe58cdc`
  match the committed source. All84 presentation assertions also pass under the
  server's CLI PHP, without loading a transport, queue or database.
- Full local module unit suite:273tests/1449assertions pass, with68 existing
  PHPUnit deprecation notices. Frontend build passed; existing bundle-size warning
  remains. Canonical deployment verified216 route shells and compiled asset MIME.
- Live frontend regression on apex and www:8 owner/wrong-account desktop/mobile
  cases pass, including exact login return, automatic Concepts focus, Review3
  anchor, all three previews and signed-in reload. Login/workspace/proof responses
  are browser fixtures, not real customer credential/session tests. Evidence:
  `.artifacts/proof-portal/2026-09-19T01-23-05-970Z-7068/results.json` and
  `.artifacts/proof-portal/2026-09-19T01-23-07-143Z-7084/results.json`.
- Actual deployed controller/asset checks at01:21:52–53Z pass33checks for#16 and
  42for#17: owner access, anonymous/cross-account denial, undeclared-asset denial
  and exact immutable asset hashes. CLI account switching is not a browser login.
- Fresh Drupal read at01:23:11Z confirms originals767/769 and corrections772/773
  remain sent, each at one attempt with the same provider receipts. Both requests
  are customer_ready, neither has selected a direction, and staging has not started.
  No additional resend, selection, payment or broad notification dispatch occurred.
- Separate unmocked read-only browser run passed on apex/www at390px/1440px:
  four populated public-home checks, real compiled JavaScript/CSS MIME checks,
  zero page errors, and all eight real request16/request17 anonymous redirects
  preserve the exact destination. No POST or customer login was performed.
  Mobile login and Concepts screenshots were visually inspected. Evidence:
  `.artifacts/proof-navigation/live-2026-09-19T01-23-55-698Z/results.json`.
  The Drive mirror was updated locally; remote Drive sync is not independently proved.
