# Selected request changes: executable evidence must still match

## Independent re-review: source association scope corrected

Exact baae38fc passed the full canonical lifecycle and81 installed checks, but
independent review found a missing boundary: source association rebuilt scope with
an older duplicate field list and rejected the newly included project type. A new
real installed issue/accept test reproduced `source_association_current_input_changed`
at `.artifacts/selected-staging-drupal/20260919T033752Z-64692/test.log` before the fix.
This failed evidence is retained; the earlier passes did not cover grant issuance.

`SelectedSourceIntent::requestedScope()` now owns field inclusion/order for both
producer and association. Strict scope/content/source checks remain intact.
The expanded real installed suite passes85 assertions, including persistent grant
issuance, exact-source association, duplicate acceptance, changed-type issuance
rejection and stale-grant rejection after a real customer update. Receipt:
`.artifacts/selected-staging-drupal/20260919T033847Z-64925/evidence.json`.
This remains a synthetic verified-source envelope, not a Studio provider build.
Final independent clearance and release are separate from these local results.

## Defect and correction

Independent review of reconciled integration `33df2775` found a release blocker:
the public request writer saved new scope/content, but an existing embedded
`selected_build_continuation` skipped all current-record resolution. A one-page
static specification could receive a new selection revision despite newly requested
pages or commerce. A revision number was not proof the specification covered it.

The portal now compares embedded evidence with a producer-recorded
`request_binding` (`famtastic.selected-request-binding.v1`). Its deterministic
digest covers request/customer/campaign identity, top-level project type, normalized
requested scope, authored page records and current request-asset authority. Raw
submission formatting, audit history and later project allocation are excluded.
The producer freezes the binding when it establishes covered source; the consumer
only recomputes for comparison. No production evidence is automatically backfilled.

Missing, malformed, unknown-version or changed bindings queue the existing planning
successor. Guard evaluation precedes the mapped/unchanged-intent shortcut. The rule
version also participates in intent identity so historical intents are reconciled.
Online-store requests cannot use the static path, even without ecommerce prose.
Normal signed-source resolution still derives recipes from saved records; this
change does not implement WooCommerce, a new planner, or a second build system.

Planning preserves selected bytes, request edits and historical receipts, clears
current review acceptance, supersedes pending old ready notifications, rejects old
dispatch/callbacks, and queues exactly one current job. Explicit revision notes
remain unfinished `continue_build` work, never completed static packaging; they
cannot bypass a mismatched baseline. The static worker may still reject unsupported
revision recipes. Implementing those recipes is separate from this safety repair.

## Verification before release

- Full module unit suite:305 tests/1563 assertions;68 pre-existing PHPUnit metadata
  deprecations. Includes deterministic request-binding, tenant/scope/content/rights
  changes and omission of raw transport/audit metadata.
- Installed Drupal/SQLite controllers:81 assertions, including added pages,
  same-page-count copy, ecommerce requirements, project-type-only edits, unchanged
  saves, before-selection edits, missing producer evidence, mapped-state shortcut,
  stale callbacks/acceptance and a fresh-process durability check. Receipt:
  `.artifacts/selected-staging-drupal/20260919T033038Z-59884/evidence.json`.
- Full fresh synthetic lifecycle passed:
  `.artifacts/fresh-customer-proof/fresh-customer-proof-20260919T033100Z-60064/evidence.json`.
  Memory mail, stub payment, fixture DNS and isolated deploy only. Installed bounded
  health/tick remained observe-only with unchanged queue/outbox/claim/budget counts.
- 27 Node worker/deployer/acceptance checks,86 email presentation assertions,
  34 portal DNA checks,42 serializer checks and3 intent seam cases passed.
- Actual React staging review was tested through CUA at320/390/768/1280px on the
  reconciled frontend: unchecked acceptance disabled, exactly one CSRF-bearing
  synthetic422 request, stale-receipt message and reset,44px button/no overflow.
  No actual client login, live acceptance, provider mail or checkout was performed.
  The browser harness now offers `--cua`; its loopback API is non-sending/synthetic.

The receipts above record base33df2775 plus then-uncommitted changes; source file
digests in the installed receipt identify the executed code. Final source/release
receipts are separate. Browser fixture evidence is not MySQL concurrency or real
client/provider proof. Independent review and canonical matching-surface release
remain required before calling this deployed. Dispatch/cloud remain disabled.

## Compatibility and recovery

Legacy embedded metadata without a baseline intentionally enters planning. Do not
forge a migration binding from current intake or rewrite approved proof hashes.
Reconcile actual covered work and register newly evidenced source through the
normal workflow. A selected site with added application features needs a real
implementation capability. Never drain old queues to demonstrate progress.

The new `project_type` scope field changes scope digests deliberately. Reconcile
old finalized-source authority against the current request instead of relabeling
its hash. Release rollback uses canonical backups; no customer or Commerce data
migration is part of this fix.
