# Shay website-delivery swarm

## Status

Architecture direction recorded 2026-08-17. This document defines the target
specialist-agent system for website discovery, research, commercial analysis,
proof generation, proposal preparation, contract drafting, build handoff, QA,
and governed learning. It is not evidence that every agent, provider, or route
is implemented or production-proven.

## Decision

Shay is the durable orchestrator, not the source of business truth and not the
single model that performs every task. Drupal owns the customer, request,
product, Commerce, approval, and evidence records. Shay starts a versioned
routine, invokes bounded specialist agents, enforces dependencies and gates,
routes eligible models through provider-neutral task contracts, and writes
validated results back to the same Drupal request.

A model is not an agent. An agent is a versioned role with an input schema,
output schema, evidence rules, approval limits, evaluation cases, repair rules,
and eligible model capabilities. Models remain interchangeable behind that
contract.

## Primary routines

```text
website.preview.v2   — anonymous or account-owned pre-sale research and three proofs
website.proposal.v2  — recommendation, add-ons, risks, contract, and priced choices
website.build.v2     — purchased-scope implementation and release candidate
website.repair.v2    — bounded repair from failed QA gates
website.learn.v1     — candidate lessons, replay evaluation, and controlled promotion
```

The first three routines must consume one canonical `website_build_brief.v2`
snapshot. They may run at different completeness levels, but may not invent a
parallel intake format.

## Canonical flow

```text
Drupal request
→ intake audit
→ parallel research
→ evidence synthesis
→ solution architecture
→ package and add-on analysis
→ scope, risk, contract, and deliverables
→ content, information architecture, and creative direction
→ prototype/build
→ independent QA swarm
→ release gate
→ Drupal proof, proposal, question, approval, or project record
```

Anonymous prospects remain prospects while evaluating a proof. Account creation
claims the same request and proofs when the person wants a durable workspace,
files/messages, a private offer, purchase, or delivery tracking. The handoff
must not create a duplicate request.

## `website_build_brief.v2`

The versioned snapshot contains:

### Identity and provenance

- Request, prospect, customer, organization, intake, project, and order IDs as applicable.
- Source lane: Solution Finder or customer portal.
- Snapshot, schema, prompt, routine, skill, and policy versions.
- Created time, source checksums, privacy class, and approved processing scope.

### Business and goals

- Name, industry, location, service area, current business model, products, and services.
- Ideal customers, customer problems, desired outcomes, visitor actions, and success measures.
- Current acquisition, booking, purchasing, payment, delivery, and follow-up process.
- Timing, decision-makers, budget context, and customer-provided constraints.

### Current technology and ownership

- Existing site, domain, registrar, DNS, hosting, CMS, SSL, email, analytics, integrations, repositories, and agency relationships.
- Ownership and access status without passwords or secrets.
- Desired domains, acceptable alternatives, domain fallback instructions, mailboxes, and forwarding requirements.

### Scope, content, and brand

- Project type, page count, pages/sections, features, integrations, ecommerce, booking, AI, automation, accessibility, legal, maintenance, and unlisted requirements.
- Content readiness, copywriting needs, photos, assets, ownership confirmations, logo/brand status, colors, and style.
- Reference sites plus the reasons to borrow or avoid particular qualities.

### Research and commercial decisions

- Customer research questions, verified findings, sources, access times, contradictions, assumptions, prohibited claims, and items requiring confirmation.
- Recommended package, deterministic reasons, required/recommended/future add-ons, custom-review state, exclusions, private-offer state, and immutable purchased scope when applicable.

### Production and acceptance

- Preview, proposal, or paid-build mode; requested directions; deliverables; revisions; approvals; acceptance criteria; callback; and evidence requirements.

Each generation uses an immutable snapshot. Agents do not query changing tables
independently and guess which values are authoritative.

## Specialist agents

### Intake Auditor

Determines whether the request is ready for research, mockup, quote, or build.
Returns missing information, contradictions, safe assumptions, customer
questions, privacy classification, and allowed next actions.

### Business Verification Agent

Verifies public business facts while preserving customer-provided facts as a
separate source. Every finding returns its source, retrieval time, confidence,
agreement state, and need for customer confirmation.

### Industry Research Agent

Researches buyer behavior, terminology, common questions, expected trust
signals, seasonal/geographic factors, accessibility/privacy/licensing concerns,
and claims that require evidence.

### Competitor Research Agent

Finds direct and adjacent competitors, recurring presentation patterns,
conversion paths, weaknesses, differentiation opportunities, and features to
consider. It records inspiration boundaries and may not copy protected work.

### Domain and Technology Agent

Checks domain availability and premium status, existing registrar/DNS/hosting/
SSL/email/CMS/analytics/integrations/repositories, migration risks, and ownership
gaps. It may report findings but may not purchase, transfer, or mutate provider
state without authorization.

### Reference-Site Analyst

Converts liked/disliked examples into layout, navigation, tone, typography,
content-density, CTA, feature, mobile, accessibility, and do-not-copy findings.

### Research Synthesizer

Produces one evidence-controlled snapshot separating customer statements,
verified facts, unverified claims, inferences, contradictions, safe assumptions,
prohibited assumptions, and customer questions. Source quality controls conflict
resolution; model voting does not.

### Solution Architect

Defines the useful system: site type, information architecture, conversion
paths, pages/sections, functionality, integrations, content, domain/email,
accessibility/privacy, dependencies, and custom engineering. It may conclude
that a website alone does not solve the request.

### Package Recommender

Compares the architecture to Drupal's canonical product definitions. Returns
the smallest useful package, why smaller/larger choices do not fit, whether
direct checkout is safe, and whether custom review is required. Models never
invent products, prices, scope, renewals, or discounts.

### Add-on and Upsell Analyst

Classifies each opportunity as required, recommended, future, or custom. Every
recommendation includes its SKU when configured, trigger evidence, customer
outcome, mockup treatment, available proof, price source, and whether it may be
declined. Likely families include brand, copywriting, pages, business email,
scheduling, ecommerce discovery, lead automation, AI agent, local SEO,
analytics, maintenance, and post-included-year hosting.

### Scope and Risk Analyst

Checks scope, dependencies, content readiness, unknowns, provider costs,
migrations, regulatory concerns, unrealistic timing, unsupported promises,
discovery needs, and acceptance-test feasibility.

### Contract and Deliverables Agent

Drafts outcomes, deliverables, inclusions, exclusions, responsibilities,
required access/assets, milestones, revisions, approvals, acceptance criteria,
third-party services, one-time/recurring/usage costs, ownership, cancellation,
refund, support, and change-order terms. Final legal and commercial commitments
remain approval-gated.

### Information Architect

Produces sitemap, page purposes, section order, navigation, user journeys,
calls to action, conversion paths, content requirements, and structured-data
opportunities.

### Content Strategist

Produces message hierarchy, page outlines, draft copy, FAQs, trust content,
CTA language, SEO intent, claims ledger, and missing-content questions. It labels
customer statements, verified facts, draft marketing language, and claims
requiring approval.

### Creative Director

Defines three genuinely different direction contracts including strategic
concept, emotional response, palette, typography, layout, imagery, motion,
content density, CTA treatment, mobile rules, accessibility, and differentiators.

### Prototype Builder

Produces portable HTML/React proofs or paid implementation, screenshots,
responsive variants, approved media, design DNA, repository/commit references,
and a build manifest. The selected implementation model is replaceable.

### Independent QA swarm

Separate agents validate facts/claims, scope/Commerce, contracts/deliverables,
accessibility, mobile/visual quality, functionality, SEO, security/privacy, and
evidence completeness. No generator approves its own output. The release gate
uses required assertions, not majority opinion.

## Model and provider routing

Every task declares required capabilities, privacy class, context, tool needs,
cost ceiling, timeout, retry policy, fallback permission, schema validator, and
human-approval requirement. The agent contract never hardcodes a vendor.

Current grounded local lanes:

- `qwen3:8b`: routine extraction, normalization, classification, outlines, and drafts.
- `glm4:9b`: independent challenger and multilingual review.
- `gemma3:4b`: screenshot observation, composition review, and alt-text drafts.
- Poe: optional cloud escalation only when its runtime key and points are available.
- Codex and Claude: complex authorized repository, synthesis, implementation, and evidence work.

Gemini/Antigravity, Z.ai/hosted GLM, Kimi, and any other subscription are
candidates, not established automation routes. Consumer subscriptions do not
prove API or CLI automation rights. Each route requires registry entry,
connectivity proof, privacy/cost classification, tool proof, and evaluation
before Shay may depend on it. Ollama `:cloud` routes must be recorded as cloud
via local CLI, never local inference.

Each route has a primary, challenger, ordered fallbacks, degraded local mode,
timeouts, attempts, cost ceiling, and circuit breaker. Fallback providers
receive the same versioned input and must satisfy the same output schema.

## Agent package contract

```text
agent-name/
  SKILL.md
  input.schema.json
  output.schema.json
  policy.json
  prompts/
  evaluation-cases/
  fixtures/
  repair-rules/
  README.md
```

Plugins provide tools. Skills define behavior. Repository and Drupal contracts
remain authoritative over generic skills.

## Monitoring and evidence

The swarm operations view records routine/request IDs, stage, specialist task,
provider/model, local/cloud classification, timestamps, attempts, fallbacks,
cost/usage, input/output checksums, schema results, QA assertions, approvals,
errors, artifacts, and every prompt/skill/routine version.

Alerts cover provider outage, malformed output, conflicting research, missing
citations, cost ceiling, material disagreement, ineligible PII route, QA
regression, overdue customer response, unreviewed proof, and approved work that
has not started.

## Governed learning

Agents do not modify or promote their own production instructions. The learning
loop records inputs/outputs, QA failures, customer decisions, revisions, final
acceptance, delivery evidence, and outcomes; creates a candidate lesson; replays
historical evaluations; compares the challenger to the current version; and
requires authorized promotion to a new rollback-capable version.

Evaluation measures schema validity, factual precision, citations,
completeness, false positives, repair rate, latency, cost, human acceptance,
customer revision patterns, and regression performance.

## Build order

### Phase 1 — orchestration spine

1. `website_build_brief.v2` schema and assembler.
2. Agent/task manifest schema.
3. Expanded provider and model registry.
4. Router with capability selection, timeout, fallback, and circuit breaker.
5. Run ledger and evidence records.
6. Shay `website.preview.v2` routine.

### Phase 2 — first useful swarm

1. Intake Auditor.
2. Business Verification.
3. Industry Research.
4. Domain/Technology Audit.
5. Research Synthesizer.
6. Solution Architect.
7. Package/Add-on Analyst.
8. Prototype Builder.
9. Independent Evidence/QA Agent.

### Phase 3 — commercial completion

1. Competitor and Reference-Site agents.
2. Scope/Risk and Contract/Deliverables agents.
3. Information Architecture and Content agents.
4. Proposal assembler.
5. Portal questions, approvals, and anonymous request claim.

### Phase 4 — evaluation and optimization

1. Evaluation harness and historical replay.
2. Challenger runs and provider scorecards.
3. Candidate lesson records and controlled promotion.
4. Cost, latency, and quality optimization.

## First acceptance target

Use the existing potential customer already represented in Drupal. Attach one
website request to the existing prospect/customer rather than duplicating it;
complete missing intake; produce a research snapshot; run
`website.preview.v2`; retain every specialist result; show package, add-on,
upsell, risk, and proof decisions; return three reviewed previews; preserve the
conversation; and convert the same request into Commerce/intake/project/build
records if the customer proceeds.

This proof remains local or provider-specific until each invoked external
provider and the production callback/delivery path have their own evidence.

## Related records

- `docs/AGENT_OPERATING_CONTRACT.md`
- `docs/SITE_STUDIO_INTEGRATION.md`
- `docs/WEBSITE_INTAKE_SCENARIO_PROOF_2026-08-17.md`
- `docs/marketing/LOCAL_MODEL_AND_AGENT_ROUTING_2026-08-12.md`
- `marketing/local-models.json`
- `marketing/providers.json`
- `docs/SOURCE_OF_TRUTH.md`
