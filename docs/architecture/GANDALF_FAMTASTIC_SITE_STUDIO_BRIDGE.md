# Gandalf notes — FAMtastic ↔ Site Studio packet bridge

These notes are the resumable boundary map for any agent working in either repository.

## The bridge in one line

`FAMtastic owned project → signed build packet → Site Studio existing engine → signed success packet → FAMtastic owned project + activity + notification`

## FAMtastic repository responsibilities

1. Load the customer-owned request and project.
2. Run or retrieve the validated research-first preview package.
3. Preserve all six directions and the quality/evidence package.
4. Accept one or two customer-selected direction IDs.
5. Resolve provider capabilities and fail closed when unavailable.
6. Produce `famtastic.site-studio.build-packet.v1` and its archive, with the
   same immutable `famtastic.build-dna.v1` record copied to
   `packet-files/build-dna.json` and referenced by its checksum.
7. Register the exact packet on the matching Drupal project before dispatch.
8. Send or paste the immutable packet into Site Studio's approved intake surface.
9. Validate the signed `site-studio.build-success.v1` return.
10. Update only the correlated project, append activity, and queue one transactional notification.

Canonical implementation:

- `website-delivery-swarm/autonomous_pipeline.py`
- `website-delivery-swarm/schemas/site-studio-build-packet.v1.schema.json`
- `website-delivery-swarm/schemas/site-studio-success-packet.v1.schema.json`
- `backend/web/modules/custom/famtastic_pipeline/src/Service/SiteStudioBuildPacketService.php`
- `backend/web/modules/custom/famtastic_pipeline/src/Controller/SiteStudioCallbackController.php`

## Site Studio repository responsibilities

Site Studio does not need a new engine. Its boundary adapter should:

1. Accept the packet as immutable input.
2. Refuse an already-seen `idempotency_key` unless returning the prior result.
3. Verify the packet signature when transport is networked.
4. Place the supplied brief, research, selected direction contracts, preview HTML, art, and evidence into its existing recipe context.
5. Run its existing registered/YAML-sequenced stages.
6. Preserve the inbound Build DNA as read-only lineage; journal Site Studio's
   actual stages separately and return its build ID, output checksums, timing,
   exposed provider/model facts, warnings, and a Build DNA continuation
   reference in the signed success packet. Do not invent missing runtime facts.
6. Preserve its own real per-stage journal and reversible checkpoints.
7. Return `site-studio.build-success.v1` with artifact URIs, SHA-256 values, real stage ledger, warnings, timestamps, and the original correlation IDs.
8. Sign the exact raw success body with the shared callback secret.

No Site Studio build-process modification was made by this work. The adapter is a boundary responsibility for the Site Studio lane.

## Correlation invariants

The following must match exactly in both directions:

- `packet_id`
- `idempotency_key`
- `request_id`
- `project_id`

The returned direction set must equal the one-or-two direction selection in the outbound packet. FAMtastic rejects a success packet with a different project, request, packet, idempotency key, or direction set.

## Security and authority

- Transport signature: HMAC-SHA256 over the exact raw JSON body.
- Site Studio cannot set price, create a charge, purchase a domain, approve a contract, or publish production.
- FAMtastic cannot claim a Site Studio build succeeded until the signed success packet is accepted.
- Artifact checksums are evidence, not authorization.
- Secrets live in environment/Drupal settings and never in either packet or repository.

## Multiple projects

The bridge is project-scoped, not merely email- or customer-scoped. One customer can have any number of projects in flight. Every packet and callback targets exactly one `famtastic_project`, and ownership is resolved through `famtastic_customer_resource`.

## Marketing boundary — third system, not a hidden bridge participant

Marketing/outreach is deliberately outside the FAMtastic ↔ Site Studio build bridge.

- It may read the published capability matrix, sanitized portfolio/template candidates, public proof URLs, and approved campaign assets.
- It may request a lead-preview run through a versioned marketing-to-core intake contract.
- It returns campaign attribution, consent state, source identity, and deduplication keys—not a customer project mutation.
- It cannot set price, claim project ownership, charge, approve, publish, invoke a transactional customer notification, or mark Site Studio work successful.
- Marketing mail and authenticated project mail remain separate pipelines with separate consent rules, templates, gates, and idempotency keys.
- Unselected preview directions may enter the reusable portfolio/template library only after removing customer identity, customer media, confidential intake data, and unlicensed assets.

Repository placement remains an explicit milestone: inventory the current marketing surface first, then choose a dedicated package/worktree or standalone repository. Do not move it opportunistically during preview or Site Studio bridge work.

## Failure behavior

- Missing capability: route declared fallback or stop as gated.
- Invalid packet: do not register or dispatch.
- Site Studio timeout: keep project submitted; retry with the same idempotency key.
- Failed Site Studio result: record failure separately; do not mark build ready.
- Invalid signature or mismatched IDs: reject without state change.
- Duplicate success event: return success with `newly_processed=false`; do not duplicate activity or mail.
- Notification failure: retain in the existing outbox retry/dead-letter workflow.

## Human handoff today

Until Site Studio installs a packet adapter, the artifact to paste or hand over is:

`site-studio-build-packet.zip`

Its root contains `site-studio-build-packet.json`, the signature, complete stage journals, and `packet-files/` with all six reference previews, the one-or-two selected build targets, `build-dna.json`, and all structured source material.

## Success definition

The two-repository integration is complete only when Site Studio consumes a real packet, returns a real signed success packet, and the production FAMtastic portal shows the correct project state and sends exactly one transactional notification. The local contract fixture proves shape, validation, and continuation logic but is not that external proof.
