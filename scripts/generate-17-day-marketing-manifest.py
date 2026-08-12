#!/usr/bin/env python3
"""Generate the draft-only 68-moment Web Basics campaign manifest."""

import json
from datetime import date
from pathlib import Path

CAMPAIGN = "web_basics_55_cents_17d"
THEMES = [
    ("declaration", "What 55 cents a day means"),
    ("excuses", "Why owners delay getting a website"),
    ("domain_basics", "The difference between a domain, website, and hosting"),
    ("hosting_basics", "What hosting does and what managed hosting changes"),
    ("one_page_anatomy", "The jobs a focused one-page website must do"),
    ("trust", "How prospective customers verify a business"),
    ("mobile", "The mobile-first customer journey"),
    ("fit", "Who Web Basics fits and who needs a different scope"),
    ("ecommerce_boundary", "Why an online store is more than a shopping cart"),
    ("lead_capture", "What should happen after a visitor submits a form"),
    ("follow_up", "Why inbox-only follow-up loses opportunities"),
    ("customer_portal", "What a useful customer portal should accomplish"),
    ("support_retention", "How support workflows protect trust"),
    ("analytics", "Which website metrics should change a decision"),
    ("automation", "What a small business should automate first"),
    ("ai_assistance", "When a website AI agent is actually useful"),
    ("recap_conversion", "How the complete FAMtastic system connects"),
]
MOMENTS = [
    ("teach", "08:00", "education"),
    ("challenge", "12:30", "objection"),
    ("prove", "17:30", "evidence"),
    ("invite", "20:30", "cta"),
]


def main() -> None:
    records = []
    for day, (theme, promise) in enumerate(THEMES, start=1):
        for moment, local_time, intent in MOMENTS:
            content_id = f"55c-d{day:02d}-{moment}"
            records.append({
                "content_id": content_id,
                "campaign": CAMPAIGN,
                "day": day,
                "theme": theme,
                "moment": moment,
                "intent": intent,
                "promise": promise,
                "suggested_time_et": local_time,
                "state": "idea",
                "channels": [],
                "asset_variants": ["9x16", "4x5"],
                "utm": {
                    "source": "pending_channel",
                    "medium": "organic_social",
                    "campaign": CAMPAIGN,
                    "content": content_id,
                },
                "approval": {"content": False, "media": False, "publish": False},
                "provider_ids": {},
                "evidence": [],
            })

    output = Path("marketing/campaigns/55-cents-17-day/manifest.json")
    output.parent.mkdir(parents=True, exist_ok=True)
    payload = {
        "schema_version": 1,
        "generated_on": date.today().isoformat(),
        "campaign": CAMPAIGN,
        "public_publish_enabled": False,
        "daily_content_moments": 4,
        "record_count": len(records),
        "records": records,
    }
    output.write_text(json.dumps(payload, indent=2) + "\n", encoding="utf-8")
    print(f"Wrote {len(records)} draft records to {output}")


if __name__ == "__main__":
    main()

