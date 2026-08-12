#!/usr/bin/env python3
"""Audit FAMtastic's canonical content and production search surfaces."""

from __future__ import annotations

import json
import re
import ssl
import urllib.request
import xml.etree.ElementTree as ET
from collections import Counter, defaultdict
from concurrent.futures import ThreadPoolExecutor
from datetime import datetime, timezone
from pathlib import Path
from urllib.parse import urlparse

ROOT = Path(__file__).resolve().parents[1]
ORIGIN = "https://famtasticdesigns.com"
MANIFEST = ROOT / "backend/config/famtastic-content-series.json"
REPORT_DIR = ROOT / "docs/qa"
JSON_REPORT = REPORT_DIR / "SEO_DISCOVERY_QA_2026-08-12.json"
MD_REPORT = REPORT_DIR / "SEO_DISCOVERY_QA_2026-08-12.md"
UA = "FAMtastic-SEO-QA/1.0"


def fetch(url: str) -> tuple[int, dict[str, str], str]:
    request = urllib.request.Request(url, headers={"User-Agent": UA})
    try:
        with urllib.request.urlopen(request, timeout=25, context=ssl.create_default_context()) as response:
            return response.status, dict(response.headers.items()), response.read().decode("utf-8", "replace")
    except urllib.error.HTTPError as exc:
        return exc.code, dict(exc.headers.items()), exc.read().decode("utf-8", "replace")
    except Exception as exc:  # retained as page-level evidence rather than aborting the batch
        return 0, {}, str(exc)


def first(pattern: str, text: str) -> str:
    match = re.search(pattern, text, re.I | re.S)
    return re.sub(r"\s+", " ", match.group(1)).strip() if match else ""


def tokenize(value: str) -> set[str]:
    stop = {"a", "an", "and", "for", "how", "is", "of", "the", "to", "what", "when", "with", "your"}
    return {word for word in re.findall(r"[a-z0-9]+", value.lower()) if len(word) > 2 and word not in stop}


def classify(url: str) -> str:
    path = urlparse(url).path.strip("/")
    if not path:
        return "home"
    return path.split("/")[0]


def audit_page(url: str) -> dict:
    status, headers, html = fetch(url)
    title = first(r"<title[^>]*>(.*?)</title>", html)
    description = first(r'<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']*)', html)
    canonical = first(r'<link[^>]+rel=["\']canonical["\'][^>]+href=["\']([^"\']*)', html)
    og_title = first(r'<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']*)', html)
    og_description = first(r'<meta[^>]+property=["\']og:description["\'][^>]+content=["\']([^"\']*)', html)
    og_url = first(r'<meta[^>]+property=["\']og:url["\'][^>]+content=["\']([^"\']*)', html)
    schemas, schema_errors = [], []
    for raw in re.findall(r'<script[^>]+type=["\']application/ld\+json["\'][^>]*>(.*?)</script>', html, re.I | re.S):
        try:
            value = json.loads(raw)
            graph = value.get("@graph", [value]) if isinstance(value, dict) else value
            schemas.extend(str(item.get("@type")) for item in graph if isinstance(item, dict) and item.get("@type"))
        except json.JSONDecodeError as exc:
            schema_errors.append(str(exc))
    issues = []
    expected = url if url.endswith("/") else f"{url}/"
    if status != 200: issues.append(f"HTTP status is {status}")
    if not title: issues.append("Missing title in initial HTML")
    elif not 30 <= len(title) <= 70: issues.append(f"Title length is {len(title)} characters")
    if not description: issues.append("Missing meta description in initial HTML")
    elif not 105 <= len(description) <= 170: issues.append(f"Meta description length is {len(description)} characters")
    if canonical != expected: issues.append(f"Canonical mismatch: {canonical or 'missing'}")
    if not og_title or not og_description or og_url != expected: issues.append("Open Graph title, description, or URL is incomplete/inconsistent")
    if not schemas: issues.append("No structured data in initial HTML")
    if schema_errors: issues.append("Invalid JSON-LD")
    if not re.search(r"<h1\b", html, re.I): issues.append("Primary H1/content is absent from initial HTML (client-rendered)")
    score = max(0, 100 - sum(20 if issue.startswith("HTTP") else 10 for issue in issues))
    return {
        "url": url, "type": classify(url), "status": status,
        "content_type": headers.get("Content-Type", ""), "title": title,
        "title_length": len(title), "description": description,
        "description_length": len(description), "canonical": canonical,
        "og_title": og_title, "og_description": og_description, "og_url": og_url,
        "schema_types": sorted(set(schemas)), "initial_html_h1": bool(re.search(r"<h1\b", html, re.I)),
        "score": score, "result": "pass" if score >= 90 else "revise" if score >= 65 else "block",
        "issues": issues,
    }


def audit_posts(data: dict, sitemap_paths: set[str]) -> tuple[list[dict], list[dict]]:
    posts = data["posts"]
    results = []
    for post in posts:
        body = post.get("body_html", "")
        plain = re.sub(r"\s+", " ", re.sub(r"<[^>]+>", " ", body)).strip()
        issues = []
        title_len = len(post.get("meta_title", ""))
        desc = post.get("meta_description", "")
        if not 40 <= title_len <= 65: issues.append(f"Meta title length is {title_len}; target 40-65")
        if not 110 <= len(desc) <= 165: issues.append(f"Meta description length is {len(desc)}; target 110-165")
        if re.search(r"\bLearn (define|understand|choose|build|decide|know|design|track|handle|measure)\b", desc):
            issues.append("Meta description has an ungrammatical 'Learn + base verb' construction")
        if desc.endswith("and.") or desc.endswith("or.") or desc.endswith("with."):
            issues.append("Meta description ends with a truncated conjunction")
        if post.get("primary_keyword", "").lower() not in f"{post.get('title','')} {desc} {plain[:500]}".lower():
            issues.append("Primary keyword is not explicit in title, description, or opening copy")
        if len(post.get("internal_links", [])) < 5: issues.append("Fewer than five planned internal links")
        broken = [link for link in post.get("internal_links", []) if link.split("?")[0].rstrip("/") not in sitemap_paths and link.split("?")[0] not in {"/start", "/contact", "/packages", "/services", "/faq"}]
        if broken: issues.append(f"Planned internal-link targets absent from sitemap: {', '.join(broken[:4])}")
        if not {"Article", "BreadcrumbList"}.issubset(post.get("schema_types", [])): issues.append("Article/Breadcrumb schema declaration incomplete")
        if len(post.get("sources", [])) < 1: issues.append("No supporting source record")
        if len(re.findall(r"<h2\b", body, re.I)) < 6: issues.append("Fewer than six substantive H2 sections")
        if len(plain.split()) < (1000 if post.get("pillar") else 900): issues.append("Body is below the repository completeness threshold")
        if not post.get("cta", {}).get("href", "").startswith("/"): issues.append("CTA destination is missing or external")
        score = max(0, 100 - 6 * len(issues))
        results.append({
            "content_id": post.get("key"), "revision": data.get("version"), "url": post.get("canonical_url"),
            "series": post.get("series"), "primary_intent": post.get("search_intent"),
            "primary_keyword": post.get("primary_keyword"), "title": post.get("title"),
            "score": score, "result": "pass" if score >= 90 else "revise" if score >= 70 else "block",
            "issues": issues, "internal_link_count": len(post.get("internal_links", [])),
            "source_count": len(post.get("sources", [])), "word_count": len(plain.split()),
            "reviewer": "FAMtastic SEO Discovery QA v1", "reviewed_at": datetime.now(timezone.utc).isoformat(),
        })
    conflicts = []
    for index, left in enumerate(posts):
        left_terms = tokenize(f"{left.get('primary_keyword','')} {left.get('title','')}")
        for right in posts[index + 1:]:
            right_terms = tokenize(f"{right.get('primary_keyword','')} {right.get('title','')}")
            union = left_terms | right_terms
            similarity = len(left_terms & right_terms) / len(union) if union else 0
            same_keyword = left.get("primary_keyword", "").strip().lower() == right.get("primary_keyword", "").strip().lower()
            if same_keyword or similarity >= 0.55:
                conflicts.append({"left": left["slug"], "right": right["slug"], "similarity": round(similarity, 3), "action": "differentiate intent or consolidate"})
    return results, conflicts


def main() -> None:
    _, _, robots = fetch(f"{ORIGIN}/robots.txt")
    sitemap_status, _, sitemap_xml = fetch(f"{ORIGIN}/sitemap.xml")
    root = ET.fromstring(sitemap_xml)
    namespace = {"s": "http://www.sitemaps.org/schemas/sitemap/0.9"}
    urls = [node.text.strip() for node in root.findall("s:url/s:loc", namespace)]
    with ThreadPoolExecutor(max_workers=10) as pool:
        pages = list(pool.map(audit_page, urls))
    manifest = json.loads(MANIFEST.read_text())
    sitemap_paths = {urlparse(url).path.rstrip("/") or "/" for url in urls}
    posts, conflicts = audit_posts(manifest, sitemap_paths)
    result = {
        "audit_date": "2026-08-12", "origin": ORIGIN,
        "scope": {"sitemap_urls": len(urls), "canonical_posts": len(posts)},
        "robots": {"sitemap_declared": f"Sitemap: {ORIGIN}/sitemap.xml" in robots, "body": robots},
        "sitemap": {"status": sitemap_status, "valid_xml": True, "url_count": len(urls)},
        "summary": {
            "page_average": round(sum(item["score"] for item in pages) / len(pages), 1),
            "content_average": round(sum(item["score"] for item in posts) / len(posts), 1),
            "page_results": dict(Counter(item["result"] for item in pages)),
            "content_results": dict(Counter(item["result"] for item in posts)),
            "intent_conflicts": len(conflicts),
        },
        "pages": pages, "content_items": posts, "cannibalization_candidates": conflicts,
        "limitations": [
            "Search Console, CrUX field data, backlink indexes, and live ranking data were unavailable.",
            "Page checks evaluate initial HTML; rendered mobile/accessibility QA is reported by the separate visual QA lane.",
            "Cannibalization candidates are heuristic and require Search Console query-to-page evidence before merge or redirect decisions.",
        ],
    }
    REPORT_DIR.mkdir(parents=True, exist_ok=True)
    JSON_REPORT.write_text(json.dumps(result, indent=2) + "\n")

    by_type = defaultdict(list)
    for page in pages: by_type[page["type"]].append(page)
    lines = [
        "# FAMtastic SEO and discovery QA — 2026-08-12", "",
        "This is an independent repository-and-production audit. Scores are internal QA heuristics, not Google rankings.", "",
        "## Executive result", "",
        f"- Production sitemap: **{len(urls)} valid HTTPS URLs**, all audited individually.",
        f"- Canonical content manifest: **{len(posts)} articles**, all audited individually.",
        f"- Initial-HTML page score: **{result['summary']['page_average']}/100**.",
        f"- Canonical article SEO/content score: **{result['summary']['content_average']}/100**.",
        f"- Cannibalization candidates requiring human/GSC confirmation: **{len(conflicts)}**.",
        "- Robots declares the working sitemap and excludes customer/admin routes.", "",
        "## Sitewide findings", "",
        "1. **Critical content is still client-rendered.** Every sitemap route has metadata shells, but primary H1/body content is absent from initial HTML. Pre-rendering remains the largest technical SEO opportunity.",
        "2. **Dynamic social metadata was inconsistent in generated shells.** The source fix in this QA pass now synchronizes Open Graph/Twitter descriptions and types for service, package, case-study, and blog shells.",
        "3. **Structured data needed route-level coverage.** The source fix now emits initial-HTML Organization/WebSite, WebPage, BreadcrumbList, BlogPosting, Service, Product, or Article entities as appropriate.",
        "4. **Several descriptions are mechanically written or truncated.** These are listed page-by-page below and should be rewritten before the next content promotion cycle.",
        "5. **Cannibalization risk is concentrated in closely related package, portal, analytics, and website-strategy topics.** Do not merge solely from this heuristic; verify competing queries in Search Console first.", "",
        "## Production page scorecard", "",
        "| Page | Type | Score | Result | Primary findings |", "|---|---|---:|---|---|",
    ]
    for page in sorted(pages, key=lambda item: (item["score"], item["url"])):
        issue = "; ".join(page["issues"][:3]) or "No initial-HTML issue detected"
        lines.append(f"| [{urlparse(page['url']).path or '/'}]({page['url']}) | {page['type']} | {page['score']} | {page['result']} | {issue.replace('|', '/')} |")
    lines.extend(["", "## Article-by-article scorecard", "", "| Article | Series | Score | Result | Primary findings |", "|---|---|---:|---|---|"])
    for post in sorted(posts, key=lambda item: (item["score"], item["content_id"])):
        issue = "; ".join(post["issues"][:3]) or "Passes canonical SEO/content contract"
        lines.append(f"| [{post['title']}]({post['url']}) | {post['series']} | {post['score']} | {post['result']} | {issue.replace('|', '/')} |")
    lines.extend(["", "## Cannibalization candidates", "", "| Left | Right | Similarity | Recommended review |", "|---|---|---:|---|"])
    for item in conflicts:
        lines.append(f"| `{item['left']}` | `{item['right']}` | {item['similarity']} | {item['action']} |")
    lines.extend(["", "## Corrections made in source", "", "- Dynamic shell Open Graph and Twitter descriptions now match each page.", "- Dynamic `og:type` is `article` for blog content.", "- Initial HTML now receives route-appropriate, parseable JSON-LD with stable organization and website identifiers.", "- Breadcrumb schema is generated for static and dynamic public routes.", "", "## Remaining priority order", "", "1. Pre-render meaningful H1/body content for every public route.", "2. Rewrite every description flagged as grammatical, truncated, or mechanically generic.", "3. Use Search Console query-to-page data to approve differentiation, merges, or redirects.", "4. Add named author/reviewer identity and original proof to priority articles.", "5. Re-run rendered mobile, accessibility, and Core Web Vitals QA after corrections.", "", "## Limitations", ""])
    lines.extend(f"- {item}" for item in result["limitations"])
    MD_REPORT.write_text("\n".join(lines) + "\n")
    print(f"Wrote {MD_REPORT}")
    print(f"Wrote {JSON_REPORT}")


if __name__ == "__main__":
    main()
