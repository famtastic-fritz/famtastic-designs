import contextlib
import hashlib
import importlib.util
import json
import os
import tempfile
import types
import unittest
from pathlib import Path
from unittest import mock


REPO = Path(__file__).resolve().parents[4]
SCRIPT = REPO / "scripts/famtastic-local-voice-convert.py"
SOURCE = REPO / "artifacts/video-studio/no-catch-v2-20260919/voice-sample/narration.wav"
REFERENCE = REPO / "artifacts/video-studio/voice-clone-research-20260919/openvoice-v2-local-proof/synthetic-reference-af-bella.wav"
SOURCE_EVIDENCE = REPO / "artifacts/video-studio/no-catch-v2-20260919/voice-sample/synthesis-receipts.json"
REFERENCE_EVIDENCE = REPO / "artifacts/video-studio/voice-clone-research-20260919/openvoice-v2-local-proof/synthetic-reference-generation.log"
OPENVOICE = REPO / "artifacts/video-studio/voice-clone-research-20260919/openvoice-v2-local-proof/openvoice-source"
CHECKPOINT = REPO / "artifacts/video-studio/voice-clone-research-20260919/openvoice-v2-local-proof/weights/converter/checkpoint.pth"
CONFIG = REPO / "artifacts/video-studio/voice-clone-research-20260919/openvoice-v2-local-proof/weights/converter/config.json"

spec = importlib.util.spec_from_file_location("famtastic_local_voice_convert", SCRIPT)
runner = importlib.util.module_from_spec(spec)
spec.loader.exec_module(runner)


def sha(path):
    return hashlib.sha256(Path(path).read_bytes()).hexdigest()


class FakeLedger:
    instances = []

    def __init__(self, run_dir, repo_root, campaign):
        self.run_dir = run_dir
        self.repo_root = repo_root
        self.campaign = campaign
        self.artifacts = []
        self.stages = []
        type(self).instances.append(self)

    def add_artifact(self, path, role, rights="unconfirmed", retention="run_evidence"):
        self.artifacts.append((Path(path), role, rights))

    @contextlib.contextmanager
    def stage(self, stage_id, capability, **kwargs):
        record = {"stage_id": stage_id, "execution": {"input": {}, **kwargs}}
        self.stages.append(record)
        try:
            yield record
        except BaseException:
            record["result"] = {"status": "failed"}
            raise
        else:
            if "result" not in record:
                record["result"] = {"status": "passed"}


class FakeSoundFile:
    durations = {}

    @classmethod
    def info(cls, path):
        duration = cls.durations.get(str(path), 13.45)
        return types.SimpleNamespace(duration=duration, samplerate=22050, channels=1,
                                     frames=int(duration * 22050), format="WAV", subtype="PCM_16")

    @staticmethod
    def read(path, **kwargs):
        return [0.1, -0.2, 0.05], 22050


class FakeTorch:
    @staticmethod
    def get_num_threads():
        return 2


class FakeConverter:
    def __init__(self, *, fail=False):
        self.fail = fail
        self.extracted = []

    def extract_se(self, path):
        self.extracted.append(path)
        return f"embedding:{Path(path).name}"

    def convert(self, source, source_embedding, target_embedding, *, output_path):
        Path(output_path).write_bytes(b"mocked conversion audio bytes")
        if self.fail:
            raise RuntimeError("mock conversion failure after output began")


class LocalVoiceConversionTests(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory(dir=REPO / "artifacts/video-studio")
        self.addCleanup(self.temporary.cleanup)
        self.root = Path(self.temporary.name)
        self.run_dir = self.root / "run"
        self.run_dir.mkdir()
        (self.run_dir / "campaign.snapshot.json").write_text(json.dumps({"id": "unit-test"}), encoding="utf-8")
        (self.run_dir / "build-dna.json").write_text(json.dumps({
            "schema": "famtastic.build-dna.v1", "completion": {"status": "in_progress"},
        }), encoding="utf-8")
        self.provenance_path = self.root / "provenance.json"
        self.provenance_path.write_text(json.dumps({
            "schema": runner.PROVENANCE_SCHEMA,
            "purpose": "Unit-test-only synthetic voice conversion.",
            "source_voice": {
                "kind": "synthetic_stock_voice", "description": "Existing synthetic source speech.",
                "voice": "am_michael", "model": "Kokoro-82M", "model_license": "Apache-2.0",
                "evidence": {"path": SOURCE_EVIDENCE.relative_to(REPO).as_posix(), "sha256": sha(SOURCE_EVIDENCE)},
            },
            "target_voice": {
                "kind": "synthetic_stock_voice", "description": "Separate synthetic target reference.",
                "voice": "af_bella", "model": "Kokoro-82M", "model_license": "Apache-2.0",
                "evidence": {"path": REFERENCE_EVIDENCE.relative_to(REPO).as_posix(), "sha256": sha(REFERENCE_EVIDENCE)},
            },
        }), encoding="utf-8")
        FakeLedger.instances.clear()
        FakeSoundFile.durations = {str(SOURCE): 13.4373, str(REFERENCE): 4.864}

    def _args(self, *, output_name="converted.wav", receipt_name="receipt.json"):
        return types.SimpleNamespace(
            source=SOURCE, reference=REFERENCE, output=self.root / output_name,
            provenance=self.provenance_path, openvoice_source=OPENVOICE,
            checkpoint=CHECKPOINT, config=CONFIG,
            build_dna=self.run_dir / "build-dna.json", receipt=self.root / receipt_name,
            threads=2,
        )

    def _patches(self, converter):
        return contextlib.ExitStack()

    def test_provenance_requires_hashed_evidence_and_rejects_boolean_consent(self):
        normalized, paths = runner._voice_provenance(self.provenance_path)
        self.assertEqual(normalized["target_voice"]["voice"], "af_bella")
        self.assertEqual(len(paths), 2)
        data = json.loads(self.provenance_path.read_text(encoding="utf-8"))
        data["target_voice"]["evidence"] = True
        self.provenance_path.write_text(json.dumps(data), encoding="utf-8")
        with self.assertRaisesRegex(runner.ConversionError, "not a boolean"):
            runner._voice_provenance(self.provenance_path)

    def test_owner_voice_claim_requires_local_consent_artifact_not_approval_flag(self):
        data = json.loads(self.provenance_path.read_text(encoding="utf-8"))
        target = data["target_voice"]
        target.clear()
        target.update({
            "kind": "owner_voice_sample", "description": "Owner sample",
            "evidence": {"path": REFERENCE_EVIDENCE.relative_to(REPO).as_posix(), "sha256": sha(REFERENCE_EVIDENCE)},
            "consent_evidence": True,
        })
        self.provenance_path.write_text(json.dumps(data), encoding="utf-8")
        with self.assertRaisesRegex(runner.ConversionError, "unexpected or missing fields|local evidence"):
            runner._voice_provenance(self.provenance_path)

    def test_reference_duration_limit_and_mono_contract(self):
        FakeSoundFile.durations = {str(SOURCE): 10, str(REFERENCE): 30.01}
        with self.assertRaisesRegex(runner.ConversionError, "between 1 and 30 seconds"):
            runner._validate_audio(SOURCE, REFERENCE, FakeSoundFile)

    def test_runtime_lock_is_hashed_and_package_inventory_is_exact(self):
        with mock.patch.object(runner, "EXPECTED_RUNTIME_LOCK_SHA256", sha(runner.RUNTIME_LOCK)), \
             mock.patch.object(runner.platform, "python_version", return_value="3.11.15"), \
             mock.patch.object(runner.platform, "machine", return_value="arm64"), \
             mock.patch.object(runner.sys, "platform", "darwin"), \
             mock.patch.object(runner.importlib.metadata, "distributions", return_value=[]):
            with self.assertRaisesRegex(runner.ConversionError, "inventory differs"):
                runner._runtime_lock(runner.RUNTIME_LOCK)

    def test_no_clobber_promotion_never_replaces_existing_output(self):
        attempt = self.root / "attempt.wav"
        final = self.root / "output.wav"
        attempt.write_bytes(b"new")
        final.write_bytes(b"original")
        with self.assertRaisesRegex(runner.ConversionError, "refusing to overwrite"):
            runner._promote_no_clobber(attempt, final)
        self.assertEqual(final.read_bytes(), b"original")
        self.assertEqual(attempt.read_bytes(), b"new")

    def test_watermark_disabled_initialization_works_around_upstream_kwarg_bug_without_loader(self):
        class FakeBase:
            def __init__(self, config_path, *, device):
                self.config_path = config_path
                self.device = device
                self.hps = types.SimpleNamespace(_version_="v2")

        class FakeToneColor:
            def __init__(self, *args, **kwargs):
                raise AssertionError("Upstream constructor would forward enable_watermark and fail")

        converter = runner._new_unwatermarked_converter(FakeToneColor, FakeBase, "/model/config.json")
        self.assertEqual(converter.device, "cpu")
        self.assertIsNone(converter.watermark_model)
        self.assertEqual(converter.version, "v2")

    def _run_with_fake(self, converter, output_name, receipt_name):
        args = self._args(output_name=output_name, receipt_name=receipt_name)
        loaded_inputs = {}
        def loader(code, checkpoint, config, threads):
            loaded_inputs.update(code=Path(code), checkpoint=Path(checkpoint), config=Path(config), threads=threads)
            return converter, FakeTorch
        with mock.patch.object(runner, "_runtime_lock", return_value=({}, {"lock_sha256": "test-lock"})), \
             mock.patch.object(runner, "_load_converter", side_effect=loader), \
             mock.patch.object(runner, "_resource_snapshot", return_value={"peak_rss_bytes": 10,
                                                                             "user_cpu_seconds": 1.0,
                                                                             "system_cpu_seconds": 0.2}):
            result = runner.run(args, ledger_type=FakeLedger, soundfile_module=FakeSoundFile)
        converter.loaded_inputs = loaded_inputs
        return args, result, FakeLedger.instances[-1]

    def test_successful_run_records_sources_model_runtime_log_and_output(self):
        converter = FakeConverter()
        args, result, ledger = self._run_with_fake(converter, "success.wav", "success.json")
        self.assertEqual(result, 0)
        self.assertTrue(args.output.is_file())
        receipt = json.loads(args.receipt.read_text(encoding="utf-8"))
        self.assertEqual(receipt["status"], "passed")
        self.assertFalse(receipt["watermark"]["enabled"])
        self.assertFalse(receipt["policy"]["network_upload"])
        self.assertEqual(receipt["result"]["output"]["sample_rate"], 22050)
        self.assertEqual(len(converter.extracted), 2)
        snapshot_dir = REPO / receipt["paths"]["input_snapshot"]
        self.assertTrue(snapshot_dir.is_dir())
        self.assertNotEqual(converter.extracted[0], str(SOURCE))
        self.assertNotEqual(converter.extracted[1], str(REFERENCE))
        self.assertEqual(converter.loaded_inputs["checkpoint"], snapshot_dir / "model/checkpoint.pth")
        self.assertEqual(converter.loaded_inputs["config"], snapshot_dir / "model/config.json")
        manifest = json.loads((snapshot_dir / "snapshot-manifest.json").read_text(encoding="utf-8"))
        roles = {item["role"] for item in manifest["files"]}
        self.assertTrue({"runner", "runtime_lock", "source", "reference", "provenance",
                         "openvoice_source_file", "provenance_evidence"}.issubset(roles))
        self.assertEqual(ledger.stages[-1]["result"]["status"], "passed")
        roles = {role for _, role, _ in ledger.artifacts}
        self.assertIn("local_voice_conversion_receipt", roles)
        self.assertIn("local_voice_conversion_output", roles)

    def test_failed_conversion_retains_partial_file_receipt_and_failed_stage(self):
        args, result, ledger = self._run_with_fake(FakeConverter(fail=True), "failed.wav", "failed.json")
        self.assertEqual(result, 1)
        receipt = json.loads(args.receipt.read_text(encoding="utf-8"))
        self.assertEqual(receipt["status"], "failed")
        partial = REPO / receipt["failure"]["retained_attempt_output"]["path"]
        self.assertTrue(partial.is_file())
        self.assertTrue(args.receipt.with_name("failed.log").is_file())
        self.assertEqual(ledger.stages[-1]["result"]["status"], "failed")
        roles = {role for _, role, _ in ledger.artifacts}
        self.assertIn("failed_local_voice_conversion_partial_output", roles)


if __name__ == "__main__":
    unittest.main()
