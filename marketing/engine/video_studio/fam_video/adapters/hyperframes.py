"""Bounded local HyperFrames adapter; never installs or invokes cloud services."""

from __future__ import annotations

import hashlib
import json
import os
import re
import shutil
import signal
import subprocess
import tempfile
import time
from fractions import Fraction
from pathlib import Path


_REQUIRED_DOCTOR_CHECKS = {
    "node.js", "cpu", "memory", "disk", "frames cache", "archive extractor", "environment",
    "ffmpeg", "ffprobe", "chrome",
}
_ADVISORY_DOCTOR_CHECKS = {
    "version", "tts (kokoro)", "bgm (musicgen)", "whisper-cpp", "docker", "docker running",
}


def _readiness_from_doctor(diagnosis: dict) -> dict:
    """Assess native video-render prerequisites separately from optional integrations and update notices."""
    checks = diagnosis.get("checks")
    if checks is None:
        # Older supported CLIs return only the aggregate boolean.
        passed = diagnosis.get("ok") is True
        return {"ok": passed, "reason": None if passed else "HyperFrames doctor reported not ready."}
    if not isinstance(checks, list):
        return {"ok": False, "reason": "HyperFrames doctor returned an invalid checks list."}

    by_name: dict[str, list[dict]] = {}
    malformed = []
    for item in checks:
        if not isinstance(item, dict) or not isinstance(item.get("name"), str):
            malformed.append("unnamed doctor check")
            continue
        name = " ".join(item["name"].lower().split())
        by_name.setdefault(name, []).append(item)

    missing = sorted(_REQUIRED_DOCTOR_CHECKS - set(by_name))
    failed = sorted(name for name, items in by_name.items()
                    if name not in _ADVISORY_DOCTOR_CHECKS and any(item.get("ok") is not True for item in items))
    duplicates = sorted(name for name, items in by_name.items() if len(items) != 1)
    problems = []
    if missing:
        problems.append("missing required checks: " + ", ".join(missing))
    if failed:
        problems.append("failed checks: " + ", ".join(failed))
    if duplicates:
        problems.append("duplicate checks: " + ", ".join(duplicates))
    if malformed:
        problems.append("malformed checks: " + ", ".join(malformed))
    advisory_failures = sorted(name for name in _ADVISORY_DOCTOR_CHECKS & set(by_name)
                               if any(item.get("ok") is not True for item in by_name[name]))
    return {
        "ok": not problems,
        "reason": "HyperFrames local rendering prerequisites " + "; ".join(problems) + "." if problems else None,
        "required_check_names": sorted(_REQUIRED_DOCTOR_CHECKS),
        "advisory_failures": advisory_failures,
    }


def _environment() -> dict[str, str]:
    return {
        **os.environ,
        "HYPERFRAMES_NO_TELEMETRY": "1",
        "HYPERFRAMES_NO_UPDATE_CHECK": "1",
        "HYPERFRAMES_NO_AUTO_INSTALL": "1",
        "DO_NOT_TRACK": "1",
    }


def _resolve(executable: str) -> str | None:
    return shutil.which(str(executable))


def _run(command: list[str], *, cwd: Path | None = None, timeout: float = 60) -> dict:
    """Kill the process group on timeout so orphaned Chrome jobs do not pile up."""
    if timeout <= 0:
        raise ValueError("Command timeout must be positive")
    began = time.monotonic()
    process = subprocess.Popen(
        command, cwd=cwd, env=_environment(), stdin=subprocess.DEVNULL,
        stdout=subprocess.PIPE, stderr=subprocess.STDOUT, text=True,
        start_new_session=os.name != "nt",
    )
    timed_out = False
    try:
        stdout, _ = process.communicate(timeout=timeout)
    except subprocess.TimeoutExpired:
        timed_out = True
        if os.name == "nt":
            process.terminate()
        else:
            os.killpg(process.pid, signal.SIGTERM)
        try:
            stdout, _ = process.communicate(timeout=3)
        except subprocess.TimeoutExpired:
            if os.name == "nt":
                process.kill()
            else:
                os.killpg(process.pid, signal.SIGKILL)
            stdout, _ = process.communicate(timeout=5)
    return {
        "command": command, "returncode": process.returncode,
        "stdout": stdout, "elapsed_seconds": round(time.monotonic() - began, 3),
        "timed_out": timed_out,
    }


def _decode_json(output: str) -> dict:
    """Accept a single JSON object surrounded by CLI notices, never eval output."""
    decoder = json.JSONDecoder()
    for offset, character in enumerate(output):
        if character == "{":
            try:
                value, _ = decoder.raw_decode(output[offset:])
            except json.JSONDecodeError:
                continue
            if isinstance(value, dict):
                return value
    raise ValueError("Command did not return a JSON object")


def inspect(executable: str = "hyperframes") -> dict:
    """Inspect the explicitly selected installation without installing anything."""
    resolved = _resolve(executable)
    if not resolved:
        return {"available": False, "ok": False, "executable": str(executable),
                "reason": "HyperFrames executable not found; configure the existing local installation."}
    result = _run([resolved, "--version"], timeout=20)
    report = {
        "available": result["returncode"] == 0 and not result["timed_out"],
        "ok": False, "executable": resolved, "version": result["stdout"].strip(),
        "installation_attempted": False,
    }
    if not report["available"]:
        report["reason"] = "Installed HyperFrames did not respond successfully to --version"
        return report
    doctor = _run([resolved, "doctor", "--json"], timeout=60)
    if doctor["timed_out"]:
        report["reason"] = "HyperFrames doctor timed out"
        return report
    try:
        diagnosis = _decode_json(doctor["stdout"])
    except ValueError:
        report["reason"] = "HyperFrames doctor returned no parseable diagnosis"
        return report
    report["doctor"] = diagnosis
    # Preserve the CLI's full aggregate diagnosis; only local render prerequisites
    # gate this adapter. Model add-ons and update notifications are advisory.
    readiness = _readiness_from_doctor(diagnosis)
    report["readiness"] = readiness
    report["ok"] = doctor["returncode"] == 0 and readiness["ok"]
    if doctor["returncode"] != 0:
        report["reason"] = "HyperFrames doctor command failed."
    elif not readiness["ok"]:
        report["reason"] = readiness["reason"]
    return report


def _checked(result: dict, log_path: Path, label: str) -> None:
    log_path.write_text(result["stdout"], encoding="utf-8")
    if result["timed_out"] or result["returncode"] != 0:
        status = "timed out" if result["timed_out"] else f"exited {result['returncode']}"
        raise RuntimeError(f"{label} {status}. Log: {log_path}\n{result['stdout'][-2500:]}")


def _resolve_quality(executable: str, requested: str) -> str:
    """Bind semantic presets to the selected CLI, without upgrading it.

    Older installations advertise standard/high; newer ones use looks/delivery.
    Never silently let an unknown preset fall back to the CLI default.
    """
    if requested == "draft":
        return requested
    help_result = _run([executable, "render", "--help"], timeout=20)
    if help_result["returncode"] or help_result["timed_out"]:
        raise RuntimeError("Cannot inspect installed HyperFrames quality presets")
    lines = [line for line in help_result["stdout"].splitlines()
             if "quality" in line.lower() and "draft" in line.lower()]
    advertised = set(re.findall(r"\b(?:draft|looks|delivery|standard|high)\b", " ".join(lines)))
    equivalents = {"looks": ("looks", "standard"), "standard": ("standard", "looks"),
                   "delivery": ("delivery", "high"), "high": ("high", "delivery")}
    for candidate in equivalents[requested]:
        if candidate in advertised:
            return candidate
    raise RuntimeError(f"Installed HyperFrames does not advertise a compatible {requested} quality preset")


def _html_metadata(project_dir: Path) -> dict:
    source = (project_dir / "index.html").read_text(encoding="utf-8")
    root = re.search(r'<[^>]+\bdata-composition-id\s*=\s*[\"\'][^>]+>', source)
    if root is None:
        raise ValueError("index.html lacks a HyperFrames composition root")
    expected = {}
    for name in ("width", "height", "duration"):
        match = re.search(rf'\bdata-{name}\s*=\s*[\"\']([^\"\']+)', root.group())
        if not match:
            raise ValueError(f"Composition root lacks static data-{name}")
        expected[name] = float(match.group(1))
    expected["audio"] = bool(re.search(r"<audio\b", source))
    return expected


def render(
    project_dir: Path, output: Path, executable: str = "hyperframes",
    fps: int = 30, quality: str = "draft", timeout: int = 1800,
) -> dict:
    """Check, render, verify, and atomically expose a successful MP4.

    Failure leaves check/render logs and never masquerades as a completed movie.
    Existing output files are not overwritten; the caller manages cache/versioning.
    """
    project_dir, output = Path(project_dir).resolve(), Path(output).resolve()
    if quality not in {"draft", "looks", "delivery", "standard", "high"}:
        raise ValueError("Unsupported HyperFrames quality")
    if isinstance(fps, bool) or not isinstance(fps, int) or not 1 <= fps <= 240:
        raise ValueError("Frame rate must be an integer between 1 and 240")
    if timeout <= 0:
        raise ValueError("Render timeout must be positive")
    if output.suffix.lower() != ".mp4":
        raise ValueError("The local video adapter currently produces MP4 outputs")
    if output.exists():
        raise FileExistsError(f"Refusing to overwrite an existing render: {output}")
    expected = _html_metadata(project_dir)
    resolved = _resolve(executable)
    if not resolved:
        raise RuntimeError("HyperFrames executable is missing; configure an existing local binary. No installation attempted.")
    ffprobe = shutil.which("ffprobe")
    if not ffprobe:
        raise RuntimeError("FFprobe is required to verify a rendered movie")
    version_result = _run([resolved, "--version"], cwd=project_dir, timeout=20)
    if version_result["returncode"] != 0 or version_result["timed_out"]:
        raise RuntimeError("HyperFrames --version failed")
    resolved_quality = _resolve_quality(resolved, quality)
    output.parent.mkdir(parents=True, exist_ok=True)
    start = time.monotonic()
    check = _run([resolved, "check", str(project_dir), "--json"], cwd=project_dir, timeout=min(timeout, 240))
    _checked(check, project_dir / "hyperframes-check.log", "HyperFrames check")
    # Some CLI generations return a useful explicit ok field, some findings only.
    try:
        check_payload = _decode_json(check["stdout"])
    except ValueError:
        check_payload = None
    if check_payload is not None and check_payload.get("ok") is False:
        raise RuntimeError(f"HyperFrames check reported ok=false. Log: {project_dir / 'hyperframes-check.log'}")
    with tempfile.TemporaryDirectory(prefix=".hyperframes-", dir=output.parent) as staging:
        staged_output = Path(staging) / "output.mp4"
        command = [resolved, "render", str(project_dir), "--output", str(staged_output),
                   "--fps", str(fps), "--quality", resolved_quality, "--strict", "--workers", "1"]
        rendered = _run(command, cwd=project_dir, timeout=timeout)
        _checked(rendered, project_dir / "hyperframes-render.log", "HyperFrames render")
        if not staged_output.is_file() or staged_output.stat().st_size == 0:
            raise RuntimeError("HyperFrames exited successfully but produced no nonempty MP4")
        probed = _run([ffprobe, "-v", "error", "-show_streams", "-show_format", "-of", "json", str(staged_output)], timeout=30)
        _checked(probed, project_dir / "ffprobe.json", "FFprobe")
        metadata = _decode_json(probed["stdout"])
        videos = [s for s in metadata.get("streams", []) if s.get("codec_type") == "video"]
        if not videos:
            raise RuntimeError("Rendered output has no video stream")
        video = videos[0]
        if video.get("width") != expected["width"] or video.get("height") != expected["height"]:
            raise RuntimeError("Rendered dimensions do not match the composition")
        measured_duration = float(metadata.get("format", {}).get("duration", 0))
        if abs(measured_duration - expected["duration"]) > 2 / fps + .02:
            raise RuntimeError("Rendered duration does not match the composition")
        measured_fps = float(Fraction(video.get("avg_frame_rate", "0")))
        if abs(measured_fps - fps) > .01:
            raise RuntimeError("Rendered frame rate does not match the request")
        has_audio = any(s.get("codec_type") == "audio" for s in metadata.get("streams", []))
        if expected["audio"] and not has_audio:
            raise RuntimeError("Composition has audio but the rendered movie is silent")
        digest = hashlib.sha256(staged_output.read_bytes()).hexdigest()
        byte_count = staged_output.stat().st_size
        staged_output.replace(output)
    return {
        "status": "rendered", "provider": "hyperframes_local", "output": str(output),
        "version": version_result["stdout"].strip(), "command": command,
        "requested_quality": quality, "resolved_quality": resolved_quality,
        "sha256": digest, "bytes": byte_count, "width": video["width"], "height": video["height"],
        "duration": measured_duration, "fps": measured_fps, "has_audio": has_audio,
        "elapsed_seconds": round(time.monotonic() - start, 3),
        "external_service_cost_usd": 0, "electricity_cost_usd": None,
        "check_log": str(project_dir / "hyperframes-check.log"),
        "render_log": str(project_dir / "hyperframes-render.log"),
        "probe_path": str(project_dir / "ffprobe.json"),
        "approved_for_publication": False,
    }
