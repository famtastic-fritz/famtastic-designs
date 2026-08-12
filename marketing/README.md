# FAMtastic marketing workspace

This directory holds machine-readable campaign manifests and local automation.
It does not hold API keys, OAuth tokens, customer lists, or downloaded provider
credentials.

Run:

```bash
./scripts/marketing-preflight.sh
python3 scripts/generate-17-day-marketing-manifest.py
```

The generated manifest is draft-only. A record must reach `approved` before a
scheduler may submit it, and real publishing remains an explicit authorization
gate. See `docs/marketing/FAMTASTIC_MARKETING_FLOW_2026-08-12.md`.

