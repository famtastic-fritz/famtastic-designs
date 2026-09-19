from __future__ import annotations

import hashlib
import importlib.util
import json
import os
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path


SCRIPT = Path(__file__).with_name("publish-no-catch-film.py")
SPEC = importlib.util.spec_from_file_location("publish_no_catch_film", SCRIPT)
assert SPEC and SPEC.loader
publisher = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = publisher
SPEC.loader.exec_module(publisher)


def sha256_bytes(value: bytes) -> str:
    return hashlib.sha256(value).hexdigest()


class TestAssets:
    def __init__(self, root: Path):
        self.directory = root / "artifacts"
        self.directory.mkdir()
        self.contents = {
            "whats-the-catch-20260919.mp4": b"fixture-mp4-container-data",
            "whats-the-catch-20260919.jpg": b"\xff\xd8\xffposter-bytes\xff\xd9",
            "whats-the-catch-20260919.vtt": b"WEBVTT\n\n00:00:00.000 --> 00:00:01.000\nCaption\n",
        }
        for name, body in self.contents.items():
            (self.directory / name).write_bytes(body)

    @property
    def hashes(self):
        return {name: sha256_bytes(body) for name, body in self.contents.items()}


def ffprobe_runner(result: dict | None = None, returncode: int = 0):
    payload = result or {
        "format": {"format_name": "mov,mp4,m4a,3gp,3g2,mj2"},
        "streams": [{"codec_type": "video"}, {"codec_type": "audio"}],
    }

    def run(command, **kwargs):
        return subprocess.CompletedProcess(command, returncode, json.dumps(payload), "")

    return run


def invoke_remote(script: str, args: list[str], home: Path) -> subprocess.CompletedProcess:
    # The remote snippets use $HOME. Keep the fixture path explicit and scoped
    # to this child process rather than changing the test runner's environment.
    wrapper = 'export HOME="$FAM_TEST_HOME"; exec bash -s -- "$@"'
    shim_dir = home / "test-bin"
    shim_dir.mkdir(exist_ok=True)
    mv_shim = shim_dir / "mv"
    if not mv_shim.exists():
        mv_shim.write_text(
            "#!/usr/bin/env python3\n"
            "import os, sys\n"
            "args = [arg for arg in sys.argv[1:] if arg != '--']\n"
            "if args[:2] != ['-T', '-n'] or len(args) != 4: sys.exit(64)\n"
            "source, target = args[2], args[3]\n"
            "if os.path.lexists(target): sys.exit(0)\n"
            "try:\n"
            "    os.link(source, target)\n"
            "    os.unlink(source)\n"
            "except OSError as error:\n"
            "    print(error, file=sys.stderr)\n"
            "    sys.exit(1)\n",
            encoding="utf-8",
        )
        mv_shim.chmod(0o755)
    env = os.environ.copy()
    env["FAM_TEST_HOME"] = str(home)
    env["PATH"] = str(shim_dir) + os.pathsep + env.get("PATH", "")
    return subprocess.run(
        ["bash", "-c", wrapper, "remote-script", *args],
        input=script,
        capture_output=True,
        text=True,
        env=env,
        check=False,
        timeout=20,
    )


def remote_state(output: str) -> str:
    for line in output.splitlines():
        if line.startswith("NO_CATCH_STATE\t"):
            return line.split("\t", 1)[1]
    raise AssertionError(f"Missing state in remote output: {output!r}")


class FakeRunner:
    """Simulate SSH/rsync orchestration without making network requests."""

    def __init__(self, assets: list[publisher.Asset]):
        self.assets = assets
        self.target_files: dict[str, bytes] = {}
        self.stage_files: dict[str, bytes] = {}
        self.calls = []

    def __call__(self, command, **kwargs):
        self.calls.append((list(command), kwargs))
        if command[0] == "ffprobe":
            return ffprobe_runner()(command, **kwargs)
        if command[0] == "rsync":
            names = [asset.name for asset in self.assets]
            self.stage_files = {
                asset.name: asset.path.read_bytes() for asset in self.assets
            }
            return subprocess.CompletedProcess(command, 0, "", "")
        if command[0] != "ssh":
            raise AssertionError(f"Unexpected command: {command}")
        remote_args = command[3].split()[3:]
        script = kwargs["input"]
        if script == publisher.REMOTE_PREFLIGHT:
            present = len(self.target_files)
            matching = all(
                self.target_files.get(asset.name) is not None
                and sha256_bytes(self.target_files[asset.name]) == asset.sha256
                for asset in self.assets
            )
            state = "ABSENT" if present == 0 else "EXACT_MATCH" if matching and present == len(self.assets) else "COLLISION"
            lines = []
            for asset in self.assets:
                body = self.target_files.get(asset.name)
                lines.append(
                    f"NO_CATCH_FILE\t{asset.name}\t{int(body is not None)}\t{sha256_bytes(body) if body is not None else '-'}\t{len(body) if body is not None else '-'}"
                )
            lines.append(f"NO_CATCH_STATE\t{state}")
            return subprocess.CompletedProcess(command, 0, "\n".join(lines) + "\n", "")
        if script == publisher.REMOTE_CREATE_STAGE:
            return subprocess.CompletedProcess(command, 0, f"NO_CATCH_STAGE\tCREATED\t{remote_args[0]}\n", "")
        if script == publisher.REMOTE_PROMOTE:
            if self.target_files:
                return subprocess.CompletedProcess(command, 26, "", "publication collision appeared")
            expected_hashes = remote_args[1:]
            for asset, expected in zip(self.assets, expected_hashes, strict=True):
                body = self.stage_files[asset.name]
                if sha256_bytes(body) != expected:
                    return subprocess.CompletedProcess(command, 25, "", "staged asset hash mismatch")
                self.target_files[asset.name] = body
            final = "PUBLISHED"
            lines = [f"NO_CATCH_STATE\t{final}"]
            for asset in self.assets:
                body = self.target_files[asset.name]
                lines.append(f"NO_CATCH_FINAL\t{asset.name}\t{sha256_bytes(body)}\t{len(body)}")
            return subprocess.CompletedProcess(command, 0, "\n".join(lines) + "\n", "")
        raise AssertionError(f"Unexpected remote script: {script[:80]!r}")


class PublisherValidationTests(unittest.TestCase):
    def test_exact_three_assets_are_validated_and_hashed(self):
        with tempfile.TemporaryDirectory() as tmp:
            assets = TestAssets(Path(tmp))
            result = publisher.inspect_assets(assets.directory, runner=ffprobe_runner())
            self.assertEqual([a.name for a in result], list(publisher.ASSET_TYPES))
            self.assertEqual({a.name: a.sha256 for a in result}, assets.hashes)
            self.assertEqual([a.mime for a in result], ["video/mp4", "image/jpeg", "text/vtt"])

    def test_rejects_symlink_and_non_video_mp4(self):
        with tempfile.TemporaryDirectory() as tmp:
            assets = TestAssets(Path(tmp))
            mp4 = assets.directory / "whats-the-catch-20260919.mp4"
            saved = Path(tmp) / "saved.mp4"
            mp4.rename(saved)
            mp4.symlink_to(saved)
            with self.assertRaisesRegex(publisher.PublishError, "regular non-symlink"):
                publisher.inspect_assets(assets.directory, runner=ffprobe_runner())

            mp4.unlink()
            mp4.write_bytes(assets.contents[mp4.name])
            bad_probe = ffprobe_runner({"format": {"format_name": "mp3"}, "streams": [{"codec_type": "audio"}]})
            with self.assertRaisesRegex(publisher.PublishError, "supported MP4-family"):
                publisher.inspect_assets(assets.directory, runner=bad_probe)


class RemoteShellTests(unittest.TestCase):
    def test_remote_preflight_stage_promote_and_exact_match(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            assets = TestAssets(root)
            validated = publisher.inspect_assets(assets.directory, runner=ffprobe_runner())
            home = root / "remote-home"
            (home / "public_html").mkdir(parents=True)
            hashes = [asset.sha256 for asset in validated]

            before = invoke_remote(publisher.REMOTE_PREFLIGHT, hashes, home)
            self.assertEqual(before.returncode, 0, before.stderr)
            self.assertEqual(remote_state(before.stdout), "ABSENT")
            self.assertEqual(publisher._parse_protocol(before.stdout, "NO_CATCH_FILE")[1][validated[0].name]["exists"], "0")

            stage_id = ".famtastic-no-catch-" + "a" * 32
            staged = invoke_remote(publisher.REMOTE_CREATE_STAGE, [stage_id], home)
            self.assertEqual(staged.returncode, 0, staged.stderr)
            self.assertEqual((home / stage_id).stat().st_mode & 0o777, 0o700)
            for asset in validated:
                (home / stage_id / asset.name).write_bytes(asset.path.read_bytes())

            promoted = invoke_remote(publisher.REMOTE_PROMOTE, [stage_id, *hashes], home)
            self.assertEqual(promoted.returncode, 0, promoted.stderr)
            self.assertEqual(remote_state(promoted.stdout), "PUBLISHED")
            self.assertEqual(
                {name: publisher._sha256(home / "public_html/media/films" / name) for name in assets.hashes},
                assets.hashes,
            )
            self.assertTrue((home / stage_id).is_dir(), "private upload originals should remain for review")
            self.assertEqual(
                {p.name: p.stat().st_mode & 0o777 for p in (home / "public_html/media/films").iterdir()},
                {name: 0o644 for name in assets.hashes},
            )
            after = invoke_remote(publisher.REMOTE_PREFLIGHT, hashes, home)
            self.assertEqual(after.returncode, 0, after.stderr)
            self.assertEqual(remote_state(after.stdout), "EXACT_MATCH")

    def test_remote_preflight_collision_does_not_modify_target(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            assets = TestAssets(root)
            validated = publisher.inspect_assets(assets.directory, runner=ffprobe_runner())
            home = root / "remote-home"
            media = home / "public_html/media/films"
            media.mkdir(parents=True)
            target = media / validated[0].name
            target.write_bytes(b"pre-existing different file")
            original_hash = publisher._sha256(target)

            checked = invoke_remote(publisher.REMOTE_PREFLIGHT, [asset.sha256 for asset in validated], home)
            self.assertEqual(checked.returncode, 0, checked.stderr)
            self.assertEqual(remote_state(checked.stdout), "COLLISION")
            self.assertEqual(publisher._sha256(target), original_hash)

    def test_remote_promote_refuses_staged_hash_mismatch(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            assets = TestAssets(root)
            validated = publisher.inspect_assets(assets.directory, runner=ffprobe_runner())
            home = root / "remote-home"
            (home / "public_html").mkdir(parents=True)
            stage_id = ".famtastic-no-catch-" + "b" * 32
            (home / stage_id).mkdir(mode=0o700)
            for asset in validated:
                (home / stage_id / asset.name).write_bytes(asset.path.read_bytes())
            (home / stage_id / validated[1].name).write_bytes(b"altered poster")
            result = invoke_remote(publisher.REMOTE_PROMOTE, [stage_id, *[asset.sha256 for asset in validated]], home)
            self.assertNotEqual(result.returncode, 0)
            self.assertIn("staged asset hash mismatch", result.stderr)
            self.assertFalse((home / "public_html/media").exists())

    def test_remote_promote_refuses_target_created_after_preflight(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            assets = TestAssets(root)
            validated = publisher.inspect_assets(assets.directory, runner=ffprobe_runner())
            home = root / "remote-home"
            (home / "public_html").mkdir(parents=True)
            stage_id = ".famtastic-no-catch-" + "c" * 32
            stage = home / stage_id
            stage.mkdir(mode=0o700)
            for asset in validated:
                (stage / asset.name).write_bytes(asset.path.read_bytes())
            media = home / "public_html/media/films"
            media.mkdir(parents=True)
            conflicting = media / validated[0].name
            conflicting.write_bytes(b"appeared after preflight")
            expected = publisher._sha256(conflicting)

            result = invoke_remote(
                publisher.REMOTE_PROMOTE,
                [stage_id, *[asset.sha256 for asset in validated]],
                home,
            )
            self.assertNotEqual(result.returncode, 0)
            self.assertIn("publication collision appeared", result.stderr)
            self.assertEqual(publisher._sha256(conflicting), expected)
            self.assertEqual(sorted(path.name for path in media.iterdir()), [validated[0].name])
            self.assertTrue(stage.is_dir())


class OrchestrationTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.root = Path(self.temp.name)
        self.artifacts = TestAssets(self.root)
        self.assets = publisher.inspect_assets(self.artifacts.directory, runner=ffprobe_runner())
        self.runner = FakeRunner(self.assets)
        self.receipts = self.root / "receipts"

    def tearDown(self):
        self.temp.cleanup()

    def execute(self, *, apply=False):
        return publisher.execute(
            self.artifacts.directory,
            apply=apply,
            receipt_dir=self.receipts,
            runner=self.runner,
            tools={"ffprobe": "ffprobe", "ssh": "ssh", "rsync": "rsync"},
            ssh_target="test-user@example.invalid",
        )

    def test_default_is_dry_run_and_receipt_is_saved(self):
        code, receipt = self.execute()
        self.assertEqual(code, 0)
        self.assertEqual(receipt["status"], "dry_run_ready")
        self.assertEqual(receipt["remote_preflight"], "ABSENT")
        self.assertTrue(Path(receipt["receipt_path"]).is_file())
        self.assertEqual([call[0][0] for call in self.runner.calls], ["ffprobe", "ssh"])
        self.assertFalse(self.runner.stage_files)
        self.assertFalse(self.runner.target_files)

    def test_exact_existing_set_is_verified_noop(self):
        self.runner.target_files = dict(self.artifacts.contents)
        code, receipt = self.execute(apply=True)
        self.assertEqual(code, 0)
        self.assertEqual(receipt["status"], "verified_noop")
        self.assertEqual(receipt["remote_final_hashes"], self.artifacts.hashes)
        self.assertEqual(receipt["remote_final_sizes"], {name: len(body) for name, body in self.artifacts.contents.items()})
        self.assertEqual([call[0][0] for call in self.runner.calls], ["ffprobe", "ssh"])

    def test_partial_or_different_target_collision_refuses_upload(self):
        self.runner.target_files = {next(iter(self.artifacts.contents)): b"unrelated"}
        code, receipt = self.execute(apply=True)
        self.assertEqual(code, 2)
        self.assertEqual(receipt["status"], "refused_collision")
        self.assertFalse(self.runner.stage_files)
        self.assertEqual([call[0][0] for call in self.runner.calls], ["ffprobe", "ssh"])

    def test_apply_uploads_only_exact_names_and_records_remote_hashes(self):
        code, receipt = self.execute(apply=True)
        self.assertEqual(code, 0, receipt)
        self.assertEqual(receipt["status"], "published")
        self.assertEqual(receipt["remote_final_hashes"], self.artifacts.hashes)
        self.assertEqual(receipt["remote_final_sizes"], {name: len(body) for name, body in self.artifacts.contents.items()})
        rsync = next(call[0] for call in self.runner.calls if call[0][0] == "rsync")
        destination_index = next(i for i, part in enumerate(rsync) if part.startswith("test-user@example.invalid:"))
        self.assertEqual(rsync[rsync.index("--") + 1:destination_index], list(self.artifacts.contents))
        self.assertNotIn("--delete", rsync)
        self.assertNotIn("--append-verify", rsync)
        promote = next(kwargs["input"] for command, kwargs in self.runner.calls if command[0] == "ssh" and kwargs["input"] == publisher.REMOTE_PROMOTE)
        self.assertIn("mv -T -n", publisher.REMOTE_PROMOTE)
        self.assertIn("hash_file", promote)

    def test_receipt_failure_on_remote_final_hash_mismatch(self):
        class BadFinalRunner(FakeRunner):
            def __call__(self, command, **kwargs):
                result = super().__call__(command, **kwargs)
                if command[0] == "ssh" and kwargs.get("input") == publisher.REMOTE_PROMOTE and result.returncode == 0:
                    output = result.stdout.replace(self.assets[0].sha256, "0" * 64, 1)
                    return subprocess.CompletedProcess(command, 0, output, "")
                return result

        self.runner = BadFinalRunner(self.assets)
        code, receipt = self.execute(apply=True)
        self.assertEqual(code, 2)
        self.assertEqual(receipt["status"], "failed")
        self.assertIn("Remote final hashes do not match", receipt["error"])
        self.assertTrue(Path(receipt["receipt_path"]).is_file())


if __name__ == "__main__":
    unittest.main()
