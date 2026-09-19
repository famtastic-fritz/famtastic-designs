"""Protocol tests run a fake local Comfy server. They do not claim GPU proof."""
import copy
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
import json
from pathlib import Path
import tempfile
import threading
import unittest
from urllib.parse import parse_qs, urlsplit

from fam_video.adapters.comfy import (
    ComfyError, Client, binding_candidates, bind_workflow, local_url, run_workflow, resume_workflow, validate_workflow,
)

INFO = {
    "LoadImage": {"input": {"required": {"image": [["plate.png", "uploaded.png"]]}}, "output": ["IMAGE", "MASK"]},
    "SaveImage": {"input": {"required": {"images": ["IMAGE"], "filename_prefix": ["STRING"]}}, "output": []},
    "UNETLoader": {"input": {"required": {"unet_name": [["installed.safetensors"]], "weight_dtype": [["default"]]}}, "output": ["MODEL"]},
}
WORKFLOW = {
    "1": {"class_type": "LoadImage", "inputs": {"image": "plate.png"}},
    "2": {"class_type": "SaveImage", "inputs": {"images": ["1", 0], "filename_prefix": "studio/proof"}},
}
BINDINGS = {"input_image": {"node": "1", "input": "image"}, "prefix": {"node": "2", "input": "filename_prefix"}}


class FakeServer:
    def __init__(self):
        self.posts = 0
        self.uploads = 0
        self.history = "success"
        self.metadata = {"filename": "result.png", "subfolder": "video", "type": "output"}
        self.view_query = None
        self.receipt = None
        self.post_payload = None
        self.redirect = False
        owner = self

        class Handler(BaseHTTPRequestHandler):
            def log_message(self, *args):
                pass

            def send_json(self, value, status=200):
                self.send_response(status)
                self.send_header("Content-Type", "application/json")
                self.end_headers()
                self.wfile.write(json.dumps(value).encode())

            def do_GET(self):
                route = urlsplit(self.path).path
                if owner.redirect:
                    self.send_response(302)
                    self.send_header("Location", "http://example.com/forbidden")
                    self.end_headers()
                elif route == "/object_info":
                    self.send_json(INFO)
                elif route.startswith("/history/"):
                    if owner.receipt:
                        owner.receipt_seen = json.loads(owner.receipt.read_text())
                    if owner.history == "waiting":
                        self.send_json({})
                    elif owner.history == "error":
                        self.send_json({"job-1": {"status": {"status_str": "error", "messages": [["execution_error", {"exception_message": "CUDA OOM"}]]}}})
                    else:
                        self.send_json({"job-1": {"status": {"completed": True, "status_str": "success"},
                                                "outputs": {"2": {"images": [owner.metadata]}}}})
                elif route == "/view":
                    owner.view_query = parse_qs(urlsplit(self.path).query)
                    self.send_response(200)
                    self.end_headers()
                    self.wfile.write(b"fake PNG output bytes; not a visual proof")
                else:
                    self.send_json({"error": "not found"}, 404)

            def do_POST(self):
                body = self.rfile.read(int(self.headers["Content-Length"]))
                if self.path == "/upload/image":
                    owner.uploads += 1
                    owner.upload_body = body
                    self.send_json({"name": "uploaded.png", "subfolder": "", "type": "input"})
                elif self.path == "/prompt":
                    owner.posts += 1
                    owner.post_payload = json.loads(body)
                    if owner.history == "submission_unknown":
                        self.send_json({"number": 0})
                    elif owner.history == "rejected":
                        self.send_json({"error": "bad_prompt", "node_errors": {"1": "failed"}})
                    else:
                        self.send_json({"prompt_id": "job-1", "number": 0, "node_errors": {}})
                else:
                    self.send_json({}, 404)

        self.server = ThreadingHTTPServer(("127.0.0.1", 0), Handler)
        self.thread = threading.Thread(target=self.server.serve_forever, daemon=True)
        self.url = "http://127.0.0.1:" + str(self.server.server_port)

    def __enter__(self):
        self.thread.start()
        return self

    def __exit__(self, *args):
        self.server.shutdown()
        self.server.server_close()
        self.thread.join()


class ComfyProtocolTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.root = Path(self.temp.name)
        self.workflow = self.root / "workflow.json"
        self.bindings = self.root / "bindings.json"
        self.receipt = self.root / "receipt.json"
        self.workflow.write_text(json.dumps(WORKFLOW))
        self.bindings.write_text(json.dumps(BINDINGS))

    def tearDown(self):
        self.temp.cleanup()

    def run_job(self, server, **kwargs):
        server.receipt = self.receipt
        return run_workflow(self.workflow, self.bindings, {}, self.root / "output",
                            base_url=server.url, receipt_path=self.receipt, timeout=.1,
                            poll_interval=.01, **kwargs)

    def test_submission_receipt_download_and_completed_resume(self):
        with FakeServer() as server:
            result = self.run_job(server)
            self.assertEqual(result["status"], "completed")
            self.assertEqual(server.receipt_seen["prompt_id"], "job-1")
            self.assertEqual(server.receipt_seen["status"], "queued")
            self.assertEqual(server.view_query, {"filename": ["result.png"], "subfolder": ["video"], "type": ["output"]})
            self.assertEqual(server.post_payload["prompt"], WORKFLOW)
            self.assertTrue(Path(result["files"][0]["path"]).is_file())
            again = resume_workflow(self.receipt)
            self.assertEqual(again["files"], result["files"])
            with self.assertRaisesRegex(ComfyError, "already exists"):
                self.run_job(server)
            self.assertEqual(server.posts, 1)

    def test_timeout_resumes_original_prompt_without_duplicate(self):
        with FakeServer() as server:
            server.history = "waiting"
            with self.assertRaisesRegex(ComfyError, "NOT cancelled"):
                self.run_job(server)
            self.assertEqual(json.loads(self.receipt.read_text())["status"], "waiting")
            server.history = "success"
            result = resume_workflow(self.receipt, timeout=.2, poll_interval=.01)
            self.assertEqual(result["prompt_id"], "job-1")
            self.assertEqual(server.posts, 1)

    def test_failed_generation_and_ambiguous_submission_never_resubmit(self):
        for status in ("error", "submission_unknown", "rejected"):
            with self.subTest(status=status), FakeServer() as server:
                self.receipt.unlink(missing_ok=True)
                server.history = status
                with self.assertRaises(ComfyError):
                    self.run_job(server)
                with self.assertRaises(ComfyError):
                    resume_workflow(self.receipt, timeout=.05, poll_interval=.01)
                self.assertEqual(server.posts, 1)

    def test_traversal_and_redirect_refused(self):
        for meta in ({"filename": "../../escape.png"}, {"filename": "result.png", "subfolder": "../escape"},
                     {"filename": "result.png", "type": "input"}, {"filename": "C:\\evil.png"}):
            with self.subTest(meta=meta), FakeServer() as server:
                self.receipt.unlink(missing_ok=True)
                server.metadata = meta
                with self.assertRaisesRegex(ComfyError, "Unsafe"):
                    self.run_job(server)
                self.assertIsNone(server.view_query)
        with FakeServer() as server:
            server.redirect = True
            with self.assertRaisesRegex(ComfyError, "redirect refused"):
                Client(server.url).json("/object_info")

    def test_image_upload_binds_returned_name(self):
        image = self.root / "my weird \" name.png"
        image.write_bytes(b"input fixture")
        with FakeServer() as server:
            result = self.run_job(server, input_image=image)
            self.assertEqual(result["status"], "completed")
            self.assertEqual(server.uploads, 1)
            self.assertEqual(server.post_payload["prompt"]["1"]["inputs"]["image"], "uploaded.png")
            self.assertNotIn(b'my weird', server.upload_body)

    def test_stale_lock_prevents_submit(self):
        self.receipt.with_suffix(".json.lock").write_text("lock")
        with FakeServer() as server:
            with self.assertRaisesRegex(ComfyError, "locked"):
                self.run_job(server)
            self.assertEqual(server.posts, 0)


class ComfyValidationTests(unittest.TestCase):
    def test_local_only(self):
        self.assertEqual(local_url("http://localhost:8188/"), "http://127.0.0.1:8188")
        for url in ("https://cloud.comfy.org", "http://192.168.1.2:8188", "http://127.0.0.1.evil.com", "http://user:pass@127.0.0.1:8188", "http://127.0.0.1:8188/path", "file:///tmp/comfy"):
            with self.subTest(url=url), self.assertRaises(ComfyError):
                local_url(url)

    def test_candidates_report_actual_ids_and_titles_without_guessing(self):
        workflow = copy.deepcopy(WORKFLOW)
        workflow["1"]["_meta"] = {"title": "Approved plate"}
        candidates = binding_candidates(workflow)
        self.assertEqual(candidates[0]["title"], "Approved plate")
        self.assertEqual(candidates[0]["node"], "1")
        self.assertFalse(any(c["input"] == "images" for c in candidates))

    def test_bindings_are_explicit_and_non_mutating(self):
        result = bind_workflow(WORKFLOW, BINDINGS, {"prefix": "new/proof"})
        self.assertEqual(result["2"]["inputs"]["filename_prefix"], "new/proof")
        self.assertEqual(WORKFLOW["2"]["inputs"]["filename_prefix"], "studio/proof")
        with self.assertRaises(ComfyError):
            bind_workflow(WORKFLOW, BINDINGS, {"seed": 42})
        with self.assertRaises(ComfyError):
            bind_workflow({"nodes": []}, {}, {})

    def test_api_nodes_missing_models_and_broken_links(self):
        self.assertEqual(validate_workflow(WORKFLOW, INFO), [])
        cloud = {"1": {"class_type": "OpenAIImage", "inputs": {}}}
        self.assertIn("not a reviewed", " ".join(validate_workflow(cloud, {"OpenAIImage": {}})))
        model = {"1": {"class_type": "UNETLoader", "inputs": {"unet_name": "missing.safetensors", "weight_dtype": "default"}}}
        self.assertIn("unavailable", " ".join(validate_workflow(model, INFO)))
        broken = copy.deepcopy(WORKFLOW)
        broken["2"]["inputs"]["images"] = ["1", 1]
        self.assertIn("incompatible", " ".join(validate_workflow(broken, INFO)))
        broken["2"]["inputs"]["images"] = ["missing", 0]
        self.assertIn("missing output", " ".join(validate_workflow(broken, INFO)))
        broken["2"]["inputs"]["filename_prefix"] = "../outside"
        self.assertIn("unsafe output prefix", " ".join(validate_workflow(broken, INFO)))

    def test_core_name_marked_api_is_rejected(self):
        info = copy.deepcopy(INFO)
        info["LoadImage"]["api_node"] = True
        self.assertIn("remote/API", " ".join(validate_workflow(WORKFLOW, info)))


if __name__ == "__main__":
    unittest.main()
