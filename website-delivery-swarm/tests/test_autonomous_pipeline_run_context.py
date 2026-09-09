#!/usr/bin/env python3
"""Regression checks for canonical verified-cold Build DNA run injection."""

import importlib.util
import pathlib
import unittest


MODULE = pathlib.Path(__file__).resolve().parents[1] / "autonomous_pipeline.py"
SPEC = importlib.util.spec_from_file_location("autonomous_pipeline", MODULE)
assert SPEC and SPEC.loader
PIPELINE = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(PIPELINE)


class VerifiedColdRunContextTest(unittest.TestCase):
    def delivery(self):
        return {
            "public_preview_delivery_id": 41,
            "build_dna_run": {
                "prospect_id": 12,
                "proof_campaign_id": 33,
                "campaign_id": "pc-fixture-abc123",
                "source_lane": "verified_cold",
                "job_id": "cold-preview-0123456789abcdef0123456789abcdef",
                "callback_event_id": "cold-proof-callback-0123456789abcdef0123456789abcdef",
                "run_started_at": "2026-08-27T01:02:03Z",
            },
        }

    def test_bundle_entry_is_copied_into_run_before_manifest_hashing(self):
        handoff = {
            "schema": "famtastic.verified-cold-proof-handoff.v1",
            "deliveries": [self.delivery()],
        }
        run = PIPELINE.normalize_build_dna_run_context(handoff, 41)
        self.assertEqual("verified_cold", run["source_lane"])
        self.assertEqual(12, run["prospect_id"])
        self.assertEqual(33, run["proof_campaign_id"])
        self.assertEqual("pc-fixture-abc123", run["campaign_id"])
        self.assertEqual("cold-preview-0123456789abcdef0123456789abcdef", run["job_id"])
        self.assertEqual("cold-proof-callback-0123456789abcdef0123456789abcdef", run["callback_event_id"])
        self.assertEqual("2026-08-27T01:02:03Z", run["run_started_at"])
        self.assertEqual("2026-08-27T01:02:03Z", run["started_at"])
        self.assertEqual(41, run["public_preview_delivery_id"])

    def test_verified_cold_rejects_synthetic_or_missing_identity(self):
        invalid = self.delivery()
        invalid["build_dna_run"].pop("callback_event_id")
        with self.assertRaises(PIPELINE.ContractError):
            PIPELINE.normalize_build_dna_run_context(invalid)

    def test_packet_source_manifest_digest_is_order_independent_and_byte_bound(self):
        records = [
            {"role": "selected_preview", "path": "packet-files/reference-previews/direction-a/index.html", "sha256": "a" * 64, "bytes": 128},
            {"role": "source_material", "path": "packet-files/brief.json", "sha256": "b" * 64, "bytes": 64},
        ]
        digest = PIPELINE.artifact_manifest_digest(records)
        self.assertEqual(digest, PIPELINE.artifact_manifest_digest(list(reversed(records))))
        changed = [dict(record) for record in records]
        changed[0]["bytes"] = 129
        self.assertNotEqual(digest, PIPELINE.artifact_manifest_digest(changed))


if __name__ == "__main__":
    unittest.main()
