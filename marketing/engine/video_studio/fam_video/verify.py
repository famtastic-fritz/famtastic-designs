"""FFprobe technical checks and FFmpeg contact sheets; visual approval is separate."""
from __future__ import annotations

import json
import math
import shutil
import subprocess
import tempfile
from fractions import Fraction
from pathlib import Path


def _number(value) -> float | None:
    try:
        number = float(Fraction(str(value)))
        return number if math.isfinite(number) else None
    except (ValueError, TypeError, ZeroDivisionError):
        return None


def verify_video(path: Path, expected: dict | None = None) -> dict:
    """Check encoded metadata, optionally enforcing dimensions/fps/duration/audio.

    Expected accepts width, height, fps, duration_seconds (or duration),
    duration_tolerance (default max(0.15, 2/fps)), codec and audio (bool).
    This does not certify visual quality, text accuracy, identity, rights or approval.
    """
    path = Path(path).resolve()
    result = {"schema": "famtastic.video-verification.v1", "path": str(path), "status": "failed", "passed": False,
              "failures": [], "warnings": [], "video": None, "audio": [], "duration_seconds": None,
              "review": "review_pending"}
    expected = expected or {}
    executable = shutil.which("ffprobe")
    if not path.is_file():
        result["failures"].append("Video file does not exist.")
        return result
    if not executable:
        result["failures"].append("ffprobe is not installed or is not on PATH.")
        return result
    command = [executable, "-v", "error", "-show_format", "-show_streams", "-of", "json", str(path)]
    result["command"] = command
    try:
        process = subprocess.run(command, capture_output=True, text=True, timeout=45, check=False)
        if process.returncode:
            result["failures"].append("ffprobe could not read media: " + process.stderr.strip()[:600])
            return result
        raw = json.loads(process.stdout)
    except (OSError, subprocess.TimeoutExpired, ValueError) as exc:
        result["failures"].append("ffprobe failed: " + str(exc))
        return result
    streams = raw.get("streams", [])
    videos = [stream for stream in streams if stream.get("codec_type") == "video" and not stream.get("disposition", {}).get("attached_pic")]
    if not videos:
        result["failures"].append("No video stream exists.")
        return result
    stream = videos[0]
    video = {"codec": stream.get("codec_name"), "width": stream.get("width"), "height": stream.get("height"),
             "pixel_format": stream.get("pix_fmt"), "fps": _number(stream.get("avg_frame_rate")) or _number(stream.get("r_frame_rate")),
             "frame_count": stream.get("nb_frames"), "duration_seconds": _number(stream.get("duration"))}
    result["video"] = video
    result["audio"] = [{"codec": item.get("codec_name"), "channels": item.get("channels"),
                         "sample_rate": _number(item.get("sample_rate")), "duration_seconds": _number(item.get("duration"))}
                        for item in streams if item.get("codec_type") == "audio"]
    # Prefer the selected video stream duration for video-specific acceptance.
    duration = video["duration_seconds"] or _number(raw.get("format", {}).get("duration"))
    result["duration_seconds"] = duration
    result["container_duration_seconds"] = _number(raw.get("format", {}).get("duration"))
    result["size_bytes"] = path.stat().st_size
    if not duration or duration <= 0:
        result["failures"].append("No positive video duration was reported.")
    if not video["width"] or not video["height"]:
        result["failures"].append("Video dimensions are missing.")
    for field in ("width", "height", "codec"):
        if field in expected and video[field] != expected[field]:
            result["failures"].append(f"Expected {field}={expected[field]}, found {video[field]}.")
    if "fps" in expected and (video["fps"] is None or abs(video["fps"] - float(expected["fps"])) > 0.01):
        result["failures"].append(f"Expected fps={expected['fps']}, found {video['fps']}.")
    target = expected.get("duration_seconds", expected.get("duration"))
    if target is not None:
        tolerance = float(expected.get("duration_tolerance", max(0.15, 2 / (video["fps"] or 24))))
        if duration is None or abs(duration - float(target)) > tolerance:
            result["failures"].append(f"Expected duration {target}s ± {tolerance:.3f}s, found {duration}s.")
    if "audio" in expected and bool(result["audio"]) != bool(expected["audio"]):
        result["failures"].append("Audio stream presence does not match the expected audio setting.")
    if not result["audio"]:
        result["warnings"].append("No audio stream; this may be intentional for a silent proof.")
    if len(videos) > 1:
        result["warnings"].append("Multiple video streams exist; checks describe the first non-cover-art stream.")
    result["passed"] = not result["failures"]
    result["status"] = "passed" if result["passed"] else "failed"
    result["limits"] = ["Metadata check only; no full-frame decode or visual approval is implied."]
    return result


def contact_sheet(video: Path, destination: Path, times: list[float]) -> dict:
    """Extract specified real frames and tile them; never adds rendered text.

    Returned times preserve order. The sheet has up to four 320px columns.
    A failed extraction cannot reuse an old frame or silently substitute a frame.
    """
    video, destination = Path(video).resolve(), Path(destination).resolve()
    if not times or len(times) > 40:
        raise ValueError("Provide between 1 and 40 frame times.")
    if any(not isinstance(value, (int, float)) or not math.isfinite(value) or value < 0 for value in times):
        raise ValueError("Frame times must be finite, nonnegative seconds.")
    if destination.suffix.lower() not in {".png", ".jpg", ".jpeg"}:
        raise ValueError("Contact-sheet destination must be PNG or JPEG.")
    if destination == video:
        raise ValueError("Contact-sheet output cannot replace the source video.")
    media = verify_video(video)
    if not media["passed"]:
        raise ValueError("Cannot create contact sheet: " + "; ".join(media["failures"]))
    if any(value >= media["duration_seconds"] for value in times):
        raise ValueError("Every requested frame must occur before the end of the video.")
    executable = shutil.which("ffmpeg")
    if not executable:
        raise RuntimeError("ffmpeg is not installed or is not on PATH.")
    destination.parent.mkdir(parents=True, exist_ok=True)
    commands = []
    columns = min(4, len(times))
    rows = math.ceil(len(times) / columns)
    with tempfile.TemporaryDirectory(prefix="contact-sheet-", dir=destination.parent) as temporary:
        temp = Path(temporary)
        for index, timestamp in enumerate(times):
            frame = temp / f"frame-{index:03d}.png"
            command = [executable, "-hide_banner", "-loglevel", "error", "-nostdin", "-y", "-ss", str(timestamp),
                       "-i", str(video), "-frames:v", "1", "-vf", "scale=320:-2", "-threads", "1", str(frame)]
            commands.append(command)
            process = subprocess.run(command, capture_output=True, text=True, timeout=60, check=False)
            if process.returncode or not frame.is_file():
                raise RuntimeError(f"Frame extraction failed at {timestamp}s: {process.stderr.strip()[:400]}")
        temporary_output = temp / ("sheet" + destination.suffix)
        command = [executable, "-hide_banner", "-loglevel", "error", "-nostdin", "-y", "-framerate", "1", "-i", str(temp / "frame-%03d.png"),
                   "-vf", f"tile={columns}x{rows}:nb_frames={len(times)}:padding=8:margin=8:color=0x101010",
                   "-frames:v", "1", "-threads", "1", str(temporary_output)]
        commands.append(command)
        process = subprocess.run(command, capture_output=True, text=True, timeout=60, check=False)
        if process.returncode or not temporary_output.is_file():
            raise RuntimeError("Contact-sheet tiling failed: " + process.stderr.strip()[:400])
        temporary_output.replace(destination)
    return {"status": "passed", "path": str(destination), "frame_times_seconds": times,
            "columns": columns, "rows": rows, "commands": commands, "review": "review_pending"}
