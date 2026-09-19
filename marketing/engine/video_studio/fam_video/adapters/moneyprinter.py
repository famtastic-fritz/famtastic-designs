"""Explicit, draft-only adapter for an existing MoneyPrinterTurbo installation.

The adapter never installs, upgrades, edits configuration, generates narration,
chooses cloud providers or publishes. Native execution requires a supplied script,
local media and recorded/local-generated audio. MPT remains an optional worker.
"""
from __future__ import annotations

import ast
import hashlib
import json
import math
import shutil
import subprocess
import time
import tomllib
import uuid
from pathlib import Path


class MoneyPrinterError(ValueError):
    """An unsupported or unsafe MoneyPrinterTurbo operation."""


_BATCH_FLAGS = ("--batch-file", "--stop-at", "--custom-audio-file", "--video-source", "--voice-name")
_SINGLE_FLAGS = (
    "--video-subject", "--video-script", "--video-source", "--video-materials",
    "--video-aspect", "--video-clip-duration", "--video-concat-mode", "--video-count",
    "--custom-audio-file", "--voice-name", "--no-subtitle-enabled", "--bgm-type",
    "--bgm-volume", "--n-threads", "--task-id", "--no-match-materials-to-script", "--stop-at",
)
_FORMATS = {"16:9", "9:16", "1:1"}
_RESOLUTIONS = {"16:9": (1920, 1080), "9:16": (1080, 1920), "1:1": (1080, 1080)}
_MEDIA_EXTENSIONS = {".png", ".jpg", ".jpeg", ".bmp", ".mp4", ".mov", ".mkv", ".webm"}
_AUDIO_EXTENSIONS = {".wav", ".mp3", ".m4a", ".aac", ".flac", ".ogg"}
_FIXED = {
    "video_source": "local", "voice_name": "no-voice", "video_concat_mode": "sequential",
    "video_fit_mode": "contain", "video_clip_speed": 1.0, "video_count": 1,
    "match_materials_to_script": False, "subtitle_enabled": False, "bgm_type": "",
    "bgm_volume": 0, "n_threads": 2,
}
_FIELDS = set(_FIXED) | {"video_subject", "video_script", "video_materials", "custom_audio_file", "video_aspect", "video_clip_duration"}
_SINGLE_SCHEMA_FIELDS = _FIELDS - {"video_fit_mode"}
_LIMITATIONS = [
    "Draft montage only; scene layouts and exact individual scene durations are not reproduced.",
    "Subtitles and generated narration/music are disabled; supplied audio determines montage duration.",
    "Human creative review is required; this adapter has no publishing authority.",
]


def _hash(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def _canonical_tasks_root(root: Path) -> Path:
    root = Path(root).resolve()
    candidate = root / "storage/tasks"
    if candidate.is_symlink():
        raise MoneyPrinterError("MoneyPrinter task storage cannot be a symlink.")
    resolved = candidate.resolve()
    if not resolved.is_relative_to(root):
        raise MoneyPrinterError("MoneyPrinter task storage must remain inside its installation.")
    return resolved


def _local_file(value, extensions: set[str]) -> Path:
    if not isinstance(value, (str, Path)) or not str(value):
        raise MoneyPrinterError("A supplied local file path is required.")
    path = Path(value).expanduser()
    if not path.is_absolute():
        raise MoneyPrinterError("MoneyPrinter inputs must use absolute local paths.")
    path = path.resolve()
    if path.suffix.lower() not in extensions or not path.is_file() or path.stat().st_size == 0:
        raise MoneyPrinterError("A local input is missing, empty or an unsupported media type.")
    return path


def _command(argv: list[str], cwd: Path, timeout: float) -> subprocess.CompletedProcess:
    try:
        return subprocess.run(argv, cwd=str(cwd), shell=False, capture_output=True, text=True,
                              encoding="utf-8", errors="replace", timeout=timeout, check=False)
    except subprocess.TimeoutExpired:
        raise MoneyPrinterError("MoneyPrinter subprocess exceeded its timeout; inspect the local task before retrying.") from None
    except OSError:
        raise MoneyPrinterError("A required local executable could not be started.") from None


def _config(root: Path) -> dict:
    path = root / "config.toml"
    if not path.is_file():
        raise MoneyPrinterError("Existing config.toml is required; the adapter does not initialize or mutate it.")
    try:
        data = tomllib.loads(path.read_text(encoding="utf-8"))
    except (OSError, ValueError, UnicodeError):
        raise MoneyPrinterError("Cannot safely read MoneyPrinter configuration.") from None
    app = data.get("app", {})
    if not isinstance(app, dict):
        raise MoneyPrinterError("Unsupported MoneyPrinter configuration.")
    # Deliberately refuse an armed auto-upload switch even if credentials or enabled
    # currently look absent. Do not reveal or depend on any credential values.
    if app.get("upload_post_auto_upload", False) is not False:
        raise MoneyPrinterError("MoneyPrinter auto-upload is armed. Disable it in the existing installation before rendering drafts.")
    if not isinstance(app.get("upload_post_enabled", False), bool):
        raise MoneyPrinterError("MoneyPrinter publishing configuration is not a valid boolean.")
    return {
        "upload_post_enabled": app.get("upload_post_enabled", False),
        "auto_upload": False,
        "config_sha256": _hash(path),
    }


def inspect(root: Path, python_executable: str) -> dict:
    """Inspect a local install without exposing config or changing it."""
    root = Path(root).expanduser().resolve()
    report = {"root": str(root), "supported": False, "draft_only": True, "limitations": list(_LIMITATIONS)}
    try:
        if not (root / "cli.py").is_file():
            raise MoneyPrinterError("Native cli.py is missing; this installed version is unsupported.")
        report["publishing"] = _config(root)
        _canonical_tasks_root(root)
        schema = root / "app/models/schema.py"
        try:
            tree = ast.parse(schema.read_text(encoding="utf-8"))
            model = next(node for node in tree.body if isinstance(node, ast.ClassDef) and node.name == "VideoParams")
            fields = {node.target.id for node in model.body if isinstance(node, ast.AnnAssign) and isinstance(node.target, ast.Name)}
        except (OSError, ValueError, SyntaxError, StopIteration):
            raise MoneyPrinterError("Installed VideoParams schema could not be verified; unsupported version.") from None
        if not (_FIELDS <= fields or _SINGLE_SCHEMA_FIELDS <= fields):
            raise MoneyPrinterError("Installed VideoParams lacks required local-only fields; unsupported version.")
        argv = [str(python_executable), str(root / "cli.py"), "--help"]
        result = _command(argv, root, 20)
        if result.returncode != 0:
            raise MoneyPrinterError("Installed CLI help could not be inspected; unsupported version.")
        help_text = result.stdout + result.stderr
        if _FIELDS <= fields and all(flag in help_text for flag in _BATCH_FLAGS):
            report["native_mode"] = "batch"
        elif _SINGLE_SCHEMA_FIELDS <= fields and all(flag in help_text for flag in _SINGLE_FLAGS):
            report["native_mode"] = "single_task"
            report["limitations"] = list(report["limitations"]) + [
                "Installed CLI has no explicit video_fit_mode field; native default media fitting is used."
            ]
        else:
            raise MoneyPrinterError("Installed schema or CLI lacks the required local-only interface; unsupported version.")
        python_result = _command([str(python_executable), "--version"], root, 10)
        if python_result.returncode != 0:
            raise MoneyPrinterError("Selected MoneyPrinter Python executable could not be verified.")
        report["python_version"] = (python_result.stdout or python_result.stderr).strip()[:80]
        report["help_command"] = argv
        report["cli_sha256"] = _hash(root / "cli.py")
        report["schema_sha256"] = _hash(schema)
        report["revision"] = None
        if (root / ".git").exists() and shutil.which("git"):
            git = _command(["git", "rev-parse", "HEAD"], root, 10)
            revision = git.stdout.strip()
            if git.returncode == 0 and len(revision) in (40, 64) and all(c in "0123456789abcdef" for c in revision):
                report["revision"] = revision
        report["version"] = None
        project = root / "pyproject.toml"
        if project.is_file():
            try:
                version = tomllib.loads(project.read_text(encoding="utf-8")).get("project", {}).get("version")
                if isinstance(version, str) and len(version) <= 80:
                    report["version"] = version
            except (ValueError, OSError):
                pass
        report["supported"] = True
    except MoneyPrinterError as exc:
        report["reason"] = str(exc)
    return report


def _validate_batch(batch) -> list[dict]:
    if not isinstance(batch, list) or not 1 <= len(batch) <= 100:
        raise MoneyPrinterError("MoneyPrinter batch must contain 1–100 local draft tasks.")
    for task in batch:
        if not isinstance(task, dict) or set(task) != _FIELDS:
            raise MoneyPrinterError("Batch fields differ from the explicit local-only contract; regenerate it.")
        if any(type(task[key]) is not type(value) or task[key] != value for key, value in _FIXED.items()):
            raise MoneyPrinterError("Batch tries to change a local-only, caption, music or publishing boundary.")
        if not isinstance(task["video_aspect"], str) or task["video_aspect"] not in _FORMATS:
            raise MoneyPrinterError("MoneyPrinter supports only 16:9, 9:16 and 1:1.")
        if not all(isinstance(task[key], str) and task[key].strip() for key in ("video_subject", "video_script")):
            raise MoneyPrinterError("Supply a complete script and subject; automatic script generation is disabled.")
        duration = task["video_clip_duration"]
        if type(duration) is not int or not 1 <= duration <= 600:
            raise MoneyPrinterError("Draft clip duration must be 1–600 whole seconds.")
        _local_file(task["custom_audio_file"], _AUDIO_EXTENSIONS)
        materials = task["video_materials"]
        if not isinstance(materials, list) or not materials:
            raise MoneyPrinterError("Every MoneyPrinter draft requires supplied local media.")
        for media in materials:
            if not isinstance(media, dict) or set(media) != {"provider", "url", "duration"} or media["provider"] != "local" or type(media["duration"]) is not int or media["duration"] != 0:
                raise MoneyPrinterError("Only explicit local media records are accepted.")
            _local_file(media["url"], _MEDIA_EXTENSIONS)
    return batch


def prepare_batch(campaign: dict, destination: Path) -> dict:
    """Write a new JSON batch for a normalized campaign; never overwrite a draft."""
    if not isinstance(campaign.get("format"), str) or campaign.get("format") not in _FORMATS:
        raise MoneyPrinterError("MoneyPrinter supports only 16:9, 9:16 and 1:1; 4:5 requires a different renderer.")
    script = campaign.get("script")
    if not isinstance(script, str) or not script.strip():
        raise MoneyPrinterError("Supply campaign.script; automatic writing is disabled.")
    audio = _local_file(campaign.get("audio"), _AUDIO_EXTENSIONS)
    scenes = campaign.get("scenes")
    if not isinstance(scenes, list) or not scenes or any(not isinstance(scene, dict) or not scene.get("media") for scene in scenes):
        raise MoneyPrinterError("Every scene needs supplied local media for the MoneyPrinter draft.")
    materials = [{"provider": "local", "url": str(_local_file(scene["media"], _MEDIA_EXTENSIONS)), "duration": 0} for scene in scenes]
    task = dict(_FIXED, video_subject=campaign.get("title") or campaign.get("id") or "Local draft",
                video_script=script, custom_audio_file=str(audio), video_materials=materials,
                video_aspect=campaign["format"], video_clip_duration=5)
    batch = _validate_batch([task])
    data = json.dumps(batch, ensure_ascii=False, indent=2) + "\n"
    if len(data.encode("utf-8")) > 1024 * 1024:
        raise MoneyPrinterError("MoneyPrinter batch exceeds the native 1 MiB limit.")
    destination = Path(destination).expanduser().resolve()
    destination.parent.mkdir(parents=True, exist_ok=True)
    with destination.open("x", encoding="utf-8") as stream:
        stream.write(data)
    return {"batch_path": str(destination), "sha256": _hash(destination), "tasks": 1,
            "draft_only": True, "limitations": list(_LIMITATIONS)}


def _probe(path: Path, executable: str) -> dict:
    result = _command([executable, "-v", "error", "-show_format", "-show_streams", "-of", "json", str(path)], path.parent, 30)
    try:
        data = json.loads(result.stdout)
        video = next(stream for stream in data["streams"] if stream.get("codec_type") == "video")
        audio = any(stream.get("codec_type") == "audio" for stream in data["streams"])
        duration = float(data["format"]["duration"])
        valid = result.returncode == 0 and audio and math.isfinite(duration) and duration > 0 and video["width"] > 0 and video["height"] > 0
    except (ValueError, KeyError, TypeError, StopIteration):
        valid = False
    if not valid:
        raise MoneyPrinterError("MoneyPrinter output failed independent video/audio validation.")
    return {"duration_seconds": duration, "width": video["width"], "height": video["height"], "audio_present": True}


def _managed_video_paths(raw_paths, root: Path, task_id: str) -> list[Path]:
    if not isinstance(raw_paths, list) or len(raw_paths) != 1:
        raise ValueError("expected one rendered file")
    try:
        normalized_id = str(uuid.UUID(task_id))
    except (AttributeError, TypeError, ValueError):
        raise ValueError("invalid task id") from None
    if normalized_id != task_id:
        raise ValueError("non-canonical task id")
    tasks_root = _canonical_tasks_root(root)
    task_dir_input = root / "storage/tasks" / normalized_id
    if task_dir_input.is_symlink():
        raise ValueError("task directory is a symlink")
    task_dir = task_dir_input.resolve()
    if not task_dir.is_relative_to(tasks_root):
        raise ValueError("task directory escapes task storage")
    videos = []
    for raw in raw_paths:
        if not isinstance(raw, str) or not raw:
            raise ValueError("invalid output path")
        path = Path(raw).resolve()
        if not path.is_relative_to(task_dir) or path.suffix.lower() != ".mp4" or not path.is_file() or path.stat().st_size == 0:
            raise ValueError("output is outside managed task storage")
        videos.append(path)
    return videos


def _native_json(stdout: str, expected: str) -> dict:
    """Extract the final task object while ignoring CLI notices around its JSON."""
    decoder = json.JSONDecoder()
    candidates = [stdout]
    candidates.extend(line for line in reversed(stdout.splitlines()) if line.strip())
    candidates.extend(stdout[index:] for index in reversed(range(len(stdout))) if stdout[index] == "{")
    for candidate in candidates:
        try:
            data = json.loads(candidate)
        except (TypeError, ValueError):
            try:
                start = candidate.find("{")
                if start < 0:
                    continue
                data, end = decoder.raw_decode(candidate, start)
                if candidate[end:].strip():
                    continue
            except (TypeError, ValueError):
                continue
        if not isinstance(data, dict):
            continue
        if expected == "batch" and {"total", "succeeded", "failed", "tasks"} <= data.keys():
            return data
        if expected == "single" and {"task_id", "result"} <= data.keys():
            return data
    raise ValueError("no native JSON result")


def _legacy_material_paths(task: dict, output_dir: Path, task_index: int) -> list[str]:
    """Avoid the legacy CLI's comma-separated path ambiguity with private copies."""
    result = []
    for media_index, media in enumerate(task["video_materials"], 1):
        source = Path(media["url"])
        if "," not in str(source):
            result.append(str(source))
            continue
        safe_dir = output_dir / "native-inputs"
        if "," in str(safe_dir):
            raise MoneyPrinterError("Installed CLI cannot safely represent comma paths beneath this output directory.")
        safe_dir.mkdir(exist_ok=True)
        destination = safe_dir / f"media-{task_index:02d}-{media_index:02d}-{_hash(source)[:12]}{source.suffix.lower()}"
        with source.open("rb") as stream, destination.open("xb") as target:
            shutil.copyfileobj(stream, target)
        result.append(str(destination))
    return result


def _single_task_command(root: Path, python_executable: str, task: dict, materials: list[str], task_id: str) -> list[str]:
    """Build argv for releases that expose local inputs only as CLI arguments."""
    argv = [
        str(python_executable), str(root / "cli.py"),
        f"--video-subject={task['video_subject']}",
        f"--video-script={task['video_script']}",
        "--video-source", "local",
        f"--video-materials={','.join(materials)}",
        "--custom-audio-file", task["custom_audio_file"],
        "--voice-name", "no-voice",
        "--video-aspect", task["video_aspect"],
        "--video-clip-duration", str(task["video_clip_duration"]),
        "--video-concat-mode", "sequential",
        "--video-count", "1",
        "--no-match-materials-to-script",
        "--n-threads", "2",
        "--no-subtitle-enabled",
        "--bgm-type", "none",
        "--bgm-volume", "0",
        "--task-id", task_id,
        "--stop-at", "video",
    ]
    if any("," in item for item in materials):
        raise MoneyPrinterError("Installed CLI cannot safely represent a comma in a local media path.")
    return argv


def _existing_task_ids(root: Path) -> set[str]:
    tasks_root = _canonical_tasks_root(root)
    if not tasks_root.is_dir():
        return set()
    return {entry.name for entry in tasks_root.iterdir() if entry.is_dir()}


def run(root: Path, batch_path: Path, python_executable: str, output_dir: Path, timeout=1800) -> dict:
    """Execute and verify an explicit batch, returning a credential-free receipt."""
    if isinstance(timeout, bool) or not isinstance(timeout, (float, int)) or not math.isfinite(timeout) or not 0 < timeout <= 86400:
        raise MoneyPrinterError("Timeout must be finite and between 0 and 86400 seconds.")
    root = Path(root).expanduser().resolve()
    batch_path = Path(batch_path).expanduser().resolve()
    if not batch_path.is_file() or batch_path.stat().st_size > 1024 * 1024:
        raise MoneyPrinterError("A local batch file of at most 1 MiB is required.")
    try:
        batch = _validate_batch(json.loads(batch_path.read_text(encoding="utf-8")))
    except (OSError, UnicodeError, json.JSONDecodeError):
        raise MoneyPrinterError("Cannot read the local draft batch.") from None
    source_batch_sha256 = _hash(batch_path)
    installation = inspect(root, python_executable)
    if not installation["supported"]:
        raise MoneyPrinterError(installation["reason"])
    ffprobe = shutil.which("ffprobe")
    if not ffprobe:
        raise MoneyPrinterError("ffprobe is required to independently verify native output.")
    output_dir = Path(output_dir).expanduser().resolve()
    output_dir.mkdir(parents=True, exist_ok=True)
    if (output_dir / "moneyprinter-receipt.json").exists():
        raise MoneyPrinterError("A receipt already exists here; choose a new output directory.")
    inputs = []
    for task in batch:
        for raw in [task["custom_audio_file"], *(media["url"] for media in task["video_materials"])]:
            file = Path(raw)
            inputs.append({"path": str(file), "sha256": _hash(file), "bytes": file.stat().st_size})
    # Freeze the validated payload so a later change to the supplied batch cannot
    # change what this invocation executes. Never write into the installed config.
    frozen = output_dir / "moneyprinter-batch.json"
    with frozen.open("x", encoding="utf-8") as stream:
        json.dump(batch, stream, ensure_ascii=False, indent=2)
    if _config(root)["config_sha256"] != installation["publishing"]["config_sha256"]:
        raise MoneyPrinterError("MoneyPrinter configuration changed during preflight; inspect before retrying.")
    videos = []
    verified_task_ids = []
    commands = []
    started = time.monotonic()
    if installation["native_mode"] == "batch":
        existing_task_ids = _existing_task_ids(root)
        seen_task_ids = set()
        command = [str(python_executable), str(root / "cli.py"), "--batch-file", str(frozen), "--stop-at", "video"]
        commands.append(command)
        result = _command(command, root, timeout)
        # Never persist native stdout/stderr: dependency diagnostics can contain keys.
        if result.returncode:
            raise MoneyPrinterError(f"MoneyPrinter draft failed (exit {result.returncode}); native diagnostics were withheld to protect credentials.")
        try:
            summary = _native_json(result.stdout, "batch")
            tasks = summary["tasks"]
            if summary.get("total") != len(batch) or summary.get("succeeded") != len(batch) or summary.get("failed") != 0 or len(tasks) != len(batch):
                raise ValueError
            for entry in tasks:
                task_id = entry["task_id"]
                if not isinstance(task_id, str) or task_id in existing_task_ids or task_id in seen_task_ids:
                    raise ValueError
                seen_task_ids.add(task_id)
                content = entry["result"]
                if entry["status"] != "succeeded" or content.get("cross_post_state") is not None:
                    raise ValueError
                paths = content["videos"]
                if not isinstance(paths, list) or len(paths) != 1:
                    raise ValueError
                videos.extend(_managed_video_paths(paths, root, task_id))
                verified_task_ids.append(task_id)
        except (ValueError, KeyError, TypeError, AttributeError):
            raise MoneyPrinterError("Native result is invalid, incomplete, outside managed tasks, or indicates publishing.") from None
    else:
        for task_index, task in enumerate(batch, 1):
            if _config(root)["config_sha256"] != installation["publishing"]["config_sha256"]:
                raise MoneyPrinterError("MoneyPrinter configuration changed during preflight; inspect before retrying.")
            materials = _legacy_material_paths(task, output_dir, task_index)
            task_id = str(uuid.uuid4())
            if (root / "storage/tasks" / task_id).exists():
                raise MoneyPrinterError("Generated MoneyPrinter task id already exists; inspect before retrying.")
            command = _single_task_command(root, python_executable, task, materials, task_id)
            commands.append(command)
            result = _command(command, root, timeout)
            if result.returncode:
                raise MoneyPrinterError(f"MoneyPrinter draft failed (exit {result.returncode}); native diagnostics were withheld to protect credentials.")
            try:
                native_result = _native_json(result.stdout, "single")
                if native_result["task_id"] != task_id:
                    raise ValueError
                content = native_result["result"]
                paths = content["videos"]
                if content.get("cross_post_state") is not None or not isinstance(paths, list) or len(paths) != 1:
                    raise ValueError
                videos.extend(_managed_video_paths(paths, root, task_id))
                verified_task_ids.append(task_id)
            except (ValueError, KeyError, TypeError, AttributeError):
                raise MoneyPrinterError("Native result is invalid, incomplete, outside managed tasks, or indicates publishing.") from None
            if _config(root)["config_sha256"] != installation["publishing"]["config_sha256"]:
                raise MoneyPrinterError("MoneyPrinter configuration changed during rendering; inspect before retrying.")
    elapsed = time.monotonic() - started
    artifacts = []
    for item in inputs:
        path = Path(item["path"])
        if not path.is_file() or _hash(path) != item["sha256"]:
            raise MoneyPrinterError("A source asset changed during rendering; the draft is not reproducible.")
    for index, path in enumerate(videos, 1):
        metadata = _probe(path, ffprobe)
        if (metadata["width"], metadata["height"]) != _RESOLUTIONS[batch[index - 1]["video_aspect"]]:
            raise MoneyPrinterError("MoneyPrinter output dimensions do not match the requested native aspect ratio.")
        destination = output_dir / f"moneyprinter-draft-{index:02d}.mp4"
        with path.open("rb") as source, destination.open("xb") as target:
            shutil.copyfileobj(source, target)
        artifacts.append({"path": str(destination), "sha256": _hash(destination), "bytes": destination.stat().st_size, **metadata})
    receipt = {"schema": "famtastic.moneyprinter-draft.v1", "status": "rendered_draft", "draft_only": True,
               "installation": installation, "command": commands if len(commands) > 1 else commands[0], "duration_seconds": elapsed,
               "native_task_ids": verified_task_ids, "batch_sha256": _hash(frozen), "source_batch_sha256": source_batch_sha256, "inputs": inputs,
               "outputs": artifacts, "provider": "moneyprinterturbo_local", "model": None,
               "cost": {"external_provider_spend_usd": 0, "status": "local_inputs_only_by_contract", "electricity_cost": "unmeasured"},
               "network_monitoring": "not_measured", "approval_state": "unreviewed", "limitations": list(_LIMITATIONS)}
    with (output_dir / "moneyprinter-receipt.json").open("x", encoding="utf-8") as stream:
        json.dump(receipt, stream, ensure_ascii=False, indent=2)
    return receipt
