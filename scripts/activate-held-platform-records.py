#!/usr/bin/env python3
"""Activate one campaign's held TikTok/YouTube platform records (plan T9 replay).

T9 (plans/ugc-character-flood/plan.md) built held-posting-schedule.json files
under marketing/campaigns/<slug>-tiktok/ and marketing/campaigns/<slug>-youtube/
— schema-valid drop records with correct per-platform `settings`, reusing the
already-approved copy and the already-rendered film, but with
approval.publish permanently false and never queued to Postiz. Their
scheduled_time values are copies of the original live-campaign slots and are
KNOWN STALE PLACEHOLDERS — TikTok/YouTube unblock on an unknowable future
date, so nobody can pre-compute the real slot today.

This script performs the one manual judgment call replay actually requires —
picking a real future anchor time — and mechanizes everything else:
  - recomputes every drop's scheduled_time from a fresh --anchor/--interval-minutes
  - flips approval.publish to true (the explicit "this is really going out" signal)
  - writes marketing/campaigns/<held-dir>/posting-schedule.json (leaving
    held-posting-schedule.json untouched as the historical record)

It does NOT contact Postiz and does NOT queue anything. See
marketing/campaigns/_shared/UNBLOCK-REPLAY.md for the full pre-flight and the
exact follow-on commands (hygiene validation, then queue-campaign-drops.py).

Usage:
    python3 scripts/activate-held-platform-records.py --campaign front-desk-tiktok \\
        --anchor 2026-11-03T09:05:00-05:00 --interval-minutes 240
    (preview only; add --confirm to actually write posting-schedule.json)
"""
from __future__ import annotations

import argparse
import json
import pathlib
import re
import sys
from datetime import datetime, timedelta, timezone

REPO_ROOT = pathlib.Path(__file__).resolve().parent.parent
CAMPAIGNS_ROOT = REPO_ROOT / "marketing/campaigns"

sys.path.insert(0, str(REPO_ROOT / "scripts"))
from campaign_schema_validate import validate_manifest  # noqa: E402

ISO_OFFSET = re.compile(r"^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:[+-]\d{2}:\d{2})$")


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--campaign", required=True, help="held directory name, e.g. front-desk-tiktok")
    ap.add_argument("--anchor", required=True,
                    help="ISO 8601 WITH an explicit offset for drop 1, e.g. 2026-11-03T09:05:00-05:00")
    ap.add_argument("--interval-minutes", type=int, required=True,
                    help="spacing in minutes between successive drops (must not collide with any "
                         "other campaign's slots — the hygiene validator checks this after you write)")
    ap.add_argument("--confirm", action="store_true",
                    help="write posting-schedule.json. Without this flag, only previews the retimed drops.")
    args = ap.parse_args()

    held_path = CAMPAIGNS_ROOT / args.campaign / "held-posting-schedule.json"
    live_path = CAMPAIGNS_ROOT / args.campaign / "posting-schedule.json"

    if not held_path.is_file():
        sys.stderr.write(f"FAIL: no held record at {held_path.relative_to(REPO_ROOT)}\n")
        return 66
    if live_path.is_file():
        sys.stderr.write(
            f"REFUSED: {live_path.relative_to(REPO_ROOT)} already exists — this campaign was already "
            "activated. Edit the existing live schedule directly (queue-campaign-drops.py --edit-drop), "
            "do not overwrite an activation that may already be queued.\n"
        )
        return 2

    if not ISO_OFFSET.match(args.anchor):
        sys.stderr.write(
            f"FAIL: --anchor must be ISO 8601 with an explicit UTC offset, "
            f"e.g. 2026-11-03T09:05:00-05:00. Got: {args.anchor!r}\n"
        )
        return 64
    anchor = datetime.fromisoformat(args.anchor)
    if anchor <= datetime.now(timezone.utc):
        sys.stderr.write(f"FAIL: --anchor {args.anchor} is not in the future\n")
        return 64
    if args.interval_minutes < 1:
        sys.stderr.write("FAIL: --interval-minutes must be >= 1\n")
        return 64

    manifest = json.loads(held_path.read_text())
    activated_at = datetime.now(timezone.utc).isoformat()

    manifest["status"] = "ready_for_evaluation"
    manifest["schedule_type"] = "activated_replay"
    manifest.pop("held_summary", None)
    manifest["publish_arming"] = {
        "mode": "single_env_switch",
        "env": "FAMTASTIC_MARKETING_PUBLISH",
        "note": "Activated from a T9 held record. QUEUE (unarmed) is safe and idempotent; --schedule "
                "requires FAMTASTIC_MARKETING_PUBLISH=true, set inline, never exported or committed.",
    }

    for i, drop in enumerate(manifest["drops"]):
        new_time = anchor + timedelta(minutes=args.interval_minutes * i)
        original_placeholder = drop["scheduled_time"]
        drop["scheduled_time"] = new_time.isoformat()
        drop["label"] = (
            f"ACTIVATED {activated_at} from held placeholder {original_placeholder} "
            f"(was: {drop.get('label', '')})"
        )
        drop["state"] = "media_ready"
        drop.setdefault("approval", {})["publish"] = True
        held = drop.setdefault("held", {})
        held["scheduled_time_is_placeholder"] = False
        held["original_placeholder_scheduled_time"] = original_placeholder
        held["activated_at"] = activated_at

    problems = validate_manifest(manifest)
    if problems:
        sys.stderr.write("FAIL: activated manifest does not conform to posting-schedule.schema.json "
                          "— nothing written:\n")
        for p in problems:
            sys.stderr.write(f"  - {p}\n")
        return 65

    print(f"{'WRITING' if args.confirm else 'PREVIEW (pass --confirm to write)'}: "
          f"{len(manifest['drops'])} drops, anchor {args.anchor}, every {args.interval_minutes} min")
    for drop in manifest["drops"]:
        print(f"  {drop['content_id']:28s} {drop['scheduled_time']}  publish={drop['approval']['publish']}")

    if not args.confirm:
        print("\nNo file written. Re-run with --confirm, then:")
        print("  python3 scripts/validate-campaign-schedule-hygiene.py")
        print(f"  FAMTASTIC_MARKETING_PUBLISH=true python3 scripts/queue-campaign-drops.py "
              f"--campaign {args.campaign} --schedule")
        return 0

    live_path.write_text(json.dumps(manifest, indent=2, ensure_ascii=False) + "\n")
    print(f"\nWROTE {live_path.relative_to(REPO_ROOT)}")
    print("REQUIRED NEXT STEP — cross-campaign collision check against the real repo state:")
    print("  python3 scripts/validate-campaign-schedule-hygiene.py")
    print(f"If it FAILS on a duplicate scheduled_time: rm {live_path.relative_to(REPO_ROOT)}, "
          "pick a different --anchor/--interval-minutes, and rerun this script.")
    print("If it PASSES, queue for real per marketing/campaigns/_shared/UNBLOCK-REPLAY.md.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
