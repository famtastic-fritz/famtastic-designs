# Claude Code handoff — 2026-09-05

## What was recovered

The most recent interrupted Claude session was working on four shared lanes:

1. a reusable Remotion campaign-video system;
2. HeyGen presenter takes and OpenArt investigation;
3. a plate/texture prompt-library expansion; and
4. six platform-dependency blog drafts.

The branch already contained twelve local commits ahead of `origin/main` when
the subscription stopped. Do not recreate those commits or reset the working
tree; the remaining uncommitted files are intentional continuations of those
lanes.

The recovery commit is `978cca49` (`Connect reusable campaign video system and
render proof`). A push of the accumulated commits was attempted twice on
2026-09-05. Local `git fsck --full` passed, but GitHub rejected both uploads
while unpacking its received pack (`inflate returned -3`, then `-5`, at
different offsets). Treat the branch as **committed locally, not pushed** until
the remote accepts a fresh push; do not rebase, reset, or force-push as a
workaround.

## Completed after recovery

- Connected `marketing/video/src/system/` to the Remotion registry through
  `marketing/video/src/drops/platform-dependency.ts` and `src/root.tsx`.
- Added three renderable composition ids:
  `PlatformDependency-9x16`, `PlatformDependency-1x1`, and
  `PlatformDependency-16x9`.
- Rendered and visually checked representative frames in all formats, then
  rendered the complete 25-second vertical proof:
  `campaigns/own-website-vs-rented-platforms/videos/platform-dependency-9x16.mp4`.
- The verified purchase target is the current FAM-FOOT-199 purchase URL. The
  video names only product-registry facts: one focused landing page, first year
  of managed hosting, first-year new-domain registration when needed or domain
  connection, and separate $99 business-email setup.
- Added `marketing/creative/prompt-cookbook/` in `ec6a7c27`: source-attributed
  image/video prompt recipes, provider notes, and a required receipt schema.
  Treat this as the prompt-training entry point rather than starting another
  unstructured prompt list.

## Resume order

1. **Blog lane:** finish all six requested draft folders with `draft.md`,
   `brief.md`, and `seo-check.json`; current new A1/A2 folders contain only
   drafts and B1/B2 drafts were left over the requested word range. Run each
   publish command in `--dry-run` only. Do not publish.
2. **HeyGen lane:** Take A exists under `marketing/creative/heygen/`; Take B
   had just been submitted when the session ended. Inspect provider status and
   save its actual receipt/render before treating it as complete. Do not post
   either render.
3. **Plate/texture lane:** validate the modified
   `marketing/creative/plates/generate-plates.mjs` and prompt library. The
   planned `marketing/creative/textures/` library was not created before the
   interruption.
4. **Video lane:** the recovered Remotion proof remains valid local evidence,
   but HyperFrames is now the default **final-video** route for new campaign
   explainers. Before any new render, make a `campaign-story.v1` from
   `marketing/creative/prompt-cookbook/schemas/` and follow
   `VIDEO_STORY_ENGINE.md`: claim ledger -> visual story -> scene storyboard
   -> review gate -> controlled shot assets -> final composition. The local
   `faceless-explainer` workflow is the intended topic/blog-title-to-story
   route. MoneyPrinterTurbo is previsual/narration-draft only, never the final
   branded-video source of truth. Do not use raw tracking URLs as on-screen
   text.

   Do not buy or generate a premium character anchor, upload a customer
   reference, train a local model, or claim low-cost consistent derivatives
   until owner authorization, rights/consent, a character bible, and a
   receipt-backed pose-matrix test exist. The current
   `story-seeds/platform-dependency-pillar.story.json` is deliberately a
   non-render-ready seed; it has not spent credits or changed social state.

## Boundaries and current state

- This recovery created local creative proof only. Nothing in this handoff has
  been uploaded, scheduled, posted, or deployed.
- Do not alter the pre-existing scheduled social drop without fresh owner
  direction.
- Preserve the dirty tree until all four lanes have been reviewed together;
  stage intentional paths explicitly rather than using `git add -A`.
