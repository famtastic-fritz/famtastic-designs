#!/usr/bin/env python3
"""Queue ANY campaign's drop schedule into Postiz. Campaign-agnostic by design.

Why this exists: every campaign so far got its own bespoke queue script
(queue-55-cent-days-1-3-drafts.sh, queue-days-4-17.py, ...), so a campaign
without a hand-written script had no execution path at all. That is exactly
what happened to Cost Is Not The Reason: it shipped 2026-09-02 with creative,
copy, and a drop schedule that was referenced by zero code, so nothing was ever
queued and nothing ever went out.

This script replaces that pattern. A campaign becomes postable by writing one
`posting-schedule.json` conforming to
marketing/engine/schemas/posting-schedule.schema.json — no new code. Scaffold a
new one with scripts/new-campaign.py.

    python3 scripts/queue-campaign-drops.py --campaign <slug>

Two stages, matching the 17-day campaign's architecture:

  1. QUEUE (default) — create one Postiz DRAFT per drop, dated from the
     schedule's absolute `scheduled_time`. Drafts never publish on their own,
     so this stage is safe to run unarmed and is idempotent: a drop that
     already carries a `postiz_draft_id` (or that matches an existing draft by
     its utm_content marker) is adopted, not duplicated.

  2. SCHEDULE (--schedule) — convert those drafts to a live schedule in place
     via PUT /posts/{id}/status, then verify QUEUE state by read-back. This
     stage sends for real and therefore requires FAMTASTIC_MARKETING_PUBLISH=true,
     the single arming switch shared with backend/scripts/publish-executor.php.

Media policy — resolve at runtime, fail loud. Video assets are gitignored
(.gitignore: *.mp4) and exist only on the operator workstation. A drop whose
media is missing is reported BLOCKED with the exact path it looked for. It is
never posted image-only by silent fallback and never skipped, because a drop
that vanishes quietly is the failure mode this whole campaign already hit once.

Run wherever the configured Postiz instance is reachable. Today that is the
operator workstation (Postiz runs at 127.0.0.1:4007 in colima/Docker), which is
a known architectural limitation: a drop cannot fire while that machine is
asleep. Point FAMTASTIC_POSTIZ_BASE_URL at a server-hosted Postiz and this same
script runs unattended from cron. See docs/marketing/CAMPAIGN_POSTING_ARCHITECTURE.md.

    python3 scripts/queue-campaign-drops.py --campaign cost-is-not-the-reason
    FAMTASTIC_MARKETING_PUBLISH=true \
      python3 scripts/queue-campaign-drops.py --campaign cost-is-not-the-reason --schedule
"""

from __future__ import annotations

import json
import os
import pathlib
import re
import subprocess
import sys
import time
from datetime import datetime, timezone

REPO_ROOT = pathlib.Path(__file__).resolve().parent.parent
CAMPAIGNS_ROOT = REPO_ROOT / "marketing/campaigns"

PG_CONTAINER = os.environ.get("POSTIZ_PG_CONTAINER", "postiz-postgres")
BASE_URL = os.environ.get(
    "FAMTASTIC_POSTIZ_BASE_URL",
    os.environ.get("POSTIZ_BASE_URL", "http://127.0.0.1:4007/api/public/v1"),
).rstrip("/")

DEFAULT_LANDING = "https://famtasticdesigns.com/onboarding?sku=FAM-FOOT-199"

# Schedule channel labels -> Postiz integration identifiers. Several schedule
# labels describe a surface of the same account (instagram_reels and
# instagram_carousel are both the Instagram integration), so this collapses.
CHANNEL_TO_INTEGRATION = {
    "facebook": "facebook",
    "facebook_video": "facebook",
    "instagram": "instagram-standalone",
    "instagram_reels": "instagram-standalone",
    "instagram_carousel": "instagram-standalone",
    "x": "x",
    "x_thread": "x",
    "tiktok": "tiktok",
    "youtube": "youtube",
    "youtube_shorts": "youtube",
    "linkedin": "linkedin",
}

# Postiz integration identifier -> preferred copy key, most specific first.
COPY_PREFERENCE = {
    "facebook": ["facebook_instagram", "linkedin_facebook", "all_channels"],
    "instagram-standalone": ["facebook_instagram", "tiktok_reels_shorts", "all_channels"],
    "x": ["x_post", "x_thread_opener", "all_channels"],
    "tiktok": ["tiktok_reels_shorts", "all_channels"],
    "youtube": ["tiktok_reels_shorts", "all_channels"],
    "linkedin": ["linkedin_facebook", "facebook_instagram", "all_channels"],
}

# Postiz validates a per-platform `settings` object on EVERY entry in `posts`,
# and a single missing required field rejects the WHOLE request — not just the
# offending channel. Facebook requires none, which is why the only script that
# ever created drafts here (facebook-only) worked while every multi-channel
# attempt failed with "draft creation returned no post id".
#
# Values below are the defaults Postiz's own validators accept; the enums come
# verbatim from its rejection messages. Override per campaign with a
# `platform_settings` block in posting-schedule.json.
PLATFORM_SETTINGS = {
    "tiktok": {
        # PUBLIC_TO_EVERYONE | MUTUAL_FOLLOW_FRIENDS | FOLLOWER_OF_CREATOR | SELF_ONLY
        "privacy_level": "PUBLIC_TO_EVERYONE",
        "duet": False,
        "stitch": False,
        "comment": False,
        "brand_content_toggle": False,
        "brand_organic_toggle": False,
        # DIRECT_POST publishes to the feed; UPLOAD only drops it in the
        # account's TikTok inbox for someone to finish by hand, which would
        # quietly turn a scheduled drop into a manual chore.
        "content_posting_method": "DIRECT_POST",
        # A string enum, not a boolean. "no" because these videos carry their
        # own voice track — auto-added music would layer over it.
        "autoAddMusic": "no",
    },
    # post | story
    "instagram-standalone": {"post_type": "post"},
    "instagram": {"post_type": "post"},
    # everyone | following | mentionedUsers | subscribers | verified
    "x": {"who_can_reply_post": "everyone"},
    # `title` is filled per drop from its headline.
    "youtube": {"type": "public"},
    "facebook": {},
    "linkedin": {},
}

# Hard per-platform content limits. Exceeding one fails at publish time rather
# than draft time, so it is warned about loudly here instead of silently cut.
CONTENT_LIMITS = {"x": 280, "youtube": 5000, "tiktok": 2200, "instagram-standalone": 2200}

ARGS = sys.argv[1:]
DO_SCHEDULE = "--schedule" in ARGS
DRY_RUN = "--dry-run" in ARGS
ARMED = os.environ.get("FAMTASTIC_MARKETING_PUBLISH") == "true"

# --requeue <drop_id> --at <ISO8601 with offset>
# Moves a drop to a new time. A stored post keeps its date through every status
# change, so re-pointing the schedule file alone would leave the provider still
# firing at the old moment. This deletes the drop's existing records and clears
# its ids so the normal queue path recreates them at the new time. It refuses
# to touch anything already PUBLISHED — a sent post cannot be unsent.
REQUEUE = ""
REQUEUE_AT = ""
for i, arg in enumerate(ARGS):
    if arg == "--requeue" and i + 1 < len(ARGS):
        REQUEUE = ARGS[i + 1]
    elif arg == "--at" and i + 1 < len(ARGS):
        REQUEUE_AT = ARGS[i + 1]

CAMPAIGN = ""
for i, arg in enumerate(ARGS):
    if arg == "--campaign" and i + 1 < len(ARGS):
        CAMPAIGN = ARGS[i + 1]
    elif arg.startswith("--campaign="):
        CAMPAIGN = arg.split("=", 1)[1]
if not CAMPAIGN:
    available = sorted(
        d.name for d in CAMPAIGNS_ROOT.iterdir()
        if d.is_dir() and (d / "posting-schedule.json").is_file()
    )
    sys.stderr.write(
        "usage: queue-campaign-drops.py --campaign <slug> [--schedule] [--dry-run]\n"
        f"campaigns with a posting-schedule.json: {', '.join(available) or '(none)'}\n"
        "scaffold a new one: python3 scripts/new-campaign.py --slug <slug>\n"
    )
    sys.exit(64)

CAMPAIGN_DIR = CAMPAIGNS_ROOT / CAMPAIGN
SCHEDULE_PATH = CAMPAIGN_DIR / "posting-schedule.json"
if not SCHEDULE_PATH.is_file():
    sys.stderr.write(
        f"FAIL: no posting schedule at {SCHEDULE_PATH.relative_to(REPO_ROOT)}\n"
        "A campaign becomes postable by adding that file — see\n"
        "marketing/engine/schemas/posting-schedule.schema.json, or scaffold one with\n"
        f"  python3 scripts/new-campaign.py --slug {CAMPAIGN}\n"
    )
    sys.exit(66)

if DO_SCHEDULE and not ARMED:
    sys.stderr.write(
        "REFUSED: --schedule converts drafts into real, live-firing posts.\n"
        "Missing: env FAMTASTIC_MARKETING_PUBLISH=true (the single arming switch).\n"
        "Nothing was read, sent, or changed.\n"
    )
    sys.exit(2)

ART = REPO_ROOT / f".artifacts/postiz-queue/{CAMPAIGN}/{int(time.time())}"
ART.mkdir(parents=True, exist_ok=True)


def resolve_api_key() -> str:
    key = os.environ.get("FAMTASTIC_POSTIZ_API_KEY") or os.environ.get("POSTIZ_API_KEY") or ""
    if key:
        return key
    host = re.sub(r"^https?://", "", BASE_URL).split("/")[0].split(":")[0]
    if host not in {"127.0.0.1", "localhost", "::1"}:
        sys.stderr.write(
            f"FAIL: no API key set and {BASE_URL} is not loopback.\n"
            "Set FAMTASTIC_POSTIZ_API_KEY on this host; never commit it.\n"
        )
        sys.exit(3)
    # Loopback only: read the org key straight from the local Postiz postgres,
    # exactly as scripts/queue-days-4-17.py does. Never printed or logged.
    out = subprocess.run(
        [
            "docker", "exec", PG_CONTAINER, "sh", "-c",
            'psql -U "$POSTGRES_USER" -d "${POSTGRES_DB:-postiz-db-local}" -t -A '
            '-c \'SELECT "apiKey" FROM "Organization" WHERE "apiKey" IS NOT NULL LIMIT 1\'',
        ],
        capture_output=True, text=True,
    )
    return out.stdout.strip()


KEY = "" if DRY_RUN else resolve_api_key()
if not KEY and not DRY_RUN:
    sys.stderr.write(f"FAIL: no Postiz org API key resolvable for {BASE_URL}\n")
    sys.exit(3)


def api(path: str, method: str = "GET", data: dict | None = None) -> dict | list:
    cmd = ["curl", "-sS", "--max-time", "120", "-H", f"Authorization: {KEY}"]
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


def upload(path: pathlib.Path) -> dict:
    out = subprocess.run(
        ["curl", "-sS", "--max-time", "300", "-H", f"Authorization: {KEY}",
         "-F", f"file=@{path}", f"{BASE_URL}/upload"],
        capture_output=True, text=True,
    )
    try:
        return json.loads(out.stdout)
    except json.JSONDecodeError:
        return {}


schedule = json.loads(SCHEDULE_PATH.read_text())
drops = schedule["drops"]
LANDING = schedule.get("landing_url", DEFAULT_LANDING)
# A campaign may extend or override the shared maps without touching this code.
CHANNEL_TO_INTEGRATION.update(schedule.get("channel_map", {}))
for ident, keys in schedule.get("copy_preference", {}).items():
    COPY_PREFERENCE[ident] = keys

if DRY_RUN:
    # Validate the schedule against the local filesystem and the shared channel
    # map without contacting Postiz. This is how a new campaign gets checked
    # before anyone arms anything.
    ENABLED = {ident: f"dry-run-{ident}" for ident in set(CHANNEL_TO_INTEGRATION.values())}
    print(f"DRY RUN: no Postiz contact; assuming all mapped integrations are connected")
else:
    connected = api("/is-connected")
    if not (isinstance(connected, dict) and connected.get("connected")):
        sys.stderr.write(f"FAIL: Postiz not reachable at {BASE_URL}: {connected}\n")
        sys.exit(4)
    print(f"PASS: Postiz reachable at {BASE_URL}, org key valid")

    integrations = api("/integrations")
    ENABLED = {}
    for item in integrations if isinstance(integrations, list) else []:
        ident = item.get("identifier", "")
        if ident and not item.get("disabled", False):
            ENABLED.setdefault(ident, item["id"])
    if not ENABLED:
        sys.stderr.write("FAIL: no enabled Postiz integrations\n")
        sys.exit(4)
    print(f"  enabled integrations: {', '.join(sorted(ENABLED))}")

results: list[dict] = []
failures: list[dict] = []
blocked: list[dict] = []


def media_for(drop: dict) -> tuple[list[pathlib.Path], list[str]]:
    """Return (resolved paths, missing relative paths) for one drop, in post order.

    Primary media leads, then any carousel slides, then supporting stills. A
    declared backup_media substitutes for a missing primary and only for that —
    it never papers over a missing slide, and a drop is never quietly emptied.
    """
    wanted: list[str] = []
    if drop.get("primary_media"):
        wanted.append(drop["primary_media"])
    wanted.extend(drop.get("carousel_slides", []))
    if drop.get("supporting_media"):
        wanted.append(drop["supporting_media"])

    resolved: list[pathlib.Path] = []
    missing: list[str] = []
    for rel in wanted:
        path = REPO_ROOT / rel
        if path.is_file():
            resolved.append(path)
        elif rel == drop.get("primary_media") and drop.get("backup_media"):
            backup = REPO_ROOT / drop["backup_media"]
            if backup.is_file():
                resolved.append(backup)
                print(f"  NOTE {drop['drop_id']}: primary media absent, using declared backup_media")
            else:
                missing.extend([rel, drop["backup_media"]])
        else:
            missing.append(rel)
    return resolved, missing


def tracked_link(drop: dict, compact: bool = False) -> str:
    """Full UTM link, or a compact one for character-limited platforms.

    The compact form keeps utm_campaign and utm_content — utm_content is the
    idempotency marker reruns adopt drafts by, so it is never dropped — while
    shedding the parameters that can be inferred, saving ~70 characters.
    """
    utm = drop["utm"]
    if compact:
        base = schedule.get("short_landing_url", LANDING).split("?")[0]
        sep = "&" if "?" in base else "?"
        return f"{base}{sep}utm_campaign={utm['campaign']}&utm_content={utm['content']}"
    return (
        f"{LANDING}&utm_source={utm['source']}&utm_medium={utm['medium']}"
        f"&utm_campaign={utm['campaign']}&utm_content={utm['content']}"
    )


def settings_for(integration_ident: str, drop: dict) -> dict:
    """Per-platform settings Postiz requires on this post entry.

    Campaign-level `platform_settings` in posting-schedule.json overrides the
    shared defaults; a drop may override again via its own `platform_settings`.
    """
    settings = dict(PLATFORM_SETTINGS.get(integration_ident, {}))
    settings.update(schedule.get("platform_settings", {}).get(integration_ident, {}))
    settings.update(drop.get("platform_settings", {}).get(integration_ident, {}))
    if integration_ident == "youtube" and not settings.get("title"):
        # YouTube requires a title; its cap is 100 characters.
        settings["title"] = (drop.get("headline") or drop.get("label", "FAMtastic"))[:100]
    return settings


def copy_for(integration_ident: str, drop: dict) -> str:
    copy = drop.get("copy", {})
    for key in COPY_PREFERENCE.get(integration_ident, ["all_channels"]):
        if copy.get(key):
            body = copy[key]
            break
    else:
        body = next(iter(copy.values()), drop.get("headline", ""))
    # The tracked link carries utm_content, which is also the idempotency
    # marker this script and publish-executor.php adopt drafts by.
    tags = " ".join(drop.get("tags", []))
    limit = CONTENT_LIMITS.get(integration_ident)
    if limit and limit <= 300:
        # Tight-limit platforms (X): a second URL and a hashtag block are the
        # difference between fitting and being rejected. Use the compact link,
        # and only if the approved copy does not already carry one.
        parts = [body]
        if "http" not in body:
            parts.append(tracked_link(drop, compact=True))
        return "\n\n".join(parts).strip()
    return f"{body}\n\n{tracked_link(drop)}\n\n{tags}".strip()


# Provider listing window, derived from the schedule itself so it works for any
# campaign and any date range, with slack on both ends for adoption.
_times = [datetime.fromisoformat(d["scheduled_time"]).astimezone(timezone.utc) for d in drops]
WINDOW = (
    f"?startDate={min(_times).strftime('%Y-%m-%dT00:00:00.000Z')}"
    f"&endDate={max(_times).strftime('%Y-%m-%dT23:59:59.000Z')}"
)

# ---------------------------------------------------------------------------
# Optional: move one drop to a new time by discarding its provider records.
# ---------------------------------------------------------------------------
if REQUEUE:
    target = next((d for d in drops if d["drop_id"] == REQUEUE), None)
    if target is None:
        sys.stderr.write(f"FAIL: no drop '{REQUEUE}' in {CAMPAIGN}\n")
        sys.exit(66)
    if REQUEUE_AT:
        if not re.match(r"^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:[+-]\d{2}:\d{2}|Z)$", REQUEUE_AT):
            sys.stderr.write("FAIL: --at needs ISO 8601 with an offset, e.g. 2026-09-03T09:00:00-04:00\n")
            sys.exit(64)
        if datetime.fromisoformat(REQUEUE_AT) <= datetime.now(timezone.utc):
            sys.stderr.write(f"FAIL: --at {REQUEUE_AT} is in the past\n")
            sys.exit(64)

    old_stamp = datetime.fromisoformat(target["scheduled_time"]).astimezone(
        timezone.utc).strftime("%Y-%m-%dT%H:%M:%S")
    current = api(f"/posts{WINDOW}") if not DRY_RUN else {}
    doomed = []
    for post in (current.get("posts", []) if isinstance(current, dict) else []):
        stamp = str(post.get("publishDate") or post.get("date") or "")[:19]
        if stamp == old_stamp:
            doomed.append((str(post.get("id")), post.get("state")))

    live = [(i, s) for i, s in doomed if s not in {"DRAFT", "QUEUE"}]
    if live:
        sys.stderr.write(
            f"REFUSED: {REQUEUE} has record(s) already past draft state: "
            + ", ".join(f"{i[:12]}={s}" for i, s in live)
            + "\nA post that has been sent cannot be unsent. Handle it on the platform.\n")
        sys.exit(3)

    for pid, state in doomed:
        if state == "QUEUE":
            # Back to DRAFT first: that terminates the publishing workflow, so
            # the record cannot fire in the window between here and deletion.
            api(f"/posts/{pid}/status", method="PUT", data={"status": "draft"})
        api(f"/posts/{pid}", method="DELETE")
        print(f"  removed {pid[:12]} ({state})")

    target["provider_ids"] = {}
    if REQUEUE_AT:
        target["scheduled_time"] = REQUEUE_AT
        when = datetime.fromisoformat(REQUEUE_AT)
        target["label"] = re.sub(r"^Drop (\d+) — [^:]+:", rf"Drop \1 — {when:%-I:%M %p} ET:",
                                 target.get("label", ""))
    SCHEDULE_PATH.write_text(json.dumps(schedule, indent=2) + "\n")
    print(f"REQUEUED: {REQUEUE} cleared ({len(doomed)} record(s)) "
          f"-> {target['scheduled_time']}; it will be recreated below\n")
    # Recompute the listing window so the new time is inside it.
    _times = [datetime.fromisoformat(d["scheduled_time"]).astimezone(timezone.utc) for d in drops]
    WINDOW = (f"?startDate={min(_times).strftime('%Y-%m-%dT00:00:00.000Z')}"
              f"&endDate={max(_times).strftime('%Y-%m-%dT23:59:59.000Z')}")

# Adopt anything a previous partial run already created, so reruns never
# duplicate a live post.
existing_by_utm: dict[str, dict] = {}
if not DRY_RUN:
    window = api(f"/posts{WINDOW}")
    for post in (window.get("posts", []) if isinstance(window, dict) else []):
        match = re.search(r"utm_content=([a-zA-Z0-9_-]+)", str(post.get("content", "")))
        if match and post.get("state") in {"DRAFT", "QUEUE"}:
            existing_by_utm.setdefault(match.group(1), post)

for drop in drops:
    cid = drop["content_id"]
    entry = {"content_id": cid, "scheduled_time": drop["scheduled_time"]}

    idents: list[str] = []
    unconnected: list[str] = []
    for channel in drop.get("channels", []):
        ident = CHANNEL_TO_INTEGRATION.get(channel)
        if ident is None:
            unconnected.append(f"{channel} (no mapping)")
        elif ident not in ENABLED:
            unconnected.append(f"{channel} -> {ident} (not connected)")
        elif ident not in idents:
            idents.append(ident)
    entry["channels_posted"] = idents
    entry["channels_unavailable"] = unconnected
    if unconnected:
        print(f"  NOTE {cid}: skipping unavailable channels: {', '.join(unconnected)}")
    if not idents:
        entry["action"] = "blocked_no_channel"
        blocked.append({"content_id": cid, "reason": "no requested channel is connected",
                        "requested": drop.get("channels", [])})
        results.append(entry)
        print(f"BLOCKED: {cid} — none of its channels are connected in Postiz")
        continue

    prior = drop.get("provider_ids", {}).get("postiz_draft_id") or (
        existing_by_utm.get(cid, {}).get("id")
    )
    if prior:
        drop.setdefault("provider_ids", {})["postiz_draft_id"] = prior
        entry["action"] = "adopted"
        entry["postiz_draft_id"] = prior
        results.append(entry)
        print(f"ADOPTED: {cid} — draft {prior} already exists; not duplicated")
        continue

    resolved, missing = media_for(drop)
    if missing:
        entry["action"] = "blocked_missing_media"
        entry["missing_media"] = [str(m) for m in missing]
        blocked.append({"content_id": cid, "reason": "media not found on this host",
                        "missing": [str(m) for m in missing]})
        results.append(entry)
        print(f"BLOCKED: {cid} — media not found on this host:")
        for m in missing:
            print(f"           {m}")
        continue

    iso_utc = (
        datetime.fromisoformat(drop["scheduled_time"])
        .astimezone(timezone.utc)
        .strftime("%Y-%m-%dT%H:%M:%S.000Z")
    )

    if DRY_RUN:
        entry["action"] = "dry_run_ok"
        entry["date_utc"] = iso_utc
        entry["media_resolved"] = [str(p.relative_to(REPO_ROOT)) for p in resolved]
        results.append(entry)
        print(f"OK (dry run): {cid} @ {iso_utc} on {', '.join(idents)} "
              f"with {len(resolved)} asset(s)")
        continue

    uploaded = []
    for path in resolved:
        got = upload(path)
        if "id" not in got or "path" not in got:
            failures.append({"content_id": cid, "stage": "upload",
                             "file": str(path), "raw": str(got)[:300]})
            break
        uploaded.append({"id": got["id"], "path": got["path"]})
        time.sleep(1)
    if len(uploaded) != len(resolved):
        entry["action"] = "upload_failed"
        results.append(entry)
        print(f"FAIL: {cid} — media upload failed")
        continue

    posts_array = []
    posted_idents = []
    for ident in idents:
        body = copy_for(ident, drop)
        limit = CONTENT_LIMITS.get(ident)
        if limit and len(body) > limit:
            # Over-limit copy is accepted at draft time and then fails at
            # PUBLISH time, so the post silently never appears. Exclude the
            # channel loudly instead — the drop still ships to the others,
            # exactly as an unconnected channel is handled. Approved copy is
            # never silently truncated; shorten it in the schedule and re-queue.
            entry.setdefault("channels_over_limit", []).append(
                {"channel": ident, "chars": len(body), "limit": limit})
            print(f"  NOTE {cid}: excluding {ident} — copy is {len(body)} chars "
                  f"vs its {limit} limit; it would fail at publish, not now")
            continue
        posted_idents.append(ident)
        posts_array.append({
            "integration": {"id": ENABLED[ident]},
            "value": [{"content": body, "image": uploaded}],
            "settings": settings_for(ident, drop),
        })

    if not posts_array:
        entry["action"] = "blocked_all_channels_over_limit"
        blocked.append({"content_id": cid, "reason": "every channel's copy exceeds its limit",
                        "detail": entry.get("channels_over_limit")})
        results.append(entry)
        print(f"BLOCKED: {cid} — no channel's copy fits its platform limit")
        continue
    idents = posted_idents
    entry["channels_posted"] = idents

    created = api("/posts", method="POST", data={
        "type": "draft", "shortLink": False, "date": iso_utc, "tags": [],
        "posts": posts_array,
    })
    time.sleep(2)

    pid = None
    if isinstance(created, list) and created:
        pid = created[0].get("postId") or created[0].get("id")
    elif isinstance(created, dict):
        pid = created.get("postId") or created.get("id")
        if not pid and isinstance(created.get("posts"), list) and created["posts"]:
            pid = created["posts"][0].get("postId") or created["posts"][0].get("id")
    if not pid:
        # Keep the provider's validation messages whole. Truncating these once
        # cost a diagnosis cycle: Postiz names the exact field and the exact
        # allowed values, and that text is the fix.
        detail = created.get("message") if isinstance(created, dict) else None
        failures.append({
            "content_id": cid, "stage": "create-no-id",
            "channels": idents,
            "provider_message": detail,
            "raw": str(created)[:2000],
        })
        entry["action"] = "create_failed"
        results.append(entry)
        print(f"FAIL: {cid} — draft creation returned no post id")
        for line in (detail if isinstance(detail, list) else [detail] if detail else []):
            print(f"         {line}")
        continue

    drop.setdefault("provider_ids", {})["postiz_draft_id"] = pid
    drop.setdefault("evidence", []).append({
        "kind": "postiz_draft_queued",
        "at": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()),
        "postiz_post_id": pid, "date_utc": iso_utc,
        "integrations": idents,
    })
    entry["action"] = "queued"
    entry["postiz_draft_id"] = pid
    entry["date_utc"] = iso_utc
    results.append(entry)
    print(f"QUEUED: {cid} -> draft {pid} @ {iso_utc} on {', '.join(idents)}")

# ---------------------------------------------------------------------------
# Stage 2: convert drafts to a live schedule (armed runs only).
# ---------------------------------------------------------------------------
if DO_SCHEDULE:
    now = datetime.now(timezone.utc)

    # Postiz creates ONE post record PER INTEGRATION and returns them as a
    # group; POST /posts hands back only the first id. Converting just that id
    # schedules a single channel and silently leaves the siblings as DRAFT, so
    # a five-channel drop would publish to one. Siblings share the drop's exact
    # publishDate, which is unique per drop, so re-list and group by it.
    siblings: dict[str, list[str]] = {}
    listing = api(f"/posts{WINDOW}")
    for post in (listing.get("posts", []) if isinstance(listing, dict) else []):
        stamp = str(post.get("publishDate") or post.get("date") or "")
        if stamp and post.get("state") in {"DRAFT", "QUEUE"}:
            siblings.setdefault(stamp[:19], []).append(str(post.get("id")))

    for entry in results:
        pid = entry.get("postiz_draft_id")
        if not pid:
            continue
        when = datetime.fromisoformat(entry["scheduled_time"]).astimezone(timezone.utc)
        if when <= now:
            # Postiz keeps the stored date across the status change, so a
            # backdated draft fires the instant it is scheduled.
            entry["schedule_action"] = "blocked_stale_date"
            blocked.append({"content_id": entry["content_id"], "reason": "scheduled_time is in the past",
                            "scheduled_time": entry["scheduled_time"]})
            print(f"BLOCKED: {entry['content_id']} — {entry['scheduled_time']} is in the past; would fire immediately")
            continue
        group = siblings.get(when.strftime("%Y-%m-%dT%H:%M:%S"), [])
        ids = [pid] + [i for i in group if i != pid]
        for one in ids:
            api(f"/posts/{one}/status", method="PUT", data={"status": "schedule"})
        entry["schedule_action"] = "converted"
        entry["scheduled_ids"] = ids
        drop = next(d for d in drops if d["content_id"] == entry["content_id"])
        drop["provider_ids"]["postiz_scheduled_id"] = pid
        drop["provider_ids"]["postiz_scheduled_group"] = ids
        print(f"SCHEDULED: {entry['content_id']} -> {len(ids)} post record(s) "
              f"({', '.join(i[:12] for i in ids)})")

    # Read-back verification: assert QUEUE for everything we converted.
    time.sleep(3)
    verify = api(f"/posts{WINDOW}")
    states = {
        str(p.get("id")): p.get("state")
        for p in (verify.get("posts", []) if isinstance(verify, dict) else [])
    }
    for entry in results:
        if entry.get("schedule_action") != "converted":
            continue
        # Verify EVERY post record in the group. Checking only the id returned
        # by POST /posts once reported 4/4 verified while most channels were
        # still DRAFT and would never have fired.
        seen = {i: states.get(i, "NOT_FOUND") for i in entry.get("scheduled_ids", [])}
        entry["read_back_states"] = seen
        bad = {i: s for i, s in seen.items() if s != "QUEUE"}
        entry["verified"] = bool(seen) and not bad
        if entry["verified"]:
            print(f"VERIFIED: {entry['content_id']} — {len(seen)}/{len(seen)} records in QUEUE")
        else:
            failures.append({"content_id": entry["content_id"], "stage": "read-back",
                             "not_queued": bad, "all_states": seen})
            print(f"FAIL: {entry['content_id']} — {len(bad)}/{len(seen)} record(s) not QUEUE: "
                  f"{', '.join(f'{i[:12]}={s}' for i, s in bad.items())}")

if not DRY_RUN:
    SCHEDULE_PATH.write_text(json.dumps(schedule, indent=2) + "\n")

queued = sum(1 for r in results if r.get("action") == "queued")
adopted = sum(1 for r in results if r.get("action") == "adopted")
verified = sum(1 for r in results if r.get("verified"))
status = not failures and not blocked

evidence = {
    "schema": "famtastic.postiz-draft-queue.v1",
    "campaign": CAMPAIGN,
    "status": status,
    "mode": "dry-run" if DRY_RUN else ("queue+schedule" if DO_SCHEDULE else "queue-only"),
    "armed": ARMED,
    "provider_base_url": BASE_URL,
    "enabled_integrations": sorted(ENABLED),
    "drops_requested": len(drops),
    "queued_this_run": queued,
    "adopted_from_prior_runs": adopted,
    "scheduled_verified": verified,
    "blocked": blocked,
    "failures": failures,
    "results": results,
    "generated_at": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()),
}
(ART / "evidence.json").write_text(json.dumps(evidence, indent=2) + "\n")

print(
    f"\n{'PASS' if status else 'FAIL'} — queued={queued} adopted={adopted} "
    f"scheduled_verified={verified} blocked={len(blocked)} failures={len(failures)}"
)
print(f"Evidence: {ART / 'evidence.json'}")
sys.exit(0 if status else 1)
