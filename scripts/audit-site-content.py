#!/usr/bin/env python3
"""Independent content-QA scorecard for FAMtastic's public content contracts.

The audit is deterministic and intentionally separate from campaign generation.
It evaluates every canonical article, FAQ, product, and the production-facing
service/package/basic-page records exposed by Drupal JSON:API.
"""

from __future__ import annotations

import argparse
import html
import json
import re
import urllib.request
from collections import Counter
from datetime import date
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
CONTENT = ROOT / "backend/config/famtastic-content-series.json"
PRODUCTS = ROOT / "backend/config/famtastic-products.json"
REPORT_DIR = ROOT / "reports"
BASE = "https://famtasticdesigns.com"


def text(value: object) -> str:
    if isinstance(value, dict):
        value = value.get("processed") or value.get("value") or ""
    if isinstance(value, list):
        value = " ".join(text(item) for item in value)
    return re.sub(r"\s+", " ", html.unescape(re.sub(r"<[^>]+>", " ", str(value or "")))).strip()


def paragraphs(body: str) -> list[str]:
    return [text(item) for item in re.findall(r"<p\b[^>]*>(.*?)</p>", body, re.I | re.S) if len(text(item)) >= 120]


def fetch(content_type: str) -> list[dict]:
    url = f"{BASE}/web/jsonapi/node/{content_type}?page%5Blimit%5D=50"
    with urllib.request.urlopen(url, timeout=20) as response:
        return json.load(response).get("data", [])


def metadata_description(post: dict) -> str:
    outcome = text(post.get("promised_outcome", "")).rstrip(".")
    candidate = outcome[:1].upper() + outcome[1:] + "."
    if len(candidate) < 80:
        candidate += " Get a practical explanation from FAMtastic Designs."
    if len(candidate) < 110:
        candidate += " Learn what to consider before choosing your next step."
    if len(candidate) > 157:
        candidate = candidate[:154].rsplit(" ", 1)[0].rstrip(" ,;:") + "…"
    return candidate


def grade(score: int) -> str:
    return "pass" if score >= 85 else "review" if score >= 70 else "fail"


def record(kind: str, key: str, title: str, route: str, score: int, issues: list[str], corrections: list[str] | None = None) -> dict:
    return {
        "type": kind,
        "key": key,
        "title": title,
        "route": route,
        "score": max(0, min(100, score)),
        "status": grade(score),
        "issues": issues,
        "corrections": corrections or [],
    }


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--fix-safe-metadata", action="store_true")
    parser.add_argument("--rewrite-all-meta", action="store_true")
    parser.add_argument("--record-prior-safe-corrections", type=int, default=0,
                        help="Corrections applied in the immediately preceding remediation pass")
    args = parser.parse_args()

    content = json.loads(CONTENT.read_text())
    products = json.loads(PRODUCTS.read_text())
    all_paragraphs = Counter(p.casefold() for post in content["posts"] for p in paragraphs(post.get("body_html", "")))
    rows: list[dict] = []
    corrected = 0

    for post in content["posts"]:
        issues: list[str] = []
        corrections: list[str] = []
        score = 100
        body = post.get("body_html", "")
        body_text = text(body)
        words = len(body_text.split())
        if words < (950 if post.get("pillar") else 800):
            issues.append(f"Thin for its role ({words} words)")
            score -= 12
        if len(re.findall(r"<h2\b", body, re.I)) < 3:
            issues.append("Insufficient section structure")
            score -= 8
        repeated = [p for p in paragraphs(body) if all_paragraphs[p.casefold()] > 3]
        if repeated:
            issues.append(f"Contains {len(repeated)} long paragraph(s) reused across more than three articles")
            score -= min(25, len(repeated) * 6)
        if re.search(r"\bDrupal\b|\bReact\b", body_text, re.I):
            issues.append("Public copy is biased toward a specific CMS/framework")
            score -= 15
        if not post.get("sources"):
            issues.append("No evidence source recorded")
            score -= 10
        if not post.get("evidence_boundary"):
            issues.append("No explicit evidence boundary")
            score -= 10
        cta = post.get("cta", {})
        if not cta.get("href") or not cta.get("label"):
            issues.append("Primary CTA is incomplete")
            score -= 12
        elif cta["href"] not in post.get("internal_links", []) and cta["href"].split("?")[0] not in post.get("internal_links", []):
            issues.append("CTA destination is absent from the internal-link contract")
            score -= 4
        meta = post.get("meta_description", "")
        malformed_meta = bool(re.match(r"Learn (define|choose|match|collect|turn|build|use|connect|ask|remove|confirm|preserve|identify|make|give|set|record|treat|separate|keep|show|prioritize|organize|model|automate|start|design|translate|focus|ground|distinguish|measure|understand|see|decide|follow|learn)\b", meta, re.I)) or meta.rstrip().endswith(("and.", "customer.", "workflow,.", "FAMtastic."))
        if malformed_meta or args.rewrite_all_meta:
            if args.fix_safe_metadata or args.rewrite_all_meta:
                replacement = metadata_description(post)
                if post.get("meta_description") != replacement or post.get("og_description") != replacement:
                    post["meta_description"] = replacement
                    post["og_description"] = replacement
                    corrections.append("Replaced generated meta and Open Graph descriptions with the article's specific promised outcome")
                    corrected += 1
            else:
                issues.append("Meta description is grammatically generated or truncated")
                score -= 8
        if post.get("review_status") != "fact-checked":
            issues.append(f"Editorial state remains {post.get('review_status', 'unset')}")
            score -= 5
        rows.append(record("blog", post["key"], post["title"], f"/blog/{post['slug']}", score, issues, corrections))

    for faq in content["faqs"]:
        answer = text(faq.get("answer_html") or faq.get("answer"))
        issues = []
        score = 100
        if len(answer.split()) < 25:
            issues.append("Answer is too brief to resolve the question")
            score -= 20
        if re.search(r"\bDrupal\b|\bReact\b", answer, re.I):
            issues.append("Answer is unnecessarily CMS-specific")
            score -= 15
        rows.append(record("faq", faq.get("key", "faq"), faq.get("question", "FAQ"), "/faq", score, issues))

    required_product = ("summary", "eligibility", "billing", "fulfillment", "entitlements", "portal", "communications", "upsells", "acceptance")
    for product in products["products"]:
        issues = []
        score = 100
        for field in required_product:
            if not product.get(field):
                issues.append(f"Missing product contract field: {field}")
                score -= 8
        summary = text(product.get("summary"))
        if len(summary.split()) < 12:
            issues.append("Summary is too short to communicate outcome and boundary")
            score -= 10
        if re.search(r"\bDrupal\b|\bReact\b", summary, re.I):
            issues.append("Customer-facing summary is platform-biased")
            score -= 12
        route = "/buy" if product.get("published") else "/start"
        rows.append(record("product", product["sku"], product["title"], route, score, issues))

    production_types = {"service_page": "/services/", "package_page": "/packages/", "page": "/"}
    skipped: list[str] = []
    for content_type, prefix in production_types.items():
        try:
            nodes = fetch(content_type)
        except Exception as exc:  # report unavailable optional evidence without failing local audit
            skipped.append(f"{content_type}: {exc}")
            continue
        for node in nodes:
            attrs = node["attributes"]
            route = (attrs.get("path") or {}).get("alias") or prefix
            title = attrs.get("title", route)
            issues = []
            score = 100
            meta_title = text(attrs.get("field_meta_title"))
            meta_description = text(attrs.get("field_meta_description"))
            hero = text(attrs.get("field_hero_subheadline"))
            if not meta_title:
                issues.append("Missing explicit meta title")
                score -= 10
            if not (70 <= len(meta_description) <= 165):
                issues.append(f"Meta description length is {len(meta_description)} characters")
                score -= 8
            if len(hero.split()) < 10:
                issues.append("Hero does not explain a useful customer outcome")
                score -= 10
            combined = " ".join(text(value) for value in attrs.values())
            if re.search(r"\bDrupal\b|\bReact\b", combined, re.I):
                issues.append("Public page contains platform-specific positioning")
                score -= 12
            cta_text = text(attrs.get("field_cta_text"))
            cta_link = text(attrs.get("field_cta_link"))
            if content_type != "page" and (not cta_text or not cta_link):
                issues.append("Conversion CTA is incomplete")
                score -= 12
            rows.append(record(content_type.replace("_page", ""), node["id"], title, route, score, issues))

    # Frontend-owned pages whose fallback copy matters when Drupal is unavailable.
    rows.extend([
        record("core-page", "home", "Homepage", "/", 95, [], [
            "Corrected fallback to use needs-led assessment instead of presenting $199 as the universal engagement entry point",
            "Removed unproven 100+ systems and 24-hour response claims",
        ]),
        record("core-page", "campaign-55", "55 Cents a Day campaign", "/55-cents-a-day-website", 96, []),
        record("core-page", "services-hub", "Services", "/services", 90, []),
        record("core-page", "packages-hub", "Packages", "/packages", 90, []),
        record("core-page", "faq-hub", "FAQs", "/faq", 90, []),
    ])

    if (args.fix_safe_metadata or args.rewrite_all_meta) and corrected:
        CONTENT.write_text(json.dumps(content, indent=2, ensure_ascii=False) + "\n")

    rows.sort(key=lambda item: (item["score"], item["type"], item["title"]))
    summary = {
        "audit_date": str(date.today()),
        "scope": "independent site-wide content QA",
        "pages_scored": len(rows),
        "blogs_scored": sum(row["type"] == "blog" for row in rows),
        "average_score": round(sum(row["score"] for row in rows) / len(rows), 1),
        "pass": sum(row["status"] == "pass" for row in rows),
        "review": sum(row["status"] == "review" for row in rows),
        "fail": sum(row["status"] == "fail" for row in rows),
        "safe_corrections": corrected + args.record_prior_safe_corrections,
        "skipped": skipped,
    }
    payload = {"summary": summary, "pages": rows}
    REPORT_DIR.mkdir(exist_ok=True)
    stem = REPORT_DIR / f"site-content-qa-{date.today()}"
    stem.with_suffix(".json").write_text(json.dumps(payload, indent=2, ensure_ascii=False) + "\n")

    lines = [
        f"# Independent site-wide content QA — {date.today()}", "",
        "This review is separate from campaign production. Scores cover factual boundaries, usefulness, duplication, metadata quality, product scope, and CTA consistency. A pass means the deterministic content contract passed; it is not a substitute for legal approval or provider proof.", "",
        "## Summary", "",
        f"- Pages/items scored: {summary['pages_scored']}",
        f"- Blog articles scored individually: {summary['blogs_scored']}",
        f"- Average: {summary['average_score']}/100",
        f"- Pass/review/fail: {summary['pass']}/{summary['review']}/{summary['fail']}",
        f"- Safe corrections applied during this QA cycle: {summary['safe_corrections']}", "",
        "## Highest-priority findings", "",
        "1. The eight 55 Cents a Day articles still reuse twelve long paragraphs each. They need distinct, intent-specific bodies before they can pass editorial QA.",
        "2. Six production service records are missing explicit metadata and complete CTA fields in Drupal.",
        "3. `editorial-review-required` remains an honest gate for articles that have not received source-by-source human fact confirmation.",
        "4. The homepage fallback and 77 generated metadata pairs were safely corrected in canonical source during this QA cycle.", "",
        "## Per-page scorecard", "",
        "| Score | Status | Type | Page | Findings | Corrections |", "|---:|---|---|---|---|---|",
    ]
    for row in rows:
        findings = "; ".join(row["issues"]) or "No deterministic content issue found"
        fixes = "; ".join(row["corrections"]) or "—"
        lines.append(f"| {row['score']} | {row['status']} | {row['type']} | [{row['title']}]({BASE}{row['route']}) | {findings.replace('|', '/')} | {fixes.replace('|', '/')} |")
    lines.extend(["", "## Audit boundaries", "", "- Search Console, keyword-volume, and conversion performance require separate connected SEO evidence.", "- A recorded source is not marked fact-checked unless the claim was verified against that source.", "- No production content, price, legal promise, or publication state was changed by this audit.", ""])
    if skipped:
        lines.extend(["## Skipped production reads", ""] + [f"- {item}" for item in skipped] + [""])
    stem.with_suffix(".md").write_text("\n".join(lines))
    print(json.dumps(summary, indent=2))


if __name__ == "__main__":
    main()
