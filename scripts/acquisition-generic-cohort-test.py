#!/usr/bin/env python3
"""Synthetic preparation/policy boundaries; no real mail or provider requests."""
import copy
import datetime
import hashlib
import importlib.util
import json
import pathlib
import tempfile
import unittest

spec = importlib.util.spec_from_file_location("generic_cohort", pathlib.Path(__file__).with_name("acquisition-generic-cohort.py"))
cohort = importlib.util.module_from_spec(spec)
spec.loader.exec_module(cohort)
NOW = datetime.datetime(2026, 10, 5, 20, 0, tzinfo=datetime.timezone.utc)
GODADDY = {"provider": "existing_approved_smtp", "account": "godaddy_cpanel", "policy": "opt_in_only"}
MAILFORGE = {"provider": "mailforge", "account": "mailforge", "policy": "verified_address_lawful_use"}
UNKNOWN = {"provider": "unknown", "account": "unknown", "policy": "unknown"}
EMAIL = "controlled@example.test"


class GenericPreparationTest(unittest.TestCase):
    def setUp(self):
        self.directory = tempfile.TemporaryDirectory()
        self.addCleanup(self.directory.cleanup)
        self.private = pathlib.Path(self.directory.name)
        self.artifact = self.private / "controlled-receipt.txt"
        self.artifact.write_text("Synthetic reviewed evidence; not a real business or sender permission.")
        self.row = {"Business_Name": "Synthetic Controlled Business", "Industry": "Unmapped supplied segment", "Contact_Email": EMAIL, "Social_Handle": "@synthetic"}

    def receipt(self, sender, **extra):
        return {**sender, "recipient": EMAIL, "status": "verified", "reviewer": "controlled-test", "checked_at": "2026-10-05T19:00:00Z", "expires_at": "2026-10-06T19:00:00Z", "artifact_path": self.artifact.name, "artifact_sha256": hashlib.sha256(self.artifact.read_bytes()).hexdigest(), **extra}

    def evidence(self, sender, **receipts):
        return {**sender, "receipts": {cohort.contact_hash(EMAIL): receipts}}

    def prepare(self, rows=None, history=None, sender=None, evidence=None):
        return cohort.prepare(rows or [self.row], "a" * 64, history or [], sender or UNKNOWN, evidence or {}, self.private, self.private, NOW)

    def test_missing_enrichment_and_history_do_not_block_preparation(self):
        records, aggregate = self.prepare()
        self.assertEqual(aggregate["counts"]["prepared"], 1)
        self.assertEqual(aggregate["counts"]["history_unknown"], 1)
        self.assertEqual(aggregate["counts"]["enrichment_known"], 0)
        self.assertEqual(records[0]["business_identity"], "supplied_unverified")
        self.assertEqual(records[0]["contact_ownership"], "unknown")
        self.assertFalse(aggregate["claims"]["missing_history_is_clearance"])

    def test_syntax_normalization_dedup_and_invalid_rows_preserved(self):
        rows = [dict(self.row, Contact_Email="  CONTROLLED@EXAMPLE.TEST "), self.row, dict(self.row, Contact_Email="missing-at"), dict(self.row, Contact_Email="two..dots@example.test")]
        records, aggregate = self.prepare(rows)
        self.assertEqual(len(records), 4)
        self.assertEqual([r["preparation_status"] for r in records], ["prepared", "duplicate", "invalid_syntax", "invalid_syntax"])
        self.assertEqual(aggregate["counts"]["syntax_usable"], 2)
        self.assertEqual(aggregate["counts"]["duplicates"], 1)
        self.assertEqual(aggregate["counts"]["prepared"], 1)
        self.assertEqual(records[0]["contact_email"], EMAIL)

    def test_known_suppression_flags_block_preparation(self):
        for flag in ("opt_out", "suppressed", "bounced", "replied", "human_reply", "purchased", "prior_contact"):
            with self.subTest(flag=flag):
                records, aggregate = self.prepare(history=[{"rows": [{"recipient": EMAIL, flag: True}]}])
                self.assertEqual(records[0]["preparation_status"], "suppressed")
                self.assertEqual(aggregate["counts"]["suppressed"], 1)
                self.assertEqual(aggregate["counts"]["prepared"], 0)

    def test_native_hash_keyed_history_uses_current_event_and_count_shape(self):
        flags = [{"negative_consent_rows": 1}, {"incoming_messages": 1}, {"reply_or_purchase_events": 1}, {"sent_rows": 1}, {"event_types": ["payment.fulfillment_started"]}, {"event_types": {"acquisition.reply_received": 1}}, {"status": "bounced"}, {"prior_contact": "previously_contacted"}]
        for entry in flags:
            with self.subTest(entry=entry):
                records, aggregate = self.prepare(history=[{"rows": {cohort.contact_hash(EMAIL): entry}}])
                self.assertEqual(records[0]["preparation_status"], "suppressed")
                self.assertEqual(aggregate["counts"]["history_unknown"], 0)

    def test_zero_events_are_not_suppression_or_clearance(self):
        records, aggregate = self.prepare(history=[{"rows": {cohort.contact_hash(EMAIL): {"sent_rows": 0, "incoming_messages": 0, "event_types": {"email.replied": 0}}}}])
        self.assertEqual(aggregate["counts"]["prepared"], 1)
        self.assertEqual(records[0]["history_state"], "no_known_flags_in_supplied_history")
        self.assertFalse(aggregate["claims"]["missing_history_is_clearance"])

    def test_negative_history_overrides_other_sources_and_policy_receipt(self):
        evidence = self.evidence(GODADDY, opt_in=self.receipt(GODADDY, opt_in=True, basis="written_opt_in"))
        records, aggregate = self.prepare(history=[{"rows": [{"recipient": EMAIL, "granted_consent_rows": 1}]}, {"rows": [{"recipient": EMAIL, "opt_out": True}]}], sender=GODADDY, evidence=evidence)
        self.assertEqual(records[0]["preparation_status"], "suppressed")
        self.assertEqual(aggregate["counts"]["sender_ready"], 0)

    def test_granted_native_consent_is_not_written_opt_in(self):
        records, aggregate = self.prepare(history=[{"rows": [{"recipient": EMAIL, "granted_consent_rows": 1}]}], sender=GODADDY, evidence=self.evidence(GODADDY))
        self.assertEqual(aggregate["counts"]["prepared"], 1)
        self.assertFalse(records[0]["sender"]["ready"])
        self.assertEqual(records[0]["sender"]["deliverability"], "unknown")

    def test_godaddy_requires_exact_current_written_opt_in(self):
        evidence = self.evidence(GODADDY, opt_in=self.receipt(GODADDY, opt_in=True, basis="written_opt_in"))
        records, aggregate = self.prepare(sender=GODADDY, evidence=evidence)
        self.assertEqual(aggregate["counts"]["sender_ready"], 1)
        self.assertFalse(records[0]["sender"]["dispatch_authorized"])
        self.assertEqual(records[0]["sender"]["deliverability"], "unknown")
        for change in ({"opt_in": False}, {"basis": "public_contact"}, {"recipient": "other@example.test"}, {"account": "other_account"}, {"provider": "mailforge"}, {"policy": "other_policy"}, {"status": "unknown"}, {"expires_at": "2026-10-04T20:00:00Z"}, {"checked_at": "2026-10-07T20:00:00Z"}, {"checked_at": "2026-10-05T19:00:00"}, {"artifact_sha256": "b" * 64}, {"artifact_path": "missing.txt"}):
            with self.subTest(change=change):
                bad = copy.deepcopy(evidence)
                bad["receipts"][cohort.contact_hash(EMAIL)]["opt_in"].update(change)
                self.assertEqual(self.prepare(sender=GODADDY, evidence=bad)[1]["counts"]["sender_ready"], 0)

    def test_artifact_outside_private_root_and_tampered_bytes_rejected(self):
        evidence = self.evidence(GODADDY, opt_in=self.receipt(GODADDY, opt_in=True, basis="written_opt_in"))
        evidence["receipts"][cohort.contact_hash(EMAIL)]["opt_in"]["artifact_path"] = "../outside.txt"
        self.assertEqual(self.prepare(sender=GODADDY, evidence=evidence)[1]["counts"]["sender_ready"], 0)
        evidence["receipts"][cohort.contact_hash(EMAIL)]["opt_in"]["artifact_path"] = self.artifact.name
        self.artifact.write_text("different receipt bytes")
        self.assertEqual(self.prepare(sender=GODADDY, evidence=evidence)[1]["counts"]["sender_ready"], 0)

    def test_mailforge_needs_valid_mailbox_and_lawful_use_receipts(self):
        evidence = self.evidence(MAILFORGE, address=self.receipt(MAILFORGE, result="valid"), permitted_use=self.receipt(MAILFORGE, sending_permitted=True, basis="controlled-lawful-use-review"))
        records, aggregate = self.prepare(sender=MAILFORGE, evidence=evidence)
        self.assertEqual(aggregate["counts"]["sender_ready"], 1)
        self.assertIn("inbox_delivery_unknown", records[0]["sender"]["deliverability"])
        for component in ("address", "permitted_use"):
            bad = copy.deepcopy(evidence)
            del bad["receipts"][cohort.contact_hash(EMAIL)][component]
            self.assertEqual(self.prepare(sender=MAILFORGE, evidence=bad)[1]["counts"]["sender_ready"], 0)
        bad = copy.deepcopy(evidence)
        bad["receipts"][cohort.contact_hash(EMAIL)]["address"]["result"] = "published_business_contact"
        self.assertEqual(self.prepare(sender=MAILFORGE, evidence=bad)[1]["counts"]["sender_ready"], 0)

    def test_transport_receipts_cannot_cross_provider_or_account(self):
        evidence = self.evidence(MAILFORGE, address=self.receipt(MAILFORGE, result="valid"), permitted_use=self.receipt(MAILFORGE, sending_permitted=True, basis="controlled-review"))
        self.assertEqual(self.prepare(sender=GODADDY, evidence=evidence)[1]["counts"]["sender_ready"], 0)
        self.assertEqual(self.prepare(sender=dict(MAILFORGE, account="godaddy_cpanel"), evidence=evidence)[1]["counts"]["sender_ready"], 0)
        self.assertEqual(self.prepare(sender=UNKNOWN, evidence=evidence)[1]["counts"]["sender_ready"], 0)

    def test_malformed_receipts_fail_closed(self):
        for payload in (None, [], "not-an-object", {"opt_in": None}, {"opt_in": []}):
            with self.subTest(payload=payload):
                evidence = {**GODADDY, "receipts": {cohort.contact_hash(EMAIL): payload}}
                self.assertEqual(self.prepare(sender=GODADDY, evidence=evidence)[1]["counts"]["sender_ready"], 0)

    def test_no_verified_import_or_dispatch_even_for_policy_ready_recipient(self):
        evidence = self.evidence(GODADDY, opt_in=self.receipt(GODADDY, opt_in=True, basis="written_opt_in"))
        records, aggregate = self.prepare(sender=GODADDY, evidence=evidence)
        for item in (records[0], aggregate):
            self.assertFalse(item["native_verified_business_import"])
            self.assertFalse(item["dispatch_authorized"])
        self.assertFalse(aggregate["claims"]["ownership_verified"])
        self.assertFalse(aggregate["claims"]["inbox_delivery_proven"])
        self.assertFalse(records[0]["sample_plan"]["send_package"])

    def test_rejected_six_never_route_into_proposed_packages(self):
        (self.private / "templates").mkdir()
        for name in ("beauty_editorial", "beauty_service_first", "detailing_precision", "baking_signature", "catering_table_story"):
            (self.private / "templates" / (name + ".html")).write_text("retired synthetic reference")
        for industry in (cohort.BEAUTY_CATEGORY, "Mobile Detailing, Auto Care & Tinting", "Custom Baking, Catering & Private Chefs", "Handcrafted Products, Fashion & Boutiques"):
            with self.subTest(industry=industry):
                records, _ = self.prepare([dict(self.row, Business_Name="Synthetic Hair Cakes Detail", Industry=industry)])
                plan = records[0]["sample_plan"]
                self.assertEqual(plan["template_id"], "general_service_business")
                self.assertEqual(plan["creative_status"], "adaptation_pending")
                self.assertIsNone(plan["confirmed_niche"])
                self.assertIsNone(plan["reference_path"])

    def test_new_beauty_reference_is_pending_review_not_approved_send(self):
        (self.private / "generic-review").mkdir()
        (self.private / "generic-review/beauty-lab.html").write_text("synthetic candidate")
        records, _ = self.prepare([dict(self.row, Industry=cohort.BEAUTY_CATEGORY)])
        plan = records[0]["sample_plan"]
        self.assertEqual(plan["template_id"], "polished_barber_lab")
        self.assertEqual(plan["creative_status"], "owner_review_pending")
        self.assertFalse(plan["owner_approved"])
        self.assertFalse(plan["send_package"])
        self.assertIsNone(plan["confirmed_niche"])

    def test_repeat_preparation_is_deterministic_and_public_aggregate_has_no_contacts(self):
        first = self.prepare()
        self.assertEqual(first, self.prepare())
        public = json.dumps(first[1])
        for private_value in (EMAIL, cohort.contact_hash(EMAIL), self.row["Business_Name"], self.row["Social_Handle"], first[0][0]["source_key"]):
            self.assertNotIn(private_value, public)

    def test_private_writer_preserves_owner_only_permissions(self):
        destination = self.private / "controlled-private.jsonl"
        cohort.shared.private_write(destination, json.dumps(self.row) + "\n")
        self.assertEqual(destination.stat().st_mode & 0o777, 0o600)

    def test_unidentified_history_fails_instead_of_silent_clearance(self):
        for bad in ({"rows": [{"replied": True}]}, {"rows": "bad-shape"}, {"rows": [False]}):
            with self.subTest(history=bad):
                with self.assertRaises(ValueError):
                    self.prepare(history=[bad])


if __name__ == "__main__":
    unittest.main()
