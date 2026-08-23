# Chandler Lead Outreach Pilot — 2026-08-01

> **Archived pilot boundary:** This record documents the retired Site Studio
> preview bridge exactly as it was observed. It is not a current runbook or a
> valid provider path for new previews. FAMtastic now owns preview generation,
> slots, rooms, approval, and delivery; Site Studio receives selected build
> packets only. See `../architecture/FAMTASTIC_PREVIEW_TO_BUILD_BOUNDARY_V1.md`.

Status: **first production batch sent on 2026-08-02**

Campaign key: `chandler-landing-pilot-2026-08-01-b1`

## Source and selection

- Source: City of Chandler,
  [*New Business List — April 2026*](https://www.chandleraz.gov/sites/default/files/departments/management-services/City-of-Chandler-New-Business-List-April-2026.pdf).
- Source population: 47 public business registrations as of April 30, 2026.
- Researched: 47 source records, with deeper web-presence verification on the
  prospective first batch.
- Selected: 10 across seven categories: coffee, hydraulic equipment repair,
  automotive sales, wellness, hair, lashes, and skin care.
- Offer routing: seven `essential_199` no-owned-site leads and three
  `business_499` explicit-upgrade or fragmented-journey leads.
- Notable data-quality findings: one candidate's public email appeared to
  belong to an accounting intermediary and its phone conflicted with a
  directory record, so it was excluded from the send batch. One automotive
  domain has expired TLS and serves a HostGator default name-server page.
- Raw contact file: environment-owned, mode `0600`, outside Git at
  `~/.config/famtastic/campaigns/2026-08-01-chandler-pilot-01.csv`.

## Executed evidence

- Local Drupal 11.4.4 bootstrapped with a connected database.
- Dry-run import: 10 total, 10 qualified, zero writes.
- Write import: campaign `draft`; 10 prospects created; 10
  `proof.generate` jobs queued.
- Without `SITE_STUDIO_URL`, the first proof worker attempt failed closed and
  refused local placeholder proofs.
- With the HMAC Site Studio integration running locally, one proof job was
  accepted asynchronously and returned a durable Site Studio job id. Site
  Studio rejected direction A after both generation and repair still contained
  the banned generic phrase `web presence`. Its quality gate therefore passed,
  but the proof campaign failed.
- A second real generation attempt used the revised Culturelocs lead. Shay
  successfully generated the page HTML and Site Studio applied the proof
  template, but required image fulfillment then invoked the hard-coded
  `scripts/google-media-generate` Imagen path. That request failed because the
  configured Google API key was invalid. The current proof route is therefore
  only partly Shay-owned: Shay generates HTML, while media has no working Shay
  or OpenAI provider adapter. The partial HTML is not a customer-ready proof.
- The failed Site Studio job remained `waiting_callback` in Drupal because the
  integration has no failure callback contract. This is a state-consistency
  blocker for autonomous operation.
- The email acceptance test originally ran its workers without a prospect or
  campaign scope and advanced unrelated queued pilot jobs using fixture proofs.
  The campaign was never approved and no message was sent. The worker now
  supports exact `--prospect` and `--campaign` scopes, and the test uses the
  exact fixture prospect. The accidentally generated local fixtures are not
  accepted customer proofs.
- The mailbox screenshots confirmed that `hello@famtasticdesigns.com` is a
  cPanel/Roundcube mailbox, not Microsoft 365 or Titan. GoDaddy's authoritative
  DNS still published the old Microsoft 365 MX and an incomplete SPF record,
  while cPanel's inactive local zone contained its intended MX, SPF, and DKIM.
- The GoDaddy zone was backed up and aligned to cPanel: apex MX to the cPanel
  host, cPanel SPF, the existing cPanel DKIM public key, and monitoring-only
  DMARC (`p=none`). Both authoritative nameservers returned the new records.
- Internal delivery then passed in both directions: authenticated cPanel SMTP
  delivered `hello@` mail to Gmail Inbox, and Gmail delivered into the cPanel
  `hello@` mailbox. Gmail reported SPF pass and DMARC pass. The received message
  had no DKIM signature, so DKIM signing remains an improvement even though the
  public key is now published.
- Drupal had a second configuration defect: SMTP was enabled, but
  `system.mail:interface.default` still selected `php_mail`. Production now
  selects `SMTPMailSystem`; a Drupal-generated message delivered into the local
  cPanel mailbox. Its external Gmail copy is still awaiting inbox evidence.
- Commit `18088151` replaced the campaign delivery boundary with direct,
  authenticated PHPMailer delivery through the configured cPanel account. A
  production canary was accepted by SMTP and arrived in the local `hello@`
  mailbox before the campaign was approved.
- Production's stale `frontend_base_url` was corrected from localhost to
  `https://famtasticdesigns.com`. A generated prospect token loaded the live
  customer hub in a real browser with three thumbnails and three working proof
  links.
- The exact ten-row source file passed a production dry run, then imported ten
  qualified prospects. The image-free pilot renderer created 30 isolated HTML
  proofs and 30 SVG layout thumbnails. Live HTTPS checks found three service
  cards per direction and no image tags, placeholder copy, or legacy `web
  presence` copy.
- Campaign `chandler-landing-pilot-2026-08-01-b1` was approved with its exact
  confirmation key. One canary and nine additional messages were released at
  60-second intervals. Final state: ten `sent`, ten unique SMTP Message-IDs,
  zero queued messages, 30 completed campaign jobs, and no immediate delivery
  failure notice in the sending mailbox. SMTP acceptance is not the same as
  confirmed recipient inbox placement.
- Production update 8010 added exact recipient, From, message-body, proof-id,
  and proof-URL snapshots. An explicit campaign-key-confirmed backfill recorded
  all ten historical sent messages without resending any email.
- The authenticated `/web/admin/famtastic` operations dashboard now renders
  this campaign, its ten recipient messages, ten ready proof sets, lifecycle
  states, and ten build records. Message and build drill-down pages expose the
  exact audit snapshots.
- Historical build telemetry correctly records the pilot provider as
  `drupal_deterministic_renderer` and agent as `none`. No Shay prompt existed
  for these original ten proof builds, so none was invented during backfill.
- The Git-tracked offline Site Studio bridge now exports both new and refresh
  jobs and imports only an exact three-direction, checksum-validated bundle.
  Refresh import preserves the public campaign and variant identities. The ten
  current pilot proofs remain the bounded deterministic versions until actual
  local Site Studio refresh artifacts are generated, reviewed, and promoted.

## Findings and controls

1. Lead source, normalization, deterministic scoring, offer routing,
   deduplication, campaign attribution, and job creation work.
   Manual contact-relevance review remains necessary; a published contact is
   not automatically the right outreach recipient.
2. Production Site Studio endpoint and matching dispatch/callback secrets are
   not configured. The HMAC proof-job endpoint used by this smoke exists on the
   clean Site Studio reconciliation branch but is not present on current main;
   that branch must pass its independent release gate before merge. The local
   integration must also complete and be visually proven before production
   promotion. Its media provider must be made selectable and proven end to end;
   selecting Shay for HTML does not currently select the proof-media provider.
3. Drupal SMTP is enabled and authenticates as `hello@famtasticdesigns.com` on
   the documented cPanel endpoint `famtasticdesigns.com:465`. The pipeline
   install default previously used `support@`; it is now aligned to `hello@`
   in source. Enabled SMTP must also select `SMTPMailSystem`; update 8009 repairs
   that drift without hard-coding an environment-specific SMTP host.
4. SPF, the cPanel DKIM public key, and monitoring-only DMARC are published.
   SPF and DMARC passed at Gmail. DKIM signing did not occur and must not be
   reported as passed.
5. Public MX now points to cPanel/Roundcube, matching the confirmed mailbox and
   cPanel's local routing configuration. The obsolete Microsoft 365 verification
   TXT was preserved because it does not control routing.
6. The physical postal address was supplied and remains environment-owned. It
   is not recorded in Git. It was provided to the explicitly approved send
   process and rendered in every campaign message.
7. Drupal cron is recent on production, but no module `hook_cron()` invokes the
   automation worker. Use controlled manual Drush execution for this first
   smoke and a bounded cPanel cron only after the first delivery is proven.
8. The open endpoint exists and its acceptance test passes, but the current
   plain-text message does not embed it. Open rate is therefore not measurable
   in this configuration; click/reply/purchase signals remain usable.
9. Campaign outreach uses PHPMailer directly against the configured cPanel
   SMTP account. This boundary fails on an SMTP rejection and records the real
   SMTP Message-ID; it does not treat Drupal mail-plugin acceptance as external
   delivery evidence. Other Drupal-generated mail remains on Drupal's selected
   mail system.
10. A bounded image-free proof mode is available for this pilot. It creates
    three category-aware directions and layout thumbnails without stock-image
    dependencies, and requires the per-run
    `FAMTASTIC_ALLOW_NO_IMAGE_PILOT_PROOFS=1` approval flag.

## Gate state

| Gate | State |
| --- | --- |
| Public source captured | Pass |
| Ten leads qualified | Pass |
| Local dry-run import | Pass |
| Local tracked import | Pass |
| Three real proofs per lead | Pass for bounded image-free pilot: 30 live HTML proofs and 30 thumbnails; full-media Site Studio remains deferred |
| Campaign SMTP acceptance | Pass: production canary plus ten campaign messages accepted with unique Message-IDs |
| Recipient inbox placement | Unverified: no recipient inbox access; zero immediate bounce notices at closeout |
| Compliant postal address | Pass: environment-owned value included at send time |
| SPF and DMARC | Pass at Gmail |
| DKIM | Partial: public key published; outbound message was not signed |
| Exact campaign approval | Pass |
| Prospect messages sent | Pass: 10 of 10 recorded sent; queue empty |
| Exact recipient/message snapshots | Pass: 10 of 10 backfilled without resend |
| Operator campaign/build dashboard | Pass: authenticated production render and drill-down verification |
| Offline Site Studio promotion bridge | Pass for tested new and refresh bundle contracts; current ten pilot proofs have not yet been refreshed |

Do not automatically start a second batch yet. First monitor bounce, reply,
click, unsubscribe, and purchase signals from this campaign, review contact
relevance, and use those results to refine the next bounded group of ten.
