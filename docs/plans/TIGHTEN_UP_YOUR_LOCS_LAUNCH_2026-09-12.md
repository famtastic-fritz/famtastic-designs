# Tighten Up Your Locs: launch and repeatable provisioning

## Purpose

Finish the real customer launch, then preserve an evidence-backed provisioning
workflow reusable by Shay, Codex, Claude, and future customer sites.

## Goal

An approved site on its customer-owned domain, working email, valid renewable
TLS, and verified Google Analytics. Use existing FAMtastic Inc hosting. Do not
purchase a separate hosting plan. Keep provider purchasing, hosting provision,
customer ownership, and Drupal/Commerce records distinct.

## Tasks

Current closeout: the approved customer launch is verified September13.
The evidence and remaining non-launch follow-ups are in
[the live proof record](TIGHTEN_UP_YOUR_LOCS_LIVE_PROOF_2026-09-13.md).
Historical checkpoints below are retained as history, not current blockers.

- [x] Locate a usable reseller storefront and existing discounted shopper.
- [x] Refresh production request linkage rather than trust the old draft notes.
- [x] Verify cPanel API authentication and locate the existing Locs subdomain.
- [x] Confirm the authorized domain purchase with the provider receipt.
- [x] Create a dedicated GA4 property and web stream; live collection and Realtime proven.
- [x] Run and inspect the canonical fresh synthetic journey evidence.
- [x] Prepare and locally test the approved design's release candidate without publishing review copy.
- [x] Complete the live request, owner-inbox and scoped notification integration.
- [x] Verify selected artifact, existing order/payment linkage, project and deployment.
- [x] Reconcile post-payment deployment honestly without fabricated pre-payment staging.
- [x] Confirm exact domain, registrant, total cost, payment source and purchase authorization.
- [x] Verify domain; record provider receipt and customer registrant update.
- [x] Implement resumable, secret-safe cPanel provisioning and artifact publishing with tests.
- [x] Provision domain/document root without altering neighboring sites.
- [x] Deploy exact approved artifact; verify domain and DNS routing.
- [x] Provision customer email, authentication records, round-trip proof and native invitation.
- [x] Install TLS, verify HTTPS and provider-test the scheduled renewal install hook.
- [x] Configure correct GA4 property/stream and prove collection.
- [x] Browser-test mobile/desktop and real request/owner actions.
- [x] Record verified external release against Drupal project and existing order.
- [x] Update canonical changelog, capabilities, learnings and Drive mirror.

## Status

Domain purchase confirmed on 2026-09-12 after Fritz explicitly instructed:
"I'm at the review screen. Hit complete purchase." The review showed one item,
`tightenupyourlocs.com`, one year, USD 11.59 plus USD 0.20 taxes/fees, total
USD 11.79. The Complete Purchase button was clicked once. The provider's
confirmation and receipt were then read; confirmation number **4183505895**.
The receipt confirms the exact domain, one-year term and USD 11.79 total, and
states that a copy was sent to the account email. No separate hosting, paid
protection or email product was purchased.

**Renewal and ownership checkpoint:** Checkout displayed annual auto-renewal
at USD 11.59 and disclosed that renewal pricing can change. This proves the
discounted purchase, not an independently reconciled wholesale/base cost.
Billing was under the existing FAMtastic purchasing account; the review and
receipt did not identify the domain registrant. Customer ownership/contact
verification and registrar product inventory remain outstanding. Do not treat
the payer as proof of the intended customer's domain ownership or reuse this
authorization for another purchase. No DNS change, mailbox creation, TLS
installation or production deployment has been performed by this task.

## Follow-up checkpoint — 2026-09-12, 21:58 UTC

- Registrar product inventory and DNS controls now show the purchased domain.
  Actual domain Contact Info lists Fitzgerald Medine / FAMtastic Designs, not
  Shay. Domain Privacy is on; renewal is September 12, 2027 at the currently
  displayed USD 11.59/year. No transfer or contact edit was attempted.
- **Owner action:** ask whether to update registrant contacts to Shay's existing
  verified profile while retaining FAMtastic management/billing. Request sent
  only to Fritz's requested Gmail address, subject `Locs launch — domain owner
  confirmation needed`. Gmail message `1a0979aaa8f3fe65` has SENT and INBOX
  labels. This is provider acceptance/inbox presence, not evidence Fritz read
  or approved it. Do not resend unchanged alerts.
- Production request `12` is still converted/customer `11`/organization `11`/
  project `5`, with staging and staging review `not_started`. Table name is
  `famtastic_project_request`, not `famtastic_website_request`.
- Existing active booking-owner binding for this request is
  `site-dffd4cb9c3aa47fd`. Preserve it. Production booking capture is enabled
  only for `noise-cuts`; availability enablement is empty. The exact Locs GET
  availability endpoint returns 404 `availability_not_enabled`. No write test
  or customer notification was performed.
- GA4 account `63753322`: created **Tighten Up Your Locs**, property
  `553918564`, web stream `15767069359`, measurement `G-V8M437DWV0`, URL
  `https://tightenupyourlocs.com`, America/New_York timezone, USD. Enhanced
  measurement is confirmed OFF on the saved stream; standard pageviews only.
  No tag is installed on the public site, no collection proof, and no customer
  access invitation has been sent. Do not create another property on resume.
- Hosting preflight at 21:51 UTC still returns domain create / ownership
  unverified / zero provider writes. SSL inventory includes the old Locs
  subdomain, not proof of a certificate for the new domain or renewal.
- Canonical synthetic proof initially lacked local dependencies. Reused the
  matching Composer-lock runtime in the canonical checkout, installed the
  isolated frontend lockfile with scripts disabled, then obtained exit zero.
  Evidence: `.artifacts/fresh-customer-proof/fresh-customer-proof-20260912T215610Z-27293/evidence.json`.
  All top-level assertions and nested boolean checks were inspected and true;
  exactly three proofs recorded. Runtime used Node 24 (repository production
  target is Node 22); this remains **locally proven**, not provider/live proof.
- A read-only artifact trace confirmed no finished Locs release package.
  Selected hybrid source is commit `c894ca31`; root form shell at `dffa35f3`
  has empty endpoint configuration. Existing backend owner-scoped APIs are
  real, but no customer React owner inbox consumer or booking notification
  delivery was found. `CustomerDeploymentService::renderRelease()` generates
  generic markup, not the selected proof; do not use it as a design-preserving
  deployment primitive. A local candidate is being prepared under
  `website-delivery-swarm/pilots/shay-tighten-up-your-locs/releases/2026-09-12/`.

## Follow-up checkpoint — 2026-09-12, 23:08 UTC run

- Local release candidate is now prepared, not deployed. Five unit tests and
  browser checks at 390/768/1280 passed again, including mocked durable receipt,
  duplicate prevention, ambiguous-response retry blocking and correctable input
  rejection. All 15 Build DNA artifact checksums validate. Requests remain off;
  external fonts and Google collection were not proven by mocked browser tests.
- Re-ran the canonical synthetic suite successfully. Evidence:
  `.artifacts/fresh-customer-proof/fresh-customer-proof-20260912T230944Z-30705/evidence.json`.
  Parsed top-level assertions and both nested evidence files: all boolean checks
  true, three proofs, provider calls false. Node 24 remains a runtime caveat.
- Hosting CLI safeguards remain 16/16 passing locally. Live availability GET
  still returns 404 `availability_not_enabled`. Public apex A answers are
  `13.248.213.45` and `76.223.67.189`, not the intended hosting IP.
- Read the existing owner-confirmation Gmail thread: only the original sent
  message is present; no reply or new approval. No duplicate email sent.
- Branch/common-dir re-anchored, origin fetched with no incoming main changes.
  Plan audit is clean. No registrar edits, DNS writes, deployment, mailbox,
  certificate installation or customer launch notification performed.
- Remaining owner gate is the already-emailed registrant decision. Independent
  technical gates also remain: live request/owner response workflow, public
  contact/privacy details, hosting/mail/TLS renewal, actual GA collection and
  an exact committed release with real end-to-end verification. Ownership
  approval alone will not constitute launch completion.

## Resume checkpoint — September 13, 07:12 UTC

Fritz explicitly requested resuming work, not repeating unchanged approval
checks. The prior implementation incorrectly coupled technical domain control
to final customer registrant identity. These are now separate, tested gates:
fresh exact-domain control plus scoped user authorization permits hosting;
customer ownership remains unverified and final launch remains blocked.

- Hosting CLI: 18 tests pass. Authenticated registrar control refreshed; no
  contacts changed. Scope preserves no new purchases/charges/transfers.
- cPanel created tightenupyourlocs.com in isolated
  `/home/nineoo/customer-sites/tighten-up-your-locs/public`; API postcondition
  and SSH directory verified. Exact vhost IP107.180.51.234 differs from the
  server machine IP107.180.116.83; DNS uses the vhost IP, not hostname -i.
- Changed only registrar apex A from Parked to107.180.51.234, TTL600. Both
  authoritative nameservers now answer the new IP. Other six records preserved;
  local recursive DNS still caches parking, so public propagation is incomplete.
- Existing SSH and ACME3.1.4 client work. Let's Encrypt issued apex+www cert;
  installed via exact-domain cPanel API at07:11:22UTC. TLS verification against
  the origin passes for both names, HTTP403 because customer files are not yet
  deployed. Certificate expires December12,2026. Origin proof is not complete
  public-DNS/customer-site proof. Temporary local certificate/key copies were
  removed; authoritative private originals remain in server ACME storage.
- Existing ACME cron is present, but the stock cPanel deploy hook invokes a
  broken jailed uapi executable. Locs renewal issuance is registered; automatic
  installation after renewal is NOT verified and requires a safe HTTP API hook.
- Independently tested local booking-owner verification correction: four tests,
  66 assertions. Missing/unverified customers now fail closed before binding
  lookup. Not deployed. Owner inbox/atomic notification-outbox integration is
  underway locally; no public capture enablement or real customer send yet.

Evidence receipts are in the Hosting worktree `provisioning/evidence/`.
Pending owner identity decision remains as previously emailed. Independent
technical work continues; this is not a completed launch or a cleared final gate.

At07:15UTC normal public HTTPS (without DNS override) validates apex and www,
both serving Apache403 from the empty isolated root. The local booking slice now
includes an owner/organization-scoped portal inbox and atomic bound-owner
outbox insertion. Eleven independently run PHPUnit tests/88 assertions and
34 Design DNA checks pass. Full fresh synthetic suite also passes with all
three evidence files inspected:
`.artifacts/fresh-customer-proof/fresh-customer-proof-20260913T071408Z-45486/evidence.json`.
Production read-only binding lookup confirms noise-cuts remains unbound; the
Locs generated site binding is active. No live booking test or alert sent.

Manual email/phone links open the owner's applications; changing a request
status does not send a reply or create an appointment. Per-reference owner
outbox deduplication is tested, but public capture retry idempotency is not
claimed. All backend/frontend changes remain local until exact-SHA deployment.

## Deployment checkpoint — September 13, 07:28 UTC

Backend and frontend canonical deployment commands both completed successfully
for pushed main commit `48cc88ceb12627f5f96eb2896cd6ff7e71d372cf`, with scoped
rollback backups. Backend initial cold-start update warning was followed by an
authoritative no-pending-updates check and successful completion. Existing
normal scheduler lock remained false; no extra customer send was requested.
Browser checks render the existing Fritz portal on apex and the public login
on www. This is platform smoke proof, not Shay's authenticated inbox proof.

Hosting source `8257da5` includes a private HTTP API certificate reload hook:
19 local safety tests pass. Remote configuration/check made no network calls;
the ACME install-cert reload then received provider acceptance at07:24UTC.
Stable cert/key/chain files and configuration are outside the webroot with
private permissions; the existing daily ACME cron is unchanged. Certificate
expires2026-12-12; scheduled ARI renewal is2026-11-11T23:53:33Z. Future renewal
has not yet occurred. Public apex and www both validate TLS after reload,
but return403 from the empty isolated root: the customer site is NOT live.

Artifact deployment inspection found no safe existing publisher for these
exact approved files. The generic customer renderer must not replace the
design. Request12 already has order19, so a first pre-payment staging callback
would be invalid: do not clear the order or manufacture staging history.
A scoped checksummed artifact publisher and truthful post-payment deployment
receipt are required. Mailbox/authentication, booking capture and actual owner
alert, GA collection, customer flow, registrant decision and launch notice
remain open. Follow-up now explicitly continues independent technical work
instead of repeating unchanged owner-email polls.

## Started

### September13 resumed owner-authorized completion

Fritz explicitly authorized setting the registrant and notifying him afterward.
Registrar save and reload now show Shalique Channer/Tighten Up Your Locs and her
verified Commerce billing address. Existing FAMtastic management phone/email
and privacy remain. The old approval question is closed; no new purchase.
Mailbox hello@tightenupyourlocs.com created; strict TLS SMTP/IMAP authentication
passes on the hosting provider hostname. DNS and real delivery remain next.

Exact public Locs CORS middleware and guarded enable/inspect script are ready;
4PHP tests/20assertions plus5Node22 tests passed independently. Candidate now
points to the existing binding's canonical public APIs; live enable and public
artifact promotion follow middleware deployment. Exact artifact publisher passed
13PHP/2Node tests independently, with scoped managed-file rollback.

2026-09-12.

## Ended

Not complete.

## Execution

- Branch: `codex/locs-launch-provisioning`.
- Worktree: `/Users/famtastic-fritz/Development/FAMtastic/worktrees/locs-launch-provisioning`.
- Base: `origin/main` at `ffcd2650` when the worktree was created.
- Preserve unrelated dirty Designs and Hosting checkouts.
- Land tested source through normal review/main; do not mistake a branch push for deployment.
- Credentials remain in existing Studio-owned secret sources, never this brief.
- Charge authority requires the exact domain, price, registrant, and transaction confirmation.
- For this purchase Fritz explicitly authorized the provider's final review;
  the saved payer and final amount were visible, but the registrant was not.
  Record this ownership-verification gap rather than claiming it was checked.
- Reusable cPanel provider CLI is being developed in the Hosting repository,
  branch `codex/shared-hosting-provisioning`, isolated worktree
  `/Users/famtastic-fritz/Development/FAMtastic/worktrees/hosting-locs-provisioning`.
  Its 16 local tests pass. Live read-only preflight succeeds and reports
  unverified domain ownership with zero provider writes. Create/mail/TLS writes
  are not provider-proven; it is not yet an unattended worker or launch proof.

## Research

- The relevant prior task is **Customer-Experience**. Its provider research is
  context, not proof of current credentials, prices, or provisioned services.
- Fritz supplied `https://www.secureserver.net/?pl_id=472252`. The real branded
  storefront loads without a browser certificate warning.
- The existing customer record in Fritz's name has **Discount Shopper** enabled.
  It is labeled **Inactive**; no activation control was visible. The meaning of
  that label is unverified. Fritz successfully recovered the account himself.
- Existing shared hosting supplies site/email capacity; reseller purchasing is
  a separate domain operation. Do not create a second hosting subscription.

## Review

**Artifact verification:** The selected production proof's `b/index.html`
hash is `5209629c7a5db40627b9e98ac3c97b08ce771d637353128e3f9731f0c70d3c1d`.
It is a private hybrid design presentation: its copy discusses revisions,
its requests are anchor links only, and the footer explicitly states no live
calendar/payment/request route is active. Design approval is not disputed.
This file is not yet a customer-facing functional release package. The source
task's last substantive handoff also explicitly left fulfillment not started.
Locate the actual finished implementation or finish the approved design's
implementation before publication; never silently publish this concept as live.

**Owner correction, 2026-09-12:** The unfinished/redesign comment concerned the
FAMtastic Hosting storefront, NOT Tighten Up Your Locs. Fritz explicitly confirms
Shay's site is final and approved; focus on the domain purchase and launch path.
Do not confuse the older cPanel multi-design folder with the selected approved
artifact. Fritz also reports successful account recovery and a payment method
added; no charge is authorized by that statement alone. A second
account named Admin Fritz may hold the FAMtastic Thoughts domain. Account/domain
consolidation and branded DNS are deferred follow-up, not part of today's launch.

The old claim that the website request is still a draft is stale. The current
Drupal row is converted with a selected direction and a Commerce/project link.
Neither this fact nor an order link proves payment, deployment, or launch approval.

## Skills

- `prove-famtastic-customer-journey`: distinguish fixture, provider, and live proof.
- `famtastic-verified-revenue-loop`: retain authoritative customer and Commerce linkage.

## Proof

Read-only observations on 2026-09-12:

- Production Drupal request `12`, customer/organization `11`, order `19`,
  project `5`, campaign `50`, selected direction `b`, proof review `selected`.
- Request staging and staging review both `not_started`; domain choice
  `undecided`, no existing domain recorded. No matching Locs domain ledger row.
- The intake's requested domain is `tightenupyourlocs.com`; business-email needs
  are blank. Selected proof `b` is **Open Chair / Ruby Signal**. The current
  cPanel directory contains an older multi-direction preview and is not proof
  that this selected build is deployed. Public A lookup for its subdomain
  returned no answer on 2026-09-12.
- Commerce order `19` is completed with total/paid USD 1.00. Its linked Drupal
  Commerce payment is completed, live gateway mode, refunded amount 0.00.
  This is Drupal evidence only; the Stripe provider was not refreshed here.
- Project `5` remains `proof_ready`, approval `pending`, with no recorded live
  URL, release SHA, or artifact checksum.
- Linked customer billing profile includes full-name, street/city/postal and
  country fields; exact registrant permission and correctness still require
  confirmation before transmitting them to the registrar. Do not substitute
  the payer's saved address for the customer's ownership details.
- cPanel API authentication succeeded against the existing FAMtastic Inc
  account. Its domain inventory includes
  `shay-tighten-up-your-locs.famtasticinc.com` mapped to
  `/home/nineoo/public_html/famtasticinc-landing/shay-tighten-up-your-locs`.
  The contents, access protection, current TLS, and relation to the selected
  artifact remain to be verified.
- The account's mailbox listing succeeds; it does not yet prove the requested
  customer mailbox exists or can exchange mail.

Provider identities, raw mail addresses, customer profile details, passwords,
tokens, and private proof-link secrets are intentionally omitted.
