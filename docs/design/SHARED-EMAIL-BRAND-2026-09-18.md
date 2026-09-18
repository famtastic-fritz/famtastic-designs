# Shared email brand migration — September 18, 2026

Status: deployed and live-render verified.

## Request and diagnosis

Owner requested replacing all old email templating after an operational verified
registration email still used the older dark-green header. The staging template
had been released as an isolated adapter, not the default. Shared documentation
alone could not change the runtime route.

## Inventory and change

- `OutreachMailer::renderHtmlMessage`: standard fallback migrated (registration,
  verification/recovery, operational alerts and other standard outbox messages).
- `renderCustomerConciergeMessage`: intake, proof-ready, revision and reply adapters
  migrated, retaining their content, assurance and action extraction.
- `StagingReviewEmail`: strict staging URL validation and copy retained; approved
  presentation extracted into the shared `BrandedEmail` class.
- All six supported runtime template IDs use the same original-logo shell.
- Native Drupal `famtastic_pipeline_mail` is a plain-text compatibility hook with
  no custom HTML; protected-staging blackhole remains unchanged. No alternative
  SMTP renderer found in agency custom modules or backend/scripts.
- Historical showcase emails, mockups, evidence and sent history are reference
  artifacts, not active mailers; they are retained rather than rewritten.
- Customer-owned sites and third-party Drupal/contrib mail are separate systems;
  this release makes no claim about their email designs.

## Version and safety

New standard/intake/revision/reply versions are 2; proof-ready is 4. Older queued
versions render through the new shell under this explicit visual migration.
Staging remains 1. No recipient, queue, message, unsubscribe, credential, transport,
producer activation or sent-state changes. No test email or broad outbox dispatch.

## Verification

- 72 isolated presentation assertions: every old branch branded, exact destinations,
  escaped content, strict staging validation, reply CTA protection and version compatibility.
- 48 browser cases: six templates at 320/390/660/768px, with images loaded and blocked;
  no overflow, visible headline and 44px CTA targets. 320px blocked-image fallback
  exposed fixed image-width overflow; mobile logo/watermark dimensions corrected.
- Registration mobile preview visually inspected; original logo and approved frame present.
- 217 module unit tests / 1,129 assertions pass using installed dependency runtime
  and a prepended PSR-4 mapping to this worktree. Existing Drupal/PHPUnit deprecations remain.
  Initial run incorrectly resolved older checkout classes; rerun with correct mapping passed.
- Actual Gmail/Outlook/Apple Mail rendering is not certified by browser fixtures.

## Agent guidance

AGENTS.md (also CLAUDE.md via symlink), GEMINI.md, operating contract, design.md,
email specification and template registry point to the shared renderer and tests.
Old worktrees must refresh current-main instructions before email work. Runtime
centralization protects templates without relying on each agent remembering a design.

## Production release — completed

Owner authorization: “deploy please”, September 18, 2026.

- Normal fast-forward push to main and canonical backend preflight/apply both succeeded.
- Live backend: `45eedc1aff207d8062ee4cb879cec0208a5ddc4d`, deployed
  `2026-09-18T12:38:14Z` (8:38 AM Eastern), PHP 8.3.32.
- Canonical deployer recorded code/theme/config/dependency/database backups under
  `/home/xrdj7j99xhzt/backups/`, timestamp `20260918T123550Z`.
- Live Drupal pure rendering passed all six template IDs. BrandedEmail, OutreachMailer
  and StagingReviewEmail SHA-256 values exactly match committed source. Evidence:
  `docs/evidence/shared-email-brand/live-render-results.json`. No test mail sent,
  no outbox dispatch, no queue mutation performed by verification.
- Approved hosted logo hash matched; apex/www homepage and Drupal login returned 200.
- No pending database updates; cache, sitemap, entity and AI-foundation checks passed.
- Existing pilot lock remained 0 and lifecycle scheduler remained enabled.
- No frontend release required. Historical messages in existing inboxes are unchanged.
- Actual email-client rendering/inbox placement remains distinct from live pure-render proof.
- Follow-up evidence/docs commit does not change deployed runtime files.
