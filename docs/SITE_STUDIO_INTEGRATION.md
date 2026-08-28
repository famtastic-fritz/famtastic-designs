# Site Studio Proof Integration

## Orchestration direction

The current proof dispatch/callback contract remains the proven integration
boundary. The planned provider-neutral specialist-agent system that prepares
research, recommendations, add-ons, contracts, prototypes, QA, and evidence is
defined in `docs/architecture/SHAY_WEBSITE_DELIVERY_SWARM.md`.

Shay will orchestrate versioned routines and bounded specialist agents rather
than acting as one all-purpose model. The future `website_build_brief.v2`
extends the present prospect-safe proof payload; it does not retroactively
upgrade historical image-free pilots or unproven provider routes.

## Page recipe and component handoff

All new Site Studio-bound site builds follow
`docs/architecture/FAMTASTIC_PAGE_COMPONENT_DOCTRINE_V1.md`. The selected
handoff should carry a versioned component-system reference, the exact page
recipe snapshot, stable component-instance IDs, component definition/variant
IDs, and typed field, slot, repeater, and action bindings. The same snapshot is
hash-addressed in Build DNA.

Site Studio consumes the selected recipe as immutable build context. It may
compile the components into its supported runtime and journal the real work,
but it must not infer identity from rendered DOM position, silently change the
selected visual system, or rerun research merely because a receipt is present.
Moving an existing instance from a one-page recipe to a multi-page recipe must
preserve its identity and bindings so upgrades do not lose theme or quality.

The Booked & Branded Component Lab is current local evidence for this handoff
shape; Site Studio import and returned continuation are still unproven and must
not be reported as integrated until a real packet round-trip succeeds.

## Modes

- Offline local mode: production Drupal creates a private request and a
  `waiting_callback` proof campaign. The request is pulled to the local machine
  over authenticated SSH, Site Studio/Shay creates and reviews the three
  directions locally, and a checksum-verified bundle is promoted back through
  Drupal's normal callback validator. Site Studio does not need to be exposed
  to the internet.
- Image-free pilot mode: with no `SITE_STUDIO_URL`, the worker deterministically
  creates three isolated, category-aware landing-page directions without stock
  images or image placeholders. Each direction also receives a truthful SVG
  layout thumbnail. The mode is fail-closed for outreach unless the executing
  environment explicitly sets `FAMTASTIC_ALLOW_NO_IMAGE_PILOT_PROOFS=1`.
- Test compatibility: acceptance fixtures may continue to use
  `FAMTASTIC_ALLOW_STUB_OUTREACH=1`; that alias is not the production runbook.
- Remote mode: the worker sends one signed, idempotent generation request to
  Site Studio and records `waiting_callback`. No placeholder is presented as a
  completed remote proof.

## Offline production-to-local bridge

Use the offline path while Site Studio runs only on the local workstation. Both
scripts are dry-run by default.

```bash
# Creates an idempotent waiting proof campaign in production and pulls its
# private machine request + human brief into a mode-0700 local directory.
./scripts/fetch-local-proof-job-godaddy.sh PROSPECT_ID \
  ~/.config/famtastic/proof-jobs/PROSPECT_ID --apply

# To improve an already-sent image-free pilot, keep its current URLs live
# while the replacement is generated, then refresh the same three artifacts.
./scripts/fetch-local-proof-job-godaddy.sh PROSPECT_ID \
  ~/.config/famtastic/proof-jobs/PROSPECT_ID \
  --refresh-campaign=EXACT_PC_CAMPAIGN_ID --apply

# After local generation and visual review, validate without changing prod.
./scripts/promote-local-proof-godaddy.sh BUNDLE_DIR

# Promote the exact reviewed bundle through the Drupal import boundary.
./scripts/promote-local-proof-godaddy.sh BUNDLE_DIR --apply
```

The first command downloads `request.json`, `request.md`, and `handoff.json`.
The generated bundle must contain:

```text
BUNDLE_DIR/
  manifest.json
  a/index.html                 a/thumbnail.png|jpg
  b/index.html                 b/thumbnail.png|jpg
  c/index.html                 c/thumbnail.png|jpg
  a|b|c/design-dna.json        optional
```

`manifest.json` must repeat the `campaign_id` and `job_id` from
`handoff.json`, add a unique `event_id`, and record the actual local execution
facts: `provider`, `agent_name`, `flow_key`, `task_key`, `prompt_snapshot`,
`input_snapshot`, and `source_sha`. Do not label a run as Shay unless Shay
actually generated it.

Promotion does not SCP files into the public proof directory. It uploads one
private callback payload, verifies its SHA-256, exact campaign confirmation,
job identity, three unique directions, HTML/content limits, and image
signatures, then lets Drupal publish the artifacts and queue outreach. Replays
of the same event are harmless. The private payload is retained as an audit
artifact.

## Dispatch contract

Remote configuration is environment-owned:

```text
SITE_STUDIO_URL=https://studio.example/api/integrations/famtastic/proof-jobs
SITE_STUDIO_DISPATCH_SECRET=...
FAMTASTIC_PUBLIC_BASE_URL=https://famtasticdesigns.com
SITE_STUDIO_CALLBACK_SECRET=...
```

The JSON request includes:

- `schema_version`;
- `idempotency_key` (`proof:<campaign_id>`);
- campaign and prospect-safe business facts;
- named directions `a`, `b`, and `c`;
- `required_variant_count: 3`;
- callback URL.

The raw JSON is signed with HMAC-SHA256 in
`X-FAMtastic-Signature`. The same idempotency key is sent in
`Idempotency-Key`. Site Studio must return a nonempty `job_id`.

Site Studio owns the matching runtime settings:

```text
FAMTASTIC_PROOF_DISPATCH_SECRET=...
FAMTASTIC_PROOF_CALLBACK_SECRET=...
FAMTASTIC_PROOF_JOBS_DIR=~/.config/famtastic/proof-jobs
FAMTASTIC_PROOF_OUTPUT_ROOT=~/.config/famtastic/proof-output
FAMTASTIC_PROOF_PROVIDER=shay
```

The supported proof path routes generation through `shay -z`; it has no
direct Claude or OpenAI API dependency. Site Studio packages every proof as
portable, script-free HTML by inlining shared CSS before page CSS, embedding
the required layered-hero rules, replacing empty media slots with intentional
brand-colored visuals, and safely resolving local image references. It also
renders a 1280x800 screenshot of each packaged direction for the selection
card; the signed callback carries the JPEG/PNG thumbnail beside the HTML.

## Callback contract

Site Studio posts to:

```text
POST /api/pipeline/site-studio/callback
X-FAMtastic-Signature: sha256=<HMAC-SHA256(raw body)>
```

The secret is `SITE_STUDIO_CALLBACK_SECRET`. The body contains `event_id`,
`campaign_id`, `job_id`, and exactly three variants. A core job contains one
unique `direction_id` for each of `a/b/c`. An explicitly prepared
`local-showcase-*` job contains one unique direction for each of `d/e/f` and
appends them to the account-owned core set. HTML is limited to 500 KB and may
include optional `design_dna`.

The callback:

- rejects missing or duplicate directions;
- rejects active content such as scripts, iframes, event handlers, and
  JavaScript URLs;
- verifies the callback job belongs to the campaign;
- writes each artifact to its isolated
  `web/proofs/<campaign>/<direction>/index.html` filesystem path, publicly
  served as `/proofs/<campaign>/<direction>/`;
- validates and stores each screenshot as
  `/proofs/<campaign>/<direction>/thumbnail.<jpg|png>`;
- creates all three callback proof records before marking the campaign ready;
- accepts a showcase only when the matching account-owned campaign already has
  exactly `a/b/c`, preserves those artifacts, and returns all six to owner review;
- records callback event IDs so replay is harmless;
- appends a `proof.ready` event and queues outreach preparation.

An invalid or partial callback never marks proofs ready.

### Optional signed proof assets

An individual variant may also include a deliberately small `assets` list:

```json
{
  "asset_id": "hero",
  "relative_path": "hero.webp",
  "media_type": "image/webp",
  "base64": "<raw base64 only>",
  "sha256": "<lowercase sha256 of decoded bytes>"
}
```

`asset_id` is lowercase `[a-z][a-z0-9_-]{0,63}` and `relative_path` is a
non-absolute, non-traversing path (at most six safe segments). The callback
accepts only JPEG, PNG, WebP, and AVIF, validates the filename extension,
declared MIME, decoded magic bytes, exact hash, and the 1.5 MB per-file / 3 MB
per-direction / four-assets-per-direction limits. It rejects data URLs,
symlinks (at local-promotion packing time), unlisted files, and a missing or
changed hash.

Generated HTML must use `assets/<relative_path>` (for example,
`<img src="assets/hero.webp">`). FAMtastic stores the original byte at the
protected deterministic location
`web/proofs/<campaign>/<direction>/assets/<relative_path>` and records only a
normalized, byte-free `design_dna.asset_manifest` with its SHA-256, size, MIME,
and artifact path. The raw `base64` must never be copied into Design DNA.

For public concept rooms, images are served only through the current signed
proof URL:

```text
/api/public-preview/<delivery>/<signature>/proofs/<a-f>/assets/<relative_path>
```

The proof response injects a proof-level signed `<base>` at serve time so the
relative `assets/...` URL resolves once. It does not rewrite the stored HTML or
its Build DNA hash. The raw asset directory remains denied to static access;
do not substitute a public `/proofs/.../assets/...` link. A frozen room accepts
only the directions in its snapshot (one through six `a`–`f` as configured by
its delivery profile), not an inferred directory listing.

When `build_dna.run.source_lane` is `verified_cold`, each frozen direction must
contain at least one signed asset and every asset SHA must be present in the
registered Build DNA artifact manifest before the room can stage. Assetless
legacy/non-`verified_cold` rooms remain readable until regenerated.

## Pilot boundary

The image-free pilot is an intentional temporary production option for a
small, explicitly approved campaign. It uses only the prospect's supplied
business category, description, public phone, and service area; it does not
invent testimonials, pricing, inventory, ratings, or performance claims. Site
Studio remains the full-media production path once its provider and failure
callback are proven.

## Verification

```bash
./scripts/e2e-site-studio-callback.sh
./scripts/e2e-local-proof-promotion.sh
MODE=refresh ./scripts/e2e-local-proof-promotion.sh
```

The synthetic test runs a signed mock Site Studio endpoint, proves remote
dispatch state, rejects a bad signature, partial callback, and active content,
accepts normal metadata plus exactly three isolated artifacts, and proves
callback replay idempotency. The offline tests separately prove new-request and
in-place pilot-refresh exports, bundle validation, checksum rejection,
exact-three local import, replay safety, stable public variant identities, and
persisted Shay/prompt/build telemetry.
