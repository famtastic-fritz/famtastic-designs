#!/usr/bin/env python3
"""FAMtastic growth deep dive via NotebookLM (owner's Google account).

Creates a grounded notebook from the company's own planning/audit docs,
runs NotebookLM web research on the four weak scores, and asks the
score-improvement questions. Findings print to stdout for capture into
docs/research/.

Run: ~/Development/FAMtastic/tools/nblm-venv/bin/python scripts/notebooklm-deep-dive.py
"""

import asyncio
from pathlib import Path

from notebooklm import NotebookLMClient
from notebooklm.auth import load_auth_from_storage

REPO = Path(__file__).resolve().parents[1]

SOURCES = [
    ("Master Plan", REPO / "docs/playbook/MASTER-PLAN.md"),
    ("CEO Full Review (scores + gaps)", REPO / "docs/audits/CEO-FULL-REVIEW-2026-08-24.md"),
    ("CEO Agents & Research plan", REPO / "docs/audits/CEO-AGENTS-AND-RESEARCH-2026-08-24.md"),
    ("Pricing & Wave Strategy", REPO / "docs/playbook/STRATEGY-PRICING.md"),
    ("Customer Service Recipe", REPO / "docs/playbook/RECIPES/AUTONOMOUS_CUSTOMER_SERVICE.md"),
]

QUESTIONS = [
    ("AUTOMATION",
     "Our automation score is 58/100. Based on the sources: which specific automations would move us toward 90, "
     "what should the publish executor for our 17-day campaign do step by step, and how do we eliminate the "
     "worker-late false-alert race in a 5-minute Drupal cron? Be concrete and reference our records."),
    ("MAKE MONEY",
     "Our make-money score is 40/100. The road exists: lead capture -> intake -> 3 proofs -> selection -> LIVE "
     "Stripe checkout -> entitlements. Zero strangers have paid. Based on the sources, what are the highest-"
     "confidence first three actions to make a stranger dollar happen within 7 days, and what conversion "
     "benchmarks should our $199 Web Basics / 55-cents-a-day funnel aim for?"),
    ("TRACK",
     "Our tracking score is 32/100. UTMs are dropped at lead capture today; GA4 fires only view_item/select_item "
     "on two pages. Design the minimal attribution stack: what fields to persist at capture, how to join "
     "content_id -> lead -> order, and which GA4 events matter for a $199 website funnel. Reference our tables."),
    ("GROW",
     "Our grow score is 22/100. Renewals are disabled in code, the blog is stale, campaign publishing awaits "
     "gates. Based on the sources: what grows this company autonomously in the next 30 days, ranked by revenue "
     "impact, with what each requires from our one human owner?"),
]

RESEARCH_QUERIES = [
    "Drupal commerce_stripe module off-session renewal payments SCA implementation guide",
    "SaaS attribution: capturing UTM parameters in a headless SPA + API lead capture architecture",
    "Conversion rate benchmarks web design agency $199 starter website funnel cold outreach",
]


async def main() -> None:
    async with NotebookLMClient.from_storage() as client:
        print("signed in:", await client.get_account_email())
        nb = await client.notebooks.create("FAMtastic Growth Deep Dive 2026-08-24")
        print("notebook:", nb.id)

        for title, path in SOURCES:
            if path.exists():
                src = await client.sources.add_text(nb.id, title, path.read_text()[:900_000], wait=True)
                print("source added:", title)
            else:
                print("source missing (skipped):", path)

        out = ["# NotebookLM Deep Dive — 2026-08-24", ""]

        for name, query in QUESTIONS:
            print(f"asking: {name}…")
            result = await client.chat.ask(nb.id, query)
            answer = result.answer if hasattr(result, "answer") else str(result)
            out += [f"## {name}", "", answer, ""]
            print(f"  answered ({len(answer)} chars)")

        for query in RESEARCH_QUERIES:
            print(f"web research: {query[:60]}…")
            try:
                started = await client.research.start(nb.id, query, source="web", mode="fast")
                done = await client.research.wait_for_completion(nb.id, started, timeout=420)
                imported = await client.research.import_sources(nb.id, done)
                out += [f"## Web research: {query}", "", f"Imported sources: {len(imported)}", ""]
                report_urls = []
                try:
                    report_urls = await client.research.extract_report_urls(nb.id, done)
                except Exception:
                    pass
                if report_urls:
                    out += ["Cited URLs:", *[f"- {u}" for u in report_urls], ""]
                print(f"  imported {len(imported)} sources")
            except Exception as exc:  # research feature can be premium-gated
                out += [f"## Web research: {query}", "", f"Research API unavailable: {exc}", ""]
                print("  research failed:", str(exc)[:120])

        dest = REPO / "docs/research/2026-08-24-notebooklm-deep-dive.md"
        dest.parent.mkdir(parents=True, exist_ok=True)
        dest.write_text("\n".join(out) + "\n")
        print("saved:", dest)


asyncio.run(main())
