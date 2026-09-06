#!/usr/bin/env python3
"""Validate cross-campaign schedule ownership without touching providers.

This validator treats posting-schedule.json as a source plan, not proof that
Postiz currently agrees with it. It has no network, credential, scheduler, or
write path. Pending source/provider reconciliations are reported as operator
work, while duplicate provider IDs, duplicate planned timestamps, and open
publish gates are failures.
"""

from __future__ import annotations

import json
import pathlib
import sys
from collections import defaultdict


REPO_ROOT = pathlib.Path(__file__).resolve().parent.parent
CAMPAIGNS_ROOT = REPO_ROOT / "marketing" / "campaigns"
REPAIR_CAMPAIGNS = {
    "already-know-the-game",
    "booked-and-losing",
    "see-it-first",
    "ive-managed-fine",
}


def schedules() -> list[tuple[str, pathlib.Path, dict]]:
    found: list[tuple[str, pathlib.Path, dict]] = []
    for path in sorted(CAMPAIGNS_ROOT.glob("*/posting-schedule.json")):
        try:
            payload = json.loads(path.read_text())
        except json.JSONDecodeError as exc:
            raise ValueError(f"{path.relative_to(REPO_ROOT)} is not valid JSON: {exc}") from exc
        found.append((path.parent.name, path, payload))
    return found


def main() -> int:
    provider_owners: dict[str, list[str]] = defaultdict(list)
    timestamp_owners: dict[str, list[str]] = defaultdict(list)
    errors: list[str] = []
    pending: list[str] = []
    checked = 0
    target_drop_count = 0

    for slug, path, schedule in schedules():
        drops = schedule.get("drops", [])
        if not isinstance(drops, list):
            errors.append(f"{path.relative_to(REPO_ROOT)}: drops must be an array")
            continue
        for drop in drops:
            if not isinstance(drop, dict):
                errors.append(f"{path.relative_to(REPO_ROOT)}: drop is not an object")
                continue
            checked += 1
            content_id = str(drop.get("content_id", "<missing-content-id>"))
            owner = f"{slug}/{content_id}"
            scheduled_time = str(drop.get("scheduled_time", ""))
            if scheduled_time:
                timestamp_owners[scheduled_time].append(owner)
            provider_ids = drop.get("provider_ids", {})
            if isinstance(provider_ids, dict):
                known = provider_ids.get("postiz_scheduled_group", [])
                if not isinstance(known, list):
                    errors.append(f"{owner}: provider_ids.postiz_scheduled_group must be an array")
                    known = []
                for key in ("postiz_draft_id", "postiz_scheduled_id"):
                    if provider_ids.get(key):
                        known.append(provider_ids[key])
                for provider_id in set(str(item) for item in known if item):
                    provider_owners[provider_id].append(owner)

            if slug not in REPAIR_CAMPAIGNS:
                continue
            target_drop_count += 1
            approval = drop.get("approval", {})
            if not isinstance(approval, dict) or approval.get("publish") is not False:
                errors.append(f"{owner}: approval.publish must remain false")
            reconciliation = drop.get("provider_reconciliation")
            if reconciliation and reconciliation.get("status") != "reconciled":
                pending.append(f"{owner}: {reconciliation.get('status', 'unresolved')}")

    for timestamp, owners in sorted(timestamp_owners.items()):
        if len(owners) > 1:
            errors.append(f"duplicate scheduled_time {timestamp}: {', '.join(sorted(owners))}")
    for provider_id, owners in sorted(provider_owners.items()):
        if len(owners) > 1:
            errors.append(f"duplicate recorded provider ID {provider_id}: {', '.join(sorted(owners))}")

    if errors:
        print("FAIL: campaign schedule hygiene")
        for error in errors:
            print(f"  - {error}")
        return 1

    print(
        "PASS: campaign schedule hygiene — "
        f"{checked} drops checked; {target_drop_count} target drops have approval.publish=false; "
        "planned timestamps and recorded provider IDs are globally unique."
    )
    if pending:
        print("PENDING OPERATOR RECONCILIATION (no provider action was taken):")
        for item in pending:
            print(f"  - {item}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
