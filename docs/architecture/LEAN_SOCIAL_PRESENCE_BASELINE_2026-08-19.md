# Lean social-presence quality baseline

This document records the first golden example. The provider-neutral reusable
recipe is `docs/architecture/LEAN_SOCIAL_PRESENCE_PRODUCTION_PROCESS_V1.md`.

**Date:** 2026-08-19
**Baseline:** `AND IF IT IS? — The Unofficial Rattler Lifers Project`
**Status:** Creative package locally proven; separate public interactive experience and public FAMtastic Lab DNA case study production-proven at desktop and phone; social publishing not attempted.

## Outcome

This run establishes a smaller quality benchmark than the six-direction website
contract. It produced one coherent social-presence direction, not six competing
website proofs:

- one researched cultural thesis;
- one responsive campaign hub;
- two original 2K campaign graphics;
- one original character system, The Lifer;
- three editable HTML social cards;
- one six-record draft campaign manifest;
- desktop, mobile, and social-card screenshots;
- exact prompts, model/provider receipts, cost, timings, hashes, and review;
- one anonymously verified public interactive experience;
- one separate public Lab DNA/process case study.

Public interactive experience:
<https://famtasticdesigns.com/and-if-it-is/>

Preserved unlisted proof artifact:
<https://famtasticdesigns.com/proofs/unlisted/0d87038b679e52c104ba126eceb02f1b/>

Public FAMtastic Lab case study:
<https://famtasticdesigns.com/lab/and-if-it-is/>

Drive evidence package:
<https://drive.google.com/drive/folders/1tk9mT2oYYQOml6WUyZXr6pveRBYj9nkv>

The source and evidence package lives at
`marketing/campaigns/and-if-it-is-rattler-lifers/`.

## Why this is one proof

The goal was to prove that research-first direction, purpose-built imagery,
expressive typography, material texture, and sourced storytelling could recover
the quality and speed of the strongest FAMU work without starting another large
swarm. Publishing six directions would have tested variety; this run tested the
smallest repeatable unit of quality.

The six-direction website benchmark remains the correct contract when a client
must compare one restrained, one medium, and four ultra-FAMtastic websites. This
social baseline instead proves one brand system deeply enough to support a
channel launch. Future social work may request variants, but it must not pretend
one campaign identity is six website proofs.

## Lean formula v1

1. Find one defensible cultural truth and the legal/trademark boundaries.
2. Reduce it to one memorable question-and-response.
3. Generate only two deliberate original images in parallel: a story hero and a reusable character or visual anchor.
4. Compose one responsive hub and three deterministic social-card layouts.
5. Run browser QA; repair only blocking defects.
6. Preserve the brief, research, prompts, models, cost, timing, screenshots, hashes, and reviewer decision.
7. Require explicit human approval before creating accounts or publishing social posts.

The measured production window from first paid image request to passing browser
QA was 13 minutes 03 seconds. Research time before the first paid request was
not reliably instrumented and is excluded. The two GPT Image jobs ran in
parallel, used no retries, and cost 315 OpenArt credits. One 8-pixel/2-pixel
decorative overflow was found and repaired by Playwright.

## Quality levers that must survive reuse

- Research precedes art direction and copy.
- The image prompt specifies story, subject, framing, material, lighting, negative space, prohibited marks, and failure modes.
- Typography is part of the design: display weight, outline type, italic serif contrast, rotation, and scale—not only bold/color.
- Surfaces carry depth: scale geometry, paper grain, concrete, wool, leather, atmosphere, rings, and perforated/ticket motifs.
- A character or graphic device is original and reusable without copying an official mascot.
- Claims are cited and time-sensitive claims carry a retrieval date.
- Desktop and phone must both pass real geometry and asset-load checks.
- A visual self-review is recorded honestly and cannot masquerade as independent approval.

## Image provider independence

OpenArt was the authenticated transport in this run; GPT Image 2 was the model.
They are not the same layer.

The same `gpt-image-2` model can be called directly through the OpenAI Image API
without OpenArt. Official OpenAI documentation says the Image API directly
selects GPT Image models and is the preferred path for one-shot generation or
editing. The Responses API is the alternative for conversational or multi-turn
image workflows, where a mainline model invokes the image-generation tool.

Direct OpenAI routing still needs a server-side API project/key, possible
organization verification, usage receipts, and a benchmark through this exact
prompt/rubric before it can be marked proven. A different model from Gemini,
MuAPI, a specialized provider, or a local runtime is also possible, but that is
a model substitution and must never be a silent fallback.

Canonical routing contract:
`marketing/campaigns/and-if-it-is-rattler-lifers/image-routing.json`.

Official source:
<https://developers.openai.com/api/docs/guides/image-generation>.

## Publishing boundary

The unlisted URL is a static proof that anyone with the link can view. It does
not create a social account, send a post, claim official FAMU affiliation, or
transmit the roll-call form. Public social publishing remains gated by content,
media, and publish approvals in `manifest.json`.

The live bundle was published atomically by
`scripts/publish-unlisted-static-proof-godaddy.sh`. The script validates paths,
file types, HTML/script behavior, size, and checksum; stages the archive; and
promotes only after the remote file count and SHA-256 pass.

## Durable evidence

- `brief.json` — verbatim user input and assembled brief
- `research.json` — claims, sources, and boundaries
- `formula.json` — versioned six-stage production formula
- `prompts.json` — exact image prompts and parameters
- `image-routing.json` — proven route, direct alternatives, and failover rules
- `manifest.json` — six draft content records with closed approval gates
- `evidence/run-ledger.json` — models, providers, durations, cost, outputs, and repair
- `evidence/browser-results.json` — deterministic local browser assertions
- `evidence/visual-review.json` — disclosed self-review and pending human gate
- `evidence/live-publication.json` — URL, checksum, and anonymous live verification
- `evidence/drive-export.json` — Drive folder, archive, cross-folder snapshots, and public-proof receipt
- `evidence/run-report.md` — human-readable outcome and limitations
- `run-blueprint.json` — adjustable stage, routing-class, and telemetry contract
- `quality-contract.json` — technical, visual, reuse, and bounded-speed rules
- `evidence/post-run-review.md` — quality/latency analysis and advancement queue
- `evidence/lab-browser-results.json` — deterministic local Lab assertions
- `evidence/lab-live-results.json` — anonymous production Lab smoke proof
- `evidence/lab-publication.json` — exact public URL, hashes, and deployment lane
- `evidence/experience-publication.json` — public microsite hashes, interaction contract, and recovery lane
- `evidence/experience-live-results.json` — production roll-call, metadata, route, card, desktop, and phone proof

## Promotion rule

This becomes a general FAMtastic social-presence recipe only after a second
unrelated brand reproduces the quality floor with the same bounded formula.
Until then it is a golden baseline, not a universal template. Do not add another
agent, swarm layer, or framework unless a measured failure identifies a missing
capability that the current tools cannot supply.
