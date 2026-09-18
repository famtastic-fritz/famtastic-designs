# Transactional email template registry v1

## September 18 — Shared branding for every active agency notification

Owner requested replacement of all old email layouts after a verified-registration
alert still used the legacy green shell. `BrandedEmail` now owns the one approved
HTML shell; standard notifications, intake, proof-ready, revision acknowledgments,
conversation replies and staging reviews all delegate to it. No new renderer may
copy a full HTML shell or use historical showcase/mockup HTML as a sending template.

Message subjects, plain-text AltBody, recipients, queue keys, transport, unsubscribe
headers and template-specific CTA extraction remain unchanged. Staging-only claims
stay in the staging adapter. New template versions: standard/intake/revision/reply
v2, proof-ready v4; staging remains v1 because its approved design is reused.
Previously queued versions remain accepted but receive the approved shared branding;
sent history is never modified or resent. This owner-authorized visual compatibility
migration is explicit, not a claim that historical HTML has changed.

Run `php scripts/email-preview/test.php` and the six-template responsive harness
before changing any renderer. CI runs the presentation contracts. See
`docs/design/SHARED-EMAIL-BRAND-2026-09-18.md` for inventory and release evidence.


Visual authority: [FAMtastic Design System](../design/FAMTASTIC-DESIGN-SYSTEM.md).
Existing PHP rendering and dynamic-message contracts remain intact. The September
17 visual-DNA pass audits this consumer; it does not duplicate or migrate templates.

## Historical September 17 approval update (superseded by September 18 migration)

`customer_staging_review_ready/v1` is owner-approved for the exact Valerie delivery.
The earlier candidate description below records the preview checkpoint. The canonical
logo URL is now the approved immutable `/brand/famtastic-designs-logo-v1.png`.
See `docs/design/EMAIL_BRAND_RELEASE_2026-09-17.md` for actual release/send receipts.
Automatic staging-ready producer wiring remains disabled; all other notifications unchanged.

Status: active source-of-truth registry for account-owned transactional notices

Purpose: keep the actual customer experience, the durable outbox record, and
the visual email renderer aligned. This document does not authorize a send,
customer-state transition, offer, charge, or launch.

## Rules

- Every newly queued notification records `template_id` and `template_version`
  with its immutable outbox key. The dispatcher uses that stored pair; it does
  not choose a visual treatment from editable subject/body copy.
- Legacy rows predating this registry are `legacy_unclassified` v0. The
  dispatcher has only narrow key-based fallbacks for old queued website-request
  receipts and proof-ready notices so an upgrade cannot change their intended
  customer treatment silently.
- Plain text is the durable, readable outbox receipt and email `AltBody`.
  Rendered HTML is a presentation layer; the local memory transport retains it
  for deterministic QA. Customer input is escaped before HTML rendering.
- Account-owned notices use authenticated workspace URLs, never bearer proof
  URLs. Lead/campaign templates live under the separate commercial campaign
  system and are not represented as Concierge customer messages.
- A template revision requires a new version, source/test update, registry
  entry, and owner-approved deployment. It never retroactively changes a sent
  message.

## Active templates

### Historical preview checkpoint: `customer_staging_review_ready` v1

Renderer registered; no producer wired, send or deployment. Uses the supplied
September 17 logo and [email brand system](../design/email-brand-system.md).
Valerie-only local fixture reviews the selected staging build with no payment due.
Owner-approved public, noindex staging is a narrow exception to authenticated
workspace CTA rules: this message links a client site, not private account records.
Rendering requires an explicit terminal staging destination and configured hosted
logo; no default hosted asset is assumed. Promotion to active sending requires
separate visual acceptance, email-client tests, asset hosting, recipient/project
verification and authorized deployment/send. All current active templates below
retain their existing rendering and behavior.

### Active templates after September 18 migration

| ID / version | Trigger and durable key | Recipient / purpose | Brand and CTA | Required truth boundary |
| --- | --- | --- | --- | --- |
| `customer_intake_submitted` v2 | First `draft → submitted`; `website-request:{id}:customer` | Verified customer; acknowledges that the Design Review and proof routine have started | Approved original-logo shell, FAMtastic Concierge, “Intake received · verified workspace,” **Open your workspace** | No proof is claimed ready; no payment is requested; exact authenticated portal URL only. |
| `customer_proof_ready` v4 | Owner approves a complete 3- or 6-direction campaign; `website-request:{id}:proofs:{campaign}:{count}` or legacy project proof key | Verified customer; delivers access to the approved Studio Review and the research behind the concepts | Approved original-logo shell, FAMtastic Concierge, “Private concept review · verified workspace,” **Open your proof set** | One job, one graphical CTA, no visible opaque portal URL. No promotion, price, or research-report claim outside the approved proof room; customer sees only owner-approved account-owned concepts. |
| `customer_revision_received` v2 | Customer submits permitted proof feedback; `website-request:{id}:customer-revision-ack:{notes-hash}` | Verified customer; confirms feedback is being used and keeps them in their workspace | Approved original-logo shell, FAMtastic Concierge, “Feedback saved · next proof round,” **Open your project** | Never claim revised proofs are ready. It says FAMtastic is building the next set and leaves the prior URL out of visible body copy. |
| `customer_message_reply` v2 | Staff saves a reply; `client-message:{message_id}:customer` | Original contact address or account-owned conversation recipient; conveys the saved reply | Approved original-logo shell, FAMtastic Concierge, **Open your conversation** | Saved portal content and queued email are separate states. Only a provider receipt establishes SMTP acceptance; no inbox, read, proof-ready or launch claim is inferred. CTA returns to the authenticated conversation. |
| `customer_owner_system_review` v1 | Restricted demonstration notice only; external send receipt is retained with the review URL | Customer invited to a temporary, non-live demonstration of a branded client path and mobile Owner Desk | FAMtastic Concierge, dark forest/lime/warm paper, **Review your business system** | Never use for a proof set or selection. Proof delivery, research review, feedback, and choice stay in the authenticated workspace via `customer_proof_ready`. |
| `standard` v2 | Operational or transactional outbox row without a specialized customer-template assignment | Customer or operator, depending on the row | Approved original-logo shell; neutral notification copy | Must not borrow customer-proof language or make a commercial claim. |

## Current customer copy contracts

### `customer_message_reply` v2

- Subject: `FAMtastic Concierge — [conversation subject]`.
- Inputs: escaped conversation subject, exact saved staff reply, authenticated
  portal conversation URL. The recipient comes from the existing conversation,
  never a browser-supplied email address.
- Customer promise: a reply is available in the conversation. Contact submissions
  can be linked only after the recipient verifies ownership of the matching email.
- Retry idempotency is scoped to conversation, staff account and request ID. A
  retry cannot create a second saved reply or notification.
- Email acceptance is reported separately from portal persistence and unread state.

### `customer_intake_submitted` v2

- Subject: `Your FAMtastic design review has started`
- Inputs: verified display name, request/project name, authenticated workspace
  URL.
- Customer promise: the intake was received; FAMtastic will review the business
  context and prepare the proof routine; a separate Studio Review notice comes
  only after owner approval.
- Forbidden: a delivery date guarantee, “proofs are ready,” payment request,
  price, research-summary claim, domain action, or public/bearer URL.

### `customer_proof_ready` v4

- Subject: `Your FAMtastic Studio Review is ready`
- Inputs: verified display name, configured concept-set label, authenticated
  request workspace URL.
- Customer promise: the concepts are ready to compare in the verified account;
  the customer may review the attached research brief, select a direction,
  request the one included design reset before choosing, or use up to three
  included edit rounds after choosing.
- Forbidden: external proof sharing, automatic launch, price/payment ask,
  marketing copy, or a Business Opportunity Snapshot claim until that
  request-owned artifact exists and has been owner-approved.

### `customer_owner_system_review` v1

- Subject: `Your [business] business system is ready to review`
- Inputs: customer name, business name, temporary review URL, owner-desk URL.
- Customer promise: a branded client path and phone-first Owner Desk are ready
  for review; the recipient can give launch-direction feedback.
- Never use this notice as an alternative proof room or selection surface.
- Required: name the review as temporary and non-live; explain that requests,
  calendar, payment, domain registration, and published availability remain off.
- Forbidden: “your website is live,” authenticated-workspace claims, a payment
  ask, a proof-delivery claim under `customer_proof_ready`, or a claim that the
  temporary link persists.

## Related but separate template systems

- Owner-invited deep-dive follow-up: `OWNER_INVITED_DEEP_DIVE_FOLLOW_UP_TEMPLATE_V1.md`.
  It stores `{{interview_url}}` as a merge token and renders the bearer link
  only at an exact-recipient send.
- Commercial lead/campaign email: `docs/EMAIL_AUTOMATION.md` and
  `CampaignMessageService`. It has its own approval, postal-address,
  suppression, and unsubscribe obligations; it must not be used for this
  customer lifecycle.
