# Beauty / Hair / Braiding first-ten proof builder

This is a local preparation tool for the first ten mapped Beauty, Hair, and
Braiding leads. It does not read the source XLSX itself, call Gemini, create a
Drupal record, publish a proof, or send an email.

The mapped input is the contract. A real lead is eligible for this builder only
when an operator has already completed contact, duplicate, suppression, and
research corroboration work. The builder rejects a record without:

- a stable operator lead ID and business name;
- an allowed beauty, hair, or braiding category;
- at least one source-backed verified fact;
- a research teaser with at least one source URL; and
- a valid campaign ID.

Raw contact email is accepted only to create a one-way contact reference. It is
not written into proof pages, promotion manifests, Build DNA, or reports.

## Run

Create an operator-only mapped JSON or CSV outside the repository. Do not copy
the source lead spreadsheet into this directory.

    node website-delivery-swarm/cohorts/beauty-hair-braiding/build-beauty-proof-cohort.mjs \
      --input /secure/operator/beauty-first-ten.mapped.json \
      --output artifacts/beauty-proof-cohort/pc-beauty-first-ten-20260826 \
      --limit 10

The builder takes the first ten records in the explicit input order. It refuses
to overwrite an existing output directory.

For a synthetic local check:

    node website-delivery-swarm/cohorts/beauty-hair-braiding/build-beauty-proof-cohort.mjs \
      --input website-delivery-swarm/cohorts/beauty-hair-braiding/input.example.json \
      --output artifacts/beauty-proof-cohort/example-json \
      --limit 2

## Output per lead

Each lead bundle contains:

- a self-contained owner-review hub and exactly three initially self-contained
  proof pages in directories a, b, and c;
- Safe, Medium FAMtastic, and Ultra FAMtastic layout systems that vary per lead
  through source-backed copy, palette hints, motif hints, and a deterministic
  design seed;
- three Gemini Flash Lite Image prompt artifacts plus one prompt manifest;
- source evidence, redacted intake, direction DNA, static QA, promotion
  readiness, and a Build DNA record with artifact hashes;
- a manifest.json that passes the structural input contract used by
  scripts/promote-local-proof-godaddy.sh.

The CSS/SVG artwork and generated PNG thumbnails are local fallbacks only. They
are deliberately not described as receipt-backed image generation.

## Canonical runtime binding (required before art finalization)

The builder deliberately creates an **unbound, non-importable** local
preparation package. Its `local-*` job ID and `beauty-proof:*` event ID are
placeholders for static-contract checks only; they are never eligible for a
Drupal Build DNA registration, signed-asset import, public room, or callback.

After the canonical FAMtastic ingress has created the real Prospect, Proof
Campaign, public campaign ID, and exact job/callback correlation, give this
local-only binder a separate operator-only JSON input (see
`runtime-binding-input.example.json`). It does not create or guess any ID. It
requires one exact binding per selected bundle:

```json
{
  "schema": "famtastic.beauty-proof-runtime-binding-input.v1",
  "source_lane": "verified_cold",
  "package_profile": "anonymous_safe_medium_ultra_v1",
  "cohort_manifest": "artifacts/beauty-proof-cohort/pc-example/cohort-manifest.json",
  "bindings": [{
    "bundle": "artifacts/beauty-proof-cohort/pc-example/example-business-1234abcd",
    "prospect_id": 101,
    "proof_campaign_id": 202,
    "public_preview_delivery_id": 303,
    "campaign_id": "pc-example",
    "job_id": "public-preview:proof.generate:delivery:303",
    "callback_event_id": "cold-proof:callback:pc-example:1",
    "run_started_at": "2026-08-27T00:00:00.000Z"
  }]
}
```

First validate the entire cohort without changing it:

    node website-delivery-swarm/cohorts/beauty-hair-braiding/bind-beauty-proof-runtime.mjs \
      --input /secure/operator/pc-example.runtime-binding.json \
      --dry-run

Then run the same command without `--dry-run`. It writes one immutable
`runtime-binding.json` per bundle, injects the complete Build DNA `run`
(`prospect_id`, `proof_campaign_id`, public `campaign_id`, `source_lane`, job,
callback event, and recorded run start), replaces the local manifest IDs, and
rehashes the Build DNA artifact ledger. It also marks the cohort manifest
`bound-canonical-runtime` only after every selected bundle has passed its
binding preflight. A second binding attempt, an absent binding, a mismatched
campaign, or any local placeholder ID fails closed.

The binder remains local-only. It does not call Gemini, Drupal, Site Studio,
the importer, production, or mail. The finalizer and callback-asset serializer
both reject an unbound or internally mismatched bundle.

## Offline Gemini Flash Lite worker handoff

After canonical runtime binding, the cohort adapter can create one
operator-only worker input per lead. It reads the a/b/c prompt files as bytes,
requires exact UTF-8 round-tripping, and retains their SHA-256 values. It uses
`trim()` only to reject an empty prompt: it does not trim, normalize, or
rewrite the prompt before the worker hashes it or sends it to the provider.

```bash
node website-delivery-swarm/cohorts/beauty-hair-braiding/prepare-gemini-flash-lite-worker-input.mjs \
  --cohort "$PWD/artifacts/beauty-proof-cohort/pc-example/cohort-manifest.json" \
  --output /secure/operator/pc-example.gemini-flash-lite-input \
  --dry-run
```

Remove `--dry-run` only to write the local JSON handoff. The output must be a
new absolute directory outside the repository because it contains the exact
prompt material. The handoff has one `<lead>.image-prompts.json` input with
exactly these names: `a-hero.png`, `b-hero.png`, and `c-hero.png`.

The imported worker is documented in
`website-delivery-swarm/GEMINI_FLASH_LITE_WORKER_PROVENANCE.md`. Its offline
validation modes are safe to run before any paid execution:

```bash
node website-delivery-swarm/gemini_flash_lite_image_worker.mjs \
  --validate-input \
  --prompts /secure/operator/pc-example.gemini-flash-lite-input/example-business.image-prompts.json

node website-delivery-swarm/gemini_flash_lite_image_worker.mjs \
  --validate-receipt \
  --prompts /secure/operator/pc-example.gemini-flash-lite-input/example-business.image-prompts.json \
  --receipt /secure/operator/pc-example.gemini-flash-lite-output/generation-receipt.json
```

Both commands are offline: they do not read Keychain or make provider, Drupal,
Site Studio, import, production, mail, or scheduler calls. They reject empty
or duplicate directions/filenames, a changed prompt hash, missing provider
usage evidence, and incomplete receipt result sets. The worker's actual
`--execute` mode remains a separate paid-provider and owner-approval gate; the
adapter does not invoke it.

## Receipt-backed local finalization

Once a separate approved worker has generated one original PNG or JPEG hero per
direction, the local finalizer can consume those files without making a Gemini
call. It only accepts a prepared cohort whose source lane is
`verified_cold` and whose package profile is the current anonymous three-proof
offer: `anonymous_safe_medium_ultra_v1` (Safe `a`, Medium FAMtastic `b`, Ultra
FAMtastic `c`). It refuses partial sets, wrong lanes/profiles, already-finalized
bundles, unsupported source image formats, missing `cwebp`, and receipts that
do not match the exact generated prompt hash plus the supplied source image
hash and byte length.

The required input shape is shown in
`finalizer-input.example.json`. The receipt is a normalized worker handoff, not
a credential dump. Its required fields are:

- `provider` (`google-gemini-api` or `gemini-developer-api`), `api`
  (`generateContent` or `interactions`),
  `model: gemini-3.1-flash-lite-image`, `status: completed`, timestamps, and
  one provider evidence field (`usage_metadata`, `response_sha256`, or
  `interaction_id`);
- a `results` entry selected by `receipt_result_id` with `id`, image
  `sha256`, `bytes`, `mime_type`, positive `duration_ms`, and the exact
  `prompt_sha256`; and
- optional actual or expected USD cost. The finalizer records only what the
  receipt supplies; it never invents a billed cost.

The finalizer also accepts the existing authenticated worker's
`famtastic.gemini-flash-lite-image-receipt.v1` output directly. For that
receipt shape, set `receipt_result_id` to the worker artifact's exact
`direction_id` (`a`, `b`, or `c`). The worker reports per-image duration and
completion time but not a fabricated per-image start time; the finalizer keeps
that timing status as `partial-receipt-recorded` rather than inventing it. This
is a local receipt adapter only—no second image route or provider call is made.

The finalizer rejects credential-like fields in a receipt and writes a compact
normalized receipt without inline image bytes. It uses local `cwebp -q 95 -m
6` to create `a|b|c/assets/hero.webp`, replaces only the prepared SVG art
fallback in each page, and writes an exact per-direction `assets.json` plus
per-asset SHA-256 data to direction DNA, the promotion manifest, the
finalization report, static QA, and Build DNA.
The manifest uses `famtastic.signed-proof-assets.v1` with `relative_path:
hero.webp`, so the eventual signed-asset importer can store it under its
variant asset root without needing a base64 hero embedded in HTML.

After finalization, the local serializer turns those frozen `assets.json`
records into the exact callback wire shape used by the signed-asset importer:
`assets[]` entries with `asset_id`, `relative_path`, `media_type`, `base64`,
and `sha256`. It validates the local bytes against both manifests before
writing a file and never sends it anywhere:

    node website-delivery-swarm/cohorts/beauty-hair-braiding/serialize-signed-proof-assets.mjs \
      --bundle artifacts/beauty-proof-cohort/pc-example/example-business-1234abcd \
      --output /secure/operator/pc-example.callback-assets.json

The eventual promotion wrapper consumes each serialized variant as its
`variants[].assets` array. The serializer also carries the frozen canonical
`prospect_id`, `proof_campaign_id`, public `campaign_id`, job, callback event,
and runtime-binding SHA so the wrapper cannot silently recover the old local
placeholder identity. Before it writes anything, it also verifies that final
Build DNA names that same binding and includes every signed asset SHA. The
canonical wrapper must still build and checksum the complete callback, then
perform its own explicit owner-authorized import; serializing an asset file is
not promotion.

The current canonical local callback path is deliberately separate from the
historical `scripts/promote-local-proof-godaddy.sh` route. That historical
promoter is not valid for runtime-bound `verified_cold` work. First assemble
the complete, importable callback from the finalized bundle and the serialized
asset file:

    node website-delivery-swarm/cohorts/beauty-hair-braiding/assemble-verified-cold-callback.mjs \
      --bundle artifacts/beauty-proof-cohort/pc-example/example-business-1234abcd \
      --assets /secure/operator/pc-example.callback-assets.json \
      --output /configured/private/famtastic/pc-example.verified-cold.callback.json

The assembler is local-only. It copies the finalized HTML, PNG/JPEG thumbnail,
direction DNA, and canonical signed `assets[]` bytes into one bounded callback
while verifying every runtime ID and every Build DNA asset/page hash. It is
fixed to the current signed a/b/c package; a different configured 1--6 cohort
must supply its own compatible finalizer/import adapter and will fail closed
here rather than silently dropping directions.

Then inspect the checksum plan (no database write) or explicitly import it
through the narrow local Drupal lane:

    scripts/import-verified-cold-proof.sh \
      --delivery 303 --confirm pc-example \
      --callback /configured/private/famtastic/pc-example.verified-cold.callback.json \
      --build-dna artifacts/beauty-proof-cohort/pc-example/example-business-1234abcd/build-dna.json

    scripts/import-verified-cold-proof.sh \
      --delivery 303 --confirm pc-example \
      --callback /configured/private/famtastic/pc-example.verified-cold.callback.json \
      --build-dna artifacts/beauty-proof-cohort/pc-example/example-business-1234abcd/build-dna.json \
      --apply-local

`--apply-local` requires the existing `SITE_STUDIO_CALLBACK_SECRET`, computes
the callback HMAC without printing the secret, and calls only
`famtastic:verified-cold-proof-import`. It does not promote, publish, stage a
room, or send email. Importing proof artifacts leaves the owner-review gate in
place.

First validate without changing the prepared bundles:

    node website-delivery-swarm/cohorts/beauty-hair-braiding/finalize-beauty-proof-cohort.mjs \
      --input /secure/operator/pc-example.finalizer.json \
      --dry-run

Then run the same command without `--dry-run` only after the worker handoff is
complete:

    node website-delivery-swarm/cohorts/beauty-hair-braiding/finalize-beauty-proof-cohort.mjs \
      --input /secure/operator/pc-example.finalizer.json

This remains a local artifact operation. It never calls Gemini, Drupal, the
proof promoter, production, or mail. Browser QA, independent visual/rights
review, canonical signed-asset import, owner approval, proof publication, and
transactional outbox delivery remain explicit gates after finalization.

## What must happen before a customer sees anything

The generated promotion-readiness.json deliberately remains blocked until:

1. Gemini Flash Lite Image has passed preflight and returned original art plus
   a real receipt/cost status;
2. final art is normalized into portable linked `assets/hero.webp` files;
3. Playwright has captured desktop and 390px QA screenshots;
4. an independent reviewer has passed the final visual set or documented repair;
5. a real FAMtastic Drupal campaign, prospect, job, Build DNA projection,
   owner review, share room, and transactional outbox record exist.

Only after those gates may an operator dry-run, then explicitly authorize the
existing promotion/import lane. The builder never invokes that lane.

## CSV mapping

The CSV adapter accepts the header shown in input.example.csv. Array fields use
a pipe character. JSON is preferred for several verified facts or source URLs.
The CSV adapter is intentionally strict: incomplete research evidence fails
instead of filling facts from a template.
