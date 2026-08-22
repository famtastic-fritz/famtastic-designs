# Canonical proof runner contract v1

## Purpose

`website_proof.generate.v1` is the only FAMtastic creative-preview routine.
It is a source-bound dispatch and evidence contract, not a renderer and not a
customer-delivery action. It exists to make a real public or portal intake
repeatable across a local/external provider while preserving the facts, Build
DNA, review gates, and exact source correlation needed by Operations.

The contract is intentionally fail-closed. A missing provider route never
falls back to a manual pilot, a deterministic mockup, direct SMTP, checkout,
payment, domain work, or publication.

## Profiles and proof phases

One routine has three immutable profiles:

| Proof phase | Profile | Direction IDs | Customer visibility |
|---|---|---|---|
| Initial public lead | `public_initial.v1` | `a`, `b`, `c` | Owner review only |
| Initial portal request | `portal_initial.v1` | `a`, `b`, `c` | Owner review only |
| Portal refinement | `portal_showcase.v1` | `d`, `e`, `f` | Owner review only |

The portal refinement is still `website_proof.generate.v1`, with
`source.proof_phase=showcase`. It creates a **new Build DNA `build_id`** tied
to the same campaign. It never overwrites the completed initial `a/b/c` record.
Together the two records explain the six-direction set.

## Source binding and dispatch

`ProofRunnerContractService` normalizes the source before any provider request:

- Public lane: exact `famtastic_intake`, prospect, and
  `public_preview_delivery_id`.
- Portal lane: exact submitted `famtastic_project_request`, opaque public ID,
  and stored `website_discovery_v3` intake.
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

Legacy callbacks remain visibly legacy. Once a campaign has a canonical runner
record, a later callback cannot bypass it by omitting Build DNA or creating a
different proof phase without a new bound record.

## Delivery gates

- Public proof staging checks final DNA against the exact prospect, campaign,
  public-preview delivery, initial phase, and `public_initial.v1` profile.
- Portal owner approval checks final DNA against the exact request public ID,
  phase, profile/routine, and campaign.
- No method in this contract calls direct SMTP. The existing outbox remains the
  only email boundary and still needs owner approval.

## Routes and local fixture

`famtastic_pipeline.settings.proof_runner_transport` is disabled by default.

- `site_studio_dispatch`: requires both Site Studio URL and dispatch secret.
- `external_runner`: requires an explicit URL/secret and an explicit signed
  provider preflight response.
- `local_contract_fixture`: only works with
  `FAMTASTIC_ALLOW_LOCAL_CONTRACT_FIXTURE=1`. It validates the contract and
  writes a receipt; it cannot generate proofs, call a provider, send mail,
  start payment, publish, or create a campaign.

Run the no-send plumbing check with:

```bash
node tests/proof-runner-contract.test.mjs
```

This proves contract shape and the fixture's declared non-mutation policy. It
does not claim a live provider run, customer email, payment, or production
proof delivery.
