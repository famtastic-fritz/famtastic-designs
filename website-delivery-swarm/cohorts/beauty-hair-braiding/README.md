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

- a self-contained owner-review hub and exactly three self-contained proof pages
  in directories a, b, and c;
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

## What must happen before a customer sees anything

The generated promotion-readiness.json deliberately remains blocked until:

1. Gemini Flash Lite Image has passed preflight and returned original art plus
   a real receipt/cost status;
2. final art is embedded into the self-contained pages;
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
