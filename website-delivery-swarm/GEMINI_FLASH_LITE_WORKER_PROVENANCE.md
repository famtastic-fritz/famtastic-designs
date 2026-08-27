# Gemini Flash Lite worker provenance

`gemini_flash_lite_image_worker.mjs` is an intentionally narrow import of the
previous authenticated FAMtastic public-preview image worker.

| Field | Recorded value |
| --- | --- |
| Source repository | `sites/site-famtastic-designs` |
| Source branch | `codex/public-preview-campaign` |
| Source commit | `0e3366a2b181a5a7570f0ef2ce2de72704b0a275` |
| Source path | `website-delivery-swarm/gemini_flash_lite_image_worker.mjs` |
| Source Git blob | `ed9cc945fb528a67e8d8c03a630f3c71f6834ae2` |
| Source SHA-256 | `ed539310bc389f22aa47928a10dbae9a4422bd9a9a4ffaf5b6e8655c8c225d8a` |
| Proven route | Gemini Developer API `generateContent`, model `gemini-3.1-flash-lite-image` |

## Scoped bridge changes

The imported worker retains the original Keychain-backed `--preflight` and
`--execute` route for a separately authorized future image run. It now adds a
local-only contract surface for the verified-cold Beauty / Hair / Braiding
cohort:

- prompt validation uses `prompt.trim()` only to reject whitespace-only text;
  API payload and SHA-256 use the original exact UTF-8 prompt bytes;
- input rejects a missing or duplicate `direction_id` / filename and requires
  the declared full a/b/c set when `expected_directions` is provided;
- provider responses and receipts require a complete one-artifact-per-prompt
  set with non-empty usage evidence, exact prompt hash, filename, direction,
  image MIME, byte count, SHA-256, and positive duration;
- `--validate-input` and `--validate-receipt` never read the macOS Keychain,
  call Gemini, write image output, contact Drupal or Site Studio, deploy,
  schedule, or send mail.

The companion adapter is
`cohorts/beauty-hair-braiding/prepare-gemini-flash-lite-worker-input.mjs`. It
does not invoke the worker. It only makes the exact prompt/filename handoff
after the canonical runtime binding is complete.

## Evidence boundary

The local contract test
`cohorts/beauty-hair-braiding/test-gemini-flash-lite-cohort-bridge.mjs` uses
only synthetic images and a finalizer dry-run. It proves byte preservation and
the finalizer's source-prompt SHA check. It is not Gemini execution evidence,
an entitlement check, a paid-provider call, a deployed proof, or email proof.
