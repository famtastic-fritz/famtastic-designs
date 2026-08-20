# Broward barbershop swarm pilot — 2026-08-17

## Outcome

A synthetic Broward County barbershop request produced three actual website
proofs named Safe, Wild, and OMG — I Can't Believe This. Playwright rendered all
three at desktop and mobile, verified the hero asset and overflow baseline,
selected OMG, clicked explicit approval, generated an approved local build,
opened the payment gate, and stopped without creating a checkout session or
attempting payment.

The fictional business is `Third Chair Studio`. Owner age, African American
ownership context, and voluntary Life Path 3 preference were used only to guide
respectful representation and optional creative exploration. They did not affect
package, price, eligibility, risk, legal, accessibility, or approval logic.

## Evidence

- Runner: `scripts/run-broward-barbershop-pilot.sh`
- Scenario: `website-delivery-swarm/pilots/broward-barbershop/scenario.json`
- Source artifacts: `website-delivery-swarm/pilots/broward-barbershop/site/`
- Generated evidence: `.artifacts/broward-barbershop-pilot/latest/evidence.json`
- Approved build: `.artifacts/broward-barbershop-pilot/latest/approved-build/`
- Model benchmark: `.artifacts/broward-barbershop-pilot/latest/benchmarks/summary.json`

Two Gmail messages were sent to and found in the authenticated account: a
proof-ready notification and an approved-build/payment-stop notification. This
is test-provider evidence for Gmail self-delivery only, not customer outreach.

## Model benchmark

- Qwen3 8B local recommended OMG in about 18 seconds.
- GLM4 9B local recommended Safe in about 18 seconds.
- Claude Sonnet cloud recommended Safe in about 97 seconds and recorded an
  estimated CLI cost of about $1.06.

The disagreement is preserved. Model preference does not overrule the explicit
synthetic customer selection. Claude's repository-aware review found a missing
persistent booking CTA, an unimplemented phone fallback, and contrast/focus
checks that remain launch work.

## Research grounding

- Broward population context: [U.S. Census QuickFacts](https://www.census.gov/quickfacts/browardcountyflorida)
- Florida shop license and sanitation requirements: [DBPR checklist](https://www.myfloridalicense.com/CheckListDetail.asp?SID=&XACT_DEFN_ID=5087&clientCode=0304&xactCode=1030) and [Florida Statutes §476.184](https://www.flsenate.gov/laws/statutes/2025/476.184)
- Accessible booking guidance: [ADA.gov](https://www.ada.gov/resources/web-guidance/)
- Community context: [PubMed study](https://pubmed.ncbi.nlm.nih.gov/38815252/)

All services, policies, credentials, reviews, hours, exact location, booking
provider, real photography, phone, domain, and email remain draft or unknown.

## Classification and boundary

- Prototype generation and click journey: locally proven.
- Gmail self-delivery: test-provider proven.
- Qwen and GLM execution: locally proven advisory runs.
- Claude Sonnet execution: cloud-provider benchmark completed.
- Drupal persistence and the existing Site Studio callback: not exercised in
  this pilot because the isolated worktree has no installed backend vendor tree.
- Full WCAG/assistive-technology QA: pending.
- Payment attempted: false.
- Payment completed: false.
- Stop reason: `payment_boundary`.

The current production pipeline normally requires payment before intake/build.
This pilot tests the proposed presale preview-build experience in an isolated
simulator; it does not change production ordering or claim Drupal integration.
