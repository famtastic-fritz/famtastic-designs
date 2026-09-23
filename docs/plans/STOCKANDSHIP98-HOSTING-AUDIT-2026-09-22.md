# Hosting and selection audit — September 22, 2026

**Current correction:** Fritz clarified a telephone choice; no portal save failure was established. Choice and DNS/TLS/routing have been repaired. See `STOCKANDSHIP98-SELECTION-HOSTING-REPAIR-2026-09-23.md`. Retain this audit as the historical pre-repair evidence.

Read-only production checks at approximately 11:16–11:22 PM EDT (September 23,
03:16–03:22 UTC). Fritz clarified that the intended staging address is
`stockandship98.famtasticinc.com`, hosted on the FAMtastic Inc shared cPanel
account; the purchased `stockandship98.com` domain is for the final live site.

## Confirmed hosting mismatch

The staging hostname exists inside cPanel as the **servername of the addon domain
stockandship98.com**. It is not a separately functioning staging site. Both names
are bound to `/home/nineoo/public_html/stockandship98.com` on the `nineoo` account,
IP `107.180.51.234`, whose main domain is `famtasticinc.com`.

The provider's domain inventory has no separate matching standalone subdomain.
Public DNS for `stockandship98.famtasticinc.com` returns **NXDOMAIN**, including
from both authoritative nameservers, `ns65.domaincontrol.com` and
`ns66.domaincontrol.com`. The purchased apex resolves to the correct shared host;
`www.stockandship98.com` follows that apex.

The live `.htaccess` in the addon domain root contains:

```apache
RewriteCond %{HTTP_HOST} !^stockandship98\.com$ [NC]
RewriteRule ^ https://stockandship98.com%{REQUEST_URI} [R=301,L]
RewriteRule ^$ /b/ [R=302,L]
```

The first rules force other hostnames to the purchased apex, and the last sends
its root to the B proof folder. Therefore adding a staging DNS record alone would
still redirect requests to the purchased domain under the current rules.
Observed HTTP chain: apex HTTP -> 301 HTTPS; apex HTTPS -> 302 `/b/`; `/b/` -> 200.
The browser shows **Curated Finds · Design preview**, sample merchandise and no
orders/payments. This is the guarded static proof bundle, not WooCommerce staging.

Existing customer-repository receipt `docs/HOSTED-REVIEW-2026-09-23.md`, commit `757410d`, records
the separate 10:28 PM EDT action that hosted this proof after an empty-domain 403.
That receipt explains the current redirect; it does not establish the requested
staging subdomain, account selection, selected-site build or final launch.

## Selection did not persist in the inspected records

Fritz reports that the customer chose B from his FAMtastic Designs account.
Current authoritative checks find:

- Customer 15 has one request, request 17, linked to campaign 56.
- Request selected direction is empty; review status is `customer_ready`.
- Campaign selected variant is empty and selected timestamp is null.
- No matching selection activity or selection notification is recorded.
- Only original proof-generation job 313 is present for prospect 299; no
  selected staging build job was found.
- Staging is `not_started`, with no deployed receipt or staging URL.

These facts establish that the expected saved selection/build state is absent.
They do not prove the customer never attempted the action or identify why it
failed. A separate investigation must trace the actual customer action, response
and relevant server errors before claiming the selection defect's cause.
Do not manufacture a customer-authenticated choice or final site acceptance.

## Concrete correction scope

1. Reconcile the reported B choice through the supported selection flow and
   verify its saved customer/request/campaign identity and resulting job.
2. Configure the intended staging hostname with DNS, TLS and a separate staging
   document root. Correct the existing cross-host redirect behavior as part of
   that change; an addon-domain hostname is not evidence of a separate site.
3. Build and verify the selected Curated Finds implementation there, including
   its real agreed WooCommerce scope and review handoff.
4. Release the accepted final site at the purchased domain's root when authorized.
   The final domain should not rely on a redirect into a concept-proof folder.

This audit changed no DNS, virtual hosts, deployed files, customer selection,
workflow jobs or notification state. No email was sent. Only local evidence and
project documentation were updated.

Evidence: `../evidence/stockandship98-hosting-audit-2026-09-22/`.
