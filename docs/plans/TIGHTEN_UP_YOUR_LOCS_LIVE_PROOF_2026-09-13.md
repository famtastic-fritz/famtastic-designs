# Tighten Up Your Locs — live launch evidence

Public site: https://tightenupyourlocs.com/

Status: public customer site launch verified; notices accepted by live SMTP.
The dedicated Owner Desk and appointment acceptance/calendar/rescheduling are
NOT delivered by the shared portal status-update proof below. See
[the scope correction and continuation](TIGHTEN_UP_YOUR_LOCS_OWNER_DESK_CONTINUATION_2026-09-13.md).
Remaining customer activation and mail reputation caveats are listed below.

## Verified production work

- Approved Open Chair / Ruby Signal artifact serves HTTP200 on apex and www.
  Source `a92b4ae1b69a6469ab27463ecb60b1e0d6b5c3bf`; package SHA256
  `f51435771fc75abbb9843832e598c14c428635f2b40ec879c3f143d3fea90394`.
  All five public files were fetched over strict TLS and matched expected hashes.
- Existing FAMtastic Inc hosting only. Managed-file publisher preserves adjacent
  sites and ACME files, retains private checksummed backups, and promotes index
  last. No new domain purchase, hosting plan, charge, or financial-history edit.
- Registrar saved/reloaded the customer's actual name, business and billing
  address under Fritz's explicit approval. Existing management email/phone
  retained where customer details were unavailable; privacy and domain lock on.
- Authoritative DNS routes the site and mail correctly. Trusted apex/www TLS
  expires December12,2026. Existing daily ACME schedule and the private cPanel
  certificate-install hook are configured and provider-tested. A future renewal
  cycle has not happened yet.
- `hello@tightenupyourlocs.com` created with1024MB quota. Strict-TLS SMTP465 and
  IMAP993 authentication and one real two-way email exchange passed. Email-only
  provider-native password invitation accepted September13 at08:13UTC; no
  hosting administrator access or plaintext password was sent to the customer.
- GA4 property553918564, stream15767069359, measurementG-V8M437DWV0. Opt-in
  collection204 observed with sanitized page location; actual GA4 Realtime
  displayed a visit/page_view. No collection before consent; declining disables
  collection. Enhanced measurement is off; no claimed Ads/Signals integration.
- Real Chromium390/768/1280 public-page runs: HTTP200, no horizontal overflow,
  no JavaScript errors. Public noindex removed; canonical URL and real business
  email fallback added. Actual-YAML routes tested, not merely controller calls.
- One clearly labeled real QA request saved with durable reference
  `f82a13ba-293d-40f8-baee-5cff1c9f85f5`. Verified customer11 owner session saw it;
  reviewing status PATCH200 persisted after reload; closed PATCH200 persisted.
  QA logout returned200. This was an authorized administrator-assisted isolated
  QA session, not proof the customer independently entered her password.
- Unknown-origin preflight returns no allow-origin grant. Anonymous private
  owner inbox returns404 with no customer data, matching the access contract.
- Dedicated real cron run at08:35UTC selected exactly one Locs owner alert:
  processed1/sent1/failed0/retried0. Outbox sent_at1789288503,attempts1 and
  provider message ID present; recipient matches the verified site owner.
  No broad shared queue was released. The private stable worker survives normal
  release-folder pruning; its dedicated heartbeat also records empty/error runs.
- Drupal project5 is `launched` with exact live URL, source SHA and checksum;
  an idempotent `deployment.external_verified` event links existing order19 and
  campaign50. No fabricated pre-payment staging or customer acceptance was added.

## Boundaries and follow-ups

Exact customer and Fritz launch notices both sent once at1789288555
(September13,08:35:55UTC), processed2/sent2/failed0/retried0:

- customer key `locs-launch:2026-09-13:customer`, receipt
  `<NRY4OLXrfgXFICulFk6lLUrEPoD9WsWqDWMQeiMm6ek@default>`;
- Fritz key `locs-launch:2026-09-13:fritz`, receipt
  `<hT2XFt8g8K4574YL1GSQ1y5AO0NSHTfkBfp4LaNow0@default>`.

SMTP is enabled, memory transport is not selected and protected staging is off.
These prove transport acceptance, not that either recipient opened the message.

Fritz's exact completion Message-ID was subsequently found in Gmail message
`1a099e9cddca49fe`, labeled INBOX/UNREAD, received08:37UTC. SPF, DKIM and DMARC
all pass for the FAMtastic Designs sender. This inbox proof is distinct from
the earlier Locs business-mail spam-placement caveat; neither claim is erased.

- The site captures requests, not appointments. Status changes do not send a
  reply, reserve time, charge money, or create calendar events. The owner uses
  the visitor's email/phone to agree on a time. No invented availability windows.
- Initial business-mail test passed SPF/DMARC but Gmail classified it as spam.
  A further read-only local-zone check recovered the provider's existing RSA2048
  DKIM public key despite the EmailAuth management interface being unavailable.
  The exact default._domainkey TXT was added to authoritative DNS and verified
  on both nameservers. One corrective test (Gmail1a099effccb71aed) still had no
  DKIM signature and landed in spam, with SPF/DMARC passing. Outbound signing is
  a provider-side unresolved issue; public DNS is no longer the missing piece.
  Sending/receiving work, but business-mail inbox readiness is not claimed.
- Mailbox password invitation expires September15; customer activation and
  opening either notice cannot be observed from the operator's account.
- Generic platform cron has an independently identified PHP-PATH defect. This
  launch installed only the scoped Locs worker; reopening broader jobs/messages
  requires separate backlog review. Do not advertise all platform workers fixed.
- Structured deployment/domain/hosting-entitlement projections are not created
  by the external receipt importer. The truthful release event/project update
  is proven; a generalized external provisioning adapter remains follow-up work.
- DNS branding, account consolidation, FAMtastic Hosting redesign and broader
  reseller purchase automation remain separate. The read-only Shay virtual
  assistant review is complete; it did not change the assistant.

## Reusable commands and proof location

Designs source: `scripts/locs-booking-launch.php`,
`scripts/locs-booking-worker.php`, `scripts/install-locs-booking-cron.sh`,
`scripts/locs-external-release-receipt.php`, `scripts/locs-launch-notify.php`.
Hosting source: `scripts/provisioning/artifact-release.mjs` and its runbook.
Scripts keep private receipts and mailbox secrets outside Git/public roots.

Local browser evidence:
`/Users/famtastic-fritz/Documents/Codex/2026-08-07/ok/locs-live-proof-20260913/`
and `locs-final-public-proof-20260913/` in the same task directory.
Hosting provider evidence is in the Hosting worktree's `provisioning/evidence/`.

Local regression proof: route3tests42assertions; exact notification scope3/12;
actual worker SQLite queries4/9; static-site5tests; portal DesignDNA34checks;
BuildDNA15artifact hashes. Mocked/unit checks are not substituted for the live
request, cron, mail, provider and portal evidence above.
