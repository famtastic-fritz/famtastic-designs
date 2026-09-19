import json
import shutil
import subprocess
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch

from fam_video.doctor import _gpus
from fam_video.verify import contact_sheet, verify_video


class VerificationFailureTests(unittest.TestCase):
    def test_missing_file_has_clear_failure(self):
        result = verify_video(Path("/does-not-exist/video.mp4"))
        self.assertFalse(result["passed"])
        self.assertIn("does not exist", result["failures"][0])

    def test_gpu_query_preserves_dedicated_memory_units(self):
        with patch("fam_video.doctor.shutil.which", return_value="nvidia-smi"), \
                patch("fam_video.doctor.platform.system", return_value="other"), \
                patch("fam_video.doctor._command", return_value={"ok": True, "stdout": "Test GPU, 16384, 123.45"}):
            devices, _ = _gpus()
        self.assertEqual(devices[0]["dedicated_vram_bytes"], 16 * 1024 ** 3)
        self.assertEqual(devices[0]["memory_type"], "dedicated")

    def test_apple_unified_memory_is_not_fabricated_dedicated_vram(self):
        with patch("fam_video.doctor.shutil.which", return_value=None), \
                patch("fam_video.doctor.platform.system", return_value="Darwin"), \
                patch("fam_video.doctor.platform.machine", return_value="arm64"), \
                patch("fam_video.doctor._command", return_value={"ok": True, "stdout": json.dumps({"SPDisplaysDataType": [{"sppci_model": "Apple M2"}]})}):
            devices, notes = _gpus()
        self.assertEqual(devices[0]["memory_type"], "unified")
        self.assertIsNone(devices[0]["dedicated_vram_bytes"])
        self.assertTrue(any("not dedicated VRAM" in note for note in notes))


@unittest.skipUnless(shutil.which("ffmpeg") and shutil.which("ffprobe"), "requires actual ffmpeg and ffprobe")
class RealMediaVerificationTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.temporary = tempfile.TemporaryDirectory()
        cls.root = Path(cls.temporary.name)
        cls.video = cls.root / "sample.mp4"
        result = subprocess.run(["ffmpeg", "-hide_banner", "-loglevel", "error", "-nostdin", "-y", "-f", "lavfi", "-i",
                                 "color=c=0x124c36:s=96x160:r=10:d=1.2", "-f", "lavfi", "-i", "sine=frequency=220:sample_rate=44100:duration=1.2",
                                 "-c:v", "mpeg4", "-pix_fmt", "yuv420p", "-c:a", "aac", "-threads", "1", "-shortest", str(cls.video)],
                                capture_output=True, text=True, check=False)
        if result.returncode:
            cls.temporary.cleanup()
            raise RuntimeError("Cannot create fixture: " + result.stderr)

    @classmethod
    def tearDownClass(cls):
        cls.temporary.cleanup()

    def test_real_encoded_media_matches_contract(self):
        result = verify_video(self.video, {"width": 96, "height": 160, "fps": 10, "duration_seconds": 1.2, "audio": True, "codec": "mpeg4"})
        self.assertTrue(result["passed"], result["failures"])
        self.assertEqual(result["review"], "review_pending")
        self.assertAlmostEqual(result["duration_seconds"], 1.2, places=2)
        self.assertEqual(result["audio"][0]["codec"], "aac")

    def test_mismatching_dimensions_rate_duration_and_audio_fail(self):
        result = verify_video(self.video, {"width": 1080, "fps": 24, "duration_seconds": 30, "audio": False})
        self.assertFalse(result["passed"])
        self.assertEqual(len(result["failures"]), 4)

    def test_sheet_contains_real_frames_and_does_not_modify_video(self):
        original = self.video.read_bytes()
        destination = self.root / "contact.png"
        result = contact_sheet(self.video, destination, [0, 0.3, 0.8])
        self.assertEqual(result["status"], "passed")
        self.assertTrue(destination.read_bytes().startswith(b"\x89PNG\r\n\x1a\n"))
        self.assertEqual(original, self.video.read_bytes())
        self.assertNotIn("drawtext", str(result["commands"]))
        self.assertEqual(result["frame_times_seconds"], [0, 0.3, 0.8])

    def test_invalid_timestamp_does_not_silently_substitute_frame(self):
        for times in ([], [-1], [float("nan")], [30]):
            with self.subTest(times=times), self.assertRaises(ValueError):
                contact_sheet(self.video, self.root / "bad.png", times)

    def test_corrupt_input_reports_decode_metadata_failure(self):
        corrupt = self.root / "broken.mp4"
        corrupt.write_text("not an encoded video")
        result = verify_video(corrupt)
        self.assertFalse(result["passed"])
        self.assertIn("ffprobe", result["failures"][0])


if __name__ == "__main__":
    unittest.main()
