# RECIPE: Product Factory — 1,000 solutions → sellable catalog

**Outcome**: The idea backlog becomes a managed portfolio: scored, tiered, batched through NEW_PRODUCT.md, surfaced on the storefront, and rotated through campaigns.
**Trigger**: Batch window (target: one wave of 3–5 products per month after T1/T2 stabilize).
**Owner**: Portfolio Manager agent (hire pending) · **Gates**: every product crosses NEW_PRODUCT step 11 (Fritz).
**Raw material**: `../../1000-IDEAS.md` (~1,000 ideas incl. token-optimizer family), monorepo `agent-business-os/`, existing unwired capabilities.

## Steps

| # | Step | Owner | Definition of done | Evidence | Status |
|---|------|-------|--------------------|----------|--------|
| 1 | Import & dedupe backlog into catalog register | Portfolio Mgr | `docs/products/CATALOG.md` created: every idea = row w/ source, one-line outcome, suggested price | Register committed | ◐ PARTIAL 2026-08-23 — CATALOG.md created; first 50 backlog rows imported+scored with dedupe rules stated. Remaining ~1,250 rows queued for batch import (explicitly recorded, not silently skipped). |
| 2 | Score each candidate | Portfolio Mgr | Scored 1–5 on four axes (below); scores in register | Score column populated for first 50 | ✅ DONE 2026-08-23 — first 50 scored on all four axes with per-row justifications + precedence rules. Key finding: backlog rows 1–50 are dominated by off-brand families → bulk KILL; wave-1 candidates come from owned capabilities instead. |
| 3 | Tier assignment | CEO reviews | Each scored item → STARTER / ADD-ON / GROWTH / RECURRING / KILL | Tier column; kill list with reasons | ◐ PROPOSED 2026-08-23 — tier column populated for first 50 w/ reasons (CEO review pending as designed). |
| 4 | Select wave (3–5) | CEO + Fritz sign-off | Wave chosen by score × strategic fit (feeds the $199/55¢ ladder) | Wave table in MASTER-PLAN weekly report | GATE→☐ |
| 5 | Run NEW_PRODUCT.md per product | Owners per that recipe | Steps 1–10 complete per product | Links to receipts | ☐ |
| 6 | Launch gate (per product) | **Fritz** | Approved at NEW_PRODUCT step 11 | Approval notes | GATE |
| 7 | Storefront surfacing | CMO + Unifier | Product visible: packages page and/or portal catalog; not buried | URL + screenshot | ☐ |
| 8 | Promo rotation | Social Ops + Content Engine | Each launched product gets ≥1 blog post + social wave within 30 days | Calendar entries | ☐ |
| 9 | Quarterly portfolio review | CEO + Fritz | Kill/pivot/promote decisions recorded; attach-rate data reviewed | Review note in CATALOG.md | ☐ |

## Scoring axes
1. **Revenue potential** (price × reachable buyers among current/lead base)
2. **Fulfillment cost** (can our pipeline deliver it mostly autonomously?)
3. **Evidence available** (does a working capability already exist? registry level)
4. **Ladder fit** (does it increase what an existing customer can spend?)

## Rules
- A product that only exists as an idea never appears on the frontend. No exceptions.
- Waves stay small. Five live products beaten to polish beat fifty half-wired ones.
- Recurring revenue (managed hosting, care plans) outranks one-off price tags at equal scores.

## Change log
- 2026-08-23 — Portfolio Manager hired; steps 1–3 advanced per DoD (register, scoring, tier proposals). Wave selection stays GATE.
- 2026-08-22 — Created; converts 1000-IDEAS.md from a static list into a governed pipeline.
