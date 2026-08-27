#!/usr/bin/env python3
"""Queue days 4-17 of the 17-day campaign as Postiz DRAFTS.
Multi-channel: Facebook, Instagram, X, TikTok, YouTube (all 5 connected).
"""
import json, sys, time, subprocess, os, pathlib, re
from datetime import datetime, timedelta, timezone

REPO_ROOT = pathlib.Path(__file__).resolve().parent.parent
CAMPAIGN_DIR = REPO_ROOT / "marketing/campaigns/55-cents-17-day"
ART = REPO_ROOT / f".artifacts/postiz-queue/{int(time.time())}"
ART.mkdir(parents=True, exist_ok=True)

PG_CONTAINER = os.environ.get("POSTIZ_PG_CONTAINER", "postiz-postgres")
BASE_URL = os.environ.get("POSTIZ_BASE_URL", "http://127.0.0.1:4007/api/public/v1")

# Get API key from Postiz DB
key_cmd = [
    "docker", "exec", PG_CONTAINER, "sh", "-c",
    'psql -U postiz-user -d postiz-db-local -t -A -c \'SELECT "apiKey" FROM "public"."Organization" WHERE "apiKey" IS NOT NULL LIMIT 1\' 2>/dev/null | head -1'
]
KEY = subprocess.run(key_cmd, capture_output=True, text=True).stdout.strip()
if not KEY:
    print(f"FAIL: no Postiz org API key found in {PG_CONTAINER}")
    sys.exit(1)

def api(path, method="GET", data=None):
    cmd = ["curl", "-sS", "--max-time", "120", "-H", f"Authorization: {KEY}"]
    if data:
        cmd += ["-H", "Content-Type: application/json", "-d", json.dumps(data)]
    if method != "GET":
        cmd += ["-X", method]
    cmd += [f"{BASE_URL}{path}"]
    out = subprocess.run(cmd, capture_output=True, text=True)
    return json.loads(out.stdout) if out.stdout else {}

# Check connectivity
connected = api("/is-connected")
if not connected.get("connected"):
    print(f"FAIL: Postiz not connected: {connected}")
    sys.exit(1)
print("PASS: Postiz reachable, org key valid")

# Resolve integrations
integrations = api("/integrations")
INTEGRATIONS = []
for name in ["facebook", "instagram-standalone", "x", "tiktok", "youtube"]:
    iid = next((i.get("id") for i in integrations if i.get("identifier") == name and not i.get("disabled", False)), None)
    if iid:
        INTEGRATIONS.append((name, iid))
        print(f"  {name}: {iid}")

if not any(n == "facebook" for n, _ in INTEGRATIONS):
    print("FAIL: no enabled facebook integration")
    sys.exit(1)

# Load campaign data
manifest = json.loads((CAMPAIGN_DIR / "manifest.json").read_text())
asset_map = json.loads((CAMPAIGN_DIR / "asset-map.days-4-17.json").read_text())
records = [r for r in manifest["records"] if r["day"] in range(4, 18)]

TODAY = datetime.now(timezone.utc).date()
DAY_DATE = {d: (TODAY + timedelta(days=d-1)).isoformat() for d in range(1, 18)}
LANDING = "https://famtasticdesigns.com/web/packages/web-basics"

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

def slot_iso_utc(day_date, time_et):
    local = datetime.strptime(f"{day_date} {time_et}", "%Y-%m-%d %H:%M").replace(tzinfo=timezone(timedelta(hours=-4)))
    return local.astimezone(timezone.utc).strftime("%Y-%m-%dT%H:%M:%S.000Z")

def upload_asset(path):
    cmd = ["curl", "-sS", "--max-time", "120", "-H", f"Authorization: {KEY}",
           "-F", f"file=@{path}", f"{BASE_URL}/upload"]
    out = subprocess.run(cmd, capture_output=True, text=True)
    try:
        return json.loads(out.stdout)
    except Exception:
        return {}

results, failures = [], []

def finish(status=None):
    manifest_path = CAMPAIGN_DIR / "manifest.json"
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
    (ART / "evidence.json").write_text(json.dumps(evidence, indent=1) + "\n")
    print(f"{'PASS' if evidence['status'] else 'FAIL'} \u2014 queued={queued_n} adopted={adopted_n} "
          f"skipped={evidence['skipped_already_queued']} failures={len(failures)}")
    return evidence

try:
    # Reconcile existing drafts
    start_date = DAY_DATE[4]
    end_date = DAY_DATE[17]
    existing = api(f"/posts?startDate={start_date}T00:00:00.000Z&endDate={end_date}T23:59:59.000Z")
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
        content = f"{headline}\n{subhead}\n\nDay {rec['day']} of 17 \u2014 {rec['promise'].lower()}.\n\n{url}"

        # Upload assets
        image_paths = []
        for variant in ("9x16", "4x5"):
            rel = asset_map["assets"][cid][variant]
            uploaded = upload_asset(str(CAMPAIGN_DIR / rel))
            if "id" not in uploaded or "path" not in uploaded:
                failures.append({"content_id": cid, "stage": f"upload-{variant}", "raw": str(uploaded)[:300]})
                break
            image_paths.append({"id": uploaded["id"], "path": uploaded["path"]})
            time.sleep(1)
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
        created = api("/posts", method="POST", data=body)
        time.sleep(2)  # Rate limit

        pid = None
        if isinstance(created, list) and created:
            pid = created[0].get("postId") or created[0].get("id")
        elif isinstance(created, dict):
            pid = (created.get("postId") or created.get("id")
                   or (created.get("posts", [{}])[0].get("postId")
                       if isinstance(created.get("posts"), list) else None))
        if not pid:
            failures.append({"content_id": cid, "stage": "create-no-id", "raw": str(created)[:300]})
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
