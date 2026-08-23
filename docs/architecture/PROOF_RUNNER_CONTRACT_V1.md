# Canonical proof runner contract v1

## Purpose

`website_proof.generate.v1` is the only FAMtastic creative-preview routine.
It is a FAMtastic-owned source-bound generation, artifact-slot, and evidence
contract—not a Site Studio renderer and not a customer-delivery action. It
exists to make a real public or portal intake repeatable across a
FAMtastic-controlled local/external provider while preserving the facts, Build
DNA, review gates, and exact source correlation needed by Operations.

FAMtastic stores every returned preview artifact in its own proof slots,
creates the share/portal access surface, and controls owner review and the
transactional outbox. Site Studio is not an eligible implementation of this
routine. It receives a later selected immutable build packet only; see
`docs/architecture/FAMTASTIC_PREVIEW_TO_BUILD_BOUNDARY_V1.md`.

The contract is intentionally fail-closed. A missing provider route never
falls back to a manual pilot, a deterministic mockup, direct SMTP, checkout,
payment, domain work, or publication.

## Profiles and proof phases

One routine has five immutable profiles:

| Proof phase | Profile | Direction IDs | Customer visibility |
|---|---|---|---|
| Initial public lead | `public_initial.v1` | `a`, `b`, `c` | Owner review only |
| Initial portal request | `portal_initial.v1` | `a`, `b`, `c` | Owner review only |
| Legacy append-only showcase | `portal_showcase.v1` | `d`, `e`, `f` | Owner review only |
| Detailed portal refinement | `portal_refined_six.v1` | `a`, `b`, `c`, `d`, `e`, `f` | Owner review only |
| Selected-direction revision | `portal_selected_direction_revision.v1` | exactly one selected `a`–`f` | Owner review only |

`portal_showcase.v1` is readable for historical records but is not used for a
new detailed-intake delivery. The new detailed run is still
`website_proof.generate.v1`, with `source.proof_phase=refined_six`, but it
creates a **brand-new proof campaign** and requires exactly one fresh `a-f`
callback. It cannot reuse public `a/b/c` artifacts or append `d/e/f` to a
public campaign.

A revision is also `website_proof.generate.v1`, with
`source.proof_phase=revision`. It is not a replacement proof campaign. Its
source contract binds the exact account request, existing refined campaign,
revision ID/version, selected direction, customer-note hash, original baseline
artifact/design/Build DNA hashes, and a deterministic inactive baseline render
reference hash. The existing `a-f` artifacts are never overwritten. A verified
callback writes a separate candidate only; owner approval remains the gate for
customer visibility and durable outbox notifications.

## Source binding and dispatch

`ProofRunnerContractService` normalizes the source before any provider request:

- Public lane: exact `famtastic_intake`, prospect, and
  `public_preview_delivery_id`.
- Portal lane: exact submitted `famtastic_project_request`, opaque public ID,
  and stored `website_discovery_v3` intake.
- Refined-six lane: exact immutable `detailed_intake_snapshot` and consented
  asset-manifest SHA-256 values, the exact public-preview delivery ID, and a
  verified parent public proof campaign + completed public Build DNA ID/hash.
  The parent must itself be source-bound to that exact public delivery. The
  runner records this continuation in both `run.source_correlation` and
  top-level Build DNA `lineage`. Those refined values are read from the
  persisted request; a queue payload may corroborate them but cannot replace
  them with alternative snapshot bytes or hashes.
- Revision lane: an exact durable revision row and its selected direction must
  agree with the authenticated request. The runner rechecks the original
  artifact bytes against its SHA-256 and produces an inactive render reference
  (active tags, handlers, `javascript:` URLs, and visible phone/email values
  removed) for the remote worker. Both original and sanitized-reference hashes
  are recorded in Build DNA lineage. A retry resumes the same bound build ID and provider
  idempotency key rather than minting a competing revision run.
- A contact hash may correlate delivery; raw email, phone, credentials, and
  Drupal `private://` references may not leave the canonical envelope.

The service persists a private request receipt and a **preflight-only** Build
DNA record. When a campaign is created (or when its d/e/f phase begins), that
record is bound to the exact Drupal campaign before outbound HTTP dispatch.
The remote signed payload contains the complete sanitized contract inline; it
does not rely on a remote runner reading a `private://` URI.

Retries resolve an existing campaign only through the same source correlation,
not through the latest campaign for a prospect. This permits a customer to
have multiple projects without crossing proof histories.

## Callback acceptance

For a runner-bound campaign, a signed callback must include the prepared
`build_id` and original contract SHA-256. The final Build DNA must match the
same prospect, campaign, profile, proof phase, routine, and source IDs.

Every returned direction must include `artifact_sha256` for its exact HTML.
The verifier recomputes that hash and requires a matching final Build DNA
artifact with:

```json
{
  "role": "proof_html",
  "direction_id": "a",
  "sha256": "<hash-of-callback-html>"
}
```

The final DNA must also include passing browser QA, a passing technical quality
state, and a non-empty independent visual-review decision and reviewer. A
stage merely named `visual_review` is not enough.

For runner-bound work, the final DNA must declare
`classification=production_proof_completion` and
`run.completion_state=provider_completed`. A local fixture, preflight, or
generic "complete" record cannot stage public or customer proof delivery.
The verifier also rejects explicit fixture, mock, test, stub, simulation,
fake, loopback, or "not a real provider" markers in the formal provider,
model, stage, artifact, retrieval, and reviewer provenance fields. A local
test cannot relabel itself as production merely by changing the top-level
classification.

Legacy callbacks remain visibly legacy. Once a campaign has a canonical runner
record, a later callback cannot bypass it by omitting Build DNA or creating a
different proof phase without a new bound record.

## Delivery gates

- Public proof staging receives the exact `public_preview_delivery_id` from
  verified final DNA—not a prospect lookup—and checks prospect, campaign,
  delivery, initial phase, and `public_initial.v1` profile. The verified
  callback freezes that owner-only stage with its own Build DNA ID/hash; it
  does **not** queue or send customer email.
- Portal owner approval checks final DNA against the exact request public ID,
  phase, profile/routine, campaign, and (for six proofs) detailed lineage.
- No method in this contract calls direct SMTP. The existing outbox remains the
  only email boundary and still needs owner approval.

## Routes and local fixture

`famtastic_pipeline.settings.proof_runner_transport` is disabled by default.

- `famtastic_preview_runner_dispatch`: requires
  `FAMTASTIC_PREVIEW_RUNNER_URL` and
  `FAMTASTIC_PREVIEW_RUNNER_DISPATCH_SECRET`. The worker returns only signed
  callback bytes/evidence to `/api/pipeline/preview-runner/callback`; FAMtastic
  writes the canonical artifacts and controls proof slots, rooms, and email.
- `external_runner`: requires an explicit FAMtastic-controlled URL/secret and
  an explicit signed provider preflight response. Its output is returned to
  FAMtastic, which validates and stores the proof slots before any room/email
  action.
- `site_studio_dispatch`: a **retired legacy transport** retained only so old
  records and fixtures can be read. It is not a supported route for any new
  preview profile and must remain disabled in a preview release. A Site Studio
  URL or secret never proves preview-provider readiness.
- `local_contract_fixture`: only works with
  `FAMTASTIC_ALLOW_LOCAL_CONTRACT_FIXTURE=1`. It validates the contract and
  writes a receipt; it cannot generate proofs, call a provider, send mail,
  start payment, publish, or create a campaign.

Run the no-send plumbing check with:

```bash
node tests/proof-runner-contract.test.mjs
```

This proves contract shape and the fixture's declared non-mutation policy. It
checks the public three-pack, detailed refined six-pack, and one-direction
revision contract shapes. It does not claim a live provider run, customer
email, payment, or production proof delivery.
