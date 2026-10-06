#!/usr/bin/env python3
"""Freeze the nine owner-approved templates and their recorded dependencies."""
import argparse
import hashlib
import json
import os
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
APPROVAL = "docs/research/acquisition-199/TEMPLATE-APPROVAL-20261006.json"
BASE = "marketing/campaigns/acquisition-199/industry-previews/"


def digest(data):
    return hashlib.sha256(data).hexdigest()


def build(destination):
    approval = json.loads((ROOT / APPROVAL).read_text())
    assert approval["schema"] == "famtastic.acquisition-industry-creative-approval.v1"
    assert len(approval["templates"]) == 9
    inputs = {APPROVAL: digest((ROOT / APPROVAL).read_bytes())}
    creative_count = 0
    for template in approval["templates"]:
        assert template["approved"] is True
        slug = template["industry"]
        assert slug and all(c in "abcdefghijklmnopqrstuvwxyz-" for c in slug)
        for name, expected in template["source_hashes"].items():
            assert name in {"email.html", "email.txt", "email-images-blocked.html", "lab.html", "lab.css", "lab.js"}
            inputs[BASE + slug + "/" + name] = expected
            creative_count += 1
        receipt_path = BASE + slug + "/receipt.json"
        receipt = json.loads((ROOT / receipt_path).read_text())
        inputs[receipt_path] = digest((ROOT / receipt_path).read_bytes())
        for asset in receipt.get("source_assets", []):
            path = asset["path"]
            if not path.startswith("marketing/campaigns/acquisition-199/"):
                continue  # Research references are not runtime dependencies.
            assert path.startswith("marketing/campaigns/acquisition-199/")
            assert Path(path).suffix in {".png", ".svg", ".woff2"}
            assert inputs.get(path, asset["sha256"]) == asset["sha256"]
            inputs[path] = asset["sha256"]
        # Historical worker receipts differ in shape. Freeze all local artwork
        # and common approved assets against the reviewed creative commit.
        dependencies = list((ROOT / (BASE + slug)).glob("*.svg"))
        dependencies += list((ROOT / (BASE + slug + "/assets")).glob("*"))
        for shared in ["assets", "generic-review/assets", "industry-previews/assets", "industry-previews/generic-review/assets"]:
            dependencies += list((ROOT / ("marketing/campaigns/acquisition-199/" + shared)).glob("*"))
        for dependency in dependencies:
            if dependency.suffix not in {".svg", ".png", ".woff2"}:
                continue
            if dependency.suffix == ".png" and dependency.name not in {"famtastic-designs-logo-v1.png", "connect-qr.png"}:
                continue
            relative = str(dependency.relative_to(ROOT))
            committed = subprocess.check_output(["git", "show", approval["creative_source_commit"] + ":" + relative], cwd=ROOT)
            expected = digest(committed)
            assert inputs.get(relative, expected) == expected
            inputs[relative] = expected
    assert creative_count == 54
    # Validate every source byte before creating a new package.
    frozen = {}
    for relative, expected in sorted(inputs.items()):
        path = ROOT / relative
        assert not path.is_symlink() and path.resolve().is_relative_to(ROOT)
        data = path.read_bytes()
        assert digest(data) == expected, "Approved source changed: " + relative
        frozen[relative] = data
    destination.mkdir(mode=0o700, parents=True, exist_ok=False)
    for relative, data in frozen.items():
        path = destination / relative
        path.parent.mkdir(mode=0o700, parents=True, exist_ok=True)
        path.write_bytes(data)
        os.chmod(path, 0o600)
    manifest = {
        "schema": "famtastic.acquisition-industry-bundle.v1",
        "creative_source_commit": approval["creative_source_commit"],
        "approval_sha256": inputs[APPROVAL],
        "files": dict(sorted(inputs.items())),
    }
    data = (json.dumps(manifest, indent=2) + "\n").encode()
    (destination / "manifest.json").write_bytes(data)
    os.chmod(destination / "manifest.json", 0o600)
    print(json.dumps({"files": len(inputs), "creative_hashes_verified": creative_count,
                      "manifest_sha256": digest(data), "recipient_data": False}))


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("destination", type=Path, help="New private package directory; must not already exist")
    build(parser.parse_args().destination.resolve())
