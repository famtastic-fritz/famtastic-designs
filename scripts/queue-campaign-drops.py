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
from datetime import datetime, timedelta, timezone

REPO_ROOT = pathlib.Path(__file__).resolve().parent.parent
CAMPAIGNS_ROOT = REPO_ROOT / "marketing/campaigns"

sys.path.insert(0, str(REPO_ROOT / "scripts"))
from campaign_schema_validate import validate_manifest  # noqa: E402

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
OVERRIDE_RECONCILIATION = "--override-reconciliation" in ARGS

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

# --edit-drop/--add-drop/--delete-drop <campaign_id>/<content_id>, plus
# repeatable --set key=value, --hard, --confirm, --test-flow.
#
# Keyed by the compound (campaign_id, content_id) id from the start — the
# same fix Phase 0 made to famtastic_social_record, applied here so a single
# drop can never be mistaken for a same-named drop in a different campaign
# (e.g. both cost-is-not-the-reason and ghost-town-ep1 use "drop-01".."drop-04").
ADD_DROP = ""
EDIT_DROP = ""
DELETE_DROP = ""
HARD_DELETE = "--hard" in ARGS
CONFIRM = "--confirm" in ARGS
TEST_FLOW = "--test-flow" in ARGS
SET_ARGS: list[str] = []
for i, arg in enumerate(ARGS):
    if arg == "--add-drop" and i + 1 < len(ARGS):
        ADD_DROP = ARGS[i + 1]
    elif arg == "--edit-drop" and i + 1 < len(ARGS):
        EDIT_DROP = ARGS[i + 1]
    elif arg == "--delete-drop" and i + 1 < len(ARGS):
        DELETE_DROP = ARGS[i + 1]
    elif arg == "--set" and i + 1 < len(ARGS):
        SET_ARGS.append(ARGS[i + 1])
MUTATION_TARGET = ADD_DROP or EDIT_DROP or DELETE_DROP
if sum(bool(x) for x in (ADD_DROP, EDIT_DROP, DELETE_DROP)) > 1:
    sys.stderr.write("FAIL: pass only one of --add-drop / --edit-drop / --delete-drop at a time\n")
    sys.exit(64)

CAMPAIGN = ""
for i, arg in enumerate(ARGS):
    if arg == "--campaign" and i + 1 < len(ARGS):
        CAMPAIGN = ARGS[i + 1]
    elif arg.startswith("--campaign="):
        CAMPAIGN = arg.split("=", 1)[1]

MUTATION_CONTENT_ID = ""
if MUTATION_TARGET:
    if "/" not in MUTATION_TARGET:
        sys.stderr.write(
            "FAIL: --add-drop/--edit-drop/--delete-drop need the compound id "
            "<campaign_id>/<content_id>, e.g. cost-is-not-the-reason/drop-05\n"
        )
        sys.exit(64)
    _mut_campaign, MUTATION_CONTENT_ID = MUTATION_TARGET.split("/", 1)
    if CAMPAIGN and CAMPAIGN != _mut_campaign:
        sys.stderr.write(
            f"FAIL: --campaign={CAMPAIGN} does not match the campaign_id "
            f"'{_mut_campaign}' in {MUTATION_TARGET!r}\n"
        )
        sys.exit(64)
    CAMPAIGN = _mut_campaign

if not CAMPAIGN:
    available = sorted(
        d.name for d in CAMPAIGNS_ROOT.iterdir()
        if d.is_dir() and (d / "posting-schedule.json").is_file()
    )
    sys.stderr.write(
        "usage: queue-campaign-drops.py --campaign <slug> [--schedule] [--dry-run]\n"
        "       queue-campaign-drops.py --add-drop <campaign_id>/<content_id> --set k=v [...]\n"
        "       queue-campaign-drops.py --edit-drop <campaign_id>/<content_id> --set k=v [...]\n"
        "       queue-campaign-drops.py --delete-drop <campaign_id>/<content_id> [--hard]\n"
        "       (mutations need --confirm, except a caller's own --test-flow post)\n"
        f"campaigns with a posting-schedule.json: {', '.join(available) or '(none)'}\n"
        "scaffold a new one: python3 scripts/new-campaign.py --slug <slug>\n"
    )
    sys.exit(64)

# Confirmation is checked as early as possible — before the schedule file is
# even read, before schema validation, before credential resolution, before
# any contact with Postiz at all. A caller with neither --confirm nor
# --test-flow gets refused having triggered zero side effects of any kind.
if MUTATION_TARGET and not TEST_FLOW and not CONFIRM:
    _verb = "add" if ADD_DROP else "edit" if EDIT_DROP else "delete"
    sys.stderr.write(
        f"REFUSED: --{_verb}-drop mutates a real Postiz record. Pass --confirm to "
        "proceed, or --test-flow only for your own disposable verification post.\n"
        "Nothing was read, sent, or changed.\n"
    )
    sys.exit(2)

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

# Validate the manifest against posting-schedule.schema.json before doing
# anything else — before resolving credentials, before --dry-run, and before
# any real Postiz call. json.loads() alone (the old behavior) accepted a
# structurally-broken manifest as long as it parsed.
try:
    schedule = json.loads(SCHEDULE_PATH.read_text())
except json.JSONDecodeError as exc:
    sys.stderr.write(f"FAIL: {SCHEDULE_PATH.relative_to(REPO_ROOT)} is not valid JSON: {exc}\n")
    sys.exit(65)
_schema_problems = validate_manifest(schedule)
if _schema_problems:
    sys.stderr.write(
        f"FAIL: {SCHEDULE_PATH.relative_to(REPO_ROOT)} does not conform to "
        "posting-schedule.schema.json:\n"
    )
    for _p in _schema_problems:
        sys.stderr.write(f"  - {_p}\n")
    sys.stderr.write(f"  fix it, or check it with: python3 scripts/new-campaign.py --validate {CAMPAIGN}\n")
    sys.exit(65)

if DO_SCHEDULE and not ARMED:
    sys.stderr.write(
        "REFUSED: --schedule converts drafts into real, live-firing posts.\n"
        "Missing: env FAMTASTIC_MARKETING_PUBLISH=true (the single arming switch).\n"
        "Nothing was read, sent, or changed.\n"
    )
    sys.exit(2)

if OVERRIDE_RECONCILIATION and (not DO_SCHEDULE or not ARMED or not CONFIRM):
    sys.stderr.write(
        "REFUSED: --override-reconciliation requires --schedule, "
        "FAMTASTIC_MARKETING_PUBLISH=true, and --confirm.\n"
        "Nothing was read, sent, or changed.\n"
    )
    sys.exit(2)

# --requeue deletes and recreates real provider records. Make the deliberately
# chosen reconciliation path just as explicit as --add/--edit/--delete, before
# reading credentials or contacting Postiz.
if REQUEUE and not CONFIRM:
    sys.stderr.write(
        "REFUSED: --requeue changes real provider records. Pass --confirm only "
        "after reviewing the target and its publish gate. Nothing was read, sent, or changed.\n"
    )
    sys.exit(2)

# An unresolved source/provider difference cannot safely pass through the
# generic edit/delete paths: those paths can retire provider records before
# the normal queue loop sees the reconciliation block. Retime it intentionally
# with --requeue --confirm, or resolve it in the provider UI first.
if EDIT_DROP or DELETE_DROP:
    _mutation_drop = next((d for d in schedule["drops"] if d["content_id"] == MUTATION_CONTENT_ID), None)
    _reconciliation = (_mutation_drop or {}).get("provider_reconciliation")
    if isinstance(_reconciliation, dict) and _reconciliation.get("status") != "reconciled":
        sys.stderr.write(
            f"REFUSED: {MUTATION_CONTENT_ID} has unresolved provider reconciliation "
            f"({_reconciliation.get('status')}). Use a reviewed --requeue --confirm "
            "or reconcile the provider record first. Nothing was read, sent, or changed.\n"
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


drops = schedule["drops"]
LANDING = schedule.get("landing_url", DEFAULT_LANDING)
if OVERRIDE_RECONCILIATION:
    print("OWNER OVERRIDE: unresolved source/provider retimes will be scheduled as recorded; duplicate or stale timing risk accepted.")
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


def reconciliation_block(drop: dict) -> dict | None:
    """Return an unresolved source/provider reconciliation record, if any.

    A posting schedule can be corrected locally without changing a provider
    draft. Treating that corrected timestamp as if Postiz had already been
    updated is the exact cross-campaign failure this runner must avoid. The
    only safe next step is a deliberately reviewed provider retime; until
    then, this runner refuses to adopt, create, or schedule the affected drop.
    """
    reconciliation = drop.get("provider_reconciliation")
    if not isinstance(reconciliation, dict):
        return None
    if reconciliation.get("status") == "reconciled" or OVERRIDE_RECONCILIATION:
        return None
    return reconciliation


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


def find_drop(content_id: str) -> dict | None:
    return next((d for d in drops if d["content_id"] == content_id), None)


def apply_set_args(drop: dict, set_args: list[str]) -> None:
    """Apply repeatable --set key=value onto one drop dict, in place.

    A handful of keys get shaped values (comma lists, nested dicts); anything
    else is set verbatim as a top-level string field.
    """
    for raw in set_args:
        if "=" not in raw:
            sys.stderr.write(f"FAIL: --set '{raw}' needs key=value\n")
            sys.exit(64)
        key, value = raw.split("=", 1)
        key = key.strip()
        if key == "media":
            drop["primary_media"] = value
        elif key == "channels":
            drop["channels"] = [c.strip() for c in value.split(",") if c.strip()]
        elif key == "tags":
            drop["tags"] = [t.strip() for t in value.split(",") if t.strip()]
        elif key.startswith("copy."):
            drop.setdefault("copy", {})[key.split(".", 1)[1]] = value
        elif key.startswith("utm."):
            drop.setdefault("utm", {})[key.split(".", 1)[1]] = value
        elif key.startswith("platform_settings."):
            _, ident = key.split(".", 1)
            try:
                drop.setdefault("platform_settings", {})[ident] = json.loads(value)
            except json.JSONDecodeError:
                sys.stderr.write(f"FAIL: --set platform_settings.{ident} needs a JSON object value\n")
                sys.exit(64)
        elif key == "drop_number":
            drop["drop_number"] = int(value)
        else:
            drop[key] = value


# Provider listing window, derived from the schedule itself so it works for any
# campaign and any date range, with slack on both ends for adoption.
_times = [datetime.fromisoformat(d["scheduled_time"]).astimezone(timezone.utc) for d in drops]
WINDOW = (
    f"?startDate={(min(_times) - timedelta(days=2)).strftime('%Y-%m-%dT00:00:00.000Z')}"
    f"&endDate={(max(_times) + timedelta(days=2)).strftime('%Y-%m-%dT23:59:59.000Z')}"
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
    # Ownership must be PROVEN before deleting, never inferred from timing.
    # Matching siblings by publishDate alone once destroyed seven unrelated
    # posts that merely shared a slot. A record is ours only if it carries this
    # drop's utm_content marker or its id is one we recorded.
    known = set(target.get("provider_ids", {}).get("postiz_scheduled_group", []))
    for key in ("postiz_draft_id", "postiz_scheduled_id"):
        if target.get("provider_ids", {}).get(key):
            known.add(target["provider_ids"][key])
    marker = f"utm_content={target['content_id']}"

    current = api(f"/posts{WINDOW}") if not DRY_RUN else {}
    doomed, foreign = [], []
    for post in (current.get("posts", []) if isinstance(current, dict) else []):
        stamp = str(post.get("publishDate") or post.get("date") or "")[:19]
        if stamp != old_stamp:
            continue
        pid = str(post.get("id"))
        if pid in known or marker in str(post.get("content", "")):
            doomed.append((pid, post.get("state")))
        else:
            foreign.append(pid)
    if foreign:
        print(f"  keeping {len(foreign)} record(s) in this slot that are not "
              f"{REQUEUE}'s: {', '.join(i[:12] for i in foreign)}")
    if not doomed:
        sys.stderr.write(
            f"FAIL: found no records provably belonging to {REQUEUE}.\n"
            "Refusing to delete by timestamp alone. If its ids were lost, remove\n"
            "them in the Postiz UI and rerun the plain queue command.\n")
        sys.exit(3)

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
    # The recorded provider records were deliberately retired above. There is
    # no longer a source/provider time mismatch; the normal queue path below
    # will create one fresh draft at the corrected source time.
    target.pop("provider_reconciliation", None)
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
    WINDOW = (f"?startDate={(min(_times) - timedelta(days=2)).strftime('%Y-%m-%dT00:00:00.000Z')}"
              f"&endDate={(max(_times) + timedelta(days=2)).strftime('%Y-%m-%dT23:59:59.000Z')}")

# ---------------------------------------------------------------------------
# --add-drop / --edit-drop / --delete-drop: mutate exactly ONE drop, never
# re-running or touching the rest of the campaign. Every path here validates
# the resulting posting-schedule.json against the schema BEFORE any Postiz
# call — never after, and never skipped.
# ---------------------------------------------------------------------------
if ADD_DROP:
    if find_drop(MUTATION_CONTENT_ID) is not None:
        sys.stderr.write(f"FAIL: content_id '{MUTATION_CONTENT_ID}' already exists in {CAMPAIGN}\n")
        sys.exit(64)

    new_drop = {
        "drop_id": MUTATION_CONTENT_ID,
        "content_id": MUTATION_CONTENT_ID,
        "scheduled_time": "",
        "channels": [],
        "copy": {},
        "state": "idea",
        "utm": {
            "source": "social",
            "medium": "social",
            "campaign": schedule.get("campaign_id", CAMPAIGN),
            "content": MUTATION_CONTENT_ID,
        },
    }
    apply_set_args(new_drop, SET_ARGS)
    if not new_drop.get("scheduled_time"):
        sys.stderr.write("FAIL: --add-drop needs --set scheduled_time=<ISO8601 with offset>\n")
        sys.exit(64)

    schedule.setdefault("drops", []).append(new_drop)
    _problems = validate_manifest(schedule)
    if _problems:
        sys.stderr.write(f"FAIL: new drop {MUTATION_CONTENT_ID} fails schema validation "
                          "(before any Postiz call — nothing sent):\n")
        for p in _problems:
            sys.stderr.write(f"  - {p}\n")
        schedule["drops"].remove(new_drop)
        sys.exit(65)

    print(f"ADD-DROP: {MUTATION_CONTENT_ID} validated and added to {CAMPAIGN}; queuing its draft now")
    # Restrict the normal per-drop loop below to ONLY this new drop. `drops`
    # is rebound to a fresh list here — schedule["drops"] (already appended
    # above) is untouched, so saving `schedule` at the end of the run persists
    # every other drop exactly as it was.
    drops = [new_drop]

elif EDIT_DROP:
    target = find_drop(MUTATION_CONTENT_ID)
    if target is None:
        sys.stderr.write(f"FAIL: no drop '{MUTATION_CONTENT_ID}' in {CAMPAIGN}\n")
        sys.exit(66)

    apply_set_args(target, SET_ARGS)
    _problems = validate_manifest(schedule)
    if _problems:
        sys.stderr.write(f"FAIL: edited drop {MUTATION_CONTENT_ID} fails schema validation "
                          "(before any Postiz call — nothing sent):\n")
        for p in _problems:
            sys.stderr.write(f"  - {p}\n")
        sys.exit(65)

    # Postiz has no in-place content-edit endpoint for a post already created
    # (only a status change and a delete), so an edit means: safely retire
    # whatever record already exists for this drop, then let the normal loop
    # below create a fresh one from the updated fields. Never an in-place
    # overwrite of a record this run did not itself just create.
    old_ids = set(target.get("provider_ids", {}).get("postiz_scheduled_group", []))
    for key_ in ("postiz_draft_id", "postiz_scheduled_id"):
        if target.get("provider_ids", {}).get(key_):
            old_ids.add(target["provider_ids"][key_])
    if old_ids and not DRY_RUN:
        current = api(f"/posts{WINDOW}")
        states = {
            str(p.get("id")): p.get("state")
            for p in (current.get("posts", []) if isinstance(current, dict) else [])
        }
        for pid in old_ids:
            state = states.get(pid, "UNKNOWN")
            if state not in {"DRAFT", "UNKNOWN"}:
                # Revert to draft first so it cannot fire mid-edit — the same
                # order proven safe manually this session.
                api(f"/posts/{pid}/status", method="PUT", data={"status": "draft"})
            api(f"/posts/{pid}", method="DELETE")
            print(f"  removed prior record {pid[:12]} (was {state}) before recreating")
    target["provider_ids"] = {}

    print(f"EDIT-DROP: {MUTATION_CONTENT_ID} validated and updated; queuing its fresh draft now")
    drops = [target]

elif DELETE_DROP:
    target = find_drop(MUTATION_CONTENT_ID)
    if target is None:
        sys.stderr.write(f"FAIL: no drop '{MUTATION_CONTENT_ID}' in {CAMPAIGN}\n")
        sys.exit(66)

    if HARD_DELETE and len(drops) <= 1:
        sys.stderr.write(
            "FAIL: refusing --hard on the last remaining drop — posting-schedule.json "
            "needs at least one drop (schema minItems: 1). Delete the campaign folder "
            "instead if that is really intended.\n"
        )
        sys.exit(3)

    # Validate the resulting manifest state BEFORE touching Postiz, exactly
    # like the add/edit paths above.
    if HARD_DELETE:
        candidate_drops = [d for d in drops if d["content_id"] != MUTATION_CONTENT_ID]
        candidate = dict(schedule)
        candidate["drops"] = candidate_drops
    else:
        candidate = schedule
    _problems = validate_manifest(candidate)
    if _problems:
        sys.stderr.write(f"FAIL: schedule after deleting {MUTATION_CONTENT_ID} would fail "
                          "schema validation (before any Postiz call — nothing sent):\n")
        for p in _problems:
            sys.stderr.write(f"  - {p}\n")
        sys.exit(65)

    known = set(target.get("provider_ids", {}).get("postiz_scheduled_group", []))
    for key_ in ("postiz_draft_id", "postiz_scheduled_id"):
        if target.get("provider_ids", {}).get(key_):
            known.add(target["provider_ids"][key_])
    marker = f"utm_content={target['content_id']}"

    doomed: list[tuple[str, str]] = []
    if not DRY_RUN:
        current = api(f"/posts{WINDOW}")
        for post in (current.get("posts", []) if isinstance(current, dict) else []):
            pid = str(post.get("id"))
            if pid in known or marker in str(post.get("content", "")):
                doomed.append((pid, str(post.get("state"))))
        seen_ids = {pid for pid, _ in doomed}
        for pid in known - seen_ids:
            # A known id the window listing didn't return (e.g. outside the
            # window, or already gone) — still attempt its delete directly
            # rather than silently doing nothing with it.
            doomed.append((pid, "UNKNOWN"))

    if DRY_RUN:
        print(f"DRY RUN: would revert-to-draft then delete {len(known)} known "
              f"record(s) for {MUTATION_CONTENT_ID}")
    else:
        # Default behavior, always: revert-to-draft then soft-delete via the
        # Postiz API — never a raw database write.
        for pid, state in doomed:
            if state not in {"DRAFT", "UNKNOWN"}:
                api(f"/posts/{pid}/status", method="PUT", data={"status": "draft"})
            api(f"/posts/{pid}", method="DELETE")
            print(f"  deleted {pid[:12]} (was {state})")
        if not doomed:
            print(f"  NOTE: {MUTATION_CONTENT_ID} had no known/matched Postiz record to delete")

    if HARD_DELETE:
        schedule["drops"] = candidate["drops"]
    else:
        target["provider_ids"] = {}
        target["state"] = "idea"

    if not DRY_RUN:
        SCHEDULE_PATH.write_text(json.dumps(schedule, indent=2) + "\n")
    print(f"{'DRY RUN ' if DRY_RUN else ''}DELETE-DROP{' (hard)' if HARD_DELETE else ''} "
          f"complete for {MUTATION_CONTENT_ID}")
    sys.exit(0)

# Adopt anything a previous partial run already created, so reruns never
# duplicate a live post.
existing_by_utm: dict[str, dict] = {}
if not DRY_RUN:
    window = api(f"/posts{WINDOW}")
    for post in (window.get("posts", []) if isinstance(window, dict) else []):
        body = str(post.get("content", ""))
        match = re.search(r"utm_content=([a-zA-Z0-9_-]+)", body)
        camp = re.search(r"utm_campaign=([a-zA-Z0-9_-]+)", body)
        if match and post.get("state") in {"DRAFT", "QUEUE"}:
            # SCOPED BY CAMPAIGN, deliberately.
            #
            # 2026-09-05: three campaigns were built in parallel and every one of
            # them numbered its drops drop-01..drop-06. Keyed on utm_content
            # alone, the second campaign to run "adopted" the first campaign's
            # live records, queued nothing of its own, and printed
            # "PASS — adopted=6". Its assets were never scheduled and the run
            # looked clean. A marker that is not unique is not an idempotency
            # marker; it is a silent overwrite.
            key = f"{camp.group(1) if camp else '?'}|{match.group(1)}"
            existing_by_utm.setdefault(key, post)

for drop in drops:
    cid = drop["content_id"]
    entry = {"content_id": cid, "scheduled_time": drop["scheduled_time"]}

    reconciliation = reconciliation_block(drop)
    if reconciliation:
        entry["action"] = "blocked_reconciliation"
        entry["provider_reconciliation"] = reconciliation.get("status", "unresolved")
        blocked.append({
            "content_id": cid,
            "reason": "source schedule differs from recorded provider draft",
            "provider_reconciliation": reconciliation,
        })
        results.append(entry)
        print(
            f"BLOCKED: {cid} — source schedule correction is not reconciled "
            "with the recorded provider draft; no adoption, queue, or schedule action was attempted"
        )
        continue

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

    _utm = drop.get("utm", {})
    _key = f"{_utm.get('campaign', '?')}|{_utm.get('content', cid)}"
    prior = drop.get("provider_ids", {}).get("postiz_draft_id") or (
        existing_by_utm.get(_key, {}).get("id")
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
if DO_SCHEDULE and DRY_RUN:
    print("DRY RUN: live schedule conversion and provider read-back skipped; no Postiz contact")

if DO_SCHEDULE and not DRY_RUN:
    now = datetime.now(timezone.utc)

    # Postiz creates ONE post record PER INTEGRATION and returns them as a
    # group; POST /posts hands back only the first id. Converting just that id
    # schedules a single channel and silently leaves the siblings as DRAFT, so
    # a multi-channel drop would publish to one. Group by the exact UTM campaign
    # and content marker instead of publishDate: source/provider retimes can
    # leave a provider row at the old time, and timestamp grouping can pull an
    # unrelated campaign's row into this drop (the original flood bug).
    siblings: dict[str, list[str]] = {}
    listing = api(f"/posts{WINDOW}")
    for post in (listing.get("posts", []) if isinstance(listing, dict) else []):
        if post.get("state") not in {"DRAFT", "QUEUE"}:
            continue
        body = str(post.get("content", ""))
        camp = re.search(r"utm_campaign=([a-zA-Z0-9_-]+)", body)
        content = re.search(r"utm_content=([a-zA-Z0-9_-]+)", body)
        if camp and content:
            siblings.setdefault(f"{camp.group(1)}|{content.group(1)}", []).append(str(post.get("id")))

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
        drop = next(d for d in drops if d["content_id"] == entry["content_id"])
        utm = drop.get("utm", {})
        marker_key = f"{utm.get('campaign', '?')}|{utm.get('content', entry['content_id'])}"
        group = siblings.get(marker_key, [])
        # If the root record already published at a stale provider time, do
        # not PUT it back through the scheduler. The marker-scoped active
        # siblings are the only records that still need scheduling. Keep the
        # root in the source ledger below for evidence, but never reschedule a
        # PUBLISHED record.
        ids = list(dict.fromkeys(group or [pid]))
        for one in ids:
            api(f"/posts/{one}/status", method="PUT", data={"status": "schedule"})
        entry["schedule_action"] = "converted"
        entry["scheduled_ids"] = ids
        drop["provider_ids"]["postiz_scheduled_id"] = pid
        recorded = drop["provider_ids"].get("postiz_scheduled_group", [])
        if not isinstance(recorded, list):
            recorded = []
        drop["provider_ids"]["postiz_scheduled_group"] = list(dict.fromkeys(recorded + ids))
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
