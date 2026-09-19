"""Atomic, resumable canonical Build DNA evidence for local draft production.

The ledger records observations, not approval. It never calls Drupal, Site
Studio, a paid provider, or a publishing service. Evidence files must live inside
the actual repository and retain their recorded bytes. Changed output needs a
new filename or a new run so historical checksums continue to mean something.
"""
from __future__ import annotations

import copy
import hashlib
import json
import os
import subprocess
import tempfile
import threading
import time
import uuid
from contextlib import contextmanager
from datetime import datetime, timezone
from pathlib import Path

try:
    import fcntl
except ImportError:  # Windows still gets thread-safe atomic writes, not flock.
    fcntl = None


def utc_now() -> str:
    return datetime.now(timezone.utc).isoformat(timespec="milliseconds").replace("+00:00", "Z")


def sha256(path: Path) -> str:
    """Hash file bytes without loading large media into memory."""
    digest = hashlib.sha256()
    with Path(path).open("rb") as handle:
        for block in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(block)
    return digest.hexdigest()


def _json_bytes(value) -> bytes:
    return (json.dumps(value, indent=2, sort_keys=True, ensure_ascii=False, allow_nan=False) + "\n").encode("utf-8")


def _atomic_json(path: Path, data: dict) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    descriptor, temporary = tempfile.mkstemp(prefix="." + path.name + ".", dir=path.parent)
    try:
        with os.fdopen(descriptor, "wb") as handle:
            handle.write(_json_bytes(data))
            handle.flush()
            os.fsync(handle.fileno())
        os.replace(temporary, path)
    finally:
        if os.path.exists(temporary):
            os.unlink(temporary)


def local_cost() -> dict:
    return {"status": "local_provider_fee_zero", "currency": "USD", "provider_fee": 0,
            "source": "local execution without a metered provider request",
            "electricity": {"status": "unmeasured", "amount": None},
            "hardware": {"status": "existing_hardware_cost_not_allocated", "amount": None}}


def _repository(root: Path) -> dict:
    def git(*args):
        try:
            result = subprocess.run(["git", "-C", str(root), *args], capture_output=True, text=True, timeout=10, check=False)
            return result.stdout.strip() if result.returncode == 0 else None
        except (OSError, subprocess.TimeoutExpired):
            return None
    revision = git("rev-parse", "HEAD")
    changes = git("status", "--porcelain", "--untracked-files=no")
    return {"name": root.name, "revision": revision or "unknown",
            "revision_status": "observed" if revision else "unavailable",
            "worktree_state": "unavailable" if changes is None else ("tracked_changes_present" if changes else "tracked_files_clean"),
            "note": "Untracked files are not included in the worktree-state check; artifact hashes identify their exact bytes."}


class Ledger:
    """One run directory, one canonical ledger, append-only stage attempts.

    ``with ledger.stage('render', 'designed-motion', command=argv) as stage:``
    lets a caller set ``stage['execution']['output']`` or ``stage['result']``.
    A successful context defaults to a technical pass; visual/human review stays
    pending. Exceptions become a failed attempt and are re-raised.
    """

    def __init__(self, run_dir: Path, repo_root: Path, campaign: dict):
        started = time.monotonic()
        started_at = utc_now()
        self.repo_root = Path(repo_root).resolve()
        self.run_dir = Path(run_dir).resolve()
        self._relative(self.run_dir)
        if not isinstance(campaign, dict):
            raise TypeError("campaign must be a JSON-compatible dictionary")
        campaign_bytes = _json_bytes(campaign)
        self.run_dir.mkdir(parents=True, exist_ok=True)
        self.path = self.run_dir / "build-dna.json"
        self._mutex = threading.RLock()
        self._lock_path = self.run_dir / ".build-dna.lock"
        self._session_started = time.monotonic()
        with self._locked():
            snapshot = self.run_dir / "campaign.snapshot.json"
            if self.path.exists():
                self.data = json.loads(self.path.read_text(encoding="utf-8"))
                if self.data.get("schema") != "famtastic.build-dna.v1":
                    raise ValueError("Existing run is not canonical Build DNA.")
                if not snapshot.is_file() or snapshot.read_bytes() != campaign_bytes:
                    raise ValueError("Campaign changed or snapshot is missing. Create a new run; never rebind an existing run.")
                expected_hash = self.data.get("campaign", {}).get("sha256")
                if expected_hash != sha256(snapshot):
                    raise ValueError("Campaign snapshot checksum does not match the existing ledger.")
                # Do not rewrite prior incomplete attempts: without a process
                # receipt, a new reader cannot claim the old worker stopped.
                # A retry gets a new attempt number while prior facts remain.
                return
            if snapshot.exists() and snapshot.read_bytes() != campaign_bytes:
                raise ValueError("Run directory contains a different campaign snapshot; choose a new directory.")
            if not snapshot.exists():
                _atomic_json(snapshot, campaign)
            snapshot_artifact = self._artifact(snapshot, "campaign_input", "operator-supplied instruction; asset rights require separate review", "run_evidence")
            self.data = {
                "schema": "famtastic.build-dna.v1",
                "build_id": "video-" + datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%S") + "-" + uuid.uuid4().hex[:10],
                "classification": "local_draft_review_pending", "created_at": started_at,
                "repository": _repository(self.repo_root),
                "recipe": {"routine": "local-video-studio", "version": "1.0.0", "build_class": "agency-marketing-video-draft",
                           "constraints": ["no automatic paid-provider fallback", "no publishing or external registration", "technical checks are not human approval"]},
                "campaign": {"id": campaign.get("id", campaign.get("name", "unreported")), "path": snapshot_artifact["path"], "sha256": snapshot_artifact["sha256"]},
                "stages": [{"stage_id": "run-initialized", "sequence": 1, "attempt": 1, "capability": "freeze-current-campaign-input",
                            "execution": {"provider": {"id": "local-filesystem", "execution_class": "deterministic"},
                                          "model": {"id": None, "status": "not_applicable"},
                                          "timing": {"status": "measured", "started_at": started_at, "ended_at": utc_now(),
                                                     "duration_ms": round((time.monotonic() - started) * 1000, 3)},
                                          "cost": local_cost(), "input": {"campaign_snapshot": snapshot_artifact}},
                            "result": {"status": "passed", "review": "review_pending"}}],
                "artifacts": [snapshot_artifact],
                "retrieval": {"filesystem": {"status": "available", "path": self._relative(self.path)},
                              "database": {"status": "not_registered", "reason": "Local draft only; no Drupal operation requested or executed."},
                              "site_studio": {"status": "not_registered", "reason": "No Site Studio packet or dispatch has been created."}},
                "integrity": {"artifact_hash_algorithm": "sha256", "self_hash": "excluded_to_avoid_recursive_hash"},
                "review": {"status": "review_pending", "reviewer": None, "decision": None},
                "completion": {"status": "in_progress"},
                "completions": [],
            }
            self._write()

    def _relative(self, path: Path) -> str:
        try:
            return Path(path).resolve().relative_to(self.repo_root).as_posix()
        except ValueError as exc:
            raise ValueError("Evidence must be inside the repository, including symlink targets.") from exc

    @contextmanager
    def _locked(self):
        with self._mutex:
            with self._lock_path.open("a+b") as handle:
                if fcntl is not None:
                    fcntl.flock(handle, fcntl.LOCK_EX)
                try:
                    yield
                finally:
                    if fcntl is not None:
                        fcntl.flock(handle, fcntl.LOCK_UN)

    def _refresh(self) -> None:
        self.data = json.loads(self.path.read_text(encoding="utf-8"))

    def _write(self) -> None:
        _atomic_json(self.path, self.data)

    def _artifact(self, path: Path, role: str, rights: str, retention: str) -> dict:
        path = Path(path)
        if not path.is_absolute():
            path = self.repo_root / path
        path = path.resolve()
        relative = self._relative(path)
        if path == self.path:
            raise ValueError("Do not add the ledger as its own artifact (recursive checksum).")
        if not path.is_file():
            raise ValueError(f"Artifact file does not exist: {relative}")
        if not role:
            raise ValueError("Artifact role must not be empty.")
        return {"role": role, "path": relative, "sha256": sha256(path), "size_bytes": path.stat().st_size,
                "rights": rights, "retention": retention}

    def add_artifact(self, path: Path, role: str, rights: str = "unconfirmed", retention: str = "run_evidence") -> dict:
        artifact = self._artifact(path, role, rights, retention)
        with self._locked():
            self._refresh()
            for existing in self.data["artifacts"]:
                if existing["path"] == artifact["path"]:
                    if existing["sha256"] != artifact["sha256"]:
                        raise ValueError("Previously recorded artifact bytes changed. Preserve old evidence and use a new filename or run.")
                    if existing["role"] == role:
                        return copy.deepcopy(existing)
            self.data["artifacts"].append(artifact)
            self._write()
        return copy.deepcopy(artifact)

    @contextmanager
    def stage(self, stage_id: str, capability: str, *, provider: str = "local", model_id: str | None = None,
              model_status: str = "not_applicable", command: list[str] | None = None, inputs: dict | None = None,
              prompt: dict | None = None, cost: dict | None = None):
        """Journal a measured attempt; no model identity or cost is guessed.

        Pass ``cost`` explicitly for nonlocal providers. Unspecified nonlocal
        provider cost is unknown, never zero. Commands must exclude credentials.
        """
        if not stage_id or not capability or not provider:
            raise ValueError("Stage ID, capability and provider are required.")
        started, started_at = time.monotonic(), utc_now()
        with self._locked():
            self._refresh()
            attempt = 1 + max((item["attempt"] for item in self.data["stages"] if item["stage_id"] == stage_id), default=0)
            stage = {"stage_id": stage_id, "sequence": len(self.data["stages"]) + 1, "attempt": attempt, "capability": capability,
                     "execution": {"provider": {"id": provider}, "model": {"id": model_id, "status": model_status},
                                   "timing": {"status": "running", "started_at": started_at},
                                   "cost": copy.deepcopy(cost) if cost is not None else (
                                       local_cost() if provider == "local" or provider.startswith("local-") else {"status": "unreported", "currency": "USD", "provider_fee": None}),
                                   "input": copy.deepcopy(inputs or {})},
                     "result": {"status": "running", "review": "review_pending"}}
            if command is not None:
                stage["execution"]["command"] = copy.deepcopy(command)
            if prompt is not None:
                stage["execution"]["prompt"] = copy.deepcopy(prompt)
            self.data["stages"].append(copy.deepcopy(stage))
            self._write()
        try:
            yield stage
        except BaseException as exc:
            stage["result"] = {"status": "failed", "error_type": type(exc).__name__, "error": str(exc)[:2000], "review": "review_pending"}
            raise
        else:
            if stage["result"].get("status") == "running":
                stage["result"]["status"] = "passed"
        finally:
            stage["execution"]["timing"] = {"status": "measured", "started_at": started_at, "ended_at": utc_now(),
                                              "duration_ms": round((time.monotonic() - started) * 1000, 3)}
            with self._locked():
                self._refresh()
                for index, saved in enumerate(self.data["stages"]):
                    if saved["stage_id"] == stage_id and saved["attempt"] == attempt:
                        self.data["stages"][index] = copy.deepcopy(stage)
                        break
                self._write()

    def finalize(self, status: str = "gated", qa: dict | None = None) -> dict:
        """Verify retained checksums and record the local outcome with review pending."""
        if status not in {"passed", "gated", "partial", "failed", "not_applicable"}:
            raise ValueError("Unsupported completion status.")
        with self._locked():
            self._refresh()
            failures = []
            for artifact in self.data["artifacts"]:
                path = self.repo_root / artifact["path"]
                try:
                    self._relative(path)
                    if not path.is_file() or sha256(path) != artifact["sha256"]:
                        failures.append(artifact["path"])
                except (OSError, ValueError):
                    failures.append(artifact["path"])
            completion = {"status": "failed" if failures else status, "recorded_at": utc_now(),
                          "session_duration_ms": round((time.monotonic() - self._session_started) * 1000, 3),
                          "integrity": "failed" if failures else "passed", "changed_or_missing_artifacts": failures,
                          "qa": copy.deepcopy(qa or {}), "review": "review_pending", "publishing": "not_authorized_by_this_record"}
            self.data["completion"] = completion
            self.data["completions"].append(copy.deepcopy(completion))
            self.data["review"] = {"status": "review_pending", "reviewer": None, "decision": None}
            self._write()
            return copy.deepcopy(self.data)
