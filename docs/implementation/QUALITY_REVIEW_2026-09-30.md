# Independent quality review — September 30, 2026

Reviewed working implementation independently, without feature edits or production access. Read root AGENTS, foundational definition, dated implementation plan and source/live baseline audit.

## Fixed and source re-reviewed

- P1: MySQL string revisions produced a different review digest from integer save results. `reviewDigest` now canonicalizes revision as integer. Verified Drupal MySQL driver sets PDO stringify fetches.
- P1: Recipient/source could change between review and enqueue. Draft status now uses a conditional atomic claim; reply serializes thread writers, compares current source and uses the exact reviewed recipient.
- P2: Preview saved canonical text but retained raw textarea input. The form now restores canonical saved text before send comparison.

## Assigned to integrator for repair and verification

- P2: `CommunicationDraftService::save` needs exception rollback around working draft and immutable history writes; force history insertion failure and assert both remain unchanged.
- P2: `MarketingCommandController::tabEmail` still mixes customer messages and worker alerts and retains a CLI instruction. Add explicit customer/operational selection, failure visibility and a communication-desk entry.
- P2: Build DNA/email inspect and back links drop selected campaign context. Retain selection through detail navigation and test campaign B does not revert to A.

## Scope and evidence limits

Follow-up: integrator repaired the three assigned issues. Actual CUA verified customer/operational email separation and selected-campaign inspect/back retention. Integrator reports forced history-insert rollback check passing; see integration report for that test. Actual browser review also found cached-form service serialization and mobile target/box-sizing failures, both repaired and retested. Detailed evidence and remaining browser replay approval block are in `MOBILE_FLOW_VERIFICATION_2026-09-30.md`.

Initial source review covered campaign CRUD, revision/archive behavior, media containment, route permissions, Form API mutations, AI source projections and installed provider signatures/timeouts, reply replay, and mobile navigation source. It made no feature changes and performed no production actions. Subsequent bounded local browser verification is documented separately; that follow-up queued one synthetic memory-only reply. Source review alone is not rendered runtime or physical-phone proof.
