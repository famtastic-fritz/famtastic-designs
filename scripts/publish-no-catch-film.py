#!/usr/bin/env python3
"""Publish only the approved three-file No-Catch film set to GoDaddy media."""

from __future__ import annotations

import argparse
import hashlib
import json
import mimetypes
import os
import re
import shlex
import shutil
import stat
import subprocess
import sys
import uuid
from dataclasses import dataclass
from datetime import datetime, timezone
from pathlib import Path
from typing import Callable, Sequence


SCRIPT_PATH = Path(__file__).resolve()
REPO_ROOT = SCRIPT_PATH.parent.parent
REMOTE_MEDIA_DIR = "~/public_html/media/films"
DEFAULT_SSH_TARGET = "xrdj7j99xhzt@p3plzcpnl497512.prod.phx3.secureserver.net"
RECEIPT_DIR = REPO_ROOT / "artifacts/video-studio/no-catch-20260919/release-preflight/publisher-receipts"
RECEIPT_SCHEMA = "famtastic.no-catch-film-publish.v1"

ASSET_TYPES = {
    "whats-the-catch-20260919.mp4": "video/mp4",
    "whats-the-catch-20260919.jpg": "image/jpeg",
    "whats-the-catch-20260919.vtt": "text/vtt",
}
SHA256_RE = re.compile(r"^[0-9a-f]{64}$")
STAGE_ID_RE = re.compile(r"^\.famtastic-no-catch-[0-9a-f]{32}$")
SSH_TARGET_RE = re.compile(r"^[A-Za-z0-9_.@:-]+$")


class PublishError(RuntimeError):
    """A validation, preflight, or publication failure safe to report."""


@dataclass(frozen=True)
class Asset:
    name: str
    path: Path
    mime: str
    size_bytes: int
    sha256: str
    media_format: str


def _utc_now() -> str:
    return datetime.now(timezone.utc).replace(microsecond=0).isoformat().replace("+00:00", "Z")


def _sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def _regular_file(path: Path) -> None:
    try:
        mode = path.lstat().st_mode
    except FileNotFoundError as exc:
        raise PublishError(f"Required asset is missing: {path.name}") from exc
    if not stat.S_ISREG(mode):
        raise PublishError(f"Asset must be a regular non-symlink file: {path.name}")


def _check_jpeg(path: Path) -> str:
    if path.stat().st_size < 5:
        raise PublishError("Poster must be a complete JPEG image")
    with path.open("rb") as stream:
        start = stream.read(3)
        stream.seek(-2, os.SEEK_END)
        end = stream.read(2)
    if start != b"\xff\xd8\xff" or end != b"\xff\xd9":
        raise PublishError("Poster must be a complete JPEG image")
    guessed = mimetypes.guess_type(path.name)[0]
    if guessed != "image/jpeg":
        raise PublishError("Poster filename does not resolve to image/jpeg")
    return "jpeg"


def _check_vtt(path: Path) -> str:
    try:
        content = path.read_text(encoding="utf-8-sig")
    except UnicodeError as exc:
        raise PublishError("Caption file must be valid UTF-8 WebVTT") from exc
    if not content.startswith("WEBVTT") or "\x00" in content:
        raise PublishError("Caption file must begin with a WEBVTT header")
    return "webvtt"


def _check_mp4(path: Path, runner: Callable = subprocess.run, ffprobe: str = "ffprobe") -> str:
    command = [
        ffprobe,
        "-v", "error",
        "-show_entries", "format=format_name:stream=codec_type",
        "-of", "json",
        str(path),
    ]
    try:
        result = runner(command, capture_output=True, text=True, check=False, timeout=60)
    except (OSError, subprocess.TimeoutExpired) as exc:
        raise PublishError(f"ffprobe could not inspect the MP4: {exc}") from exc
    if result.returncode != 0:
        raise PublishError("ffprobe rejected the MP4 file")
    try:
        probe = json.loads(result.stdout)
    except (TypeError, json.JSONDecodeError) as exc:
        raise PublishError("ffprobe returned invalid JSON for the MP4") from exc
    formats = set((probe.get("format", {}).get("format_name") or "").split(","))
    streams = probe.get("streams") or []
    if not formats.intersection({"mp4", "mov", "m4a", "3gp", "3g2", "mj2"}):
        raise PublishError("Video container is not a supported MP4-family file")
    if not any(stream.get("codec_type") == "video" for stream in streams):
        raise PublishError("MP4 must contain a video stream")
    return ",".join(sorted(formats))


def inspect_assets(
    artifact_dir: Path | str,
    *,
    runner: Callable = subprocess.run,
    ffprobe: str = "ffprobe",
) -> list[Asset]:
    directory = Path(artifact_dir).expanduser().resolve()
    if not directory.is_dir():
        raise PublishError("Artifact directory does not exist or is not a directory")

    assets: list[Asset] = []
    for name, mime in ASSET_TYPES.items():
        path = directory / name
        _regular_file(path)
        if path.stat().st_size == 0:
            raise PublishError(f"Asset is empty: {name}")
        if name.endswith(".mp4"):
            media_format = _check_mp4(path, runner=runner, ffprobe=ffprobe)
        elif name.endswith(".jpg"):
            media_format = _check_jpeg(path)
        else:
            media_format = _check_vtt(path)
        assets.append(
            Asset(
                name=name,
                path=path,
                mime=mime,
                size_bytes=path.stat().st_size,
                sha256=_sha256(path),
                media_format=media_format,
            )
        )
    return assets


def _ssh_target() -> str:
    target = os.environ.get("FAMTASTIC_SSH_TARGET", DEFAULT_SSH_TARGET)
    if not target or target.startswith("-") or not SSH_TARGET_RE.fullmatch(target):
        raise PublishError("FAMTASTIC_SSH_TARGET contains unsupported SSH target characters")
    return target


def _remote_command(arguments: Sequence[str]) -> str:
    # Each dynamic value is already either a checked SHA-256 or generated UUID.
    return "bash -s -- " + " ".join(shlex.quote(value) for value in arguments)


def _ssh_script(
    script: str,
    arguments: Sequence[str],
    *,
    target: str,
    runner: Callable,
    timeout: int = 90,
) -> subprocess.CompletedProcess:
    command = ["ssh", "-T", target, _remote_command(arguments)]
    try:
        result = runner(command, input=script, capture_output=True, text=True, check=False, timeout=timeout)
    except (OSError, subprocess.TimeoutExpired) as exc:
        raise PublishError(f"SSH operation failed: {exc}") from exc
    return result


REMOTE_PREFLIGHT = r'''set -euo pipefail
names=("whats-the-catch-20260919.mp4" "whats-the-catch-20260919.jpg" "whats-the-catch-20260919.vtt")
if [[ "$#" -ne 3 ]]; then printf 'NO_CATCH_ERROR\tinvalid hash arguments\n' >&2; exit 20; fi
for value in "$@"; do [[ "$value" =~ ^[0-9a-f]{64}$ ]] || { printf 'NO_CATCH_ERROR\tinvalid SHA-256 argument\n' >&2; exit 20; }; done
web_root="$HOME/public_html"
media_dir="$web_root/media/films"
[[ -d "$web_root" && ! -L "$web_root" ]] || { printf 'NO_CATCH_ERROR\tpublic_html is missing or symlinked\n' >&2; exit 21; }
[[ ! -L "$web_root/media" && ! -L "$media_dir" ]] || { printf 'NO_CATCH_ERROR\tmedia path contains a symlink\n' >&2; exit 21; }
if [[ -e "$web_root/media" && ! -d "$web_root/media" ]]; then printf 'NO_CATCH_ERROR\tmedia path component is not a directory\n' >&2; exit 21; fi
if [[ -e "$media_dir" && ! -d "$media_dir" ]]; then printf 'NO_CATCH_ERROR\tfilm path is not a directory\n' >&2; exit 21; fi
hash_file() {
  if command -v sha256sum >/dev/null 2>&1; then sha256sum "$1" | awk '{print $1}'
  elif command -v shasum >/dev/null 2>&1; then shasum -a 256 "$1" | awk '{print $1}'
  else printf 'NO_CATCH_ERROR\tSHA-256 utility is unavailable\n' >&2; exit 22
  fi
}
present=0
all_match=1
for i in 0 1 2; do
  path="$media_dir/${names[$i]}"
  actual="-"
  bytes="-"
  exists=0
  if [[ -e "$path" || -L "$path" ]]; then
    exists=1
    present=$((present + 1))
    if [[ -f "$path" && ! -L "$path" ]]; then
      actual="$(hash_file "$path")"
      bytes="$(wc -c < "$path" | tr -d '[:space:]')"
    else actual="NONREGULAR"; fi
    case "$i" in
      0) [[ "$actual" == "$1" ]] || all_match=0 ;;
      1) [[ "$actual" == "$2" ]] || all_match=0 ;;
      2) [[ "$actual" == "$3" ]] || all_match=0 ;;
    esac
  else
    all_match=0
  fi
  printf 'NO_CATCH_FILE\t%s\t%s\t%s\t%s\n' "${names[$i]}" "$exists" "$actual" "$bytes"
done
if [[ "$present" -eq 0 ]]; then state=ABSENT
elif [[ "$present" -eq 3 && "$all_match" -eq 1 ]]; then state=EXACT_MATCH
else state=COLLISION
fi
printf 'NO_CATCH_STATE\t%s\n' "$state"
'''


REMOTE_CREATE_STAGE = r'''set -euo pipefail
stage_id="$1"
[[ "$stage_id" =~ ^\.famtastic-no-catch-[0-9a-f]{32}$ ]] || { printf 'NO_CATCH_ERROR\tinvalid stage id\n' >&2; exit 20; }
stage="$HOME/$stage_id"
[[ ! -e "$stage" && ! -L "$stage" ]] || { printf 'NO_CATCH_ERROR\tunique staging path already exists\n' >&2; exit 23; }
umask 077
mkdir -m 0700 "$stage"
printf 'NO_CATCH_STAGE\tCREATED\t%s\n' "$stage_id"
'''


REMOTE_PROMOTE = r'''set -euo pipefail
stage_id="$1"
[[ "$stage_id" =~ ^\.famtastic-no-catch-[0-9a-f]{32}$ ]] || { printf 'NO_CATCH_ERROR\tinvalid stage id\n' >&2; exit 20; }
if [[ "$#" -ne 4 ]]; then printf 'NO_CATCH_ERROR\tinvalid hash arguments\n' >&2; exit 20; fi
for value in "${@:2}"; do [[ "$value" =~ ^[0-9a-f]{64}$ ]] || { printf 'NO_CATCH_ERROR\tinvalid SHA-256 argument\n' >&2; exit 20; }; done
names=("whats-the-catch-20260919.mp4" "whats-the-catch-20260919.jpg" "whats-the-catch-20260919.vtt")
stage="$HOME/$stage_id"
web_root="$HOME/public_html"
media_dir="$web_root/media/films"
[[ -d "$stage" && ! -L "$stage" ]] || { printf 'NO_CATCH_ERROR\tprivate staging directory is missing or symlinked\n' >&2; exit 24; }
[[ -d "$web_root" && ! -L "$web_root" ]] || { printf 'NO_CATCH_ERROR\tpublic_html is missing or symlinked\n' >&2; exit 21; }
[[ ! -L "$web_root/media" && ! -L "$media_dir" ]] || { printf 'NO_CATCH_ERROR\tmedia path contains a symlink\n' >&2; exit 21; }
if [[ -e "$web_root/media" && ! -d "$web_root/media" ]]; then printf 'NO_CATCH_ERROR\tmedia path component is not a directory\n' >&2; exit 21; fi
if [[ -e "$media_dir" && ! -d "$media_dir" ]]; then printf 'NO_CATCH_ERROR\tfilm path is not a directory\n' >&2; exit 21; fi
hash_file() {
  if command -v sha256sum >/dev/null 2>&1; then sha256sum "$1" | awk '{print $1}'
  elif command -v shasum >/dev/null 2>&1; then shasum -a 256 "$1" | awk '{print $1}'
  else printf 'NO_CATCH_ERROR\tSHA-256 utility is unavailable\n' >&2; exit 22
  fi
}
for i in 0 1 2; do
  source="$stage/${names[$i]}"
  [[ -f "$source" && ! -L "$source" ]] || { printf 'NO_CATCH_ERROR\tstaged asset is missing or nonregular\n' >&2; exit 25; }
  actual="$(hash_file "$source")"
  case "$i" in
    0) expected="$2" ;;
    1) expected="$3" ;;
    2) expected="$4" ;;
  esac
  [[ "$actual" == "$expected" ]] || { printf 'NO_CATCH_ERROR\tstaged asset hash mismatch\n' >&2; exit 25; }
done

present=0
all_match=1
for i in 0 1 2; do
  path="$media_dir/${names[$i]}"
  if [[ -e "$path" || -L "$path" ]]; then
    present=$((present + 1))
    if [[ -f "$path" && ! -L "$path" ]]; then actual="$(hash_file "$path")"; else actual=NONREGULAR; fi
    case "$i" in
      0) expected="$2" ;;
      1) expected="$3" ;;
      2) expected="$4" ;;
    esac
    [[ "$actual" == "$expected" ]] || all_match=0
  else
    all_match=0
  fi
done
if [[ "$present" -eq 3 && "$all_match" -eq 1 ]]; then
  state=VERIFIED_NOOP
elif [[ "$present" -ne 0 ]]; then
  printf 'NO_CATCH_ERROR\tpublication collision appeared after staging; nothing overwritten\n' >&2
  exit 26
else
  state=PUBLISHED
fi

if [[ "$state" == PUBLISHED ]]; then
  umask 022
  mkdir -p -m 0755 "$media_dir"
  [[ -d "$web_root/media" && ! -L "$web_root/media" && -d "$media_dir" && ! -L "$media_dir" ]] || { printf 'NO_CATCH_ERROR\tmedia directories are unsafe\n' >&2; exit 21; }
  umask 077
  # Build private same-directory temporary files first; a later collision cannot
  # overwrite a target, and mv's -n/-T performs the atomic no-clobber rename.
  suffix="${stage_id#.famtastic-no-catch-}"
  for i in 0 1 2; do
    temp="$media_dir/.${names[$i]}.${suffix}.tmp"
    [[ ! -e "$temp" && ! -L "$temp" ]] || { printf 'NO_CATCH_ERROR\tunique same-directory temp already exists\n' >&2; exit 27; }
    cp "$stage/${names[$i]}" "$temp"
    chmod 0600 "$temp"
  done
  # Recheck all destinations immediately before the per-file atomic installs.
  for name in "${names[@]}"; do [[ ! -e "$media_dir/$name" && ! -L "$media_dir/$name" ]] || { printf 'NO_CATCH_ERROR\tpublication collision appeared before install\n' >&2; exit 26; }; done
  for i in 0 1 2; do
    temp="$media_dir/.${names[$i]}.${suffix}.tmp"
    target="$media_dir/${names[$i]}"
    mv -T -n -- "$temp" "$target"
    [[ ! -e "$temp" && ! -L "$temp" ]] || { printf 'NO_CATCH_ERROR\tno-clobber rename refused an existing target\n' >&2; exit 26; }
    chmod 0644 "$target"
  done
fi

printf 'NO_CATCH_STATE\t%s\n' "$state"
for i in 0 1 2; do
  path="$media_dir/${names[$i]}"
  [[ -f "$path" && ! -L "$path" ]] || { printf 'NO_CATCH_ERROR\tfinal asset is missing or nonregular\n' >&2; exit 28; }
  actual="$(hash_file "$path")"
  case "$i" in
    0) expected="$2" ;;
    1) expected="$3" ;;
    2) expected="$4" ;;
  esac
  [[ "$actual" == "$expected" ]] || { printf 'NO_CATCH_ERROR\tfinal asset hash mismatch\n' >&2; exit 28; }
  bytes="$(wc -c < "$path" | tr -d '[:space:]')"
  printf 'NO_CATCH_FINAL\t%s\t%s\t%s\n' "${names[$i]}" "$actual" "$bytes"
done
'''


def _parse_protocol(stdout: str, prefix: str) -> tuple[str | None, dict[str, dict[str, str]]]:
    state = None
    rows: dict[str, dict[str, str]] = {}
    for line in stdout.splitlines():
        if line.startswith("NO_CATCH_STATE\t"):
            state = line.split("\t", 1)[1].strip()
        elif line.startswith(prefix + "\t"):
            parts = line.split("\t")
            if prefix == "NO_CATCH_FILE" and len(parts) == 5:
                row = {"exists": parts[2], "remote_sha256": parts[3], "size_bytes": parts[4]}
            elif prefix == "NO_CATCH_FINAL" and len(parts) == 4:
                row = {"sha256": parts[2], "size_bytes": parts[3]}
            else:
                raise PublishError(f"Remote returned a malformed {prefix} record")
            if parts[1] in rows:
                raise PublishError(f"Remote returned a duplicate {prefix} record")
            rows[parts[1]] = row
    return state, rows


def _run_ssh_script(
    script: str,
    arguments: Sequence[str],
    *,
    target: str,
    runner: Callable,
    timeout: int = 90,
) -> str:
    result = _ssh_script(script, arguments, target=target, runner=runner, timeout=timeout)
    if result.returncode != 0:
        detail = (result.stderr or result.stdout or "remote command failed").strip()
        raise PublishError(detail)
    return result.stdout or ""


def _run_preflight(
    assets: list[Asset], *, target: str, runner: Callable
) -> tuple[str, dict[str, dict[str, str]]]:
    output = _run_ssh_script(
        REMOTE_PREFLIGHT,
        [asset.sha256 for asset in assets],
        target=target,
        runner=runner,
    )
    state, rows = _parse_protocol(output, "NO_CATCH_FILE")
    expected_names = {asset.name for asset in assets}
    if state not in {"ABSENT", "EXACT_MATCH", "COLLISION"} or set(rows) != expected_names:
        raise PublishError("Remote preflight did not return a recognized target state")
    expected_hashes = {asset.name: asset.sha256 for asset in assets}
    for name, row in rows.items():
        if row.get("exists") not in {"0", "1"}:
            raise PublishError("Remote preflight returned an invalid file status")
        remote_hash = row.get("remote_sha256", "")
        if row["exists"] == "0":
            if remote_hash != "-" or row["size_bytes"] != "-":
                raise PublishError("Remote preflight returned inconsistent absent-file data")
        elif remote_hash == "NONREGULAR":
            if row["size_bytes"] != "-":
                raise PublishError("Remote preflight returned inconsistent nonregular-file data")
        elif not SHA256_RE.fullmatch(remote_hash) or not row["size_bytes"].isdigit():
            raise PublishError("Remote preflight returned an invalid hash or size")
    present = [row["exists"] == "1" for row in rows.values()]
    if not any(present):
        derived_state = "ABSENT"
    elif all(present) and all(rows[name]["remote_sha256"] == expected_hashes[name] for name in expected_names):
        derived_state = "EXACT_MATCH"
    else:
        derived_state = "COLLISION"
    if state != derived_state:
        raise PublishError("Remote preflight state does not match its per-file results")
    return state, rows


def _write_receipt(receipt_dir: Path, run_id: str, receipt: dict) -> Path:
    receipt_dir.mkdir(parents=True, exist_ok=True)
    path = receipt_dir / f"no-catch-publish-{run_id}.json"
    path.write_text(json.dumps(receipt, indent=2, sort_keys=True) + "\n", encoding="utf-8")
    return path


def execute(
    artifact_dir: Path | str,
    *,
    apply: bool = False,
    receipt_dir: Path = RECEIPT_DIR,
    runner: Callable | None = None,
    tools: dict[str, str] | None = None,
    ssh_target: str | None = None,
) -> tuple[int, dict]:
    """Validate and dry-run by default; mutate only with the explicit apply flag."""
    command_runner = runner or subprocess.run
    if tools is None:
        resolved_tools = {
            "ffprobe": shutil.which("ffprobe"),
            "ssh": shutil.which("ssh"),
            "rsync": shutil.which("rsync") if apply else None,
        }
    else:
        resolved_tools = tools
    for name in ("ffprobe", "ssh", *(('rsync',) if apply else ())):
        if not resolved_tools.get(name):
            raise PublishError(f"Required local command is not installed: {name}")

    target = ssh_target or _ssh_target()
    if not SSH_TARGET_RE.fullmatch(target) or target.startswith("-"):
        raise PublishError("SSH target is not a safe host alias or user@host value")

    assets = inspect_assets(artifact_dir, runner=command_runner, ffprobe=resolved_tools["ffprobe"])
    run_id = uuid.uuid4().hex
    expected = {asset.name: asset.sha256 for asset in assets}
    base_receipt = {
        "schema": RECEIPT_SCHEMA,
        "created_at": _utc_now(),
        "remote_target": REMOTE_MEDIA_DIR,
        "apply_requested": bool(apply),
        "artifact_directory": str(Path(artifact_dir).expanduser().resolve()),
        "files": [
            {
                "name": asset.name,
                "mime": asset.mime,
                "media_format": asset.media_format,
                "size_bytes": asset.size_bytes,
                "sha256": asset.sha256,
            }
            for asset in assets
        ],
    }

    try:
        remote_state, remote_rows = _run_preflight(assets, target=target, runner=command_runner)
        base_receipt["remote_preflight_files"] = remote_rows
        base_receipt["remote_preflight"] = remote_state
        if remote_state == "COLLISION":
            base_receipt["status"] = "refused_collision"
            raise PublishError("At least one target exists with a different hash, or only part of the set exists; nothing was uploaded")
        if not apply:
            base_receipt["status"] = "dry_run_exact_match" if remote_state == "EXACT_MATCH" else "dry_run_ready"
            path = _write_receipt(receipt_dir, run_id, base_receipt)
            base_receipt["receipt_path"] = str(path)
            return 0, base_receipt
        if remote_state == "EXACT_MATCH":
            base_receipt["status"] = "verified_noop"
            base_receipt["remote_final_hashes"] = {
                name: row["remote_sha256"] for name, row in remote_rows.items()
            }
            base_receipt["remote_final_sizes"] = {
                name: int(row["size_bytes"]) for name, row in remote_rows.items()
            }
            path = _write_receipt(receipt_dir, run_id, base_receipt)
            base_receipt["receipt_path"] = str(path)
            return 0, base_receipt

        stage_id = f".famtastic-no-catch-{run_id}"
        base_receipt["private_staging_path"] = f"~/{stage_id}"
        stage_output = _run_ssh_script(
            REMOTE_CREATE_STAGE,
            [stage_id],
            target=target,
            runner=command_runner,
        )
        if f"NO_CATCH_STAGE\tCREATED\t{stage_id}" not in stage_output.splitlines():
            raise PublishError("Remote staging directory creation could not be verified")

        rsync_command = [
            resolved_tools["rsync"],
            "-a",
            "-e",
            "ssh -T",
            "--",
            *[asset.name for asset in assets],
            f"{target}:~/{stage_id}/",
        ]
        try:
            transfer = command_runner(
                rsync_command,
                cwd=str(Path(artifact_dir).expanduser().resolve()),
                capture_output=True,
                text=True,
                check=False,
                timeout=3600,
            )
        except (OSError, subprocess.TimeoutExpired) as exc:
            raise PublishError(f"Explicit staging upload failed: {exc}") from exc
        if transfer.returncode != 0:
            detail = (transfer.stderr or transfer.stdout or "rsync upload failed").strip()
            raise PublishError(f"Explicit staging upload failed: {detail}")

        final_output = _run_ssh_script(
            REMOTE_PROMOTE,
            [stage_id, *[asset.sha256 for asset in assets]],
            target=target,
            runner=command_runner,
            timeout=3600,
        )
        state, final_rows = _parse_protocol(final_output, "NO_CATCH_FINAL")
        if state not in {"PUBLISHED", "VERIFIED_NOOP"}:
            raise PublishError("Remote promotion did not return a recognized final state")
        remote_hashes = {name: row["sha256"] for name, row in final_rows.items()}
        if remote_hashes != expected:
            raise PublishError("Remote final hashes do not match the validated local assets")
        base_receipt["status"] = "published" if state == "PUBLISHED" else "verified_noop_after_staging"
        base_receipt["remote_final_hashes"] = remote_hashes
        base_receipt["remote_final_sizes"] = {
            name: int(row["size_bytes"]) for name, row in final_rows.items()
        }
        path = _write_receipt(receipt_dir, run_id, base_receipt)
        base_receipt["receipt_path"] = str(path)
        return 0, base_receipt
    except PublishError as exc:
        base_receipt.setdefault("status", "failed")
        base_receipt["error"] = str(exc)
        path = _write_receipt(receipt_dir, run_id, base_receipt)
        base_receipt["receipt_path"] = str(path)
        return 2, base_receipt


def main(argv: Sequence[str] | None = None) -> int:
    parser = argparse.ArgumentParser(
        description="Dry-run a narrowly scoped upload of the No-Catch film set; use --apply to publish."
    )
    parser.add_argument("artifact_dir", type=Path, help="Directory containing the three exact film asset filenames")
    parser.add_argument("--apply", action="store_true", help="Upload and publish after the read-only collision preflight")
    args = parser.parse_args(argv)
    try:
        code, receipt = execute(args.artifact_dir, apply=args.apply)
    except PublishError as exc:
        print(f"No-Catch publisher refused: {exc}", file=sys.stderr)
        return 2
    print(json.dumps(receipt, indent=2, sort_keys=True))
    return code


if __name__ == "__main__":
    raise SystemExit(main())
