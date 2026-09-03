---
name: gpt-image-prompt-architect
description: Master prompt engineering, parameter optimization, and constraint architecture for GPT Image Generation models (gpt-image-2, gpt-image-1.5, gpt-image-1-mini). Use when generating, editing, localizing, or compositing production-grade visuals, infographics, ads, UI mockups, logos, comics, and photorealistic imagery.
---

# GPT Image Prompt Architect & Visual Engineering Standard

This skill codifies the complete prompting patterns, constraint architectures, and production workflows for OpenAI's `gpt-image` models (`gpt-image-2`, `gpt-image-1.5`, `gpt-image-1-mini`).

---

## 1. The 4-Part Structural Prompt Grammar

Never write unstructured, runaway image prompts. Every production prompt must follow this 4-part architectural order:

```
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                          4-PART PROMPT GRAMMAR HIERARCHY                               │
├────────────────────────────────────────────────────────────────────────────────────────┤
│ 1. SCENE & CONTEXT       │ Environment, time of day, atmosphere, audience "mode"      │
│ 2. SUBJECT & ACTION      │ Primary subject, physical pose, gaze direction, tool/object │
│ 3. TECHNICAL & STYLISTIC │ Medium, optics/focal length, lighting, texture, materials   │
│ 4. CONSTRAINTS & INVARIANTS│ Verbatim text in quotes, exclusions, what NOT to change   │
└────────────────────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Model Parameters & Operating Envelopes

| Parameter | Recommended Setting | Production Rules |
|---|---|---|
| **Model** | `gpt-image-2` | Default for highest-quality generation, editing, text-heavy images, compositing, and identity preservation. |
| **Model (Mini)** | `gpt-image-1-mini` | High-volume ideation, draft exploratory batches, low-latency previews. |
| **`outputQuality`** | `low`, `medium`, `high` | Use `low` for high-speed experimentation. Use `high` for small text, dense infographics, and close-up portraits. |
| **`background`** | `"transparent"` | Preview feature for `gpt-image-2`. Requires `output_format="png"` or `"webp"`. Explicitly state: *"isolated on a fully transparent background with no solid backdrop, checkerboard, or unwanted shadows."* |
| **Resolution Constraints** | Multiples of 16 | Max edge `< 3840px`. Min total pixels: `655,360`. Max total pixels: `8,294,400`. Ratio $\le 3:1$. Recommended 2K sweet spot: `2560x1440` (16:9), `1024x1536` (portrait), `1360x1360` (square). |

---

## 3. The 10 Core Production Blueprints

### Blueprint 1: High-Impact Direct Response Ads
* **Formula**: Creative brief framing $\rightarrow$ Target audience culture $\rightarrow$ Exact verbatim tagline $\rightarrow$ Clean composition with negative space.
* **Prompt Template**:
  ```text
  Create an in-culture commercial ad for [Brand Name].
  Target Audience: [Specific customer demographic and lifestyle].
  Scene: [Authentic real-world interaction, natural poses, dynamic lighting].
  Tagline (EXACT, verbatim, rendered once): "[Exact Headline Text]"
  Typography: Clean, bold modern sans-serif, high contrast, perfectly integrated into layout.
  Constraints: No extra text, no watermarks, no unrelated logos. Left side composed with clean negative space for layout copy.
  ```

### Blueprint 2: Photorealism That Feels Natural
* **Formula**: Candid language $\rightarrow$ Camera optics $\rightarrow$ Imperfection cues (pores, fabric wear, natural grain) $\rightarrow$ Exclusion of artificial CGI gloss.
* **Prompt Template**:
  ```text
  Photorealistic candid photograph of [Subject with specific age, expression, and attire].
  Action: [Natural, unposed physical action, gaze directed at work/tool, not camera].
  Optics & Lighting: Shot on 35mm camera, 50mm f/1.8 lens, shallow depth of field, natural soft daylight, subtle film grain.
  Imperfections: Real human skin texture with visible pores, subtle wrinkles, worn work clothing, and tactile material grain.
  Constraints: Honest and unposed. No studio gloss, no heavy retouching, no CGI sheen.
  ```

### Blueprint 3: Clean Scalable Logos (Transparent Alpha)
* **Formula**: Brand personality $\rightarrow$ Vector-like geometric silhouette $\rightarrow$ High negative space $\rightarrow$ Transparent alpha isolation.
* **Prompt Template**:
  ```text
  Original, non-infringing logo for [Company Name], a [Business Type].
  Personality: [e.g., Warm, modern, architectural, timeless].
  Style: Clean vector mark, strong silhouette, balanced negative space, minimal strokes, flat design.
  Background: Fully transparent background. Deliver a single centered mark with generous padding, clean alpha edges, and no solid backdrop, scenery, checkerboard, or watermark.
  ```

### Blueprint 4: Infographics & Structured Systems
* **Formula**: Target audience $\rightarrow$ Flow progression (Step 1 to Step N) $\rightarrow$ Labeled components $\rightarrow$ High-contrast clarity.
* **Prompt Template**:
  ```text
  Detailed technical infographic titled "[Title]" for [Audience].
  Content Flow: Clearly visualize the end-to-end flow from [Input A] to [Process B] to [Output C].
  Labels: Clean arrows connecting nodes, exact technical labels: [List of Terms].
  Visual Language: Modern minimal aesthetic, white or dark slate background, clear data hierarchy, easy-to-read typography.
  Constraints: No decorative clutter, no illegible tiny text, high-contrast labels only.
  ```

### Blueprint 5: Story-to-Comic Pacing (Sequential Multi-Panel)
* **Formula**: Multi-panel specification $\rightarrow$ Concrete action beats per panel $\rightarrow$ Character consistency.
* **Prompt Template**:
  ```text
  Vertical comic strip with [N] equal-sized panels telling a sequential story:
  Panel 1: [Initial status quo & subtle tension].
  Panel 2: [Inciting action & shift in posture].
  Panel 3: [The transformation / chaotic or extraordinary climax].
  Panel 4: [Resolution & return to calm / master state].
  Style: [Consistent illustration style], expressive character acting, clean panel borders.
  ```

### Blueprint 6: Realistic UI Mockups
* **Formula**: Describe as already shipped $\rightarrow$ Concrete layout hierarchy $\rightarrow$ Real copy/data $\rightarrow$ Device framing.
* **Prompt Template**:
  ```text
  Realistic mobile UI mockup for [App / Platform Name].
  Screen Content: Header with [Brand], main section displaying [Feature Cards with real metrics], quick action button for [Action], and clean navigation bar.
  Aesthetic: Clean white/dark mode background, refined typography (Inter), subtle border glows, and polished spacing.
  Framing: Displayed inside a modern bezel-less smartphone frame.
  ```

### Blueprint 7: Surgical Image Editing ("Change ONLY X, Preserve Y")
* **Formula**: State the surgical delta $\rightarrow$ Explicit list of invariants (what must not change) $\rightarrow$ Contact physics & shadow integration.
* **Prompt Template**:
  ```text
  In this input image, replace ONLY [Target Object/Clothing] with [New Object/Clothing].
  Preserve Invariants: Keep subject identity, facial features, pose, skin tone, background, camera angle, and room lighting 100% unchanged.
  Integration: Match contact shadows, fabric drape, and color temperature to the original photo so the edit integrates photorealistically.
  Constraints: Do not change any other element, do not add text or watermarks.
  ```

### Blueprint 8: Multi-Image Referencing & Transplanting
* **Formula**: Reference by index ("Image 1", "Image 2") $\rightarrow$ State source and target destination $\rightarrow$ Match lighting and scale.
* **Prompt Template**:
  ```text
  Transplant [Element X] from Image 2 into the setting of Image 1, placing it [exact position relative to Subject in Image 1].
  Preserve Invariants: Keep Image 1's scene, background, framing, and subject completely unchanged.
  Integration: Match Image 1's lighting angle, color temperature, perspective, and shadow behavior so the composite looks captured in one shot.
  ```

### Blueprint 9: Character Consistency Anchor Workflow
* **Step 1 (Anchor)**: Lock character features (face, hairstyle, outfit, proportions, tone) against a neutral background.
* **Step 2 (Continuation)**: Reuse Image 1 as input, describe the new action and scene, and repeat the invariant checklist:
  * *"Same character: exact same face, hair, outfit, and body proportions. Do not redesign the character."*

### Blueprint 10: Environmental & Lighting Restaging
* **Formula**: Change only atmospheric physics $\rightarrow$ Lock geometry and camera placement.
* **Prompt Template**:
  ```text
  Restage this image to look like [New Environment / Time of Day, e.g., a golden hour sunset / rainy winter dusk].
  Change ONLY: Lighting quality, light direction, sky color, atmosphere, precipitation, and ground reflections.
  Preserve: Exact camera perspective, subject geometry, building placement, and scene layout.
  ```
