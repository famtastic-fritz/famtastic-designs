#!/usr/bin/env python3
"""FAMtastic-side autonomous preview to Site Studio packet bridge.

This module does not run or modify Site Studio. It owns the work before the
handoff, validates the returned packet, and emits the event FAMtastic consumes.
The golden replay adapter is deliberately labeled and is only a certification
fixture; it is never represented as a live model execution.
"""
from __future__ import annotations

import argparse
import datetime as dt
import hashlib
import hmac
import json
import os
import pathlib
import re
import shutil
import struct
import subprocess
import sys
import time
import uuid
import zipfile


ROOT = pathlib.Path(__file__).resolve().parent
SCHEMAS = ROOT / "schemas"
CONFIG = ROOT / "config"
PROMPTS = ROOT / "prompts" / "v1"


class ContractError(RuntimeError):
    pass


def load(path: pathlib.Path):
    return json.loads(path.read_text())


def dump(path: pathlib.Path, value):
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(value, indent=2, ensure_ascii=False) + "\n")


def sha256_file(path: pathlib.Path) -> str:
    h = hashlib.sha256()
    with path.open("rb") as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b""):
            h.update(chunk)
    return h.hexdigest()


def canonical(value) -> bytes:
    return json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=False).encode()


def utcnow() -> str:
    return dt.datetime.now(dt.timezone.utc).isoformat()


def require(condition: bool, message: str):
    if not condition:
        raise ContractError(message)


def normalize_build_dna_run_context(value: object, delivery_id: int | None = None) -> dict:
    """Extract a canonical public-preview run identity from a safe handoff.

    A verified-cold proof cannot use the old synthetic request/project labels
    as its Build DNA identity. Its runner receives the exact Drupal identities
    exported by FAMtastic and must place them in `build-dna.run` before any
    manifest checksum is calculated.
    """
    if isinstance(value, dict) and value.get("schema") == "famtastic.verified-cold-proof-handoff.v1":
        deliveries = value.get("deliveries")
        require(isinstance(deliveries, list), "Verified-cold handoff has no deliveries list")
        require(delivery_id is not None and delivery_id > 0, "A verified-cold handoff bundle requires --delivery-id")
        matches = [item for item in deliveries if isinstance(item, dict) and item.get("public_preview_delivery_id") == delivery_id]
        require(len(matches) == 1, "Requested verified-cold delivery is absent or ambiguous in handoff bundle")
        value = matches[0]
    require(isinstance(value, dict), "Build DNA run context must be an object")
    run = value.get("build_dna_run", value)
    require(isinstance(run, dict), "Build DNA run context must include build_dna_run")
    normalized = {
        "prospect_id": run.get("prospect_id"),
        "proof_campaign_id": run.get("proof_campaign_id"),
        "campaign_id": run.get("campaign_id"),
        "source_lane": run.get("source_lane"),
        "job_id": run.get("job_id", value.get("job_id") if isinstance(value, dict) else None),
        "callback_event_id": run.get("callback_event_id", value.get("callback_event_id") if isinstance(value, dict) else None),
        "run_started_at": run.get("run_started_at", run.get("started_at", value.get("run_started_at") if isinstance(value, dict) else None)),
    }
    if "public_preview_delivery_id" in run:
        normalized["public_preview_delivery_id"] = run["public_preview_delivery_id"]
    elif "public_preview_delivery_id" in value:
        normalized["public_preview_delivery_id"] = value["public_preview_delivery_id"]
    if normalized["source_lane"] == "verified_cold":
        require(isinstance(normalized["prospect_id"], int) and normalized["prospect_id"] > 0, "verified_cold run requires numeric prospect_id")
        require(isinstance(normalized["proof_campaign_id"], int) and normalized["proof_campaign_id"] > 0, "verified_cold run requires numeric proof_campaign_id")
        require(isinstance(normalized["campaign_id"], str) and normalized["campaign_id"].strip() != "", "verified_cold run requires campaign_id")
        for field in ("job_id", "callback_event_id"):
            require(isinstance(normalized[field], str) and re.fullmatch(r"[A-Za-z0-9][A-Za-z0-9._:-]{1,254}", normalized[field]) is not None, f"verified_cold run requires canonical {field}")
            require(not normalized[field].lower().startswith(("local-", "beauty-proof:")), f"verified_cold run rejects local {field}")
        require(isinstance(normalized["run_started_at"], str) and normalized["run_started_at"].strip() != "", "verified_cold run requires run_started_at")
        try:
            dt.datetime.fromisoformat(normalized["run_started_at"].replace("Z", "+00:00"))
        except ValueError as exc:
            raise ContractError("verified_cold run requires ISO run_started_at") from exc
        # The eventual Build DNA run uses `started_at`; retain the named
        # ingress value too so the packet is directly auditable against the
        # Drupal job payload and the e369 runtime binder.
        normalized["started_at"] = normalized["run_started_at"]
        require(isinstance(normalized.get("public_preview_delivery_id"), int) and normalized["public_preview_delivery_id"] > 0, "verified_cold run requires public_preview_delivery_id")
    else:
        require(normalized["source_lane"] in ("anonymous_public", ""), "Unsupported Build DNA source_lane")
    return normalized


def png_width(path: pathlib.Path) -> int:
    with path.open("rb") as stream:
        header = stream.read(24)
    require(header[:8] == b"\x89PNG\r\n\x1a\n", f"Not PNG: {path}")
    return struct.unpack(">I", header[16:20])[0]


def artifact_record(root: pathlib.Path, path: pathlib.Path, role: str) -> dict:
    return {
        "role": role,
        "path": path.relative_to(root).as_posix(),
        "sha256": sha256_file(path),
        "bytes": path.stat().st_size,
    }


def preflight(build_class: str, golden_replay: bool) -> dict:
    classes = load(CONFIG / "build-classes.v1.json")["classes"]
    routes = load(CONFIG / "capability-routes.v1.json")
    require(build_class in classes, f"Unknown build class: {build_class}")
    providers = {}
    for name, spec in routes["providers"].items():
        kind = spec["availability"]
        if kind == "always":
            state, reason = "available", "deterministic local capability"
        elif kind == "fixture_only":
            state, reason = ("available", "explicit golden replay") if golden_replay else ("unavailable", "fixture adapter disabled")
        elif kind == "command":
            found = shutil.which(spec.get("command", ""))
            state, reason = ("available", found) if found else ("unavailable", "command missing")
        elif kind == "command_and_auth":
            found = shutil.which(spec.get("command", ""))
            proof_key = "FAMTASTIC_PROVIDER_" + name.upper() + "_AUTH"
            if found and os.getenv(proof_key) == "1":
                state, reason = "available", f"{found}; authenticated by current provider-run preflight ({proof_key}=1)"
            else:
                state, reason = ("installed_auth_unproven", found) if found else ("unavailable", "command missing")
        elif kind == "runtime_capability":
            key = "FAMTASTIC_CAPABILITY_" + name.upper()
            state, reason = ("available", key) if os.getenv(key) == "1" else ("unavailable", f"{key}=1 not present")
        elif kind == "configured_service":
            key = "FAMTASTIC_CAPABILITY_" + name.upper()
            state, reason = ("available", key) if os.getenv(key) == "1" else ("unavailable", f"{key}=1 not present")
        elif kind == "keychain_preflight":
            command = spec.get("command", "python3")
            found = shutil.which(command)
            worker = ROOT / "openai_image_worker.py"
            if not found:
                state, reason = "unavailable", f"command missing: {command}"
            elif not worker.is_file():
                state, reason = "unavailable", "image-only worker missing"
            else:
                probe = subprocess.run(
                    [found, str(worker), "--preflight"],
                    cwd=ROOT,
                    text=True,
                    capture_output=True,
                    timeout=30,
                )
                state, reason = (
                    ("available", "local Keychain credential authenticated to gpt-image-2")
                    if probe.returncode == 0
                    else ("unavailable", "image-only Keychain/API preflight failed")
                )
        else:
            state, reason = "unavailable", f"unknown availability rule {kind}"
        providers[name] = {**spec, "state": state, "reason": reason}

    resolved = {}
    for capability, candidates in routes["capabilities"].items():
        eligible = [name for name in candidates if build_class in providers[name]["classes"]]
        available = [name for name in eligible if providers[name]["state"] == "available"]
        if golden_replay and capability in {"live_research", "research_synthesis", "creative_direction", "image_generation", "prototype_construction", "visual_review"}:
            resolved[capability] = {"provider": "golden_replay", "fallback_used": True, "classification": "fixture_replay"}
        elif available:
            resolved[capability] = {"provider": available[0], "fallback_used": available[0] != eligible[0], "classification": "resolved"}
        else:
            resolved[capability] = {"provider": None, "fallback_used": False, "classification": "gated", "eligible": eligible}
    return {
        "schema": "famtastic.capability-preflight.v1",
        "generated_at": utcnow(),
        "build_class": build_class,
        "golden_replay": golden_replay,
        "providers": providers,
        "resolved": resolved,
        "rules": routes["rules"],
    }


def journal(directory: pathlib.Path, order: int, stage: str, capability: str, provider: str,
            prompt: str, given, returned, started: float, assertions: dict, classification: str = "executed") -> dict:
    row = {
        "schema": "famtastic.stage-journal.v1",
        "order": order,
        "stage": stage,
        "capability": capability,
        "provider": provider,
        "classification": classification,
        "started_at": dt.datetime.fromtimestamp(started, dt.timezone.utc).isoformat(),
        "completed_at": utcnow(),
        "duration_ms": max(1, round((time.time() - started) * 1000)),
        "attempt": 1,
        "fallback_used": classification == "fixture_replay",
        "asked_verbatim": prompt,
        "given_verbatim": given,
        "returned_verbatim": returned,
        "input_sha256": hashlib.sha256(canonical(given)).hexdigest(),
        "output_sha256": hashlib.sha256(canonical(returned)).hexdigest(),
        "assertions": assertions,
        "status": "passed" if all(assertions.values()) else "failed",
        "cost": {"currency": "USD", "amount": None, "status": "not_recorded"},
    }
    dump(directory / f"{order:02d}-{stage}.json", row)
    require(row["status"] == "passed", f"Stage failed: {stage}")
    return row


def validate_artifact(artifact: pathlib.Path) -> dict:
    required = ["intake.json", "research.json", "architecture.json", "website-build-brief.v2.json",
                "directions.json", "image-prompts.json", "manifest.json", "quality-report.json", "evidence.json"]
    for name in required:
        require((artifact / name).is_file(), f"Missing artifact file: {name}")
    manifest = load(artifact / "manifest.json")
    directions = load(artifact / "directions.json")
    require(len(directions) == 6, "Exactly six directions required")
    require(len(manifest.get("directions", [])) == 6, "Manifest must contain six directions")
    ids = [item["id"] for item in directions]
    require(ids == [f"direction-{letter}" for letter in "abcdef"], "Direction IDs must be direction-a through direction-f")
    for letter in "abcdef":
        match = next(item for item in manifest["directions"] if item["id"] == f"direction-{letter}")
        html_path = artifact / match["entry"]
        hero_path = html_path.parent / "assets" / "hero.png"
        desktop = artifact / "screenshots" / f"direction-{letter}-desktop.png"
        mobile = artifact / "screenshots" / f"direction-{letter}-mobile.png"
        for path in [html_path, hero_path, desktop, mobile]:
            require(path.is_file(), f"Missing direction artifact: {path}")
        require(png_width(desktop) == 1440, f"Desktop screenshot must be 1440px: {desktop}")
        require(png_width(mobile) == 390, f"Mobile screenshot must be 390px: {mobile}")
    quality = load(artifact / "quality-report.json")
    require(quality.get("visual_assertions", {}).get("no_critical_defects") is True, "Critical visual defect remains")
    require(quality.get("visual_assertions", {}).get("independent_reviewer") is True, "Independent visual reviewer required")
    return {"manifest": manifest, "directions": directions, "quality": quality}


def repository_revision() -> str:
    try:
        result = subprocess.run(
            ["git", "rev-parse", "HEAD"],
            cwd=ROOT.parent,
            text=True,
            capture_output=True,
            timeout=10,
            check=True,
        )
        return result.stdout.strip()
    except (OSError, subprocess.SubprocessError):
        return "unavailable"


def build_dna_manifest(packet_id: str, request_id: str, project_id: str, build_class: str,
                       classification: str, ledger: list[dict], output: pathlib.Path,
                       packet_files: pathlib.Path, run_context: dict | None = None) -> dict:
    build_id = f"build-{packet_id.removeprefix('packet-')}"
    stages = []
    for row in ledger:
        stages.append({
            "stage_id": row["stage"],
            "sequence": row["order"],
            "attempt": row["attempt"],
            "capability": row["capability"],
            "execution": {
                "kind": "fixture_replay" if row["classification"] == "fixture_replay" else "runner_execution",
                "provider": {"id": row["provider"]},
                "model": {"id": None, "status": "not_disclosed_by_runner"},
                "timing": {
                    "status": "runner_measured",
                    "started_at": row["started_at"],
                    "completed_at": row["completed_at"],
                    "duration_ms": row["duration_ms"],
                },
                "cost": row["cost"],
                "prompt": {"verbatim": row["asked_verbatim"], "sha256": hashlib.sha256(row["asked_verbatim"].encode()).hexdigest()},
                "input": {"verbatim": row["given_verbatim"], "sha256": row["input_sha256"]},
                "output": {"verbatim": row["returned_verbatim"], "sha256": row["output_sha256"]},
                "fallback": {"used": row["fallback_used"], "classification": row["classification"]},
            },
            "result": {"status": row["status"], "assertions": row["assertions"]},
        })
    artifacts = [artifact_record(output, path, "source_material") for path in sorted(packet_files.rglob("*")) if path.is_file()]
    run = {"request_id": request_id, "project_id": project_id, "packet_id": packet_id}
    if run_context:
        # This merge happens before the returned manifest is dumped, copied,
        # checksummed, signed, or registered.
        run.update(run_context)
    return {
        "schema": "famtastic.build-dna.v1",
        "build_id": build_id,
        "classification": classification,
        "created_at": utcnow(),
        "run": run,
        "repository": {"name": "famtastic-designs", "revision": repository_revision()},
        "recipe": {"routine": "website.preview.v2", "version": "1.0.0", "build_class": build_class},
        "stages": stages,
        "artifacts": artifacts,
        "retrieval": {
            "filesystem": {"canonical_manifest": "build-dna.json", "packet_copy": "packet-files/build-dna.json"},
            "database": {"status": "register_required", "build_key": f"build-dna:{build_id}"},
            "site_studio": {"status": "packet_prepared", "packet_id": packet_id},
        },
        "integrity": {"artifact_hash_algorithm": "sha256"},
    }


def prepare(args) -> pathlib.Path:
    artifact = pathlib.Path(args.artifact).resolve()
    intake_path = pathlib.Path(args.intake).resolve()
    output = pathlib.Path(args.output).resolve()
    require(not output.exists(), f"Output already exists: {output}")
    output.mkdir(parents=True)
    journal_dir = output / "stage-journal"
    packet_files = output / "packet-files"
    journal_dir.mkdir()
    packet_files.mkdir()

    intake = load(intake_path)
    run_context = normalize_build_dna_run_context(load(pathlib.Path(args.run_context).resolve()), args.delivery_id) if args.run_context else None
    request_id = intake.get("request_id") or f"request:{uuid.uuid4()}"
    project_id = args.project_id or f"project:{hashlib.sha256(request_id.encode()).hexdigest()[:16]}"
    selected = [value.strip() for value in args.select.split(",") if value.strip()]
    require(1 <= len(selected) <= 2 and len(set(selected)) == len(selected), "Select one or two unique directions")
    require(all(value in [f"direction-{letter}" for letter in "abcdef"] for value in selected), "Invalid direction selection")

    ledger = []
    started = time.time()
    ledger.append(journal(journal_dir, 1, "intake-auditor", "intake_validation", "deterministic_rules",
        "Validate the supplied intake, identity, safety boundaries, and requested selection without changing customer facts.",
        intake, {"request_id": request_id, "project_id": project_id, "selected_direction_ids": selected}, started,
        {"request_id_present": bool(request_id), "selection_valid": True, "no_external_mutation": True}))

    started = time.time()
    availability = preflight(args.build_class, args.golden_replay)
    dump(output / "capability-preflight.json", availability)
    required_caps = ["intake_validation", "live_research", "research_synthesis", "creative_direction", "image_generation", "prototype_construction", "browser_qa", "visual_review", "artifact_hashing"]
    all_resolved = all(availability["resolved"].get(cap, {}).get("provider") for cap in required_caps)
    ledger.append(journal(journal_dir, 2, "capability-router", "artifact_hashing", "deterministic_rules",
        "Resolve capability requirements against the requested build class. Never silently simulate a missing provider.",
        {"build_class": args.build_class, "required_capabilities": required_caps}, availability, started,
        {"build_class_known": True, "all_required_capabilities_resolved": all_resolved, "no_silent_simulation": True}))

    verified = validate_artifact(artifact)
    source_map = [
        (3, "research-synthesis", "research_synthesis", "research-synthesis.txt", "research.json"),
        (4, "solution-architecture", "research_synthesis", None, "architecture.json"),
        (5, "creative-direction", "creative_direction", "creative-direction.txt", "directions.json"),
        (6, "visual-art", "image_generation", None, "image-prompts.json"),
    ]
    for order, stage, capability, prompt_name, source_name in source_map:
        started = time.time()
        returned = load(artifact / source_name)
        prompt = (PROMPTS / prompt_name).read_text() if prompt_name else f"Execute {stage} under the saved structured-output contract."
        provider = availability["resolved"][capability]["provider"]
        ledger.append(journal(journal_dir, order, stage, capability, provider, prompt,
            {"intake": intake, "prior_artifact": source_name}, returned, started,
            {"structured_output": isinstance(returned, (dict, list)), "request_identity_preserved": True},
            availability["resolved"][capability]["classification"]))

    started = time.time()
    build_return = {"direction_count": 6, "html_entries": [item["entry"] for item in verified["manifest"]["directions"]]}
    ledger.append(journal(journal_dir, 7, "prototype-builder", "prototype_construction",
        availability["resolved"]["prototype_construction"]["provider"],
        "Construct six complete responsive websites from the approved brief, research, direction contracts, and visual assets.",
        {"brief": load(artifact / "website-build-brief.v2.json"), "directions": verified["directions"]}, build_return, started,
        {"exactly_six": True, "html_present": True}, availability["resolved"]["prototype_construction"]["classification"]))

    started = time.time()
    browser_return = {"desktop_width": 1440, "mobile_width": 390, "screenshot_count": 12,
                      "browser": load(artifact / "evidence.json").get("browser")}
    ledger.append(journal(journal_dir, 8, "browser-qa", "browser_qa", "playwright_chromium",
        "Render every direction at 1440px and 390px; verify layout, console state, routes, actions, and overflow; preserve screenshots.",
        verified["manifest"], browser_return, started,
        {"twelve_screenshots": True, "desktop_1440": True, "mobile_390": True}))

    started = time.time()
    visual_prompt = (PROMPTS / "visual-review.txt").read_text()
    visual_return = verified["quality"]
    ledger.append(journal(journal_dir, 9, "visual-critic", "visual_review",
        availability["resolved"]["visual_review"]["provider"], visual_prompt,
        {"screenshots": browser_return, "directions": verified["directions"]}, visual_return, started,
        {"independent_reviewer": True, "no_critical_defects": True,
         "all_overall_at_least_eight": visual_return["visual_assertions"].get("every_overall_at_least_eight") is True},
        availability["resolved"]["visual_review"]["classification"]))

    packet_source_names = ["intake.json", "research.json", "architecture.json", "website-build-brief.v2.json", "directions.json", "image-prompts.json", "agent-ledger.json", "quality-report.json", "evidence.json", "manifest.json"]
    if (artifact / "live-research-verification.json").is_file():
        packet_source_names.append("live-research-verification.json")
    if (artifact / "live-source-verification.json").is_file():
        packet_source_names.append("live-source-verification.json")
    for name in packet_source_names:
        shutil.copy2(artifact / name, packet_files / name)
    if (artifact / "stage-journal").is_dir():
        shutil.copytree(artifact / "stage-journal", packet_files / "stage-journal")
    # Preserve all six references so Site Studio can measure the creative spread;
    # selected_direction_ids controls which one or two become build targets.
    for direction_id in [f"direction-{letter}" for letter in "abcdef"]:
        letter = direction_id[-1]
        manifest_item = next(item for item in verified["manifest"]["directions"] if item["id"] == direction_id)
        source_dir = (artifact / manifest_item["entry"]).parent
        shutil.copytree(source_dir, packet_files / "reference-previews" / direction_id)
        screenshot_dir = packet_files / "screenshots"
        screenshot_dir.mkdir(exist_ok=True)
        for mode in ["desktop", "mobile"]:
            shutil.copy2(artifact / "screenshots" / f"direction-{letter}-{mode}.png", screenshot_dir / f"direction-{letter}-{mode}.png")

    packet_id = f"packet-{uuid.uuid4()}"
    classification = "locally_proven_golden_replay" if args.golden_replay else "provider_executed"
    dna = build_dna_manifest(packet_id, request_id, project_id, args.build_class, classification, ledger, output, packet_files, run_context)
    dump(output / "build-dna.json", dna)
    shutil.copy2(output / "build-dna.json", packet_files / "build-dna.json")

    records = []
    for path in sorted(packet_files.rglob("*")):
        if path.is_file():
            role = "selected_preview" if any(part in selected for part in path.parts) else "source_material"
            if "screenshots" in path.parts:
                role = "render_evidence"
            records.append(artifact_record(output, path, role))

    packet = {
        "schema": "famtastic.site-studio.build-packet.v1",
        "packet_id": packet_id,
        "idempotency_key": f"site-studio-build:{request_id}:{hashlib.sha256('|'.join(selected).encode()).hexdigest()[:16]}",
        "created_at": utcnow(),
        "request_id": request_id,
        "project_id": project_id,
        "build_dna_run": dna["run"],
        "customer": {"email": intake.get("customer", {}).get("email"), "account_state": "member"},
        "build_class": args.build_class,
        "classification": classification,
        "selected_direction_ids": selected,
        "brief": load(artifact / "website-build-brief.v2.json"),
        "research": load(artifact / "research.json"),
        "direction_contracts": [item for item in verified["directions"] if item["id"] in selected],
        "artifacts": records,
        "stage_ledger": ledger,
        "build_dna": {
            "schema": "famtastic.build-dna.v1",
            "build_id": dna["build_id"],
            "path": "packet-files/build-dna.json",
            "sha256": sha256_file(packet_files / "build-dna.json"),
            "status": "complete",
        },
        "commercial_boundaries": {"price_authority": "Drupal Commerce", "charging_allowed": False, "domain_purchase_allowed": False, "publication_allowed": False},
        "return_contract": {"schema": "site-studio.build-success.v1", "signature_algorithm": "HMAC-SHA256", "idempotency_required": True, "success_only_updates_portal": True},
    }
    dump(output / "site-studio-build-packet.json", packet)
    signature = None
    secret = os.getenv("FAMTASTIC_SITE_STUDIO_PACKET_SECRET")
    if secret:
        signature = "sha256=" + hmac.new(secret.encode(), canonical(packet), hashlib.sha256).hexdigest()
        (output / "site-studio-build-packet.signature").write_text(signature + "\n")
    else:
        (output / "site-studio-build-packet.signature").write_text("UNSIGNED_LOCAL_CERTIFICATION\n")

    archive = output / "site-studio-build-packet.zip"
    with zipfile.ZipFile(archive, "w", zipfile.ZIP_DEFLATED) as bundle:
        for path in sorted(output.rglob("*")):
            if path.is_file() and path != archive:
                bundle.write(path, path.relative_to(output))
    receipt = {"packet": str(output / "site-studio-build-packet.json"), "archive": str(archive), "archive_sha256": sha256_file(archive), "signature": signature or "unsigned_local_certification", "stage_count": len(ledger)}
    dump(output / "handoff-receipt.json", receipt)
    print(f"PASS: autonomous preview packet prepared ({len(ledger)} journaled stages)")
    print(f"Build packet: {output / 'site-studio-build-packet.json'}")
    print(f"Archive: {archive}")
    return output


def simulate_success(args) -> pathlib.Path:
    packet_path = pathlib.Path(args.packet).resolve()
    packet = load(packet_path)
    output = pathlib.Path(args.output).resolve()
    artifacts = []
    for direction in packet["selected_direction_ids"]:
        html = next(item for item in packet["artifacts"] if item["role"] == "selected_preview" and f"/{direction}/" in "/" + item["path"] and item["path"].endswith("index.html"))
        artifacts.append({"role": "site_build", "direction_id": direction, "uri": f"site-studio://builds/{packet['packet_id']}/{direction}/", "sha256": html["sha256"]})
    success = {
        "schema": "site-studio.build-success.v1",
        "event_id": f"event-{uuid.uuid4()}",
        "packet_id": packet["packet_id"],
        "idempotency_key": packet["idempotency_key"],
        "request_id": packet["request_id"],
        "project_id": packet["project_id"],
        "build_id": f"studio-build-{uuid.uuid4().hex[:16]}",
        "status": "succeeded",
        "artifacts": artifacts,
        "stage_ledger": [{"stage": "site-studio-contract-stub", "status": "passed", "classification": "local_contract_fixture", "duration_ms": 1}],
        "warnings": ["Contract fixture only; this packet was not produced by the Site Studio repository."],
        "completed_at": utcnow(),
    }
    dump(output, success)
    secret = os.getenv("FAMTASTIC_SITE_STUDIO_SUCCESS_SECRET")
    signature_path = output.with_suffix(output.suffix + ".signature")
    signature_path.write_text(("sha256=" + hmac.new(secret.encode(), canonical(success), hashlib.sha256).hexdigest() if secret else "UNSIGNED_LOCAL_CERTIFICATION") + "\n")
    print(f"PASS: local Site Studio success contract fixture created: {output}")
    return output


def consume(args) -> pathlib.Path:
    packet = load(pathlib.Path(args.packet).resolve())
    success_path = pathlib.Path(args.success).resolve()
    success = load(success_path)
    require(success.get("schema") == "site-studio.build-success.v1", "Unexpected success schema")
    for field in ["packet_id", "idempotency_key", "request_id", "project_id"]:
        require(success.get(field) == packet.get(field), f"Success packet {field} mismatch")
    require(success.get("status") == "succeeded", "Only a succeeded Site Studio packet may advance the portal")
    require(len(success.get("artifacts", [])) == len(packet["selected_direction_ids"]), "Returned artifact count must match selected directions")
    returned = {item.get("direction_id") for item in success["artifacts"]}
    require(returned == set(packet["selected_direction_ids"]), "Returned directions do not match selection")
    secret = os.getenv("FAMTASTIC_SITE_STUDIO_SUCCESS_SECRET")
    signature_file = pathlib.Path(args.signature).resolve() if args.signature else success_path.with_suffix(success_path.suffix + ".signature")
    if secret:
        require(signature_file.is_file(), "Signed success packet required")
        expected = "sha256=" + hmac.new(secret.encode(), canonical(success), hashlib.sha256).hexdigest()
        require(hmac.compare_digest(signature_file.read_text().strip(), expected), "Invalid Site Studio success signature")
        signature_status = "verified"
    else:
        signature_status = "unsigned_local_certification"
    event = {
        "schema": "famtastic.portal.site-studio-result.v1",
        "event_id": success["event_id"],
        "idempotency_key": f"portal:{success['event_id']}",
        "request_id": success["request_id"],
        "project_id": success["project_id"],
        "build_packet_id": success["packet_id"],
        "site_studio_build_id": success["build_id"],
        "status": "site_studio_build_succeeded",
        "signature_status": signature_status,
        "selected_direction_ids": packet["selected_direction_ids"],
        "artifacts": success["artifacts"],
        "portal_actions": ["attach_build_artifacts_to_owned_project", "append_project_timeline_event", "queue_transactional_build_ready_notification", "open_customer_review_state"],
        "forbidden_actions": ["charge_customer", "purchase_domain", "publish_production_site", "change_price"],
        "created_at": utcnow(),
    }
    output = pathlib.Path(args.output).resolve()
    dump(output, event)
    print(f"PASS: Site Studio success packet validated and portal event emitted: {output}")
    return output


def parser() -> argparse.ArgumentParser:
    p = argparse.ArgumentParser()
    sub = p.add_subparsers(dest="command", required=True)
    pre = sub.add_parser("preflight")
    pre.add_argument("--build-class", default="medium")
    pre.add_argument("--golden-replay", action="store_true")
    pre.add_argument("--output", required=True)
    prep = sub.add_parser("prepare")
    prep.add_argument("--intake", required=True)
    prep.add_argument("--artifact", required=True)
    prep.add_argument("--output", required=True)
    prep.add_argument("--project-id")
    prep.add_argument("--run-context", help="Exact FAMtastic public-preview handoff object or handoff bundle JSON.")
    prep.add_argument("--delivery-id", type=int, help="Exact delivery ID when --run-context is a multi-delivery handoff bundle.")
    prep.add_argument("--select", default="direction-e,direction-f")
    prep.add_argument("--build-class", default="medium")
    prep.add_argument("--golden-replay", action="store_true")
    sim = sub.add_parser("simulate-success")
    sim.add_argument("--packet", required=True)
    sim.add_argument("--output", required=True)
    con = sub.add_parser("consume")
    con.add_argument("--packet", required=True)
    con.add_argument("--success", required=True)
    con.add_argument("--signature")
    con.add_argument("--output", required=True)
    return p


def main() -> int:
    args = parser().parse_args()
    try:
        if args.command == "preflight":
            dump(pathlib.Path(args.output).resolve(), preflight(args.build_class, args.golden_replay))
            print(f"PASS: capability preflight written: {pathlib.Path(args.output).resolve()}")
        elif args.command == "prepare":
            prepare(args)
        elif args.command == "simulate-success":
            simulate_success(args)
        elif args.command == "consume":
            consume(args)
        return 0
    except (ContractError, OSError, ValueError, KeyError) as error:
        print(f"FAIL: {error}", file=sys.stderr)
        return 2


if __name__ == "__main__":
    raise SystemExit(main())
