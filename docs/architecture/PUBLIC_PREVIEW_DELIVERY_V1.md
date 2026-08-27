# Public preview delivery v1

This is the only supported pre-registration proof path for a FAMtastic Designs public lead. It is intentionally separate from marketing campaigns and from the account-owned proof-share feature.

```text
Public lead + intake
→ preview delivery (inert)
→ website_proof.generate.v1, verified research, three real core directions
→ Build DNA validated and registered in Drupal
→ owner review and frozen invitation/research snapshot
→ explicit owner approval into a held outbox
→ bounded exact-ID dispatch (one to ten only)
→ revocable, 14-day concept room + one recorded SMTP acceptance
→ verified same-email account claim
→ detailed portal website request and optional research-report retrieval
→ a distinct, request-bound account proof campaign (current core-three path)
→ optional owner-gated showcase expansion, selection, scope, and checkout
```

## Guardrails

- A public room is never created from an image-free pilot, fixture, mock, or chat-only page. Its campaign must contain exactly `a/b/c` core directions, while its stored direction labels remain configurable per run. Its immutable Build DNA projection must be registered before staging. This is enforced both during public-job creation and at the final stage gate.
- The public-preview Build DNA run must name the exact `prospect_id`, Drupal `proof_campaign_id`, and public `campaign_id`; all three served proof HTML hashes must appear in its artifact manifest. A staging attempt fails closed if any of those correlations is missing.
- A room includes only a business label, three working concept links, a safe per-delivery context note, an optional bounded research teaser, and the account-creation handoff. It excludes email/contact values, intake answers, account IDs, prices, packages, grants, selection, revision, payment, and checkout. The anonymous provider brief is separately allowlisted and redacted; raw public request JSON and prospect phone/email/address do not cross into the Site Studio dispatch payload.
- Signing up does not select a proof or create an offer. The email must be verified before the same-email account claims the delivery; claim data is recorded without advancing or consuming the delivery state, so a lead that signs up early cannot fall through to generic outreach. Revoked and expired rooms remain unavailable but still preserve their original Prospect/proof history for that verified account.
- A research teaser requires a bounded source summary plus the SHA-256 and exact `research*` artifact role from registered Build DNA. The stored report, if supplied, is retrievable only by the verified same-email customer; do not promise a report that was not stored.
- Staging freezes the subject, text, context, research teaser, source summary, report, and exact direction/entity/path/SHA-256 snapshot. The signed proof controller rehashes that frozen artifact on every serve; a later mutable variant refresh cannot silently alter the room. Only the proof-review role can approve one held invitation. `famtastic:preview-delivery-dispatch` then requires an exact confirmed list of one to ten IDs; it never runs the general lifecycle/outbox dispatcher. SMTP acceptance is recorded separately from inbox delivery; a provider-accepted receipt-write problem enters reconciliation rather than being marked retryable.
- The share signature is server-derived and versioned. Revoke rotates the version immediately, cancels an unsent held invitation, and a replacement stage clears the old revocation marker. A room cannot be revoked while SMTP dispatch is in progress, preventing a real acceptance receipt from being misrecorded. Public responses are private, no-store, no-referrer, and no-index.
- This v1 dispatcher is a bounded owner-approved preview invitation lane, not the cold-campaign sender. A cold cohort needs a verified recipient/source and an equivalent campaign-message contract for postal/unsubscribe/provider-event records before any preview delivery can be used. If a list lacks a verified recipient or its website presence is unknown, it is not an eligible proof/email cohort.
- The current lead importer does **not** create public-preview deliveries or a personalized public intake/research snapshot. Therefore no XLSX/cold cohort—including `cold-260-aug-2026`—may be routed through this v1 lane yet. Build a separately approved verified-source campaign-seed contract before enabling that lane; do not substitute generic proof jobs or a direct preview dispatch.
- The legacy public-request acknowledgment still uses its existing direct SMTP path. It is not evidence that the full public request lifecycle has been migrated to this durable preview lane; that migration remains a separate release.
- Campaign ownership is explicit: a public job binds its delivery to a new campaign before remote dispatch; a detailed registered request similarly binds a distinct campaign before dispatch. Generic/cold/account jobs cannot attach to an unbound public delivery, and callback retries complete protection/owner-gating rather than enqueue generic outreach.
- This release does **not** implement the proposed automatic registered six-direction family (one Normal, one Medium, four Ultra). The current account lane produces an independent core-three campaign and can receive the existing owner-gated showcase expansion. Do not promise automatic six directions until a separately owned refinement contract is implemented and tested.

## Operator sequence

1. Confirm the research ledger, source/consent boundaries, three real proofs, desktop/mobile Browser QA, independent visual review, and Build DNA record.
2. Register the exact Build DNA record in Drupal.
3. Open `/web/admin/famtastic/preview-delivery/{id}/review`, stage the named campaign, Build DNA ID/hash, industry-neutral context, and (when used) research teaser/source/report plus its Build DNA research-artifact hash and exact role. Inspect the frozen email.
4. Approve and hold only when the named recipient, room, proof set, source boundaries, and copy are ready. Then run `drush famtastic:preview-delivery-dispatch --ids=<exact-list> --confirm=<same-list>` for no more than ten explicitly approved deliveries.
5. Monitor the delivery receipt, concept-room view, verified account claim, customer-only research route, and detailed intake. Stop at any exception; no link visit or reply is a commercial decision.

## Release verification boundary

Migration `8041` creates or upgrades the isolated delivery table, including its unique public-ID and delivery-key constraints. This clean worktree has no Drupal vendor tree or production MySQL database, so release still requires a staging/production-like MySQL `drush updb` dry run and schema inspection before deployment. No deploy, import, proof generation, email, or customer-data mutation occurred while preparing this candidate.
