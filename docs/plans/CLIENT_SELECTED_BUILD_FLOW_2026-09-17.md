# Client-selected build, review, then payment

Status: owner policy recorded; one protected customer review hosted; unattended
end-to-end continuation remains unproven. This plan is not a production release.

## Owner decision and precedence

Fritz directed that selecting a direction starts the complete website build,
without his approval on every routine site. The client reviews and requests
changes until fully satisfied; only their explicit acceptance of the exact
finished revision makes checkout eligible. Selection is not permission to
charge. The scaling target is 1,000+ sites with Fritz handling exceptions, not
every build. Hosting is `famtasticinc.com`; a protected client path is acceptable.

This supersedes per-site Fritz approval requirements for routine, in-scope
selected-direction builds and revisions. It does not authorize blanket customer
messages, new offers, additional paid services, domain purchases, or final
public launches. Existing initial-proof delivery gates remain until their
independent QA/policy replacement is implemented and proved.

### Recorded private payment exceptions — September 18

Request17's confirmed $200 Zelle receipt continues on the same native order/payment;
no second sale or complimentary grant. Request16's exact $199 private alumni scope
may be paid after authenticated direction selection, without waiting for completed
staging acceptance. Both still require final client acceptance and launch checks.
Ordinary customers retain the acceptance-first policy above. Private purchase
binding and the still-disabled checkout activation gate are documented in
`PRIVATE_PURCHASE_COMPLETION_2026-09-18.md`; an approved exception is not proof that
its checkout is live.

## Required state and evidence

| Stage | Advance when | Must not be inferred |
| --- | --- | --- |
| Direction selected | Authenticated account-owned choice is persisted | Final site acceptance or payment consent |
| Build queued/running | One immutable packet and job per request/selection revision | Completion from a queued job |
| Protected review ready | Complete agreed scope, independent QA, actual HTTPS artifact, signed account-bound receipt | Final launch from hosting |
| Changes requested | Client feedback creates a versioned job; same visual direction retained | Paid add-on from ordinary pre-acceptance corrections |
| Accepted | Client explicitly accepts current receipt/artifact hash with actor/time | Acceptance from silence, staff action, timeout or previous version |
| Checkout eligible | Acceptance, exact receipt, approved scope/terms and account ownership all match | A charge from eligibility alone |
| Paid / launch eligible | Customer completes authorized checkout and Commerce/provider truth confirms it | Payment from a redirect or an agent assertion |
| Launched | Separate authorized release and complete domain/HTTPS/workflow receipt | Launch from review approval alone |

Any build change or unresolved revision invalidates prior acceptance and closes
checkout. Preserve all earlier artifacts, feedback and acceptance records.
Pre-acceptance edits within agreed scope cannot be charged merely because a
legacy revision counter is exhausted. A genuinely new scope requires an explicit
proposal, not silent billing or indefinite free work.

## Automatic continuation contract

- Selection atomically persists ownership, direction, immutable input hashes and
  a deduplicated build job. Retry uses the same identity; a new revision gets a
  new version and invalidates older acceptance.
- Studio consumes the packet and creates/updates the independent customer repo.
  Existing approved design, pages, media provenance and backend stay intact.
- Policy and independent functional/visual QA decide routine review readiness.
  Missing claims/content/rights, unsupported scope, unsafe integrations, budget
  exceptions and repeated QA failure enter an actionable exception queue.
- Protected review uses the established FAMtastic Inc cPanel transport. Record
  source SHA, artifact and manifest hashes, target, authorization, QA, rollback
  and actual transport receipt. Anonymous denial is tested before content upload.
- Drupal remains the customer, project, acceptance and commercial authority.
  Studio reports facts through the authenticated packet-bound callback. No
  database status edits or manufactured receipts to make a manual upload green.
- Customer-ready notifications use the existing approved transactional outbox
  and verified account recipient. New/freeform outreach still needs exact send
  authorization. Staff receives progress and exception visibility, not a routine
  blocking approval task. Provider acceptance and inbox delivery remain distinct.

## Implementation and proof checklist

- [x] Record owner policy in AGENTS, operating/Concierge/proof contracts, plan and lead-to-launch recipe.
- [x] Locate and prove the existing cPanel API credential/upload route; see the Studio contract and customer deployment runbook.
- [x] Host Pros In Training's current static review at `https://famtasticinc.com/prosintraining/`, protected before content upload, with no DNS change or final launch.
- [x] Verify 390/768/1280 browser layouts, HTTPS bytes, anonymous denial, noindex/no-store and unchanged Inc/MBSH homepages.
- [ ] Connect the production selection queue to a revision-safe cPanel-capable Studio runner. The default adapter is still dry-run/SFTP-shaped.
- [ ] Prove completion of the customer's agreed full scope and selected-packet parity. A one-page contact review is not proof of newsletter, registration, payments or other requested application features.
- [ ] Prove the signed callback from a real worker run and record it against the correct request; this manual hosting receipt has not done that.
- [ ] Complete portal review/feedback/accept-current-revision controls and remove stale selection-to-payment copy. Guard stale acceptance server-side with the displayed receipt hash.
- [ ] Prove selected-direction revision jobs, prior-artifact retention, acceptance invalidation and no pre-acceptance revision charging.
- [ ] Test wrong-account, duplicate selection/callback, worker restart, failed upload, unauthenticated assets, stale acceptance, concurrent revision/checkout, and client rejection.
- [ ] Repair and observe registration-alert scheduling separately; flushing a backlog does not prove the scheduled worker.
- [ ] Release runtime changes through the canonical agency scripts only after isolated and protected-environment proof. Do not call documentation an enabled automation.

## Current evidence

Customer repository: `famtastic-fritz/site-pros-in-training`, deployed source
`f552643613135afe74fa2b6a93e919e3a36770ef`; index SHA-256
`65b2ed0b1d12d15dfc97753ba3dfa73f9a8d5cc09f92e7b0fe7abba8854c31b6`.
Authenticated review is 200; anonymous HTML/assets are 401. The erroneous
Designs path is denied (403) with files preserved. No payment, DNS mutation,
client acceptance, final launch or new client message was performed here.

The existing API credential is Keychain `famtastic-platform` /
`studio.cpanel.api_token`; do not copy its value. `nineoo` on FAMtastic Inc is
distinct from the agency's `xrdj7j99xhzt` account. Read live document roots and
host-aware routing, not only a nominal Site Studio default path.

### Registration-alert scheduler diagnosis

The existing five-minute lifecycle crontab calls `vendor/bin/drush` with an
implicit PHP lookup and discards stderr. In a clean cron-like environment
(`PATH=/usr/bin:/bin`), Drush fails with "designed to run via the command line"
and CGI response headers. The same read-only `--version` check succeeds with
`/usr/local/bin/php /home/xrdj7j99xhzt/public_html/vendor/bin/drush.php`.
This reproduces a concrete scheduler failure; it is not an SMTP credential issue.

At 2026-09-17T03:33:44Z the notification outbox had no queued/retry/dispatching
rows after the earlier manual recovery. The lifecycle heartbeat remained stale.
The broad worker also has staging job 309 and proof job 310 queued, so restoring
it would run more than registration email. No broad cron repair or extra send
was performed during this hosting correction. A safe repair must pin CLI PHP,
preserve the exact existing schedule/lock policy, retain a private crontab backup
and useful error log, inspect pending work, and observe a real scheduled run.
Do not report a manually delivered alert as repaired recurring delivery.
