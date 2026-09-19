import importlib.util
import json
import tempfile
import unittest
from pathlib import Path

from fam_video.evidence import Ledger


ROOT = Path(__file__).resolve().parents[4]
SCRIPT_PATH = ROOT / "scripts/famtastic-local-narration.py"
SPEC = importlib.util.spec_from_file_location("famtastic_local_narration", SCRIPT_PATH)
NARRATION = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(NARRATION)


class LocalNarrationCliTests(unittest.TestCase):
    def test_freezes_the_exact_source_and_performance_bytes_used_to_resolve(self):
        with tempfile.TemporaryDirectory(prefix=".test-local-narration-", dir=ROOT) as temporary:
            directory = Path(temporary)
            source = directory / "script.txt"
            source_bytes = b"First thought.\nSecond thought.\n"
            source.write_bytes(source_bytes)
            source_sha256 = NARRATION._sha256_bytes(source_bytes)
            performance_bytes = (
                '{\n  "schema": "famtastic.narration-performance.v1",\n'
                f'  "source_sha256": "{source_sha256}",\n'
                '  "lines": [{"index": 0, "pause_after_seconds": 0.375}, {"index": 1}]\n}\n'
            ).encode()
            performance_path = directory / "performance.json"
            performance_path.write_bytes(performance_bytes)

            bound = NARRATION._read_bound_inputs(source, performance_path, speed=1.06, gap=0.18)
            source.write_text("Changed after binding.\n")
            performance_path.write_text("{}\n")

            frozen_source = directory / "frozen" / "script.txt"
            frozen_performance = directory / "frozen" / "performance.json"
            NARRATION._write_frozen_bytes(frozen_source, bound["source_bytes"])
            NARRATION._write_frozen_bytes(frozen_performance, bound["performance_bytes"])
            self.assertEqual(frozen_source.read_bytes(), source_bytes)
            self.assertEqual(frozen_performance.read_bytes(), performance_bytes)
            self.assertEqual(bound["source_sha256"], source_sha256)
            self.assertEqual(bound["performance_source_sha256"], NARRATION._sha256_bytes(performance_bytes))
            self.assertEqual(bound["resolved"][0]["source_text"], "First thought.")
            self.assertEqual(bound["resolved"][0]["pause_after_seconds"], 0.375)

    def test_line_cues_preserve_indexes_and_report_actual_sample_gap(self):
        rate = 24000
        cues = [
            NARRATION._line_cue(0, "First.", 0, 2400, 7200, rate),
            NARRATION._line_cue(1, "Second.", 9600, 12000, 0, rate),
        ]
        self.assertEqual([cue["index"] for cue in cues], [0, 1])
        self.assertEqual(cues[0]["pause_after_frames"], 7200)
        self.assertEqual(cues[0]["pause_after_seconds"], 0.3)
        self.assertAlmostEqual(cues[1]["start"] - cues[0]["end"], cues[0]["pause_after_seconds"])
        self.assertEqual(cues[1]["pause_after_seconds"], 0)

    def test_failed_stage_retains_and_hash_registers_partial_outputs_without_stale_ledger_hash(self):
        with tempfile.TemporaryDirectory(prefix=".test-local-narration-", dir=ROOT) as temporary:
            output = Path(temporary) / "partial"
            output.mkdir()
            ledger = Ledger(output / "run", ROOT, {"id": output.name, "schema": "test"})
            partial = output / "narration-before-loudnorm.wav"
            cues = output / "cues.json"
            partial.write_bytes(b"partial audio")
            cues.write_text('[{"index":0,"text":"retained"}]\n')
            original = RuntimeError("synthetic failure after partial outputs")
            try:
                with ledger.stage("local-tts", "test-failure", provider="local-kokoro-onnx"):
                    raise original
            except RuntimeError as caught:
                self.assertIs(caught, original)

            errors = NARRATION._record_run_completion(output, ledger, original)
            result = json.loads(ledger.path.read_text())
            receipt = json.loads((output / "failure-receipt.json").read_text())
            retained_paths = {entry["path"] for entry in receipt["retained_files"]}
            artifact_paths = {entry["path"] for entry in result["artifacts"]}
            self.assertEqual(errors, [])
            self.assertEqual(result["completion"]["status"], "failed")
            self.assertEqual(result["stages"][-1]["result"]["status"], "failed")
            self.assertIn(partial.relative_to(ROOT).as_posix(), retained_paths)
            self.assertIn(cues.relative_to(ROOT).as_posix(), retained_paths)
            self.assertNotIn(ledger.path.relative_to(ROOT).as_posix(), retained_paths)
            self.assertNotIn(ledger._lock_path.relative_to(ROOT).as_posix(), retained_paths)
            self.assertIn(partial.relative_to(ROOT).as_posix(), artifact_paths)
            self.assertIn(cues.relative_to(ROOT).as_posix(), artifact_paths)
            self.assertIn((output / "failure-receipt.json").relative_to(ROOT).as_posix(), artifact_paths)

    def test_finalization_error_does_not_replace_original_generation_error(self):
        with tempfile.TemporaryDirectory(prefix=".test-local-narration-", dir=ROOT) as temporary:
            output = Path(temporary) / "partial"
            output.mkdir()
            ledger = Ledger(output / "run", ROOT, {"id": output.name, "schema": "test"})
            original = RuntimeError("preserve this original failure")

            def broken_finalize(*_args, **_kwargs):
                raise OSError("fixture finalization error")

            ledger.finalize = broken_finalize
            errors = NARRATION._record_run_completion(output, ledger, original)
            self.assertTrue(any("ledger finalization" in error for error in errors))
            self.assertEqual(str(original), "preserve this original failure")


if __name__ == "__main__":
    unittest.main()
