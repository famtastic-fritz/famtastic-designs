# FAMtastic creative asset graph

The asset graph is the evidence-first execution layer for creative work. It is
not tied to marketing, a blog, a renderer, or a single provider. A job starts
from one or more declared inputs and creates hash-linked asset nodes. Any
accepted node may become an input to another job.

Examples of valid inputs include an approved brief, article, brand guide,
research note, design guide, image, audio clip, video, or an earlier asset
node. The input type changes the adapter, not the proof contract.

## Working modes

- **Experiment (default):** compare one premium benchmark with at least two
  lower-cost candidates. The job remains experimental until a human accepts a
  task-specific comparison.
- **Promoted route:** a previously accepted route may be reused, but each run
  still records its resolved provider/model, inputs, outputs, cost, and QA.

The current budgets for an unproven comparison are USD 5 for still/copy
families and USD 25 for video families. A job must stop and report when it
reaches its declared cap; a fallback may not silently lower the required
quality or review standard.

## Provider discovery

Before a job chooses candidates, inspect the live registry rather than relying
on a remembered subscription or assumed API. This prints safe capability/status
metadata only; it never prints credentials or proves that a conditional route
is currently authenticated.

```sh
node marketing/creative/asset-graph/list-provider-routes.mjs --family video
node marketing/creative/asset-graph/list-provider-routes.mjs --family still
node marketing/creative/asset-graph/list-provider-routes.mjs --family copy
```

The catalog explicitly includes connected, conditional, manual-assisted, and
unproven routes. MoneyPrinterTurbo is presently an unproven local video
candidate: historical claims exist, but this checkout has no helper/runtime or
current receipt. A discovery listing is therefore a routing input, never a
capability upgrade.

## Contracts

1. [`creative-job.v1`](./schemas/creative-job.schema.json) declares the
   operator-supplied brief, source inputs, requested output, candidate plan,
   budget, and authority gates.
2. [`asset-node.v1`](./schemas/asset-node.schema.json) records each generated
   or deterministic output with its direct input ids and hash-backed receipt.
3. [`experiment-report.v1`](./schemas/experiment-report.schema.json) compares
   the premium baseline and lower-cost candidates against one task-specific QA
   rubric and records the human decision.

Use the validator before a run:

```sh
node marketing/creative/asset-graph/validate-creative-job.mjs path/to/job.json
node marketing/creative/asset-graph/validate-creative-job.mjs path/to/job.json --require-run-ready
node marketing/creative/asset-graph/validate-asset-graph.mjs path/to/job.json path/to/node.json ... --report path/to/report.json
node marketing/creative/asset-graph/preflight-asset-spend.mjs path/to/job.json path/to/node.json ... --next-cost-usd 0.25
```

The second command is intentionally stricter: it requires spend authority,
the appropriate family cap, a premium benchmark, and at least two cheap
candidates. It does not generate anything.

Run the spend preflight immediately before each paid provider call. It totals
recorded node costs and blocks a call whose declared cost would exceed the
job's approved budget.

## Optional storyboard adapter

A storyboard is an optional visual-treatment node for a video job. It must be
created from the human brief and reviewed as an experiment; no article, source
file, or title is automatically converted into a prescribed story. The legacy
`prompt-cookbook/campaign-story.v1` validator remains available only to review
an explicitly supplied treatment. It does not authorize spend, select a
provider, or trigger rendering.

## Required receipts and lineage

Every provider or deterministic step records the literal input/prompt or input
hash, resolved provider/model, settings, output location/hash, measured or
declared cost, failure/fallback reason, and human QA decision. A derivative
lists the exact earlier asset node ids it consumed. Rejected nodes remain in
the experiment report so the team can learn from failure instead of repeating
it.
