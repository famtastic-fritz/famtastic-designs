# FAMtastic marketing workspace

This directory holds machine-readable campaign manifests and local automation.
It does not hold API keys, OAuth tokens, customer lists, or downloaded provider
credentials.

Run:

```bash
./scripts/marketing-preflight.sh
python3 scripts/generate-17-day-marketing-manifest.py
python3 scripts/campaign-readiness.py
```

The generated manifest is draft-only. A record must reach `approved` before a
scheduler may submit it, and real publishing remains an explicit authorization
gate. See `docs/marketing/FAMTASTIC_MARKETING_FLOW_2026-08-12.md`.

Reusable schemas incubate in `marketing/engine`; FAMtastic-specific brand and
campaign records remain outside that boundary. See
`docs/architecture/MARKETING_ENGINE_INCUBATION_AND_EXTRACTION.md`.

Adobe-enabled creative-production scenarios and their repeatable proof live in
`marketing/adobe-pipeline`. Run:

```bash
python3 scripts/prove-adobe-marketing-pipeline.py
```

See `docs/marketing/ADOBE_CREATIVE_PIPELINE_PROOF_2026-08-13.md` for the
production boundary, three use cases, and evidence classifications.
