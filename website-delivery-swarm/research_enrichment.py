"""Safe optional research-enrichment handoff for Site Studio build packets.

The FAMtastic packet is an immutable business/build boundary, not a dump of a
provider conversation. This module accepts an explicitly named research-
execution receipt, copies a deliberately small provenance projection into
packet-files, and returns the additive packet field. No opt-in means no field.
"""
from __future__ import annotations

import hashlib
import json
import math
import pathlib
import re
from typing import Any


RECEIPT_SCHEMA = "famtastic.research-execution.v1"
RECEIPT_PATH = "packet-files/research-execution.json"
SHA256 = re.compile(r"^[a-f0-9]{64}$")
SAFE_IDENTIFIER = re.compile(r"^[A-Za-z0-9][A-Za-z0-9._:/-]{0,160}$")
SECRET_SHAPED_IDENTIFIER = re.compile(r"^(?:sk-[A-Za-z0-9_-]{16,}|AIza[A-Za-z0-9_-]{20,}|eyJ[A-Za-z0-9_-]+(?:\.[A-Za-z0-9_-]+){2,})$")


class ResearchEnrichmentError(ValueError):
    """A named execution receipt cannot safely cross the packet boundary."""


def _load(path: pathlib.Path) -> dict[str, Any]:
    try:
        value = json.loads(path.read_text())
    except (OSError, json.JSONDecodeError) as error:
        raise ResearchEnrichmentError(f"Cannot read research execution receipt: {path}") from error
    if not isinstance(value, dict):
        raise ResearchEnrichmentError(f"Research execution receipt must be a JSON object: {path}")
    return value


def _sha256_file(path: pathlib.Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def _required_identifier(value: Any, field: str) -> str:
    if not isinstance(value, str) or not SAFE_IDENTIFIER.fullmatch(value) or SECRET_SHAPED_IDENTIFIER.fullmatch(value):
        raise ResearchEnrichmentError(f"Research execution {field} must be a safe identifier")
    return value


def _optional_identifier(value: Any) -> str | None:
    if not isinstance(value, str) or not SAFE_IDENTIFIER.fullmatch(value) or SECRET_SHAPED_IDENTIFIER.fullmatch(value):
        return None
    return value


def _optional_sha256(value: Any, field: str) -> str | None:
    if value is None:
        return None
    if not isinstance(value, str) or not SHA256.fullmatch(value):
        raise ResearchEnrichmentError(f"Research execution {field} must be a lowercase SHA-256 or null")
    return value


def _optional_nonnegative_number(value: Any, field: str) -> int | float | None:
    if value is None:
        return None
    if isinstance(value, bool) or not isinstance(value, (int, float)) or not math.isfinite(value) or value < 0:
        raise ResearchEnrichmentError(f"Research execution {field} must be a non-negative number or null")
    return value


def _optional_nonnegative_int(value: Any, field: str) -> int | None:
    if value is None:
        return None
    if isinstance(value, bool) or not isinstance(value, int) or value < 0:
        raise ResearchEnrichmentError(f"Research execution {field} must be a non-negative integer or null")
    return value


def _safe_fields(value: Any, fields: tuple[str, ...]) -> dict[str, str]:
    if not isinstance(value, dict):
        return {}
    safe = {}
    for field in fields:
        candidate = _optional_identifier(value.get(field))
        if candidate is not None:
            safe[field] = candidate
    return safe


def _compact_requested(value: Any) -> dict[str, str]:
    return _safe_fields(value, ("model", "thinking", "permission_mode"))


def _compact_tools(value: Any) -> dict[str, Any]:
    if not isinstance(value, dict):
        return {}
    compact = {}
    calls = _optional_nonnegative_int(value.get("calls"), "tools.calls")
    if calls is not None:
        compact["calls"] = calls
    permission_requests = _optional_nonnegative_int(value.get("permission_requests"), "tools.permission_requests")
    if permission_requests is not None:
        compact["permission_requests"] = permission_requests
    kinds = value.get("kinds")
    if isinstance(kinds, list):
        safe_kinds = [_required_identifier(item, "tools.kinds item") for item in kinds]
        compact["kinds"] = safe_kinds
    return compact


def _compact_usage(value: Any) -> dict[str, Any]:
    if not isinstance(value, dict):
        return {}
    compact = {}
    for field in ("input_tokens", "output_tokens"):
        amount = _optional_nonnegative_number(value.get(field), f"usage.{field}")
        if amount is not None:
            compact[field] = amount
    status = _optional_identifier(value.get("status"))
    if status is not None:
        compact["status"] = status
    return compact


def _compact_cost(value: Any) -> dict[str, Any]:
    if not isinstance(value, dict):
        return {}
    compact = {}
    amount = _optional_nonnegative_number(value.get("amount_usd"), "cost_estimate.amount_usd")
    if amount is not None:
        compact["amount_usd"] = amount
    currency = _optional_identifier(value.get("currency"))
    if currency is not None:
        compact["currency"] = currency
    status = _optional_identifier(value.get("status"))
    if status is not None:
        compact["status"] = status
    return compact


def _compact_fallback(value: Any) -> dict[str, Any] | None:
    if not isinstance(value, dict):
        return None
    compact = _safe_fields(value, ("adapter", "reason", "actual_agent", "actual_model", "execution_status"))
    primary = value.get("primary_attempt")
    if isinstance(primary, dict):
        primary_compact = _safe_fields(
            primary,
            ("adapter", "provider", "agent", "model", "status", "code", "prompt_template"),
        )
        for field in ("prompt_sha256", "output_sha256"):
            digest = _optional_sha256(primary.get(field), f"fallback.primary_attempt.{field}")
            if digest is not None:
                primary_compact[field] = digest
        duration = _optional_nonnegative_number(primary.get("duration_ms"), "fallback.primary_attempt.duration_ms")
        if duration is not None:
            primary_compact["duration_ms"] = duration
        if primary_compact:
            compact["primary_attempt"] = primary_compact
    return compact or None


def compact_execution_receipt(receipt: dict[str, Any]) -> dict[str, Any]:
    """Return the provenance-only receipt that is allowed into packet-files.

    Deliberately absent: raw prompts/transcripts, broad response objects,
    customer claims, contact values, credentials, session ids, and arbitrary
    provider extensions.  Unsupported values fail closed instead of becoming
    a leaky "metadata" escape hatch.
    """
    if receipt.get("schema") != RECEIPT_SCHEMA:
        raise ResearchEnrichmentError("Unsupported research execution receipt schema")
    compact: dict[str, Any] = {
        "schema": RECEIPT_SCHEMA,
        "adapter": _required_identifier(receipt.get("adapter"), "adapter"),
        "provider": _required_identifier(receipt.get("provider"), "provider"),
        "status": _required_identifier(receipt.get("status"), "status"),
    }
    for field in ("agent", "model", "execution_class", "protocol", "cli_version", "code", "prompt_template", "stop_reason"):
        candidate = _optional_identifier(receipt.get(field))
        if candidate is not None:
            compact[field] = candidate
    for field in ("prompt_sha256", "output_sha256"):
        digest = _optional_sha256(receipt.get(field), field)
        if digest is not None:
            compact[field] = digest
    duration = _optional_nonnegative_number(receipt.get("duration_ms"), "duration_ms")
    if duration is not None:
        compact["duration_ms"] = duration
    for field in ("redacted_brief_fields", "redacted_provider_output_values", "redacted_persisted_values"):
        count = _optional_nonnegative_int(receipt.get(field), field)
        if count is not None:
            compact[field] = count
    for field, compactor in (("requested", _compact_requested), ("tools", _compact_tools), ("usage", _compact_usage), ("cost_estimate", _compact_cost)):
        value = compactor(receipt.get(field))
        if value:
            compact[field] = value
    fallback = _compact_fallback(receipt.get("fallback"))
    if fallback is not None:
        compact["fallback"] = fallback
    return compact


def _validate_object_keys(value: Any, required: set[str], allowed: set[str], field: str) -> dict[str, Any]:
    if not isinstance(value, dict):
        raise ResearchEnrichmentError(f"Research enrichment {field} must be an object")
    missing = required - set(value)
    extra = set(value) - allowed
    if missing:
        raise ResearchEnrichmentError(f"Research enrichment {field} is missing: {', '.join(sorted(missing))}")
    if extra:
        raise ResearchEnrichmentError(f"Research enrichment {field} contains unsupported fields: {', '.join(sorted(extra))}")
    return value


def validate_research_enrichment(value: dict[str, Any], packet_files: pathlib.Path) -> None:
    """Validate the exact optional projection before it enters a signed packet."""
    root = _validate_object_keys(value, {"requested", "actual", "receipt"}, {"requested", "actual", "receipt"}, "root")
    requested = _validate_object_keys(
        root["requested"],
        {"status", "adapter"},
        {"status", "adapter", "model", "thinking", "permission_mode"},
        "requested",
    )
    if requested["status"] != "optional":
        raise ResearchEnrichmentError("Research enrichment requested.status must be optional")
    for field in requested:
        if field != "status":
            _required_identifier(requested[field], f"requested.{field}")
    actual = _validate_object_keys(
        root["actual"],
        {"status", "provider", "adapter"},
        {"status", "provider", "adapter", "agent", "model", "execution_class", "research_status"},
        "actual",
    )
    for field, candidate in actual.items():
        _required_identifier(candidate, f"actual.{field}")
    receipt = _validate_object_keys(root["receipt"], {"schema", "path", "sha256"}, {"schema", "path", "sha256"}, "receipt")
    if receipt["schema"] != RECEIPT_SCHEMA or receipt["path"] != RECEIPT_PATH:
        raise ResearchEnrichmentError("Research enrichment receipt must point to the canonical redacted execution receipt")
    if not isinstance(receipt["sha256"], str) or not SHA256.fullmatch(receipt["sha256"]):
        raise ResearchEnrichmentError("Research enrichment receipt.sha256 must be a lowercase SHA-256")
    stored_path = packet_files / "research-execution.json"
    if not stored_path.is_file() or _sha256_file(stored_path) != receipt["sha256"]:
        raise ResearchEnrichmentError("Research enrichment receipt checksum does not match packet-files")
    stored = _load(stored_path)
    if compact_execution_receipt(stored) != stored:
        raise ResearchEnrichmentError("Research enrichment receipt contains data outside the safe provenance projection")
    for field in ("status", "provider", "adapter"):
        if actual[field] != stored[field]:
            raise ResearchEnrichmentError(f"Research enrichment actual.{field} does not match the receipt")


def _extract_receipt(value: dict[str, Any]) -> tuple[dict[str, Any], dict[str, Any]] | None:
    if value.get("schema") == RECEIPT_SCHEMA:
        return value, {}
    execution = value.get("execution")
    if isinstance(execution, dict) and execution.get("schema") == RECEIPT_SCHEMA:
        return execution, {
            "source_adapter": value.get("source_adapter"),
            "research_status": value.get("execution_status"),
        }
    return None


def _find_receipt(explicit_path: str | None) -> tuple[pathlib.Path, dict[str, Any], dict[str, Any]] | None:
    # A receipt existing beside an artifact is not permission to impose Kimi
    # (or any research route) on a build. The caller explicitly supplies this
    # optional feature when it wants Site Studio to receive frozen context.
    if not explicit_path:
        return None
    path = pathlib.Path(explicit_path).expanduser().resolve()
    if not path.is_file():
        raise ResearchEnrichmentError(f"Research execution receipt does not exist: {path}")
    extracted = _extract_receipt(_load(path))
    if extracted is None:
        raise ResearchEnrichmentError(f"Named research execution receipt does not contain {RECEIPT_SCHEMA}: {path}")
    return path, *extracted


def prepare_research_enrichment(
    packet_files: pathlib.Path,
    explicit_path: str | None = None,
) -> dict[str, Any] | None:
    """Copy a safe receipt projection and return the additive packet field.

    This is intentionally a non-gating feature: callers receive ``None`` when
    they do not opt in with a receipt, and therefore emit no
    ``research_enrichment`` field.
    """
    found = _find_receipt(explicit_path)
    if found is None:
        return None
    _, receipt, source = found
    compact = compact_execution_receipt(receipt)
    destination = packet_files / "research-execution.json"
    destination.write_text(json.dumps(compact, indent=2, ensure_ascii=False) + "\n")

    requested = {"status": "optional"}
    source_adapter = _optional_identifier(source.get("source_adapter"))
    requested["adapter"] = source_adapter or compact["adapter"]
    for field, value in compact.get("requested", {}).items():
        requested[field] = value
    actual = {
        "status": compact["status"],
        "provider": compact["provider"],
        "adapter": compact["adapter"],
    }
    for field in ("agent", "model", "execution_class"):
        if field in compact:
            actual[field] = compact[field]
    research_status = _optional_identifier(source.get("research_status"))
    if research_status is not None:
        actual["research_status"] = research_status
    return {
        "requested": requested,
        "actual": actual,
        "receipt": {
            "schema": RECEIPT_SCHEMA,
            "path": RECEIPT_PATH,
            "sha256": _sha256_file(destination),
        },
    }
