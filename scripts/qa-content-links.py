#!/usr/bin/env python3
"""qa-content-links.py — crawl every published blog_post and check every
link its body actually contains, live, following redirects to a final
status code. Read-only: never edits or publishes anything.

Why this exists: three separate production-breaking link bugs were found by
hand in one session (2026-09-04) — a campaign post linking to a blog article
that was never written, a tracked-link base URL that was never a real route,
and a Drupal-backend-prefix bug copied into every blog draft. None of these
were caught mechanically. This script is that mechanical check, made
repeatable instead of a one-off agent prompt.

Usage:
  python3 scripts/qa-content-links.py                 # check all published posts
  python3 scripts/qa-content-links.py --slug <slug>    # check just one post
  python3 scripts/qa-content-links.py --json           # machine-readable output

Exit code 0 = no broken links found. Exit code 1 = at least one broken link.
"""
from __future__ import annotations

import argparse
import json
import re
import sys
import urllib.error
import urllib.request
from urllib.parse import urljoin, urlparse

JSONAPI_BASE = "https://famtasticdesigns.com/web/jsonapi"
SITE_ORIGIN = "https://famtasticdesigns.com"
PAGE_LIMIT = 50

LINK_RE = re.compile(r'href=["\']([^"\']+)["\']')
BARE_URL_RE = re.compile(r'(?<![("\'])https?://famtasticdesigns\.com[^\s)"\'<]*')


def fetch_json(url: str) -> dict:
    req = urllib.request.Request(url, headers={"Accept": "application/vnd.api+json"})
    with urllib.request.urlopen(req, timeout=20) as resp:
        return json.loads(resp.read().decode("utf-8"))


def fetch_all_posts(single_slug: str | None) -> list[dict]:
    if single_slug:
        url = f"{JSONAPI_BASE}/node/blog_post?filter[path.alias]=/blog/{single_slug}"
        data = fetch_json(url)
        return data.get("data", [])

    posts: list[dict] = []
    url = f"{JSONAPI_BASE}/node/blog_post?filter[status]=1&page[limit]={PAGE_LIMIT}"
    while url:
        data = fetch_json(url)
        posts.extend(data.get("data", []))
        next_link = data.get("links", {}).get("next", {})
        url = next_link.get("href") if isinstance(next_link, dict) else None
    return posts


def extract_links(body_html: str) -> set[str]:
    found = set(LINK_RE.findall(body_html))
    found |= set(BARE_URL_RE.findall(body_html))
    return found


def normalize(link: str) -> str | None:
    if link.startswith("internal:"):
        link = link[len("internal:"):]
    if link.startswith("#") or link.startswith("mailto:") or link.startswith("tel:"):
        return None
    if link.startswith("/"):
        return urljoin(SITE_ORIGIN, link)
    parsed = urlparse(link)
    if not parsed.scheme:
        return None
    return link


def check_url(url: str) -> int:
    req = urllib.request.Request(url, method="GET", headers={"User-Agent": "famtastic-content-qa/1.0"})
    try:
        with urllib.request.urlopen(req, timeout=15) as resp:
            return resp.status
    except urllib.error.HTTPError as e:
        return e.code
    except Exception:
        return 0  # DNS failure, timeout, etc — treated as broken


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--slug", help="Check only this one post's slug instead of every published post.")
    ap.add_argument("--json", action="store_true", help="Machine-readable output.")
    args = ap.parse_args()

    posts = fetch_all_posts(args.slug)
    if not posts:
        print(f"No published post(s) found{' for slug ' + args.slug if args.slug else ''}.", file=sys.stderr)
        return 1

    checked_urls: dict[str, int] = {}
    broken: list[dict] = []
    posts_checked = 0

    for post in posts:
        attrs = post.get("attributes", {})
        title = attrs.get("title", "(untitled)")
        path = (attrs.get("path") or {}).get("alias") or f"/node/{post.get('id')}"
        nid = attrs.get("drupal_internal__nid")
        body = (attrs.get("body") or {}).get("value") or ""
        posts_checked += 1

        for raw in extract_links(body):
            url = normalize(raw)
            if not url:
                continue
            if url not in checked_urls:
                checked_urls[url] = check_url(url)
            status = checked_urls[url]
            if status >= 400 or status == 0:
                broken.append({
                    "post_title": title,
                    "post_path": path,
                    "post_nid": nid,
                    "broken_url": url,
                    "status": status,
                })

    result = {
        "posts_checked": posts_checked,
        "unique_urls_checked": len(checked_urls),
        "broken_count": len(broken),
        "broken": broken,
    }

    if args.json:
        print(json.dumps(result, indent=2))
    else:
        print(f"Checked {posts_checked} post(s), {len(checked_urls)} unique link(s).")
        if not broken:
            print("PASS — no broken links found.")
        else:
            print(f"FAIL — {len(broken)} broken link occurrence(s):")
            for b in broken:
                print(f"  [{b['status']}] {b['broken_url']}")
                print(f"      in \"{b['post_title']}\" ({b['post_path']}, nid {b['post_nid']})")

    return 1 if broken else 0


if __name__ == "__main__":
    raise SystemExit(main())
