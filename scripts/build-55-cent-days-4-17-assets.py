#!/usr/bin/env python3
"""Re-cut approved 55 Cents seed creative into days 4-17 content_id assets.

SOCIAL_POSTING Step 2 completion (days 4-17 scope). Same doctrine as
build-55-cent-day-assets.py: deterministic local Pillow build only — no
generative provider, no network, no publishing. Copy strings reused verbatim;
sources cycle through the three approved photoreal originals so every record
gets a distinct day/moment pairing without new creative claims.
"""

import importlib.util
import json
from datetime import date
from pathlib import Path

from PIL import Image

ROOT = Path(__file__).resolve().parents[1]
CAMPAIGN_DIR = ROOT / "marketing/campaigns/55-cents-17-day"
OUT_DIR = CAMPAIGN_DIR / "assets"
MANIFEST_PATH = CAMPAIGN_DIR / "manifest.json"
ASSET_MAP_PATH = CAMPAIGN_DIR / "asset-map.days-4-17.json"
DAYS = tuple(range(4, 18))
MOMENTS = ("teach", "challenge", "prove", "invite")
VARIANTS = {
    "9x16": ((1080, 1920), 80),
    "4x5": ((1080, 1350), 68),
}
READY_STATE = "media_ready"

# Cycle the approved sources; wide source suits landscape-ish moments.
_SOURCE_CYCLE = (
    ("photoreal-local-owners-wide.png", (0.5, 0.5)),
    ("photoreal-bakery-owner-vertical.png", (0.5, 0.48)),
    ("photoreal-barber-owner-vertical.png", (0.5, 0.48)),
)
DAY_SOURCE = {day: _SOURCE_CYCLE[(day - 4) % len(_SOURCE_CYCLE)] for day in DAYS}

_spec = importlib.util.spec_from_file_location(
    "build_55_cent_social_assets", ROOT / "scripts/build-55-cent-social-assets.py"
)
builder = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(builder)

MOMENT_COPY = {
    "teach": (
        "YOUR BUSINESS CAN HAVE A WEBSITE FOR ABOUT 55¢ A DAY.",
        "The $199 price is paid once. 55¢ is the annualized comparison.",
    ),
    "challenge": (
        "TOO EXPENSIVE. I DON'T NEED ONE. BUSINESS IS FINE.",
        "The concerns are understandable. The cost barrier is removable.",
    ),
    "prove": (
        "$199 ÷ 365 = ABOUT 55¢ A DAY.",
        "One-time purchase. First-year basic hosting included. Eligible new-domain registration or existing-domain connection included.",
    ),
    "invite": (
        "COST IS NOT ONE OF THEM. PERIOD.",
        "Start Web Basics—or take the assessment if your business needs more.",
    ),
}


def render_all() -> dict:
    builder.OUT = OUT_DIR
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    asset_map = {}
    for day in DAYS:
        source, focus = DAY_SOURCE[day]
        for moment in MOMENTS:
            content_id = f"55c-d{day:02d}-{moment}"
            headline, subhead = MOMENT_COPY[moment]
            variants = {}
            for variant, (size, headline_size) in VARIANTS.items():
                name = f"{content_id}.{variant}.png"
                builder.render(name, source, size, headline, subhead, focus, headline_size)
                path = OUT_DIR / name
                with Image.open(path) as check:
                    if check.format != "PNG" or check.size != size:
                        raise RuntimeError(f"FAIL {name}: format {check.format} != PNG or size {check.size} != {size}")
                variants[variant] = f"assets/{name}"
            asset_map[content_id] = variants

    ASSET_MAP_PATH.write_text(json.dumps({
        "schema": "famtastic.asset-map.v1",
        "generated_on": date.today().isoformat(),
        "campaign": "web_basics_55_cents_17d",
        "scope": "days 4-17",
        "built_by": "scripts/build-55-cent-days-4-17-assets.py",
        "note": "Deterministic local re-cut of approved seed creative under each content_id (same doctrine as days 1-3). No generative provider, no publishing. Copy reused verbatim; sources cycle the three approved photoreal originals.",
        "assets": asset_map,
    }, indent=1) + "\n")

    manifest = json.loads(MANIFEST_PATH.read_text())
    updated = 0
    for record in manifest["records"]:
        cid = record["content_id"]
        if cid in asset_map and record["state"] != READY_STATE:
            record["state"] = READY_STATE
            record["asset_variants"] = ["9x16", "4x5"]
            record.setdefault("evidence", []).append({
                "kind": "local_recut_assets",
                "built_by": "scripts/build-55-cent-days-4-17-assets.py",
                "assets": asset_map[cid],
            })
            updated += 1
    MANIFEST_PATH.write_text(json.dumps(manifest, indent=1) + "\n")
    return {"records_updated": updated, "assets": len(asset_map) * len(VARIANTS)}


if __name__ == "__main__":
    result = render_all()
    print(f"OK — records updated: {result['records_updated']}, assets written: {result['assets']}")
