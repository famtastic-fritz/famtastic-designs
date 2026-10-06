# Local integration evidence — September 30, 2026

Status: implementation and independent local browser verification complete, ready for reviewed commit/release handoff. No production deployment, real customer message, campaign publication, paid AI call or live model-default change occurred. Live Postiz is independently confirmed offline; see PRODUCTION_READINESS_2026-09-30.md.

## Final implementation

Shared registrations include campaign workspace/media/manage routes, communication drafts, task AI adapter, owner work summary and task settings. New installs and idempotent update8066 add plan/revision fields, active staff labels, working/history draft tables and AI receipts without rewriting historical records. All AI tasks start disabled. Staff permission remains `administer famtastic pipeline`; mutations use Form API.

Email history defaults to customer communication, exposes failed deliveries across categories, groups operational alerts and links to drafts. Inspect/back links retain campaign selection. Canonical deployment stages immutable JSON snapshots outside the document root, with a source marker promoted with module code. The marker makes its snapshot authoritative; missing files do not fall back to stale mutable data. Module rollback selects the previous snapshot. Mutable CLI schedules are never rewritten; media remains outside the JSON package.

## Runtime safety and dependency proof

Final patched disposable SQLite runtime: `/private/tmp/famtastic-selected-drupal.hkmZ6c`, loopback28986. Every installed package version matches its updated composer.lock. Drupal core11.4.8, Webform6.3.1 and Project Browser2.1.5 contain the scoped security updates; AI remains1.4.8. Locked audit evidence is in SECURITY_TRIAGE_2026-09-30.md.

The earlier full-flow runtime `/private/tmp/famtastic-selected-drupal.2sxxq4`, loopback28985, used the prior exact matching lock. No live settings, private files or credentials were copied into either runtime. Environments are allowlisted, mail uses memory/test collector, cron is disabled, Guzzle uses an empty mock handler and native outbound PHP transports are disabled. All accounts, recipients and records are synthetic.

## Passed checks

- Full patched unit suite:333 tests,1960 assertions (69 pre-existing PHPUnit deprecations).
- Email presentation:86 assertions.
- Actual Drupal integration:14 campaign/AI/rollback/authorization checks plus10 communication checks, three cached-form roundtrips and one snapshot lookup/discovery/rollback check. Generic database-error injection separately confirms SQL/body details stay out of the UI.
- Existing-site migration rehearsal: new columns removed only in the retired synthetic runtime, update8066 run twice, campaign/thread records retained and labels defaulted active.
- Campaign packaging/promotion primitives:29 fixture checks; canonical deployment uses only immutable staging. Independent missing-snapshot regression confirms no mutable fallback when a marker is present.
- Strict Composer validation, navigation/action inventory, theme and creator-credit contracts, changed PHP/YAML/shell syntax and diff whitespace checks pass.

AI success/failure exercises the real adapter with a provider double and durable receipts, including provider/model identity, unknown cost and no outbox change. It is not live-provider evidence. Communication checks cover persistence/revision conflicts/stale source, escaping, preview/save zero queue, reviewed synthetic queue exactly once, replay same message and reversible labels.

## Independent browser proof

Actual CUA390/1440 full-flow proof passed on the first fixture: campaign create/edit/duplicate/archive/restore; recipe/save/reload/preview; exact synthetic recipient reviewed send raises queue1→2; reload remains2. UI replay was blocked by automatic approval and is not counted as browser proof; service-fixture replay passed.

Final patched-runtime CUA390 smoke passed home, campaign creation, draft save/reload and branded preview; queue remained1. More and breadcrumb targets measure44px, home actions48px, and controls fit the390px viewport. See the independent quality report for full receipts.

Runtime testing exposed and fixed integer/string review-digest drift, pasted-HTML canonical draft mismatch, recipient/source race, ignored draft CAS, missing history-write rollback and cached multi-step Form API500 caused by private-readonly injected services. Protected injected properties now survive the actual form-cache serialization roundtrip.

## Explicit remaining boundaries

Production currently uses an offline ngrok endpoint: HTTP404 with ERR_NGROK_3200, authenticated channel service unreachable and local4007/4040 refused. The URL normalizer fixes full-base compatibility but does not repair this outage. Controlled service recovery requires inspecting queued work before any restart; no guessed replacement endpoint is configured.

Deployment, live AI configuration/provider execution, Postiz recovery and customer delivery are separate actions. The exact release procedure and rollback are in RELEASE_HANDOFF_2026-09-30.md; the owner workflow is in OWNER_GUIDE_2026-09-30.md.

Repository documentation is updated. Automatic approval rejected the Google Drive mirror because exact payload/destination authorization was absent; no workaround was attempted. The reviewable payload and proposed destination are recorded in the release handoff. That external mirror remains pending.
