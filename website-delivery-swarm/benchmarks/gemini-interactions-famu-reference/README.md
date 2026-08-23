# Gemini Interactions FAMU-reference benchmark

This is a provider-route benchmark, not a customer proof or Site Studio build.
It tests one FAMU-adjacent, unofficial visual canon through the Gemini
Developer API `interactions` endpoint:

1. a new, reference-led 16:9 Flash Lite Image output;
2. a stateful 9:16 companion revision using `previous_interaction_id`.

The benchmark reads the existing image-only Google API key from the macOS
Keychain; it never stores or prints that key. It writes generated outputs, a
complete request/response receipt, and valid `famtastic.build-dna.v1` evidence
under ignored `artifacts/` storage. The evidence captures prompts, interaction
IDs, provider usage when returned, durations, output hashes, and the
no-customer/no-production boundary.

Run from the repository root:

```bash
node website-delivery-swarm/benchmarks/gemini-interactions-famu-reference/run.mjs
```

This source uses raw REST intentionally. The separately installed Google
`gemini-interactions-api` skill targets Gemini Enterprise Agent Platform
(GEAP), which requires a provisioned agent and Application Default Credentials.
This FAMtastic benchmark uses the Gemini Developer API and its existing
image-only Keychain credential. Do not claim that one credential type proves
the other.
