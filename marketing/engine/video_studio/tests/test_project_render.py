import contextlib
import io
import json
import shutil
import subprocess
import tempfile
import unittest
from pathlib import Path
from unittest import mock

from fam_video import project_render
from fam_video.campaign import load_brand, sha256
from fam_video.cli import main, parser


REPO = Path(__file__).resolve().parents[4]
BRAND_PATH = REPO / "marketing/brands/famtastic/video-studio/brand.json"


class LocalProjectTests(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory()
        self.addCleanup(self.temporary.cleanup)
        self.root = Path(self.temporary.name).resolve()
        self.brand = load_brand(BRAND_PATH, REPO)
        self.project = self.root / "authored-project"
        self._write_project()

    def _write_project(self, *, duration=721 / 24, credit=True, files=None, audio_master=None):
        self.project.mkdir(parents=True, exist_ok=True)
        (self.project / "assets").mkdir(exist_ok=True)
        (self.project / "css").mkdir(exist_ok=True)
        (self.project / "js").mkdir(exist_ok=True)
        shutil.copyfile(self.brand["logo"], self.project / "assets/famtastic-designs-logo-v1.png")
        (self.project / "assets/soundtrack.m4a").write_bytes(b"source AAC test bytes")
        (self.project / "assets/soundtrack-alt.m4a").write_bytes(b"alternate AAC test bytes")
        (self.project / "assets/local.svg").write_text(
            '<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"></svg>', encoding="utf-8"
        )
        (self.project / "css/site.css").write_text(
            'main{background-image:url("../assets/local.svg")}', encoding="utf-8"
        )
        (self.project / "js/scene.js").write_text(
            '// Docs mention https://remote.example.invalid but are inert.\nconst scene = "local";\n', encoding="utf-8"
        )
        (self.project / "hyperframes.json").write_text(json.dumps({
            "schema": "https://hyperframes.dev/schemas/project.json",
            "registry": "https://registry.npmjs.org/",
        }), encoding="utf-8")
        credit = project_render._canonical_credit_markup(self.brand) if credit else ""
        html = f'''<!doctype html>
<html><head><link rel="stylesheet" href="css/site.css">
<script type="module" src="js/scene.js"></script></head><body>
<main data-composition-id="authored-project" data-width="1080" data-height="1920" data-duration="{duration!r}">
  <img src="assets/famtastic-designs-logo-v1.png" alt="FAMtastic Designs">
  <a href="https://famtasticdesigns.com/">Visit FAMtastic Designs</a>
</main>{credit}</body></html>'''
        (self.project / "index.html").write_text(html, encoding="utf-8")
        listed = list(files or [
            "index.html", "css/site.css", "js/scene.js", "assets/local.svg",
            "assets/famtastic-designs-logo-v1.png", "hyperframes.json",
        ])
        if audio_master and audio_master.get("path") not in listed:
            listed.append(audio_master["path"])
        manifest = {
            "schema": project_render.SCHEMA,
            "id": "authored-project",
            "title": "Authored local composition",
            "entrypoint": "index.html",
            "width": 1080,
            "height": 1920,
            "fps": 24,
            "duration_seconds": duration,
            "files": listed,
            "brand_logo": {
                "path": "assets/famtastic-designs-logo-v1.png",
                "sha256": self.brand["logo_sha256"],
            },
            "creator_credit_marker": project_render.CREATOR_MARKER,
        }
        if audio_master is not None:
            manifest["audio_master"] = audio_master
        (self.project / "project.json").write_text(json.dumps(manifest, indent=2), encoding="utf-8")
        return manifest

    def test_manifest_snapshots_manifest_and_local_referenced_assets(self):
        parsed = project_render._parse_project(self.project, "project.json", self.brand)
        self.assertEqual(parsed["frame_count"], 721)
        self.assertEqual(parsed["brand_logo_path"], "assets/famtastic-designs-logo-v1.png")
        self.assertEqual(set(parsed["files"]), {
            "index.html", "css/site.css", "js/scene.js", "assets/local.svg", "hyperframes.json",
            "assets/famtastic-designs-logo-v1.png",
        })

    def test_actual_remote_resources_are_rejected_but_anchor_and_comment_urls_are_allowed(self):
        project_render._parse_project(self.project, "project.json", self.brand)
        html_path = self.project / "index.html"
        html_path.write_text(html_path.read_text(encoding="utf-8").replace(
            'alt="FAMtastic Designs">',
            'alt="FAMtastic Designs" srcset="data:image/png;base64,AAAA 1x, https://cdn.example.invalid/a.png 2x">',
        ), encoding="utf-8")
        with self.assertRaisesRegex(project_render.ProjectError, "Remote fetched assets"):
            project_render._parse_project(self.project, "project.json", self.brand)
        self._write_project()
        (self.project / "css/site.css").write_text('main{background:url("https://cdn.example.invalid/a.png")}', encoding="utf-8")
        with self.assertRaisesRegex(project_render.ProjectError, "Remote fetched assets"):
            project_render._parse_project(self.project, "project.json", self.brand)
        (self.project / "css/site.css").write_text('main{background:url("../assets/local.svg")}', encoding="utf-8")
        for source, message in (
            ('fetch("https://cdn.example.invalid/movie.mp4");', "may not fetch network"),
            ('new Worker("https://cdn.example.invalid/worker.js");', "Remote fetched assets"),
            ('export {x} from "https://cdn.example.invalid/module.js";', "Remote fetched assets"),
            ('node.setAttribute("src", "https://cdn.example.invalid/movie.mp4");', "Remote fetched assets"),
        ):
            with self.subTest(source=source):
                (self.project / "js/scene.js").write_text(source, encoding="utf-8")
                with self.assertRaisesRegex(project_render.ProjectError, message):
                    project_render._parse_project(self.project, "project.json", self.brand)

    def test_unlisted_assets_and_symlink_escapes_are_rejected(self):
        manifest = self._write_project(files=[
            "index.html", "css/site.css", "js/scene.js", "assets/famtastic-designs-logo-v1.png",
        ])
        with self.assertRaisesRegex(project_render.ProjectError, "absent from manifest"):
            project_render._parse_project(self.project, "project.json", self.brand)
        manifest["files"].append("assets/local.svg")
        (self.project / "project.json").write_text(json.dumps(manifest), encoding="utf-8")
        external = self.root / "outside.svg"
        external.write_text("<svg/>", encoding="utf-8")
        (self.project / "assets/local.svg").unlink()
        (self.project / "assets/local.svg").symlink_to(external)
        with self.assertRaisesRegex(project_render.ProjectError, "outside the project"):
            project_render._parse_project(self.project, "project.json", self.brand)

    def test_manifest_enforces_creator_credit_logo_and_frame_alignment(self):
        self._write_project(credit=False)
        with self.assertRaisesRegex(project_render.ProjectError, "exact canonical creator-credit"):
            project_render._parse_project(self.project, "project.json", self.brand)
        credit_markup = project_render._canonical_credit_markup(self.brand)
        index = self.project / "index.html"
        index.write_text(index.read_text(encoding="utf-8").replace("</body>", f"<!--{credit_markup}--></body>"), encoding="utf-8")
        with self.assertRaisesRegex(project_render.ProjectError, "must include the exact canonical creator-credit"):
            project_render._parse_project(self.project, "project.json", self.brand)
        self._write_project(duration=30.02)
        with self.assertRaisesRegex(project_render.ProjectError, "whole number of frames"):
            project_render._parse_project(self.project, "project.json", self.brand)
        self._write_project()
        logo = self.project / "assets/famtastic-designs-logo-v1.png"
        logo.write_bytes(b"different image")
        with self.assertRaisesRegex(project_render.ProjectError, "logo bytes"):
            project_render._parse_project(self.project, "project.json", self.brand)

    def _patch_render_tools(self):
        provider = {"available": True, "ok": True, "executable": "/fake/hyperframes", "version": "fake-1"}
        def fake_render(project_dir, output, **kwargs):
            output.write_bytes(b"test-only mp4 bytes")
            (Path(project_dir) / "hyperframes-check.log").write_text("check passed", encoding="utf-8")
            (Path(project_dir) / "hyperframes-render.log").write_text("render passed", encoding="utf-8")
            return {"status": "rendered", "version": "fake-1"}
        def fake_verify(path, expected=None):
            return {"passed": True, "status": "passed", "path": str(path), "expected": expected}
        def fake_sheet(video, destination, times):
            destination.write_bytes(b"test-only contact sheet")
            return {"status": "passed", "path": str(destination), "frame_times_seconds": times}
        return (
            mock.patch.object(project_render.hyperframes, "inspect", return_value=provider),
            mock.patch.object(project_render.hyperframes, "render", side_effect=fake_render),
            mock.patch.object(project_render, "verify_video", side_effect=fake_verify),
            mock.patch.object(project_render, "contact_sheet", side_effect=fake_sheet),
        )

    @staticmethod
    def _audio_probe(stdout=None):
        return subprocess.CompletedProcess(
            ["ffprobe"], 0,
            stdout if stdout is not None else json.dumps({"streams": [{
                "index": 0, "codec_type": "audio", "codec_name": "aac", "duration": "30.0",
            }]}),
            "",
        )

    def _declare_audio_master(self, path="assets/soundtrack.m4a", **overrides):
        audio_master = {"path": path, "mode": "copy", "start_seconds": 0}
        audio_master.update(overrides)
        return audio_master

    def test_audio_master_requires_exact_local_aac_source_and_bounded_duration(self):
        with self.subTest("unsafe path"):
            self._write_project(audio_master=self._declare_audio_master("../outside.m4a"))
            with self.assertRaisesRegex(project_render.ProjectError, "escape|relative"):
                project_render._parse_project(self.project, "project.json", self.brand)
        with self.subTest("missing path"):
            self._write_project(audio_master=self._declare_audio_master("assets/missing.m4a"))
            with self.assertRaisesRegex(project_render.ProjectError, "missing or unreadable"):
                project_render._parse_project(self.project, "project.json", self.brand)
        with self.subTest("wrong codec"):
            self._write_project(audio_master=self._declare_audio_master())
            wrong_codec = self._audio_probe(json.dumps({"streams": [{
                "index": 0, "codec_type": "audio", "codec_name": "mp3", "duration": "30.0",
            }]}))
            with mock.patch.object(project_render.subprocess, "run", return_value=wrong_codec):
                with self.assertRaisesRegex(project_render.ProjectError, "AAC audio stream"):
                    project_render._parse_project(self.project, "project.json", self.brand)
        with self.subTest("too long"):
            self._write_project(audio_master=self._declare_audio_master())
            too_long = self._audio_probe(json.dumps({"streams": [{
                "index": 0, "codec_type": "audio", "codec_name": "aac", "duration": "30.2",
            }]}))
            with mock.patch.object(project_render.subprocess, "run", return_value=too_long):
                with self.assertRaisesRegex(project_render.ProjectError, "more than one frame"):
                    project_render._parse_project(self.project, "project.json", self.brand)
        with self.subTest("invalid offset"):
            self._write_project(audio_master=self._declare_audio_master(start_seconds=0.01))
            with self.assertRaisesRegex(project_render.ProjectError, "exactly 0"):
                project_render._parse_project(self.project, "project.json", self.brand)

    def test_audio_master_is_snapshotted_and_cache_identity_binds_config_and_bytes(self):
        self._write_project(audio_master=self._declare_audio_master())
        probe = {"command": ["ffprobe"], "stream_index": 0, "codec": "aac", "duration_seconds": 30.0}
        provider = {"executable": "/fake/hyperframes", "version": "fake-1"}
        engine_root = Path(project_render.__file__).resolve().parent
        with mock.patch.object(project_render, "_probe_audio_master", return_value=probe):
            parsed_first = project_render._parse_project(self.project, "project.json", self.brand)
            key_first = project_render._identity(parsed_first, self.brand, BRAND_PATH, provider, "draft", engine_root)
            audio_path = self.project / "assets/soundtrack.m4a"
            audio_path.write_bytes(b"changed source AAC bytes")
            parsed_bytes = project_render._parse_project(self.project, "project.json", self.brand)
            key_bytes = project_render._identity(parsed_bytes, self.brand, BRAND_PATH, provider, "draft", engine_root)
            self.assertNotEqual(key_first, key_bytes)
            manifest = json.loads((self.project / "project.json").read_text(encoding="utf-8"))
            manifest["audio_master"]["path"] = "assets/soundtrack-alt.m4a"
            manifest["files"].append("assets/soundtrack-alt.m4a")
            (self.project / "project.json").write_text(json.dumps(manifest, indent=2), encoding="utf-8")
            parsed_config = project_render._parse_project(self.project, "project.json", self.brand)
            key_config = project_render._identity(parsed_config, self.brand, BRAND_PATH, provider, "draft", engine_root)
        self.assertNotEqual(key_bytes, key_config)

    def test_audio_master_stream_copy_uses_frozen_master_and_keeps_raw_video(self):
        audio_config = self._declare_audio_master()
        self._write_project(audio_master=audio_config)
        patches = self._patch_render_tools()
        completed = self._audio_probe()

        def fake_ffmpeg(command, **kwargs):
            output = Path(command[-1])
            output.write_bytes(b"stream-copied final test mp4")
            return subprocess.CompletedProcess(command, 0, "", "")

        original_run = subprocess.run

        def probe_and_remux(command, **kwargs):
            if "-show_entries" in command:
                return completed
            if "-movflags" in command:
                return fake_ffmpeg(command, **kwargs)
            return original_run(command, **kwargs)

        verify_proof = {"passed": True, "status": "passed", "video": {"codec": "h264"},
                        "audio": [{"codec": "aac", "duration_seconds": 30.0}]}
        with contextlib.ExitStack() as stack:
            inspect, render, verify, sheet = [stack.enter_context(patch) for patch in patches]
            verify.side_effect = lambda path, expected=None: dict(verify_proof, path=str(path), expected=expected)
            probe_and_remux_patch = stack.enter_context(mock.patch.object(
                project_render.subprocess, "run", side_effect=probe_and_remux))
            result = project_render.render_project(self.project, self.root, self.brand, BRAND_PATH,
                                                   executable="fake-hyperframes")
        run = Path(result["run_dir"])
        raw_video = Path(result["raw_hyperframes_video"])
        final_video = Path(result["video"])
        frozen_master = Path(result["project_snapshot"]) / "assets/soundtrack.m4a"
        self.assertEqual(raw_video.name, "hyperframes-render.mp4")
        self.assertTrue(raw_video.is_file())
        self.assertTrue(final_video.is_file())
        self.assertEqual(sha256(frozen_master), sha256(self.project / "assets/soundtrack.m4a"))
        self.assertEqual(render.call_args.args[1], raw_video)
        command = [call.args[0] for call in probe_and_remux_patch.call_args_list if "-map" in call.args[0]][0]
        self.assertEqual(command[command.index("-i") + 1], str(raw_video))
        self.assertEqual(command[command.index("-i", command.index("-i") + 1) + 1], str(frozen_master))
        self.assertEqual(command[command.index("-map") + 1], "0:v:0")
        self.assertEqual(command[command.index("-map", command.index("-map") + 1) + 1], "1:a:0")
        self.assertIn("-c", command)
        self.assertEqual(command[command.index("-c") + 1], "copy")
        self.assertEqual(command[command.index("-movflags") + 1], "+faststart")
        self.assertNotIn("-ss", command)
        self.assertEqual(result["sha256"], sha256(final_video))
        self.assertEqual(result["raw_hyperframes_video_sha256"], sha256(raw_video))
        mastering_log = json.loads((run / "audio-mastering.log").read_text(encoding="utf-8"))
        self.assertEqual(mastering_log["status"], "passed")
        self.assertEqual(mastering_log["inputs"]["audio_master_sha256"], sha256(frozen_master))
        dna = json.loads(Path(result["build_dna"]).read_text(encoding="utf-8"))
        roles = {artifact["role"] for artifact in dna["artifacts"]}
        self.assertIn("raw_hyperframes_video", roles)
        self.assertIn("audio_master_source", roles)
        self.assertIn("audio_mastering_log", roles)
        self.assertIn("video_draft", roles)

    def test_audio_master_timeout_retains_raw_render_and_failed_mastering_log(self):
        self._write_project(audio_master=self._declare_audio_master())
        patches = self._patch_render_tools()

        original_run = subprocess.run

        def probe_then_timeout(command, **kwargs):
            if "-show_entries" in command:
                return self._audio_probe()
            if "-movflags" in command:
                raise subprocess.TimeoutExpired(command, kwargs.get("timeout", 1), output=b"partial stdout", stderr=b"timed out")
            return original_run(command, **kwargs)

        with contextlib.ExitStack() as stack:
            inspect, render, verify, sheet = [stack.enter_context(patch) for patch in patches]
            stack.enter_context(mock.patch.object(project_render.subprocess, "run", side_effect=probe_then_timeout))
            with self.assertRaisesRegex(project_render.ProjectError, "timed out after 1s"):
                project_render.render_project(self.project, self.root, self.brand, BRAND_PATH,
                                               executable="fake-hyperframes", timeout=1)
        runs = list((self.root / "artifacts/video-studio").glob("project-authored-project-*"))
        self.assertEqual(len(runs), 1)
        run = runs[0]
        self.assertTrue((run / "hyperframes-render.mp4").is_file())
        self.assertFalse((run / "video.mp4").exists())
        mastering_log = json.loads((run / "audio-mastering.log").read_text(encoding="utf-8"))
        self.assertEqual(mastering_log["status"], "failed")
        self.assertEqual(mastering_log["failure"], "timeout")
        dna = json.loads((run / "build-dna.json").read_text(encoding="utf-8"))
        self.assertEqual(dna["completion"]["status"], "failed")
        self.assertTrue(any(a["role"] == "raw_hyperframes_video" for a in dna["artifacts"]))
        self.assertTrue(any(a["role"] == "audio_mastering_log" for a in dna["artifacts"]))

    def test_render_records_frozen_inputs_and_only_reuses_completed_cache(self):
        patches = self._patch_render_tools()
        with contextlib.ExitStack() as stack:
            inspect, render, verify, sheet = [stack.enter_context(patch) for patch in patches]
            first = project_render.render_project(self.project, self.root, self.brand, BRAND_PATH, executable="fake-hyperframes")
            self.assertFalse(first["cache_hit"])
            snapshot = Path(first["project_snapshot"])
            self.assertEqual(sha256(snapshot / "project.json"), sha256(self.project / "project.json"))
            self.assertEqual(sha256(snapshot / "index.html"), sha256(self.project / "index.html"))
            self.assertEqual((snapshot / "index.html").stat().st_mode & 0o222, 0)
            dna = json.loads(Path(first["build_dna"]).read_text(encoding="utf-8"))
            self.assertEqual(dna["completion"]["status"], "gated")
            self.assertEqual(dna["review"]["status"], "review_pending")
            second = project_render.render_project(self.project, self.root, self.brand, BRAND_PATH, executable="fake-hyperframes")
            self.assertTrue(second["cache_hit"])
            self.assertEqual(render.call_count, 1)
            self.assertEqual(inspect.call_count, 2)
            self.assertTrue(Path(first["contact_sheet"]).is_file())

    def test_failed_render_keeps_source_snapshot_and_failed_build_dna(self):
        patches = self._patch_render_tools()
        with contextlib.ExitStack() as stack:
            inspect, render, verify, sheet = [stack.enter_context(patch) for patch in patches]
            def fail_after_log(project_dir, output, **kwargs):
                output.write_bytes(b"failed test render bytes")
                (Path(project_dir) / "hyperframes-render.log").write_text("intentional test failure", encoding="utf-8")
                raise RuntimeError("intentional test failure")
            render.side_effect = fail_after_log
            with self.assertRaisesRegex(RuntimeError, "intentional test failure"):
                project_render.render_project(self.project, self.root, self.brand, BRAND_PATH, executable="fake-hyperframes")
        runs = list((self.root / "artifacts/video-studio").glob("project-authored-project-*"))
        self.assertEqual(len(runs), 1)
        self.assertTrue((runs[0] / "project-source-snapshot/index.html").is_file())
        self.assertTrue((runs[0] / "project-render-worktree/hyperframes-render.log").is_file())
        self.assertTrue((runs[0] / "video.mp4").is_file())
        dna = json.loads((runs[0] / "build-dna.json").read_text(encoding="utf-8"))
        self.assertEqual(dna["completion"]["status"], "failed")
        self.assertTrue(any(a["path"].endswith("hyperframes-render.log") for a in dna["artifacts"]))
        self.assertTrue(any(a["path"].endswith("video.mp4") for a in dna["artifacts"]))

    def test_evidence_directory_symlink_cannot_write_outside_repository(self):
        artifacts = self.root / "artifacts"
        artifacts.mkdir()
        outside = Path(str(self.root) + "-outside-evidence")
        outside.mkdir()
        self.addCleanup(shutil.rmtree, outside, ignore_errors=True)
        (artifacts / "video-studio").symlink_to(outside, target_is_directory=True)
        inspect = mock.patch.object(project_render.hyperframes, "inspect", return_value={
            "available": True, "ok": True, "executable": "/fake/hyperframes", "version": "fake-1",
        })
        with inspect, self.assertRaisesRegex(project_render.ProjectError, "resolves outside the repository"):
            project_render.render_project(self.project, self.root, self.brand, BRAND_PATH, executable="fake-hyperframes")
        self.assertEqual(list(outside.iterdir()), [])

    def test_cli_exposes_and_routes_render_project(self):
        args = parser(default_brand=BRAND_PATH).parse_args(["render-project", str(self.project)])
        self.assertEqual(args.quality, "draft")
        self.assertEqual(args.brand, BRAND_PATH)
        with mock.patch("fam_video.cli.load_brand", return_value=self.brand), \
             mock.patch("fam_video.project_render.render_project", return_value={"status": "test"}) as render_project:
            stdout = io.StringIO()
            with contextlib.redirect_stdout(stdout):
                code = main(["render-project", str(self.project)], repo_root=self.root, default_brand=BRAND_PATH)
            self.assertEqual(code, 0)
            self.assertEqual(json.loads(stdout.getvalue()), {"status": "test"})
            self.assertEqual(render_project.call_args.args[:3], (self.project, self.root, self.brand))


if __name__ == "__main__":
    unittest.main()
