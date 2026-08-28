# FAMtastic Build DNA standard v1

## Purpose

Every creative proof, selected-direction refinement, and Site Studio-bound build
gets one immutable **Build DNA** record. It answers, without guesswork:

- What request, recipe, build class, and design constraints produced this?
- Which agent/task/tool did each stage, with which resolved provider and model?
- What exact prompt and normalized input were used, what came back, and what
  files prove it?
- How long did each recorded stage take, what did it cost, which fallback or
  repair occurred, and who made the review decision?
- What did FAMtastic pass to Site Studio, and what should Site Studio preserve
  and return?

It is observability and continuity—not a new creative engine and not permission
to publish, charge, email, or replace Drupal/Site Studio truth.

## Canonical record and evidence locations

`famtastic.build-dna.v1` is the canonical JSON contract. Its schema is
`website-delivery-swarm/schemas/build-dna.v1.schema.json` and its validator is
`website-delivery-swarm/scripts/validate-build-dna.mjs`.

Each run stores `build-dna.json` beside its receipts, exact prompts, raw or
parsed outputs, assets, screenshots, QA, and review decision. The JSON lists
all evidence using repository-relative paths and SHA-256 values. The JSON is a
map; the referred evidence is the source material. Do not flatten it into a
summary or attempt to recreate missing telemetry after the fact.

The same immutable record has three retrieval surfaces:

| Surface | Purpose | Canonical lookup |
|---|---|---|
| Filesystem / artifact package | Exact prompts, input/output evidence, media, screenshots, and hashes | `build-dna.json` next to the run evidence |
| Drupal | Searchable project/request/build projection | `famtastic_build_run` row keyed as `build-dna:<build_id>` |
| Site Studio handoff | Consistent proof-to-build context | `packet-files/build-dna.json` plus top-level `build_dna` pointer in the immutable build packet |

Google Drive is a human/LLM-readable mirror of selected standards and approved
or rights-safe run records. It is never the source of truth for customer state,
payment, credentials, private prompts containing customer secrets, or deployed
artifact integrity.

## Required fields

### Build level

- `build_id`, routine/version, classification, build class, request/project or
  campaign correlation, timestamps, repository revision, and worktree state.
- The creative controls that explain the result: intensity, research depth,
  direction mix, typography, texture/depth, motion, original-asset strategy,
  repair budget, rights/affiliation boundaries, and declared success gates.
- A completion result that distinguishes **passed**, **gated**, **partial**,
  **failed**, and **not applicable**. Never relabel a missing measurement as a
  pass.

### Stage level

For every attempt, including an unavailable provider and every retry:

- stable stage ID, sequence, capability, attempt, parent dependencies, and
  whether it was deterministic, model-assisted, tool-assisted, or human;
- resolved provider, model ID/version when the runtime exposes it, transport,
  tool/CLI, and execution class; `model.status=not_disclosed_by_runtime` is
  valid and more honest than guessing;
- verbatim rendered prompt or a SHA-256-addressed prompt artifact, normalized
  inputs, outputs, output hashes, and references used;
- start/end timestamps and `duration_ms`, or a declared `partial`/`unavailable`
  timing status; cost units, currency, provider receipt/estimate status, and
  source of the price;
- retry/fallback route and reason; schema/technical/visual result; reviewer,
  review type, decision, scored dimensions, and open gates.

### Artifact level

Every asset, page source, screenshot, receipt, prompt file, research source,
QA result, and build packet named by the record must have a role, relative path,
SHA-256, and rights/retention status. Raw credentials, OAuth tokens, card data,
private account passwords, and session transcripts are prohibited. Session
metadata is limited to relevant runtime/tool/agent identifiers and disclosed
capabilities.

For a signed proof image, retain the normalized proof `asset_manifest` in the
direction DNA and add a matching Build DNA artifact entry with the exact SHA-256
of the stored byte. The manifest may name `asset_id`, safe `relative_path`,
media type, byte size, and protected artifact path; it must never contain the
base64 source. A `run.source_lane` of `verified_cold` makes those asset hashes a
stage gate for public proof delivery, not a best-effort annotation.

## Site Studio boundary

FAMtastic owns the Build DNA up to the immutable packet boundary. When a preview
is handed over, copy `build-dna.json` into `packet-files/`, include the artifact
hash and Build DNA ID in the packet's `build_dna` pointer, and retain the full
FAMtastic stage ledger. Site Studio does not need a new engine: it receives the
record as context, journals its own real stages, and returns its build ID,
artifact hashes, timing, provider/model facts when exposed, warnings, and a
Build DNA continuation reference in the signed success packet.

The existing v1 packet is intentionally additive-compatible. New FAMtastic
packets must include the pointer; legacy packets can be read but are visibly
marked `build_dna_status=legacy_missing` rather than silently upgraded.

For componentized builds, the selected packet also carries the immutable page
recipe and binding snapshot defined by
`docs/architecture/FAMTASTIC_PAGE_COMPONENT_DOCTRINE_V1.md`. The Build DNA must
identify the component-system contract/version, page recipe ID/version, stable
page and component-instance IDs, selected definition/variant IDs, and the
hash-addressed bindings that matter to the change. A slot-only experiment names
the single permitted binding; a component or recipe change records its own
explicit acceptance result. Site Studio appends continuation evidence and must
not create a parallel component-history ledger.

## Drupal projection

`BuildTelemetryService::recordBuildDna()` writes the complete manifest into the
existing `famtastic_build_run.output_manifest` field, keyed by
`build-dna:<build_id>`. It does not create a second customer/commerce source of
truth. The projection stores searchable high-level fields plus the exact JSON.

Use:

```bash
drush famtastic:build-dna-register /absolute/path/to/build-dna.json
drush famtastic:build-dna-show <build-id>
```

The register operation is idempotent. A future worker must register the record
at package creation before notification or Site Studio dispatch. A record can be
re-read by build ID without reconstructing it from an old conversation.

## Agent protocol

All Codex, Shay, Claude, local, and provider worker roles must:

1. create the Build DNA record when the run directory is created;
2. append facts at the stage that produced them—not after memory fades;
3. validate it after final artifact hashing;
4. register its projection before handoff when a Drupal environment is in
   scope;
5. copy it into an eligible Site Studio packet and retain its hash in the
   packet/success lineage;
6. update a capability only when a run's evidence changes its maturity; and
7. mirror rights-safe run records and lessons to the designated Drive folders.

The current schema is build-led, with stage-, page-, and component-level facts
referenced through stable `stage_id`, `page_id`, component-instance ID, and
component-definition ID values. New detail extends this same record; it must not
introduce parallel ledgers.

## Validation

```bash
node website-delivery-swarm/scripts/validate-build-dna.mjs \
  marketing/campaigns/and-if-it-is-rattler-lifers/experiments/lite-image-story-20260820/build-dna.json .
```

Validation proves record shape, unique stage attempts, SHA-256 integrity for
every referenced artifact, and presence of every retrieval surface. It does not
prove an undisclosed model identity, a provider invoice, independent visual
approval, live production, or actual Site Studio execution.
