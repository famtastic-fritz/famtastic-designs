# Selected staging continuation — isolated agency implementation

## Bounded uploaded-reference increment

Normal upload rights now bind exact same-request/customer proof asset bytes for protected review only. Withdrawal refreshes pending work and acceptance; export/receipt/reader/checkout/public-share gates retain the restriction. Next preserves source policy and denies public consumers. Synthetic PHP/Chromium proof covers this path; no live or Drive writes. Next: authenticated fresh Studio source association to an existing canonical agency request, without seeded source authority or a second repository.

Status: local source and synthetic adapter proof only. No production activation,
customer job, mail transport, upload, charge, service reload or release occurred.
Base: `411252cc98ee6fd6147682615c9d218542e1a275`.
Branch: `codex/selected-staging-contract`.
Worktree: `/Users/famtastic-fritz/Development/worktrees/fd-selected-staging-contract`.

## Contract and behavior

The real portal producer now uses `SelectedStagingContinuation::createPacket`.
It retains the additive `famtastic.site-studio.build-packet.v1` envelope and adds
`continuation.schema_version=1`. Authoritative customer ID/contact, request ID,
project ID, campaign/variant IDs, selected source hash and monotonically increasing
selection revision come from the existing records. It never infers final acceptance.

Executable source metadata must already exist at
`proof_variant.design_dna.selected_build_continuation`. Required evidence includes
explicit static scope (`spec.site_needs`), every required page, path-to-source
bindings, approved rights references, selected design contract, research reference,
operation, initiating system, correlation ID, requested next action, and the exact
protected `famtasticinc.com` hosting target. No default design, scope, rights,
research result or hosting authorization is manufactured. Missing metadata records a durable
`selected_continuation_evidence_required` operational exception while preserving
the selected intent, existing project and notes. Older callback/dispatch cannot
re-enable review while the exception remains unresolved. Unsupported application
scope is `unsupported_scope`, distinct from a transport failure.

`operation` is `package_existing` or `continue_build`; `requested_next_action` is
`protected_review`; `initiating_system` is `designs` or `studio`. Operation does not
implicitly rerun research, generation, copywriting, imagery or redesign. Optional
`completed_stages` retains source evidence; it cannot substitute for new QA/HTTPS
verification. An explicit rebuild is not implemented by silently packaging a proof.

Additional complete static source may be registered in
`design_dna.selected_build_artifacts` as `{path, sha256, bytes}` records. The producer
checks their actual bytes, restricts them to the selected protected artifact directory
(including resolved symlinks), and adds them to the signed immutable manifest. This
supports authored multipage static source without representing absent application
features as complete. Every public file still needs a rights and source binding.

The registry serializes the account request row, accepts only the next revision for
the same account/request/project, preserves earlier packets and receipt/acceptance
history on the existing project, and invalidates current acceptance before queuing
the newer revision. Same payload retry is idempotent; same revision changed payload,
skipped/reversed revision, and changed tenant binding are rejected. Legacy registered
packets cannot be silently upgraded: `legacy_packet_migration_required` records an
operational exception requiring explicit evidence reconciliation.
`php scripts/reconcile-selected-staging-packet.php legacy.json authoritative-context.json design-dna.json evidence-ref`
creates a local unregistered proposal. The dependency-free helper preserves exact
legacy selected artifacts, manifest and project identity, binds newly recorded
account/selection evidence and includes a SHA-256 of the full previous packet.
The real registry validates this linkage and archives the legacy packet (existing
`famtastic:site-studio-packet-register` takes the proposal's inner `packet` object
after separately authorized record reconciliation); missing
facts remain a precise gap. This command never registers or dispatches anything.

Post-selection customer edit requests queue `continue_build` with the exact notes
and `remaining_stages=['apply_customer_revision']`, retaining the selected direction.
They do not restart the three-concept proof routine or charge an exhausted edit
counter. Missing revision execution capability must return an actionable exception;
packaging unchanged source does not satisfy requested edits. Source registration
and job enqueue are inside the existing transaction and roll back on unexpected
failure. Known evidence gaps preserve selection and create the operational exception.
A declared `recipe.id=selected-html-slots-v1` may cover a required missing page
using hash-bound authored template/slots; it is retained without invented content.
General freeform revisions remain unsupported until an appropriate executor exists.

`StagingReceiptService::accept` handles success and the distinct
`famtastic.site-studio.staging-failure.v1` schema through the existing signed callback
controller. Both bind current packet, account, request, project, selection revision
and source manifest. Success additionally binds exact target URL/path/subdirectory
and requires passed QA. A different receipt for the same revision stays forbidden;
new revision registration must invalidate it first. Queued/retry ready notifications
for the exact request are superseded when new selected work or a metadata exception
invalidates review; delivered history is preserved. Failure is durable and never
queues staging-ready mail or enables checkout. Existing success outbox architecture
is preserved. Studio cannot set acceptance, checkout eligibility or final launch.

The authenticated staging acceptance endpoint now requires JSON `receipt_hash`
matching the displayed `staging_preview.receipt_hash`, including a conditional DB
update to reject a concurrent artifact change. Existing callers omitting it fail
closed. The project frontend now shows the completed protected review with an explicit
acceptance checkbox and exact-hash submit control. The existing CSRF API client
sends only the displayed hash; a stale reply refreshes the workspace and clears
the checkbox without automatically accepting the new artifact. Waiting/exception
copy and ordinary selected-site revision controls no longer imply payment starts
the build or impose the old post-selection edit-round count.

## Initiation matrix

| Entry | Current behavior | Continuation boundary |
| --- | --- | --- |
| Agency `send-to-site-studio` website request endpoint | Queues existing three-concept proof routine | Not selected-site build; ownership stays Designs |
| Agency account proof selection | Serializes/registers selected packet and queues existing staging job | Packages already authored complete scope when metadata exists |
| Agency selected-site edit request | Queues a newer same-direction continuation with notes | Applies unfinished revision stages only; unsupported executor is an exception |
| Studio generic callback endpoint in Designs | Validates signed facts against existing records | Does not create customer/request or dispatch another job |
| Studio-origin selected transfer | Same envelope can retain `initiating_system=studio` and correlation | Requires an already owned agency request/project and evidence; no speculative reverse request-creation API |
| Explicit rebuild | No new generalized engine here | Must not be inferred from receipt/import |

## Reproducible local evidence

- `php scripts/test-selected-staging-contract.php`: 40 assertions. Uses the actual
  producer serializer, `registerPacket`, and `StagingReceiptService::accept`, with
  synthetic account/request/project/entity/database adapters and no external I/O.
- `php scripts/test-selected-staging-contract.php --packet`: emits the actual wire
  serializer's `{packet}` body for the companion Next integration test.
- `php scripts/test-selected-staging-contract.php --html`: exact synthetic bytes.
- `php scripts/test-selected-staging-contract.php --validate-receipt`: accepts a
  callback JSON body on stdin through the real receipt service with synthetic stores;
  emits acceptance, notification count and checkout facts.
- `node scripts/validate-client-portal-design-dna.mjs`: 34 passed, 0 failed after
  expanding only required sparse frontend source paths. Frontend API and review controls are also compiled and separately browser-tested below.
- `node --test scripts/test-staging-review-flow.mjs`: 3 passing API/current-hash/refresh tests.
- `node scripts/test-staging-review-browser.mjs`: actual React component and API,
  one stale-hash request, checkbox reset, no automatic resubmit, containment and
  minimum 44px button at 320/390/768/1280px. Synthetic loopback test only.
- `npm --prefix frontend run build`: compiled successfully with existing borrowed
  dependency tree, no install. Node v24.19.0 was available; canonical Node22 was
  not available. Sparse public assets produced unresolved runtime-asset warnings
  and the existing large-chunk warning; this output is not a deployable release.
- PHP lint, brand sync check and `git diff --check` pass.

The synthetic adapters do not prove Drupal transactions/row locks, entity cache
behavior, live HTTP HMAC controller routing, production portal/session behavior, Commerce
checkout races, or real outbox scheduling. The source checkout has no matching
Drupal vendor runtime. The canonical customer-journey runner was not run or claimed
passed. Do not install a large runtime on the low-disk host to hide this dependency.
Next's companion test must record its own results for acceptance → worker → selected
artifact build → mock protected hosting → signed callback → this receipt boundary.

## Narrow activation checklist (requires separate authority)

1. Review both isolated branches and exact SHAs; finish missing metadata production
   path and certify portal current-receipt controls in the matching runtime. Verify that each supported customer scope
   has all pages/features and rights evidence; isolate unsupported scope exceptions.
2. In a matching disposable Drupal runtime, execute actual DB transaction, concurrent
   selection/revision/checkout, stale callback, rollback, CSRF/current receipt, and
   outbox dedupe tests. Test the HMAC callback route with synthetic secrets only.
3. Inventory old packets/jobs read-only. Do not drain broad queues. Reconcile each
   authorized legacy selection to current evidence without inventing a revision or
   acceptance. Never silently migrate a paid request.
4. Prove Next's immutable account/project hosting binding, anonymous denial, noindex,
   complete manifest/HTTPS verification, backup restore and callback retries using
   the isolated protected environment. Confirm unsupported edits cannot publish.
5. Only after explicit release authorization, use canonical reviewed deployment
   mechanisms; configure exact dispatch/callback endpoints and secret references
   without printing values. Enable only the selected staging consumer. No broad cron
   restoration, DNS change, final launch or backlog customer messaging is implied.
6. Observe one separately authorized synthetic selection and revision lifecycle before
   live customer eligibility; record source SHA, DB result, hosted receipt, callback,
   mail capture/outbox state and exact client acceptance separately.

## Rollback

Disable only the newly authorized selected consumer/dispatch capability; preserve
all durable job packets, receipts and callback retry state. Restore reviewed backend
code through the canonical deployment tool and restore the affected request/project
state from the scoped pre-activation backup only when separately approved. No schema
migration is introduced. New additive packets/history remain readable as JSON but
older code cannot safely execute newer revisions; pause them rather than replaying
against the old active-packet rules. Roll back each review target through its verified
backup/manifest transport. Never fabricate an old acceptance or delete customer
source, broad queues, existing outbox records, or unrelated authored files.

Drive mirror was not written: this task is restricted to isolated local implementation
and documentation; synchronizing an external mirror was outside its authorized scope.

The local browser harness can use `STAGING_REVIEW_TEST_DEPENDENCIES` to point at an
existing compatible frontend dependency directory; no install is required. The
implementation borrowed `/Users/famtastic-fritz/Development/worktrees/fd-client-selected-build-flow/frontend/node_modules`
via an ignored symlink for verification only. No canonical Node22 or complete
public-asset production build was proved in this low-disk worktree.
## Shared-shell contract follow-on

The consumer recipe allowlist now includes `legacy-shared-shell-v1`. Next local proof covers hash-bound authored content/permission records, source-offset text assembly, preserved shared shell, explicit metadata, same-repository continuation and assembled-page QA. The bounded regression sweep passed 69 tests in 9 files with actual PHP harnesses; the agency contract harness passed 42 assertions. Normal content/permission/template producer writers remain implementation work. No production activation, legacy writes, credentials, mail or provider calls. Drive mirror omitted under explicit two-worktree-only scope.
## Parent review corrections and connection plan

Source registry now verifies domain-separated finalized-source-wire v2 bytes and stores the envelope unchanged. Planning intent and scope digests bind exact producer JSON bytes. Normal-writer plan is in the paired Next worktree at `docs/plans/NORMAL-SELECTED-WRITERS-2026-09-17.md`: capture immutable source/template at existing proof finalization, bind normal request scope and authored-content/permission updates, derive per-file rights, add unchanged-byte worker retrieval, and persist project source/review-target mappings. Missing infrastructure records are not requests for duplicate customer input. Missing actual authored copy or ambiguous business requirements remain genuine input gaps. These writers are still unfinished; no automatic-build completion or runtime activation is claimed.
## 2026-09-17 - Reference aliases and inactive reupload correction

Independent review found two bounded edge cases. Hash-addressed reads now validate every same-packet declared alias and return exact bytes without dropping either output path; tenant/current-rights/containment checks remain required. Reuploading withdrawn bytes returns an explicit inactive-reference conflict, preserving withdrawal evidence. The dashboard clears stale success notices before actions; its existing API error path displays the conflict. Actual controller and two-path worker completion plus browser API error propagation are covered locally. No regrant, live action or Drive write.
## 2026-09-17 - First canonical-request Studio association (local)

The actual current unpaid request/selection writer issues an immutable one-hour signed source association. A normal Studio run can bind its verified Git/browser/file evidence without seeded mapping or rebuilding completed pages. Exact completed-copy evidence is checked against current authored fields. Durable callback retry survives restart; the first signed callback registers source and refreshes the normal request without Select. Complete pages package unchanged; only missing pages build in the same repository. Paid/reselected/expired/cross-tenant/conflicting-source/changed-copy cases fail. The grant never establishes hosting, acceptance or checkout. Initial association is static and assetless; arbitrary Studio-first agency customer/request creation and live transport remain unproven. Full regression is recorded separately; no production or Drive writes.

Association freshness follow-up: grant issue/acceptance re-read actual canonical intake scope/authored pages and contained selected file hashes/sizes, not only the saved intent. Direct row or source drift is rejected before mapping. Focused normal association/controller/route proof passes 3/3 in7.02s after this correction; the preceding full regression remains 1,014/99. Local only; no Drive write.

## 2026-09-17 - Unattended source callback and partial ancestry correction

Independent review found that durable association callbacks needed another manual call and that later ancestry rejected valid associated partial sources. The existing worker wake now retries the exact outbox envelope with explicit matched acknowledgement, shared project claims and bounded attempts. Expired/paid/reselected/changed-source/incorrect acknowledgements enter actionable non-ready reconciliation; unrelated work proceeds. Valid partial ancestry requires the original association and scope digest, exact authenticated wire, bound source/browser QA, only required_pages_incomplete, and actual Git ancestry/unchanged bytes. Callback-delay and upload-failure sequences add only Team after local About completion. Focused lifecycle proof10/10 passes21.57s; full regression recorded separately. No daemon, live activation, mail or Drive writes.

## 2026-09-17 - Keep build success separate from handoff retries

Independent POST-route review found that combining build and source association could rebuild on a failed callback replay. The combined run option is now explicitly rejected before work; supported orchestration creates once, then associates the stored site/run through its separate endpoint. Actual POST replay/concurrency/conflicting-input checks preserve run count and Git HEAD. Transient callback failures (including408/429/503) now retain persisted due times with5-second exponential backoff capped300 seconds, continuing within grant validity instead of stopping after three attempts. Permanent/stale authority still requires reconciliation. Focused11-case proof includes more than three transient failures, restart, automatic recovery and unrelated work; full regression recorded separately. No live or Drive changes.
