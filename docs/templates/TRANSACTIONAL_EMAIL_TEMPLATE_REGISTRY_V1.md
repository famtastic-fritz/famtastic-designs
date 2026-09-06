# Transactional email template registry v1

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

| ID / version | Trigger and durable key | Recipient / purpose | Brand and CTA | Required truth boundary |
| --- | --- | --- | --- | --- |
| `customer_intake_submitted` v1 | First `draft → submitted`; `website-request:{id}:customer` | Verified customer; acknowledges that the Design Review and proof routine have started | FAMtastic Concierge, dark green/lime, “Intake received · verified workspace,” **Open your workspace** | No proof is claimed ready; no payment is requested; exact authenticated portal URL only. |
| `customer_proof_ready` v3 | Owner approves a complete 3- or 6-direction campaign; `website-request:{id}:proofs:{campaign}:{count}` or legacy project proof key | Verified customer; delivers access to the approved Studio Review and the research behind the concepts | FAMtastic Concierge, dark green/lime, “Private concept review · verified workspace,” **Open your proof set** | One job, one graphical CTA, no visible opaque portal URL. No promotion, price, or research-report claim outside the approved proof room; customer sees only owner-approved account-owned concepts. |
| `customer_revision_received` v1 | Customer submits permitted proof feedback; `website-request:{id}:customer-revision-ack:{notes-hash}` | Verified customer; confirms feedback is being used and keeps them in their workspace | FAMtastic Concierge, dark green/lime, “Feedback saved · next proof round,” **Open your project** | Never claim revised proofs are ready. It says FAMtastic is building the next set and leaves the prior URL out of visible body copy. |
| `customer_owner_system_review` v1 | Restricted demonstration notice only; external send receipt is retained with the review URL | Customer invited to a temporary, non-live demonstration of a branded client path and mobile Owner Desk | FAMtastic Concierge, dark forest/lime/warm paper, **Review your business system** | Never use for a proof set or selection. Proof delivery, research review, feedback, and choice stay in the authenticated workspace via `customer_proof_ready`. |
| `standard` v1 | Operational or transactional outbox row without a specialized customer-template assignment | Customer or operator, depending on the row | Neutral FAMtastic operational shell | Must not borrow customer-proof language or make a commercial claim. |

## Current customer copy contracts

### `customer_intake_submitted` v1

- Subject: `Your FAMtastic design review has started`
- Inputs: verified display name, request/project name, authenticated workspace
  URL.
- Customer promise: the intake was received; FAMtastic will review the business
  context and prepare the proof routine; a separate Studio Review notice comes
  only after owner approval.
- Forbidden: a delivery date guarantee, “proofs are ready,” payment request,
  price, research-summary claim, domain action, or public/bearer URL.

### `customer_proof_ready` v3

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
