# FAMtastic video production: the opinionated decision

Research and implementation date: September 19, 2026. This is a local production system and a CLI handoff, not a launch or publishing authorization.

## Build a reusable production system, not a credit-dependent generator

The best economics come from owning the composition and reusing the ingredients. Make one excellent scene, founder take, still or reference; reuse it across hooks, timing, captions, aspect ratios and calls to action. Spend creative effort on the concept. Do not regenerate a face or scene just because a headline changed.

The canonical definition remains exact:

> Fearless deviation from established norms with a bold and unapologetic commitment to stand apart on purpose, applying mastery of craft to the point that the results are the proof, and manifesting the extraordinary from the ordinary.

| Principle | Production consequence |
| --- | --- |
| Fearless deviation | One impossible visual premise tied to the business idea. A calm person in an absurd world; unusual scale and perspective. Not random spectacle. |
| Applying mastery | Real identity, exact typography, original logo, deliberate sound, readable crops, frame-aligned timing, repeatable source and evidence. |
| Manifesting the extraordinary | Turn existing footage, screens, objects and photographs into a distinctive campaign through composition, lighting, pacing and narrative. |

The source conversation's “Normal energy. Impossible context.” is a valuable creative rule. Photoreal identities and environmental motion need generated or filmed source material. Deterministic graphics cannot truthfully stand in for a newly generated live-action scene. The implementation labels those modes explicitly.

## Hardware changes the plan

The repository's `marketing/local-models.json` and local-model routing document identify **Apple Silicon with 16GB unified memory**. It is shared with the operating system, not 16GB of dedicated NVIDIA VRAM. The original conversation's assumed generation speeds, model attribution from a finished clip, and blanket quality guarantees are unsupported.

ComfyUI's native Wan2.2 5B guidance describes offloading into an 8GB-VRAM setup; upstream Wan's reference pipeline describes a different hardware requirement. Neither establishes useful speed or fit on this particular Mac. Benchmark a short shot before choosing any generation model. Do not download a model simply because its weight size is below16GB. [ComfyUI Wan guide](https://docs.comfy.org/tutorials/video/wan/wan2_2), [Wan repository](https://github.com/Wan-Video/Wan2.2).

## Selected stack and alternatives

| Tool | Decision | Cost and limitation |
| --- | --- | --- |
| HyperFrames | Primary compositor: exact type, objects, clips, captions, multiple formats | Apache-2.0; local render uses no HeyGen credits. Browser and FFmpeg are required. |
| FFmpeg/FFprobe | Encode, probe, audio conversion, contact sheets | Local work, no metered provider request. Never use `drawtext` as a branding fallback. |
| Existing founder footage / owned source library | First source choice | Best identity preservation and lowest repeated-generation cost. Rights and consent still follow the actual source. |
| MoneyPrinterTurbo | Explicit draft montage worker | Existing install; full script, local media, supplied audio. Disabled cloud generation, stock fetching, captions and automatic publishing in this adapter. |
| Existing Photoshop/Premiere/After Effects | Optional attended hero finishing | Already subscribed; no new purchase assumed. GUI reliability makes these unsuitable as an unattended dependency here. |
| Existing Remotion | Preserve for working React compositions | Useful alternative; current free/company eligibility has limits. Avoid adding another framework when HyperFrames already fits. |
| ComfyUI + appropriate local model | Optional shot supplier with measured evidence | Free local inference may still be slow or fail on16GB. API export must match the installed version; no fabricated workflow node IDs. |
| Recorded voice / installed macOS voice | Baseline narration | Recording has the strongest identity; system voice is a functional draft option. Imported audio keeps the renderer independent of TTS services. |
| Kokoro | Optional voice upgrade after a local audition | Open-weight lightweight TTS; model/software download and voice quality evaluation required. Not automatically installed. |
| Blender | Later only for reusable physical sets/camera moves | More art/rigging effort; useful when the same impossible environment is reused enough to justify construction. Not a prerequisite. |
| Paid generative video / rented GPU | Explicit exceptional escalation, not implemented | Compare cost per accepted shot only after local benchmarks. No account, spend or fallback is provisioned. |

Primary details: [HyperFrames quickstart](https://hyperframes.heygen.com/quickstart), [HyperFrames license](https://github.com/heygen-com/hyperframes/blob/main/LICENSE), [MoneyPrinterTurbo](https://github.com/harry0703/MoneyPrinterTurbo), [Remotion pricing/license](https://www.remotion.dev/docs/license/pricing), [Kokoro](https://github.com/hexgrad/kokoro). Exact integration findings and source revisions are in the companion research files.

The older creative skill says to buy a premium anchor first. This task's cheapest-possible instruction changes the default to **reuse an existing approved anchor first**. Buying another anchor is optional, not a requirement. The repo's newer HyperFrames routing governs designed motion. Existing campaign palettes remain varied; black/lime is not a universal campaign theme.

## Production recipe

1. Freeze a brief: audience, one useful idea, desired action, exact claims and source references.
2. Choose the strongest existing approved visual/voice reference. Preserve identity and logo bytes.
3. Author4–6 scenes. Each earns its place: establish the idea, show the ordinary action, make the business consequence visible, resolve with one useful next step.
4. Reuse approved material. Generate only the missing shot, and accept it before requesting variations.
5. Compose exact text and logo afterward. Type, prices, screens and URLs do not belong inside generative prompts.
6. Render a small draft, inspect actual frames and listen. Then produce delivery formats; change crop/focal point where required.
7. Review and retain exact evidence. The existing marketing pipeline handles approval and distribution later.

The user-supplied30-second idea is retained as a configurable storyboard. Its pricing is intentionally absent from the starter: current product terms must be bound from canonical records before an offer version is approved. This is not a change to those terms. Likewise, “take payments” cannot become an implied inclusion in a starter website merely because it appeared in a brainstorm.

## Economics that can be proved

Local composition's metered provider fee is zero. Electricity, hardware depreciation and editing time are not zero and are not measured by this prototype. Report render time and generation attempts, not invented pennies-per-video. A useful cost metric is **total effort and spend divided by accepted usable seconds**, including rejected takes.

Default budget: zero paid API requests, zero new subscriptions, no automatic large model download. Cache invalidation includes inputs, asset bytes, code, brand and selected tool probe. A failed provider does not trigger a paid replacement. Setup can download explicitly selected free dependencies once.

## What this version delivers

Runnable CLI and campaign validation; four authored layouts and aspect formats; imported image/video and soundtrack; exact caption cues; original logo and canonical credit; immutable run evidence; cached complete renders; local system voice; original demo pulse; MoneyPrinter draft adapter; Comfy API adapter with explicit bindings and resume; two-video comparison package.

It does not claim photoreal generation has been proven on the owner's Mac, that the two unseen source movies were recreated, that a draft has human approval, or that marketing publication is enabled. Those are separate measurable outcomes in the handoff.
