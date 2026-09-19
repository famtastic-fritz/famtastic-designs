"""CLI-to-ledger integration over a fake loopback Comfy protocol.

These tests exercise submission/resume and evidence. The returned fixture bytes
are deliberately not a real image and never constitute GPU or creative proof.
"""
import contextlib
import copy
import io
import json
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest

from fam_video.cli import main
from fam_video.evidence import sha256
from test_comfy import BINDINGS, FakeServer, WORKFLOW


class GenerationCliEvidenceTests(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory()
        self.addCleanup(self.temporary.cleanup)
        self.root = Path(self.temporary.name)
        self.workflow = self.root / "workflow.api.json"
        self.bindings = self.root / "bindings.json"
        self.values = self.root / "values.json"
        self.run = self.root / "runs" / "generation"
        self.receipt = self.run / "comfy-receipt.json"
        self.workflow.write_text(json.dumps(WORKFLOW))
        self.bindings.write_text(json.dumps(BINDINGS))
        self.values.write_text(json.dumps({"prefix": "cli-proof"}))

    def call(self, argv):
        stdout, stderr = io.StringIO(), io.StringIO()
        with contextlib.redirect_stdout(stdout), contextlib.redirect_stderr(stderr):
            code = main(argv, repo_root=self.root)
        return code, stdout.getvalue(), stderr.getvalue()

    def generate(self, server):
        return self.call(["generate", "--workflow", str(self.workflow), "--bindings", str(self.bindings),
                          "--values", str(self.values), "--output", str(self.run), "--url", server.url, "--timeout", "1"])

    def ledger(self):
        return json.loads((self.run / "build-dna.json").read_text())

    def assert_integrity(self):
        data = self.ledger()
        for artifact in data["artifacts"]:
            self.assertEqual(artifact["sha256"], sha256(self.root / artifact["path"]), artifact["path"])
        validator = Path(__file__).resolve().parents[4] / "website-delivery-swarm/scripts/validate-build-dna.mjs"
        if shutil.which("node") and validator.is_file():
            result = subprocess.run(["node", str(validator), str(self.run / "build-dna.json"), str(self.root)],
                                    capture_output=True, text=True, check=False)
            self.assertEqual(result.returncode, 0, result.stdout + result.stderr)

    def test_cli_download_is_gated_evidence_and_never_a_claim_of_gpu_or_visual_proof(self):
        with FakeServer() as server:
            code, stdout, stderr = self.generate(server)
            self.assertEqual(code, 0, stderr)
            receipt = json.loads(stdout)
            self.assertEqual(receipt["status"], "completed")
            self.assertEqual(server.posts, 1)
        dna = self.ledger()
        self.assertEqual(dna["completion"]["status"], "gated")
        self.assertEqual(dna["review"], {"status": "review_pending", "reviewer": None, "decision": None})
        generation = next(stage for stage in dna["stages"] if stage["stage_id"] == "generation")
        self.assertEqual(generation["execution"]["model"]["status"], "workflow_declared_not_runtime_verified")
        self.assertIsNone(generation["execution"]["model"]["id"])
        self.assertEqual(generation["execution"]["cost"]["provider_fee"], 0)
        self.assertIsNone(generation["execution"]["cost"]["electricity"]["amount"])
        self.assertEqual(dna["retrieval"]["database"]["status"], "not_registered")
        self.assertEqual(dna["retrieval"]["site_studio"]["status"], "not_registered")
        generated = [artifact for artifact in dna["artifacts"] if artifact["role"] == "generated_media"]
        self.assertEqual(len(generated), 1)
        self.assertIn(b"not a visual proof", (self.root / generated[0]["path"]).read_bytes())
        sources = {artifact["role"]: artifact for artifact in dna["artifacts"] if artifact["role"].startswith("generation_")}
        self.assertEqual(set(sources), {"generation_workflow", "generation_bindings", "generation_values"})
        for role, source in [("generation_workflow", self.workflow), ("generation_bindings", self.bindings), ("generation_values", self.values)]:
            self.assertEqual((self.root / sources[role]["path"]).read_bytes(), source.read_bytes())
        self.assert_integrity()

    def test_cli_timeout_resume_preserves_first_attempt_and_never_submits_twice(self):
        with FakeServer() as server:
            server.history = "waiting"
            code, stdout, stderr = self.generate(server)
            self.assertEqual(code, 1)
            self.assertIn("NOT cancelled", stderr)
            self.assertEqual(stdout, "")
            first = copy.deepcopy(self.ledger())
            self.assertEqual(first["completion"]["status"], "partial")
            first_attempt = next(stage for stage in first["stages"] if stage["stage_id"] == "generation")
            self.assertEqual(first_attempt["result"]["status"], "failed")
            self.assertEqual(json.loads(self.receipt.read_text())["status"], "waiting")
            self.assert_integrity()
            # Resume must use frozen evidence and the submitted prompt, even if
            # the operator edits the original request files for a future job.
            self.values.write_text(json.dumps({"prefix": "different-future-job"}))
            self.workflow.write_text("an unrelated future workflow")
            server.history = "success"
            code, stdout, stderr = self.call(["resume", str(self.receipt), "--timeout", "1"])
            self.assertEqual(code, 0, stderr)
            self.assertEqual(json.loads(stdout)["prompt_id"], "job-1")
            self.assertEqual(server.posts, 1)
            self.assertEqual(server.post_payload["prompt"]["2"]["inputs"]["filename_prefix"], "cli-proof")
        stages = [stage for stage in self.ledger()["stages"] if stage["stage_id"] == "generation"]
        self.assertEqual([stage["attempt"] for stage in stages], [1, 2])
        self.assertEqual(stages[0], first_attempt)
        self.assertEqual(stages[1]["result"]["status"], "passed")
        self.assertEqual(self.ledger()["build_id"], first["build_id"])
        self.assert_integrity()

    def test_existing_generation_is_refused_and_completed_resume_preserves_prior_artifacts(self):
        with FakeServer() as server:
            code, _, stderr = self.generate(server)
            self.assertEqual(code, 0, stderr)
            original_artifacts = copy.deepcopy(self.ledger()["artifacts"])
            code, _, stderr = self.generate(server)
            self.assertEqual(code, 1)
            self.assertIn("run exists", stderr)
            code, _, stderr = self.call(["resume", str(self.receipt)])
            self.assertEqual(code, 0, stderr)
            self.assertEqual(server.posts, 1)
        current = self.ledger()["artifacts"]
        for artifact in original_artifacts:
            self.assertIn(artifact, current)
        self.assert_integrity()


if __name__ == "__main__":
    unittest.main()
