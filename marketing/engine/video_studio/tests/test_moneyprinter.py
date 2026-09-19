"""Contract tests using a fake native process, never an installed MPT runtime."""
import json
from pathlib import Path
import subprocess
import tempfile
import unittest
from unittest.mock import patch

from fam_video.adapters import moneyprinter as mpt


class MoneyPrinterTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.base = Path(self.temp.name)
        self.root = self.base / "Money Printer"
        (self.root / "app/models").mkdir(parents=True)
        (self.root / "cli.py").write_text("# fixture: native CLI replaced by subprocess fake\n")
        (self.root / "config.toml").write_text('[app]\nupload_post_enabled = false\nupload_post_auto_upload = false\napi_key = "NEVER-LOG-THIS"\n')
        (self.root / "app/models/schema.py").write_text("class VideoParams:\n" + "".join(
            f"    {name}: str = ''\n" for name in (
                "video_subject", "video_script", "video_source", "voice_name", "video_concat_mode",
                "video_fit_mode", "video_clip_speed", "video_count", "match_materials_to_script",
                "subtitle_enabled", "bgm_type", "bgm_volume", "n_threads", "video_materials",
                "custom_audio_file", "video_aspect", "video_clip_duration")))
        self.audio = self.base / "recorded narration.wav"
        self.audio.write_bytes(b"fixture audio")
        self.image = self.base / "scene, with spaces.png"
        self.image.write_bytes(b"fixture image")
        self.campaign = {"id": "test", "title": "Draft", "format": "16:9", "script": "A prepared script.",
                         "audio": str(self.audio), "scenes": [{"id": "one", "duration": 4, "media": str(self.image)}]}
        self.batch = self.base / "draft.json"
        self.out = self.base / "output"
        self.executed = []

    def native(self, argv, **kwargs):
        self.assertFalse(kwargs["shell"])
        self.assertGreater(kwargs["timeout"], 0)
        self.executed.append(argv)
        if argv[-1] == "--help":
            return subprocess.CompletedProcess(argv, 0, "--batch-file --stop-at --custom-audio-file --video-source --voice-name", "")
        if "-show_streams" in argv:
            return subprocess.CompletedProcess(argv, 0, json.dumps({"format": {"duration": "4.0"}, "streams": [
                {"codec_type": "video", "width": 1920, "height": 1080}, {"codec_type": "audio"}]}), "")
        task = self.root / "storage/tasks/fake-task/video.mp4"
        task.parent.mkdir(parents=True, exist_ok=True)
        task.write_bytes(b"fake video; ffprobe verification is separately mocked")
        result = {"total": 1, "succeeded": 1, "failed": 0, "tasks": [{"status": "succeeded", "result": {
            "videos": [str(task)], "cross_post_state": None}}]}
        return subprocess.CompletedProcess(argv, 0, json.dumps(result), "NEVER-LOG-THIS")

    def test_prepare_is_explicit_local_draft_with_space_and_comma_paths(self):
        result = mpt.prepare_batch(self.campaign, self.batch)
        data = json.loads(self.batch.read_text())[0]
        self.assertTrue(result["draft_only"])
        self.assertEqual(data["video_materials"][0]["url"], str(self.image))
        self.assertEqual(data["custom_audio_file"], str(self.audio))
        self.assertFalse(data["subtitle_enabled"])
        self.assertEqual(data["bgm_type"], "")
        self.assertEqual(data["voice_name"], "no-voice")
        with self.assertRaises(FileExistsError):
            mpt.prepare_batch(self.campaign, self.batch)

    def test_prepare_refuses_missing_audio_missing_scene_media_and_unsupported_format(self):
        for change in ({"audio": None}, {"script": ""}, {"format": "4:5"}, {"scenes": [{"id": "no-media"}]}):
            with self.subTest(change=change), self.assertRaises(mpt.MoneyPrinterError):
                mpt.prepare_batch(dict(self.campaign, **change), self.batch)
        self.assertFalse(self.batch.exists())

    def test_no_provider_or_caption_override_can_be_smuggled_in_batch(self):
        mpt.prepare_batch(self.campaign, self.batch)
        original = json.loads(self.batch.read_text())
        for key, value in (("video_source", "openai_image"), ("voice_name", "elevenlabs:voice"),
                           ("subtitle_enabled", True), ("bgm_type", "elevenlabs"), ("unknown", True)):
            batch = [dict(original[0], **{key: value})]
            self.batch.write_text(json.dumps(batch))
            with self.subTest(key=key), patch.object(mpt.subprocess, "run") as process, self.assertRaises(mpt.MoneyPrinterError):
                mpt.run(self.root, self.batch, "python3", self.out)
            process.assert_not_called()

    def test_armed_publishing_is_rejected_even_when_disabled_or_no_credentials(self):
        (self.root / "config.toml").write_text("[app]\nupload_post_enabled=false\nupload_post_auto_upload=true\n")
        with patch.object(mpt.subprocess, "run") as process:
            info = mpt.inspect(self.root, "python3")
        self.assertFalse(info["supported"])
        self.assertIn("auto-upload", info["reason"])
        process.assert_not_called()

    def test_old_cli_or_schema_reports_unsupported(self):
        with patch.object(mpt.subprocess, "run", return_value=subprocess.CompletedProcess([], 0, "old help", "secret")):
            info = mpt.inspect(self.root, "python3")
        self.assertFalse(info["supported"])
        self.assertIn("unsupported", info["reason"])
        (self.root / "app/models/schema.py").write_text("class VideoParams:\n    video_subject: str\n")
        with patch.object(mpt.subprocess, "run") as process:
            self.assertFalse(mpt.inspect(self.root, "python3")["supported"])
        process.assert_not_called()

    def test_run_verifies_and_copies_output_with_receipt_and_no_native_logs(self):
        mpt.prepare_batch(self.campaign, self.batch)
        before = (self.root / "config.toml").read_bytes()
        with patch.object(mpt.subprocess, "run", side_effect=self.native), patch.object(mpt.shutil, "which", return_value="ffprobe"):
            receipt = mpt.run(self.root, self.batch, "python3", self.out, timeout=5)
        self.assertEqual(receipt["status"], "rendered_draft")
        self.assertEqual(receipt["approval_state"], "unreviewed")
        self.assertEqual(receipt["outputs"][0]["duration_seconds"], 4)
        self.assertEqual(len(receipt["outputs"][0]["sha256"]), 64)
        self.assertTrue(Path(receipt["outputs"][0]["path"]).is_file())
        self.assertNotIn("NEVER-LOG-THIS", (self.out / "moneyprinter-receipt.json").read_text())
        self.assertEqual(before, (self.root / "config.toml").read_bytes())
        render = next(argv for argv in self.executed if "--batch-file" in argv)
        self.assertEqual(render[render.index("--batch-file") + 1], str(self.out / "moneyprinter-batch.json"))

    def test_failing_native_process_does_not_expose_diagnostics(self):
        mpt.prepare_batch(self.campaign, self.batch)
        def fail(argv, **kwargs):
            if argv[-1] == "--help":
                return self.native(argv, **kwargs)
            return subprocess.CompletedProcess(argv, 1, "NEVER-LOG-THIS", "NEVER-LOG-THIS")
        with patch.object(mpt.subprocess, "run", side_effect=fail), patch.object(mpt.shutil, "which", return_value="ffprobe"):
            with self.assertRaises(mpt.MoneyPrinterError) as exc:
                mpt.run(self.root, self.batch, "python3", self.out)
        self.assertNotIn("NEVER-LOG-THIS", str(exc.exception))
        self.assertFalse((self.out / "moneyprinter-receipt.json").exists())

    def test_outside_task_output_and_missing_audio_stream_are_rejected(self):
        for invalid in ("outside", "silent"):
            with self.subTest(invalid=invalid):
                dest = self.base / invalid
                if not self.batch.exists():
                    mpt.prepare_batch(self.campaign, self.batch)
                def fake(argv, **kwargs):
                    result = self.native(argv, **kwargs)
                    if invalid == "outside" and "--batch-file" in argv:
                        data = json.loads(result.stdout)
                        data["tasks"][0]["result"]["videos"] = [str(self.image)]
                        result.stdout = json.dumps(data)
                    if invalid == "silent" and "-show_streams" in argv:
                        data = json.loads(result.stdout)
                        data["streams"] = [data["streams"][0]]
                        result.stdout = json.dumps(data)
                    return result
                with patch.object(mpt.subprocess, "run", side_effect=fake), patch.object(mpt.shutil, "which", return_value="ffprobe"), self.assertRaises(mpt.MoneyPrinterError):
                    mpt.run(self.root, self.batch, "python3", dest)
                self.assertFalse((dest / "moneyprinter-receipt.json").exists())

    def test_timeout_is_bounded_and_generic(self):
        with patch.object(mpt.subprocess, "run", side_effect=subprocess.TimeoutExpired("NEVER-LOG-THIS", 20)):
            result = mpt.inspect(self.root, "python3")
        self.assertFalse(result["supported"])
        self.assertIn("timeout", result["reason"])
        self.assertNotIn("NEVER-LOG-THIS", result["reason"])


if __name__ == "__main__":
    unittest.main()
