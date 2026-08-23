# FAMtastic preview-to-build boundary v1

**Status:** Current doctrine. This document corrects and supersedes the retired
"Site Studio proof runner" language in legacy fixtures and runbooks. It does
not by itself claim that every production setting or legacy code path has been
migrated.

## One-line rule

**FAMtastic Designs owns the preview factory and customer delivery; Site Studio
only builds from an already-selected immutable FAMtastic build packet and later
returns a build-success packet.**

Site Studio is never the public- or portal-preview generator, preview-artifact
store, proof-share host, owner-review surface, email sender, request owner, or
project source of truth.

## Ownership contract

| Capability | FAMtastic Designs owns | Site Studio owns |
| --- | --- | --- |
| Intake and research | Normalized lead/portal intake, consent, source facts, research, direction contract | Nothing before a selected packet arrives |
| Preview generation | `website_proof.generate.v1`, its provider routing, Build DNA, QA/review gates, and the selected versioned direction contract (current defaults: public 3, detailed up to 6, revision 1) | Never generates a customer preview |
| Preview artifacts and slots | `proof_campaign`, `proof_variant`, revision-artifact lineage, controlled filesystem/object storage, screenshots, hashes, and retention | May read the selected packet copies; it cannot become the authoritative preview store |
| Preview access and delivery | Signed/unlisted concept rooms, authenticated portal previews, owner approval, transactional outbox/email, revocation, and same-email claim | Never hosts a proof room, sends proof mail, or advances a delivery state |
| Customer and commercial truth | Prospect, intake, customer, request, project, selection, revision, package, terms, payment, and Operations records | Never sets commercial or customer state |
| Build handoff | Creates, registers, signs, archives, and dispatches one or two selected immutable `famtastic.site-studio.build-packet.v1` packets | Consumes the packet as read-only input |
| Build result | Verifies the signed `site-studio.build-success.v1` packet, updates the correlated FAMtastic project, and queues any approved notification | Executes its own build stages and returns only real build facts, output hashes, warnings, and stage ledger |

## Canonical flow

```text
public or portal intake
  → FAMtastic preview generation and Build DNA
  → FAMtastic-controlled artifact slots and screenshots
  → FAMtastic owner review
  → FAMtastic signed concept room / portal preview / approved email
  → selection and any FAMtastic-owned revision
  → FAMtastic immutable selected-build packet
  → private or offline Site Studio build execution
  → signed build-success packet
  → FAMtastic project update and approved notification
```

The default public three-proof package, default detailed portal package of up to
six proofs, and selected-direction revision remain FAMtastic proof histories.
The exact count, mix, labels, access, and send behavior are selected from a
versioned proof-package profile before dispatch and then frozen for that run.
They are not intermediate Site Studio jobs, and a Site Studio success result
cannot rewrite them.

## Provider routing rule

The preview runner may use an internal FAMtastic worker or a declared
FAMtastic-controlled provider adapter. It must meet
`docs/architecture/PROOF_RUNNER_CONTRACT_V1.md`: actual provider preflight,
source-bound Build DNA, the exact selected direction contract, browser QA,
independent visual review, artifact hashes, and a verified completion receipt.

`site_studio_dispatch`, `SITE_STUDIO_URL`, and the old
`/api/pipeline/site-studio/callback` proof callback are **legacy preview
transport names**, not approved preview-provider routes. Their presence in old
code, fixtures, or audit records does not authorize them for a new preview run.

FAMtastic may keep a Site Studio workstation private. The selected build packet
can be handed to it through an approved offline/private channel; Site Studio
then returns a signed success packet to FAMtastic. No public Studio listener,
Studio hostname, or Studio preview URL is required for FAMtastic to generate,
store, review, share, or email previews.

## Packet boundary

Before any Site Studio handoff, FAMtastic must freeze and retain:

- the selected direction IDs (one or two only), their exact HTML/art/image
  hashes, and their Build DNA reference;
- the approved brief, research, permissions, assets, design constraints, and
  project correlation;
- `famtastic.site-studio.build-packet.v1`, its signature, idempotency key, and
  FAMtastic archive location.

Site Studio may enrich a final build, but it must not alter the inbound preview
record or return a replacement preview campaign. Its signed
`site-studio.build-success.v1` result must correlate to the exact packet and
report actual build-stage facts. FAMtastic alone decides whether that result
updates a project or triggers an approved customer notification.

## FAMtastic preview release checklist — Site Studio stays private

Use this checklist for a preview-delivery release. It deliberately does not
deploy, expose, or depend on Site Studio.

- [ ] Merge the reviewed FAMtastic source and use an exact clean `main` SHA.
- [ ] Run the FAMtastic backend migration and verify the preview-delivery,
  proof-variant, Build DNA, and revision records exist.
- [ ] Configure only a FAMtastic-controlled preview runner route; leave the
  legacy `site_studio_dispatch` route, `SITE_STUDIO_URL`, and
  `SITE_STUDIO_DISPATCH_SECRET` unset/disabled for preview work.
- [ ] Prove runner preflight with its declared provider/model receipt and
  fallback behavior; do not infer capability from a desktop sign-in.
- [ ] Prove that FAMtastic receives exact HTML, screenshots, hashes, final
  Build DNA, browser QA, and independent visual-review evidence before an
  owner stage can be created.
- [ ] Verify all preview artifacts live in FAMtastic-controlled slots and that
  no proof URL points at a Site Studio host, localhost, a workstation path, or
  an unreviewed provider URL.
- [ ] Browser-test the FAMtastic owner review, signed/unlisted room,
  revocation, anonymous access boundary, same-email claim, authenticated
  portal, selection, and revision paths.
- [ ] Stage but do not send the frozen transactional invitation; the owner
  approval and outbox receipt remain the only email gate.
- [ ] Confirm Site Studio has no public listener, no preview-generation
  credential, and no customer-mail authority. A later selected-build handoff
  is a separate, private/offline release gate.
- [ ] Record the exact SHA, migration result, provider receipt, Build DNA,
  browser evidence, owner approval, and outbox/delivery evidence in the
  FAMtastic release record.

Do not release a customer preview path when a proof requires a Site Studio
preview callback, when FAMtastic cannot retrieve the artifact from its own
slot, or when the only evidence is a legacy fixture. Those are migration
failures, not reasons to expose Site Studio or relax the FAMtastic verifier.

## Migration and evidence boundary

Historical Site Studio proof-dispatch records and fixtures preserve prior audit
evidence only. A production release is conformant with this doctrine only after
the FAMtastic preview path uses a compatible FAMtastic-controlled runner and
passes the checklist above.

For the selected-build handoff contract, use
`docs/architecture/GANDALF_FAMTASTIC_SITE_STUDIO_BRIDGE.md`. For public proof
delivery, use `docs/architecture/PUBLIC_PREVIEW_DELIVERY_V1.md`. For
cross-session startup and the production-compatible promotion distinction, use
`docs/architecture/FAMTASTIC_PREVIEW_DELIVERY_OPERATING_ROUTINE_V1.md`.
