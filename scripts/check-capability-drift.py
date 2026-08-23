#!/usr/bin/env python3
"""Fail closed when capability-changing code lacks truth-surface updates."""
from __future__ import annotations
import argparse, json, pathlib, shutil, subprocess, sys

ROOT = pathlib.Path(__file__).resolve().parent.parent
REQUIRED = [
    "docs/CAPABILITY_REGISTRY.md",
    "docs/CHANGELOG.md",
    "docs/SITE_LEARNINGS.md",
    "docs/architecture/AUTONOMOUS_PREVIEW_TO_SITE_STUDIO_MASTER_PLAN_2026-08-18.md",
    "docs/architecture/GANDALF_FAMTASTIC_SITE_STUDIO_BRIDGE.md",
]
CAPABILITY_PREFIXES = ("website-delivery-swarm/", "backend/web/modules/custom/famtastic_pipeline/", "frontend/src/", "agent-skills/")

def git_paths(staged):
    command = ["git", "diff", "--name-only"] + (["--cached"] if staged else [])
    return [line for line in subprocess.check_output(command, cwd=ROOT, text=True).splitlines() if line]

def availability():
    commands = ["node", "python3", "ollama", "codex", "claude", "gemini", "kimi"]
    return {name: {"installed": shutil.which(name) is not None, "path": shutil.which(name)} for name in commands}

def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--staged", action="store_true")
    parser.add_argument("--snapshot")
    args = parser.parse_args()
    missing = [name for name in REQUIRED if not (ROOT / name).is_file()]
    if missing:
        print("FAIL: missing capability truth surfaces: " + ", ".join(missing), file=sys.stderr)
        return 2
    changed = git_paths(args.staged)
    capability_changed = any(path.startswith(CAPABILITY_PREFIXES) for path in changed)
    truth_changed = all(path in changed for path in REQUIRED[:3])
    status = "pass"
    messages = []
    if capability_changed and not truth_changed:
        status = "fail"
        messages.append("Capability code changed without staged capability registry, changelog, and site-learning updates.")
    snapshot = {"schema": "famtastic.capability-drift-check.v1", "status": status, "staged": args.staged, "changed_paths": changed, "availability": availability(), "messages": messages}
    if args.snapshot:
        path = pathlib.Path(args.snapshot).resolve(); path.parent.mkdir(parents=True, exist_ok=True); path.write_text(json.dumps(snapshot, indent=2) + "\n")
    print(json.dumps(snapshot, indent=2))
    return 0 if status == "pass" else 2

if __name__ == "__main__":
    raise SystemExit(main())
