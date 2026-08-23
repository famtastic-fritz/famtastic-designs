# ADR-001: Autonomous Lead-to-Launch Architecture

- Status: Accepted
- Date: 2026-07-30
- Governing issue: [#15](https://github.com/famtastic-fritz/famtastic-designs/issues/15)

## Context

FAMtastic Designs needs an agent-agnostic system that can take qualified public
business leads through campaign attribution, three design proofs, package
selection, payment, intake, fulfillment, customer deployment, and ongoing
hosting without requiring phone calls or routine operator work.

The current canonical application is a React/Vite frontend and a Drupal 11
backend. The production host is GoDaddy/cPanel. Earlier production changes were
transported manually, which allowed Git, the server, and the promoted frontend
to diverge.

## Decision

Build the first complete system as a modular monolith:

- Drupal is the system of record for prospects, campaigns, consent and
  suppression, proof campaigns, orders, intake, projects, deployments,
  subscriptions, customer-visible status, and audit events.
- React/Vite is the public proof, checkout, intake, approval, and portal
  experience.
- Long-running or external work crosses explicit adapter and queue boundaries:
  FAMtastic-controlled preview generation, email delivery, selected-packet Site
  Studio build execution, Stripe, deployment, DNS/domain, and analytics. Each
  adapter has an idempotency key, durable status, retry policy, and
  operator-visible failure state. Site Studio is not a preview generator or
  delivery authority.
- Git is the only source for deployable code. All agents call the same checked-in
  scripts. Agent identity never changes the release procedure.
- Production releases use an exact committed SHA, build outside the document
  root, back up the replaced application surface, apply only the intended
  paths, run database updates and cache rebuilds, verify both hostnames, and
  record the result.
- Live charges, domain purchases, paid infrastructure, real outreach, DNS
  changes, and production releases remain explicit approval gates.

## Commercial rules

- Essential Launch: $199.
- Business Launch: $499.
- The selected package and charged price are immutable order facts; UI labels
  cannot choose the server-side amount.
- The initial purchase includes twelve months of hosting.
- Recurring hosting begins at month 13, only after the customer has accepted
  clear renewal terms and supplied a valid payment mandate.
- The customer owns or controls their domain. FAMtastic may register or manage
  it as an agent with documented authorization, renewal terms, and a transfer
  path. Domain ownership is never used as customer lock-in.
- No routine phone call is required. Exceptions enter an operator queue.

## Security boundaries

- Public prospect access uses high-entropy, revocable, expiring tokens stored
  only as hashes.
- Every public read and write is scoped to the token's owning prospect.
- Payment status is accepted only from a verified provider webhook or an
  authenticated provider lookup. Local payment simulation is disabled by
  default and can never run while a real Stripe key is configured.
- Public ingestion and form endpoints require validation, rate limiting,
  duplicate protection, and abuse controls.
- Secrets live in environment or host secret stores, never Git.
- Personally identifiable information has an explicit retention and deletion
  policy, and audit events avoid secret or full-token values.

## Why this shape

A modular monolith uses the deployed Drupal capability already present, keeps
transactions consistent, and is affordable at the expected starting volume.
Adapter boundaries preserve the option to move proof generation, deployment, or
campaign workers to cloud compute later without splitting the transactional
core prematurely.

## Rejected alternatives

- Direct file upload as the normal release flow: fast for emergencies but not
  reproducible and caused source/runtime divergence.
- Building inside `public_html`: exposes partial builds and mixes source with
  runtime files.
- Independent agent-specific deployment instructions: guarantees drift.
- Microservices for the initial system: adds operational cost and distributed
  failure modes before volume requires them.
- FAMtastic ownership of customer domains: lowers short-term friction but creates
  legal, renewal, transfer, and trust risk.

## Consequences

- Drupal schema updates and deployment scripts must ship in the same commit as
  the code that needs them.
- External actions must be retryable and idempotent.
- A release is incomplete without recorded verification evidence.
- The production server may build the exact Git commit, but must do so in the
  private deployment checkout rather than the public document root.
