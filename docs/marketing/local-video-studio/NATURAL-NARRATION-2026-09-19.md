# Local female voice auditions and narration pacing — September 19, 2026

The owner liked the V2 ad and asked for a local female voice (the word “gemal” was interpreted provisionally as “female”), an audit of existing voice APIs, and more realistic pauses. This adds two stock-voice auditions and a reusable narration skill. It does not claim that either voice has passed the owner’s listening review or replace the chosen full-ad narrator.

## Local result

Heart (`af_heart`) and Bella (`af_bella`) use the same exact excerpt from the owner’s V2 script. Both use cached Kokoro-ONNX 0.6.1 and the hash-pinned Kokoro 82M model. They retain the `fæmtˈæstɪk` phonetic override for **fam-TAS-tik**, while written text retains FAMtastic. No personal voice clone, model download or paid provider request was used.

The plan uses brisk 1.08 speed for connective copy and 1.02–1.04 for the question and closing. Explicit inter-line rests vary from 180–450 ms; internal phrase rests are 160–280 ms. These are inserted PCM silence, in addition to any silence already produced by the model. A fixed 180 ms value in the campaign is the fallback, not the effective performance schedule. The audition omits the middle of the full script deliberately; its source is documented in the campaign brief.

| Voice | Initial audio duration | Initial synthesis time | AAC loudness / true peak |
| --- | ---: | ---: | --- |
| Heart | 22.516667 s | 7.221 s | −16.2 LUFS / −1.9 dBTP |
| Bella | 21.514 s | 6.179 s | −16.3 LUFS / −1.8 dBTP |

Local runtime observation: Voicebox answers HTTP 200/healthy and lists Kokoro as loaded/downloaded, with 11 American English female presets. Its Qwen, LuxTTS, Chatterbox and TADA entries are not downloaded. The audition runner uses the existing ONNX CPU runtime directly for its explicit brand phonemes and deterministic pause assembly; the service reporting MPS/MLX does not mean these auditions used GPU inference.

## Reusable capability

`scripts/famtastic-local-narration.py --performance <json>` accepts a script-hash-bound performance plan. Phrase text must reconstruct each source line without rewriting it; speeds and pauses must be finite and bounded. Source-line and phrase cues come from actual sample counts, with silence outside spoken phrase bounds. The existing walking-continuation assembly intentionally requires its fixed 180 ms source-line contract; a variable-line-gap plan must not be passed to that assembler without an explicit contract revision. V2’s project builder accepts measured nonoverlapping line cues.

The discoverable `$natural-narration` skill is installed at `~/.codex/skills/natural-narration` and mirrored in `.agents/skills/natural-narration`. Both copies pass the bundled skill validator using the already installed Voicebox Python environment. Guidance distinguishes fast delivery from deliberate thought pauses, keeps pronunciation out of canonical captions, and requires listening review before claiming naturalness or pronunciation acceptance.

An independent source review identified a pre-existing reread race in narration input snapshots and missing artifact registration when generation failed. New-run repairs and regression evidence are retained separately from the initial successful stable-file auditions. Historical audio and frozen runner files remain unchanged.

## Existing API options

- **HeyGen: account and catalog connection proved; speech generation not invoked.** Authenticated read-only calls reported 461 web-plan premium credits and 100 English female Starfish voices on the first catalog page, with further pages available. The connected speech tool supports SSML break tags. MCP generation uses web-plan premium credits; direct API billing is a separate balance. The published direct API Starfish price is $0.12/minute (2 API credits/minute), which is not evidence of the exact MCP charge. [HeyGen pricing](https://help.heygen.com/en/articles/10060327-heygen-api-pricing-explained).
- **Gemini: documented option; current TTS access unproved.** Its speech API supports style/pace direction and pause tags. The existing Keychain credential is registered for image work; a secret retrieval attempt timed out and no model-list or speech request completed. The documented free tier has product-improvement data-use terms. [Official speech guide](https://ai.google.dev/gemini-api/docs/generate-content/speech-generation), [pricing](https://ai.google.dev/gemini-api/docs/pricing).
- **OpenAI: documented option; current TTS access unproved.** `gpt-4o-mini-tts` supports voice instructions and speed; its documented Free tier is unsupported. The existing image credential is not proof of speech permission or billing. [Official model documentation](https://developers.openai.com/api/docs/models/gpt-4o-mini-tts).

No paid synthesis, new account, API purchase or provider fallback occurred. Cached local synthesis incurs zero provider fees; electricity and hardware cost are unmeasured.

## Evidence and delivery

Evidence root: `artifacts/video-studio/natural-narration-20260919/`. Separate `heart` and `bella` ledgers retain the initial audio, exact inputs, phrase cues and measurements. The API audit has sanitized configuration, connection and official-source evidence. Human listening acceptance remains pending; successful playback and transcription cannot substitute for it.

The existing `/video-review/` route is being extended with two native audio controls, a matching transcript and mutual exclusion across audio and video players. New owner notice key: `natural-narration-auditions-20260919-v1`; exact recipient `fritz.medine@gmail.com`; existing standard/v2 renderer. Production and inbox receipts will be appended after verification.

## Validation before publication

- Initial complete Video Studio suite: **112 tests passed** in 13.109 seconds; pause and brand-focused tests: **11/11**.
- Canonical logo equality and **5 creator-credit contracts** passed; owner notice PHP syntax passed.
- Production frontend build passed. CUA at **390 and 320 px** observed no horizontal overflow; both audio clips completed unmuted, without media errors, and starting Bella paused Heart. Transcript and public asset hashes match the source auditions.
- Both initial narration ledgers pass the canonical validator with 3 stages and 39 artifact checksums each.
- A mistakenly selected legacy content browser test stopped at connection refusal to its unrelated default localhost:4187, before page assertions. This is retained as an unsuccessful invocation, not counted as a pass. The current page’s browser verification uses CUA.

### Final regeneration

The repaired runner regenerated fresh `heart-v2` and `bella-v2` runs in **7.114 s** and **6.122 s** respectively. Their AAC outputs are byte-identical to the browser-tested auditions and the staged public files. Each has 10 measured source-line cues and 14 phrase cues; every line gap matches its explicit sample-derived cue value. The final complete suite passes **116 tests in 13.222 s**, including four new snapshot/failure regressions; the focused suite passes 15/15.

- Heart AAC SHA-256: `52adde6f86e15c8b52f4c56fdffb1b5de0a24c4dadab5fa96b20bc24bbcacc15`.
- Bella AAC SHA-256: `092b6764805101df9a2453471bbf5e2054bfe2bb2f080401168ee2c57fb45f86`.

The final source-bound runs retain their own immutable script, performance, input manifest and runner/module snapshots. Failed-run cleanup regressions verify partial artifact retention and preservation of the original failure. This proves the revised runner’s technical behavior, not owner listening acceptance.
