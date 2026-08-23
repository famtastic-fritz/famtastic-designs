---
name: run-website-delivery-swarm
description: Run and prove FAMtastic Designs' provider-neutral website.preview.v2 specialist-agent routine. Use when testing Shay website orchestration, intake-to-mockup behavior, package and add-on reasoning, anonymous-versus-member handoffs, model routing, agent traces, screenshots, or readiness to connect the website-delivery swarm to Site Studio.
---

# Run Website Delivery Swarm

Use the repository runner as the executable contract. Do not replace it with an untraced chat-only simulation.

## Workflow

1. Read `references/proof-contract.md`.
2. Inspect repository status and preserve unrelated work.
3. Install locked frontend dependencies when absent with Node 22: `npm --prefix frontend ci`.
4. Run `scripts/run-swarm.sh <repo-path> [output-path]`.
5. Read the final `Evidence:` path from stdout and fail closed when absent.
6. Verify every top-level and specialist assertion in `evidence.json`.
7. Report screenshots, package decisions, add-ons, fallbacks, and unresolved gates.

## Rules

- Classify fixture output only as `locally proven`.
- Preserve Drupal as customer, product, Commerce, approval, and evidence truth.
- Keep one request ID through anonymous claim, portal continuation, proposal, and build.
- Never invent an SKU, price, domain result, business fact, or legal promise.
- Never treat a consumer subscription as automated API access.
- Require independent QA; a generator cannot approve itself.
- Keep production mutations and external deployment behind explicit approval.
