#!/usr/bin/env bash
# FAMtastic Designs — queue days 4-17 of the 17-day campaign as Postiz DRAFTS.
# Multi-channel: Facebook, Instagram, X, TikTok, YouTube (all 5 connected).
# Tier-1 approval (Fritz) covers DRAFT QUEUEING ONLY. Publish remains gated.
set -uo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
CAMPAIGN_DIR="$REPO_ROOT/marketing/campaigns/55-cents-17-day"
ART="$REPO_ROOT/.artifacts/postiz-queue/$(date +%s)"
mkdir -p "$ART"

PG_CONTAINER="${POSTIZ_PG_CONTAINER:-postiz-postgres}"
BASE_URL="${POSTIZ_BASE_URL:-http://127.0.0.1:4007/api/public/v1}"

KEY="$(docker exec "$PG_CONTAINER" sh -c 'psql -U postiz-user -d postiz-db-local -t -A -c '"'"'SELECT "apiKey" FROM "public"."Organization" WHERE "apiKey" IS NOT NULL LIMIT 1'"'"' 2>/dev/null | head -1)"
if [[ -z "$KEY" ]]; then
  printf 'FAIL: no Postiz org API key found in %s\n' "$PG_CONTAINER"
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

# Resolve all 5 integration IDs
FB_ID=$(printf '%s' "$INTEGRATION" | jq -r '[.[] | select(.identifier=="facebook" and (.disabled|not))][0].id // empty')
IG_ID=$(printf '%s' "$INTEGRATION" | jq -r '[.[] | select(.identifier=="instagram-standalone" and (.disabled|not))][0].id // empty')
X_ID=$(printf '%s' "$INTEGRATION" | jq -r '[.[] | select(.identifier=="x" and (.disabled|not))][0].id // empty')
TK_ID=$(printf '%s' "$INTEGRATION" | jq -r '[.[] | select(.identifier=="tiktok" and (.disabled|not))][0].id // empty')
YT_ID=$(printf '%s' "$INTEGRATION" | jq -r '[.[] | select(.identifier=="youtube" and (.disabled|not))][0].id // empty')

printf 'Integrations: fb=%s ig=%s x=%s tiktok=%s youtube=%s\n' "$FB_ID" "$IG_ID" "$X_ID" "$TK_ID" "$YT_ID"

if [[ -z "$FB_ID" ]]; then
  printf 'FAIL: no enabled facebook integration\n'
  exit 1
fi

export POSTIZ_KEY="$KEY"
python3 - "$CAMPAIGN_DIR" "$BASE_URL" "$FB_ID" "$IG_ID" "$X_ID" "$TK_ID" "$YT_ID" "$ART" << 'PYEOF'
import json, sys, time, subprocess, os, pathlib, re
from datetime import datetime, timedelta, timezone

campaign_dir, base_url, fb_id, ig_id, x_id, tk_id, yt_id, art_dir = sys.argv[1:9]
campaign_dir = pathlib.Path(campaign_dir)
manifest_path = campaign_dir / "manifest.json"
asset_map = json.loads((campaign_dir / "asset-map.days-4-17.json").read_text())
manifest = json.loads(manifest_path.read_text())
records = [r for r in manifest["records"] if r["day"] in range(4, 18)]

# Multi-channel mapping: which integrations to post to per channel config
# For now: post to all enabled channels
INTEGRATIONS = []
for name, iid in [("facebook", fb_id), ("instagram-standalone", ig_id), ("x", x_id), ("tiktok", tk_id), ("youtube", yt_id)]:
    if iid:
        INTEGRATIONS.append((name, iid))

# Dates: start from tomorrow, 17 days total
TODAY = datetime.now(timezone.utc).date()
DAY_DATE = {d: (TODAY + timedelta(days=d-1)).isoformat() for d in range(1, 18)}

LANDING = "https://famtasticdesigns.com/web/packages/web-basics"

MOMENT_COPY = {
    "teach": ("YOUR BUSINESS CAN HAVE A WEBSITE FOR ABOUT 55¢ A DAY.",
              "The $199 price is paid once. 55¢ is the annualized comparison."),
    "challenge": ("TOO EXPENSIVE. I DON'T NEED ONE. BUSINESS IS FINE.",
                  "The concerns are understandable. The cost barrier is removable."),
    "prove": ("$199 ÷ 365 = ABOUT 55¢ A DAY.",
              "One-time purchase. First-year basic hosting included. Eligible new-domain registration or existing-domain connection included."),
    "invite": ("COST IS NOT ONE OF THEM. PERIOD.",
               "Start Web Basics—or take the assessment if your business needs more."),
}


def slot_iso_utc(day_date: str, time_et: str) -> str:
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
        "status": bool(status) if status is not None else (len(failures) == 0 and queued_n + adopted_n >= len(records)),
        "integrations": [name for name, _ in INTEGRATIONS],
        "publish_gate": "CLOSED - all items type=draft; publish requires Fritz review",
        "requested_records": len(records),
        "queued_this_run": queued_n,
        "adopted_from_prior_runs": adopted_n,
        "skipped_already_queued": sum(1 for r in results if r.get("skipped")),
        "failures": failures,
        "results": results,
        "generated_at": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()),
    }
    (pathlib.Path(art_dir) / "evidence.json").write_text(json.dumps(evidence, indent=1) + "\n")
    print(f"{'PASS' if evidence['status'] else 'FAIL'} — queued={queued_n} adopted={adopted_n} "
          f"skipped={evidence['skipped_already_queued']} failures={len(failures)}")
    return evidence


try:
    # Reconcile existing drafts
    start_date = DAY_DATE[4]
    end_date = DAY_DATE[17]
    existing = curl_json([f"{base_url}/posts?startDate={start_date}T00:00:00.000Z&endDate={end_date}T23:59:59.000Z"])
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
                "integration": "multi-channel", "postiz_post_id": pid,
                "note": "draft existed in Postiz from prior partial run; reconciled into manifest"})
            rec["channels"] = [name for name, _ in INTEGRATIONS]
            results.append({"content_id": cid, "adopted": True, "postiz_draft_id": pid})

    # Queue missing records
    for rec in records:
        cid = rec["content_id"]
        if any(r.get("content_id") == cid and (r.get("queued") or r.get("adopted") or r.get("skipped")) for r in results):
            continue

        headline, subhead = MOMENT_COPY[rec["moment"]]
        utm = rec["utm"]
        url = (f"{LANDING}?utm_source=famtastic&utm_medium={utm['medium']}"
               f"&utm_campaign={utm['campaign']}&utm_content={cid}")
        content = f"{headline}\n{subhead}\n\nDay {rec['day']} of 17 — {rec['promise'].lower()}.\n\n{url}"

        # Upload assets for this record
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

        # Build posts array for all enabled integrations
        posts_array = []
        for name, iid in INTEGRATIONS:
            posts_array.append({
                "integration": {"id": iid},
                "value": [{"content": content, "image": image_paths}]
            })

        body = {
            "type": "draft", "shortLink": False, "date": iso, "tags": [],
            "posts": posts_array,
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
            "integration": "multi-channel", "date_utc": iso, "postiz_post_id": pid})
        rec["channels"] = [name for name, _ in INTEGRATIONS]
        results.append({"content_id": cid, "queued": True, "postiz_draft_id": pid, "date_utc": iso})
finally:
    ev = finish()

sys.exit(0 if ev["status"] else 1)
PYEOF
RC=$?

printf 'Evidence: %s/evidence.json\n' "$ART"
exit $RC
