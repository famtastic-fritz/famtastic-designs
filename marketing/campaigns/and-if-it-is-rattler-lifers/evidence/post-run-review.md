# Post-run review — preview quality, latency, and repeatability

Date: 2026-08-19

## Decision

The `AND IF IT IS?` campaign is the golden baseline for one deep social-presence
direction. The research-led thesis, two-image strategy, expressive typography,
subject-native texture, original character, deterministic composition, and
browser evidence are worth preserving.

The exact quality is not yet a guaranteed automatic outcome. The creative
recipe is repeatable and inspectable; the 13-minute timing and consistent visual
quality still require a second unrelated brand to reproduce the contract.

## What created the quality jump

The largest contributor was the research-to-thesis pass. It replaced a generic
visual theme with a culturally specific argument that controlled the copy,
imagery, type, texture, character, and interaction. The next strongest
contributors were the visual-grammar pass and the two-purpose image constraint.

If only three creative passes could remain, keep:

1. source research and boundary synthesis;
2. campaign thesis plus visual grammar;
3. independent screenshot criticism.

Deterministic assembly, browser QA, ownership, pricing, and publishing remain
mandatory engine work rather than creative passes.

## Timing evidence

### Lean social baseline

The first paid image request through final passing browser QA took 13 minutes
03 seconds. Two GPT Image 2 jobs ran in parallel, no image retry was needed, and
one small decorative overflow received a targeted repair. Research happened
before that clock and was not reliably instrumented.

### Recent six-direction autonomous run

The Rattler Football Fan Portal run began at 10:49:56 UTC and the last recorded
packet cycle completed at 12:43:42 UTC: approximately 1 hour 53 minutes 46
seconds of ledger-span wall time. Its ledger recorded about 71.82 aggregate
active stage-minutes; the remaining 41 minutes 56 seconds were gaps between
manual/resumed invocations rather than provider compute. Both numbers matter:
the customer experienced the wall time, while stage totals locate the engine
bottlenecks.

| Stage family | Calls | Aggregate minutes | Finding |
| --- | ---: | ---: | --- |
| Visual review | 8 | 29.51 | Primary latency defect; repeated reviews were not a quality benefit. |
| Prototype construction | 1 | 10.05 | Large six-site build call. |
| Prototype repair | 1 | 8.75 | Repair was broad instead of tightly consolidated. |
| Visual art | 1 | 7.03 | Acceptable for six original assets; parallelism must remain explicit. |
| Live research | 1 | 5.92 | Useful quality contribution; instrument separately. |
| Technical repair | 1 | 5.15 | Should merge with the consolidated repair packet where safe. |
| Browser QA | 11 | 3.36 | Cheap per attempt, but excessive attempts signal orchestration churn. |

The slowdown was therefore orchestration, review churn, and resumed-run gaps—not
evidence that the creative standard itself needs nearly two hours.

## Rules changed after review

- One initial independent visual review is allowed.
- One consolidated technical/visual repair is the default.
- A second visual review is allowed only when the rendered artifact hash changed.
- An unchanged screenshot or output hash reuses the recorded verdict instead of
  calling the reviewer again.
- Preview QA rejects generic typography, flat untextured surfaces, recolored
  templates, poster-only layouts, weak conversion, and subject-agnostic symbols.
- Noncritical refinement moves to the selected direction instead of multiplying
  across every preview candidate.
- Research time, generation-to-QA time, and selected-direction refinement are
  three separate clocks.
- A missed speed target creates a finding; it never lowers the quality,
  accessibility, rights, or approval gate.

The machine-readable rules live in `quality-contract.json`; adjustable run
parameters and the telemetry contract live in `run-blueprint.json`. The resume
runner now skips its repair/re-review loop when the saved visual verdict passes,
defaults to one repair, and rejects requests that exceed two total visual
reviews.

## Telemetry and adjustment

Every stage must preserve capability, resolved provider/model, input and output
hashes, start/end/duration, cost when known, retries, fallbacks, and reviewer
decision. The operator can tune research depth, direction mix, image count,
FAMtastic intensity, typography experimentation, texture density, motion,
routing class, and repair budget without disabling truth, rights, ownership,
accessibility, approval, or publishing boundaries.

## Remaining advancements

1. Instrument research from the first source request rather than starting the
   clock at paid generation.
2. Extend exact screenshot-hash verdict reuse to every specialized legacy
   resume entrypoint; the primary resume lane now enforces the two-review budget
   and skips already-passing work.
3. Produce one desktop and one mobile contact sheet before independent review;
   open individual directions only to investigate a suspected defect.
4. Normalize provider cost into dollars or a documented credit conversion when
   the provider exposes it.
5. Add a second unrelated brand benchmark using the same quality-and-speed
   contract and no campaign-specific composition reuse.
6. Verify the Lab page's GA4 `page_view` and `cta_clicked` events in production
   Realtime after publication.

## Confidence

- Source preservation and deterministic replay: high.
- Technical browser QA repeatability: high.
- Ability to tune the recipe from recorded telemetry: high.
- Reproducing this quality on a new subject: moderate-high, pending the second
  unrelated benchmark.
- Reproducing 13 minutes every time: moderate-low until research is timed and a
  second unrelated run passes.
- Fully unattended independent creative approval: moderate-high in the primary
  provider lane; the capability and bounded review ceiling are connected, but
  all legacy resume entrypoints still need the same exact hash-reuse helper.
