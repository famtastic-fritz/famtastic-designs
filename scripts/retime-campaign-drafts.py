#!/usr/bin/env python3
"""Re-date existing Postiz drafts to the times a campaign manifest now declares.

Why this is a separate step: Postiz keeps a post's stored date when a draft is
converted to a schedule. The 17-day campaign's days 1-3 drafts were created on
2026-08-23 and carry that date. Converting them today would not schedule them —
it would publish twelve backdated posts at once, immediately. publish-executor.php
now refuses to convert a draft whose date is in the past; this script is how that
block is cleared, deliberately and visibly.

Reads `scheduled_at_et` (absolute, offset-bearing) from each manifest record and
pushes it to the provider, then verifies by read-back that the stored date
actually moved. A provider that silently ignores the update is reported BLOCKED,
never assumed to have worked.

    python3 scripts/retime-campaign-drafts.py --day 1
    python3 scripts/retime-campaign-drafts.py --day 1 --apply

Dry by default: it prints exactly what would move and stops. --apply mutates.
This changes draft dates only. It never converts a draft to a live schedule, so
it does not need the FAMTASTIC_MARKETING_PUBLISH arming switch.
"""

from __future__ import annotations

import argparse
import json
import os
import pathlib
import re
import subprocess
import sys
from datetime import datetime, timezone

REPO_ROOT = pathlib.Path(__file__).resolve().parent.parent
DEFAULT_MANIFEST = REPO_ROOT / "marketing/campaigns/55-cents-17-day/manifest.json"
PG_CONTAINER = os.environ.get("POSTIZ_PG_CONTAINER", "postiz-postgres")
BASE_URL = os.environ.get(
    "FAMTASTIC_POSTIZ_BASE_URL",
    os.environ.get("POSTIZ_BASE_URL", "http://127.0.0.1:4007/api/public/v1"),
).rstrip("/")


def resolve_api_key() -> str:
    key = os.environ.get("FAMTASTIC_POSTIZ_API_KEY") or os.environ.get("POSTIZ_API_KEY") or ""
    if key:
        return key
    host = re.sub(r"^https?://", "", BASE_URL).split("/")[0].split(":")[0]
    if host not in {"127.0.0.1", "localhost", "::1"}:
        sys.stderr.write(f"FAIL: no API key set and {BASE_URL} is not loopback.\n")
        sys.exit(3)
    out = subprocess.run(
        ["docker", "exec", PG_CONTAINER, "sh", "-c",
         'psql -U "$POSTGRES_USER" -d "${POSTGRES_DB:-postiz-db-local}" -t -A '
         '-c \'SELECT "apiKey" FROM "Organization" WHERE "apiKey" IS NOT NULL LIMIT 1\''],
        capture_output=True, text=True,
    )
    return out.stdout.strip()


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--manifest", default=str(DEFAULT_MANIFEST))
    parser.add_argument("--day", type=int, action="append",
                        help="restrict to these campaign days (repeatable)")
    parser.add_argument("--apply", action="store_true", help="actually push the new dates")
    args = parser.parse_args()

    manifest = json.loads(pathlib.Path(args.manifest).read_text())
    records = [
        r for r in manifest.get("records", [])
        if r.get("scheduled_at_et")
        and r.get("provider_ids", {}).get("postiz_draft_id")
        and (args.day is None or r.get("day") in args.day)
    ]
    if not records:
        print("nothing to re-date: no records carry both scheduled_at_et and a postiz_draft_id")
        print("(set an absolute scheduled_at_et on the records you want moved)")
        return 0

    key = resolve_api_key()
    if not key:
        sys.stderr.write(f"FAIL: no Postiz org API key resolvable for {BASE_URL}\n")
        return 3

    def api(path: str, method: str = "GET", data: dict | None = None):
        cmd = ["curl", "-sS", "--max-time", "60", "-H", f"Authorization: {key}"]
        if data is not None:
            cmd += ["-H", "Content-Type: application/json", "-d", json.dumps(data)]
        if method != "GET":
            cmd += ["-X", method]
        cmd += [f"{BASE_URL}{path}"]
        out = subprocess.run(cmd, capture_output=True, text=True)
        if not out.stdout:
            return {}
        try:
            return json.loads(out.stdout)
        except json.JSONDecodeError:
            return {"_raw": out.stdout[:300]}

    connected = api("/is-connected")
    if not (isinstance(connected, dict) and connected.get("connected")):
        sys.stderr.write(f"FAIL: Postiz not reachable at {BASE_URL}: {connected}\n")
        return 4

    moved, blocked, unchanged = 0, 0, 0
    for record in records:
        cid = record["content_id"]
        pid = record["provider_ids"]["postiz_draft_id"]
        target = datetime.fromisoformat(record["scheduled_at_et"]).astimezone(timezone.utc)
        target_iso = target.strftime("%Y-%m-%dT%H:%M:%S.000Z")

        current = api(f"/posts/{pid}")
        if not isinstance(current, dict) or not current.get("id"):
            print(f"BLOCKED {cid}: draft {pid} not found in Postiz")
            blocked += 1
            continue
        stored = str(current.get("publishDate") or current.get("date") or "")
        state = current.get("state", "?")

        if target <= datetime.now(timezone.utc):
            print(f"BLOCKED {cid}: target {record['scheduled_at_et']} is in the past; refusing")
            blocked += 1
            continue
        if state not in {"DRAFT", "QUEUE"}:
            print(f"BLOCKED {cid}: provider state {state} is not DRAFT/QUEUE; left untouched")
            blocked += 1
            continue

        print(f"{cid}: {stored or '(no date)'}  ->  {target_iso}   [{state}]")
        if not args.apply:
            continue

        # Postiz upserts on POST /posts when an id is supplied. Verify by
        # read-back rather than trusting the response, because a provider that
        # accepts and ignores the field would otherwise look like success.
        api("/posts", method="POST", data={"id": pid, "type": "draft",
                                           "shortLink": False, "date": target_iso})
        after = api(f"/posts/{pid}")
        now_stored = str(after.get("publishDate") or after.get("date") or "") if isinstance(after, dict) else ""
        if now_stored[:16] == target_iso[:16]:
            record["provider_ids"]["postiz_retimed_at"] = target_iso
            record.setdefault("evidence", []).append({
                "kind": "postiz_draft_retimed",
                "at": datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ"),
                "postiz_post_id": pid,
                "from": stored, "to": target_iso,
            })
            moved += 1
            print(f"  OK read-back confirms {now_stored}")
        else:
            unchanged += 1
            print(f"  BLOCKED read-back still shows {now_stored or '(unknown)'}; date did NOT move.")
            print(f"          This Postiz build may not support date update via the public API.")
            print(f"          Move it by hand in the Postiz UI, or delete and re-queue the draft.")

    if args.apply:
        pathlib.Path(args.manifest).write_text(json.dumps(manifest, indent=1) + "\n")

    print(f"\n{'APPLIED' if args.apply else 'DRY RUN'} — "
          f"retimed={moved} blocked={blocked} unchanged={unchanged} candidates={len(records)}")
    if not args.apply:
        print("re-run with --apply to push these dates")
    return 0 if (blocked == 0 and unchanged == 0) else 1


if __name__ == "__main__":
    sys.exit(main())
