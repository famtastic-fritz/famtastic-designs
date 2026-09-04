#!/usr/bin/env python3
"""Scaffold and validate a campaign posting schedule.

Campaigns differ — cadence, channels, media mix, copy shape — so the difference
belongs in data, not in a new script each time. This creates a campaign folder
with a posting-schedule.json conforming to
marketing/engine/schemas/posting-schedule.schema.json, which is the only file
scripts/queue-campaign-drops.py needs in order to post it.

    # scaffold 4 drops, 2.5h apart, first one tonight at 23:50 ET
    python3 scripts/new-campaign.py --slug spring-refresh \
        --name "Spring Refresh" --drops 4 \
        --anchor 2026-09-03T23:50:00-04:00 --interval 150

    # check an existing campaign before anyone arms anything
    python3 scripts/new-campaign.py --validate cost-is-not-the-reason

Then fill in copy, channels, and media paths per drop, re-run --validate, and
dry-run it:

    python3 scripts/queue-campaign-drops.py --campaign spring-refresh --dry-run
"""

from __future__ import annotations

import argparse
import json
import pathlib
import re
import sys
from datetime import datetime, timedelta

REPO_ROOT = pathlib.Path(__file__).resolve().parent.parent
CAMPAIGNS_ROOT = REPO_ROOT / "marketing/campaigns"
SCHEMA_PATH = REPO_ROOT / "marketing/engine/schemas/posting-schedule.schema.json"

sys.path.insert(0, str(REPO_ROOT / "scripts"))
from campaign_schema_validate import validate_manifest  # noqa: E402

# Channel labels the shared map in queue-campaign-drops.py already understands.
KNOWN_CHANNELS = {
    "facebook", "facebook_video", "instagram", "instagram_reels",
    "instagram_carousel", "x", "x_thread", "tiktok", "youtube",
    "youtube_shorts", "linkedin",
}
ISO_WITH_OFFSET = re.compile(r"^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:[+-]\d{2}:\d{2}|Z)$")


def scaffold(args: argparse.Namespace) -> int:
    campaign_dir = CAMPAIGNS_ROOT / args.slug
    schedule_path = campaign_dir / "posting-schedule.json"
    if schedule_path.exists() and not args.force:
        sys.stderr.write(
            f"REFUSED: {schedule_path.relative_to(REPO_ROOT)} already exists.\n"
            "Overwriting would discard live provider_ids and lose track of posts\n"
            "already queued in Postiz. Pass --force only if you are certain.\n"
        )
        return 1

    anchor = datetime.fromisoformat(args.anchor)
    campaign_id = args.slug.replace("-", "_")
    drops = []
    for n in range(args.drops):
        when = anchor + timedelta(minutes=args.interval * n)
        drop_id = f"drop-{n + 1:02d}"
        drops.append({
            "drop_number": n + 1,
            "drop_id": drop_id,
            "content_id": drop_id,
            "scheduled_time": when.isoformat(),
            "label": f"Drop {n + 1} — {when:%-I:%M %p} {when:%b %-d}",
            "theme": "TODO: what this drop argues",
            "headline": "TODO: the one line a scroller reads",
            "state": "idea",
            "channels": ["facebook", "instagram", "x"],
            "media_type": "image_1x1",
            "primary_media": f"marketing/campaigns/{args.slug}/images/TODO.jpg",
            "copy": {
                "facebook_instagram": "TODO: long-form copy for Facebook and Instagram.",
                "x_post": "TODO: short copy for X.",
                "all_channels": "TODO: fallback copy used when no channel-specific key matches.",
            },
            "tags": ["#famtasticdesigns"],
            "utm": {
                "source": "famtastic",
                "medium": "organic_social",
                "campaign": campaign_id,
                "content": drop_id,
            },
            "approval": {"content": False, "media": False, "publish": False},
            "provider_ids": {},
        })

    schedule = {
        "schema_version": 2,
        "campaign_id": campaign_id,
        "program_id": args.program_id,
        "series_id": args.series_id,
        "campaign_name": args.name or args.slug.replace("-", " ").title(),
        "created_at": datetime.now().astimezone().isoformat(timespec="seconds"),
        "status": "draft",
        "schedule_type": "rapid_evaluation_sequence",
        "time_zone": args.time_zone,
        "landing_url": args.landing,
        "cadence": {
            "anchor_et": args.anchor,
            "interval_minutes": args.interval,
            "set_by": "scripts/new-campaign.py",
        },
        "publish_arming": {
            "mode": "single_env_switch",
            "env": "FAMTASTIC_MARKETING_PUBLISH",
            "note": "Queue creates DRAFTS unarmed. Converting to a live schedule "
                    "requires FAMTASTIC_MARKETING_PUBLISH=true on the host that runs it.",
        },
        "media_resolution": {
            "policy": "resolve_at_runtime_fail_loud",
            "note": "Media paths resolve on the host running the queue. A missing "
                    "file blocks that drop by name; it is never substituted or skipped.",
        },
        "drops": drops,
    }

    for sub in ("images", "videos", "articles"):
        (campaign_dir / sub).mkdir(parents=True, exist_ok=True)
    schedule_path.write_text(json.dumps(schedule, indent=2) + "\n")

    print(f"created {schedule_path.relative_to(REPO_ROOT)} with {args.drops} drops")
    for drop in drops:
        print(f"  {drop['drop_id']}  {drop['scheduled_time']}")
    print("\nnext:")
    print("  1. fill in copy, channels, and media paths per drop")
    print("  2. set each drop's approval flags once the creative is signed off")
    print(f"  3. python3 scripts/new-campaign.py --validate {args.slug}")
    print(f"  4. python3 scripts/queue-campaign-drops.py --campaign {args.slug} --dry-run")
    return 0


def validate(slug: str) -> int:
    schedule_path = CAMPAIGNS_ROOT / slug / "posting-schedule.json"
    if not schedule_path.is_file():
        sys.stderr.write(f"FAIL: no posting-schedule.json for campaign '{slug}'\n")
        return 1
    schedule = json.loads(schedule_path.read_text())

    problems: list[str] = list(validate_manifest(schedule))

    seen_ids: set[str] = set()
    seen_content: set[str] = set()
    known = KNOWN_CHANNELS | set(schedule.get("channel_map", {}))
    times: list[datetime] = []

    for drop in schedule.get("drops", []):
        did = drop.get("drop_id", "<unnamed>")
        if did in seen_ids:
            problems.append(f"{did}: duplicate drop_id")
        seen_ids.add(did)

        cid = drop.get("content_id", "")
        if not cid:
            problems.append(f"{did}: missing content_id (the idempotency key)")
        elif cid in seen_content:
            problems.append(f"{did}: content_id '{cid}' is reused; reruns would collide")
        seen_content.add(cid)

        when = drop.get("scheduled_time", "")
        if not ISO_WITH_OFFSET.match(when):
            problems.append(
                f"{did}: scheduled_time '{when}' needs an explicit UTC offset "
                "(e.g. 2026-09-03T23:50:00-04:00)"
            )
        else:
            times.append(datetime.fromisoformat(when))

        if not drop.get("copy"):
            problems.append(f"{did}: no copy variants")
        for unknown in [c for c in drop.get("channels", []) if c not in known]:
            problems.append(
                f"{did}: channel '{unknown}' has no integration mapping "
                "(add it to the campaign's channel_map)"
            )
        if not drop.get("channels"):
            problems.append(f"{did}: no channels")

        for key in ("primary_media", "backup_media", "supporting_media"):
            rel = drop.get(key)
            if rel and "TODO" in rel:
                problems.append(f"{did}: {key} is still a TODO placeholder")
        for rel in [drop.get("primary_media")] + list(drop.get("carousel_slides", [])):
            if rel and not (REPO_ROOT / rel).is_file():
                # Not fatal: video lives only on the operator host by design.
                print(f"  NOTE {did}: media absent on this host — {rel}")

        for field in ("headline", "theme"):
            if "TODO" in str(drop.get(field, "")):
                problems.append(f"{did}: {field} is still a TODO placeholder")
        for ck, cv in (drop.get("copy") or {}).items():
            if "TODO" in cv:
                problems.append(f"{did}: copy.{ck} is still a TODO placeholder")

    if times and times != sorted(times):
        problems.append("drops are not in chronological order by scheduled_time")

    if problems:
        print(f"BLOCKED {slug} — {len(problems)} problem(s):")
        for p in problems:
            print(f"  - {p}")
        return 1
    print(f"READY {slug} — {len(schedule['drops'])} drops, schema v2, all checks pass")
    print(f"  window: {min(times).isoformat()} .. {max(times).isoformat()}")
    return 0


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--slug", help="campaign folder name, e.g. spring-refresh")
    parser.add_argument("--name", help="human-readable campaign name")
    parser.add_argument("--drops", type=int, default=4, help="number of drops to scaffold (default 4)")
    parser.add_argument("--anchor", help="first drop time, ISO 8601 with offset, e.g. 2026-09-03T23:50:00-04:00")
    parser.add_argument("--interval", type=int, default=150, help="minutes between drops (default 150 = 2.5h)")
    parser.add_argument("--time-zone", default="America/New_York")
    parser.add_argument("--program-id", default="FAM-FOOT-199", help="offer/SKU this campaign sells (required by schema)")
    parser.add_argument("--series-id", default=None, help="shared id for campaigns in the same narrative sequence (omit for a standalone campaign)")
    parser.add_argument("--landing", default="https://famtasticdesigns.com/onboarding?sku=FAM-FOOT-199")
    parser.add_argument("--force", action="store_true", help="overwrite an existing posting-schedule.json")
    parser.add_argument("--validate", metavar="SLUG", help="validate an existing campaign instead of scaffolding")
    args = parser.parse_args()

    if args.validate:
        return validate(args.validate)
    if not args.slug or not args.anchor:
        parser.error("--slug and --anchor are required when scaffolding")
    if not ISO_WITH_OFFSET.match(args.anchor):
        parser.error("--anchor must be ISO 8601 with an explicit offset, e.g. 2026-09-03T23:50:00-04:00")
    return scaffold(args)


if __name__ == "__main__":
    sys.exit(main())
