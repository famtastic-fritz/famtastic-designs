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
REQUIRED_SEO_FIELDS = {
    "primary_keyword",
    "secondary_keywords",
    "search_intent",
    "content_template",
    "target_audience",
    "og_title",
    "og_description",
    "canonical_url",
    "schema_types",
    "author",
    "review_status",
    "sources",
    "word_count",
}


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
    paragraph_owners: dict[str, set[str]] = {}

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
    keyword_dupes = duplicates([post.get("primary_keyword", "").lower().strip() for post in posts])
    if keyword_dupes:
        fail(errors, f"duplicate primary keywords: {', '.join(keyword_dupes)}")
    inbound_links = Counter(
        link
        for post in posts
        for link in post.get("internal_links", [])
        if str(link).startswith("/blog/")
    )

    series_sequences: dict[str, list[int]] = {key: [] for key in series}
    pillar_counts: Counter[str] = Counter()
    for post in posts:
        key = post.get("key", "<missing>")
        missing_seo = sorted(REQUIRED_SEO_FIELDS - set(post))
        if missing_seo:
            fail(errors, f"{key}: missing SEO/editorial fields: {', '.join(missing_seo)}")
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
        for paragraph in re.findall(r"<p\b[^>]*>(.*?)</p>", body, re.I | re.S):
            normalized = re.sub(r"\s+", " ", re.sub(r"<[^>]+>", " ", paragraph)).strip().lower()
            if len(normalized.split()) >= 35:
                paragraph_owners.setdefault(normalized, set()).add(key)
        if post.get("series") != "fifty-five-cents-a-day":
            plain_general = re.sub(r"<[^>]+>", " ", body).lower()
            biased_phrases = [
                "drupal can serve as",
                "drupal interface",
                "drupal + react",
                "drupal and react",
                "react interface provides",
            ]
            for phrase in biased_phrases:
                if phrase in plain_general:
                    fail(errors, f"{key}: general-interest article contains CMS-biased boilerplate: {phrase!r}")
        if post.get("series") == "fifty-five-cents-a-day":
            plain_campaign = re.sub(r"<[^>]+>", " ", body).lower()
            for forbidden_scope in ["drupal", "react", "ai-optimized", "48-hour delivery", "live in 48 hours"]:
                if forbidden_scope in plain_campaign:
                    fail(errors, f"{key}: $199 campaign contains out-of-scope language: {forbidden_scope!r}")
            if len(post.get("sources", [])) < 3:
                fail(errors, f"{key}: $199 campaign requires three reviewed source records")
            if not str(post.get("visual", {}).get("src", "")).startswith("/blog-images/campaign-"):
                fail(errors, f"{key}: $199 campaign requires a purpose-built campaign header visual")
        body_words = len(re.findall(r"\b[\w'-]+\b", re.sub(r"<[^>]+>", " ", body)))
        minimum_words = 1000 if post.get("pillar") else 900
        if body_words < minimum_words:
            fail(errors, f"{key}: complete draft needs at least {minimum_words} words (got {body_words})")
        if post.get("word_count") != body_words:
            fail(errors, f"{key}: recorded word_count does not match body ({post.get('word_count')} != {body_words})")
        if len(re.findall(r"<h2\b", body, re.I)) < 6:
            fail(errors, f"{key}: complete draft needs at least six H2 sections")
        if re.search(r"<\s*(script|iframe)\b", body, re.I):
            fail(errors, f"{key}: body_html contains a forbidden executable/embed tag")
        if not post.get("primary_keyword") or len(post.get("secondary_keywords", [])) < 2:
            fail(errors, f"{key}: primary keyword and at least two secondary keywords are required")
        if post.get("search_intent") not in {"informational", "commercial-investigation", "transactional", "navigational"}:
            fail(errors, f"{key}: invalid search intent {post.get('search_intent')!r}")
        if post.get("canonical_url") != f"https://famtasticdesigns.com/blog/{slug}/":
            fail(errors, f"{key}: canonical URL does not match slug")
        if not {"Article", "BreadcrumbList"}.issubset(set(post.get("schema_types", []))):
            fail(errors, f"{key}: Article and BreadcrumbList schema declarations are required")
        if post.get("og_title") != post.get("title") or post.get("og_description") != post.get("meta_description"):
            fail(errors, f"{key}: Open Graph title/description must match the reviewed article metadata")
        sources = post.get("sources", [])
        if not sources or not all(str(source.get("url", "")).startswith("https://") for source in sources):
            fail(errors, f"{key}: at least one HTTPS primary source is required")
        cta = post.get("cta", {})
        if cta.get("stage") not in ALLOWED_STAGE:
            fail(errors, f"{key}: invalid CTA stage {cta.get('stage')!r}")
        if not str(cta.get("href", "")).startswith("/"):
            fail(errors, f"{key}: CTA must use a same-origin route")
        if not EVENT.match(cta.get("event", "")):
            fail(errors, f"{key}: invalid CTA event name")
        links = post.get("internal_links", [])
        if len(links) < 5 or not all(str(link).startswith("/") for link in links):
            fail(errors, f"{key}: internal_links must contain at least five same-origin routes")
        if f"/blog/{slug}" in links:
            fail(errors, f"{key}: internal_links contains a self-link")
        if inbound_links[f"/blog/{slug}"] < 2:
            fail(errors, f"{key}: article needs at least two planned inbound links")
        if len(post.get("faqs", [])) < 3:
            fail(errors, f"{key}: at least three related FAQs are required")
        visual = post.get("visual")
        if visual:
            if not visual.get("alt") or not visual.get("caption"):
                fail(errors, f"{key}: visual requires descriptive alt text and a caption")
            src = str(visual.get("src", ""))
            if not src.startswith("/blog-images/") or not (ROOT / "frontend/public" / src.lstrip("/")).is_file():
                fail(errors, f"{key}: visual asset is missing from frontend/public: {src!r}")
            brand_mark = str(visual.get("brand_mark", ""))
            if not brand_mark.startswith("/brand/") or not (ROOT / "frontend/public" / brand_mark.lstrip("/")).is_file():
                fail(errors, f"{key}: brand mark is missing from frontend/public: {brand_mark!r}")

    for series_key, item in series.items():
        sequences = series_sequences.get(series_key, [])
        if len(sequences) < 8:
            fail(errors, f"{series_key}: a complete series needs at least eight posts")
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

    repeated = [
        (paragraph, owners)
        for paragraph, owners in paragraph_owners.items()
        if len(owners) > 3 and not all(
            next((post.get("series") for post in posts if post.get("key") == owner), "") == "fifty-five-cents-a-day"
            for owner in owners
        )
    ]
    for paragraph, owners in sorted(repeated, key=lambda item: -len(item[1])):
        preview = paragraph[:90] + ("..." if len(paragraph) > 90 else "")
        fail(errors, f"long paragraph reused across {len(owners)} posts: {preview!r}")

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
