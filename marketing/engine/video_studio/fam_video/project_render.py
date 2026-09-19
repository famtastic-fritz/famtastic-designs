"""Render a locally authored HyperFrames project with frozen inputs and Build DNA."""
from __future__ import annotations

import base64
import hashlib
import html
import json
import math
import os
import re
import shutil
import subprocess
import time
import uuid
from html.parser import HTMLParser
from fractions import Fraction
from pathlib import Path, PurePosixPath
from urllib.parse import unquote, urlsplit

from .adapters import hyperframes
from .campaign import sha256
from .evidence import Ledger
from .verify import contact_sheet, verify_video


SCHEMA = "famtastic.local-hyperframes-project.v1"
CREATOR_MARKER = 'data-famtastic-creator-credit="v1"'
_ID = re.compile(r"^[a-z0-9][a-z0-9-]{0,79}$")
_HEX = re.compile(r"^[a-f0-9]{64}$")
_TEXT_SUFFIXES = {".html", ".htm", ".css", ".js", ".mjs", ".cjs", ".svg"}
_RESERVED = {"hyperframes-check.log", "hyperframes-render.log", "ffprobe.json"}
_AUDIO_MASTER_LOG = "audio-mastering.log"


class ProjectError(ValueError):
    """An invalid or unsafe local HyperFrames project."""


def _digest_file(path: Path) -> str:
    digest = hashlib.sha256()
    with Path(path).open("rb") as stream:
        for block in iter(lambda: stream.read(1024 * 1024), b""):
            digest.update(block)
    return digest.hexdigest()


def _safe_relative(value, label: str) -> str:
    if not isinstance(value, str) or not value or "\\" in value:
        raise ProjectError(f"{label} must be a nonempty project-relative POSIX path.")
    path = PurePosixPath(value)
    if path.is_absolute() or any(part in {"", ".", ".."} for part in path.parts):
        raise ProjectError(f"{label} must not escape the project directory.")
    return path.as_posix()


def _resolve_project_file(project_root: Path, relative: str, label: str) -> Path:
    candidate = project_root / PurePosixPath(relative)
    try:
        resolved = candidate.resolve(strict=True)
    except OSError as exc:
        raise ProjectError(f"{label} is missing or unreadable: {relative}") from exc
    if not resolved.is_relative_to(project_root):
        raise ProjectError(f"{label} resolves outside the project directory: {relative}")
    if not resolved.is_file():
        raise ProjectError(f"{label} is not a file: {relative}")
    return resolved


def _positive_stream_duration(stream: dict) -> float | None:
    """Return the selected stream's duration without borrowing container duration."""
    raw = stream.get("duration")
    try:
        duration = float(raw)
    except (TypeError, ValueError):
        duration = None
    if duration is not None and math.isfinite(duration) and duration > 0:
        return duration
    try:
        duration = float(Fraction(str(stream["duration_ts"])) * Fraction(str(stream["time_base"])))
    except (KeyError, TypeError, ValueError, ZeroDivisionError, OverflowError):
        return None
    return duration if math.isfinite(duration) and duration > 0 else None


def _probe_audio_master(path: Path, max_duration: float) -> dict:
    """Require one finite, positive AAC stream suitable for lossless mastering."""
    executable = shutil.which("ffprobe")
    if not executable:
        raise ProjectError("ffprobe is required to validate the declared audio master.")
    command = [executable, "-v", "error", "-select_streams", "a", "-show_entries",
               "stream=index,codec_type,codec_name,duration,duration_ts,time_base", "-of", "json", str(path)]
    try:
        process = subprocess.run(command, capture_output=True, text=True, timeout=45, check=False)
    except (OSError, subprocess.TimeoutExpired) as exc:
        raise ProjectError(f"Could not probe the declared audio master: {exc}") from exc
    if process.returncode:
        raise ProjectError("ffprobe could not read the declared audio master: " + process.stderr.strip()[:500])
    try:
        raw = json.loads(process.stdout)
    except (TypeError, json.JSONDecodeError) as exc:
        raise ProjectError("ffprobe returned invalid JSON for the declared audio master.") from exc
    streams = raw.get("streams") if isinstance(raw, dict) else None
    if not isinstance(streams, list) or len(streams) != 1:
        raise ProjectError("Audio master must contain exactly one audio stream.")
    stream = streams[0]
    if not isinstance(stream, dict) or stream.get("codec_type") != "audio" or stream.get("codec_name") != "aac":
        raise ProjectError("Audio master must contain exactly one AAC audio stream.")
    duration = _positive_stream_duration(stream)
    if duration is None:
        raise ProjectError("Audio master must report a finite, positive audio-stream duration.")
    if duration > max_duration + 1e-9:
        raise ProjectError("Audio master may not exceed the composition duration by more than one frame.")
    return {"command": command, "stream_index": stream.get("index"), "codec": "aac",
            "duration_seconds": duration}


def _command_text(value) -> str:
    if value is None:
        return ""
    if isinstance(value, bytes):
        return value.decode("utf-8", errors="replace")
    return str(value)


def _run_audio_master_copy(command: list[str], log_path: Path, *, timeout: int,
                           raw_video_sha256: str, audio_master_sha256: str) -> dict:
    """Stream-copy the source AAC beside the retained native HyperFrames render."""
    log = {"status": "running", "method": "ffmpeg_stream_copy", "command": command,
           "inputs": {"native_video_sha256": raw_video_sha256, "audio_master_sha256": audio_master_sha256}}
    _atomic_json(log_path, log)
    try:
        process = subprocess.run(command, capture_output=True, text=True, timeout=timeout, check=False)
    except subprocess.TimeoutExpired as exc:
        log.update({"status": "failed", "failure": "timeout", "timeout_seconds": timeout,
                    "stdout": _command_text(exc.stdout)[-4000:], "stderr": _command_text(exc.stderr)[-4000:]})
        _atomic_json(log_path, log)
        raise ProjectError(f"FFmpeg audio-master stream copy timed out after {timeout}s; native render was retained.") from exc
    except OSError as exc:
        log.update({"status": "failed", "failure": "execution_error", "error_type": type(exc).__name__,
                    "stderr": str(exc)[:1000]})
        _atomic_json(log_path, log)
        raise ProjectError("FFmpeg audio-master stream copy could not start; native render was retained.") from exc
    if process.returncode:
        log.update({"status": "failed", "failure": "nonzero_exit", "returncode": process.returncode,
                    "stdout": _command_text(process.stdout)[-4000:], "stderr": _command_text(process.stderr)[-4000:]})
        _atomic_json(log_path, log)
        raise ProjectError("FFmpeg audio-master stream copy failed: " + _command_text(process.stderr).strip()[:500])
    output = Path(command[-1])
    if not output.is_file() or output.stat().st_size <= 0:
        log.update({"status": "failed", "failure": "missing_or_empty_output"})
        _atomic_json(log_path, log)
        raise ProjectError("FFmpeg audio-master stream copy produced no final video; native render was retained.")
    output_sha256 = _digest_file(output)
    log.update({"status": "passed", "returncode": process.returncode, "output": str(output),
                "output_sha256": output_sha256, "stdout": _command_text(process.stdout)[-4000:],
                "stderr": _command_text(process.stderr)[-4000:]})
    _atomic_json(log_path, log)
    return {"command": command, "returncode": process.returncode, "output": str(output),
            "output_sha256": output_sha256, "log": str(log_path)}


def _strip_comments(text: str, *, javascript: bool) -> str:
    """Blank comments without rejecting URLs mentioned in source comments."""
    out = list(text)
    quote = None
    escaped = False
    line_comment = False
    block_comment = False
    index = 0
    while index < len(text):
        char = text[index]
        following = text[index + 1] if index + 1 < len(text) else ""
        if line_comment:
            if char == "\n":
                line_comment = False
            else:
                out[index] = " "
        elif block_comment:
            if char == "*" and following == "/":
                out[index] = out[index + 1] = " "
                index += 1
                block_comment = False
            elif char != "\n":
                out[index] = " "
        elif quote:
            if escaped:
                escaped = False
            elif char == "\\":
                escaped = True
            elif char == quote:
                quote = None
        elif char in "'\"`":
            quote = char
        elif char == "/" and following == "*":
            out[index] = out[index + 1] = " "
            index += 1
            block_comment = True
        elif javascript and char == "/" and following == "/":
            out[index] = out[index + 1] = " "
            index += 1
            line_comment = True
        index += 1
    return "".join(out)


def _strip_js_strings(text: str) -> str:
    out = list(text)
    quote = None
    escaped = False
    for index, char in enumerate(text):
        if quote:
            if char != "\n":
                out[index] = " "
            if escaped:
                escaped = False
            elif char == "\\":
                escaped = True
            elif char == quote:
                quote = None
        elif char in "'\"`":
            out[index] = " "
            quote = char
    return "".join(out)


def _srcset_urls(value: str) -> list[str]:
    """Extract candidate URLs while keeping the comma inside a data URL."""
    urls = []
    index = 0
    while index < len(value):
        while index < len(value) and (value[index].isspace() or value[index] == ","):
            index += 1
        if index >= len(value):
            break
        start = index
        data_url = value[index:index + 5].lower() == "data:"
        while index < len(value) and not value[index].isspace() and (data_url or value[index] != ","):
            index += 1
        url = value[start:index].rstrip(",")
        if url:
            urls.append(url)
        while index < len(value) and value[index] != ",":
            index += 1
        if index < len(value):
            index += 1
    return urls


def _asset_reference(raw: str, current: str, project_root: Path, listed_targets: set[str], label: str) -> None:
    value = html.unescape(raw).strip().strip("\"'")
    if not value or value.startswith("#"):
        return
    parsed = urlsplit(value)
    if parsed.scheme.lower() == "data":
        return
    if parsed.scheme or parsed.netloc or value.startswith("//"):
        raise ProjectError(f"Remote fetched assets are not allowed ({label}).")
    pathname = unquote(parsed.path)
    if not pathname:
        return
    if pathname.startswith("/") or "\\" in pathname:
        raise ProjectError(f"Fetched asset must use a project-relative POSIX path ({label}).")
    try:
        target = (project_root / PurePosixPath(current).parent / PurePosixPath(pathname)).resolve(strict=True)
    except OSError as exc:
        raise ProjectError(f"Fetched local asset is missing or unreadable ({label}): {pathname}") from exc
    if not target.is_relative_to(project_root):
        raise ProjectError(f"Fetched asset escapes the project directory ({label}).")
    resolved_relative = target.relative_to(project_root).as_posix()
    if resolved_relative not in listed_targets:
        raise ProjectError(f"Fetched local asset is absent from manifest files: {resolved_relative}")


def _scan_css(source: str, current: str, root: Path, listed_targets: set[str]) -> None:
    clean = _strip_comments(source, javascript=False)
    refs = [match.group(2) for match in re.finditer(r"url\(\s*(['\"]?)(.*?)\1\s*\)", clean, re.I)]
    refs.extend(match.group(2) for match in re.finditer(r"@import\s+(['\"])(.*?)\1", clean, re.I))
    for reference in refs:
        _asset_reference(reference, current, root, listed_targets, "CSS resource")


def _scan_javascript(source: str, current: str, root: Path, listed_targets: set[str]) -> None:
    clean = _strip_comments(source, javascript=True)
    code = _strip_js_strings(clean)
    if re.search(r"\b(?:fetch|XMLHttpRequest|WebSocket|EventSource|importScripts|sendBeacon)\b", code):
        raise ProjectError("Project scripts may not fetch network resources.")
    patterns = (
        r"\bimport\s*\(\s*(['\"])(.*?)\1\s*\)",
        r"\bimport\s+(['\"])(.*?)\1",
        r"\bexport\b[^;\n]*?\bfrom\s*(['\"])(.*?)\1",
        r"\bimport\b[^;\n]*?\bfrom\s*(['\"])(.*?)\1",
        r"\brequire\s*\(\s*(['\"])(.*?)\1\s*\)",
        r"\bnew\s+URL\s*\(\s*(['\"])(.*?)\1\s*,\s*import\.meta\.url\s*\)",
        r"\bnew\s+(?:Worker|SharedWorker|Audio|Image)\s*\(\s*(['\"])(.*?)\1",
        r"\bsetAttribute\s*\(\s*(['\"])(?:src|href|poster|data)\1\s*,\s*(['\"])(.*?)\2\s*\)",
        r"\.(?:src|href|poster)\s*=\s*(['\"])(.*?)\1",
    )
    for pattern in patterns:
        for match in re.finditer(pattern, clean, re.S):
            reference_group = 3 if "setAttribute" in pattern else 2
            _asset_reference(match.group(reference_group), current, root, listed_targets, "JavaScript resource")


class _HTMLResources(HTMLParser):
    def __init__(self, current: str, root: Path, listed_targets: set[str]):
        super().__init__(convert_charrefs=True)
        self.current, self.root, self.listed_targets = current, root, listed_targets
        self.script_type = None
        self.style_depth = 0

    def _attrs(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        values = {key.lower(): value or "" for key, value in attrs}
        fetch_attrs = {
            "script": ("src",), "img": ("src", "srcset"), "video": ("src", "poster"),
            "audio": ("src",), "source": ("src", "srcset"), "track": ("src",),
            "iframe": ("src",), "embed": ("src",), "object": ("data",),
            "input": ("src",), "link": ("href",), "image": ("href", "xlink:href"),
            "use": ("href", "xlink:href"), "feimage": ("href", "xlink:href"),
            "body": ("background",), "table": ("background",), "tr": ("background",), "td": ("background",),
        }
        for key in fetch_attrs.get(tag, ()):
            if not values.get(key):
                continue
            raw = values[key]
            refs = _srcset_urls(raw) if key == "srcset" else [raw]
            for ref in refs:
                self_ref = ref.strip().split()[0] if ref.strip() else ""
                if self_ref:
                    _asset_reference(self_ref, self.current, self.root, self.listed_targets, f"<{tag} {key}>")
        if values.get("data-composition-src"):
            _asset_reference(values["data-composition-src"], self.current, self.root, self.listed_targets, "sub-composition")
        if values.get("style"):
            _scan_css(values["style"], self.current, self.root, self.listed_targets)
        if tag == "meta" and values.get("http-equiv", "").lower() == "refresh":
            match = re.search(r"url\s*=\s*([^;]+)", values.get("content", ""), re.I)
            if match:
                _asset_reference(match.group(1), self.current, self.root, self.listed_targets, "meta refresh")
        if tag == "script":
            self.script_type = values.get("type", "").lower()
        elif tag == "style":
            self.style_depth += 1

    def handle_starttag(self, tag, attrs):
        self._attrs(tag.lower(), attrs)

    def handle_startendtag(self, tag, attrs):
        self._attrs(tag.lower(), attrs)

    def handle_endtag(self, tag):
        tag = tag.lower()
        if tag == "script":
            self.script_type = None
        elif tag == "style" and self.style_depth:
            self.style_depth -= 1

    def handle_data(self, data):
        if self.script_type in {"", "text/javascript", "module", "application/javascript"}:
            _scan_javascript(data, self.current, self.root, self.listed_targets)
        elif self.style_depth:
            _scan_css(data, self.current, self.root, self.listed_targets)


class _CreatorCreditProbe(HTMLParser):
    """Confirm the canonical row is live markup and embeds the configured PNG."""

    def __init__(self, expected_logo_sha256: str):
        super().__init__(convert_charrefs=True)
        self.expected_logo_sha256 = expected_logo_sha256
        self.marker_found = False
        self.marker_count = 0
        self.embedded_logo_matches = False
        self.marker_depth = 0
        self.body_depth = 0
        self.inert_tags = []

    def handle_starttag(self, tag, attrs):
        tag = tag.lower()
        values = {key.lower(): value or "" for key, value in attrs}
        if tag == "body":
            self.body_depth += 1
        if tag in {"template", "noscript"}:
            self.inert_tags.append(tag)
            return
        if self.inert_tags:
            return
        if self.body_depth and tag == "div" and values.get("data-famtastic-creator-credit") == "v1":
            self.marker_found = True
            self.marker_count += 1
            self.marker_depth = 1
            return
        if not self.marker_depth:
            return
        if tag == "div":
            self.marker_depth += 1
        elif tag == "img":
            source = values.get("src", "")
            prefix = "data:image/png;base64,"
            if source.startswith(prefix):
                try:
                    content = base64.b64decode(source[len(prefix):], validate=True)
                    self.embedded_logo_matches = hashlib.sha256(content).hexdigest() == self.expected_logo_sha256
                except (ValueError, base64.binascii.Error):
                    self.embedded_logo_matches = False

    def handle_startendtag(self, tag, attrs):
        self.handle_starttag(tag, attrs)
        self.handle_endtag(tag)

    def handle_endtag(self, tag):
        tag = tag.lower()
        if tag in self.inert_tags:
            self.inert_tags.reverse()
            self.inert_tags.remove(tag)
            self.inert_tags.reverse()
        elif self.inert_tags:
            return
        if tag == "body" and self.body_depth:
            self.body_depth -= 1
        if tag == "div" and self.marker_depth:
            self.marker_depth -= 1


class _MarkupEvents(HTMLParser):
    """A small parsed event stream, so comments and script strings cannot fake markup."""

    def __init__(self):
        super().__init__(convert_charrefs=False)
        self.events = []

    def handle_starttag(self, tag, attrs):
        self.events.append(("start", tag.lower(), tuple((key.lower(), value) for key, value in attrs)))

    def handle_startendtag(self, tag, attrs):
        self.events.append(("startend", tag.lower(), tuple((key.lower(), value) for key, value in attrs)))

    def handle_endtag(self, tag):
        self.events.append(("end", tag.lower()))

    def handle_data(self, data):
        if data:
            self.events.append(("data", data))

    def handle_entityref(self, name):
        self.events.append(("entity", name))

    def handle_charref(self, name):
        self.events.append(("charref", name))


def _parsed_markup_events(source: str) -> list[tuple]:
    parser = _MarkupEvents()
    parser.feed(source)
    parser.close()
    return parser.events


def _has_event_sequence(events: list[tuple], expected: list[tuple]) -> bool:
    return bool(expected) and any(events[index:index + len(expected)] == expected
                                  for index in range(len(events) - len(expected) + 1))


def _scan_project_references(project_root: Path, listed_files: list[str], resolved_paths: dict[str, Path]) -> None:
    listed_targets = {path.resolve().relative_to(project_root).as_posix() for path in resolved_paths.values()}
    for relative in listed_files:
        suffix = Path(relative).suffix.lower()
        if suffix not in _TEXT_SUFFIXES:
            continue
        path = resolved_paths[relative]
        try:
            source = path.read_text(encoding="utf-8")
        except (OSError, UnicodeError) as exc:
            raise ProjectError(f"Text asset is not valid UTF-8: {relative}") from exc
        if suffix in {".html", ".htm", ".svg"}:
            parser = _HTMLResources(relative, project_root, listed_targets)
            parser.feed(source)
            parser.close()
            for match in re.finditer(r"<style\b[^>]*>(.*?)</style\s*>", source, re.I | re.S):
                _scan_css(match.group(1), relative, project_root, listed_targets)
        elif suffix == ".css":
            _scan_css(source, relative, project_root, listed_targets)
        else:
            _scan_javascript(source, relative, project_root, listed_targets)


def _canonical_credit_markup(brand: dict) -> str:
    module = Path(brand.get("credit_module", ""))
    node = shutil.which("node")
    if not module.is_file() or not node:
        raise ProjectError("Configured creator-credit module and local Node.js are required.")
    source = (
        "import {pathToFileURL} from 'node:url';"
        "const credit=await import(pathToFileURL(process.argv[1]).href);"
        "process.stdout.write(credit.creatorCreditHtml({embedded:true}));"
    )
    try:
        result = subprocess.run([node, "--input-type=module", "-e", source, str(module.resolve())],
                                capture_output=True, text=True, timeout=20, check=False)
    except (OSError, subprocess.TimeoutExpired) as exc:
        raise ProjectError("Canonical creator-credit markup could not be generated locally.") from exc
    if result.returncode or not result.stdout:
        raise ProjectError("Canonical creator-credit markup could not be generated locally.")
    return result.stdout


def _parse_project(project_dir: Path, manifest_name: str, brand: dict) -> dict:
    project_root = Path(project_dir).expanduser().resolve(strict=True)
    if not project_root.is_dir():
        raise ProjectError("Project directory must be a local directory.")
    manifest_rel = _safe_relative(manifest_name, "Manifest path")
    manifest_path = _resolve_project_file(project_root, manifest_rel, "Project manifest")
    manifest_bytes = manifest_path.read_bytes()
    try:
        manifest = json.loads(manifest_bytes.decode("utf-8"))
    except (UnicodeError, json.JSONDecodeError) as exc:
        raise ProjectError("Project manifest must be valid UTF-8 JSON.") from exc
    if not isinstance(manifest, dict) or manifest.get("schema") != SCHEMA:
        raise ProjectError(f"Expected {SCHEMA}.")
    project_id = manifest.get("id")
    if not isinstance(project_id, str) or not _ID.fullmatch(project_id):
        raise ProjectError("Project id must be a lowercase public slug.")
    title = manifest.get("title")
    if not isinstance(title, str) or not title.strip() or len(title) > 160:
        raise ProjectError("Project title must be nonempty text of at most 160 characters.")
    entrypoint = _safe_relative(manifest.get("entrypoint", "index.html"), "Entrypoint")
    if entrypoint != "index.html":
        raise ProjectError("This project format uses index.html as its HyperFrames entrypoint.")
    width, height, fps = manifest.get("width"), manifest.get("height"), manifest.get("fps")
    if type(width) is not int or type(height) is not int or not (1 <= width <= 16384 and 1 <= height <= 16384):
        raise ProjectError("Project width and height must be positive integers up to 16384.")
    if type(fps) is not int or not 1 <= fps <= 240:
        raise ProjectError("Project fps must be an integer between 1 and 240.")
    duration = manifest.get("duration_seconds")
    if isinstance(duration, bool) or not isinstance(duration, (int, float)) or not math.isfinite(duration) or not 0 < duration <= 600:
        raise ProjectError("Project duration_seconds must be positive, finite, and at most 600.")
    frame_count = round(duration * fps)
    if frame_count < 1 or abs(duration * fps - frame_count) > 1e-6:
        raise ProjectError("Project duration must align to a whole number of frames at the declared fps.")
    if manifest.get("creator_credit_marker") != CREATOR_MARKER:
        raise ProjectError("Project manifest must declare the canonical creator-credit marker.")
    logo = manifest.get("brand_logo")
    if not isinstance(logo, dict) or set(logo) != {"path", "sha256"}:
        raise ProjectError("Project brand_logo must contain exactly path and sha256.")
    logo_rel = _safe_relative(logo.get("path"), "Brand logo path")
    logo_digest = logo.get("sha256")
    if not isinstance(logo_digest, str) or not _HEX.fullmatch(logo_digest):
        raise ProjectError("Project brand_logo.sha256 must be lowercase SHA-256.")
    if logo_digest != brand.get("logo_sha256") or _digest_file(Path(brand["logo"])) != logo_digest:
        raise ProjectError("Project brand logo does not match the configured canonical logo.")
    files = manifest.get("files")
    if not isinstance(files, list) or not 1 <= len(files) <= 200:
        raise ProjectError("Project files must list 1–200 local source and asset paths.")
    files = [_safe_relative(item, "Project file") for item in files]
    if len(files) != len(set(files)):
        raise ProjectError("Project files must not contain duplicate paths.")
    if manifest_rel in files:
        raise ProjectError("The manifest is snapshotted automatically; do not repeat it in files.")
    if entrypoint not in files or logo_rel not in files:
        raise ProjectError("Project files must include index.html and brand_logo.path.")
    if any(Path(item).name in _RESERVED for item in files):
        raise ProjectError("Project files may not shadow HyperFrames renderer logs.")
    resolved_paths = {item: _resolve_project_file(project_root, item, "Project asset") for item in files}
    if _digest_file(resolved_paths[logo_rel]) != logo_digest:
        raise ProjectError("Listed project logo bytes do not match the configured canonical logo.")
    audio_master = None
    if "audio_master" in manifest:
        declared_master = manifest["audio_master"]
        if not isinstance(declared_master, dict) or set(declared_master) != {"path", "mode", "start_seconds"}:
            raise ProjectError("audio_master must contain exactly path, mode, and start_seconds.")
        master_rel = _safe_relative(declared_master.get("path"), "Audio-master path")
        if master_rel not in files:
            raise ProjectError("Audio-master path must be included in the manifest files list.")
        if declared_master.get("mode") != "copy":
            raise ProjectError("audio_master.mode must be copy; this mode never decodes or re-encodes audio.")
        start_seconds = declared_master.get("start_seconds")
        try:
            finite_start = math.isfinite(float(start_seconds))
        except (TypeError, ValueError, OverflowError):
            finite_start = False
        if (isinstance(start_seconds, bool) or not isinstance(start_seconds, (int, float))
                or not finite_start or start_seconds != 0):
            raise ProjectError("audio_master.start_seconds must be exactly 0 for lossless source audio.")
        master_probe = _probe_audio_master(resolved_paths[master_rel], float(duration) + 1 / fps)
        audio_master = {"path": master_rel, "mode": "copy", "start_seconds": 0.0,
                        "source_probe": master_probe}
    entry_html = resolved_paths[entrypoint].read_text(encoding="utf-8")
    canonical_credit = _canonical_credit_markup(brand)
    canonical_events = _parsed_markup_events(canonical_credit)
    if not _has_event_sequence(_parsed_markup_events(entry_html), canonical_events):
        raise ProjectError("index.html must include the exact canonical creator-credit row and embedded logo.")
    credit_probe = _CreatorCreditProbe(logo_digest)
    credit_probe.feed(entry_html)
    credit_probe.close()
    if credit_probe.marker_count != 1 or credit_probe.marker_depth or not credit_probe.embedded_logo_matches:
        raise ProjectError("index.html must render the canonical credit marker with its matching embedded logo.")
    from .adapters.hyperframes import _html_metadata
    try:
        html_metadata = _html_metadata(project_root)
    except (OSError, ValueError) as exc:
        raise ProjectError(f"HyperFrames entrypoint metadata is invalid: {exc}") from exc
    if html_metadata["width"] != width or html_metadata["height"] != height:
        raise ProjectError("HyperFrames composition dimensions must match the manifest.")
    if abs(html_metadata["duration"] - duration) > 1e-9:
        raise ProjectError("HyperFrames composition duration must match the manifest.")
    _scan_project_references(project_root, files, resolved_paths)
    return {
        "project_root": project_root, "manifest_rel": manifest_rel, "manifest_bytes": manifest_bytes,
        "manifest": manifest, "manifest_sha256": hashlib.sha256(manifest_bytes).hexdigest(),
        "project_id": project_id, "title": title, "entrypoint": entrypoint,
        "width": width, "height": height, "fps": fps, "duration_seconds": float(duration),
        "frame_count": frame_count, "files": files, "resolved_paths": resolved_paths,
        "file_hashes": {item: _digest_file(resolved_paths[item]) for item in files},
        "brand_logo_path": logo_rel, "audio_master": audio_master,
    }


def _snapshot_inputs(parsed: dict, source_dir: Path, render_dir: Path) -> list[dict]:
    source_dir.mkdir(parents=True, exist_ok=False)
    render_dir.mkdir(parents=True, exist_ok=False)
    rows = []
    all_files = [parsed["manifest_rel"], *parsed["files"]]
    for relative in all_files:
        if relative == parsed["manifest_rel"]:
            raw = parsed["manifest_bytes"]
            source = None
            expected = parsed["manifest_sha256"]
        else:
            source = parsed["resolved_paths"][relative]
            expected = parsed["file_hashes"][relative]
            raw = None
        for directory in (source_dir, render_dir):
            destination = directory / PurePosixPath(relative)
            destination.parent.mkdir(parents=True, exist_ok=True)
            if raw is not None:
                destination.write_bytes(raw)
            else:
                shutil.copyfile(source, destination)
            digest = _digest_file(destination)
            if digest != expected:
                raise ProjectError(f"Project input changed while it was being snapshotted: {relative}")
            if directory == source_dir:
                destination.chmod(0o444)
                rows.append({"path": relative, "sha256": digest, "size_bytes": destination.stat().st_size,
                             "role": "project_manifest" if raw is not None else (
                                 "brand_logo" if relative == parsed["brand_logo_path"] else
                                 "audio_master_source" if parsed.get("audio_master") and relative == parsed["audio_master"]["path"] else
                                 "project_source_asset")})
            else:
                destination.chmod(0o644)
    for path in source_dir.rglob("*"):
        if path.is_dir():
            path.chmod(0o555)
    for path in render_dir.rglob("*"):
        if path.is_dir():
            path.chmod(0o755)
    return rows


def _engine_hashes(engine_root: Path) -> dict[str, str]:
    return {path.relative_to(engine_root).as_posix(): _digest_file(path)
            for path in sorted(engine_root.rglob("*.py")) if "__pycache__" not in path.parts}


def _identity(parsed: dict, brand: dict, brand_path: Path, provider: dict, quality: str, engine_root: Path) -> str:
    tool_path = Path(provider.get("executable", ""))
    tool_hash = _digest_file(tool_path) if tool_path.is_file() else None
    brand_assets = {}
    for key in ("logo", "display_font", "body_font", "signature_font", "credit_module"):
        path = brand.get(key)
        if path and Path(path).is_file():
            brand_assets[key] = _digest_file(Path(path))
    dependency = Path(brand.get("credit_module", "")).parent.parent / "frontend/src/lib/creatorCredit.js"
    if dependency.is_file():
        brand_assets["creator_credit_styles"] = _digest_file(dependency)
    value = {
        "schema": SCHEMA, "manifest_sha256": parsed["manifest_sha256"],
        "project_files": parsed["file_hashes"], "brand_config_sha256": _digest_file(brand_path),
        "brand": {"name": brand.get("name"), "url": brand.get("url"),
                  "logo_sha256": brand.get("logo_sha256"), "assets": brand_assets},
        "hyperframes": {"executable": provider.get("executable"), "version": provider.get("version"), "sha256": tool_hash},
        "quality": quality, "engine": _engine_hashes(engine_root),
    }
    encoded = json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=False, allow_nan=False).encode()
    return hashlib.sha256(encoded).hexdigest()


def _verify_copied_audio(proof: dict, parsed: dict) -> dict:
    """Check that the final file exposes the one declared AAC master stream."""
    audio = proof.get("audio")
    if not isinstance(audio, list) or len(audio) != 1:
        raise ProjectError("Final video must contain exactly the declared single AAC audio stream.")
    stream = audio[0]
    if not isinstance(stream, dict) or stream.get("codec") != "aac":
        raise ProjectError("Final video did not preserve the declared AAC audio codec.")
    final_duration = stream.get("duration_seconds")
    if (isinstance(final_duration, bool) or not isinstance(final_duration, (int, float))
            or not math.isfinite(final_duration) or final_duration <= 0):
        raise ProjectError("Final AAC stream must have a finite, positive duration.")
    max_duration = parsed["duration_seconds"] + 1 / parsed["fps"]
    if final_duration > max_duration + 1e-9:
        raise ProjectError("Final AAC stream exceeds the composition duration by more than one frame.")
    source_duration = parsed["audio_master"]["source_probe"]["duration_seconds"]
    if abs(final_duration - source_duration) > 1 / parsed["fps"] + 1e-9:
        raise ProjectError("Final AAC stream duration differs from the declared master by more than one frame.")
    return {"mode": "copy", "start_seconds": 0.0, "codec": "aac",
            "source_path": parsed["audio_master"]["path"],
            "source_sha256": parsed["file_hashes"][parsed["audio_master"]["path"]],
            "source_probe": parsed["audio_master"]["source_probe"],
            "final_stream": {"codec": "aac", "duration_seconds": final_duration},
            "verification": "single AAC stream; codec and duration match the declared source master"}


def _atomic_json(path: Path, value: dict) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    temporary = path.with_name(f".{path.name}.{uuid.uuid4().hex}.tmp")
    temporary.write_text(json.dumps(value, indent=2, ensure_ascii=False, allow_nan=False) + "\n", encoding="utf-8")
    os.replace(temporary, path)


def _ensure_repo_directory(path: Path, repo_root: Path, label: str) -> Path:
    try:
        path.mkdir(parents=True, exist_ok=True)
        resolved = path.resolve(strict=True)
    except OSError as exc:
        raise ProjectError(f"Cannot prepare {label} inside the repository.") from exc
    if not resolved.is_relative_to(repo_root):
        raise ProjectError(f"{label} resolves outside the repository.")
    return resolved


def render_project(
    project_dir: Path, repo_root: Path, brand: dict, brand_path: Path, *, manifest_name: str = "project.json",
    executable: str = "hyperframes", quality: str = "draft", timeout: int = 1800, no_cache: bool = False,
) -> dict:
    """Snapshot, locally render, verify and retain evidence for one authored project."""
    if quality not in {"draft", "looks", "delivery"}:
        raise ProjectError("Quality must be draft, looks, or delivery.")
    if type(timeout) is not int or not 1 <= timeout <= 86400:
        raise ProjectError("Timeout must be an integer between 1 and 86400 seconds.")
    repo_root = Path(repo_root).expanduser().resolve(strict=True)
    brand_path = Path(brand_path).expanduser().resolve(strict=True)
    parsed = _parse_project(project_dir, manifest_name, brand)
    provider = hyperframes.inspect(executable)
    if not provider.get("available") or not provider.get("ok"):
        raise ProjectError(provider.get("reason", "Selected local HyperFrames installation is not ready."))
    engine_root = Path(__file__).resolve().parent
    key = _identity(parsed, brand, brand_path, provider, quality, engine_root)
    artifact_root = _ensure_repo_directory(repo_root / "artifacts/video-studio", repo_root, "Video Studio evidence directory")
    cache_root = _ensure_repo_directory(artifact_root / "cache", repo_root, "Video Studio cache directory")
    cache = cache_root / f"project-{key}.json"
    if cache.is_file() and not no_cache:
        try:
            hit = json.loads(cache.read_text(encoding="utf-8"))
            video = Path(hit["video"])
            from .cli import valid_cached_evidence
            if hit.get("input_fingerprint") == key and video.is_file() and _digest_file(video) == hit.get("sha256") and valid_cached_evidence(hit, repo_root):
                proof = verify_video(video, {"width": parsed["width"], "height": parsed["height"],
                                             "fps": parsed["fps"], "duration_seconds": parsed["duration_seconds"],
                                             "duration_tolerance": 1 / parsed["fps"]})
                if proof.get("passed"):
                    return dict(hit, cache_hit=True)
        except (OSError, KeyError, TypeError, ValueError, json.JSONDecodeError):
            pass
    run_root = artifact_root
    run = run_root / f"project-{parsed['project_id']}-{time.strftime('%Y%m%dT%H%M%SZ', time.gmtime())}-{uuid.uuid4().hex[:8]}"
    run.mkdir()
    project_record = {
        "schema": SCHEMA, "id": parsed["project_id"], "title": parsed["title"],
        "manifest_sha256": parsed["manifest_sha256"], "manifest_name": parsed["manifest_rel"],
        "source_project_directory_name": parsed["project_root"].name,
        "files": [{"path": item, "sha256": parsed["file_hashes"][item]} for item in parsed["files"]],
        "brand_logo": {"path": parsed["brand_logo_path"], "sha256": brand["logo_sha256"]},
        "audio_master": ({"path": parsed["audio_master"]["path"], "mode": "copy", "start_seconds": 0.0,
                          "sha256": parsed["file_hashes"][parsed["audio_master"]["path"]],
                          "source_probe": parsed["audio_master"]["source_probe"]}
                         if parsed["audio_master"] else None),
        "brand_config_sha256": _digest_file(brand_path), "width": parsed["width"], "height": parsed["height"],
        "fps": parsed["fps"], "duration_seconds": parsed["duration_seconds"], "frame_count": parsed["frame_count"],
        "quality": quality, "input_fingerprint": key,
        "constraints": ["local HyperFrames only", "no paid fallback", "no publishing", "creative approval remains pending"],
    }
    ledger = Ledger(run, repo_root, project_record)
    source_dir, render_dir = run / "project-source-snapshot", run / "project-render-worktree"
    video, proof_path, sheet = run / "video.mp4", run / "verification.json", run / "contact-sheet.jpg"
    raw_video = run / ("hyperframes-render.mp4" if parsed["audio_master"] else "video.mp4")
    mastering_log = run / _AUDIO_MASTER_LOG
    try:
        with ledger.stage("freeze-project", "freeze_local_project_inputs", provider="local-filesystem",
                          inputs={"manifest_sha256": parsed["manifest_sha256"], "file_count": len(parsed["files"]),
                                  "audio_master": project_record["audio_master"]}) as stage:
            rows = _snapshot_inputs(parsed, source_dir, render_dir)
            stage["execution"]["output"] = {"source_files": rows, "immutable_snapshot": str(source_dir)}
        for row in rows:
            path = source_dir / PurePosixPath(row["path"])
            ledger.add_artifact(path, row["role"], rights="canonical_brand_asset" if row["role"] == "brand_logo" else "operator_supplied_requires_review")
        ledger.add_artifact(run / "campaign.snapshot.json", "build_dna_project_record", rights="original")
        with ledger.stage("render", "local_hyperframes_project_render", provider="local-hyperframes",
                          inputs={"version_probe": {"executable": provider.get("executable"), "version": provider.get("version")},
                                  "input_fingerprint": key, "quality": quality}) as stage:
            rendered = hyperframes.render(render_dir, raw_video, executable=executable, fps=parsed["fps"], quality=quality, timeout=timeout)
            stage["execution"]["output"] = {**rendered, "path": str(raw_video), "sha256": _digest_file(raw_video)}
        ledger.add_artifact(raw_video, "raw_hyperframes_video" if parsed["audio_master"] else "video_draft",
                            rights="source_declared_or_original")
        expected_hashes = {parsed["manifest_rel"]: parsed["manifest_sha256"], **parsed["file_hashes"]}
        for relative, expected_hash in expected_hashes.items():
            work_file = render_dir / PurePosixPath(relative)
            if not work_file.is_file() or _digest_file(work_file) != expected_hash:
                raise ProjectError(f"HyperFrames modified an input project source: {relative}")
        for path in sorted(render_dir.rglob("*")):
            if path.is_file() and path.name in _RESERVED:
                ledger.add_artifact(path, "hyperframes_renderer_log", rights="original")
        if parsed["audio_master"]:
            master_relative = parsed["audio_master"]["path"]
            master_snapshot = source_dir / PurePosixPath(master_relative)
            expected_master_hash = parsed["file_hashes"][master_relative]
            if _digest_file(master_snapshot) != expected_master_hash:
                raise ProjectError("Frozen audio-master snapshot failed its SHA-256 check.")
            ffmpeg = shutil.which("ffmpeg") or "ffmpeg"
            command = [ffmpeg, "-hide_banner", "-nostdin", "-n", "-i", str(raw_video), "-i", str(master_snapshot),
                       "-map", "0:v:0", "-map", "1:a:0", "-c", "copy", "-movflags", "+faststart", str(video)]
            with ledger.stage("master-audio", "stream_copy_declared_aac_master", provider="local-ffmpeg",
                              command=command, inputs={"mode": "copy", "start_seconds": 0.0,
                                  "native_video_sha256": _digest_file(raw_video), "master_path": master_relative,
                                  "master_sha256": expected_master_hash,
                                  "source_probe": parsed["audio_master"]["source_probe"]}) as stage:
                stage["execution"]["output"] = _run_audio_master_copy(
                    command, mastering_log, timeout=timeout, raw_video_sha256=_digest_file(raw_video),
                    audio_master_sha256=expected_master_hash)
            ledger.add_artifact(mastering_log, "audio_mastering_log", rights="original")
            ledger.add_artifact(video, "video_draft", rights="source_declared_or_original")
        proof = verify_video(video, {"width": parsed["width"], "height": parsed["height"], "fps": parsed["fps"],
                                     "duration_seconds": parsed["duration_seconds"],
                                     "duration_tolerance": 1 / parsed["fps"]})
        if parsed["audio_master"] and proof.get("passed"):
            try:
                proof["audio_master"] = _verify_copied_audio(proof, parsed)
            except ProjectError as exc:
                proof["passed"] = False
                proof["status"] = "failed"
                proof.setdefault("failures", []).append(str(exc))
        _atomic_json(proof_path, proof)
        with ledger.stage("verify", "video_technical_qa", provider="local-ffprobe", inputs={"expected": {
            "width": parsed["width"], "height": parsed["height"], "fps": parsed["fps"],
            "duration_seconds": parsed["duration_seconds"], "frame_count": parsed["frame_count"],
            "audio_master": bool(parsed["audio_master"])}}) as stage:
            stage["execution"]["output"] = proof
            if not proof.get("passed"):
                raise ProjectError("Rendered video failed technical verification: " + "; ".join(proof.get("failures", [])))
        ledger.add_artifact(proof_path, "technical_verification", rights="original")
        samples = [(index + 0.5) * parsed["duration_seconds"] / 6 for index in range(6)]
        with ledger.stage("contact-sheet", "visual_review_preparation", provider="local-ffmpeg", inputs={"times": samples}):
            contact_sheet(video, sheet, samples)
        ledger.add_artifact(sheet, "visual_review_sheet", rights="original")
        result_file = run / "render-project-result.json"
        result_record = {"status": "rendered_draft", "video": str(video), "sha256": _digest_file(video),
                         "project_id": parsed["project_id"], "input_fingerprint": key, "review": "pending",
                         "publication": "not_performed",
                         "raw_hyperframes_video": (str(raw_video) if parsed["audio_master"] else None),
                         "raw_hyperframes_video_sha256": (_digest_file(raw_video) if parsed["audio_master"] else None),
                         "audio_master": (proof.get("audio_master") if parsed["audio_master"] else None),
                         "audio_mastering_log": (str(mastering_log) if parsed["audio_master"] else None)}
        _atomic_json(result_file, result_record)
        ledger.add_artifact(result_file, "render_project_receipt", rights="original")
        final = ledger.finalize(status="gated", qa={"technical": "passed", "visual": "review_pending", "publication": "not_requested"})
        if final.get("completion", {}).get("status") != "gated":
            raise ProjectError("Build DNA integrity did not pass.")
        result = {
            **result_record, "width": parsed["width"], "height": parsed["height"], "fps": parsed["fps"],
            "duration_seconds": parsed["duration_seconds"], "frame_count": parsed["frame_count"],
            "run_dir": str(run), "project_snapshot": str(source_dir), "contact_sheet": str(sheet),
            "verification": str(proof_path), "build_dna": str(ledger.path), "build_dna_sha256": _digest_file(ledger.path),
            "provider_fee_usd": 0, "electricity_cost": "not_measured", "cache_hit": False,
        }
        _atomic_json(cache, result)
        return result
    except BaseException:
        for path in sorted(render_dir.rglob("*")) if render_dir.exists() else []:
            if path.is_file() and path.name in _RESERVED:
                try:
                    ledger.add_artifact(path, "hyperframes_renderer_log", rights="original")
                except (OSError, ValueError):
                    pass
        existing = {item.get("path") for item in ledger.data.get("artifacts", [])}
        candidates = ((raw_video, "raw_hyperframes_video"), (video, "failed_render_output"),
                      (mastering_log, "audio_mastering_log"), (proof_path, "failed_or_incomplete_verification"),
                      (sheet, "failed_or_incomplete_contact_sheet"))
        seen = set()
        for path, role in candidates:
            if path in seen:
                continue
            seen.add(path)
            try:
                relative = path.resolve().relative_to(repo_root).as_posix()
                if path.is_file() and relative not in existing:
                    ledger.add_artifact(path, role, rights="original")
            except (OSError, ValueError):
                pass
        ledger.finalize(status="failed")
        raise
