# September 18 client delivery — operational truth

## September19 03:32Z monitoring and reliability checkpoint

Authoritative read still shows16/17 customer_ready with no selection or staging.
Original/correction outboxes767/769/772/773 remain sent, one attempt each. No resend,
payment mutation, new campaign or selected build was initiated. Native payment
semantics are unchanged; the normal checkout field remains intentionally NULL.

Reliability merged latest main b2aadd5a at33df2775 and corrected an independently
found stale embedded-spec reuse defect. Actual local controller tests now prove
edited scope/content goes to planning with old acceptance invalidated. Full fresh
synthetic journey passed. See SELECTED_REQUEST_BINDING_2026-09-18 and runtime plan
for exact evidence and pending release/observe-only activation; static work still
does not implement either client's backend.

Owner-authorized priority: StockandShip98 request17/customer15/campaign56, then
Class of2000 request16/customer14/account campaign57. Do not duplicate campaigns.
Public inquiry campaign55 remains separate from account-bound reunion campaign57.

## Milestones are separate

Submitted request → research → three proofs → independent QA → client notice →
authenticated selection → selected staging/revisions → exact client acceptance →
confirmed settlement → final launch. A handoff job completing with zero variants
is not a completed proof build. SMTP acceptance is not readership. A manual payment
is not acceptance or launch authority. Routine green work needs no additional Fritz
gate; scope/spend/rights/security/merchant authority/repeated QA failure escalate.

## Verified receipts at this checkpoint

- Backend independent-QA gate deployed `988d9d6d` at23:46:20Z September18.
  Commercial protection release `de3aa707` deployed00:00:40Z September19 UTC
  (September18 local). No pending database updates; canonical backups retained.
- StockandShip native order21/FAM-2609-0021, manual payment5: $200 received,
  $0 outstanding. Owner confirmation is the evidence, not a bank/API receipt.
  Unknown bank reference and received date remain unknown. Held private offer
  binds the request/order without converting the normal checkout field.
- Reunion private $199 one-time scope is recorded, unpaid. Original customer
  draft is preserved; additional requirements explicitly staff-assisted.
- Reunion variants109/110/111 are imported. Exact callback retry deduplicates.
  Build DNA record41 retains reviewed source `ca144070`. Independent visual QA
  passed all nine gates. Actual deployed controller/asset boundary passed33 checks
  for owner, cross-account, anonymous and undeclared assets.
- Reunion outbox767, `website-request:16:proofs:57:qa-v1`, standard/v2,
  accepted once at1789776111. Redacted provider receipt is in its customer repo.
  Readership and an actual customer browser session are not claimed.
- StockandShip proof source `1b34fe7` is pushed in private `site-stockandship98`.
  C received dedicated texture/surface passes. Final independent QA passed all nine
  gates; scores A8.30/B8.55/C8.55. Build DNA record42 is registered. Canonical import
  created exactly three variants in campaign56. Deployed protected access passed42
  controller/asset checks. Personal standard/v2 outbox769 was accepted once by SMTP
  at1789777893 /00:31:33Z, signed Always FAMtastic, Shay. Provider acceptance is not
  inbox placement/readership. The private customer repo retains the redacted receipt.
- The post-release callback replay test discovered a real regression: duplicate
  import preserved the three artifacts but reset `customer_ready` to `owner_review`.
  Do not call that whole-lifecycle idempotency. The attachment writer now preserves
  later states for the same campaign and uses compare-and-set against concurrent
  QA/choice. Fix and trusted-intake preservation deployed as `5a9c6906` at00:28:57Z.
  Exact original QA evidence was reconciled at00:29:16Z with a separate repair event;
  callback replay then preserved the release and42 access checks passed again.
  No QA gate was waived and no client choice was forged.
- StockandShip rollback-only production selection rehearsal: correct account could
  select, other account denied, exactly one staging job queued, payment unchanged,
  acceptance separate. Fresh process00:30:40Z confirmed every test write rolled back.
  Both clients remain genuinely unselected; there are no real selected staging URLs
  at this checkpoint. Selection-to-queue is not completed WooCommerce execution.

The targeted integrated gate/payment/messaging suite passed34 tests177 assertions.
The fresh canonical lifecycle initially found stale template and staging fixtures;
the automation lane retains failures and its corrected full synthetic run. Do not
represent a synthetic fixture as live merchant, cloud or actual customer execution.

## Active bridge and remaining runtime boundaries

The existing Codex heartbeat `review-selected-site-automation-implementation` is
now named **Client delivery and selected staging**, ACTIVE every15minutes on this
thread. It checks exact customer selections, stays quiet/no-generation if unchanged,
and resumes only selected source with duplicate-job safeguards. It is Mac-hosted,
not laptop-independent. No owner approval is needed for routine selected work.

The five-minute server scheduler still needs the separately reviewed bounded CLI
repair. Never fix its PHP path by enabling a broad historical queue drain. New
coordinator/Cloud Run source is not active merely because tests pass. The live
Google Cloud CLI has no authenticated account and project is unset (rechecked
00:03Z). No retired cloud agent/VM was restarted. Laptop-independent execution
requires cloud authorization, a real compatible worker and an unattended test.

See `AUTOMATION_RUNTIME_2026-09-18.md` on the automation integration branch for
host, scheduler, lease, credentials, recovery and $25/month cost boundaries.
Static selected packaging is not WooCommerce or reunion backend implementation.

### Finite background work still pending activation

- Reliability integration: `codex/automation-reliability`, tested merge `6792b0db`
  and docs `21a95402`, reconciled with main `5a9c6906`. It preserves the callback
  replay repair and trusted staff/revision metadata. Source has101 focused PHP
  tests/604 assertions,28 Node tests, and a complete fresh synthetic lifecycle
  receipt at00:27:03Z. Independent main integration/production checks remain.
- Do not merge this as an urgent email prerequisite. It includes coordinated
  frontend/backend receipt-hash acceptance, selected source association, bounded
  worker tables/endpoints and observe-only scheduler repair. Review, test and
  release the matching surfaces together; keep static dispatch disabled until
  its source/target/rights/cost bindings and callbacks are verified.
- Private purchase follow-up: `codex/request17-commerce`, `dc3eae6d`. Same-order
  completion form for17 and a customer-initiated, scoped199 checkout for16. Source
  tests pass; real native checkout/CSRF/provider/browser acceptance is still needed.
  The reunion checkout flag defaultsOFF. Do not issue a second order/payment for17
  or test a real card charge. Never describe a mocked gateway as tested production.
- The Mac heartbeat should advance these finite approved repairs while clients
  choose, then perform only small selection checks. It must not regenerate proofs,
  resend accepted emails or run idle generation loops. No cloud deployment until
  the authorized Google account is connected and the complete cost/worker proof
  can be run. Do not restart retired VM agents to bypass that prerequisite.

## Customer source and completion boundaries

- StockandShip: `https://github.com/famtastic-fritz/site-stockandship98`.
  Preserve selected visual source, original media provenance and component IDs.
  Native proof cart/hash views are deliberately script-free demonstrations. Build
  real commerce with business-owned WordPress/WooCommerce, not a new cart engine.
- Reunion: `https://github.com/famtastic-fritz/site-mbsh-class-of-2000`.
  Reuse verified capabilities, never another class's private records or credentials.
- Both: actual owner login, durable edits, forms, mail, analytics, real inventory or
  event details and payment setup require their own staging tests and screenshot
  instructions. No real checkout until merchant/business requirements are verified.
- Private conversations are assigned through support-case ownership, because the
  thread table has no assignee field. Reply persistence/notification routing uses
  the existing ClientMessagingService, not a parallel inbox or published phone.

## Reusable material-layer contract

StockandShip C separates surface geometry (`drop-surfaces.mjs`) from texture
(`drop-texture.mjs`). Both are build-time C-scoped modules. Compose geometry before
texture to preserve grain, keep decorative planes noninteractive, retain stable
component IDs, and simplify materials on mobile/high-contrast modes.

This is a captured customer recipe pattern, not a global theme migration. Reuse
with explicit business-approved typography/palette/intensity and real provenance.
Do not apply a customer's cobalt/graphite identity to agency or other client sites.
Test text against the rendered lit surface, not just its nominal background color.
Selected lime controls require a dark focus ring; dark surfaces require a light
ring. Preserve44px targets, per-view skip focus, reduced motion, honest sample copy
and independent QA. Never waive accessibility to ship a more dramatic effect.
# September18 follow-up: request17 email navigation corrected

Fritz reported direct `/web` proof links and raw visible URLs in the original
personal notice. The authorized correction, outbox772, was SMTP-accepted once
at2026-09-19T01:02:52Z with one named portal button and existing proof-ready/v4
branding. Original769 is unchanged. No selection/payment/build state changed.
See `../design/PROOF-EMAIL-NAVIGATION-2026-09-18.md` for recurrence guards,
login-return browser evidence, deployment status and recipient-read limits.

Separate owner approval also sent request16 correction773 once at01:13:10Z;
original767 unchanged. Repair8eb12209 is now deployed to both backend and frontend.
Live markers and browser/controller evidence are recorded in the same receipt.
At01:23:11Z neither client had selected a direction; this navigation correction
does not assert a started staging build or activate the pending scheduler repairs.
