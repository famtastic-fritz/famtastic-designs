# Master AI Creative Platforms & Tooling Comparison Matrix (2026)

**Date**: September 2, 2026  
**Status**: Canonical Production Evaluation  
**Audience**: Fritz, Creative Engineers, Autonomous Agents  
**Location**: `docs/research/2026-09-02-ai-creative-platforms-comparison-matrix.md`  

---

## 1. Complete Image Generation Platforms Comparison

| Platform / Model | Best Use Case | Strengths | Limitations | Cost / Economics | Access Method in FAMtastic |
|---|---|---|---|---|---|
| **Google Imagen 3** (`imagen-3.0-generate-002`) | Commercial product ads, clean photography, in-image typography | • Unbelievable lighting & texture<br>• Best-in-class text rendering<br>• High prompt adherence | • Slower than Flash Lite | ~$0.030 – $0.040 / image | **Direct Google API** (`FAMtastic.Gemini.Image` in Keychain) |
| **Google Gemini 3.1 Flash Lite** | High-volume niche multiplier & prompt deltas | • Ultra-fast (sub-2s latency)<br>• Incredible consistency with seed anchors<br>• Pennies for dozens of variations | • 1K resolution baseline (scale via canvas) | **$0.0336 / image** ($1.00 = 30 variants) | **Direct Google API** (`generate-booked-branded-gemini-reference-images.mjs`) |
| **OpenAI GPT Image 2** | Luxury brand hero shots, fashion, UI icons, creative composition | • High editorial aesthetic<br>• Multilingual graphic layouts<br>• Precise control over fine details | • Moderate credit cost on aggregator platforms | ~15–20 credits on OpenArt (~$0.15/ea) | **OpenArt MCP** (`gpt-image-2`) |
| **Midjourney v6.1** | Artistic direction, mood boards, editorial magazine aesthetic | • Unmatched cinematic mood and lighting<br>• Deep skin texture nuance<br>• Highly stylized aesthetics | • No native API (Discord/Web UI or unofficial wrapper)<br>• Text rendering still trails Imagen 3 | $10–$60 / month subscription | Web UI / Prompt Cookbook |
| **Adobe Firefly Image 3** | Brand-safe enterprise assets, vector art, Photoshop layers | • 100% commercially cleared (trained on Adobe Stock)<br>• Native Photoshop JSX layer integration<br>• Vector SVG generation | • Less stylized than Midjourney | Included in Creative Cloud / Firefly credits | **Photoshop JSX Batch Exporter** |
| **Google Nano Banana Pro** | In-image text posters, ads, multi-subject character blends | • Native 4K detail<br>• Multi-subject consistency (up to 5 people)<br>• Multi-turn editing | • Higher compute cost | ~25–35 credits on OpenArt | **OpenArt MCP** (`nano-banana-pro`) |

---

## 2. Complete Video Generation Platforms Comparison

| Platform / Model | Best Use Case | Strengths | Limitations | Cost / Economics | Access Method in FAMtastic |
|---|---|---|---|---|---|
| **HeyGen Avatar Studio v3** | Direct-to-camera sales pitches, explainer ads, founder messages | • Photoreal talking human presenters<br>• Synced facial lip-movement & natural gestures<br>• Direct script-to-video workflow | • Talking-head format (not general cinematic B-roll) | Subscription credits (e.g. 15–30 credits/mo) | **HeyGen CLI** (`~/.local/bin/heygen` via OAuth) |
| **Google Veo (Veo 2)** | Cinematic commercial scenes, complex camera sweeps, brand b-roll | • Native 1080p/4K resolution<br>• Realistic physics and lighting consistency<br>• Complex camera motion control | • Cloud compute queue latency | Vertex AI API / AI Studio | **Google Cloud Vertex AI** |
| **Kling 3 Omni / Kling 1.5** | High-energy commercial footage, human motion, cinematic b-roll | • Outstanding human motion dynamics<br>• 4K output with extreme detail<br>• High stability across scene transitions | • Heavy credit consumption | ~30–50 credits on OpenArt | **OpenArt MCP** (`kling-3-omni`) |
| **OpenAI Sora** | High-concept surrealism, cinematic physics, long cohesive shots | • Deep understanding of 3D space and physics<br>• Multi-character interaction<br>• High visual fidelity | • API access rollouts gated<br>• Higher cost per second | OpenAI API / ChatGPT Plus/Pro | OpenAI API |
| **Runway Gen-3 Alpha** | Director-level camera control (pan, tilt, zoom, speed), VFX | • Precise motion brush & camera control<br>• Text-to-video and image-to-video<br>• High cinematic color grading | • Can struggle with fast fine-motor human hands | ~$0.05 – $0.10 / second | Web API / Runway Studio |
| **ByteDance Seedance 2.5** | Multi-shot storytelling, AI short commercials with dialogue | • Up to 30-second single-shot clips<br>• Synchronized dialogue, lip-sync, and BGM<br>• Large reference budget (up to 30 images) | • Rendered at 1080p | Medium credit cost | **OpenArt MCP** (`byte-plus-seedance-2-5`) |
| **Luma Dream Machine** | Rapid camera fly-throughs, dynamic product spins | • Smooth camera motion<br>• Fast rendering speed | • Occasional morphing on complex objects | ~$0.20 – $0.50 / clip | Luma API / Web Studio |
| **Local Engine (MoneyPrinterTurbo)** | Automated short-form video assembly (TikTok/Reels/Shorts) | • $0 cost per video<br>• Automated B-roll stitching + Edge-TTS + Subtitles<br>• Runs 100% locally on Mac | • Ken Burns pan/zoom over stills or stock clips | **$0.00 (Free / Unlimited)** | **Local Tool** (`tools/MoneyPrinterTurbo`) |
| **Local Engine (Remotion)** | Deterministic 60fps kinetic motion graphics & UI cards | • Programmatic React-based video generation<br>• Pixel-perfect branding, single-glow halo tokens<br>• Instant batch rendering | • Requires code templates | **$0.00 (Free / Unlimited)** | **Local Tool** (`marketing/video/`) |

---

## 3. Recommended Multi-Platform Architecture for FAMtastic

```
                                  CREATIVE INPUT / BRIEF
                                            │
        ┌───────────────────────────────────┴───────────────────────────────────┐
        ▼                                                                       ▼
 [STATIC VISUAL PIPELINE]                                               [VIDEO PRODUCTION PIPELINE]
 1. Anchor Key Visual:                                                  1. Direct Sales / Presenter Commercial:
    • Google Imagen 3 OR OpenArt GPT Image 2                               • HeyGen Avatar Studio (Shay/Kenji)
 2. Niche Multiplier (15–30 variants):                                  2. Cinematic B-Roll & Scenes:
    • Google Gemini 3.1 Flash Lite ($0.0336/ea)                           • Google Veo OR Kling 3 Omni (OpenArt)
 3. Safe-Zone Crops & Typography:                                       3. Automated Social Short-Form Stitcher:
    • Photoshop JSX Batch Exporter ($0.00)                                 • MoneyPrinterTurbo ($0.00)
                                                                        4. Kinetic UI & Branding Overlays:
                                                                           • Remotion 60fps React Layers ($0.00)
```

---

## 4. Key Takeaways & Strategic Recommendation

1. **Leverage Google Imagen 3 & Gemini Flash Lite for Imagery**:
   Google gives us the best price-to-performance ratio on the market ($0.0336 vs $0.20+), while matching or beating DALL-E 3 on prompt adherence and photorealism.
2. **Combine HeyGen (Presenter) + Kling/Veo (Cinematic Scenes)**:
   Use HeyGen when a human presenter must look directly at the viewer to explain the $199 Web Basics offer, and use Kling 3 / Veo when we need cinematic action scenes of the barber shop, auto garage, or bustling bakery.
3. **Keep Local Assembly at $0**:
   Always assemble the final social cuts using MoneyPrinterTurbo, Remotion, and Photoshop JSX to keep our unit cost per campaign under $2.00 total.
