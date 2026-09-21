# Selected source creator-credit policy v2

Status: local source implementation, 2026-09-21. Owner-authorized integration;
not customer acceptance, publication, deployment or a new creative generator.
The general `CreatorCredit` presenter is unchanged.

## Signed authority and exact projection

New grants use `famtastic.source-association.v2`. The HMAC input is the schema,
one LF, then the exact `payload_json` bytes. All existing identity, intent,
selection, scope, operation, audience and one-hour lifetime fields remain.
`creator_credit_projection` is added to the signed payload and copied unchanged
into the completion mapping. No worker-selected policy or repair instruction is
accepted. Existing stored v1 grants keep their original signature domain, exact
Home check, HTML-only restriction and content-loop behavior; they acquire no PNG
or derivative exception. Explicit v1 issuance remains available to legacy callers.

The exact ordered projection is returned by
`SelectedCreatorCreditProjection::project($originalBytes, $artifact)`:

```json
{
  "schema": "famtastic.creator-credit-projection.v1",
  "policy": {
    "id": "famtastic.canonical-root-credit.v1",
    "foundation_commit": "2937a3bf58c52f146734b8779375ec884c6417ae",
    "public_base_path": "/",
    "row_sha256": "0cffde4586bb4dbc1c9163f5154b1f94f26e82e3c4f7541b840b67fb67566bc4",
    "row_bytes": 694,
    "system_asset": {
      "path": "assets/brand/famtastic-designs-logo-v1.png",
      "sha256": "ebb0477344132d32e449ba19e2b622921585aa71af0decdbcf8abfbe033fa950",
      "bytes": 2020725
    },
    "authorization": "owner_creator_attribution_only",
    "customer_acceptance_changed": false,
    "publication_authorized": false
  },
  "policy_sha256": "bb45adcade4cb87faac70ca84b14f8b1cb347395fbc8f3e14f50215d3704e914",
  "mode": "append_missing_root_row",
  "original_home": {"source_path": "<agency-original-path>", "sha256": "<original-hash>", "bytes": 123},
  "derived_home": {"path": "index.html", "sha256": "<independently-computed-hash>", "bytes": 818}
}
```

The example lengths illustrate a 695-byte append, not a fixture assertion.
`policy_sha256` hashes compact ordered UTF-8 JSON with unescaped slashes.
`mode` is `identity` only for the already-exact pinned final root row; original
and derived hashes/lengths then match. Freeze this version: changes to the row,
policy or PNG require a new supported policy ID, not reinterpretation of grants.

## Independent PHP gates

- Read the authoritative agency original under the injected storage root's
  `web/proofs`, never worker-provided original bytes. Resolve and confine the
  path; reject symlink components/traversal, invalid UTF-8/NUL, oversized input,
  or disagreement with the saved original SHA-256 and byte count (25 MiB limit).
- Apply the pinned canonical Node root row without DOM serialization. Eligible
  sources have exactly one literal case-insensitive closing body and supported
  trailing HTML whitespace. Identity requires that exact final row. Ambiguous,
  inert, hidden, nested or noncanonical existing credits are not repaired.
  The conservative PHP gate also refuses other creator markers/domain mentions;
  unsupported inputs stay fail-closed for an explicit source revision.
- Recompute and compare the entire signed projection and mapping copy. Require
  unique file paths, exact derived Home hash/length and exact PNG path/hash/length.
  No arbitrary assets, arbitrary hashes, alternate logo or new source-use license.
- Included pages must be flat requested HTML paths. Every included non-Home page
  must have exactly one current same-customer authored record and matching
  `content_records` entry plus base64 HTML evidence. Validate evidence hash/length
  and exact authored title, description, heading and body. No extra evidence or
  records; absent requested pages must be explicitly incomplete, not concealed.
- Keep current tenant/selection/input/expiry/paid-state, source-export binding,
  scope and passed-QA gates, including refusal of restricted source use. PNG
  metadata is an exact allowlist; actual PNG bytes and the private transform
  receipt remain independently verified by Node, not presumed from PHP metadata.

## Continuation and compatibility

`SelectedRecordResolver` may reuse only the independently recomputed Home
derivative and exact PNG for normal mapped continuations. Associated v1 mappings
cannot inherit that exception. A present frozen projection must match completely.
The original selection artifact remains unchanged; only the system PNG receives
owner-creator-attribution-only, publication-false rights. Existing non-system
asset authority checks remain in place; a conflicting system-asset authority
fails closed. No customer content or recipe authorization is broadened.

`SelectedFinalizedSource::continuation()` deliberately remains unchanged. Its
explicit `authority.files` contract requires matching current source artifacts,
URLs and rights for every file. An uncredited original cannot authorize a branded
Home, and Home-only authority cannot authorize the PNG. The alternate
`test-selected-source-intent.php --export-packet` fixture must supply the actual
fully branded authoritative source and explicit PNG authority; do not bypass it.

Node integration owns immutable `record.spec_snapshot.artifact_bundle` retention,
exact existing private receipt verification, actual built Home/PNG validation,
and independent source/visual QA. Current built output is never its own original
baseline. This PHP milestone does not claim first-association end-to-end proof.

## Focused evidence and fixture API

Run from the Designs root, with existing PHP plus DOM:

```sh
php scripts/test-selected-credit-policy.php
php scripts/test-selected-source-intent.php
php scripts/test-selected-credit-policy.php --policy
php scripts/test-selected-credit-policy.php --project
```

`--project` reads stdin JSON containing `html_base64` and optional `source_path`
(default `web/proofs/synthetic.html`). It emits only the exact ordered projection
JSON. This synthetic parity accessor is not a production authority input.

Executed locally: 89 dependency-free assertions, the existing three-case portal
selection harness, PHP syntax checks and five exact JSON-byte projection parity
fixtures plus policy JSON against the parent's Node helper. The PHPUnit pure
class is provided and syntax-checked; no installed PHPUnit runner exists in this
isolated worktree. No install, full suite, provider, build, customer write, network,
push or deployment was performed. Parent integration/end-to-end testing remains
separate. The normal selected-record fixture is untouched.

The 2,020,725-byte PNG can inflate inline bundle metadata when materialized on
later runs. Bounded metadata wire plus private immutable original archive remains
subsequent hardening, not a newly imposed prerequisite or a claimed traced bug.
Drive mirror/fetch/full-journey checks are deferred under the owner's isolated,
no-network, low-disk scope; repository docs and local learnings are updated.

## Completed-source fixture follow-through

The legacy export fixture now optionally accepts `complete_source_files`, a list
of at most one `{path, content_base64}` entry for the exact canonical PNG path.
It materializes bounded actual bytes and computes `selected_build_artifacts`
hashes/lengths independently of export metadata. The Node fixture supplies fully
branded Home, pinned original PNG and explicit authority/rights for both files,
starting from an unassociated normal pipeline result. The real confined source
and `SelectedFinalizedSource` checks remain untouched. This is not callback asset
ingress: `ProofAssetContract` still has its original 2 MB cap and all other limits.

The paired one-file Node roundtrip passes adoption, receipt/retry and negative
duplicate/account/missing-rights/changed-bytes cases plus existing raw numeric
JSON wire cases. The PHP three-case default harness and syntax checks pass.
No full suite or production activation; Drive sync remains deferred. Temporary
fixture cleanup names only its own exact files/directories, including failures.
