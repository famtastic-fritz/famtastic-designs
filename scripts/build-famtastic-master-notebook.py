#!/usr/bin/env python3
"""FAMtastic Master Intelligence, Tools & Creative Cookbooks Notebook Builder.

Creates a unified, grounded NotebookLM notebook from FAMtastic's core repository
sources (lessons learned, sites built, Site Studio logic, brand philosophy,
and creative capability standards), then runs deep research on modern free &
competent creative tools and design prompt cookbooks.

Run: ~/Development/FAMtastic/tools/nblm-venv/bin/python scripts/build-famtastic-master-notebook.py
"""

import asyncio
import os
from pathlib import Path

from notebooklm import NotebookLMClient

REPO = Path(__file__).resolve().parents[1]

# Core Grounding Sources to Ingest
SOURCES = [
    ("FAMtastic Definition & Core Doctrine", REPO / "docs/DEMAND_ENGINE_DOCTRINE.md"),
    ("Site Studio & Build DNA Standard", REPO / "docs/architecture/BUILD_DNA_STANDARD_V1.md"),
    ("Client Portal Design DNA Standard", REPO / "docs/architecture/FAMTASTIC_CLIENT_PORTAL_DESIGN_DNA_V1.md"),
    ("Marketing DNA & Safe Zones Standard", REPO / "docs/architecture/FAMTASTIC_MARKETING_DNA_STANDARD_V1.md"),
    ("3-Tier Asset Cascade & Tooling Architecture", REPO / "docs/architecture/CAMPAIGN_ASSET_CASCADE_AND_DISTRIBUTION_V1.md"),
    ("Capability Registry (Proven vs Unproven)", REPO / "docs/CAPABILITY_REGISTRY.md"),
    ("Site Learnings & Operational Incident Log", REPO / "docs/SITE_LEARNINGS.md"),
    ("CEO Full Strategic Audit & Weak Score Analysis", REPO / "docs/audits/CEO-FULL-REVIEW-2026-08-24.md"),
    ("3-Tier Capability Benchmark & Telemetry", REPO / "marketing/campaigns/cost-is-not-the-reason/evidence/THREE_TIER_CAPABILITY_BENCHMARK_2026-09-02.md"),
    ("Blogger Persona (Dex) & Voice Guide", REPO / "marketing/personas/FAMTASTIC_BLOGGER_PERSONA_V1.md"),
]

# Custom Brand Manifesto Source
BRAND_MANIFESTO = """
# The FAMtastic Brand Definition & Guiding Philosophy

## 1. "FAMtastic" as an Adjective
* **Definition**: Characterized by warmth, unyielding craftsmanship, engineering rigor, and deep alignment with family-owned, independent, and hardworking small businesses.
* **In Practice**: A system, design, or business relationship that is not transactional, bloated, or predatory, but built to empower long-term self-reliance and ownership.
* **The Mission**: We are a Business Solutions Engineering studio. We do not create your craft—you are already the master of your trade (barber, mechanic, baker, contractor). We engineer the digital business engine that protects your revenue, eliminates third-party platform rent, and turns your web presence into an owned asset.

## 2. Guiding Business Rules
1. **Never Build on Rented Land**: Third-party marketplaces and booking apps take 20-30% cuts and withhold customer lists. Every business must own their domain, customer database, and booking funnel.
2. **The 3-Direction Proof Standard**: Every client receives 3 bespoke interactive design directions in 48 hours: Safe (Clean/Modern), Wild (Creative/Bold), and OMG (Luxury/State-of-the-Art).
3. **Cost Is Never The Reason**: We invest in the business upfront with the $199 Web Basics Bundle (~55¢ a day) including managed cloud hosting and custom domain connection.
4. **Zero Fluff / Clear Unit Economics**: Every tech recommendation must be mathematically justified by positive ROI.
"""

RESEARCH_QUERIES = [
    ("FREE_CREATIVE_AI_TOOLS",
     "What are the best free or open-source AI video and image tools in 2026 for automated marketing pipelines? "
     "Analyze tools like MoneyPrinterTurbo, Remotion, ComfyUI, Whisper, Edge-TTS, and local video stitchers for commercial output."),
    ("MODULAR_DESIGN_PROMPTS",
     "What are the best framework practices for modular image and video prompt engineering (seed anchors, prompt deltas, negative constraints, and zero-hallucinated-text photo rules) for business advertising?"),
    ("MULTI_TIER_CREATIVE_CASCADE",
     "How can a creative agency use 1 paid high-fidelity asset (HeyGen, Midjourney, OpenArt) as a seed anchor to generate 30+ low-cost variants using lightweight models (Gemini Flash Lite) and local code generators?"),
]

async def main():
    print("--- Initializing FAMtastic Master NotebookLM Builder ---")
    
    async with NotebookLMClient.from_storage() as client:
        user_email = await client.get_account_email()
        print(f"Authenticated as: {user_email}")
        
        nb_title = "FAMtastic Master Intelligence, Tools & Creative Cookbooks"
        nb = await client.notebooks.create(nb_title)
        print(f"Created Notebook: {nb_title} (ID: {nb.id})")
        
        # Add Brand Manifesto
        await client.sources.add_text(nb.id, "FAMtastic Definition & Guiding Business Philosophy", BRAND_MANIFESTO, wait=True)
        print("✔ Added Brand Manifesto & Definition")
        
        # Ingest Core Sources
        for title, path in SOURCES:
            if path.exists():
                content = path.read_text()[:900_000]
                await client.sources.add_text(nb.id, title, content, wait=True)
                print(f"✔ Added Source: {title}")
            else:
                print(f"⚠ Source not found (skipped): {path}")
                
        out = [
            f"# {nb_title}",
            f"**Generated**: {nb.id}",
            f"**Account**: {user_email}",
            "",
            "## 1. Grounded Synthesis & Core Insights",
            ""
        ]
        
        # Synthesis Questions
        synthesis_questions = [
            ("CREATIVE_ENGINEERING",
             "Synthesize our Site Studio philosophy, 3-tier creative multiplier, and the definition of 'FAMtastic' as an adjective into a guiding operational playbook for autonomous agents building future campaigns."),
            ("PROMPT_COOKBOOK_STANDARDS",
             "Based on our lessons learned and benchmark telemetry, summarize the exact prompt engineering rules, negative constraints, and safe-zone requirements that prevent visual errors in our multi-channel ad runs.")
        ]
        
        for name, q in synthesis_questions:
            print(f"Running synthesis: {name}...")
            res = await client.chat.ask(nb.id, q)
            ans = res.answer if hasattr(res, "answer") else str(res)
            out += [f"### {name}", "", ans, ""]
            print(f"✔ Completed synthesis: {name}")

        # Web Research
        out += ["## 2. Advanced Tooling & Prompt Cookbook Research", ""]
        for label, query in RESEARCH_QUERIES:
            print(f"Running web research: {label}...")
            try:
                started = await client.research.start(nb.id, query, source="web", mode="fast")
                done = await client.research.wait_for_completion(nb.id, started, timeout=420)
                imported = await client.research.import_sources(nb.id, done)
                out += [f"### Research: {label}", f"Query: *{query}*", "", f"Imported Sources: {len(imported)}", ""]
                print(f"✔ Imported {len(imported)} sources for {label}")
            except Exception as e:
                out += [f"### Research: {label}", f"Research query status: {e}", ""]
                print(f"⚠ Research note: {e}")

        # Save to Docs & Drive
        dest = REPO / "docs/research/2026-09-02-famtastic-master-intelligence-notebook.md"
        dest.parent.mkdir(parents=True, exist_ok=True)
        dest.write_text("\n".join(out) + "\n")
        print(f"\nSaved master findings to: {dest}")

        # Sync to Google Drive
        gdrive_path = Path(os.path.expanduser("~/Library/CloudStorage/GoogleDrive-fritz.medine@gmail.com/My Drive/FAMtastic-2026/Sites/FAMtastic Designs.com/Marketing & Revenue Strategy/2026-09-02-famtastic-master-intelligence-notebook.md"))
        gdrive_path.parent.mkdir(parents=True, exist_ok=True)
        gdrive_path.write_text("\n".join(out) + "\n")
        print(f"Mirrored to Google Drive: {gdrive_path}")

if __name__ == "__main__":
    asyncio.run(main())
