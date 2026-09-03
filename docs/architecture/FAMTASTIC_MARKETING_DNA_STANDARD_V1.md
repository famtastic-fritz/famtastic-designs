# FAMtastic Marketing DNA Standard v1

**Version**: `famtastic.marketing-dna.v1`  
**Status**: Canonical Standard  
**Effective Date**: September 2, 2026  
**Audience**: Fritz, Creative Agents, Video Editors, Content Writers, and Campaign Operators  

---

## 1. Core Philosophy: The FAMtastic Standard

**FAMtastic (adj.)**:  
> *"Fearless deviation from established norms with a bold and unapologetic commitment to stand apart on purpose, applying mastery of craft to the point that the results are the proof, and manifesting the extraordinary from the ordinary."*

### The F-A-M Letter Breakdown:
* **F — Fearless**: Fearless deviation from established norms with a bold and unapologetic commitment to stand apart on purpose. *(Motto: Boldly different, on purpose.)*
* **A — Applying Mastery**: Applying mastery of craft to the point that the results are the proof. *(Motto: Demonstration, not declaration.)*
* **M — Manifesting**: Manifesting the extraordinary from the ordinary. *(Motto: Turning the common into the remarkable.)*

FAMtastic Designs does not sell generic templates or abstract "digital transformation." We engineer **connected digital business systems** that replace fragmented tools, eliminate third-party middleman fees, and turn web presence into an owned revenue asset.

Every campaign item must follow the **Governing Demand Model**:
$$\text{Proven Capability} \longrightarrow \text{Customer Pain Point} \longrightarrow \text{Visceral Demonstration} \longrightarrow \text{Irresistible Packaged Offer} \longrightarrow \text{Zero-Friction Intake}$$

---

## 2. Universal Visual & Aspect Ratio Standards

To prevent visual cut-offs, awkward crops, or unreadable text on mobile apps, every asset must adhere strictly to the **Safe Zone Matrix**:

```
 1:1 Square (1080x1080)       9:16 Vertical (1080x1920)         16:9 Landscape (1920x1080)
┌───────────────────────┐   ┌───────────────────────────┐   ┌───────────────────────────────────┐
│     Padding: 60px     │   │  Top Safe Zone: 240px     │   │         Padding: 80px             │
│  ┌─────────────────┐  │   │ ┌───────────────────────┐ │   │ ┌───────────────────────────────┐ │
│  │   Active Hero   │  │   │ │                       │ │   │ │         Cinematic 16:9        │ │
│  │     Content     │  │   │ │      Visual Core      │ │   │ │            Content            │ │
│  └─────────────────┘  │   │ │                       │ │   │ └───────────────────────────────┘ │
│                       │   │ └───────────────────────┘ │   │                                   │
└───────────────────────┘   │  Bottom Safe Zone: 380px  │   └───────────────────────────────────┘
                            └───────────────────────────┘
```

### Channel Format Rules:
1. **9:16 Vertical (1080x1920px)** — TikTok, Instagram Reels, YouTube Shorts:
   * **Top Safe Margin**: 240px (clears app search bar, audio tags, camera cutouts).
   * **Bottom Safe Margin**: 380px (clears TikTok captions, sound name, share icons, and profile handles).
   * **Side Safe Margins**: 80px on left and right.
   * **Rule**: Master text and primary subjects MUST sit within the center `1080x1300px` bounding box. Never place critical text in the bottom 25% of the frame.
2. **1:1 Square Feed (1080x1080px)** — Instagram & Facebook Feed:
   * **Safe Margin**: 60px all sides.
   * Bold, high-contrast headings with maximum 7 words per slide.
3. **16:9 Landscape (1920x1080px)** — YouTube Long-Form & Web Banners:
   * Safe title area: 80px margin all sides. Minimum 48pt font for on-screen text.

---

## 3. The 4-Beat Commercial Video Standard

Every short-form video (15s–60s) produced across MoneyPrinterTurbo, HeyGen, or Remotion must follow the **4-Beat Arc**:

| Beat | Timestamp | Structural Function | Psychological Trigger |
|---|---|---|---|
| **Beat 1: The Hook** | `0:00 – 0:03s` | Break the scroll with a provocative claim, physical object, or harsh truth. | Disruption / Pattern Interrupt |
| **Beat 2: The Agitation** | `0:03 – 0:10s` | Expose the hidden cost of the status quo (fees, lost clients, `@gmail.com` trust gap). | Loss Aversion / Frustration |
| **Beat 3: The Mechanism** | `0:10 – 0:22s` | Introduce the solution with crystal-clear math ($199/yr = 55¢/day) and 3-direction proof. | Relief / Logic / Feasibility |
| **Beat 4: The Call to Action** | `0:22 – 0:30s` | One single, unambiguous next step (`famtasticdesigns.com`). | Low Friction Action |

---

## 4. The Brand Style & Color Palette Tokens

All visual assets must enforce the FAMtastic dark luxury palette:
* **Obsidian Charcoal (`#070907` / `#0D100D`)**: Primary backgrounds and deep canvas depth.
* **Slate Glass Surface (`#141814` / `#1A201A`)**: Card containers with 1px border (`#252B25`).
* **Signature Electric Lime (`#7CFC00`)**: Primary accent, badges, buttons, glow halos (`box-shadow: 0 0 24px rgba(124, 252, 0, 0.35)`).
* **Pure Crisp White (`#FFFFFF`)**: Primary headlines and high-contrast typography.
* **Muted Platinum (`#A3A3A3`)**: Secondary descriptions and metadata.

---

## 5. The Campaign Manifest Standard (`famtastic.campaign-manifest.v1`)

Every campaign must be registered as a version-controlled manifest containing:
1. `campaign_id` (kebab-case unique identifier).
2. `primary_offer` (SKU, price, billing term, and daily rate equivalent).
3. `audiences` (specific customer niches and explicit pain points).
4. `creative_assets` (file path, aspect ratio, safe-zone certified status).
5. `video_scripts` (beat breakdown, channel targets, audio transcription).
6. `articles` (pillar and spoke slug map with internal linking requirements).
7. `utm_parameters` (standardized tracking parameters for GA4 and Drupal leads).
