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
`variants[].assets` array. The canonical wrapper must still build and checksum
the complete callback, then perform its own explicit owner-authorized import;
serializing an asset file is not promotion.

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
