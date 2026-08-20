# AND IF IT IS? — Proof Run Report

## Result

**PASS — locally proven campaign draft with production-smoke-tested unlisted
hosting.** The package contains one responsive
social hub, two original GPT Image 2 graphics, three editable HTML social cards,
desktop and mobile screenshots, source research, the exact prompts, a model/cost
ledger, and a deterministic browser proof.

This is not a published social presence. No account was created and no post was
sent. Human brand approval is still required before publication.

Anyone with the unlisted link can view the static proof:
<https://famtasticdesigns.com/proofs/unlisted/0d87038b679e52c104ba126eceb02f1b/>.
The live page was anonymously verified at 1440px and 390px with all images
loaded, no horizontal overflow, and no browser errors. This hosting proof does
not open any social-publication gate.

## Speed

The measured production window—from the first paid image job being accepted to
the final passing browser QA—was **13 minutes 03 seconds**. The two image jobs ran
in parallel and completed in approximately 108 seconds and 152 seconds. The
browser proof initially caught a small decorative overflow; one targeted repair
produced the final pass.

This is close to the earlier 13-minute proof target without hiding the repair.
Research time before the first paid generation was not reliably instrumented in
this run, so it is not included in the measured window.

## Formula

1. One sourced cultural truth.
2. One memorable question-and-response.
3. Two deliberate original images generated in parallel.
4. One responsive hub plus three deterministic social-card layouts.
5. One browser QA pass, with only blocker repair allowed.
6. One explicit human approval gate before publication.

## Models, tools, and cost

- Research: OpenAI web search; underlying search model not disclosed.
- Strategy, copy, design, build, and self-review: OpenAI `gpt-5.6-sol`.
- Graphics: OpenArt `gpt-image-2`, one 2K 16:9 image and one 2K 4:5 image.
- Graphics cost: **315 OpenArt credits** total.
- Browser verification: Playwright with Chromium.
- New agents or skills: **none**. Existing capabilities were sufficient.

## Quality decision

The technical gate passed every assertion: five routes returned 200; all images
loaded; all local links worked; desktop and mobile had no horizontal overflow;
the unofficial and publication boundaries were visible; and the browser logged
no console, page, or network errors.

The generating agent's visual self-review scored the package 9.0 overall, with
no critical defects. That is evidence, not independent approval. The next gate
is Fritz's brand decision.

## Reproduce

From the repository root:

```bash
marketing/campaigns/and-if-it-is-rattler-lifers/run.sh
```

The command validates the campaign JSON, runs the browser proof, and refreshes
the artifact hashes. It does not regenerate paid imagery or publish anything.
