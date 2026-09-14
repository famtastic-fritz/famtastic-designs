# Client messaging, Kesline proof rescue, admin filters and security maintenance

## Purpose
Restore a clear owner/customer communication loop and recover the overdue Kakes By Kesline website proofs.

## Goal

Deliver Kesline's complete working proof set to the verified customer, make contact messages readable and replyable from admin and the account portal, add useful admin filters, and address the reported Drupal security updates with release evidence.

## Tasks
- [x] Re-anchor current main in an isolated worktree and allocate parallel agent scopes.
- [x] Read design.md and deliver a generated messaging mockup before messaging implementation.
- [x] Diagnose missing contact messages and stalled proof records from source and live state.
- [x] Generate, independently review, attach and send Kesline's proof set; verify exact SMTP acceptance.
- [x] Reuse durable conversation records for contact intake, staff inbox, read state and client replies.
- [x] Implement portal inbox, unread/needs-reply states and order/project/proof context.
- [x] Add server-side search/status/date filters and repair desktop width/readability.
- [ ] Audit current Drupal dependency/security state and apply verified fixes through the canonical release.
- [x] Run focused security, lifecycle, responsive/browser and build checks.
- [ ] Commit, integrate and deploy reviewed changes; verify live admin and portal behavior.
- [ ] Update source truth, operational lessons and Drive status mirror with exact evidence.

## Status
active

## Started
2026-09-14

## Ended
Pending.

## Execution
Branch: codex/client-messaging-proof-rescue. Worktree: /Users/famtastic-fritz/Development/FAMtastic/worktrees/client-messaging-proof-rescue. Base: origin/main 598aa8ff. Parent owns integration, admin filters, security audit, documentation and independent proof review. Parallel agents own Kesline proof rescue, messaging backend, and portal UI respectively. Release to main through reviewed exact SHA and canonical deployment scripts.

## Research
The original contact is stored in intake.about but the owner alert links to a different website-request table. Existing portal conversation primitives are reusable. Live Kesline detailed request 14 has a job marked completed but campaign waiting_callback and no attached proofs. Exact-customer rescue is authorized by the owner; authentication and tenant controls remain required.

## Review
Mockup was generated with the built-in image tool after OpenArt GPT Image 2.5 Flare rejected for insufficient balance. The owner explicitly authorized a fallback. Sample conversation labels are illustrative and must never become production records. No model identity is inferred for the fallback image tool.

## Skills
imagegen; prove-famtastic-customer-journey; project design.md and operating/release contracts.

## Proof
Messaging mockup: docs/design/client-messaging-2026-09-14/mockup.png, explicitly approved by Fritz before implementation.

Kesline request14/campaign53 was sent once at 20:27:27Z through outbox633; exact receipt and limitations are in kesline-proof-delivery.md. Thin duplicate13 was reversibly archived. No global send gate or general outbox dispatch was changed.

Final backend module unit suite: 203 tests / 1078 assertions. Disposable Drupal HTTP smoke: 36/36 checks, including an existing-schema8062→8063 migration rehearsal, fresh staff email/password authentication without a customer row, CSRF, tenant isolation, one reply/outbox and actual rendering of all seven filtered metric screens. Evidence: .artifacts/client-messaging/inbox-20260914T204015Z-7461/. Migration generated zero notifications; HTTP fixtures left two queued memory-only notices and dispatched none.

Frontend build, Portal DNA34/34, loader/redirect8/8 and 11 responsive inbox browser checks passed at390/768/1280. These browser tests use mocked API responses. Staff with and without a customer workspace and fresh portal sign-in are covered. Fresh isolated customer lifecycle separately passed, including exactly three proofs, account ownership, commerce binding and all lifecycle assertions; evidence is .artifacts/fresh-customer-proof/fresh-customer-proof-20260914T203448Z-4518/evidence.json. Its source_sha is the base HEAD while reviewed changes were uncommitted, not a deployed source claim.

Locked security fixes are limited to drupal/ai1.4.6→1.4.8 and composer/composer2.10.2→2.10.3; Composer validation/audit pass with zero advisories. Publication and live checks remain pending at this implementation checkpoint.
