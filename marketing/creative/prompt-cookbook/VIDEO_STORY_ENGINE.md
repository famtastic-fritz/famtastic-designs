# Story-first campaign-video engine

HyperFrames is the default **final-video** framework for new FAMtastic
campaign explainers. A video begins with the story, claim ledger, and planned
keyframes—not with a render timeline. The nearest route is the local
`faceless-explainer` HyperFrames skill: article or topic -> narrative angle ->
script -> storyboard -> reviewable render brief -> composition. The
`muapi-storyboard` route may generate keyframes after a storyboard is approved.

MoneyPrinterTurbo remains available for a quick previsual, narration draft, or
stock-footage sketch. It is not the final branded-video source of truth: its
default stock-footage/voice assembly cannot establish FAMtastic's claim,
composition, or character-continuity controls.

## Required flow

1. Start with one source: a published blog, approved campaign brief, or
   source-backed research note. Extract only claims that have a named source.
2. Make a `campaign-story.v1` document using
   [`schemas/campaign-story.schema.json`](./schemas/campaign-story.schema.json).
   It names the audience, one angle, one visual metaphor, destination, and
   30–90 second duration.
3. Plan at least five scenes: hook, friction, mechanism, turn, and offer/CTA.
   Each scene gets one visual job and one physical action; it may not merely
   repeat a heading as text on a card.
4. Review the storyboard and claim ledger before requesting any generation or
   render. A storyboard is a decision artifact, not an automatic permission to
   use credits, upload a reference, or publish.
5. Create or select only the scene ingredients that passed review. Use an
   image-to-video provider for one physical shot at a time, then let
   HyperFrames own type, captions, transitions, safe areas, pace, and final
   output. Record the prompt-to-output receipt.

Validate a seed before review with:

```sh
node marketing/creative/prompt-cookbook/validate-campaign-story.mjs path/to/story.json
```

Immediately before requesting a render, use the stricter gate:

```sh
node marketing/creative/prompt-cookbook/validate-campaign-story.mjs path/to/story.json --require-render-ready
```

The stricter command must fail until a reviewer has changed the story status,
storyboard and claim-ledger decisions, provider authorization, and gate status.

The story seed under `story-seeds/` is deliberately **not render-ready**. It
demonstrates how an existing pillar can become visual causality rather than a
slide sequence.

## Brand-character anchor and derivative policy

A high-quality licensed/approved anchor asset can be worth premium spend.
It is an approval-gated input, not a promise that any arbitrary local model
will preserve a person across fifty outputs.

Before a paid anchor, upload, training run, or likeness-bearing generation:

- identify the character owner, consent/rights basis, intended markets, and
  allowed provider;
- create a character bible: reference hash, immutable facial/wardrobe/age
  traits, approved palette, lighting/world rules, forbidden changes, and an
  approved prompt baseline;
- decide whether an approved reference/edit route or a locally controlled
  route is actually available. Never call a model "fine tuned" without a
  documented training dataset, license, and evaluation result.

After approval, vary one dimension at a time (pose **or** camera **or**
background) from the same anchor. Generate a small pose matrix first, then
QA identity, wardrobe, lighting, hands, and background fidelity. Promote the
route only after the matrix passes. Every derivative records the anchor hash,
reference strength/control settings, output hash, provider/local-compute cost,
and human acceptance result. This makes a cost target measurable; it does not
pretend a $3 anchor will automatically yield $0.50 assets.

## Tool decisions

| Need | Default route | Why |
|---|---|---|
| Blog title to a branded explainable story | HyperFrames + faceless-explainer | Forces narrative/script/storyboard before rendering. |
| Reviewable visual beats | Written story document, then `muapi-storyboard` after approval | Keyframes test visual logic before motion spend. |
| Short cinematic ingredient | Approved image-to-video provider | One controlled shot, then compose deterministically. |
| Approved talking presenter | HeyGen | Presenter continuity and spoken delivery. |
| Quick disposable previsual | MoneyPrinterTurbo | Fast draft only; never final campaign truth. |
| Consistent character variations | Approved anchor + validated edit/control route | Variation is measured against a character bible, not guessed. |

## Receipt additions for character work

In addition to the cookbook's base receipt, record `character_id`, anchor
hash, rights/consent reference, immutable-trait checklist, the one permitted
variable, reference/control settings, identity-QA decision, and actual unit
cost. A rejected derivative is evidence too: keep its hash and reason so the
next run does not repeat the same failure.
