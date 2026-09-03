# Advanced Campaign Engineering & Design Prompt Cookbook Techniques

**Date**: September 2, 2026  
**Status**: Canonical Production Research & Recipe Guide  
**Skill Reference**: `.agents/skills/famtastic-campaign-engineer/SKILL.md`  
**Governing Standard**: `famtastic.marketing-dna.v1`  

---

## 1. Researching & Deconstructing Any Campaign Suggestion

When the founder or team pitches a core idea (e.g. *"55 cents a day / we believe in your business"*, *"Estée Lauder scale ecommerce for growing brands"*, *"Stop DM chaos for auto shops"*), follow this 4-step deconstruction method:

```
                      CAMPAIGN DECONSTRUCTION PROCESS
                                     │
      ┌──────────────────────────────┼──────────────────────────────┐
      ▼                              ▼                              ▼
[Pillar 1: Contrarian Truth]    [Pillar 2: Upfront Faith]     [Pillar 3: Pure Math]
"Cost is NOT the reason you     "We believe in your idea so   "1 client booking covers
 don't have a website—fear       much we invest upfront with   the full year ($199 vs
 and predatory agencies are."    hosting, domain & AI data."   $1,500 in 30% app cuts)."
```

### Psychological Hook Archetypes:
1. **The Pattern Interrupt**: *"Of all the reasons you don't have a website... cost is NOT one of them."*
2. **The Faith & Risk Inversion**: *"Before you spend $1 of your own money on agency retainers, you should already see how 55¢ a day jumps your business. At worst, you're out 55 cents; we're out a promise made."*
3. **The Unfair Math Comparison**: *"$35/mo booking fee + 20% platform cut on 100 clients = $1,800/yr lost. Web Basics = $199 flat with 0% take rate."*
4. **The Enterprise Contrast**: *"We build business systems at ANY scale—from Estée Lauder-level global checkouts to $199 local launches. We bring that same engineering rigor to your trade."*

---

## 2. Modular Prompt Cookbooks (Master Recipes)

### Recipe A: The Local Authority Business Owner (1:1 & 9:16)
```text
PROMPT:
Cinematic commercial photography of a confident [NICHE_OPERATOR: master barber / female master mechanic / artisan baker], mid-30s, looking directly into the camera with proud, focused determination. Setting is a pristine, modern [ENVIRONMENT: upscale urban barbershop / high-tech auto studio / sunlit artisanal bakery]. Background features soft bokeh with warm practical lights and deep [BRAND_ACCENT: obsidian #080808 and subtle chartreuse rim light]. Shot on Hasselblad H6D-100c, 85mm f/1.4 lens, crisp depth of field, dramatic cinematic editorial lighting.

COMPOSITION & SAFE ZONES:
Subject positioned in the right two-thirds of the frame. Left one-third features clean, uncluttered negative space for native typography overlay.

NEGATIVE PROMPT:
text, watermark, logo, typography, distorted hands, extra fingers, plastic skin, cartoon, anime, blurry, low resolution, crowded composition, centered subject with no negative space.
```

### Recipe B: The High-Ticket Enterprise / Creator Powerhouse (16:9 & 9:16)
```text
PROMPT:
Ultra-high-end studio photography of a sleek glass desk workspace overlooking a modern city skyline at dusk. On the desk rests an ultra-thin laptop showing a vibrant, glowing data dashboard with clean growth charts. Atmospheric ambient lighting in deep indigo (#0A0F24) with sharp electric cyan and gold accent streaks. Cinematic color grading, 8k resolution, razor-sharp focus on the desk foreground with cinematic soft focus in the background.

COMPOSITION & SAFE ZONES:
Top 240px and bottom 380px clear of focal objects. Center rectangle contains the glowing hardware and workspace.

NEGATIVE PROMPT:
unreadable text, distorted screens, pixelated gradients, oversaturated neon, fake 3D renders, stock photo smiling models, clutter.
```

---

## 3. The 3-Tier Execution Workflow

```
┌─────────────────────────────────────────────────────────────────────────────┐
│ 1. SEED PROMPT EXECUTION (OpenArt / Midjourney)                             │
│    • Generate 1–2 master key visuals with perfect lighting and composition. │
├─────────────────────────────────────────────────────────────────────────────┤
│ 2. CLOUD PROMPT MULTIPLIER (Google Gemini Flash Lite 3.1)                   │
│    • Send seed visual + niche parameters to Gemini Developer API.           │
│    • Output 15–30 niche variations (mechanic, salon, bakery, creator).      │
│    • Cost: ~$0.0336 per image / $0.50 for the entire batch.                 │
├─────────────────────────────────────────────────────────────────────────────┤
│ 3. LOCAL FREE ASSEMBLY (MoneyPrinterTurbo + Remotion + JSX Exporter)        │
│    • MoneyPrinterTurbo: Combines narration (Edge-TTS) + B-roll + subtitles. │
│    • Remotion: Renders 60fps kinetic typography and UI mockups over media.  │
│    • Photoshop JSX: Batch exports 1:1, 9:16, 16:9 crops with safe borders.  │
│    • Cost: $0.00 / Local CPU/GPU compute.                                   │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 4. How to Invoke This for Future Campaigns

To launch any new campaign:
1. Run the `famtastic-campaign-engineer` skill.
2. Provide the core offer or suggestion (e.g. *"Summer Campus Hustle"*, *"High-Ticket Contractor SEO"*, *"0% Fee Booking for Stylists"*).
3. The skill will automatically research the angle, generate the modular prompt cookbook, produce the 3-tier assets, and format the live 5-channel distribution package.
