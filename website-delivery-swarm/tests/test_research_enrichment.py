import hashlib
import importlib.util
import json
import pathlib
import struct
import sys
import tempfile
import unittest
import zipfile
from argparse import Namespace


MODULE = pathlib.Path(__file__).parents[1] / "research_enrichment.py"
SPEC = importlib.util.spec_from_file_location("research_enrichment", MODULE)
enrichment = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(enrichment)
sys.path.insert(0, str(MODULE.parent))
PIPELINE_MODULE = MODULE.parent / "autonomous_pipeline.py"
PIPELINE_SPEC = importlib.util.spec_from_file_location("autonomous_pipeline", PIPELINE_MODULE)
pipeline = importlib.util.module_from_spec(PIPELINE_SPEC)
PIPELINE_SPEC.loader.exec_module(pipeline)


def receipt(**overrides):
    value = {
        "schema": "famtastic.research-execution.v1",
        "adapter": "kimi-autonomous",
        "provider": "managed:kimi-code",
        "agent": "kimi-acp",
        "model": "kimi-code/k3",
        "execution_class": "cloud_via_local_cli",
        "protocol": "acp-v1",
        "cli_version": "0.38.0",
        "status": "completed",
        "prompt_template": "site-studio.research.kimi-autonomous.v1",
        "prompt_snapshot": "Email private@example.test and use api_key=AIza12345678901234567890.",
        "prompt_sha256": "a" * 64,
        "output_sha256": "b" * 64,
        "duration_ms": 321,
        "requested": {"model": "kimi-code/k3", "thinking": "high", "permission_mode": "auto"},
        "tools": {"calls": 2, "kinds": ["web_search"], "permission_requests": 0},
        "usage": {"input_tokens": 12, "output_tokens": 34, "status": "provider_did_not_report"},
        "cost_estimate": {"amount_usd": None, "currency": None, "status": "provider_did_not_report_currency_cost"},
        "customer_claims": ["Jane Customer <private@example.test>"],
        "credential": "sk-1234567890123456",
    }
    value.update(overrides)
    return value


class ResearchEnrichmentTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.root = pathlib.Path(self.temp.name)
        self.packet_files = self.root / "packet-files"
        self.packet_files.mkdir()

    def tearDown(self):
        self.temp.cleanup()

    def write(self, name, value):
        path = self.root / name
        path.write_text(json.dumps(value))
        return path

    def test_explicit_receipt_emits_optional_frozen_provenance_only(self):
        source = self.write("research-packet.json", {
            "source_adapter": "kimi-autonomous",
            "execution_status": "partial",
            "customer_claims": ["Email private@example.test"],
            "execution": receipt(),
        })

        result = enrichment.prepare_research_enrichment(self.packet_files, str(source))

        self.assertEqual(result["requested"], {
            "status": "optional",
            "adapter": "kimi-autonomous",
            "model": "kimi-code/k3",
            "thinking": "high",
            "permission_mode": "auto",
        })
        self.assertEqual(result["actual"], {
            "status": "completed",
            "provider": "managed:kimi-code",
            "adapter": "kimi-autonomous",
            "agent": "kimi-acp",
            "model": "kimi-code/k3",
            "execution_class": "cloud_via_local_cli",
            "research_status": "partial",
        })
        copied = self.packet_files / "research-execution.json"
        self.assertEqual(result["receipt"]["path"], "packet-files/research-execution.json")
        self.assertEqual(result["receipt"]["sha256"], hashlib.sha256(copied.read_bytes()).hexdigest())
        serialized = copied.read_text()
        self.assertNotIn("private@example.test", serialized)
        self.assertNotIn("AIza12345678901234567890", serialized)
        self.assertNotIn("sk-1234567890123456", serialized)
        self.assertNotIn("prompt_snapshot", serialized)
        self.assertNotIn("customer_claims", serialized)

    def test_receipt_is_not_auto_attached_without_explicit_opt_in(self):
        self.write("research-execution.json", receipt())

        result = enrichment.prepare_research_enrichment(self.packet_files)

        self.assertIsNone(result)
        self.assertFalse((self.packet_files / "research-execution.json").exists())

    def test_rejects_unsafe_required_provenance_value(self):
        source = self.write("unsafe.json", receipt(provider="private@example.test"))

        with self.assertRaisesRegex(enrichment.ResearchEnrichmentError, "provider"):
            enrichment.prepare_research_enrichment(self.packet_files, str(source))

    def test_projection_validator_rejects_data_outside_the_safe_allowlist(self):
        source = self.write("execution.json", receipt())
        result = enrichment.prepare_research_enrichment(self.packet_files, str(source))
        copied = self.packet_files / "research-execution.json"
        stored = json.loads(copied.read_text())
        stored["prompt_snapshot"] = "Email private@example.test"
        copied.write_text(json.dumps(stored))
        result["receipt"]["sha256"] = hashlib.sha256(copied.read_bytes()).hexdigest()

        with self.assertRaisesRegex(enrichment.ResearchEnrichmentError, "outside the safe provenance projection"):
            enrichment.validate_research_enrichment(result, self.packet_files)

    def test_secret_shaped_optional_model_value_is_not_projected(self):
        secret_model = "sk-1234567890123456"
        source = self.write("secret-model.json", receipt(model=secret_model, requested={"model": secret_model}))

        result = enrichment.prepare_research_enrichment(self.packet_files, str(source))

        self.assertNotIn("model", result["requested"])
        self.assertNotIn("model", result["actual"])
        self.assertNotIn(secret_model, (self.packet_files / "research-execution.json").read_text())

    def test_pipeline_only_adds_enrichment_when_the_optional_receipt_is_supplied(self):
        artifact = self.root / "artifact"
        self.make_artifact(artifact)
        source = self.write("research-packet.json", {
            "source_adapter": "kimi-autonomous",
            "execution_status": "partial",
            "execution": receipt(),
        })

        included = self.root / "included"
        pipeline.prepare(self.prepare_args(artifact, included, str(source)))
        packet = json.loads((included / "site-studio-build-packet.json").read_text())
        self.assertEqual(packet["research_enrichment"]["requested"]["status"], "optional")
        self.assertEqual(packet["research_enrichment"]["actual"]["provider"], "managed:kimi-code")
        self.assertIn("packet-files/research-execution.json", [
            item["path"] for item in packet["artifacts"]
        ])
        self.assertNotIn("private@example.test", json.dumps(packet))
        with zipfile.ZipFile(included / "site-studio-build-packet.zip") as archive:
            self.assertIn("packet-files/research-execution.json", archive.namelist())

        omitted = self.root / "omitted"
        pipeline.prepare(self.prepare_args(artifact, omitted, None))
        no_enrichment = json.loads((omitted / "site-studio-build-packet.json").read_text())
        self.assertNotIn("research_enrichment", no_enrichment)

    def prepare_args(self, artifact, output, research_execution):
        return Namespace(
            artifact=str(artifact),
            intake=str(artifact / "intake.json"),
            output=str(output),
            project_id="project:research-enrichment-test",
            select="direction-e,direction-f",
            build_class="medium",
            golden_replay=True,
            research_execution=research_execution,
        )

    def make_artifact(self, artifact):
        artifact.mkdir()
        directions = []
        manifest = {"directions": []}
        for letter in "abcdef":
            slug = f"direction-{letter}"
            directions.append({"id": slug})
            manifest["directions"].append({"id": slug, "entry": f"{slug}/index.html"})
            site = artifact / slug
            (site / "assets").mkdir(parents=True)
            (site / "index.html").write_text(f"<title>{slug}</title>")
            self.write_png(site / "assets" / "hero.png", 1440)
            self.write_png(artifact / "screenshots" / f"{slug}-desktop.png", 1440)
            self.write_png(artifact / "screenshots" / f"{slug}-mobile.png", 390)
        files = {
            "intake.json": {"request_id": "request:research-enrichment-test", "customer": {"email": "safe@example.test"}},
            "research.json": {"findings": []},
            "architecture.json": {},
            "website-build-brief.v2.json": {"request_id": "request:research-enrichment-test"},
            "directions.json": directions,
            "image-prompts.json": [],
            "agent-ledger.json": [],
            "quality-report.json": {"visual_assertions": {"no_critical_defects": True, "independent_reviewer": True, "every_overall_at_least_eight": True}},
            "evidence.json": {"browser": "fixture"},
            "manifest.json": manifest,
        }
        for name, value in files.items():
            (artifact / name).write_text(json.dumps(value))

    @staticmethod
    def write_png(path, width):
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_bytes(b"\x89PNG\r\n\x1a\n" + b"\x00\x00\x00\rIHDR" + struct.pack(">I", width) + struct.pack(">I", 1))


if __name__ == "__main__":
    unittest.main()
