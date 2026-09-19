#!/usr/bin/env python3
"""Run one pinned, local-only OpenVoice V2 tone-color conversion.

This deliberately does not synthesize speech, install dependencies, download
models, call a provider, upload audio, or publish output. Evidence is attached to
an existing canonical Build DNA run. A failed attempt keeps its log and any
partial output beside the caller-selected receipt path.
"""
from __future__ import annotations

import argparse
import contextlib
import hashlib
import importlib.metadata
import json
import math
import os
import platform
import re
import resource
import shutil
import subprocess
import sys
import time
import traceback
import uuid
from datetime import datetime, timezone
from pathlib import Path
from typing import Any, Callable


REPO_ROOT = Path(__file__).resolve().parents[1]
ENGINE_ROOT = REPO_ROOT / "marketing/engine/video_studio"
RUNTIME_LOCK = ENGINE_ROOT / "openvoice-v2-runtime.lock"
EXPECTED_RUNTIME_LOCK_SHA256 = "2572e504a299c409bfb580ad32b377889ba04e184701f7bcf6c663b8fa7941fc"
OPENVOICE_COMMIT = "74a1d147b17a8c3092dd5430504bd83ef6c7eb23"
CHECKPOINT_SHA256 = "9652c27e92b6b2a91632590ac9962ef7ae2b712e5c5b7f4c34ec55ee2b37ab9e"
CONFIG_SHA256 = "9dfff60350b8c63f2c664efd92a61b2516efb22671466960f0e5dfebd881fa47"
MAX_THREADS = 2
MAX_REFERENCE_SECONDS = 30.0
MIN_REFERENCE_SECONDS = 1.0
MAX_SOURCE_SECONDS = 600.0
PROVENANCE_SCHEMA = "famtastic.local-voice-conversion-provenance.v1"


class ConversionError(RuntimeError):
    pass


def _sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with Path(path).open("rb") as handle:
        for block in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(block)
    return digest.hexdigest()


def _now() -> str:
    return datetime.now(timezone.utc).isoformat(timespec="milliseconds").replace("+00:00", "Z")


def _json_dump(path: Path, value: dict) -> None:
    with path.open("x", encoding="utf-8") as handle:
        json.dump(value, handle, indent=2, sort_keys=True, ensure_ascii=False, allow_nan=False)
        handle.write("\n")
        handle.flush()
        os.fsync(handle.fileno())


def _safe_repo_path(value: str | Path, label: str, *, must_exist: bool = True) -> Path:
    candidate = Path(value).expanduser()
    if not candidate.is_absolute():
        candidate = REPO_ROOT / candidate
    candidate = Path(os.path.abspath(candidate))
    try:
        relative = candidate.relative_to(REPO_ROOT)
    except ValueError as exc:
        raise ConversionError(f"{label} must be inside the repository for retained evidence") from exc
    current = REPO_ROOT
    if current.is_symlink():
        raise ConversionError("Repository root may not be a symlink")
    for part in relative.parts:
        current = current / part
        if current.is_symlink():
            raise ConversionError(f"{label} may not traverse a symlink: {current.relative_to(REPO_ROOT)}")
    if must_exist and not candidate.exists():
        raise ConversionError(f"{label} does not exist: {relative}")
    if must_exist and not (candidate.is_file() or candidate.is_dir()):
        raise ConversionError(f"{label} must be a regular file or directory: {relative}")
    if candidate.exists() and candidate.resolve() != candidate:
        raise ConversionError(f"{label} must resolve to its literal in-repository path")
    return candidate


def _repo_relative(path: Path) -> str:
    return path.relative_to(REPO_ROOT).as_posix()


def _run_git(source: Path, *args: str) -> str:
    result = subprocess.run(
        ["git", "-C", str(source), *args], capture_output=True, text=True,
        timeout=15, check=False,
    )
    if result.returncode:
        raise ConversionError(f"Could not inspect pinned OpenVoice source ({args[0]})")
    return result.stdout.strip()


def _code_provenance(source: Path) -> dict:
    top = Path(_run_git(source, "rev-parse", "--show-toplevel")).resolve()
    if top != source.resolve():
        raise ConversionError("OpenVoice source path must be the repository root")
    commit = _run_git(source, "rev-parse", "HEAD")
    if commit != OPENVOICE_COMMIT:
        raise ConversionError(f"OpenVoice source revision must be pinned commit {OPENVOICE_COMMIT}")
    dirty = _run_git(source, "status", "--porcelain=v1", "--untracked-files=all")
    if dirty:
        raise ConversionError("OpenVoice source checkout must be clean")
    names = _run_git(source, "ls-files", "-z").split("\0")
    tree = hashlib.sha256()
    tracked = []
    for name in sorted(item for item in names if item):
        item = source / name
        if item.is_symlink() or not item.is_file():
            raise ConversionError(f"Tracked OpenVoice source is missing or unsafe: {name}")
        digest = _sha256(item)
        tree.update(name.encode("utf-8") + b"\0" + digest.encode("ascii") + b"\n")
        tracked.append({"path": name, "sha256": digest})
    return {"repo": "https://github.com/myshell-ai/OpenVoice", "commit": commit,
            "worktree": "clean", "tracked_file_count": len(tracked),
            "tree_sha256": tree.hexdigest(), "tracked_files": tracked}


def _copy_frozen_file(source: Path, destination: Path) -> dict:
    """Copy a local input once into a unique, retained run snapshot."""
    destination.parent.mkdir(parents=True, exist_ok=True)
    if destination.exists() or destination.is_symlink():
        raise ConversionError(f"Snapshot destination already exists: {_repo_relative(destination)}")
    digest = hashlib.sha256()
    with source.open("rb") as incoming, destination.open("xb") as outgoing:
        for block in iter(lambda: incoming.read(1024 * 1024), b""):
            outgoing.write(block)
            digest.update(block)
        outgoing.flush()
        os.fsync(outgoing.fileno())
    frozen_hash = digest.hexdigest()
    if _sha256(source) != frozen_hash or _sha256(destination) != frozen_hash:
        raise ConversionError(f"Input changed while it was being frozen: {_repo_relative(source)}")
    return {"source_path": _repo_relative(source), "snapshot_path": _repo_relative(destination),
            "sha256": frozen_hash, "size_bytes": destination.stat().st_size}


def _snapshot_code_tree(source: Path, destination: Path, code_info: dict) -> tuple[Path, list[dict]]:
    destination.mkdir(parents=True, exist_ok=False)
    frozen_files = []
    tree = hashlib.sha256()
    for record in code_info["tracked_files"]:
        relative = record["path"]
        original = source / relative
        if original.is_symlink() or not original.is_file() or _sha256(original) != record["sha256"]:
            raise ConversionError(f"Pinned OpenVoice file changed during snapshot: {relative}")
        frozen = destination / relative
        details = _copy_frozen_file(original, frozen)
        if details["sha256"] != record["sha256"]:
            raise ConversionError(f"Frozen OpenVoice file checksum mismatch: {relative}")
        tree.update(relative.encode("utf-8") + b"\0" + details["sha256"].encode("ascii") + b"\n")
        frozen_files.append({"path": _repo_relative(frozen), "sha256": details["sha256"],
                             "size_bytes": details["size_bytes"], "upstream_path": relative})
    if tree.hexdigest() != code_info["tree_sha256"]:
        raise ConversionError("Frozen OpenVoice source tree does not match the pinned checkout")
    return destination, frozen_files


def _snapshot_tree_sha256(files: list[dict]) -> str:
    tree = hashlib.sha256()
    for record in sorted(files, key=lambda item: item["upstream_path"]):
        path = REPO_ROOT / record["path"]
        if not path.is_file() or path.is_symlink() or _sha256(path) != record["sha256"]:
            raise ConversionError(f"Frozen OpenVoice source changed during inference: {record['upstream_path']}")
        tree.update(record["upstream_path"].encode("utf-8") + b"\0" + record["sha256"].encode("ascii") + b"\n")
    return tree.hexdigest()


def _runtime_lock(lock_path: Path) -> tuple[dict[str, str], dict]:
    actual_hash = _sha256(lock_path)
    if actual_hash != EXPECTED_RUNTIME_LOCK_SHA256:
        raise ConversionError("Runtime lock checksum differs from the runner's pinned lock")
    expected: dict[str, str] = {}
    python_pin = platform_pin = None
    for raw in lock_path.read_text(encoding="utf-8").splitlines():
        line = raw.strip()
        if not line or line.startswith("#"):
            continue
        if line.startswith("python=="):
            python_pin = line.partition("==")[2]
        elif line.startswith("platform=="):
            platform_pin = line.partition("==")[2]
        else:
            match = re.fullmatch(r"([A-Za-z0-9_.-]+)==([^=]+)", line)
            if not match:
                raise ConversionError("Runtime lock must contain exact package pins only")
            name = re.sub(r"[-_.]+", "-", match.group(1)).lower()
            if name in expected:
                raise ConversionError(f"Runtime lock repeats package {name}")
            expected[name] = match.group(2)
    actual_python = platform.python_version()
    actual_platform = f"{sys.platform}-{platform.machine()}"
    if actual_python != python_pin or actual_platform != platform_pin:
        raise ConversionError(
            f"Runtime requires Python/platform {python_pin}/{platform_pin}; found {actual_python}/{actual_platform}"
        )
    installed = {}
    for distribution in importlib.metadata.distributions():
        name = distribution.metadata.get("Name")
        if name:
            normalized = re.sub(r"[-_.]+", "-", name).lower()
            installed[normalized] = distribution.version
    if installed != expected:
        missing = sorted(set(expected) - set(installed))
        extra = sorted(set(installed) - set(expected))
        changed = sorted(name for name in set(installed) & set(expected) if installed[name] != expected[name])
        raise ConversionError(
            "Installed package inventory differs from pinned runtime lock "
            f"(missing={missing}, extra={extra}, changed={changed})"
        )
    receipt = {"python": actual_python, "platform": actual_platform,
               "lock_path": _repo_relative(lock_path), "lock_sha256": actual_hash,
               "packages": dict(sorted(installed.items()))}
    return expected, receipt


def _evidence(value: Any, label: str, *, expected_scope: str | None = None) -> dict:
    if not isinstance(value, dict):
        raise ConversionError(f"{label} must cite a local evidence file, not a boolean")
    if set(value) != {"path", "sha256"}:
        raise ConversionError(f"{label} must contain only path and sha256")
    path = _safe_repo_path(value["path"], f"{label} path")
    if not path.is_file():
        raise ConversionError(f"{label} must cite a file")
    digest = _sha256(path)
    if not isinstance(value["sha256"], str) or digest != value["sha256"]:
        raise ConversionError(f"{label} checksum does not match cited local evidence")
    return {"path": _repo_relative(path), "sha256": digest, "size_bytes": path.stat().st_size}


def _voice_provenance(path: Path) -> tuple[dict, list[Path]]:
    try:
        data = json.loads(path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError) as exc:
        raise ConversionError("Voice provenance must be valid local JSON") from exc
    if not isinstance(data, dict) or set(data) != {"schema", "purpose", "source_voice", "target_voice"}:
        raise ConversionError("Voice provenance has unexpected or missing fields")
    if data.get("schema") != PROVENANCE_SCHEMA or not isinstance(data.get("purpose"), str) or not data["purpose"].strip():
        raise ConversionError("Voice provenance needs the supported schema and a specific purpose")
    evidence_paths = []
    normalized = {"schema": PROVENANCE_SCHEMA, "purpose": data["purpose"].strip()}
    for key in ("source_voice", "target_voice"):
        voice = data.get(key)
        if not isinstance(voice, dict):
            raise ConversionError(f"{key} must record its origin")
        kind = voice.get("kind")
        common = {"kind", "description", "evidence"}
        allowed = {
            "synthetic_stock_voice": common | {"voice", "model", "model_license"},
            "licensed_voice_sample": common | {"license", "license_evidence"},
            "owner_voice_sample": common | {"consent_evidence"},
        }
        if kind not in allowed or set(voice) != allowed[kind]:
            raise ConversionError(f"{key} must use a supported, evidence-backed source description")
        if not isinstance(voice.get("description"), str) or not voice["description"].strip():
            raise ConversionError(f"{key}.description must be specific")
        item = {"kind": kind, "description": voice["description"].strip()}
        item["evidence"] = _evidence(voice["evidence"], f"{key}.evidence")
        evidence_paths.append(_safe_repo_path(item["evidence"]["path"], f"{key}.evidence.path"))
        if kind == "synthetic_stock_voice":
            for field in ("voice", "model", "model_license"):
                if not isinstance(voice.get(field), str) or not voice[field].strip():
                    raise ConversionError(f"{key}.{field} must be recorded")
                item[field] = voice[field].strip()
        elif kind == "licensed_voice_sample":
            if not isinstance(voice.get("license"), str) or not voice["license"].strip():
                raise ConversionError(f"{key}.license must identify the sample's terms")
            item["license"] = voice["license"].strip()
            item["license_evidence"] = _evidence(voice["license_evidence"], f"{key}.license_evidence")
            evidence_paths.append(_safe_repo_path(item["license_evidence"]["path"], f"{key}.license_evidence.path"))
        else:
            item["consent_evidence"] = _evidence(voice["consent_evidence"], f"{key}.consent_evidence")
            evidence_paths.append(_safe_repo_path(item["consent_evidence"]["path"], f"{key}.consent_evidence.path"))
            item["consent_review"] = "evidence recorded; meaning and authority still require human review"
        normalized[key] = item
    return normalized, evidence_paths


def _audio_info(path: Path, soundfile_module=None) -> dict:
    sf = soundfile_module
    if sf is None:
        import soundfile as sf
    try:
        info = sf.info(str(path))
    except Exception as exc:
        raise ConversionError(f"Unsupported or unreadable audio file: {_repo_relative(path)}") from exc
    duration = float(info.duration)
    if not math.isfinite(duration) or duration <= 0:
        raise ConversionError(f"Audio duration must be finite and positive: {_repo_relative(path)}")
    return {"duration_seconds": duration, "sample_rate": int(info.samplerate), "channels": int(info.channels),
            "frames": int(info.frames), "format": str(info.format), "subtype": str(info.subtype)}


def _validate_audio(source: Path, reference: Path, soundfile_module=None) -> tuple[dict, dict]:
    src = _audio_info(source, soundfile_module)
    ref = _audio_info(reference, soundfile_module)
    if src["channels"] != 1 or ref["channels"] != 1:
        raise ConversionError("Source speech and voice reference must both be mono audio")
    if src["duration_seconds"] > MAX_SOURCE_SECONDS:
        raise ConversionError(f"Source speech exceeds {MAX_SOURCE_SECONDS:g} seconds")
    if not MIN_REFERENCE_SECONDS <= ref["duration_seconds"] <= MAX_REFERENCE_SECONDS:
        raise ConversionError(
            f"Reference duration must be between {MIN_REFERENCE_SECONDS:g} and {MAX_REFERENCE_SECONDS:g} seconds"
        )
    return src, ref


def _resource_snapshot() -> dict:
    usage = resource.getrusage(resource.RUSAGE_SELF)
    # macOS reports ru_maxrss in bytes; Linux reports KiB. This runner is pinned to macOS.
    return {"peak_rss_bytes": int(usage.ru_maxrss),
            "user_cpu_seconds": float(usage.ru_utime), "system_cpu_seconds": float(usage.ru_stime)}


def _load_converter(source: Path, checkpoint: Path, config: Path, threads: int):
    os.environ["HF_HUB_OFFLINE"] = "1"
    os.environ["TRANSFORMERS_OFFLINE"] = "1"
    os.environ["HF_DATASETS_OFFLINE"] = "1"
    if str(source) not in sys.path:
        sys.path.insert(0, str(source))
    import torch
    torch.set_num_threads(threads)
    torch.set_num_interop_threads(1)
    from openvoice.api import OpenVoiceBaseClass, ToneColorConverter
    converter = _new_unwatermarked_converter(ToneColorConverter, OpenVoiceBaseClass, str(config))
    converter.load_ckpt(str(checkpoint))
    return converter, torch


def _new_unwatermarked_converter(converter_type, base_type, config_path: str):
    """Avoid upstream's broken enable_watermark kwarg forwarding.

    OpenVoice 74a1d14 passes all kwargs to its base before checking the
    watermark flag. Initialize the same class's base directly, then apply the
    class's own disabled-watermark state. The separate WavMark dependency and
    checkpoint are not installed in this pinned runtime and are never fetched.
    """
    converter = converter_type.__new__(converter_type)
    base_type.__init__(converter, config_path, device="cpu")
    converter.watermark_model = None
    converter.version = getattr(converter.hps, "_version_", "v1")
    return converter


def _promote_no_clobber(attempt_output: Path, final_output: Path) -> None:
    try:
        os.link(attempt_output, final_output)
    except FileExistsError as exc:
        raise ConversionError("Output appeared during conversion; refusing to overwrite it") from exc
    except OSError as exc:
        raise ConversionError(f"Atomic no-clobber promotion failed; attempt output retained: {attempt_output.name}") from exc
    attempt_output.unlink()


def _ledger_for(path: Path, ledger_type):
    if not path.is_file() or path.name != "build-dna.json":
        raise ConversionError("--build-dna must name an existing canonical ledger")
    run_dir = path.parent
    snapshot = run_dir / "campaign.snapshot.json"
    try:
        data = json.loads(path.read_text(encoding="utf-8"))
        campaign = json.loads(snapshot.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError) as exc:
        raise ConversionError("Build DNA ledger or its frozen campaign snapshot is unreadable") from exc
    if data.get("schema") != "famtastic.build-dna.v1":
        raise ConversionError("Build DNA ledger has an unsupported schema")
    if data.get("completion", {}).get("status") != "in_progress":
        raise ConversionError("Voice conversion requires an open Build DNA run; use a new run after finalization")
    return ledger_type(run_dir, REPO_ROOT, campaign)


def _arguments(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--source", required=True, help="Local mono WAV speech to convert")
    parser.add_argument("--reference", required=True, help="Local mono WAV defining the target voice color")
    parser.add_argument("--output", required=True, help="New WAV path; existing files are never overwritten")
    parser.add_argument("--provenance", required=True, help="Local JSON describing source/reference origins and evidence")
    parser.add_argument("--openvoice-source", required=True, help="Clean checkout of the pinned OpenVoice commit")
    parser.add_argument("--checkpoint", required=True, help="Local pinned OpenVoice V2 converter checkpoint")
    parser.add_argument("--config", required=True, help="Local pinned OpenVoice V2 converter config")
    parser.add_argument("--build-dna", required=True, help="Existing open canonical Build DNA ledger")
    parser.add_argument("--receipt", required=True, help="New JSON receipt path; unique to this attempt")
    parser.add_argument("--threads", type=int, choices=(1, 2), default=2, help="CPU PyTorch threads (default 2, maximum 2)")
    return parser.parse_args(argv)


def run(args, *, converter_loader: Callable | None = None, ledger_type=None, soundfile_module=None) -> int:
    # Import the canonical local evidence implementation without making the CLI
    # depend on installation or package manager state.
    if ledger_type is None:
        sys.path.insert(0, str(ENGINE_ROOT))
        from fam_video.evidence import Ledger
        ledger_type = Ledger

    build_dna = _safe_repo_path(args.build_dna, "Build DNA path")
    output = _safe_repo_path(args.output, "Output path", must_exist=False)
    receipt = _safe_repo_path(args.receipt, "Receipt path", must_exist=False)
    log_path = receipt.with_name(receipt.stem + ".log")
    _safe_repo_path(log_path, "Log path", must_exist=False)
    if output.suffix.lower() != ".wav" or receipt.suffix.lower() != ".json":
        raise ConversionError("Output must end in .wav and receipt must end in .json")
    for path, label in ((output, "Output"), (receipt, "Receipt"), (log_path, "Log")):
        if path.exists() or path.is_symlink():
            raise ConversionError(f"{label} already exists; choose a unique path and never overwrite evidence")
    ledger = _ledger_for(build_dna, ledger_type)
    output.parent.mkdir(parents=True, exist_ok=True)
    receipt.parent.mkdir(parents=True, exist_ok=True)
    if output.parent.resolve() != output.parent or receipt.parent.resolve() != receipt.parent:
        raise ConversionError("Output and receipt parents must be literal, symlink-free paths")

    started = time.monotonic()
    stage_id = f"local-voice-conversion-{receipt.stem}"
    attempt_output = output.with_name(f".{output.stem}.failed-attempt-{uuid.uuid4().hex}.wav")
    receipt_data = {
        "schema": "famtastic.local-voice-conversion-receipt.v1", "status": "in_progress",
        "created_at": _now(), "stage_id": stage_id,
        "policy": {"execution": "local_cpu_only", "threads_requested": args.threads,
                   "paid_provider_requests": False, "network_upload": False, "publishing": False,
                   "automatic_model_downloads": False,
                   "network_note": "Runner makes no network calls; this is not a workstation firewall."},
        "watermark": {"enabled": False,
                      "reason": "The separate WavMark dependency and checkpoint are not installed in this pinned runtime; the runner does not install, download, or load them."},
        "review": {"voice_identity": "not_evaluated", "human_review": "pending"},
        "paths": {"output": _repo_relative(output), "attempt_output": _repo_relative(attempt_output),
                  "receipt": _repo_relative(receipt), "log": _repo_relative(log_path)},
    }
    input_artifacts: list[tuple[Path, str, str]] = []
    failure: Exception | None = None
    source = reference = provenance_path = code = checkpoint = config = None
    openvoice_source = None
    snapshot_paths: dict[str, Path] = {}
    snapshot_code_files: list[dict] = []
    snapshot_manifest_path: Path | None = None
    runner_source = Path(__file__).resolve()
    runner_source_hash = lock_source_hash = None
    lock_path = RUNTIME_LOCK
    source_before = ref_before = None
    output_info = None
    resource_before = _resource_snapshot()
    conversion_start = time.monotonic()
    with log_path.open("x", encoding="utf-8") as log_handle:
        with contextlib.redirect_stdout(log_handle), contextlib.redirect_stderr(log_handle):
            try:
                with ledger.stage(stage_id, "local-openvoice-tone-color-conversion",
                                  provider="local-openvoice", model_id="myshell-ai/OpenVoiceV2",
                                  model_status="pinned_local_checkpoint", command=[
                                      sys.executable, Path(__file__).name,
                                      "--source", str(args.source), "--reference", str(args.reference),
                                      "--output", str(output), "--provenance", str(args.provenance),
                                      "--threads", str(args.threads),
                                  ]) as stage:
                    try:
                        source = _safe_repo_path(args.source, "Source audio")
                        reference = _safe_repo_path(args.reference, "Reference audio")
                        provenance_path = _safe_repo_path(args.provenance, "Voice provenance")
                        code = _safe_repo_path(args.openvoice_source, "OpenVoice source")
                        openvoice_source = code
                        checkpoint = _safe_repo_path(args.checkpoint, "OpenVoice checkpoint")
                        config = _safe_repo_path(args.config, "OpenVoice config")
                        lock_source = _safe_repo_path(RUNTIME_LOCK, "Pinned runtime lock")
                        runner_source_hash = _sha256(runner_source)
                        lock_source_hash = _sha256(lock_source)
                        if not source.is_file() or not reference.is_file() or not provenance_path.is_file():
                            raise ConversionError("Source, reference and provenance must be regular files")
                        provenance, provenance_evidence = _voice_provenance(provenance_path)
                        source_info, reference_info = _validate_audio(source, reference, soundfile_module)
                        code_info = _code_provenance(code)
                        _runtime_lock(lock_source)
                        if _sha256(checkpoint) != CHECKPOINT_SHA256:
                            raise ConversionError("OpenVoice V2 checkpoint does not match the pinned official SHA-256")
                        if _sha256(config) != CONFIG_SHA256:
                            raise ConversionError("OpenVoice V2 config does not match the pinned official SHA-256")
                        original_hashes = {
                            "source": _sha256(source), "reference": _sha256(reference),
                            "provenance": _sha256(provenance_path), "checkpoint": _sha256(checkpoint),
                            "config": _sha256(config), "runtime_lock": _sha256(lock_source),
                            "runner": runner_source_hash,
                        }
                        snapshot_dir = ledger.run_dir / f"inputs-{receipt.stem}"
                        snapshot_dir.mkdir(parents=True, exist_ok=False)
                        snapshot_paths["source"] = snapshot_dir / "audio/source.wav"
                        snapshot_paths["reference"] = snapshot_dir / "audio/reference.wav"
                        snapshot_paths["provenance"] = snapshot_dir / "provenance/source-provenance.json"
                        snapshot_paths["checkpoint"] = snapshot_dir / "model/checkpoint.pth"
                        snapshot_paths["config"] = snapshot_dir / "model/config.json"
                        snapshot_paths["runtime_lock"] = snapshot_dir / "runtime/openvoice-v2-runtime.lock"
                        snapshot_paths["runner"] = snapshot_dir / "runner/famtastic-local-voice-convert.py"
                        snapshot_records = []
                        for key, original in (("source", source), ("reference", reference),
                                              ("provenance", provenance_path), ("checkpoint", checkpoint),
                                              ("config", config), ("runtime_lock", lock_source),
                                              ("runner", runner_source)):
                            record = _copy_frozen_file(original, snapshot_paths[key])
                            if record["sha256"] != original_hashes[key]:
                                raise ConversionError(f"Frozen {key} snapshot checksum differs from its source")
                            snapshot_records.append({"role": key, **record})
                        snapshot_evidence = []
                        for index, evidence_path in enumerate(provenance_evidence):
                            frozen = snapshot_dir / f"provenance/evidence-{index:02d}-{evidence_path.name}"
                            record = _copy_frozen_file(evidence_path, frozen)
                            snapshot_records.append({"role": "provenance_evidence", **record})
                            snapshot_evidence.append((evidence_path, frozen, record))
                        snapshot_code_dir, snapshot_code_files = _snapshot_code_tree(
                            code, snapshot_dir / "openvoice-source", code_info)
                        snapshot_records.extend({"role": "openvoice_source_file", **item,
                                                 "source_path": _repo_relative(code / item["upstream_path"])}
                                                for item in snapshot_code_files)
                        lock_path = snapshot_paths["runtime_lock"]
                        code = snapshot_code_dir
                        source = snapshot_paths["source"]
                        reference = snapshot_paths["reference"]
                        provenance_path = snapshot_paths["provenance"]
                        checkpoint = snapshot_paths["checkpoint"]
                        config = snapshot_paths["config"]
                        source_before, ref_before = _sha256(source), _sha256(reference)
                        if source_before != original_hashes["source"] or ref_before != original_hashes["reference"]:
                            raise ConversionError("Frozen audio snapshots do not match their selected source files")
                        if _sha256(snapshot_paths["runner"]) != runner_source_hash:
                            raise ConversionError("Frozen runner snapshot does not match the executing runner")
                        _, runtime_info = _runtime_lock(lock_path)
                        provenance_for_receipt = json.loads(json.dumps(provenance))
                        evidence_snapshot_by_source = {
                            _repo_relative(original): _repo_relative(frozen)
                            for original, frozen, _ in snapshot_evidence
                        }
                        for voice_key in ("source_voice", "target_voice"):
                            voice = provenance_for_receipt[voice_key]
                            old_path = voice["evidence"]["path"]
                            voice["evidence"]["source_path"] = old_path
                            voice["evidence"]["path"] = evidence_snapshot_by_source[old_path]
                            if "license_evidence" in voice:
                                old_path = voice["license_evidence"]["path"]
                                voice["license_evidence"]["source_path"] = old_path
                                voice["license_evidence"]["path"] = evidence_snapshot_by_source[old_path]
                            if "consent_evidence" in voice:
                                old_path = voice["consent_evidence"]["path"]
                                voice["consent_evidence"]["source_path"] = old_path
                                voice["consent_evidence"]["path"] = evidence_snapshot_by_source[old_path]
                        snapshot_manifest_path = snapshot_dir / "snapshot-manifest.json"
                        snapshot_manifest = {
                            "schema": "famtastic.local-voice-input-snapshot.v1",
                            "created_at": _now(), "receipt": _repo_relative(receipt),
                            "openvoice": {"commit": code_info["commit"],
                                          "source_tree_sha256": code_info["tree_sha256"],
                                          "snapshot_tree_sha256": _snapshot_tree_sha256(snapshot_code_files),
                                          "tracked_files": snapshot_code_files},
                            "files": snapshot_records,
                        }
                        _json_dump(snapshot_manifest_path, snapshot_manifest)
                        snapshot_records.append({"role": "input_snapshot_manifest",
                                                 "source_path": None,
                                                 "snapshot_path": _repo_relative(snapshot_manifest_path),
                                                 "sha256": _sha256(snapshot_manifest_path),
                                                 "size_bytes": snapshot_manifest_path.stat().st_size})
                        input_artifacts = [
                            (source, "voice_conversion_source_audio_snapshot", "frozen local source audio"),
                            (reference, "voice_conversion_target_reference_snapshot", "frozen local target reference"),
                            (provenance_path, "voice_conversion_provenance_snapshot", "frozen source provenance record"),
                            (checkpoint, "openvoice_v2_pinned_converter_weights_snapshot", "frozen myshell-ai OpenVoice V2 MIT checkpoint"),
                            (config, "openvoice_v2_pinned_converter_config_snapshot", "frozen myshell-ai OpenVoice V2 config"),
                            (lock_path, "pinned_local_voice_runtime_lock_snapshot", "frozen exact runtime package inventory"),
                            (snapshot_paths["runner"], "local_voice_conversion_runner_snapshot", "frozen executing FAMtastic runner"),
                            (snapshot_manifest_path, "local_voice_input_snapshot_manifest", "manifest of receipt-specific frozen inputs"),
                        ]
                        input_artifacts += [
                            (frozen, "voice_source_provenance_evidence_snapshot", "frozen operator-supplied provenance evidence")
                            for _, frozen, _ in snapshot_evidence
                        ]
                        input_artifacts += [
                            (REPO_ROOT / item["path"], "openvoice_pinned_source_snapshot", "frozen upstream source at pinned clean commit")
                            for item in snapshot_code_files
                        ]
                        for path, role, rights in input_artifacts:
                            ledger.add_artifact(path, role, rights=rights)
                        receipt_data["paths"]["input_snapshot"] = _repo_relative(snapshot_dir)
                        receipt_data["paths"]["input_snapshot_manifest"] = _repo_relative(snapshot_manifest_path)
                        stage["execution"]["input"] = {
                            "source": {"path": _repo_relative(source), "sha256": source_before, **source_info},
                            "target_reference": {"path": _repo_relative(reference), "sha256": ref_before, **reference_info},
                            "source_original_path": _repo_relative(_safe_repo_path(args.source, "Source audio")),
                            "target_reference_original_path": _repo_relative(_safe_repo_path(args.reference, "Reference audio")),
                            "provenance": provenance_for_receipt, "openvoice_code": code_info,
                            "checkpoint": {"path": _repo_relative(checkpoint), "sha256": CHECKPOINT_SHA256,
                                           "repository": "https://huggingface.co/myshell-ai/OpenVoiceV2",
                                           "revision": "f36e7edfe1684461a8343844af60babc2efbb727",
                                           "license": "MIT"},
                            "config": {"path": _repo_relative(config), "sha256": CONFIG_SHA256},
                            "runtime": runtime_info,
                            "snapshot_manifest": {"path": _repo_relative(snapshot_manifest_path),
                                                  "sha256": _sha256(snapshot_manifest_path)},
                            "watermark": receipt_data["watermark"],
                        }
                        loader = converter_loader or _load_converter
                        converter, torch_module = loader(code, checkpoint, config, args.threads)
                        if torch_module.get_num_threads() > MAX_THREADS or torch_module.get_num_threads() < 1:
                            raise ConversionError("PyTorch thread count exceeded the CPU limit")
                        source_embedding = converter.extract_se(str(source))
                        target_embedding = converter.extract_se(str(reference))
                        converter.convert(str(source), source_embedding, target_embedding,
                                          output_path=str(attempt_output))
                        if not attempt_output.is_file() or attempt_output.stat().st_size == 0:
                            raise ConversionError("Converter returned without producing nonempty attempt audio")
                        if _sha256(source) != source_before or _sha256(reference) != ref_before:
                            raise ConversionError("Frozen source/reference audio changed during inference")
                        if _snapshot_tree_sha256(snapshot_code_files) != code_info["tree_sha256"]:
                            raise ConversionError("Frozen OpenVoice source tree changed during inference")
                        if (_sha256(checkpoint) != CHECKPOINT_SHA256 or _sha256(config) != CONFIG_SHA256
                                or _sha256(lock_path) != EXPECTED_RUNTIME_LOCK_SHA256
                                or _sha256(snapshot_paths["runner"]) != runner_source_hash):
                            raise ConversionError("Frozen runner, runtime, or OpenVoice model files changed during inference")
                        for record in snapshot_records:
                            frozen_path = REPO_ROOT / record.get("snapshot_path", record.get("path", ""))
                            if (not frozen_path.is_file() or frozen_path.is_symlink()
                                    or _sha256(frozen_path) != record["sha256"]):
                                raise ConversionError("A receipt-specific frozen input changed during inference")
                        if (_sha256(_safe_repo_path(args.source, "Source audio")) != original_hashes["source"]
                                or _sha256(_safe_repo_path(args.reference, "Reference audio")) != original_hashes["reference"]
                                or _sha256(_safe_repo_path(args.provenance, "Voice provenance")) != original_hashes["provenance"]
                                or _sha256(checkpoint_source := _safe_repo_path(args.checkpoint, "OpenVoice checkpoint")) != original_hashes["checkpoint"]
                                or _sha256(config_source := _safe_repo_path(args.config, "OpenVoice config")) != original_hashes["config"]
                                or _sha256(lock_source) != lock_source_hash
                                or _sha256(runner_source) != runner_source_hash):
                            raise ConversionError("An original source, runner, or runtime lock changed during inference")
                        if _code_provenance(openvoice_source)["tree_sha256"] != code_info["tree_sha256"]:
                            raise ConversionError("Original pinned OpenVoice source changed during inference")
                        for original, expected in ((checkpoint_source, original_hashes["checkpoint"]),
                                                   (config_source, original_hashes["config"])):
                            if _sha256(original) != expected:
                                raise ConversionError("Original OpenVoice model files changed during inference")
                        if soundfile_module is None:
                            import soundfile as sf
                        else:
                            sf = soundfile_module
                        output_info = _audio_info(attempt_output, sf)
                        if output_info["channels"] != 1 or output_info["sample_rate"] != 22050:
                            raise ConversionError("Converted audio must be mono 22,050 Hz PCM")
                        samples, _ = sf.read(str(attempt_output), dtype="float32", always_2d=False)
                        sample_values = [float(sample) for sample in samples]
                        if not sample_values or not all(math.isfinite(sample) for sample in sample_values) or max(map(abs, sample_values)) < 0.001:
                            raise ConversionError("Converted audio is non-finite or effectively silent")
                        source_duration = source_info["duration_seconds"]
                        allowed_duration_delta = max(0.5, source_duration * 0.02)
                        if abs(output_info["duration_seconds"] - source_duration) > allowed_duration_delta:
                            raise ConversionError("Converted audio duration differs materially from source speech")
                        _promote_no_clobber(attempt_output, output)
                        output_info["sha256"] = _sha256(output)
                        output_info["path"] = _repo_relative(output)
                        receipt_data["status"] = "passed"
                        receipt_data["result"] = {"output": output_info,
                                                   "duration_delta_seconds": output_info["duration_seconds"] - source_duration,
                                                   "voice_identity": "not_scored; human listening review pending"}
                        stage["result"] = {"status": "passed", "review": "review_pending",
                                            "output": output_info, "technical_checks": "passed"}
                        stage["execution"]["output"] = output_info
                    except Exception as exc:
                        failure = exc
                        traceback.print_exc(file=log_handle)
                        receipt_data["status"] = "failed"
                        receipt_data["failure"] = {"type": type(exc).__name__, "message": str(exc)[:1500]}
                        if attempt_output.exists():
                            receipt_data["failure"]["retained_attempt_output"] = {
                                "path": _repo_relative(attempt_output), "sha256": _sha256(attempt_output),
                                "size_bytes": attempt_output.stat().st_size,
                            }
                            stage["execution"]["output"] = receipt_data["failure"]["retained_attempt_output"]
                        stage["result"] = {"status": "failed", "error_type": type(exc).__name__,
                                            "error": str(exc)[:1500], "review": "review_pending"}
                    finally:
                        log_handle.flush()
                        os.fsync(log_handle.fileno())
                        receipt_data["timing"] = {"wall_seconds": round(time.monotonic() - started, 6),
                                                  "conversion_seconds": round(time.monotonic() - conversion_start, 6)}
                        resource_after = _resource_snapshot()
                        receipt_data["resources"] = {
                            "peak_rss_bytes": resource_after["peak_rss_bytes"],
                            "user_cpu_seconds": round(max(0.0, resource_after["user_cpu_seconds"] - resource_before["user_cpu_seconds"]), 6),
                            "system_cpu_seconds": round(max(0.0, resource_after["system_cpu_seconds"] - resource_before["system_cpu_seconds"]), 6),
                            "threads_limit": MAX_THREADS,
                        }
                        _json_dump(receipt, receipt_data)
                        stage["execution"]["command_receipt"] = {
                            "path": _repo_relative(receipt), "sha256": _sha256(receipt),
                        }
            except Exception as exc:
                if failure is None:
                    failure = exc
                    traceback.print_exc(file=log_handle)
                    receipt_data["status"] = "failed"
                    receipt_data["failure"] = {"type": type(exc).__name__, "message": str(exc)[:1500]}
                    receipt_data["timing"] = {"wall_seconds": round(time.monotonic() - started, 6)}
                    receipt_data["resources"] = _resource_snapshot()
                    if not receipt.exists():
                        _json_dump(receipt, receipt_data)
    for path, role, rights in (
        (log_path, "local_voice_conversion_log", "retained local attempt log"),
        (receipt, "local_voice_conversion_receipt", "technical evidence; review pending"),
    ):
        if path.is_file():
            ledger.add_artifact(path, role, rights=rights)
    if output.is_file():
        ledger.add_artifact(output, "local_voice_conversion_output", rights="local synthetic conversion draft; review pending")
    if attempt_output.is_file():
        ledger.add_artifact(attempt_output, "failed_local_voice_conversion_partial_output", rights="retained failed attempt output")
    return 1 if failure else 0


def main(argv=None) -> int:
    args = _arguments(argv)
    try:
        result = run(args)
    except (ConversionError, OSError, ValueError) as exc:
        print(f"FAIL: {exc}", file=sys.stderr)
        return 2
    if result:
        print("FAIL: local voice conversion attempt retained; inspect the JSON receipt and log.", file=sys.stderr)
        return result
    print("PASS: local voice conversion completed; human voice-identity review remains pending.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
