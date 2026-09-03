#!/usr/bin/env python3
"""Schedule the 4-drop evaluation campaign directly into Postiz database."""

import json
import subprocess
import time
import uuid

DROPS_FILE = "marketing/campaigns/cost-is-not-the-reason/evaluation-schedule-tonight.json"

INTEGRATIONS = {
    "facebook": "cmsqql1yi0001ue7cchyfzhia",
    "instagram": "cmt7n494s0001o76t6wljyda9",
    "tiktok": "cmt7yfwap0001mt8md6t1a26p",
    "x": "cmt8mwan00001nv8a9lk8iw9s",
    "youtube": "cmt8u318e0001rt859rdy710c",
}

def get_org_id():
    cmd = [
        "docker", "exec", "postiz-postgres", "psql", "-U", "postiz-user", "-d", "postiz-db-local",
        "-t", "-A", "-c", 'SELECT id FROM "Organization" LIMIT 1;'
    ]
    res = subprocess.run(cmd, capture_output=True, text=True, check=True)
    return res.stdout.strip()

def schedule_post(org_id, integration_id, content, publish_date_iso, image_url=None):
    post_id = f"c{uuid.uuid4().hex[:24]}"
    group_id = f"g_{uuid.uuid4().hex[:12]}"
    
    # Escape single quotes for SQL
    clean_content = content.replace("'", "''")
    image_val = f"'{image_url}'" if image_url else "NULL"
    
    sql = f"""
    INSERT INTO "Post" (
        "id", "state", "publishDate", "organizationId", "integrationId", 
        "content", "delay", "group", "creationMethod", "updatedAt", "image"
    ) VALUES (
        '{post_id}', 'QUEUE', '{publish_date_iso}', '{org_id}', '{integration_id}',
        '{clean_content}', 0, '{group_id}', 'UNKNOWN', CURRENT_TIMESTAMP, {image_val}
    );
    """
    
    cmd = [
        "docker", "exec", "postiz-postgres", "psql", "-U", "postiz-user", "-d", "postiz-db-local",
        "-c", sql
    ]
    subprocess.run(cmd, check=True)
    print(f"Scheduled post {post_id} on integration {integration_id} for {publish_date_iso}")

def main():
    org_id = get_org_id()
    print(f"Postiz Organization ID: {org_id}")
    
    with open(DROPS_FILE, "r") as f:
        data = json.load(f)
        
    for drop in data["drops"]:
        drop_num = drop["drop_number"]
        sched_time = drop["scheduled_time"]
        
        # If Drop 1 was scheduled for 10 PM and time has passed, schedule for NOW (or +1 min)
        if drop_num == 1:
            # Current UTC time formatted for Postgres
            utc_now = time.strftime("%Y-%m-%d %H:%M:%S", time.gmtime(time.time() + 30))
            drop_date = utc_now
        elif drop_num == 2:
            drop_date = "2026-09-03 12:00:00" # 8:00 AM EDT is 12:00 UTC
        elif drop_num == 3:
            drop_date = "2026-09-03 16:00:00" # 12:00 PM EDT is 16:00 UTC
        elif drop_num == 4:
            drop_date = "2026-09-03 18:00:00" # 2:00 PM EDT is 18:00 UTC
        else:
            drop_date = "2026-09-03 12:00:00"
            
        print(f"\n--- Staging Drop {drop_num}: {drop['label']} at {drop_date} UTC ---")
        
        # Queue across connected integrations
        for channel, integ_id in INTEGRATIONS.items():
            copy_text = None
            if channel == "x":
                copy_text = drop["copy"].get("x_post") or drop["copy"].get("x_thread_opener") or drop["copy"].get("all_channels")
            elif channel in ["tiktok", "youtube"]:
                copy_text = drop["copy"].get("tiktok_reels_shorts") or drop["copy"].get("all_channels") or drop["copy"].get("linkedin_facebook") or drop["headline"]
            else:
                copy_text = drop["copy"].get("facebook_instagram") or drop["copy"].get("linkedin_facebook") or drop["copy"].get("all_channels") or drop["headline"]
                
            if not copy_text:
                copy_text = drop["headline"]
                
            schedule_post(org_id, integ_id, copy_text, drop_date)

if __name__ == "__main__":
    main()
