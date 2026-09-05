# FAMtastic creative prompt cookbook

This is a working training library for image plates, edited images, and short
video ingredients. It is not a gallery of magic phrases. Every recipe states
the intended output, the fields an operator must decide, the provider behavior
it assumes, and the receipt needed to decide whether the recipe actually works.

The source of truth for a campaign's claim, offer, CTA, palette, and approval
state remains the campaign brief and product registry. A model is never the
source of a business claim.

## How to use a recipe

1. Choose the output surface and aspect ratio before writing scene detail.
2. Fill the fields in the recipe; do not replace missing decisions with
   adjectives such as "beautiful" or "premium".
3. Generate a cheap draft first when a provider supports it, then vary exactly
   one field per iteration.
4. Record the literal submitted prompt, provider/model/version, inputs,
   aspect ratio, seed/reference if available, output hash, cost/credit use, and
   a human QA result in the campaign receipt.
5. Set type in the approved composition layer. Image plates carry no
   generated words, prices, CTAs, logos, or watermarks.

## Core prompt grammar

The local GPT Image architecture uses four ordered parts; the Gemini plate
worker expands the same idea into labelled blocks. Keep this order even when a
provider accepts free-form prose:

1. **Use and scene** — output surface, ratio, environment, time, and mood.
2. **Subject and action** — exactly what appears and its physical behavior.
3. **Visual direction** — composition, lens/angle, light, materials, palette,
   and the reserved text area.
4. **Constraints and invariants** — no generated type, objects to preserve,
   any edit boundary, and the one element that must not drift.

The canonical field schema lives in
[prompt-cookbook.json](./prompt-cookbook.json). It makes a prompt reviewable
before it costs credits.

## Asset graph before provider choice

The [creative asset graph](../asset-graph/README.md) is the entry point for
new work. It accepts a human-supplied brief plus any legitimate input source
and records how each output becomes an input to another skill. It benchmarks a
premium route against multiple cheaper/local routes before promoting a
repeatable recipe.

The [story-first campaign-video engine](./VIDEO_STORY_ENGINE.md) is now an
optional video-treatment adapter inside that graph. It must not infer a
prescribed story from a blog title or source file. MoneyPrinterTurbo, HeyGen,
image-to-video providers, HyperFrames, Remotion, and deterministic composition
can all be nodes in a job; none is the universal generator. A paid asset,
reference upload, or claimed cost saving still needs its separate authority and
receipt.

## Image recipes

### 1. Campaign plate — text set later

Use for blog heroes, paid-ad backgrounds, and Remotion plates. This is the
default FAMtastic recipe.

```text
Intended use: [surface], [aspect ratio]. This is an image plate; typography
will be set later in the composition layer.
Scene / background: [specific place, time, weather, material state].
Subject: [one concrete object or adult subject], [pose or state].
Key details: [three tactile or story-carrying details].
Camera and light: [distance], [angle], [lens], [light direction and quality].
Colour world: [campaign palette clause].
Composition: keep [left/right/top/bottom] [percentage or named zone] visually
quiet for post-production type.
Constraints: no text, numbers, logos, watermark, UI, or invented brand marks.
Preserve: [the campaign's visual invariant].
```

**FAMtastic example — owned-front-door argument**

```text
Intended use: vertical 9:16 social video opening plate. Typography will be
set later.
Scene / background: a modest storefront before opening, blue-black predawn,
wet concrete and a closed painted door.
Subject: one blank brass letter slot in the lower third of the door.
Key details: worn brass edges, a real keyhole, rain beads on the threshold.
Camera and light: straight-on eye-level 50mm photograph, one soft practical
reflection from the pavement, subdued natural grain.
Colour world: blue-black ground with one safety-orange reflected edge.
Composition: reserve the upper half as quiet negative space.
Constraints: no text, business names, house numbers, logos, watermark, people,
or second accent colour.
Preserve: the door must feel usable and owned, not abandoned or threatening.
```

### 2. Controlled edit — change one thing

Use only with a supplied, approved source image. Describe the delta first, then
list invariants. Do not ask a generator to recreate a customer identity from
memory.

```text
In the supplied image, change only [target].
Preserve: subject identity, pose, expression, camera position, framing,
background geometry, lighting direction, and every object except [target].
Integration: match perspective, contact shadows, texture, and colour
temperature to the original.
Constraints: no new text, logo, watermark, or additional subjects.
```

### 3. Brand object / transparent ingredient

Use for a visual ingredient that will be composed by Photoshop or Remotion.

```text
Create one original [object] for [campaign purpose].
Silhouette and material: [shape, surface, real wear or finish].
Framing: centered, generous padding, isolated on a transparent background.
Light: [single direction], with realistic contact shadow only if requested.
Constraints: no words, brand names, icons, watermark, scenery, or checkerboard.
```

## Video recipes

Short generative-video clips should be one physical shot, not a whole edit.
For image-to-video, the source image already establishes identity, framing,
composition, and colour. Prompt the **motion**, not a rewritten image
description.

### 4. Image-to-video — one physical event

```text
The [subject] [one precise action].
The [environment] [one responsive motion].
Camera: [locked / slow dolly / handheld track / slow pan] [direction].
Timing: [settles / begins still then moves / continuous slow motion].
Style: [naturalistic / restrained stop-motion / documentary], stable framing.
```

**FAMtastic example — platform-dependency plate**

```text
The blank brass letter slot catches a single moving reflection as a light rain
drifts across the threshold. The hanging sign bracket sways once and settles.
Camera: locked, straight-on. Timing: begins still, then the reflection and
rain move gently for one continuous shot. Style: restrained naturalistic
documentary motion, stable framing.
```

### 5. Presenter insert

Use an approved avatar or licensed performer. Keep the message in a script;
the visual-generation prompt only controls shot and delivery.

```text
Speaker: [approved presenter id], [wardrobe/setting already approved].
Delivery: [measured / warm / direct], with [one gesture] at [beat].
Camera: [medium close-up], [locked or subtle push-in], eye level.
Background behavior: [quiet, no readable signs, no moving distraction].
Continuity: preserve face, wardrobe, lighting, and set from the reference.
```

## Provider notes

| Provider / route | What to emphasize | What to avoid |
|---|---|---|
| Local Gemini Flash Lite plate worker | Ordered descriptive blocks; subject, context, style, physical camera/light; explicit negative space. | Generated typography; unsupported assumptions about exact output dimensions; treating a request label as evidence. |
| GPT Image | Four-part grammar; explicit invariants for edits; transparent-isolation language for ingredients. | Runaway unstructured prompts and identity reconstruction without a source image. |
| Runway image | Full visual sentences: subject, scene, composition, light, colour, focus, and mood. | Conversational instructions and negative-only wording. |
| Runway image-to-video | Source image for appearance; text for one subject motion, camera motion, and scene response. Start simple, then change one variable. | Multi-scene stories, abstract verbs, and several contradictory camera moves in a 5–10 second clip. |
| OpenArt | Treat it as a model router. Record the selected underlying model, image reference, and settings in the receipt. | Calling an output "OpenArt behavior" when it actually comes from a routed model with different rules. |
| HeyGen | Approved presenter plus exact script, shot framing, delivery, and continuity constraints. | Treating an avatar render as proof that a social post has been delivered. |

## Research sources and adaptation boundary

These sources informed the recipes. They are references, not copy-and-paste
prompt stock:

- [OpenAI GPT Image 2 model and prompting route](https://developers.openai.com/api/docs/models/gpt-image-2)
- [Google Imagen prompt guide](https://ai.google.dev/gemini-api/docs/imagen?authuser=2&hl=en)
- [Google image-generation prompt guide](https://ai.google.dev/gemini-api/docs/imagen-prompt-guide?authuser=1)
- [Runway Gen-4 Image Prompting Guide](https://help.runwayml.com/hc/en-us/articles/35694045317139-Gen-4-Image-Prompting-Guide)
- [Runway Gen-4 Video Prompting Guide](https://help.runwayml.com/hc/en-us/articles/39789879462419-Gen-4-Video-Prompting-Guide)
- [OpenArt image-generation overview](https://openart.ai/blog/how-to-generate-an-ai-image/)

The explicit FAMtastic adaptation is deliberate: provider documents may permit
or encourage generated text, but the FAMtastic plate workflow forbids it so
claims and typography remain controllable, accessible, and reviewable.

## Receipt minimum

No prompt becomes a reusable recipe merely because it sounds good. Add a
receipt record with:

- `recipe_id`, campaign, claim source, intended use, and prompt literal;
- provider, model/version, generation settings, input asset hashes, and cost;
- output asset path, mime type, dimensions, hash, and moderation/refusal state;
- human review: composition, claim safety, text-free result, likeness/rights,
  palette, format-safe area, and decision (`accepted`, `revise`, or `reject`).
