# Independent anti-pattern review — September 30, 2026

Scope: complete working diff plus new files against `f750a163`, implementation plan and handoffs. Review-only role; no application edits, configuration, provider calls, messaging or deployment. Read FAMtastic foundational definition v1.0.0 and repository startup guidance. Final re-review includes the integrated immutable campaign snapshot contract and the repairs described below.

## Findings closed after independent regression

1. **P2 closed — Raw persistence exceptions in reply form.** The initial form displayed every `RuntimeException::getMessage()`, including Drupal database exceptions that could reveal SQL, values or paths. The final form catches database/PDO exceptions first and displays a safe actionable message. The injected SQL-marker regression passed.
2. **P2 closed — Snapshot provenance could include mutable fallback files.** The initial locator prepended the release directory but fell through to mutable roots under a global snapshot label. The final locator treats a valid marker as authoritative for both file reads and campaign discovery. The isolated missing-snapshot regression confirmed that mutable-only files remain unavailable instead of receiving false snapshot provenance.
3. **Documentation reconciled.** The release helper document now leads with the final immutable snapshot contract and identifies the mutable promotion helper as unused by canonical deployment. Integration evidence records completed local packaging and its production proof boundary.

Findings sent promptly to parent for serialized integration. Closing review confirmed the database/PDO catch precedes domain RuntimeException, the marker makes snapshot roots authoritative, and final docs identify the immutable canonical lane. Independently reran the injected database-error form fixture against patched hkmZ6c runtime: PASS. An isolated copied-locator fixture proved mutable-only discovery without a marker and refusal of mutable fallback/discovery with a marker and missing snapshot: PASS. Source anti-pattern signoff is complete for these findings; production, provider and mobile acceptance remain separate gates. See PRODUCTION_READINESS_2026-09-30.md for live read-only endpoint evidence.

## Checks performed

- Parsed old and new Composer lock JSON: exactly seven package entries changed; no added/removed packages and no other top-level changes. `drupal/core`, `core-composer-scaffold`, `core-project-message`, `core-recommended` 11.4.5 → 11.4.8; `core-dev` 11.4.4 → 11.4.8; `project_browser` 2.1.4 → 2.1.5; `webform` 6.3.0 → 6.3.1. These are the intended security patch scope, not a platform migration. Advisory claims remain in the separate security triage.
- `php scripts/test-stage-campaign-release.php`: PASS, 29 local fixture checks. Exact-file allowlist, Git source binding, dirty/untracked/symlink refusal, hashes, drift checks and rollback helper tests. This does not prove canonical remote deploy execution.
- `bash -n scripts/deploy-backend-godaddy.sh`: PASS.
- `git diff --check f750a163`: PASS.
- `php -l` across all 25 changed/new PHP, install and theme files: PASS.
- Reviewed route permissions: new campaign manage/media, AI settings and owner summary routes require staff administration permission. Mutations are Form API submits; no new public AI endpoint or GET mutation found.
- Reviewed draft isolation and write paths: actor-authorized source lookup, per-user draft storage, revision compare-and-swap, source/recipient digest and idempotent reply key retained. Save/preview/AI paths do not enqueue; explicit reviewed send does. Transactional purpose recipes cannot invoke event-gated proof/account notices.
- Reviewed AI adapter: explicit task enrollment, chat default readiness, allowed provider with installed timeout adapter, input byte/record bounds, per-user flood/lock, source digests, prompt-injection instruction boundary, plain-text output, durable receipt and unknown-cost labeling. No model-labeled canned fallback, autonomous publication or business approval found.
- Reviewed generated output rendering: customer history is escaped; AI text uses plain text or escaped markup; branded preview uses autoescaped sandboxed srcdoc. No newly introduced unescaped customer/AI HTML path identified.
- Reviewed Postiz error handling: redirects disabled; HTTP status/JSON failures use sanitized diagnostics. Enabled integration is described as provider-listed status, not publishing proof. Live 404 repair remains unproven.
- Reviewed campaign reads/writes: shared screens project selected campaign and arbitrary planning rows; legacy access remains explicitly scoped. Drupal planning does not write CLI schedules. Canonical deploy stages immutable snapshots rather than overwriting mutable campaign files; marker-bound reads and discovery close the provenance finding.
- Reviewed mobile CSS and test assertions: changes use wrapping, bounded controls and scrollable tables rather than global overflow masking. Static layout test measures viewport overflow, target geometry, keyboard More and bottom-control clearance. Did not rerun browser tests in this role; real Drupal/mobile acceptance belongs to independent verification.

## Proof boundaries

This is source safety review with local packaging fixtures and targeted isolated runtime regressions. It does not establish live AI output quality/cost, Postiz connectivity, production persistence, customer delivery, publication, or authenticated mobile usability. No live effect occurred. A source-safe result is not a release authorization.
