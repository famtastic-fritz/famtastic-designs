import importlib.util
import json
import pathlib
import subprocess
import unittest
from unittest import mock


ROOT = pathlib.Path(__file__).parents[1]
MODULE = ROOT / "autonomous_pipeline.py"
SPEC = importlib.util.spec_from_file_location("autonomous_pipeline", MODULE)
pipeline = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(pipeline)


class AutonomousPipelinePreflightTests(unittest.TestCase):
    def setUp(self):
        self.routes = json.loads((ROOT / "config" / "capability-routes.v1.json").read_text())

    def test_gemini_keychain_route_uses_its_declared_node_worker(self):
        worker = pipeline.resolve_keychain_worker(self.routes["providers"]["gemini_flash_lite_image"])
        self.assertEqual(worker, ROOT / "gemini_flash_lite_image_worker.mjs")

    def test_openai_keychain_route_uses_its_default_worker(self):
        worker = pipeline.resolve_keychain_worker(self.routes["providers"]["openai_image_api"])
        self.assertEqual(worker, ROOT / "openai_image_worker.py")

    @mock.patch.object(pipeline.subprocess, "run")
    @mock.patch.object(pipeline.shutil, "which", return_value="/usr/bin/node")
    def test_keychain_preflight_labels_the_actual_declared_model(self, _which, run):
        run.return_value = subprocess.CompletedProcess([], 0, "", "")
        result = pipeline.preflight("low", False)
        gemini = result["providers"]["gemini_flash_lite_image"]
        self.assertEqual(gemini["state"], "available")
        self.assertIn("gemini-3.1-flash-lite-image", gemini["reason"])
        self.assertNotIn("gpt-image-2", gemini["reason"])


if __name__ == "__main__":
    unittest.main()
