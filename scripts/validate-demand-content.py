#!/usr/bin/env python3
"""Validate FAMtastic's canonical demand-content manifest."""

from __future__ import annotations

import json
import re
import sys
from collections import Counter
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
MANIFEST = ROOT / "backend/config/famtastic-content-series.json"
ALLOWED_STATUS = {"draft", "review", "approved", "published", "archived"}
ALLOWED_STAGE = {"awareness", "consideration", "decision", "customer"}
SLUG = re.compile(r"^[a-z0-9]+(?:-[a-z0-9]+)*$")
EVENT = re.compile(r"^[a-z][a-z0-9_]*$")


def fail(errors: list[str], message: str) -> None:
    errors.append(message)


def duplicates(values: list[str]) -> list[str]:
    return sorted(key for key, count in Counter(values).items() if count > 1)


def main() -> int:
    errors: list[str] = []
    try:
        data = json.loads(MANIFEST.read_text())
    except (OSError, json.JSONDecodeError) as exc:
        print(f"ERROR: cannot read {MANIFEST}: {exc}", file=sys.stderr)
        return 1

    required_top = {"version", "approval", "capabilities", "categories", "tags", "series", "faqs", "posts"}
    missing_top = sorted(required_top - set(data))
    if missing_top:
        fail(errors, f"manifest missing top-level keys: {', '.join(missing_top)}")

    capabilities = {item.get("key") for item in data.get("capabilities", [])}
    categories = {item.get("key") for item in data.get("categories", [])}
    tags = {item.get("key") for item in data.get("tags", [])}
    series = {item.get("key"): item for item in data.get("series", [])}
    faqs = {item.get("key"): item for item in data.get("faqs", [])}
    posts = data.get("posts", [])

    for collection_name, collection in (
        ("capabilities", data.get("capabilities", [])),
        ("categories", data.get("categories", [])),
        ("tags", data.get("tags", [])),
        ("series", data.get("series", [])),
        ("faqs", data.get("faqs", [])),
        ("posts", posts),
    ):
        keys = [item.get("key", "") for item in collection]
        if "" in keys:
            fail(errors, f"{collection_name} contains an item without a key")
        dupes = duplicates(keys)
        if dupes:
            fail(errors, f"{collection_name} has duplicate keys: {', '.join(dupes)}")

    for item in data.get("categories", []) + data.get("tags", []):
        if not SLUG.match(item.get("key", "")):
            fail(errors, f"taxonomy key is not normalized: {item.get('key')!r}")
        if not item.get("label"):
            fail(errors, f"taxonomy {item.get('key')} is missing a label")

    post_slugs = [post.get("slug", "") for post in posts]
    dupes = duplicates(post_slugs)
    if dupes:
        fail(errors, f"duplicate post slugs: {', '.join(dupes)}")

    series_sequences: dict[str, list[int]] = {key: [] for key in series}
    pillar_counts: Counter[str] = Counter()
    for post in posts:
        key = post.get("key", "<missing>")
        status = post.get("status")
        if status not in ALLOWED_STATUS:
            fail(errors, f"{key}: invalid status {status!r}")
        if status == "published" and not data.get("approval", {}).get("broad_publish_approved", False):
            fail(errors, f"{key}: published while broad publication approval is false")
        slug = post.get("slug", "")
        if not SLUG.match(slug):
            fail(errors, f"{key}: invalid slug {slug!r}")
        series_key = post.get("series")
        if series_key not in series:
            fail(errors, f"{key}: unknown series {series_key!r}")
        else:
            series_sequences[series_key].append(post.get("sequence"))
        if post.get("pillar"):
            pillar_counts[series_key] += 1
        if post.get("category") not in categories:
            fail(errors, f"{key}: unknown category {post.get('category')!r}")
        unknown_tags = sorted(set(post.get("tags", [])) - tags)
        if unknown_tags:
            fail(errors, f"{key}: unknown tags: {', '.join(unknown_tags)}")
        unknown_caps = sorted(set(post.get("capabilities", [])) - capabilities)
        if unknown_caps:
            fail(errors, f"{key}: unknown capabilities: {', '.join(unknown_caps)}")
        unknown_faqs = sorted(set(post.get("faqs", [])) - set(faqs))
        if unknown_faqs:
            fail(errors, f"{key}: unknown FAQs: {', '.join(unknown_faqs)}")
        if len(post.get("meta_title", "")) > 65:
            fail(errors, f"{key}: meta title exceeds 65 characters")
        description_length = len(post.get("meta_description", ""))
        if not 110 <= description_length <= 165:
            fail(errors, f"{key}: meta description must be 110-165 characters (got {description_length})")
        body = post.get("body_html", "")
        if len(body) < 900:
            fail(errors, f"{key}: body_html is too short for a complete draft")
        if re.search(r"<\s*(script|iframe)\b", body, re.I):
            fail(errors, f"{key}: body_html contains a forbidden executable/embed tag")
        cta = post.get("cta", {})
        if cta.get("stage") not in ALLOWED_STAGE:
            fail(errors, f"{key}: invalid CTA stage {cta.get('stage')!r}")
        if not str(cta.get("href", "")).startswith("/"):
            fail(errors, f"{key}: CTA must use a same-origin route")
        if not EVENT.match(cta.get("event", "")):
            fail(errors, f"{key}: invalid CTA event name")
        links = post.get("internal_links", [])
        if not links or not all(str(link).startswith("/") for link in links):
            fail(errors, f"{key}: internal_links must contain same-origin routes")

    for series_key, item in series.items():
        sequences = series_sequences.get(series_key, [])
        if len(sequences) < 3:
            fail(errors, f"{series_key}: a series needs at least three posts")
        if any(not isinstance(number, int) or number < 1 for number in sequences):
            fail(errors, f"{series_key}: all post sequence values must be positive integers")
        if duplicates([str(number) for number in sequences]):
            fail(errors, f"{series_key}: duplicate sequence values")
        if pillar_counts[series_key] != 1:
            fail(errors, f"{series_key}: expected exactly one pillar post")
        if item.get("status") not in ALLOWED_STATUS:
            fail(errors, f"{series_key}: invalid status {item.get('status')!r}")

    for faq_key, faq in faqs.items():
        if faq.get("status") not in ALLOWED_STATUS:
            fail(errors, f"{faq_key}: invalid status {faq.get('status')!r}")
        if faq.get("category") not in categories:
            fail(errors, f"{faq_key}: unknown category {faq.get('category')!r}")
        if len(faq.get("answer_html", "")) < 80:
            fail(errors, f"{faq_key}: answer is too short")

    if errors:
        print("Demand content validation FAILED", file=sys.stderr)
        for error in errors:
            print(f"- {error}", file=sys.stderr)
        return 1

    print(
        "Demand content validation passed: "
        f"{len(series)} series, {len(posts)} posts, {len(faqs)} FAQs, "
        f"{len(categories)} categories, {len(tags)} tags."
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
