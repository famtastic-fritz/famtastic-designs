#!/usr/bin/env python3
"""Publish an allowlisted new film edition through the existing no-clobber lane."""
from __future__ import annotations
import argparse, importlib.util, json, sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
EDITIONS = {
    "no-catch-v2": "whats-the-catch-v2-20260919",
    "walking-continuation": "walking-continuation-20260919",
    "local-voice-proof": "local-voice-proof-20260919",
}


def publisher_for(edition):
    if edition not in EDITIONS:
        raise ValueError("Unknown film edition; arbitrary public filenames are forbidden")
    spec = importlib.util.spec_from_file_location("_bounded_film_publisher", ROOT / "scripts/publish-no-catch-film.py")
    module = importlib.util.module_from_spec(spec)
    sys.modules[spec.name] = module
    spec.loader.exec_module(module)
    before, after = "whats-the-catch-20260919", EDITIONS[edition]
    module.ASSET_TYPES = {name.replace(before, after): mime for name, mime in module.ASSET_TYPES.items()}
    # Substitute only audited literal basenames, never user text or shell code.
    module.REMOTE_PREFLIGHT = module.REMOTE_PREFLIGHT.replace(before, after)
    module.REMOTE_PROMOTE = module.REMOTE_PROMOTE.replace(before, after)
    return module


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("edition", choices=EDITIONS)
    parser.add_argument("artifact_dir", type=Path)
    parser.add_argument("--apply", action="store_true")
    args = parser.parse_args()
    publisher = publisher_for(args.edition)
    code, receipt = publisher.execute(args.artifact_dir, apply=args.apply,
        receipt_dir=ROOT / "artifacts/video-studio/revision-release-20260919" / args.edition / "publisher-receipts")
    print(json.dumps(receipt, indent=2))
    return code


if __name__ == "__main__": raise SystemExit(main())
