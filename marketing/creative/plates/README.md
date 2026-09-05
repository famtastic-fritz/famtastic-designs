# Tier-2 Cheap Image Plates — Proof + Reusable Prompt Library

Proves the Tier-2 "cheap multiplier" tier of
`docs/architecture/CAMPAIGN_ASSET_CASCADE_AND_DISTRIBUTION_V1.md` (and the
same $0.0336/image figure in `docs/marketing/CHEAP_PRODUCTION_ECONOMICS_V1.md`)
for the platform-dependency blog series
(`marketing/blog/clusters/cluster-own-website-vs-rented-platforms/cluster-plan.json`)
and the Ghost Town Ep1 campaign (`marketing/campaigns/ghost-town-ep1/`).

Everything in this directory is new output from this task. Nothing under
`marketing/video/` was touched (another agent is actively rendering there).

## Provider actually used

- **Provider**: Google Gemini Developer API (`google-gemini-api`)
- **Model id**: `gemini-3.1-flash-lite-image` (provider display name: "Nano
  Banana 2 Lite", confirmed via a live model-info call — see Preflight below)
- **API**: `generateContent`
- **Endpoint** (exact, confirmed working): `https://generativelanguage.googleapis.com/v1/models/gemini-3.1-flash-lite-image:generateContent`
  - Note: `v1beta` and the `ASPECT_RATIO_*`/`IMAGE_SIZE_*` enum forms both
    400 on this model (pre-existing evidence at
    `marketing/campaigns/and-if-it-is-rattler-lifers/evidence/gemini-flash-lite-image-api-test-20260820/receipt-attempt-{1,2,3}*.json`).
    The working request body uses `v1` and plain string values:
    `generationConfig: { responseModalities: ["IMAGE"], imageConfig: { aspectRatio: "16:9", imageSize: "1K" } }`.
- **This is Tier 2** of the cascade (cheap multiplier), not Tier 1 (flagship
  anchor). No Tier-1 provider (HeyGen / Imagen 3 / GPT-Image) was used or
  spent against in this task; two library entries are labeled
  `"tier": "1 flagship"` as reusable specs for a future flagship run, but
  were **not** generated here (see prompt-library.json `status: "planned"`).

This is the same model, endpoint, and credential already proven in this
repo's evidence (2026-08-20) and referenced in `docs/marketing/CHEAP_PRODUCTION_ECONOMICS_V1.md`
— this task re-verified it still works today (2026-09-04/05) and extended it
to non-16:9 aspect ratios, which no prior evidence in this repo had proven.

## How the credential was retrieved

**Not in an environment variable.** Per the mandatory capability-discovery
order for this task (see `.site-context/SITE-LEARNINGS.md`, 2026-09-05 entry,
"Four false capability-unavailable calls in one session"), the key lives in
the macOS Keychain:

```
security find-generic-password -s "FAMtastic.Gemini.Image" -a "famtastic-gemini-image-worker" -w
```

- Keychain service: `FAMtastic.Gemini.Image`
- Keychain account: `famtastic-gemini-image-worker`
- Sent as the `x-goog-api-key` request header. Never echoed to stdout, never
  written to a file, never placed in an env var by this task's scripts.
- Confirmed working with a live preflight call before spending anything:
  `GET https://generativelanguage.googleapis.com/v1/models/gemini-3.1-flash-lite-image`
  returned HTTP 200 with the model's `displayName`, `inputTokenLimit`, etc.

## Files in this directory

| File | What it is |
|---|---|
| `prompt-library.json` | Deliverable 1 — 19 reusable prompts across 8 topics (the platform-dependency series' 7 posts + Ghost Town Ep1), each with id, topic, blog_post_slug, full prompt text, negative prompt, aspect ratio, surface, and tier. |
| `plate-01..08-*.jpg` | Deliverable 2 — 8 real generated images (see Verification below). |
| `generation-receipt.json` | Full measured receipt: every attempt (8 successes + 1 blocked/retried), cost, duration, sha256, dimensions. |
| `generate-plates.mjs` | The generator script — adapted from the repo's own proven worker, extended to support a different aspect ratio per prompt. |
| `README.md` | This file. |

## Measured cost

**Total spend: $0.2688** (8 successful images x $0.0336 published per-1K-image
rate). This is a **measured** count of successful, non-refused generations at
the provider's own published per-image price, not an estimate — the Gemini
Developer API's `generateContent` response does not return a per-call dollar
amount or invoice line, so "measured" here means: every prior Gemini Flash
Lite receipt in this repo (`marketing/campaigns/and-if-it-is-rattler-lifers/evidence/`)
reports cost the same way, and the provider's own error message on the one
blocked attempt confirms blocked images are not charged ("You will not be
charged for blocked images"), which is why that attempt is excluded from the
total. Per-call token usage (`usageMetadata`) is recorded as corroborating
evidence where it was preserved (see `generation-receipt.json`'s
`backfill_note` for the one gap: a script bug overwrote the mid-run receipt
before six of the eight `usageMetadata` objects could be read back; the
image files, byte counts, durations, cost, and sha256 hashes for those six
are still exact, independently re-measured values, not estimates).

| # | id | aspect ratio | file | bytes | dimensions (measured) | cost |
|---|---|---|---|---|---|---|
| 1 | p-hero-blog | 16:9 | plate-01-pillar-hero-16x9.jpg | 551,358 | 1376x768 | $0.0336 |
| 2 | a1-hero-blog | 16:9 | plate-02-a1-two-doors-16x9.jpg | 595,692 | 1376x768 | $0.0336 |
| 3 | b1-hero-blog | 16:9 | plate-03-b1-coin-drain-16x9.jpg | 516,203 | 1376x768 | $0.0336 |
| 4 | b2-hero-blog | 16:9 | plate-04-b2-locked-ledger-16x9.jpg | 531,907 | 1376x768 | $0.0336 |
| 5 | c1-hero-blog | 16:9 | plate-05-c1-message-backlog-16x9.jpg | 445,633 | 1376x768 | $0.0336 |
| 6 | c1-shorts-vertical | 9:16 | plate-06-c1-hand-phone-vertical-9x16.jpg | 437,405 | 768x1376 | $0.0336 |
| 7 | c2-hero-blog | 16:9 | plate-07-c2-concierge-desk-16x9.jpg | 455,939 | 1376x768 | $0.0336 |
| 8 | ghost-ig-hook | 1:1 | plate-08-ghost-hook-square-1x1.jpg | 405,758 | 1024x1024 | $0.0336 |

**Total: 8 images, $0.2688.**

## Verification (each file is a real image)

Ran `file` and `shasum -a 256` against every output. All eight are valid,
non-zero, correctly-dimensioned JPEGs — none are empty, truncated, or a text
error page saved with an image extension:

```
$ file plate-01-pillar-hero-16x9.jpg
plate-01-pillar-hero-16x9.jpg: JPEG image data, JFIF standard 1.01, ... 1376x768, components 3
```

(repeated for all 8; full output and sha256 digests are in
`generation-receipt.json`, field `results[].sha256` / `results[].dimensions`).

## Dimension constraints hit

- **Requested `imageSize` is always `"1K"`.** This is the only size proven
  against this model/endpoint in this repo (the $0.0336 price is specifically
  for a 1K output; `"2K"` exists but was only ever tested against the
  different, pricier `gemini-3-pro-image` model in prior evidence, not this
  one). Not tested here — out of scope for a Tier-2 proof.
- **`imageConfig.aspectRatio` accepts plain strings, not the `ASPECT_RATIO_*`
  enum.** `"16:9"`, `"9:16"`, and `"1:1"` all worked in this run. Prior repo
  evidence had already proven `"16:9"`, `"3:2"`, and `"4:5"` (2026-08-20,
  `and-if-it-is-rattler-lifers/evidence/`) and, as of the same day as this
  task, `"9:16"` (`marketing/campaigns/cost-is-not-the-reason/images/broll/receipt.json`,
  generated ~19 minutes before this run by a concurrent agent). **`"1:1"` had
  not been proven in this repo before this task** — checked by grepping every
  `.json` file for a successful `generateContent` result at that ratio before
  writing this claim; this run's `ghost-ig-hook` plate is the first.
- **Actual pixel output for "1K" is not 1024px on every side.** 16:9 came
  back at 1376x768, 9:16 at 768x1376 (correctly the transpose, not a
  letterboxed square), and 1:1 at 1024x1024. "1K" appears to mean "roughly
  one megapixel," not "1024 on the long edge" — plan crop/safe-area margins
  accordingly rather than assuming exact dimensions from the requested label.
- **Output format is JPEG, not PNG.** Every successful response returned
  `mimeType: "image/jpeg"`, regardless of what filename extension was
  requested. `generate-plates.mjs` reads the actual returned `mimeType` and
  renames the output file to match rather than forcing a `.png` extension on
  JPEG bytes. If a hard PNG requirement exists downstream (e.g. for
  transparency), add a lossless re-encode step — the proven, already-working
  path in this repo for that is the Photoshop bridge's
  `ExportType.SAVEFORWEB` (`docs/marketing/CHEAP_PRODUCTION_ECONOMICS_V1.md`,
  "No PNG optimizer installed" entry), not a naive `saveAs`, which produced a
  measured 130x size bloat there.

## What failed and why

One of the nine total attempts was blocked, not a hard error:

- **`p-hero-blog`, attempt 1** — HTTP 200, but `finishReason: "IMAGE_SAFETY"`.
  The provider's own response: *"the image was filtered out because it
  violated Google's Generative AI Prohibited Use policy... You will not be
  charged for blocked images."* The original prompt included "one small
  unidentifiable silhouette standing just inside the doorway, not facing
  camera" in an isolated nighttime setting — plausibly read by the safety
  classifier as a vulnerable-person/isolation scenario, even though no other
  prompt in this library describing a person (including one with an explicit
  human silhouette, `ghost-ig-hook`) was blocked. **Fix**: removed the person
  entirely and made it a pure architectural establishing shot; attempt 2
  succeeded immediately with no other change. `prompt-library.json` was
  updated in place to the working wording, so the library reflects what
  actually generates rather than what was first tried. **Not charged** —
  confirmed by the provider, and excluded from `total_cost_usd`.
- A separate **tooling bug**, not a provider failure: `generate-plates.mjs`
  originally overwrote `generation-receipt.json` on every run rather than
  merging. Running it once for 7 prompts, then again for 1 retry, silently
  destroyed the first run's per-call `usageMetadata` before it was ever read.
  The image files and their byte counts/hashes were unaffected (they're
  separate files, written before the receipt), so nothing about the actual
  8 images is in question — but the receipt for 6 of the 8 no longer carries
  provider token-usage detail. Fixed going forward in `generate-plates.mjs`
  (merges prior receipt results by `id` now); the historical gap is disclosed
  in `generation-receipt.json`'s `backfill_note` rather than silently patched
  over.

## Exact command to regenerate

```bash
cd marketing/creative/plates

# Optional: verify the credential still works before spending anything
node -e "
const {execFileSync} = require('node:child_process');
const key = execFileSync('/usr/bin/security', ['find-generic-password','-s','FAMtastic.Gemini.Image','-a','famtastic-gemini-image-worker','-w'], {encoding:'utf8'}).trim();
fetch('https://generativelanguage.googleapis.com/v1/models/gemini-3.1-flash-lite-image', {headers:{'x-goog-api-key':key}}).then(r=>r.json()).then(console.log);
"

# Regenerate exactly the 8 plates this task produced (reads status:"generated" from prompt-library.json)
node generate-plates.mjs --max-cost-usd 1.00

# Or generate any specific subset by id (comma-separated), e.g. the two
# unproven Tier-1 flagship specs or any "planned" Tier-2 entry:
node generate-plates.mjs --ids p-thumb-youtube,p-square-ig --max-cost-usd 1.00
```

`--max-cost-usd` is a hard ceiling the script refuses to exceed
(`requested_count x $0.0336` must be `<= --max-cost-usd`, checked before any
network call). Output files land in this directory; `generation-receipt.json`
is merged (not overwritten) across runs.

## Capability the registry does not list

`marketing/providers.json` (the provider registry of record per this repo's
CLAUDE.md) had **no entry at all** for Gemini/Imagen image generation at the
start of this task. It listed `openai_image`, `adobe_firefly_*`, `muapi`,
`heygen`, `poe`, and others, but nothing named `gemini_image` or similar —
despite this being the documented, working Tier-2 provider in
`docs/architecture/CAMPAIGN_ASSET_CASCADE_AND_DISTRIBUTION_V1.md` and
`docs/marketing/CHEAP_PRODUCTION_ECONOMICS_V1.md`, with a keychain credential
already provisioned and prior working evidence in this exact repo. This is
the same gap `.site-context/SITE-LEARNINGS.md`'s 2026-09-05 entry names as
"Google Imagen 3 / Gemini Flash Lite — key in keychain
(`FAMtastic.Gemini.Image`)" being missing from the registry that agents are
told to check first.

**Fixed as part of this task**, not just flagged: added a `gemini_image`
entry to `marketing/providers.json` (`status: "connected_local_keychain"`,
matching the `muapi` entry's shape) with the exact endpoint/model id, the
keychain service/account, the proven aspect ratios, and a pointer back to
this directory, so the next agent finds it on the first read instead of
rediscovering it from evidence folders.

## What this does not cover

- No Build DNA record (`docs/architecture/BUILD_DNA_STANDARD_V1.md`) was
  created for this run. That standard is scoped to creative proofs, selected-
  direction refinements, and Site Studio-bound builds; this task is an
  internal capability-proving exercise for an engineering/marketing prompt
  library, not a customer-facing creative proof or campaign asset going out
  the door. If any of these 8 plates is later selected for an actual
  publish (a blog hero, a scheduled social post), that publish should get its
  own Build DNA record at that time, citing this receipt as the plate's
  provenance.
- Compositing the plates with Photoshop-set type (the actual point of the
  negative-space/no-baked-text constraint) was not done in this task — these
  are unfinished plates, exactly as designed. The proven path for that step
  is the Photoshop MCP bridge (`mcp__photoshop-bridge__*`), already used for
  `marketing/creative/adobe-proofs/` in this same working tree.
- The 11 `"planned"` library entries (including both `"tier": "1 flagship"`
  entries) were written and are ready to run, but were not generated —
  Deliverable 2 only required 8, and spending further against a Tier-1
  provider was out of scope for a Tier-2 proof task.
