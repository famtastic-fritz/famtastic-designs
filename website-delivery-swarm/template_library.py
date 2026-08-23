#!/usr/bin/env python3
"""Build a deduplicated catalog of every retained six-direction proof."""
from __future__ import annotations
import argparse, datetime as dt, hashlib, json, pathlib

def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()

def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--artifacts", required=True)
    parser.add_argument("--output", required=True)
    args = parser.parse_args()
    root = pathlib.Path(args.artifacts).resolve()
    rows = []
    seen = set()
    for manifest_path in sorted(root.glob("*/manifest.json")):
        manifest = json.loads(manifest_path.read_text())
        if len(manifest.get("directions", [])) != 6:
            continue
        run = manifest_path.parent
        for direction in manifest["directions"]:
            html_path = run / direction.get("entry", "")
            if not html_path.is_file():
                continue
            html_sha = sha(html_path)
            if html_sha in seen:
                continue
            seen.add(html_sha)
            rows.append({
                "template_id": f"template-{html_sha[:16]}",
                "source_request_id": manifest.get("request_id"),
                "source_run": run.name,
                "source_direction_id": direction.get("id"),
                "name": direction.get("name"),
                "mode": direction.get("mode"),
                "famtastic_level": direction.get("famtastic_level"),
                "strategy": direction.get("strategy"),
                "information_architecture": direction.get("information_architecture"),
                "html_path": str(html_path),
                "html_sha256": html_sha,
                "hero_sha256": direction.get("hero_sha256"),
                "reuse_status": "retained_candidate",
                "customer_media_reuse_allowed": False,
                "customer_copy_reuse_allowed": False,
                "structure_and_design_learning_allowed": True
            })
    output = pathlib.Path(args.output).resolve()
    output.parent.mkdir(parents=True, exist_ok=True)
    payload = {"schema": "famtastic.template-library.v1", "generated_at": dt.datetime.now(dt.timezone.utc).isoformat(), "template_count": len(rows), "templates": rows}
    output.write_text(json.dumps(payload, indent=2) + "\n")
    print(f"PASS: retained {len(rows)} unique proof directions in {output}")
    return 0 if rows else 2

if __name__ == "__main__":
    raise SystemExit(main())
