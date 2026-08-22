# Gemini Interactions FAMU-reference benchmark — 2026-08-22

## Result

**PASS — provider route and stateful image continuation.** This was a local,
no-customer/no-production benchmark, not a customer proof or an independent
creative-release approval.

| Interaction | Model | Output | Duration | Result |
| --- | --- | --- | ---: | --- |
| `v1_ChdfektKYXViUEp0U3pxdHNQdE02bzhBbxIXX3pLSmF1YlBKdFN6cXRzUHRNNm84QW8` | `gemini-3.1-flash-lite-image` | 1376×768 JPEG, reference-led 16:9 | 6.096 s | Passed |
| `v1_ChdfektKYXViUEp0U3pxdHNQdE02bzhBbxIXQlRPSmFwaVlJc3lKcXRzUDhzN0M2UXM` | `gemini-3.1-flash-lite-image` | 768×1376 JPEG, stateful 9:16 companion | 5.028 s | Passed |

The reference was the existing unofficial Rattler Lifers visual canon, SHA-256
`55a4cbd0099bb17c21179cdbb2d28b405c300eccf5f7205ea46ab31e2f39e34f`.
Neither prompt asked for FAMU marks, affiliations, copied people, or copied
pixels. Both outputs are original, unbranded, FAMU-adjacent fan-culture art.

## What this proves

- The Gemini **Developer API** `v1beta/interactions` endpoint accepts an image
  reference and the existing image-only Keychain credential.
- `gemini-3.1-flash-lite-image` returned a new image with usage metadata and a
  stable interaction ID.
- `previous_interaction_id` created a second, distinct image in the same visual
  world without resending the visual canon.
- The Build DNA validator accepted the two stage records and four artifact
  checksums.

## What it does not prove

- Gemini Enterprise Agent Platform (GEAP), a provisioned Google agent, or
  Antigravity desktop automation.
- An invoice-level provider cost, independent visual-review pass, customer
  ownership/portal delivery, Site Studio build, email, payment, or production
  publishing.

## Retrieval and rerun

- Source runner:
  `website-delivery-swarm/benchmarks/gemini-interactions-famu-reference/run.mjs`
- Local Build DNA:
  `artifacts/gemini-interactions-famu-reference-20260822/build-dna.json`
- Local receipt and original outputs:
  `artifacts/gemini-interactions-famu-reference-20260822/`

The source runner reads the key only from macOS Keychain service
`FAMtastic.Gemini.Image` / account `famtastic-gemini-image-worker`; no secret
is committed. The artifacts stay ignored because this is a provider benchmark,
not yet an approved portfolio/campaign baseline.
