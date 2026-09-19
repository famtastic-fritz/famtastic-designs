import json
import shutil
import subprocess
import tempfile
import unittest
from pathlib import Path

from fam_video.evidence import Ledger, sha256


class EvidenceTests(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory()
        self.addCleanup(self.temporary.cleanup)
        self.root = Path(self.temporary.name)
        self.campaign = {"id": "proof-a", "scenes": [{"id": "hook", "duration": 1.0}]}
        self.ledger = Ledger(self.root / "runs" / "proof-a", self.root, self.campaign)

    def test_creation_is_canonical_and_finalization_does_not_approve(self):
        data = json.loads(self.ledger.path.read_text())
        self.assertEqual(data["schema"], "famtastic.build-dna.v1")
        self.assertEqual(data["stages"][0]["stage_id"], "run-initialized")
        self.assertEqual(data["artifacts"][0]["sha256"], sha256(self.ledger.run_dir / "campaign.snapshot.json"))
        completed = self.ledger.finalize("passed", {"metadata": "passed"})
        self.assertEqual(completed["review"]["status"], "review_pending")
        self.assertIsNone(completed["review"]["reviewer"])
        self.assertEqual(completed["retrieval"]["database"]["status"], "not_registered")
        self.assertEqual(completed["retrieval"]["site_studio"]["status"], "not_registered")
        validator = Path(__file__).resolve().parents[4] / "website-delivery-swarm" / "scripts" / "validate-build-dna.mjs"
        if shutil.which("node") and validator.is_file():
            result = subprocess.run(["node", str(validator), str(self.ledger.path), str(self.root)], capture_output=True, text=True, check=False)
            self.assertEqual(result.returncode, 0, result.stdout + result.stderr)

    def test_retry_appends_attempt_and_preserves_failed_fact(self):
        with self.assertRaisesRegex(RuntimeError, "device unavailable"):
            with self.ledger.stage("render", "designed-motion", provider="local-hyperframes", command=["hyperframes", "render"]):
                raise RuntimeError("device unavailable")
        before = json.loads(self.ledger.path.read_text())["stages"][1]
        resumed = Ledger(self.ledger.run_dir, self.root, self.campaign)
        with resumed.stage("render", "designed-motion", provider="local-hyperframes") as stage:
            stage["execution"]["output"] = {"status": "test-only"}
        stages = json.loads(resumed.path.read_text())["stages"]
        self.assertEqual(stages[1], before)
        self.assertEqual(stages[2]["attempt"], 2)
        self.assertEqual(stages[1]["result"]["status"], "failed")
        self.assertEqual(stages[2]["result"]["status"], "passed")
        self.assertGreaterEqual(stages[2]["execution"]["timing"]["duration_ms"], 0)
        self.assertEqual(stages[2]["execution"]["cost"]["provider_fee"], 0)
        self.assertIsNone(stages[2]["execution"]["cost"]["electricity"]["amount"])

    def test_external_provider_fee_is_not_invented(self):
        with self.ledger.stage("provider", "test", provider="example-provider"):
            pass
        self.assertEqual(self.ledger.data["stages"][-1]["execution"]["cost"]["status"], "unreported")
        self.assertIsNone(self.ledger.data["stages"][-1]["execution"]["cost"]["provider_fee"])

    def test_input_change_requires_new_run(self):
        with self.assertRaisesRegex(ValueError, "Campaign changed"):
            Ledger(self.ledger.run_dir, self.root, {**self.campaign, "id": "a-different-request"})

    def test_artifact_change_is_detected_without_rewriting_historical_hash(self):
        artifact = self.ledger.run_dir / "proof.txt"
        artifact.write_text("first version")
        stored = self.ledger.add_artifact(artifact, "proof", rights="test fixture")
        artifact.write_text("second version")
        with self.assertRaisesRegex(ValueError, "recorded artifact bytes changed"):
            self.ledger.add_artifact(artifact, "proof")
        completed = self.ledger.finalize("passed")
        self.assertEqual(completed["completion"]["status"], "failed")
        self.assertEqual(completed["artifacts"][-1]["sha256"], stored["sha256"])

    def test_escape_and_self_hash_are_rejected(self):
        with self.assertRaisesRegex(ValueError, "own artifact"):
            self.ledger.add_artifact(self.ledger.path, "self")
        outside = Path(self.temporary.name + "-outside.txt")
        outside.write_text("not repository evidence")
        self.addCleanup(lambda: outside.unlink(missing_ok=True))
        with self.assertRaisesRegex(ValueError, "inside the repository"):
            self.ledger.add_artifact(outside, "escape")
        symlink = self.root / "linked.txt"
        try:
            symlink.symlink_to(outside)
        except OSError:
            return
        with self.assertRaisesRegex(ValueError, "including symlink targets"):
            self.ledger.add_artifact(symlink, "escape")


if __name__ == "__main__":
    unittest.main()
