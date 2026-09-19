"""Bounded, resumable client for a trusted *local* ComfyUI installation.

Only reviewed native node classes are accepted. This client is not a sandbox for
custom Python installed inside ComfyUI. Keep that process local and trustworthy.
No model downloads, cloud calls, automatic resubmission or global interruption.
"""
from __future__ import annotations

import copy
import hashlib
import ipaddress
import json
import math
import mimetypes
import os
from pathlib import Path, PurePosixPath
import re
import time
import urllib.error
import urllib.parse
import urllib.request
import uuid


class ComfyError(RuntimeError):
    """An actionable local-generation error; its receipt remains resumable."""


# An allowlist is intentional: a denylist cannot identify future API nodes.
LOCAL_NODE_CLASSES = frozenset({
    "CheckpointLoaderSimple", "UNETLoader", "CLIPLoader", "DualCLIPLoader",
    "VAELoader", "CLIPTextEncode", "CLIPTextEncodeSDXL", "CLIPSetLastLayer",
    "KSampler", "KSamplerAdvanced", "ModelSamplingSD3", "ModelSamplingFlux",
    "ModelSamplingAuraFlow", "ModelSamplingDiscrete", "ModelSamplingContinuousEDM",
    "EmptyLatentImage", "EmptySD3LatentImage", "EmptyHunyuanLatentVideo",
    "Wan22ImageToVideoLatent", "WanImageToVideo", "VAEEncode", "VAEDecode",
    "VAEEncodeTiled", "VAEDecodeTiled", "LoadImage", "ImageScale", "ImageScaleBy",
    "ImageCrop", "ImageBatch", "ImageFromBatch", "ImagePadForOutpaint",
    "ConditioningZeroOut", "ConditioningCombine", "ConditioningConcat",
    "ConditioningSetTimestepRange", "LoraLoader", "LoraLoaderModelOnly",
    "SaveImage", "SaveAnimatedWEBP", "SaveAnimatedPNG", "CreateVideo", "SaveVideo",
    "RandomNoise", "BasicGuider", "CFGGuider", "KSamplerSelect", "BasicScheduler",
    "SamplerCustomAdvanced", "SplitSigmas", "ModelSamplingLTXV",
})
MAX_JSON_BYTES = 32 * 1024 * 1024
MAX_OUTPUT_BYTES = 1024 * 1024 * 1024


def _digest(value):
    return hashlib.sha256(json.dumps(value, sort_keys=True, separators=(",", ":"),
                                    allow_nan=False).encode()).hexdigest()


def _file_hash(path):
    h = hashlib.sha256()
    with open(path, "rb") as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b""):
            h.update(chunk)
    return h.hexdigest()


def _read_json(path):
    try:
        return json.loads(Path(path).read_text(encoding="utf-8"))
    except (OSError, ValueError) as exc:
        raise ComfyError(f"Cannot read JSON {path}: {exc}") from exc


def _save(path, data):
    path = Path(path)
    path.parent.mkdir(parents=True, exist_ok=True)
    temp = path.with_name(path.name + "." + uuid.uuid4().hex + ".tmp")
    try:
        with temp.open("x", encoding="utf-8") as stream:
            json.dump(data, stream, indent=2, sort_keys=True, allow_nan=False)
            stream.write("\n")
            stream.flush()
            os.fsync(stream.fileno())
        temp.replace(path)
    finally:
        temp.unlink(missing_ok=True)


def local_url(base_url):
    """Reject credentials, paths, proxies, LAN/cloud hosts, and redirects."""
    try:
        u = urllib.parse.urlsplit(base_url)
        if u.scheme != "http" or u.username or u.password or u.query or u.fragment:
            raise ValueError("expected plain local HTTP URL")
        if u.path not in ("", "/") or not u.hostname:
            raise ValueError("unexpected URL path")
        host = "127.0.0.1" if u.hostname.lower() == "localhost" else u.hostname
        if not ipaddress.ip_address(host).is_loopback:
            raise ValueError("host is not loopback")
        port = u.port or 8188
        host = f"[{host}]" if ":" in host else host
        return f"http://{host}:{port}"
    except ValueError as exc:
        raise ComfyError(f"ComfyUI must use a loopback address: {exc}") from exc


class _NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        raise ComfyError("ComfyUI HTTP redirect refused")


class Client:
    def __init__(self, base_url="http://127.0.0.1:8188", request_timeout=30):
        self.base_url = local_url(base_url)
        self.request_timeout = request_timeout
        self.opener = urllib.request.build_opener(urllib.request.ProxyHandler({}), _NoRedirect())

    def request(self, route, data=None, content_type="application/json", *, limit=MAX_JSON_BYTES):
        request = urllib.request.Request(self.base_url + route, data=data,
                                         headers={"Content-Type": content_type})
        try:
            with self.opener.open(request, timeout=self.request_timeout) as response:
                body = response.read(limit + 1)
                if len(body) > limit:
                    raise ComfyError(f"ComfyUI response exceeds {limit} bytes")
                return body
        except (urllib.error.URLError, TimeoutError, OSError) as exc:
            raise ComfyError(f"ComfyUI {route.split('?')[0]} request failed: {exc}") from exc

    def json(self, route, payload=None):
        data = None if payload is None else json.dumps(payload, allow_nan=False).encode()
        try:
            return json.loads(self.request(route, data))
        except (ValueError, UnicodeError) as exc:
            raise ComfyError(f"ComfyUI {route} returned invalid JSON") from exc

    def upload_image(self, path):
        path = Path(path).resolve()
        if not path.is_file() or path.suffix.lower() not in {".png", ".jpg", ".jpeg", ".webp"}:
            raise ComfyError("Input image must be an existing PNG, JPG or WebP file")
        if path.stat().st_size > 100 * 1024 * 1024:
            raise ComfyError("Input image exceeds the 100 MB upload limit")
        name = "input-" + _file_hash(path)[:20] + path.suffix.lower()
        boundary = "famvideo" + uuid.uuid4().hex
        mime = mimetypes.guess_type(name)[0] or "application/octet-stream"
        body = (f'--{boundary}\r\nContent-Disposition: form-data; name="image"; filename="{name}"\r\n'
                f'Content-Type: {mime}\r\n\r\n').encode() + path.read_bytes()
        body += (f'\r\n--{boundary}\r\nContent-Disposition: form-data; name="type"\r\n\r\ninput\r\n'
                 f'--{boundary}\r\nContent-Disposition: form-data; name="overwrite"\r\n\r\nfalse\r\n'
                 f'--{boundary}--\r\n').encode()
        try:
            result = json.loads(self.request("/upload/image", body, "multipart/form-data; boundary=" + boundary))
        except ValueError as exc:
            raise ComfyError("Image upload returned invalid JSON") from exc
        metadata = _safe_metadata({"filename": result.get("name"), "subfolder": result.get("subfolder", ""),
                                   "type": result.get("type", "input")}, allowed_types={"input"})
        return str(PurePosixPath(metadata["subfolder"]) / metadata["filename"])



def binding_candidates(workflow):
    """List actual scalar inputs with node titles; never guess positive/negative IDs."""
    if not isinstance(workflow, dict) or "nodes" in workflow:
        raise ComfyError("Expected ComfyUI API export, not UI workflow JSON")
    result = []
    for node_id, node in workflow.items():
        if not isinstance(node, dict) or not isinstance(node.get("inputs"), dict):
            raise ComfyError(f"Invalid API node {node_id}")
        title = node.get("_meta", {}).get("title", node.get("class_type"))
        for field, value in node["inputs"].items():
            if not isinstance(value, (dict, list)):
                result.append({"node": str(node_id), "input": field, "class_type": node.get("class_type"),
                               "title": title, "current_value": value})
    return result


def bind_workflow(workflow, bindings, values):
    """Copy an API export and replace only named, explicitly bound inputs."""
    if not isinstance(workflow, dict) or "nodes" in workflow:
        raise ComfyError("Expected ComfyUI API export (node-id map), not UI workflow JSON")
    if not isinstance(bindings, dict) or not isinstance(values, dict):
        raise ComfyError("Bindings and values must be JSON objects")
    result = copy.deepcopy(workflow)
    for name, value in values.items():
        binding = bindings.get(name)
        if not isinstance(binding, dict) or set(binding) != {"node", "input"}:
            raise ComfyError(f"Missing or invalid explicit binding for {name}")
        node_id, field = str(binding["node"]), binding["input"]
        node = result.get(node_id)
        if not isinstance(node, dict) or field not in node.get("inputs", {}):
            raise ComfyError(f"Binding {name} targets missing input {node_id}.{field}")
        if isinstance(value, (dict, list)):
            raise ComfyError(f"Binding {name} must be a scalar; graph changes require a new workflow")
        node["inputs"][field] = value
    return result


def validate_workflow(workflow, object_info):
    """Return local-node, input, link, combo/model and numeric validation errors.

    Server-side /prompt remains authoritative, especially for dynamic V3 inputs.
    The returned list being empty does not prove GPU execution or visual quality.
    """
    errors = []
    if not isinstance(workflow, dict) or not workflow or "nodes" in workflow:
        return ["Expected nonempty ComfyUI API export, not UI workflow JSON"]
    if not isinstance(object_info, dict):
        return ["ComfyUI /object_info was not an object"]
    for node_id, node in workflow.items():
        if not isinstance(node_id, str) or not re.fullmatch(r"[A-Za-z0-9_.-]+", node_id):
            errors.append(f"Invalid node id {node_id!r}")
        if not isinstance(node, dict) or not isinstance(node.get("inputs"), dict):
            errors.append(f"Node {node_id} needs class_type and inputs")
            continue
        kind = node.get("class_type")
        if kind not in LOCAL_NODE_CLASSES:
            errors.append(f"Node {node_id}: {kind} is not a reviewed native local node")
            continue
        info = object_info.get(kind)
        if not isinstance(info, dict):
            errors.append(f"Node {node_id}: {kind} is missing from installed ComfyUI")
            continue
        if info.get("api_node") or "api_nodes" in str(info.get("python_module", "")):
            errors.append(f"Node {node_id}: remote/API node refused")
        specs = info.get("input", {})
        required = specs.get("required", {})
        allowed = {**required, **specs.get("optional", {})}
        for name in required:
            if name not in node["inputs"]:
                errors.append(f"Node {node_id}: missing required input {name}")
        for name, value in node["inputs"].items():
            if name not in allowed:
                errors.append(f"Node {node_id}: unknown input {name}; re-export installed workflow")
                continue
            spec = allowed[name]
            expected = spec[0] if isinstance(spec, list) and spec else None
            options = spec[1] if isinstance(spec, list) and len(spec) > 1 and isinstance(spec[1], dict) else {}
            if isinstance(value, list):
                if len(value) != 2 or not isinstance(value[0], str) or type(value[1]) is not int:
                    errors.append(f"Node {node_id}.{name}: malformed graph link")
                    continue
                source = workflow.get(value[0], {})
                outputs = object_info.get(source.get("class_type"), {}).get("output", [])
                if value[1] < 0 or value[1] >= len(outputs):
                    errors.append(f"Node {node_id}.{name}: missing output {value}")
                elif isinstance(expected, str) and expected != "*" and outputs[value[1]] not in (expected, "*"):
                    errors.append(f"Node {node_id}.{name}: incompatible linked output")
            elif isinstance(expected, list) and value not in expected:
                errors.append(f"Node {node_id}.{name}: {value!r} unavailable (model/file/choice missing)")
            elif expected in ("INT", "FLOAT"):
                valid = type(value) is int if expected == "INT" else type(value) in (int, float)
                if not valid or not math.isfinite(value):
                    errors.append(f"Node {node_id}.{name}: expected finite {expected}")
                elif ("min" in options and value < options["min"]) or ("max" in options and value > options["max"]):
                    errors.append(f"Node {node_id}.{name}: outside installed node range")
            elif expected == "STRING" and not isinstance(value, str):
                errors.append(f"Node {node_id}.{name}: expected string")
            elif expected == "BOOLEAN" and type(value) is not bool:
                errors.append(f"Node {node_id}.{name}: expected boolean")
            if name == "filename_prefix" and isinstance(value, str):
                if not _safe_relative(value):
                    errors.append(f"Node {node_id}: unsafe output prefix")
        if kind == "Wan22ImageToVideoLatent":
            inputs = node["inputs"]
            for key in ("width", "height"):
                value = inputs.get(key)
                if type(value) is int and value % 32:
                    errors.append(f"Node {node_id}.{key}: Wan5B requires a multiple of 32")
            length = inputs.get("length")
            if type(length) is int and (length - 1) % 4:
                errors.append(f"Node {node_id}.length: use 4n+1 frames")
    return errors


def _safe_relative(value):
    return (isinstance(value, str) and not any(c in value for c in ("\\", "\x00", ":"))
            and not value.startswith("/") and ".." not in PurePosixPath(value).parts)


def _safe_metadata(item, allowed_types=frozenset({"output", "temp"})):
    if not isinstance(item, dict):
        raise ComfyError("Malformed output metadata")
    name, folder, kind = item.get("filename"), item.get("subfolder", ""), item.get("type", "output")
    if not _safe_relative(name) or not name or "/" in name or name in (".", ".."):
        raise ComfyError("Unsafe output filename in ComfyUI metadata")
    if not _safe_relative(folder) or kind not in allowed_types:
        raise ComfyError("Unsafe output subfolder/type in ComfyUI metadata")
    return {"filename": name, "subfolder": folder, "type": kind}


def _download_outputs(client, entry, output_dir):
    output_dir = Path(output_dir).resolve()
    output_dir.mkdir(parents=True, exist_ok=True)
    files, seen = [], set()
    for node_id, output in entry.get("outputs", {}).items():
        if not isinstance(output, dict):
            continue
        for key in ("images", "gifs", "videos", "audio"):
            for item in output.get(key, []):
                meta = _safe_metadata(item)
                identity = _digest(meta)
                if identity in seen:
                    continue
                seen.add(identity)
                # Local destination is derived from a hash, never the remote path.
                suffix = Path(meta["filename"]).suffix.lower()
                if not re.fullmatch(r"\.[a-z0-9]{1,8}", suffix):
                    raise ComfyError("Output has an unsafe or absent extension")
                target = output_dir / (identity[:20] + suffix)
                temp = output_dir / (identity[:20] + "." + uuid.uuid4().hex + ".part")
                if target.is_symlink():
                    raise ComfyError("Output destination is a symlink")
                try:
                    request = urllib.request.Request(client.base_url + "/view?" + urllib.parse.urlencode(meta))
                    with client.opener.open(request, timeout=client.request_timeout) as response, temp.open("xb") as stream:
                        total = 0
                        while chunk := response.read(1024 * 1024):
                            total += len(chunk)
                            if total > MAX_OUTPUT_BYTES:
                                raise ComfyError("Output exceeds the 1 GB per-file limit")
                            stream.write(chunk)
                    if total == 0:
                        raise ComfyError("ComfyUI returned an empty output")
                    temp.replace(target)
                except (urllib.error.URLError, TimeoutError, OSError) as exc:
                    raise ComfyError(f"Output download failed; resume the receipt: {exc}") from exc
                finally:
                    temp.unlink(missing_ok=True)
                files.append({"path": str(target), "sha256": _file_hash(target), "bytes": target.stat().st_size,
                              "node_id": node_id, "remote": meta})
    if not files:
        raise ComfyError("Completed workflow returned no downloadable image/video/audio metadata")
    return files


def _poll(client, receipt_path, receipt, timeout, poll_interval):
    if not math.isfinite(timeout) or not math.isfinite(poll_interval) or timeout <= 0 or poll_interval <= 0:
        raise ComfyError("Timeout and poll interval must be positive")
    deadline = time.monotonic() + timeout
    prompt_id = receipt["prompt_id"]
    while time.monotonic() < deadline:
        client.request_timeout = min(30, max(.01, deadline - time.monotonic()))
        result = client.json("/history/" + urllib.parse.quote(prompt_id, safe=""))
        entry = result.get(prompt_id, {})
        status = entry.get("status", {})
        messages = status.get("messages", [])
        failed = status.get("status_str") == "error" or any(
            isinstance(m, list) and m and m[0] in ("execution_error", "execution_interrupted") for m in messages)
        if failed:
            receipt.update(status="failed", error=messages, finished_at=time.time())
            _save(receipt_path, receipt)
            raise ComfyError(f"ComfyUI execution failed; inspect receipt {receipt_path}")
        if status.get("completed"):
            client.request_timeout = 30
            receipt["files"] = _download_outputs(client, entry, receipt["output_dir"])
            receipt.update(status="completed", finished_at=time.time())
            _save(receipt_path, receipt)
            return receipt
        time.sleep(min(poll_interval, max(0, deadline - time.monotonic())))
    receipt.update(status="waiting", last_poll_at=time.time())
    _save(receipt_path, receipt)
    raise ComfyError(f"Timed out waiting for {prompt_id}; job was NOT cancelled. Resume {receipt_path}; do not resubmit")


def run_workflow(workflow_path, bindings_path, values, output_dir, *,
                 base_url="http://127.0.0.1:8188", receipt_path=None,
                 input_image=None, timeout=3600, poll_interval=2):
    """Submit exactly once per new receipt, recording its ID before polling.

    Any existing receipt is refused: use resume_workflow. A transport failure
    during POST remains 'submission_unknown'; inspect the Comfy queue manually.
    """
    client = Client(base_url)
    output_dir = Path(output_dir).resolve()
    receipt_path = Path(receipt_path or output_dir / "comfy-receipt.json").resolve()
    if not math.isfinite(timeout) or not math.isfinite(poll_interval) or timeout <= 0 or poll_interval <= 0:
        raise ComfyError("Timeout and poll interval must be positive")
    receipt_path.parent.mkdir(parents=True, exist_ok=True)
    lock = receipt_path.with_name(receipt_path.name + ".lock")
    try:
        fd = os.open(lock, os.O_CREAT | os.O_EXCL | os.O_WRONLY, 0o600)
    except FileExistsError as exc:
        raise ComfyError(f"Receipt is locked: {lock}; check the original runner before removing a stale lock") from exc
    os.close(fd)
    try:
        if receipt_path.exists():
            raise ComfyError(f"Receipt already exists; resume {receipt_path}; no new job submitted")
        workflow, bindings = _read_json(workflow_path), _read_json(bindings_path)
        exact_values = dict(values)
        if input_image is not None:
            if "input_image" not in bindings:
                raise ComfyError("Input image needs an explicit input_image binding")
            exact_values["input_image"] = client.upload_image(input_image)
        workflow = bind_workflow(workflow, bindings, exact_values)
        errors = validate_workflow(workflow, client.json("/object_info"))
        if errors:
            raise ComfyError("Workflow preflight failed:\n" + "\n".join(errors))
        receipt = {"schema": "fam-video.comfy-receipt.v1", "status": "submitting", "provider": "comfyui",
                   "execution": "local", "base_url": client.base_url, "output_dir": str(output_dir),
                   "workflow_sha256": _digest(workflow), "workflow": workflow,
                   "client_id": str(uuid.uuid4()), "created_at": time.time(), "prompt_id": None,
                   "api_spend_usd": 0, "electricity_cost_usd": None}
        # Durable intent precedes the expensive operation. No retry after ambiguity.
        _save(receipt_path, receipt)
        try:
            reply = client.json("/prompt", {"prompt": workflow, "client_id": receipt["client_id"]})
            if reply.get("error") or reply.get("node_errors"):
                receipt.update(status="rejected", error=reply)
                _save(receipt_path, receipt)
                raise ComfyError(f"ComfyUI rejected workflow; inspect {receipt_path}")
            if not isinstance(reply.get("prompt_id"), str) or not reply["prompt_id"]:
                raise ComfyError("ComfyUI did not return a prompt_id")
            receipt.update(status="queued", prompt_id=reply["prompt_id"], queued_at=time.time())
            _save(receipt_path, receipt)
        except ComfyError:
            if receipt["status"] == "submitting":
                receipt["status"] = "submission_unknown"
                _save(receipt_path, receipt)
            raise
        return _poll(client, receipt_path, receipt, timeout, poll_interval)
    finally:
        lock.unlink(missing_ok=True)


def resume_workflow(receipt_path, *, timeout=3600, poll_interval=2):
    """Poll/download the SAME prompt ID. This function never POSTs a prompt."""
    receipt_path = Path(receipt_path).resolve()
    receipt = _read_json(receipt_path)
    if receipt.get("schema") != "fam-video.comfy-receipt.v1":
        raise ComfyError("Unsupported ComfyUI receipt")
    client = Client(receipt["base_url"])
    if receipt.get("status") in ("failed", "rejected"):
        raise ComfyError("This job failed or was rejected; fix the cause and explicitly create a new receipt")
    if not receipt.get("prompt_id"):
        raise ComfyError("Submission state is uncertain; inspect the ComfyUI queue/history manually; automatic resubmission refused")
    if receipt.get("status") == "completed":
        if all(Path(f["path"]).is_file() and _file_hash(f["path"]) == f["sha256"] for f in receipt.get("files", [])) and receipt.get("files"):
            return receipt
    return _poll(client, receipt_path, receipt, timeout, poll_interval)
