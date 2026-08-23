# FAMtastic Playbook DNA

The playbook is the operating system of FAMtastic Designs. **If it isn't in a recipe, it isn't real work.**

## What a recipe is

A recipe is one repeatable business flow written as ordered, ownable steps. Each step names WHO (agent role or Fritz), WHAT (the action), the DEFINITION OF DONE, and the EVIDENCE required before the step may be marked done. Recipes are living documents: status is updated inline, in the file, by whoever completes a step.

## Files

| File | Purpose |
|---|---|
| `MASTER-PLAN.md` | The company plan: five tracks, sequencing, metrics |
| `STRATEGY-PRICING.md` | Pricing architecture, lead-wave campaign plan, positioning |
| `RECIPE_TEMPLATE.md` | Canonical structure every recipe must follow |
| `ROSTER.md` | The workforce: hired agents, their mandates, status |
| `RECIPES/LEAD_TO_LAUNCH.md` | Lead capture → paid → built → launched → retained |
| `RECIPES/NEW_PRODUCT.md` | How any new product X goes from idea to sellable + promoted + supportable |
| `RECIPES/AUTONOMOUS_CUSTOMER_SERVICE.md` | T1: mail integrity, reply visibility, triage autonomy ladder |
| `RECIPES/SOCIAL_POSTING.md` | T2: scheduled, verified, attributed social publishing (17-day pilot) |
| `RECIPES/BLOG_FACTORY.md` | T3: 2 posts/week repeatable production line |
| `RECIPES/PRODUCT_PIPELINE.md` | T5: 1,000-solution backlog → scored catalog → launch waves |
| `RECIPES/CAMPAIGN_17DAY.md` | Day-by-day revival of the stalled sprint (Social Ops to create in step 1) |
| `RUNBOOKS/A1-prod-mail-integrity.md` | Fritz-executed production SMTP verification + fix for customer-service A1 |

## Rules

1. **Recipes over memory.** New agents (human or AI) onboard by reading recipes — never by asking "how does this work?" and trusting vibes.
2. **Evidence closes steps.** Acceptable evidence: validator/acceptance script output, test run, git SHA demonstrating behavior, screenshot/log path. Code existing ≠ step done.
3. **Status lives in the recipe.** Mark steps ✅ / 🔄 / ⚠️ inline with owner + date + receipt link.
4. **Gates stay gated.** Steps touching real outreach, billing, DNS, or production deploys end at an APPROVAL GATE owned by Fritz. No agent crosses a gate.
5. **The CEO enforces this file.** See `.opencode/agent/fam-ceo.md`.

## Onboarding a new agent (60 seconds)

1. Read this README.
2. Read your role entry in `ROSTER.md` and your agent file in `.opencode/agent/`.
3. Open the recipe containing your assignment; work top-down through your ⚠️/🔄 steps.
4. Return DONE/BLOCKED with evidence. Update the recipe inline.
