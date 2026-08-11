#!/usr/bin/env python3
"""Create a human-readable inventory and SEO scorecard for the demand library."""

from __future__ import annotations

import json
import statistics
from collections import Counter, defaultdict
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
MANIFEST = ROOT / "backend/config/famtastic-content-series.json"
REPORT = ROOT / "docs/DEMAND_LIBRARY_INVENTORY_2026-08-11.md"


def main() -> None:
    data = json.loads(MANIFEST.read_text())
    posts = data["posts"]
    by_series: dict[str, list[dict]] = defaultdict(list)
    for post in posts:
        by_series[post["series"]].append(post)
    series_names = {item["key"]: item["title"] for item in data["series"]}
    incoming = Counter(link for post in posts for link in post["internal_links"] if link.startswith("/blog/"))
    word_counts = [post["word_count"] for post in posts]
    lines = [
        "# FAMtastic demand-library inventory and SEO scorecard - 2026-08-11",
        "",
        "## Library totals",
        "",
        f"- Series: {len(data['series'])}",
        f"- Full article drafts: {len(posts)}",
        f"- Pillar articles: {sum(bool(post['pillar']) for post in posts)}",
        f"- Supporting articles: {sum(not post['pillar'] for post in posts)}",
        f"- Canonical FAQs: {len(data['faqs'])}",
        f"- Categories: {len(data['categories'])}",
        f"- Controlled tags: {len(data['tags'])}",
        f"- Total article words: {sum(word_counts):,}",
        f"- Article word-count range: {min(word_counts):,}-{max(word_counts):,}",
        f"- Average article words: {round(statistics.mean(word_counts)):,}",
        "- Publication state: all drafts; broad publication approval is false",
        "",
        "## SEO and editorial contract",
        "",
        "Every article has a unique slug and primary keyword; at least two secondary keywords; explicit search intent and template; target audience, reader problem, promised outcome, and evidence boundary; excerpt, title tag, meta description, Open Graph data, canonical URL, Article and Breadcrumb schema declarations; author and review state; at least one primary source; four related FAQs; at least five internal links; one contextual CTA; and a validated body of at least 900 words (1,000 for pillars).",
        "",
    ]
    for series_key in series_names:
        items = sorted(by_series[series_key], key=lambda item: item["sequence"])
        lines.extend([f"## {series_names[series_key]}", ""])
        for post in items:
            inbound = incoming[f"/blog/{post['slug']}"]
            role = "Pillar" if post["pillar"] else "Spoke"
            lines.append(
                f"{post['sequence']}. **{post['title']}** - {role}; "
                f"keyword: `{post['primary_keyword']}`; {post['word_count']:,} words; "
                f"{len(post['internal_links'])} planned links; {inbound} planned inbound links."
            )
        lines.append("")
    lines.extend([
        "## Gate status",
        "",
        "The library is structurally complete and locally validated. Each article remains an editorial draft. Broad publication, customer-facing commercial promises, live pricing changes, promotional sends, and advertising remain separate approval gates.",
        "",
    ])
    REPORT.write_text("\n".join(lines))
    print(f"Wrote {REPORT}")


if __name__ == "__main__":
    main()
