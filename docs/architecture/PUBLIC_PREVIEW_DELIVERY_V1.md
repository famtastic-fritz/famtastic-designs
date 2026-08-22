# Public preview delivery v1

This is the only supported pre-registration proof path for a FAMtastic Designs public lead. It is intentionally separate from marketing campaigns and from the account-owned proof-share feature.

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

## Operator sequence

1. Confirm the research ledger, consent boundaries, three real proofs, desktop/mobile Browser QA, independent visual review, and Build DNA record.
2. Register the exact Build DNA record in Drupal.
3. Open `/web/admin/famtastic/preview-delivery/{id}/review`, stage the named campaign, Build DNA ID, and manifest SHA-256, then inspect the frozen email.
4. Approve and queue only when the named recipient, room, proof set, and copy are ready. This action is the required external-send gate.
5. Monitor the Operations/outbox result, concept-room view, verified account claim, and detailed intake. Stop at any exception; no link visit or reply is a commercial decision.
