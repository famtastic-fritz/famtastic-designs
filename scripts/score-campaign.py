#!/usr/bin/env python3
"""Score a finished campaign's REAL publish state and write scorecard.json.

    python3 scripts/score-campaign.py --campaign <slug>

Reads marketing/campaigns/<slug>/posting-schedule.json for its program_id,
series_id, and drops[].content_id list (the compound (campaign_id, content_id)
key Phase 0 made authoritative — see famtastic_social_record's campaign_id
column and AttributionService::recordSocialLead()). For every drop it
resolves the Postiz Post-record ids recorded at queue/schedule time
(provider_ids.postiz_scheduled_group, falling back to postiz_draft_id) and
queries the local Postiz postgres, READ-ONLY, for each record's actual state:

    docker exec postiz-postgres psql -U postiz-user -d postiz-db-local -c \
      'SELECT id, state, error, "publishDate", "integrationId" FROM "Post" WHERE id IN (...)'

This script never mutates a Postiz record — no UPDATE, no status change, no
DELETE. It only reads.

Output: marketing/campaigns/<slug>/scorecard.json, conforming to
marketing/engine/schemas/campaign-scorecard.schema.json.

Honesty boundary (from the Phase 2 feasibility spike): Postiz's Post table has
no click/impression column, and GA4 reporting
(GoogleAnalyticsReportingService.php) does not request a utm_content
dimension or any click/conversion metric. So this scorecard reports publish
state only — drop/channel counts of published, error, queued, and
not-found — and carries clicks_conversions_available: false with a gap_note
explaining why, rather than inventing a number. It does not query GA4 or the
Drupal famtastic_social_record leads table; AttributionService's separate
leads->requests->revenue join is real and working but out of scope for this
script, and is named (not scored) via attribution_note.

Exit code is always 0 on a successful score (a campaign scoring badly is not
a script failure); non-zero only for usage/lookup errors before scoring.
"""

from __future__ import annotations

import argparse
import json
import pathlib
import subprocess
import sys
import time

REPO_ROOT = pathlib.Path(__file__).resolve().parent.parent
CAMPAIGNS_ROOT = REPO_ROOT / "marketing/campaigns"
SCHEMA_PATH = REPO_ROOT / "marketing/engine/schemas/campaign-scorecard.schema.json"

sys.path.insert(0, str(REPO_ROOT / "scripts"))
from campaign_schema_validate import validate_manifest  # noqa: E402

PG_CONTAINER = "postiz-postgres"
PG_USER = "postiz-user"
PG_DB = "postiz-db-local"

GAP_NOTE = (
    "clicks, impressions, conversions, CTR, and CPC are not available for any "
    "drop or channel. Postiz's Post table has no click/impression column "
    "(columns are: state, publishDate, content, image, integrationId, "
    "organizationId, createdAt, updatedAt, deletedAt, error). GA4 reporting "
    "(backend GoogleAnalyticsReportingService.php) requests only "
    "activeUsers/sessions/screenPageViews/engagedSessions/keyEvents grouped by "
    "pagePath and sessionDefaultChannelGroup — no utm_content dimension and no "
    "conversion event query exists. There is no bridge today between the "
    "Postiz publish queue and GA4 events keyed by utm_content. This scorecard "
    "reports only real Postiz publish state (published/error/queued/"
    "not_found) per drop and channel; it never estimates or backfills a "
    "click/conversion number."
)

ATTRIBUTION_NOTE = (
    "A separate leads->requests->paid-revenue join keyed on utm_content DOES "
    "work today (AttributionService.php snapshotFromRequest/recordSocialLead, "
    "MarketingCommandController.php contentGrainRows(), locally proven, not "
    "yet deployed) but this script does not query it — it only reads Postiz. "
    "Run the Marketing Command Center's content-grain report separately for "
    "lead/request/revenue-per-drop figures."
)


def fail(msg: str, code: int = 1) -> None:
    sys.stderr.write(f"FAIL: {msg}\n")
    sys.exit(code)


def parse_args() -> argparse.Namespace:
    p = argparse.ArgumentParser(
        description="Score a campaign's real Postiz publish state into scorecard.json"
    )
    p.add_argument("--campaign", required=False, help="campaign slug under marketing/campaigns/")
    return p.parse_args()


def available_campaigns() -> list[str]:
    return sorted(
        d.name for d in CAMPAIGNS_ROOT.iterdir()
        if d.is_dir() and (d / "posting-schedule.json").is_file()
    )


def load_schedule(slug: str) -> tuple[pathlib.Path, dict]:
    campaign_dir = CAMPAIGNS_ROOT / slug
    schedule_path = campaign_dir / "posting-schedule.json"
    if not schedule_path.is_file():
        fail(
            f"no posting schedule at {schedule_path.relative_to(REPO_ROOT)}\n"
            f"campaigns with a posting-schedule.json: {', '.join(available_campaigns()) or '(none)'}",
            66,
        )
    try:
        schedule = json.loads(schedule_path.read_text())
    except json.JSONDecodeError as exc:
        fail(f"{schedule_path.relative_to(REPO_ROOT)} is not valid JSON: {exc}", 65)

    problems = validate_manifest(schedule)
    if problems:
        sys.stderr.write(
            f"FAIL: {schedule_path.relative_to(REPO_ROOT)} does not conform to "
            "posting-schedule.schema.json:\n"
        )
        for p in problems:
            sys.stderr.write(f"  - {p}\n")
        sys.exit(65)
    return schedule_path, schedule


def collect_post_ids(schedule: dict) -> tuple[list[str], dict[str, list[str]]]:
    """Return (all unique post ids across the campaign, {drop_id: [post ids]})."""
    all_ids: list[str] = []
    per_drop: dict[str, list[str]] = {}
    for drop in schedule["drops"]:
        provider_ids = drop.get("provider_ids", {}) or {}
        ids = list(provider_ids.get("postiz_scheduled_group") or [])
        if not ids and provider_ids.get("postiz_draft_id"):
            ids = [provider_ids["postiz_draft_id"]]
        per_drop[drop["drop_id"]] = ids
        for i in ids:
            if i not in all_ids:
                all_ids.append(i)
    return all_ids, per_drop


def query_postiz(post_ids: list[str]) -> dict[str, dict]:
    """Read-only query of the local Postiz postgres for real state per post id.

    Returns {post_id: {state, has_error, publish_date_utc, integration_identifier,
    integration_name}} for every id that resolves to a row. Ids not found are
    simply absent from the returned dict — the caller reports those as
    not_found rather than guessing a state for them.
    """
    if not post_ids:
        return {}
    id_list = ",".join(f"'{i}'" for i in post_ids)
    sql = (
        "SELECT p.id, p.state, (p.error IS NOT NULL) AS has_error, "
        'p."publishDate", i."providerIdentifier", i.name '
        'FROM "Post" p LEFT JOIN "Integration" i ON i.id = p."integrationId" '
        f"WHERE p.id IN ({id_list});"
    )
    out = subprocess.run(
        ["docker", "exec", PG_CONTAINER, "psql", "-U", PG_USER, "-d", PG_DB,
         "-t", "-A", "-F", "|", "-c", sql],
        capture_output=True, text=True,
    )
    if out.returncode != 0:
        fail(
            "read-only Postiz postgres query failed "
            f"(docker exec {PG_CONTAINER} psql ...): {out.stderr.strip()[:500]}",
            5,
        )

    found: dict[str, dict] = {}
    for line in out.stdout.splitlines():
        line = line.strip()
        if not line:
            continue
        parts = line.split("|")
        if len(parts) < 6:
            continue
        post_id, state, has_error, publish_date, integ_ident, integ_name = parts[:6]
        publish_utc = None
        if publish_date:
            # Postiz stores naive UTC timestamps for publishDate.
            publish_utc = publish_date.replace(" ", "T") + "Z"
        found[post_id] = {
            "postiz_post_id": post_id,
            "found": True,
            "state": state or None,
            "has_error": has_error == "t",
            "integration_identifier": integ_ident or None,
            "integration_name": integ_name or None,
            "publish_date_utc": publish_utc,
        }
    return found


def score_drop(drop: dict, post_ids: list[str], resolved: dict[str, dict]) -> dict:
    channels_requested = list(drop.get("channels", []))
    records = []
    for pid in post_ids:
        rec = resolved.get(pid)
        if rec is not None:
            records.append(rec)
        else:
            records.append({
                "postiz_post_id": pid,
                "found": False,
                "state": None,
                "has_error": False,
                "integration_identifier": None,
                "integration_name": None,
                "publish_date_utc": None,
            })

    published = sum(1 for r in records if r["found"] and r["state"] == "PUBLISHED")
    error = sum(1 for r in records if r["found"] and (r["state"] == "ERROR" or r["has_error"]))
    queued = sum(1 for r in records if r["found"] and r["state"] in {"QUEUE", "DRAFT"})
    other_state = sum(
        1 for r in records
        if r["found"] and r["state"] not in {"PUBLISHED", "ERROR", "QUEUE", "DRAFT"}
        and not r["has_error"]
    )
    not_found = sum(1 for r in records if not r["found"])

    # A requested channel with zero resolved provider ids at all (e.g. the
    # schedule never recorded an id for it) is reported by name, never
    # silently absorbed into a count.
    resolved_channel_idents = {
        r["integration_identifier"] for r in records if r["found"] and r["integration_identifier"]
    }
    channel_to_integration_hint = {
        "facebook": "facebook", "facebook_video": "facebook",
        "instagram": "instagram-standalone", "instagram_reels": "instagram-standalone",
        "instagram_carousel": "instagram-standalone",
        "x": "x", "x_thread": "x",
        "tiktok": "tiktok",
        "youtube": "youtube", "youtube_shorts": "youtube",
        "linkedin": "linkedin",
    }
    missing_channels = [
        ch for ch in channels_requested
        if channel_to_integration_hint.get(ch, ch) not in resolved_channel_idents
    ]

    return {
        "drop_id": drop["drop_id"],
        "content_id": drop["content_id"],
        "scheduled_time": drop.get("scheduled_time", ""),
        "theme": drop.get("theme", ""),
        "channels_requested": channels_requested,
        "channels_missing_provider_record": missing_channels,
        "records": records,
        "counts": {
            "requested_channels": len(channels_requested),
            "provider_records_found": len(post_ids) - not_found,
            "published": published,
            "error": error,
            "queued": queued,
            "other_state": other_state,
            "not_found": not_found,
        },
    }


def _validate_scorecard(doc: dict, schema: dict) -> list[str]:
    """Minimal structural check against campaign-scorecard.schema.json.

    Purpose-built for this schema rather than reusing
    campaign_schema_validate._validate_by_hand, which is hardcoded to
    posting-schedule.json's shape (e.g. it asserts schema_version == 2
    unconditionally). Checks required top-level keys, schema_version's const,
    clicks_conversions_available's const, non-empty gap_note, and each drop's
    required keys and counts' required keys.
    """
    errors: list[str] = []
    top_required = schema.get("required", [])
    for key in top_required:
        if key not in doc:
            errors.append(f"missing required top-level key: {key}")

    if doc.get("schema_version") != schema.get("properties", {}).get("schema_version", {}).get("const"):
        errors.append("schema_version must match the schema's const")
    if doc.get("clicks_conversions_available") is not False:
        errors.append("clicks_conversions_available must be false")
    if not isinstance(doc.get("gap_note"), str) or not doc.get("gap_note", "").strip():
        errors.append("gap_note must be a non-empty string")

    drop_schema = schema.get("properties", {}).get("drops", {}).get("items", {})
    drop_required = drop_schema.get("required", [])
    counts_required = (
        drop_schema.get("properties", {}).get("counts", {}).get("required", [])
    )
    for i, drop in enumerate(doc.get("drops", [])):
        label = f"drops[{i}] ({drop.get('drop_id', '?')})"
        for key in drop_required:
            if key not in drop:
                errors.append(f"{label}: missing required key: {key}")
        for key in counts_required:
            if key not in drop.get("counts", {}):
                errors.append(f"{label}.counts: missing required key: {key}")

    totals_required = schema.get("properties", {}).get("totals", {}).get("required", [])
    for key in totals_required:
        if key not in doc.get("totals", {}):
            errors.append(f"totals: missing required key: {key}")

    return errors


def main() -> None:
    args = parse_args()
    if not args.campaign:
        sys.stderr.write(
            "usage: score-campaign.py --campaign <slug>\n"
            f"campaigns with a posting-schedule.json: {', '.join(available_campaigns()) or '(none)'}\n"
        )
        sys.exit(64)

    schedule_path, schedule = load_schedule(args.campaign)
    all_ids, per_drop = collect_post_ids(schedule)
    if not all_ids:
        print(
            f"NOTE: {args.campaign} has no provider_ids recorded on any drop yet "
            "(nothing has been queued). Scoring with zero provider records."
        )
    resolved = query_postiz(all_ids)

    drop_scores = [score_drop(d, per_drop[d["drop_id"]], resolved) for d in schedule["drops"]]

    totals = {
        "drops": len(drop_scores),
        "requested_channels": sum(d["counts"]["requested_channels"] for d in drop_scores),
        "provider_records_found": sum(d["counts"]["provider_records_found"] for d in drop_scores),
        "published": sum(d["counts"]["published"] for d in drop_scores),
        "error": sum(d["counts"]["error"] for d in drop_scores),
        "queued": sum(d["counts"]["queued"] for d in drop_scores),
        "other_state": sum(d["counts"]["other_state"] for d in drop_scores),
        "not_found": sum(d["counts"]["not_found"] for d in drop_scores),
    }
    totals["publish_success_rate"] = (
        round(totals["published"] / totals["provider_records_found"], 4)
        if totals["provider_records_found"] else None
    )

    scorecard = {
        "schema_version": 1,
        "generated_at": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()),
        "campaign_slug": args.campaign,
        "campaign_id": schedule.get("campaign_id", ""),
        "program_id": schedule.get("program_id", ""),
        "series_id": schedule.get("series_id"),
        "schedule_path": str(schedule_path.relative_to(REPO_ROOT)),
        "provider": "postiz",
        "provider_query_note": (
            f"read-only `docker exec {PG_CONTAINER} psql -U {PG_USER} -d {PG_DB}` "
            'against the "Post"/"Integration" tables, matched by the '
            "provider_ids.postiz_scheduled_group ids posting-schedule.json already "
            "recorded at queue/schedule time. No Postiz mutation performed."
        ),
        "clicks_conversions_available": False,
        "gap_note": GAP_NOTE,
        "attribution_note": ATTRIBUTION_NOTE,
        "drops": drop_scores,
        "totals": totals,
    }

    # Sanity-check our own output against campaign-scorecard.schema.json's
    # required-key shape. NOTE: campaign_schema_validate.py's hand-rolled
    # fallback is hardcoded for posting-schedule.json's shape (it asserts
    # schema_version == 2 unconditionally), so it is not reused here — a
    # purpose-built check against this schema's actual required lists instead.
    schema = json.loads(SCHEMA_PATH.read_text())
    schema_problems = _validate_scorecard(scorecard, schema)
    if schema_problems:
        sys.stderr.write("FAIL: computed scorecard does not conform to campaign-scorecard.schema.json:\n")
        for p in schema_problems:
            sys.stderr.write(f"  - {p}\n")
        sys.exit(70)

    out_path = CAMPAIGNS_ROOT / args.campaign / "scorecard.json"
    out_path.write_text(json.dumps(scorecard, indent=2) + "\n")

    print(
        f"PASS — {args.campaign}: {totals['drops']} drop(s), "
        f"{totals['provider_records_found']}/{totals['requested_channels']} channel record(s) found, "
        f"published={totals['published']} error={totals['error']} queued={totals['queued']} "
        f"not_found={totals['not_found']}"
    )
    print(f"Wrote {out_path.relative_to(REPO_ROOT)}")


if __name__ == "__main__":
    main()
