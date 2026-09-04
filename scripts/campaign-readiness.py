#!/usr/bin/env python3
"""Fail-closed readiness audit for starting campaign production."""

import json
import shutil
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
MANIFEST = ROOT / "marketing/campaigns/55-cents-17-day/manifest.json"
ROUTING = ROOT / "marketing/local-models.json"
BRAND = ROOT / "marketing/brands/famtastic/brand.json"
SCHEMA = ROOT / "marketing/engine/schemas/campaign-manifest.schema.json"


def report(ok: bool, message: str) -> bool:
    print(f"{'PASS' if ok else 'FAIL'} {message}")
    return ok


def main() -> int:
    checks = []
    for path in (MANIFEST, ROUTING, BRAND, SCHEMA):
        checks.append(report(path.is_file(), f"required file {path.relative_to(ROOT)}"))
    if not all(checks):
        return 1

    manifest = json.loads(MANIFEST.read_text(encoding="utf-8"))
    records = manifest.get("records", [])
    ids = [record.get("content_id") for record in records]
    checks.append(report(len(records) == 68, "17 days x 4 content moments = 68 records"))
    checks.append(report(len(ids) == len(set(ids)), "content IDs are unique"))
    checks.append(report(manifest.get("record_count") == len(records), "record count matches records"))
    checks.append(report(all(record.get("utm", {}).get("content") == record.get("content_id") for record in records), "UTM content IDs match records"))
    checks.append(report(all(set(record.get("approval", {})) == {"content", "media", "publish"} for record in records), "three approval gates exist on every record"))
    checks.append(report(all(record.get("state") in {"idea", "media_ready"} for record in records), "records advance only through sanctioned pre-publication states"))

    # Owner directive 2026-09-03 opened the record-level approval gates and set
    # public_publish_enabled. Asserting "all gates closed" would now fail the
    # audit forever, so the check inverts: an OPEN manifest must name the single
    # switch that still governs real sends, and that switch must not be armed
    # from inside the repository. A closed manifest is still valid.
    gates_open = manifest.get("public_publish_enabled") is True
    if gates_open:
        arming = manifest.get("publish_arming", {})
        checks.append(report(
            arming.get("mode") == "single_env_switch" and arming.get("env") == "FAMTASTIC_MARKETING_PUBLISH",
            "open manifest declares FAMTASTIC_MARKETING_PUBLISH as its single arming switch",
        ))
        env_file = ROOT / "marketing/.env.example"
        checks.append(report(
            "FAMTASTIC_MARKETING_PUBLISH=false" in env_file.read_text(encoding="utf-8"),
            "arming switch is never defaulted on in committed configuration",
        ))
    else:
        checks.append(report(
            all(not any(record.get("approval", {}).values()) for record in records),
            "closed manifest: unapproved records cannot imply release authorization",
        ))

    required_commands = ("git", "python3", "ffmpeg", "ollama")
    checks.append(report(all(shutil.which(command) for command in required_commands), "local command foundation is installed"))

    try:
        output = subprocess.run(
            ["ollama", "list"], check=True, capture_output=True, text=True, timeout=15
        ).stdout
        installed = {line.split()[0] for line in output.splitlines()[1:] if line.split()}
        expected = {"qwen3:8b", "glm4:9b", "gemma3:4b"}
        checks.append(report(expected <= installed, "Qwen, GLM, and Gemma local lanes are installed"))
    except (OSError, subprocess.SubprocessError):
        checks.append(report(False, "Ollama service and model inventory are reachable"))

    docs = (
        "AGENTS.md",
        "docs/marketing/FAMTASTIC_MARKETING_FLOW_2026-08-12.md",
        "docs/marketing/LOCAL_MODEL_AND_AGENT_ROUTING_2026-08-12.md",
        "docs/architecture/MARKETING_ENGINE_INCUBATION_AND_EXTRACTION.md",
    )
    checks.append(report(all((ROOT / path).is_file() for path in docs), "shared Codex, Claude, and Shay contracts exist"))

    if all(checks):
        print("READY draft production and private-provider tests may begin")
        if gates_open:
            print("ARMED-BY-ENV record approval gates are open; real sends fire only where")
            print("            FAMTASTIC_MARKETING_PUBLISH=true is set on the executing host")
        else:
            print("GATED public posts, promotional sends, social OAuth, paid plans, and ad spend still require Fritz")
        return 0
    print("BLOCKED campaign production readiness checks failed")
    return 1


if __name__ == "__main__":
    sys.exit(main())

