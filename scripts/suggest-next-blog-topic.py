#!/usr/bin/env python3
"""suggest-next-blog-topic.py — find real gaps between the original content
plan, what's actually drafted, and what's actually live.

Why this exists: Fritz asked for "more blog creation and some automation
there" (2026-09-04 session). Rather than a content generator, this is a
gap-finder: it reads the original content plan
(backend/config/famtastic-content-series.json "posts" array — 80 planned
articles, each with a slug/title/category), cross-references
marketing/blog/drafts/ (work in progress, whether stub or finished), and
cross-references the live published post list via production's JSON:API
(the same endpoint scripts/qa-content-links.py already uses successfully:
https://famtasticdesigns.com/web/jsonapi/node/blog_post — NOT the bare
/jsonapi path, which 404s on this stack; see that script's own comment).

It reports three buckets:
  1. LIVE       — planned topic is confirmed published right now.
  2. DRAFTED     — a marketing/blog/drafts/<slug>/ folder exists but the
                   topic isn't live yet (may be a real draft or a stub —
                   this script doesn't grade draft quality, only presence).
  3. NOT STARTED — planned topic has neither a draft folder nor a live post.
                   This is the real backlog: what to write next.

It also flags the reverse case — a drafts/ folder whose slug isn't in the
plan at all (ad-hoc work done outside the original 80-post series, which is
fine, just worth knowing about).

This script is read-only. It writes nothing, publishes nothing, and never
guesses at a category/tag classification (that stays in
scripts/publish-blog-draft.py's DRAFT_CLASSIFICATION, which is never
guessed either).

Usage:
    python3 scripts/suggest-next-blog-topic.py                # human-readable report
    python3 scripts/suggest-next-blog-topic.py --json          # machine-readable
    python3 scripts/suggest-next-blog-topic.py --no-live-check # skip the network
                                                                 call, plan+drafts only
"""
from __future__ import annotations

import argparse
import json
import pathlib
import sys
import urllib.error
import urllib.request

REPO_ROOT = pathlib.Path(__file__).resolve().parent.parent
CONTENT_SERIES_PATH = REPO_ROOT / "backend/config/famtastic-content-series.json"
DRAFTS_ROOT = REPO_ROOT / "marketing/blog/drafts"

# Same endpoint scripts/qa-content-links.py already proved works against this
# production stack — the bare /jsonapi path 404s here; /web/jsonapi is the
# backend's real JSON:API mount. Read-only GET, no credential needed.
JSONAPI_BASE = "https://famtasticdesigns.com/web/jsonapi"
PAGE_LIMIT = 50


def fetch_json(url: str) -> dict:
    req = urllib.request.Request(url, headers={"Accept": "application/vnd.api+json"})
    with urllib.request.urlopen(req, timeout=20) as resp:
        return json.loads(resp.read().decode("utf-8"))


def fetch_live_slugs() -> tuple[set[str], str | None]:
    """Returns (slugs, error). error is None on success, else a short
    human-readable reason the live check couldn't complete — the caller
    treats that as UNKNOWN, never as "nothing is live"."""
    slugs: set[str] = set()
    url = f"{JSONAPI_BASE}/node/blog_post?filter[status]=1&page[limit]={PAGE_LIMIT}"
    try:
        while url:
            data = fetch_json(url)
            for item in data.get("data", []):
                alias = (item.get("attributes", {}) or {}).get("path", {}).get("alias")
                if alias:
                    # alias looks like "/blog/<slug>"
                    slug = alias.rsplit("/", 1)[-1]
                    slugs.add(slug)
            next_link = data.get("links", {}).get("next", {})
            url = next_link.get("href") if isinstance(next_link, dict) else None
    except (urllib.error.URLError, TimeoutError, OSError) as exc:
        return slugs, str(exc)
    except json.JSONDecodeError as exc:
        return slugs, f"could not parse JSON:API response: {exc}"
    return slugs, None


def load_plan() -> list[dict]:
    if not CONTENT_SERIES_PATH.is_file():
        print(f"ERROR: content plan not found at {CONTENT_SERIES_PATH}", file=sys.stderr)
        sys.exit(2)
    data = json.loads(CONTENT_SERIES_PATH.read_text())
    return data.get("posts", [])


def load_draft_slugs() -> set[str]:
    if not DRAFTS_ROOT.is_dir():
        return set()
    return {p.name for p in DRAFTS_ROOT.iterdir() if p.is_dir()}


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--json", action="store_true", help="machine-readable output")
    parser.add_argument(
        "--no-live-check", action="store_true",
        help="skip the network call to production; report plan-vs-drafts only",
    )
    args = parser.parse_args()

    plan = load_plan()
    draft_slugs = load_draft_slugs()

    live_slugs: set[str] = set()
    live_check_error: str | None = None
    if not args.no_live_check:
        live_slugs, live_check_error = fetch_live_slugs()

    live_bucket: list[dict] = []
    drafted_bucket: list[dict] = []
    not_started_bucket: list[dict] = []

    for post in plan:
        slug = post.get("slug", post.get("key", ""))
        entry = {
            "slug": slug,
            "title": post.get("title", ""),
            "category": post.get("category", ""),
            "series": post.get("series", ""),
            "sequence": post.get("sequence"),
            "plan_status_field": post.get("status", ""),
        }
        is_live = slug in live_slugs
        has_draft = slug in draft_slugs

        if is_live:
            live_bucket.append(entry)
        elif has_draft:
            drafted_bucket.append(entry)
        else:
            not_started_bucket.append(entry)

    plan_slugs = {post.get("slug", post.get("key", "")) for post in plan}
    orphan_drafts = sorted(draft_slugs - plan_slugs)

    result = {
        "schema": "famtastic.blog-topic-gap-report.v1",
        "live_check": "skipped" if args.no_live_check else ("ok" if live_check_error is None else "failed"),
        "live_check_error": live_check_error,
        "plan_total": len(plan),
        "live_count": len(live_bucket),
        "drafted_count": len(drafted_bucket),
        "not_started_count": len(not_started_bucket),
        "not_started": not_started_bucket,
        "drafted_not_live": drafted_bucket,
        "orphan_drafts_not_in_plan": orphan_drafts,
    }

    if args.json:
        print(json.dumps(result, indent=2))
        return 0

    print("=== Blog topic gap report ===")
    print(f"Plan (backend/config/famtastic-content-series.json): {len(plan)} planned topics")
    if args.no_live_check:
        print("Live-post check: SKIPPED (--no-live-check)")
    elif live_check_error:
        print(f"Live-post check: FAILED — {live_check_error}")
        print("  (treat 'not started' below as plan-vs-drafts only; live status is UNKNOWN,")
        print("   not confirmed absent — do not conclude these topics are actually missing live.)")
    else:
        print(f"Live-post check: OK — {len(live_slugs)} published posts found via JSON:API")

    print(f"\nLive and confirmed published: {len(live_bucket)}")
    print(f"Drafted (folder exists) but not confirmed live: {len(drafted_bucket)}")
    for e in drafted_bucket:
        print(f"  - {e['slug']}  ({e['title']})")

    print(f"\nNOT STARTED — no draft folder, not confirmed live ({len(not_started_bucket)}):")
    if not not_started_bucket:
        print("  (none — every planned topic has at least a draft folder or is live)")
    for e in not_started_bucket:
        seq = f"#{e['sequence']}" if e["sequence"] is not None else "?"
        print(f"  - [{e['category']}] {seq} {e['slug']}  —  {e['title']}")

    if orphan_drafts:
        print(f"\nDraft folders that exist but aren't in the original plan ({len(orphan_drafts)}):")
        for slug in orphan_drafts:
            print(f"  - {slug}")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
