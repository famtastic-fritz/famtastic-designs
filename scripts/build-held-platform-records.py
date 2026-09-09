#!/usr/bin/env python3
"""Build held TikTok/YouTube drop records for a live campaign (plan T9).

TikTok is sandbox-only pending app review and YouTube is in testing mode and
disabled (see plans/ugc-character-flood/plan.md §A "Ground truth"), so a
campaign's already-approved copy and already-rendered films for those two
platforms cannot be queued to Postiz yet. This script derives, from an
existing marketing/campaigns/<slug>/posting-schedule.json (built by the
normal facebook/instagram/x flow), one held-posting-schedule.json per
platform under marketing/campaigns/<slug>-tiktok/ and
marketing/campaigns/<slug>-youtube/:

  - reuses the exact approved copy (tiktok_reels_shorts / all_channels) and
    the exact already-rendered film — no new render, no new writing
  - fills in the correct per-platform `settings` block from
    plans/ugc-character-flood/plan.md §B (tiktok's full block; youtube's
    `type: public` + a `title` derived from the drop's headline)
  - sets approval.publish permanently false
  - is named `held-posting-schedule.json`, NOT `posting-schedule.json`, so it
    is invisible to scripts/queue-campaign-drops.py and
    scripts/validate-campaign-schedule-hygiene.py until a human deliberately
    activates it with scripts/activate-held-platform-records.py

See marketing/campaigns/_shared/UNBLOCK-REPLAY.md for the full procedure.

Usage:
    python3 scripts/build-held-platform-records.py --campaign front-desk
    python3 scripts/build-held-platform-records.py --campaign every-reason --platforms tiktok
    python3 scripts/build-held-platform-records.py --campaign front-desk --check   # validate only, write nothing
"""
from __future__ import annotations

import argparse
import json
import pathlib
import sys
import time

REPO_ROOT = pathlib.Path(__file__).resolve().parent.parent
CAMPAIGNS_ROOT = REPO_ROOT / "marketing/campaigns"

sys.path.insert(0, str(REPO_ROOT / "scripts"))
from campaign_schema_validate import validate_manifest  # noqa: E402

TIKTOK_SETTINGS = {
    # Verbatim from plan.md §B — a single missing/wrong field rejects the
    # WHOLE Postiz request, not just this channel.
    "privacy_level": "PUBLIC_TO_EVERYONE",
    "duet": False,
    "stitch": False,
    "comment": False,
    "brand_content_toggle": False,
    "brand_organic_toggle": False,
    "content_posting_method": "DIRECT_POST",
    "autoAddMusic": "no",
}

REASON = {
    "tiktok": "TikTok integration is sandbox-only pending app review (plan.md §A). Never queued to Postiz.",
    "youtube": "YouTube integration is in testing mode and disabled (plan.md §A). Never queued to Postiz.",
}


def snake(slug: str) -> str:
    return slug.replace("-", "_")


def build_platform_file(slug: str, source: dict, platform: str, built_at: str) -> dict:
    held_drops = []
    for drop in source["drops"]:
        content_id = f"{drop['content_id']}-{platform}"
        drop_id = f"{drop['drop_id']}-{platform}"

        copy = {}
        if drop.get("copy", {}).get("tiktok_reels_shorts"):
            copy["tiktok_reels_shorts"] = drop["copy"]["tiktok_reels_shorts"]
        if drop.get("copy", {}).get("all_channels"):
            copy["all_channels"] = drop["copy"]["all_channels"]
        if not copy:
            copy = dict(drop.get("copy", {}))

        if platform == "tiktok":
            platform_settings = {"tiktok": dict(TIKTOK_SETTINGS)}
        else:
            platform_settings = {
                "youtube": {
                    "type": "public",
                    "title": (drop.get("headline") or drop.get("label") or "FAMtastic")[:100],
                }
            }

        held_drops.append({
            "drop_number": drop.get("drop_number"),
            "drop_id": drop_id,
            "content_id": content_id,
            "scheduled_time": drop["scheduled_time"],
            "label": (
                f"HELD ({platform}) — mirrors {slug}/{drop['content_id']}. scheduled_time copied "
                "from the live drop for reference ONLY — recompute per "
                "marketing/campaigns/_shared/UNBLOCK-REPLAY.md before ever queueing this."
            ),
            "theme": drop.get("theme", ""),
            "headline": drop.get("headline", ""),
            "state": "idea",
            "channels": [platform],
            "media_type": drop.get("media_type", "video_9x16"),
            "primary_media": drop["primary_media"],
            "copy": copy,
            "tags": list(drop.get("tags", [])),
            "utm": {
                "source": drop["utm"]["source"],
                "medium": drop["utm"]["medium"],
                "campaign": drop["utm"]["campaign"],
                "content": content_id,
            },
            "approval": {"content": True, "media": True, "publish": False},
            "platform_settings": platform_settings,
            "representation": drop.get("representation", ""),
            "source_blog_post": drop.get("source_blog_post", ""),
            "provider_ids": {},
            "evidence": [],
            "held": {
                "source_campaign": slug,
                "source_content_id": drop["content_id"],
                "platform": platform,
                "reason": REASON[platform],
                "scheduled_time_is_placeholder": True,
                "built_at": built_at,
                "built_by_task": "T9",
            },
        })

    return {
        "schema_version": 2,
        "campaign_id": f"{snake(slug)}_{platform}_held",
        "program_id": source["program_id"],
        "series_id": source.get("series_id"),
        "campaign_name": f"{source.get('campaign_name', slug)} — {platform.upper()} (HELD, T9)",
        "created_at": built_at,
        "status": "draft",
        "schedule_type": "held_replay_shadow",
        "time_zone": source.get("time_zone", "America/New_York"),
        "landing_url": source.get("landing_url"),
        "publish_arming": {
            "mode": "single_env_switch",
            "env": "FAMTASTIC_MARKETING_PUBLISH",
            "note": (
                "Held record. Not queued to Postiz under any circumstance until a human runs "
                "scripts/activate-held-platform-records.py per "
                "marketing/campaigns/_shared/UNBLOCK-REPLAY.md."
            ),
        },
        "media_resolution": {
            "policy": "resolve_at_runtime_fail_loud",
            "note": (
                f"primary_media re-uses the exact film already rendered and graded for "
                f"{slug}/{platform} publish; no new render is required for replay."
            ),
        },
        "channels_note": (
            f"HELD RECORD — {platform} only. Built by T9 (plan.md) so that when {platform} "
            "unblocks the drop is a one-command replay instead of a rebuild. See "
            "marketing/campaigns/_shared/UNBLOCK-REPLAY.md."
        ),
        "held_summary": {
            "source_campaign": slug,
            "source_schedule": f"marketing/campaigns/{slug}/posting-schedule.json",
            "platform": platform,
            "drop_count": len(held_drops),
            "reason": REASON[platform],
            "activation_doc": "marketing/campaigns/_shared/UNBLOCK-REPLAY.md",
        },
        "drops": held_drops,
    }


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--campaign", required=True, help="live campaign slug, e.g. front-desk")
    ap.add_argument("--platforms", default="tiktok,youtube", help="comma list, default tiktok,youtube")
    ap.add_argument("--check", action="store_true", help="validate only; write nothing")
    args = ap.parse_args()

    source_path = CAMPAIGNS_ROOT / args.campaign / "posting-schedule.json"
    if not source_path.is_file():
        sys.stderr.write(f"FAIL: no live posting schedule at {source_path.relative_to(REPO_ROOT)}\n")
        return 66

    source = json.loads(source_path.read_text())
    built_at = time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime())
    platforms = [p.strip() for p in args.platforms.split(",") if p.strip()]
    for p in platforms:
        if p not in REASON:
            sys.stderr.write(f"FAIL: unknown platform {p!r} (expected tiktok and/or youtube)\n")
            return 64

    ok = True
    for platform in platforms:
        manifest = build_platform_file(args.campaign, source, platform, built_at)
        problems = validate_manifest(manifest)
        out_dir = CAMPAIGNS_ROOT / f"{args.campaign}-{platform}"
        out_path = out_dir / "held-posting-schedule.json"
        status = "VALID" if not problems else "INVALID"
        ok = ok and not problems
        action = "would write" if args.check else "writing"
        print(f"{status} ({action} {out_path.relative_to(REPO_ROOT)}, {len(manifest['drops'])} drops)")
        for p in problems:
            print(f"   - {p}")
        if not args.check and not problems:
            out_dir.mkdir(parents=True, exist_ok=True)
            out_path.write_text(json.dumps(manifest, indent=2, ensure_ascii=False) + "\n")

    return 0 if ok else 1


if __name__ == "__main__":
    raise SystemExit(main())
