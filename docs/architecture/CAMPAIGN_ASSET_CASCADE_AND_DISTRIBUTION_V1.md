# Campaign Asset Cascade and Multi-Channel Distribution Standard v1

**Version**: `famtastic.campaign-cascade.v1`  
**Status**: Active Production Standard  
**Effective Date**: September 2, 2026  
**Audience**: Fritz, Creative Specialists, Video Engineers, and Distributing Agents  

---

## 1. The Strategy: Flagship Anchor $\rightarrow$ Multimodal Asset Cascade

Instead of buying 50 individual expensive assets or settling for low-quality generic outputs, FAMtastic operates a **3-Tier Asset Multiplier Engine**:

```
                                  TIER 1: FLAGSHIP ANCHOR SEED
                       (1 Flagship Paid Video or Ultra-High-Res Art Run)
                                 e.g., HeyGen 30s Presenter / OpenArt Flagship
                                                │
                                                ▼
                                TIER 2: GOOGLE MULTIMODAL MULTIPLIER
                       (Gemini 2.0 Flash / Pro & Developer Interactions API)
                    • Deconstruct video frames into high-res character stills
                    • Extract lighting, color palette tokens & stylistic embeddings
                    • Generate 30+ niche character variants (Barber, Nail Tech, Auto, Pop-up)
                    • Generate multi-channel script adaptations & hook variations
                                                │
                                                ▼
                                TIER 3: LOCAL DETERMINISTIC ASSEMBLY
                            (MoneyPrinterTurbo + Remotion + Adobe Suite)
                    • MoneyPrinterTurbo: Fast vertical short video stitches with Edge-TTS
                    • Remotion: Pixel-perfect branded kinetic typography & safe-area rendering
                    • Photoshop / Premiere: Layered comps, batch crops (1:1, 9:16, 16:9), and final master cuts
```

---

## 2. Cloud vs. Machine Infrastructure Division

| Tool / Service | Execution Domain | Bounded Responsibility | Cost / Entitlement Profile |
|---|---|---|---|
| **HeyGen Avatar API** | Cloud Provider | Flagship 30s avatar presenter commercial anchor | Paid Tier (Used as Master Anchor) |
| **Google Gemini API** | Cloud (GCP) | Multimodal decomposition, script variation, 30-image prompt cascade | High-speed, high-token efficiency |
| **OpenAI / OpenArt** | Cloud Provider | Benchmark master brand graphics | Provider-routed |
| **MoneyPrinterTurbo** | Local Machine | Rapid vertical video synthesis, voiceover pacing, and subtitle burning | $0 / Local Python Runtime |
| **Remotion Video** | Local Machine | Deterministic React-rendered branded kinetic video loops | $0 / Local Node.js Runtime |
| **Adobe Suite (PS/PR/AE)**| Local Studio | Master PSD layout comps, high-fidelity color grading, multi-format cropping | Local Desktop Studio |
| **Drupal + React** | Production Hosting | Lead capture, UTM snapshot, Commerce checkout, and Client Portal | Canonical Web Infrastructure |

---

## 3. Recipe Tracking & Prompt Ledger Contract

Every asset cascade must log its recipe in the campaign manifest:
```json
{
  "recipe_id": "booksy_escape_cascade_v1",
  "seed_asset": "01-hair-beauty-booksy-escape-ad.jpg",
  "seed_provider": "openai_image",
  "derived_assets": [
    {
      "asset_id": "04-nail-salon-booksy-escape-ad.jpg",
      "generator_model": "gemini-flash-lite:cloud",
      "prompt_delta": "Change salon environment to nail tech studio with velvet green seating and acrylic display; retain dark obsidian background and chartreuse #7CFC00 glowing badge tokens.",
      "cost_usd": 0.0003
    }
  ]
}
```

---

## 4. Multi-Channel Distribution Protocol (5 Channels + Blog)

Every published campaign must simultaneously execute across all 5 channels with stable UTM tracking:

1. **Blog (`famtasticdesigns.com/blog/:slug`)**: Canonical educational pillar with dynamic social share buttons.
2. **Facebook Feed**: 1:1 image or 16:9 video + problem-agitation-solution long-form copy + link preview.
3. **X (Twitter)**: 3-part punchy thread + 9:16 video clip + direct link.
4. **TikTok**: 9:16 vertical video + on-screen pattern interrupt text + trending hashtag cluster + bio CTA.
5. **YouTube**: 16:9 Long-form explainer + 9:16 Shorts cut + SEO description + pinned comment link.
6. **Instagram**: 9:16 Reel + 4-slide 1:1 carousel graphic + bio link.
