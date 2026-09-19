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
    # Doctor always exits zero; the payload determines environment readiness.
    report["ok"] = doctor["returncode"] == 0 and diagnosis.get("ok") is True
    return report


def _checked(result: dict, log_path: Path, label: str) -> None:
    log_path.write_text(result["stdout"], encoding="utf-8")
    if result["timed_out"] or result["returncode"] != 0:
        status = "timed out" if result["timed_out"] else f"exited {result['returncode']}"
        raise RuntimeError(f"{label} {status}. Log: {log_path}\n{result['stdout'][-2500:]}")


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
                   "--fps", str(fps), "--quality", quality, "--strict", "--workers", "1"]
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
        "sha256": digest, "bytes": byte_count, "width": video["width"], "height": video["height"],
        "duration": measured_duration, "fps": measured_fps, "has_audio": has_audio,
        "elapsed_seconds": round(time.monotonic() - start, 3),
        "external_service_cost_usd": 0, "electricity_cost_usd": None,
        "check_log": str(project_dir / "hyperframes-check.log"),
        "render_log": str(project_dir / "hyperframes-render.log"),
        "probe_path": str(project_dir / "ffprobe.json"),
        "approved_for_publication": False,
    }
