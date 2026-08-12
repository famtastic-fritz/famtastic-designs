# Local model and agent routing

Date: 2026-08-12

This is the shared model policy for Shay, Claude, Codex, and local runners on
the 16 GB Apple Silicon workstation.

## Truth about Kimi K3

The official Kimi K3 release is a 2.8-trillion-total-parameter MoE with about
104 billion activated parameters per token, not 32 billion. Its official Ollama
entry exposes only `kimi-k3:cloud`; Ollama states that it currently requires a
Pro or Max subscription and consumes extra usage credits. The command looks
local because it uses the Ollama CLI, but prompts and inference leave the Mac.

Disk streaming or extreme offload demonstrations do not make Kimi K3 a useful
local agent for FAMtastic. They require enormous storage and trade interactive
latency for novelty. Do not install or advertise those experiments as working
local capability.

## GLM is included, with the right size

GLM must remain in the comparison pool. The current frontier GLM models are
also too large for this machine: GLM-4.5-Air has 106B total/12B active
parameters, while the local Ollama GLM-4.7-Flash Q4 artifact is about 19 GB.
The practical local GLM lane is `glm4:9b` at about 5.5 GB. It is especially
useful as a multilingual and second-opinion challenger to Qwen.

## Installed routing

- `qwen3:8b`: primary local text drafter, caption variant generator, outline
  builder, and classifier.
- `glm4:9b`: independent local challenger and multilingual copy reviewer.
- `gemma3:4b`: local image-aware reviewer for composition descriptions,
  screenshot observations, and alt-text drafts.
- Poe: optional hosted escalation and cross-model comparison using the existing
  subscription points. Never commit its key.
- Codex/Claude: complex orchestration, evidence review, code, and final judgment
  within their authorized workflows.

Model routing is defined in `marketing/local-models.json`. A model is not an
agent: it receives a bounded role, evidence, output schema, and approval limit.

## Shay's operating contract

Shay may use local models to draft, summarize, classify, compare variants, and
flag suspected issues. Shay must read `AGENTS.md`, the marketing flow, the
product-marketing context, the capability registry, and the demand doctrine
before campaign work. Shay must never allow a local model to:

- approve its own factual claims or invented statistics;
- change product scope, pricing, renewals, or legal language;
- send promotional email or publish to a real social account;
- receive customer PII, OAuth tokens, API keys, or payment data;
- claim a `:cloud` model was executed locally;
- overwrite the canonical campaign manifest with an untracked workflow.

For each material content item, Shay should compare the primary model against
the named challenger only when disagreement is informative. Escalate disputed
claims to repository evidence and an authorized agent/human; do not decide by
majority vote between models.

## Memory policy

Do not download a model merely because its weight file is smaller than 16 GB.
The OS, runtime, KV cache, context, browser, media tools, and agent all need
memory. The default working ceiling is approximately 8–9 GB of model weights.
Larger candidates require a timed benchmark covering memory pressure, tokens
per second, context size, output quality, and coexistence with the real task.

## Cloud disclosure

Every provider invocation is recorded as one of:

- `local`: inference performed on the workstation;
- `cloud_direct`: direct vendor/API inference;
- `cloud_via_local_cli`: cloud inference invoked by a local tool such as
  Ollama;
- `hybrid`: local preparation plus cloud generation/review.

This prevents cost, confidentiality, and proof claims from becoming ambiguous.

