# FAMtastic Master Intelligence, Tools & Creative Cookbooks Guide

**Date**: September 2, 2026  
**Status**: Canonical Master Reference  
**Audience**: Fritz, Creative Engineers, Autonomous Agents, and Production Operators  
**Location**: `docs/research/2026-09-02-famtastic-master-intelligence-notebook.md`  

---

## 1. The Core Definition & F-A-M Letter Breakdown

### FAMtastic (adj.):
> **"Fearless deviation from established norms with a bold and unapologetic commitment to stand apart on purpose, applying mastery of craft to the point that the results are the proof, and manifesting the extraordinary from the ordinary."**

### The Letter Breakdown (F-A-M):
* **F — Fearless**:
  * *Principle*: Fearless deviation from established norms with a bold and unapologetic commitment to stand apart on purpose.
  * *Motto*: **Boldly different, on purpose.**
* **A — Applying Mastery**:
  * *Principle*: Applying mastery of craft to the point that the results are the proof.
  * *Motto*: **Demonstration, not declaration.**
* **M — Manifesting**:
  * *Principle*: Manifesting the extraordinary from the ordinary.
  * *Motto*: **Turning the common into the remarkable.**

### The 4 Immutable Business Rules:
1. **Never Build on Rented Land**: Third-party marketplaces and booking apps take 20–30% platform cuts and hold customer lists hostage. Every business must own their custom domain, customer database, and booking funnel.
2. **The 3-Direction Proof Standard**: Every customer receives 3 bespoke interactive design directions in 48 hours: **Safe** (Clean/Modern), **Wild** (Creative/Bold), and **OMG** (Luxury/State-of-the-Art).
3. **Cost Is Never The Reason**: We invest into the business upfront with the **$199 Web Basics Bundle** (~55¢ a day for the entire first year), including managed cloud hosting and custom domain connection.
4. **Zero Fluff & Pure Unit Economics**: Every tech choice, software recommendation, and design decision must be mathematically justified by transparent unit economics.

---

## 2. Site Studio Architecture: Legacy vs. vNext

```
                               SITE STUDIO EVOLUTION
                                         │
        ┌────────────────────────────────┴────────────────────────────────┐
        ▼                                                                 ▼
[Legacy Architecture]                                            [vNext Architecture]
• Monolithic Drupal/Theme dependencies                           • Decoupled headless React frontend (Vite)
• Tight server coupling & manual edits                           • Automated 48-hour 3-direction proof packets
• Fragile CSS overrides across templates                        • Design DNA v1 Machine-Readable Contract
• Slow release cadence                                           • Single glow rule (`0 0 24px rgba(124,252,0,.35)`)
                                                                 • Automated GoDaddy document root deployment
```

### Key Architectural Contracts:
* **Client Portal Design DNA v1**: Follows `docs/architecture/FAMTASTIC_CLIENT_PORTAL_DESIGN_DNA_V1.md`. Enforces 44px touch targets, single-glow chartreuse token, dark luxury obsidian palette (`#070907`), and never leaks authenticated actions to public contact forms.
* **Build DNA Standard v1**: Follows `docs/architecture/BUILD_DNA_STANDARD_V1.md`. Every build run journals real provider/model status, prompts, input hashes, timing, costs, fallback reasons, and reviewer sign-offs.

---

## 3. Creative Tooling & Multi-Tier Matrix (Free vs. Competent vs. Paid)

| Creative Layer | Tier 1: Paid Flagship Anchors | Tier 2: Google Cloud Multiplier (~$0.03/ea) | Tier 3: Local / Free Engine ($0 / Unlimited) |
|---|---|---|---|
| **Ad Imagery & Scenes** | **OpenArt AI / Adobe Firefly**<br>• Master 4K photoreal scenes | **Gemini 3.1 Flash Lite Image**<br>• Seeding reference + prompt deltas<br>• Generates 12–30 variations for pennies | **Local Photoshop JSX Exporter + HTML**<br>• Certified 1:1, 9:16, 16:9 safe-zone crops<br>• Native HTML/CSS typography overlay |
| **Commercial Video** | **HeyGen Avatar Studio**<br>• Photoreal talking human presenters<br>• Synced facial lip movement | **Gemini Multimodal Breakdown**<br>• Visual timing markers & script tone adaptations | **MoneyPrinterTurbo (`tools/MoneyPrinterTurbo`)**<br>• Auto-stitches B-roll, voice, and subtitles<br>• Output: 1080x1920 MP4s at $0 |
| **Motion Graphics & UI** | **Adobe After Effects / Premiere**<br>• Master timeline compositing | **HyperFrames (`tools/hyperframes-upstream`)**<br>• Programmatic motion frame builder<br>• Kinetic lower thirds & UI cards | **Remotion (`marketing/video/`)**<br>• React-rendered motion graphic videos<br>• Deterministic 60fps animations |
| **Voiceover & Audio** | **ElevenLabs / HeyGen Studio Voice** | **Google Cloud Text-to-Speech** | **Edge-TTS (`msedge-tts`)**<br>• Neural voices (Christopher/Andrew) at $0 |

---

## 4. The Design Prompt Cookbook & Negative Constraint Standard

To ensure consistently high-converting, professional assets without visual artifacts:

### Rule 1: The "Photo-Only" Rule for Image Models
Never instruct generative image models to render typography, text, brand names, prices (`$199`), or URLs. AI image models inevitably hallucinate garbled characters.
* **Correct Approach**: Prompt for clean, realistic commercial photography with generous natural negative space on one side. Layer 100% crisp typography using native HTML/CSS, Photoshop text layers, or Remotion motion text.

### Rule 2: Safe-Zone Bounding Box for Short-Form Video
* **9:16 Vertical (TikTok/Reels/Shorts)**: Top 240px padding (clears search bars and audio labels) and bottom 380px padding (clears profile names, captions, like/share icons).
* **Rule**: Master subjects and key UI cards must be contained within the center `1080x1300px` safe rectangle.

### Rule 3: Single-Topic Hook Pacing
* **0:00 – 0:03s**: High-contrast pattern interrupt (e.g. holding 55¢, DM notification chaos, or Booksy fee breakdown).
* **0:03 – 0:10s**: Agitate the pain of rented land.
* **0:10 – 0:22s**: Transparent math ($199/yr = 55¢/day).
* **0:22 – 0:30s**: Low-friction action CTA (`famtasticdesigns.com`).

---

## 5. Next Steps: Authenticating External Cloud Providers

1. **NotebookLM**: Run `~/Development/FAMtastic/tools/nblm-venv/bin/notebooklm login --browser chrome` in your terminal to refresh Google authentication cookies.
2. **OpenArt**: OpenArt MCP credentials exist in your Keychain (`openart|f807845581003f91`). Add the OpenArt bridge to `~/.gemini/config/mcp_config.json` to enable direct API generation.
3. **HeyGen**: Run `curl -fsSL https://static.heygen.ai/cli/install.sh | bash && heygen auth login` to authorize the HeyGen CLI using your existing plan credits.
