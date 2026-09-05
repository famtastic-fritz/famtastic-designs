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

1. **Blog lane:** all six requested draft folders now have `draft.md`,
   `brief.md`, and `seo-check.json`; their local cluster-plan category/tag/
   keyword values are also restored. Run each publish command in `--dry-run`
   only. Do not publish. The remaining intentional blocker is the platform-
   dependency arc's series/title/order and complete manifest facts: do not
   assign the drafts to an unrelated existing series or remove that gate.
   A separate review-only series package now lives at
   `marketing/blog/series-drafts/platform-dependency/`; it has a proposed
   title, provisional reader persona, current Google-source notes, and a
   pillar revision candidate. It is not the Drupal manifest and must not be
   treated as publication authority.
2. **HeyGen lane:** Take A exists under `marketing/creative/heygen/`; Take B
   had just been submitted when the session ended. Inspect provider status and
   save its actual receipt/render before treating it as complete. Do not post
   either render.
3. **Plate/texture lane:** validate the modified
   `marketing/creative/plates/generate-plates.mjs` and prompt library. The
   planned `marketing/creative/textures/` library was not created before the
   interruption.
4. **Creative/video lane:** resume from
   `marketing/creative/asset-graph/README.md`, not from a default storyboard.
   A job may begin with a human brief plus any declared source (blog, research,
   guide, image, audio, video, or prior asset node). It must compare one
   premium benchmark with at least two cheap/local candidates, retain hashes,
   inputs, costs, QA, and rejected results, and stop at the USD 5 still/copy or
   USD 25 video experiment cap. The video-story treatment is optional and may
   only be created from the human brief as an experiment; it no longer infers a
   story from a title or source. HyperFrames, Remotion, HeyGen, image-to-video,
   and MoneyPrinterTurbo are selectable nodes, not a mandatory one-provider
   route. Run `list-provider-routes.mjs --family <...>` before choosing a
   candidate. MoneyPrinterTurbo is currently a historical/unproven candidate:
   do not call it installed or available until a fresh local run has a receipt.
   Do not use raw tracking URLs as on-screen text.

   Do not buy or generate a premium character anchor, upload a customer
   reference, train a local model, or claim low-cost consistent derivatives
   until owner authorization, rights/consent, a character bible, and a
   receipt-backed pose-matrix test exist.

5. **Specialist and model routing:** select named FAMtastic roles from
   `docs/playbook/ROSTER.md` / `.opencode/agent/`, not from a duplicate agent
   list. The local Ollama runtime has `qwen3:8b`, `glm4:9b`, and `gemma3:4b`;
   use the bounded roles in `marketing/local-models.json` and disclose cloud
   tags. The owner reports an OpenCode Go subscription, but the local CLI has
   only an OpenRouter credential receipt so far; do not call Go authenticated
   until an authorized status/login check is recorded. The owner also reports a
   Piece subscription; leave it un-routed until the exact product URL/name is
   supplied.

## Boundaries and current state

- This recovery created local creative proof only. Nothing in this handoff has
  been uploaded, scheduled, posted, or deployed.
- Do not alter the pre-existing scheduled social drop without fresh owner
  direction.
- Preserve the dirty tree until all four lanes have been reviewed together;
  stage intentional paths explicitly rather than using `git add -A`.
