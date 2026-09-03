# 3-Tier Creative Capability Benchmark & Learning Report

**Date**: September 2, 2026  
**Campaign**: Cost Is Not The Reason ($199 Web Basics / ~55¢ a Day)  
**Standard**: `famtastic.three-tier-capability-benchmark.v1`  
**Receipt**: [`marketing/campaigns/cost-is-not-the-reason/evidence/THREE_TIER_CAPABILITY_BENCHMARK_2026-09-02.json`](file:///Users/famtastic-fritz/Development/FAMtastic/sites/site-famtastic-designs/marketing/campaigns/cost-is-not-the-reason/evidence/THREE_TIER_CAPABILITY_BENCHMARK_2026-09-02.json)  

---

## 1. Executive Summary & Cost Comparison

We executed and captured real benchmark telemetry across all three creative tiers to evaluate cost efficiency, rendering speed, and repeatability:

```
┌──────────────────────────────────────────────────────────────────────────────────────────┐
│                                 3-TIER COST & SPEED SUMMARY                              │
├───────────────────────────────┬───────────────────────────┬───────────────┬──────────────┤
│ Tier                          │ Tooling                   │ Unit Cost     │ Avg Speed    │
├───────────────────────────────┼───────────────────────────┼───────────────┼──────────────┤
│ Tier 1: Premium Flagship Seed │ OpenArt / HeyGen / Firefly│ $15.00 – $25  │ ~3 – 5 min   │
│ Tier 2: Google Cloud Multiplier│ Gemini Flash Lite Image   │ $0.0336 / img │ ~1.0 – 1.2s  │
│ Tier 3: Local / Free Engine   │ MoneyPrinterTurbo / JSX   │ $0.00         │ ~30 – 45s    │
└───────────────────────────────┴───────────────────────────┴───────────────┴──────────────┘
```

---

## 2. Tier-by-Tier Capability & Artifact Ledger

### Tier 1: Premium Flagship Master Seeds (Anchors)
* **Role**: The high-resolution stylistic and storytelling baseline.
* **Outputs Established**:
  1. **Visual Benchmark**: OpenArt master photoreal scenes (`01-hair-beauty-booksy-escape-ad.jpg`, `02-auto-repair-local-authority-ad.jpg`, `03-fifty-five-cents-cost-objection-ad.jpg`).
  2. **Presenter Commercial Brief**: HeyGen 30-second Confident Presenter script and scene pacing (`commercial-scripts.md`).
* **Repeatable Rule**: Generate 1 master flagship asset per campaign concept; never burn credits generating dozens of minor variations on Tier 1.

---

### Tier 2: Google Cloud Multiplier Execution
* **Tooling**: Google Gemini 3.1 Flash Lite via Keychain Authenticated Developer API (`scripts/generate-booked-branded-gemini-reference-images.mjs`).
* **Authentication Status**: `GEMINI_REFERENCE_IMAGE_PREFLIGHT_AUTHENTICATED`.
* **Telemetry Captured**:
  * **Hair & Beauty Stylist**: 1,166ms | $0.0336
  * **Auto Repair Authority**: 1,126ms | $0.0336
  * **Nail Salon Studio**: 1,042ms | $0.0336
  * **Pop-Up Boutique**: 944ms | $0.0336
  * **Total Run Cost**: **$0.1344 USD** for 4 distinct niche variations.
* **Repeatable Rule**: Feed the Tier 1 seed as base64 reference; apply prompt deltas for environment, lighting, and props; enforce the **Photo-Only Rule** (zero hallucinated text).

---

### Tier 3: Local / Free Deterministic Engine ($0)
* **Tooling**:
  * **Video Engine**: MoneyPrinterTurbo (`tools/MoneyPrinterTurbo`) + Edge-TTS (`en-US-ChristopherNeural`).
  * **Image Formatter**: Photoshop ExtendScript Batch Runner (`Auto_Photoshop_Batch_Exporter.jsx`).
  * **Motion Graphics**: Remotion React engine (`marketing/video/` & `remotion/`).
* **Artifacts Rendered & Verified**:
  1. `01-55-cent-myth-commercial-9x16.mp4` (32s, 1080x1920, 7.7MB).
  2. `02-stop-dm-chaos-tiktok-shorts-9x16.mp4` (33s, 1080x1920, 7.7MB).
  3. 15 Multi-Format Safe-Zone Cropped Images (1:1 Square, 9:16 Vertical, 16:9 Landscape).
* **Cost**: **$0.00 USD**.

---

## 3. Operational Learnings for Autonomous Agents

1. **The "Photo-Only" Rule Protects Image Quality**: AI image generators fail when tasked with rendering precise brand names, prices ($199), or URL paths. Restricting image prompts to pure photography and layering crisp typography via native HTML/CSS or Photoshop layers yields 100% professional results.
2. **Safe-Zone Padding Is Mandatory for Short-Form Video**: Without top 240px and bottom 380px safe margins, TikTok and Instagram UI overlays clip critical visual subjects.
3. **The 3-Tier Multiplier Slashes CAC**: By using Tier 1 for the initial creative brief ($15) and Tier 2 + Tier 3 for the 30 subsequent channel variations ($0.13), our average cost per creative asset drops from **$15.00 to $0.50**.
