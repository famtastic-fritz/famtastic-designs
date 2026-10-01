# Backend and mobile command center repair plan

Date: 2026-09-30. Status: implementation plan; no release or provider execution claim.

## Owner outcome

Fritz can start on a phone, find work needing attention, create and manage any campaign, prepare a reusable branded customer message, and ask AI for a draft inside those tasks. Every task explains its next action in plain language. Saving, previewing and AI drafting do not send or publish anything.

A default AI model chooses which configured provider handles a supported task. It does not itself create campaign workflows, email templates or customer responses. The repair must connect actual task buttons to the supported Drupal chat API, show the configured provider/model, and explain unavailable/setup states. Business eligibility, prices, account access and approval remain deterministic.

## Baseline and specialist provenance

- Isolated worktree: `/private/tmp/famtastic-backend-mobile-command-center`.
- Branch: `codex/backend-mobile-command-center`.
- Fetched `origin/main`: `f750a163d418f8fbda4aa601165fe70c31f4545f`.
- Canonical checkout preserved on `feat/youtube-brand-channel-activation`, with unrelated changed schedules and untracked campaign/media files. It is 211 commits behind and 5 ahead of the fetched main; do not pull, reset or clean it.
- Audit: workspace `output/backend-usability-review-2026-09-30/REVIEW.md`, source inspection plus authenticated live walkthrough. Live model execution and customer delivery remain unproven.
- Bootstrap specialist: Agency Agents `engineering/engineering-git-workflow-master`, pinned upstream `765be42358100bf89d2faa567668a94c602f9a26`. Its Git craft is reference guidance; repository contracts and owner scope control release decisions.
- Required foundation read in full: FAMtastic Definition v1.0.0, root AGENTS.md, agent operating contract, Git sync discipline and backend deployment contract. Each implementer reads these and task-specific design, communication and marketing contracts before changing code.

## Phases and gates

### 0. Bootstrap and agree ownership

Fresh source worktree, this plan, audit-to-acceptance mapping. Parent verifies base and clean feature-code state. No commit yet.

### 1. Implement independent workstreams

Use three implementers with strict ownership below. Each reports exact files, local tests, assumptions, remaining blockers and registration/migration snippets. No shared registry writes by feature owners. Do not overwrite another owner's work.

**Campaign owner**

- Own `MarketingCommandController.php`, `CampaignAddForm.php`, new campaign edit/archive forms and campaign workspace/projection services, `PostizChannelsService.php`, `Utility/CampaignFileLocator.php`, campaign-specific tests and CSS only.
- Give original and subsequent campaigns one consistent selector across planning, creative, calendar, dispatch and results. Preserve stable IDs and original history; arbitrary campaign durations must work.
- Provide save-as-draft, edit, duplicate-as-draft, archive and restore; define key validation once. Capture goal, audience, offer/evidence, CTA, channels, dates and content plan. An active database row is never publishing approval.
- Drupal records own GUI planning data. File-backed schedules remain their existing writer's truth; render source and availability explicitly, avoid dual writes, and reject revision conflicts where a writer already exists.
- Replace legacy 17-day assumptions in shared screens. Investigate Postiz 404 against actual configured client paths/API documentation; do not merely label a failing connection successful. Missing deploy files and missing provider connection need distinct actionable messages. Preserve reviewed publication gates.
- Correct the locator's first-root early return so discovery searches all approved roots. Define deployment manifest allowlist and source provenance for campaign assets; any deployment script change must be serialized through integration and remains unapplied.
- Fix the literal Array render defect with a real render regression.

**AI and communication owner**

- Own `ClientMessageReplyForm.php`, `ClientMessagesController.php`, new draft/template/AI forms/services, `SupportDraftService.php`, `ClientMessagingService.php` if necessary, communication-specific tests and CSS. Reuse `BrandedEmail` and `OutreachMailer`; avoid changing shared renderer semantics unless necessary and explicitly reviewed.
- Add a template catalog of existing supported purposes, required-field validation, editable and persisted drafts, exact-recipient review, escaped branded HTML/plain-text preview, clear delivery history. Preview/save/generate cannot enqueue. Template ID/version and missing data remain visible.
- Manual purpose recipes use the governed `customer_message_reply/v2` shell. Proof-ready, account-grant and other event-gated transactional templates remain restricted to their original business transitions; template browsing must not become an event bypass.
- Keep transactional/customer replies and promotional eligibility distinct. Existing deduplication, suppression and recipient-account authorization remain authoritative. Do not create a second CRM or mail queue.
- Implement bounded model drafting using signatures verified from the locked Drupal AI source or official primary documentation. No invented methods and no silent paid fallback. Context is verified record data; inbound instructions are untrusted. Include source IDs, actual provider/model, result/error, elapsed time, draft version and cost only when known.
- Use the verified contrib default route only with explicit per-task enablement and a rate cap. Include authoritative source IDs/digests and immutable generation receipts. Implement/test the adapter without real model calls in this phase.
- Show explicit unavailable/timeout/insufficient-context states, actionable setup guidance and actual draft buttons. Preserve deterministic business recommendation rules.
- Segregate explicit test/duplicate records through filtering or reversible labels without deletion or guesses based solely on customer names. Separate worker alerts from customer communication views without hiding delivery failures.

**UI and mobile owner**

- Own `OperationsController.php` or a dedicated new command-center controller, `StaffCommandCenterBridge.php` if needed, custom admin theme files, `css/operations.css`, mobile-specific tests. Do not edit campaign/message controller logic or their CSS without handoff.
- Lead with Needs reply, Needs review, Failed delivery and Due next from real records. Include next action, reason, age and destination; no invented counts or stale hard-coded operational promises.
- Provide discoverable campaign/message/AI entry points and explain what AI setup gives in ordinary language. Technical evidence belongs in expandable detail; failures remain visible.
- Make navigation, forms, previews and sticky controls usable at 390px and desktop, with 44px touch targets, keyboard access and no obscured actions. Follow the existing Experience System and FAMtastic visual contracts.

### 2. Serialized integration

One fresh integration implementer exclusively owns all shared files: module `.routing.yml`, `.services.yml`, `.libraries.yml`, `.links.menu.yml`, `.permissions.yml`, `.install`, `.module`, config install/schema, shared service changes not assigned above and all coordination documentation. Feature owners provide proposed registration/schema fragments; integrator reconciles and applies them once. Integrator verifies update-hook numbering against refreshed origin before commit.

Wire the workstreams, add idempotent non-destructive schema updates, enforce permission checks/CSRF on mutations, reconcile navigation and run integrated acceptance. No dependency upgrades in this feature phase. Run a separate security triage against the exact lock and current official advisories; record affected package versions and upgrade implications without bulk updating production or mixing an unreviewed platform migration into this release.

### 3. Independent verification, anti-pattern and quality review

Use fresh agents for each responsibility as required by `claude-mem:do`. Verification covers meaningful behavior, anti-pattern review scans the actual diff, and quality review checks security/data boundaries and maintainability. Remediate failures before any commit.

Minimum acceptance:

1. Two campaigns with different durations/channels retain context across screens; edit/duplicate/archive/restore never alters the other campaign or historical provider receipts. Missing file and provider failure states render accurately. Original 17-day history remains accessible.
2. Each existing template family renders required information and escapes customer input; missing merge data is actionable. Draft survives reload. Preview and generation leave outbox counts unchanged. Unauthorized/cross-account access fails. Repeated submit cannot duplicate outbound work.
3. AI adapter success using a test double proves integration contracts, not live model use. Verify unavailable, timeout, malformed output, prompt injection, source isolation, edit/reject/retry and zero-send behavior. A real call requires an authorized test provider; otherwise record the remaining provider-proof gap explicitly.
4. Mobile flow: start command center → select/create campaign → save plan → open message → select template → save/edit/preview → request/reject draft → return to work list. Check 390×844 and desktop keyboard use. No horizontal overflow, covered controls or ambiguous fake successes.
5. Test/system records are reversible and discoverable outside default customer backlog. No deletion. Worker errors remain discoverable.
6. No raw Array rendering, fixed 17-day loop in shared campaign paths, model-labeled canned fallback, auto-send on preview, hard-coded connected status, insecure HTML interpolation, secret logging, unbounded customer context or uncontrolled schedule writes.

Run relevant existing checks: PHP syntax for changed files/module; Composer strict validation; pipeline PHPUnit unit suite when dependencies available; `php scripts/email-preview/test.php`; creator-credit PHP/admin contracts when touched; navigation/action inventory validation for new routes; Design DNA validation for any portal touch. Use isolated fixture runtime with blackhole mail and no production credentials for persistent workflow tests. Static markup/mobile fixture proof must not be reported as live Drupal proof.

### 4. Verified commit and branch handoff

After parent confirms the independent checks, a fresh commit agent stages only the scoped source/tests/docs and commits without attribution trailers. A branch/sync agent refreshes remote refs, inspects divergence, integrates deliberately if required, reruns impacted checks and pushes only the reviewed feature branch. No automatic main merge or production apply.

Update `docs/CHANGELOG.md`, evidence-sensitive `docs/CAPABILITY_REGISTRY.md`, `.site-context/SITE-LEARNINGS.md`, `docs/SITE_LEARNINGS.md`, and a dated synced Drive summary. Clearly distinguish local implementation, test-double/fixture proof, live provider proof and production proof. If a mirror cannot be written, report the exact pending surface.

## Release boundary

This task authorizes implementation and review. Do not send real customer emails, publish/schedule campaigns, spend on model calls, change live model defaults, or deploy production in these phases. Prepare a concrete reviewed release with exact SHA, migration/backups, smoke checklist and rollback before any required production authorization. Production applies only through the canonical backend release primitive from clean current GitHub main. Configuration-only setup is never presented as completion of the owner workflow.
