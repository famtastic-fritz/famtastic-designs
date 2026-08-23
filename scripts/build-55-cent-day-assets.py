#!/usr/bin/env python3
"""Re-cut approved 55 Cents seed creative into content_id-named day assets.

SOCIAL_POSTING Step 2 (days 1-3 scope). Deterministic local Pillow build only:
no generative provider, no network, no publishing. Copy strings are reused
verbatim from build-55-cent-social-assets.py - no new claims or prices.
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
ASSET_MAP_PATH = CAMPAIGN_DIR / "asset-map.days-1-3.json"
DAYS = (1, 2, 3)
MOMENTS = ("teach", "challenge", "prove", "invite")
VARIANTS = {
    "9x16": ((1080, 1920), 80),
    "4x5": ((1080, 1350), 68),
}
READY_STATE = "media_ready"

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
DAY_SOURCE = {
    1: ("photoreal-bakery-owner-vertical.png", (0.5, 0.48)),
    2: ("photoreal-barber-owner-vertical.png", (0.5, 0.48)),
    3: ("photoreal-local-owners-wide.png", (0.5, 0.5)),
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
                builder.render(
                    name,
                    source,
                    size,
                    headline,
                    subhead,
                    focus,
                    headline_size,
                )
                path = OUT_DIR / name
                with Image.open(path) as image:
                    actual = image.size
                    if image.format != "PNG":
                        raise SystemExit(f"FAIL {name}: format {image.format} != PNG")
                    if actual != size:
                        raise SystemExit(f"FAIL {name}: dimensions {actual} != {size}")
                    if path.stat().st_size == 0:
                        raise SystemExit(f"FAIL {name}: empty file")
                variants[variant] = f"assets/{name}"
            asset_map[content_id] = variants
    return asset_map


def mark_manifest_ready(asset_map: dict) -> list:
    manifest = json.loads(MANIFEST_PATH.read_text(encoding="utf-8"))
    updated = []
    for record in manifest["records"]:
        if record["content_id"] not in asset_map:
            continue
        if record.get("state") != READY_STATE:
            record["state"] = READY_STATE
            updated.append(record["content_id"])
        for variant, rel_path in asset_map[record["content_id"]].items():
            if variant not in record.get("asset_variants", []):
                raise SystemExit(
                    f"FAIL {record['content_id']}: manifest lacks variant {variant}"
                )
        if any(record["approval"].values()):
            raise SystemExit(f"FAIL {record['content_id']}: approval gate already true")
    if manifest.get("public_publish_enabled") is not False:
        raise SystemExit("FAIL public_publish_enabled is not false")
    MANIFEST_PATH.write_text(json.dumps(manifest, indent=2) + "\n", encoding="utf-8")
    return updated


def write_asset_map(asset_map: dict) -> None:
    payload = {
        "schema": "famtastic.asset-map.v1",
        "generated_on": date.today().isoformat(),
        "campaign": "web_basics_55_cents_17d",
        "scope": "days 1-3",
        "built_by": "scripts/build-55-cent-day-assets.py",
        "note": (
            "Deterministic local re-cut of approved seed creative under each "
            "content_id (CEO decision). No generative provider, no publishing. "
            "Copy reused verbatim from build-55-cent-social-assets.py."
        ),
        "assets": asset_map,
    }
    ASSET_MAP_PATH.write_text(json.dumps(payload, indent=2) + "\n", encoding="utf-8")


def main() -> None:
    asset_map = render_all()
    updated = mark_manifest_ready(asset_map)
    write_asset_map(asset_map)
    total = sum(len(variants) for variants in asset_map.values())
    print(f"Built {total} verified PNG variants for {len(asset_map)} content_ids in {OUT_DIR}")
    print(f"Manifest states set to {READY_STATE}: {len(updated)} of {len(asset_map)}")
    print(f"Wrote sidecar asset map to {ASSET_MAP_PATH}")


if __name__ == "__main__":
    main()
