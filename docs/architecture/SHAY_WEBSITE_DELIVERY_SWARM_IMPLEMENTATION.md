# Shay website-delivery swarm implementation

## Proven spine

The repository now contains a callable `website.preview.v2` reference engine.
It assembles one `website_build_brief.v2`, runs bounded specialists, applies
canonical package and add-on rules, creates three direction contracts, invokes
independent QA, renders a simulated FAMtastic Operations preview view, captures
three Playwright screenshots, and writes correlated machine-readable evidence.

Run it with Node 22:

```bash
./scripts/run-website-delivery-swarm.sh
```

The installed repository skill is `agent-skills/run-website-delivery-swarm`.
Shay can call the same shell primitive instead of reimplementing the routine.

## Current specialist proof

The first acceptance swarm invokes intake audit, industry research planning,
solution architecture, add-on analysis, creative direction, and independent QA.
It also invokes Maya, the human-experience tester, in either neutral control mode
or a disclosed opt-in Life Path creative lens. The tester evaluates clarity,
trust, control, accessibility, continuity, emotional response, and idea fit while
leaving package, price, risk, legal, accessibility, and approval logic unchanged.
The agent registry also records the intended prototype-builder route. Fixture
mode is deterministic: it proves orchestration, gates, traceability, commercial
reasoning, and screenshot generation without claiming that a model performed
live research.

## Provider reality

Ollama with qwen3:8b, glm4:9b, and gemma3:4b is available locally. Codex,
Claude, Gemini/Antigravity, and Kimi CLIs are installed but their unattended
authentication and external-worker rights remain unproven. Poe and Z.ai are not
currently callable locally. A subscription is never treated as API authority.

The pass/fail authority remains schemas, deterministic rules, Playwright, and
independent assertions. Model output is advisory until it passes the same
contract. Missing providers are `skipped` or `gated`, never silently simulated.

## Scale-out seam

Extract the provider-neutral engine into a stateless worker service only after
the local routine is stable. The remote service should accept an immutable
brief plus routine version, write artifacts to object storage, publish progress
events to a queue, and return a signed callback containing artifact references
and checksums. Drupal remains the system of record; workers receive scoped,
short-lived credentials and cannot set prices, approve contracts, purchase
domains, charge customers, or publish without the existing gates.

Use the FAMtastic proof-runner callback and artifact-slot contract for preview
generation. The retired signed Site Studio dispatch/callback and exact-three
import fixtures are historical compatibility evidence only. Extend build
telemetry to one row per specialist attempt and retain request ID,
provider/model, execution class, versions, hashes, timing, fallback, schema
result, QA result, and approval state.

## Not yet proven

- Live business, industry, competitor, or domain research.
- Automated cloud-provider authentication, quotas, costs, or fallback.
- Persistence of the enriched brief and per-agent ledger in Drupal.
- A compatible FAMtastic-controlled provider callback and artifact-slot
  persistence for a real preview run.
- A separate selected-build packet handoff and real Site Studio build-success
  return.
- A hosted queue, worker, artifact store, secret broker, or autoscaling policy.
- Customer acceptance, production deployment, or legal/commercial approval.
