#!/usr/bin/env python3
"""Fail when a campaign URL resolves only to the generic SPA shell."""

from __future__ import annotations

import sys
import urllib.request


ROUTES = {
    "https://famtasticdesigns.com/55-cents-a-day-website": (
        "$199 Website | About 55 Cents a Day",
        "first-year basic hosting and a domain path included",
    ),
    "https://famtasticdesigns.com/blog/what-should-a-small-business-website-do": (
        "What Should a Small-Business Website Actually Do?",
    ),
}


def main() -> int:
    failed = False
    for url, required_markers in ROUTES.items():
        request = urllib.request.Request(url, headers={"User-Agent": "FAMtasticCampaignQA/1.0"})
        with urllib.request.urlopen(request, timeout=15) as response:
            body = response.read().decode("utf-8", errors="replace")
            status = response.status
        missing = [marker for marker in required_markers if marker not in body]
        if status != 200 or missing:
            print(f"FAIL {url} status={status} missing={missing}")
            failed = True
        else:
            print(f"PASS {url} status={status} campaign markers present")
    return 1 if failed else 0


if __name__ == "__main__":
    sys.exit(main())
