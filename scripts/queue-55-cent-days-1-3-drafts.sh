#!/usr/bin/env bash
# FAMtastic Designs — queue days 1-3 of the 17-day campaign as Postiz DRAFTS.
# Tier-1 approval (Fritz, 2026-08-23) covers DRAFT QUEUEING ONLY. Publish
# remains gated until Fritz reviews a queued week. Nothing publishes: every
# post is created with type=draft and lands in state DRAFT in Postiz.
# Idempotent: existing Postiz drafts are reconciled into the manifest by
# utm_content, and records already carrying postiz_draft_id are skipped.
set -uo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
CAMPAIGN_DIR="$REPO_ROOT/marketing/campaigns/55-cents-17-day"
ART="$REPO_ROOT/.artifacts/postiz-queue/$(date +%s)"
mkdir -p "$ART"

PG_CONTAINER="${POSTIZ_PG_CONTAINER:-postiz-postgres}"
BASE_URL="${POSTIZ_BASE_URL:-http://127.0.0.1:4007/api/public/v1}"

KEY="$(docker exec "$PG_CONTAINER" sh -c 'psql -U "$POSTGRES_USER" -d "${POSTGRES_DB:-postiz-db-local}" -t -A -c "SELECT \"apiKey\" FROM \"Organization\" WHERE \"apiKey\" IS NOT NULL LIMIT 1"' 2>/dev/null | head -1)"
if [[ -z "$KEY" ]]; then
  printf 'FAIL: no Postiz org API key found in %s (never commit keys; fetch from runtime DB)\n' "$PG_CONTAINER"
  exit 1
fi
AUTH="Authorization: $KEY"

api() { curl -sS --max-time 60 -H "$AUTH" "$@"; }

CONNECTED=$(api "$BASE_URL/is-connected")
if [[ "$CONNECTED" != *'"connected": true'* && "$CONNECTED" != *'"connected":true'* ]]; then
  printf 'FAIL: Postiz is-connected says: %s\n' "$CONNECTED"
  exit 1
fi
printf 'PASS: Postiz reachable, org key valid\n'

INTEGRATION=$(api "$BASE_URL/integrations")
INTEGRATION_ID=$(printf '%s' "$INTEGRATION" | jq -r '[.[] | select(.identifier=="facebook" and (.disabled|not))][0].id // empty')
INTEGRATION_NAME=$(printf '%s' "$INTEGRATION" | jq -r '[.[] | select(.identifier=="facebook")][0].name // empty')
if [[ -z "$INTEGRATION_ID" ]]; then
  printf 'FAIL: no enabled facebook integration. Integrations response:\n%s\n' "$INTEGRATION"
  exit 1
fi
printf 'PASS: enabled facebook integration found (%s)\n' "$INTEGRATION_NAME"

export POSTIZ_KEY="$KEY"
python3 - "$CAMPAIGN_DIR" "$BASE_URL" "$INTEGRATION_ID" "$ART" << 'PYEOF'
import json, sys, time, subprocess, os, pathlib, re
from datetime import datetime, timedelta, timezone

campaign_dir, base_url, integration_id, art_dir = sys.argv[1:5]
campaign_dir = pathlib.Path(campaign_dir)
manifest_path = campaign_dir / "manifest.json"
asset_map = json.loads((campaign_dir / "asset-map.days-1-3.json").read_text())
manifest = json.loads(manifest_path.read_text())
records = [r for r in manifest["records"] if r["day"] in (1, 2, 3)]

MOMENT_COPY = {
    "teach": ("YOUR BUSINESS CAN HAVE A WEBSITE FOR ABOUT 55\u00a2 A DAY.",
              "The $199 price is paid once. 55\u00a2 is the annualized comparison."),
    "challenge": ("TOO EXPENSIVE. I DON'T NEED ONE. BUSINESS IS FINE.",
                  "The concerns are understandable. The cost barrier is removable."),
    "prove": ("$199 \u00f7 365 = ABOUT 55\u00a2 A DAY.",
              "One-time purchase. First-year basic hosting included. Eligible new-domain registration or existing-domain connection included."),
    "invite": ("COST IS NOT ONE OF THEM. PERIOD.",
               "Start Web Basics\u2014or take the assessment if your business needs more."),
}
DAY_DATE = {1: "2026-08-24", 2: "2026-08-25", 3: "2026-08-26"}
LANDING = "https://famtasticdesigns.com/web/packages/web-basics"


def slot_iso_utc(day_date: str, time_et: str) -> str:
    """ET (UTC-4, August DST) -> valid ISO-8601 UTC; handles hour rollover."""
    local = datetime.strptime(f"{day_date} {time_et}", "%Y-%m-%d %H:%M").replace(
        tzinfo=timezone(timedelta(hours=-4)))
    return local.astimezone(timezone.utc).strftime("%Y-%m-%dT%H:%M:%S.000Z")


def curl_json(args):
    out = subprocess.run(["curl", "-sS", "--max-time", "120", "-H",
                          "Authorization: " + os.environ["POSTIZ_KEY"]] + args,
                         capture_output=True, text=True)
    if out.returncode != 0:
        raise RuntimeError(out.stderr)
    return json.loads(out.stdout)


results, failures = [], []


def finish(status=None):
    manifest_path.write_text(json.dumps(manifest, indent=1) + "\n")
    queued_n = sum(1 for r in results if r.get("queued"))
    adopted_n = sum(1 for r in results if r.get("adopted"))
    evidence = {
        "schema": "famtastic.postiz-draft-queue.v1",
        "status": bool(status) if status is not None else (len(failures) == 0 and queued_n + adopted_n >= 12),
        "integration": {"id": integration_id, "provider": "facebook"},
        "publish_gate": "CLOSED - all items type=draft; publish requires Fritz review of a queued week",
        "requested_records": len(records),
        "queued_this_run": queued_n,
        "adopted_from_prior_runs": adopted_n,
        "skipped_already_queued": sum(1 for r in results if r.get("skipped")),
        "failures": failures,
        "results": results,
        "generated_at": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()),
    }
    (pathlib.Path(art_dir) / "evidence.json").write_text(json.dumps(evidence, indent=1) + "\n")
    print(f"{'PASS' if evidence['status'] else 'FAIL'} \u2014 queued={queued_n} adopted={adopted_n} "
          f"skipped={evidence['skipped_already_queued']} failures={len(failures)}")
    return evidence


try:
    # --- Reconcile drafts created by prior runs whose ids never landed ------
    existing = curl_json(["%s/posts?startDate=2026-08-20T00%%3A00%%3A00.000Z&endDate=2026-08-30T00%%3A00%%3A00.000Z" % base_url])
    by_cid = {}
    for p in existing.get("posts", []):
        if p.get("state") != "DRAFT":
            continue
        m = re.search(r"utm_content=([a-z0-9-]+)", p.get("content", ""))
        if m:
            by_cid.setdefault(m.group(1), p["id"])
    for rec in records:
        cid = rec["content_id"]
        if rec.get("provider_ids", {}).get("postiz_draft_id"):
            results.append({"content_id": cid, "skipped": True,
                            "postiz_draft_id": rec["provider_ids"]["postiz_draft_id"]})
            continue
        if cid in by_cid:
            pid = by_cid[cid]
            rec.setdefault("provider_ids", {})["postiz_draft_id"] = pid
            rec.setdefault("evidence", []).append({
                "kind": "postiz_draft_adopted",
                "at": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()),
                "integration": "facebook", "postiz_post_id": pid,
                "note": "draft existed in Postiz from prior partial run; reconciled into manifest"})
            rec["channels"] = ["facebook"]
            rec["utm"]["source"] = "facebook"
            results.append({"content_id": cid, "adopted": True, "postiz_draft_id": pid})

    # --- Queue whatever is still missing -------------------------------------
    for rec in records:
        cid = rec["content_id"]
        if any(r.get("content_id") == cid and (r.get("queued") or r.get("adopted") or r.get("skipped")) for r in results):
            continue
        headline, subhead = MOMENT_COPY[rec["moment"]]
        utm = rec["utm"]
        url = (f"{LANDING}?utm_source=facebook&utm_medium={utm['medium']}"
               f"&utm_campaign={utm['campaign']}&utm_content={cid}")
        content = f"{headline}\n{subhead}\n\nDay {rec['day']} of 17 \u2014 {rec['promise'].lower()}.\n\n{url}"

        image_paths = []
        for variant in ("9x16", "4x5"):
            rel = asset_map["assets"][cid][variant]
            up = subprocess.run(["curl", "-sS", "--max-time", "120", "-H",
                                 "Authorization: " + os.environ["POSTIZ_KEY"],
                                 "-F", "file=@" + str(campaign_dir / rel),
                                 base_url + "/upload"], capture_output=True, text=True)
            try:
                uploaded = json.loads(up.stdout)
            except Exception:
                uploaded = {}
            if "id" not in uploaded or "path" not in uploaded:
                failures.append({"content_id": cid, "stage": f"upload-{variant}", "raw": up.stdout[:300]})
                break
            image_paths.append({"id": uploaded["id"], "path": uploaded["path"]})
        if len(image_paths) != 2:
            continue

        iso = slot_iso_utc(DAY_DATE[rec["day"]], rec["suggested_time_et"])
        body = {
            "type": "draft", "shortLink": False, "date": iso, "tags": [],
            "posts": [{"integration": {"id": integration_id},
                       "value": [{"content": content, "image": image_paths}]}],
        }
        create = subprocess.run(["curl", "-sS", "--max-time", "120", "-X", "POST", "-H",
                                 "Authorization: " + os.environ["POSTIZ_KEY"], "-H",
                                 "Content-Type: application/json", "-d", json.dumps(body),
                                 base_url + "/posts"], capture_output=True, text=True)
        try:
            created = json.loads(create.stdout)
        except Exception:
            created = None
        pid = None
        if isinstance(created, list) and created:
            pid = created[0].get("postId") or created[0].get("id")
        elif isinstance(created, dict):
            pid = (created.get("postId") or created.get("id")
                   or (created.get("posts", [{}])[0].get("postId")
                       if isinstance(created.get("posts"), list) else None))
        if not pid:
            failures.append({"content_id": cid, "stage": "create-no-id", "raw": create.stdout[:300]})
            continue
        rec.setdefault("provider_ids", {})["postiz_draft_id"] = pid
        rec.setdefault("evidence", []).append({
            "kind": "postiz_draft_queued", "at": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()),
            "integration": "facebook", "date_utc": iso, "postiz_post_id": pid})
        rec["channels"] = ["facebook"]
        rec["utm"]["source"] = "facebook"
        results.append({"content_id": cid, "queued": True, "postiz_draft_id": pid, "date_utc": iso})
finally:
    ev = finish()

sys.exit(0 if ev["status"] else 1)
PYEOF
RC=$?

VERIFY=$(api "$BASE_URL/posts?startDate=2026-08-20T00%3A00%3A00.000Z&endDate=2026-08-30T00%3A00%3A00.000Z")
printf '%s\n' "$VERIFY" > "$ART/posts-verify.json"
COUNT_DRAFT=$(printf '%s' "$VERIFY" | jq '[.posts[]? | select(.state=="DRAFT")] | length')
if [[ "${COUNT_DRAFT:-0}" -ge 12 ]]; then
  printf 'PASS: %s draft post(s) visible via Postiz API\n' "$COUNT_DRAFT"
else
  printf 'FAIL: expected >=12 drafts via API verification, saw %s (see %s)\n' "$COUNT_DRAFT" "$ART/posts-verify.json"
  RC=$((RC > 0 ? RC : 2))
fi
printf 'Evidence: %s/evidence.json\n' "$ART"
exit $RC
