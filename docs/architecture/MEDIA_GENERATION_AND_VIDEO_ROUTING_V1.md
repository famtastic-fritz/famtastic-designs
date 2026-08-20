# Media generation and video routing v1

Date: 2026-08-20
Status: accepted routing policy; individual providers retain their evidence level

## Intent

FAMtastic needs several media lanes, not a single “AI video” button. A customer
proof, an art-directed social clip, and a high-volume explanatory reel have
different quality, timing, cost, rights, and approval needs.

| Need | Primary lane | Input | Output | Boundary |
| --- | --- | --- | --- | --- |
| Original proof/campaign stills | Approved image route (currently Gemini Flash Lite is provider-proven for one story sequence) | Art-direction brief, prompt, approved reference when permitted | Still-image receipt and local asset | No publishing or customer claim from the image alone |
| Designed motion and proof walkthrough | HyperFrames | Approved stills, screenshots, copy, a motion brief | Deterministic composition source plus rendered media | No creative or publishing authority; human review required |
| Fast narrated social/video assembly | MoneyPrinterTurbo | Governed topic, approved script, licensed/owned media, captions/TTS policy | Draft video and run receipt | Draft-only; publish stays closed until explicit approval |
| Low-cost image-volume candidate | ACI AI | Exact benchmark brief | Benchmark result only | Quoted plan is unverified until API, terms, rights, quota, and receipts are captured |

## Why the lanes stay separate

HyperFrames is the compositional lane: it is for intentional motion, responsive
layout, type, screens, and approved visual assets. It should be chosen when the
visual design itself is the deliverable.

MoneyPrinterTurbo is the narrative-assembly lane: its documented flow combines
script, footage, subtitles, music, and formatting for short videos. It is a
good candidate for FAMtastic explainers and campaign drafts, not the source of
truth for a customer proof or an unattended publishing mechanism. Its automatic
distribution features remain disabled in FAMtastic until a separately approved
channel, consent, attribution, rollback, and alert policy exists.

## Required Build DNA records

Every qualifying media stage writes to the build’s existing
`famtastic.build-dna.v1` record. At minimum record:

1. Stage purpose, capability route, provider, exact model/version or explicit
   unknown state, executable command, and source checkout/revision.
2. Prompt/script hashes, source asset hashes, licenses/rights notes, and the
   normalized input contract.
3. Start/end/duration, declared budget, provider usage/receipt, actual cost or
   explicitly estimated/unavailable cost status, retry/fallback decision.
4. Output paths, output hashes, render settings, captions/transcript, and
   desktop/mobile or frame-level QA evidence as relevant.
5. Reviewer identity/decision and the next authorized handoff: customer proof,
   Site Studio packet, campaign draft, or explicit publish gate.

Build DNA is copied unchanged into a Site Studio build packet when the media is
part of a selected website direction. The success packet later points back to
the same build ID; Site Studio does not recreate or reinterpret upstream media
lineage.

## Provider policy

- Do not put a provider name inside a creative-stage contract. Declare a media
  capability and route it through configuration.
- Do not treat a subscription as an API or a guaranteed usage allowance.
- Benchmark any new image/video route against a held-out brief and the visual
  rubric before pricing it into an offer.
- Never persist keys, cookies, OAuth, customer lists, or raw credentials in
  Build DNA, Drive, source control, prompts, or CLI arguments.
- No media route may publish, email customers, bill, or modify a paid project
  without its separately authorized FAMtastic workflow action.

## Current evidence

Gemini Flash Lite has one provider-proven, reference-led 1K story series with
locally verified build evidence. HyperFrames is installed but has no FAMtastic
render proof. MoneyPrinterTurbo has been evaluated from its public repository
documentation but has no FAMtastic run. ACI AI was reported as a possible
subscription plan; its price and conditions have not been independently
verified. These labels are deliberately conservative.
