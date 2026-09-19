"""Read-only inventory of the machine actually running this command.

No credential stores, shell profiles, services, model downloads, or network calls
are inspected. Available system RAM is never reported as dedicated GPU VRAM.
"""
from __future__ import annotations

import csv
import io
import os
import platform
import shutil
import subprocess
from datetime import datetime, timezone
from pathlib import Path


def _command(args: list[str], timeout: int = 10) -> dict:
    try:
        result = subprocess.run(args, capture_output=True, text=True, timeout=timeout, check=False)
        return {"ok": result.returncode == 0, "stdout": result.stdout.strip(),
                "stderr": result.stderr.strip()[:600], "returncode": result.returncode}
    except (OSError, subprocess.TimeoutExpired) as exc:
        return {"ok": False, "error": str(exc)}


def _tool(name: str, version_args: list[str]) -> dict:
    executable = shutil.which(name)
    if not executable:
        return {"status": "missing"}
    result = _command([executable, *version_args])
    lines = (result.get("stdout") or result.get("stderr") or "").splitlines()
    return {"status": "available" if result["ok"] else "version_check_failed",
            "path": executable, "version": lines[0][:300] if lines else None}


def _ram() -> dict:
    try:
        if platform.system() == "Darwin":
            result = _command(["sysctl", "-n", "hw.memsize"])
            if result["ok"]:
                return {"status": "measured", "total_bytes": int(result["stdout"]), "source": "sysctl hw.memsize"}
        elif Path("/proc/meminfo").is_file():
            values = {}
            for line in Path("/proc/meminfo").read_text().splitlines():
                key, value = line.split(":", 1)
                if key in {"MemTotal", "MemAvailable"}:
                    values[key] = int(value.split()[0]) * 1024
            return {"status": "measured", "total_bytes": values.get("MemTotal"),
                    "available_bytes": values.get("MemAvailable"), "source": "/proc/meminfo"}
        elif hasattr(os, "sysconf"):
            return {"status": "measured", "total_bytes": os.sysconf("SC_PAGE_SIZE") * os.sysconf("SC_PHYS_PAGES"),
                    "source": "sysconf"}
    except (OSError, ValueError, KeyError):
        pass
    return {"status": "unavailable", "total_bytes": None}


def _gpus() -> tuple[list[dict], list[str]]:
    devices: list[dict] = []
    notes: list[str] = []
    nvidia = shutil.which("nvidia-smi")
    if nvidia:
        result = _command([nvidia, "--query-gpu=name,memory.total,driver_version", "--format=csv,noheader,nounits"])
        if result["ok"]:
            for row in csv.reader(io.StringIO(result["stdout"])):
                if len(row) >= 3:
                    try:
                        vram = int(float(row[1].strip()) * 1024 * 1024)
                    except ValueError:
                        vram = None
                    devices.append({"vendor": "NVIDIA", "name": row[0].strip(),
                                    "dedicated_vram_bytes": vram, "memory_type": "dedicated",
                                    "driver_version": row[2].strip(), "source": "nvidia-smi"})
        else:
            notes.append("nvidia-smi is installed but did not enumerate a working GPU.")
    if platform.system() == "Darwin":
        # Display data has model/VRAM information; no serial-number inventory is requested.
        result = _command(["system_profiler", "SPDisplaysDataType", "-json"], timeout=20)
        if result["ok"]:
            import json
            try:
                for item in json.loads(result["stdout"]).get("SPDisplaysDataType", []):
                    name = item.get("sppci_model") or item.get("_name") or "unreported"
                    apple = "apple" in str(name).lower()
                    devices.append({"vendor": "Apple" if apple else item.get("spdisplays_vendor", "unreported"),
                                    "name": name, "memory_type": "unified" if apple else "unreported",
                                    "dedicated_vram_bytes": None,
                                    "reported_memory": item.get("spdisplays_vram") or item.get("spdisplays_vram_shared"),
                                    "source": "system_profiler SPDisplaysDataType"})
            except (TypeError, ValueError):
                notes.append("Could not parse macOS display inventory.")
        if platform.machine() == "arm64":
            notes.append("Apple Silicon uses shared system memory. Total RAM is not dedicated VRAM; actual model fit requires a local benchmark.")
    elif platform.system() == "Linux":
        # Enumerate DRM vendor IDs without interpreting shared memory as VRAM.
        vendors = {"0x1002": "AMD", "0x8086": "Intel", "0x10de": "NVIDIA"}
        for card in sorted(Path("/sys/class/drm").glob("card[0-9]*/device/vendor")):
            try:
                vendor = vendors.get(card.read_text().strip(), "other")
                if vendor == "NVIDIA" and any(d["vendor"] == vendor for d in devices):
                    continue
                devices.append({"vendor": vendor, "name": card.parent.parent.name,
                                "dedicated_vram_bytes": None, "memory_type": "unreported", "source": "sysfs DRM vendor ID"})
            except OSError:
                pass
    if not devices:
        notes.append("No GPU was enumerated. This is not proof that the owner's separate computer has no GPU.")
    return devices, notes


def inspect() -> dict:
    """Return factual local inventory and bounded readiness, never model-fit promises."""
    devices, notes = _gpus()
    tools = {name: _tool(name, args) for name, args in {
        "ffmpeg": ["-version"], "ffprobe": ["-version"], "node": ["--version"],
        "npm": ["--version"], "git": ["--version"], "hyperframes": ["--version"],
    }.items()}
    ram = _ram()
    cgroup_limit = Path("/sys/fs/cgroup/memory.max")
    if cgroup_limit.is_file():
        try:
            value = cgroup_limit.read_text().strip()
            ram["container_limit_bytes"] = int(value) if value.isdigit() else None
        except OSError:
            pass
    ready = all(tools[name]["status"] == "available" for name in ("ffmpeg", "ffprobe", "node"))
    return {"schema": "famtastic.video-doctor.v1", "checked_at": datetime.now(timezone.utc).isoformat(),
            "scope": "current execution machine only", "os": {"system": platform.system(), "release": platform.release(),
            "architecture": platform.machine()}, "cpu": {"name": platform.processor() or "unreported", "logical_cores": os.cpu_count()},
            "python": {"version": platform.python_version()}, "ram": ram, "gpus": devices, "tools": tools,
            "readiness": {"media_utilities": "available" if ready else "missing_dependencies",
                          "hyperframes_render": "requires_local_project_and_browser_check",
                          "local_ai_video": "unproven_requires_runtime_and_model_benchmark"},
            "notes": notes + ["No model was downloaded, no provider was contacted, and no credential store was inspected."]}
