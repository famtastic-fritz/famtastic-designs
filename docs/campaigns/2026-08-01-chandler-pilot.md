# Chandler Lead Outreach Pilot — 2026-08-01

Status: **staged locally; no prospects contacted**

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

## Findings before any live send

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
   is not recorded in Git. The production runtime value must be installed with
   the compliant template deployment before real outreach is enabled.
7. Drupal cron is recent on production, but no module `hook_cron()` invokes the
   automation worker. Use controlled manual Drush execution for this first
   smoke and a bounded cPanel cron only after the first delivery is proven.
8. The open endpoint exists and its acceptance test passes, but the current
   plain-text message does not embed it. Open rate is therefore not measurable
   in this configuration; click/reply/purchase signals remain usable.

## Gate state

| Gate | State |
| --- | --- |
| Public source captured | Pass |
| Ten leads qualified | Pass |
| Local dry-run import | Pass |
| Local tracked import | Pass |
| Three real proofs per lead | Fail: content repair failed once; second proof reached HTML but Imagen media failed; failure callback absent |
| Internal inbox delivery | Partial pass: direct cPanel SMTP passed both directions; Drupal passed locally; Drupal-to-Gmail evidence pending |
| Compliant postal address | Pass: supplied for environment-only configuration |
| SPF and DMARC | Pass at Gmail |
| DKIM | Partial: public key published; outbound message was not signed |
| Exact campaign approval | Not attempted |
| Prospect messages sent | 0 |

The next legal state transition is proof callback plus visual acceptance,
followed by external Drupal-to-Gmail delivery evidence and installation of the
environment-owned postal value with the compliant template deployment. Only
then can the exact ten-recipient campaign be reviewed and approved.
