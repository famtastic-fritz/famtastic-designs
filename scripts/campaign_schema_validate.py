#!/usr/bin/env python3
"""Shared JSON Schema validation for posting-schedule.json manifests.

Both scripts/new-campaign.py (--validate) and scripts/queue-campaign-drops.py
import validate_manifest() from here instead of trusting json.loads() alone.

Prefers the `jsonschema` package when it is installed (checked at import
time). When it is not installed, falls back to a hand-rolled structural
validator.

LIMITATION — the hand-rolled fallback is NOT a full JSON Schema
implementation. It checks:
  - required top-level keys (including program_id / series_id)
  - per-drop required keys, drop_id/content_id patterns, scheduled_time's
    UTC-offset pattern, and that channels/copy are non-empty
  - the basic types called out above (string / non-empty array / non-empty
    object / null-or-string for series_id)
It does NOT evaluate $ref, oneOf/anyOf, additionalProperties sub-schemas, or
any pattern in the schema file beyond the handful hardcoded below. If the
schema file changes shape in a way this fallback does not know about, it can
pass a manifest that a real JSON Schema validator would reject. Install
`jsonschema` (pip install jsonschema) for full, schema-driven coverage.
"""

from __future__ import annotations

import json
import pathlib
import re

REPO_ROOT = pathlib.Path(__file__).resolve().parent.parent
SCHEMA_PATH = REPO_ROOT / "marketing/engine/schemas/posting-schedule.schema.json"

try:
    import jsonschema  # type: ignore

    HAVE_JSONSCHEMA = True
except ImportError:
    HAVE_JSONSCHEMA = False


def load_schema() -> dict:
    return json.loads(SCHEMA_PATH.read_text())


def validate_manifest(manifest: dict, schema: dict | None = None) -> list[str]:
    """Return a list of human-readable validation errors; empty means valid."""
    schema = schema if schema is not None else load_schema()
    if HAVE_JSONSCHEMA:
        return _validate_with_jsonschema(manifest, schema)
    return _validate_by_hand(manifest, schema)


def _validate_with_jsonschema(manifest: dict, schema: dict) -> list[str]:
    validator_cls = jsonschema.validators.validator_for(schema)
    validator_cls.check_schema(schema)
    validator = validator_cls(schema)
    errors = sorted(validator.iter_errors(manifest), key=lambda e: list(e.path))
    out = []
    for e in errors:
        where = "/".join(str(p) for p in e.path) or "<root>"
        out.append(f"{where}: {e.message}")
    return out


_ISO_OFFSET = re.compile(r"^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:[+-]\d{2}:\d{2}|Z)$")
_DROP_ID = re.compile(r"^[a-z0-9-]+$")
_CONTENT_ID = re.compile(r"^[a-zA-Z0-9_-]+$")


def _validate_by_hand(manifest: dict, schema: dict) -> list[str]:
    errors: list[str] = []
    if not isinstance(manifest, dict):
        return ["manifest root must be a JSON object"]

    for key in schema.get("required", []):
        if key not in manifest:
            errors.append(f"missing required top-level key: {key}")

    if "schema_version" in manifest and manifest["schema_version"] != 2:
        errors.append("schema_version must be 2")

    allowed_statuses = {"draft", "ready_for_evaluation", "armed_for_scheduling", "completed", "archived"}
    if "status" in manifest and manifest["status"] not in allowed_statuses:
        errors.append("status must be one of: " + ", ".join(sorted(allowed_statuses)))

    for key in ("campaign_id", "program_id", "time_zone"):
        if key in manifest and not isinstance(manifest[key], str):
            errors.append(f"{key} must be a string")

    if "series_id" in manifest:
        value = manifest["series_id"]
        if value is not None and not isinstance(value, str):
            errors.append("series_id must be a string or null")

    drops = manifest.get("drops")
    if not isinstance(drops, list) or not drops:
        errors.append("drops must be a non-empty array")
        drops = []

    drop_schema = schema.get("properties", {}).get("drops", {}).get("items", {})
    drop_required = drop_schema.get("required") or [
        "drop_id", "content_id", "scheduled_time", "channels", "copy",
    ]

    for i, drop in enumerate(drops):
        if not isinstance(drop, dict):
            errors.append(f"drops[{i}]: must be an object")
            continue
        label = f"drops[{i}] ({drop.get('drop_id', '?')})"
        for key in drop_required:
            if key not in drop:
                errors.append(f"{label}: missing required key: {key}")
        if "drop_id" in drop and not _DROP_ID.match(str(drop["drop_id"])):
            errors.append(f"{label}: drop_id must match ^[a-z0-9-]+$")
        if "content_id" in drop and not _CONTENT_ID.match(str(drop["content_id"])):
            errors.append(f"{label}: content_id must match ^[a-zA-Z0-9_-]+$")
        if "scheduled_time" in drop and not _ISO_OFFSET.match(str(drop["scheduled_time"])):
            errors.append(f"{label}: scheduled_time needs an explicit UTC offset, e.g. 2026-09-03T23:50:00-04:00")
        if "channels" in drop and (not isinstance(drop["channels"], list) or not drop["channels"]):
            errors.append(f"{label}: channels must be a non-empty array")
        if "copy" in drop and (not isinstance(drop["copy"], dict) or not drop["copy"]):
            errors.append(f"{label}: copy must be a non-empty object")
        reconciliation = drop.get("provider_reconciliation")
        if reconciliation is not None:
            if not isinstance(reconciliation, dict):
                errors.append(f"{label}: provider_reconciliation must be an object")
            else:
                for key in ("status", "provider_recorded_time", "source_schedule_changed_at", "reason"):
                    if not isinstance(reconciliation.get(key), str) or not reconciliation[key]:
                        errors.append(f"{label}: provider_reconciliation.{key} must be a non-empty string")
                if reconciliation.get("status") not in {"draft_retime_required", "reconciled"}:
                    errors.append(f"{label}: provider_reconciliation.status must be draft_retime_required or reconciled")

    return errors


def validate_file(path: pathlib.Path) -> list[str]:
    try:
        manifest = json.loads(path.read_text())
    except (json.JSONDecodeError, OSError) as exc:
        return [f"could not read/parse {path}: {exc}"]
    return validate_manifest(manifest)


if __name__ == "__main__":
    import sys

    if len(sys.argv) != 2:
        print("usage: campaign_schema_validate.py <path-to-posting-schedule.json>")
        raise SystemExit(64)
    errs = validate_file(pathlib.Path(sys.argv[1]))
    mode = "jsonschema" if HAVE_JSONSCHEMA else "hand-rolled fallback"
    if errs:
        print(f"INVALID ({mode}): {len(errs)} problem(s)")
        for e in errs:
            print(f"  - {e}")
        raise SystemExit(1)
    print(f"VALID ({mode})")
