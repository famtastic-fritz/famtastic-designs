# Campaign Equivalence Matrix & Asset Recipe Ledger

**Campaign**: Cost Is Not The Reason ($199 Web Basics / ~55¢ a Day)  
**Standard**: `famtastic.marketing-dna.v1`  
**Purpose**: Map every Premium Flagship Asset (OpenArt, HeyGen, Adobe) directly to its fractional-cost Google Multiplier and $0 Local Free Equivalent.

---

## 1. Master Creative Equivalence Matrix

| Asset Category | Tier 1: Premium Flagship (Anchor) | Tier 2: Google Cloud Multiplier (~$0.03 / call) | Tier 3: Local / Free Engine ($0 / Unlimited) |
|---|---|---|---|
| **Static Ad Visuals** | **OpenArt AI / Adobe Firefly**<br>• Photoreal master scenes<br>• High-res visual benchmark | **Gemini 3.1 Flash Lite Image**<br>• `generate-booked-branded-gemini-reference-images.mjs`<br>• Reference image + prompt delta<br>• Cost: ~$0.0336 / image | **Local Canvas / Photoshop JSX Batch Exporter**<br>• `Auto_Photoshop_Batch_Exporter.jsx`<br>• Native HTML/CSS typography overlay<br>• Cost: $0 |
| **Talking Presenter Video** | **HeyGen Avatar Studio**<br>• Confident Presenter delivering 30s script<br>• Photo-realistic lip sync | **Gemini Multimodal Video Breakdown**<br>• Frame-by-frame analysis & timing markers<br>• Script tone adaptations | **MoneyPrinterTurbo + Edge-TTS**<br>• High-hook vertical cuts (9:16)<br>• Christopher/Andrew neural voices<br>• Burned chartreuse subtitles (`#7CFC00`)<br>• Cost: $0 |
| **Motion Graphics & B-Roll** | **Adobe After Effects / Premiere**<br>• Master compositing & timeline cuts | **HyperFrames (HeyGen Upstream)**<br>• Programmatic motion frame builder<br>• Kinetic lower thirds | **Remotion (React Video Engine)**<br>• `marketing/video/` & `remotion/`<br>• Deterministic React animations<br>• Cost: $0 |
| **Voiceover Synthesis** | **ElevenLabs / HeyGen Studio Voice** | **Google Cloud Text-to-Speech** | **Edge-TTS (`msedge-tts`)**<br>• Integrated in MoneyPrinterTurbo<br>• Cost: $0 |

---

## 2. Test Execution & Recipe Tracking Contract

Every asset variant produced across these 3 tiers is tracked with its exact inputs, prompt, seed hash, and unit cost:

### Recipe 1: 55¢ Objection Buster (Visual Stills)
* **Tier 1 (Premium Benchmark)**: OpenArt / Firefly Master Photoreal Scene.
* **Tier 2 (Gemini Multiplier)**:
  * Script: `node scripts/generate-booked-branded-gemini-reference-images.mjs`
  * Model: `gemini-3.1-flash-lite-image`
  * Status: Authenticated (`GEMINI_REFERENCE_IMAGE_PREFLIGHT_AUTHENTICATED`)
  * Prompt: Clean commercial photography, generous negative space, zero hallucinated text.
* **Tier 3 (Local Assembly)**:
  * Photoshop Batch Runner generates 1:1, 9:16, 16:9 safe-zone cropped masters with native HTML typography overlay.

### Recipe 2: "Stop DM Chaos" Commercial (Video)
* **Tier 1 (Premium Benchmark)**: HeyGen 30s Presenter Commercial.
* **Tier 2 (HyperFrames)**: Programmatic motion graphics for the 3-direction proof cards (Safe, Wild, OMG).
* **Tier 3 (MoneyPrinterTurbo)**:
  * Script: *"Stop answering price questions in the DMs at 11 PM..."*
  * Output: `marketing/campaigns/cost-is-not-the-reason/videos/02-stop-dm-chaos-tiktok-shorts-9x16.mp4`
  * Cost: $0 (Edge-TTS Christopher voice + FFmpeg stitching + burned subtitles).

---

## 3. The 5-Channel Distribution Readiness

With both the Premium anchors and their free/cheap equivalents mapped, the campaign is fully packaged to run across:
1. **Facebook Feed** (1:1 Square Ad + Long-Form Conversion Copy).
2. **X / Twitter** (3-Tweet Thread + 9:16 Video Clip).
3. **TikTok** (9:16 High-Hook Video + Trending Hashtags).
4. **YouTube** (16:9 Long-Form Explainer + 9:16 Shorts Cut).
5. **Instagram** (9:16 Reel + 4-Slide 1:1 Carousel).
6. **Blog** (`famtasticdesigns.com/blog/why-running-business-on-gmail-and-linktree-costs-revenue`).
