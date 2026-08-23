# Public preview delivery v1

This is the only supported pre-registration proof path for a FAMtastic Designs public lead. It is intentionally separate from marketing campaigns and from the account-owned proof-share feature.

> **Compatibility evidence notice — 2026-08-23:** P.I.T. received a real,
> controlled FAMtastic legacy `a/b/c` static-package promotion and one
> transactional outbox send. That package was delivered by direct view-only
> proof URLs, not by this signed/revocable public-preview delivery record. It
> is useful production evidence for artifact import and email tracking, but it
> does not certify this v1 contract's durable delivery, claim, refined-six, or
> checkout behavior.

```text
Public lead + intake
→ preview delivery (inert)
→ website_proof.generate.v1, research, three real Safe/Medium FAMtastic/Ultra FAMtastic proofs
→ Build DNA validated and registered in Drupal
→ owner review and frozen invitation
→ explicit owner approval
→ revocable, 14-day concept room + one transactional email
→ verified same-email account claim
→ detailed portal website request
→ six account-owned refinements (1 Normal / 1 Medium / 4 Ultra), owner review, selection, scope, and checkout
```

## Guardrails

- A public room is never created from an image-free pilot, fixture, mock, or chat-only page. Its campaign must contain exactly `a/b/c` Safe, Medium FAMtastic, and Ultra FAMtastic proofs, and its immutable Build DNA projection must be registered before staging.
- A room includes only a business label, three working concept links, the early-direction explanation, and the account-creation handoff. It excludes email/contact values, intake answers, account IDs, prices, packages, grants, selection, revision, payment, and checkout.
- Signing up does not select a proof or create an offer. The email must be verified before the same-email account claims the delivery; the next portal request reuses the original Prospect rather than creating a duplicate.
- Staging freezes the subject and text snapshot. Only the proof-review role can approve and queue one transactional invitation. SMTP acceptance is recorded separately from delivery; no nurture or commercial follow-up is automatic.
- The share signature is server-derived and versioned. Revoke rotates the version immediately; public responses are private, no-store, no-referrer, and no-index.

## Exact continuation and detailed proof lineage

- The continuation token resolves one immutable `famtastic_preview_delivery`, not an email-wide or "latest prospect" lookup. It is bound to the customer before verification; a customer with more than one eligible delivery must name the opaque delivery ID for the new project.
- `famtastic_project_request.source_preview_delivery_id` is the immutable historical link. It is indexed and nullable because a customer can start an unrelated project without a public preview.
- A claimed public campaign is never copied into `famtastic_project_request.proof_campaign_id`. The field stays empty until a new account-owned refined campaign has completed its verified callback and entered owner review.
- Detailed intake freezes as direct canonical `website_discovery_v3` JSON and a separate consent-filtered asset manifest. Each raw string is SHA-256 recorded, then passed as the exact bytes to `proof.refined.generate`; later portal edits cannot change a queued run.
- The refined job carries `delivery_class=authenticated_refined`, `proof_phase=refined_six`, `requested_profile_id=portal_refined_six.v1`, the exact source delivery, and the original public campaign/Build DNA identifiers and checksum. It must create six new directions (`a`–`f`: one Normal, one Medium FAMtastic, four Ultra FAMtastic), never append to the public three.
- Parent and refined Build DNA evidence must be `classification=production_proof_completion` with `run.completion_state=provider_completed`. Preflight, fixture, local-only, or merely registered records cannot unlock an owner send gate.

## Operator sequence

1. Confirm the research ledger, consent boundaries, three real proofs, desktop/mobile Browser QA, independent visual review, and Build DNA record.
2. Register the exact Build DNA record in Drupal.
3. Open `/web/admin/famtastic/preview-delivery/{id}/review`, stage the named campaign, Build DNA ID, and manifest SHA-256, then inspect the frozen email.
4. Approve and queue only when the named recipient, room, proof set, and copy are ready. This action is the required external-send gate.
5. Monitor the Operations/outbox result, concept-room view, verified account claim, and detailed intake. Stop at any exception; no link visit or reply is a commercial decision.
